<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Reminder;
use App\Models\Sale;
use App\Models\TenantApiKey;
use App\Models\TenantNotification;
use App\Models\TenantNotificationGateway;
use App\Models\User;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DocumentPreviewNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $customer;

    private string $token = 'zk_live_document_preview_test';

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'document-preview-plan',
            'display_name' => 'Document Preview Plan',
            'price' => 49,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'features' => ['pos' => true, 'quotes' => true],
            'limits' => ['products' => 100, 'users' => 5],
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Zoom Preview Store',
            'trade_name' => 'Zoom Preview Store',
            'slug' => 'zoom-preview-store',
            'email' => 'owner@preview.test',
            'country' => 'IN',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'tax_id_label' => 'GSTIN',
            'tax_id' => '27AAAAA0000A1Z5',
            'plan_name' => 'document-preview-plan',
            'expires_at' => now()->addMonth(),
            'licensed_modules' => ['retail'],
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Preview Admin',
            'login' => 'preview_admin',
            'email' => 'admin@preview.test',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin',
            'active' => true,
        ]);

        $this->customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Asha Sharma',
            'phone' => '+91 98765 43210',
            'email' => 'asha@example.test',
        ]);

        TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Preview test terminal',
            'token' => $this->token,
            'permissions' => ['*'],
            'active' => true,
        ]);
    }

    public function test_all_document_types_expose_the_same_multi_format_preview_contract(): void
    {
        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_SMS,
            'provider' => TenantNotificationGateway::PROVIDER_GENERIC_HTTP,
            'is_enabled' => true,
            'credentials' => ['url' => 'https://sms.example.test/send'],
        ]);

        $invoice = $this->sale('INV-1001', 'sale');
        $quotation = $this->sale('QT-1001', 'quotation');

        foreach ([
            ['invoice', $invoice],
            ['sale', $invoice],
            ['quotation', $quotation],
        ] as [$type, $document]) {
            $response = $this->withToken($this->token)
                ->getJson("/api/v1/tenant/documents/{$type}/{$document->id}/preview-modal?format=thermal_58mm")
                ->assertOk()
                ->assertJsonPath('schema.type', 'bottom_sheet')
                ->assertJsonPath('schema.theme.surface', 'theme.surface')
                ->assertJsonPath('schema.theme.canvas', 'theme.canvas')
                ->assertJsonPath('schema.theme.divider', 'theme.divider')
                ->assertJsonPath('schema.theme.text_primary', 'theme.textPrimary')
                ->assertJsonPath('schema.background_color', '#0B1120')
                ->assertJsonPath('schema.loading_background_color', '#0B1120')
                ->assertJsonPath('schema.empty_background_color', '#0F172A')
                ->assertJsonPath('schema.components.0.type', 'segmented_tabs')
                ->assertJsonPath('schema.components.0.active_value', 'thermal_58mm')
                ->assertJsonPath('schema.components.0.active_background_color', '#10B981')
                ->assertJsonPath('schema.components.0.inactive_background_color', '#1E293B')
                ->assertJsonCount(4, 'schema.components.0.options')
                ->assertJsonPath('schema.components.1.type', 'document_preview_card')
                ->assertJsonPath('schema.components.1.background_color', '#0F172A')
                ->assertJsonPath('schema.components.1.loading_background_color', '#0B1120')
                ->assertJsonPath('schema.components.1.empty_text_color', '#CBD5E1');

            $components = collect($response->json('schema.components'));
            $this->assertTrue($components->contains(fn (array $component) => ($component['channel'] ?? null) === 'sms'));
            $this->assertTrue($components->contains(fn (array $component) => ($component['channel'] ?? null) === 'whatsapp'));
            $this->assertEmpty(app(SchemaValidator::class)->validate($response->json('schema')));
        }

        $this->withToken($this->token)
            ->get("/api/v1/tenant/documents/invoice/{$invoice->id}/render-html?format=a4")
            ->assertOk()
            ->assertSee('TAX INVOICE')
            ->assertSee('INV-1001')
            ->assertSee('Consulting Service');
    }

    public function test_dashboard_badge_and_notification_feed_are_tenant_scoped_sdui(): void
    {
        $invoice = $this->sale('INV-DUE-1', 'sale', dueAmount: 900);
        $invoice->forceFill(['due_date' => now()->subDay()])->save();

        Reminder::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'type' => 'lead_follow_up',
            'title' => 'Call the new lead',
            'due_date' => now()->addHour(),
            'status' => Reminder::STATUS_PENDING,
            'remindable_type' => 'lead',
            'remindable_id' => 88,
        ]);

        TenantNotification::create([
            'company_id' => $this->company->id,
            'category' => 'quotation',
            'title' => 'Quote accepted',
            'message' => 'QT-88 was accepted by the customer.',
            'read_status' => false,
        ]);

        $dashboard = $this->withToken($this->token)
            ->getJson('/api/v1/tenant/views/dashboard')
            ->assertOk()
            ->assertJsonPath('schema.app_bar.actions.2.type', 'notification_bell')
            ->assertJsonPath('schema.app_bar.actions.2.badge_count', 3)
            ->assertJsonPath('schema.app_bar.actions.2.action.type', 'OPEN_BOTTOM_SHEET')
            ->assertJsonPath('schema.app_bar.actions.2.action.endpoint', '/api/v1/tenant/notifications/feed');

        $this->assertEmpty(app(SchemaValidator::class)->validate($dashboard->json('schema')));

        $feed = $this->withToken($this->token)
            ->getJson('/api/v1/tenant/notifications/feed')
            ->assertOk()
            ->assertJsonPath('unread_count', 3)
            ->assertJsonPath('schema.type', 'bottom_sheet');

        $alertItems = collect($feed->json('schema.components'))
            ->flatMap(fn (array $component) => $component['children'] ?? [])
            ->where('type', 'notification_item');
        $categories = $alertItems->pluck('category');
        $this->assertTrue($categories->contains('invoice'));
        $this->assertTrue($categories->contains('lead'));
        $this->assertTrue($categories->contains('quotation'));
        $invoiceAlert = $alertItems->firstWhere('category', 'invoice');
        $this->assertSame('show_post_sale_sheet', $invoiceAlert['action']['type']);
        $this->assertStringContainsString('/pdf-stream', $invoiceAlert['action']['data']['pdf_endpoint']);
        $this->assertEmpty(app(SchemaValidator::class)->validate($feed->json('schema')));
    }

    private function sale(string $number, string $operationType, float $dueAmount = 0): Sale
    {
        return Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => $number,
            'operation_type' => $operationType,
            'items' => [[
                'name' => 'Consulting Service',
                'quantity' => 2,
                'unit_price' => 500,
                'line_total' => 1000,
            ]],
            'total' => 1180,
            'tax_amount' => 180,
            'paid_amount' => 1180 - $dueAmount,
            'due_amount' => $dueAmount,
            'payment_status' => $dueAmount > 0 ? 'partially_paid' : 'paid',
            'status' => $operationType === 'quotation' ? 'draft' : 'completed',
        ]);
    }
}
