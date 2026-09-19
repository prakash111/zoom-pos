<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Quotation;
use App\Models\Reminder;
use App\Models\Sale;
use App\Models\TenantApiKey;
use App\Models\TenantNotificationGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DispatchQuotationAndNotificationDismissTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $customer;

    private string $token = 'zk_live_dispatch_quotation_test_token';

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'test-plan',
            'display_name' => 'Test Plan',
            'price' => 49,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'features' => ['pos' => true, 'quotes' => true],
            'limits' => ['products' => 100, 'users' => 5],
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Test Store',
            'trade_name' => 'Test Store',
            'slug' => 'test-store',
            'email' => 'owner@teststore.test',
            'country' => 'IN',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'tax_id_label' => 'GSTIN',
            'tax_id' => '27AAAAA0000A1Z5',
            'plan_name' => 'test-plan',
            'expires_at' => now()->addMonth(),
            'licensed_modules' => ['retail'],
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Test Admin',
            'login' => 'test_admin',
            'email' => 'admin@teststore.test',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
            'active' => true,
        ]);

        $this->customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Prakash Kumar Singh',
            'phone' => '+919123456789',
            'email' => 'prakash@test.com',
        ]);

        TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Test Token',
            'token' => $this->token,
            'permissions' => ['*'],
            'active' => true,
        ]);

        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_SMS,
            'provider' => TenantNotificationGateway::PROVIDER_GENERIC_HTTP,
            'is_enabled' => true,
            'credentials' => ['url' => 'https://sms.example.test/send'],
        ]);

        TenantNotificationGateway::create([
            'company_id' => $this->company->id,
            'tenant_id' => $this->company->id,
            'channel' => TenantNotificationGateway::CHANNEL_EMAIL,
            'provider' => TenantNotificationGateway::PROVIDER_SMTP,
            'is_enabled' => true,
            'credentials' => [
                'host' => 'smtp.example.test',
                'port' => 587,
                'username' => 'user@example.test',
                'password' => 'secret',
                'from_address' => 'noreply@teststore.test',
                'from_name' => 'Test Store',
            ],
        ]);
    }

    public function test_quotation_dispatch_by_numeric_id_resolves_and_purges_intent(): void
    {
        Mail::fake();
        Http::fake([
            '*' => Http::response(['success' => true], 200),
        ]);

        // Create quotation with higher numeric ID 242 and sale_number QUO-0007
        $quotation = Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => 'QUO-0007',
            'operation_type' => 'quotation',
            'status' => 'draft',
            'total' => 25000,
            'items' => [['name' => 'POS Package', 'quantity' => 1, 'unit_price' => 25000, 'total' => 25000]],
        ]);

        // 1. Dispatch Quotation with numeric suffix '7' via Email
        $responseEmail = $this->withToken($this->token)
            ->postJson('/api/v1/tenant/dispatch/send', [
                'channel' => 'email',
                'type' => 'quotation',
                'id' => 7,
                'recipient' => 'client@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('document_id', (string) $quotation->id)
            ->assertJsonPath('document_type', 'quotation');

        // Verify that action, intent, url are strictly null (no ACTIVITY_NOT_FOUND)
        $this->assertNull($responseEmail->json('action'));
        $this->assertNull($responseEmail->json('intent'));
        $this->assertNull($responseEmail->json('url'));
        $this->assertNull($responseEmail->json('whatsapp_url'));

        // 2. Dispatch Quotation with id '7' via SMS
        $responseSms = $this->withToken($this->token)
            ->postJson('/api/v1/tenant/dispatch/send', [
                'channel' => 'sms',
                'type' => 'quotation',
                'id' => 7,
                'recipient' => '+919123456789',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull($responseSms->json('action'));
        $this->assertNull($responseSms->json('intent'));
        $this->assertNull($responseSms->json('url'));
        $this->assertNull($responseSms->json('whatsapp_url'));

        // 3. Dispatch Quotation via WhatsApp provides deep-link url
        $responseWa = $this->withToken($this->token)
            ->postJson('/api/v1/tenant/dispatch/send', [
                'channel' => 'whatsapp',
                'type' => 'quotation',
                'id' => 7,
                'recipient' => '+919123456789',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($responseWa->json('whatsapp_url'));
        $this->assertStringContainsString('QUO-0007', $responseWa->json('whatsapp_url'));
    }

    public function test_notification_feed_has_clear_all_header_and_dismiss_action(): void
    {
        $sale = Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => 'DEMO-RETAIL-INV-021',
            'operation_type' => 'sale',
            'status' => 'completed',
            'due_amount' => 5000,
            'total' => 5000,
            'due_date' => now()->subDay(),
        ]);

        $reminder = Reminder::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'title' => 'Follow up with prakash Kumar Singh',
            'status' => 'pending',
            'due_at' => now()->subHour(),
        ]);

        // 1. Get Feed
        $feed = $this->withToken($this->token)
            ->getJson('/api/v1/tenant/notifications/feed')
            ->assertOk()
            ->assertJsonPath('success', true);

        $components = $feed->json('schema.components');
        $this->assertNotEmpty($components);

        // First component is the header with Clear All button
        $this->assertEquals('row', $components[0]['type']);
        $clearBtn = collect($components[0]['children'])->firstWhere('type', 'button_danger');
        $this->assertNotNull($clearBtn);
        $this->assertEquals('Clear All', $clearBtn['label']);
        $this->assertEquals('/api/v1/tenant/notifications/clear-all', $clearBtn['action']['endpoint']);

        // Dismiss single reminder
        $this->withToken($this->token)
            ->postJson("/api/v1/tenant/notifications/lead/{$reminder->id}/dismiss")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals('dismissed', $reminder->fresh()->status);

        // Clear All remaining notifications
        $this->withToken($this->token)
            ->postJson('/api/v1/tenant/notifications/clear-all')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($sale->fresh()->due_reminder_dismissed_at);

        // Feed should now return empty state
        $emptyFeed = $this->withToken($this->token)
            ->getJson('/api/v1/tenant/notifications/feed')
            ->assertOk();

        $this->assertEquals('empty_state', $emptyFeed->json('schema.components.0.type'));
    }
}
