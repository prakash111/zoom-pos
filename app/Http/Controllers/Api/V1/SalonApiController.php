<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalonAppointment;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\Sdui\PosScreenBuilder;
use App\Services\Sdui\SchemaValidator;
use App\Services\TaxCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Salon & Service counter-sale POS: real service/retail catalog (see
 * PosScreenBuilder::salonPosScreen — services are Product rows with
 * duration_minutes set, no separate Service model), optional specialist
 * assignment, per-line commission attribution, appointment booking, and
 * checkout through the shared native POS drawer contract.
 */
class SalonApiController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Checkout/settlement sheet (component tree) driven by the client's
     * current local cart preview, including a specialist dropdown sourced
     * from real staff tagged is_specialist.
     * GET /api/tenant/salon/checkout-sheet
     */
    public function checkoutSheet(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $cartPreview = $this->decodeCartPreview($request);
        $appointment = $request->integer('appointment_id') && Schema::hasTable('salon_appointments')
            ? SalonAppointment::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->with(['service', 'specialist'])
                ->find($request->integer('appointment_id'))
            : null;

        $specialists = User::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('is_specialist', true)
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();

        $schema = PosScreenBuilder::checkoutSheet(
            $company,
            '/api/tenant/salon/pos-checkout'.($appointment ? '?appointment_id='.$appointment->id : ''),
            $cartPreview,
            customerFieldLabel: 'Client Name',
            specialistOptions: $specialists,
            module: 'salon',
            defaultSpecialistId: $appointment?->specialist_id,
            defaultCustomerName: $appointment?->customer_name,
        );

        if ($appointment) {
            $schema['booking_context'] = [
                'appointment_id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'customer_name' => $appointment->customer_name,
                'service_id' => $appointment->product_id,
                'service_name' => $appointment->service?->name,
                'specialist_id' => $appointment->specialist_id,
                'specialist_name' => $appointment->specialist?->name,
            ];
        }

        $errors = app(SchemaValidator::class)->validate($schema);
        if ($errors !== []) {
            return response()->json(['success' => false, 'error' => 'Invalid SDUI schema.', 'details' => ['schema' => $errors]], 500);
        }

        return response()->json(['success' => true, 'schema' => $schema]);
    }

    /**
     * Specialist picker sheet (component tree) for a single salon service.
     * Rendered by UniversalPosScreen when a service catalog item is tapped.
     * GET /api/tenant/salon/specialist-sheet
     */
    public function specialistSheet(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $productId = (int) $request->query('product_id');

        if ($productId <= 0) {
            return response()->json(['success' => false, 'error' => 'product_id is required.'], 422);
        }

        $service = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->findOrFail($productId);

        $currency = $company->currency_symbol ?: '$';
        $duration = (int) ($service->duration_minutes ?: 30);
        $price = (float) $service->sale_price;

        $specialists = User::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('is_specialist', true)
            ->where('status', 'approved')
            ->orderBy('name')
            ->get();

        $components = [
            \App\Services\Sdui\SchemaResponse::row([
                \App\Services\Sdui\SchemaResponse::icon('spa', ['color' => '#7c3aed', 'size' => 28]),
                \App\Services\Sdui\SchemaResponse::column([
                    \App\Services\Sdui\SchemaResponse::text($service->name, 'title_medium', ['bold' => true]),
                    \App\Services\Sdui\SchemaResponse::text("Duration: {$duration} mins · Rate: {$currency}".number_format($price, 2), 'body_small', ['color' => '#64748b']),
                ]),
            ]),
            \App\Services\Sdui\SchemaResponse::divider(),
            \App\Services\Sdui\SchemaResponse::text('Select Stylist / Specialist for this Service:', 'label_large', ['bold' => true]),
        ];

        // 1. Any Available Stylist option
        $components[] = \App\Services\Sdui\SchemaResponse::card([
            \App\Services\Sdui\SchemaResponse::row([
                \App\Services\Sdui\SchemaResponse::icon('groups', ['color' => '#0284c7', 'size' => 24]),
                \App\Services\Sdui\SchemaResponse::column([
                    \App\Services\Sdui\SchemaResponse::text('Any Available Specialist', 'title_small', ['bold' => true]),
                    \App\Services\Sdui\SchemaResponse::text('Assign automatically at service time', 'body_small', ['color' => '#64748b']),
                ]),
                \App\Services\Sdui\SchemaResponse::badge('Flexible', '#0284c7', 'subtle'),
            ], ['main_axis_alignment' => 'space_between']),
            \App\Services\Sdui\SchemaResponse::buttonPrimary('Select Any Available', \App\Services\Sdui\SchemaResponse::addToCartAction([
                'id' => $service->id,
                'batch_id' => null,
                'title' => $service->name,
                'subtitle' => "Service · {$duration}m (Any Specialist)",
                'price' => $price,
                'quantity' => 1,
                'max_quantity' => 99,
                'duration_minutes' => $duration,
                'service_type' => 'service',
            ]), 'add_shopping_cart'),
        ]);

        // 2. Individual Specialists
        foreach ($specialists as $staff) {
            $components[] = \App\Services\Sdui\SchemaResponse::card([
                \App\Services\Sdui\SchemaResponse::row([
                    \App\Services\Sdui\SchemaResponse::icon('person', ['color' => '#7c3aed', 'size' => 24]),
                    \App\Services\Sdui\SchemaResponse::column([
                        \App\Services\Sdui\SchemaResponse::text($staff->name, 'title_small', ['bold' => true]),
                        \App\Services\Sdui\SchemaResponse::text(User::ROLES[$staff->role] ?? 'Stylist / Technician', 'body_small', ['color' => '#64748b']),
                    ]),
                    \App\Services\Sdui\SchemaResponse::badge('Available', '#10b981', 'subtle'),
                ], ['main_axis_alignment' => 'space_between']),
                \App\Services\Sdui\SchemaResponse::buttonPrimary("Assign {$staff->name}", \App\Services\Sdui\SchemaResponse::addToCartAction([
                    'id' => $service->id,
                    'batch_id' => null,
                    'title' => $service->name,
                    'subtitle' => "Stylist: {$staff->name} · {$duration}m",
                    'price' => $price,
                    'quantity' => 1,
                    'max_quantity' => 99,
                    'specialist_id' => $staff->id,
                    'specialist_name' => $staff->name,
                    'duration_minutes' => $duration,
                    'service_type' => 'service',
                ]), 'person_add'),
            ]);
        }

        $schema = \App\Services\Sdui\SchemaResponse::sheet("Select Stylist — {$service->name}", $components);
        $errors = app(SchemaValidator::class)->validate($schema);
        if ($errors !== []) {
            return response()->json(['success' => false, 'error' => 'Invalid SDUI schema.', 'details' => ['schema' => $errors]], 500);
        }

        return response()->json(['success' => true, 'schema' => $schema]);
    }

    /** List the tenant's real appointment book for a date or date range. */
    public function appointmentsIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $timezone = $company->resolveTimezone();
        $from = Carbon::parse((string) $request->query('from', $request->query('date', 'today')), $timezone)->startOfDay()->utc();
        $to = Carbon::parse((string) $request->query('to', $request->query('date', 'today')), $timezone)->endOfDay()->utc();

        $query = SalonAppointment::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereBetween('starts_at', [$from, $to])
            ->with(['service:id,name,duration_minutes,sale_price', 'specialist:id,name'])
            ->orderBy('starts_at');

        if ($request->filled('specialist_id')) {
            $query->where('specialist_id', $request->query('specialist_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json([
            'success' => true,
            'timezone' => $timezone,
            'appointments' => $query->get()->map(fn (SalonAppointment $appointment) => $this->presentAppointment($appointment, $timezone)),
        ]);
    }

    /** Create a conflict-checked service booking for a specialist time slot. */
    public function appointmentsStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|integer',
            'specialist_id' => 'required|string',
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'nullable|string|max:50',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
            'advance_deposit' => 'nullable|numeric|min:0',
            'advance_paid' => 'nullable|numeric|min:0',
            'deposit_payment_method' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $service = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->find($request->integer('service_id'));
        $specialist = User::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('is_specialist', true)
            ->where('status', 'approved')
            ->find($request->input('specialist_id'));

        if (! $service || ! $service->duration_minutes) {
            return response()->json(['success' => false, 'error' => 'Selected salon service is unavailable.'], 422);
        }
        if (! $specialist) {
            return response()->json(['success' => false, 'error' => 'Selected stylist or specialist is unavailable.'], 422);
        }

        $startsAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $request->input('appointment_date').' '.$request->input('appointment_time'),
            $company->resolveTimezone()
        )->utc();
        $endsAt = $startsAt->copy()->addMinutes((int) $service->duration_minutes);

        $hasConflict = SalonAppointment::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('specialist_id', $specialist->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($hasConflict) {
            return response()->json(['success' => false, 'error' => 'That specialist already has an appointment in this time slot.'], 422);
        }

        $dailyCount = SalonAppointment::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereDate('starts_at', $startsAt->toDateString())
            ->count() + 1;
        $advancePaid = (float) $request->input('advance_deposit', $request->input('advance_paid', 0));
        $appointment = SalonAppointment::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'appointment_number' => 'APT-'.$startsAt->format('Ymd').'-'.sprintf('%04d', $dailyCount),
            'customer_id' => $request->input('customer_id'),
            'customer_name' => $request->input('customer_name'),
            'customer_phone' => $request->input('customer_phone'),
            'product_id' => $service->id,
            'specialist_id' => $specialist->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'scheduled',
            'notes' => $request->input('notes'),
            'advance_paid' => $advancePaid,
            'deposit_payment_method' => $request->input('deposit_payment_method', 'cash'),
        ]);

        AuditLog::record('salon.appointment_created', $company->id, $this->resolveUser($request, $company)?->id, [
            'appointment_id' => $appointment->id,
            'specialist_id' => $specialist->id,
            'starts_at' => $startsAt->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Appointment booked successfully.',
            'appointment' => $this->presentAppointment($appointment->load(['service', 'specialist']), $company->resolveTimezone()),
        ], 201);
    }

    /** Return half-hour slots with live conflict availability. */
    public function appointmentsAvailability(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $validator = Validator::make($request->query(), [
            'date' => 'required|date',
            'specialist_id' => 'required|string',
            'service_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $service = Product::withoutGlobalScope('company')->where('company_id', $company->id)->find($request->integer('service_id'));
        $specialistExists = User::withoutGlobalScope('company')->where('company_id', $company->id)->where('is_specialist', true)->whereKey($request->query('specialist_id'))->exists();
        if (! $service || ! $specialistExists) {
            return response()->json(['success' => false, 'error' => 'Service or specialist not found.'], 404);
        }

        $timezone = $company->resolveTimezone();
        $day = Carbon::parse($request->query('date'), $timezone);
        $booked = SalonAppointment::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('specialist_id', $request->query('specialist_id'))
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('starts_at', [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()])
            ->get(['starts_at', 'ends_at']);
        $slots = [];
        for ($slot = $day->copy()->setTime(9, 0); $slot->lt($day->copy()->setTime(20, 0)); $slot->addMinutes(30)) {
            $startUtc = $slot->copy()->utc();
            $endUtc = $startUtc->copy()->addMinutes((int) ($service->duration_minutes ?: 30));
            $available = ! $booked->contains(fn (SalonAppointment $appointment) => $appointment->starts_at->lt($endUtc) && $appointment->ends_at->gt($startUtc));
            $slots[] = ['time' => $slot->format('H:i'), 'label' => $slot->format('g:i A'), 'available' => $available];
        }

        return response()->json(['success' => true, 'timezone' => $timezone, 'slots' => $slots]);
    }

    public function appointmentsUpdateStatus(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:scheduled,checked_in,completed,cancelled,no_show',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $appointment = SalonAppointment::withoutGlobalScope('company')->where('company_id', $company->id)->find($id);
        if (! $appointment) {
            return response()->json(['success' => false, 'error' => 'Appointment not found.'], 404);
        }
        $appointment->update(['status' => $request->input('status')]);

        return response()->json(['success' => true, 'message' => 'Appointment status updated.', 'appointment' => $this->presentAppointment($appointment->load(['service', 'specialist']), $company->resolveTimezone())]);
    }

    /**
     * Completes a walk-in or booked salon sale with specialist attribution
     * and commission snapshots stored on every service line.
     * POST /api/tenant/salon/pos-checkout
     */
    public function posCheckout(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $this->normalizeFixedSplitPayments($request);

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'specialist_id' => 'nullable|string',
            'appointment_id' => 'nullable|integer',
            'payment_method' => 'nullable|string',
            'payments' => 'nullable|array',
            'discount' => 'nullable|numeric',
            'tendered' => 'nullable|numeric|min:0',
            'quick_cash_tendered' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $items = $request->input('items', []);
        $appointment = $request->filled('appointment_id')
            ? SalonAppointment::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->find($request->integer('appointment_id'))
            : null;
        $specialistId = $request->input('specialist_id') ?: $appointment?->specialist_id;

        try {
            $sale = DB::transaction(function () use ($company, $user, $request, $items, $specialistId, $appointment) {
                $specialist = null;
                if (! empty($specialistId)) {
                    $specialist = User::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->find($specialistId);
                }

                $saleLineItems = [];
                $totalRevenue = 0;
                $totalCommission = 0;
                $commissionRates = [];
                $commissionTypes = [];

                foreach (array_values($items) as $index => $itemData) {
                    $productId = $itemData['product_id'];
                    $qty = (int) $itemData['quantity'];

                    $product = Product::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->find($productId);

                    if (! $product) {
                        throw new \InvalidArgumentException("Service or product with ID {$productId} not found.");
                    }

                    $product->decrementStock($qty, 'Salon Counter POS sale');

                    $unitPrice = isset($itemData['unit_price']) && (float) $itemData['unit_price'] > 0
                        ? (float) $itemData['unit_price']
                        : (float) $product->sale_price;

                    $lineTotal = round($qty * $unitPrice, 2);
                    $totalRevenue += $lineTotal;

                    $lineSpecialistId = $product->duration_minutes
                        ? ($request->input("line_specialist_{$index}") ?: $specialist?->id)
                        : null;
                    $lineSpecialist = null;
                    if ($lineSpecialistId) {
                        $lineSpecialist = User::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where('is_specialist', true)
                            ->find($lineSpecialistId);
                        if (! $lineSpecialist) {
                            throw new \InvalidArgumentException("Assigned specialist for {$product->name} is unavailable.");
                        }
                    }

                    $commissionRate = (float) ($lineSpecialist?->commission_rate ?: $company->default_commission_rate ?: 0);
                    $commissionType = (string) ($lineSpecialist?->commission_type ?: $company->default_commission_type ?: 'percentage');
                    $lineCommission = $lineSpecialist
                        ? app(CommissionService::class)->calculate($commissionRate, $commissionType, $lineTotal, [[
                            'product_id' => $product->id,
                            'price' => $unitPrice,
                            'quantity' => $qty,
                            'cost_price' => (float) $product->cost_price,
                        ]], $company->id)
                        : 0.0;
                    $totalCommission += $lineCommission;
                    if ($lineSpecialist) {
                        $commissionRates[] = $commissionRate;
                        $commissionTypes[] = $commissionType;
                    }

                    $saleLineItems[] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'price' => $unitPrice,
                        'total' => $lineTotal,
                        'duration_minutes' => $product->duration_minutes,
                        'specialist_id' => $lineSpecialist?->id,
                        'specialist_name' => $lineSpecialist?->name,
                        'commission_rate' => $commissionRate,
                        'commission_type' => $commissionType,
                        'commission_amount' => $lineCommission,
                    ];
                }

                $discount = (float) $request->input('discount', 0);
                $taxTotals = app(TaxCalculationService::class)->calculateCartTotals($saleLineItems, $company, null, $discount);
                $saleLineItems = $taxTotals['items'];
                $discount = (float) $taxTotals['discount'];
                $netAmount = (float) $taxTotals['total'];
                $totalRevenue = (float) $taxTotals['total'];
                $paymentMethod = strtolower((string) $request->input('payment_method', $request->input('selected_payment_method', 'cash')));
                $advanceApplied = $appointment ? min((float) ($appointment->advance_paid ?? 0), $netAmount) : 0.0;
                $balanceToCollect = max(0, round($netAmount - $advanceApplied, 2));
                $payments = $request->input('payments');
                $collectedNow = $paymentMethod === 'credit'
                    ? 0.0
                    : (! empty($payments) && is_array($payments)
                        ? min($balanceToCollect, collect($payments)->sum(fn ($payment) => max(0, (float) ($payment['amount'] ?? 0))))
                        : $balanceToCollect);
                $paidAmount = min($netAmount, $advanceApplied + $collectedNow);
                $dueAmount = max(0, round($netAmount - $paidAmount, 2));
                $paymentStatus = $dueAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending');
                $cashRegister = CashRegister::openFor($company->id);

                $prefix = $company->invoice_prefix ?: 'INV-';
                $saleCount = Sale::withoutGlobalScope('company')->where('company_id', $company->id)->count() + 1;
                $saleNumber = $prefix.sprintf('%04d', $saleCount);

                $sale = Sale::create([
                    'company_id' => $company->id,
                    'sale_number' => $saleNumber,
                    'customer_id' => $request->input('customer_id'),
                    'customer_name' => $request->input('customer_name') ?: ($appointment?->customer_name ?? 'Walk-in Customer'),
                    'user_id' => $user?->id,
                    'cash_register_id' => $cashRegister?->id,
                    'total' => $totalRevenue,
                    'discount' => $discount,
                    'net_amount' => $netAmount,
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'status' => 'completed',
                    'operation_type' => 'sale',
                    'items' => $saleLineItems,
                    'tax_amount' => $taxTotals['tax_amount'],
                    'tax_name' => $company->tax_id_label ?: 'Tax',
                    'tax_breakdown' => $taxTotals['tax_summary_table'],
                    'commission_rate' => $commissionRates !== [] ? round(array_sum($commissionRates) / count($commissionRates), 2) : 0,
                    'commission_type' => count(array_unique($commissionTypes)) === 1 ? ($commissionTypes[0] ?? 'percentage') : 'mixed',
                    'commission_amount' => round($totalCommission, 2),
                    'notes' => $specialist
                        ? "Serviced by {$specialist->name}"
                        : ($request->input('notes') ?? 'Salon Counter Sale'),
                ]);

                if ($advanceApplied > 0) {
                    OrderPayment::create([
                        'company_id' => $company->id,
                        'sale_id' => $sale->id,
                        'cash_register_id' => $cashRegister?->id,
                        'payment_method' => 'advance_deposit',
                        'amount' => $advanceApplied,
                        'net_amount' => $advanceApplied,
                        'notes' => "Advance deposit applied from Appointment #{$appointment->id}",
                    ]);
                }

                if (! empty($payments) && is_array($payments)) {
                    foreach ($payments as $pay) {
                        $amt = (float) ($pay['amount'] ?? 0);
                        if ($amt > 0) {
                            OrderPayment::create([
                                'company_id' => $company->id,
                                'sale_id' => $sale->id,
                                'cash_register_id' => $cashRegister?->id,
                                'payment_method' => strtolower((string) ($pay['method'] ?? 'cash')),
                                'amount' => $amt,
                                'net_amount' => $amt,
                            ]);
                        }
                    }
                } elseif ($paymentMethod !== 'credit' && $collectedNow > 0) {
                    $tendered = $request->filled('tendered')
                        ? (float) $request->input('tendered')
                        : (float) $request->input('quick_cash_tendered', $request->input('selected_tendered', $collectedNow));
                    OrderPayment::create([
                        'company_id' => $company->id,
                        'sale_id' => $sale->id,
                        'cash_register_id' => $cashRegister?->id,
                        'payment_method' => $paymentMethod,
                        'amount' => $collectedNow,
                        'tendered' => $paymentMethod === 'cash' ? max($collectedNow, $tendered) : null,
                        'change_returned' => $paymentMethod === 'cash' ? max(0, $tendered - $collectedNow) : 0,
                        'net_amount' => $collectedNow,
                    ]);
                }

                if ($appointment) {
                    $appointment->update(['status' => 'completed', 'sale_id' => $sale->id]);
                }

                return $sale;
            });

            AuditLog::record('salon.pos_sale_completed', $company->id, $user?->id, [
                'sale_id' => $sale->id,
                'total' => (float) $sale->total,
                'specialist_id' => $specialistId,
            ]);

            $whatsappUrl = app(\App\Services\Invoice\InvoiceDeliveryService::class)->generateInvoiceWhatsAppUrl($sale, $request->input('customer_phone'));

            return response()->json([
                'success' => true,
                'message' => 'Salon counter sale completed successfully',
                'sale' => $sale,
                // In-app Post-Sale Action Sheet — no auto-launch keys.
                'invoice_number' => $sale->sale_number,
                'post_sale_sheet' => \App\Services\Sdui\SchemaResponse::postSaleActionResponse($sale->fresh(['customer', 'company', 'payments']), $whatsappUrl),
                'whatsapp_share_url' => $whatsappUrl,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Checkout failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * Plain JSON specialist roster (id/name/role/is_specialist) — the
     * management UI itself is served via the SDUI service-stylists view
     * (PosScreenBuilder::specialistRosterScreen); this endpoint exists for
     * any non-SDUI consumer that needs the list directly.
     * GET /api/tenant/salon/specialists
     */
    public function specialistsIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $staff = User::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'is_specialist']);

        return response()->json(['success' => true, 'specialists' => $staff]);
    }

    /**
     * Toggles a staff member's is_specialist flag. Mirrors
     * UserApiController::toggleStatus's shape.
     * POST /api/tenant/salon/specialists/{id}/toggle
     */
    public function specialistsToggle(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $me = $this->resolveUser($request, $company);

        $staffMember = User::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->first();

        if (! $staffMember) {
            return response()->json(['success' => false, 'error' => 'Staff member not found.'], 404);
        }

        $staffMember->update(['is_specialist' => ! $staffMember->is_specialist]);

        AuditLog::record('salon.specialist_toggled', $company->id, $me?->id, [
            'user_id' => $staffMember->id,
            'is_specialist' => $staffMember->is_specialist,
        ]);

        return response()->json([
            'success' => true,
            'message' => $staffMember->is_specialist ? 'Added to specialist roster.' : 'Removed from specialist roster.',
            'user' => $staffMember->fresh(),
        ]);
    }

    private function presentAppointment(SalonAppointment $appointment, string $timezone): array
    {
        return [
            'id' => $appointment->id,
            'appointment_number' => $appointment->appointment_number,
            'customer_id' => $appointment->customer_id,
            'customer_name' => $appointment->customer_name,
            'customer_phone' => $appointment->customer_phone,
            'service_id' => $appointment->product_id,
            'service_name' => $appointment->service?->name,
            'duration_minutes' => $appointment->service?->duration_minutes,
            'specialist_id' => $appointment->specialist_id,
            'specialist_name' => $appointment->specialist?->name,
            'starts_at' => $appointment->starts_at?->copy()->setTimezone($timezone)->toIso8601String(),
            'ends_at' => $appointment->ends_at?->copy()->setTimezone($timezone)->toIso8601String(),
            'status' => $appointment->status,
            'notes' => $appointment->notes,
            'sale_id' => $appointment->sale_id,
            'advance_paid' => (float) ($appointment->advance_paid ?? 0),
            'deposit_payment_method' => $appointment->deposit_payment_method,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeCartPreview(Request $request): array
    {
        $raw = $request->query('cart');
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * The checkout sheet ships two fixed optional split-payment rows
     * rather than a dynamic list (see PharmacyApiController for the same
     * pattern) — normalize them into the payments[] array posCheckout()
     * understands, when a split is actually being used.
     */
    private function normalizeFixedSplitPayments(Request $request): void
    {
        if ($request->filled('payments')) {
            return;
        }

        $rows = [];
        foreach ([1, 2] as $i) {
            $amount = (float) $request->input("payment_{$i}_amount", 0);
            if ($amount > 0) {
                $rows[] = [
                    'method' => (string) $request->input("payment_{$i}_method", 'cash'),
                    'amount' => $amount,
                ];
            }
        }

        if ($rows !== []) {
            $request->merge(['payments' => $rows]);
        }
    }
}
