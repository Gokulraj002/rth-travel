<?php

declare(strict_types=1);

/*
 * Business / billing settings (stored in the `settings` table, edited on the "Business details" page).
 * Key => default. Read anywhere with setting('company_gstin') — empty values fall back to these defaults.
 * NOT exposed by the public website API.
 */
return [
    'company_legal_name'      => 'Reyaan Trip Holiday',
    'company_gstin'           => '',
    'company_pan'             => '',
    'company_state'           => 'Jammu and Kashmir',
    'company_state_code'      => '01',
    'company_address'         => 'Srinagar, Jammu & Kashmir',
    'company_phone'           => '',
    'company_email'           => '',
    'company_website'         => '',
    'bank_name'               => '',
    'bank_account_name'       => '',
    'bank_account_no'         => '',
    'bank_ifsc'               => '',
    'bank_branch'             => '',
    'upi_id'                  => '',
    'tax_percent_default'     => '5',          // tour operator services (SAC 998555) — 5% without ITC
    'invoice_hsn_sac'         => '998555',
    'quotation_prefix'        => 'QT',
    'booking_prefix'          => 'BK',
    'invoice_prefix'          => 'INV',
    'receipt_prefix'          => 'RC',
    'quotation_validity_days' => '7',
    'quotation_terms'         => "50% advance to confirm the booking, balance before the trip starts.\nRates are subject to availability at the time of booking.\nCancellation charges apply as per company policy.",
    'invoice_terms'           => "Thank you for travelling with us.\nThis is a computer-generated invoice.",
    'signature_name'          => 'Authorised Signatory',
];
