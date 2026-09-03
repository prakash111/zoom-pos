<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\Settings\Index as SuperAdminSettings;
use App\Models\Company;
use App\Models\KitchenTicket;
use App\Models\PushDevice;
use App\Models\PushNotificationSetting;
use App\Models\Sale;
use App\Models\TenantApiKey;
use App\Models\User;
use App\Services\Push\FirebasePushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class SystemPushNotificationArchitectureTest extends TestCase
{
    use ActsAsPlatformAdmin, ActsAsTenantUser, RefreshDatabase;

    public function test_superadmin_can_save_encrypted_global_push_configuration(): void
    {
        $this->actingAsSuperAdmin();
        $credentials = json_encode([
            'project_id' => 'zoom-pos-prod',
            'client_email' => 'firebase-admin@zoom-pos-prod.iam.gserviceaccount.com',
            'private_key' => '-----BEGIN PRIVATE KEY----- test -----END PRIVATE KEY-----',
        ], JSON_THROW_ON_ERROR);

        Livewire::test(SuperAdminSettings::class)
            ->set('activeTab', 'push')
            ->set('pushEnabled', true)
            ->set('fcmProjectId', 'zoom-pos-prod')
            ->set('fcmServiceAccountJson', $credentials)
            ->set('androidApiKey', 'public-android-api-key')
            ->set('androidAppId', '1:123456:android:abcdef')
            ->set('messagingSenderId', '123456')
            ->set('orderSound', 'alarm')
            ->set('invoiceSound', 'notification')
            ->call('savePushNotifications')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $settings = PushNotificationSetting::current();
        $this->assertTrue($settings->enabled);
        $this->assertSame($credentials, $settings->fcm_service_account_json);
        $this->assertNotSame($credentials, DB::table('push_notification_settings')->value('fcm_service_account_json'));
        $this->get(route('superadmin.settings.notifications'))->assertOk()->assertSee('Global push gateway');
        $this->getJson('/api/v1/pos/auth/push-config')
            ->assertOk()
            ->assertJsonPath('push.project_id', 'zoom-pos-prod')
            ->assertJsonPath('push.android_api_key', 'public-android-api-key')
            ->assertJsonMissingPath('push.fcm_service_account_json')
            ->assertJsonMissingPath('push.fcm_server_key');
    }

    public function test_tenant_settings_exclude_notification_credentials_and_write_routes(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        $apiKey = TenantApiKey::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'name' => 'Android POS',
            'token' => 'zk_live_test_push_architecture',
            'permissions' => ['*'],
            'active' => true,
        ]);

        $this->withToken($apiKey->token)->getJson('/api/v1/pos/settings')
            ->assertOk()
            ->assertJsonMissingPath('notifications');
        $this->withToken($apiKey->token)->putJson('/api/v1/pos/settings/notifications', [
            'whatsapp_api_token' => 'tenant-must-not-set-this',
        ])->assertNotFound();

        $this->get(route('tenant.settings.index'))->assertOk()
            ->assertDontSee("activeTab = 'notifications'", false)
            ->assertDontSee('WhatsApp & Messaging Settings')
            ->assertDontSee('FCM Server Key');
    }

    public function test_device_registration_is_tenant_scoped_and_revocable(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        $apiKey = TenantApiKey::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'name' => 'Android POS',
            'token' => 'zk_live_test_device_registration',
            'permissions' => ['*'],
            'active' => true,
        ]);

        $this->withToken($apiKey->token)->postJson('/api/v1/pos/push-devices', [
            'token' => 'fcm-device-token',
            'platform' => 'android',
            'device_name' => 'Kitchen Tablet',
        ])->assertOk()->assertJsonPath('success', true);

        $device = PushDevice::withoutGlobalScope('company')->where('token', 'fcm-device-token')->firstOrFail();
        $this->assertSame($company->id, $device->company_id);
        $this->assertSame($user->id, $device->user_id);

        $this->withToken($apiKey->token)->deleteJson('/api/v1/pos/push-devices', ['token' => 'fcm-device-token'])->assertOk();
        $this->assertNotNull($device->fresh()->revoked_at);
    }

    public function test_mobile_can_schedule_a_due_invoice_reminder_for_its_tenant(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();
        $apiKey = TenantApiKey::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'name' => 'Android POS',
            'token' => 'zk_live_test_due_schedule',
            'permissions' => ['*'],
            'active' => true,
        ]);
        $sale = Sale::withoutEvents(fn () => Sale::create([
            'company_id' => $company->id,
            'sale_number' => 'POS-DUE-SCHEDULE',
            'total' => 150,
            'paid_amount' => 50,
            'due_amount' => 100,
            'status' => 'completed',
            'payment_status' => 'partially_paid',
        ]));
        $reminderAt = now()->addDay()->startOfHour();

        $this->withToken($apiKey->token)->putJson("/api/v1/pos/receivables/{$sale->id}/reminder", [
            'due_date' => $reminderAt->toDateString(),
            'reminder_at' => $reminderAt->toIso8601String(),
        ])->assertOk()
            ->assertJsonPath('success', true);

        $sale->refresh();
        $this->assertSame($reminderAt->toDateString(), $sale->due_date->toDateString());
        $this->assertSame($reminderAt->timestamp, $sale->due_reminder_at->timestamp);
        $this->assertNull($sale->due_reminder_sent_at);

        $this->withToken($apiKey->token)->getJson("/api/v1/pos/sales/{$sale->id}")
            ->assertOk()
            ->assertJsonPath('sale.server_id', (string) $sale->id)
            ->assertJsonPath('sale.sale_number', 'POS-DUE-SCHEDULE');
    }

    public function test_scheduler_dispatches_due_order_and_invoice_once(): void
    {
        $company = Company::create(['name' => 'Scheduled Alerts Co', 'status' => 'active']);
        $sale = Sale::withoutEvents(fn () => Sale::create([
            'company_id' => $company->id,
            'sale_number' => 'POS-DUE-001',
            'total' => 100,
            'paid_amount' => 25,
            'due_amount' => 75,
            'due_date' => now()->toDateString(),
            'due_reminder_at' => now()->subMinute(),
            'status' => 'completed',
            'payment_status' => 'partially_paid',
        ]));
        $ticket = KitchenTicket::create([
            'company_id' => $company->id,
            'kot_number' => 'KOT-DELAYED',
            'service_type' => 'takeaway',
            'status' => KitchenTicket::STATUS_PREPARING,
            'items' => [['name' => 'Coffee', 'quantity' => 1]],
            'sent_to_kitchen_at' => now()->subMinutes(12),
            'prep_minutes' => 10,
            'intimation_minutes' => 2,
            'target_completion_at' => now()->subMinutes(2),
            'alarm_at' => now()->subMinutes(4),
        ]);

        $push = $this->mock(FirebasePushService::class);
        $push->shouldReceive('sendToCompany')->twice()->andReturn(1);

        $this->artisan('notifications:dispatch-scheduled')->assertSuccessful();
        $this->assertNotNull($ticket->fresh()->alarm_sent_at);
        $this->assertNotNull($sale->fresh()->due_reminder_sent_at);

        $this->artisan('notifications:dispatch-scheduled')->assertSuccessful();
    }
}
