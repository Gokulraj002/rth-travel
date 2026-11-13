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
use App\Repositories\FaqRepository;

/** Frequently asked questions shown on the website. */
final class FaqController extends Controller
{
    public function index(): never
    {
        $q = mb_substr((string) $this->request->query('q', ''), 0, 100);
        $status = (string) $this->request->query('status', '');
        $page = (int) filter_var($this->request->query('page', '1'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);

        $this->view('admin/faqs/index', [
            'title' => 'FAQs',
            'pageTitle' => 'Frequently asked questions',
            'pretitle' => 'Website content',
            'q' => $q,
            'status' => $status,
            'result' => FaqRepository::paginate($q, $status, $page),
            'pageActions' => '<a href="' . e(url('/faqs/create')) . '" class="btn btn-primary">' . icon('plus') . ' Add question</a>',
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
            $this->back('/faqs/create', $errors);
        }
        $id = FaqRepository::create($data);
        Revalidator::ping(['faqs']);
        Activity::log('created', 'faq', $id, 'Added FAQ “' . str_limit($data['question'], 120) . '”');
        Flash::success('The question has been added.');
        $this->redirect('/faqs');
    }

    public function update(string $id): never
    {
        $row = $this->findOr404($this->id($id));
        [$data, $errors] = $this->validateInput();
        if ($errors !== []) {
            $this->back('/faqs/' . (int) $row['id'] . '/edit', $errors);
        }
        FaqRepository::update((int) $row['id'], $data);
        Revalidator::ping(['faqs']);
        Activity::log('updated', 'faq', (int) $row['id'], 'Updated FAQ “' . str_limit($data['question'], 120) . '”');
        Flash::success('The question has been saved.');
        $this->redirect('/faqs');
    }

    public function destroy(string $id): never
    {
        $row = $this->findOr404($this->id($id));
        FaqRepository::delete((int) $row['id']);
        Revalidator::ping(['faqs']);
        Activity::log('deleted', 'faq', (int) $row['id'], 'Deleted FAQ “' . str_limit((string) $row['question'], 120) . '”');
        Flash::success('The question has been deleted.');
        $this->redirect('/faqs');
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
        $n = Database::transaction(static fn (): int => FaqRepository::reorder($ids));
        if ($n > 0) {
            Revalidator::ping(['faqs']);
            Activity::log('reordered', 'faq', null, 'Changed the order of FAQs');
        }
        $this->json(['ok' => true, 'updated' => $n]);
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $row = FaqRepository::find($id);
        if ($row === null) {
            Response::abort(404);
        }
        return $row;
    }

    /** @param array<string,mixed>|null $row */
    private function form(?array $row): never
    {
        $this->view('admin/faqs/form', [
            'title' => $row ? 'Edit FAQ' : 'Add FAQ',
            'pageTitle' => $row ? 'Edit question' : 'Add a question',
            'pretitle' => 'FAQs',
            'faq' => $row,
        ]);
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} */
    private function validateInput(): array
    {
        $in = [];
        foreach (['question', 'answer', 'status'] as $k) {
            $v = $this->request->input($k, '');
            $in[$k] = is_string($v) ? trim($v) : '';
        }
        $v = Validator::make($in, [
            'question' => 'required|string|max:300',
            'answer' => 'required|string|max:5000',
            'status' => 'required|in:published,draft',
        ]);
        if ($v->fails()) {
            return [[], $v->errors()];
        }
        $c = $v->validated();
        return [['question' => $c['question'], 'answer' => $c['answer'], 'status' => $c['status']], []];
    }
}
