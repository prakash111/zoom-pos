<?php

namespace Tests\Feature;

use App\Http\Middleware\PreventDemoModifications;
use App\Models\Company;
use App\Models\TenantApiKey;
use App\Models\User;
use App\Services\Modular\ModuleRegistry;
use App\Services\Navigation\TenantNavRegistry;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\PlatformDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlatformDefaultsSeeder::class, PermissionsTableSeeder::class]);
    }

    private function tenant(bool $demo): array
    {
        $company = Company::create([
            'name' => $demo ? 'Retail Demo Store' : 'Real Store',
            'slug' => $demo ? 'retail-demo' : 'real-store',
            'email' => $demo ? 'retail@demo.com' : 'real@store.test',
            'country' => 'US', 'currency' => 'USD', 'currency_symbol' => '$',
            'plan_name' => 'trial', 'expires_at' => now()->addDays(14),
            'is_demo' => $demo,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => $company->email,
            'password' => Hash::make('demo1234'),
            'role' => 'admin',
            'is_demo' => $demo,
        ]);
        $key = TenantApiKey::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'name' => 'Test',
            'token' => 'zk_live_'.bin2hex(random_bytes(16)),
            'permissions' => ['*'],
            'active' => true,
        ]);

        return [$company, $user, $key];
    }

    public function test_demo_seeder_provisions_five_flagged_accounts(): void
    {
        config(['app.demo_mode' => true]);

        $this->seed(DemoAccountsSeeder::class);

        foreach (DemoAccountsSeeder::ACCOUNTS as $meta) {
            $user = User::withoutGlobalScopes()->where('email', $meta['email'])->first();
            $this->assertNotNull($user, "missing demo user {$meta['email']}");
            $this->assertTrue((bool) $user->is_demo);
            $this->assertTrue(Hash::check(DemoAccountsSeeder::PASSWORD, $user->password));

            $company = Company::withoutGlobalScopes()->find($user->company_id);
            $this->assertTrue((bool) $company->is_demo);
            $this->assertSame($meta['pos_mode'], $company->pos_mode);

            // restaurant_mode_locked hides the Cafe & Restaurant vertical —
            // it must be OFF for the restaurant demo and ON for the others.
            $isRestaurant = $meta['pos_mode'] === 'restaurant';
            $this->assertSame(! $isRestaurant, (bool) $company->restaurant_mode_locked,
                "restaurant_mode_locked wrong for {$meta['email']}");
            $this->assertSame($isRestaurant, $company->isRestaurantMode(),
                "isRestaurantMode() wrong for {$meta['email']}");

            $navKeys = collect(TenantNavRegistry::getEffectiveNavForTenant($company))
                ->map(fn ($s) => $s['key'] ?? $s['id'] ?? null)->all();
            if ($isRestaurant) {
                $this->assertContains('restaurant_operations', $navKeys,
                    'cafe demo store is missing the Cafe & Restaurant nav section');
            } else {
                $this->assertNotContains('restaurant_operations', $navKeys,
                    "{$meta['email']} should not show the restaurant nav section");
            }
        }
    }

    public function test_cafe_demo_store_exposes_restaurant_tables_kot_and_kds_nav_items(): void
    {
        config(['app.demo_mode' => true]);
        $this->seed(DemoAccountsSeeder::class);

        $company = Company::withoutGlobalScopes()->where('email', 'cafe@demo.com')->firstOrFail();

        $sections = collect(TenantNavRegistry::getEffectiveNavForTenant($company))
            ->keyBy(fn ($s) => $s['key'] ?? $s['id'] ?? '');

        $this->assertTrue($sections->has('restaurant_operations'));

        $items = collect($sections['restaurant_operations']['items'] ?? [])
            ->map(fn ($i) => $i['key'] ?? $i['component'] ?? null)->all();

        $this->assertContains('floor_plan', $items, 'Tables & Floor Plan missing');
        $this->assertContains('kitchen_display', $items, 'Kitchen Display (KDS) missing');
        $this->assertContains('dining_history', $items, 'KOT register missing');

        // available_modes must report restaurant so the dashboard quick bar
        // renders the Tables / Pending KOTs shortcuts.
        $this->assertContains('restaurant', ModuleRegistry::availableModes($company));
    }

    public function test_demo_seeder_is_a_noop_when_demo_mode_is_off(): void
    {
        config(['app.demo_mode' => false]);

        $this->seed(DemoAccountsSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'retail@demo.com']);
    }

    public function test_demo_account_cannot_change_password_or_touch_settings(): void
    {
        config(['app.demo_mode' => true]);
        [$company, , $key] = $this->tenant(demo: true);

        // Authenticated settings + credential writes.
        foreach ([
            '/api/tenant/profile/change-password',
            '/api/tenant/settings/branding',
            '/api/tenant/settings/tax-rules',
            '/api/tenant/settings/payment-methods',
        ] as $url) {
            $this->withToken($key->token)->postJson($url, ['x' => 1])
                ->assertStatus(403)
                ->assertJsonPath('status', 'error')
                ->assertJsonPath('message', PreventDemoModifications::MESSAGE);
        }

        // Unauthenticated forgot/reset, keyed by the demo e-mail.
        foreach (['/api/tenant/password/email', '/api/tenant/password/reset'] as $url) {
            $this->postJson($url, ['email' => $company->email])
                ->assertStatus(403)
                ->assertJsonPath('message', PreventDemoModifications::MESSAGE);
        }

        // PUT branding on the /v1/pos surface too.
        $this->withToken($key->token)
            ->putJson('/api/v1/pos/settings/branding', ['x' => 1])
            ->assertStatus(403)
            ->assertJsonPath('message', PreventDemoModifications::MESSAGE);
    }

    public function test_demo_account_cannot_upload_files(): void
    {
        config(['app.demo_mode' => true]);
        [, , $key] = $this->tenant(demo: true);

        $this->withToken($key->token)
            ->post('/api/v1/pos/settings/profile/logo', [
                'logo' => File::image('x.png'),
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', PreventDemoModifications::MESSAGE);
    }

    public function test_demo_account_can_still_read_and_make_a_sale(): void
    {
        config(['app.demo_mode' => true]);
        [, , $key] = $this->tenant(demo: true);

        // A GET is never blocked.
        $this->withToken($key->token)->getJson('/api/v1/pos/sync-pull')
            ->assertStatus(200);

        // A non-sensitive write (offline sync batch) is allowed.
        $this->withToken($key->token)->postJson('/api/v1/pos/sync-batch', [])
            ->assertStatus(200);
    }

    public function test_a_real_tenant_is_unaffected_even_with_demo_mode_on(): void
    {
        config(['app.demo_mode' => true]);
        [, , $key] = $this->tenant(demo: false);

        // A real tenant hitting a settings route is never given the demo 403 —
        // it flows through to the endpoint's own validation (422) instead.
        $res = $this->withToken($key->token)
            ->postJson('/api/tenant/settings/branding', []);

        $this->assertNotSame(403, $res->status());
        $this->assertNotSame(PreventDemoModifications::MESSAGE, $res->json('message'));
    }

    public function test_sdui_settings_schema_is_locked_down_for_a_demo_tenant(): void
    {
        config(['app.demo_mode' => true]);
        [, , $key] = $this->tenant(demo: true);

        $schema = $this->withToken($key->token)
            ->getJson('/api/tenant/views/settings-profile')
            ->assertOk()
            ->json('schema');

        $body = json_encode($schema, JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('Running in Demo Mode', $body);
        $this->assertStringContainsString('File uploads are disabled in Demo Mode', $body);

        $disabled = 0;
        $walk = function ($n) use (&$walk, &$disabled) {
            if (! is_array($n)) {
                return;
            }
            if (($n['type'] ?? null) === 'button_primary' && ($n['disabled'] ?? false) === true) {
                $disabled++;
            }
            foreach ($n as $c) {
                $walk($c);
            }
        };
        $walk($schema);
        $this->assertGreaterThan(0, $disabled, 'demo tenant still has an enabled Save button');
    }

    public function test_sdui_settings_schema_is_untouched_for_a_real_tenant(): void
    {
        config(['app.demo_mode' => true]);
        [, , $key] = $this->tenant(demo: false);

        $body = json_encode(
            $this->withToken($key->token)
                ->getJson('/api/tenant/views/settings-profile')
                ->assertOk()->json('schema'),
            JSON_UNESCAPED_SLASHES
        );
        $this->assertStringNotContainsString('Running in Demo Mode', $body);
    }

    public function test_auth_config_advertises_the_demo_accounts(): void
    {
        config(['app.demo_mode' => true]);

        $this->getJson('/api/v1/pos/auth/auth-config')
            ->assertOk()
            ->assertJsonPath('demo_mode', true)
            ->assertJsonPath('demo_accounts.0.store_type', 'RETAIL')
            ->assertJsonPath('demo_accounts.0.email', 'retail@demo.com')
            ->assertJsonPath('demo_accounts.0.password', DemoAccountsSeeder::PASSWORD)
            ->assertJsonCount(5, 'demo_accounts');
    }

    public function test_auth_config_hides_demo_accounts_when_off(): void
    {
        config(['app.demo_mode' => false]);

        $this->getJson('/api/v1/pos/auth/auth-config')
            ->assertOk()
            ->assertJsonPath('demo_mode', false)
            ->assertJsonPath('demo_accounts', []);
    }

    public function test_web_demo_login_signs_the_visitor_into_the_matching_tenant(): void
    {
        config(['app.demo_mode' => true]);
        $this->seed(DemoAccountsSeeder::class);

        $user = User::withoutGlobalScopes()->where('email', 'pharmacy@demo.com')->firstOrFail();

        $this->get('/demo-login/pharmacy')
            ->assertRedirect('/tenant');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_web_demo_login_404s_when_demo_mode_is_off(): void
    {
        config(['app.demo_mode' => true]);
        $this->seed(DemoAccountsSeeder::class);

        // Controller hard-guards on demo_mode regardless of route registration.
        config(['app.demo_mode' => false]);
        $this->get('/demo-login/retail')->assertNotFound();

        // And an unknown store type is a 404 even with demo mode on.
        config(['app.demo_mode' => true]);
        $this->get('/demo-login/spaceship')->assertNotFound();
    }
}
