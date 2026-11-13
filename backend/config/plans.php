<?php

declare(strict_types=1);

/*
 * Plans & paid features — the ONE place to change what each plan unlocks.
 *
 * A plan is unlocked by a signed license key (see app/Core/License.php, docs/LICENSE-KEYS.md).
 * No key = "basic". Each feature names the cheapest plan that includes it; higher plans include
 * everything below them (rank).
 */
return [
    'plans' => [
        'basic'   => ['label' => 'Basic',   'rank' => 1, 'tagline' => 'Website content manager'],
        'pro'     => ['label' => 'Pro',     'rank' => 2, 'tagline' => 'Sales & bookings suite'],
        'premium' => ['label' => 'Premium', 'rank' => 3, 'tagline' => 'Complete travel agency ERP'],
    ],

    // Basic (always included — listed for the comparison table only)
    'basic_features' => [
        'Dashboard', 'Packages & destinations', 'Reviews & FAQs', 'Website enquiries inbox',
        'Website & business settings', 'Owner + editor accounts', 'Instant website updates',
    ],

    'features' => [
        // ── Pro ──────────────────────────────────────────────────────────────
        'customers'  => ['plan' => 'pro', 'label' => 'Customer database', 'desc' => 'Every traveller in one place with trip history, total spend, birthdays and tags.'],
        'crm'        => ['plan' => 'pro', 'label' => 'Lead pipeline & follow-ups', 'desc' => 'Drag leads through stages, log calls & WhatsApp chats, and never miss a follow-up reminder.'],
        'quotations' => ['plan' => 'pro', 'label' => 'Quotations', 'desc' => 'Build day-wise quotations in minutes, share a beautiful link or PDF on WhatsApp, convert to a booking in one click.'],
        'bookings'   => ['plan' => 'pro', 'label' => 'Bookings & travellers', 'desc' => 'Confirmed trips with traveller details, pickup points, status and balance due.'],
        'payments'   => ['plan' => 'pro', 'label' => 'Payments & receipts', 'desc' => 'Record advances and balances (UPI, cash, bank) and print receipts.'],
        'calendar'   => ['plan' => 'pro', 'label' => 'Trip calendar', 'desc' => 'See every departure, return and follow-up on one monthly calendar.'],
        'reports'    => ['plan' => 'pro', 'label' => 'Business reports', 'desc' => 'Sales, collections, outstanding balances, lead sources and top packages.'],
        // ── Premium ──────────────────────────────────────────────────────────
        'invoices'   => ['plan' => 'premium', 'label' => 'GST invoices', 'desc' => 'GST-compliant tax invoices (CGST/SGST/IGST, SAC 998555) generated from bookings.'],
        'vendors'    => ['plan' => 'premium', 'label' => 'Hotels & vendors', 'desc' => 'Hotels, houseboats, transporters and guides with contacts and payables.'],
        'vehicles'   => ['plan' => 'premium', 'label' => 'Vehicles & drivers', 'desc' => 'Your fleet, drivers, and insurance / permit expiry alerts.'],
        'profit'     => ['plan' => 'premium', 'label' => 'Cost & profit per booking', 'desc' => 'Add supplier costs to each booking and see the real profit margin.'],
        'roles'      => ['plan' => 'premium', 'label' => 'Staff roles & permissions', 'desc' => 'Give sales, accounts and managers only the screens they need.'],
        'templates'  => ['plan' => 'premium', 'label' => 'WhatsApp & email templates', 'desc' => 'One-click messages for quotes, confirmations, payment receipts and reminders.'],
        'activity'   => ['plan' => 'premium', 'label' => 'Activity log', 'desc' => 'See who changed what, and when.'],
        'backup'     => ['plan' => 'premium', 'label' => 'Backup & export', 'desc' => 'Download a full database backup and Excel-ready exports anytime.'],
    ],
];
