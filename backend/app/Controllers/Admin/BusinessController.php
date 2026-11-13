<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Activity;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Settings;
use App\Core\Validator;
use App\Repositories\InvoiceRepository;

/** "Business details" — legal, GST, bank and document settings used on quotations, invoices and receipts. */
final class BusinessController extends Controller
{
    private const RULES = [
        'company_legal_name' => 'required|string|max:160',
        'company_gstin' => 'nullable|string|max:15',
        'company_pan' => 'nullable|string|max:10',
        'company_state' => 'required|string|max:80',
        'company_address' => 'nullable|string|max:500',
        'company_phone' => 'nullable|phone|max:30',
        'company_email' => 'nullable|email|max:190',
        'company_website' => 'nullable|url|max:190',
        'bank_name' => 'nullable|string|max:120',
        'bank_account_name' => 'nullable|string|max:160',
        'bank_account_no' => 'nullable|string|max:30',
        'bank_ifsc' => 'nullable|string|max:11',
        'bank_branch' => 'nullable|string|max:120',
        'upi_id' => 'nullable|string|max:80',
        'tax_percent_default' => 'required|numeric|min:0|max:28',
        'invoice_hsn_sac' => 'required|string|max:8',
        'quotation_prefix' => 'required|string|max:6',
        'booking_prefix' => 'required|string|max:6',
        'invoice_prefix' => 'required|string|max:6',
        'receipt_prefix' => 'required|string|max:6',
        'quotation_validity_days' => 'required|int|min:1|max:365',
        'quotation_terms' => 'nullable|string|max:3000',
        'invoice_terms' => 'nullable|string|max:3000',
        'signature_name' => 'nullable|string|max:120',
    ];

    public const LABELS = [
        'company_legal_name' => 'Legal business name',
        'company_gstin' => 'GSTIN',
        'company_pan' => 'PAN',
        'company_state' => 'State',
        'company_state_code' => 'State code',
        'company_address' => 'Registered address',
        'company_phone' => 'Phone',
        'company_email' => 'Email',
        'company_website' => 'Website',
        'bank_name' => 'Bank name',
        'bank_account_name' => 'Account holder name',
        'bank_account_no' => 'Account number',
        'bank_ifsc' => 'IFSC code',
        'bank_branch' => 'Branch',
        'upi_id' => 'UPI ID',
        'tax_percent_default' => 'Default GST %',
        'invoice_hsn_sac' => 'SAC code',
        'quotation_prefix' => 'Quotation prefix',
        'booking_prefix' => 'Booking prefix',
        'invoice_prefix' => 'Invoice prefix',
        'receipt_prefix' => 'Receipt prefix',
        'quotation_validity_days' => 'Quotation valid for (days)',
        'quotation_terms' => 'Quotation terms',
        'invoice_terms' => 'Invoice terms',
        'signature_name' => 'Signature name',
    ];

    public function edit(): never
    {
        $values = [];
        foreach (array_keys(config('business')) as $key) {
            $values[$key] = Settings::get($key);
        }
        $this->view('admin/business/edit', [
            'title' => 'Business details',
            'pretitle' => 'Printed on quotations, invoices & receipts',
            'values' => $values,
            'states' => InvoiceRepository::STATES,
            'previews' => [
                'Quotation' => InvoiceRepository::previewNumber('quotation'),
                'Booking' => InvoiceRepository::previewNumber('booking'),
                'Invoice' => InvoiceRepository::previewNumber('invoice'),
                'Receipt' => InvoiceRepository::previewNumber('receipt'),
            ],
        ]);
    }

