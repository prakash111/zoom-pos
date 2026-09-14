<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\TenantApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\leadmanagement\Models\LeadActivity;
use Tests\TestCase;

class LeadQuotationModalRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $customer;

    private Lead $lead;

    private string $token = 'zk_live_lead_quotation_modal_routing_test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path'     => base_path('module-packages/leadmanagement/Database/Migrations'),
            '--realpath' => true,
        ]);

        Plan::create([
            'name' => 'test-plan',
            'display_name' => 'Test Plan',
            'price' => 49,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'features' => ['pos' => true, 'quotes' => true, 'leads' => true],
            'limits' => ['products' => 100, 'users' => 5],
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Metro Retail Mart Pvt. Ltd.',
            'trade_name' => 'Metro Retail',
            'slug' => 'metro-retail',
            'email' => 'owner@metroretail.test',
            'country' => 'IN',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'plan_name' => 'test-plan',
            'expires_at' => now()->addMonth(),
            'quote_notes' => 'Account Name: Metro Retail Mart Pvt. Ltd.',
            'quote_terms' => 'This quotation is valid for 15 days from the date of issue; prices are subject to change.',
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Manager User',
            'email' => 'manager@metroretail.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Test Key',
            'token' => $this->token,
            'permissions' => ['*'],
            'active' => true,
        ]);

        $this->customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Prakash Kumar Singh',
            'phone' => '+919876543210',
            'email' => 'prakash@example.test',
        ]);

        $this->lead = Lead::create([
            'company_id' => $this->company->id,
            'lead_code' => 'LD-UPCJDPWE',
            'name' => 'Prakash Kumar Singh',
            'title' => 'Retail POS Setup Inquiry',
            'customer_id' => $this->customer->id,
            'phone' => '+919876543210',
            'email' => 'prakash@example.test',
            'stage' => 'qualified',
            'expected_value' => 25000.00,
            'notes' => 'Client requires thermal printer integration and barcode scanner.',
        ]);
    }

    public function test_lead_detail_action_buttons_route_create_quotation_to_create_modal(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/tenant/views/lead-detail?id={$this->lead->id}");

        $response->assertStatus(200);

        $createQuotation = $this->findNodeByLabel($response->json(), 'Create Quotation');
        $convertToInvoice = $this->findNodeByLabel($response->json(), 'Convert to Tax Invoice');

        $this->assertNotNull($createQuotation);
        $this->assertNotNull($convertToInvoice);
        $this->assertSame('button_primary', $createQuotation['type']);
        $this->assertSame('#166534', $createQuotation['background_color']);
        $this->assertSame('#FFFFFF', $createQuotation['foreground_color']);
        $this->assertSame('OPEN_BOTTOM_SHEET', $createQuotation['action_type']);
        $this->assertQuotationModalAction($createQuotation['action']);
        $this->assertSame('button_outlined', $convertToInvoice['type']);
    }

    public function test_all_leads_card_create_quote_uses_the_standard_modal_action_and_primary_color(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/tenant/views/leads?tab=all_leads');

        $response->assertOk();

        $createQuote = $this->findNodeByLabel($response->json(), 'Create quote');
        $this->assertNotNull($createQuote);
        $this->assertSame('primary', $createQuote['variant']);
        $this->assertSame('#166534', $createQuote['color']);
        $this->assertSame('#FFFFFF', $createQuote['style']['textColor']);
        $this->assertSame('OPEN_BOTTOM_SHEET', $createQuote['action_type']);
        $this->assertQuotationModalAction($createQuote['action']);
    }

    public function test_rest_lead_list_create_quote_is_a_renderable_primary_button(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/tenant/leads/list');

        $response->assertOk();

        $createQuote = $this->findNodeByLabel($response->json(), 'Create quote');
        $this->assertNotNull($createQuote);
        $this->assertSame('button_primary', $createQuote['type']);
        $this->assertSame('#166534', $createQuote['background_color']);
        $this->assertSame('OPEN_BOTTOM_SHEET', $createQuote['action_type']);
        $this->assertQuotationModalAction($createQuote['action']);
    }

    public function test_capture_lead_customer_search_uses_active_theme_tokens(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/tenant/views/leads?tab=capture');

        $response->assertOk();

        $selector = $this->findNodeByType($response->json(), 'customer_selector');
        $this->assertNotNull($selector);
        $this->assertSame('theme.surface', $selector['style']['dropdownBackgroundColor']);
        $this->assertSame('theme.divider', $selector['style']['borderColor']);
        $this->assertSame('theme.textPrimary', $selector['style']['titleColor']);
        $this->assertSame('theme.textSecondary', $selector['style']['subtitleColor']);
        $this->assertSame('#EF4444', $selector['style']['dueColor']);
    }

    public function test_quotation_create_modal_endpoint_prefills_lead_and_customer(): void
    {
        $response = $this->withToken($this->token)
            ->getJson("/api/v1/tenant/quotations/create-modal?lead_id={$this->lead->id}&customer_id={$this->customer->id}");

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertEquals('bottom_sheet', $json['type']);
        $this->assertEquals('native_quotation', $json['sheet_type']);
        $this->assertEquals('New quotation', $json['title']);
        $this->assertEquals($this->lead->id, $json['lead_id']);
        $this->assertEquals('LD-UPCJDPWE', $json['lead_code']);
        $this->assertEquals($this->customer->id, $json['customer_id']);
        $this->assertStringContainsString('Prakash Kumar Singh', $json['customer_name']);
        $this->assertStringContainsString("#{$this->customer->id}", $json['customer_name']);

        // Check components array for standard native modal inputs
        $components = $json['components'];
        $componentNames = array_column($components, 'name');
        $componentTypes = array_column($components, 'type');

        $this->assertContains('lead_id', $componentNames);
        $this->assertContains('customer_id', $componentNames);
        $this->assertContains('items', $componentNames);
        $this->assertContains('discount', $componentNames);
        $this->assertContains('tax_rule', $componentNames);
        $this->assertContains('notes', $componentNames);
        $this->assertContains('terms', $componentNames);

        $this->assertContains('customer_picker', $componentTypes);
        $this->assertContains('item_collection_picker', $componentTypes);
        $this->assertContains('button', $componentTypes);
    }

    public function test_quotation_store_links_lead_and_updates_stage_and_activity(): void
    {
        $payload = [
            'lead_id'       => $this->lead->id,
            'customer_id'   => $this->customer->id,
            'customer_name' => $this->customer->name,
            'discount'      => 500.00,
            'notes'         => 'Account Name: Metro Retail Mart Pvt. Ltd.',
            'terms'         => 'Valid for 15 days.',
            'items'         => [
                [
                    'name'     => 'Thermal Receipt Printer 80mm',
                    'price'    => 4500.00,
                    'quantity' => 1,
                    'tax_rate' => 18,
                ],
                [
                    'name'     => 'Handheld 2D Barcode Scanner',
                    'price'    => 2500.00,
                    'quantity' => 2,
                    'tax_rate' => 18,
                ],
            ],
        ];

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/tenant/quotations', $payload);

        $response->assertStatus(201);
        $quoteData = $response->json('quotation');

        $this->assertNotEmpty($quoteData);
        $this->assertDatabaseHas('sales', [
            'company_id'     => $this->company->id,
            'operation_type' => 'quotation',
            'customer_id'    => $this->customer->id,
            'lead_id'        => $this->lead->id,
        ]);

        // Verify lead stage updated to proposal_sent
        $freshLead = $this->lead->fresh();
        $this->assertEquals('proposal_sent', $freshLead->stage);

        // Verify activity log recorded
        $this->assertDatabaseHas('lead_mod_activities', [
            'company_id' => $this->company->id,
            'lead_id'    => $this->lead->id,
            'title'      => 'Quotation Created',
        ]);

        // Verify lead has linked quotations
        $this->assertCount(1, $freshLead->quotations);
    }

    private function assertQuotationModalAction(array $action): void
    {
        $this->assertSame('OPEN_BOTTOM_SHEET', $action['type']);
        $this->assertSame('OPEN_BOTTOM_SHEET', $action['action_type']);
        $this->assertSame('New quotation', $action['title']);
        $this->assertSame($action['endpoint'], $action['sheet_endpoint']);
        $this->assertStringStartsWith('/api/v1/tenant/quotations/create-modal?', $action['endpoint']);

        parse_str((string) parse_url($action['endpoint'], PHP_URL_QUERY), $query);
        $this->assertSame((string) $this->lead->id, $query['lead_id']);
        $this->assertSame((string) $this->customer->id, $query['customer_id']);
    }

    private function findNodeByLabel(mixed $node, string $label): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        if (($node['label'] ?? null) === $label) {
            return $node;
        }

        foreach ($node as $value) {
            if (($match = $this->findNodeByLabel($value, $label)) !== null) {
                return $match;
            }
        }

        return null;
    }

    private function findNodeByType(mixed $node, string $type): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        if (($node['type'] ?? null) === $type) {
            return $node;
        }

        foreach ($node as $value) {
            if (($match = $this->findNodeByType($value, $type)) !== null) {
                return $match;
            }
        }

        return null;
    }
}
