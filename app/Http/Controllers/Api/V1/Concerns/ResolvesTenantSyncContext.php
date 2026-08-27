<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Shared tenant-resolution helpers for the desktop/POS sync API controllers.
 * Extracted from PosSyncApiController so PosDesktopSyncController can reuse
 * the exact same tenant/user resolution without duplicating it.
 */
trait ResolvesTenantSyncContext
{
    /**
     * Resolve the active tenant Company from the request attributes or service container.
     */
    protected function resolveCompany(Request $request): Company
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : $request->attributes->get('company_id');

        if (! $companyId && Auth::check()) {
            $companyId = Auth::user()->company_id;
        }

        if (! $companyId) {
            abort(response()->json([
                'success' => false,
                'error' => 'Tenant company context could not be resolved.',
            ], 401));
        }

        return Company::findOrFail($companyId);
    }

    /**
     * Resolve the active User or fallback to company owner/admin.
     */
    protected function resolveUser(Request $request, Company $company): ?User
    {
        if (Auth::check()) {
            return Auth::user();
        }

        return User::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->first();
    }
}
