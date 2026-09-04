<?php

namespace App\Services\Tenancy;

use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\DiningFloor;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\Product;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Models\TaxRule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
                default => $this->seedRetail($company, $admin),
            };

            $company->update(['is_seeding_complete' => true]);
        });
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
                'sku' => $p['sku'],
            ], [
                'name' => $p['name'],
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
                'sku' => $m['sku'],
            ], [
                'name' => $m['name'],
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
            Product::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'sku' => $d['sku'],
            ], [
                'name' => $d['name'],
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

        // 4. Sample Dispensed Prescription Sale
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
            'notes' => 'Prescription dispensed by Dr. Robert Evans (License #MD-88392).',
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

        foreach ($servicesData as $s) {
            $cat = $categories[$s['category']] ?? null;
            Product::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $companyId,
                'sku' => $s['sku'],
            ], [
                'name' => $s['name'],
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
            'technician_id' => $primarySpecialist?->name ?? 'Elena Rostova',
            'received_at' => $appointmentTime,
            'labor_cost' => 25.00,
            'total_amount' => 25.00,
            'notes' => "Scheduled appointment for today at 2:00 PM with {$primarySpecialist?->name}.",
            'is_demo' => true,
        ]);

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

            // 3. Customer Ledgers
            $counts['customer_ledgers'] = CustomerLedger::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 4. Sales
            $counts['sales'] = Sale::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 5. Dining Tables & Floors
            $counts['dining_tables'] = DiningTable::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            $counts['dining_floors'] = DiningFloor::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 6. Products & Categories
            $counts['products'] = Product::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            $counts['categories'] = Category::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 7. Customers
            $counts['customers'] = Customer::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 8. Tax Rules
            $counts['tax_rules'] = TaxRule::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_demo', true)
                ->delete();

            // 9. Demo Users (excluding owner/administrators)
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
