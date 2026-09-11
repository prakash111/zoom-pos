<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Configuration;
use App\Models\Plan;
use App\Models\PushDevice;
use App\Models\PushNotificationSetting;
use App\Models\User;
use App\Services\Push\FirebasePushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Store Profile ▸ "Notifications & Sounds" tab: persistence
 * (SettingsApiController::updateNotificationSounds via the SDUI
 * form_submit endpoint) and its effect on FirebasePushService's outbound
 * payload.
 */
class NotificationSoundsSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'trial', 'display_name' => 'Free Trial', 'price' => 0.00,
            'currency' => 'USD', 'billing_cycle' => 'monthly', 'duration_days' => 14,
            'features' => ['pos' => true], 'limits' => ['products' => 500, 'users' => 5],
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Sound Test Co', 'slug' => 'sound-test-co',
            'email' => 'admin@soundtest.test', 'country' => 'US',
            'currency' => 'USD', 'currency_symbol' => '$',
            'plan_name' => 'trial', 'expires_at' => now()->addDays(14),
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@soundtest.test',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
    }

    private function token(): string
    {
        return $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@soundtest.test',
            'password' => 'secret123',
        ])->json('token');
    }

    public function test_saving_a_preset_persists_it_and_is_returned_on_reload(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/notification-sounds', [
                'order_sound_preset' => 'alarm',
                'delayed_order_sound' => 'siren',
                'sound_vibration_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('notification_sounds.order_sound_preset', 'alarm')
            ->assertJsonPath('notification_sounds.delayed_order_sound', 'siren')
            ->assertJsonPath('notification_sounds.sound_vibration_enabled', false);

        $this->assertSame('alarm', Configuration::withoutGlobalScopes()
            ->where('company_id', $this->company->id)->where('key', 'order_sound_preset')->value('value'));
    }

    public function test_custom_preset_requires_and_stores_the_custom_url(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/notification-sounds', [
                'order_sound_preset' => 'custom',
                'order_sound_custom_url' => 'https://cdn.example.com/sounds/kitchen-alert.mp3',
                'delayed_order_sound' => 'alarm',
            ])
            ->assertOk()
            ->assertJsonPath('notification_sounds.order_sound_custom_url', 'https://cdn.example.com/sounds/kitchen-alert.mp3');
    }

    public function test_custom_preset_without_a_url_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/notification-sounds', [
                'order_sound_preset' => 'custom',
                'delayed_order_sound' => 'alarm',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_sound_custom_url', 'details');
    }

    public function test_an_unknown_preset_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/notification-sounds', [
                'order_sound_preset' => 'air-horn',
                'delayed_order_sound' => 'alarm',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_sound_preset', 'details');
    }

    public function test_switching_away_from_custom_clears_the_stored_url(): void
    {
        $token = $this->token();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tenant/settings/notification-sounds', [
                'order_sound_preset' => 'custom',
                'order_sound_custom_url' => 'https://cdn.example.com/a.mp3',
                'delayed_order_sound' => 'alarm',
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tenant/settings/notification-sounds', [
                'order_sound_preset' => 'chime',
                'delayed_order_sound' => 'alarm',
            ])
            ->assertOk()
            ->assertJsonPath('notification_sounds.order_sound_custom_url', '');

        $this->assertSame('', Configuration::withoutGlobalScopes()
            ->where('company_id', $this->company->id)->where('key', 'order_sound_custom_url')->value('value'));
    }

    public function test_a_tenant_that_never_saved_gets_sane_defaults(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-profile?tab=sounds')
            ->assertOk()
            ->assertJsonPath('schema.components.0.initial_index', 4);
    }

    public function test_firebase_push_service_merges_the_tenants_custom_sound_into_the_outbound_payload(): void
    {
        Configuration::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'key' => 'order_sound_preset', 'value' => 'custom',
        ]);
        Configuration::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'key' => 'order_sound_custom_url', 'value' => 'https://cdn.example.com/kitchen-alert.mp3',
        ]);
        Configuration::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'key' => 'delayed_order_sound', 'value' => 'siren',
        ]);
        Configuration::withoutGlobalScopes()->create([
            'company_id' => $this->company->id, 'key' => 'sound_vibration_enabled', 'value' => '0',
        ]);

        PushNotificationSetting::current()->update(['enabled' => true, 'fcm_server_key' => 'legacy-server-key']);
        PushDevice::create([
            'company_id' => $this->company->id, 'user_id' => $this->admin->id,
            'token' => 'device-token-1', 'platform' => 'android',
        ]);

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['success' => 1, 'results' => [['message_id' => '1']]], 200),
        ]);

        app(FirebasePushService::class)->sendToCompany($this->company->id, ['type' => 'new_order']);

        Http::assertSent(function ($request) {
            $data = $request->data()['data'] ?? [];

            return ($data['tenant_order_sound'] ?? null) === 'https://cdn.example.com/kitchen-alert.mp3'
                && ($data['tenant_order_sound_preset'] ?? null) === 'custom'
                && ($data['tenant_delayed_order_sound'] ?? null) === 'siren'
                && ($data['tenant_sound_vibration_enabled'] ?? null) === 'false';
        });
    }

    public function test_firebase_push_service_falls_back_to_defaults_for_a_tenant_with_no_saved_preference(): void
    {
        PushNotificationSetting::current()->update(['enabled' => true, 'fcm_server_key' => 'legacy-server-key']);
        PushDevice::create([
            'company_id' => $this->company->id, 'user_id' => $this->admin->id,
            'token' => 'device-token-2', 'platform' => 'android',
        ]);

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['success' => 1, 'results' => [['message_id' => '1']]], 200),
        ]);

        app(FirebasePushService::class)->sendToCompany($this->company->id, ['type' => 'new_order']);

        Http::assertSent(function ($request) {
            $data = $request->data()['data'] ?? [];

            return ($data['tenant_order_sound'] ?? null) === 'chime'
                && ($data['tenant_delayed_order_sound'] ?? null) === 'alarm'
                && ($data['tenant_sound_vibration_enabled'] ?? null) === 'true';
        });
    }
}
