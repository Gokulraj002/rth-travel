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
use App\Repositories\TestimonialRepository;

/** Guest reviews ("Reviews" in the sidebar). */
final class TestimonialController extends Controller
{
    public function index(): never
    {
        $q = mb_substr((string) $this->request->query('q', ''), 0, 100);
        $status = (string) $this->request->query('status', '');
        $page = (int) filter_var($this->request->query('page', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);

        $this->view('admin/testimonials/index', [
            'title' => 'Reviews',
            'pageTitle' => 'Guest reviews',
            'pretitle' => 'Website content',
            'q' => $q,
            'status' => $status,
            'result' => TestimonialRepository::paginate($q, $status, $page),
            'pageActions' => '<a href="' . e(url('/testimonials/create')) . '" class="btn btn-primary">' . icon('plus') . ' Add review</a>',
        ]);
    }

    public function create(): never
    {
        $this->form(null);
    }

    public function edit(string $id): never
    {
        $this->form($this->findOr404($this->id($id)));
    }

    public function store(): never
    {
        [$data, $errors] = $this->validateInput();
        if ($errors !== []) {
            $this->back('/testimonials/create', $errors);
        }
        $id = TestimonialRepository::create($data);
        Revalidator::ping(['testimonials']);
        Activity::log('created', 'testimonial', $id, 'Added a review from ' . $data['name']);
        Flash::success('The review from ' . $data['name'] . ' has been added.');
        $this->redirect('/testimonials');
    }

    public function update(string $id): never
    {
        $row = $this->findOr404($this->id($id));
        [$data, $errors] = $this->validateInput();
        if ($errors !== []) {
            $this->back('/testimonials/' . (int) $row['id'] . '/edit', $errors);
        }
        TestimonialRepository::update((int) $row['id'], $data);
        Revalidator::ping(['testimonials']);
        Activity::log('updated', 'testimonial', (int) $row['id'], 'Updated the review from ' . $data['name']);
        Flash::success('The review from ' . $data['name'] . ' has been saved.');
        $this->redirect('/testimonials');
    }

    public function destroy(string $id): never
    {
        $row = $this->findOr404($this->id($id));
        TestimonialRepository::delete((int) $row['id']);
        Revalidator::ping(['testimonials']);
        Activity::log('deleted', 'testimonial', (int) $row['id'], 'Deleted the review from ' . $row['name']);
        Flash::success('The review from ' . $row['name'] . ' has been deleted.');
        $this->redirect('/testimonials');
    }

    /** POST JSON {ids:[…]} from the drag-and-drop table. */
    public function reorder(): never
    {
        $raw = $this->request->input('ids', []);
        if (!is_array($raw)) {
            $this->json(['error' => 'Invalid order.'], 422);
        }
        $ids = array_values(array_filter(array_map(
            static fn ($v) => filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]),
            array_slice($raw, 0, 500),
        ), static fn ($v) => $v !== false));
        $n = Database::transaction(static fn (): int => TestimonialRepository::reorder($ids));
        if ($n > 0) {
            Revalidator::ping(['testimonials']);
            Activity::log('reordered', 'testimonial', null, 'Changed the order of reviews');
        }
        $this->json(['ok' => true, 'updated' => $n]);
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $row = TestimonialRepository::find($id);
        if ($row === null) {
            Response::abort(404);
        }
        return $row;
    }

    /** @param array<string,mixed>|null $row */
    private function form(?array $row): never
    {
        $this->view('admin/testimonials/form', [
            'title' => $row ? 'Edit review' : 'Add review',
            'pageTitle' => $row ? 'Edit review from ' . $row['name'] : 'Add a guest review',
            'pretitle' => 'Reviews',
            'review' => $row,
        ]);
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} */
    private function validateInput(): array
    {
        $in = [];
        foreach (['name', 'from_city', 'trip', 'quote', 'rating', 'status'] as $k) {
            $v = $this->request->input($k, '');
            $in[$k] = is_string($v) || is_int($v) ? trim((string) $v) : '';
        }
        $v = Validator::make($in, [
            'name' => 'required|string|max:120',
            'from_city' => 'nullable|string|max:120',
            'trip' => 'nullable|string|max:160',
            'quote' => 'required|string|max:600',
            'rating' => 'required|int|min:1|max:5',
            'status' => 'required|in:published,draft',
        ], [
            'name' => 'Guest name',
            'from_city' => 'City',
            'trip' => 'Trip',
            'quote' => 'Review',
        ]);
        if ($v->fails()) {
            return [[], $v->errors()];
        }
        $c = $v->validated();
        return [[
            'name' => $c['name'],
            'from_city' => (string) ($c['from_city'] ?? ''),
            'trip' => (string) ($c['trip'] ?? ''),
            'quote' => $c['quote'],
            'rating' => (int) $c['rating'],
            'status' => $c['status'],
        ], []];
    }
}
