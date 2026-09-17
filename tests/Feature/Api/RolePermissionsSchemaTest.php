<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use App\Services\Sdui\SchemaResponse;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionsSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'enterprise', 'display_name' => 'Enterprise Plan', 'price' => 99.0,
            'currency' => 'USD', 'billing_cycle' => 'monthly', 'duration_days' => 30,
            'features' => ['pos' => true], 'limits' => ['users' => 100], 'active' => true,
        ]);

        // Tenant with restaurant, pharmacy, salon licensed (matching the video context)
        $this->company = Company::create([
            'name' => 'Multi-Vertical Hub', 'trade_name' => 'MultiHub', 'slug' => 'multihub',
            'email' => 'admin@multihub.test', 'country' => 'US', 'currency' => 'USD',
            'currency_symbol' => '$', 'plan_name' => 'enterprise', 'expires_at' => now()->addDays(30),
            'pos_mode' => 'restaurant',
            'licensed_modules' => ['restaurant', 'pharmacy', 'salon'],
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@multihub.test',
            'password' => Hash::make('secret123'),
            'role' => 'administrator',
        ]);
    }

    private function token(): string
    {
        return $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@multihub.test',
            'password' => 'secret123',
        ])->json('token');
    }

    public function test_sdui_roles_view_dynamically_renders_tenant_enabled_vertical_permission_groups(): void
    {
        $schema = SchemaResponse::rolesView($this->company);

        $this->assertSame([], app(SchemaValidator::class)->validate($schema));
        $this->assertSame('Manage Roles', $schema['title']);

        // Collect all accordion group titles in the schema
        $accordionTitles = [];
        $collectAccordions = function (array $nodes) use (&$collectAccordions, &$accordionTitles) {
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }
                if (($node['type'] ?? '') === 'accordion_group') {
                    $accordionTitles[] = $node['title'] ?? '';
                }
                if (! empty($node['components']) && is_array($node['components'])) {
                    $collectAccordions($node['components']);
                }
                if (! empty($node['children']) && is_array($node['children'])) {
                    $collectAccordions($node['children']);
                }
            }
        };

        $collectAccordions($schema['components'] ?? []);

        // Assert Restaurant, Pharmacy, Salon permission groups are present
        $this->assertContains('Restaurant POS Terminal', $accordionTitles);
        $this->assertContains('Pharmacy POS & Checkout', $accordionTitles);
        $this->assertContains('Salon POS & Checkout', $accordionTitles);

        // Core modules are always present
        $this->assertContains('Sales & Receipts', $accordionTitles);
        $this->assertContains('Finance & Expenses', $accordionTitles);
        $this->assertContains('Store Settings & SMTP', $accordionTitles);
        $this->assertContains('Users & Permissions', $accordionTitles);

        // Unlicensed vertical modules (repair, leads) MUST NOT be present
        $this->assertNotContains('Repair & Service Workbench', $accordionTitles);
        $this->assertNotContains('Lead Management System', $accordionTitles);
    }

    public function test_roles_schema_endpoint_returns_filtered_permission_groups(): void
    {
        $token = $this->token();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tenant/roles/schema');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tenant_id', (string) $this->company->id);

        $modules = collect($response->json('permission_groups'))->pluck('slug')->all();

        $this->assertContains('restaurant', $modules);
        $this->assertContains('pharmacy', $modules);
        $this->assertContains('salon', $modules);
        $this->assertContains('sales', $modules);
        $this->assertContains('settings', $modules);

        $this->assertNotContains('repair', $modules);
        $this->assertNotContains('leads', $modules);
    }

    public function test_tenant_roles_index_returns_filtered_modules_and_permission_groups(): void
    {
        $token = $this->token();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tenant/roles');

        $response->assertOk()->assertJsonPath('success', true);

        $modules = collect($response->json('modules'))->pluck('slug')->all();
        $this->assertContains('restaurant', $modules);
        $this->assertContains('pharmacy', $modules);
        $this->assertContains('salon', $modules);
        $this->assertNotContains('repair', $modules);
        $this->assertNotContains('leads', $modules);
    }

    public function test_create_custom_role_with_restaurant_pharmacy_salon_permissions(): void
    {
        $token = $this->token();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tenant/roles', [
                'name' => 'Floor & Dispensary Lead',
                'description' => 'Oversees restaurant floor and pharmacy counter',
                'perm__restaurant__manage_kot' => true,
                'perm__restaurant__manage_tables' => true,
                'perm__pharmacy__manage_batches' => true,
                'perm__salon__checkout' => true,
            ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $perms = $response->json('role.permissions');
        $this->assertSame(['manage_kot', 'manage_tables'], collect($perms['restaurant'])->sort()->values()->all());
        $this->assertSame(['manage_batches'], $perms['pharmacy']);
        $this->assertSame(['checkout'], $perms['salon']);

        // Create user with this role and verify PermissionChecker enforcement
        $staff = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'floor_dispensary_lead',
        ]);

        $checker = app(PermissionChecker::class);
        PermissionChecker::flushRoleCache();

        $this->assertTrue($checker->allows($staff, 'restaurant', 'manage_kot'));
        $this->assertTrue($checker->allows($staff, 'pharmacy', 'manage_batches'));
        $this->assertTrue($checker->allows($staff, 'salon', 'checkout'));
        $this->assertFalse($checker->allows($staff, 'restaurant', 'delete'));
        $this->assertFalse($checker->allows($staff, 'repair', 'diagnose'));
    }

    public function test_superadmin_update_modules_endpoint_synchronizes_tenant_settings_and_invalidates_cache(): void
    {
        $token = $this->token();

        // 1. Initial check: restaurant is present
        $initial = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/tenant/roles/schema');
        $this->assertTrue(collect($initial->json('permission_groups'))->pluck('slug')->contains('restaurant'));

        // 2. SuperAdmin updates modules: remove restaurant, add repairs
        $updateResp = $this->postJson("/api/superadmin/tenants/{$this->company->id}/modules", [
            'modules' => ['pharmacy', 'salon', 'repair_technician'],
        ]);

        $updateResp->assertOk()->assertJsonPath('success', true);

        // Verify company model was updated
        $this->company->refresh();
        $this->assertContains('repair_technician', $this->company->licensed_modules);

        // Verify tenant_settings table was synchronized
        $settingModules = TenantSetting::get($this->company->id, 'enabled_modules');
        $this->assertContains('repair_technician', $settingModules);

        // 3. Subsequent check: restaurant is GONE, repair is PRESENT
        $after = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/tenant/roles/schema');
        $afterSlugs = collect($after->json('permission_groups'))->pluck('slug')->all();

        $this->assertNotContains('restaurant', $afterSlugs);
        $this->assertContains('repair', $afterSlugs);
        $this->assertContains('pharmacy', $afterSlugs);
        $this->assertContains('salon', $afterSlugs);

        // 4. SDUI roles view reflects the update immediately
        $sdui = SchemaResponse::rolesView($this->company);
        $accordionTitles = [];
        $collectAccordions = function (array $nodes) use (&$collectAccordions, &$accordionTitles) {
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }
                if (($node['type'] ?? '') === 'accordion_group') {
                    $accordionTitles[] = $node['title'] ?? '';
                }
                if (! empty($node['components']) && is_array($node['components'])) {
                    $collectAccordions($node['components']);
                }
                if (! empty($node['children']) && is_array($node['children'])) {
                    $collectAccordions($node['children']);
                }
            }
        };
        $collectAccordions($sdui['components'] ?? []);

        $this->assertNotContains('Restaurant POS Terminal', $accordionTitles);
        $this->assertContains('Repair & Service Workbench', $accordionTitles);
        $this->assertContains('Pharmacy POS & Checkout', $accordionTitles);
        $this->assertContains('Salon POS & Checkout', $accordionTitles);
    }
}
