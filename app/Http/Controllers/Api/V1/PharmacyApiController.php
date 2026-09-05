<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OrderPayment;
use App\Models\PharmacyBatch;
use App\Models\PharmacyPrescription;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Sdui\PosScreenBuilder;
use App\Services\Sdui\SchemaValidator;
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
            'payment_method' => 'nullable|string',
            'payments' => 'nullable|array',
            'total' => 'nullable|numeric',
            'discount' => 'nullable|numeric',
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
                        ->find($productId);

                    if (! $product) {
                        throw new \InvalidArgumentException("Medicine with ID {$productId} not found.");
                    }

                    if ($product->requires_prescription) {
                        $hasPrescriptionControlledItem = true;
                        $prescriptionControlledNames[] = "{$product->name} (".($product->narcotic_schedule ?: 'Rx Required').')';
                    }

                    // Locate specified batch or pick earliest active FEFO batch
                    $batch = null;
                    if (! empty($itemData['batch_id'])) {
                        $batch = PharmacyBatch::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where('product_id', $product->id)
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
                            ->first();
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
                            ->find($prescriptionId);
                    } elseif (! empty($prescriptionDetails['patient_name']) && ! empty($prescriptionDetails['doctor_name'])) {
                        $prescriptionPrefix = 'RX-'.date('Ymd').'-';
                        $rxCount = PharmacyPrescription::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->whereDate('created_at', Carbon::today())
                            ->count() + 1;

                        $prescription = PharmacyPrescription::create([
                            'company_id' => $company->id,
                            'tenant_id' => $company->id,
                            'prescription_number' => $prescriptionPrefix.sprintf('%04d', $rxCount),
                            'customer_id' => $request->input('customer_id'),
                            'patient_name' => $prescriptionDetails['patient_name'],
                            'patient_phone' => $prescriptionDetails['patient_phone'] ?? $request->input('customer_phone'),
                            'doctor_name' => $prescriptionDetails['doctor_name'],
                            'doctor_registration_no' => $prescriptionDetails['doctor_registration_no'] ?? null,
                            'prescription_date' => Carbon::today(),
                            'diagnosis' => $prescriptionDetails['diagnosis'] ?? 'POS Dispensing',
                            'medicines' => $saleLineItems,
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
                $netAmount = max(0, $totalRevenue - $discount);
                $paymentMethod = strtolower((string) ($request->input('payment_method', 'cash')));

                // Generate Sale
                $prefix = $company->invoice_prefix ?: 'INV-';
                $saleCount = Sale::withoutGlobalScope('company')->where('company_id', $company->id)->count() + 1;
                $saleNumber = $prefix.sprintf('%04d', $saleCount);

                $sale = Sale::create([
                    'company_id' => $company->id,
                    'sale_number' => $saleNumber,
                    'customer_id' => $request->input('customer_id'),
                    'customer_name' => $request->input('customer_name') ?? $prescription?->patient_name ?? 'Walk-in Customer',
                    'user_id' => $user?->id,
                    'total' => $totalRevenue,
                    'discount' => $discount,
                    'net_amount' => $netAmount,
                    'paid_amount' => $netAmount,
                    'due_amount' => 0,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'status' => 'completed',
                    'operation_type' => 'sale',
                    'items' => $saleLineItems,
                    'notes' => $prescription ? "Dispensed against Rx #{$prescription->prescription_number} (Dr. {$prescription->doctor_name})" : ($request->input('notes') ?? $request->input('checkout_notes') ?? 'POS Sale'),
                ]);

                // Record Payments
                $payments = $request->input('payments');
                if (! empty($payments) && is_array($payments)) {
                    foreach ($payments as $pay) {
                        $amt = (float) ($pay['amount'] ?? 0);
                        if ($amt > 0) {
                            OrderPayment::create([
                                'company_id' => $company->id,
                                'sale_id' => $sale->id,
                                'payment_method' => strtolower((string) ($pay['method'] ?? 'cash')),
                                'amount' => $amt,
                                'net_amount' => $amt,
                            ]);
                        }
                    }
                } else {
                    OrderPayment::create([
                        'company_id' => $company->id,
                        'sale_id' => $sale->id,
                        'payment_method' => $paymentMethod,
                        'amount' => $netAmount,
                        'net_amount' => $netAmount,
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

            return response()->json([
                'success' => true,
                'message' => 'Pharmacy checkout completed successfully',
                'sale' => $result['sale'],
                'prescription' => $result['prescription'],
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

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer',
            'batch_number' => 'required|string|max:100',
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
     * Adjust batch stock.
     * POST /api/tenant/pharmacy/batches/adjust
     */
    public function batchesAdjust(Request $request, ?string $id = null): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

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
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $prescriptionPrefix = 'RX-'.date('Ymd').'-';
        $count = PharmacyPrescription::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereDate('created_at', Carbon::today())
            ->count() + 1;

        $prescription = PharmacyPrescription::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'prescription_number' => $prescriptionPrefix.sprintf('%04d', $count),
            'customer_id' => $request->input('customer_id'),
            'patient_name' => trim($request->input('patient_name')),
            'patient_phone' => $request->input('patient_phone'),
            'doctor_name' => trim($request->input('doctor_name')),
            'doctor_registration_no' => $request->input('doctor_registration_no'),
            'prescription_date' => $request->input('prescription_date', Carbon::today()),
            'diagnosis' => $request->input('diagnosis'),
            'medicines' => $request->input('medicines'),
            'notes' => $request->input('notes'),
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

        $schema = PosScreenBuilder::checkoutSheet(
            $company,
            '/api/tenant/pharmacy/checkout',
            $cartPreview,
            customerFieldLabel: 'Customer / Patient Name',
            collectPrescription: true,
        );

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
            ],
        ]);
    }
}
