<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Customer;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Mirrors app/Livewire/Tenant/Consignments/{Create,Index,Show}.php's exact
 * lifecycle (draft → dispatched → reconciled → finalized) and math, so
 * mobile consignments produce the same sales/stock effects as the web page.
 */
class ConsignmentApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $query = Consignment::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['items']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('consignment_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $consignments = $query->orderByDesc('created_at')->get()->map(fn (Consignment $c) => $this->present($c));

        return response()->json([
            'success' => true,
            'stats' => [
                'total' => Consignment::withoutGlobalScope('company')->where('company_id', $company->id)->count(),
                'dispatched' => Consignment::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', 'dispatched')->count(),
                'reconciled' => Consignment::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', 'reconciled')->count(),
                'finalized' => Consignment::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', 'finalized')->count(),
            ],
            'consignments' => $consignments,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $consignment = $this->findConsignment($company, $id);

        if (! $consignment) {
            return response()->json(['success' => false, 'error' => 'Consignment not found.'], 404);
        }

        return response()->json(['success' => true, 'consignment' => $this->present($consignment, withItems: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'customer_id' => ['required'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'in:draft,dispatched'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $customer = Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(fn ($q) => $q->where('id', $data['customer_id'])->orWhere('external_id', (string) $data['customer_id']))
            ->first();

        if (! $customer) {
            return response()->json(['success' => false, 'error' => 'Customer not found.'], 404);
        }

        $status = $data['status'] ?? 'draft';

        $consignment = DB::transaction(function () use ($company, $user, $customer, $status, $data) {
            $c = Consignment::create([
                'company_id' => $company->id,
                'consignment_number' => 'CSG-'.now()->format('YmdHis'),
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'user_id' => $user?->id,
                'status' => $status,
                'dispatched_at' => $status === 'dispatched' ? now() : null,
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $product = Product::withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where(fn ($q) => $q->where('id', $item['product_id'])->orWhere('external_id', (string) $item['product_id']))
                    ->first();

                ConsignmentItem::create([
                    'consignment_id' => $c->id,
                    'product_id' => $product?->id,
                    'product_name' => $product?->name ?? 'Item',
                    'dispatched_quantity' => (float) $item['quantity'],
                    'returned_quantity' => 0,
                    'sold_quantity' => 0,
                    'unit_price' => (float) $item['unit_price'],
                    'sold_total' => 0,
                ]);
            }

            $c->recalculateTotals();

            return $c->fresh(['items']);
        });

        AuditLog::record('consignment.created', $company->id, $user?->id, [
            'consignment_id' => $consignment->id,
            'consignment_number' => $consignment->consignment_number,
            'status' => $status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Consignment created.',
            'consignment' => $this->present($consignment, withItems: true),
        ], 201);
    }

    public function dispatch(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $consignment = $this->findConsignment($company, $id);

        if (! $consignment) {
            return response()->json(['success' => false, 'error' => 'Consignment not found.'], 404);
        }
        if ($consignment->status !== 'draft') {
            return response()->json(['success' => false, 'error' => 'Only a draft consignment can be dispatched.'], 422);
        }

        $consignment->update(['status' => 'dispatched', 'dispatched_at' => now()]);
        AuditLog::record('consignment.dispatched', $company->id, $user?->id, ['consignment_id' => $consignment->id]);

        return response()->json(['success' => true, 'message' => 'Consignment dispatched.', 'consignment' => $this->present($consignment->fresh(['items']), withItems: true)]);
    }

    /**
     * Body: items[] of {id, returned_qty} — sold_qty is always derived as
     * dispatched_qty - returned_qty (see Show::updatedItems), never sent by
     * the client.
     */
    public function reconcile(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $consignment = $this->findConsignment($company, $id);

        if (! $consignment) {
            return response()->json(['success' => false, 'error' => 'Consignment not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required'],
            'items.*.returned_qty' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $totals = DB::transaction(function () use ($consignment, $request) {
            $soldRevenue = 0.0;
            $returnedAmount = 0.0;
            $dispatchedAmount = 0.0;

            foreach ($request->input('items') as $itemInput) {
                $item = $consignment->items()->where('id', $itemInput['id'])->first();
                if (! $item) {
                    continue;
                }

                $dispatched = (float) $item->dispatched_quantity;
                $returned = max(0, min($dispatched, (float) $itemInput['returned_qty']));
                $sold = max(0, $dispatched - $returned);
                $billedTotal = round($sold * (float) $item->unit_price, 2);

                $item->update(['returned_quantity' => $returned, 'sold_quantity' => $sold, 'sold_total' => $billedTotal]);

                $soldRevenue += $billedTotal;
                $returnedAmount += round($returned * (float) $item->unit_price, 2);
                $dispatchedAmount += round($dispatched * (float) $item->unit_price, 2);
            }

            $consignment->update([
                'status' => 'reconciled',
                'reconciled_at' => now(),
                'total_sold_amount' => round($soldRevenue, 2),
                'total_returned_amount' => round($returnedAmount, 2),
                'total_dispatched_amount' => round($dispatchedAmount, 2),
            ]);

            return $consignment->fresh(['items']);
        });

        AuditLog::record('consignment.reconciled', $company->id, $user?->id, [
            'consignment_id' => $consignment->id,
            'total_sold' => (float) $totals->total_sold_amount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reconciliation saved.',
            'consignment' => $this->present($totals, withItems: true),
        ]);
    }

    /**
     * Generates a completed, fully-paid Sale from the sold quantities and
     * deducts stock — mirrors Show::confirmAndGenerateInvoice exactly.
     */
    public function finalize(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $consignment = $this->findConsignment($company, $id);

        if (! $consignment) {
            return response()->json(['success' => false, 'error' => 'Consignment not found.'], 404);
        }

        $paymentMethod = strtolower((string) $request->input('payment_method', 'cash'));
        $soldRevenue = (float) $consignment->items->sum(fn (ConsignmentItem $i) => (float) $i->sold_quantity * (float) $i->unit_price);

        if ($soldRevenue <= 0) {
            return response()->json(['success' => false, 'error' => 'Nothing to bill — all items are marked returned.'], 422);
        }

        $sale = DB::transaction(function () use ($company, $user, $consignment, $paymentMethod, $soldRevenue) {
            $items = [];
            foreach ($consignment->items as $item) {
                if ((float) $item->sold_quantity <= 0) {
                    continue;
                }

                $items[] = [
                    'product_id' => $item->product_id,
                    'name' => $item->product_name,
                    'quantity' => (float) $item->sold_quantity,
                    'price' => (float) $item->unit_price,
                    'total' => (float) $item->sold_total,
                ];

                if ($item->product_id) {
                    Product::withoutGlobalScope('company')->find($item->product_id)
                        ?->decrement('current_stock', (float) $item->sold_quantity);
                }
            }

            $prefix = $company->invoice_prefix ?: 'INV-';
            $saleNumber = $prefix.sprintf('%04d', Sale::withoutGlobalScope('company')->where('operation_type', 'sale')->count() + 1);

            $sale = Sale::create([
                'company_id' => $company->id,
                'sale_number' => $saleNumber,
                'customer_id' => $consignment->customer_id,
                'customer_name' => $consignment->customer_name,
                'user_id' => $user?->id,
                'total' => $soldRevenue,
                'net_amount' => $soldRevenue,
                'paid_amount' => $soldRevenue,
                'due_amount' => 0,
                'discount' => 0,
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'status' => 'completed',
                'operation_type' => 'sale',
                'items' => $items,
                'notes' => "Generated from Consignment #{$consignment->consignment_number}.",
            ]);

            OrderPayment::create([
                'company_id' => $company->id,
                'sale_id' => $sale->id,
                'payment_method' => $paymentMethod,
                'amount' => $sale->total,
                'net_amount' => $sale->total,
            ]);

            $consignment->update([
                'status' => 'finalized',
                'sale_id' => $sale->id,
                'total_sold_amount' => $soldRevenue,
                'reconciled_at' => $consignment->reconciled_at ?? now(),
            ]);

            return $sale;
        });

        AuditLog::record('consignment.finalized_to_sale', $company->id, $user?->id, [
            'consignment_id' => $consignment->id,
            'sale_id' => $sale->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Consignment finalized to sale.',
            'sale' => ['id' => (string) ($sale->external_id ?: $sale->id), 'server_id' => $sale->id, 'sale_number' => $sale->sale_number, 'total' => (float) $sale->total],
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $consignment = $this->findConsignment($company, $id);

        if (! $consignment) {
            return response()->json(['success' => false, 'error' => 'Consignment not found.'], 404);
        }
        if ($consignment->status === 'finalized') {
            return response()->json(['success' => false, 'error' => 'A finalized consignment cannot be deleted.'], 422);
        }

        $consignment->items()->delete();
        $consignment->delete();
        AuditLog::record('consignment.deleted', $company->id, $user?->id, ['consignment_id' => $id]);

        return response()->json(['success' => true, 'message' => 'Consignment deleted.']);
    }

    private function findConsignment(Company $company, string $id): ?Consignment
    {
        return Consignment::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with('items')
            ->where(fn ($q) => $q->where('id', $id)->orWhere('external_id', $id))
            ->first();
    }

    private function present(Consignment $c, bool $withItems = false): array
    {
        $data = [
            'id' => (string) ($c->external_id ?: $c->id),
            'server_id' => $c->id,
            'consignment_number' => $c->consignment_number,
            'customer_id' => $c->customer_id ? (string) $c->customer_id : null,
            'customer_name' => $c->customer_name,
            'status' => $c->status,
            'dispatched_at' => $c->dispatched_at?->toIso8601String(),
            'due_date' => $c->due_date?->toIso8601String(),
            'reconciled_at' => $c->reconciled_at?->toIso8601String(),
            'total_dispatched_amount' => (float) $c->total_dispatched_amount,
            'total_sold_amount' => (float) $c->total_sold_amount,
            'total_returned_amount' => (float) $c->total_returned_amount,
            'notes' => $c->notes,
        ];

        if ($withItems) {
            $data['items'] = $c->items->map(fn (ConsignmentItem $i) => [
                'id' => $i->id,
                'product_id' => $i->product_id ? (string) $i->product_id : null,
                'product_name' => $i->product_name,
                'dispatched_quantity' => (float) $i->dispatched_quantity,
                'returned_quantity' => (float) $i->returned_quantity,
                'sold_quantity' => (float) $i->sold_quantity,
                'unit_price' => (float) $i->unit_price,
                'sold_total' => (float) $i->sold_total,
            ]);
        }

        return $data;
    }
}
