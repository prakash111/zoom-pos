<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\CustomNotificationChannel;
use App\Models\Customer;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Delivery\MessageQueueService;
use App\Services\Delivery\WebhookDispatchService;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Sdui\PosScreenBuilder;
use App\Services\Sdui\SchemaValidator;
use App\Services\TaxCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleApiController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Send sale invoice via WhatsApp, SMS, or Email.
     * POST /api/tenant/sales/{id}/send-invoice
     */
    public function sendInvoice(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'channel' => ['nullable', 'string', 'in:whatsapp,sms,email,custom'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2500'],
            'channel_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed.',
                'details' => $validator->errors(),
            ], 422);
        }

        $sale = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                    ->orWhere('external_id', $id)
                    ->orWhere('sale_number', $id);
            })
            ->with(['customer', 'company'])
            ->first();

        if (! $sale) {
            return response()->json([
                'success' => false,
                'error' => "Sale transaction #{$id} not found.",
            ], 404);
        }

        $channel = strtolower($request->input('channel', 'whatsapp'));
        $recipient = trim((string) $request->input('recipient'));
        $customMessage = $request->input('message');

        $messageQueue = app(MessageQueueService::class);
        $deliveryService = app(InvoiceDeliveryService::class);

        if ($channel === 'email') {
            $email = $recipient ?: ($sale->customer?->email ?? '');
            if (empty($email)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Customer has no email address configured. Please provide an email recipient.',
                ], 422);
            }

            try {
                $result = $messageQueue->sendOrQueueEmail($sale, $email, $customMessage, true);
                AuditLog::record('invoice.dispatched', $company->id, $user?->id, [
                    'sale_id' => $sale->id,
                    'sale_number' => $sale->sale_number,
                    'channel' => 'email',
                    'recipient' => $email,
                    'status' => $result['status'],
                ]);

                return response()->json([
                    'success' => true,
                    'channel' => 'email',
                    'status' => $result['status'],
                    'message' => $result['status'] === 'sent'
                        ? "Invoice #{$sale->sale_number} sent to {$email}."
                        : "Invoice #{$sale->sale_number} queued for delivery to {$email}.",
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to deliver email invoice: '.$e->getMessage(),
                ], 500);
            }
        }

        if ($channel === 'sms') {
            $phone = $recipient ?: ($sale->customer?->phone ?? '');
            if (empty($phone)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Customer has no phone number configured. Please provide a phone recipient.',
                ], 422);
            }

            $smsChannel = CustomNotificationChannel::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->get()
                ->first(fn ($c) => $c->handlesEvent('invoice') && str_contains(strtolower($c->driver ?? $c->name), 'sms'));

            if ($smsChannel) {
                app(WebhookDispatchService::class)->dispatch($smsChannel, [
                    'customer_name' => $sale->customer?->name ?? $sale->customer_name ?? 'Customer',
                    'invoice_no' => $sale->sale_number,
                    'total' => (float) $sale->total,
                    'phone' => $phone,
                    'receipt_link' => route('sales.public', $sale->sale_number),
                ]);
            }

            AuditLog::record('invoice.dispatched', $company->id, $user?->id, [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'channel' => 'sms',
                'recipient' => $phone,
            ]);

            return response()->json([
                'success' => true,
                'channel' => 'sms',
                'message' => "SMS receipt notification dispatched to {$phone}.",
            ]);
        }

        // WhatsApp (default)
        $phone = $recipient ?: ($sale->customer?->phone ?? '');
        $whatsappUrl = $deliveryService->generateInvoiceWhatsAppUrl($sale, $phone ?: null, $customMessage);

        try {
            $result = $messageQueue->sendOrQueueWhatsApp($sale, $phone ?: '0000000000', $customMessage);
            AuditLog::record('invoice.dispatched', $company->id, $user?->id, [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'channel' => 'whatsapp',
                'recipient' => $phone,
                'status' => $result['status'] ?? 'manual_link',
            ]);

            return response()->json([
                'success' => true,
                'channel' => 'whatsapp',
                'status' => $result['status'] ?? 'manual_link',
                'url' => $result['url'] ?? $whatsappUrl,
                'whatsapp_url' => $result['url'] ?? $whatsappUrl,
                'message' => ($result['status'] ?? '') === 'sent'
                    ? "Invoice #{$sale->sale_number} delivered via WhatsApp."
                    : "Opening WhatsApp with receipt for #{$sale->sale_number}.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => true,
                'channel' => 'whatsapp',
                'status' => 'manual_link',
                'url' => $whatsappUrl,
                'whatsapp_url' => $whatsappUrl,
                'message' => "Invoice #{$sale->sale_number} ready to share via WhatsApp.",
            ]);
        }
    }

    /**
     * Get printable/downloadable PDF URLs for sale invoice.
     * POST /api/tenant/sales/{id}/print
     */
    public function printInvoice(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $sale = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                    ->orWhere('external_id', $id)
                    ->orWhere('sale_number', $id);
            })
            ->first();

        if (! $sale) {
            return response()->json([
                'success' => false,
                'error' => "Sale transaction #{$id} not found.",
            ], 404);
        }

        $printUrl = route('tenant.sales.pdf', ['sale' => $sale->id, 'download' => 0, 'embed' => 1]);
        $downloadUrl = route('tenant.sales.pdf', ['sale' => $sale->id, 'download' => 1]);

        return response()->json([
            'success' => true,
            'message' => "Invoice #{$sale->sale_number} ready for print.",
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'url' => $printUrl,
            'print_url' => $printUrl,
            'download_url' => $downloadUrl,
        ]);
    }

    /**
     * Get the Universal POS checkout sheet schema.
     * GET /api/tenant/pos/checkout-sheet or GET /api/app/pos/checkout-sheet
     */
    public function checkoutSheet(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $cartPreview = $this->decodeCartPreview($request);
        $module = $request->query('module', 'retail');
        $submitEndpoint = $request->is('api/app/*')
            ? '/api/app/pos/checkout'
            : '/api/tenant/pos/checkout';

        $schema = PosScreenBuilder::checkoutSheet(
            $company,
            $submitEndpoint,
            $cartPreview,
            customerFieldLabel: 'Customer Name',
            module: $module,
        );

        $errors = app(SchemaValidator::class)->validate($schema);
        if ($errors !== []) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid SDUI schema.',
                'details' => ['schema' => $errors],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'schema' => $schema,
        ]);
    }

    /**
     * Complete Universal POS checkout transaction.
     * POST /api/tenant/pos/checkout or POST /api/app/pos/checkout
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

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'customer_id' => 'nullable',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'payment_method' => 'nullable|string',
            'payments' => 'nullable|array',
            'discount' => 'nullable|numeric|min:0',
            'tendered' => 'nullable|numeric|min:0',
            'quick_cash_tendered' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $items = $request->input('items', []);

        try {
            $sale = DB::transaction(function () use ($company, $user, $request, $items) {
                $saleLineItems = [];
                $totalRevenue = 0;

                foreach ($items as $itemData) {
                    $productId = $itemData['product_id'];
                    $qty = (float) $itemData['quantity'];

                    $product = Product::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->find($productId);

                    if (! $product) {
                        throw new \InvalidArgumentException("Product with ID {$productId} not found.");
                    }

                    $product->decrementStock($qty, 'Universal POS Sale');

                    $unitPrice = isset($itemData['unit_price']) && (float) $itemData['unit_price'] >= 0
                        ? (float) $itemData['unit_price']
                        : (float) $product->sale_price;

                    $lineTotal = round($qty * $unitPrice, 2);
                    $totalRevenue += $lineTotal;

                    $saleLineItems[] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'price' => $unitPrice,
                        'total' => $lineTotal,
                    ];
                }

                $discount = self::resolveDiscountAmount($request, (float) array_sum(array_column($saleLineItems, 'total')));
                $taxTotals = app(TaxCalculationService::class)->calculateCartTotals($saleLineItems, $company, null, $discount);
                $saleLineItems = $taxTotals['items'];
                $discount = (float) $taxTotals['discount'];
                $netAmount = (float) $taxTotals['total'];
                $totalRevenue = (float) $taxTotals['total'];

                $paymentMethod = strtolower((string) $request->input('payment_method', $request->input('selected_payment_method', 'cash')));
                $payments = $request->input('payments');
                $isCredit = in_array($paymentMethod, ['credit', 'khata', 'due'], true);

                if (! empty($payments) && is_array($payments)) {
                    $collected = collect($payments)->sum(fn ($p) => max(0, (float) ($p['amount'] ?? 0)));
                    $paidAmount = min($netAmount, $collected);
                } elseif ($isCredit) {
                    $paidAmount = 0.0;
                } else {
                    $paidAmount = $netAmount;
                }

                $dueAmount = max(0, round($netAmount - $paidAmount, 2));
                $paymentStatus = $dueAmount <= 0.001 ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'pending');

                // Customer resolution
                $customerId = null;
                $customerName = trim((string) ($request->input('customer_name') ?: ''));

                if ($request->filled('customer_id')) {
                    $cust = Customer::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->where(function ($q) use ($request) {
                            $q->where('id', $request->input('customer_id'))
                                ->orWhere('external_id', (string) $request->input('customer_id'));
                        })
                        ->first();
                    if ($cust) {
                        $customerId = $cust->id;
                        $customerName = $cust->name;
                    }
                }

                if ($dueAmount > 0 && empty($customerId)) {
                    if ($customerName !== '' && $customerName !== 'Walk-in Customer') {
                        $cust = Customer::withoutGlobalScope('company')
                            ->where('company_id', $company->id)
                            ->where('name', $customerName)
                            ->first();

                        if (! $cust) {
                            $cust = Customer::create([
                                'company_id' => $company->id,
                                'name' => $customerName,
                                'phone' => $request->input('customer_phone') ?: null,
                            ]);
                        }
                        $customerId = $cust->id;
                    } else {
                        throw new \InvalidArgumentException('A customer must be selected for due, partial, or credit sales.');
                    }
                }

                $cashRegister = CashRegister::openFor($company->id);

                $prefix = $company->invoice_prefix ?: 'INV-';
                $saleCount = Sale::withoutGlobalScope('company')->where('company_id', $company->id)->count() + 1;
                $saleNumber = $prefix.sprintf('%04d', $saleCount);

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
                    'payment_method' => ! empty($payments) ? 'split' : $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'status' => 'completed',
                    'operation_type' => 'sale',
                    'items' => $saleLineItems,
                    'tax_amount' => $taxTotals['tax_amount'],
                    'tax_name' => $company->tax_id_label ?: 'Tax',
                    'tax_breakdown' => $taxTotals['tax_summary_table'],
                    'notes' => $request->input('notes') ?? $request->input('checkout_notes') ?? 'Universal POS Sale',
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
                                'payment_method' => strtolower((string) ($pay['method'] ?? $pay['payment_method'] ?? 'cash')),
                                'amount' => $amt,
                                'net_amount' => $amt,
                            ]);
                        }
                    }
                } elseif ($paymentMethod !== 'credit' && $paidAmount > 0) {
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

                return $sale;
            });

            AuditLog::record('pos.sale_completed', $company->id, $user?->id, [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => (float) $sale->total,
                'payment_method' => $sale->payment_method,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'POS sale completed successfully',
                'sale' => $sale->fresh(['customer', 'payments']),
                // In-app Post-Sale Action Sheet — no top-level `url` / `print_url`
                // / `whatsapp_url`, which the SDUI client auto-opens in an
                // external browser (bouncing to the web login page).
                'invoice_number' => $sale->sale_number,
                'post_sale_sheet' => \App\Services\Sdui\SchemaResponse::postSaleActionResponse(
                    $sale->fresh(['customer', 'company', 'payments']),
                    app(InvoiceDeliveryService::class)->generateInvoiceWhatsAppUrl($sale->fresh(), $sale->customer?->phone)
                ),
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
     * Park the active cart so it can be resumed later.
     * POST /api/tenant/pos/hold-order
     *
     * Called by the "Hold" action pill in the universal checkout drawer. It
     * accepts whatever the drawer currently holds (line items are optional —
     * a repair-ticket settlement has none of its own) and stores it as a
     * non-completed sale with status "on_hold", so it stays out of sales
     * reports and cash totals until it is picked back up.
     */
    public function holdOrder(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $rawItems = $request->input('items');
        $items = is_array($rawItems) ? array_values($rawItems) : [];

        $lineItems = [];
        $subtotal = 0.0;
        foreach ($items as $row) {
            $qty = max(0.0, (float) ($row['quantity'] ?? $row['qty'] ?? 0));
            $price = max(0.0, (float) ($row['unit_price'] ?? $row['price'] ?? 0));
            $lineTotal = round($qty * $price, 2);
            $subtotal += $lineTotal;
            $lineItems[] = [
                'product_id' => $row['product_id'] ?? $row['id'] ?? null,
                'name' => (string) ($row['name'] ?? $row['title'] ?? 'Item'),
                'quantity' => $qty ?: 1,
                'unit_price' => $price,
                'price' => $price,
                'total' => $lineTotal,
            ];
        }

        $prefix = $company->invoice_prefix ?: 'INV-';
        $seq = Sale::withoutGlobalScope('company')->where('company_id', $company->id)->count() + 1;

        $sale = Sale::create([
            'company_id' => $company->id,
            'sale_number' => $prefix.'HOLD-'.sprintf('%04d', $seq),
            'customer_id' => $request->filled('customer_id') ? $request->input('customer_id') : null,
            'customer_name' => trim((string) $request->input('customer_name')) ?: 'Walk-in Customer',
            'user_id' => $user?->id,
            'total' => round($subtotal, 2),
            'discount' => (float) $request->input('discount', 0),
            'net_amount' => round($subtotal, 2),
            'paid_amount' => 0,
            'due_amount' => round($subtotal, 2),
            'payment_method' => null,
            'payment_status' => 'pending',
            'status' => 'on_hold',
            'operation_type' => 'hold',
            'items' => $lineItems,
            'notes' => $request->input('notes') ?: 'Held from universal checkout drawer',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order held. Resume it from Held Orders.',
            'hold_id' => $sale->id,
            'hold_number' => $sale->sale_number,
        ]);
    }

    /**
     * Resolve the effective flat discount from a request that may carry
     * either a flat amount or a percentage (discount_type = flat|percent).
     */
    public static function resolveDiscountAmount(Request $request, float $subtotal): float
    {
        $value = (float) $request->input('discount', $request->input('discount_amount', 0));
        if ($value <= 0) {
            return 0.0;
        }

        if (strtolower((string) $request->input('discount_type', 'flat')) === 'percent') {
            return round($subtotal * min($value, 100) / 100, 2);
        }

        return $value;
    }

    /**
     * Decode query-string cart preview payload into structured rows.
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
     * Normalize fixed split payment fields (payment_1_amount, etc.) into payments array.
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
