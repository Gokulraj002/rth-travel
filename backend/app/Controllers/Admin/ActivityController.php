<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Repositories\ActivityRepository;
use App\Repositories\AdminRepository;

/** Activity log — who changed what, and when (feature "activity", permission "activity"). */
final class ActivityController extends Controller
{
    public function index(): never
    {
        $filters = $this->filters();
        $page = max(1, (int) $this->request->query('page', 1));
        $result = ActivityRepository::paginate($filters, $page);
        $active = array_filter($filters, static fn ($v) => $v !== '');
        $exportUrl = url('/activity/export') . ($active === [] ? '' : '?' . http_build_query($active));

        $this->view('admin/activity/index', [
            'title' => 'Activity log',
            'pretitle' => 'Who changed what, and when',
            'filters' => $filters,
            'result' => $result,
            'users' => AdminRepository::options(),
            'entities' => ActivityRepository::entities(),
            'actions' => ActivityRepository::actions(),
            'pageActions' => '<a href="' . e($exportUrl) . '" class="btn" title="Download the entries matching your filters as a spreadsheet (CSV)">'
                . icon('download') . ' Download CSV</a>',
        ]);
    }

    public function export(): never
    {
        $filters = $this->filters();
        Activity::log('exported', 'activity', null, 'Downloaded the activity log as CSV');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="activity-log-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        BackupController::csvRow($out, ['When', 'Who', 'Action', 'Section', 'Record ID', 'Details']);
        foreach (ActivityRepository::cursor($filters) as $r) {
            BackupController::csvRow($out, [
                (string) $r['created_at'],
                $r['admin_name'] ?? ($r['admin_id'] ? 'Removed user #' . $r['admin_id'] : 'System / website'),
                ActivityRepository::actionLabel((string) $r['action']),
                ActivityRepository::entityLabel((string) $r['entity']),
                $r['entity_id'],
                $r['summary'],
            ]);
        }
        fclose($out);
        exit;
    }

    /** @return array{user:string,entity:string,action:string,from:string,to:string,q:string} */
    private function filters(): array
    {
        $date = static function (mixed $v): string {
            $v = is_string($v) ? trim($v) : '';
            $d = \DateTime::createFromFormat('!Y-m-d', $v);
            return $d && $d->format('Y-m-d') === $v ? $v : '';
        };
        $user = (string) $this->request->query('user', '');
        $entity = (string) $this->request->query('entity', '');
        $action = (string) $this->request->query('action', '');
        return [
            'user' => $user === 'system' || ctype_digit($user) ? $user : '',
            'entity' => preg_match('/^[a-z0-9_-]{1,40}$/i', $entity) ? $entity : '',
            'action' => preg_match('/^[a-z0-9_-]{1,40}$/i', $action) ? $action : '',
            'from' => $date($this->request->query('from', '')),
            'to' => $date($this->request->query('to', '')),
            'q' => mb_substr((string) $this->request->query('q', ''), 0, 100),
        ];
    }
}
