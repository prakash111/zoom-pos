<?php

namespace Tests\Feature;

use App\Livewire\Tenant\Products\Index as ProductsIndex;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TenantIsolationInvestigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_a_cannot_see_tenant_bs_products_via_web_login(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a']);
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b']);

        $userA = User::create([
            'company_id' => $companyA->id, 'name' => 'Owner A', 'login' => 'ownerA',
            'email' => 'ownerA@a.test', 'password' => Hash::make('secret123'), 'role' => 'administrator', 'status' => 'approved',
        ]);
        $userB = User::create([
            'company_id' => $companyB->id, 'name' => 'Owner B', 'login' => 'ownerB',
            'email' => 'ownerB@b.test', 'password' => Hash::make('secret123'), 'role' => 'administrator', 'status' => 'approved',
        ]);

        Product::create(['company_id' => $companyA->id, 'name' => 'A-Only Product', 'sale_price' => 10, 'current_stock' => 1, 'active' => true]);
        Product::create(['company_id' => $companyB->id, 'name' => 'B-Only Product', 'sale_price' => 20, 'current_stock' => 1, 'active' => true]);

        $this->actingAs($userA, 'web');
        app()->instance('tenant.company_id', $userA->company_id);

        $names = Product::query()->pluck('name')->all();

        $this->assertSame(['A-Only Product'], $names, 'Tenant A must only see its own products.');
    }

    public function test_products_livewire_page_scopes_by_logged_in_tenant(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a']);
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b']);

        $userA = User::create([
            'company_id' => $companyA->id, 'name' => 'Owner A', 'login' => 'ownerA',
            'email' => 'ownerA@a.test', 'password' => Hash::make('secret123'), 'role' => 'administrator', 'status' => 'approved',
        ]);

        Product::create(['company_id' => $companyA->id, 'name' => 'A-Only Product', 'sale_price' => 10, 'current_stock' => 1, 'active' => true]);
        Product::create(['company_id' => $companyB->id, 'name' => 'B-Only Product', 'sale_price' => 20, 'current_stock' => 1, 'active' => true]);

        $this->actingAs($userA, 'web');
        app()->instance('tenant.company_id', $userA->company_id);

        Livewire::test(ProductsIndex::class)
            ->assertSee('A-Only Product')
            ->assertDontSee('B-Only Product');
    }

    /**
     * Full HTTP round trip through the real middleware stack (ResolveTenantContext
     * included) — no manual app()->instance() shortcut. This is the closest
     * simulation of "log in as tenant A in a browser, then load the products page."
     */
    public function test_full_http_login_then_products_request_is_tenant_scoped(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a']);
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b']);

        $userA = User::create([
            'company_id' => $companyA->id, 'name' => 'Owner A', 'login' => 'ownerA',
            'email' => 'ownerA@a.test', 'password' => Hash::make('secret123'), 'role' => 'administrator', 'status' => 'approved',
        ]);

        Product::create(['company_id' => $companyA->id, 'name' => 'A-Only Product', 'sale_price' => 10, 'current_stock' => 1, 'active' => true]);
        Product::create(['company_id' => $companyB->id, 'name' => 'B-Only Product', 'sale_price' => 20, 'current_stock' => 1, 'active' => true]);

        Livewire::test(\App\Livewire\Auth\TenantLogin::class)
            ->set('identifier', 'ownerA@a.test')
            ->set('password', 'secret123')
            ->call('login')
            ->assertRedirect('/tenant');

        $response = $this->get('/tenant/products');

        $response->assertOk();
        $response->assertSee('A-Only Product');
        $response->assertDontSee('B-Only Product');
    }

    /**
     * Regression test for the exact scenario in the desktop QA script's
     * "Switching users" step. ResolveTenantContext (global 'web' middleware)
     * used to only bind tenant.company_id into the container if nothing was
     * bound yet. That's invisible on classic PHP-FPM (a fresh container per
     * request), but NativePHP's desktop app keeps ONE PHP process alive for
     * the whole app session — and the same is true of any Octane/Swoole/
     * RoadRunner web deployment reusing worker processes across different
     * users' requests. Under the old code, once one tenant's company_id was
     * bound, a second, already-authenticated session for a *different*
     * tenant served by that same process would never get re-bound — every
     * request kept silently resolving to the first tenant's data.
     *
     * Deliberately uses actingAs() rather than the TenantLogin component:
     * TenantLogin::login() happens to also explicitly rebind tenant.company_id
     * itself as an application-level side effect, which would mask this
     * specific middleware bug. This test targets the far more common case of
     * two already-authenticated sessions (no login action involved) being
     * served back-to-back by the same long-lived process — a plain page
     * load is all it takes to trigger the leak, or its fix.
     */
    public function test_switching_tenants_on_the_same_process_does_not_leak_the_previous_tenants_data(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a']);
        $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b']);

        $userA = User::create([
            'company_id' => $companyA->id, 'name' => 'Owner A', 'login' => 'ownerA',
            'email' => 'ownerA@a.test', 'password' => Hash::make('secret123'), 'role' => 'administrator', 'status' => 'approved',
        ]);
        $userB = User::create([
            'company_id' => $companyB->id, 'name' => 'Owner B', 'login' => 'ownerB',
            'email' => 'ownerB@b.test', 'password' => Hash::make('secret123'), 'role' => 'administrator', 'status' => 'approved',
        ]);

        Product::create(['company_id' => $companyA->id, 'name' => 'A-Only Product', 'sale_price' => 10, 'current_stock' => 1, 'active' => true]);
        Product::create(['company_id' => $companyB->id, 'name' => 'B-Only Product', 'sale_price' => 20, 'current_stock' => 1, 'active' => true]);

        $this->actingAs($userA, 'web');
        $this->get('/tenant/products')
            ->assertOk()
            ->assertSee('A-Only Product')
            ->assertDontSee('B-Only Product');

        // A different, already-authenticated tenant's session now lands on
        // this same process — no login action, no manual cache-clearing.
        $this->actingAs($userB, 'web');
        $this->get('/tenant/products')
            ->assertOk()
            ->assertSee('B-Only Product')
            ->assertDontSee('A-Only Product');
    }
}
