<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RunDesktopSyncCycle;
use App\Models\Company;
use App\Models\Configuration;
use App\Services\Sync\DesktopSyncEngine;
use App\Support\Desktop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunDesktopSyncCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(storage_path('app/desktop-active-company'));
        parent::tearDown();
    }

    protected function configureCompanyForSync(Company $company, string $baseUrl): void
    {
        Configuration::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'desktop_sync.remote_base_url'],
            ['value' => $baseUrl]
        );
        Configuration::withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'desktop_sync.device_token'],
            ['value' => Crypt::encryptString('device-token')]
        );
    }

    /**
     * Regression test: a device that has ever bootstrapped more than one
     * cloud account (switching accounts, shared/demo hardware, testing)
     * accumulates more than one local Company row. Company::first() used to
     * pick whichever one happened to have the lowest id and sync that one
     * forever, regardless of who is actually logged in — starving the real
     * account of background refreshes and mixing a foreign tenant's data
     * into the same local database. The job must follow
     * Desktop::activeCompanyId() (set at login) instead.
     */
    public function test_sync_cycle_targets_the_actively_logged_in_company_not_the_first_row(): void
    {
        Queue::fake();

        $companyA = Company::create(['name' => 'Old Test Company', 'slug' => 'old-test-co']);
        $companyB = Company::create(['name' => 'Currently Logged In Co', 'slug' => 'current-co']);

        $this->configureCompanyForSync($companyA, 'https://old-company.test');
        $this->configureCompanyForSync($companyB, 'https://current-company.test');

        Desktop::rememberActiveCompany($companyB->id);

        Http::fake([
            'https://current-company.test/*' => Http::response([
                'success' => true, 'server_time' => now()->toIso8601String(),
                'products' => [], 'categories' => [], 'customers' => [], 'suppliers' => [], 'brands' => [], 'units' => [], 'sales' => [], 'quotations' => [],
                'data' => [], 'ok' => true,
            ]),
            'https://old-company.test/*' => Http::response(['ok' => true]),
        ]);

        (new RunDesktopSyncCycle)->handle(app(DesktopSyncEngine::class));

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://current-company.test'));
        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://old-company.test'));

        $statusB = Configuration::withoutGlobalScopes()
            ->where('company_id', $companyB->id)->where('key', 'desktop_sync.status')->value('value');
        $statusA = Configuration::withoutGlobalScopes()
            ->where('company_id', $companyA->id)->where('key', 'desktop_sync.status')->value('value');

        $this->assertSame('synced', $statusB, 'The currently logged-in company must be the one that gets synced.');
        $this->assertNull($statusA, 'A different, previously-used company on this device must not be touched.');
    }
}
