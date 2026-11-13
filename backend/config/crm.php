<?php

declare(strict_types=1);

/* Lead pipeline (enquiries.stage). `status` = the matching Basic-plan inbox status kept in sync. */
return [
    'stages' => [
        'new'         => ['label' => 'New',         'color' => 'blue',   'status' => 'new'],
        'contacted'   => ['label' => 'Contacted',   'color' => 'azure',  'status' => 'contacted'],
        'quoted'      => ['label' => 'Quote sent',  'color' => 'purple', 'status' => 'quoted'],
        'negotiation' => ['label' => 'Negotiation', 'color' => 'orange', 'status' => 'quoted'],
        'won'         => ['label' => 'Won',         'color' => 'green',  'status' => 'booked'],
        'lost'        => ['label' => 'Lost',        'color' => 'red',    'status' => 'closed'],
    ],
    'sources' => ['website' => 'Website', 'whatsapp' => 'WhatsApp', 'phone' => 'Phone call', 'instagram' => 'Instagram',
                  'facebook' => 'Facebook', 'referral' => 'Referral', 'walk-in' => 'Walk-in', 'repeat' => 'Repeat customer', 'other' => 'Other'],
    'lost_reasons' => ['Price too high', 'Booked with another agent', 'Trip postponed / cancelled', 'No response', 'Dates not available', 'Other'],
];
