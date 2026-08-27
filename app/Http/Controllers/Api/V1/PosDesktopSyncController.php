<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Services\Sync\DesktopSyncEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sync endpoints for the tenant modules DesktopSyncEngine covers: cash
 * register, sales targets, consignments, service orders, and payables.
 * Sibling to PosSyncApiController, which keeps owning products/categories/
 * customers/suppliers/sales/quotations with its existing hand-tuned wire
 * format — this controller only handles the modules that format never
 * touched.
 */
class PosDesktopSyncController extends Controller
{
    use ResolvesTenantSyncContext;

    public function __construct(protected DesktopSyncEngine $syncEngine)
    {
    }

    /**
     * GET /api/v1/pos/desktop-sync/pull?since=<ISO8601>
     */
    public function pull(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $since = null;
        if ($request->filled('since')) {
            try {
                $since = Carbon::parse($request->query('since'));
            } catch (\Throwable) {
                $since = null;
            }
        }

        $data = $this->syncEngine->pull($company, $since);

        return response()->json([
            'success' => true,
            'server_time' => now()->toIso8601String(),
            'counts' => array_map('count', $data),
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/pos/desktop-sync/push
     * Body shape: { "cash_registers": [...], "consignments": [...], ... }
     * — any subset of the registered table keys, only what changed locally.
     */
    public function push(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $payload = $request->all();

        if (empty($payload) || ! is_array($payload)) {
            return response()->json([
                'success' => false,
                'error' => 'Empty sync push payload.',
            ], 422);
        }

        try {
            $synced = $this->syncEngine->push($company, $user, $payload);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error' => 'Desktop sync push failed: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'server_time' => now()->toIso8601String(),
            'synced' => $synced,
        ]);
    }
}
