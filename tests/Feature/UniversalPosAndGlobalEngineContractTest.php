<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Customer;
use App\Models\OrderPayment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Modular\ModulePackageService;
use App\Services\Navigation\TenantNavRegistry;
use App\Services\Sdui\SchemaResponse;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

class UniversalPosAndGlobalEngineContractTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $admin;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'enterprise',
            'display_name' => 'Enterprise Plan',
            'price' => 99.00,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'features' => ['pos' => true, 'offline' => true, 'inventory' => true],
            'limits' => ['products' => 5000, 'users' => 20],
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Universal Retail Corp',
            'trade_name' => 'Universal Retail',
            'slug' => 'universal-retail',
            'email' => 'admin@universal.test',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'enterprise',
            'expires_at' => now()->addDays(30),
            'licensed_modules' => ['retail', 'restaurant', 'pharmacy', 'repair_technician', 'service_booking'],
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@universal.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $loginResponse = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@universal.test',
            'password' => 'secret123',
        ]);

        $this->token = (string) $loginResponse->json('token');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('modules/hardwareshop'));
        File::deleteDirectory(storage_path('framework/testing/hardwareshop_src'));
        @unlink(storage_path('framework/testing/hardwareshop.zip'));

        parent::tearDown();
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_universal_pos_screens_have_identical_catalog_grid_and_drawer_actions(): void
    {
        Product::create([
            'company_id' => $this->company->id,
            'name' => 'Standard Item A',
            'code' => 'ITEM-A',
            'sale_price' => 25.00,
            'current_stock' => 50,
            'active' => true,
        ]);

        $endpoints = [
            'retail' => '/api/tenant/views/retail-pos',
            'restaurant' => '/api/tenant/views/restaurant-pos',
            'pharmacy' => '/api/tenant/views/pharmacy-pos',
            'repair' => '/api/tenant/views/repair-pos',
            'salon' => '/api/tenant/views/salon-pos',
            'pos' => '/api/tenant/views/pos',
            'dynamic-pos' => '/api/tenant/views/hardware-pos',
        ];

        foreach ($endpoints as $vertical => $url) {
            $response = $this->getJson($url, $this->authHeaders());
            $response->assertOk();

            $schema = $response->json('schema');
            $this->assertIsArray($schema, "Schema for {$vertical} must be an array");
            $this->assertSame('pos_screen', $schema['type'], "Type for {$vertical} must be pos_screen");

            // Universal Material catalog cards grid
            $this->assertArrayHasKey('catalog', $schema, "Catalog missing for {$vertical}");
            $this->assertSame('standard_grid', $schema['catalog']['layout_type']);
            $this->assertIsArray($schema['catalog']['items']);
            $this->assertNotEmpty($schema['catalog']['items']);

            // Full-width search bar with barcode scan
            $this->assertArrayHasKey('search', $schema);
            $this->assertNotEmpty($schema['search']['placeholder']);
            $this->assertTrue($schema['search']['scanner_enabled']);

            // Horizontal category chips list
            $this->assertArrayHasKey('categories', $schema);
            $this->assertIsArray($schema['categories']);
            $this->assertNotEmpty($schema['categories']);
            $this->assertNotEmpty($schema['categories'][0]['label']);

            // Floating bottom cart bar
            $this->assertArrayHasKey('cart_bar', $schema);
            $this->assertNotEmpty($schema['cart_bar']['checkout_sheet_endpoint']);

            if ($schema['banner'] !== null) {
                $this->assertSame('#2A1E17', $schema['banner']['background_color']);
                $this->assertSame('#D97706', $schema['banner']['border_color']);
                $this->assertSame('#FCD34D', $schema['banner']['text_color']);
                $this->assertSame('#10B981', $schema['banner']['action_text_color']);
            }

            // SDUI Schema Validator must pass cleanly
            $errors = app(SchemaValidator::class)->validate($schema);
            $this->assertEmpty($errors, "Schema validation failed for {$vertical}: ".json_encode($errors));
        }
    }

    public function test_universal_checkout_drawer_matches_standard_settlement_spec(): void
    {
        $response = $this->getJson('/api/tenant/pos/checkout-sheet', $this->authHeaders());
        $response->assertOk();

        $schema = $response->json('schema');
        $this->assertIsArray($schema);
        $this->assertSame('sheet', $schema['type']);
        $this->assertSame('native_pos_checkout_drawer', $schema['presentation']);

        // Order summary and action pills
        $this->assertArrayHasKey('order_summary', $schema);
        $this->assertContains('add_customer', $schema['customer_actions']);
        $this->assertContains('hold', $schema['customer_actions']);
        $this->assertContains('note', $schema['customer_actions']);
        $this->assertContains('discount', $schema['customer_actions']);
        $this->assertContains('split_payment', $schema['customer_actions']);

        // Payment method selector — exactly the three standard toggles, no Khata/credit
        $methods = array_column($schema['payment_methods'], 'value');
        $this->assertSame(['cash', 'card', 'transfer'], $methods);
        $this->assertNotContains('credit', $methods);

        // Quick cash suggestions & change due box
        $this->assertArrayHasKey('quick_cash', $schema);
        $this->assertNotEmpty($schema['quick_cash']['suggestions']);
        $this->assertSame('Change Due to Customer', $schema['quick_cash']['change_due_label']);

        // Settlement breakdown — button reads "Complete Sale · {total}", forest green
        $this->assertArrayHasKey('bottom_bar', $schema);
        $this->assertStringStartsWith('Complete Sale · ', $schema['bottom_bar']['primary_action_label']);
        $this->assertStringNotContainsString('Collect Payment', $schema['bottom_bar']['primary_action_label']);
        $this->assertSame('#166534', $schema['bottom_bar']['primary_color']);

        // Every action pill is a real handler — never a decorative badge.
        $schemaStr = json_encode($schema, JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('"label":"+ Add Customer","color"', $schemaStr, 'Add Customer must be a button, not a badge');
        $this->assertStringContainsString('"type":"open_modal"', $schemaStr);
        $this->assertStringContainsString('/api/tenant/pos/hold-order', $schemaStr);
        $this->assertStringContainsString('discount_type', $schemaStr);
        // The metadata block advertises the wired handlers.
        $pillsByKey = collect($schema['action_pills'])->keyBy('key');
        $this->assertSame('form_submit', $pillsByKey['hold']['action']);
        $this->assertSame('/api/tenant/pos/hold-order', $pillsByKey['hold']['endpoint']);
        $this->assertSame('open_modal', $pillsByKey['discount']['action']);

        // Cart chips must NOT tear down the drawer: every open_modal pill keeps
        // the parent sheet alive and refreshes it in place once dismissed.
        foreach (['Add Customer', 'Order Note', 'Apply Discount', 'Split Payment'] as $modalTitle) {
            $needle = '"type":"open_modal","title":"'.$modalTitle.'"';
            $this->assertStringContainsString($needle, $schemaStr);
        }
        $this->assertStringNotContainsString('"keep_parent_sheet":false', $schemaStr);
        $addCustomerPos = strpos($schemaStr, '"type":"open_modal","title":"Add Customer"');
        $this->assertStringContainsString(
            'keep_parent_sheet', substr($schemaStr, $addCustomerPos, 900),
            'Add Customer modal must stack over the cart, not replace it'
        );
        $this->assertStringContainsString('"refresh_in_place":true', $schemaStr);

        // Change due is computed on-device — the schema ships the widget, not
        // a server-rendered "CHANGE DUE TO CUSTOMER" string.
        $this->assertStringContainsString('"type":"cash_tendered_field"', $schemaStr);
        $this->assertStringNotContainsString('CHANGE DUE TO CUSTOMER', $schemaStr);
        $this->assertTrue($pillsByKey['add_customer']['keep_parent_sheet'] ?? false);

        // The whole drawer, including the new component, is valid SDUI.
        $this->assertEmpty(app(SchemaValidator::class)->validate($schema));
    }

    public function test_settlement_stays_in_app_with_a_valid_post_sale_sheet_and_login_free_pdf(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Post-Sale Widget',
            'code' => 'PS-01',
            'sale_price' => 60.00,
            'current_stock' => 10,
            'active' => true,
        ]);
        CashRegister::create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'opened_at' => now(),
            'opening_balance' => 0,
            'status' => 'open',
        ]);

        $response = $this->postJson('/api/tenant/pos/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 60.00]],
            'payment_method' => 'cash',
        ], $this->authHeaders());
        $response->assertOk()->assertJsonPath('success', true);

        // No key the SDUI client would auto-launch in an external browser, and
        // no signed web receipt URL at all — the native sheet carries everything.
        $this->assertNull($response->json('url'));
        $this->assertNull($response->json('print_url'));
        $this->assertNull($response->json('whatsapp_url'));
        $this->assertNull($response->json('receipt_pdf_url'));

        // The post-sale response is the native `show_post_sale_sheet` action
        // envelope — the Flutter client drives its own bottom sheet from it.
        $sheet = $response->json('post_sale_sheet');
        $this->assertIsArray($sheet);
        $this->assertSame('show_post_sale_sheet', $sheet['action']);
        $data = $sheet['data'];
        $this->assertSame($response->json('invoice_number'), $data['invoice_number']);
        $this->assertStringContainsString('/pdf-stream', $data['pdf_endpoint']);
        $this->assertArrayHasKey('customer_phone', $data);
        $this->assertArrayHasKey('lines', $data);
        $this->assertEqualsWithDelta(60.00, (float) $data['total'], 0.01);

        $sheetStr = json_encode($sheet, JSON_UNESCAPED_SLASHES);
        // No web-page launches: no signed receipt route, no old outline grid.
        $this->assertStringNotContainsString('tenant.sales.pdf', $sheetStr);
        $this->assertStringNotContainsString('receipt.signed.pdf', $sheetStr);
        $this->assertStringNotContainsString('/api/tenant/receipt/', $sheetStr);
        $this->assertStringNotContainsString('open_pdf', $sheetStr);

        // The prepended workbench summary card is a valid component tree with a
        // single primary button that fires the same native action.
        $card = SchemaResponse::postSaleActionSheet(Sale::findOrFail($data['sale_id']));
        $screen = SchemaResponse::screen('Post-Sale', [$card]);
        $this->assertEmpty(app(SchemaValidator::class)->validate($screen));
        $cardStr = json_encode($card, JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('Invoice & Receipt Options', $cardStr);
        $this->assertStringContainsString('"type":"show_post_sale_sheet"', $cardStr);
        $this->assertStringContainsString('Balance Paid', $cardStr);
        $this->assertStringNotContainsString('"type":"grid_view"', $cardStr);

        // The PDF endpoint streams application/pdf to an authenticated caller…
        $pdf = $this->get('/api/tenant/invoices/'.$data['sale_id'].'/pdf-stream', $this->authHeaders());
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        // …and rejects an unauthenticated one (no signature fallback).
        $this->getJson('/api/tenant/invoices/'.$data['sale_id'].'/pdf-stream')->assertStatus(401);
    }

    public function test_hold_order_parks_the_cart_without_touching_sales_or_stock(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Held Widget',
            'code' => 'HELD-01',
            'sale_price' => 40.00,
            'current_stock' => 12,
            'active' => true,
        ]);

        $response = $this->postJson('/api/tenant/pos/hold-order', [
            'items' => [
                ['product_id' => $product->id, 'name' => 'Held Widget', 'quantity' => 3, 'unit_price' => 40.00],
            ],
            'customer_name' => 'Layaway Larry',
            'notes' => 'Customer will collect tomorrow',
        ], $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
        $holdId = $response->json('hold_id');

        $held = Sale::withoutGlobalScope('company')->find($holdId);
        $this->assertSame('on_hold', $held->status);
        $this->assertSame('hold', $held->operation_type);
        $this->assertEqualsWithDelta(120.00, (float) $held->net_amount, 0.01);

        // Stock untouched — nothing was actually sold.
        $this->assertSame(12.0, (float) $product->fresh()->current_stock);
        // Stays out of completed sales.
        $this->assertSame(0, Sale::withoutGlobalScope('company')
            ->where('company_id', $this->company->id)
            ->where('status', 'completed')
            ->count());
    }

    public function test_percentage_discount_from_the_discount_modal_is_resolved_against_subtotal(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Discountable Kit',
            'code' => 'DISC-01',
            'sale_price' => 200.00,
            'current_stock' => 10,
            'active' => true,
        ]);
        CashRegister::create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'opened_at' => now(),
            'opening_balance' => 0,
            'status' => 'open',
        ]);

        $response = $this->postJson('/api/tenant/pos/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 200.00]],
            'payment_method' => 'cash',
            'discount' => 10,
            'discount_type' => 'percent',
        ], $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
        $saleId = $response->json('sale.id') ?? $response->json('sale_id') ?? $response->json('id');
        $sale = Sale::withoutGlobalScope('company')->find($saleId);
        // 10% of the 400.00 subtotal == 40.00 off, not a flat 10.00.
        $this->assertEqualsWithDelta(40.00, (float) $sale->discount, 0.01);
    }

    public function test_universal_checkout_records_sale_inventory_and_payments(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Universal Drill Set',
            'code' => 'DRILL-01',
            'sale_price' => 50.00,
            'current_stock' => 20,
            'active' => true,
        ]);

        $cashRegister = CashRegister::create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'opened_at' => now(),
            'opening_balance' => 100.00,
            'status' => 'open',
        ]);

        $payload = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 50.00,
                ],
            ],
            'customer_name' => 'Walk-in Customer',
            'payment_method' => 'cash',
            'tendered' => 120.00,
        ];

        $response = $this->postJson('/api/tenant/pos/checkout', $payload, $this->authHeaders());
        $response->assertOk();
        $response->assertJsonPath('success', true);

        $saleId = $response->json('sale.id');
        $this->assertNotNull($saleId);

        // Verify product stock decremented
        $product->refresh();
        $this->assertSame(18, (int) $product->current_stock);

        // Verify sale row in core database
        $sale = Sale::find($saleId);
        $this->assertNotNull($sale);
        $this->assertSame('completed', $sale->status);
        $this->assertSame('100.00', number_format((float) $sale->total, 2, '.', ''));
        $this->assertSame($cashRegister->id, $sale->cash_register_id);

        // Verify OrderPayment created with tendered and change
        $payment = OrderPayment::where('sale_id', $saleId)->first();
        $this->assertNotNull($payment);
        $this->assertSame('cash', $payment->payment_method);
        $this->assertSame('100.00', number_format((float) $payment->amount, 2, '.', ''));
        $this->assertSame('120.00', number_format((float) $payment->tendered, 2, '.', ''));
        $this->assertSame('20.00', number_format((float) $payment->change_returned, 2, '.', ''));

        // Settlement returns the native in-app post-sale sheet action — never a
        // top-level url/print_url or a signed web receipt link the client would
        // auto-open in an external browser.
        $this->assertNull($response->json('url'));
        $this->assertNull($response->json('print_url'));
        $this->assertNull($response->json('receipt_pdf_url'));
        $this->assertSame('show_post_sale_sheet', $response->json('post_sale_sheet.action'));
        $this->assertStringContainsString('/pdf-stream', (string) $response->json('post_sale_sheet.data.pdf_endpoint'));
    }

    public function test_universal_checkout_supports_split_payments_and_khata_due_tracking(): void
    {
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Acme Wholesale',
            'phone' => '+1555123456',
            'due_balance' => 0.00,
        ]);

        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Pro Router Table',
            'code' => 'ROUTER-01',
            'sale_price' => 100.00,
            'current_stock' => 10,
            'active' => true,
        ]);

        // 1. Test Split Payments (Cash $40 + Card $30 + Due $30)
        $splitPayload = [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100.00],
            ],
            'customer_id' => $customer->id,
            'payments' => [
                ['method' => 'cash', 'amount' => 40.00],
                ['method' => 'card', 'amount' => 30.00],
            ],
        ];

        $response = $this->postJson('/api/tenant/pos/checkout', $splitPayload, $this->authHeaders());
        $response->assertOk();
        $saleId = $response->json('sale.id');

        $sale = Sale::find($saleId);
        $this->assertSame('70.00', number_format((float) $sale->paid_amount, 2, '.', ''));
        $this->assertSame('30.00', number_format((float) $sale->due_amount, 2, '.', ''));
        $this->assertSame('partially_paid', $sale->payment_status);

        // Core CustomerLedgerService & SaleObserver guarantee Khata update
        $customer->refresh();
        $this->assertSame('30.00', number_format((float) $customer->due_balance, 2, '.', ''));
        $this->assertDatabaseHas('customer_ledgers', [
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'type' => 'invoice',
        ]);
    }

    public function test_perfex_style_module_package_upload_and_auto_navigation_hydration(): void
    {
        $key = 'hardwareshop';
        $sourceDir = storage_path('framework/testing/'.$key.'_src');
        File::deleteDirectory($sourceDir);
        File::makeDirectory($sourceDir.'/Database/Migrations', 0700, true);

        // Perfex CRM manifest with inherits_ui: "universal_pos" and id/route fields
        File::put($sourceDir.'/module.json', json_encode([
            'key' => $key,
            'name' => 'Hardware Shop Pro',
            'version' => '1.0.0',
            'author' => 'Perfex Partner',
            'inherits_ui' => 'universal_pos',
            'requires_license' => false,
            'navigation' => [
                [
                    'id' => 'hardware_management',
                    'title' => 'Hardware Management',
                    'color' => '#b45309',
                    'items' => [
                        [
                            'title' => 'Hardware POS Register',
                            'route' => '/api/tenant/views/hardwareshop-pos',
                            'icon' => 'build',
                        ],
                    ],
                ],
            ],
        ]));

        $zipPath = storage_path('framework/testing/'.$key.'.zip');
        @unlink($zipPath);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFile($sourceDir.'/module.json', 'module.json');
        $zip->close();

        $uploaded = UploadedFile::fake()->createWithContent($key.'.zip', file_get_contents($zipPath));

        $service = app(ModulePackageService::class);
        $module = $service->install($uploaded, $this->admin->id);

        $this->assertNotNull($module);
        $this->assertSame('hardwareshop', $module->slug);
        $this->assertSame('universal_pos', $module->layout_type);
        $this->assertSame('universal_pos', $module->features['inherits_ui'] ?? null);

        // Activate module (Perfex plugin pattern)
        $service->activate($module, $this->admin->id);
        $module->refresh();
        $this->assertTrue($module->is_active);

        // Auto-hydration into Tenant Navigation
        $nav = TenantNavRegistry::getEffectiveNavForTenant($this->company);
        $sectionKeys = array_column($nav, 'key');
        $this->assertContains('hardware_management', $sectionKeys);

        // Verify collapsed-by-default rule
        foreach ($nav as $sec) {
            $this->assertFalse($sec['initially_expanded'] ?? true);
            foreach ($sec['items'] ?? [] as $item) {
                if (! empty($item['children'])) {
                    $this->assertFalse($item['initially_expanded'] ?? true);
                    $this->assertFalse($item['expanded'] ?? true);
                }
            }
        }

        // Verify dynamic view endpoint renders Universal POS screen
        $posView = $this->getJson('/api/tenant/views/hardwareshop-pos', $this->authHeaders());
        $posView->assertOk();
        $schema = $posView->json('schema');
        $this->assertSame('pos_screen', $schema['type']);
        $this->assertArrayHasKey('catalog', $schema);
        $this->assertArrayHasKey('cart_bar', $schema);
    }

    public function test_centralized_invoice_dispatch_and_print_urls(): void
    {
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'John Buyer',
            'phone' => '+1234567890',
            'email' => 'john@buyer.test',
        ]);

        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'INV-9999',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'user_id' => $this->admin->id,
            'total' => 150.00,
            'net_amount' => 150.00,
            'paid_amount' => 150.00,
            'due_amount' => 0.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
            'operation_type' => 'sale',
            'items' => [],
        ]);

        // WhatsApp Invoice Dispatch
        $resWa = $this->postJson("/api/tenant/sales/{$sale->id}/send-invoice", [
            'channel' => 'whatsapp',
        ], $this->authHeaders());
        $resWa->assertOk();
        $resWa->assertJsonPath('success', true);
        $resWa->assertJsonPath('channel', 'whatsapp');
        $this->assertNotEmpty($resWa->json('whatsapp_url') ?? $resWa->json('url'));

        // Print URLs
        $resPrint = $this->postJson("/api/tenant/sales/{$sale->id}/print", [], $this->authHeaders());
        $resPrint->assertOk();
        $resPrint->assertJsonPath('success', true);
        $this->assertNotEmpty($resPrint->json('print_url'));
        $this->assertNotEmpty($resPrint->json('download_url'));
    }
}
