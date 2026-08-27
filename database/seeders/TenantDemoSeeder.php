<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PlatformAdmin;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantDemoSeeder extends Seeder
{
    public function run(?Company $targetCompany = null): void
    {
        $companies = $targetCompany ? collect([$targetCompany]) : Company::all();

        if ($companies->isEmpty()) {
            $company = Company::create([
                'name' => 'Zoom POS & Market',
                'trade_name' => 'Zoom Fresh Supermarket & Cafe',
                'slug' => 'demo',
                'email' => 'store@zoommarket.test',
                'phone' => '+1-555-0199',
                'address' => '742 Evergreen Terrace',
                'city' => 'Springfield',
                'state' => 'IL',
                'postal_code' => '62704',
                'country' => 'US',
                'currency' => 'USD',
                'currency_symbol' => '$',
                'primary_color' => '#059669',
                'theme_color' => 'emerald',
                'pos_mode' => 'both',
                'pos_layout' => 'touch',
                'status' => 'active',
                'plan_name' => 'professional',
                'registered_at' => now()->subMonths(3),
                'expires_at' => now()->addYear(),
            ]);

            User::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'name' => 'Store Manager',
                'login' => 'admin',
                'email' => 'admin@zoommarket.test',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'status' => 'active',
            ]);

            User::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'name' => 'Cashier Staff',
                'login' => 'cashier',
                'email' => 'cashier@zoommarket.test',
                'password' => Hash::make('password123'),
                'role' => 'cashier',
                'status' => 'active',
            ]);

            PlatformAdmin::query()->firstOrCreate(['email' => 'superadmin@gmail.com'], [
                'name' => 'Super Administrator',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
                'status' => 'active',
            ]);

            $companies = collect([$company]);
        }

        foreach ($companies as $company) {
            $companyId = $company->id;

            // 1. Categories matching pos.png
            $categoriesData = [
                ['name' => 'Vegetables', 'color' => '#22c55e', 'description' => 'Fresh vegetables and greens'],
                ['name' => 'Fresh Fruit', 'color' => '#f43f5e', 'description' => 'Seasonal sweet fruits and berries'],
                ['name' => 'Carbohydrate', 'color' => '#f59e0b', 'description' => 'Fresh breads, grains, and baked goods'],
                ['name' => 'Beverages', 'color' => '#06b6d4', 'description' => 'Chilled juices, milk, and drinks'],
                ['name' => 'Snacks', 'color' => '#a855f7', 'description' => 'Healthy snacks and quick bites'],
            ];

            $catModels = [];
            foreach ($categoriesData as $cData) {
                $cat = Category::withoutGlobalScopes()->firstOrCreate([
                    'company_id' => $companyId,
                    'name' => $cData['name'],
                ], [
                    'color' => $cData['color'],
                    'description' => $cData['description'],
                    'active' => true,
                ]);
                $catModels[$cData['name']] = $cat;
            }

            // 2. Brands
            $brandNames = ['FreshFarm', 'Harvest Valley', 'Nature\'s Best', 'Sunny Orchard'];
            $brandModels = [];
            foreach ($brandNames as $bName) {
                $b = Brand::withoutGlobalScopes()->firstOrCreate([
                    'company_id' => $companyId,
                    'name' => $bName,
                ]);
                $brandModels[$bName] = $b;
            }

            // 3. Units
            $units = [
                ['name' => 'Piece', 'abbreviation' => 'pcs'],
                ['name' => 'Kilogram', 'abbreviation' => 'kg'],
                ['name' => 'Box', 'abbreviation' => 'bx'],
                ['name' => 'Pack', 'abbreviation' => 'pk'],
            ];
            foreach ($units as $u) {
                Unit::withoutGlobalScopes()->firstOrCreate([
                    'company_id' => $companyId,
                    'name' => $u['name'],
                ], ['abbreviation' => $u['abbreviation']]);
            }

            // 4. Suppliers
            $suppliers = [
                ['name' => 'Green Valley Produce', 'email' => 'orders@greenvalley.test', 'phone' => '+1-555-0321', 'city' => 'Fresno', 'state' => 'CA'],
                ['name' => 'Sunrise Orchard Supply', 'email' => 'sales@sunriseorchard.test', 'phone' => '+1-555-0322', 'city' => 'Yakima', 'state' => 'WA'],
                ['name' => 'Golden Grain Bakery', 'email' => 'info@goldengrain.test', 'phone' => '+1-555-0323', 'city' => 'Portland', 'state' => 'OR'],
            ];
            foreach ($suppliers as $s) {
                Supplier::withoutGlobalScopes()->firstOrCreate([
                    'company_id' => $companyId,
                    'name' => $s['name'],
                ], [
                    'email' => $s['email'],
                    'phone' => $s['phone'],
                    'city' => $s['city'],
                    'state' => $s['state'],
                    'active' => true,
                ]);
            }

            // 5. Products matching pos.png (Terong, Melon, Apple, Semangka, Jeruk, Strawberry, Pisang, Lemon)
            $productsData = [
                ['name' => 'Terong', 'code' => 'VEG-001', 'barcode' => '8901001', 'category' => 'Vegetables', 'cost' => 2.00, 'price' => 4.00, 'stock' => 40, 'min' => 5],
                ['name' => 'Melon', 'code' => 'FRT-001', 'barcode' => '8901002', 'category' => 'Fresh Fruit', 'cost' => 4.00, 'price' => 8.00, 'stock' => 25, 'min' => 5],
                ['name' => 'Apple', 'code' => 'FRT-002', 'barcode' => '8901003', 'category' => 'Fresh Fruit', 'cost' => 2.50, 'price' => 5.00, 'stock' => 50, 'min' => 10],
                ['name' => 'Semangka', 'code' => 'FRT-003', 'barcode' => '8901004', 'category' => 'Fresh Fruit', 'cost' => 8.00, 'price' => 15.00, 'stock' => 20, 'min' => 5],
                ['name' => 'Jeruk', 'code' => 'FRT-004', 'barcode' => '8901005', 'category' => 'Fresh Fruit', 'cost' => 10.00, 'price' => 20.00, 'stock' => 30, 'min' => 5],
                ['name' => 'Strawberry', 'code' => 'FRT-005', 'barcode' => '8901006', 'category' => 'Fresh Fruit', 'cost' => 1.50, 'price' => 3.00, 'stock' => 35, 'min' => 5],
                ['name' => 'Pisang', 'code' => 'FRT-006', 'barcode' => '8901007', 'category' => 'Fresh Fruit', 'cost' => 3.00, 'price' => 6.00, 'stock' => 45, 'min' => 8],
                ['name' => 'Lemon', 'code' => 'FRT-007', 'barcode' => '8901008', 'category' => 'Fresh Fruit', 'cost' => 3.50, 'price' => 7.00, 'stock' => 30, 'min' => 5],
                ['name' => 'Tomato', 'code' => 'VEG-002', 'barcode' => '8901009', 'category' => 'Vegetables', 'cost' => 1.80, 'price' => 3.50, 'stock' => 50, 'min' => 10],
                ['name' => 'Carrot', 'code' => 'VEG-003', 'barcode' => '8901010', 'category' => 'Vegetables', 'cost' => 1.20, 'price' => 2.50, 'stock' => 40, 'min' => 8],
                ['name' => 'Artisan Bread', 'code' => 'CRB-001', 'barcode' => '8901011', 'category' => 'Carbohydrate', 'cost' => 2.20, 'price' => 4.50, 'stock' => 20, 'min' => 5],
                ['name' => 'Fresh Milk', 'code' => 'BEV-001', 'barcode' => '8901012', 'category' => 'Beverages', 'cost' => 1.80, 'price' => 3.20, 'stock' => 28, 'min' => 6],
            ];

            foreach ($productsData as $p) {
                $catId = $catModels[$p['category']]->id ?? null;
                Product::withoutGlobalScopes()->updateOrCreate([
                    'company_id' => $companyId,
                    'name' => $p['name'],
                ], [
                    'code' => $p['code'],
                    'barcode' => $p['barcode'],
                    'category_id' => $catId,
                    'category_name' => $p['category'],
                    'unit' => 'pcs',
                    'cost_price' => $p['cost'],
                    'sale_price' => $p['price'],
                    'current_stock' => $p['stock'],
                    'minimum_stock' => $p['min'],
                    'active' => true,
                    'taxable' => true,
                ]);
            }

            // 6. Customers
            $customersData = [
                ['name' => 'John Doe', 'email' => 'john.doe@example.com', 'phone' => '+1-555-0101', 'document' => 'TAX-8891', 'city' => 'New York', 'state' => 'NY', 'points' => 120],
                ['name' => 'Sarah Smith', 'email' => 'sarah.smith@example.com', 'phone' => '+1-555-0102', 'document' => 'TAX-4423', 'city' => 'San Francisco', 'state' => 'CA', 'points' => 350],
                ['name' => 'Alex Rivera', 'email' => 'alex.rivera@example.com', 'phone' => '+1-555-0103', 'document' => 'TAX-1190', 'city' => 'Austin', 'state' => 'TX', 'points' => 80],
                ['name' => 'Maria Garcia', 'email' => 'maria.garcia@example.com', 'phone' => '+1-555-0104', 'document' => 'TAX-6652', 'city' => 'Miami', 'state' => 'FL', 'points' => 210],
            ];

            $customerModels = [];
            foreach ($customersData as $c) {
                $cust = Customer::withoutGlobalScopes()->firstOrCreate([
                    'company_id' => $companyId,
                    'email' => $c['email'],
                ], [
                    'name' => $c['name'],
                    'phone' => $c['phone'],
                    'document' => $c['document'],
                    'city' => $c['city'],
                    'state' => $c['state'],
                    'loyalty_points' => $c['points'],
                ]);
                $customerModels[] = $cust;
            }

            // 7. Dummy Sales
            $user = User::withoutGlobalScopes()->where('company_id', $companyId)->first();
            $userId = $user?->id;

            if (Sale::withoutGlobalScopes()->where('company_id', $companyId)->count() === 0) {
                Sale::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'sale_number' => 'S-20260821001',
                    'customer_id' => $customerModels[0]->id ?? null,
                    'customer_name' => $customerModels[0]->name ?? 'John Doe',
                    'user_id' => $userId,
                    'total' => 45.00,
                    'discount' => 0.00,
                    'payment_method' => 'cash',
                    'status' => 'completed',
                    'items' => [
                        ['product_id' => null, 'name' => 'Melon', 'quantity' => 1, 'price' => 8.00],
                        ['product_id' => null, 'name' => 'Semangka', 'quantity' => 2, 'price' => 15.00],
                        ['product_id' => null, 'name' => 'Strawberry', 'quantity' => 1, 'price' => 3.00],
                        ['product_id' => null, 'name' => 'Lemon', 'quantity' => 1, 'price' => 4.00],
                    ],
                    'created_at' => now()->subHours(5),
                ]);

                Sale::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'sale_number' => 'S-20260822002',
                    'customer_id' => $customerModels[1]->id ?? null,
                    'customer_name' => $customerModels[1]->name ?? 'Sarah Smith',
                    'user_id' => $userId,
                    'total' => 62.00,
                    'discount' => 0.00,
                    'payment_method' => 'card',
                    'status' => 'completed',
                    'items' => [
                        ['product_id' => null, 'name' => 'Jeruk', 'quantity' => 2, 'price' => 20.00],
                        ['product_id' => null, 'name' => 'Apple', 'quantity' => 3, 'price' => 5.00],
                        ['product_id' => null, 'name' => 'Lemon', 'quantity' => 1, 'price' => 7.00],
                    ],
                    'created_at' => now()->subHours(2),
                ]);

                Sale::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'sale_number' => 'HOLD-20260822003',
                    'customer_id' => null,
                    'customer_name' => 'Walk-in Customer',
                    'user_id' => $userId,
                    'total' => 17.50,
                    'discount' => 0.00,
                    'payment_method' => 'cash',
                    'status' => 'pending',
                    'items' => [
                        ['product_id' => null, 'name' => 'Pisang', 'quantity' => 2, 'price' => 6.00],
                        ['product_id' => null, 'name' => 'Artisan Bread', 'quantity' => 1, 'price' => 4.50],
                        ['product_id' => null, 'name' => 'Tomato', 'quantity' => 1, 'price' => 3.50],
                    ],
                    'created_at' => now()->subMinutes(30),
                ]);

                // 8. Demo Credit Sales & Accounts Receivables Entries
                Sale::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'sale_number' => 'INV-REC-001',
                    'customer_id' => $customerModels[0]->id ?? null,
                    'customer_name' => $customerModels[0]->name ?? 'John Doe',
                    'user_id' => $userId,
                    'total' => 150.00,
                    'paid_amount' => 50.00,
                    'due_amount' => 100.00,
                    'payment_method' => 'card',
                    'payment_status' => 'partial',
                    'status' => 'completed',
                    'operation_type' => 'sale',
                    'due_date' => now()->addDays(14),
                    'items' => [
                        ['product_id' => null, 'name' => 'Apple', 'quantity' => 10, 'price' => 5.00],
                        ['product_id' => null, 'name' => 'Melon', 'quantity' => 5, 'price' => 8.00],
                        ['product_id' => null, 'name' => 'Jeruk', 'quantity' => 3, 'price' => 20.00],
                    ],
                    'notes' => 'Net 14 payment terms. $50 upfront deposit paid by card.',
                    'created_at' => now()->subDays(3),
                ]);

                Sale::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'sale_number' => 'INV-REC-002',
                    'customer_id' => $customerModels[2]->id ?? null,
                    'customer_name' => $customerModels[2]->name ?? 'Alex Rivera',
                    'user_id' => $userId,
                    'total' => 85.00,
                    'paid_amount' => 0.00,
                    'due_amount' => 85.00,
                    'payment_method' => 'cash',
                    'payment_status' => 'unpaid',
                    'status' => 'completed',
                    'operation_type' => 'sale',
                    'due_date' => now()->subDays(2), // Overdue entry
                    'items' => [
                        ['product_id' => null, 'name' => 'Semangka', 'quantity' => 5, 'price' => 15.00],
                        ['product_id' => null, 'name' => 'Lemon', 'quantity' => 1, 'price' => 10.00],
                    ],
                    'notes' => 'Invoice overdue for payment. Reminder notification queued.',
                    'created_at' => now()->subDays(10),
                ]);

                // 9. Demo Quotations matching 1131w-Zy7QIPVSff8.png
                Sale::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'sale_number' => 'QUO-0001',
                    'customer_id' => $customerModels[1]->id ?? null,
                    'customer_name' => 'Salford & Co.',
                    'user_id' => $userId,
                    'total' => 1925.00,
                    'discount' => 0.00,
                    'status' => 'sent',
                    'operation_type' => 'quotation',
                    'items' => [
                        ['product_id' => null, 'name' => 'Website Design', 'quantity' => 30, 'price' => 25.00],
                        ['product_id' => null, 'name' => 'Icon Design', 'quantity' => 50, 'price' => 10.00],
                        ['product_id' => null, 'name' => 'Illustration', 'quantity' => 10, 'price' => 15.00],
                        ['product_id' => null, 'name' => 'Presentation Template', 'quantity' => 50, 'price' => 7.00],
                    ],
                    'created_at' => now()->subDays(2),
                ]);
            }
        }
    }
}
