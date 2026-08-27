<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

/**
 * Mirrors the legacy api/subscription.php action dispatch at the same URL
 * (/api/subscription.php). Only the public plan catalog is implemented in
 * Milestone 1; checkout/activation-code/payment-gateway actions land in
 * Milestone 4.
 */
class SubscriptionSyncController extends Controller
{
    public function dispatch(Request $request)
    {
        $action = $request->input('action', 'listar_planos');

        return match ($action) {
            'listar_planos' => $this->listPlans(),
            default => response()->json([
                'success' => false,
                'error' => "Not yet implemented in Laravel rebuild: {$action}",
            ], 501),
        };
    }

    protected function listPlans()
    {
        $plans = Plan::query()->where('active', true)->get([
            'name', 'display_name', 'billing_cycle', 'duration_days', 'price', 'currency', 'features', 'limits',
        ]);

        return response()->json(['success' => true, 'plans' => $plans]);
    }
}
