<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Controllers\Api\RolePermissionController;
use App\Livewire\Auth\TenantRegister;
use App\Livewire\SuperAdmin\Modules\Index as ModulesIndex;
use App\Livewire\SuperAdmin\Tenants\Show;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Models\PlatformSystem;
use App\Models\Sale;
use App\Models\SduiModule;
use App\Models\TenantApiKey;
use App\Models\TenantSetting;
use App\Models\User;
use App\Providers\ModuleServiceProvider;
use App\Services\Auth\PermissionChecker;
use App\Services\Modular\ModulePackageService;
use App\Services\Modular\ModuleRegistry;
use App\Services\Navigation\TenantNavRegistry;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\Concerns\LicensesModules;
use Tests\TestCase;
use ZipArchive;

class LeadManagementExtensionTest extends TestCase
{
    use ActsAsPlatformAdmin, LicensesModules, RefreshDatabase;

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('modules/leadmanagement'));
        parent::tearDown();
    }

    private function installExtension(bool $active = false): SduiModule
    {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        $path = storage_path('framework/testing/lead-extension.zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (File::allFiles(base_path('module-packages/leadmanagement')) as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }
        $zip->close();
        $service = app(ModulePackageService::class);
        $module = $service->install(UploadedFile::fake()->createWithContent('leadmanagement.zip', File::get($path)), null);
        $this->licenseModule($module);
        if ($active) {
            $admin = $this->actingAsSuperAdmin();
            $service->activate($module, $admin->id);
            (new ModuleServiceProvider(app()))->bootModule('leadmanagement');
            auth('platform_web')->logout();
            Auth::shouldUse('web');
        }

        return $module->refresh();
    }

    private function tenant(?array $modules = ['retail'], bool $userBoundKey = true): array
    {
        PlatformSystem::set('auto_seed_demo_data_on_registration', '0');
        Plan::firstOrCreate(['name' => 'extension-test'], [
            'display_name' => 'Extension Test', 'price' => 10, 'currency' => 'USD',
            'billing_cycle' => 'monthly', 'duration_days' => 30,
            'features' => ['pos' => true, 'quotes' => true], 'active' => true,
        ]);
        $company = Company::create([
            'name' => 'Retail Extension Test', 'slug' => 'extension-tenant',
            'status' => 'active', 'pos_mode' => 'retail', 'licensed_modules' => $modules,
            'plan_name' => 'extension-test', 'expires_at' => now()->addMonth(),
            'currency' => 'USD', 'currency_symbol' => '$', 'is_seeding_complete' => true,
        ]);
        $user = User::create([
            'company_id' => $company->id, 'name' => 'Tenant Owner',
            'email' => 'extension-owner@example.test', 'password' => 'secret1234',
            'role' => 'administrator', 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $token = 'zk_live_lead_extension_test';
        TenantApiKey::create([
            'company_id' => $company->id, 'user_id' => $userBoundKey ? $user->id : null,
            'name' => 'Extension Test', 'token' => $token, 'permissions' => ['*'], 'active' => true,
        ]);

        return [$company, $user, $token];
    }

    private function assertCannotActivate(SduiModule $module, ?string $actor): void
    {
        try {
            app(ModulePackageService::class)->activate($module, $actor);
            $this->fail('Unauthorized extension activation was accepted.');
        } catch (AuthorizationException) {
            $this->assertFalse($module->fresh()->is_active);
        }
    }

    public function test_extensions_never_appear_in_registration_even_if_configured(): void
    {
        $module = $this->installExtension(true);
        $module->update(['registration_allowed' => true]);
        $this->assertSame('extension', $module->type);
        $this->assertFalse($module->fresh()->registration_allowed);

        foreach (['all', 'both', '["retail","restaurant","leadmanagement","leads"]'] as $setting) {
            PlatformSystem::set('allowed_registration_modes', $setting);
            $this->assertArrayNotHasKey('leadmanagement', ModuleRegistry::registrationModules());
            $this->assertContains('retail', ModuleRegistry::enabledRegistrationModes());
            $this->assertContains('restaurant', ModuleRegistry::enabledRegistrationModes());
        }
        Livewire::test(TenantRegister::class)->assertDontSee('Lead Management System');
    }

    public function test_registration_rejects_forged_extension_modes(): void
    {
        $this->installExtension(true);
        foreach (['leadmanagement', 'lead_management', 'leads', 'lead'] as $mode) {
            $this->postJson('/api/auth/register', [
                'store_name' => 'Forged CRM', 'name' => 'Forged Owner',
                'email' => 'forged@example.test', 'password' => 'secret1234',
                'password_confirmation' => 'secret1234', 'pos_mode' => $mode,
            ])->assertStatus(422);
        }
        Livewire::test(TenantRegister::class)
            ->set('storeName', 'Forged CRM')->set('slug', 'forged-crm')
            ->set('ownerName', 'Forged Owner')->set('email', 'forged@example.test')
            ->set('password', 'secret1234')->set('password_confirmation', 'secret1234')
            ->set('posMode', 'leadmanagement')->call('register')->assertHasErrors('posMode');
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_provisioning_cannot_grant_an_extension_as_the_primary_mode(): void
    {
        try {
            app(TenantProvisioningService::class)->registerTenant(['pos_mode' => 'lead_management']);
            $this->fail('Provisioning accepted an extension operating mode.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pos_mode', $e->errors());
            $this->assertDatabaseCount('companies', 0);
        }
    }

    public function test_only_super_admin_can_activate_from_the_panel(): void
    {
        $module = $this->installExtension();
        [, $owner] = $this->tenant();
        $this->actingAs($owner, 'web');
        $this->assertCannotActivate($module, $owner->id);
        $support = $this->actingAsSupportAdmin();
        $this->assertCannotActivate($module, $support->id);
        $admin = $this->actingAsSuperAdmin();
        $this->assertCannotActivate($module, null);
        Livewire::test(ModulesIndex::class)->call('activate', $module->id);
        $this->assertTrue($module->fresh()->is_active);
    }

    public function test_tenant_settings_and_role_grants_cannot_enable_the_extension(): void
    {
        $this->installExtension(true);
        [$company, $owner, $token] = $this->tenant();
        TenantSetting::set($company->id, 'enabled_modules', ['retail', 'leadmanagement']);
        Permission::create(['company_id' => $company->id, 'user_id' => $owner->id, 'module' => 'leads', 'action' => 'view', 'allowed' => true]);
        $this->assertFalse(PermissionChecker::can($owner, 'leads'));
        $this->assertArrayNotHasKey('leads', RolePermissionController::getFilteredModulesForTenant($company));
        $this->assertFalse(ModuleRegistry::activeFeaturesFor($company)['leads']);
        $this->assertNotContains('lead_ops', collect(TenantNavRegistry::getEffectiveNavForTenant($company))->pluck('key')->all());
        foreach (['/api/tenant/views/leads', '/api/tenant/views/create-lead', '/api/v1/tenant/leads', '/api/tenant/lead-module/views/dashboard', '/api/app/views/create-lead'] as $url) {
            $this->withToken($token)->getJson($url)->assertForbidden();
        }
        $this->withToken($token)->getJson('/api/app/bootstrap')->assertOk()->assertJsonMissingPath('modules.leadmanagement');
        $this->actingAs($owner, 'web');
        $this->getJson('/tenant/leads')->assertForbidden();
        $this->postJson("/superadmin/tenants/{$company->id}/modules", ['modules' => ['retail', 'leadmanagement']])->assertUnauthorized();
        $this->withToken($token)->postJson("/api/superadmin/tenants/{$company->id}/modules", ['modules' => ['retail', 'leadmanagement']])->assertForbidden();
    }

    public function test_super_admin_assigns_and_revokes_without_changing_the_core_mode(): void
    {
        $this->installExtension(true);
        [$company, , $token] = $this->tenant();
        $admin = PlatformAdmin::where('role', 'super_admin')->firstOrFail();
        $this->actingAs($admin, 'platform_web');
        $this->postJson("/superadmin/tenants/{$company->id}/modules", ['modules' => ['retail', 'leadmanagement']])->assertOk();
        auth('platform_web')->logout();
        Auth::shouldUse('web');
        $company->refresh();
        $this->assertSame('retail', $company->pos_mode);
        $this->assertTrue($company->hasModule('leads'));
        $this->assertSame(['retail'], ModuleRegistry::availableModes($company));
        $this->assertContains('lead_ops', collect(TenantNavRegistry::getEffectiveNavForTenant($company))->pluck('key')->all());
        $this->withToken($token)->getJson('/api/tenant/views/create-lead')->assertOk();
        $this->withToken($token)->getJson('/api/app/bootstrap')->assertOk()->assertJsonPath('modules.leadmanagement.type', 'extension');

        $this->actingAs($admin, 'platform_web');
        $this->postJson("/superadmin/tenants/{$company->id}/modules", ['modules' => ['retail']])->assertOk();
        auth('platform_web')->logout();
        Auth::shouldUse('web');
        $this->withToken($token)->getJson('/api/tenant/views/create-lead')->assertForbidden();
        $this->withToken($token)->getJson('/api/tenant/quotations/create-modal')->assertOk();
    }

    public function test_support_cannot_assign_extensions_but_can_manage_core_modules(): void
    {
        $this->installExtension(true);
        [$company] = $this->tenant();
        $this->actingAsSupportAdmin();
        $this->postJson("/superadmin/tenants/{$company->id}/modules", ['modules' => ['retail', 'leadmanagement']])->assertForbidden();
        $this->assertSame(['retail'], $company->fresh()->licensed_modules);
        $this->postJson("/superadmin/tenants/{$company->id}/modules", ['modules' => ['retail', 'restaurant']])->assertOk();
    }

    public function test_an_inactive_extension_cannot_be_newly_assigned(): void
    {
        $this->installExtension();
        [$company] = $this->tenant();
        $this->actingAsSuperAdmin();
        $this->postJson("/superadmin/tenants/{$company->id}/modules", ['modules' => ['retail', 'leadmanagement']])->assertStatus(422);
        $this->assertSame(['retail'], $company->fresh()->licensed_modules);
    }

    public function test_legacy_tenants_and_primary_mode_values_do_not_implicitly_grant_extensions(): void
    {
        $this->installExtension(true);
        [$company, , $token] = $this->tenant(null);
        $this->assertFalse($company->hasModule('leadmanagement'));
        $this->assertNotContains('lead_ops', collect(TenantNavRegistry::getEffectiveNavForTenant($company))->pluck('key')->all());
        $this->withToken($token)->getJson('/api/tenant/views/create-lead')->assertForbidden();
        $company->update(['pos_mode' => 'leadmanagement']);
        $this->assertSame('retail', ModuleRegistry::resolveActiveMode($company));
        $this->assertFalse($company->hasModule('leadmanagement'));
    }

    public function test_disabled_revoked_expired_and_missing_packages_block_access(): void
    {
        $module = $this->installExtension(true);
        [$company, , $token] = $this->tenant(['retail', 'leadmanagement']);
        $this->withToken($token)->getJson('/api/tenant/views/create-lead')->assertOk();
        foreach ([['is_active' => false], ['license_status' => 'revoked'], ['license_expires_at' => now()->subDay()]] as $state) {
            $module->update(['is_active' => true, 'license_status' => 'active', 'license_expires_at' => null, ...$state]);
            $this->assertFalse($company->hasModule('leadmanagement'));
            $this->assertFalse(ModuleRegistry::activeFeaturesFor($company)['leads']);
            $this->withToken($token)->getJson('/api/tenant/views/create-lead')->assertForbidden();
            $this->withToken($token)->getJson('/api/tenant/quotations/create-modal')->assertOk();
        }
        $module->update(['is_active' => true, 'license_status' => 'active', 'license_expires_at' => null]);
        $module->update(['package_path' => 'missing-lead-extension']);
        File::deleteDirectory(base_path('modules/leadmanagement'));
        $this->withToken($token)->getJson('/api/tenant/views/create-lead')->assertForbidden();
        $this->assertTrue(Schema::hasTable('lead_mod_leads'));
    }

    public function test_company_scoped_keys_and_quotation_integration_cannot_bypass_access(): void
    {
        $this->installExtension(true);
        [, , $token] = $this->tenant(['retail'], false);
        $this->withToken($token)->getJson('/api/tenant/views/create-lead')->assertForbidden();
        $this->withToken($token)->getJson('/api/tenant/quotations/create-modal?lead_id=1')->assertForbidden();
        $this->withToken($token)->postJson('/api/tenant/quotations', ['lead_id' => 1])->assertForbidden();
        $this->withToken($token)->getJson('/api/tenant/quotations/create-modal')->assertOk();
    }

    public function test_signed_license_callback_records_license_without_activating(): void
    {
        $module = $this->installExtension();
        $this->actingAsSuperAdmin();
        config(['services.license_server.secret' => 'extension-callback-secret']);
        $raw = json_encode(['product_slug' => 'leadmanagement', 'license_key' => 'demo-extension-callback-license-0000']);
        $this->call('POST', '/api/license/activate', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LICENSE_SIGNATURE' => hash_hmac('sha256', $raw, 'extension-callback-secret'),
        ], $raw)->assertOk()->assertJsonPath('activated', false);
        $this->assertSame('active', $module->fresh()->license_status);
        $this->assertFalse($module->fresh()->is_active);
    }

    public function test_tenant_panel_separates_extensions_from_operating_modes_and_core_selection(): void
    {
        $this->installExtension(true);
        [$company] = $this->tenant();
        $admin = PlatformAdmin::where('role', 'super_admin')->firstOrFail();
        $this->actingAs($admin, 'platform_web');
        Livewire::test(Show::class, ['company' => $company])
            ->assertSee('Optional Extensions')->assertDontSee('<option value="leadmanagement">', false)
            ->call('selectAllModules')->assertSet('licensedModules', ['retail', 'restaurant'])
            ->set('posMode', 'leadmanagement')->call('save')->assertHasErrors('posMode');
        Livewire::test(Show::class, ['company' => $company])
            ->set('licensedModules', ['retail', 'leadmanagement'])->call('save')->assertHasNoErrors();
        $this->assertSame(['retail', 'leadmanagement'], $company->fresh()->licensed_modules);
        $this->assertSame('retail', $company->fresh()->pos_mode);
    }

    public function test_type_migration_preserves_installation_licenses_assignments_and_core_modes(): void
    {
        $module = $this->installExtension(true);
        [$company] = $this->tenant(['retail', 'leadmanagement']);
        $before = $module->only(['is_active', 'source_type', 'package_path', 'license_status', 'license_key_hash']);
        SduiModule::create(['name' => 'Pharmacy', 'slug' => 'pharmacy', 'source_type' => 'package', 'is_active' => true]);
        $migration = require database_path('migrations/2026_09_16_120000_add_type_to_sdui_modules_table.php');
        $migration->down();
        DB::table('sdui_modules')->where('slug', 'leadmanagement')->update(['registration_allowed' => true]);
        PlatformSystem::set('allowed_registration_modes', '["retail","restaurant","pharmacy","leadmanagement"]');
        $migration->up();
        $this->assertSame('extension', $module->fresh()->type);
        $this->assertFalse($module->fresh()->registration_allowed);
        $this->assertSame($before, $module->fresh()->only(array_keys($before)));
        $this->assertSame(['retail', 'leadmanagement'], $company->fresh()->licensed_modules);
        $this->assertSame('core', SduiModule::where('slug', 'pharmacy')->firstOrFail()->type);
        $this->assertSame(['retail', 'restaurant', 'pharmacy'], json_decode(PlatformSystem::get('allowed_registration_modes'), true));
    }

    public function test_core_tenant_edits_preserve_existing_inactive_extension_assignments(): void
    {
        $module = $this->installExtension(true);
        [$company] = $this->tenant(['retail', 'leadmanagement']);
        $module->update(['is_active' => false]);
        $this->actingAsSupportAdmin();
        Livewire::test(Show::class, ['company' => $company])
            ->set('name', 'Updated Retail Store')->call('save')->assertHasNoErrors();
        $this->assertSame('Updated Retail Store', $company->fresh()->name);
        $this->assertSame(['retail', 'leadmanagement'], $company->fresh()->licensed_modules);
        $this->assertFalse($company->fresh()->hasModule('leadmanagement'));
    }

    public function test_existing_quotations_continue_working_after_extension_deactivation(): void
    {
        $module = $this->installExtension(true);
        [$company, $owner, $token] = $this->tenant(['retail', 'leadmanagement']);
        $lead = Lead::create(['company_id' => $company->id, 'lead_code' => 'LD-EXT-QUOTE', 'name' => 'Prior Lead', 'stage' => 'qualified']);
        $items = [['name' => 'Scope of work', 'price' => 100, 'quantity' => 1]];
        $quote = Sale::create([
            'company_id' => $company->id, 'user_id' => $owner->id, 'lead_id' => $lead->id,
            'sale_number' => 'Q-EXTENSION-TEST', 'operation_type' => 'quotation',
            'status' => 'draft', 'total' => 100, 'net_amount' => 100, 'items' => $items,
        ]);
        $module->update(['is_active' => false]);
        // WhatsApp dispatch only produces a link; no external message is sent.
        $this->withToken($token)->postJson("/api/tenant/quotations/{$quote->id}/dispatch", ['channel' => 'whatsapp'])
            ->assertOk()->assertJsonPath('status', 'sent')->assertJsonPath('whatsapp_url', null);
        $this->withToken($token)->putJson("/api/v1/pos/quotations/{$quote->id}", ['status' => 'accepted', 'items' => $items])->assertOk();
        $this->assertSame('qualified', $lead->fresh()->stage);
    }
}
