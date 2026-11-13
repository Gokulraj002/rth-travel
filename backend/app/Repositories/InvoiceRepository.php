<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Sequence;
use App\Core\Settings;

/**
 * GST invoices (+ shared Indian GST helpers used by Business details).
 *
 * Money rules (all server-side, 2-decimal rounding):
 *   line amount = qty × rate; subtotal = Σ lines; taxable = subtotal − discount
 *   intra-state (place of supply = company state): CGST = SGST = taxable × rate/2
 *   inter-state: IGST = taxable × rate
 *   "amount includes GST": entered figures are gross; taxable = gross ÷ (1 + rate) and the stored
 *   line amounts / discount are converted to their pre-tax values so the invoice adds up exactly.
 * Drafts carry a temporary number ("DRAFT-12") and get the real gap-free INV number when issued.
 */
final class InvoiceRepository
{
    public const STATUSES = ['draft' => 'Draft', 'issued' => 'Issued', 'paid' => 'Paid', 'void' => 'Void'];

    /** GST state codes → state / union territory names. */
    public const STATES = [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh',
        '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
        '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur',
        '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
        '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa',
        '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh',
        '97' => 'Other Territory',
    ];

    private const PER_PAGE = 20;

    // ── GST helpers ──────────────────────────────────────────────────────────

    public static function stateCode(string $state): string
    {
        $code = array_search(self::canonicalState($state), self::STATES, true);
        return $code === false ? '' : (string) $code;
    }

    /** Case-insensitive match against the official list ('' if unknown). */
    public static function canonicalState(string $state): string
    {
        $needle = strtolower(trim(str_replace('&', 'and', $state)));
        foreach (self::STATES as $name) {
            if (strtolower($name) === $needle) {
                return $name;
            }
        }
        return '';
    }

    /** Best guess of a customer's state from free text (city / address). */
    public static function guessState(string $text): string
    {
        $t = strtolower(str_replace('&', 'and', $text));
        if ($t === '') {
            return '';
        }
        foreach (self::STATES as $name) {
            if (str_contains($t, strtolower($name))) {
                return $name;
            }
        }
        $cities = ['srinagar' => 'Jammu and Kashmir', 'jammu' => 'Jammu and Kashmir', 'leh' => 'Ladakh', 'mumbai' => 'Maharashtra',
                   'pune' => 'Maharashtra', 'bengaluru' => 'Karnataka', 'bangalore' => 'Karnataka', 'chennai' => 'Tamil Nadu',
                   'hyderabad' => 'Telangana', 'kolkata' => 'West Bengal', 'ahmedabad' => 'Gujarat', 'surat' => 'Gujarat',
                   'jaipur' => 'Rajasthan', 'lucknow' => 'Uttar Pradesh', 'noida' => 'Uttar Pradesh', 'gurugram' => 'Haryana',
                   'gurgaon' => 'Haryana', 'new delhi' => 'Delhi', 'kochi' => 'Kerala', 'bhopal' => 'Madhya Pradesh',
                   'indore' => 'Madhya Pradesh', 'patna' => 'Bihar', 'ludhiana' => 'Punjab', 'amritsar' => 'Punjab'];
        foreach ($cities as $city => $state) {
            if (preg_match('/\b' . preg_quote($city, '/') . '\b/', $t)) {
                return $state;
            }
        }
        return '';
    }

