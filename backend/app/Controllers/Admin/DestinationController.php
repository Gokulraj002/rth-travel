<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Revalidator;
use App\Core\Validator;
use App\Repositories\DestinationRepository;

final class DestinationController extends Controller
{
    private const SCALARS = [
        'name', 'display_name', 'slug', 'region', 'tagline', 'description', 'lat', 'lng', 'altitude_m',
        'best_time', 'distance_note', 'image', 'card_image', 'credit', 'status',
    ];

    public function index(): never
    {
        $q = mb_substr((string) $this->request->query('q', ''), 0, 100);
        $status = (string) $this->request->query('status', '');
        $page = (int) filter_var($this->request->query('page', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
        $result = DestinationRepository::paginate($q, $status, $page);

        $this->view('admin/destinations/index', [
            'title' => 'Destinations',
            'pretitle' => 'Website content',
            'q' => $q,
            'status' => $status,
            'result' => $result,
            'regions' => DestinationRepository::REGIONS,
            'pageActions' => '<a href="' . e(url('/destinations/create')) . '" class="btn btn-primary">' . icon('plus') . ' Add destination</a>',
        ]);
    }

    public function create(): never
    {
        $this->form(null, [], [], []);
    }

    public function edit(string $id): never
    {
        $destination = $this->findOr404($this->id($id));
        $items = DestinationRepository::items((int) $destination['id']);
        $this->form($destination, $items['highlights'], $items['things'], DestinationRepository::gallery((int) $destination['id']));
    }

    public function store(): never
    {
        [$data, $highlights, $things, $gallery, $errors] = $this->validateInput(null);
        if ($errors !== []) {
            $this->back('/destinations/create', $errors);
        }

        $id = Database::transaction(static function () use ($data, $highlights, $things, $gallery): int {
            $id = DestinationRepository::create($data);
            DestinationRepository::replaceChildren($id, $highlights, $things, $gallery);
            return $id;
        });

        Revalidator::ping(['destinations', 'packages']);
        Activity::log('created', 'destination', $id, 'Added destination “' . $data['name'] . '”');
        Flash::success('Destination “' . $data['name'] . '” has been added.');
        $this->redirect('/destinations');
    }

    public function update(string $id): never
    {
        $destination = $this->findOr404($this->id($id));
        $destId = (int) $destination['id'];
        [$data, $highlights, $things, $gallery, $errors] = $this->validateInput($destId);
        if ($errors !== []) {
            $this->back('/destinations/' . $destId . '/edit', $errors);
        }

        $oldSlug = (string) $destination['slug'];
        Database::transaction(static function () use ($destId, $data, $highlights, $things, $gallery, $oldSlug): void {
            DestinationRepository::update($destId, $data);
            if ($data['slug'] !== $oldSlug) {
                DestinationRepository::renameSlugInPackages($oldSlug, $data['slug']);
            }
            DestinationRepository::replaceChildren($destId, $highlights, $things, $gallery);
        });

        Revalidator::ping(['destinations', 'packages']);
        Activity::log('updated', 'destination', $destId, 'Updated destination “' . $data['name'] . '”');
        Flash::success('Destination “' . $data['name'] . '” has been saved.');
        $this->redirect('/destinations');
    }

    public function destroy(string $id): never
    {
        $destination = $this->findOr404($this->id($id));
        $name = (string) $destination['name'];

        $packages = DestinationRepository::packagesUsing((string) $destination['slug']);
        if ($packages !== []) {
            $titles = array_map(static fn (array $p): string => '“' . $p['title'] . '”', $packages);
            Flash::error(sprintf(
                '“%s” can’t be deleted because %s still include%s it: %s. Remove it from %s first — or switch it to Draft to hide it from the website.',
                $name,
                count($packages) === 1 ? 'this package' : 'these packages',
                count($packages) === 1 ? 's' : '',
                implode(', ', $titles),
                count($packages) === 1 ? 'that package' : 'those packages',
            ));
            $this->redirect('/destinations');
        }

        Database::transaction(static fn () => DestinationRepository::delete((int) $destination['id']));
        Revalidator::ping(['destinations', 'packages']);
        Activity::log('deleted', 'destination', (int) $destination['id'], 'Deleted destination “' . $name . '”');
        Flash::success('Destination “' . $name . '” has been deleted.');
        $this->redirect('/destinations');
    }

    /** Publish ⇄ draft. */
    public function toggle(string $id): never
    {
        $destination = $this->findOr404($this->id($id));
        $name = (string) $destination['name'];
        $next = $destination['status'] === 'published' ? 'draft' : 'published';

        if ($next === 'published' && trim((string) $destination['image']) === '') {
            Flash::error('Add a main photo to “' . $name . '” before publishing it.');
            $this->redirect('/destinations/' . (int) $destination['id'] . '/edit');
        }

        DestinationRepository::setStatus((int) $destination['id'], $next);
        Revalidator::ping(['destinations', 'packages']);
        Activity::log($next === 'published' ? 'published' : 'unpublished', 'destination', (int) $destination['id'],
            ($next === 'published' ? 'Published' : 'Unpublished') . ' destination “' . $name . '”');
        Flash::success($next === 'published' ? '“' . $name . '” is now live on the website.' : '“' . $name . '” is now a draft (hidden from the website).');
        $this->redirect('/destinations');
    }

    /** POST JSON {ids:[…]} from the drag-and-drop table. */
    public function reorder(): never
    {
        $ids = $this->idsFromJson();
        $n = Database::transaction(static fn (): int => DestinationRepository::reorder($ids));
        if ($n > 0) {
            Revalidator::ping(['destinations', 'packages']);
            Activity::log('reordered', 'destination', null, 'Changed the order of destinations');
        }
        $this->json(['ok' => true, 'updated' => $n]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $row = DestinationRepository::find($id);
        if ($row === null) {
            Response::abort(404);
        }
        return $row;
    }

    /**
     * @param array<string,mixed>|null $destination
     * @param list<string> $highlights @param list<string> $things @param list<string> $gallery
     */
    private function form(?array $destination, array $highlights, array $things, array $gallery): never
    {
        $isEdit = $destination !== null;
        $this->view('admin/destinations/form', [
            'title' => $isEdit ? 'Edit destination' : 'Add destination',
            'pageTitle' => $isEdit ? 'Edit “' . $destination['name'] . '”' : 'Add a destination',
            'pretitle' => 'Destinations',
            'destination' => $destination,
            'highlights' => $this->oldList('highlights', $highlights),
            'things' => $this->oldList('things', $things),
            'gallery' => $this->oldList('gallery', $gallery),
            'regions' => DestinationRepository::REGIONS,
        ]);
    }

    /**
     * After a failed submit, repeaters are re-filled from the submitted rows.
     * @param list<string> $fallback @return list<string>
     */
    private function oldList(string $key, array $fallback): array
    {
        $old = old($key, null);
        if (!is_array($old)) {
            return $fallback;
        }
        return array_values(array_filter(array_map(
            static fn ($v): string => is_string($v) ? $v : '',
            $old,
        ), static fn (string $v): bool => trim($v) !== ''));
    }

    /**
     * @return array{0:array<string,mixed>,1:list<string>,2:list<string>,3:list<string>,4:array<string,string>}
     */
    private function validateInput(?int $exceptId): array
    {
        $in = [];
        foreach (self::SCALARS as $k) {
            $v = $this->request->input($k, '');
            $in[$k] = is_string($v) || is_int($v) || is_float($v) ? trim((string) $v) : '';
        }
        if ($in['display_name'] === '' && $in['name'] !== '') {
            $in['display_name'] = mb_strtoupper($in['name']);
        }
        $in['slug'] = strtolower($in['slug']);

        $v = Validator::make($in, [
            'name' => 'required|string|max:120',
            'display_name' => 'required|string|max:120',
            'slug' => 'required|slug|max:80',
            'region' => 'required|in:' . implode(',', array_keys(DestinationRepository::REGIONS)),
            'tagline' => 'nullable|string|max:200',
            'description' => 'required|string|max:5000',
            'lat' => 'required|numeric|min:-90|max:90',
            'lng' => 'required|numeric|min:-180|max:180',
            'altitude_m' => 'nullable|int|min:-500|max:9000',
            'best_time' => 'nullable|string|max:200',
            'distance_note' => 'nullable|string|max:200',
            'image' => 'nullable|string|max:255',
            'card_image' => 'nullable|string|max:255',
            'credit' => 'nullable|string|max:120',
            'status' => 'required|in:published,draft',
        ], [
            'display_name' => 'Big display name',
            'slug' => 'Web address (slug)',
            'lat' => 'Latitude',
            'lng' => 'Longitude',
            'altitude_m' => 'Altitude',
            'best_time' => 'Best time to visit',
            'distance_note' => 'Distance note',
            'image' => 'Main photo',
            'card_image' => 'Card photo',
        ]);
        $errors = $v->errors();
        $clean = $v->validated();

        if (!isset($errors['slug']) && DestinationRepository::slugExists($in['slug'], $exceptId)) {
            $errors['slug'] = 'Another destination already uses this web address. Please choose a different one.';
        }
        foreach (['image', 'card_image'] as $f) {
            if (!isset($errors[$f]) && !self::validImagePath($in[$f])) {
                $errors[$f] = 'Please upload this photo again.';
            }
        }
        if (($clean['status'] ?? '') === 'published' && $in['image'] === '' && !isset($errors['image'])) {
            $errors['image'] = 'A main photo is required to publish. Upload one, or save as Draft for now.';
        }

        $highlights = $this->listInput('highlights', 30, 300);
        $things = $this->listInput('things', 30, 300);
        $gallery = $this->listInput('gallery', 24, 255);
        foreach ($gallery as $path) {
            if (!self::validImagePath($path)) {
                $errors['gallery'] = 'One of the gallery photos is not valid — please remove it and upload it again.';
                break;
            }
        }

        $data = [];
        if ($errors === []) {
            $data = [
                'slug' => $clean['slug'],
                'name' => $clean['name'],
                'display_name' => $clean['display_name'],
                'region' => $clean['region'],
                'tagline' => (string) ($clean['tagline'] ?? ''),
                'description' => $clean['description'],
                'lat' => round((float) $clean['lat'], 6),
                'lng' => round((float) $clean['lng'], 6),
                'altitude_m' => (int) ($clean['altitude_m'] ?? 0),
                'best_time' => (string) ($clean['best_time'] ?? ''),
                'distance_note' => (string) ($clean['distance_note'] ?? ''),
                'image' => (string) ($clean['image'] ?? ''),
                'card_image' => ($clean['card_image'] ?? '') !== '' ? $clean['card_image'] : null,
                'credit' => (string) ($clean['credit'] ?? ''),
                'status' => $clean['status'],
            ];
        }
        return [$data, $highlights, $things, $gallery, $errors];
    }

    /** Only website images (/images/…) or admin uploads (/uploads/…). Empty is allowed. */
    private static function validImagePath(string $path): bool
    {
        return $path === ''
            || (preg_match('#^/(images|uploads)/[A-Za-z0-9._/-]+$#', $path) === 1 && !str_contains($path, '..'));
    }

    /** @return list<int> */
    private function idsFromJson(): array
    {
        $raw = $this->request->input('ids', []);
        if (!is_array($raw)) {
            $this->json(['error' => 'Invalid order.'], 422);
        }
        $ids = [];
        foreach (array_slice($raw, 0, 500) as $v) {
            $id = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                $ids[] = $id;
            }
        }
        return $ids;
    }
}
