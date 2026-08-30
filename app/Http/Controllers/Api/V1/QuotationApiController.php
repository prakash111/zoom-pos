<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Mobile/REST surface for quotations — a Sale row with operation_type
 * 'quotation' (see PosSyncApiController::syncPull/syncBatch for the delta-sync
 * equivalent of create/update; this controller adds the single-resource CRUD
 * and convert-to-sale actions the sync wire format doesn't provide).
 */
class QuotationApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $query = Sale::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('operation_type', 'quotation');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('sale_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $quotations = $query->latest('created_at')->get()->map(fn (Sale $q) => $this->present($q));

        return response()->json([
            'success' => true,
            'quotations' => $quotations,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $quote = $this->findQuote($company, $id);

        if (! $quote) {
            return response()->json(['success' => false, 'error' => 'Quotation not found.'], 404);
        }

        return response()->json(['success' => true, 'quotation' => $this->present($quote)]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'customer_id' => ['nullable', 'string'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable'],
            'items.*.product_id' => ['nullable'],
            'items.*.name' => ['required', 'string'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'valid_until' => ['nullable', 'date'],
            'external_id' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error while creating quotation.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        [$customerId, $customerName] = $this->resolveCustomer($company, $data);

        $items = $this->normalizeItems($data['items']);
        $subtotal = array_sum(array_column($items, 'total'));
        $discount = (float) ($data['discount'] ?? 0);
        $tax = (float) ($data['tax'] ?? 0);
        $total = max(0, $subtotal - $discount + $tax);

        $quote = Sale::create([
            'company_id' => $company->id,
            'external_id' => $data['external_id'] ?? Str::uuid()->toString(),
            'sale_number' => $this->nextQuoteNumber($company),
            'user_id' => $user?->id,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'total' => $total,
            'net_amount' => max(0, $total - $discount),
            'discount' => $discount,
            'tax_amount' => $tax,
            'status' => 'draft',
            'operation_type' => 'quotation',
            'notes' => $data['notes'] ?? null,
            'terms' => $data['terms'] ?? null,
            'due_date' => $data['valid_until'] ?? null,
            'items' => $items,
        ]);

        AuditLog::record('quotation.created', $company->id, $user?->id, ['quote_id' => $quote->id]);

        return response()->json([
            'success' => true,
            'message' => 'Quotation created.',
            'quotation' => $this->present($quote),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $quote = $this->findQuote($company, $id);

        if (! $quote) {
            return response()->json(['success' => false, 'error' => 'Quotation not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => ['nullable', 'string'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable'],
            'items.*.product_id' => ['nullable'],
            'items.*.name' => ['required', 'string'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'valid_until' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:draft,sent,accepted,declined,expired'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error while updating quotation.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        [$customerId, $customerName] = $this->resolveCustomer($company, $data);

        $items = $this->normalizeItems($data['items']);
        $subtotal = array_sum(array_column($items, 'total'));
        $discount = (float) ($data['discount'] ?? 0);
        $tax = (float) ($data['tax'] ?? 0);
        $total = max(0, $subtotal - $discount + $tax);

        $quote->update([
            'customer_id' => $customerId,
            'customer_name' => $customerName ?? $quote->customer_name,
            'total' => $total,
            'net_amount' => max(0, $total - $discount),
            'discount' => $discount,
            'tax_amount' => $tax,
            'status' => $data['status'] ?? $quote->status,
            'notes' => $data['notes'] ?? $quote->notes,
            'terms' => $data['terms'] ?? $quote->terms,
            'due_date' => $data['valid_until'] ?? $quote->due_date,
            'items' => $items,
        ]);

        AuditLog::record('quotation.updated', $company->id, $user?->id, ['quote_id' => $quote->id]);

        return response()->json([
            'success' => true,
            'message' => 'Quotation updated.',
            'quotation' => $this->present($quote->fresh()),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $quote = $this->findQuote($company, $id);

        if (! $quote) {
            return response()->json(['success' => false, 'error' => 'Quotation not found.'], 404);
        }

        $quote->delete();
        AuditLog::record('quotation.deleted', $company->id, $user?->id, ['quote_id' => $id]);

        return response()->json(['success' => true, 'message' => 'Quotation deleted.']);
    }

    public function convert(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $quote = $this->findQuote($company, $id);

        if (! $quote) {
            return response()->json(['success' => false, 'error' => 'Quotation not found.'], 404);
        }

        if ($quote->status === 'converted') {
            return response()->json(['success' => false, 'error' => 'Quotation was already converted.'], 422);
        }

        $sale = DB::transaction(function () use ($quote, $company, $user) {
            $items = is_array($quote->items) ? $quote->items : [];

            foreach ($items as $item) {
                $productId = $item['product_id'] ?? $item['id'] ?? null;
                $qty = (float) ($item['quantity'] ?? 0);

                if (! $productId || $qty <= 0) {
                    continue;
                }

                $product = Product::query()
                    ->withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where(function ($q) use ($productId) {
                        $q->where('id', $productId)->orWhere('external_id', (string) $productId);
                    })
                    ->first();

                $product?->decrementStock($qty, "Quotation #{$quote->sale_number} converted to sale");
            }

            $sale = Sale::create([
                'company_id' => $company->id,
                'external_id' => Str::uuid()->toString(),
                'sale_number' => 'INV-'.strtoupper(Str::random(8)),
                'user_id' => $user?->id,
                'customer_id' => $quote->customer_id,
                'customer_name' => $quote->customer_name,
                'total' => $quote->total,
                'net_amount' => $quote->net_amount,
                'discount' => $quote->discount,
                'tax_amount' => $quote->tax_amount,
                'status' => 'completed',
                'payment_status' => 'paid',
                'paid_amount' => $quote->total,
                'due_amount' => 0,
                'operation_type' => null,
                'notes' => $quote->notes,
                'items' => $items,
            ]);

            $quote->update(['status' => 'converted']);

            return $sale;
        });

        AuditLog::record('quotation.converted', $company->id, $user?->id, [
            'quote_id' => $quote->id,
            'sale_id' => $sale->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quotation converted to sale.',
            'sale' => [
                'id' => (string) ($sale->external_id ?: $sale->id),
                'server_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => (float) $sale->total,
            ],
        ]);
    }

    private function findQuote(Company $company, string $id): ?Sale
    {
        return Sale::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('operation_type', 'quotation')
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('external_id', $id);
            })
            ->first();
    }

    /** @return array{0: int|string|null, 1: string|null} */
    private function resolveCustomer(Company $company, array $data): array
    {
        $customerId = null;
        $customerName = $data['customer_name'] ?? null;

        if (! empty($data['customer_id'])) {
            $customer = Customer::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where(function ($q) use ($data) {
                    $q->where('id', $data['customer_id'])->orWhere('external_id', (string) $data['customer_id']);
                })
                ->first();

            if ($customer) {
                $customerId = $customer->id;
                $customerName = $customer->name;
            }
        }

        return [$customerId, $customerName];
    }

    private function normalizeItems(array $items): array
    {
        return array_map(function ($item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0);
            $lineTotal = round($qty * $price, 2);

            return [
                'id' => $item['id'] ?? $item['product_id'] ?? null,
                'product_id' => $item['id'] ?? $item['product_id'] ?? null,
                'name' => $item['name'] ?? 'Item',
                'price' => $price,
                'quantity' => $qty,
                'total' => $lineTotal,
            ];
        }, $items);
    }

    private function nextQuoteNumber(Company $company): string
    {
        $prefix = $company->quotation_prefix ?: 'QUO';

        return $prefix.'-'.strtoupper(Str::random(6));
    }

    private function present(Sale $quote): array
    {
        $items = is_array($quote->items) ? $quote->items : [];

        return [
            'id' => (string) ($quote->external_id ?: $quote->id),
            'server_id' => $quote->id,
            'quote_number' => $quote->sale_number,
            'customer_id' => $quote->customer_id ? (string) $quote->customer_id : null,
            'customer_name' => $quote->customer_name ?: 'Customer',
            'items' => $items,
            'discount' => (float) ($quote->discount ?? 0),
            'tax' => (float) ($quote->tax_amount ?? 0),
            'total' => (float) ($quote->total ?? 0),
            'notes' => $quote->notes ?? '',
            'terms' => $quote->terms ?? '',
            'valid_until' => $quote->due_date?->toIso8601String(),
            'status' => $quote->status ?: 'draft',
            'updated_at' => $quote->updated_at?->toIso8601String(),
            'created_at' => $quote->created_at?->toIso8601String(),
        ];
    }
}
