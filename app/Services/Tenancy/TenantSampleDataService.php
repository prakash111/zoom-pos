<?php

namespace App\Services\Tenancy;

use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\DiningFloor;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\PharmacyBatch;
use App\Models\PharmacyPrescription;
use App\Models\Product;
use App\Models\RepairDeviceCategory;
use App\Models\RepairTicket;
use App\Models\RepairTicketItem;
use App\Models\Sale;
use App\Models\SalonAppointment;
use App\Models\ServiceOrder;
use App\Models\TaxRule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantSampleDataService
{
    /**
     * Seeds demo data matching the company's active POS mode.
     */
    public function seed(Company $company, string $posMode = 'general', ?User $admin = null): void
    {
        $normalizedMode = strtolower(trim($posMode));
        if ($normalizedMode === 'general') {
            $normalizedMode = 'retail';
        }

        DB::transaction(function () use ($company, $normalizedMode, $admin) {
            match ($normalizedMode) {
                'restaurant' => $this->seedRestaurant($company, $admin),
                'pharmacy' => $this->seedPharmacy($company, $admin),
                'service_booking' => $this->seedServiceBooking($company, $admin),
                'repair', 'repair_technician' => $this->seedRepairTechnician($company, $admin),
                default => $this->seedRetail($company, $admin),
            };

            // Store profile, document prefixes, T&C / bank details and a
            // placeholder brand logo — everything the receipt/invoice/quote
            // templates and the drawer header render.
            $this->seedBusinessProfile($company, $normalizedMode);

            // Cross-cutting business history every mode shares: more customers,
            // a spread of dated sales (drives the dashboard charts, reports,
            // top products & cash flow), a few on-credit invoices (receivables
            // + customer ledger, via SaleObserver) and open quotations.
            $this->seedShared($company, $admin, $normalizedMode);

            $company->update(['is_seeding_complete' => true]);
        });
    }

    /**
     * Store identity, document number prefixes, Terms & Conditions / bank
     * details and a generated placeholder logo/favicon/cover — populated so a
     * fresh demo tenant's invoices, quotes and receipts are not blank.
     * Overwrites (demo data), safe to re-run.
     */
    public function seedBusinessProfile(Company $company, string $mode): void
    {
        $profiles = [
            'retail' => [
                'trade' => 'Metro Retail Mart', 'emoji' => '🛒', 'color' => '#2563EB',
                'inv' => 'INV-', 'quo' => 'QUO-',
                'address' => '14 Market Street, MG Road', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal' => '560001',
                'phone' => '+91 80 4000 1200', 'website' => 'https://metromart.demo',
            ],
            'restaurant' => [
                'trade' => 'The Copper Kettle Café', 'emoji' => '🍽️', 'color' => '#D97706',
                'inv' => 'INV-', 'quo' => 'QUO-',
                'address' => '2 Brigade Lane, Indiranagar', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal' => '560038',
                'phone' => '+91 80 4111 8080', 'website' => 'https://copperkettle.demo',
            ],
            'pharmacy' => [
                'trade' => 'CareFirst Pharmacy', 'emoji' => '💊', 'color' => '#059669',
                'inv' => 'INV-', 'quo' => 'QUO-',
                'address' => '7 Health Avenue, Jayanagar', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal' => '560011',
                'phone' => '+91 80 2233 4455', 'website' => 'https://carefirst.demo',
            ],
            'repair_technician' => [
                'trade' => 'FixPoint Device Repairs', 'emoji' => '🔧', 'color' => '#0284C7',
                'inv' => 'INV-', 'quo' => 'QUO-',
                'address' => '19 Tech Park Road, Koramangala', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal' => '560095',
                'phone' => '+91 80 5566 7788', 'website' => 'https://fixpoint.demo',
            ],
            'service_booking' => [
                'trade' => 'Lumière Salon & Spa', 'emoji' => '✂️', 'color' => '#7C3AED',
                'inv' => 'INV-', 'quo' => 'QUO-',
                'address' => '5 Boulevard Court, HSR Layout', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal' => '560102',
                'phone' => '+91 80 6677 9900', 'website' => 'https://lumiere.demo',
            ],
        ];

        $p = $profiles[$mode] ?? $profiles['retail'];
        $legal = $p['trade'].' Pvt. Ltd.';
        $slug = Str::slug($p['trade']);

        $invoiceTerms = implode("\n", [
            '1. Payment is due within 14 days of the invoice date unless otherwise agreed in writing.',
            '2. Goods once sold may be returned within 7 days with the original receipt and intact packaging.',
            '3. All prices are inclusive of applicable taxes unless stated otherwise.',
            '4. Warranty, where applicable, is as per the manufacturer / service terms overleaf.',
            '5. All disputes are subject to '.$p['city'].' jurisdiction.',
        ]);

        $quoteTerms = implode("\n", [
            'This quotation is valid for 15 days from the date of issue; prices are subject to change thereafter.',
            'A 50% advance is required to confirm the order. Balance is payable on delivery / completion.',
            'Delivery / completion timelines are indicative and begin from the date of confirmed advance.',
            'Taxes will be charged as applicable on the final invoice.',
        ]);

        $bankDetails = implode("\n", [
            'Account Name: '.$legal,
            'Bank: Demo Bank of India, MG Road Branch',
            'A/C No: 0000 1111 2222 4021',
            'IFSC: DEMO0001234',
            'UPI: '.$slug.'@demobank',
            'Please quote the invoice number as the payment reference.',
        ]);

        // Fill only what the tenant hasn't set — never clobber a real
        // (or test-provided) trade name, address, logo, terms, etc.
        $fillIfBlank = [
            'trade_name' => $p['trade'],
            'legal_name' => $legal,
            'tax_id' => '29ABCDE1234F1Z5',
            'tax_id_label' => 'GSTIN',
            'phone' => $p['phone'],
            'website' => $p['website'],
            'address' => $p['address'],
            'city' => $p['city'],
            'state' => $p['state'],
            'postal_code' => $p['postal'],
            'invoice_prefix' => $p['inv'],
            'quotation_prefix' => $p['quo'],
            'repair_prefix' => 'RPR',
            'prescription_prefix' => 'RX',
            'salon_prefix' => 'APT',
            'invoice_terms' => $invoiceTerms,
            'quote_terms' => $quoteTerms,
            'bank_details' => $bankDetails,
            'primary_color' => $p['color'],
            'logo' => fn () => $this->putDemoAsset("{$mode}-logo.svg", $this->demoBrandSvg($p['emoji'], $p['color'], 512, 512)),
            'favicon' => fn () => $this->putDemoAsset("{$mode}-favicon.svg", $this->demoBrandSvg($p['emoji'], $p['color'], 96, 96)),
            'drawer_cover' => fn () => $this->putDemoAsset("{$mode}-cover.svg", $this->demoCoverSvg($p['color'])),
        ];

        if ($mode === 'pharmacy') {
            $fillIfBlank['dispensing_disclaimer'] = implode("\n", [
                'Prescription (Schedule H / H1 / X) medicines are dispensed only against a valid prescription from a registered medical practitioner.',
                'Please read the package insert before use and complete the full course as advised.',
                'Medicines once sold are not returnable except for a manufacturing defect verified by the store.',
                'Store below 25°C, away from direct sunlight and out of reach of children.',
            ]);
        } elseif (in_array($mode, ['repair', 'repair_technician'], true)) {
            $fillIfBlank['repair_warranty_terms'] = implode("\n", [
                'Repairs carry a 30-day warranty covering only the replaced part and the workmanship performed.',
                'Physical damage, liquid ingress or third-party tampering after service voids the warranty.',
                'The store is not responsible for data loss — please back up your device before handing it over.',
                'Devices not collected within 30 days of the completion notice may attract storage charges.',
            ]);
        } elseif ($mode === 'service_booking') {
            $fillIfBlank['salon_policy_terms'] = implode("\n", [
                'Please arrive 10 minutes before your scheduled appointment.',
                'Cancellations within 4 hours of the appointment, and no-shows, are charged 50% of the service value.',
                'Prepaid packages are non-transferable and valid for 6 months from the date of purchase.',
                'A patch test is recommended at least 24 hours before any colour or chemical service.',
            ]);
        }

        $updates = [];
        foreach ($fillIfBlank as $column => $value) {
            if (blank($company->{$column})) {
                $updates[$column] = is_callable($value) ? $value() : $value;
            }
        }
        if ($updates !== []) {
            $company->forceFill($updates)->save();
        }
    }

    /**
     * Write a generated demo brand asset to the public disk and return the
     * short relative path stored on the company (the `logo` column is only
     * varchar(255), so a data: URI won't fit). getLogoUrl()/getFaviconUrl()
     * resolve it to /storage/demo-branding/<name>.
     */
    private function putDemoAsset(string $name, string $svg): string
    {
        $path = 'demo-branding/'.$name;
        if (str_starts_with($svg, 'data:image/svg+xml;base64,')) {
            $svg = base64_decode(substr($svg, strlen('data:image/svg+xml;base64,')), true) ?: $svg;
        }
        Storage::disk('public')->put($path, $svg);

        return $path;
    }

    /** Inline square brand badge SVG. */
    private function demoBrandSvg(string $emoji, string $bg, int $w, int $h): string
    {
        $fs = (int) round(min($w, $h) * 0.52);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'">'
            .'<rect width="'.$w.'" height="'.$h.'" rx="'.(int) round($w * 0.18).'" fill="'.$bg.'"/>'
            .'<text x="50%" y="50%" dy="0.06em" font-size="'.$fs.'" text-anchor="middle" dominant-baseline="central">'.$emoji.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** Wide gradient cover banner (data: URI) for the drawer header. */
    private function demoCoverSvg(string $bg): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="400" viewBox="0 0 1200 400">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
            .'<stop offset="0" stop-color="'.$bg.'"/><stop offset="1" stop-color="#0F172A"/>'
            .'</linearGradient></defs><rect width="1200" height="400" fill="url(#g)"/>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Mode-agnostic demo history: extra customers, ~24 dated sales across the
     * last three weeks (some part-paid / on credit), and a handful of
     * quotations. Idempotent — keyed on stable demo document numbers.
     */
    public function seedShared(Company $company, ?User $admin, string $mode): void
    {
        $companyId = $company->id;
        $modeTag = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $mode) ?: 'GEN');

        foreach ([
            ['name' => 'Priya Nair', 'email' => 'priya.nair@example.com', 'phone' => '+14155550101'],
            ['name' => 'Daniel Kim', 'email' => 'daniel.kim@example.com', 'phone' => '+14155550102'],
            ['name' => 'Sofia Rossi', 'email' => 'sofia.rossi@example.com', 'phone' => '+14155550103'],
            ['name' => 'Marcus Bennett', 'email' => 'marcus.bennett@example.com', 'phone' => '+14155550104'],
            ['name' => 'Ava Thompson', 'email' => 'ava.thompson@example.com', 'phone' => '+14155550105'],
        ] as $c) {
            Customer::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'name' => $c['name'],
            ], [
                'email' => $c['email'],
                'phone' => $c['phone'],
                'person_type' => 'individual',
                'is_demo' => true,
                'due_balance' => 0.00,
            ]);
        }

        $customers = Customer::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('is_demo', true)
            ->orderBy('id')->get()->values();
        if ($customers->isEmpty()) {
            return;
        }

        $products = Product::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('is_demo', true)->where('active', true)
            ->orderBy('id')->get()->values();

        $methods = ['cash', 'card', 'upi', 'card', 'cash'];

        // [days ago, hour, line items, fraction still owed]
        $plan = [
            [0, 9, 2, 0.0], [0, 11, 1, 0.0], [0, 13, 3, 0.0], [0, 16, 2, 0.0], [0, 19, 1, 0.0],
            [1, 10, 2, 0.0], [1, 15, 2, 1.0], [1, 18, 1, 0.0],
            [2, 12, 3, 0.0], [2, 17, 1, 0.0],
            [3, 10, 2, 0.0], [3, 14, 2, 0.4],
            [4, 11, 1, 0.0], [4, 16, 3, 0.0],
            [6, 13, 2, 0.0], [7, 10, 1, 0.0], [8, 15, 2, 1.0],
            [10, 12, 3, 0.0], [12, 11, 2, 0.0], [14, 16, 1, 0.0],
            [16, 13, 2, 0.5], [18, 10, 2, 0.0], [20, 14, 3, 0.0], [20, 18, 1, 0.0],
        ];

        foreach ($plan as $i => [$daysAgo, $hour, $lineCount, $dueFraction]) {
            $number = sprintf('DEMO-%s-INV-%03d', $modeTag, $i + 1);
            if (Sale::withoutGlobalScopes()->where('company_id', $companyId)->where('sale_number', $number)->exists()) {
                continue;
            }

            $customer = $customers[$i % $customers->count()];
            $items = [];
            $subtotal = 0.0;
            if ($products->isNotEmpty()) {
                for ($k = 0; $k < $lineCount; $k++) {
                    $p = $products[($i + $k) % $products->count()];
                    $qty = 1 + (($i + $k) % 3);
                    $price = round((float) ($p->sale_price ?: 1), 2);
                    $line = round($qty * $price, 2);
                    $subtotal += $line;
                    $items[] = [
                        'product_id' => $p->id,
                        'name' => $p->name,
                        'quantity' => $qty,
                        'price' => $price,
                        'total' => $line,
                    ];
                }
            } else {
                $subtotal = round(18 + $i * 6.5, 2);
            }

            $total = round($subtotal, 2);
            $due = round($total * $dueFraction, 2);
            $paid = round($total - $due, 2);
            $when = now()->subDays($daysAgo)->setTime($hour, ($i * 7) % 60, 0);

            $sale = Sale::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'sale_number' => $number,
                'operation_type' => 'sale',
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'user_id' => $admin?->id,
                'total' => $total,
                'net_amount' => $total,
                'paid_amount' => $paid,
                'due_amount' => $due,
                'due_date' => $due > 0 ? now()->subDays($daysAgo)->addDays(14)->toDateString() : null,
                'payment_method' => $due > 0 ? 'on_credit' : $methods[$i % count($methods)],
                'payment_status' => $due > 0 ? ($paid > 0 ? 'partial' : 'due') : 'paid',
                'status' => 'completed',
                'is_demo' => true,
                'items' => $items,
            ]);

            Sale::withoutGlobalScopes()->whereKey($sale->id)
                ->update(['created_at' => $when, 'updated_at' => $when]);
        }

        // Open quotations for the Quotations module.
        foreach (['draft', 'sent', 'sent'] as $q => $status) {
            $number = sprintf('DEMO-%s-QUO-%02d', $modeTag, $q + 1);
            if (Sale::withoutGlobalScopes()->where('company_id', $companyId)->where('sale_number', $number)->exists()) {
                continue;
            }

            $customer = $customers[($q + 1) % $customers->count()];
            $items = [];
            $subtotal = 0.0;
            if ($products->isNotEmpty()) {
                for ($k = 0; $k < 2; $k++) {
                    $p = $products[($q + $k) % $products->count()];
                    $price = round((float) ($p->sale_price ?: 1), 2);
                    $subtotal += $price * 2;
                    $items[] = [
                        'product_id' => $p->id,
                        'name' => $p->name,
                        'quantity' => 2,
                        'price' => $price,
                        'total' => round($price * 2, 2),
                    ];
                }
            } else {
                $subtotal = 110.0 + $q * 25;
            }

            Sale::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'sale_number' => $number,
                'operation_type' => 'quotation',
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'user_id' => $admin?->id,
                'total' => round($subtotal, 2),
                'net_amount' => round($subtotal, 2),
                'paid_amount' => 0,
                'due_amount' => 0,
                'payment_method' => 'cash',
                'payment_status' => 'pending',
                'status' => $status,
                'is_demo' => true,
                'items' => $items,
            ]);
        }
    }

    /**
     * Mode: Retail POS.
     */
    public function seedRetail(Company $company, ?User $admin = null): void
    {
        $companyId = $company->id;

        // 1. Categories
        $categoriesData = [
            ['name' => 'Beverages', 'color' => '#06b6d4', 'description' => 'Chilled beverages, juices, and specialty drinks'],
            ['name' => 'Packaged Snacks', 'color' => '#a855f7', 'description' => 'Crisps, energy bars, and packaged sweets'],
            ['name' => 'Electronics & Accessories', 'color' => '#3b82f6', 'description' => 'Cables, chargers, and mobile gadgets'],
            ['name' => 'Household Goods', 'color' => '#10b981', 'description' => 'Everyday cleaning and personal care essentials'],
        ];

        $categories = [];
        foreach ($categoriesData as $c) {
            $categories[$c['name']] = Category::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'name' => $c['name'],
            ], [
                'color' => $c['color'],
                'description' => $c['description'],
                'type' => 'retail',
                'active' => true,
                'is_demo' => true,
            ]);
        }

        // 2. Products & Inventory
        $productsData = [
            [
                'name' => 'Organic Cold Brew Coffee (330ml)',
                'category' => 'Beverages',
                'sku' => 'RET-BEV-001',
                'barcode' => '890103000001',
                'cost_price' => 1.50,
                'sale_price' => 3.50,
                'current_stock' => 45,
                'unit' => 'bottle',
                'image_url' => 'https://images.unsplash.com/photo-1517256064527-09c73fc73e38?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Sparkling Mineral Water (500ml)',
                'category' => 'Beverages',
                'sku' => 'RET-BEV-002',
                'barcode' => '890103000002',
                'cost_price' => 0.80,
                'sale_price' => 2.00,
                'current_stock' => 50,
                'unit' => 'bottle',
                'image_url' => 'https://images.unsplash.com/photo-1559839914-17aae19cec71?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Artisan Potato Crisps - Sea Salt',
                'category' => 'Packaged Snacks',
                'sku' => 'RET-SNK-001',
                'barcode' => '890103000003',
                'cost_price' => 1.20,
                'sale_price' => 2.75,
                'current_stock' => 35,
                'unit' => 'pack',
                'image_url' => 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Dark Chocolate & Almond Energy Bar',
                'category' => 'Packaged Snacks',
                'sku' => 'RET-SNK-002',
                'barcode' => '890103000004',
                'cost_price' => 1.00,
                'sale_price' => 2.50,
                'current_stock' => 40,
                'unit' => 'bar',
                'image_url' => 'https://images.unsplash.com/photo-1541781774459-bb2af2f05b55?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Braided USB-C Fast Charging Cable 2m',
                'category' => 'Electronics & Accessories',
                'sku' => 'RET-ELE-001',
                'barcode' => '890103000005',
                'cost_price' => 4.50,
                'sale_price' => 12.99,
                'current_stock' => 30,
                'unit' => 'pcs',
                'image_url' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Compact Dual-Port USB-A/C Wall Adapter',
                'category' => 'Electronics & Accessories',
                'sku' => 'RET-ELE-002',
                'barcode' => '890103000006',
                'cost_price' => 6.00,
                'sale_price' => 18.50,
                'current_stock' => 25,
                'unit' => 'pcs',
                'image_url' => 'https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Eco-Friendly Bamboo Toothbrush (4-Pack)',
                'category' => 'Household Goods',
                'sku' => 'RET-HSE-001',
                'barcode' => '890103000007',
                'cost_price' => 2.50,
                'sale_price' => 6.99,
                'current_stock' => 40,
                'unit' => 'pack',
                'image_url' => 'https://images.unsplash.com/photo-1607613009820-a29f7bb81c04?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Lavender & Tea Tree Hand Sanitizer 250ml',
                'category' => 'Household Goods',
                'sku' => 'RET-HSE-002',
                'barcode' => '890103000008',
                'cost_price' => 1.80,
                'sale_price' => 4.50,
                'current_stock' => 50,
                'unit' => 'bottle',
                'image_url' => 'https://images.unsplash.com/photo-1584744982491-665216d95f8b?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Microfiber Multi-Surface Cleaning Cloths (3pk)',
                'category' => 'Household Goods',
                'sku' => 'RET-HSE-003',
                'barcode' => '890103000009',
                'cost_price' => 1.90,
                'sale_price' => 4.99,
                'current_stock' => 30,
                'unit' => 'pack',
                'image_url' => 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=500&auto=format&fit=crop&q=80',
            ],
        ];

        $createdProducts = [];
        foreach ($productsData as $p) {
            $cat = $categories[$p['category']] ?? null;
            $createdProducts[] = Product::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'code' => $p['sku'],
            ], [
                'name' => $p['name'],
                'code' => $p['sku'],
                'barcode' => $p['barcode'],
                'category_id' => $cat?->id,
                'category_name' => $cat?->name,
                'cost_price' => $p['cost_price'],
                'sale_price' => $p['sale_price'],
                'current_stock' => $p['current_stock'],
                'minimum_stock' => 5,
                'unit' => $p['unit'],
                'image_url' => $p['image_url'],
                'active' => true,
                'is_demo' => true,
            ]);
        }

        // 3. Tax Defaults
        $this->seedTaxRuleForCountry($company);

        // 4. Customers
        $walkIn = Customer::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'name' => 'Walk-in Customer',
        ], [
            'person_type' => 'individual',
            'is_demo' => true,
            'due_balance' => 0.00,
        ]);

        $johnDoe = Customer::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'name' => 'John Doe',
        ], [
            'email' => 'john.doe@example.com',
            'phone' => '+1-555-0142',
            'person_type' => 'individual',
            'is_demo' => true,
            'due_balance' => 0.00,
        ]);

        // 5. Sample Invoices (1 Paid, 1 Due)
        $p1 = $createdProducts[0] ?? null;
        $p2 = $createdProducts[2] ?? null;

        // Paid Invoice
        Sale::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'sale_number' => 'INV-DEMO-001',
        ], [
            'customer_id' => $walkIn->id,
            'customer_name' => $walkIn->name,
            'user_id' => $admin?->id,
            'total' => 34.50,
            'net_amount' => 34.50,
            'paid_amount' => 34.50,
            'due_amount' => 0.00,
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'status' => 'completed',
            'is_demo' => true,
            'items' => [
                [
                    'product_id' => $p1?->id,
                    'name' => $p1?->name ?? 'Organic Cold Brew Coffee',
                    'quantity' => 4,
                    'price' => 3.50,
                    'total' => 14.00,
                ],
                [
                    'product_id' => $p2?->id,
                    'name' => $p2?->name ?? 'Artisan Potato Crisps',
                    'quantity' => 6,
                    'price' => 2.75,
                    'total' => 16.50,
                ],
            ],
        ]);

        // Due / Unpaid Invoice with upcoming due date
        Sale::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'sale_number' => 'INV-DEMO-002',
        ], [
            'customer_id' => $johnDoe->id,
            'customer_name' => $johnDoe->name,
            'user_id' => $admin?->id,
            'total' => 150.00,
            'net_amount' => 150.00,
            'paid_amount' => 0.00,
            'due_amount' => 150.00,
            'due_date' => now()->addDays(7)->toDateString(),
            'due_reminder_at' => now()->addDays(5),
            'payment_method' => 'on_credit',
            'payment_status' => 'due',
            'status' => 'completed',
            'is_demo' => true,
            'notes' => 'Sample store credit invoice for John Doe with 7-day payment reminder.',
            'items' => [
                [
                    'product_id' => $createdProducts[4]?->id ?? 5,
                    'name' => 'Braided USB-C Fast Charging Cable 2m',
                    'quantity' => 5,
                    'price' => 12.99,
                    'total' => 64.95,
                ],
                [
                    'product_id' => $createdProducts[5]?->id ?? 6,
                    'name' => 'Compact Dual-Port USB-A/C Wall Adapter',
                    'quantity' => 4,
                    'price' => 18.50,
                    'total' => 74.00,
                ],
            ],
        ]);
    }

    /**
     * Mode: Cafe & Restaurant POS.
     */
    public function seedRestaurant(Company $company, ?User $admin = null): void
    {
        $companyId = $company->id;

        // 1. Dining Floors (Main Dining Hall & Outdoor Patio)
        $mainFloor = DiningFloor::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'name' => 'Main Dining Hall',
        ], [
            'order_index' => 1,
            'is_active' => true,
            'is_demo' => true,
        ]);

        $patioFloor = DiningFloor::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'name' => 'Outdoor Patio',
        ], [
            'order_index' => 2,
            'is_active' => true,
            'is_demo' => true,
        ]);

        // 2. 6 Numbered Tables (T-01 to T-06)
        $tablesConfig = [
            ['floor' => $mainFloor, 'number' => 'T-01', 'capacity' => 2, 'status' => 'available'],
            ['floor' => $mainFloor, 'number' => 'T-02', 'capacity' => 4, 'status' => 'occupied', 'guests' => 3],
            ['floor' => $mainFloor, 'number' => 'T-03', 'capacity' => 4, 'status' => 'available'],
            ['floor' => $mainFloor, 'number' => 'T-04', 'capacity' => 6, 'status' => 'reserved'],
            ['floor' => $patioFloor, 'number' => 'T-05', 'capacity' => 2, 'status' => 'available'],
            ['floor' => $patioFloor, 'number' => 'T-06', 'capacity' => 4, 'status' => 'available'],
        ];

        $tables = [];
        foreach ($tablesConfig as $t) {
            $tables[$t['number']] = DiningTable::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'table_number' => $t['number'],
            ], [
                'dining_floor_id' => $t['floor']->id,
                'seating_capacity' => $t['capacity'],
                'status' => $t['status'],
                'guest_count' => $t['guests'] ?? 0,
                'is_active' => true,
                'is_demo' => true,
            ]);
        }

        // 3. Menu Categories
        $categoriesData = [
            ['name' => 'Starters', 'color' => '#f59e0b', 'description' => 'Appetizers, soups, and shared plates'],
            ['name' => 'Main Course', 'color' => '#ef4444', 'description' => 'Burgers, artisan pizzas, and chef specials'],
            ['name' => 'Hot Beverages', 'color' => '#8b5cf6', 'description' => 'Espressos, lattes, and specialty teas'],
            ['name' => 'Desserts', 'color' => '#ec4899', 'description' => 'Cakes, pastries, and artisanal ice creams'],
        ];

        $categories = [];
        foreach ($categoriesData as $c) {
            $categories[$c['name']] = Category::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'name' => $c['name'],
            ], [
                'color' => $c['color'],
                'description' => $c['description'],
                'type' => 'restaurant',
                'active' => true,
                'is_demo' => true,
            ]);
        }

        // 4. Menu Items with Prep Times (10m, 15m)
        $menuItems = [
            [
                'name' => 'Crispy Garlic Bruschetta',
                'category' => 'Starters',
                'sku' => 'RES-STR-001',
                'cost_price' => 2.00,
                'sale_price' => 6.50,
                'prep_minutes' => 10,
                'image_url' => 'https://images.unsplash.com/photo-1572695157366-5e585ab2b69f?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Truffle Mushroom Burger',
                'category' => 'Main Course',
                'sku' => 'RES-MN-001',
                'cost_price' => 4.50,
                'sale_price' => 12.50,
                'prep_minutes' => 15,
                'image_url' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Wood-Fired Margherita Pizza',
                'category' => 'Main Course',
                'sku' => 'RES-MN-002',
                'cost_price' => 4.00,
                'sale_price' => 14.00,
                'prep_minutes' => 15,
                'image_url' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Single Origin Cappuccino',
                'category' => 'Hot Beverages',
                'sku' => 'RES-BEV-001',
                'cost_price' => 1.00,
                'sale_price' => 4.25,
                'prep_minutes' => 5,
                'image_url' => 'https://images.unsplash.com/photo-1534778101976-62847782c213?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Belgian Chocolate Lava Cake',
                'category' => 'Desserts',
                'sku' => 'RES-DES-001',
                'cost_price' => 2.50,
                'sale_price' => 7.00,
                'prep_minutes' => 10,
                'image_url' => 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=500&auto=format&fit=crop&q=80',
            ],
        ];

        $products = [];
        foreach ($menuItems as $m) {
            $cat = $categories[$m['category']] ?? null;
            $products[$m['name']] = Product::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'code' => $m['sku'],
            ], [
                'name' => $m['name'],
                'code' => $m['sku'],
                'category_id' => $cat?->id,
                'category_name' => $cat?->name,
                'cost_price' => $m['cost_price'],
                'sale_price' => $m['sale_price'],
                'duration_minutes' => $m['prep_minutes'],
                'current_stock' => 100,
                'minimum_stock' => 10,
                'unit' => 'portion',
                'image_url' => $m['image_url'],
                'active' => true,
                'is_demo' => true,
            ]);
        }

        // 5. Active Order & KOT on Table T-02 (Sent to Kitchen, 10 min countdown)
        $t2 = $tables['T-02'] ?? null;
        if ($t2) {
            $activeSale = Sale::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'sale_number' => 'ORD-TAB-002',
            ], [
                'dining_table_id' => $t2->id,
                'table_name' => 'T-02',
                'guest_count' => 3,
                'status' => 'in_progress',
                'kot_status' => 'in_kitchen',
                'service_type' => 'dine_in',
                'total' => 33.25,
                'net_amount' => 33.25,
                'paid_amount' => 0.00,
                'due_amount' => 33.25,
                'user_id' => $admin?->id,
                'is_demo' => true,
                'items' => [
                    [
                        'name' => 'Crispy Garlic Bruschetta',
                        'quantity' => 1,
                        'price' => 6.50,
                        'total' => 6.50,
                        'prep_minutes' => 10,
                    ],
                    [
                        'name' => 'Truffle Mushroom Burger',
                        'quantity' => 1,
                        'price' => 12.50,
                        'total' => 12.50,
                        'prep_minutes' => 15,
                    ],
                    [
                        'name' => 'Wood-Fired Margherita Pizza',
                        'quantity' => 1,
                        'price' => 14.00,
                        'total' => 14.00,
                        'prep_minutes' => 15,
                    ],
                ],
            ]);

            $t2->update(['current_sale_id' => $activeSale->id]);

            KitchenTicket::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'kot_number' => 'KOT-DEMO-001',
            ], [
                'sale_id' => $activeSale->id,
                'dining_table_id' => $t2->id,
                'table_name' => 'T-02',
                'service_type' => 'dine_in',
                'status' => 'sent_to_kitchen',
                'server_name' => $admin?->name ?? 'Head Waiter',
                'sent_to_kitchen_at' => now()->subMinutes(5),
                'prep_minutes' => 15,
                'target_completion_at' => now()->addMinutes(10),
                'alarm_at' => now()->addMinutes(10),
                'is_demo' => true,
                'items' => $activeSale->items,
                'kitchen_notes' => 'Extra crispy crust on the Margherita pizza.',
            ]);
        }

        // More kitchen tickets across the KDS lifecycle so the Kitchen Display
        // and the dine-in / takeaway / delivery queues have real depth.
        $menuItems = Product::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('is_demo', true)->where('active', true)
            ->orderBy('id')->get()->values();

        $kotPlan = [
            ['num' => 'KOT-DEMO-002', 'table' => 'T-04', 'type' => 'dine_in', 'status' => KitchenTicket::STATUS_PREPARING, 'offset' => -12],
            ['num' => 'KOT-DEMO-003', 'table' => null, 'type' => 'takeaway', 'status' => KitchenTicket::STATUS_READY, 'offset' => -22],
            ['num' => 'KOT-DEMO-004', 'table' => null, 'type' => 'delivery', 'status' => KitchenTicket::STATUS_PENDING, 'offset' => -3],
            ['num' => 'KOT-DEMO-005', 'table' => 'T-05', 'type' => 'dine_in', 'status' => KitchenTicket::STATUS_SERVED, 'offset' => -55],
        ];

        foreach ($kotPlan as $ix => $k) {
            if (KitchenTicket::withoutGlobalScopes()->where('company_id', $companyId)->where('kot_number', $k['num'])->exists()) {
                continue;
            }

            $lineItems = [];
            $lineTotal = 0.0;
            for ($j = 0; $j < 2 && $menuItems->isNotEmpty(); $j++) {
                $mp = $menuItems[($ix + $j) % $menuItems->count()];
                $price = round((float) ($mp->sale_price ?: 5), 2);
                $lineTotal += $price;
                $lineItems[] = [
                    'product_id' => $mp->id,
                    'name' => $mp->name,
                    'quantity' => 1,
                    'price' => $price,
                    'total' => $price,
                    'prep_minutes' => 12,
                ];
            }

            $isDone = $k['status'] === KitchenTicket::STATUS_SERVED;
            $kotSale = Sale::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                    'sale_number' => 'KOT-'.str_pad((string) ($ix + 2), 3, '0', STR_PAD_LEFT),
            ], [
                'operation_type' => 'sale',
                'user_id' => $admin?->id,
                'total' => round($lineTotal, 2),
                'net_amount' => round($lineTotal, 2),
                'paid_amount' => $isDone ? round($lineTotal, 2) : 0.0,
                'due_amount' => $isDone ? 0.0 : round($lineTotal, 2),
                'payment_status' => $isDone ? 'paid' : 'pending',
                'status' => $isDone ? 'completed' : 'in_progress',
                'service_type' => $k['type'],
                'is_demo' => true,
                'items' => $lineItems,
            ]);

            KitchenTicket::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'kot_number' => $k['num'],
            ], [
                'sale_id' => $kotSale->id,
                'dining_table_id' => $k['table'] ? ($tables[$k['table']]->id ?? null) : null,
                'table_name' => $k['table'],
                'service_type' => $k['type'],
                'status' => $k['status'],
                'server_name' => $admin?->name ?? 'Head Waiter',
                'sent_to_kitchen_at' => now()->addMinutes($k['offset']),
                'prep_minutes' => 15,
                'target_completion_at' => now()->addMinutes($k['offset'] + 15),
                'alarm_at' => now()->addMinutes($k['offset'] + 15),
                'is_demo' => true,
                'items' => $lineItems,
            ]);
        }

        $this->seedTaxRuleForCountry($company);
    }

    /**
     * Mode: Pharmacy POS.
     */
    public function seedPharmacy(Company $company, ?User $admin = null): void
    {
        $companyId = $company->id;

        // 1. Medicine Categories
        $categoriesData = [
            ['name' => 'Antibiotics', 'color' => '#ef4444', 'description' => 'Prescription antibacterial medications'],
            ['name' => 'Pain Relief', 'color' => '#f59e0b', 'description' => 'Analgesics, antipyretics, and anti-inflammatories'],
            ['name' => 'First Aid', 'color' => '#10b981', 'description' => 'Dressings, antiseptics, and emergency supplies'],
            ['name' => 'Vitamins & Supplements', 'color' => '#3b82f6', 'description' => 'Daily multivitamins, minerals, and wellness items'],
        ];

        $categories = [];
        foreach ($categoriesData as $c) {
            $categories[$c['name']] = Category::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'name' => $c['name'],
            ], [
                'color' => $c['color'],
                'description' => $c['description'],
                'type' => 'pharmacy',
                'active' => true,
                'is_demo' => true,
            ]);
        }

        // 2. Pharmaceutical Products with Batches, Expiries & Rx Flags
        $drugsData = [
            [
                'name' => 'Amoxicillin 500mg Capsules (10pk)',
                'category' => 'Antibiotics',
                'sku' => 'PHR-ANT-001',
                'barcode' => '890204000001',
                'batch_number' => 'BATCH-2026-AMX',
                'mfg_date' => '2025-11-10',
                'expiry_date' => '2027-11-10',
                'requires_prescription' => true,
                'cost_price' => 4.00,
                'sale_price' => 9.50,
                'current_stock' => 60,
                'unit' => 'pack',
                'image_url' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Azithromycin 250mg Tablets (6pk)',
                'category' => 'Antibiotics',
                'sku' => 'PHR-ANT-002',
                'barcode' => '890204000002',
                'batch_number' => 'BATCH-2026-AZT',
                'mfg_date' => '2026-01-15',
                'expiry_date' => '2028-01-15',
                'requires_prescription' => true,
                'cost_price' => 5.50,
                'sale_price' => 13.00,
                'current_stock' => 45,
                'unit' => 'pack',
                'image_url' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Paracetamol 500mg Rapid Relief (20pk)',
                'category' => 'Pain Relief',
                'sku' => 'PHR-PN-001',
                'barcode' => '890204000003',
                'batch_number' => 'BATCH-2026-PAR',
                'mfg_date' => '2026-02-01',
                'expiry_date' => '2028-02-01',
                'requires_prescription' => false,
                'cost_price' => 1.00,
                'sale_price' => 3.25,
                'current_stock' => 120,
                'unit' => 'strip',
                'image_url' => 'https://images.unsplash.com/photo-1550572017-ed200f545dec?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Ibuprofen 400mg Softgels (20pk)',
                'category' => 'Pain Relief',
                'sku' => 'PHR-PN-002',
                'barcode' => '890204000004',
                'batch_number' => 'BATCH-2026-IBU',
                'mfg_date' => '2025-08-01',
                // Near-expiry batch (~40 days) to trigger batch expiry warning
                'expiry_date' => Carbon::now()->addDays(40)->toDateString(),
                'requires_prescription' => false,
                'cost_price' => 2.00,
                'sale_price' => 5.50,
                'current_stock' => 50,
                'unit' => 'pack',
                'image_url' => 'https://images.unsplash.com/photo-1577401239170-897942555fb3?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Sterile Gauze Pads 10cm x 10cm (10pk)',
                'category' => 'First Aid',
                'sku' => 'PHR-FA-001',
                'barcode' => '890204000005',
                'batch_number' => 'BATCH-2026-GZ1',
                'mfg_date' => '2025-06-01',
                'expiry_date' => '2029-06-01',
                'requires_prescription' => false,
                'cost_price' => 1.20,
                'sale_price' => 3.50,
                'current_stock' => 80,
                'unit' => 'box',
                'image_url' => 'https://images.unsplash.com/photo-1584362917165-526a968579e8?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Antiseptic Povidone Iodine 100ml',
                'category' => 'First Aid',
                'sku' => 'PHR-FA-002',
                'barcode' => '890204000006',
                'batch_number' => 'BATCH-2026-PVD',
                'mfg_date' => '2025-09-01',
                'expiry_date' => '2028-09-01',
                'requires_prescription' => false,
                'cost_price' => 2.10,
                'sale_price' => 5.00,
                'current_stock' => 40,
                'unit' => 'bottle',
                'image_url' => 'https://images.unsplash.com/photo-1603398938378-e54eab446dde?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Vitamin C 1000mg + Zinc Effervescent (20pk)',
                'category' => 'Vitamins & Supplements',
                'sku' => 'PHR-VIT-001',
                'barcode' => '890204000007',
                'batch_number' => 'BATCH-2026-VTC',
                'mfg_date' => '2026-03-01',
                'expiry_date' => '2028-03-01',
                'requires_prescription' => false,
                'cost_price' => 3.00,
                'sale_price' => 7.50,
                'current_stock' => 75,
                'unit' => 'tube',
                'image_url' => 'https://images.unsplash.com/photo-1550572017-edd951aa8f72?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Omega-3 Triple Strength Fish Oil (60ct)',
                'category' => 'Vitamins & Supplements',
                'sku' => 'PHR-VIT-002',
                'barcode' => '890204000008',
                'batch_number' => 'BATCH-2026-OMG',
                'mfg_date' => '2025-12-01',
                'expiry_date' => '2027-12-01',
                'requires_prescription' => false,
                'cost_price' => 6.00,
                'sale_price' => 16.00,
                'current_stock' => 35,
                'unit' => 'bottle',
                'image_url' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=500&auto=format&fit=crop&q=80',
            ],
        ];

        foreach ($drugsData as $d) {
            $cat = $categories[$d['category']] ?? null;
            $product = Product::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'code' => $d['sku'],
            ], [
                'name' => $d['name'],
                'generic_name' => match ($d['category']) {
                    'Antibiotics' => str_contains($d['name'], 'Amoxicillin') ? 'Amoxicillin Trihydrate' : 'Azithromycin Dihydrate',
                    'Pain Relief' => str_contains($d['name'], 'Paracetamol') ? 'Acetaminophen (Paracetamol)' : 'Ibuprofen Micronized',
                    'First Aid' => str_contains($d['name'], 'Iodine') ? 'Povidone-Iodine Complex' : 'Sterile Hydrophilic Cotton',
                    default => 'Ascorbic Acid + Zinc Gluconate',
                },
                'composition' => match ($d['category']) {
                    'Antibiotics' => 'Active pharmaceutical ingredient 500mg, microcrystalline cellulose',
                    'Pain Relief' => 'Paracetamol BP 500mg, maize starch, sodium starch glycolate',
                    default => 'Nutraceutical therapeutic formulation',
                },
                'narcotic_schedule' => $d['requires_prescription'] ? 'Schedule H' : null,
                'code' => $d['sku'],
                'barcode' => $d['barcode'],
                'category_id' => $cat?->id,
                'category_name' => $cat?->name,
                'batch_number' => $d['batch_number'],
                'mfg_date' => $d['mfg_date'],
                'expiry_date' => $d['expiry_date'],
                'requires_prescription' => $d['requires_prescription'],
                'cost_price' => $d['cost_price'],
                'sale_price' => $d['sale_price'],
                'current_stock' => $d['current_stock'],
                'minimum_stock' => 10,
                'unit' => $d['unit'],
                'image_url' => $d['image_url'],
                'active' => true,
                'is_demo' => true,
            ]);

            // Seed FEFO batches for each medicine
            PharmacyBatch::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'product_id' => $product->id,
                'batch_number' => $d['batch_number'],
            ], [
                'tenant_id' => $companyId,
                'manufacturing_date' => $d['mfg_date'],
                'expiry_date' => $d['expiry_date'],
                'cost_price' => $d['cost_price'],
                'selling_price' => $d['sale_price'],
                'stock_qty' => (int) ($d['current_stock'] * 0.7),
                'alert_days_before_expiry' => 90,
                'is_active' => true,
                'is_demo' => true,
            ]);

            // Additional near-expiry or alternate batch for FEFO demonstration
            PharmacyBatch::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'product_id' => $product->id,
                'batch_number' => $d['batch_number'].'-ALT',
            ], [
                'tenant_id' => $companyId,
                'manufacturing_date' => Carbon::parse($d['mfg_date'])->subMonths(3)->toDateString(),
                'expiry_date' => Carbon::now()->addDays(rand(30, 85))->toDateString(),
                'cost_price' => $d['cost_price'],
                'selling_price' => $d['sale_price'],
                'stock_qty' => (int) ($d['current_stock'] * 0.3),
                'alert_days_before_expiry' => 90,
                'is_active' => true,
                'is_demo' => true,
            ]);
        }

        // 3. Patients / Customers
        $patient = Customer::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'name' => 'Jane Smith (Patient)',
        ], [
            'email' => 'jane.smith@example.com',
            'phone' => '+1-555-0198',
            'person_type' => 'individual',
            'is_demo' => true,
            'due_balance' => 0.00,
        ]);

        // 4. Sample Prescriptions (Pending & Dispensed)
        PharmacyPrescription::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'prescription_number' => 'RX-DEMO-2026-001',
        ], [
            'tenant_id' => $companyId,
            'customer_id' => $patient->id,
            'patient_name' => 'Jane Smith',
            'patient_phone' => '+1-555-0198',
            'doctor_name' => 'Dr. Robert Evans, MD',
            'doctor_registration_no' => 'MED-REG-88392',
            'prescription_date' => Carbon::today()->subDays(1),
            'diagnosis' => 'Acute Respiratory Infection / Bronchitis',
            'medicines' => [
                ['name' => 'Amoxicillin 500mg Capsules', 'dosage' => '1 cap TID for 7 days', 'quantity' => 21],
                ['name' => 'Paracetamol 500mg', 'dosage' => '1 tab PRN fever', 'quantity' => 10],
            ],
            'notes' => 'Patient advised rest and increased hydration.',
            'status' => 'pending',
            'is_demo' => true,
        ]);

        $dispensedRx = PharmacyPrescription::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'prescription_number' => 'RX-DEMO-2026-002',
        ], [
            'tenant_id' => $companyId,
            'customer_id' => $patient->id,
            'patient_name' => 'Michael Chang',
            'patient_phone' => '+1-555-0244',
            'doctor_name' => 'Dr. Emily Watson, MBBS',
            'doctor_registration_no' => 'MED-REG-44910',
            'prescription_date' => Carbon::today(),
            'diagnosis' => 'Bacterial Pharyngitis',
            'medicines' => [
                ['name' => 'Azithromycin 250mg Tablets', 'dosage' => '500mg stat then 250mg OD', 'quantity' => 6],
            ],
            'notes' => 'Dispensed full 6-tablet blister.',
            'status' => 'dispensed',
            'dispensed_at' => now(),
            'dispensed_by_user_id' => $admin?->id,
            'is_demo' => true,
        ]);

        // 5. Sample Dispensed Prescription Sale
        Sale::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'sale_number' => 'RX-DEMO-001',
        ], [
            'customer_id' => $patient->id,
            'customer_name' => $patient->name,
            'user_id' => $admin?->id,
            'total' => 19.00,
            'net_amount' => 19.00,
            'paid_amount' => 19.00,
            'due_amount' => 0.00,
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'status' => 'completed',
            'is_demo' => true,
            'notes' => 'Prescription dispensed against Rx #RX-DEMO-2026-002 (Dr. Emily Watson).',
            'items' => [
                [
                    'name' => 'Amoxicillin 500mg Capsules (10pk)',
                    'batch_number' => 'BATCH-2026-AMX',
                    'quantity' => 2,
                    'price' => 9.50,
                    'total' => 19.00,
                ],
            ],
        ]);

        $this->seedTaxRuleForCountry($company);
    }

    /**
     * Mode: Repair & Technician POS Workbench.
     */
    public function seedRepairTechnician(Company $company, ?User $admin = null): void
    {
        $companyId = $company->id;

        // 1. Repair Parts & Hardware Categories
        $categoriesData = [
            ['name' => 'Display Assemblies', 'color' => '#0284c7', 'description' => 'OLED, AMOLED, LCD screens and digitizers'],
            ['name' => 'Batteries & Power', 'color' => '#10b981', 'description' => 'Original OEM & high-capacity replacement batteries'],
            ['name' => 'Charging & Ports', 'color' => '#f59e0b', 'description' => 'USB-C, Lightning, DC jacks and flex cables'],
            ['name' => 'Workshop Consumables', 'color' => '#8b5cf6', 'description' => 'Thermal paste, adhesives, kapton tape, screws'],
        ];

        $categories = [];
        foreach ($categoriesData as $c) {
            $categories[$c['name']] = Category::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'name' => $c['name'],
            ], [
                'color' => $c['color'],
                'description' => $c['description'],
                'type' => 'repair',
                'active' => true,
                'is_demo' => true,
            ]);
        }

        // 2. Spare Parts Catalog (Inventory)
        $partsCatalog = [
            [
                'name' => 'iPhone 14 Pro OLED Display Assembly (OEM Grade)',
                'category' => 'Display Assemblies',
                'sku' => 'REP-SCR-001',
                'barcode' => '790101000001',
                'cost_price' => 75.00,
                'sale_price' => 160.00,
                'current_stock' => 12,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Samsung Galaxy S23 Ultra Replacement Battery 5000mAh',
                'category' => 'Batteries & Power',
                'sku' => 'REP-BAT-001',
                'barcode' => '790101000002',
                'cost_price' => 18.50,
                'sale_price' => 55.00,
                'current_stock' => 20,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Universal USB-C Fast Charging Flex PCB Sub-Board',
                'category' => 'Charging & Ports',
                'sku' => 'REP-CHG-001',
                'barcode' => '790101000003',
                'cost_price' => 7.00,
                'sale_price' => 29.00,
                'current_stock' => 35,
                'unit' => 'pcs',
            ],
            [
                'name' => 'Thermal Grizzly Kryonaut High Performance Paste 1g',
                'category' => 'Workshop Consumables',
                'sku' => 'REP-CON-001',
                'barcode' => '790101000004',
                'cost_price' => 4.50,
                'sale_price' => 15.00,
                'current_stock' => 40,
                'unit' => 'tube',
            ],
        ];

        $createdProducts = [];
        foreach ($partsCatalog as $p) {
            $cat = $categories[$p['category']] ?? null;
            $prod = Product::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'code' => $p['sku'],
            ], [
                'name' => $p['name'],
                'code' => $p['sku'],
                'barcode' => $p['barcode'],
                'category_id' => $cat?->id,
                'category_name' => $cat?->name,
                'cost_price' => $p['cost_price'],
                'sale_price' => $p['sale_price'],
                'current_stock' => $p['current_stock'],
                'minimum_stock' => 5,
                'unit' => $p['unit'],
                'active' => true,
                'is_demo' => true,
            ]);
            $createdProducts[$p['sku']] = $prod;
        }

        // 3. Customers
        $cust1 = Customer::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'name' => 'Alex Rivera',
        ], [
            'phone' => '+14155550142',
            'email' => 'alex.rivera@example.com',
            'is_demo' => true,
        ]);

        $cust2 = Customer::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'name' => 'Samantha Lee',
        ], [
            'phone' => '+14155550188',
            'email' => 'samantha.lee@example.com',
            'is_demo' => true,
        ]);

        // 4. Device Categories & Hardware Specifications
        $deviceCategories = [];
        foreach (RepairDeviceCategory::defaultPresets() as $preset) {
            $cat = Category::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'name' => $preset['name'],
            ], [
                'tenant_id' => $companyId,
                'slug' => Str::slug($preset['name']),
                'type' => 'device',
                'icon' => $preset['icon'],
                'description' => $preset['description'] ?? null,
                'metadata' => [
                    'identifier_type' => $preset['identifier_type'] ?? 'Serial / IMEI',
                    'brands' => $preset['brands'] ?? [],
                    'checklist_items' => $preset['checklist_items'] ?? [],
                    'common_issues' => $preset['common_issues'] ?? [],
                ],
                'sort_order' => $preset['sort_order'] ?? 1,
                'active' => true,
                'is_demo' => true,
            ]);
            $deviceCategories[$preset['name']] = $cat;
        }

        // 5. Sample Repair Tickets in Different Lifecycle Stages
        // Ticket 1: Active Intake
        $ticket1 = RepairTicket::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'ticket_number' => 'REP-2026-0001',
        ], [
            'tenant_id' => $companyId,
            'customer_id' => $cust1->id,
            'customer_name' => $cust1->name,
            'customer_phone' => $cust1->phone,
            'category_id' => $deviceCategories['Smartphones & Mobiles']->id ?? null,
            'brand' => 'Apple',
            'model' => 'iPhone 14 Pro',
            'serial_number_or_imei' => '359281094827164',
            'passcode_pattern' => '198402',
            'problem_reported' => 'Shattered front screen after drop; display flickers with vertical green line.',
            'physical_condition_notes' => 'Minor scuffs on stainless steel bezel; back glass pristine.',
            'status' => 'received',
            'priority' => 'high',
            'assigned_technician_id' => $admin?->id,
            'estimated_cost' => 190.00,
            'advance_deposit' => 50.00,
            'advance_payment_method' => 'cash',
            'inspection_checklist' => [
                'power_on' => true,
                'display' => false,
            ],
            'intake_at' => now()->subHours(3),
            'is_demo' => true,
        ]);

        if (isset($createdProducts['REP-SCR-001'])) {
            RepairTicketItem::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'ticket_id' => $ticket1->id,
                'item_name' => 'iPhone 14 Pro OLED Display Assembly (OEM Quality)',
            ], [
                'tenant_id' => $companyId,
                'product_id' => $createdProducts['REP-SCR-001']->id,
                'item_type' => 'spare_part',
                'quantity' => 1,
                'unit_price' => 160.00,
                'subtotal' => 160.00,
                'total' => 160.00,
                'billed_to_customer' => true,
            ]);
        }

        RepairTicketItem::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'ticket_id' => $ticket1->id,
            'item_name' => 'Diagnostic & Screen Replacement Labor',
        ], [
            'tenant_id' => $companyId,
            'item_type' => 'service_labor',
            'quantity' => 1,
            'unit_price' => 30.00,
            'subtotal' => 30.00,
            'total' => 30.00,
            'billed_to_customer' => true,
        ]);

        // Ticket 2: In Progress
        $ticket2 = RepairTicket::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'ticket_number' => 'REP-2026-0002',
        ], [
            'tenant_id' => $companyId,
            'customer_id' => $cust2->id,
            'customer_name' => $cust2->name,
            'customer_phone' => $cust2->phone,
            'category_id' => $deviceCategories['Smartphones & Mobiles']->id ?? null,
            'brand' => 'Samsung',
            'model' => 'Galaxy S23 Ultra',
            'serial_number_or_imei' => 'R5CW301827Z',
            'passcode_pattern' => 'Pattern: L-shape',
            'problem_reported' => 'Battery draining from 100% to 15% in 2 hours; device gets warm near charging port.',
            'physical_condition_notes' => 'Clean condition, screen protector installed.',
            'status' => 'in_progress',
            'priority' => 'normal',
            'assigned_technician_id' => $admin?->id,
            'estimated_cost' => 95.00,
            'advance_deposit' => 30.00,
            'advance_payment_method' => 'cash',
            'inspection_checklist' => [
                'power_on' => true,
                'battery_health' => '71%',
            ],
            'intake_at' => now()->subDays(1),
            'is_demo' => true,
        ]);

        if (isset($createdProducts['REP-BAT-001'])) {
            RepairTicketItem::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'ticket_id' => $ticket2->id,
                'item_name' => 'Samsung Galaxy S23 Ultra Replacement Battery 5000mAh',
            ], [
                'tenant_id' => $companyId,
                'product_id' => $createdProducts['REP-BAT-001']->id,
                'item_type' => 'spare_part',
                'quantity' => 1,
                'unit_price' => 55.00,
                'subtotal' => 55.00,
                'total' => 55.00,
                'billed_to_customer' => true,
            ]);
        }

        RepairTicketItem::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'ticket_id' => $ticket2->id,
            'item_name' => 'Battery Calibration & Replacement Labor',
        ], [
            'tenant_id' => $companyId,
            'item_type' => 'service_labor',
            'quantity' => 1,
            'unit_price' => 40.00,
            'subtotal' => 40.00,
            'total' => 40.00,
            'billed_to_customer' => true,
        ]);

        // Ticket 3: Repaired & Ready for Pickup
        $ticket3 = RepairTicket::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'ticket_number' => 'REP-2026-0003',
        ], [
            'tenant_id' => $companyId,
            'customer_id' => $cust1->id,
            'customer_name' => $cust1->name,
            'customer_phone' => $cust1->phone,
            'category_id' => $deviceCategories['Laptops & MacBooks']->id ?? null,
            'brand' => 'Dell',
            'model' => 'XPS 15 9520',
            'serial_number_or_imei' => 'DELL-SN-8921A',
            'passcode_pattern' => 'Windows Hello Pin: 4091',
            'problem_reported' => 'Thermal overheating shutdown under load. Thermal paste dried out.',
            'physical_condition_notes' => 'Missing two bottom rubber feet.',
            'status' => 'ready',
            'priority' => 'normal',
            'assigned_technician_id' => $admin?->id,
            'estimated_cost' => 65.00,
            'advance_deposit' => 20.00,
            'advance_payment_method' => 'card',
            'inspection_checklist' => [
                'power_on' => true,
                'thermal_test' => 'pass',
            ],
            'intake_at' => now()->subDays(2),
            'completed_at' => now()->subHours(2),
            'is_demo' => true,
        ]);

        RepairTicketItem::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'ticket_id' => $ticket3->id,
            'item_name' => 'High-Performance Thermal Paste Compound',
        ], [
            'tenant_id' => $companyId,
            'item_type' => 'spare_part',
            'quantity' => 1,
            'unit_price' => 15.00,
            'subtotal' => 15.00,
            'total' => 15.00,
            'billed_to_customer' => true,
        ]);

        RepairTicketItem::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'ticket_id' => $ticket3->id,
            'item_name' => 'Internal Dust Clean & Heatsink Repasting',
        ], [
            'tenant_id' => $companyId,
            'item_type' => 'service_labor',
            'quantity' => 1,
            'unit_price' => 50.00,
            'subtotal' => 50.00,
            'total' => 50.00,
            'billed_to_customer' => true,
        ]);

        $this->seedTaxRuleForCountry($company);
    }

    /**
     * Mode: Service & Salon POS.
     */
    public function seedServiceBooking(Company $company, ?User $admin = null): void
    {
        $companyId = $company->id;

        // 1. Service Categories
        $categoriesData = [
            ['name' => 'Hair & Styling', 'color' => '#8b5cf6', 'description' => 'Cuts, blowouts, coloring, and styling'],
            ['name' => 'Facials & Skincare', 'color' => '#ec4899', 'description' => 'Rejuvenating facials, peels, and therapy'],
            ['name' => 'Spa & Body Treatments', 'color' => '#06b6d4', 'description' => 'Aromatherapy, deep tissue, and relaxation'],
        ];

        $categories = [];
        foreach ($categoriesData as $c) {
            $categories[$c['name']] = Category::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'name' => $c['name'],
            ], [
                'color' => $c['color'],
                'description' => $c['description'],
                'type' => 'salon',
                'active' => true,
                'is_demo' => true,
            ]);
        }

        // 2. Services (as Products with duration_minutes)
        $servicesData = [
            [
                'name' => 'Haircut & Styling',
                'category' => 'Hair & Styling',
                'sku' => 'SRV-HAR-001',
                'duration_minutes' => 30,
                'cost_price' => 5.00,
                'sale_price' => 25.00,
                'image_url' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Facial Treatment',
                'category' => 'Facials & Skincare',
                'sku' => 'SRV-FCL-001',
                'duration_minutes' => 60,
                'cost_price' => 10.00,
                'sale_price' => 55.00,
                'image_url' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Full Body Massage',
                'category' => 'Spa & Body Treatments',
                'sku' => 'SRV-SPA-001',
                'duration_minutes' => 60,
                'cost_price' => 15.00,
                'sale_price' => 80.00,
                'image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=500&auto=format&fit=crop&q=80',
            ],
            [
                'name' => 'Beard Trim & Hot Towel',
                'category' => 'Hair & Styling',
                'sku' => 'SRV-HAR-002',
                'duration_minutes' => 20,
                'cost_price' => 3.00,
                'sale_price' => 15.00,
                'image_url' => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=500&auto=format&fit=crop&q=80',
            ],
        ];

        $serviceProducts = [];
        foreach ($servicesData as $s) {
            $cat = $categories[$s['category']] ?? null;
            $serviceProducts[$s['name']] = Product::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'code' => $s['sku'],
            ], [
                'name' => $s['name'],
                'code' => $s['sku'],
                'category_id' => $cat?->id,
                'category_name' => $cat?->name,
                'duration_minutes' => $s['duration_minutes'],
                'cost_price' => $s['cost_price'],
                'sale_price' => $s['sale_price'],
                'current_stock' => 999,
                'minimum_stock' => 0,
                'unit' => 'service',
                'image_url' => $s['image_url'],
                'active' => true,
                'is_demo' => true,
            ]);
        }

        // 3. Staff / Specialists with Assigned Shifts
        $specialists = [
            [
                'name' => 'Elena Rostova (Master Stylist)',
                'login' => 'elena.stylist',
                'email' => 'elena@example.test',
                'shift' => 'Morning Shift (9:00 AM - 3:00 PM)',
                'role' => User::ROLE_SALESPERSON,
            ],
            [
                'name' => 'Marcus Chen (Senior Therapist)',
                'login' => 'marcus.therapist',
                'email' => 'marcus@example.test',
                'shift' => 'Evening Shift (2:00 PM - 8:00 PM)',
                'role' => User::ROLE_SALESPERSON,
            ],
        ];

        $specialistUsers = [];
        foreach ($specialists as $sp) {
            $specialistUsers[] = User::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'email' => $sp['email'],
            ], [
                'name' => $sp['name'],
                'login' => $sp['login'],
                'password' => Hash::make('password123'),
                'role' => $sp['role'],
                'shift' => $sp['shift'],
                'status' => 'approved',
                'is_demo' => true,
                'is_specialist' => true,
                'email_verified_at' => now(),
            ]);
        }

        // 4. Client / Customer
        $client = Customer::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'name' => 'Alice Walker',
        ], [
            'email' => 'alice.walker@example.com',
            'phone' => '+1-555-0188',
            'person_type' => 'individual',
            'is_demo' => true,
            'due_balance' => 0.00,
        ]);

        // 5. 1 Scheduled Appointment for Current Day
        $primarySpecialist = $specialistUsers[0] ?? null;
        $appointmentTime = Carbon::today()->setTime(14, 0, 0); // Today at 2:00 PM

        ServiceOrder::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'order_number' => 'SRV-DEMO-001',
        ], [
            'customer_id' => $client->id,
            'customer_name' => $client->name,
            'customer_phone' => $client->phone,
            'customer_email' => $client->email,
            'equipment_name' => 'Haircut & Styling',
            'reported_defect' => 'Requested consultation: Haircut & Styling appointment',
            'status' => ServiceOrder::STATUS_RECEIVED,
            'priority' => 'normal',
            'technician_id' => $primarySpecialist?->id,
            'received_at' => $appointmentTime,
            'labor_cost' => 25.00,
            'total_amount' => 25.00,
            'notes' => "Scheduled appointment for today at 2:00 PM with {$primarySpecialist?->name}.",
            'is_demo' => true,
        ]);

        if ($primarySpecialist && isset($serviceProducts['Haircut & Styling'])) {
            SalonAppointment::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'appointment_number' => 'APT-DEMO-001',
            ], [
                'tenant_id' => $companyId,
                'customer_id' => $client->id,
                'customer_name' => $client->name,
                'customer_phone' => $client->phone,
                'product_id' => $serviceProducts['Haircut & Styling']->id,
                'specialist_id' => $primarySpecialist->id,
                'starts_at' => $appointmentTime,
                'ends_at' => $appointmentTime->copy()->addMinutes(30),
                'status' => 'scheduled',
                'notes' => 'Demo salon appointment.',
                'is_demo' => true,
            ]);
        }

        Sale::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $companyId,
            'sale_number' => 'SRV-BOOK-001',
        ], [
            'customer_id' => $client->id,
            'customer_name' => $client->name,
            'user_id' => $primarySpecialist?->id ?? $admin?->id,
            'total' => 25.00,
            'net_amount' => 25.00,
            'paid_amount' => 0.00,
            'due_amount' => 25.00,
            'payment_status' => 'pending',
            'status' => 'in_progress',
            'service_type' => 'appointment',
            'pickup_time' => $appointmentTime->toDateTimeString(),
            'notes' => 'Confirmed salon booking for Haircut & Styling.',
            'is_demo' => true,
            'items' => [
                [
                    'name' => 'Haircut & Styling',
                    'quantity' => 1,
                    'price' => 25.00,
                    'total' => 25.00,
                    'duration_minutes' => 30,
                ],
            ],
        ]);

        // A spread of appointments across the week & lifecycle so the calendar,
        // the stylist schedule and the service-order board all have depth.
        $serviceList = array_values($serviceProducts);
        $bookingCustomers = Customer::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('is_demo', true)
            ->orderBy('id')->get()->values();

        $apptPlan = [
            ['num' => 'APT-DEMO-002', 'dayOffset' => 0, 'hour' => 10, 'status' => 'checked_in', 'spec' => 0],
            ['num' => 'APT-DEMO-003', 'dayOffset' => 0, 'hour' => 16, 'status' => 'scheduled', 'spec' => 1],
            ['num' => 'APT-DEMO-004', 'dayOffset' => 1, 'hour' => 11, 'status' => 'scheduled', 'spec' => 0],
            ['num' => 'APT-DEMO-005', 'dayOffset' => 2, 'hour' => 14, 'status' => 'scheduled', 'spec' => 1],
            ['num' => 'APT-DEMO-006', 'dayOffset' => -1, 'hour' => 15, 'status' => 'completed', 'spec' => 0],
            ['num' => 'APT-DEMO-007', 'dayOffset' => -2, 'hour' => 12, 'status' => 'completed', 'spec' => 1],
        ];

        foreach ($apptPlan as $ix => $a) {
            if (SalonAppointment::withoutGlobalScopes()->where('company_id', $companyId)->where('appointment_number', $a['num'])->exists()) {
                continue;
            }

            $svc = $serviceList[$ix % max(1, count($serviceList))] ?? null;
            $spec = $specialistUsers[$a['spec']] ?? $primarySpecialist;
            $cust = $bookingCustomers->isNotEmpty() ? $bookingCustomers[$ix % $bookingCustomers->count()] : $client;
            if (! $svc || ! $spec) {
                continue;
            }

            $startsAt = Carbon::today()->addDays($a['dayOffset'])->setTime($a['hour'], 0, 0);
            $price = round((float) ($svc->sale_price ?: 25), 2);
            $done = $a['status'] === 'completed';

            SalonAppointment::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'appointment_number' => $a['num'],
            ], [
                'tenant_id' => $companyId,
                'customer_id' => $cust->id,
                'customer_name' => $cust->name,
                'customer_phone' => $cust->phone,
                'product_id' => $svc->id,
                'specialist_id' => $spec->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addMinutes(45),
                'status' => $a['status'],
                'notes' => 'Demo salon appointment ('.$a['status'].').',
                'is_demo' => true,
            ]);

            ServiceOrder::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'order_number' => 'SRV-DEMO-'.str_pad((string) ($ix + 2), 3, '0', STR_PAD_LEFT),
            ], [
                'customer_id' => $cust->id,
                'customer_name' => $cust->name,
                'customer_phone' => $cust->phone,
                'customer_email' => $cust->email,
                'equipment_name' => $svc->name,
                'reported_defect' => 'Booked service: '.$svc->name,
                'status' => $done ? ServiceOrder::STATUS_DELIVERED_SETTLED : ServiceOrder::STATUS_RECEIVED,
                'priority' => 'normal',
                'technician_id' => $spec->id,
                'received_at' => $startsAt,
                'labor_cost' => $price,
                'total_amount' => $price,
                'notes' => 'Salon service order for '.$cust->name.'.',
                'is_demo' => true,
            ]);

            if ($done) {
                $bookingSale = Sale::withoutGlobalScopes()->firstOrCreate([
                    'company_id' => $companyId,
                    'sale_number' => 'BKG-'.str_pad((string) ($ix + 2), 3, '0', STR_PAD_LEFT),
                ], [
                    'operation_type' => 'sale',
                    'customer_id' => $cust->id,
                    'customer_name' => $cust->name,
                    'user_id' => $spec->id,
                    'total' => $price,
                    'net_amount' => $price,
                    'paid_amount' => $price,
                    'due_amount' => 0.0,
                    'payment_method' => 'card',
                    'payment_status' => 'paid',
                    'status' => 'completed',
                    'service_type' => 'appointment',
                    'is_demo' => true,
                    'items' => [[
                        'name' => $svc->name,
                        'quantity' => 1,
                        'price' => $price,
                        'total' => $price,
                        'duration_minutes' => 45,
                    ]],
                ]);
                Sale::withoutGlobalScopes()->whereKey($bookingSale->id)
                    ->update(['created_at' => $startsAt, 'updated_at' => $startsAt]);
            }
        }

        $this->seedTaxRuleForCountry($company);
    }

    /**
     * Seeds country-appropriate tax rule (GST / VAT / Sales Tax).
     */
    public function seedTaxRuleForCountry(Company $company): void
    {
        $country = strtoupper(trim($company->country ?? 'US'));

        if ($country === 'IN') {
            TaxRule::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $company->id,
                'tax_code' => 'GST_18',
            ], [
                'tax_name' => 'GST 18%',
                'rate' => 18.000,
                'type' => 'gst',
                'country' => 'IN',
                'calc_type' => 'exclusive',
                'is_default' => true,
                'sub_components' => [
                    ['name' => 'CGST', 'rate' => 9.0],
                    ['name' => 'SGST', 'rate' => 9.0],
                ],
                'active' => true,
                'is_demo' => true,
            ]);
        } elseif (in_array($country, ['GB', 'DE', 'FR', 'ES', 'IT', 'EU'], true)) {
            TaxRule::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $company->id,
                'tax_code' => 'VAT_STANDARD',
            ], [
                'tax_name' => 'Standard VAT',
                'rate' => 20.000,
                'type' => 'vat',
                'country' => $country,
                'calc_type' => 'inclusive',
                'is_default' => true,
                'active' => true,
                'is_demo' => true,
            ]);
        } else {
            TaxRule::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $company->id,
                'tax_code' => 'SALES_TAX',
            ], [
                'tax_name' => 'Sales Tax',
                'rate' => 8.250,
                'type' => 'sales_tax',
                'country' => $country,
                'calc_type' => 'exclusive',
                'is_default' => true,
                'active' => true,
                'is_demo' => true,
            ]);
        }
    }

    /**
     * One-Click Demo Data Purge.
     * Safely purges only records flagged with is_demo = true.
     */
    public function purgeDemoData(Company $company): array
    {
        return DB::transaction(function () use ($company) {
            $companyId = $company->id;
            $counts = [];

            // 1. Kitchen Tickets
            $counts['kitchen_tickets'] = KitchenTicket::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 2. Service Orders
            $counts['service_orders'] = ServiceOrder::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            $counts['salon_appointments'] = SalonAppointment::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 3. Repair Tickets & Items (before customers/products)
            $counts['repair_ticket_items'] = RepairTicketItem::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->delete();

            $counts['repair_tickets'] = RepairTicket::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            $counts['repair_device_categories'] = Category::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('type', 'device')
                ->where('is_demo', true)
                ->delete();

            // 4. Pharmacy Prescriptions & Batches (before sales/products)
            $counts['pharmacy_prescriptions'] = PharmacyPrescription::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            $counts['pharmacy_batches'] = PharmacyBatch::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 5. Customer Ledgers
            $counts['customer_ledgers'] = CustomerLedger::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 6. Sales
            $counts['sales'] = Sale::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 7. Dining Tables & Floors
            $counts['dining_tables'] = DiningTable::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            $counts['dining_floors'] = DiningFloor::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 8. Products & Categories
            $counts['products'] = Product::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->forceDelete();

            $counts['categories'] = Category::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 9. Customers
            $counts['customers'] = Customer::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 10. Tax Rules
            $counts['tax_rules'] = TaxRule::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 11. Demo Users (excluding owner/administrators)
            $counts['users'] = User::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->where('role', '!=', User::ROLE_ADMINISTRATOR)
                ->delete();

            $company->update(['is_seeding_complete' => false]);

            Log::info("Sample demo data purged for company [{$companyId}]", $counts);

            return $counts;
        });
    }
}
