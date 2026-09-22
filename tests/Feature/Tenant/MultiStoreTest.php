<?php

namespace Tests\Feature\Tenant;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MultiStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creation_switching_stock_and_sales_stay_in_branch(): void
    {
        Plan::create(['name' => 'multi', 'display_name' => 'Multi Store', 'store_limit' => 2]);
        $company = Company::create([
            'name' => 'Branch Test', 'slug' => 'branch-test', 'email' => 'branch@example.com',
            'country' => 'IN', 'plan_name' => 'multi', 'expires_at' => now()->addMonth(),
        ]);
        $admin = User::factory()->create([
            'company_id' => $company->id, 'role' => 'admin',
            'email' => 'branch-admin@example.com', 'password' => 'password',
        ]);
        $product = Product::create([
            'company_id' => $company->id, 'name' => 'Shared Product',
            'current_stock' => 10, 'sale_price' => 20,
        ]);
        $token = $this->postJson('/api/v1/pos/auth/login', [
            'email' => $admin->email, 'password' => 'password',
        ])->assertOk()->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $primaryId = $this->getJson('/api/v1/tenant/stores', $headers)
            ->assertOk()->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('meta.max_allowed_stores', 2)
            ->assertJsonPath('meta.can_create_more', true)->json('current_store_id');
        $this->assertSame(10.0, (float) $this->getJson('/api/v1/pos/inventory', $headers)
            ->assertOk()->json('products.0.current_stock'));

        $secondaryId = $this->postJson('/api/v1/tenant/stores', [
            'name' => 'North Branch', 'phone' => '9876543210', 'address' => 'North Street',
        ], $headers)->assertCreated()->assertJsonPath('data.code', 'NORTH-BRANCH')
            ->assertJsonPath('data.phone', '+919876543210')
            ->assertJsonPath('data.receipt_prefix', 'NORTH-BRANCH-INV-')->json('store.id');
        $this->assertDatabaseHas('product_store_stock', ['store_id' => $secondaryId, 'product_id' => $product->id, 'quantity' => 0]);
        $this->assertNotNull(DB::table('store_user')->where('store_id', $secondaryId)->where('user_id', $admin->id)->value('role_id'));
        $this->assertSame('NORTH-BRANCH-POS-1', Store::findOrFail($secondaryId)->settings['cash_register']['terminal_id']);
        $this->assertSame(0.0, (float) $this->getJson('/api/v1/pos/inventory', $headers)
            ->assertOk()->json('products.0.current_stock'));

        $this->postJson('/api/v1/pos/inventory/adjust', [
            'product_id' => $product->id, 'type' => 'set', 'quantity' => 5,
        ], $headers)->assertOk();
        $this->assertSame(5.0, (float) $this->getJson('/api/v1/pos/inventory', $headers)
            ->assertOk()->json('products.0.current_stock'));
        $this->assertSame(10.0, (float) $this->getJson('/api/v1/pos/inventory',
            $headers + ['X-Store-Id' => $primaryId])->assertOk()->json('products.0.current_stock'));

        $primarySaleId = DB::table('sales')->insertGetId([
            'company_id' => $company->id, 'store_id' => $primaryId,
            'sale_number' => 'PRIMARY-1', 'total' => 30,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->getJson('/api/v1/pos/sales/'.$primarySaleId, $headers)->assertNotFound();
        $this->postJson('/api/v1/tenant/stores/'.$primaryId.'/switch', [], $headers)
            ->assertOk()->assertJsonPath('current_store.id', $primaryId)->assertJsonPath('current_store.is_current', true);
        $this->getJson('/api/v1/pos/sales/'.$primarySaleId, $headers)->assertOk();

        $this->postJson('/api/v1/tenant/stores', [
            'name' => 'Third Branch', 'code' => 'third',
        ], $headers)->assertForbidden()->assertJsonPath('error', 'quota_exceeded')->assertJsonPath('upgrade_required', true);

        $other = Company::create(['name' => 'Other Tenant', 'slug' => 'other-branch', 'email' => 'other-branch@example.com']);
        $foreignStore = Store::withoutGlobalScopes()->create([
            'company_id' => $other->id, 'name' => 'Other', 'code' => 'main',
            'is_primary' => true,
        ]);
        $this->getJson('/api/v1/tenant/stores', $headers + ['X-Store-Id' => $foreignStore->id])
            ->assertForbidden();
        $this->assertNotEquals($primaryId, $secondaryId);
    }

    public function test_staff_cannot_switch_to_unassigned_store(): void
    {
        $company = Company::create(['name' => 'Staff Branches', 'slug' => 'staff-branches', 'email' => 'staff-branches@example.com']);
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin', 'email' => 'staff-admin@example.com']);
        $staff = User::factory()->create(['company_id' => $company->id, 'role' => 'cashier', 'email' => 'staff-cashier@example.com', 'password' => 'password']);
        $primary = app(\App\Services\Stores\StoreContext::class)->ensurePrimary($company);
        $secondary = Store::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Restricted', 'code' => 'restricted']);
        $token = $this->postJson('/api/v1/pos/auth/login', [
            'email' => $staff->email, 'password' => 'password',
        ])->assertOk()->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/tenant/stores', $headers)->assertOk()->assertJsonCount(1, 'stores');
        $this->postJson('/api/v1/tenant/stores/switch', ['store_id' => $secondary->id], $headers)->assertForbidden();
        $this->getJson('/api/v1/tenant/stores', $headers + ['X-Store-Id' => $secondary->id])->assertForbidden();
        $this->postJson('/api/v1/tenant/stores', ['name' => 'Forbidden'], $headers)->assertForbidden();
        $this->putJson('/api/v1/tenant/stores/'.$primary->id, ['name' => 'Forbidden'], $headers)->assertForbidden();
        $this->getJson('/api/v1/tenant/stores', $headers)->assertJsonPath('meta.can_create_more', false);
        $this->assertEquals($primary->id, $staff->fresh()->current_store_id);
    }

    private function adminAccount(): array
    {
        Plan::create(['name' => 'branches', 'display_name' => 'Branches', 'store_limit' => -1]);
        $company = Company::create(['name' => 'Managed branches', 'slug' => 'managed-branches',
            'email' => 'branches@example.com', 'plan_name' => 'branches', 'expires_at' => now()->addMonth()]);
        $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin', 'password' => 'password']);
        $token = $this->postJson('/api/v1/pos/auth/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertOk()->json('token');
        return [$company, $admin, ['Authorization' => 'Bearer '.$token]];
    }

    public function test_branch_management_deactivation_and_stale_header_recovery(): void
    {
        [$company, $admin, $headers] = $this->adminAccount();
        $primary = $this->getJson('/api/v1/tenant/stores', $headers)->assertOk()->json('current_store_id');
        $branch = $this->postJson('/api/v1/tenant/stores', ['name' => 'Second'], $headers)->assertCreated()->json('data.id');
        $this->putJson('/api/v1/tenant/stores/'.$primary, ['is_active' => false], $headers)->assertUnprocessable();
        $this->putJson('/api/v1/tenant/stores/'.$branch, ['name' => 'Updated', 'address' => 'New road'], $headers)
            ->assertOk()->assertJsonPath('data.address', 'New road');
        $this->putJson('/api/v1/tenant/stores/'.$branch, ['is_active' => false], $headers)->assertOk();
        $stale = $headers + ['X-Store-Id' => $branch];
        $this->getJson('/api/v1/tenant/stores', $stale)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $primary)->assertJsonPath('data.0.is_current', true);
        $this->getJson('/api/v1/pos/inventory', $stale)->assertForbidden();
        $this->postJson('/api/v1/tenant/stores/'.$primary.'/switch', [], $stale)->assertOk();
        $this->getJson('/api/v1/tenant/stores?include_inactive=1', $headers)->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/v1/tenant/stores/'.$branch.'/switch', [], $headers)->assertUnprocessable();
        $this->putJson('/api/v1/tenant/stores/'.$branch, ['is_active' => true], $headers)->assertOk();
        $this->postJson('/api/v1/tenant/stores/'.$branch.'/switch', [], $headers)->assertOk();
        $this->assertEquals($branch, $admin->fresh()->current_store_id);
    }

    public function test_invalid_phone_and_duplicate_codes_are_validation_errors(): void
    {
        [, , $headers] = $this->adminAccount();
        $this->postJson('/api/v1/tenant/stores', ['name' => 'Branch', 'phone' => ['invalid']], $headers)->assertUnprocessable();
        $this->postJson('/api/v1/tenant/stores', ['name' => 'Branch'], $headers)->assertCreated()->assertJsonPath('data.code', 'BRANCH');
        $this->postJson('/api/v1/tenant/stores', ['name' => 'Branch'], $headers)->assertCreated()->assertJsonPath('data.code', 'BRANCH-2');
        $this->postJson('/api/v1/tenant/stores', ['name' => 'Other', 'code' => 'BRANCH'], $headers)->assertUnprocessable();
    }

    public function test_web_management_uses_the_same_store_permissions(): void
    {
        [$company, $admin] = $this->adminAccount();
        $this->actingAs($admin, 'web')->get(route('tenant.settings.stores'))->assertOk()->assertSee('Add New Store / Branch');
        $this->actingAs($admin, 'web')->post(route('tenant.stores.create'), ['name' => 'Web Branch'])
            ->assertRedirect(route('tenant.settings.stores'));
        $this->assertDatabaseHas('stores', ['company_id' => $company->id, 'code' => 'WEB-BRANCH']);
        $staff = User::factory()->create(['company_id' => $company->id, 'role' => 'cashier']);
        $primary = Store::withoutGlobalScopes()->where('company_id', $company->id)->where('is_primary', true)->firstOrFail();
        $primary->users()->syncWithoutDetaching([$staff->id]);
        $this->actingAs($staff, 'web')->get(route('tenant.settings.stores'))->assertOk()->assertDontSee('Add New Store / Branch');
        $this->actingAs($staff, 'web')->post(route('tenant.stores.create'), ['name' => 'Forbidden'])->assertForbidden();
    }
    public function test_store_managers_cannot_create_without_a_separate_grant(): void
    {
        [$company, , $headers] = $this->adminAccount();
        $primary = $this->getJson('/api/v1/tenant/stores', $headers)->assertOk()->json('current_store_id');
        $staff = User::factory()->create(['company_id' => $company->id, 'role' => 'cashier', 'password' => 'password']);
        \App\Models\Permission::create(['company_id' => $company->id, 'user_id' => $staff->id,
            'module' => 'stores', 'action' => 'manage', 'allowed' => true]);
        $token = $this->postJson('/api/v1/pos/auth/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertOk()->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->getJson('/api/v1/tenant/stores', $headers)->assertOk()
            ->assertJsonPath('meta.can_manage', true)->assertJsonPath('meta.can_create_more', false);
        $this->putJson('/api/v1/tenant/stores/'.$primary, ['address' => 'Updated by manager'], $headers)->assertOk();
        $this->postJson('/api/v1/tenant/stores', ['name' => 'Forbidden'], $headers)->assertForbidden();
        \App\Models\Permission::create(['company_id' => $company->id, 'user_id' => $staff->id,
            'module' => 'stores', 'action' => 'view', 'allowed' => false]);
        $this->getJson('/api/v1/tenant/stores', $headers)->assertForbidden();
    }

    public function test_branch_default_register_is_used_and_prevents_deactivation_while_open(): void
    {
        [, , $headers] = $this->adminAccount();
        $id = $this->postJson('/api/v1/tenant/stores', ['name' => 'North'], $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/pos/cash-register/open', ['opening_balance' => 0], $headers)->assertCreated();
        $this->assertDatabaseHas('cash_registers', ['store_id' => $id, 'terminal_id' => 'NORTH-POS-1', 'status' => 'open']);
        $this->putJson('/api/v1/tenant/stores/'.$id, ['is_active' => false], $headers)->assertUnprocessable();
    }

    public function test_laravel_header_exposes_branch_actions_and_switches_back_to_dashboard(): void
    {
        [$company, $admin, $headers] = $this->adminAccount();
        $mainId = $this->getJson('/api/v1/tenant/stores', $headers)->assertOk()->json('current_store_id');
        $branchId = $this->postJson('/api/v1/tenant/stores', ['name' => 'Visible North Branch', 'address' => 'North Street'], $headers)
            ->assertCreated()->json('data.id');
        $this->actingAs($admin->fresh(), 'web')->get(route('tenant.dashboard'))->assertOk()
            ->assertSee('data-testid="tenant-store-switcher"', false)
            ->assertSee('Visible North Branch')->assertSee('North Street')
            ->assertSee('Manage Stores & Branches')->assertSee('Add New Store / Branch');
        $this->post(route('tenant.stores.switch', $mainId), ['redirect_to' => 'dashboard'])
            ->assertRedirect(route('tenant.dashboard'));
        $this->assertEquals($mainId, $admin->fresh()->current_store_id);
        $this->put(route('tenant.stores.update', $branchId), ['name' => 'Edited North Branch', 'phone' => '+919876543210'])
            ->assertRedirect(route('tenant.settings.stores'));
        $this->assertDatabaseHas('stores', ['id' => $branchId, 'name' => 'Edited North Branch']);
        $this->get(route('tenant.settings.index'))->assertOk()->assertSee('Stores & Branches');
        $company->plan->update(['store_limit' => 2]);
        $this->actingAs($admin->fresh(), 'web')->get(route('tenant.dashboard'))->assertOk()
            ->assertDontSee('Add New Store / Branch')->assertSee('Store limit reached. Upgrade to add a branch.');
    }

    public function test_laravel_switcher_only_shows_assigned_stores_and_honors_denied_view(): void
    {
        [$company, , $headers] = $this->adminAccount();
        $this->getJson('/api/v1/tenant/stores', $headers)->assertOk();
        $staff = User::factory()->create(['company_id' => $company->id, 'role' => 'cashier']);
        Store::withoutGlobalScopes()->create(['company_id' => $company->id, 'name' => 'Unassigned Secret Branch', 'code' => 'SECRET']);
        $this->actingAs($staff, 'web')->get(route('tenant.dashboard'))->assertOk()
            ->assertSee('data-testid="tenant-store-switcher"', false)
            ->assertDontSee('Unassigned Secret Branch')->assertDontSee('Add New Store / Branch');
        \App\Models\Permission::create(['company_id' => $company->id, 'user_id' => $staff->id,
            'module' => 'stores', 'action' => 'view', 'allowed' => false]);
        $this->get(route('tenant.dashboard'))->assertOk()->assertDontSee('data-testid="tenant-store-switcher"', false);
        $this->get(route('tenant.settings.stores'))->assertForbidden();
    }

    public function test_laravel_dashboard_caches_and_low_stock_counts_are_separate_per_branch(): void
    {
        [$company, $admin, $headers] = $this->adminAccount();
        $mainId = $this->getJson('/api/v1/tenant/stores', $headers)->assertOk()->json('current_store_id');
        $product = Product::create(['company_id' => $company->id, 'name' => 'Branch Stock',
            'current_stock' => 10, 'minimum_stock' => 2, 'active' => true, 'sale_price' => 20]);
        \App\Models\Sale::create(['company_id' => $company->id, 'store_id' => $mainId,
            'sale_number' => 'MAIN-CACHED', 'status' => 'completed', 'total' => 125, 'items' => []]);
        $service = app(\App\Services\FinancialAnalyticsService::class);
        $this->assertSame(125.0, $service->getExecutiveDashboardKpis($company)['dailyRevenue']);
        $branchId = $this->postJson('/api/v1/tenant/stores', ['name' => 'Empty Branch'], $headers)->assertCreated()->json('data.id');
        $this->actingAs($admin->fresh(), 'web')->get(route('tenant.dashboard'))->assertOk();
        $this->assertSame(0.0, $service->getExecutiveDashboardKpis($company)['dailyRevenue']);
        \Livewire\Livewire::test(\App\Livewire\Tenant\Dashboard::class)->assertViewHas('lowStockCount', 1);
        $this->post(route('tenant.stores.switch', $mainId), ['redirect_to' => 'dashboard'])->assertRedirect(route('tenant.dashboard'));
        $this->actingAs($admin->fresh(), 'web')->get(route('tenant.dashboard'))->assertOk();
        $this->assertSame(125.0, $service->getExecutiveDashboardKpis($company)['dailyRevenue']);
        \Livewire\Livewire::test(\App\Livewire\Tenant\Dashboard::class)->assertViewHas('lowStockCount', 0);
        $this->assertNotEquals($mainId, $branchId);
    }

}
