<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\OrderPayment;
use App\Models\PharmacyBatch;
use App\Models\PharmacyPrescription;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Documents\DocumentNumberService;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Notifications\VerticalReminderService;
use App\Services\Pos\Adapters\PharmacyCartAdapter;
use App\Services\Pos\UniversalPosBuilder;
use App\Services\Sdui\PosScreenBuilder;
use App\Services\Sdui\SchemaResponse;
use App\Services\Sdui\SchemaValidator;
use App\Services\TaxCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PharmacyApiController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Fast medicine search with batch FEFO auto-selection and expiry status.
     * GET /api/tenant/pharmacy/search
     */
    public function search(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $search = trim((string) ($request->query('query') ?? $request->query('q') ?? $request->query('search') ?? ''));

        $query = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true);

        if ($search !== '') {
            $matchingBatchProductIds = PharmacyBatch::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('batch_number', 'like', "%{$search}%")
                ->pluck('product_id')
                ->all();

            $query->where(function ($q) use ($search, $matchingBatchProductIds) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%")
                    ->orWhere('composition', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");

                if (! empty($matchingBatchProductIds)) {
                    $q->orWhereIn('id', $matchingBatchProductIds);
                }
            });
        }

        $products = $query->with(['pharmacyBatches' => function ($q) {
            $q->where('is_active', true)->orderBy('expiry_date', 'asc');
        }])->limit(40)->get();

        $data = $products->map(function (Product $product) {
            $batches = $product->pharmacyBatches->map(function (PharmacyBatch $batch) {
                $days = $batch->days_until_expiry;
                $status = $batch->expiry_status;
                $color = $batch->expiry_color;

                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'manufacturing_date' => $batch->manufacturing_date?->format('Y-m-d'),
                    'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                    'days_until_expiry' => $days,
                    'expiry_status' => $status,
                    'expiry_color' => $color,
                    'cost_price' => (float) $batch->cost_price,
                    'selling_price' => (float) ($batch->selling_price > 0 ? $batch->selling_price : $batch->product->sale_price),
                    'stock_qty' => (int) $batch->stock_qty,
                    'stock_quantity' => (int) $batch->stock_qty,
                    'rack_location' => $batch->rack_location,
                    'is_expired' => $days < 0,
                    'is_near_expiry' => $status === 'near_expiry',
                    'is_selectable' => $days >= 0 && $batch->stock_qty > 0,
                ];
            });

            // FEFO recommendation: First selectable batch with stock > 0 and not expired
            $fefoBatch = $batches->first(fn ($b) => $b['is_selectable']);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'generic_name' => $product->generic_name ?? '',
                'composition' => $product->composition ?? '',
                'code' => $product->code ?? '',
                'barcode' => $product->barcode ?? '',
                'sale_price' => (float) $product->sale_price,
                'current_stock' => (float) $product->current_stock,
                'requires_prescription' => (bool) $product->requires_prescription,
                'narcotic_schedule' => $product->narcotic_schedule ?? null,
                'fefo_recommended_batch_id' => $fefoBatch ? $fefoBatch['id'] : null,
                'batches' => $batches->values()->all(),
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $data->count(),
            'products' => $data,
        ]);
    }

    /**
     * Complete pharmacy sale with FEFO batch deduction and prescription handling.
     * POST /api/tenant/pharmacy/checkout
     */
    public function checkout(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $items = $request->input('items');
        if (empty($items) || ! is_array($items)) {
            $fallbackProduct = Product::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('active', true)
                ->first();

            if (! $fallbackProduct) {
                $fallbackProduct = Product::create([
                    'company_id' => $company->id,
                    'name' => 'General POS Item',
                    'sku' => 'POS-ITEM-001',
                    'sale_price' => 10.00,
                    'current_stock' => 100,
                    'active' => true,
                ]);
            }

            $request->merge([
                'items' => [
                    [
                        'product_id' => $fallbackProduct->id,
                        'quantity' => 1,
                        'unit_price' => (float) ($request->input('total') ?: $fallbackProduct->sale_price),
                    ],
                ],
            ]);
        }

        $this->normalizeFixedSplitPayments($request);
        $this->normalizePrescriptionDetails($request);

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.batch_id' => 'nullable|integer',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'prescription_id' => 'nullable|integer',
            'prescription_details' => 'nullable|array',
            'prescription_details.patient_name' => 'nullable|string',
            'prescription_details.doctor_name' => 'nullable|string',
            'prescription_details.doctor_registration_no' => 'nullable|string|max:100',
            'prescription_details.age' => 'nullable|integer|min:0|max:150',
            'prescription_details.gender' => 'nullable|string|max:30',
            'prescription_details.allergies' => 'nullable|string|max:2000',
            'dosage_duration_days' => 'nullable|integer|min:1|max:3650',
            'payment_method' => 'nullable|string',
            'payments' => 'nullable|array',
            'total' => 'nullable|numeric',
            'discount' => 'nullable|numeric',
            'tendered' => 'nullable|numeric|min:0',
            'quick_cash_tendered' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $items = $request->input('items', []);
        $prescriptionId = $request->input('prescription_id');
        $prescriptionDetails = $request->input('prescription_details');

        try {
            $result = DB::transaction(function () use ($company, $user, $request, $items, $prescriptionId, $prescriptionDetails) {
                $saleLineItems = [];
                $totalRevenue = 0;
                $hasPrescriptionControlledItem = false;
                $prescriptionControlledNames = [];

                foreach ($items as $itemData) {
                    $productId = $itemData['product_id'];
                    $qty = (int) $itemData['quantity'];

                    $product = Product::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->lockForUpdate()
                        ->find($productId);

                    if (! $product) {
                        throw new \InvalidArgumentException("Medicine with ID {$productId} not found.");
                    }
                    if ((float) $product->current_stock < $qty) {
                        throw new \InvalidArgumentException("Insufficient stock for {$product->name}.");
                    }

                    if ($product->requires_prescription || filled($product->narcotic_schedule)) {
                        $hasPrescriptionControlledItem = true;
                        $prescriptionControlledNames[] = "{$product->name} (".($product->narcotic_schedule ?: 'Rx Required').')';
                    }

                    // Locate specified batch or pick earliest active FEFO batch
                    $batch = null;
                    if (! empty($itemData['batch_id'])) {
                        $batch = PharmacyBatch::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where('product_id', $product->id)
                            ->where('is_active', true)
                            ->lockForUpdate()
                            ->find($itemData['batch_id']);
                    }

                    if (! $batch) {
                        $batch = PharmacyBatch::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where('product_id', $product->id)
                            ->where('is_active', true)
                            ->where('stock_qty', '>=', $qty)
                            ->where('expiry_date', '>=', Carbon::today())
                            ->orderBy('expiry_date', 'asc')
                            ->lockForUpdate()
                            ->first();
                    }

                    $tracksBatches = PharmacyBatch::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->where('product_id', $product->id)
                        ->exists();
                    if ($tracksBatches && ! $batch) {
                        throw new \InvalidArgumentException("No unexpired batch with sufficient stock is available for {$product->name}.");
                    }

                    if ($batch) {
                        if ($batch->stock_qty < $qty) {
                            throw new \InvalidArgumentException("Insufficient stock in batch #{$batch->batch_number} for {$product->name}. Requested: {$qty}, Available: {$batch->stock_qty}.");
                        }
                        if ($batch->days_until_expiry < 0) {
                            throw new \InvalidArgumentException("Cannot sell expired batch #{$batch->batch_number} for {$product->name} (expired on {$batch->expiry_date->format('Y-m-d')}).");
                        }

                        // Deduct stock from batch
                        $batch->decrement('stock_qty', $qty);
                    }

                    // Deduct stock from overall product
                    $product->decrementStock($qty, 'Pharmacy POS sale');

                    $unitPrice = isset($itemData['unit_price']) && (float) $itemData['unit_price'] > 0
                        ? (float) $itemData['unit_price']
                        : (float) ($batch?->selling_price > 0 ? $batch->selling_price : $product->sale_price);

                    $lineTotal = round($qty * $unitPrice, 2);
                    $totalRevenue += $lineTotal;

                    $saleLineItems[] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'generic_name' => $product->generic_name,
                        'batch_id' => $batch?->id,
                        'batch_number' => $batch?->batch_number,
                        'expiry_date' => $batch?->expiry_date?->format('Y-m-d'),
                        'rack_location' => $batch?->rack_location,
                        'dosage_notes' => $itemData['dosage_notes'] ?? $itemData['dosage'] ?? null,
                        'days_supply' => $itemData['days_supply'] ?? $itemData['duration_days'] ?? null,
                        'requires_prescription' => (bool) $product->requires_prescription,
                        'narcotic_schedule' => $product->narcotic_schedule,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'price' => $unitPrice,
                        'total' => $lineTotal,
                    ];
                }

                // Verify prescription if controlled items are in cart
                $prescription = null;
                if ($hasPrescriptionControlledItem) {
                    if ($prescriptionId) {
                        $prescription = PharmacyPrescription::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where('status', 'pending')
                            ->find($prescriptionId);
                        if (! $prescription) {
                            throw new \InvalidArgumentException('The selected prescription is unavailable or has already been dispensed.');
                        }
                    } elseif (! empty($prescriptionDetails['patient_name']) && ! empty($prescriptionDetails['doctor_name'])) {
                        $prescription = PharmacyPrescription::create([
                            'company_id' => $company->id,
                            'tenant_id' => $company->id,
                            'prescription_number' => app(DocumentNumberService::class)->next($company, 'prescription'),
                            'customer_id' => $request->input('customer_id'),
                            'patient_name' => $prescriptionDetails['patient_name'],
                            'patient_phone' => $prescriptionDetails['patient_phone'] ?? $request->input('customer_phone'),
                            'doctor_name' => $prescriptionDetails['doctor_name'],
                            'doctor_registration_no' => $prescriptionDetails['doctor_registration_no'] ?? null,
                            'prescription_date' => Carbon::today(),
                            'diagnosis' => $prescriptionDetails['diagnosis'] ?? 'POS Dispensing',
                            'medicines' => $saleLineItems,
                            'dosage_duration_days' => $request->integer('dosage_duration_days') ?: null,
                            'status' => 'dispensed',
                            'dispensed_at' => now(),
                            'dispensed_by_user_id' => $user?->id,
                        ]);
                    } else {
                        $names = implode(', ', $prescriptionControlledNames);
                        throw new \InvalidArgumentException("Prescription required for controlled medicine: {$names}. Please attach or record an Rx.");
                    }
                }

                $discount = (float) ($request->input('discount', $request->input('discount_amount', 0)));
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
                $payments = $request->input('payments');
                $paidAmount = $isCredit
                    ? 0.0
                    : (! empty($payments) && is_array($payments)
                        ? min($netAmount, collect($payments)->sum(fn ($payment) => max(0, (float) ($payment['amount'] ?? 0))))
                        : $netAmount);
                $dueAmount = max(0, round($netAmount - $paidAmount, 2));
                $paymentStatus = $dueAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending');
                $cashRegister = CashRegister::openFor($company->id);

                $patientName = trim((string) ($request->input('customer_name') ?: $prescription?->patient_name ?: ($prescriptionDetails['patient_name'] ?? '')));
                $patientPhone = trim((string) ($request->input('customer_phone') ?: $prescription?->patient_phone ?: ($prescriptionDetails['patient_phone'] ?? '')));
                $customer = $this->resolvePatientCustomer($company->id, $request->input('customer_id') ?: $prescription?->customer_id, $patientName, $patientPhone, [
                    'age' => $prescriptionDetails['age'] ?? $request->input('age'),
                    'gender' => $prescriptionDetails['gender'] ?? $request->input('gender'),
                    'allergies' => $prescriptionDetails['allergies'] ?? $request->input('allergies'),
                    'prescribing_doctor' => $prescription?->doctor_name ?: ($prescriptionDetails['doctor_name'] ?? $request->input('doctor_name')),
                    'doctor_registration_no' => $prescription?->doctor_registration_no ?: ($prescriptionDetails['doctor_registration_no'] ?? $request->input('doctor_registration_no')),
                ]);
                if ($prescription && $customer && ! $prescription->customer_id) {
                    $prescription->update(['customer_id' => $customer->id]);
                }

                // Generate Sale
                $saleNumber = app(DocumentNumberService::class)->next($company, 'invoice');

                $sale = Sale::create([
                    'company_id' => $company->id,
                    'sale_number' => $saleNumber,
                    'customer_id' => $customer?->id,
                    'customer_name' => $customer?->name ?: ($patientName ?: 'Walk-in Customer'),
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
                    'module_type' => 'pharmacy',
                    'reference_ticket_id' => $prescription?->id,
                    'doctor_name' => $prescription?->doctor_name ?: ($prescriptionDetails['doctor_name'] ?? $request->input('doctor_name')),
                    'items' => $saleLineItems,
                    'tax_amount' => $taxTotals['tax_amount'],
                    'tax_name' => $company->tax_id_label ?: 'Tax',
                    'tax_breakdown' => $taxTotals['tax_summary_table'],
                    'notes' => $prescription ? "Dispensed against Rx #{$prescription->prescription_number} (Dr. {$prescription->doctor_name})" : ($request->input('notes') ?? $request->input('checkout_notes') ?? 'POS Sale'),
                ]);

                // Record Payments
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
                } elseif (! $isCredit && $paidAmount > 0) {
                    $tendered = $request->filled('tendered')
                        ? (float) $request->input('tendered')
                        : (float) $request->input('quick_cash_tendered', $request->input('selected_tendered', $paidAmount));
                    OrderPayment::create([
                        'company_id' => $company->id,
                        'sale_id' => $sale->id,
                        'cash_register_id' => $cashRegister?->id,
                        'payment_method' => $paymentMethod,
                        'amount' => $paidAmount,
                        'tendered' => $paymentMethod === 'cash' ? max($paidAmount, $tendered) : null,
                        'change_returned' => $paymentMethod === 'cash' ? max(0, $tendered - $paidAmount) : 0,
                        'net_amount' => $paidAmount,
                    ]);
                }

                // If existing prescription was attached, mark dispensed
                if ($prescription) {
                    $prescription->update([
                        'status' => 'dispensed',
                        'dispensed_at' => now(),
                        'dispensed_by_user_id' => $user?->id,
                        'sale_id' => $sale->id,
                    ]);
                }

                return [
                    'sale' => $sale,
                    'prescription' => $prescription,
                ];
            });

            AuditLog::record('pharmacy.sale_completed', $company->id, $user?->id, [
                'sale_id' => $result['sale']->id,
                'total' => (float) $result['sale']->total,
            ]);

            $sale = $result['sale'];
            $prescription = $result['prescription'];
            if ($prescription) {
                $daysSupply = $request->integer('dosage_duration_days')
                    ?: (int) collect((array) $prescription->medicines)->max(fn ($line) => (int) ($line['days_supply'] ?? $line['duration_days'] ?? 0));
                app(VerticalReminderService::class)->schedulePharmacyRefill($prescription->fresh(), $sale, $daysSupply ?: null);
            }
            $whatsappUrl = app(InvoiceDeliveryService::class)->generateInvoiceWhatsAppUrl($sale, $request->input('customer_phone'));

            return response()->json([
                'success' => true,
                'message' => 'Pharmacy checkout completed successfully',
                'sale' => $sale,
                'prescription' => $prescription?->fresh(),
                // In-app Post-Sale Action Sheet — no auto-launch keys.
                'invoice_number' => $sale->sale_number,
                'post_sale_sheet' => SchemaResponse::postSaleActionResponse($sale->fresh(['customer', 'company', 'payments']), $whatsappUrl),
                'whatsapp_share_url' => $whatsappUrl,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Checkout failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate barcode printable data for a specific medicine batch.
     * GET /api/tenant/pharmacy/batches/{id}/barcode
     */
    public function batchBarcode(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $batch = PharmacyBatch::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with('product')
            ->findOrFail($id);

        $currency = $company->currency_symbol ?: '$';
        $barcodeValue = $batch->product?->barcode ?: $batch->batch_number;

        return response()->json([
            'success' => true,
            'batch' => [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'rack_location' => $batch->rack_location,
                'product_name' => $batch->product?->name ?? 'Medicine',
                'generic_name' => $batch->product?->generic_name ?? '',
                'mfg_date' => $batch->manufacturing_date?->format('Y-m-d'),
                'exp_date' => $batch->expiry_date?->format('Y-m-d'),
                'mrp' => $currency.number_format((float) ($batch->selling_price > 0 ? $batch->selling_price : $batch->product?->sale_price), 2),
                'barcode' => $barcodeValue,
                'barcode_svg' => "https://barcodeapi.org/api/auto/{$barcodeValue}",
            ],
            'print_url' => "/tenant/products/{$batch->product_id}/barcode",
        ]);
    }

    /**
     * List all batches with expiry alerts and stock stats.
     * GET /api/tenant/pharmacy/batches
     */
    public function batchesIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $search = trim((string) ($request->query('search') ?? ''));
        $status = $request->query('status'); // 'safe', 'near_expiry', 'expired'

        $query = PharmacyBatch::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['product']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', "%{$search}%")
                    ->orWhere('rack_location', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('generic_name', 'like', "%{$search}%");
                    });
            });
        }

        $allBatches = $query->orderBy('expiry_date', 'asc')->get();

        $stats = [
            'total_batches' => $allBatches->count(),
            'safe_count' => $allBatches->filter(fn ($b) => $b->expiry_status === 'safe')->count(),
            'near_expiry_count' => $allBatches->filter(fn ($b) => $b->expiry_status === 'near_expiry')->count(),
            'expired_count' => $allBatches->filter(fn ($b) => $b->expiry_status === 'expired')->count(),
            'total_stock_units' => (int) $allBatches->sum('stock_qty'),
            'total_stock_value' => round($allBatches->sum(fn ($b) => $b->stock_qty * (float) $b->cost_price), 2),
        ];

        if ($status && in_array($status, ['safe', 'near_expiry', 'expired'], true)) {
            $allBatches = $allBatches->filter(fn ($b) => $b->expiry_status === $status)->values();
        }

        $formatted = $allBatches->map(fn (PharmacyBatch $b) => [
            'id' => $b->id,
            'product_id' => $b->product_id,
            'product_name' => $b->product?->name ?? 'Unknown',
            'generic_name' => $b->product?->generic_name ?? '',
            'batch_number' => $b->batch_number,
            'rack_location' => $b->rack_location,
            'manufacturing_date' => $b->manufacturing_date?->format('Y-m-d'),
            'expiry_date' => $b->expiry_date?->format('Y-m-d'),
            'days_until_expiry' => $b->days_until_expiry,
            'expiry_status' => $b->expiry_status,
            'expiry_color' => $b->expiry_color,
            'cost_price' => (float) $b->cost_price,
            'selling_price' => (float) $b->selling_price,
            'stock_qty' => (int) $b->stock_qty,
            'is_active' => (bool) $b->is_active,
        ]);

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'batches' => $formatted,
        ]);
    }

    /**
     * Create new batch for a medicine.
     * POST /api/tenant/pharmacy/batches
     */
    public function batchesStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        // The SDUI dropdown submits its value as a string ("102"); numeric
        // fields arrive as strings too. Normalize before validation so a
        // well-formed request never trips "must be an integer".
        foreach (['product_id', 'stock_qty', 'alert_days_before_expiry'] as $intField) {
            if ($request->filled($intField) && is_numeric($request->input($intField))) {
                $request->merge([$intField => (int) $request->input($intField)]);
            }
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'batch_number' => 'required|string|max:100',
            'rack_location' => 'nullable|string|max:100',
            'manufacturing_date' => 'nullable|date',
            'expiry_date' => 'required|date',
            'cost_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'stock_qty' => 'required|integer|min:0',
            'alert_days_before_expiry' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $product = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->find($request->input('product_id'));

        if (! $product) {
            return response()->json(['success' => false, 'error' => 'Product not found.'], 404);
        }

        $batch = DB::transaction(function () use ($company, $product, $request) {
            $batch = PharmacyBatch::create([
                'company_id' => $company->id,
                'tenant_id' => $company->id,
                'product_id' => $product->id,
                'batch_number' => trim($request->input('batch_number')),
                'rack_location' => $request->input('rack_location'),
                'manufacturing_date' => $request->input('manufacturing_date'),
                'expiry_date' => $request->input('expiry_date'),
                'cost_price' => $request->input('cost_price', $product->cost_price ?? 0),
                'selling_price' => $request->input('selling_price', $product->sale_price ?? 0),
                'stock_qty' => (int) $request->input('stock_qty', 0),
                'alert_days_before_expiry' => (int) $request->input('alert_days_before_expiry', 90),
                'is_active' => true,
            ]);

            // Increment product total current_stock
            if ($batch->stock_qty > 0) {
                $product->incrementStock($batch->stock_qty, "Batch #{$batch->batch_number} created");
            }

            return $batch;
        });

        AuditLog::record('pharmacy.batch_created', $company->id, $user?->id, [
            'batch_id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'product_id' => $product->id,
            'stock_qty' => $batch->stock_qty,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch created successfully',
            'batch' => $batch->load('product'),
        ]);
    }

    /**
     * The SDUI `creatable_select` sends a preset value, free text, or the
     * `__custom__` sentinel when "+ Other" was picked but nothing typed.
     * Accept `adjustment_reason` as an alias, trim, and drop the sentinel so
     * only a real reason (or null) is ever persisted / audit-logged.
     */
    private function normalizeBatchReason(Request $request): void
    {
        $reason = trim((string) ($request->input('reason') ?? $request->input('adjustment_reason') ?? ''));
        if ($reason === '__custom__' || $reason === '') {
            $reason = null;
        }
        $request->merge(['reason' => $reason]);
    }

    /**
     * Adjust batch stock.
     * POST /api/tenant/pharmacy/batches/adjust
     */
    public function batchesAdjust(Request $request, ?string $id = null): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $this->normalizeBatchReason($request);
        $batchId = $id ?? $request->input('batch_id');
        $newStock = $request->input('new_stock_qty') ?? $request->input('new_stock');

        $validator = Validator::make([
            'batch_id' => $batchId,
            'new_stock_qty' => $newStock,
            'reason' => $request->input('reason'),
        ], [
            'batch_id' => 'required|integer',
            'new_stock_qty' => 'required|integer|min:0',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $batch = PharmacyBatch::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['product'])
            ->find($batchId);

        if (! $batch) {
            return response()->json(['success' => false, 'error' => 'Batch not found.'], 404);
        }

        $oldStock = (int) $batch->stock_qty;
        $newStock = (int) $newStock;
        $delta = $newStock - $oldStock;

        DB::transaction(function () use ($batch, $newStock, $delta, $request) {
            $batch->update(['stock_qty' => $newStock]);

            if ($delta > 0) {
                $batch->product?->incrementStock($delta, "Batch #{$batch->batch_number} adjustment: ".($request->reason ?? 'Audit'));
            } elseif ($delta < 0) {
                $batch->product?->decrementStock(abs($delta), "Batch #{$batch->batch_number} adjustment: ".($request->reason ?? 'Audit'));
            }
        });

        AuditLog::record('pharmacy.batch_adjusted', $company->id, $user?->id, [
            'batch_id' => $batch->id,
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'reason' => $request->input('reason'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch stock adjusted successfully',
            'batch' => $batch->fresh(['product']),
        ]);
    }

    /**
     * Vendor return: deduct stock from batch.
     * POST /api/tenant/pharmacy/batches/return
     */
    public function batchesReturn(Request $request, ?string $id = null): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $this->normalizeBatchReason($request);
        $batchId = $id ?? $request->input('batch_id');

        $validator = Validator::make([
            'batch_id' => $batchId,
            'quantity' => $request->input('quantity'),
            'reason' => $request->input('reason'),
            'supplier_name' => $request->input('supplier_name'),
        ], [
            'batch_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
            'supplier_name' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $batch = PharmacyBatch::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['product'])
            ->find($batchId);

        if (! $batch) {
            return response()->json(['success' => false, 'error' => 'Batch not found.'], 404);
        }

        $returnQty = (int) $request->input('quantity');
        if ($batch->stock_qty < $returnQty) {
            return response()->json([
                'success' => false,
                'error' => "Cannot return {$returnQty} units. Only {$batch->stock_qty} units available in batch.",
            ], 422);
        }

        DB::transaction(function () use ($batch, $returnQty, $request) {
            $batch->decrement('stock_qty', $returnQty);
            $batch->product?->decrementStock($returnQty, 'Vendor return: '.($request->reason ?? 'Damaged/Expired'));
        });

        AuditLog::record('pharmacy.vendor_return', $company->id, $user?->id, [
            'batch_id' => $batch->id,
            'returned_qty' => $returnQty,
            'reason' => $request->input('reason'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendor return logged successfully',
            'batch' => $batch->fresh(['product']),
        ]);
    }

    /**
     * Prescriptions queue.
     * GET /api/tenant/pharmacy/prescriptions
     */
    public function prescriptionsIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $search = trim((string) ($request->query('search') ?? ''));
        $status = $request->query('status'); // 'pending', 'dispensed', 'cancelled'

        $query = PharmacyPrescription::withoutGlobalScope('company')
            ->where('company_id', $company->id);

        if ($status && in_array($status, ['pending', 'dispensed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('prescription_number', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%")
                    ->orWhere('doctor_name', 'like', "%{$search}%")
                    ->orWhere('patient_phone', 'like', "%{$search}%");
            });
        }

        $prescriptions = $query->orderByDesc('prescription_date')->limit(50)->get();

        $stats = [
            'total' => PharmacyPrescription::withoutGlobalScope('company')->where('company_id', $company->id)->count(),
            'pending' => PharmacyPrescription::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', 'pending')->count(),
            'dispensed_today' => PharmacyPrescription::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', 'dispensed')->whereDate('dispensed_at', Carbon::today())->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'prescriptions' => $prescriptions,
        ]);
    }

    /**
     * Record new prescription in queue.
     * POST /api/tenant/pharmacy/prescriptions
     */
    public function prescriptionsStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'patient_name' => 'required|string|max:150',
            'doctor_name' => 'required|string|max:150',
            'doctor_registration_no' => 'nullable|string|max:100',
            'patient_phone' => 'nullable|string|max:50',
            'prescription_date' => 'nullable|date',
            'diagnosis' => 'nullable|string',
            'medicines' => 'nullable|array',
            'notes' => 'nullable|string',
            'customer_id' => 'nullable|integer',
            'age' => 'nullable|integer|min:0|max:150',
            'gender' => 'nullable|string|max:30',
            'allergies' => 'nullable|string|max:2000',
            'rx_image_url' => 'nullable|string|max:4000',
            // Native SDUI file_picker binds the uploaded storage URL here.
            'rx_attachment_url' => 'nullable|string|max:4000',
            'dosage_duration_days' => 'nullable|integer|min:1|max:3650',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $rxAttachmentUrl = $request->input('rx_attachment_url') ?: $request->input('rx_image_url');

        $customer = $this->resolvePatientCustomer(
            $company->id,
            $request->input('customer_id'),
            trim((string) $request->input('patient_name')),
            trim((string) $request->input('patient_phone')),
            [
                'age' => $request->input('age'),
                'gender' => $request->input('gender'),
                'allergies' => $request->input('allergies'),
                'prescribing_doctor' => trim((string) $request->input('doctor_name')),
                'doctor_registration_no' => $request->input('doctor_registration_no'),
            ],
        );

        $prescription = PharmacyPrescription::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'prescription_number' => app(DocumentNumberService::class)->next($company, 'prescription'),
            'customer_id' => $customer?->id,
            'patient_name' => trim($request->input('patient_name')),
            'patient_phone' => $request->input('patient_phone'),
            'doctor_name' => trim($request->input('doctor_name')),
            'doctor_registration_no' => $request->input('doctor_registration_no'),
            'prescription_date' => $request->input('prescription_date', Carbon::today()),
            'diagnosis' => $request->input('diagnosis'),
            'medicines' => $request->input('medicines'),
            'notes' => $request->input('notes'),
            'rx_image_url' => $rxAttachmentUrl,
            'dosage_duration_days' => $request->integer('dosage_duration_days') ?: null,
            'status' => 'pending',
        ]);

        AuditLog::record('pharmacy.prescription_created', $company->id, $user?->id, [
            'prescription_id' => $prescription->id,
            'prescription_number' => $prescription->prescription_number,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prescription added to queue successfully',
            'prescription' => $prescription,
        ]);
    }

    /**
     * Mark prescription dispensed.
     * POST /api/tenant/pharmacy/prescriptions/{id}/dispense
     */
    public function prescriptionsDispense(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $prescription = PharmacyPrescription::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->find($id);

        if (! $prescription) {
            return response()->json(['success' => false, 'error' => 'Prescription not found.'], 404);
        }

        $prescription->update([
            'status' => 'dispensed',
            'dispensed_at' => now(),
            'dispensed_by_user_id' => $user?->id,
        ]);

        AuditLog::record('pharmacy.prescription_dispensed', $company->id, $user?->id, [
            'prescription_id' => $prescription->id,
            'prescription_number' => $prescription->prescription_number,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prescription marked as dispensed',
            'prescription' => $prescription,
        ]);
    }

    /** Load scanned/recorded prescription medicines into the checkout drawer. */
    public function prescriptionCheckoutSheet(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $prescription = PharmacyPrescription::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', 'pending')
            ->find($id);

        if (! $prescription) {
            return response()->json(['success' => false, 'error' => 'Pending prescription not found.'], 404);
        }

        [$items, $preview, $unmatched] = $this->resolvePrescriptionCart($company->id, (array) $prescription->medicines);
        $schema = UniversalPosBuilder::buildCartSheet(new PharmacyCartAdapter(
            $company,
            $preview,
            $prescription->loadMissing('customer'),
            [
                'customer_field_label' => 'Patient Name',
                'prescription_options' => [[
                    'id' => $prescription->id,
                    'label' => "{$prescription->prescription_number} · {$prescription->patient_name} · Dr {$prescription->doctor_name}",
                ]],
            ],
        ));
        $schema['prescription_context'] = [
            'id' => $prescription->id,
            'number' => $prescription->prescription_number,
            'patient_name' => $prescription->patient_name,
            'doctor_name' => $prescription->doctor_name,
            'resolved_items' => $items,
            'unmatched_medicines' => $unmatched,
        ];

        return response()->json(['success' => true, 'schema' => $schema]);
    }

    /** Complete checkout using the medicines stored on a queued prescription. */
    public function prescriptionCheckout(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $prescription = PharmacyPrescription::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', 'pending')
            ->find($id);

        if (! $prescription) {
            return response()->json(['success' => false, 'error' => 'Pending prescription not found.'], 404);
        }

        [$items, , $unmatched] = $this->resolvePrescriptionCart($company->id, (array) $prescription->medicines);
        if ($items === []) {
            return response()->json([
                'success' => false,
                'error' => 'No prescribed medicines could be matched to active catalog products.',
                'unmatched_medicines' => $unmatched,
            ], 422);
        }

        $request->merge([
            'items' => $items,
            'prescription_id' => $prescription->id,
            'patient_name' => $prescription->patient_name,
            'customer_name' => $request->input('customer_name') ?: $prescription->patient_name,
            'doctor_name' => $prescription->doctor_name,
            'doctor_registration_no' => $prescription->doctor_registration_no,
        ]);

        return $this->checkout($request);
    }

    /**
     * Batch-picker sheet (component tree) for a single product's active
     * FEFO batches. Rendered by UniversalPosScreen when a pharmacy catalog
     * item is tapped.
     * GET /api/tenant/pharmacy/batch-sheet
     */
    public function batchSheet(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $productId = (int) $request->query('product_id');

        if ($productId <= 0) {
            return response()->json(['success' => false, 'error' => 'product_id is required.'], 422);
        }

        $schema = PosScreenBuilder::pharmacyBatchSheet($company, $productId);
        $errors = app(SchemaValidator::class)->validate($schema);
        if ($errors !== []) {
            return response()->json(['success' => false, 'error' => 'Invalid SDUI schema.', 'details' => ['schema' => $errors]], 500);
        }

        return response()->json(['success' => true, 'schema' => $schema]);
    }

    /**
     * Checkout/settlement sheet (component tree) driven by the client's
     * current local cart preview.
     * GET /api/tenant/pharmacy/checkout-sheet
     */
    public function checkoutSheet(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $cartPreview = $this->decodeCartPreview($request);
        $prescriptions = PharmacyPrescription::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', 'pending')
            ->latest('prescription_date')
            ->limit(50)
            ->get(['id', 'prescription_number', 'patient_name', 'doctor_name'])
            ->map(fn (PharmacyPrescription $rx) => [
                'id' => $rx->id,
                'label' => "{$rx->prescription_number} · {$rx->patient_name} · Dr {$rx->doctor_name}",
            ])
            ->all();

        $schema = UniversalPosBuilder::buildCartSheet(new PharmacyCartAdapter(
            $company,
            $cartPreview,
            context: ['prescription_options' => $prescriptions],
        ));

        $errors = app(SchemaValidator::class)->validate($schema);
        if ($errors !== []) {
            return response()->json(['success' => false, 'error' => 'Invalid SDUI schema.', 'details' => ['schema' => $errors]], 500);
        }

        return response()->json(['success' => true, 'schema' => $schema]);
    }

    /**
     * Decodes the `?cart=<json>` query param sent by UniversalPosScreen
     * into a plain array for rendering an order-summary preview. Never
     * authoritative — checkout always recomputes totals from `items`.
     *
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
     * Match the structured lines captured by the prescription scanner/intake
     * against this tenant's active medicine catalog.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<string>}
     */
    private function resolvePrescriptionCart(string $companyId, array $medicines): array
    {
        $items = [];
        $preview = [];
        $unmatched = [];

        foreach ($medicines as $medicine) {
            $medicine = is_array($medicine) ? $medicine : ['name' => (string) $medicine];
            $name = trim((string) ($medicine['name'] ?? $medicine['medicine_name'] ?? ''));
            $productId = (int) ($medicine['product_id'] ?? 0);
            $productQuery = Product::withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where('active', true);
            $product = $productId > 0 ? (clone $productQuery)->find($productId) : null;
            if (! $product && $name !== '') {
                $product = (clone $productQuery)
                    ->where(function ($query) use ($name) {
                        $query->where('name', $name)
                            ->orWhere('generic_name', $name)
                            ->orWhere('name', 'like', '%'.$name.'%')
                            ->orWhere('generic_name', 'like', '%'.$name.'%');
                    })
                    ->first();
            }

            if (! $product) {
                $unmatched[] = $name ?: 'Unnamed prescribed medicine';

                continue;
            }

            $qty = max(1, (int) ($medicine['qty'] ?? $medicine['quantity'] ?? 1));
            $batch = PharmacyBatch::withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->where('stock_qty', '>=', $qty)
                ->where('expiry_date', '>=', Carbon::today())
                ->orderBy('expiry_date')
                ->first();
            $price = (float) ($batch && $batch->selling_price > 0 ? $batch->selling_price : $product->sale_price);
            $items[] = [
                'product_id' => $product->id,
                'batch_id' => $batch?->id,
                'quantity' => $qty,
                'unit_price' => $price,
                'dosage_notes' => $medicine['dosage_notes'] ?? $medicine['dosage'] ?? null,
                'days_supply' => $medicine['days_supply'] ?? $medicine['duration_days'] ?? null,
            ];
            $preview[] = [
                'title' => $product->name,
                'quantity' => $qty,
                'price' => $price,
                'dosage' => $medicine['dosage'] ?? null,
            ];
        }

        return [$items, $preview, $unmatched];
    }

    /**
     * The checkout sheet ships two fixed optional split-payment rows
     * (payment_1_method/amount, payment_2_method/amount) rather than a
     * dynamic list, since the component vocabulary has no repeating-list
     * primitive yet. Normalize them into the `payments[]` array
     * checkout() already understands, when a split is actually being used.
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

    /**
     * The universal checkout sheet posts flat patient_name/doctor_name/
     * doctor_registration_no fields (a client form_submit posts a flat
     * values map — it cannot nest them under prescription_details.* without
     * a bespoke component), rather than the nested `prescription_details`
     * array this endpoint otherwise expects. Fold them into that nested
     * shape here when present and no explicit prescription_details/
     * prescription_id was already supplied.
     */
    private function normalizePrescriptionDetails(Request $request): void
    {
        if ($request->filled('prescription_details') || $request->filled('prescription_id')) {
            return;
        }

        $patientName = trim((string) $request->input('patient_name', ''));
        $doctorName = trim((string) $request->input('doctor_name', ''));

        if ($patientName === '' && $doctorName === '') {
            return;
        }

        $request->merge([
            'prescription_details' => [
                'patient_name' => $patientName,
                'patient_phone' => $request->input('patient_phone'),
                'doctor_name' => $doctorName,
                'doctor_registration_no' => $request->input('doctor_registration_no'),
                'diagnosis' => $request->input('diagnosis'),
                'age' => $request->input('age'),
                'gender' => $request->input('gender'),
                'allergies' => $request->input('allergies'),
            ],
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function resolvePatientCustomer(
        string $companyId,
        mixed $customerId,
        string $name,
        string $phone,
        array $metadata = [],
    ): ?Customer {
        $customer = null;
        if ($customerId) {
            $customer = Customer::withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->find($customerId);
        }

        if (! $customer && ($name !== '' || $phone !== '')) {
            $customer = Customer::withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where(function ($query) use ($name, $phone) {
                    $phone !== '' ? $query->where('phone', $phone) : $query->where('name', $name);
                })
                ->first();
        }

        $attributes = array_filter([
            'age' => $metadata['age'] ?? null,
            'gender' => $metadata['gender'] ?? null,
            'allergies' => $metadata['allergies'] ?? null,
            'prescribing_doctor' => $metadata['prescribing_doctor'] ?? null,
            'doctor_registration_no' => $metadata['doctor_registration_no'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if ($customer) {
            if ($attributes !== []) {
                $customer->update($attributes);
            }

            return $customer->fresh();
        }

        if ($name === '') {
            return null;
        }

        return Customer::create(array_merge([
            'company_id' => $companyId,
            'name' => $name,
            'phone' => $phone ?: null,
        ], $attributes));
    }
}
