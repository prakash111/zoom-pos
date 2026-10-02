<?php

namespace Tests\Feature\Tenant;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileDeviceStoreNameFormattingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $admin;
    private Store $store;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create(['name' => 'multi', 'display_name' => 'Multi Store', 'store_limit' => 5]);

        $this->company = Company::create([
            'name' => 'ZoomNearby Enterprise Demo',
            'slug' => 'zoomnearby-enterprise-demo',
            'email' => 'enterprise@zoomnearby.com',
            'country' => 'IN',
            'plan_name' => 'multi',
            'expires_at' => now()->addMonth(),
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'admin',
            'email' => 'admin@zoomnearby.com',
            'password' => 'secret123',
        ]);

        $this->store = Store::firstOrCreate(
            ['company_id' => $this->company->id, 'is_primary' => true],
            [
                'name' => 'ZoomNearby Enterprise Demo',
                'code' => 'ZNE-DEMO',
                'branch_code' => 'ZNE-DEMO',
                'tenant_id' => $this->company->id,
                'is_active' => true,
            ]
        );
        $this->store->update(['name' => 'ZoomNearby Enterprise Demo']);
        $this->admin->update(['current_store_id' => $this->store->id]);

        $res = $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@zoomnearby.com',
            'password' => 'secret123',
        ])->assertOk();

        $this->token = $res->json('token');
    }

    public function test_mobile_user_agent_returns_truncated_four_character_short_name_and_preserves_full_name(): void
    {
        $mobileAgents = [
            'Dart/3.1 (dart:io)',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148',
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Mobile Safari/537.36',
            'okhttp/4.9.2',
            'Zoomnearby-POS-Client/android',
        ];

        foreach ($mobileAgents as $ua) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => $ua,
                'Accept' => 'application/json',
            ])->getJson('/api/v1/tenant/stores');

            $response->assertOk()
                ->assertJsonPath('data.0.short_name', 'Zoom')
                ->assertJsonPath('data.0.name', 'ZoomNearby Enterprise Demo')
                ->assertJsonPath('data.0.full_name', 'ZoomNearby Enterprise Demo')
                ->assertJsonPath('stores.0.short_name', 'Zoom')
                ->assertJsonPath('stores.0.name', 'ZoomNearby Enterprise Demo')
                ->assertJsonPath('stores.0.full_name', 'ZoomNearby Enterprise Demo');
        }
    }

    public function test_mobile_client_platform_header_returns_four_character_short_name(): void
    {
        foreach (['android', 'ios', 'mobile'] as $platform) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'X-Client-Platform' => $platform,
                'Accept' => 'application/json',
            ])->getJson('/api/v1/tenant/stores');

            $response->assertOk()
                ->assertJsonPath('data.0.short_name', 'Zoom')
                ->assertJsonPath('data.0.name', 'ZoomNearby Enterprise Demo')
                ->assertJsonPath('data.0.full_name', 'ZoomNearby Enterprise Demo');
        }
    }

    public function test_desktop_browser_user_agent_returns_full_store_name(): void
    {
        $desktopAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0',
        ];

        foreach ($desktopAgents as $ua) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'User-Agent' => $ua,
                'Accept' => 'application/json',
            ])->getJson('/api/v1/tenant/stores');

            $response->assertOk()
                ->assertJsonPath('data.0.short_name', 'Zoom')
                ->assertJsonPath('data.0.name', 'ZoomNearby Enterprise Demo')
                ->assertJsonPath('data.0.full_name', 'ZoomNearby Enterprise Demo');
        }
    }

    public function test_flutter_web_client_returns_full_store_name(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'User-Agent' => 'Zoomnearby-POS-Client/web',
            'X-Client-Platform' => 'web',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/tenant/stores');

        $response->assertOk()
            ->assertJsonPath('data.0.short_name', 'Zoom')
            ->assertJsonPath('data.0.name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('data.0.full_name', 'ZoomNearby Enterprise Demo');
    }

    public function test_store_switch_endpoint_returns_three_character_name_on_mobile_and_full_on_desktop(): void
    {
        // Mobile switch request
        $mobileSwitch = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'User-Agent' => 'Dart/3.1 (dart:io)',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/tenant/stores/switch', [
            'store_id' => $this->store->id,
        ]);

        $mobileSwitch->assertOk()
            ->assertJsonPath('current_store.short_name', 'Zoom')
            ->assertJsonPath('current_store.name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('current_store.full_name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('store.short_name', 'Zoom')
            ->assertJsonPath('store.name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('store.full_name', 'ZoomNearby Enterprise Demo');

        // Desktop switch request
        $desktopSwitch = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/tenant/stores/switch', [
            'store_id' => $this->store->id,
        ]);

        $desktopSwitch->assertOk()
            ->assertJsonPath('current_store.short_name', 'Zoom')
            ->assertJsonPath('current_store.name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('current_store.full_name', 'ZoomNearby Enterprise Demo');
    }

    public function test_store_profile_endpoint_returns_three_character_name_on_mobile_and_full_on_desktop(): void
    {
        // Mobile request
        $mobileRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'User-Agent' => 'Dart/3.1 (dart:io)',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/tenant/store-profile');

        $mobileRes->assertOk()
            ->assertJsonPath('short_name', 'Zoom')
            ->assertJsonPath('name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('full_name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('store_name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('tenant.short_name', 'Zoom')
            ->assertJsonPath('tenant.name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('tenant.full_name', 'ZoomNearby Enterprise Demo');

        // Desktop request
        $desktopRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/tenant/store-profile');

        $desktopRes->assertOk()
            ->assertJsonPath('short_name', 'Zoom')
            ->assertJsonPath('name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('full_name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('store_name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('tenant.short_name', 'Zoom')
            ->assertJsonPath('tenant.name', 'ZoomNearby Enterprise Demo')
            ->assertJsonPath('tenant.full_name', 'ZoomNearby Enterprise Demo');
    }

    public function test_short_store_name_is_not_altered_or_padded(): void
    {
        $this->store->update(['name' => 'AB']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'User-Agent' => 'Dart/3.1 (dart:io)',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/tenant/stores');

        $response->assertOk()
            ->assertJsonPath('data.0.short_name', 'AB')
            ->assertJsonPath('data.0.name', 'AB')
            ->assertJsonPath('data.0.full_name', 'AB');

        $this->store->update(['name' => 'ZNE']);

        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'User-Agent' => 'Dart/3.1 (dart:io)',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/tenant/stores');

        $response2->assertOk()
            ->assertJsonPath('data.0.short_name', 'ZNE')
            ->assertJsonPath('data.0.name', 'ZNE')
            ->assertJsonPath('data.0.full_name', 'ZNE');
    }
}
