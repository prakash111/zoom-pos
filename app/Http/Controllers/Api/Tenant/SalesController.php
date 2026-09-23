<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Company;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * List sales with due date filtering, custom date range, and search.
     * GET /api/v1/tenant/sales
     * GET /tenant/sales
     * GET /v1/tenant/sales
     * GET /sales
     * GET /api/v1/pos/sales
     */
    public function index(Request $request): JsonResponse
    {
        if (method_exists($this, 'authorize')) {
            try {
                $this->authorize('viewAny', Sale::class);
            } catch (\Throwable $e) {
                $user = auth('sanctum')->user() ?? auth('web')->user() ?? $request->user();
                if ($user && method_exists($user, 'hasPermission') && ! $user->isPrivilegedRole()) {
                    if (! $user->hasPermission('sales.view')) {
                        abort(403, 'Unauthorized access to sales.');
                    }
                }
            }
        }

        $user = auth('sanctum')->user() ?? auth('web')->user() ?? $request->user();

        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable $e) {
            $companyId = $user?->company_id ?? $user?->tenant_id ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null);
            if ($companyId) {
                $company = Company::find($companyId);
            }
        }

        $storeId = $request->get('store_id', $user?->current_store_id ?? $request->header('X-Store-Id'));
        $filter = $request->get('filter', 'all');
        $search = $request->get('query');
        $today = now()->toDateString();

        $query = Sale::query();

        if ($company) {
            $query->where('company_id', $company->id);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $query->where('status', '!=', 'cancelled');

        $query->when($search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('sale_number', 'like', "%{$search}%")
                    ->orWhere('tracking_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhere('items', 'like', "%{$search}%");
            });
        });

        // Apply Due Date Filters
        switch ($filter) {
            case 'overdue':
                // 1. Fetch ALL unpaid or partially paid sales/invoices
                $query->where(function ($q) {
                    $q->whereIn('payment_status', ['unpaid', 'partial', 'pending'])
                        ->orWhere('due_amount', '>', 0);
                });

                // 2. Strict prioritization: Overdue first, Due Today second, Future Dues third
                $query->orderByRaw("
                    CASE 
                        WHEN due_date IS NOT NULL AND due_date < '{$today}' THEN 1
                        WHEN due_date IS NOT NULL AND due_date = '{$today}' THEN 2
                        ELSE 3
                    END ASC
                ")
                // 3. Within past-due invoices, sort by oldest due date (highest urgency)
                ->orderBy('due_date', 'asc')
                // 4. Secondary sort by highest unpaid balance
                ->orderByDesc('due_amount');
                break;

            case 'due_today':
                $query->where('payment_status', '!=', 'paid')
                    ->whereDate('due_date', '=', $today)
                    ->orderByDesc('total_amount');
                break;

            case 'due_7_days':
                $query->where('payment_status', '!=', 'paid')
                    ->whereBetween('due_date', [$today, now()->addDays(7)->toDateString()])
                    ->orderBy('due_date', 'asc');
                break;

            case 'due_15_days':
                $query->where('payment_status', '!=', 'paid')
                    ->whereBetween('due_date', [$today, now()->addDays(15)->toDateString()])
                    ->orderBy('due_date', 'asc');
                break;

            case 'custom_date':
                if ($request->filled('start_date') && $request->filled('end_date')) {
                    $query->whereBetween('created_at', [
                        Carbon::parse($request->start_date)->startOfDay(),
                        Carbon::parse($request->end_date)->endOfDay(),
                    ]);
                }
                $query->latest();
                break;

            default:
                $query->latest();
                break;
        }

        $sales = $query->with('customer')->paginate((int) $request->get('per_page', 20));

        $collection = SaleResource::collection($sales);

        return response()->json([
            'success'      => true,
            'data'         => $collection,
            'sales'        => $collection,
            'meta'         => [
                'total'        => $sales->total(),
                'current_page' => $sales->currentPage(),
                'last_page'    => $sales->lastPage(),
                'per_page'     => $sales->perPage(),
            ],
            'total'        => $sales->total(),
            'current_page' => $sales->currentPage(),
            'last_page'    => $sales->lastPage(),
        ]);
    }
}
