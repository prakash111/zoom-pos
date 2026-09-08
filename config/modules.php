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
