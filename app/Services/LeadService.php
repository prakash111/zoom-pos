<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Modules\leadmanagement\Models\Lead;
use Modules\leadmanagement\Services\LeadService as ModularLeadService;

class LeadService extends ModularLeadService
{
    public function getCreateLeadSchema(Company $company, ?Lead $existingLead = null): array
    {
        abort_unless($company->hasModule('leadmanagement'), 403,
            'Lead Management is not activated for this store by Super Admin.');

        return parent::getCreateLeadSchema($company, $existingLead);
    }

    public function getTabbedLeadManagementSchema(Company $company, ?User $user = null, ?string $activeTab = null): array
    {
        abort_unless($company->hasModule('leadmanagement'), 403,
            'Lead Management is not activated for this store by Super Admin.');

        return parent::getTabbedLeadManagementSchema($company, $user, $activeTab);
    }
}
