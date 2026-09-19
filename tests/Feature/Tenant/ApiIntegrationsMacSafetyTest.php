<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Settings\Index;
use App\Models\Company;
use App\Models\PaymentGatewaySetting;
use App\Models\PlatformBranding;
use App\Models\PushNotificationSetting;
use App\Models\TenantNotificationGateway;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class ApiIntegrationsMacSafetyTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsTenantUser;

    public function test_tenant_notification_gateway_handles_invalid_mac_without_throwing(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        // Insert a record with raw ciphertext from an old/different key
        $id = DB::table('tenant_notification_gateways')->insertGetId([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'channel' => TenantNotificationGateway::CHANNEL_WHATSAPP,
            'provider' => TenantNotificationGateway::PROVIDER_META_CLOUD,
            'is_enabled' => true,
            'credentials' => 'eyJpdiI6IjBHcjQvb05zWDBZalZycGciLCJ2YWx1ZSI6ImludmFsaWRfbWFjX2RhdGEiLCJtYWMiOiIwMTIzNDU2Nzg5YWJjZGVmIn0=',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gw = TenantNotificationGateway::withoutGlobalScope('company')->find($id);
        $this->assertNotNull($gw);

        // Accessing credentials must not throw DecryptException and must return an empty array
        $creds = $gw->credentials;
        $this->assertIsArray($creds);
        $this->assertEmpty($creds);
    }

    public function test_api_view_and_integrations_tab_render_cleanly_when_credentials_have_invalid_mac(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        // Insert invalid MAC rows for all channels
        foreach ([TenantNotificationGateway::CHANNEL_WHATSAPP, TenantNotificationGateway::CHANNEL_SMS, TenantNotificationGateway::CHANNEL_EMAIL, TenantNotificationGateway::CHANNEL_WEBHOOK] as $channel) {
            DB::table('tenant_notification_gateways')->insert([
                'company_id' => $company->id,
                'tenant_id' => $company->id,
                'channel' => $channel,
                'provider' => 'test_provider',
                'is_enabled' => true,
                'credentials' => 'eyJpdiI6IjBHcjQvb05zWDBZalZycGciLCJ2YWx1ZSI6ImludmFsaWRfbWFjX2RhdGEiLCJtYWMiOiIwMTIzNDU2Nzg5YWJjZGVmIn0=',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // SchemaResponse::apiView must render cleanly without throwing DecryptException
        $schema = SchemaResponse::apiView($company);
        $this->assertIsArray($schema);
        $this->assertSame('screen', $schema['type'] ?? null);
    }

    public function test_livewire_settings_integrations_mounts_cleanly_with_invalid_mac(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        DB::table('tenant_notification_gateways')->insert([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'channel' => TenantNotificationGateway::CHANNEL_WHATSAPP,
            'provider' => TenantNotificationGateway::PROVIDER_META_CLOUD,
            'is_enabled' => true,
            'credentials' => 'eyJpdiI6IjBHcjQvb05zWDBZalZycGciLCJ2YWx1ZSI6ImludmFsaWRfbWFjX2RhdGEiLCJtYWMiOiIwMTIzNDU2Nzg5YWJjZGVmIn0=',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($user, 'web')
            ->test(Index::class)
            ->assertOk();
    }

    public function test_safe_encrypted_attributes_encrypt_and_decrypt_with_current_app_key(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        $gw = new TenantNotificationGateway();
        $gw->company_id = $company->id;
        $gw->channel = TenantNotificationGateway::CHANNEL_WHATSAPP;
        $gw->provider = TenantNotificationGateway::PROVIDER_META_CLOUD;
        $gw->is_enabled = true;
        $gw->credentials = [
            'phone_number_id' => '987654321',
            'access_token' => 'secure_token_abc',
        ];
        $gw->save();

        $fresh = TenantNotificationGateway::withoutGlobalScope('company')->find($gw->id);
        $this->assertSame('987654321', $fresh->credentials['phone_number_id'] ?? null);
        $this->assertSame('secure_token_abc', $fresh->credentials['access_token'] ?? null);
    }
}
