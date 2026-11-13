<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Response;
use App\Core\Template;
use App\Core\Validator;
use App\Repositories\BookingRepository as Bookings;
use App\Repositories\PaymentRepository as Payments;

/** Payments received against bookings: record, list, receipt print, delete, CSV. */
final class PaymentController extends Controller
{
    public function index(): never
    {
        $filters = $this->filters();
        $page = filter_var($this->request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $exportUrl = url('/payments/export') . '?' . http_build_query(['from' => $filters['from'], 'to' => $filters['to'], 'method' => $filters['method'], 'q' => $filters['q']]);

        $this->view('admin/payments/index', [
            'title' => 'Payments',
            'pretitle' => 'Money received',
            'filters' => $filters,
            'result' => Payments::paginate($filters, $page, 20),
            'summary' => Payments::summary($filters),
            'pageActions' => '<a href="' . e($exportUrl) . '" class="btn" title="Download the payments shown below as a spreadsheet (CSV)">' . icon('download') . ' Download CSV</a>',
        ]);
    }

    /** Record a payment on a booking. Over-payment is allowed (with a warning), never blocked. */
    public function store(string $id): never
    {
        $booking = Bookings::find($this->id($id));
        if ($booking === null) {
            Response::abort(404);
        }
        $bid = (int) $booking['id'];
        $back = '/bookings/' . $bid . '#payments';

        $v = Validator::make($this->request->all(), [
            'amount' => 'required|numeric|min:1|max:99999999',
            'method' => 'required|in:' . implode(',', array_keys(Payments::METHODS)),
            'reference' => 'nullable|string|max:120',
            'paid_at' => 'required|string',
            'notes' => 'nullable|string|max:255',
        ], ['amount' => 'Amount', 'method' => 'Payment method', 'paid_at' => 'Payment date', 'reference' => 'Reference']);
        $errors = $v->errors();
        $d = $v->validated();
        if (!isset($errors['paid_at'])) {
            if (!Bookings::validDate($d['paid_at'] ?? null)) {
                $errors['paid_at'] = 'Please pick a valid payment date.';
            } elseif ($d['paid_at'] > date('Y-m-d', strtotime('+1 day'))) {
                $errors['paid_at'] = 'The payment date can’t be in the future.';
            }
        }
        if ($errors !== []) {
            $this->back($back, $errors, 'The payment was not saved — please check the payment form.');
        }

        $balanceBefore = (float) $booking['balance'];
        $created = Payments::create($bid, [
            'amount' => (float) $d['amount'],
            'method' => (string) $d['method'],
            'reference' => $d['reference'] ?? null,
            'paid_at' => (string) $d['paid_at'],
            'notes' => $d['notes'] ?? null,
        ]);
        Activity::log('created', 'payment', $created['id'], sprintf('Recorded %s (%s) on booking %s — receipt %s',
            inr2($d['amount']), Payments::methodLabel($d['method']), $booking['number'], $created['receipt_no']));

        Flash::success(sprintf('Payment of %s recorded — receipt %s.', inr2($d['amount']), $created['receipt_no']));
        if ((float) $d['amount'] > $balanceBefore + 0.009) {
            Flash::info(sprintf('Note: this is %s more than the balance that was due (%s). Check the total amount if this was not intended.',
                inr2((float) $d['amount'] - max(0, $balanceBefore)), inr2(max(0, $balanceBefore))));
        }
        $this->redirect($back);
    }

    /** Owner / accounts (anyone with the payments permission) can delete a wrongly entered payment. */
    public function destroy(string $id): never
    {
        $payment = $this->findOr404($id);
        Payments::delete((int) $payment['id']);
        Activity::log('deleted', 'payment', (int) $payment['id'], sprintf('Deleted payment %s of %s on booking %s',
            $payment['receipt_no'], inr2($payment['amount']), $payment['booking_number']));
        Flash::success('Payment ' . $payment['receipt_no'] . ' deleted.');
        $to = $this->request->input('return') === 'payments' ? '/payments' : '/bookings/' . $payment['booking_id'] . '#payments';
        $this->redirect($to);
    }

    /** Printable receipt (A4). */
    public function receipt(string $id): never
    {
        $payment = $this->findOr404($id);
        $paidUpTo = Payments::paidUpTo($payment);
        $balanceAfter = (float) $payment['total_amount'] - $paidUpTo;

        $msg = Template::message('Payment received', Payments::messageVars($payment, $balanceAfter),
            'Hi {{customer_name}}, we received {{payment_amount}} for booking {{booking_number}} (receipt {{receipt_no}}). Balance due: {{balance}}. Thank you! — {{company_name}}');
        $toolbar = '<a class="btn btn-success" href="' . e(wa_link((string) $payment['customer_phone'], $msg)) . '" target="_blank" rel="noopener">'
            . icon('whatsapp') . ' Send on WhatsApp</a>';

        $this->view('admin/payments/receipt', [
            'title' => 'Receipt ' . $payment['receipt_no'] . ' — ' . $payment['customer_name'],
            'backUrl' => '/bookings/' . $payment['booking_id'],
            'toolbar' => $toolbar,
            'payment' => $payment,
            'paidUpTo' => $paidUpTo,
            'balanceAfter' => $balanceAfter,
        ], 'layouts/print');
    }

    public function export(): never
    {
        $filters = $this->filters();
        $rows = Payments::forExport($filters);
        Activity::log('exported', 'payment', null, sprintf('Downloaded %d payment%s as CSV', count($rows), count($rows) === 1 ? '' : 's'));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="payments-' . ($filters['from'] ?: 'all') . '-to-' . ($filters['to'] ?: date('Y-m-d')) . '.csv"');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        self::csvRow($out, ['Date', 'Receipt no.', 'Booking no.', 'Trip', 'Customer', 'Phone', 'Method', 'Reference', 'Amount (INR)', 'Notes', 'Recorded by']);
        $total = 0.0;
        foreach ($rows as $r) {
            $total += (float) $r['amount'];
            self::csvRow($out, [
                $r['paid_at'], $r['receipt_no'], $r['booking_number'], $r['booking_title'], $r['customer_name'], $r['customer_phone'],
                Payments::methodLabel($r['method']), $r['reference'], number_format((float) $r['amount'], 2, '.', ''), $r['notes'], $r['recorded_by'],
            ]);
        }
        self::csvRow($out, ['', '', '', '', '', '', '', 'TOTAL', number_format($total, 2, '.', ''), '', '']);
        fclose($out);
        exit;
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    /** @return array{from:string,to:string,method:string,q:string} */
    private function filters(): array
    {
        $hasAny = false;
        foreach (['from', 'to', 'method', 'q'] as $k) {
            if (array_key_exists($k, $_GET)) {
                $hasAny = true;
            }
        }
        return Payments::filtersFrom(
            $this->request->query('from', ''),
            $this->request->query('to', ''),
            $this->request->query('method', ''),
            $this->request->query('q', ''),
            $hasAny,
        );
    }

    /** @return array<string,mixed> */
    private function findOr404(string $rawId): array
    {
        $p = Payments::find($this->id($rawId));
        if ($p === null) {
            Response::abort(404);
        }
        return $p;
    }

    /** @param resource $out @param list<mixed> $cells */
    private static function csvRow($out, array $cells): void
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
