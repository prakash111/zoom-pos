<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalonAppointment;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\Documents\DocumentNumberService;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Notifications\VerticalReminderService;
use App\Services\Pos\Adapters\SalonCartAdapter;
use App\Services\Pos\UniversalPosBuilder;
use App\Services\Sdui\PosScreenBuilder;
use App\Services\Sdui\SchemaResponse;
use App\Services\Sdui\SchemaValidator;
use App\Services\TaxCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
                ->with(['service', 'specialist', 'customer'])
                ->find($request->integer('appointment_id'))
            : null;

        // Backfill bookings created by older clients before appointment CRM
        // linkage was mandatory. This also guarantees that every timeline
        // checkout can render a real assigned-customer card.
        if ($appointment && ! $appointment->customer && trim((string) $appointment->customer_name) !== '') {
            $customer = Customer::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where(function ($query) use ($appointment) {
                    $appointment->customer_phone
                        ? $query->where('phone', $appointment->customer_phone)
                        : $query->where('name', $appointment->customer_name);
                })
                ->first();
            $customer ??= Customer::create([
                'company_id' => $company->id,
                'name' => $appointment->customer_name,
                'phone' => $appointment->customer_phone,
            ]);
            $appointment->update(['customer_id' => $customer->id]);
            $appointment->setRelation('customer', $customer);
        }

        $specialists = User::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('is_specialist', true)
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();

        $schema = UniversalPosBuilder::buildCartSheet(new SalonCartAdapter(
            $company,
            $cartPreview,
            $appointment,
            ['specialist_options' => $specialists],
        ));

        if ($appointment) {
            $schema['booking_context'] = [
                'appointment_id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'customer_id' => $appointment->customer_id,
                'customer_name' => $appointment->customer_name,
                'customer_phone' => $appointment->customer_phone,
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
            SchemaResponse::row([
                SchemaResponse::icon('spa', ['color' => '#7c3aed', 'size' => 28]),
                SchemaResponse::column([
                    SchemaResponse::text($service->name, 'title_medium', ['bold' => true]),
                    SchemaResponse::text("Duration: {$duration} mins · Rate: {$currency}".number_format($price, 2), 'body_small', ['color' => '#64748b']),
                ]),
            ]),
            SchemaResponse::divider(),
            SchemaResponse::text('Select Stylist / Specialist for this Service:', 'label_large', ['bold' => true]),
        ];

        // 1. Any Available Stylist option
        $components[] = SchemaResponse::card([
            SchemaResponse::row([
                SchemaResponse::icon('groups', ['color' => '#0284c7', 'size' => 24]),
                SchemaResponse::column([
                    SchemaResponse::text('Any Available Specialist', 'title_small', ['bold' => true]),
                    SchemaResponse::text('Assign automatically at service time', 'body_small', ['color' => '#64748b']),
                ]),
                SchemaResponse::badge('Flexible', '#0284c7', 'subtle'),
            ], ['main_axis_alignment' => 'space_between']),
            SchemaResponse::buttonPrimary('Select Any Available', SchemaResponse::addToCartAction([
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
            $components[] = SchemaResponse::card([
                SchemaResponse::row([
                    SchemaResponse::icon('person', ['color' => '#7c3aed', 'size' => 24]),
                    SchemaResponse::column([
                        SchemaResponse::text($staff->name, 'title_small', ['bold' => true]),
                        SchemaResponse::text(User::ROLES[$staff->role] ?? 'Stylist / Technician', 'body_small', ['color' => '#64748b']),
                    ]),
                    SchemaResponse::badge('Available', '#10b981', 'subtle'),
                ], ['main_axis_alignment' => 'space_between']),
                SchemaResponse::buttonPrimary("Assign {$staff->name}", SchemaResponse::addToCartAction([
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

        $schema = SchemaResponse::sheet("Select Stylist — {$service->name}", $components);
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
        $selectedDate = (string) ($request->query('date') ?: $request->query('from') ?: Carbon::today($timezone)->toDateString());
        $day = Carbon::parse($selectedDate, $timezone);
        $from = $day->copy()->startOfDay()->utc();
        $to = $day->copy()->endOfDay()->utc();

        $query = SalonAppointment::withoutGlobalScope('company')
            ->where(function ($q) use ($company) {
                $q->where('company_id', $company->id)
                  ->orWhere('tenant_id', $company->id);
            })
            ->where(function ($q) use ($from, $to, $selectedDate) {
                $q->whereBetween('starts_at', [$from, $to])
                  ->orWhereDate('starts_at', $selectedDate);
            })
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
            'selected_date' => $selectedDate,
            'appointments' => $query->get()->map(fn (SalonAppointment $appointment) => $this->presentAppointment($appointment, $timezone)),
        ]);
    }

    /** Alias matching getAppointments() requirement */
    public function getAppointments(Request $request): JsonResponse
    {
        return $this->appointmentsIndex($request);
    }

    /** Create a conflict-checked service booking for a specialist time slot. */
    public function appointmentsStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        // Normalize aliases: client_name -> customer_name, client_phone -> customer_phone, client_id -> customer_id
        $normalized = [];
        if (! $request->filled('customer_id') && $request->filled('client_id')) {
            $normalized['customer_id'] = $request->input('client_id');
        }
        if (! $request->filled('customer_name') && $request->filled('client_name')) {
            $normalized['customer_name'] = $request->input('client_name');
        }
        if (! $request->filled('customer_phone') && $request->filled('client_phone')) {
            $normalized['customer_phone'] = $request->input('client_phone');
        }

        $customerId = $normalized['customer_id'] ?? $request->input('customer_id');
        if (! empty($customerId) && (! $request->filled('customer_name') && empty($normalized['customer_name']))) {
            $existingCust = Customer::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->find($customerId);
            if ($existingCust) {
                $normalized['customer_name'] = $existingCust->name;
                if (! $request->filled('customer_phone') && empty($normalized['customer_phone'])) {
                    $normalized['customer_phone'] = $existingCust->phone;
                }
            }
        }
        if (! empty($normalized)) {
            $request->merge($normalized);
        }

        $validator = Validator::make($request->all(), [
            'service_id' => 'required|integer',
            'specialist_id' => 'required|string',
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'nullable|string|max:50',
            'customer_id' => 'nullable|integer',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
            'advance_deposit' => 'nullable|numeric|min:0',
            'advance_paid' => 'nullable|numeric|min:0',
            'deposit_payment_method' => 'nullable|string|max:50',
            'chair_label' => 'nullable|string|max:80',
            'custom_fields' => 'nullable',
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

        if (! $service) {
            return response()->json(['success' => false, 'error' => 'Selected salon service is unavailable.'], 422);
        }
        if (! $specialist) {
            return response()->json(['success' => false, 'error' => 'Selected stylist or specialist is unavailable.'], 422);
        }

        $timezone = $company->resolveTimezone();
        $durationMinutes = (int) ($service->duration_minutes ?: 30);
        $startsAt = Carbon::createFromFormat(
            'Y-m-d H:i',
            $request->input('appointment_date').' '.$request->input('appointment_time'),
            $timezone
        )->utc();
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

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

        $advancePaid = (float) $request->input('advance_deposit', $request->input('advance_paid', 0));
        $customer = null;
        if ($request->filled('customer_id')) {
            $customer = Customer::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->find($request->integer('customer_id'));
        }
        if (! $customer) {
            $customer = Customer::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where(function ($query) use ($request) {
                    $request->filled('customer_phone')
                        ? $query->where('phone', $request->input('customer_phone'))
                        : $query->where('name', $request->input('customer_name'));
                })
                ->first();
        }
        $customer ??= Customer::create([
            'company_id' => $company->id,
            'name' => $request->input('customer_name'),
            'phone' => $request->input('customer_phone'),
        ]);

        $customFields = $request->input('custom_fields');
        if (is_string($customFields)) {
            $decoded = json_decode($customFields, true);
            $customFields = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($customFields)) {
            $customFields = [];
        }

        $appointment = SalonAppointment::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'appointment_number' => app(DocumentNumberService::class)->next($company, 'salon', $startsAt),
            'customer_id' => $customer->id,
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
            'chair_label' => $request->input('chair_label'),
            'custom_fields' => $customFields,
        ]);

        AuditLog::record('salon.appointment_created', $company->id, $this->resolveUser($request, $company)?->id, [
            'appointment_id' => $appointment->id,
            'specialist_id' => $specialist->id,
            'starts_at' => $startsAt->toIso8601String(),
        ]);

        $appointmentDateStr = $startsAt->copy()->setTimezone($timezone)->toDateString();

        return response()->json([
            'success' => true,
            'message' => 'Appointment booked successfully.',
            'action' => 'toast_and_navigate',
            'route' => "/api/tenant/views/salon-calendar?date={$appointmentDateStr}",
            'appointment' => $this->presentAppointment($appointment->load(['service', 'specialist']), $timezone),
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
            'status' => 'required|in:scheduled,checked_in,in_progress,completed,cancelled,no_show',
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

        $appointment = null;
        if ($request->filled('appointment_id')) {
            $appointment = SalonAppointment::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->with(['service', 'customer'])
                ->find($request->integer('appointment_id'));

            if (! $appointment) {
                return response()->json(['success' => false, 'error' => 'Appointment not found.'], 404);
            }
        }

        // Appointment drawers can be opened from a generic SDUI timeline,
        // where the service is rendered from server data rather than added to
        // the native LocalCart. Always restore that booked service into the
        // canonical POS items array before validation. The explicit payload
        // emitted by UniversalPosBuilder is the primary path; this is the
        // authoritative server fallback for installed/older clients.
        if ($appointment && empty($request->input('items'))) {
            if (! $appointment->service) {
                return response()->json(['success' => false, 'error' => 'The booked service is no longer available.'], 422);
            }

            $request->merge([
                'items' => [[
                    'product_id' => $appointment->product_id,
                    'item_id' => $appointment->product_id,
                    'id' => $appointment->product_id,
                    'type' => 'service',
                    'name' => $appointment->service->name,
                    'unit_price' => (float) $appointment->service->sale_price,
                    'price' => (float) $appointment->service->sale_price,
                    'quantity' => 1,
                    'staff_id' => $appointment->specialist_id,
                ]],
                'customer_id' => $request->input('customer_id') ?: $appointment->customer_id,
                'customer_name' => $request->input('customer_name') ?: $appointment->customer_name,
                'customer_phone' => $request->input('customer_phone') ?: $appointment->customer_phone,
            ]);
        }

        // Accept both the native POS names and the public/core POS aliases
        // (id/item_id, price, staff_id) while keeping one validator and one
        // settlement implementation downstream.
        $normalizedItems = collect((array) $request->input('items', []))
            ->map(function ($item, int $index) use ($request) {
                if (! is_array($item)) {
                    return $item;
                }

                $staffId = $item['staff_id'] ?? $item['specialist_id'] ?? null;
                if ($staffId && ! $request->filled("line_specialist_{$index}")) {
                    $request->merge(["line_specialist_{$index}" => $staffId]);
                }

                return array_merge($item, [
                    'product_id' => $item['product_id'] ?? $item['item_id'] ?? $item['id'] ?? null,
                    'quantity' => $item['quantity'] ?? $item['qty'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? $item['price'] ?? null,
                ]);
            })
            ->all();
        $request->merge(['items' => $normalizedItems]);

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
            'follow_up_days' => 'nullable|integer|min:1|max:3650',
            'follow_up_message' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $items = $request->input('items', []);
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
                        ->lockForUpdate()
                        ->find($productId);

                    if (! $product) {
                        throw new \InvalidArgumentException("Service or product with ID {$productId} not found.");
                    }

                    if ((float) $product->current_stock < $qty) {
                        throw new \InvalidArgumentException("Insufficient stock for {$product->name}.");
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
                        'follow_up_days' => $product->follow_up_days,
                        'line_type' => $product->duration_minutes ? 'service' : 'product',
                    ];
                }

                $discount = (float) $request->input('discount', 0);
                $taxTotals = app(TaxCalculationService::class)->calculateCartTotals($saleLineItems, $company, null, $discount);
                $saleLineItems = $taxTotals['items'];
                $discount = (float) $taxTotals['discount'];
                $netAmount = (float) $taxTotals['total'];
                $totalRevenue = (float) $taxTotals['total'];
                $paymentMethod = strtolower((string) $request->input('payment_method', $request->input('selected_payment_method', 'cash')));
                if ($paymentMethod === 'upi') {
                    $paymentMethod = 'transfer';
                }
                $isCredit = in_array($paymentMethod, ['credit', 'khata', 'due'], true);
                $advanceApplied = $appointment ? min((float) ($appointment->advance_paid ?? 0), $netAmount) : 0.0;
                $balanceToCollect = max(0, round($netAmount - $advanceApplied, 2));
                $payments = $request->input('payments');
                $collectedNow = $isCredit
                    ? 0.0
                    : (! empty($payments) && is_array($payments)
                        ? min($balanceToCollect, collect($payments)->sum(fn ($payment) => max(0, (float) ($payment['amount'] ?? 0))))
                        : $balanceToCollect);
                $paidAmount = min($netAmount, $advanceApplied + $collectedNow);
                $dueAmount = max(0, round($netAmount - $paidAmount, 2));
                $paymentStatus = $dueAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending');
                $cashRegister = CashRegister::openFor($company->id);

                $saleNumber = app(DocumentNumberService::class)->next($company, 'invoice');
                $stylistIds = collect($saleLineItems)->pluck('specialist_id')->filter()->unique()->values()->all();
                $customerId = $request->input('customer_id') ?: $appointment?->customer_id;
                $customerName = trim((string) ($request->input('customer_name') ?: ($appointment?->customer_name ?? '')));
                if ($customerId && ! Customer::withoutGlobalScope('company')->where('company_id', $company->id)->whereKey($customerId)->exists()) {
                    $customerId = null;
                }
                if (! $customerId && $customerName !== '' && $customerName !== 'Walk-in Customer') {
                    $customer = Customer::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->where(function ($query) use ($request, $customerName, $appointment) {
                            $phone = $request->input('customer_phone') ?: $appointment?->customer_phone;
                            $phone ? $query->where('phone', $phone) : $query->where('name', $customerName);
                        })
                        ->first();
                    $customer ??= Customer::create([
                        'company_id' => $company->id,
                        'name' => $customerName,
                        'phone' => $request->input('customer_phone') ?: $appointment?->customer_phone,
                    ]);
                    $customerId = $customer->id;
                }

                $sale = Sale::create([
                    'company_id' => $company->id,
                    'sale_number' => $saleNumber,
                    'customer_id' => $customerId,
                    'customer_name' => $customerName ?: 'Walk-in Customer',
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
                    'module_type' => 'salon',
                    'reference_ticket_id' => $appointment?->id,
                    'stylist_ids' => $stylistIds,
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
                } elseif (! $isCredit && $collectedNow > 0) {
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
                    $appointment->update([
                        'status' => 'completed',
                        'sale_id' => $sale->id,
                        'customer_id' => $customerId ?: $appointment->customer_id,
                    ]);
                }

                return $sale;
            });

            AuditLog::record('salon.pos_sale_completed', $company->id, $user?->id, [
                'sale_id' => $sale->id,
                'total' => (float) $sale->total,
                'specialist_id' => $specialistId,
            ]);

            $whatsappUrl = app(InvoiceDeliveryService::class)->generateInvoiceWhatsAppUrl($sale, $request->input('customer_phone'));
            app(VerticalReminderService::class)->scheduleSalonFollowUp(
                $sale->loadMissing('customer'),
                (array) $sale->items,
                $appointment,
                $request->input('customer_phone'),
                $request->integer('follow_up_days') ?: null,
                $request->input('follow_up_message'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Salon counter sale completed successfully',
                'sale' => $sale,
                // In-app Post-Sale Action Sheet — no auto-launch keys.
                'invoice_number' => $sale->sale_number,
                'post_sale_sheet' => SchemaResponse::postSaleActionResponse($sale->fresh(['customer', 'company', 'payments']), $whatsappUrl),
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

    /**
     * Plain JSON service catalog for salon/services.
     * GET /api/tenant/salon/services
     */
    public function servicesIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $services = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->where(function ($q) {
                $q->where('type', 'service')
                    ->orWhere('duration_minutes', '>', 0)
                    ->orWhere('category_type', 'salon');
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'services' => $services,
        ]);
    }

    /**
     * Create a new salon service product.
     * POST /api/tenant/salon/services
     */
    public function servicesStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:1|max:1440',
            'description' => 'nullable|string|max:1000',
            'category_id' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $rate = (float) $request->input('price', $request->input('sale_price', 0));
        $duration = (int) ($request->input('duration_minutes', 30) ?: 30);
        $rawCategory = $request->input('category_id');
        $categoryId = (! empty($rawCategory) && is_numeric($rawCategory)) ? (int) $rawCategory : null;

        $service = Product::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'name' => $request->input('name'),
            'sku' => 'SRV-'.strtoupper(Str::random(6)),
            'barcode' => 'SRV-'.strtoupper(Str::random(8)),
            'type' => 'service',
            'category_type' => 'salon',
            'price' => $rate,
            'sale_price' => $rate,
            'purchase_price' => 0,
            'duration_minutes' => $duration,
            'description' => $request->input('description'),
            'category_id' => $categoryId,
            'active' => true,
            'manage_stock' => false,
            'stock' => 999999,
            'unit' => 'service',
        ]);

        AuditLog::record('salon.service_created', $company->id, $user?->id, [
            'product_id' => $service->id,
            'name' => $service->name,
            'price' => $rate,
            'duration_minutes' => $duration,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service created successfully.',
            'service' => $service,
        ], 201);
    }

    /**
     * SDUI Edit Sheet schema for a salon service.
     * GET /api/tenant/salon/services/{id}/edit-sheet
     */
    public function servicesEditSheet(Request $request, int|string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $service = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->findOrFail((int) $id);

        $currency = $company->currency_symbol ?: '$';

        $sheet = SchemaResponse::screen("Edit {$service->name}", [
            SchemaResponse::card([
                SchemaResponse::row([
                    SchemaResponse::icon('spa', ['color' => '#7c3aed', 'size' => 28]),
                    SchemaResponse::column([
                        SchemaResponse::text("Modify Service Rate & Duration", 'title_medium', ['bold' => true]),
                        SchemaResponse::text("Update the service name, duration in minutes, and base pricing.", 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                SchemaResponse::divider(),
                SchemaResponse::textInput('name', 'Service Name', $service->name),
                SchemaResponse::textInput('price', "Price / Rate ({$currency})", number_format((float) ($service->price ?: $service->sale_price), 2, '.', ''), ['keyboard_type' => 'decimal']),
                SchemaResponse::textInput('duration_minutes', 'Duration (Minutes)', (string) ($service->duration_minutes ?: 30), ['keyboard_type' => 'number']),
                SchemaResponse::textInput('description', 'Description', (string) ($service->description ?? ''), ['max_lines' => 2]),
                SchemaResponse::buttonPrimary('Save Changes', SchemaResponse::formSubmitAction(
                    "/api/tenant/salon/services/{$service->id}",
                    'POST',
                    'Service updated successfully.',
                    navigateBack: true,
                    reload: true
                ), 'save'),
            ]),
        ]);

        return response()->json($sheet);
    }

    /**
     * Update an existing salon service product.
     * POST/PUT /api/tenant/salon/services/{id}
     */
    public function servicesUpdate(Request $request, int|string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $service = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->findOrFail((int) $id);

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:150',
            'price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:1|max:1440',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $updates = [];
        if ($request->filled('name')) {
            $updates['name'] = $request->input('name');
        }
        if ($request->filled('price') || $request->filled('sale_price')) {
            $rate = (float) $request->input('price', $request->input('sale_price'));
            $updates['price'] = $rate;
            $updates['sale_price'] = $rate;
        }
        if ($request->filled('duration_minutes')) {
            $updates['duration_minutes'] = (int) $request->input('duration_minutes');
        }
        if ($request->has('description')) {
            $updates['description'] = $request->input('description');
        }
        if ($request->has('category_id')) {
            $rawCat = $request->input('category_id');
            $updates['category_id'] = (! empty($rawCat) && is_numeric($rawCat)) ? (int) $rawCat : null;
        }

        $updates['type'] = 'service';
        $updates['category_type'] = 'salon';

        $service->update($updates);

        AuditLog::record('salon.service_updated', $company->id, $user?->id, [
            'product_id' => $service->id,
            'updates' => $updates,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully.',
            'service' => $service->fresh(),
        ]);
    }

    /**
     * Delete / deactivate a salon service product.
     * DELETE /api/tenant/salon/services/{id}
     */
    public function servicesDestroy(Request $request, int|string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $service = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->findOrFail((int) $id);

        $service->update(['active' => false]);

        AuditLog::record('salon.service_deleted', $company->id, $user?->id, [
            'product_id' => $service->id,
            'name' => $service->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service removed from catalog.',
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
            'custom_fields' => $appointment->custom_fields ?? [],
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
