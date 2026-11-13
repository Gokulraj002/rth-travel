<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\EnquiryRepository;

/** Lead inbox. Enquiries are private (not website content) → no Revalidator::ping here. */
final class EnquiryController extends Controller
{
    public function index(): never
    {
        $filters = $this->filters();
        $page = filter_var($this->request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $result = EnquiryRepository::paginate($filters, $page, 20);
        $active = array_filter($filters, static fn ($v) => $v !== '');
        $exportUrl = url('/enquiries/export') . ($active === [] ? '' : '?' . http_build_query($active));

        $this->view('admin/enquiries/index', [
            'title' => 'Enquiries',
            'pretitle' => 'Leads from your website',
            'filters' => $filters,
            'result' => $result,
            'newCount' => EnquiryRepository::countByStatus('new'),
            'pageActions' => '<a href="' . e($exportUrl) . '" class="btn" title="Download the enquiries shown below as a spreadsheet (CSV)">'
                . icon('arrow-down') . ' Download CSV</a>',
        ]);
    }

    public function show(string $id): never
    {
        $enquiry = $this->findOr404($id);
        $this->view('admin/enquiries/show', [
            'title' => 'Enquiry from ' . $enquiry['name'],
            'pretitle' => 'Enquiry #' . $enquiry['id'],
            'enquiry' => $enquiry,
        ]);
    }

    public function update(string $id): never
    {
        $enquiry = $this->findOr404($id);
        $v = Validator::make($this->request->all(), [
            'status' => 'required|in:' . implode(',', array_keys(EnquiryRepository::STATUSES)),
            'notes' => 'nullable|string|max:5000',
        ], ['notes' => 'Internal notes']);
        if ($v->fails()) {
            $this->back('/enquiries/' . $enquiry['id'], $v->errors());
        }
        $data = $v->validated();
        EnquiryRepository::updateStatusNotes((int) $enquiry['id'], $data['status'], $data['notes']);

        $summary = $data['status'] !== $enquiry['status']
            ? sprintf('Marked enquiry from “%s” as %s', $enquiry['name'], EnquiryRepository::statusLabel($data['status']))
            : sprintf('Updated notes on enquiry from “%s”', $enquiry['name']);
        Activity::log('updated', 'enquiry', (int) $enquiry['id'], $summary);
        Flash::success('Enquiry saved.');
        $this->redirect('/enquiries/' . $enquiry['id']);
    }

    public function destroy(string $id): never
    {
        $enquiry = $this->findOr404($id);
        EnquiryRepository::delete((int) $enquiry['id']);
        Activity::log('deleted', 'enquiry', (int) $enquiry['id'], sprintf('Deleted enquiry from “%s”', $enquiry['name']));
        Flash::success('Enquiry deleted.');
        $this->redirect('/enquiries');
    }

    /** CSV download of the current filtered list (UTF-8 with BOM so Excel shows ₹ and names correctly). */
    public function export(): never
    {
        $filters = $this->filters();
        $rows = EnquiryRepository::forExport($filters);

        Activity::log('exported', 'enquiry', null, sprintf('Downloaded %d enquir%s as CSV', count($rows), count($rows) === 1 ? 'y' : 'ies'));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="enquiries-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        $this->csvRow($out, [
            'ID', 'Received', 'Status', 'Name', 'Phone', 'Email', 'Destination', 'Package', 'Travel month',
            'Adults', 'Children', 'Hotel category', 'Message', 'Source', 'Internal notes',
        ]);
        foreach ($rows as $r) {
            $this->csvRow($out, [
                $r['id'],
                (string) $r['created_at'],
                EnquiryRepository::statusLabel((string) $r['status']),
                $r['name'],
                $r['phone'],
                $r['email'],
                $r['destination'],
                $r['package'],
                $r['travel_month'],
                $r['adults'],
                $r['children'],
                $r['hotel_category'],
                $r['message'],
                EnquiryRepository::sourceLabel($r['source']),
                $r['notes'],
            ]);
        }
        fclose($out);
        exit;
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /** @return array{q:string,status:string,from:string,to:string} */
    private function filters(): array
    {
        return EnquiryRepository::filtersFrom(
            $this->request->query('q', ''),
            $this->request->query('status', ''),
            $this->request->query('from', ''),
            $this->request->query('to', ''),
        );
    }

    /** @return array<string,mixed> */
    private function findOr404(string $rawId): array
    {
        $enquiry = EnquiryRepository::find($this->id($rawId));
        if ($enquiry === null) {
            Response::abort(404);
        }
        return $enquiry;
    }

    /**
     * Write one CSV line, neutralising spreadsheet formula injection:
     * cells starting with = + - @ (or tab / CR) get a leading single quote.
     * @param resource $out
     * @param list<mixed> $cells
     */
    private function csvRow($out, array $cells): void
    {
        $clean = array_map(static function (mixed $v): string {
            $s = (string) ($v ?? '');
            if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                $s = "'" . $s;
            }
            return $s;
        }, $cells);
        fputcsv($out, $clean, ',', '"', '');
    }
}
