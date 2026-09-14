<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Company;
use App\Models\Customer;
use App\Models\SduiModule;
use App\Models\User;
use App\Services\Modular\ModulePackageService;
use App\Services\Modular\ModuleRegistry;
use App\Services\Navigation\TenantNavRegistry;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\leadmanagement\Http\Controllers\LeadModuleController;
use Modules\leadmanagement\Models\Lead;
use Modules\leadmanagement\Models\LeadActivity;
use Modules\leadmanagement\Models\LeadSource;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\Concerns\LicensesModules;
use Tests\TestCase;
use ZipArchive;

/**
 * Lifecycle and integration tests for the Lead Management package module.
 */
class LeadManagementPackageTest extends TestCase
{
    use ActsAsPlatformAdmin, LicensesModules, RefreshDatabase;

    private const TABLES = [
        'lead_mod_activities',
        'lead_mod_leads',
        'lead_mod_sources',
    ];

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('modules/leadmanagement'));
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
        parent::tearDown();
    }

    private function zipLeadPackage(): UploadedFile
    {
        $src = base_path('module-packages/leadmanagement');
        $this->assertFileExists($src.'/module.json', 'missing source package: leadmanagement');

        $zipPath = storage_path('framework/testing/leadmanagement_pkg.zip');
        @unlink($zipPath);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        foreach (File::allFiles($src, true) as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }
        $zip->close();

        return UploadedFile::fake()->createWithContent('leadmanagement.zip', file_get_contents($zipPath));
    }

    private function tenantRequest(Company $company, string $uri = '/', string $method = 'GET', array $parameters = []): Request
    {
        app()->instance('tenant.company_id', $company->id);
        $request = Request::create($uri, $method, $parameters);
        $request->attributes->set('company_id', $company->id);

        return $request;
    }

    public function test_lead_management_package_installs_activates_executes_and_uninstalls(): void
    {
        $service = app(ModulePackageService::class);
        $company = Company::create([
            'name' => 'Acme CRM Corp',
            'slug' => 'acme-crm',
            'status' => 'active',
            'pos_mode' => 'retail',
            'currency' => 'USD',
            'currency_symbol' => '$',
        ]);

        // 1. Install from ZIP
        $module = $service->install($this->zipLeadPackage(), null);
        $this->assertSame('leadmanagement', $module->slug);
        $this->assertSame('package', $module->source_type);
        $this->assertFalse($module->is_active);
        $this->assertTrue(is_dir(base_path('modules/leadmanagement')));
        $this->assertFileExists(base_path('modules/leadmanagement/module.json'));

        // 2. License and Activate
        $this->licenseModule($module);
        $service->activate($module, null);

        $this->assertTrue($module->fresh()->is_active);
        $this->assertTrue(Schema::hasTable('lead_mod_sources'));
        $this->assertTrue(Schema::hasTable('lead_mod_leads'));
        $this->assertTrue(Schema::hasTable('lead_mod_activities'));

        // 3. Navigation injection check
        $navKeys = collect(TenantNavRegistry::getEffectiveNavForTenant($company))
            ->pluck('key')
            ->map(fn ($k) => (string) $k);
        $this->assertTrue($navKeys->contains('lead_ops'), 'Tenant navigation should include lead_ops section');

        // 4. Controller instantiate & SDUI schema test
        $controller = app(LeadModuleController::class);
        $validator = app(SchemaValidator::class);

        // Dashboard
        $dashResponse = $controller->dashboard($this->tenantRequest($company));
        $this->assertSame(200, $dashResponse->getStatusCode());
        $dashData = $dashResponse->getData(true);
        $this->assertTrue($dashData['success']);
        $this->assertSame('screen', $dashData['schema']['type']);
        $this->assertEmpty($validator->validate($dashData['schema']));

        // Sources CRUD
        $sourceReq = $this->tenantRequest($company, '/', 'POST', [
            'name' => 'Google Search Ads',
            'description' => 'Inbound PPC leads',
        ]);
        $sourceStoreData = $controller->sourcesStore($sourceReq)->getData(true);
        $this->assertTrue($sourceStoreData['success']);
        $sourceId = $sourceStoreData['id'];

        $sourcesViewData = $controller->sourcesView($this->tenantRequest($company))->getData(true);
        $this->assertEmpty($validator->validate($sourcesViewData['schema']));
        $this->assertStringContainsString('Google Search Ads', json_encode($sourcesViewData));

        // Leads CRUD
        $repUser = User::create([
            'company_id' => $company->id,
            'name' => 'Bob Rep',
            'login' => 'bobrep',
            'email' => 'bob@acme.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SALESPERSON,
            'status' => 'active',
        ]);

        $leadReq = $this->tenantRequest($company, '/', 'POST', [
            'name' => 'Alice Johnson',
            'company_name' => 'Johnson Enterprises',
            'phone' => '555-4321',
            'email' => 'alice@johnson.test',
            'source_id' => $sourceId,
            'priority' => 'high',
            'estimated_value' => 7500.50,
            'assigned_to' => (string) $repUser->id,
            'notes' => 'Interested in enterprise annual plan.',
        ]);
        $leadStoreData = $controller->leadsStore($leadReq)->getData(true);
        $this->assertTrue($leadStoreData['success']);
        $leadId = $leadStoreData['id'];
        $leadCode = $leadStoreData['lead_code'];

        $lead = Lead::find($leadId);
        $this->assertNotNull($lead);
        $this->assertSame('Alice Johnson', $lead->name);
        $this->assertSame('Google Search Ads', $lead->source_name);
        $this->assertSame('7500.50', (string) $lead->estimated_value);
        $this->assertSame('active', $lead->status);
        $this->assertSame('new', $lead->stage);

        // Verify initial creation activity was logged
        $this->assertDatabaseHas('lead_mod_activities', [
            'lead_id' => $leadId,
            'type' => 'note',
            'title' => 'Lead Created',
        ]);

        // Leads Pipeline View
        $leadsViewData = $controller->leadsView($this->tenantRequest($company))->getData(true);
        $this->assertEmpty($validator->validate($leadsViewData['schema']));
        $this->assertStringContainsString('Alice Johnson', json_encode($leadsViewData));
        $this->assertStringContainsString($leadCode, json_encode($leadsViewData));

        // Lead Detail View
        $detailReq = $this->tenantRequest($company, '/', 'GET', ['id' => $leadId]);
        $detailData = $controller->leadDetail($detailReq)->getData(true);
        $this->assertEmpty($validator->validate($detailData['schema']));
        $this->assertStringContainsString($leadCode, json_encode($detailData));

        // Schedule Follow-up Activity
        $activityReq = $this->tenantRequest($company, '/', 'POST', [
            'lead_id' => $leadId,
            'type' => 'call',
            'title' => 'Discovery Call',
            'due_date' => now()->addDay()->toDateString(),
            'description' => 'Discuss enterprise requirements.',
        ]);
        $actStoreData = $controller->activitiesStore($activityReq)->getData(true);
        $this->assertTrue($actStoreData['success']);
        $activityId = $actStoreData['id'];

        $act = LeadActivity::find($activityId);
        $this->assertSame('pending', $act->status);

        // Complete Activity
        $compReq = $this->tenantRequest($company);
        $compData = $controller->activityComplete($compReq, (string) $activityId)->getData(true);
        $this->assertTrue($compData['success']);
        $this->assertSame('completed', $act->fresh()->status);
        $this->assertNotNull($act->fresh()->completed_at);

        // Status transition to Qualified
        $statusReq = $this->tenantRequest($company, '/', 'POST', ['status' => 'qualified']);
        $statusData = $controller->leadStatus($statusReq, (string) $leadId)->getData(true);
        $this->assertTrue($statusData['success']);
        $this->assertSame('qualified', $lead->fresh()->stage);

        // Convert Lead to Customer
        $convertReq = $this->tenantRequest($company);
        $convertData = $controller->leadConvert($convertReq, (string) $leadId)->getData(true);
        $this->assertTrue($convertData['success']);
        $customerId = $convertData['customer_id'];

        $customer = Customer::find($customerId);
        $this->assertNotNull($customer);
        $this->assertSame('Alice Johnson', $customer->name);
        $this->assertSame('555-4321', $customer->phone);
        $this->assertSame('alice@johnson.test', $customer->email);
        $this->assertSame($leadCode, $customer->custom_fields['converted_from_lead'] ?? null);

        $this->assertSame('won', $lead->fresh()->status);
        $this->assertSame($customerId, $lead->fresh()->customer_id);
        $this->assertNotNull($lead->fresh()->converted_at);

        // 5. Deactivate
        $service->deactivate($module->fresh(), null);
        $this->assertFalse(ModuleRegistry::isActive('leadmanagement'), 'ModuleRegistry should not be active');
        $this->assertTrue(is_dir(base_path('modules/leadmanagement')), 'Directory should still exist after deactivate');
        $this->assertTrue(Schema::hasTable('lead_mod_leads'), 'Table should still exist after deactivate');

        // 6. Uninstall + Drop Data
        $service->uninstall($module->fresh(), true, null);
        $this->assertNull(SduiModule::where('slug', 'leadmanagement')->first(), 'SduiModule row should be deleted');
        $this->assertFalse(is_dir(base_path('modules/leadmanagement')), 'Directory should be deleted after uninstall');
        $this->assertFalse(Schema::hasTable('lead_mod_sources'), 'lead_mod_sources should be dropped');
        $this->assertFalse(Schema::hasTable('lead_mod_leads'), 'lead_mod_leads should be dropped');
        $this->assertFalse(Schema::hasTable('lead_mod_activities'), 'lead_mod_activities should be dropped');
    }
}
