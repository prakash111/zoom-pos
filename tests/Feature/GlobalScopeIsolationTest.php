<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalScopeIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_company_scope_isolates_tenant_data(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        Product::create(['company_id' => $companyA->id, 'name' => 'A Product', 'sale_price' => 10]);
        Product::create(['company_id' => $companyB->id, 'name' => 'B Product', 'sale_price' => 20]);

        app()->instance('tenant.company_id', $companyA->id);

        $visible = Product::all();

        $this->assertCount(1, $visible);
        $this->assertSame('A Product', $visible->first()->name);

        // Super Admin code path explicitly opts out of the scope.
        $this->assertCount(2, Product::withoutGlobalScope('company')->get());
    }

    public function test_creating_without_explicit_company_id_uses_the_bound_tenant_context(): void
    {
        $company = Company::create(['name' => 'Company A']);
        app()->instance('tenant.company_id', $company->id);

        $product = Product::create(['name' => 'Auto-scoped Product', 'sale_price' => 5]);

        $this->assertSame($company->id, $product->company_id);
    }
}
