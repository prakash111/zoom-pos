<?php

namespace Tests\Feature\Delivery;

use App\Models\Company;
use App\Models\Configuration;
use App\Models\MessageQueue;
use App\Models\Plan;
use App\Models\Sale;
use App\Services\Delivery\MessageQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MessageQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'trial', 'display_name' => 'Free Trial', 'price' => 0, 'currency' => 'USD',
            'billing_cycle' => 'monthly', 'duration_days' => 14,
            'features' => ['pos' => true], 'limits' => ['products' => 500, 'users' => 5], 'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Queue Test Co', 'slug' => 'queue-test-co', 'email' => 'owner@queuetest.com',
            'country' => 'US', 'currency' => 'USD', 'currency_symbol' => '$', 'document' => 'US-888',
            'plan_name' => 'trial', 'expires_at' => now()->addDays(14),
        ]);

        $this->sale = Sale::create([
            'company_id' => $this->company->id,
            'sale_number' => 'SALE-Q-0001',
            'customer_name' => 'Jane Doe',
            'total' => 42.00,
            'net_amount' => 42.00,
            'status' => 'completed',
            'items' => [['name' => 'Widget', 'price' => 42, 'quantity' => 1]],
        ]);
    }

    protected function tearDown(): void
    {
        // Guaranteed reset even if a test that flips the environment fails an
        // assertion mid-body — otherwise a leaked 'production' env would break
        // every other test in the suite that relies on the testing-mode mail shortcut.
        app()->detectEnvironment(fn () => 'testing');
        config(['mail.default' => 'array']);
        parent::tearDown();
    }

    public function test_email_send_succeeds_immediately_when_smtp_works(): void
    {
        Mail::fake();

        Configuration::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'key' => 'smtp_host', 'value' => 'smtp.example.com',
        ]);

        $result = app(MessageQueueService::class)->sendOrQueueEmail($this->sale, 'customer@example.com');

        $this->assertSame('sent', $result['status']);
        $this->assertSame(0, MessageQueue::withoutGlobalScopes()->where('company_id', $this->company->id)->count());
    }

    public function test_email_without_smtp_opens_device_composer_without_queueing(): void
    {
        Mail::fake();
        config(['mail.mailers.smtp.host' => null]);
        $result = app(MessageQueueService::class)->sendOrQueueEmail($this->sale, 'customer@example.com');

        $this->assertSame('manual_link', $result['status']);
        $this->assertStringStartsWith('mailto:', $result['url']);
        $this->assertStringContainsString(route('sales.public', $this->sale->sale_number), rawurldecode($result['url']));
        $this->assertSame(0, MessageQueue::withoutGlobalScopes()->where('company_id', $this->company->id)->count());
        Mail::assertNothingSent();
    }

    public function test_email_provider_failure_is_still_queued_for_retry(): void
    {
        Configuration::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'key' => 'smtp_host', 'value' => 'smtp.example.test',
        ]);
        $service = \Mockery::mock(MessageQueueService::class, [app(\App\Services\Invoice\InvoiceDeliveryService::class), app(\App\Services\WhatsApp\WhatsAppCloudApiClient::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('deliverEmail')->once()->andThrow(new \RuntimeException('Connection unavailable.'));
        $result = $service->sendOrQueueEmail($this->sale, 'customer@example.com');
        $this->assertSame('queued', $result['status']);
        $this->assertDatabaseHas('message_queue', ['sale_id' => $this->sale->id, 'status' => 'queued']);
    }

    public function test_invalid_email_throws_immediately_without_queueing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        try {
            app(MessageQueueService::class)->sendOrQueueEmail($this->sale, 'not-an-email');
        } finally {
            $this->assertSame(0, MessageQueue::withoutGlobalScopes()->where('company_id', $this->company->id)->count());
        }
    }

    public function test_whatsapp_without_credentials_returns_manual_link_and_does_not_queue(): void
    {
        $result = app(MessageQueueService::class)->sendOrQueueWhatsApp($this->sale, '+15551234567');

        $this->assertSame('manual_link', $result['status']);
        $this->assertStringStartsWith('whatsapp://send?', $result['url']);
        $this->assertSame(0, MessageQueue::withoutGlobalScopes()->where('company_id', $this->company->id)->count());
    }

    public function test_whatsapp_with_credentials_sends_via_cloud_api(): void
    {
        Configuration::withoutGlobalScopes()->insert([
            ['company_id' => $this->company->id, 'key' => 'whatsapp_phone_number_id', 'value' => '1234567890', 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => $this->company->id, 'key' => 'whatsapp_api_token', 'value' => 'test-token', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.123']]])]);

        $result = app(MessageQueueService::class)->sendOrQueueWhatsApp($this->sale, '+15551234567');

        $this->assertSame('sent', $result['status']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '1234567890/messages')
            && $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_whatsapp_api_failure_falls_back_to_queue(): void
    {
        Configuration::withoutGlobalScopes()->insert([
            ['company_id' => $this->company->id, 'key' => 'whatsapp_phone_number_id', 'value' => '1234567890', 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => $this->company->id, 'key' => 'whatsapp_api_token', 'value' => 'test-token', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Unreachable']], 500)]);

        $result = app(MessageQueueService::class)->sendOrQueueWhatsApp($this->sale, '+15551234567');

        $this->assertSame('queued', $result['status']);
        $row = MessageQueue::withoutGlobalScopes()->where('company_id', $this->company->id)->first();
        $this->assertSame('whatsapp', $row->type);
    }

    public function test_process_due_sends_a_queued_email_once_smtp_is_available_again(): void
    {
        Mail::fake();

        $row = MessageQueue::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'sale_id' => $this->sale->id,
            'type' => 'email',
            'recipient' => 'later@example.com',
            'payload' => ['custom_message' => null, 'attach_pdf' => true],
            'status' => MessageQueue::STATUS_QUEUED,
        ]);

        $result = app(MessageQueueService::class)->processDue($this->company);

        $this->assertSame(1, $result['sent']);
        $this->assertSame(MessageQueue::STATUS_SENT, $row->fresh()->status);
        $this->assertNotNull($row->fresh()->sent_at);
    }

    public function test_process_due_leaves_a_row_failed_and_retryable_when_still_unreachable(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['mail.default' => 'smtp']);

        $row = MessageQueue::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'sale_id' => $this->sale->id,
            'type' => 'email',
            'recipient' => 'later@example.com',
            'payload' => ['custom_message' => null, 'attach_pdf' => true],
            'status' => MessageQueue::STATUS_QUEUED,
        ]);

        $result = app(MessageQueueService::class)->processDue($this->company);

        $this->assertSame(1, $result['failed']);
        $fresh = $row->fresh();
        $this->assertSame(MessageQueue::STATUS_FAILED, $fresh->status);
        $this->assertSame(1, $fresh->attempts);
        $this->assertTrue($fresh->isRetryable());

        app()->detectEnvironment(fn () => 'testing');
    }
}
