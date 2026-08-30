<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Product;
use App\Models\ServiceOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Mirrors app/Livewire/Tenant/ServiceOrders/Index.php's save/status/delete
 * logic — parts stock deduction on create, completed_at/delivered_at
 * timestamp rules on status change.
 */
class ServiceOrderApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $query = ServiceOrder::withoutGlobalScope('company')->where('company_id', $company->id);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }
        if ($technicianId = $request->query('technician_id')) {
            $query->where('technician_id', $technicianId);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $t = "%{$search}%";
                $q->where('order_number', 'like', $t)
                    ->orWhere('customer_name', 'like', $t)
                    ->orWhere('equipment_name', 'like', $t)
                    ->orWhere('serial_number', 'like', $t);
            });
        }

        $orders = $query->orderByDesc('created_at')->get()->map(fn (ServiceOrder $o) => $this->present($o));

        $counts = ['all' => ServiceOrder::withoutGlobalScope('company')->where('company_id', $company->id)->count()];
        foreach (array_keys(ServiceOrder::STATUSES) as $key) {
            $counts[$key] = ServiceOrder::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', $key)->count();
        }

        return response()->json(['success' => true, 'counts' => $counts, 'service_orders' => $orders]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $order = $this->findOrder($company, $id);

        if (! $order) {
            return response()->json(['success' => false, 'error' => 'Service order not found.'], 404);
        }

        return response()->json(['success' => true, 'service_order' => $this->present($order)]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        return $this->save($request, $id);
    }

    private function save(Request $request, ?string $id = null): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'customer_id' => ['nullable'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email'],
            'equipment_name' => ['required', 'string', 'max:255'],
            'brand_model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'reported_defect' => ['required', 'string'],
            'technical_diagnosis' => ['nullable', 'string'],
            'parts_used' => ['nullable', 'array'],
            'parts_used.*.product_id' => ['nullable'],
            'parts_used.*.name' => ['nullable', 'string'],
            'parts_used.*.quantity' => ['nullable', 'numeric', 'min:0.1'],
            'parts_used.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(ServiceOrder::STATUSES))],
            'priority' => ['required', 'string', 'in:low,normal,high,urgent'],
            'warranty_period' => ['nullable', 'string', 'max:100'],
            'warranty_terms' => ['nullable', 'string'],
            'technician_id' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $parts = $data['parts_used'] ?? [];
        $partsTotal = 0.0;
        $normalizedParts = [];
        foreach ($parts as $part) {
            $qty = max(0.1, (float) ($part['quantity'] ?? 1));
            $price = max(0, (float) ($part['unit_price'] ?? 0));
            $total = round($qty * $price, 2);
            $partsTotal += $total;
            $normalizedParts[] = [
                'product_id' => $part['product_id'] ?? null,
                'name' => $part['name'] ?? 'Part',
                'quantity' => $qty,
                'unit_price' => $price,
                'total' => $total,
            ];
        }
        $partsTotal = round($partsTotal, 2);
        $laborCost = (float) ($data['labor_cost'] ?? 0);
        $discount = (float) ($data['discount'] ?? 0);
        $totalAmount = max(0, round($partsTotal + $laborCost - $discount, 2));

        $payload = [
            'customer_id' => $data['customer_id'] ?? null,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'equipment_name' => $data['equipment_name'],
            'brand_model' => $data['brand_model'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'reported_defect' => $data['reported_defect'],
            'technical_diagnosis' => $data['technical_diagnosis'] ?? null,
            'parts_used' => $normalizedParts,
            'parts_total' => $partsTotal,
            'labor_cost' => $laborCost,
            'discount' => $discount,
            'total_amount' => $totalAmount,
            'status' => $data['status'],
            'priority' => $data['priority'],
            'warranty_period' => $data['warranty_period'] ?? '90 days',
            'warranty_terms' => $data['warranty_terms'] ?? null,
            'technician_id' => $data['technician_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        $completesNow = in_array($data['status'], [ServiceOrder::STATUS_READY_FOR_PICKUP, ServiceOrder::STATUS_DELIVERED_SETTLED], true);
        $deliversNow = $data['status'] === ServiceOrder::STATUS_DELIVERED_SETTLED;

        if ($id !== null) {
            $order = $this->findOrder($company, $id);
            if (! $order) {
                return response()->json(['success' => false, 'error' => 'Service order not found.'], 404);
            }

            $payload['completed_at'] = $completesNow ? ($order->completed_at ?: now()) : null;
            $payload['delivered_at'] = $deliversNow ? ($order->delivered_at ?: now()) : null;
            $order->update($payload);

            AuditLog::record('service_order.updated', $company->id, $user?->id, ['order_id' => $order->id]);

            return response()->json(['success' => true, 'message' => 'Service order updated.', 'service_order' => $this->present($order->fresh())]);
        }

        $payload['company_id'] = $company->id;
        $payload['order_number'] = ServiceOrder::generateOrderNumber($company->id);
        $payload['received_at'] = now();
        $order = ServiceOrder::create($payload);

        foreach ($normalizedParts as $part) {
            if (! empty($part['product_id'])) {
                $product = Product::withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where(fn ($q) => $q->where('id', $part['product_id'])->orWhere('external_id', (string) $part['product_id']))
                    ->first();
                $product?->decrementStock((float) $part['quantity'], "Parts used for Service Order #{$order->order_number}");
            }
        }

        AuditLog::record('service_order.created', $company->id, $user?->id, ['order_id' => $order->id, 'order_number' => $order->order_number]);

        return response()->json([
            'success' => true,
            'message' => 'Service order created.',
            'service_order' => $this->present($order),
        ], 201);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $order = $this->findOrder($company, $id);

        if (! $order) {
            return response()->json(['success' => false, 'error' => 'Service order not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(ServiceOrder::STATUSES))],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $newStatus = $request->input('status');
        $order->update([
            'status' => $newStatus,
            'completed_at' => in_array($newStatus, [ServiceOrder::STATUS_READY_FOR_PICKUP, ServiceOrder::STATUS_DELIVERED_SETTLED], true) ? ($order->completed_at ?: now()) : null,
            'delivered_at' => $newStatus === ServiceOrder::STATUS_DELIVERED_SETTLED ? ($order->delivered_at ?: now()) : null,
        ]);

        AuditLog::record('service_order.status_changed', $company->id, $user?->id, ['order_id' => $order->id, 'new_status' => $newStatus]);

        return response()->json(['success' => true, 'message' => 'Status updated.', 'service_order' => $this->present($order->fresh())]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $order = $this->findOrder($company, $id);

        if (! $order) {
            return response()->json(['success' => false, 'error' => 'Service order not found.'], 404);
        }

        $order->delete();
        AuditLog::record('service_order.deleted', $company->id, $user?->id, ['order_id' => $id]);

        return response()->json(['success' => true, 'message' => 'Service order deleted.']);
    }

    private function findOrder(Company $company, string $id): ?ServiceOrder
    {
        return ServiceOrder::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(fn ($q) => $q->where('id', $id)->orWhere('external_id', $id))
            ->first();
    }

    private function present(ServiceOrder $o): array
    {
        return [
            'id' => (string) ($o->external_id ?: $o->id),
            'server_id' => $o->id,
            'order_number' => $o->order_number,
            'customer_id' => $o->customer_id ? (string) $o->customer_id : null,
            'customer_name' => $o->customer_name,
            'customer_phone' => $o->customer_phone,
            'customer_email' => $o->customer_email,
            'equipment_name' => $o->equipment_name,
            'brand_model' => $o->brand_model,
            'serial_number' => $o->serial_number,
            'reported_defect' => $o->reported_defect,
            'technical_diagnosis' => $o->technical_diagnosis,
            'parts_used' => $o->parts_used ?? [],
            'parts_total' => (float) $o->parts_total,
            'labor_cost' => (float) $o->labor_cost,
            'discount' => (float) $o->discount,
            'total_amount' => (float) $o->total_amount,
            'status' => $o->status,
            'status_label' => $o->getStatusInfo()['label'] ?? $o->status,
            'priority' => $o->priority,
            'warranty_period' => $o->warranty_period,
            'warranty_terms' => $o->warranty_terms,
            'technician_id' => $o->technician_id ? (string) $o->technician_id : null,
            'notes' => $o->notes,
            'received_at' => $o->received_at?->toIso8601String(),
            'completed_at' => $o->completed_at?->toIso8601String(),
            'delivered_at' => $o->delivered_at?->toIso8601String(),
            'updated_at' => $o->updated_at?->toIso8601String(),
        ];
    }
}
