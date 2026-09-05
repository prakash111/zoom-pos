<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TenantApiKey;
use App\Models\TenantSession;
use App\Models\User;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosViewsAndSalesHistoryTest extends TestCase
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
            'name' => 'Global Retail & Tech',
            'trade_name' => 'Global Retail POS',
            'slug' => 'global-retail-tech',
            'email' => 'admin@globalretail.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'enterprise',
            'expires_at' => now()->addDays(30),
            'licensed_modules' => ['retail', 'pharmacy', 'repair_technician', 'service_booking'],
            'operating_mode' => 'retail',
        ]);

        $this->admin = User::create([
            'company_id' => $this->company->id,
            'name' => 'Jane Store Admin',
            'login' => 'jane_admin',
            'email' => 'jane@globalretail.com',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin',
            'active' => true,
        ]);

        $this->token = 'test_token_'.Str::random(32);
        TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'name' => 'Android POS Station 1',
            'token' => $this->token,
            'active' => true,
            'last_used_at' => now(),
        ]);
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
            'X-Company-Id' => (string) $this->company->id,
        ];
    }

    public function test_native_pos_views_render_valid_sdui_schemas(): void
    {
        Product::create([
            'company_id' => $this->company->id,
            'name' => 'Wireless Optical Mouse',
            'sku' => 'MOU-001',
            'sale_price' => 25.00,
            'current_stock' => 50,
            'active' => true,
        ]);

        $views = ['pos', 'retail-pos', 'salon-pos'];

        foreach ($views as $view) {
            $response = $this->getJson("/api/tenant/views/{$view}", $this->authHeaders());
            $response->assertStatus(200);
            $response->assertJsonPath('success', true);
            $response->assertJsonPath('view', $view);

            $schema = $response->json('schema');
            $this->assertIsArray($schema);
            $this->assertNotEmpty($schema['components']);

            $errors = app(SchemaValidator::class)->validate($schema);
            $this->assertEmpty($errors, "Schema validation failed for view '{$view}': ".json_encode($errors));
        }

        // pharmacy-pos and repair-pos return the newer `pos_screen` universal
        // contract (see PosScreenBuilder) instead of a generic component
        // tree — covered in detail by PharmacyAndRepairPosTest, asserted
        // lightly here just to keep this "every POS view responds" smoke
        // test complete.
        foreach (['pharmacy-pos', 'repair-pos'] as $view) {
            $response = $this->getJson("/api/tenant/views/{$view}", $this->authHeaders());
            $response->assertStatus(200);
            $response->assertJsonPath('success', true);
            $response->assertJsonPath('view', $view);
            $response->assertJsonPath('schema.type', 'pos_screen');

            $errors = app(SchemaValidator::class)->validate($response->json('schema'));
            $this->assertEmpty($errors, "Schema validation failed for view '{$view}': ".json_encode($errors));
        }
    }

    public function test_sales_and_invoices_view_renders_chronological_register(): void
    {
        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'INV-0001',
            'customer_name' => 'Michael Scott',
            'user_id' => $this->admin->id,
            'total' => 150.00,
            'paid_amount' => 100.00,
            'due_amount' => 50.00,
            'payment_method' => 'cash',
            'payment_status' => 'partial',
            'status' => 'completed',
            'operation_type' => 'sale',
            'items' => [
                ['name' => 'Paper Ream', 'quantity' => 10, 'unit_price' => 15.00],
            ],
        ]);

        $response = $this->getJson('/api/tenant/views/sales', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('view', 'sales');

        $schema = $response->json('schema');
        $this->assertIsArray($schema);
        $errors = app(SchemaValidator::class)->validate($schema);
        $this->assertEmpty($errors);

        // Verify invoice alias renders same
        $aliasResponse = $this->getJson('/api/tenant/views/invoices', $this->authHeaders());
        $aliasResponse->assertStatus(200);
        $aliasResponse->assertJsonPath('success', true);
    }

    public function test_sales_post_invoicing_dispatch_and_print(): void
    {
        $sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'INV-0002',
            'customer_name' => 'Dwight Schrute',
            'user_id' => $this->admin->id,
            'total' => 200.00,
            'paid_amount' => 200.00,
            'due_amount' => 0.00,
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'status' => 'completed',
            'operation_type' => 'sale',
        ]);

        // 1. WhatsApp dispatch
        $resWa = $this->postJson("/api/tenant/sales/{$sale->id}/send-invoice", [
            'channel' => 'whatsapp',
            'recipient' => '+1234567890',
        ], $this->authHeaders());
        $resWa->assertStatus(200);
        $resWa->assertJsonPath('success', true);
        $this->assertNotEmpty($resWa->json('whatsapp_url'));

        // 2. SMS dispatch
        $resSms = $this->postJson("/api/tenant/sales/{$sale->id}/send-invoice", [
            'channel' => 'sms',
            'recipient' => '+1234567890',
        ], $this->authHeaders());
        $resSms->assertStatus(200);
        $resSms->assertJsonPath('success', true);

        // 3. Email dispatch
        $resEmail = $this->postJson("/api/tenant/sales/{$sale->id}/send-invoice", [
            'channel' => 'email',
            'recipient' => 'dwight@schrute.com',
        ], $this->authHeaders());
        $resEmail->assertStatus(200);
        $resEmail->assertJsonPath('success', true);

        // 4. Print / PDF URL
        $resPrint = $this->postJson("/api/tenant/sales/{$sale->id}/print", [], $this->authHeaders());
        $resPrint->assertStatus(200);
        $resPrint->assertJsonPath('success', true);
        $this->assertNotEmpty($resPrint->json('print_url'));
        $this->assertNotEmpty($resPrint->json('download_url'));
    }

    public function test_devices_sessions_multi_platform_query_and_revoke(): void
    {
        // 1. Web session
        $webToken = Str::random(40);
        TenantSession::create([
            'token' => $webToken,
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'ip' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'expires_at' => now()->addDays(7),
            'revoked' => false,
        ]);

        // 2. SDUI Devices View
        $viewRes = $this->getJson('/api/tenant/views/devices', $this->authHeaders());
        $viewRes->assertStatus(200);
        $viewRes->assertJsonPath('success', true);
        $schema = $viewRes->json('schema');
        $errors = app(SchemaValidator::class)->validate($schema);
        $this->assertEmpty($errors);

        // 3. API sessions endpoint
        $apiRes = $this->getJson('/api/devices', $this->authHeaders());
        $apiRes->assertStatus(200);
        $apiRes->assertJsonPath('success', true);
        $sessions = $apiRes->json('sessions');
        $this->assertIsArray($sessions);
        $this->assertGreaterThanOrEqual(2, count($sessions));

        // 4. Revoke web session
        $revokeWeb = $this->postJson("/api/devices/{$webToken}/revoke", [], $this->authHeaders());
        $revokeWeb->assertStatus(200);
        $revokeWeb->assertJsonPath('success', true);

        // Verify web session revoked
        $this->assertDatabaseHas('sessions', [
            'token' => $webToken,
            'revoked' => true,
        ]);

        // 5. Revoke terminal token
        $terminalToken = TenantApiKey::where('name', 'Android POS Station 1')->first();
        $revokeTerm = $this->postJson("/api/devices/{$terminalToken->id}/revoke", [], $this->authHeaders());
        $revokeTerm->assertStatus(200);
        $revokeTerm->assertJsonPath('success', true);

        $terminalToken->refresh();
        $this->assertFalse((bool) $terminalToken->active);
    }

    public function test_quotations_customers_cash_register_views_render_valid_sdui_schemas(): void
    {
        Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Jim Halpert',
            'phone' => '+1555123456',
            'email' => 'jim@dundermifflin.com',
            'due_balance' => 45.00,
        ]);

        Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'QUO-0001',
            'customer_name' => 'Jim Halpert',
            'user_id' => $this->admin->id,
            'total' => 350.00,
            'operation_type' => 'quotation',
            'status' => 'draft',
        ]);

        CashRegister::create([
            'company_id' => $this->company->id,
            'opened_by' => $this->admin->id,
            'opening_balance' => 100.00,
            'expected_closing_balance' => 100.00,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $views = ['quotations', 'customers', 'cash-register'];

        foreach ($views as $view) {
            $response = $this->getJson("/api/tenant/views/{$view}", $this->authHeaders());
            $response->assertStatus(200);
            $response->assertJsonPath('success', true);
            $response->assertJsonPath('view', $view);

            $schema = $response->json('schema');
            $this->assertIsArray($schema);
            $this->assertNotEmpty($schema['components']);

            $errors = app(SchemaValidator::class)->validate($schema);
            $this->assertEmpty($errors, "Schema validation failed for view '{$view}': ".json_encode($errors));
        }
    }

    public function test_navigation_drawer_accordion_is_collapsed_by_default_and_has_consistent_metadata(): void
    {
        $bootstrapRes = $this->getJson('/api/v1/pos/app/bootstrap?locale=en', $this->authHeaders());
        $bootstrapRes->assertStatus(200);

        $menuStructure = $bootstrapRes->json('menu_structure');
        $this->assertIsArray($menuStructure);
        $this->assertNotEmpty($menuStructure);

        $foundAccordion = false;
        foreach ($menuStructure as $section) {
            foreach ($section['items'] ?? [] as $item) {
                if (! empty($item['children'])) {
                    $foundAccordion = true;
                    // Accordion must be collapsed by default
                    $this->assertFalse($item['initially_expanded'] ?? true, "Parent item {$item['key']} must have initially_expanded: false");
                    $this->assertFalse($item['expanded'] ?? true, "Parent item {$item['key']} must have expanded: false");
                    $this->assertFalse($item['is_expanded'] ?? true, "Parent item {$item['key']} must have is_expanded: false");
                    $this->assertSame('accordion', $item['type']);

                    // Children must carry parent_id and type link
                    foreach ($item['children'] as $child) {
                        $this->assertSame($item['key'], $child['parent_id'] ?? $child['parent']);
                        $this->assertSame('link', $child['type']);
                    }
                }
            }
        }

        $this->assertTrue($foundAccordion, 'Expected to find at least one accordion parent item with children');
    }

    public function test_custom_nav_config_persists_and_serves_in_bootstrap_menu_structure(): void
    {
        $savePayload = [
            'sections' => [
                ['key' => 'cashier_sales', 'order' => 0],
                ['key' => 'administration', 'order' => 1],
            ],
            'items' => [
                ['key' => 'pos', 'section' => 'cashier_sales', 'order' => 0, 'visible' => true],
                ['key' => 'sales', 'section' => 'cashier_sales', 'order' => 1, 'visible' => true],
                ['key' => 'quotations', 'section' => 'cashier_sales', 'order' => 2, 'visible' => false],
            ],
        ];

        $saveRes = $this->postJson('/api/v1/pos/settings/nav-config', $savePayload, $this->authHeaders());
        $saveRes->assertStatus(200);
        $saveRes->assertJsonPath('success', true);

        // Verify that bootstrap serves the custom menu structure
        $bootstrapRes = $this->getJson('/api/v1/pos/app/bootstrap?locale=en', $this->authHeaders());
        $bootstrapRes->assertStatus(200);

        $customStructure = $bootstrapRes->json('menu_structure');
        $this->assertIsArray($customStructure);

        $cashierSection = collect($customStructure)->firstWhere('key', 'cashier_sales');
        $this->assertNotNull($cashierSection);

        $itemKeys = collect($cashierSection['items'])->pluck('key')->all();
        $this->assertContains('pos', $itemKeys);
        $this->assertContains('sales', $itemKeys);
        // Hidden items must be excluded from the live menu structure
        $this->assertNotContains('quotations', $itemKeys);
    }
}
