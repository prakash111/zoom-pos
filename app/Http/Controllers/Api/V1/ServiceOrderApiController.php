<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\CashRegisterTransaction;
use App\Models\Company;
use App\Models\Customer;
use App\Models\NotificationReminder;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Reminder;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\TaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

    public function partsIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $query = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->spareParts();

        if ($search = $request->query('search')) {
            $t = "%{$search}%";
            $query->where(function ($q) use ($t) {
                $q->where('name', 'like', $t)
                    ->orWhere('code', 'like', $t)
                    ->orWhere('sku', 'like', $t)
                    ->orWhere('barcode', 'like', $t);
            });
        }

        $parts = $query->orderBy('name')->limit(100)->get()->map(function (Product $p) {
            return [
                'id' => (string) ($p->external_id ?: $p->id),
                'server_id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku ?: $p->code ?: '',
                'sale_price' => (float) ($p->sale_price ?? 0),
                'cost_price' => (float) ($p->cost_price ?? 0),
                'current_stock' => (float) ($p->current_stock ?? 0),
                'unit' => $p->unit ?? 'pcs',
                'category_id' => $p->category_id ? (string) $p->category_id : null,
                'category_name' => $p->category_name ?? $p->category?->name ?? 'Spare Parts',
                'image_url' => $p->getImageUrlOrDefault(),
            ];
        });

        return response()->json(['success' => true, 'parts' => $parts]);
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
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(ServiceOrder::STATUSES))],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'warranty_period' => ['nullable', 'string', 'max:100'],
            'warranty_terms' => ['nullable', 'string'],
            'technician_id' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'extra_attributes' => ['nullable'],
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

        $customerId = isset($data['customer_id']) && $data['customer_id'] !== '' ? (string) $data['customer_id'] : null;
        $technicianId = isset($data['technician_id']) && $data['technician_id'] !== '' ? (string) $data['technician_id'] : null;

        $extraAttributes = $data['extra_attributes'] ?? $request->input('extra_attributes') ?? [];
        if (is_string($extraAttributes)) {
            $extraAttributes = json_decode($extraAttributes, true) ?: [];
        }
        if (! is_array($extraAttributes)) {
            $extraAttributes = [];
        }

        // Universal Tax Engine Binding
        $taxLines = [];
        foreach ($normalizedParts as $part) {
            $taxLines[] = [
                'product_id' => $part['product_id'] ?? null,
                'name' => $part['name'] ?? 'Part',
                'price' => (float) $part['unit_price'],
                'quantity' => (float) $part['quantity'],
            ];
        }
        if ($laborCost > 0) {
            $taxLines[] = [
                'product_id' => null,
                'name' => 'Labor / Technical Service Charges',
                'price' => $laborCost,
                'quantity' => 1,
            ];
        }

        $customerModel = null;
        if ($customerId) {
            $customerModel = Customer::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where(fn ($q) => $q->where('id', $customerId)->orWhere('external_id', $customerId))
                ->first();
        }

        $taxTotals = TaxService::calculate($taxLines, $company, $customerModel, $discount);
        $taxAmount = round((float) ($taxTotals['tax_amount'] ?? 0), 2);
        $effectiveRate = 0.0;
        $isInclusive = false;
        if (! empty($taxTotals['tax_summary_table'])) {
            $firstTaxSummary = reset($taxTotals['tax_summary_table']);
            $effectiveRate = (float) ($firstTaxSummary['rate'] ?? 0);
            $isInclusive = (bool) ($firstTaxSummary['is_inclusive'] ?? false);
        }

        if ($isInclusive) {
            $totalAmount = max(0, round($partsTotal + $laborCost - $discount, 2));
        } else {
            $totalAmount = max(0, round($partsTotal + $laborCost + $taxAmount - $discount, 2));
        }

        $payload = [
            'customer_id' => $customerId,
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
            'tax_amount' => $taxAmount,
            'tax_rate' => $effectiveRate,
            'is_tax_inclusive' => $isInclusive,
            'tax_breakdown' => $taxTotals['tax_summary_table'] ?? [],
            'extra_attributes' => $extraAttributes,
            'total_amount' => $totalAmount,
            'status' => $data['status'] ?? ServiceOrder::STATUS_RECEIVED,
            'priority' => $data['priority'] ?? 'normal',
            'warranty_period' => $data['warranty_period'] ?? '90 days',
            'warranty_terms' => $data['warranty_terms'] ?? null,
            'technician_id' => $technicianId,
            'notes' => $data['notes'] ?? null,
        ];

        $effectiveStatus = $data['status'] ?? ServiceOrder::STATUS_RECEIVED;
        $completesNow = in_array($effectiveStatus, [ServiceOrder::STATUS_READY_FOR_PICKUP, ServiceOrder::STATUS_DELIVERED_SETTLED], true);
        $deliversNow = $effectiveStatus === ServiceOrder::STATUS_DELIVERED_SETTLED;

        if ($id !== null) {
            $order = $this->findOrder($company, $id);
            if (! $order) {
                return response()->json(['success' => false, 'error' => 'Service order not found.'], 404);
            }

            $payload['completed_at'] = $completesNow ? ($order->completed_at ?: now()) : null;
            $payload['delivered_at'] = $deliversNow ? ($order->delivered_at ?: now()) : null;
            $order->update($payload);

            AuditLog::record('service_order.updated', $company->id, $user?->id, ['order_id' => $order->id]);

            $respData = [
                'success' => true,
                'message' => 'Service order updated.',
                'service_order' => $this->present($order->fresh()),
            ];

            if ($deliversNow) {
                $settlement = $this->handleDeliverySettlement($order->fresh(), $company, $user);
                $respData['sale'] = $settlement['sale'];
                $respData['post_sale_sheet'] = $settlement['post_sale_sheet'];
                $respData['service_order'] = $this->present($order->fresh());
            }

            return response()->json($respData);
        }

        $payload['company_id'] = $company->id;
        $payload['order_number'] = ServiceOrder::generateOrderNumber($company->id);
        $payload['received_at'] = now();
        $payload['completed_at'] = $completesNow ? now() : null;
        $payload['delivered_at'] = $deliversNow ? now() : null;
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

        $respData = [
            'success' => true,
            'message' => 'Service order created.',
            'service_order' => $this->present($order),
        ];

        if ($deliversNow) {
            $settlement = $this->handleDeliverySettlement($order->fresh(), $company, $user);
            $respData['sale'] = $settlement['sale'];
            $respData['post_sale_sheet'] = $settlement['post_sale_sheet'];
            $respData['show_post_sale_sheet'] = true;
            $respData['service_order'] = $this->present($order->fresh());
        }

        return response()->json($respData, 201);
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
        $deliversNow = $newStatus === ServiceOrder::STATUS_DELIVERED_SETTLED;
        $order->update([
            'status' => $newStatus,
            'completed_at' => in_array($newStatus, [ServiceOrder::STATUS_READY_FOR_PICKUP, ServiceOrder::STATUS_DELIVERED_SETTLED], true) ? ($order->completed_at ?: now()) : null,
            'delivered_at' => $deliversNow ? ($order->delivered_at ?: now()) : null,
        ]);

        AuditLog::record('service_order.status_changed', $company->id, $user?->id, ['order_id' => $order->id, 'new_status' => $newStatus]);

        $respData = [
            'success' => true,
            'message' => 'Status updated.',
            'service_order' => $this->present($order->fresh()),
        ];

        if ($deliversNow) {
            $settlement = $this->handleDeliverySettlement($order->fresh(), $company, $user);
            $respData['sale'] = $settlement['sale'];
            $respData['post_sale_sheet'] = $settlement['post_sale_sheet'];
            $respData['show_post_sale_sheet'] = true;
            $respData['service_order'] = $this->present($order->fresh());
        }

        return response()->json($respData);
    }

    private function handleDeliverySettlement(ServiceOrder $order, Company $company, ?User $user): array
    {
        $sale = null;
        if ($order->sale_id) {
            $sale = Sale::withoutGlobalScope('company')->where('company_id', $company->id)->find($order->sale_id);
        }

        if (! $sale) {
            $prefix = $company->invoice_prefix ?: 'INV-';
            $saleCount = Sale::withoutGlobalScope('company')->where('company_id', $company->id)->count() + 1;
            $saleNumber = $prefix.sprintf('%04d', $saleCount);

            $saleLineItems = [];
            foreach ($order->parts_used ?? [] as $part) {
                $qty = (float) ($part['quantity'] ?? 1);
                $unitPrice = (float) ($part['unit_price'] ?? 0);
                $saleLineItems[] = [
                    'product_id' => $part['product_id'] ?? null,
                    'name' => $part['name'] ?? 'Spare Part',
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'price' => $unitPrice,
                    'total' => round($qty * $unitPrice, 2),
                    'is_service' => false,
                ];
            }
            if ((float) $order->labor_cost > 0) {
                $saleLineItems[] = [
                    'product_id' => null,
                    'name' => 'Labor / Technical Service Charges',
                    'quantity' => 1,
                    'unit_price' => (float) $order->labor_cost,
                    'price' => (float) $order->labor_cost,
                    'total' => (float) $order->labor_cost,
                    'is_service' => true,
                ];
            }

            $customerId = null;
            if ($order->customer_record) {
                $customerId = $order->customer_record->id;
            } elseif ($order->customer_id && is_numeric($order->customer_id)) {
                $customerId = (int) $order->customer_id;
            }

            $cashRegister = CashRegister::openFor($company->id);

            $sale = Sale::create([
                'company_id' => $company->id,
                'sale_number' => $saleNumber,
                'customer_id' => $customerId,
                'customer_name' => $order->customer_name ?: 'Walk-in Customer',
                'customer_phone' => $order->customer_phone,
                'customer_email' => $order->customer_email,
                'user_id' => $user?->id,
                'cash_register_id' => $cashRegister?->id,
                'total' => (float) $order->total_amount,
                'discount' => (float) $order->discount,
                'net_amount' => (float) $order->total_amount,
                'paid_amount' => (float) $order->total_amount,
                'due_amount' => 0.00,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'status' => 'completed',
                'operation_type' => 'service_settlement',
                'items' => $saleLineItems,
                'tax_amount' => (float) $order->tax_amount,
                'tax_name' => $company->tax_id_label ?: 'Tax',
                'tax_breakdown' => $order->tax_breakdown ?? [],
                'notes' => "Settled Service Order #{$order->order_number} ({$order->equipment_name})",
            ]);

            OrderPayment::create([
                'company_id' => $company->id,
                'sale_id' => $sale->id,
                'cash_register_id' => $cashRegister?->id,
                'payment_method' => 'cash',
                'amount' => (float) $order->total_amount,
                'tendered' => (float) $order->total_amount,
                'change_returned' => 0.00,
                'net_amount' => (float) $order->total_amount,
                'notes' => "Payment for Service Order #{$order->order_number}",
            ]);

            if ($cashRegister && (float) $order->total_amount > 0) {
                $prevBalance = (float) $cashRegister->current_balance;
                $newBalance = $prevBalance + (float) $order->total_amount;
                CashRegisterTransaction::create([
                    'company_id' => $company->id,
                    'cash_register_id' => $cashRegister->id,
                    'voucher_number' => 'CR-TX-'.strtoupper(Str::random(6)),
                    'type' => 'cash_in',
                    'category' => 'service_settlement',
                    'amount' => (float) $order->total_amount,
                    'balance_before' => $prevBalance,
                    'balance_after' => $newBalance,
                    'reason' => "Service Order #{$order->order_number} settlement",
                    'created_by' => $user?->id,
                ]);
            }

            $order->update(['sale_id' => $sale->id]);

            // Automatic 30-day follow-up reminder
            NotificationReminder::create([
                'company_id' => $company->id,
                'module_type' => 'repair',
                'event_type' => 'service_follow_up',
                'reference_type' => ServiceOrder::class,
                'reference_id' => $order->id,
                'customer_id' => $customerId,
                'customer_name' => $order->customer_name,
                'recipient' => $order->customer_phone ?: $order->customer_email ?: '',
                'channels' => ['whatsapp', 'sms', 'email'],
                'message' => "Hi {$order->customer_name}, how is your {$order->equipment_name} performing after service on #{$order->order_number}? Contact us if you need any assistance under warranty.",
                'payload' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'equipment_name' => $order->equipment_name,
                    'warranty_period' => $order->warranty_period,
                ],
                'scheduled_at' => now()->addDays(30),
                'status' => NotificationReminder::STATUS_SCHEDULED,
            ]);

            Reminder::create([
                'company_id' => $company->id,
                'customer_id' => $customerId,
                'type' => 'warranty_follow_up',
                'title' => "Follow-up / Warranty Check for #{$order->order_number}",
                'notes' => "30-day warranty follow-up check for {$order->customer_name} ({$order->equipment_name}).",
                'due_date' => now()->addDays(30),
                'status' => Reminder::STATUS_PENDING,
                'remindable_type' => ServiceOrder::class,
                'remindable_id' => $order->id,
            ]);
        }

        $receiptLines = [];
        foreach ($sale->items ?? [] as $line) {
            $receiptLines[] = [
                'name' => $line['name'] ?? 'Item',
                'quantity' => (float) ($line['quantity'] ?? 1),
                'price' => (float) ($line['price'] ?? $line['unit_price'] ?? 0),
                'unit_price' => (float) ($line['unit_price'] ?? $line['price'] ?? 0),
                'total' => (float) ($line['total'] ?? 0),
            ];
        }

        $postSaleSheet = [
            'action' => 'show_post_sale_sheet',
            'data' => [
                'documentType' => 'invoice',
                'documentId' => (string) $sale->id,
                'documentNumber' => $sale->sale_number,
                'companyName' => $company->name,
                'customerName' => $order->customer_name,
                'customerPhone' => $order->customer_phone,
                'customerEmail' => $order->customer_email,
                'lines' => $receiptLines,
                'subtotal' => (float) ($order->parts_total + $order->labor_cost),
                'discount' => (float) $order->discount,
                'tax' => (float) $order->tax_amount,
                'taxRate' => (float) $order->tax_rate,
                'total' => (float) $order->total_amount,
                'paidAmount' => (float) $order->total_amount,
                'dueAmount' => 0.0,
                'currencySymbol' => $company->currency_symbol ?: '$',
                'pdfPathOverride' => "/api/tenant/invoices/{$sale->id}/pdf-stream",
            ],
        ];

        return [
            'sale' => $sale,
            'sale_id' => $sale->id,
            'invoice_number' => $sale->sale_number,
            'post_sale_sheet' => $postSaleSheet,
        ];
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
            'tax_amount' => (float) ($o->tax_amount ?? 0),
            'tax_rate' => (float) ($o->tax_rate ?? 0),
            'is_tax_inclusive' => (bool) ($o->is_tax_inclusive ?? false),
            'tax_breakdown' => $o->tax_breakdown ?? [],
            'extra_attributes' => $o->extra_attributes ?? (object) [],
            'total_amount' => (float) $o->total_amount,
            'sale_id' => $o->sale_id,
            'invoice_number' => $o->sale?->sale_number,
            'show_post_sale_sheet' => $o->status === ServiceOrder::STATUS_DELIVERED_SETTLED,
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