    /** Valid 15-character GSTIN (format + check digit). */
    public static function validGstin(string $gstin): bool
    {
        if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)) {
            return false;
        }
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $p = strpos($chars, $gstin[$i]) * ($i % 2 === 0 ? 1 : 2);
            $sum += intdiv($p, 36) + $p % 36;
        }
        return $chars[(36 - $sum % 36) % 36] === $gstin[14];
    }

    public static function validPan(string $pan): bool
    {
        return (bool) preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan);
    }

    /** The number the next document of this type will get, without using it up. */
    public static function previewNumber(string $type): string
    {
        $year = date('Y');
        $last = (int) Database::value('SELECT last_no FROM sequences WHERE name = ?', [$type . ':' . $year]);
        $defaults = ['quotation' => 'QT', 'booking' => 'BK', 'invoice' => 'INV', 'receipt' => 'RC'];
        $prefix = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', Settings::get($type . '_prefix', $defaults[$type] ?? 'DOC'))) ?: 'DOC';
        return sprintf('%s-%s-%04d', $prefix, $year, $last + 1);
    }

    public static function companyState(): string
    {
        return self::canonicalState(Settings::get('company_state')) ?: Settings::get('company_state');
    }

    public static function isIntraState(?string $placeOfSupply): bool
    {
        $pos = strtolower(trim((string) $placeOfSupply));
        return $pos === '' || $pos === strtolower(self::companyState());
    }

    /**
     * All invoice maths.
     * @param list<array{description:string,hsn_sac:string,qty:float,unit_price:float}> $lines
     * @return array{items:list<array{description:string,hsn_sac:string,qty:float,unit_price:float,amount:float}>,
     *               subtotal:float,discount:float,taxable:float,cgst:float,sgst:float,igst:float,tax:float,total:float}
     */
    public static function compute(array $lines, float $discount, float $taxPercent, bool $inclusive, bool $intra): array
    {
        $r = max(0.0, $taxPercent);
        $items = [];
        $gross = 0.0;
        foreach ($lines as $l) {
            $amount = round($l['qty'] * $l['unit_price'], 2);
            $items[] = [...$l, 'amount' => $amount];
            $gross += $amount;
        }
        $gross = round($gross, 2);
        $discount = round(min(max(0.0, $discount), $gross), 2);

        if (!$inclusive || $r == 0.0) {
            $taxable = round($gross - $discount, 2);
            if ($intra) {
                $cgst = $sgst = round($taxable * $r / 200, 2);
                $igst = 0.0;
            } else {
                $cgst = $sgst = 0.0;
                $igst = round($taxable * $r / 100, 2);
            }
            $subtotal = $gross;
        } else {
            // Back-calculate from the GST-inclusive figure the customer pays.
            $grossTotal = round($gross - $discount, 2);
            $taxable = round($grossTotal * 100 / (100 + $r), 2);
            $tax = round($grossTotal - $taxable, 2);
            if ($intra) {
                $cgst = round($tax / 2, 2);
                $sgst = round($tax - $cgst, 2);
                $igst = 0.0;
            } else {
                $cgst = $sgst = 0.0;
                $igst = $tax;
            }
            // Convert lines + discount to pre-tax values; the last line absorbs rounding paise.
            $f = 100 / (100 + $r);
            $discount = round($discount * $f, 2);
            $subtotal = round($taxable + $discount, 2);
            $sum = 0.0;
            $n = count($items);
            foreach ($items as $i => &$it) {
                $it['unit_price'] = round($it['unit_price'] * $f, 2);
                $it['amount'] = $i === $n - 1 ? round($subtotal - $sum, 2) : round($it['amount'] * $f, 2);
                if ($it['qty'] == 1.0) {
                    $it['unit_price'] = $it['amount'];
                }
                $sum += $it['amount'];
            }
            unset($it);
        }
        $tax = round($cgst + $sgst + $igst, 2);
        return [
            'items' => $items, 'subtotal' => $subtotal, 'discount' => $discount, 'taxable' => $taxable,
            'cgst' => $cgst, 'sgst' => $sgst, 'igst' => $igst, 'tax' => $tax, 'total' => round($taxable + $tax, 2),
        ];
    }

    // ── Customer GSTIN lives in the notes column as a first line "Customer GSTIN: …" ──

    /** @return array{gstin:string,notes:string} */
    public static function splitNotes(?string $notes): array
    {
        $notes = (string) $notes;
        if (preg_match('/^Customer GSTIN: ([0-9A-Z]{15})\R?/', $notes, $m)) {
            return ['gstin' => $m[1], 'notes' => (string) substr($notes, strlen($m[0]))];
        }
        return ['gstin' => '', 'notes' => $notes];
    }

    public static function joinNotes(string $gstin, string $notes): ?string
    {
        $out = ($gstin !== '' ? 'Customer GSTIN: ' . $gstin . "\n" : '') . $notes;
        return trim($out) === '' ? null : rtrim($out);
    }

    // ── Queries ──────────────────────────────────────────────────────────────

    private const SELECT = 'SELECT i.*, c.name AS customer_name, c.phone AS customer_phone, b.number AS booking_number, b.title AS booking_title
        FROM invoices i
        JOIN customers c ON c.id = i.customer_id
        LEFT JOIN bookings b ON b.id = i.booking_id';

    /** @param array{q:string,status:string,month:string} $f @return array{0:string,1:list<mixed>} */
    private static function where(array $f): array
    {
        $w = ['1=1'];
        $p = [];
        if ($f['q'] !== '') {
            $w[] = '(i.number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR b.number LIKE ? OR b.title LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($p, $like, $like, $like, $like, $like);
        }
        if (isset(self::STATUSES[$f['status']])) {
            $w[] = 'i.status = ?';
            $p[] = $f['status'];
        }
        if (preg_match('/^\d{4}-\d{2}$/', $f['month'])) {
            $w[] = 'i.issue_date BETWEEN ? AND LAST_DAY(?)';
            $p[] = $f['month'] . '-01';
            $p[] = $f['month'] . '-01';
        }
        return [implode(' AND ', $w), $p];
    }

    /** @param array{q:string,status:string,month:string} $f */
    public static function paginate(array $f, int $page): array
    {
        [$where, $params] = self::where($f);
        $base = ' FROM invoices i JOIN customers c ON c.id = i.customer_id LEFT JOIN bookings b ON b.id = i.booking_id WHERE ' . $where;
        $total = (int) Database::value('SELECT COUNT(*)' . $base, $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * self::PER_PAGE;
        $rows = Database::fetchAll(self::SELECT . ' WHERE ' . $where . ' ORDER BY i.issue_date DESC, i.id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset, $params);
        // Totals exclude drafts and void invoices (they are not sales).
        $sum = Database::fetch(
            "SELECT COUNT(*) AS n, COALESCE(SUM(i.subtotal - i.discount),0) AS taxable, COALESCE(SUM(i.cgst + i.sgst + i.igst),0) AS tax,
                    COALESCE(SUM(i.total),0) AS total, COALESCE(SUM(CASE WHEN i.status = 'issued' THEN i.total ELSE 0 END),0) AS unpaid
             {$base} AND i.status IN ('issued','paid')",
            $params,
        ) ?? [];
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => self::PER_PAGE, 'sum' => $sum];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $row = Database::fetch(self::SELECT . ' WHERE i.id = ?', [$id]);
        if ($row === null) {
            return null;
        }
        $row['items'] = Database::fetchAll('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY position, id', [$id]);
        $row['customer'] = Database::fetch('SELECT * FROM customers WHERE id = ?', [(int) $row['customer_id']]) ?? [];
        return $row;
    }

    /** @return list<array<string,mixed>> */
    public static function forBooking(int $bookingId): array
    {
        return Database::fetchAll('SELECT * FROM invoices WHERE booking_id = ? ORDER BY issue_date DESC, id DESC', [$bookingId]);
    }

    /** Booking + customer + money, for pre-filling a new invoice. @return array<string,mixed>|null */
    public static function booking(int $id): ?array
    {
        return Database::fetch(
            'SELECT b.*, c.name AS customer_name, c.phone AS customer_phone, c.city AS customer_city, c.address AS customer_address,
                    (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.booking_id = b.id) AS paid
             FROM bookings b JOIN customers c ON c.id = b.customer_id WHERE b.id = ?',
            [$id],
        );
    }

    /** Recent bookings for the "invoice for which booking?" picker. @return list<array<string,mixed>> */
    public static function bookingOptions(int $limit = 200): array
    {
        return Database::fetchAll(
            "SELECT b.id, b.number, b.title, b.travel_start, c.name AS customer_name
             FROM bookings b JOIN customers c ON c.id = b.customer_id
             WHERE b.status <> 'cancelled' ORDER BY b.id DESC LIMIT " . max(1, min(1000, $limit)),
        );
    }

    /** @return list<array<string,mixed>> */
    public static function customerOptions(int $limit = 500): array
    {
        return Database::fetchAll('SELECT id, name, phone, city FROM customers ORDER BY name LIMIT ' . max(1, min(2000, $limit)));
    }

    // ── Writes ───────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $data invoice columns (without number/status/money)
     * @param array<string,mixed> $calc result of compute()
     */
    public static function create(array $data, array $calc, string $status): int
    {
        return (int) Database::transaction(static function () use ($data, $calc, $status): int {
            $temp = 'DRAFT-' . bin2hex(random_bytes(6));
            $id = Database::insert('invoices', [
                ...$data, ...self::money($calc),
                'number' => $status === 'draft' ? $temp : Sequence::next('invoice'),
                'status' => $status,
            ]);
            if ($status === 'draft') {
                Database::update('invoices', ['number' => 'DRAFT-' . $id], 'id = ?', [$id]);
            }
            self::saveItems($id, $calc['items']);
            return $id;
        });
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $calc */
    public static function update(int $id, array $data, array $calc, ?string $issue = null): void
    {
        Database::transaction(static function () use ($id, $data, $calc, $issue): void {
            $row = [...$data, ...self::money($calc)];
            if ($issue === 'issued') {
                $row['number'] = Sequence::next('invoice');
                $row['status'] = 'issued';
            }
            Database::update('invoices', $row, 'id = ?', [$id]);
            Database::query('DELETE FROM invoice_items WHERE invoice_id = ?', [$id]);
            self::saveItems($id, $calc['items']);
        });
    }

    /** Draft → issued: gives it the next invoice number. Returns the number. */
    public static function issue(int $id): string
    {
        return (string) Database::transaction(static function () use ($id): string {
            $no = Sequence::next('invoice');
            Database::update('invoices', ['number' => $no, 'status' => 'issued'], "id = ? AND status = 'draft'", [$id]);
            return $no;
        });
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::update('invoices', ['status' => $status], 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::query("DELETE FROM invoices WHERE id = ? AND status = 'draft'", [$id]);
    }

    /** @param array<string,mixed> $calc @return array<string,string> */
    private static function money(array $calc): array
    {
        $f = static fn (float $v): string => number_format($v, 2, '.', '');
        return [
            'subtotal' => $f($calc['subtotal']), 'discount' => $f($calc['discount']),
            'cgst' => $f($calc['cgst']), 'sgst' => $f($calc['sgst']), 'igst' => $f($calc['igst']), 'total' => $f($calc['total']),
        ];
    }

    /** @param list<array<string,mixed>> $items */
    private static function saveItems(int $invoiceId, array $items): void
    {
        foreach ($items as $pos => $it) {
            Database::insert('invoice_items', [
                'invoice_id' => $invoiceId,
                'description' => mb_substr((string) $it['description'], 0, 300),
                'hsn_sac' => $it['hsn_sac'] !== '' ? $it['hsn_sac'] : null,
                'qty' => number_format((float) $it['qty'], 2, '.', ''),
                'unit_price' => number_format((float) $it['unit_price'], 2, '.', ''),
                'amount' => number_format((float) $it['amount'], 2, '.', ''),
                'position' => $pos,
            ]);
        }
    }
}
