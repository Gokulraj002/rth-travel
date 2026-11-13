<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Env;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Revalidator;
use App\Core\Uploader;
use App\Core\Validator;
use App\Repositories\PackageRepository;

/** Tour packages — the main content the owner manages. */
final class PackageController extends Controller
{
    private const PER_PAGE = 20;

    private ?PackageRepository $repo = null;

    private function repo(): PackageRepository
    {
        return $this->repo ??= new PackageRepository();
    }

    // ── List ──────────────────────────────────────────────────────────────────
    public function index(): never
    {
        $filters = [
            'q' => mb_substr((string) $this->request->query('q', ''), 0, 100),
            'status' => (string) $this->request->query('status', ''),
            'region' => (string) $this->request->query('region', ''),
        ];
        if (!isset(PackageRepository::STATUSES[$filters['status']])) {
            $filters['status'] = '';
        }
        if (!isset(PackageRepository::REGIONS[$filters['region']])) {
            $filters['region'] = '';
        }
        $page = max(1, (int) $this->request->query('page', 1));
        $result = $this->repo()->paginate($filters, $page, self::PER_PAGE);

        $this->view('admin/packages/index', [
            'title' => 'Packages',
            'pretitle' => 'Website content',
            'filters' => $filters,
            'result' => $result,
            'websiteUrl' => rtrim((string) Env::get('WEBSITE_URL', ''), '/'),
            'pageActions' => '<a class="btn btn-primary" href="' . e(url('/packages/create')) . '">' . icon('plus') . ' New package</a>',
        ]);
    }

    // ── Create / edit ─────────────────────────────────────────────────────────
    public function create(): never
    {
        $this->form(null);
    }

    public function edit(string $id): never
    {
        $package = $this->repo()->find($this->id($id));
        if ($package === null) {
            Response::abort(404);
        }
        $this->form($package);
    }

    /** @param array<string,mixed>|null $package */
    private function form(?array $package): never
    {
        $isEdit = $package !== null;
        $websiteUrl = rtrim((string) Env::get('WEBSITE_URL', ''), '/');
        $actions = '<a class="btn btn-outline-secondary" href="' . e(url('/packages')) . '">Back to packages</a>';
        if ($isEdit && $package['status'] === 'published' && $websiteUrl !== '') {
            $actions = '<a class="btn btn-outline-primary" href="' . e($websiteUrl . '/packages/' . rawurlencode((string) $package['slug']))
                . '" target="_blank" rel="noopener">' . icon('external') . ' View on website</a>' . $actions;
        }

        $this->view('admin/packages/form', [
            'title' => $isEdit ? 'Edit package' : 'New package',
            'pageTitle' => $isEdit ? (string) $package['title'] : 'New package',
            'pretitle' => $isEdit ? 'Edit package' : 'Packages',
            'pageActions' => $actions,
            'package' => $package,
            'destinations' => $this->repo()->destinationOptions(),
            'regions' => PackageRepository::REGIONS,
            'categories' => PackageRepository::CATEGORIES,
            'statuses' => PackageRepository::STATUSES,
        ]);
    }

    // ── Save ──────────────────────────────────────────────────────────────────
    public function store(): never
    {
        [$data, $children, $errors] = $this->validateInput(null);
        if ($errors !== []) {
            $this->back('/packages/create', $errors);
        }
        $id = $this->repo()->save(null, $data, $children);
        Revalidator::ping(['packages']);
        Activity::log('created', 'package', $id, 'Created “' . $data['title'] . '”');
        Flash::success($data['status'] === 'published'
            ? 'Package “' . $data['title'] . '” created and published on the website.'
            : 'Package “' . $data['title'] . '” saved as a draft (not visible on the website yet).');
        $this->dayCountHint($data, $children);
        $this->redirect('/packages/' . $id . '/edit');
    }

    public function update(string $id): never
    {
        $id = $this->id($id);
        if ($this->repo()->findBasic($id) === null) {
            Response::abort(404);
        }
        [$data, $children, $errors] = $this->validateInput($id);
        if ($errors !== []) {
            $this->back('/packages/' . $id . '/edit', $errors);
        }
        $this->repo()->save($id, $data, $children);
        Revalidator::ping(['packages']);
        Activity::log('updated', 'package', $id, 'Updated “' . $data['title'] . '”');
        Flash::success('Changes to “' . $data['title'] . '” saved.' . ($data['status'] === 'published' ? ' The website will update in a few seconds.' : ''));
        $this->dayCountHint($data, $children);
        $this->redirect('/packages/' . $id . '/edit');
    }

    /** Gentle reminder when the itinerary length doesn't match the number of days. */
    private function dayCountHint(array $data, array $children): void
    {
        $count = count($children['itinerary']);
        if ($count > 0 && $count !== (int) $data['days']) {
            Flash::info("Heads-up: the itinerary has {$count} day(s) but the trip is set to {$data['days']} days.");
        }
    }

