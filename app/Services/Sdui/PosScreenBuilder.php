<?php

namespace App\Services\Sdui;

use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Product;

/**
 * Builds the universal `pos_screen` JSON contract (search + category pills +
 * 2-column catalog grid + floating cart bar) and its companion sheets
 * (batch picker, checkout/settlement drawer) for Pharmacy and Repair counter
 * sales — giving them the same native-feeling structure as Retail's
 * hand-coded Flutter POS screen, without any per-module Flutter code.
 *
 * Kept separate from SchemaResponse (already large) since this is a
 * cohesive, POS-specific concern.
 */
class PosScreenBuilder
{
    public static function pharmacyPosScreen(Company $company): array
    {
        $products = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->with(['pharmacyBatches' => fn ($q) => $q->where('is_active', true)->orderBy('expiry_date', 'asc')])
            ->limit(60)
            ->get();

        $categories = [['id' => null, 'label' => 'All']];
        $seenCategories = [];
        $items = [];

        foreach ($products as $product) {
            self::collectCategory($product, $categories, $seenCategories);

            $batches = $product->pharmacyBatches;
            $fefoBatch = $batches->first(fn ($b) => $b->days_until_expiry >= 0 && $b->stock_qty > 0);

            $badge = $product->narcotic_schedule
                ? ['text' => $product->narcotic_schedule, 'color' => '#ef4444']
                : ($product->requires_prescription
                    ? ['text' => 'Rx', 'color' => '#f59e0b']
                    : ['text' => 'OTC', 'color' => '#10b981']);

            $subtitle = $fefoBatch
                ? "Batch #{$fefoBatch->batch_number} · Exp {$fefoBatch->days_until_expiry}d"
                : ($product->generic_name ?: ($product->composition ?: 'No batch tracked'));

            $price = (float) ($fefoBatch && $fefoBatch->selling_price > 0 ? $fefoBatch->selling_price : $product->sale_price);

            $onTap = $batches->isNotEmpty()
                ? SchemaResponse::openRemoteSheetAction(
                    "/api/tenant/pharmacy/batch-sheet?product_id={$product->id}",
                    "Select FEFO Batch — {$product->name}"
                )
                : SchemaResponse::addToCartAction([
                    'id' => $product->id,
                    'batch_id' => null,
                    'title' => $product->name,
                    'subtitle' => $subtitle,
                    'price' => $price,
                    'quantity' => 1,
                    'max_quantity' => max(1, (int) $product->current_stock),
                ]);

            $items[] = [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'title' => $product->name,
                'subtitle' => $subtitle,
                'price' => $price,
                'image_url' => $product->image_url,
                'stock' => (int) $product->current_stock,
                'badge' => $badge,
                'on_tap' => $onTap,
            ];
        }

        return self::envelope(
            title: 'Pharmacy Counter POS',
            company: $company,
            searchPlaceholder: 'Search Generic Formula, Brand Name, or Barcode',
            categories: $categories,
            items: $items,
            checkoutSheetEndpoint: '/api/tenant/pharmacy/checkout-sheet',
        );
    }

    public static function repairPosScreen(Company $company): array
    {
        $products = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->limit(60)
            ->get();

        $categories = [['id' => null, 'label' => 'All']];
        $seenCategories = [];
        $items = [];

        foreach ($products as $product) {
            self::collectCategory($product, $categories, $seenCategories);

            $stock = (int) $product->current_stock;
            $inStock = $stock > 0;

            $items[] = [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'title' => $product->name,
                'subtitle' => 'SKU: '.($product->sku ?: ($product->barcode ?: 'General')),
                'price' => (float) $product->sale_price,
                'image_url' => $product->image_url,
                'stock' => $stock,
                'badge' => [
                    'text' => $inStock ? "Stock: {$stock}" : 'Out of Stock',
                    'color' => $inStock ? '#10b981' : '#ef4444',
                ],
                'on_tap' => SchemaResponse::addToCartAction([
                    'id' => $product->id,
                    'batch_id' => null,
                    'title' => $product->name,
                    'subtitle' => $product->category_name ?: 'Spare Part',
                    'price' => (float) $product->sale_price,
                    'quantity' => 1,
                    'max_quantity' => max(1, $stock),
                ]),
            ];
        }

        return self::envelope(
            title: 'Repair Counter & Parts POS',
            company: $company,
            searchPlaceholder: 'Search Spare Parts, Hardware Modules, or Labor Fees',
            categories: $categories,
            items: $items,
            checkoutSheetEndpoint: '/api/tenant/repair/checkout-sheet',
        );
    }