    public function update(): never
    {
        $input = $this->request->all();
        // Normalise codes before validating (people type them in lower case / with spaces).
        foreach (['company_gstin', 'company_pan', 'bank_ifsc', 'quotation_prefix', 'booking_prefix', 'invoice_prefix', 'receipt_prefix'] as $k) {
            if (isset($input[$k]) && is_string($input[$k])) {
                $input[$k] = strtoupper((string) preg_replace('/\s+/', '', $input[$k]));
            }
        }
        if (isset($input['bank_account_no']) && is_string($input['bank_account_no'])) {
            $input['bank_account_no'] = (string) preg_replace('/[\s-]+/', '', $input['bank_account_no']);
        }

        $v = Validator::make($input, self::RULES, self::LABELS);
        $errors = $v->errors();
        $c = $v->validated();

        $state = InvoiceRepository::canonicalState((string) ($c['company_state'] ?? ''));
        if (!isset($errors['company_state']) && $state === '') {
            $errors['company_state'] = 'Please choose your state from the list.';
        }
        $stateCode = InvoiceRepository::stateCode($state);

        $gstin = (string) ($c['company_gstin'] ?? '');
        if (!isset($errors['company_gstin']) && $gstin !== '') {
            if (!InvoiceRepository::validGstin($gstin)) {
                $errors['company_gstin'] = 'This GSTIN is not valid. It has 15 characters, e.g. 01ABCDE1234F1Z5 — please check it against your GST certificate.';
            } elseif ($stateCode !== '' && substr($gstin, 0, 2) !== $stateCode) {
                $errors['company_gstin'] = 'This GSTIN belongs to state code ' . substr($gstin, 0, 2) . ', but the state below is ' . $state . ' (' . $stateCode . ').';
            }
        }
        $pan = (string) ($c['company_pan'] ?? '');
        if (!isset($errors['company_pan']) && $pan !== '') {
            if (!InvoiceRepository::validPan($pan)) {
                $errors['company_pan'] = 'PAN must look like ABCDE1234F (5 letters, 4 numbers, 1 letter).';
            } elseif ($gstin !== '' && !isset($errors['company_gstin']) && substr($gstin, 2, 10) !== $pan) {
                $errors['company_pan'] = 'This PAN does not match your GSTIN (characters 3–12 of the GSTIN are your PAN).';
            }
        }
        if (!isset($errors['company_website']) && ($c['company_website'] ?? null) !== null && !preg_match('#^https?://#i', (string) $c['company_website'])) {
            $errors['company_website'] = 'Website must start with https://';
        }
        $ifsc = (string) ($c['bank_ifsc'] ?? '');
        if (!isset($errors['bank_ifsc']) && $ifsc !== '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
            $errors['bank_ifsc'] = 'IFSC must be 11 characters, e.g. JAKA0SRINGR (4 letters, a zero, then 6 letters/numbers).';
        }
        if (!isset($errors['bank_account_no']) && ($c['bank_account_no'] ?? null) !== null && !preg_match('/^[0-9A-Z]{6,30}$/i', (string) $c['bank_account_no'])) {
            $errors['bank_account_no'] = 'Account number may only contain digits (6–30).';
        }
        $upi = (string) ($c['upi_id'] ?? '');
        if (!isset($errors['upi_id']) && $upi !== '' && !preg_match('/^[A-Za-z0-9._-]{2,64}@[A-Za-z0-9.-]{2,64}$/', $upi)) {
            $errors['upi_id'] = 'UPI ID must look like yourname@bank.';
        }
        if (!isset($errors['invoice_hsn_sac']) && !preg_match('/^[0-9]{4,8}$/', (string) ($c['invoice_hsn_sac'] ?? ''))) {
            $errors['invoice_hsn_sac'] = 'SAC code must be 4–8 digits (tour operator services: 998555).';
        }
        foreach (['quotation_prefix', 'booking_prefix', 'invoice_prefix', 'receipt_prefix'] as $k) {
            if (!isset($errors[$k]) && !preg_match('/^[A-Z0-9]{1,6}$/', (string) ($c[$k] ?? ''))) {
                $errors[$k] = self::LABELS[$k] . ' may only use letters A–Z and numbers 0–9 (max 6).';
            }
        }

        if ($errors !== []) {
            $this->back('/business', $errors);
        }

        $before = [];
        foreach (array_keys(config('business')) as $key) {
            $before[$key] = Settings::get($key);
        }
        $values = [];
        foreach (array_keys(self::RULES) as $key) {
            $val = $c[$key] ?? '';
            if ($key === 'tax_percent_default') {
                $val = rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.');
            }
            $values[$key] = str_replace("\r\n", "\n", (string) $val);
        }
        $values['company_state'] = $state;
        $values['company_state_code'] = $stateCode;
        Settings::set($values);

        $changed = array_keys(array_diff_assoc($values, $before));
        Activity::log('updated', 'business', null, $changed === []
            ? 'Saved business details (no changes)'
            : 'Updated business details: ' . str_limit(implode(', ', array_map(static fn ($k) => self::LABELS[$k] ?? $k, $changed)), 200));
        Flash::success('Business details saved. New quotations, invoices and receipts will use them.');
        $this->redirect('/business');
    }
}
