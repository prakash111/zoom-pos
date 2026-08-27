<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait ActsAsTenantUser
{
    /** @return array{0: Company, 1: User} */
    protected function actingAsTenantAdmin(?Company $company = null): array
    {
        $company ??= Company::create(['name' => 'Acme Inc', 'status' => 'active']);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Jane Admin',
            'login' => 'jane',
            'email' => 'jane@acme.test',
            'password' => Hash::make('secret1234'),
            'role' => 'administrator',
            'status' => 'approved',
        ]);

        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        return [$company, $user];
    }

    /** @return array{0: Company, 1: User} */
    protected function actingAsTenantStaff(?Company $company = null): array
    {
        $company ??= Company::create(['name' => 'Acme Inc', 'status' => 'active']);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Sam Staff',
            'login' => 'sam',
            'email' => 'sam@acme.test',
            'password' => Hash::make('secret1234'),
            'role' => 'operador',
            'status' => 'approved',
        ]);

        $this->actingAs($user, 'web');
        app()->instance('tenant.company_id', $company->id);

        return [$company, $user];
    }
}