    /**
     * Batch-picker sheet for a single pharmacy product: lists active FEFO
     * batches (informational) and adds a single "Add to Dispensing Cart"
     * action pinned to the earliest-expiring batch — matches FEFO
     * compliance (dispense oldest stock first), not a manual override.
     */
    public static function pharmacyBatchSheet(Company $company, int $productId): array
    {
        $currency = $company->currency_symbol ?: '$';

        $product = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->findOrFail($productId);

        $batches = $product->activePharmacyBatches;

        $components = [
            SchemaResponse::row([
                SchemaResponse::icon('medication', ['color' => '#059669', 'size' => 28]),
                SchemaResponse::column([
                    SchemaResponse::text($product->name, 'title_medium', ['bold' => true]),
                    SchemaResponse::text('Generic: '.($product->generic_name ?: ($product->composition ?: 'Standard Formulation')), 'body_small', ['color' => '#64748b']),
                ]),
            ]),
            SchemaResponse::divider(),
            SchemaResponse::text('Active FEFO Batches (Earliest Expiry Priority):', 'label_large', ['bold' => true]),
        ];

        if ($batches->isEmpty()) {
            $components[] = SchemaResponse::text('No active batch stock for this medicine.', 'body_small', ['color' => '#64748b']);

            return self::sheet("Select FEFO Batch — {$product->name}", $components);
        }

        foreach ($batches as $b) {
            $expLabel = $b->days_until_expiry < 0
                ? 'EXPIRED'
                : ($b->days_until_expiry <= 90 ? "EXPIRING ({$b->days_until_expiry}d)" : "SAFE ({$b->days_until_expiry}d)");

            $components[] = SchemaResponse::card([
                SchemaResponse::row([
                    SchemaResponse::icon('inventory_2', ['color' => $b->expiry_color, 'size' => 20]),
                    SchemaResponse::column([
                        SchemaResponse::text("Batch #{$b->batch_number}", 'title_small', ['bold' => true]),
                        SchemaResponse::text("Stock: {$b->stock_qty} • Exp: {$b->expiry_date?->format('Y-m-d')}", 'body_small', ['color' => '#64748b']),
                    ]),
                    SchemaResponse::badge($expLabel, $b->expiry_color, 'subtle'),
                    SchemaResponse::text($currency.number_format((float) ($b->selling_price > 0 ? $b->selling_price : $product->sale_price), 2), 'label_large', ['bold' => true, 'color' => '#059669']),
                ]),
            ]);
        }

        $components[] = SchemaResponse::divider();

        $primaryBatch = $batches->first();
        $components[] = SchemaResponse::stepCounter('dispense_qty', 'Dispense Quantity', 1, 1, max(1, (int) $primaryBatch->stock_qty));
        $components[] = SchemaResponse::buttonPrimary('Add to Dispensing Cart', SchemaResponse::addToCartAction([
            'id' => $product->id,
            'batch_id' => $primaryBatch->id,
            'title' => $product->name,
            'subtitle' => "Batch #{$primaryBatch->batch_number}",
            'price' => (float) ($primaryBatch->selling_price > 0 ? $primaryBatch->selling_price : $product->sale_price),
            'quantity_field' => 'dispense_qty',
            'max_quantity' => max(1, (int) $primaryBatch->stock_qty),
        ]), 'add_shopping_cart');

        return self::sheet("Select FEFO Batch — {$product->name}", $components);
    }

