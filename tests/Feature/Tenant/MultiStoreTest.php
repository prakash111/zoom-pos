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
            'plan_name' => 'multi', 'expires_at' => now()->addMonth(),
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
            ->assertOk()->json('current_store_id');
        $this->assertSame(10.0, (float) $this->getJson('/api/v1/pos/inventory', $headers)
            ->assertOk()->json('products.0.current_stock'));

        $secondaryId = $this->postJson('/api/v1/tenant/stores', [
            'name' => 'North Branch', 'code' => 'north', 'address' => 'North Street',
        ], $headers)->assertCreated()->json('store.id');
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
        $this->postJson('/api/v1/tenant/stores/switch', ['store_id' => $primaryId], $headers)->assertOk();
        $this->getJson('/api/v1/pos/sales/'.$primarySaleId, $headers)->assertOk();

        $this->postJson('/api/v1/tenant/stores', [
            'name' => 'Third Branch', 'code' => 'third',
        ], $headers)->assertUnprocessable();

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
        $this->assertEquals($primary->id, $staff->fresh()->current_store_id);
    }
}
