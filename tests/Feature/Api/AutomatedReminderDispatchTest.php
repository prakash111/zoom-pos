<?php

namespace Tests\Feature\Api;

use App\Jobs\DispatchAutomatedCustomerReminder;
use App\Models\AutomatedReminderDispatch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\TenantApiKey;
use App\Models\TenantNotificationGateway;
use App\Models\User;
use App\Services\Notifications\AutomatedReminderSettingsService;
use App\Services\Notifications\TenantNotificationDispatcherService;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomatedReminderDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $customer;

    private string $token = 'zk_live_automated_reminder_test';

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'automated-reminders-plan',
            'display_name' => 'Automated Reminders Plan',
            'price' => 49,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'features' => ['pos' => true, 'quotes' => true, 'settings' => true],
            'limits' => ['products' => 100, 'users' => 5],
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Reminder Test Store',
            'trade_name' => 'Reminder Test Store',
            'slug' => 'reminder-test-store',
            'email' => 'owner@reminders.test',
            'country' => 'IN',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'plan_name' => 'automated-reminders-plan',
            'expires_at' => now()->addMonth(),
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Reminder Admin',
            'email' => 'admin@reminders.test',
            'role' => 'admin',
        ]);

        $this->customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Customer One',
            'phone' => '+91 91234 56789',
            'email' => 'customer@example.test',
        ]);

        TenantApiKey::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Reminder test terminal',
            'token' => $this->token,
            'permissions' => ['*'],
            'active' => true,
        ]);
    }

    public function test_notifications_audio_schema_contains_and_persists_automated_reminder_card(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/tenant/settings/notifications-audio')
            ->assertOk()
            ->assertJsonPath('schema.components.2.type', 'card')
            ->assertJsonPath('schema.components.2.components.0.text', 'Automated Customer Reminders')
            ->assertJsonPath('schema.components.2.components.6.action.endpoint', '/api/v1/tenant/settings/auto-reminders')
            ->assertJsonPath('auto_reminders.auto_reminders_enabled', false);

        $this->assertEmpty(app(SchemaValidator::class)->validate($response->json('schema')));

        $this->withToken($this->token)
            ->postJson('/api/v1/tenant/settings/auto-reminders', [
                'auto_reminders_enabled' => true,
                'reminder_preferred_channel' => 'all_active',
                'reminder_schedule_frequency' => 'daily_evening',
                'reminder_target_documents' => 'both',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('auto_reminders.auto_reminders_enabled', true)
            ->assertJsonPath('auto_reminders.reminder_schedule_frequency', 'daily_evening');

        $this->withToken($this->token)
            ->getJson('/api/v1/tenant/settings/notifications-audio')
            ->assertOk()
            ->assertJsonPath('auto_reminders.auto_reminders_enabled', true)
            ->assertJsonPath('auto_reminders.reminder_schedule_frequency', 'daily_evening')
            ->assertJsonPath('schema.components.2.components.4.value', 'daily_evening');
    }

    public function test_command_queues_each_eligible_document_and_active_channel_only_once_per_cycle(): void
    {
        Queue::fake();
        $this->configureAllGateways();
        app(AutomatedReminderSettingsService::class)->save($this->company, [
            'auto_reminders_enabled' => true,
            'reminder_preferred_channel' => 'all_active',
            'reminder_schedule_frequency' => 'daily_morning',
            'reminder_target_documents' => 'both',
        ]);

        $this->createInvoiceAndQuotation();

        $this->artisan('app:dispatch-automated-reminders', ['--tenant' => $this->company->id])
            ->expectsOutputToContain('6 job(s) queued')
            ->assertSuccessful();

        Queue::assertPushed(DispatchAutomatedCustomerReminder::class, 6);
        $this->assertDatabaseCount('automated_reminder_dispatches', 6);

        $this->artisan('app:dispatch-automated-reminders', ['--tenant' => $this->company->id])
            ->expectsOutputToContain('6 duplicate(s)')
            ->assertSuccessful();

        Queue::assertPushed(DispatchAutomatedCustomerReminder::class, 6);
        $this->assertDatabaseCount('automated_reminder_dispatches', 6);
    }

    public function test_queued_jobs_perform_real_sms_whatsapp_and_smtp_dispatch_paths(): void
    {
        Mail::fake();
        Http::fake([
            'https://sms.example.test/*' => Http::response(['success' => true], 200),
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.automated-reminder']],
            ], 200),
        ]);

        $this->configureAllGateways();
        app(AutomatedReminderSettingsService::class)->save($this->company, [
            'auto_reminders_enabled' => true,
            'reminder_preferred_channel' => 'all_active',
            'reminder_schedule_frequency' => 'daily_morning',
            'reminder_target_documents' => 'invoices_only',
        ]);

        [$invoice] = $this->createInvoiceAndQuotation();
        foreach (['sms', 'whatsapp', 'email'] as $channel) {
            $recipient = $channel === 'email' ? $this->customer->email : $this->customer->phone;
            $dispatch = AutomatedReminderDispatch::create([
                'company_id' => $this->company->id,
                'sale_id' => $invoice->id,
                'document_type' => 'invoice',
                'channel' => $channel,
                'recipient' => $recipient,
                'cycle_key' => 'test:'.now()->toDateString(),
                'dispatch_key' => hash('sha256', "test|{$channel}|{$invoice->id}"),
                'scheduled_for' => now(),
                'status' => AutomatedReminderDispatch::STATUS_QUEUED,
            ]);

            (new DispatchAutomatedCustomerReminder($dispatch->id))->handle(
                app(TenantNotificationDispatcherService::class),
                app(AutomatedReminderSettingsService::class),
            );
        }

        $this->assertSame(
            3,
            AutomatedReminderDispatch::withoutGlobalScope('company')
                ->where('status', AutomatedReminderDispatch::STATUS_SENT)
                ->count()
        );
        $this->assertDatabaseHas('message_queue', ['company_id' => $this->company->id, 'type' => 'sms', 'status' => 'sent']);
        $this->assertDatabaseHas('message_queue', ['company_id' => $this->company->id, 'type' => 'whatsapp', 'status' => 'sent']);
        $this->assertDatabaseHas('message_queue', ['company_id' => $this->company->id, 'type' => 'email', 'status' => 'sent']);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://sms.example.test/'));
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://graph.facebook.com/'));
    }

    private function configureAllGateways(): void
    {
        foreach ([
            [
                'channel' => TenantNotificationGateway::CHANNEL_SMS,
                'provider' => TenantNotificationGateway::PROVIDER_GENERIC_HTTP,
                'credentials' => [
                    'url' => 'https://sms.example.test/send?phone={phone}&message={message}',
                    'method' => 'GET',
                ],
            ],
            [
                'channel' => TenantNotificationGateway::CHANNEL_WHATSAPP,
                'provider' => TenantNotificationGateway::PROVIDER_META_CLOUD,
                'credentials' => [
                    'phone_number_id' => '123456789',
                    'access_token' => 'whatsapp-test-token',
                ],
            ],
            [
                'channel' => TenantNotificationGateway::CHANNEL_EMAIL,
                'provider' => TenantNotificationGateway::PROVIDER_SMTP,
                'credentials' => [
                    'host' => 'smtp.example.test',
                    'port' => 587,
                    'username' => 'mailer@example.test',
                    'password' => 'secret',
                    'encryption' => 'tls',
                    'from_address' => 'mailer@example.test',
                    'from_name' => 'Reminder Test Store',
                ],
            ],
        ] as $gateway) {
            TenantNotificationGateway::create(array_merge($gateway, [
                'company_id' => $this->company->id,
                'tenant_id' => $this->company->id,
                'is_enabled' => true,
            ]));
        }
    }

    /** @return array{Sale, Sale} */
    private function createInvoiceAndQuotation(): array
    {
        $invoice = Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => 'INV-AUTO-001',
            'operation_type' => 'sale',
            'items' => [['name' => 'Invoice Item', 'quantity' => 1, 'price' => 500]],
            'total' => 500,
            'paid_amount' => 100,
            'due_amount' => 400,
            'due_date' => now()->subDay(),
            'status' => 'completed',
        ]);

        $quotation = Sale::create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'sale_number' => 'QT-AUTO-001',
            'operation_type' => 'quotation',
            'items' => [['name' => 'Quotation Item', 'quantity' => 1, 'price' => 750]],
            'total' => 750,
            'due_date' => now()->addDays(2),
            'status' => 'sent',
        ]);

        return [$invoice, $quotation];
    }
}