    /**
     * Checkout/settlement drawer shared by Pharmacy and Repair: order
     * summary (from the client-supplied cart preview), customer/patient
     * name, optional ticket linkage, payment method, discount, tendered
     * cash, and two fixed optional split-payment rows. The submit button
     * form_submits straight to the module's real checkout endpoint — the
     * client is responsible for merging the authoritative cart items into
     * the form payload before submit (see UniversalPosScreen).
     *
     * When $collectPrescription is true (Pharmacy only), also collects
     * flat patient_name/doctor_name/doctor_registration_no fields —
     * required by PharmacyApiController::checkout() whenever the cart
     * contains a prescription-controlled item. Sent as flat fields (not
     * nested prescription_details.*) since the client posts a flat form
     * values map; checkout() normalizes them into prescription_details
     * itself (see normalizePrescriptionDetails()).
     *
     * @param  list<array<string, mixed>>  $cartPreview
     */
    public static function checkoutSheet(
        Company $company,
        string $formSubmitEndpoint,
        array $cartPreview,
        ?string $ticketFieldLabel = null,
        string $customerFieldLabel = 'Customer Name',
        bool $collectPrescription = false,
    ): array {
        $currency = $company->currency_symbol ?: '$';

        $subtotal = 0.0;
        $summaryRows = [];
        foreach ($cartPreview as $line) {
            $qty = (float) ($line['qty'] ?? $line['quantity'] ?? 1);
            $price = (float) ($line['price'] ?? 0);
            $lineTotal = round($qty * $price, 2);
            $subtotal += $lineTotal;
            $summaryRows[] = SchemaResponse::lineItemTile(
                ($line['title'] ?? 'Item')." × {$qty}",
                $currency.number_format($lineTotal, 2)
            );
        }

        $components = [];
        if (! empty($summaryRows)) {
            $components[] = SchemaResponse::card(array_merge(
                [SchemaResponse::text('Order Summary', 'title_small', ['bold' => true]), SchemaResponse::divider()],
                $summaryRows,
                [
                    SchemaResponse::divider(),
                    SchemaResponse::row([
                        SchemaResponse::text('Subtotal', 'label_large', ['bold' => true]),
                        SchemaResponse::text($currency.number_format($subtotal, 2), 'label_large', ['bold' => true, 'color' => '#059669']),
                    ], ['main_axis_alignment' => 'space_between']),
                ]
            ));
        } else {
            $components[] = SchemaResponse::text('Your cart is empty. Add items before checking out.', 'body_medium', ['color' => '#64748b']);
        }

        $components[] = SchemaResponse::textInput('customer_name', $customerFieldLabel, 'Walk-in Customer');
        if ($ticketFieldLabel !== null) {
            $components[] = SchemaResponse::textInput('ticket_id', $ticketFieldLabel, '');
        }
        if ($collectPrescription) {
            $components[] = SchemaResponse::divider();
            $components[] = SchemaResponse::text('Prescription (required for Rx / controlled items)', 'label_medium', ['color' => '#64748b']);
            $components[] = SchemaResponse::textInput('patient_name', 'Patient Full Name', '');
            $components[] = SchemaResponse::textInput('doctor_name', 'Prescribing Doctor Name', '');
            $components[] = SchemaResponse::textInput('doctor_registration_no', 'Doctor Registration # (Optional)', '');
        }
        $components[] = SchemaResponse::dropdownSelect('payment_method', 'Payment Method', [
            ['label' => 'Cash Payment', 'value' => 'cash'],
            ['label' => 'Debit / Credit Card', 'value' => 'card'],
            ['label' => 'UPI / QR Code', 'value' => 'upi'],
            ['label' => 'Customer Credit / Khata', 'value' => 'credit'],
            ['label' => 'Split Payment', 'value' => 'split'],
        ], 'cash');
        $components[] = SchemaResponse::textInput('discount', 'Discount Amount', '0.00');
        $components[] = SchemaResponse::textInput('tendered', 'Cash Tendered (optional)', '');
        $components[] = SchemaResponse::divider();
        $components[] = SchemaResponse::text('Split Payment (only used when Payment Method is Split)', 'label_medium', ['color' => '#64748b']);
        $components[] = SchemaResponse::dropdownSelect('payment_1_method', 'Payment 1 Method', [
            ['label' => 'Cash', 'value' => 'cash'],
            ['label' => 'Card', 'value' => 'card'],
            ['label' => 'UPI', 'value' => 'upi'],
        ], 'cash');
        $components[] = SchemaResponse::textInput('payment_1_amount', 'Payment 1 Amount', '');
        $components[] = SchemaResponse::dropdownSelect('payment_2_method', 'Payment 2 Method', [
            ['label' => 'Cash', 'value' => 'cash'],
            ['label' => 'Card', 'value' => 'card'],
            ['label' => 'UPI', 'value' => 'upi'],
        ], 'card');
        $components[] = SchemaResponse::textInput('payment_2_amount', 'Payment 2 Amount', '');
        $components[] = SchemaResponse::divider();
        $components[] = SchemaResponse::buttonPrimary('Complete Checkout', SchemaResponse::formSubmitAction(
            $formSubmitEndpoint,
            'POST',
            'Sale completed successfully.',
            reload: true
        ), 'point_of_sale');

        return self::sheet('Checkout & Settlement', $components);
    }

    /** @param  list<array<string, mixed>>  $categories */
    private static function collectCategory(Product $product, array &$categories, array &$seen): void
    {
        if ($product->category_id === null || isset($seen[$product->category_id])) {
            return;
        }

        $seen[$product->category_id] = true;
        $categories[] = ['id' => $product->category_id, 'label' => $product->category_name ?: 'Category'];
    }

    /**
     * @param  list<array<string, mixed>>  $categories
     * @param  list<array<string, mixed>>  $items
     */
    private static function envelope(
        string $title,
        Company $company,
        string $searchPlaceholder,
        array $categories,
        array $items,
        string $checkoutSheetEndpoint,
    ): array {
        $registerOpen = CashRegister::openFor($company->id) !== null;

        $schema = SchemaResponse::screen($title, []);
        $schema['type'] = 'pos_screen';
        $schema['banner'] = $registerOpen ? null : [
            'icon' => 'info_outline',
            'message' => 'No cash register is open. Sales can continue outside a register session.',
            'action' => SchemaResponse::navigateAction('/api/tenant/views/cash-register', title: 'Cash Register'),
        ];
        $schema['search'] = [
            'placeholder' => $searchPlaceholder,
            'scanner_enabled' => true,
        ];
        $schema['categories'] = $categories;
        $schema['catalog'] = [
            'layout_type' => 'standard_grid',
            'items' => $items,
        ];
        $schema['cart_bar'] = [
            'label_template' => 'View Cart · {count} items · {total}',
            'checkout_sheet_endpoint' => $checkoutSheetEndpoint,
        ];

        return $schema;
    }

    /** @param  list<array<string, mixed>>  $components */
    private static function sheet(string $title, array $components): array
    {
        return [
            'type' => 'sheet',
            'schema_version' => SchemaResponse::SCHEMA_VERSION,
            'layout' => 'column',
            'title' => $title,
            'components' => $components,
        ];
    }
}
