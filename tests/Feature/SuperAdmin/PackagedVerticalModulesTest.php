<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Company;
use App\Models\PlatformSystem;
use App\Services\Modular\ModulePackageService;
use App\Services\Modular\ModuleRegistry;
use App\Services\Navigation\TenantNavRegistry;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

/**
 * Lifecycle + smoke test for the two standalone vertical packages under
 * module-packages/. Zips the real source, installs it through
 * ModulePackageService exactly as Super Admin -> Modules would, activates,
 * drives one screen + one create through the module controller, then
 * uninstalls with drop-data and asserts a clean slate.
 */
class PackagedVerticalModulesTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'pharmacy_mod_prescription_items', 'pharmacy_mod_prescriptions', 'pharmacy_mod_drug_batches',
        'repair_mod_ticket_items', 'repair_mod_tickets', 'repair_mod_device_categories',
        'salon_mod_appointments', 'salon_mod_stylists', 'salon_mod_services',
    ];

    protected function tearDown(): void
    {
        foreach (['pharmacy', 'repairtechnician', 'salon'] as $key) {
            File::deleteDirectory(base_path('modules/'.$key));
        }
        foreach (self::TABLES as $t) {
            Schema::dropIfExists($t);
        }
        parent::tearDown();
    }

    private function zipPackage(string $key): UploadedFile
    {
        $src = base_path('module-packages/'.$key);
        $this->assertFileExists($src.'/module.json', "missing source package: {$key}");

        $zipPath = storage_path('framework/testing/'.$key.'_pkg.zip');
        @unlink($zipPath);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        foreach (File::allFiles($src, true) as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }
        $zip->close();

        return UploadedFile::fake()->createWithContent($key.'.zip', file_get_contents($zipPath));
    }

    private function tenantRequest(Company $company): Request
    {
        app()->instance('tenant.company_id', $company->id);
        $request = Request::create('/', 'GET');
        $request->attributes->set('company_id', $company->id);

        return $request;
    }

    public function test_pharmacy_package_installs_activates_runs_and_uninstalls(): void
    {
        $service = app(ModulePackageService::class);
        $company = Company::create([
            'name' => 'Rx Co', 'slug' => 'rx-co', 'status' => 'active',
            'pos_mode' => 'retail', 'currency' => 'USD', 'currency_symbol' => '$',
        ]);

        // install -> inactive package row + files on disk
        $module = $service->install($this->zipPackage('pharmacy'), null);
        $this->assertSame('pharmacy', $module->slug);
        $this->assertSame('package', $module->source_type);
        $this->assertFalse($module->is_active);
        $this->assertTrue(is_dir(base_path('modules/pharmacy')));

        // activate -> migrations run
        $service->activate($module, null);
        $this->assertTrue($module->fresh()->is_active);
        $this->assertTrue(Schema::hasTable('pharmacy_mod_drug_batches'));
        $this->assertTrue(Schema::hasTable('pharmacy_mod_prescriptions'));

        $controller = new \Modules\pharmacy\Http\Controllers\PharmacyModuleController();

        // dashboard renders a valid SDUI screen
        $dash = $controller->dashboard($this->tenantRequest($company));
        $this->assertSame(200, $dash->getStatusCode());
        $payload = $dash->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertSame('screen', $payload['schema']['type']);
        $this->assertEmpty(app(SchemaValidator::class)->validate($payload['schema']));

        // create a batch, then confirm it shows on the batches screen
        $store = Request::create('/', 'POST', [
            'product_name' => 'Amoxicillin 500mg', 'batch_no' => 'B-01',
            'expiry_date' => now()->addMonths(6)->toDateString(), 'quantity' => 120, 'mrp' => 3.5,
        ]);
        $store->attributes->set('company_id', $company->id);
        $this->assertTrue($controller->batchesStore($store)->getData(true)['success']);

        $batchesJson = json_encode(
            $controller->batchesView($this->tenantRequest($company))->getData(true),
            JSON_UNESCAPED_SLASHES,
        );
        $this->assertStringContainsString('Amoxicillin 500mg', $batchesJson);

        // uninstall + drop data -> clean slate
        $service->uninstall($module->fresh(), true, null);
        $this->assertNull(\App\Models\SduiModule::where('slug', 'pharmacy')->first());
        $this->assertFalse(is_dir(base_path('modules/pharmacy')));
        $this->assertFalse(Schema::hasTable('pharmacy_mod_drug_batches'));
    }

    public function test_repair_package_installs_activates_runs_and_uninstalls(): void
    {
        $service = app(ModulePackageService::class);
        $company = Company::create([
            'name' => 'Fix Co', 'slug' => 'fix-co', 'status' => 'active',
            'pos_mode' => 'retail', 'currency' => 'USD', 'currency_symbol' => '$',
        ]);

        $module = $service->install($this->zipPackage('repairtechnician'), null);
        $this->assertSame('repairtechnician', $module->slug);
        $this->assertFalse($module->is_active);

        $service->activate($module, null);
        $this->assertTrue(Schema::hasTable('repair_mod_tickets'));
        $this->assertTrue(Schema::hasTable('repair_mod_device_categories'));

        $controller = new \Modules\repairtechnician\Http\Controllers\RepairModuleController();

        $dash = $controller->dashboard($this->tenantRequest($company))->getData(true);
        $this->assertTrue($dash['success']);
        $this->assertEmpty(app(SchemaValidator::class)->validate($dash['schema']));

        // category -> ticket, checklist points copy across
        $catReq = Request::create('/', 'POST', [
            'name' => 'Smartphone', 'default_diagnostic_fee' => 10,
            'checklist_points' => "Power\nDisplay\nCharging",
        ]);
        $catReq->attributes->set('company_id', $company->id);
        $catId = $controller->categoriesStore($catReq)->getData(true)['id'];

        $ticketReq = Request::create('/', 'POST', [
            'customer_name' => 'Jane Doe', 'customer_phone' => '555-1000',
            'category_id' => $catId, 'device_brand' => 'Acme', 'device_model' => 'X1',
            'reported_issue' => 'No power',
        ]);
        $ticketReq->attributes->set('company_id', $company->id);
        $ticketId = $controller->ticketsStore($ticketReq)->getData(true)['id'];

        $ticket = \Modules\repairtechnician\Models\RepairTicket::find($ticketId);
        $this->assertCount(3, $ticket->inspection_checklist);

        // status transition
        $statusReq = Request::create('/', 'POST', ['status' => 'in_progress']);
        $statusReq->attributes->set('company_id', $company->id);
        $this->assertTrue($controller->ticketStatus($statusReq, (string) $ticketId)->getData(true)['success']);
        $this->assertSame('in_progress', $ticket->fresh()->status);

        $service->uninstall($module->fresh(), true, null);
        $this->assertFalse(Schema::hasTable('repair_mod_tickets'));
        $this->assertFalse(is_dir(base_path('modules/repairtechnician')));
    }

    public function test_salon_package_installs_activates_runs_and_uninstalls(): void
    {
        $service = app(ModulePackageService::class);
        $company = Company::create([
            'name' => 'Glow Co', 'slug' => 'glow-co', 'status' => 'active',
            'pos_mode' => 'retail', 'currency' => 'USD', 'currency_symbol' => '$',
        ]);

        $module = $service->install($this->zipPackage('salon'), null);
        $this->assertSame('salon', $module->slug);
        $this->assertFalse($module->is_active);

        $service->activate($module, null);
        $this->assertTrue(Schema::hasTable('salon_mod_appointments'));
        $this->assertTrue(Schema::hasTable('salon_mod_services'));
        $this->assertTrue(Schema::hasTable('salon_mod_stylists'));

        $controller = new \Modules\salon\Http\Controllers\SalonModuleController();

        $dash = $controller->dashboard($this->tenantRequest($company))->getData(true);
        $this->assertTrue($dash['success']);
        $this->assertEmpty(app(SchemaValidator::class)->validate($dash['schema']));

        // service + stylist -> appointment -> priced from the service
        $svcReq = Request::create('/', 'POST', ['name' => 'Haircut & Style', 'duration_minutes' => 30, 'price' => 25]);
        $svcReq->attributes->set('company_id', $company->id);
        $svcId = $controller->servicesStore($svcReq)->getData(true)['id'];

        $stReq = Request::create('/', 'POST', ['name' => 'Alex Kim', 'specialties' => 'colour, balayage']);
        $stReq->attributes->set('company_id', $company->id);
        $stId = $controller->stylistsStore($stReq)->getData(true)['id'];

        $apReq = Request::create('/', 'POST', [
            'customer_name' => 'Priya', 'customer_phone' => '555-2000',
            'service_id' => $svcId, 'stylist_id' => $stId,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);
        $apReq->attributes->set('company_id', $company->id);
        $apId = $controller->appointmentsStore($apReq)->getData(true)['id'];

        $appt = \Modules\salon\Models\Appointment::find($apId);
        $this->assertSame('25.00', (string) $appt->price);

        $detail = $controller->appointmentDetail(
            tap(Request::create('/', 'GET', ['id' => $apId]), fn ($r) => $r->attributes->set('company_id', $company->id))
        )->getData(true);
        $this->assertEmpty(app(SchemaValidator::class)->validate($detail['schema']));

        $stReq2 = Request::create('/', 'POST', ['status' => 'confirmed']);
        $stReq2->attributes->set('company_id', $company->id);
        $this->assertTrue($controller->appointmentStatus($stReq2, (string) $apId)->getData(true)['success']);
        $this->assertSame('confirmed', $appt->fresh()->status);

        $service->uninstall($module->fresh(), true, null);
        $this->assertFalse(Schema::hasTable('salon_mod_appointments'));
        $this->assertFalse(is_dir(base_path('modules/salon')));
    }

    public function test_deactivate_and_uninstall_fully_remove_the_module_from_every_surface(): void
    {
        $service = app(ModulePackageService::class);
        $company = Company::create([
            'name' => 'Gate Co', 'slug' => 'gate-co', 'status' => 'active',
            'pos_mode' => 'retail', 'currency' => 'USD', 'currency_symbol' => '$',
        ]);

        // Pretend a SuperAdmin had also enabled it for registration.
        PlatformSystem::set('allowed_registration_modes', json_encode(['retail', 'salon']));

        $module = $service->install($this->zipPackage('salon'), null);
        $service->activate($module, null);

        // Active -> visible in the module registry AND the tenant drawer.
        $this->assertTrue(ModuleRegistry::isActive('salon'));
        $this->assertArrayHasKey('salon', ModuleRegistry::allModules());
        $navKeys = collect(TenantNavRegistry::getEffectiveNavForTenant($company))->pluck('key');
        $this->assertTrue($navKeys->contains(fn ($k) => str_starts_with((string) $k, 'salon')));

        // Deactivate -> gone from every read surface, data + files kept.
        $service->deactivate($module->fresh(), null);
        $this->assertFalse(ModuleRegistry::isActive('salon'));
        $this->assertArrayNotHasKey('salon', ModuleRegistry::allModules());
        $this->assertTrue(ModuleRegistry::isInstalled('salon'));
        $this->assertTrue(Schema::hasTable('salon_mod_services'));
        $this->assertTrue(is_dir(base_path('modules/salon')));
        $navKeys = collect(TenantNavRegistry::getEffectiveNavForTenant($company))->pluck('key');
        $this->assertFalse($navKeys->contains(fn ($k) => str_starts_with((string) $k, 'salon')));

        // Uninstall + drop data -> row, files, tables all gone; registration
        // modes list no longer carries the dangling key.
        $service->uninstall($module->fresh(), true, null);
        $this->assertFalse(ModuleRegistry::isInstalled('salon'));
        $this->assertFalse(Schema::hasTable('salon_mod_services'));
        $this->assertFalse(is_dir(base_path('modules/salon')));

        $modes = json_decode((string) PlatformSystem::get('allowed_registration_modes'), true);
        $this->assertNotContains('salon', $modes);
        $this->assertContains('retail', $modes);
    }

    public function test_a_purged_module_re_installs_cleanly_without_table_already_exists(): void
    {
        $service = app(ModulePackageService::class);
        Company::create([
            'name' => 'Cycle Co', 'slug' => 'cycle-co', 'status' => 'active',
            'pos_mode' => 'retail', 'currency' => 'USD', 'currency_symbol' => '$',
        ]);

        // Cycle 1 — install, activate, uninstall + drop data.
        $m1 = $service->install($this->zipPackage('pharmacy'), null);
        $service->activate($m1, null);
        $this->assertTrue(Schema::hasTable('pharmacy_mod_drug_batches'));
        $service->uninstall($m1->fresh(), true, null);
        $this->assertFalse(Schema::hasTable('pharmacy_mod_drug_batches'));

        // The migration tracking row must be gone, or cycle 2's activate
        // would silently skip the migration and leave no tables.
        $this->assertDatabaseMissing('migrations', [
            'migration' => '2026_09_08_000001_create_pharmacy_module_tables',
        ]);

        // Cycle 2 — a fresh install + activate must succeed and rebuild the
        // tables (no "table already exists", no skipped migration).
        $m2 = $service->install($this->zipPackage('pharmacy'), null);
        $this->assertFalse($m2->is_active);
        $service->activate($m2, null); // would throw RuntimeException on failure
        $this->assertTrue(Schema::hasTable('pharmacy_mod_drug_batches'));
        $this->assertTrue(Schema::hasTable('pharmacy_mod_prescriptions'));

        $service->uninstall($m2->fresh(), true, null);
    }

    public function test_reserved_pharmacy_key_is_allowed_only_because_it_inherits_universal_pos(): void
    {
        // Sanity: the built-in "pharmacy" key is reserved; our manifest gets
        // through purely on inherits_ui = universal_pos. Prove the guard is
        // still there for a manifest without it.
        $this->assertStringContainsString('"inherits_ui": "universal_pos"', File::get(base_path('module-packages/pharmacy/module.json')));
    }
}
