<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\EnquiryRepository;
use App\Repositories\TaskRepository as Tasks;

/** Follow-ups / reminders (feature "crm", permission "enquiries"). */
final class TaskController extends Controller
{
    public function index(): never
    {
        $tab = (string) $this->request->query('tab', '');
        $filters = $this->filters();
        $counts = Tasks::counts($filters);
        if (!isset(Tasks::TABS[$tab])) {
            // Land on the most urgent non-empty tab.
            $tab = $counts['overdue'] > 0 ? 'overdue' : ($counts['today'] > 0 || $counts['upcoming'] === 0 ? 'today' : 'upcoming');
        }
        $page = filter_var($this->request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

        $this->view('admin/tasks/index', [
            'title' => 'Follow-ups',
            'pretitle' => 'Sales',
            'tab' => $tab,
            'filters' => $filters,
            'counts' => $counts,
            'result' => Tasks::paginate($tab, $filters, $page),
            'assignees' => EnquiryRepository::assignees(),
            'pageActions' => '<a href="' . e(url('/tasks/create')) . '" class="btn btn-primary">' . icon('plus') . ' New follow-up</a>',
        ]);
    }

    public function create(): never
    {
        $related = (string) $this->request->query('related', '');
        $type = (string) $this->request->query('related_type', '');
        $rid = (string) $this->request->query('related_id', '');
        if ($related === '' && $type !== '' && ctype_digit($rid)) {
            $related = $type . ':' . $rid;
        }
        [$rType, $rId] = $this->parseRelated($related);
        $title = '';
        if ($rType === 'enquiry' && ($enq = EnquiryRepository::find((int) $rId))) {
            $title = 'Follow up with ' . $enq['name'];
        }

        $this->view('admin/tasks/form', [
            'title' => 'New follow-up',
            'pretitle' => 'Follow-ups',
            'task' => null,
            'defaults' => [
                'title' => $title,
                'related' => $rType !== 'none' ? $rType . ':' . $rId : '',
                'due_at' => date('Y-m-d\TH:i', strtotime('tomorrow 11:00')),
                'assigned_to' => (string) Auth::id(),
            ],
            'relatedOptions' => Tasks::relatedOptions(),
            'assignees' => EnquiryRepository::assignees(),
            'back' => $this->backUrl($rType, $rId),
        ]);
    }

    public function store(): never
    {
        $data = $this->validated('/tasks/create');
        $id = Tasks::create($data);

        // A follow-up created for an enquiry also becomes the lead's "next follow-up" when it is sooner.
        if ($data['related_type'] === 'enquiry') {
            $enq = EnquiryRepository::find((int) $data['related_id']);
            if ($enq && ($enq['next_follow_up_at'] === null || strtotime((string) $enq['next_follow_up_at']) > strtotime($data['due_at'])
                || strtotime((string) $enq['next_follow_up_at']) < time())) {
                EnquiryRepository::setFollowUp((int) $enq['id'], $data['due_at']);
            }
        }

        Activity::log('created', 'task', $id, sprintf('Added follow-up “%s”', $data['title']));
        Flash::success('Follow-up added for ' . nice_date($data['due_at']) . '.');
        $this->redirect($this->afterSave($data));
    }

    public function edit(string $id): never
    {
        $task = $this->findOr404($id);
        $this->view('admin/tasks/form', [
            'title' => 'Edit follow-up',
            'pretitle' => 'Follow-ups',
            'task' => $task,
            'defaults' => [
                'title' => $task['title'],
                'notes' => $task['notes'],
                'related' => $task['related_type'] !== 'none' && $task['related_id'] ? $task['related_type'] . ':' . $task['related_id'] : '',
                'due_at' => date('Y-m-d\TH:i', (int) strtotime((string) $task['due_at'])),
                'assigned_to' => (string) ($task['assigned_to'] ?? ''),
            ],
            'relatedOptions' => Tasks::relatedOptions(),
            'assignees' => EnquiryRepository::assignees(),
            'back' => '/tasks',
        ]);
    }

    public function update(string $id): never
    {
        $task = $this->findOr404($id);
        $data = $this->validated('/tasks/' . $task['id'] . '/edit');
        Tasks::update((int) $task['id'], $data);
        if ($data['related_type'] === 'enquiry') {
            $this->syncEnquiry((int) $data['related_id'], (string) $task['due_at'], $data['due_at']);
        }
        Activity::log('updated', 'task', (int) $task['id'], sprintf('Updated follow-up “%s”', $data['title']));
        Flash::success('Follow-up saved.');
        $this->redirect('/tasks');
    }

    public function done(string $id): never
    {
        $task = $this->findOr404($id);
        Tasks::markDone((int) $task['id']);
        if ($task['related_type'] === 'enquiry' && $task['related_id']) {
            $this->syncEnquiry((int) $task['related_id'], (string) $task['due_at'], null);
        }
        Activity::log('updated', 'task', (int) $task['id'], sprintf('Completed follow-up “%s”', $task['title']));
        Flash::success('Done! “' . $task['title'] . '” is marked complete.');
        $this->redirectBack('/tasks');
    }

    public function reopen(string $id): never
    {
        $task = $this->findOr404($id);
        Tasks::reopen((int) $task['id']);
        Activity::log('updated', 'task', (int) $task['id'], sprintf('Reopened follow-up “%s”', $task['title']));
        Flash::success('Follow-up reopened.');
        $this->redirectBack('/tasks?tab=done');
    }

    public function destroy(string $id): never
    {
        $task = $this->findOr404($id);
        Tasks::delete((int) $task['id']);
        if ($task['related_type'] === 'enquiry' && $task['related_id'] && $task['done_at'] === null) {
            $this->syncEnquiry((int) $task['related_id'], (string) $task['due_at'], null);
        }
        Activity::log('deleted', 'task', (int) $task['id'], sprintf('Deleted follow-up “%s”', $task['title']));
        Flash::success('Follow-up deleted.');
        $this->redirectBack('/tasks');
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /**
     * When an enquiry's follow-up task moves/completes, keep enquiries.next_follow_up_at pointing at
     * the earliest open follow-up (only if it was tracking this task's old date).
     */
    private function syncEnquiry(int $enquiryId, string $oldDue, ?string $newDue): void
    {
        $enq = EnquiryRepository::find($enquiryId);
        if (!$enq) {
            return;
        }
        $current = $enq['next_follow_up_at'];
        $tracksThis = $current !== null && date('Y-m-d H:i', (int) strtotime((string) $current)) === date('Y-m-d H:i', (int) strtotime($oldDue));
        if ($tracksThis || $current === null) {
            EnquiryRepository::setFollowUp($enquiryId, Tasks::nextOpenForEnquiry($enquiryId));
        }
    }

    /** @return array{q:string,assigned:string} */
    private function filters(): array
    {
        $q = $this->request->query('q', '');
        $assigned = $this->request->query('assigned', '');
        return [
            'q' => is_string($q) ? mb_substr($q, 0, 100) : '',
            'assigned' => is_string($assigned) && ($assigned === 'me' || $assigned === 'none' || ctype_digit($assigned)) ? $assigned : '',
        ];
    }

    /** @return array<string,mixed> */
    private function validated(string $backPath): array
    {
        $in = $this->request->all();
        $v = Validator::make($in, [
            'title' => 'required|string|max:200',
            'notes' => 'nullable|string|max:2000',
            'due_at' => 'required|string|max:20',
            'assigned_to' => 'nullable|int|min:1',
            'related' => 'nullable|string|max:40',
        ], ['due_at' => 'Due date & time', 'assigned_to' => 'Assigned to', 'related' => 'Related to']);
        $errors = $v->errors();
        $data = $v->validated();

        $due = null;
        if (!isset($errors['due_at'])) {
            $due = $this->parseDateTime((string) $data['due_at']);
            if ($due === null) {
                $errors['due_at'] = 'Please pick a valid date and time.';
            }
        }
        if (!isset($errors['assigned_to']) && $data['assigned_to'] !== null
            && !in_array((int) $data['assigned_to'], array_map(static fn ($a) => (int) $a['id'], EnquiryRepository::assignees()), true)) {
            $errors['assigned_to'] = 'Please choose a team member from the list.';
        }
        [$rType, $rId] = $this->parseRelated((string) ($data['related'] ?? ''));
        if (!isset($errors['related']) && ($data['related'] ?? null) !== null) {
            if ($rType === 'none' || !Tasks::relatedExists($rType, (int) $rId)) {
                $errors['related'] = 'That record no longer exists — please choose another or leave it empty.';
            }
        }
        if ($errors !== []) {
            $this->back($backPath, $errors);
        }
        return [
            'title' => $data['title'],
            'notes' => $data['notes'],
            'due_at' => $due,
            'assigned_to' => $data['assigned_to'],
            'related_type' => $rType,
            'related_id' => $rType === 'none' ? null : (int) $rId,
        ];
    }

    /** "enquiry:12" → ['enquiry', '12'] ; anything else → ['none', ''] @return array{0:string,1:string} */
    private function parseRelated(string $value): array
    {
        if (preg_match('/^(enquiry|customer|quotation|booking):([1-9]\d{0,9})$/', $value, $m) === 1) {
            return [$m[1], $m[2]];
        }
        return ['none', ''];
    }

    private function parseDateTime(string $v): ?string
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $fmt) {
            $d = \DateTimeImmutable::createFromFormat('!' . $fmt, $v);
            if ($d && $d->format($fmt) === $v && (int) $d->format('Y') >= 2000 && (int) $d->format('Y') <= 2100) {
                return $d->format('Y-m-d H:i:00');
            }
        }
        return null;
    }

    /** @param array<string,mixed> $data */
    private function afterSave(array $data): string
    {
        $return = $this->request->input('return', '');
        if (is_string($return) && $return !== '' && $data['related_id'] !== null) {
            return $this->backUrl($data['related_type'], (string) $data['related_id']);
        }
        return '/tasks';
    }

    private function backUrl(string $type, string $id): string
    {
        return match ($type) {
            'enquiry' => '/enquiries/' . $id,
            'customer' => feature('customers') && can('customers') ? '/customers/' . $id : '/tasks',
            default => '/tasks',
        };
    }

    /** Return to a same-site page the form named (e.g. the enquiry page), else $fallback. */
    private function redirectBack(string $fallback): never
    {
        $to = $this->request->input('return_to', '');
        if (is_string($to) && preg_match('#^/(enquiries|customers|tasks)(/[0-9]+)?(\?[a-z=&_0-9]*)?$#', $to) === 1) {
            $this->redirect($to);
        }
        $this->redirect($fallback);
    }

    /** @return array<string,mixed> */
    private function findOr404(string $rawId): array
    {
        $task = Tasks::find($this->id($rawId));
        if ($task === null) {
            Response::abort(404);
        }
        return $task;
    }
}
