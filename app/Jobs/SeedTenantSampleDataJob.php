<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\User;
use App\Services\Tenancy\TenantSampleDataService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SeedTenantSampleDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $companyId,
        public string $posMode = 'general',
        public ?string $adminId = null
    ) {}

    public function handle(TenantSampleDataService $seeder): void
    {
        $company = Company::withoutGlobalScopes()->find($this->companyId);
        if (! $company) {
            Log::warning("SeedTenantSampleDataJob: Company [{$this->companyId}] not found.");
            return;
        }

        $admin = $this->adminId ? User::withoutGlobalScopes()->find($this->adminId) : null;

        $seeder->seed($company, $this->posMode, $admin);

        Log::info("SeedTenantSampleDataJob: Successfully seeded sample data for company [{$company->id}] with mode [{$this->posMode}].");
    }
}
