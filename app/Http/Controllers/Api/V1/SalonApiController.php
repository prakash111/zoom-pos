<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sdui\PosScreenBuilder;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Salon & Service counter-sale POS: real service/retail catalog (see
 * PosScreenBuilder::salonPosScreen — services are Product rows with
 * duration_minutes set, no separate Service model), optional specialist
 * assignment, and checkout. Walk-in only — no appointment/booking
 * persistence (see PosScreenBuilder class doc and the project plan for
 * why that's explicitly out of scope for this pass).
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
            '/api/tenant/salon/pos-checkout',
            $cartPreview,
            customerFieldLabel: 'Client Name',
            specialistOptions: $specialists,
        );

        $errors = app(SchemaValidator::class)->validate($schema);
        if ($errors !== []) {
            return response()->json(['success' => false, 'error' => 'Invalid SDUI schema.', 'details' => ['schema' => $errors]], 500);
        }

        return response()->json(['success' => true, 'schema' => $schema]);
    }

    /**
     * Completes a salon counter sale (services and/or retail add-ons sold
     * as a walk-in, no appointment record involved). Optional
     * specialist_id is recorded in the Sale's notes only — it does not
     * feed commission reporting (Sale.user_id, the authenticated cashier,
     * remains the commission-report join key, consistent with every
     * other checkout endpoint).
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
            'payment_method' => 'nullable|string',
            'payments' => 'nullable|array',
            'discount' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $items = $request->input('items', []);
        $specialistId = $request->input('specialist_id');

        try {
            $sale = DB::transaction(function () use ($company, $user, $request, $items, $specialistId) {
                $specialist = null;
                if (! empty($specialistId)) {
                    $specialist = User::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->find($specialistId);
                }

                $saleLineItems = [];
                $totalRevenue = 0;

                foreach ($items as $itemData) {
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

                    $saleLineItems[] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'price' => $unitPrice,
                        'total' => $lineTotal,
                        'duration_minutes' => $product->duration_minutes,
                    ];
                }

                $discount = (float) $request->input('discount', 0);
                $netAmount = max(0, $totalRevenue - $discount);
                $paymentMethod = strtolower((string) $request->input('payment_method', 'cash'));

                $prefix = $company->invoice_prefix ?: 'INV-';
                $saleCount = Sale::withoutGlobalScope('company')->where('company_id', $company->id)->count() + 1;
                $saleNumber = $prefix.sprintf('%04d', $saleCount);

                $sale = Sale::create([
                    'company_id' => $company->id,
                    'sale_number' => $saleNumber,
                    'customer_id' => $request->input('customer_id'),
                    'customer_name' => $request->input('customer_name') ?: 'Walk-in Customer',
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
                    'notes' => $specialist
                        ? "Serviced by {$specialist->name}"
                        : ($request->input('notes') ?? 'Salon Counter Sale'),
                ]);

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

                return $sale;
            });

            AuditLog::record('salon.pos_sale_completed', $company->id, $user?->id, [
                'sale_id' => $sale->id,
                'total' => (float) $sale->total,
                'specialist_id' => $specialistId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Salon counter sale completed successfully',
                'sale' => $sale,
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
