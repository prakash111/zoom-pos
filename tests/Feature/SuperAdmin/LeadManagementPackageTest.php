<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\Api\LeadController;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\leadmanagement\Http\Controllers\LeadModuleController;
use Modules\leadmanagement\Models\Lead;
use Modules\leadmanagement\Models\LeadActivity;
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

    private string $superAdminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdminId = (string) $this->actingAsSuperAdmin()->id;
        Auth::shouldUse('web');
    }

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
            'licensed_modules' => ['retail', 'leadmanagement'],
            'currency' => 'USD',
            'currency_symbol' => '$',
        ]);

        // 1. Install from ZIP
        $module = $service->install($this->zipLeadPackage(), null);
        $this->assertSame('leadmanagement', $module->slug);
        $this->assertSame('package', $module->source_type);
        $this->assertSame('extension', $module->type);
        $this->assertFalse($module->registration_allowed);
        $this->assertFalse($module->is_active);
        $this->assertTrue(is_dir(base_path('modules/leadmanagement')));
        $this->assertFileExists(base_path('modules/leadmanagement/module.json'));

        // 2. License and Activate
        $this->licenseModule($module);
        $service->activate($module, $this->superAdminId);

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
        $this->assertEmpty($validator->validate($dashData['schema']), json_encode($validator->validate($dashData['schema'])));

        // Sources CRUD
        $sourceReq = $this->tenantRequest($company, '/', 'POST', [
            'name' => 'Google Search Ads',
            'description' => 'Inbound PPC leads',
        ]);
        $sourceStoreData = $controller->sourcesStore($sourceReq)->getData(true);
        $this->assertTrue($sourceStoreData['success']);
        $sourceId = $sourceStoreData['id'];

        $sourcesViewData = $controller->sourcesView($this->tenantRequest($company))->getData(true);
        $this->assertEmpty($validator->validate($sourcesViewData['schema']), json_encode($validator->validate($sourcesViewData['schema'])));
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
        $this->assertEmpty($validator->validate($leadsViewData['schema']), json_encode($validator->validate($leadsViewData['schema'])));
        $this->assertStringContainsString('Alice Johnson', json_encode($leadsViewData));
        $this->assertStringContainsString($leadCode, json_encode($leadsViewData));

        // Lead Detail View
        $detailReq = $this->tenantRequest($company, '/', 'GET', ['id' => $leadId]);
        $detailData = $controller->leadDetail($detailReq)->getData(true);
        $this->assertEmpty($validator->validate($detailData['schema']), json_encode($validator->validate($detailData['schema'])));
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

    public function test_lead_management_module_navigation_isolation_and_permission_gating(): void
    {
        File::copyDirectory(base_path('module-packages/leadmanagement'), base_path('modules/leadmanagement'));
        SduiModule::create([
            'name' => 'Lead Management System',
            'slug' => 'leadmanagement',
            'source_type' => 'package',
            'package_path' => 'leadmanagement',
            'is_active' => true,
            'requires_license' => true,
            'license_status' => 'active',
            'navigation' => [
                [
                    'key' => 'lead_ops',
                    'title' => 'Lead Management',
                    'icon' => 'leaderboard',
                    'items' => [
                        ['key' => 'lead_dashboard', 'title' => 'Leads Dashboard', 'icon' => 'dashboard', 'target_endpoint' => '/api/tenant/lead-module/views/dashboard'],
                        ['key' => 'lead_pipeline', 'title' => 'Leads Pipeline', 'icon' => 'view_kanban', 'target_endpoint' => '/api/tenant/lead-module/views/leads'],
                    ],
                ],
            ],
        ]);

        // 1. Tenant with no leadmanagement licensed
        $unlicensedTenant = Company::create([
            'id' => 'crm_unlicensed_001',
            'name' => 'Unlicensed Retail Store',
            'slug' => 'unlicensed-retail',
            'status' => 'active',
            'pos_mode' => 'retail',
            'licensed_modules' => ['retail', 'restaurant'],
        ]);

        $navUnlicensed = TenantNavRegistry::getEffectiveNavForTenant($unlicensedTenant);
        $unlicensedSections = collect($navUnlicensed)->keyBy('key');

        $this->assertFalse($unlicensedSections->has('lead_ops'), 'Unlicensed tenant must not have lead_ops section');
        $cashierSalesItems = collect($unlicensedSections->get('cashier_sales')['items'] ?? [])->pluck('key')->all();
        $this->assertNotContains('lead_management', $cashierSalesItems, 'Unlicensed tenant must not have lead_management in cashier_sales');
        $this->assertSame(['pos', 'sales', 'quotations', 'consignments', 'customers'], $cashierSalesItems);

        $featuresUnlicensed = ModuleRegistry::activeFeaturesFor($unlicensedTenant);
        $this->assertFalse($featuresUnlicensed['leads']);
        $this->assertFalse($featuresUnlicensed['lead_management']);

        // 2. Tenant with leadmanagement licensed
        $licensedTenant = Company::create([
            'id' => 'crm_licensed_001',
            'name' => 'Licensed CRM Store',
            'slug' => 'licensed-crm',
            'status' => 'active',
            'pos_mode' => 'retail',
            'licensed_modules' => ['retail', 'leadmanagement'],
        ]);

        $navLicensed = TenantNavRegistry::getEffectiveNavForTenant($licensedTenant);
        $licensedSections = collect($navLicensed)->keyBy('key');

        $this->assertTrue($licensedSections->has('lead_ops'), 'Licensed tenant must have standalone lead_ops section');
        $leadOpsItems = collect($licensedSections->get('lead_ops')['items'] ?? [])->pluck('key')->all();
        $this->assertContains('lead_dashboard', $leadOpsItems);
        $this->assertContains('lead_pipeline', $leadOpsItems);

        // Crucial: Lead Management must NOT be mixed into cashier_sales even when licensed
        $licensedCashierItems = collect($licensedSections->get('cashier_sales')['items'] ?? [])->pluck('key')->all();
        $this->assertNotContains('lead_management', $licensedCashierItems, 'Licensed tenant must not have lead_management mixed in cashier_sales');

        $featuresLicensed = ModuleRegistry::activeFeaturesFor($licensedTenant);
        $this->assertTrue($featuresLicensed['leads']);
        $this->assertTrue($featuresLicensed['lead_management']);

        // 3. Custom navigation tree with rogue/stray lead_management in cashier_sales
        $unlicensedTenant->update([
            'navigation_menu_customization' => [
                'tree' => [
                    [
                        'key' => 'cashier_sales',
                        'items' => [
                            ['key' => 'pos'],
                            ['key' => 'sales'],
                            ['key' => 'lead_management'],
                            ['key' => 'customers'],
                        ],
                    ],
                    [
                        'key' => 'lead_ops',
                        'items' => [
                            ['key' => 'lead_dashboard'],
                        ],
                    ],
                ],
            ],
        ]);

        $customNav = TenantNavRegistry::getEffectiveNavForTenant($unlicensedTenant->fresh());
        $customSections = collect($customNav)->keyBy('key');
        $this->assertFalse($customSections->has('lead_ops'), 'Saved lead_ops must be purged for unlicensed tenant');
        $customCashierItems = collect($customSections->get('cashier_sales')['items'] ?? [])->pluck('key')->all();
        $this->assertNotContains('lead_management', $customCashierItems, 'Saved lead_management in cashier_sales must be purged');
    }

    public function test_lead_lookup_by_lead_code_and_id_and_prefix_variations(): void
    {
        $service = app(ModulePackageService::class);
        $module = $service->install($this->zipLeadPackage(), $this->superAdminId);
        $this->licenseModule($module);
        $service->activate($module, $this->superAdminId);

        $company = Company::create([
            'id' => 'cmp_test_lookup_'.uniqid(),
            'name' => 'Lookup Test Corp',
            'slug' => 'lookup-test-'.uniqid(),
            'status' => 'active',
            'pos_mode' => 'retail',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'licensed_modules' => ['retail', 'leadmanagement'],
        ]);

        $admin = User::create([
            'company_id' => $company->id,
            'name' => 'Lead Admin',
            'email' => 'lookup_admin_'.uniqid().'@example.test',
            'password' => bcrypt('secret123'),
            'role' => 'administrator',
        ]);

        $lead = Lead::create([
            'company_id' => $company->id,
            'lead_code' => 'LD-TEST999',
            'name' => 'Test Lead Buyer',
            'email' => 'buyer@test.org',
            'phone' => '1234567890',
            'status' => 'active',
            'stage' => 'qualified',
            'priority' => 'high',
            'expected_value' => 15000.00,
            'estimated_value' => 15000.00,
            'assigned_to' => $admin->id,
        ]);

        $this->actingAs($admin);
        $controller = app(LeadController::class);

        // 1. Lookup by numeric ID
        $reqId = $this->tenantRequest($company, '/', 'GET', ['id' => (string) $lead->id]);
        $resId = $controller->leadDetail($reqId);
        $this->assertSame(200, $resId->getStatusCode());
        $this->assertStringContainsString('LD-TEST999', json_encode($resId->getData(true)));

        // 2. Lookup by full lead_code with LD- prefix
        $reqCode = $this->tenantRequest($company, '/', 'GET', ['id' => 'LD-TEST999']);
        $resCode = $controller->leadDetail($reqCode);
        $this->assertSame(200, $resCode->getStatusCode());

        // 3. Lookup by code without LD- prefix
        $reqNoPrefix = $this->tenantRequest($company, '/', 'GET', ['id' => 'TEST999']);
        $resNoPrefix = $controller->leadDetail($reqNoPrefix);
        $this->assertSame(200, $resNoPrefix->getStatusCode());

        // 4. RESTful leadsShow with code
        $reqShow = $this->tenantRequest($company, '/', 'GET');
        $resShow = $controller->leadsShow($reqShow, 'LD-TEST999');
        $this->assertSame(200, $resShow->getStatusCode());
        $this->assertSame('LD-TEST999', $resShow->getData(true)['lead']['lead_code']);

        // 5. RESTful leadsShow with no-prefix code
        $resShowNoPrefix = $controller->leadsShow($reqShow, 'TEST999');
        $this->assertSame(200, $resShowNoPrefix->getStatusCode());
        $this->assertSame('LD-TEST999', $resShowNoPrefix->getData(true)['lead']['lead_code']);

        // 6. Direct show method
        $resDirect = $controller->show('LD-TEST999');
        $this->assertSame(200, $resDirect->getStatusCode());

        // 7. Soft-deleted lead auto-restores and resolves
        $lead->delete();
        $this->assertSoftDeleted('lead_mod_leads', ['id' => $lead->id]);
        $resRestored = $controller->show('LD-TEST999');
        $this->assertSame(200, $resRestored->getStatusCode());
        $this->assertNotSoftDeleted('lead_mod_leads', ['id' => $lead->id]);

        // 8. All Leads tab and pipeline calculation
        $reqAllLeads = $this->tenantRequest($company, '/', 'GET', ['tab' => 'all_leads']);
        $resAllLeads = $controller->dashboard($reqAllLeads);
        $allLeadsJson = json_encode($resAllLeads->getData(true));
        $this->assertStringContainsString('1 leads in view', $allLeadsJson);
        $this->assertStringContainsString('15,000 pipeline value', $allLeadsJson);

        // 9. API index JSON response
        $reqIndex = $this->tenantRequest($company, '/api/v1/tenant/leads', 'GET');
        $reqIndex->headers->set('Accept', 'application/json');
        $resIndex = $controller->index($reqIndex);
        $this->assertSame(200, $resIndex->getStatusCode());
        $indexData = $resIndex->getData(true);
        $this->assertSame(1, $indexData['pipeline_count']);
        $this->assertEquals(15000.0, (float) $indexData['pipeline_value']);
    }
}
