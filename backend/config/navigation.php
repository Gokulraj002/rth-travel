<?php

declare(strict_types=1);

/*
 * Sidebar. Each item: href, label, icon (helpers.php icon()), optional:
 *   'can'     => permission needed to SEE it (config/permissions.php)
 *   'feature' => paid feature (config/plans.php) — shown with a lock badge when the plan lacks it
 *   'owner'   => true  → owner only
 *   'badge'   => 'tasks_due'  → live count pill (layouts/admin.php resolves the known badge names)
 */
return [
    ['label' => null, 'items' => [
        ['href' => '/', 'label' => 'Dashboard', 'icon' => 'dashboard'],
    ]],
    ['label' => 'Sales', 'items' => [
        ['href' => '/enquiries', 'label' => 'Enquiries', 'icon' => 'inbox', 'can' => 'enquiries'],
        ['href' => '/leads', 'label' => 'Lead pipeline', 'icon' => 'columns', 'can' => 'enquiries', 'feature' => 'crm'],
        ['href' => '/tasks', 'label' => 'Follow-ups', 'icon' => 'bell', 'can' => 'enquiries', 'feature' => 'crm', 'badge' => 'tasks_due'],
        ['href' => '/customers', 'label' => 'Customers', 'icon' => 'user', 'can' => 'customers', 'feature' => 'customers'],
        ['href' => '/quotations', 'label' => 'Quotations', 'icon' => 'file-text', 'can' => 'quotations', 'feature' => 'quotations'],
    ]],
    ['label' => 'Operations', 'items' => [
        ['href' => '/bookings', 'label' => 'Bookings', 'icon' => 'briefcase', 'can' => 'bookings', 'feature' => 'bookings'],
        ['href' => '/calendar', 'label' => 'Trip calendar', 'icon' => 'calendar', 'can' => 'bookings', 'feature' => 'calendar'],
        ['href' => '/vendors', 'label' => 'Hotels & vendors', 'icon' => 'building', 'can' => 'vendors', 'feature' => 'vendors'],
        ['href' => '/vehicles', 'label' => 'Vehicles & drivers', 'icon' => 'car', 'can' => 'vendors', 'feature' => 'vehicles'],
    ]],
    ['label' => 'Accounts', 'items' => [
        ['href' => '/payments', 'label' => 'Payments', 'icon' => 'cash', 'can' => 'payments', 'feature' => 'payments'],
        ['href' => '/invoices', 'label' => 'GST invoices', 'icon' => 'receipt', 'can' => 'invoices', 'feature' => 'invoices'],
        ['href' => '/reports', 'label' => 'Reports', 'icon' => 'chart', 'can' => 'reports', 'feature' => 'reports'],
    ]],
    ['label' => 'Website', 'items' => [
        ['href' => '/packages', 'label' => 'Packages', 'icon' => 'package', 'can' => 'website'],
        ['href' => '/destinations', 'label' => 'Destinations', 'icon' => 'map-pin', 'can' => 'website'],
        ['href' => '/testimonials', 'label' => 'Reviews', 'icon' => 'star', 'can' => 'website'],
        ['href' => '/faqs', 'label' => 'FAQs', 'icon' => 'help', 'can' => 'website'],
        ['href' => '/settings', 'label' => 'Website settings', 'icon' => 'world', 'can' => 'settings'],
    ]],
    ['label' => 'Administration', 'items' => [
        ['href' => '/business', 'label' => 'Business details', 'icon' => 'id', 'can' => 'settings'],
        ['href' => '/templates', 'label' => 'Message templates', 'icon' => 'message', 'can' => 'templates', 'feature' => 'templates'],
        ['href' => '/users', 'label' => 'Team & roles', 'icon' => 'users', 'owner' => true],
        ['href' => '/activity', 'label' => 'Activity log', 'icon' => 'history', 'can' => 'activity', 'feature' => 'activity'],
        ['href' => '/backup', 'label' => 'Backup & export', 'icon' => 'database', 'can' => 'backup', 'feature' => 'backup'],
        ['href' => '/license', 'label' => 'Plan & upgrade', 'icon' => 'crown', 'owner' => true],
    ]],
];
