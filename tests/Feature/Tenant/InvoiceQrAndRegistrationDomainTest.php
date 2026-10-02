<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Auth\TenantRegister;
use App\Models\Company;
use App\Models\DynamicSetting;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceQrAndRegistrationDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://saas.zoomnearby.com']);
        config(['app.domain' => 'saas.zoomnearby.com']);
        file_put_contents(storage_path('installed'), '{}');

        $this->company = Company::withoutGlobalScopes()->create([
            'name' => 'Metro Supermarket',
            'slug' => 'metro-market',
            'subdomain' => 'metro-market',
            'phone' => '+15550199',
            'email' => 'metro@zoomnearby.com',
            'city' => 'New York',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $this->user = User::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Store Admin',
            'email' => 'admin@metro.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }

    public function test_invoice_public_route_returns_200_and_does_not_404(): void
    {
        $sale = Sale::withoutGlobalScope('company')->create([
            'company_id' => $this->company->id,
            'sale_number' => 'POS-99887766',
            'customer_name' => 'Jane Doe',
            'subtotal' => 120.00,
            'tax_amount' => 12.00,
            'total' => 132.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'operation_type' => 'sale',
            'status' => 'completed',
        ]);

        // Access by sale_number
        $response = $this->get('/i/' . $sale->sale_number);
        $response->assertStatus(200);
        $response->assertSee('POS-99887766');

        // Access by id
        $responseId = $this->get('/i/' . $sale->id);
        $responseId->assertStatus(200);
        $responseId->assertSee('POS-99887766');
    }

    public function test_quotation_public_route_returns_200(): void
    {
        $quote = Sale::withoutGlobalScope('company')->create([
            'company_id' => $this->company->id,
            'sale_number' => 'QUO-44332211',
            'customer_name' => 'Acme Corp',
            'subtotal' => 500.00,
            'tax_amount' => 50.00,
            'total' => 550.00,
            'payment_status' => 'unpaid',
            'operation_type' => 'quotation',
            'status' => 'draft',
        ]);

        $response = $this->get('/q/' . $quote->sale_number);
        $response->assertStatus(200);
        $response->assertSee('QUO-44332211');
    }

    public function test_invoice_html_template_renders_clickable_qr_code_link(): void
    {
        $sale = Sale::withoutGlobalScope('company')->create([
            'company_id' => $this->company->id,
            'sale_number' => 'POS-11223344',
            'customer_name' => 'Alice Walker',
            'subtotal' => 80.00,
            'tax_amount' => 8.00,
            'total' => 88.00,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'operation_type' => 'sale',
            'status' => 'completed',
        ]);

        $response = $this->get('/i/' . $sale->sale_number);
        $response->assertStatus(200);

        // QR code should be wrapped with clickable verification link
        $expectedUrl = route('sales.public', $sale->sale_number);
        $response->assertSee($expectedUrl, false);
    }

    public function test_when_domain_setup_disabled_tenant_registration_displays_store_directory_link(): void
    {
        DynamicSetting::put('enable_registration_domain_setup', false);

        Livewire::test(TenantRegister::class)
            ->assertSee('Store Directory Link')
            ->assertSee('Example:')
            ->assertSee('/store-name')
            ->assertDontSee('Subdomain & Custom Domain Setup')
            ->set('storeName', 'Super Fresh Market')
            ->assertSet('slug', 'super-fresh-market')
            ->assertSee('super-fresh-market');
    }

    public function test_when_domain_setup_enabled_tenant_registration_displays_subdomain_setup(): void
    {
        DynamicSetting::put('enable_registration_domain_setup', true);

        Livewire::test(TenantRegister::class)
            ->assertSee('Subdomain & Custom Domain Setup')
            ->assertDontSee('Store Directory Link');
    }
}
