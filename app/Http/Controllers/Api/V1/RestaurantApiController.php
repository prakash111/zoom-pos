<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DiningFloor;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\OrderPayment;
use App\Models\PushNotificationSetting;
use App\Models\Sale;
use App\Services\Push\FirebasePushService;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Mobile REST surface for Restaurant Mode (floors/tables, sending orders to
 * the kitchen, the Kitchen Display System, and settling a table's bill).
 *
 * Mirrors app/Livewire/Tenant/Restaurant/Tables.php, Pos.php (sendToKitchen/
 * settleBill), and Kds.php — those Livewire components remain the source of
 * truth for the web UI; this controller re-implements their side effects as
 * JSON endpoints for the Flutter app. One deliberate deviation from Pos::
 * settleBill(): that method always creates a brand-new "completed" Sale row
 * on top of the "pending"/"unpaid" Sale that sendToKitchen() already created
 * for the same table order, leaving the original row permanently pending.
 * Here, settle() instead completes that same pending Sale in place, which
 * avoids leaving an orphaned phantom sale behind for every dine-in order.
 */
class RestaurantApiController extends Controller
{
    use ResolvesTenantSyncContext;

    private function ensureRestaurantMode(Company $company): ?JsonResponse
    {
        if (! $company->isRestaurantMode() && ! $company->hasModule('restaurant')) {
            return response()->json([
                'success' => false,
                'error' => 'Restaurant Mode is not enabled for this store.',
            ], 403);
        }

        return null;
    }

    // ---------------------------------------------------------------
    // Floors
    // ---------------------------------------------------------------

    public function floorsIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $floors = DiningFloor::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['tables' => function ($q) {
                $q->withoutGlobalScope('company')->with('currentSale');
            }])
            ->orderBy('order_index')
            ->get();

        return response()->json([
            'success' => true,
            'floors' => $floors->map(fn (DiningFloor $f) => $this->presentFloor($f))->values(),
        ]);
    }

    public function floorsStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $floor = DiningFloor::create([
            'company_id' => $company->id,
            'name' => $request->input('name'),
            'order_index' => $request->input('order_index', DiningFloor::withoutGlobalScope('company')->where('company_id', $company->id)->count() + 1),
        ]);

        return response()->json(['success' => true, 'message' => 'Floor area created.', 'floor' => $this->presentFloor($floor->fresh('tables'))], 201);
    }

    public function floorsUpdate(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $floor = $this->findFloor($company, $id);
        if (! $floor) {
            return response()->json(['success' => false, 'error' => 'Floor not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $floor->update([
            'name' => $request->input('name'),
            'order_index' => $request->input('order_index', $floor->order_index),
        ]);

        return response()->json(['success' => true, 'message' => 'Floor area updated.', 'floor' => $this->presentFloor($floor->fresh('tables'))]);
    }

    public function floorsDestroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $floor = $this->findFloor($company, $id);
        if (! $floor) {
            return response()->json(['success' => false, 'error' => 'Floor not found.'], 404);
        }

        $name = $floor->name;
        $floor->delete();

        return response()->json(['success' => true, 'message' => "Floor area {$name} removed."]);
    }

    // ---------------------------------------------------------------
    // Tables
    // ---------------------------------------------------------------

    public function tablesStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $validator = Validator::make($request->all(), [
            'table_number' => ['required', 'string', 'max:50'],
            'dining_floor_id' => ['nullable', 'string'],
            'seating_capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'in:available,occupied,reserved,billed'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $table = DiningTable::create([
            'company_id' => $company->id,
            'table_number' => $request->input('table_number'),
            'dining_floor_id' => $request->input('dining_floor_id'),
            'seating_capacity' => $request->input('seating_capacity'),
            'status' => $request->input('status', DiningTable::STATUS_AVAILABLE),
        ]);

        return response()->json(['success' => true, 'message' => 'Table added.', 'table' => $this->presentTable($table->fresh('floor', 'currentSale'))], 201);
    }

    public function tablesUpdate(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $table = $this->findTable($company, $id);
        if (! $table) {
            return response()->json(['success' => false, 'error' => 'Table not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'table_number' => ['required', 'string', 'max:50'],
            'dining_floor_id' => ['nullable', 'string'],
            'seating_capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'status' => ['required', 'in:available,occupied,reserved,billed'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $table->update($validator->validated());

        return response()->json(['success' => true, 'message' => 'Table updated.', 'table' => $this->presentTable($table->fresh('floor', 'currentSale'))]);
    }

    public function tablesSetStatus(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $table = $this->findTable($company, $id);
        if (! $table) {
            return response()->json(['success' => false, 'error' => 'Table not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:available,occupied,reserved,billed'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $table->update(['status' => $request->input('status')]);

        return response()->json(['success' => true, 'message' => "Table {$table->table_number} status updated.", 'table' => $this->presentTable($table->fresh('floor', 'currentSale'))]);
    }

    public function tablesDestroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $table = $this->findTable($company, $id);
        if (! $table) {
            return response()->json(['success' => false, 'error' => 'Table not found.'], 404);
        }

        $num = $table->table_number;
        $table->delete();

        return response()->json(['success' => true, 'message' => "Table {$num} deleted."]);
    }

    public function tableShow(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $table = $this->findTable($company, $id);
        if (! $table) {
            return response()->json(['success' => false, 'error' => 'Table not found.'], 404);
        }
        $table->load('floor', 'currentSale');

        return response()->json([
            'success' => true,
            'table' => $this->presentTable($table),
            'open_order' => $table->currentSale ? $this->presentSale($table->currentSale) : null,
        ]);
    }

    public function tableActionsSheet(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $table = $this->findTable($company, $id);
        if (! $table) {
            return response()->json(['success' => false, 'error' => 'Table not found.'], 404);
        }

        $sheet = SchemaResponse::tableActionsSheet($table, $company);

        return response()->json($sheet);
    }

    // ---------------------------------------------------------------
    // Orders: send to kitchen, settle bill
    // ---------------------------------------------------------------

    public function sendToKitchen(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'service_type' => ['required', 'in:dine_in,takeaway,delivery'],
            'table_id' => ['required_if:service_type,dine_in', 'nullable', 'string'],
            'sale_id' => ['nullable', 'string'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'pickup_time' => ['nullable', 'string', 'max:50'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'driver_phone' => ['nullable', 'string', 'max:50'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string'],
            'channel' => ['nullable', 'string'],
            'channel_id' => ['nullable', 'integer'],
            'recipient_phone' => ['nullable', 'string', 'max:50'],
            'recipient_email' => ['nullable', 'email'],
            'customer_email' => ['nullable', 'email'],
            'send_whatsapp' => ['sometimes', 'boolean'],
            'send_email' => ['sometimes', 'boolean'],
            'send_sms' => ['sometimes', 'boolean'],
            'prep_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            // Any custom alert lead time is accepted (was previously capped to
            // the 0/2/5 quick-chip presets); it's clamped to <= prep_minutes
            // below so an alert can never fire before the order was sent.
            'intimation_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.base_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.1'],
            'items.*.variant' => ['nullable', 'string', 'max:150'],
            'items.*.modifiers' => ['nullable', 'array'],
            'items.*.modifiers.*.name' => ['nullable', 'string'],
            'items.*.modifiers.*.price' => ['nullable', 'numeric'],
            'items.*.spice_level' => ['nullable', 'string', 'max:150'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'items.*.seat' => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        $table = null;
        if (! empty($data['table_id'])) {
            $table = $this->findTable($company, $data['table_id']);
            if (! $table) {
                return response()->json(['success' => false, 'error' => 'Table not found.'], 404);
            }
        }

        $items = array_map(function ($item) {
            return [
                'id' => (string) Str::uuid(),
                'product_id' => $item['product_id'] ?? null,
                'name' => $item['name'],
                'price' => (float) $item['price'],
                'base_price' => (float) ($item['base_price'] ?? $item['price']),
                'is_overridden' => (float) ($item['base_price'] ?? $item['price']) !== (float) $item['price'],
                'quantity' => (float) $item['quantity'],
                'variant' => $item['variant'] ?? null,
                'modifiers' => $item['modifiers'] ?? [],
                'spice_level' => $item['spice_level'] ?? null,
                'note' => $item['note'] ?? '',
                'seat' => $item['seat'] ?? 1,
            ];
        }, $data['items']);

        $subtotal = array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $items));
        $discount = (float) ($data['discount'] ?? 0);
        $total = max(0, round($subtotal - $discount, 2));

        $serviceType = $data['service_type'];
        $tableName = $table
            ? ($table->table_number.($table->floor ? " ({$table->floor->name})" : ''))
            : ucfirst(str_replace('_', ' ', $serviceType)).(! empty($data['customer_name']) ? " - {$data['customer_name']}" : '');

        // If sending more items to a table that already has an open pending
        // order (rather than Pos::sendToKitchen()'s behavior of always
        // minting a brand-new Sale+KOT — see class docblock), append to it.
        $existingSale = null;
        if (! empty($data['sale_id'])) {
            $existingSale = $this->findOpenSale($company, $data['sale_id']);
        } elseif ($table && $table->currentSale && $table->currentSale->status === 'pending') {
            $existingSale = $table->currentSale;
        }

        [$sale, $kot] = DB::transaction(function () use ($company, $user, $existingSale, $items, $discount, $total, $serviceType, $table, $tableName, $data) {
            if ($existingSale) {
                $mergedItems = array_merge($existingSale->items ?? [], $items);
                $mergedSubtotal = array_sum(array_map(fn ($i) => (float) $i['price'] * (float) $i['quantity'], $mergedItems));
                $mergedTotal = max(0, round($mergedSubtotal - $discount, 2));
                $existingSale->update([
                    'items' => $mergedItems,
                    'total' => $mergedTotal,
                    'discount' => $discount,
                ]);
                $sale = $existingSale;
            } else {
                $sale = Sale::create([
                    'company_id' => $company->id,
                    'sale_number' => 'ORD-'.strtoupper(Str::random(6)),
                    'customer_name' => $data['customer_name'] ?? ($table ? $table->table_number : 'Restaurant Guest'),
                    'user_id' => $user?->id,
                    'total' => $total,
                    'discount' => $discount,
                    'payment_method' => 'unpaid',
                    'status' => 'pending',
                    'operation_type' => 'sale',
                    'service_type' => $serviceType,
                    'dining_table_id' => $table?->id,
                    'table_name' => $tableName,
                    'guest_count' => $data['guest_count'] ?? ($table->guest_count ?? 1),
                    'pickup_time' => $data['pickup_time'] ?? null,
                    'delivery_address' => $data['delivery_address'] ?? null,
                    'driver_name' => $data['driver_name'] ?? null,
                    'driver_phone' => $data['driver_phone'] ?? null,
                    'dispatch_status' => $serviceType === 'delivery' ? 'pending' : null,
                    'kot_status' => 'pending',
                    'items' => $items,
                    'notes' => $data['notes'] ?? null,
                    'gst_invoice' => array_filter([
                        'customer_phone' => $data['customer_phone'] ?? null,
                        'customer_email' => $data['customer_email'] ?? null,
                    ]),
                ]);
            }

            $prepMinutes = (int) ($data['prep_minutes'] ?? 15);
            $intimationMinutes = min($prepMinutes, (int) ($data['intimation_minutes'] ?? 0));
            $sentAt = now();
            $targetAt = $sentAt->copy()->addMinutes($prepMinutes);
            $kotCount = KitchenTicket::withoutGlobalScope('company')->where('company_id', $company->id)->count();
            $kot = KitchenTicket::create([
                'company_id' => $company->id,
                'sale_id' => $sale->id,
                'kot_number' => 'KOT-'.sprintf('%03d', $kotCount + 1),
                'dining_table_id' => $table?->id,
                'table_name' => $tableName,
                'service_type' => $serviceType,
                'status' => KitchenTicket::STATUS_PENDING,
                'server_name' => $user?->name ?? 'POS Staff',
                'items' => $items,
                'sent_to_kitchen_at' => $sentAt,
                'kitchen_notes' => $data['notes'] ?? null,
                'prep_minutes' => $prepMinutes,
                'intimation_minutes' => $intimationMinutes,
                'target_completion_at' => $targetAt,
                'alarm_at' => $targetAt->copy()->subMinutes($intimationMinutes),
            ]);

            if ($table) {
                $table->update([
                    'status' => DiningTable::STATUS_OCCUPIED,
                    'current_sale_id' => $sale->id,
                    'guest_count' => $data['guest_count'] ?? ($table->guest_count ?: 1),
                ]);
            }

            return [$sale, $kot];
        });

        AuditLog::record('restaurant.kot_dispatched', $company->id, $user?->id, [
            'kot_number' => $kot->kot_number,
            'table' => $tableName,
            'service_type' => $serviceType,
        ]);

        $freshKot = $kot->fresh('table');
        $thermalText = SchemaResponse::formatKotThermalText($freshKot, $company);
        $printModalSheet = SchemaResponse::kotPrintModalSheet($freshKot, $company, $thermalText);
        $delivery = null;
        if (! empty($data['channels']) || ! empty($data['channel']) || $request->boolean('send_whatsapp') || $request->boolean('send_email') || $request->boolean('send_sms')) {
            $dispatchRequest = clone $request;
            $dispatchRequest->merge([
                'phone' => $data['recipient_phone'] ?? $data['customer_phone'] ?? null,
                'email' => $data['recipient_email'] ?? $data['customer_email'] ?? null,
            ]);
            $delivery = app(\App\Http\Controllers\Api\DocumentDispatchController::class)
                ->dispatchKot($dispatchRequest, $kot->id)->getData(true);
        }

        return response()->json([
            'success' => true,
            'message' => "{$kot->kot_number} dispatched to kitchen for {$tableName}.".($delivery ? ' '.$delivery['message'] : ''),
            'delivery_success' => $delivery['success'] ?? null,
            'results' => $delivery['results'] ?? [],
            'device_actions' => $delivery['device_actions'] ?? [],
            'delivery_status' => $delivery['status'] ?? null,
            'action' => 'OPEN_MODAL_BOTTOM_SHEET',
            'type' => 'OPEN_MODAL_BOTTOM_SHEET',
            'sheet' => $printModalSheet,
            'modal' => $printModalSheet,
            'thermal_preview' => $thermalText,
            'thermal_text' => $thermalText,
            'print_action' => [
                'type' => 'TRIGGER_PRINT',
                'action' => 'TRIGGER_PRINT',
                'target' => 'thermal_printer',
                'endpoint' => "/api/tenant/restaurant/kot/{$kot->id}/print",
                'payload' => [
                    'kot_id' => (string) $kot->id,
                    'thermal_text' => $thermalText,
                ],
            ],
            'sale' => $this->presentSale($sale->fresh()),
            'kot' => $this->presentKot($freshKot),
        ], 201);
    }

    public function settle(Request $request, string $saleId): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }
        $user = $this->resolveUser($request, $company);

        $sale = $this->findOpenSale($company, $saleId);
        if (! $sale) {
            return response()->json(['success' => false, 'error' => 'Open order not found.'], 404);
        }

        if (empty($sale->items)) {
            return response()->json(['success' => false, 'error' => 'No items to bill.'], 422);
        }

        $openRegister = CashRegister::openFor($company->id);
        // Settling a bill is allowed without an active shift. The nullable
        // association keeps those sales outside register reconciliation.
        $cashRegisterId = $openRegister?->id;

        $validator = Validator::make($request->all(), [
            'payment_method' => ['required_if:is_split_payment,false,0', 'nullable', 'string'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'cash_tendered' => ['nullable', 'numeric', 'min:0'],
            'is_split_payment' => ['nullable', 'boolean'],
            'split_payments' => ['required_if:is_split_payment,true,1', 'array'],
            'split_payments.*.payment_method' => ['required', 'string'],
            'split_payments.*.amount' => ['required', 'numeric', 'min:0'],
            'split_payments.*.reference_number' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'customer_id' => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        $customer = null;
        if (! empty($data['customer_id'])) {
            $customer = Customer::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->find($data['customer_id']);
            if (! $customer) {
                return response()->json(['success' => false, 'error' => 'Selected customer was not found.'], 422);
            }
        }

        $isSplit = (bool) ($data['is_split_payment'] ?? false);
        $discount = (float) ($data['discount'] ?? $sale->discount ?? 0);
        $subtotal = array_sum(array_map(fn ($i) => (float) $i['price'] * (float) $i['quantity'], $sale->items ?? []));
        $total = max(0, round($subtotal - $discount, 2));

        if ($isSplit) {
            $splitPayments = $data['split_payments'];
            $splitTotalPaid = array_sum(array_map(fn ($p) => (float) $p['amount'], $splitPayments));
            $remainingBalance = round($total - $splitTotalPaid, 2);
            if ($remainingBalance > 0 && empty($data['due_date'])) {
                return response()->json(['success' => false, 'error' => 'A due date is required when the split payment leaves a remaining balance.'], 422);
            }
            $paidAmount = min($total, $splitTotalPaid);
        } else {
            $paidAmount = $total;
            $splitPayments = [];
        }

        $dueAmount = max(0, round($total - $paidAmount, 2));
        $paymentStatus = $dueAmount <= 0.001 ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'pending');

        $saleNumber = 'INV-'.sprintf('%04d', Sale::withoutGlobalScope('company')->where('company_id', $company->id)->count() + 1);

        $sale = DB::transaction(function () use ($sale, $saleNumber, $total, $discount, $isSplit, $paidAmount, $dueAmount, $paymentStatus, $data, $splitPayments, $company, $customer, $cashRegisterId) {
            $sale->update([
                'sale_number' => $saleNumber,
                'total' => $total,
                'discount' => $discount,
                'payment_method' => $isSplit ? 'split' : ($data['payment_method'] ?? 'cash'),
                'status' => 'completed',
                'notes' => $data['notes'] ?? $sale->notes,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'due_date' => $dueAmount > 0 ? ($data['due_date'] ?? null) : null,
                'payment_status' => $paymentStatus,
                'kot_status' => 'served',
                'customer_id' => $customer?->id ?? $sale->customer_id,
                'customer_name' => $customer?->name ?? $sale->customer_name,
                'cash_register_id' => $cashRegisterId,
            ]);

            if ($isSplit) {
                foreach ($splitPayments as $sp) {
                    if ((float) ($sp['amount'] ?? 0) <= 0) {
                        continue;
                    }
                    OrderPayment::create([
                        'company_id' => $company->id,
                        'sale_id' => $sale->id,
                        'cash_register_id' => $cashRegisterId,
                        'payment_method' => $sp['payment_method'],
                        'amount' => (float) $sp['amount'],
                        'reference_number' => $sp['reference_number'] ?? null,
                    ]);
                }
            } else {
                $method = $data['payment_method'] ?? 'cash';
                $tendered = (float) ($data['cash_tendered'] ?? 0);
                OrderPayment::create([
                    'company_id' => $company->id,
                    'sale_id' => $sale->id,
                    'cash_register_id' => $cashRegisterId,
                    'payment_method' => $method,
                    'amount' => $paidAmount,
                    'tendered' => $method === 'cash' ? $tendered : null,
                    'change_returned' => $method === 'cash' ? max(0, round($tendered - $paidAmount, 2)) : 0,
                ]);
            }

            return $sale;
        });

        if ($sale->dining_table_id) {
            DiningTable::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('id', $sale->dining_table_id)
                ->update(['status' => DiningTable::STATUS_AVAILABLE, 'current_sale_id' => null, 'guest_count' => 0]);
        }

        AuditLog::record('restaurant.bill_settled', $company->id, $user?->id, [
            'sale_number' => $saleNumber,
            'total' => $total,
            'payment_method' => $sale->payment_method,
            'is_split' => $isSplit,
        ]);

        return response()->json(['success' => true, 'message' => "Bill {$saleNumber} settled.", 'sale' => $this->presentSale($sale->fresh())]);
    }

    // ---------------------------------------------------------------
    // Kitchen Display System (KOT)
    // ---------------------------------------------------------------

    public function kotIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $query = KitchenTicket::withoutGlobalScope('company')->where('company_id', $company->id);

        if ($serviceType = $request->query('service_type')) {
            $query->where('service_type', $serviceType);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', [KitchenTicket::STATUS_PENDING, KitchenTicket::STATUS_PREPARING, KitchenTicket::STATUS_READY]);
        }

        $tickets = $query->with('table')->orderBy('created_at')->get();

        $completed = KitchenTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', KitchenTicket::STATUS_SERVED)
            ->latest('served_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'tickets' => $tickets->map(fn (KitchenTicket $k) => $this->presentKot($k))->values(),
            'completed_tickets' => $completed->map(fn (KitchenTicket $k) => $this->presentKot($k))->values(),
            'counts' => [
                'pending' => KitchenTicket::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', KitchenTicket::STATUS_PENDING)->count(),
                'preparing' => KitchenTicket::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', KitchenTicket::STATUS_PREPARING)->count(),
                'ready' => KitchenTicket::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', KitchenTicket::STATUS_READY)->count(),
            ],
            'alert_settings' => PushNotificationSetting::current()->publicConfig($company->id),
        ]);
    }

    public function kotUpdateStatus(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $kot = KitchenTicket::withoutGlobalScope('company')->where('company_id', $company->id)->find($id);
        if (! $kot) {
            return response()->json(['success' => false, 'error' => 'Kitchen ticket not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:preparing,ready,served,cancelled'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $status = $request->input('status');
        $payload = ['status' => $status];
        $saleKotStatus = null;
        switch ($status) {
            case 'preparing':
                $payload['prepared_at'] = now();
                $saleKotStatus = 'preparing';
                break;
            case 'ready':
                $payload['ready_at'] = now();
                $saleKotStatus = 'ready';
                break;
            case 'served':
                $payload['served_at'] = now();
                $saleKotStatus = 'served';
                break;
        }

        $kot->update($payload);
        if ($saleKotStatus && $kot->sale) {
            $kot->sale->update(['kot_status' => $saleKotStatus]);
        }

        if (in_array($status, [KitchenTicket::STATUS_SERVED, KitchenTicket::STATUS_CANCELLED], true)) {
            $kot->update(['alarm_dismissed_at' => now()]);
            rescue(fn () => app(FirebasePushService::class)->sendToCompany($company->id, [
                'type' => 'delayed_order_alarm',
                'action' => 'clear',
                'notification_id' => 'order_'.$kot->id,
                'kitchen_ticket_id' => $kot->id,
            ]), report: true);
        }

        return response()->json(['success' => true, 'message' => "Ticket {$kot->kot_number} updated.", 'kot' => $this->presentKot($kot->fresh('table'))]);
    }

    public function kotDismissAlarm(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $kot = KitchenTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->find($id);

        if (! $kot) {
            return response()->json(['success' => false, 'error' => 'Kitchen ticket not found.'], 404);
        }

        $kot->update(['alarm_dismissed_at' => now()]);
        rescue(fn () => app(FirebasePushService::class)->sendToCompany($company->id, [
            'type' => 'delayed_order_alarm',
            'action' => 'clear',
            'notification_id' => 'order_'.$kot->id,
            'kitchen_ticket_id' => $kot->id,
        ]), report: true);

        return response()->json(['success' => true, 'message' => 'Order alarm dismissed.']);
    }

    public function kotPrint(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        if ($blocked = $this->ensureRestaurantMode($company)) {
            return $blocked;
        }

        $kot = KitchenTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with('table')
            ->find($id);

        if (! $kot) {
            return response()->json(['success' => false, 'error' => 'Kitchen ticket not found.'], 404);
        }

        $thermalText = $request->input('thermal_text') ?: SchemaResponse::formatKotThermalText($kot, $company);

        AuditLog::record('restaurant.kot_printed', $company->id, $this->resolveUser($request, $company)?->id, [
            'kot_number' => $kot->kot_number,
            'kot_id' => $kot->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "KOT {$kot->kot_number} print payload generated.",
            'kot_id' => (string) $kot->id,
            'kot_number' => $kot->kot_number,
            'raw_text' => $thermalText,
            'thermal_text' => $thermalText,
            'print_action' => [
                'type' => 'TRIGGER_PRINT',
                'action' => 'TRIGGER_PRINT',
                'target' => 'thermal_printer',
                'raw_text' => $thermalText,
            ],
        ]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function findFloor(Company $company, string $id): ?DiningFloor
    {
        return DiningFloor::withoutGlobalScope('company')->where('company_id', $company->id)->where('id', $id)->first();
    }

    private function findTable(Company $company, string $id): ?DiningTable
    {
        return DiningTable::withoutGlobalScope('company')->where('company_id', $company->id)->where('id', $id)->with('floor', 'currentSale')->first();
    }

    private function findOpenSale(Company $company, string $id): ?Sale
    {
        return Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->where('status', 'pending')
            ->first();
    }

    private function presentFloor(DiningFloor $floor): array
    {
        return [
            'id' => $floor->id,
            'name' => $floor->name,
            'order_index' => $floor->order_index,
            'tables' => $floor->tables->map(fn (DiningTable $t) => $this->presentTable($t))->values(),
        ];
    }

    private function presentTable(DiningTable $table): array
    {
        return [
            'id' => $table->id,
            'dining_floor_id' => $table->dining_floor_id,
            'floor_name' => $table->floor?->name,
            'table_number' => $table->table_number,
            'seating_capacity' => $table->seating_capacity,
            'status' => $table->status,
            'current_sale_id' => $table->current_sale_id,
            'guest_count' => $table->guest_count,
            'qr_token' => $table->qr_token,
            'qr_order_url' => $table->getQrOrderUrl(),
        ];
    }

    private function presentSale(Sale $sale): array
    {
        $sale->loadMissing('customer');

        return [
            'id' => (string) $sale->id,
            'sale_number' => $sale->sale_number,
            'customer_id' => $sale->customer_id ? (string) $sale->customer_id : null,
            'customer_name' => $sale->customer?->name ?? $sale->customer_name,
            'customer_phone' => $sale->customer?->phone,
            'customer_email' => $sale->customer?->email,
            'status' => $sale->status,
            'service_type' => $sale->service_type,
            'dining_table_id' => $sale->dining_table_id,
            'table_name' => $sale->table_name,
            'guest_count' => $sale->guest_count,
            'items' => $sale->items ?? [],
            'discount' => (float) $sale->discount,
            'total' => (float) $sale->total,
            'paid_amount' => (float) $sale->paid_amount,
            'due_amount' => (float) $sale->due_amount,
            'payment_status' => $sale->payment_status,
            'payment_method' => $sale->payment_method,
            'kot_status' => $sale->kot_status,
            'notes' => $sale->notes,
            'created_at' => $sale->created_at?->toIso8601String(),
        ];
    }

    private function presentKot(KitchenTicket $kot): array
    {
        return [
            'id' => $kot->id,
            'sale_id' => $kot->sale_id,
            'kot_number' => $kot->kot_number,
            'dining_table_id' => $kot->dining_table_id,
            'table_name' => $kot->table_name,
            'service_type' => $kot->service_type,
            'status' => $kot->status,
            'server_name' => $kot->server_name,
            'items' => $kot->items ?? [],
            'kitchen_notes' => $kot->kitchen_notes,
            'elapsed_minutes' => $kot->getElapsedMinutes(),
            'prepared_at' => $kot->prepared_at?->toIso8601String(),
            'ready_at' => $kot->ready_at?->toIso8601String(),
            'served_at' => $kot->served_at?->toIso8601String(),
            'created_at' => $kot->created_at?->toIso8601String(),
            'sent_to_kitchen_at' => $kot->sent_to_kitchen_at?->toIso8601String(),
            'prep_minutes' => $kot->prep_minutes,
            'intimation_minutes' => $kot->intimation_minutes,
            'target_completion_at' => $kot->target_completion_at?->toIso8601String(),
            'alarm_at' => $kot->alarm_at?->toIso8601String(),
            'alarm_sent_at' => $kot->alarm_sent_at?->toIso8601String(),
            'alarm_dismissed_at' => $kot->alarm_dismissed_at?->toIso8601String(),
            'is_alarm_active' => $kot->isAlarmActive(),
            'is_overdue' => $kot->isOverdue(),
        ];
    }
}
