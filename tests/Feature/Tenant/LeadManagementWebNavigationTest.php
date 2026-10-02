<?php

namespace Tests\Feature\Tenant;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\leadmanagement\Models\LeadSource;
use Tests\TestCase;

class LeadManagementWebNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--path' => 'module-packages/leadmanagement/Database/Migrations',
            '--realpath' => false,
        ]);
    }

    private function leadTenant(): array
    {
        \App\Models\SduiModule::firstOrCreate(['slug' => 'leadmanagement'], [
            'name' => 'Lead Management System',
            'slug' => 'leadmanagement',
            'source_type' => 'package',
            'package_path' => 'leadmanagement',
            'is_active' => true,
            'requires_license' => true,
            'license_status' => 'active',
        ]);

        $company = Company::create([
            'name' => 'Acme CRM Corp',
            'status' => 'active',
            'pos_mode' => 'general',
            'licensed_modules' => ['retail', 'leadmanagement'],
            'currency_symbol' => '$',
            'expires_at' => now()->addYear(),
        ]);
        $user = User::create([
            'company_id' => $company->id,
            'name' => 'CRM Admin',
            'login' => 'crmadmin',
            'email' => 'crm@acme.test',
            'password' => Hash::make('secret1234'),
            'role' => 'administrator',
            'status' => 'approved',
        ]);
        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        return [$company, $user];
    }

    public function test_all_five_lead_routes_load_successfully(): void
    {
        $this->leadTenant();

        $this->get(route('tenant.leads.index'))->assertOk()->assertSee('Lead Management');
        $this->get(route('tenant.leads.dashboard'))->assertOk()->assertSee('Overview & Pipeline');
        $this->get(route('tenant.leads.pipeline'))->assertOk()->assertSee('Leads Pipeline');
        $this->get(route('tenant.leads.activities'))->assertOk()->assertSee('Follow-ups & Activities');
        $this->get(route('tenant.leads.sources'))->assertOk()->assertSee('Lead Sources');
        $this->get(route('tenant.leads.create'))->assertRedirect(route('tenant.leads.index', ['tab' => 'capture']));
    }

    public function test_navigation_renders_all_five_lead_items_in_drawer_and_sidebar(): void
    {
        $this->leadTenant();

        $response = $this->get(route('tenant.leads.index'))->assertOk();

        // Mobile drawer items
        $response->assertSee('data-section-key="lead_ops"', false);
        $response->assertSee('item-key="lead_dashboard"', false);
        $response->assertSee('item-key="lead_create"', false);
        $response->assertSee('item-key="lead_pipeline"', false);
        $response->assertSee('item-key="lead_activities"', false);
        $response->assertSee('item-key="lead_sources"', false);

        // Menu item titles matching Flutter SDUI
        $response->assertSee('Leads Dashboard');
        $response->assertSee('Capture Lead');
        $response->assertSee('Leads Pipeline');
        $response->assertSee('Follow-ups & Activities');
        $response->assertSee('Lead Sources');
    }

    public function test_can_create_and_destroy_lead_source(): void
    {
        [$company] = $this->leadTenant();

        $storeResponse = $this->post(route('tenant.leads.sources.store'), [
            'name' => 'Instagram Ads',
            'description' => 'Paid sponsored social campaigns',
        ]);

        $storeResponse->assertRedirect(route('tenant.leads.sources'));
        $storeResponse->assertSessionHas('status');

        $this->assertDatabaseHas('lead_mod_sources', [
            'company_id' => $company->id,
            'name' => 'Instagram Ads',
        ]);

        $source = LeadSource::where('company_id', $company->id)->where('name', 'Instagram Ads')->firstOrFail();

        $deleteResponse = $this->delete(route('tenant.leads.sources.destroy', $source->id));
        $deleteResponse->assertRedirect(route('tenant.leads.sources'));
        $deleteResponse->assertSessionHas('status');

        $this->assertDatabaseMissing('lead_mod_sources', [
            'id' => $source->id,
        ]);
    }
}
