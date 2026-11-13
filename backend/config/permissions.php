<?php

declare(strict_types=1);

/*
 * Staff roles → what they may open. Checked with Gate::can('bookings') / can('bookings') /
 * route middleware 'can:bookings'. The owner can always do everything.
 * Roles other than owner/editor need the Premium "roles" feature to be assigned (see UserController).
 */
return [
    'permissions' => [
        'website'    => 'Edit website content (packages, destinations, reviews, FAQs, images)',
        'settings'   => 'Change website & business settings',
        'enquiries'  => 'Enquiries, lead pipeline & follow-ups',
        'customers'  => 'Customer database',
        'quotations' => 'Quotations',
        'bookings'   => 'Bookings, travellers & trip calendar',
        'payments'   => 'Record payments & receipts',
        'invoices'   => 'GST invoices',
        'vendors'    => 'Hotels, vendors & vehicles',
        'profit'     => 'See supplier costs & profit',
        'reports'    => 'Business reports',
        'templates'  => 'Manage message templates',
        'activity'   => 'Activity log',
        'backup'     => 'Backup & data export',
    ],

    'roles' => [
        'owner'    => ['label' => 'Owner',    'desc' => 'Full access, team, plan & license.', 'permissions' => ['*']],
        'manager'  => ['label' => 'Manager',  'desc' => 'Runs the business day to day — everything except team, license and backups.',
                       'permissions' => ['website', 'settings', 'enquiries', 'customers', 'quotations', 'bookings', 'payments', 'invoices', 'vendors', 'profit', 'reports', 'templates', 'activity']],
        'sales'    => ['label' => 'Sales',    'desc' => 'Leads, customers, quotations and bookings.',
                       'permissions' => ['enquiries', 'customers', 'quotations', 'bookings']],
        'accounts' => ['label' => 'Accounts', 'desc' => 'Payments, invoices, vendors, profit and reports.',
                       'permissions' => ['customers', 'bookings', 'payments', 'invoices', 'vendors', 'profit', 'reports']],
        'editor'   => ['label' => 'Editor',   'desc' => 'Website content, settings and enquiries.',
                       'permissions' => ['website', 'settings', 'enquiries']],
    ],

    // Roles anyone can assign without the Premium "roles" feature.
    'basic_roles' => ['owner', 'editor'],
];
