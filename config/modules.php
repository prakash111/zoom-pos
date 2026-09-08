<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Module catalog (fallback)
    |--------------------------------------------------------------------------
    |
    | The sellable add-on verticals shown on SuperAdmin -> Modules -> "Available
    | modules". When the License Manager is reachable its /api/v1/catalog wins
    | (price, extra products); this list is what the operator sees before that,
    | and it never needs the module source to be present locally.
    |
    | `slug` MUST match the module.json "key" and the License Manager product
    | slug. The source ZIP is downloaded from the License Manager after the
    | license key verifies — nothing is shipped in the build.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Registration-mode gating
    |--------------------------------------------------------------------------
    |
    | `free`    — store types always selectable at tenant signup, no licence.
    | `premium` — registration-mode key => the catalog/package slug whose
    |             licence unlocks it. Until that module is licensed & active the
    |             SuperAdmin Module Governance card shows it as "Not purchased"
    |             with a "Purchase to activate" button, and it cannot be enabled.
    |
    */
    'registration' => [
        'free' => ['retail', 'restaurant'],
        'premium' => [
            'pharmacy' => 'pharmacy',
            'service_booking' => 'salon',
            'repair_technician' => 'repairtechnician',
        ],
    ],

    'catalog' => [
        [
            'slug' => 'pharmacy',
            'name' => 'Pharmacy POS Module',
            'description' => 'Drug batch & expiry tracking, prescription intake and dispensing.',
        ],
        [
            'slug' => 'salon',
            'name' => 'Salon & Bookings Module',
            'description' => 'Service catalogue, stylists / specialists, appointment booking and lifecycle.',
        ],
        [
            'slug' => 'repairtechnician',
            'name' => 'Repair & Service Workbench Module',
            'description' => 'Device intake tickets, diagnostic checklist, parts & labour, pickup lifecycle.',
        ],
    ],

];