    /**
     * Validate the whole form.
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,string>}
     */
    private function validateInput(?int $id): array
    {
        $in = $this->request->all();
        $str = static fn (string $k): string => is_string($in[$k] ?? null) ? trim($in[$k]) : '';
        $price = preg_replace('/[₹,\s]/u', '', $str('price_from')) ?? '';

        $v = Validator::make([
            'title' => $str('title'),
            'slug' => mb_strtolower($str('slug')),
            'subtitle' => $str('subtitle'),
            'region' => $str('region'),
            'nights' => $str('nights'),
            'days' => $str('days'),
            'price_from' => $price,
            'image' => $str('image'),
            'status' => $str('status'),
            'featured' => $str('featured'),
        ], [
            'title' => 'required|string|max:160',
            'slug' => 'required|slug|max:120',
            'subtitle' => 'nullable|string|max:255',
            'region' => 'required|in:' . implode(',', array_keys(PackageRepository::REGIONS)),
            'nights' => 'required|int|min:0|max:60',
            'days' => 'required|int|min:1|max:61',
            'price_from' => 'required|int|min:0|max:10000000',
            'image' => 'nullable|string|max:255',
            'status' => 'required|in:' . implode(',', array_keys(PackageRepository::STATUSES)),
            'featured' => 'bool',
        ], [
            'slug' => 'Web address (slug)',
            'price_from' => 'Starting price',
        ]);
        $errors = $v->errors();
        $clean = $v->validated();

        if (!isset($errors['slug']) && $this->repo()->slugExists((string) $clean['slug'], $id)) {
            $errors['slug'] = 'Another package already uses this web address. Please choose a different one.';
        }
        if (!isset($errors['nights']) && !isset($errors['days'])) {
            $n = (int) $clean['nights'];
            $d = (int) $clean['days'];
            if ($d !== $n + 1 && $d !== $n) {
                $errors['days'] = 'Days should normally be nights + 1 (e.g. 5 nights = 6 days).';
            }
        }
        $image = (string) ($clean['image'] ?? '');
        if (!isset($errors['image']) && $image !== ''
            && !preg_match('#^(/(uploads|images)/[A-Za-z0-9/_.\-]+|https?://[^\s"<>]+)$#', $image)) {
            $errors['image'] = 'Please upload the image again.';
        }

        // Trip types (whitelist)
        $cats = $in['categories'] ?? [];
        $cats = is_array($cats) ? array_values(array_unique(array_filter($cats, static fn ($c) => is_string($c) && isset(PackageRepository::CATEGORIES[$c])))) : [];

        // Destinations covered (ordered, must exist)
        $known = array_column($this->repo()->destinationOptions(), 'slug');
        $dests = [];
        foreach (is_array($in['destinations'] ?? null) ? $in['destinations'] : [] as $slug) {
            $slug = is_string($slug) ? trim($slug) : '';
            if ($slug === '') {
                continue;
            }
            if (!in_array($slug, $known, true)) {
                $errors['destinations'] = 'One of the chosen destinations no longer exists. Please pick again.';
                continue;
            }
            if (!in_array($slug, $dests, true)) {
                $dests[] = $slug;
            }
        }
        if (count($dests) > 30) {
            $errors['destinations'] = 'Please choose at most 30 destinations.';
        }

        // Day-by-day itinerary
        $itinerary = [];
        foreach (is_array($in['itinerary'] ?? null) ? $in['itinerary'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = trim((string) (is_string($row['title'] ?? null) ? $row['title'] : ''));
            $desc = trim((string) (is_string($row['description'] ?? null) ? $row['description'] : ''));
            $stay = trim((string) (is_string($row['stay'] ?? null) ? $row['stay'] : ''));
            if ($title === '' && $desc === '' && $stay === '') {
                continue;
            }
            $dayNo = count($itinerary) + 1;
            if ($title === '') {
                $errors['itinerary'] ??= "Day {$dayNo} needs a title.";
            } elseif (mb_strlen($title) > 200) {
                $errors['itinerary'] ??= "The title of day {$dayNo} is too long (max 200 characters).";
            }
            if (mb_strlen($desc) > 5000) {
                $errors['itinerary'] ??= "The description of day {$dayNo} is too long (max 5000 characters).";
            }
            if (mb_strlen($stay) > 120) {
                $errors['itinerary'] ??= "The overnight stay of day {$dayNo} is too long (max 120 characters).";
            }
            $itinerary[] = ['title' => $title, 'description' => $desc, 'stay' => $stay !== '' ? $stay : null];
        }
        if (count($itinerary) > 61) {
            $errors['itinerary'] = 'The itinerary can have at most 61 days.';
        }

        // Publishing rules
        if (($clean['status'] ?? '') === 'published') {
            if ($image === '' && !isset($errors['image'])) {
                $errors['image'] = 'Add a main photo before publishing (or save as draft).';
            }
            if ($dests === [] && !isset($errors['destinations'])) {
                $errors['destinations'] = 'Add at least one destination before publishing.';
            }
            if ($itinerary === [] && !isset($errors['itinerary'])) {
                $errors['itinerary'] = 'Add at least one day to the itinerary before publishing.';
            }
        }

        $data = [];
        $children = [];
        if ($errors === []) {
            $data = [
                'slug' => (string) $clean['slug'],
                'title' => (string) $clean['title'],
                'subtitle' => (string) ($clean['subtitle'] ?? ''),
                'region' => (string) $clean['region'],
                'nights' => (int) $clean['nights'],
                'days' => (int) $clean['days'],
                'price_from' => (int) $clean['price_from'],
                'image' => $image,
                'featured' => (int) ($clean['featured'] ?? 0),
                'status' => (string) $clean['status'],
            ];
            $children = [
                'categories' => $cats,
                'destinations' => $dests,
                'highlights' => $this->listInput('highlights'),
                'inclusions' => $this->listInput('inclusions'),
                'exclusions' => $this->listInput('exclusions'),
                'itinerary' => $itinerary,
            ];
        }
        return [$data, $children, $errors];
    }

    // ── Row actions ───────────────────────────────────────────────────────────
    public function duplicate(string $id): never
    {
        $id = $this->id($id);
        $orig = $this->repo()->findBasic($id);
        if ($orig === null) {
            Response::abort(404);
        }
        $newId = $this->repo()->duplicate($id);
        Activity::log('created', 'package', $newId, 'Duplicated “' . $orig['title'] . '”');
        Flash::success('Copy created as a draft. Change the title and web address, then publish when ready.');
        $this->redirect('/packages/' . $newId . '/edit');
    }

    public function toggle(string $id): never
    {
        $id = $this->id($id);
        $package = $this->repo()->find($id);
        if ($package === null) {
            Response::abort(404);
        }
        $return = $this->returnPath();
        if ($package['status'] === 'published') {
            $this->repo()->setStatus($id, 'draft');
            Revalidator::ping(['packages']);
            Activity::log('unpublished', 'package', $id, 'Unpublished “' . $package['title'] . '”');
            Flash::success('“' . $package['title'] . '” is now a draft and hidden from the website.');
            $this->redirect($return);
        }

        $missing = [];
        if ((string) $package['image'] === '') {
            $missing[] = 'a main photo';
        }
        if ($package['destinations'] === []) {
            $missing[] = 'at least one destination';
        }
        if ($package['itinerary'] === []) {
            $missing[] = 'at least one itinerary day';
        }
        if ($missing !== []) {
            Flash::error('“' . $package['title'] . '” can’t be published yet — it needs ' . implode(', ', $missing) . '.');
            $this->redirect('/packages/' . $id . '/edit');
        }
        $this->repo()->setStatus($id, 'published');
        Revalidator::ping(['packages']);
        Activity::log('published', 'package', $id, 'Published “' . $package['title'] . '”');
        Flash::success('“' . $package['title'] . '” is now live on the website.');
        $this->redirect($return);
    }

    public function destroy(string $id): never
    {
        $id = $this->id($id);
        $package = $this->repo()->findBasic($id);
        if ($package === null) {
            Response::abort(404);
        }
        $this->repo()->delete($id);

        $image = (string) $package['image'];
        if (str_starts_with($image, '/uploads/') && !$this->repo()->imageInUse($image)) {
            Uploader::delete($image);
        }
        Revalidator::ping(['packages']);
        Activity::log('deleted', 'package', $id, 'Deleted “' . $package['title'] . '”');
        Flash::success('Package “' . $package['title'] . '” deleted.');
        $this->redirect('/packages');
    }

    /** JSON {ids:[…]} from the drag-to-reorder table. */
    public function reorder(): never
    {
        $raw = $this->request->input('ids', []);
        if (!is_array($raw) || $raw === [] || count($raw) > 1000) {
            $this->json(['error' => 'Invalid order.'], 422);
        }
        $ids = [];
        foreach ($raw as $v) {
            $n = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($n === false) {
                $this->json(['error' => 'Invalid order.'], 422);
            }
            $ids[] = $n;
        }
        if (count(array_unique($ids)) !== count($ids)) {
            $this->json(['error' => 'Invalid order.'], 422);
        }
        $this->repo()->reorder($ids);
        Revalidator::ping(['packages']);
        Activity::log('reordered', 'package', null, 'Changed the order of packages');
        $this->json(['ok' => true]);
    }

    /** Safe redirect target from a hidden "return" field (only package pages). */
    private function returnPath(): string
    {
        $r = (string) $this->request->input('return', '');
        return preg_match('#^/packages(/\d+/edit)?(\?[A-Za-z0-9=&%_.+\-]*)?$#', $r) ? $r : '/packages';
    }
}
