<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Services\Tenancy\TenantSampleDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantDemoDataController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Purges only records flagged with is_demo = true for the current tenant.
     * DELETE /api/tenant/demo-data
     * DELETE /api/v1/pos/demo-data
     */
    public function destroy(Request $request, TenantSampleDataService $seeder): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $counts = $seeder->purgeDemoData($company);

        return response()->json([
            'success' => true,
            'message' => 'Sample demo data cleared successfully.',
            'purged' => $counts,
        ]);
    }
}
