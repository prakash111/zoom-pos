<?php

namespace App\Services\Sdui;

use App\Models\CashRegister;
use App\Models\Company;
use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\SalonAppointment;
use App\Models\User;
use App\Services\TaxCalculationService;
use Illuminate\Support\Facades\Schema;

/**
 * Universal System UI & POS Base Contract (Perfex CRM Pattern).
 *
 * Provides a single, locked UI interface across every current module
 * (Retail, Restaurant, Pharmacy, Repair, Salon) and future modules:
 * - 2-column Material elevated card grid with product thumbnail, title,
 *   price, stock badge, and quantity stepper / add action.
 * - Full-width search bar + QR/barcode scan button + horizontal category chips.
 * - Universal Checkout Drawer (1000586105.jpg, 1000586111.jpg, 1000586109.jpg):
 *   Summary line items, top action pills (Add Customer, Hold, Note, Discount,
 *   Split Payment), rounded toggle payment buttons (Cash, Card, Transfer),
 *   Quick Cash Tendered chips (Exact, +$5, +$10, +$20, Next Round $50) + custom input,
 *   green highlighted Change Due box, and settlement breakdown with Complete Sale button.
 */
class UniversalPosBuilder
{
    /**
     * Retail POS Screen.
     */
    public static function retailPosScreen(Company $company): array
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
                    'subtitle' => $product->category_name ?: 'General',
                    'price' => (float) $product->sale_price,
                    'quantity' => 1,
                    'max_quantity' => max(1, $stock),
                ]),
            ];
        }

        return self::envelope(
            title: 'Retail',
            company: $company,
            searchPlaceholder: 'Search by Product Name, SKU, or Barcode',
            categories: $categories,
            items: $items,
            checkoutSheetEndpoint: '/api/tenant/pos/checkout-sheet',
        );
    }

    /**
     * Pharmacy POS Screen with FEFO batch awareness.
     */
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

            $fefoStock = $batches->isNotEmpty()
                ? (int) ($fefoBatch?->stock_qty ?? 0)
                : (int) $product->current_stock;
            $badge = [
                'text' => $fefoBatch ? "FEFO: {$fefoStock} units" : "Stock: {$fefoStock}",
                'color' => $fefoStock > 0 ? '#10b981' : '#ef4444',
            ];

            preg_match('/\b\d+(?:\.\d+)?\s*(?:mg|mcg|g|ml|iu)\b/i', $product->name, $dosageMatch);
            $dosage = $dosageMatch[0] ?? ($product->unit ?: 'unit');
            $generic = $product->generic_name ?: ($product->composition ?: 'Standard formulation');
            $compliance = $product->narcotic_schedule ?: ($product->requires_prescription ? 'Rx Required' : 'OTC');
            $subtitle = "{$generic} · {$dosage} · {$compliance}";

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
                'title' => $product->brand_name ?: $product->name,
                'subtitle' => $subtitle,
                'price' => $price,
                'image_url' => $product->image_url,
                'stock' => $fefoStock,
                'badge' => $badge,
                'brand_name' => $product->brand_name ?: $product->name,
                'generic_formula' => $generic,
                'dosage' => $dosage,
                'fefo_stock' => $fefoStock,
                'fefo_batch' => $fefoBatch?->batch_number,
                'compliance_badges' => array_values(array_filter([
                    $product->narcotic_schedule ?: null,
                    $product->requires_prescription ? 'Rx Required' : 'OTC',
                ])),
                'on_tap' => $onTap,
            ];
        }

        return self::envelope(
            title: 'Pharmacy',
            company: $company,
            searchPlaceholder: 'Search by Product Name, SKU, or Barcode',
            categories: $categories,
            items: $items,
            checkoutSheetEndpoint: '/api/tenant/pharmacy/checkout-sheet',
        );
    }

    /**
     * Repair & Technician POS Screen: parts, labor, and intake ticket billing.
     */
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

        $ticketId = (int) request('ticket_id', 0);
        $checkoutEndpoint = '/api/tenant/repair/checkout-sheet';
        if ($ticketId > 0) {
            $belongsToTenant = RepairTicket::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->whereKey($ticketId)
                ->exists();
            if ($belongsToTenant) {
                $checkoutEndpoint .= '?ticket_id='.$ticketId;
            }
        }

        return self::envelope(
            title: 'Repair',
            company: $company,
            searchPlaceholder: 'Search by Product Name, SKU, or Barcode',
            categories: $categories,
            items: $items,
            checkoutSheetEndpoint: $checkoutEndpoint,
        );
    }

    /**
     * Salon & Service POS Screen: services, durations, and retail add-ons.
     */
    public static function salonPosScreen(Company $company): array
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

            $duration = $product->duration_minutes !== null ? (int) $product->duration_minutes : null;
            $stock = (int) $product->current_stock;
            $inStock = $stock > 0;

            $badge = $duration !== null
                ? ['text' => "{$duration} mins", 'color' => '#8b5cf6']
                : ['text' => $inStock ? "Stock: {$stock}" : 'Out of Stock', 'color' => $inStock ? '#10b981' : '#ef4444'];

            $subtitle = $duration !== null
                ? 'Service · '.($product->category_name ?: 'Treatment')
                : 'Retail · '.($product->category_name ?: 'Add-on');

            $items[] = [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'title' => $product->name,
                'subtitle' => $subtitle,
                'price' => (float) $product->sale_price,
                'image_url' => $product->image_url,
                'stock' => $stock,
                'badge' => $badge,
                'duration_minutes' => $duration,
                'service_type' => $duration !== null ? 'service' : 'retail_add_on',
                'on_tap' => SchemaResponse::addToCartAction([
                    'id' => $product->id,
                    'batch_id' => null,
                    'title' => $product->name,
                    'subtitle' => $subtitle,
                    'price' => (float) $product->sale_price,
                    'quantity' => 1,
                    'max_quantity' => max(1, $stock),
                ]),
            ];
        }

        $appointmentId = (int) request('appointment_id', 0);
        $checkoutEndpoint = '/api/tenant/salon/checkout-sheet';
        if ($appointmentId > 0 && Schema::hasTable('salon_appointments')
            && SalonAppointment::withoutGlobalScope('company')->where('company_id', $company->id)->whereKey($appointmentId)->exists()) {
            $checkoutEndpoint .= '?appointment_id='.$appointmentId;
        }

        return self::envelope(
            title: 'Salon',
            company: $company,
            searchPlaceholder: 'Search by Product Name, SKU, or Barcode',
            categories: $categories,
            items: $items,
            checkoutSheetEndpoint: $checkoutEndpoint,
        );
    }

    /**
     * Restaurant & Cafe POS Screen: dishes, beverages, and order items.
     */
    public static function restaurantPosScreen(Company $company): array
    {
        $products = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->limit(60)
            ->get();

        $categories = [['id' => null, 'label' => 'All Dishes']];
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
                'subtitle' => $product->category_name ?: 'Food & Beverage',
                'price' => (float) $product->sale_price,
                'image_url' => $product->image_url,
                'stock' => $stock,
                'badge' => [
                    'text' => $inStock ? 'Available' : 'Sold Out',
                    'color' => $inStock ? '#10b981' : '#ef4444',
                ],
                'on_tap' => SchemaResponse::addToCartAction([
                    'id' => $product->id,
                    'batch_id' => null,
                    'title' => $product->name,
                    'subtitle' => $product->category_name ?: 'Food & Beverage',
                    'price' => (float) $product->sale_price,
                    'quantity' => 1,
                    'max_quantity' => max(1, $stock),
                ]),
            ];
        }

        return self::envelope(
            title: 'Restaurant POS Terminal',
            company: $company,
            searchPlaceholder: 'Search Menu Dishes, Drinks, or Food Items',
            categories: $categories,
            items: $items,
            checkoutSheetEndpoint: '/api/tenant/pos/checkout-sheet',
        );
    }

    /**
     * Dynamic POS Screen for uploaded or secondary business modules.
     */
    public static function genericPosScreen(Company $company, string $moduleKey, ?string $title = null): array
    {
        $title = $title ?: ucwords(str_replace(['_', '-'], ' ', $moduleKey)).' POS';

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
                'subtitle' => $product->category_name ?: 'Item',
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
                    'subtitle' => $product->category_name ?: 'Item',
                    'price' => (float) $product->sale_price,
                    'quantity' => 1,
                    'max_quantity' => max(1, $stock),
                ]),
            ];
        }

        return self::envelope(
            title: $title,
            company: $company,
            searchPlaceholder: 'Search '.$title.' Catalog...',
            categories: $categories,
            items: $items,
            checkoutSheetEndpoint: '/api/tenant/pos/checkout-sheet',
        );
    }

    /**
     * Dispatcher to resolve POS screen by module name.
     */
    public static function posScreenForModule(string $module, Company $company): array
    {
        $mod = strtolower(trim($module));

        return match ($mod) {
            'pharmacy' => self::pharmacyPosScreen($company),
            'repair', 'repair_technician' => self::repairPosScreen($company),
            'salon', 'service_booking' => self::salonPosScreen($company),
            'restaurant' => self::restaurantPosScreen($company),
            'retail', 'general', 'general_retail' => self::retailPosScreen($company),
            default => self::genericPosScreen($company, $mod),
        };
    }

    /**
     * Specialists / Stylists roster screen.
     */
    public static function specialistRosterScreen(Company $company): array
    {
        $staff = User::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', 'approved')
            ->orderBy('name')
            ->get();

        $cards = [];
        foreach ($staff as $user) {
            $isSpecialist = (bool) $user->is_specialist;
            $cards[] = SchemaResponse::card([
                SchemaResponse::row([
                    SchemaResponse::column([
                        SchemaResponse::text($user->name, 'title_small', ['bold' => true]),
                        SchemaResponse::text(User::ROLES[$user->role] ?? ucfirst($user->role), 'body_small', ['color' => '#64748b']),
                    ]),
                    $isSpecialist
                        ? SchemaResponse::badge('Specialist', '#7c3aed', 'solid')
                        : SchemaResponse::badge('Not Assigned', '#64748b', 'subtle'),
                ], ['main_axis_alignment' => 'space_between']),
                SchemaResponse::buttonOutlined(
                    $isSpecialist ? 'Remove Specialist' : 'Mark as Specialist',
                    SchemaResponse::apiPostAction(
                        "/api/tenant/salon/specialists/{$user->id}/toggle",
                        [],
                        $isSpecialist ? 'Removed from specialist roster.' : 'Added to specialist roster.',
                        reload: true
                    ),
                ),
            ]);
        }

        if (empty($cards)) {
            $cards[] = SchemaResponse::text('No staff members found. Invite staff from Settings → Users.', 'body_medium', ['color' => '#64748b']);
        }

        return SchemaResponse::screen('Specialists & Stylists', $cards);
    }

    /**
     * Batch-picker sheet for a single pharmacy product with FEFO priority.
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
                        SchemaResponse::text("Expiry Date: {$b->expiry_date?->format('Y-m-d')} · Available Units: {$b->stock_qty}", 'body_small', ['color' => '#64748b']),
                    ]),
                    SchemaResponse::badge($expLabel, $b->expiry_color, 'subtle'),
                    SchemaResponse::text($currency.number_format((float) ($b->selling_price > 0 ? $b->selling_price : $product->sale_price), 2), 'label_large', ['bold' => true, 'color' => '#059669']),
                ]),
            ]);
        }

        $components[] = SchemaResponse::divider();

        $primaryBatch = $batches->first(fn ($batch) => $batch->days_until_expiry >= 0);
        if (! $primaryBatch) {
            $components[] = SchemaResponse::container([
                SchemaResponse::text('All remaining lots are expired. Dispensing is blocked.', 'body_medium', ['bold' => true, 'color' => '#b91c1c']),
            ], ['padding' => 12, 'color' => '#fef2f2', 'border_color' => '#fecaca', 'border_radius' => 12]);

            return self::sheet("Select FEFO Batch — {$product->name}", $components);
        }

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
     * Universal POS Checkout Drawer (1000586105.jpg, 1000586111.jpg, 1000586109.jpg).
     *
     * Shared across Retail, Restaurant, Pharmacy, Repair, Salon, and all dynamic modules.
     *
     * @param  list<array<string, mixed>>  $cartPreview
     * @param  list<array{id: mixed, name: string}>|null  $specialistOptions
     * @param  list<array{id: mixed, label: string}>|null  $prescriptionOptions
     * @param  list<array{id: mixed, label: string, advance_paid?: float, total_amount?: float}>|null  $repairTicketOptions
     */
    public static function checkoutSheet(
        Company $company,
        string $formSubmitEndpoint,
        array $cartPreview,
        ?string $ticketFieldLabel = null,
        string $customerFieldLabel = 'Customer Name',
        bool $collectPrescription = false,
        ?array $specialistOptions = null,
        string $module = 'retail',
        ?array $prescriptionOptions = null,
        ?array $repairTicketOptions = null,
        int|string|null $selectedTicketId = null,
        bool $previewIncludesTicket = false,
        int|string|null $defaultSpecialistId = null,
        ?string $defaultCustomerName = null,
    ): array {
        $currency = $company->currency_symbol ?: '$';

        $subtotal = 0.0;
        $itemCount = 0;
        $summaryRows = [];
        foreach (array_values($cartPreview) as $index => $line) {
            $qty = max(1, (float) ($line['qty'] ?? $line['quantity'] ?? 1));
            $price = max(0, (float) ($line['price'] ?? 0));
            $lineTotal = round($qty * $price, 2);
            $subtotal += $lineTotal;
            $itemCount++;

            $lineComponents = [
                SchemaResponse::row([
                    SchemaResponse::column([
                        SchemaResponse::text((string) ($line['title'] ?? 'Item'), 'label_large', ['bold' => true]),
                        SchemaResponse::text("{$qty} × {$currency}".number_format($price, 2), 'body_small', ['color' => '#64748b']),
                    ]),
                    SchemaResponse::text($currency.number_format($lineTotal, 2), 'label_large', ['bold' => true]),
                ], ['main_axis_alignment' => 'space_between']),
            ];

            $isSalonService = $module === 'salon' && Product::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('name', (string) ($line['title'] ?? ''))
                ->whereNotNull('duration_minutes')
                ->exists();
            if ($isSalonService && ! empty($specialistOptions)) {
                $lineComponents[] = SchemaResponse::badge('Assigned Stylist / Specialist', '#7c3aed', 'subtle');
                $lineComponents[] = SchemaResponse::dropdownSelect(
                    "line_specialist_{$index}",
                    'Assign this service',
                    array_merge(
                        [['label' => 'Unassigned', 'value' => '']],
                        array_map(fn (array $staff) => ['label' => $staff['name'], 'value' => (string) $staff['id']], $specialistOptions)
                    ),
                    (string) ($defaultSpecialistId ?? '')
                );
            }

            $summaryRows[] = SchemaResponse::container($lineComponents, [
                'padding' => 10,
                'margin' => ['bottom' => 8],
                'color' => '#f8fafc',
                'border_color' => '#e2e8f0',
                'border_radius' => 10,
            ]);
        }

        $taxPreviewItems = array_map(function (array $line) use ($company) {
            $title = (string) ($line['title'] ?? $line['name'] ?? 'Item');
            $product = Product::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('name', $title)
                ->first(['id']);

            return [
                'product_id' => $product?->id,
                'name' => $title,
                'quantity' => max(1, (float) ($line['qty'] ?? $line['quantity'] ?? 1)),
                'price' => max(0, (float) ($line['price'] ?? 0)),
            ];
        }, $cartPreview);
        $previewTotals = app(TaxCalculationService::class)->calculateCartTotals($taxPreviewItems, $company);

        $selectedTicket = null;
        if ($module === 'repair' && $selectedTicketId) {
            $selectedTicket = RepairTicket::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->with('parts')
                ->find($selectedTicketId);
        }
        $advancePaid = (float) ($selectedTicket?->advance_paid ?? 0);
        $existingTicketTotal = (float) ($selectedTicket?->total_amount ?? 0);
        if ($selectedTicket && ! $previewIncludesTicket) {
            $existingTaxItems = $selectedTicket->parts
                ->where('billed_to_customer', true)
                ->map(fn ($part) => [
                    'product_id' => $part->product_id,
                    'name' => "Part: {$part->part_name}",
                    'quantity' => (int) $part->quantity,
                    'price' => (float) $part->unit_price,
                ])
                ->values()
                ->all();
            if ((float) $selectedTicket->labor_fee > 0) {
                $existingTaxItems[] = [
                    'name' => "Labor / Service Charge ({$selectedTicket->device_type})",
                    'quantity' => 1,
                    'price' => (float) $selectedTicket->labor_fee,
                ];
            }
            if ($existingTaxItems === [] && $existingTicketTotal > 0) {
                $existingTaxItems[] = [
                    'name' => "Repair Service: {$selectedTicket->device_type}",
                    'quantity' => 1,
                    'price' => $existingTicketTotal,
                ];
            }
            $previewTotals = app(TaxCalculationService::class)->calculateCartTotals(
                array_merge($existingTaxItems, $taxPreviewItems),
                $company
            );
        }
        $taxAmount = (float) $previewTotals['tax_amount'];
        $billingSubtotal = round($subtotal + ($previewIncludesTicket ? 0 : $existingTicketTotal), 2);
        $grandTotal = max(0, round($billingSubtotal + $taxAmount - $advancePaid, 2));

        $quickCash = self::quickCashSuggestions($grandTotal, $currency);
        $paymentMethods = [
            ['label' => 'Cash', 'value' => 'cash', 'icon' => 'payments'],
            ['label' => 'Card', 'value' => 'card', 'icon' => 'credit_card'],
            ['label' => 'Transfer', 'value' => 'transfer', 'icon' => 'account_balance'],
            ['label' => 'Due / Credit', 'value' => 'credit', 'icon' => 'schedule'],
        ];
        $allowedPaymentMethods = array_column($paymentMethods, 'value');
        $rawMethod = strtolower((string) request('selected_payment_method', request('payment_method', 'cash')));
        if ($rawMethod === 'upi') {
            $rawMethod = 'transfer';
        }
        $selectedPaymentMethod = in_array($rawMethod, $allowedPaymentMethods, true) ? $rawMethod : 'cash';

        $selectedTendered = is_numeric(request('selected_tendered'))
            ? max(0, (float) request('selected_tendered'))
            : $grandTotal;
        $changeDue = max(0, round($selectedTendered - $grandTotal, 2));

        $sheetPath = request()->getPathInfo();
        $sheetQuery = request()->query();
        $refreshSheet = static function (array $changes) use ($sheetPath, $sheetQuery): string {
            $query = array_merge($sheetQuery, $changes);

            return $sheetPath.($query === [] ? '' : '?'.http_build_query($query));
        };
        $submitEndpoint = self::appendQuery($formSubmitEndpoint, [
            'selected_payment_method' => $selectedPaymentMethod,
            'selected_tendered' => number_format($selectedTendered, 2, '.', ''),
        ]);

        $components = [
            SchemaResponse::card([
                SchemaResponse::row([
                    SchemaResponse::row([
                        SchemaResponse::text('Order Cart', 'title_large', ['bold' => true]),
                        SchemaResponse::badge("{$itemCount} items", '#2563eb', 'subtle'),
                    ]),
                    SchemaResponse::badge('Clear Cart', '#ef4444', 'subtle'),
                ], ['main_axis_alignment' => 'space_between']),
                SchemaResponse::divider(),
                ...$summaryRows,
            ], ['border_radius' => 16, 'border_color' => '#e2e8f0']),
        ];

        if (empty($summaryRows)) {
            $components[] = SchemaResponse::text('Your cart is empty. Add items before checking out.', 'body_medium', ['color' => '#64748b']);
        }

        // Action Pills row: Add Customer, Hold, Note, Discount, Split Payment, Amount Paid
        $components[] = SchemaResponse::card([
            SchemaResponse::wrap([
                SchemaResponse::badge('+ Add Customer', '#2563eb', 'subtle'),
                SchemaResponse::badge('⏱ Hold', '#d97706', 'subtle'),
                SchemaResponse::badge('📝 Note', '#475569', 'subtle'),
                SchemaResponse::badge('% Discount', '#7c3aed', 'subtle'),
                SchemaResponse::badge('➗ Split Payment', '#059669', 'subtle'),
                SchemaResponse::badge($currency.' Amount Paid', '#0284c7', 'subtle'),
            ]),
            SchemaResponse::accordionGroup('Customer, Note & Discount', [
                SchemaResponse::textInput('customer_name', $customerFieldLabel, $defaultCustomerName ?: 'Walk-in Customer'),
                SchemaResponse::textInput('customer_phone', 'Phone (Optional)', '', ['keyboard_type' => 'phone']),
                SchemaResponse::textInput('notes', 'Order Note (Optional)', '', ['max_lines' => 2]),
                SchemaResponse::textInput('discount', 'Discount Amount', '0.00', ['keyboard_type' => 'number']),
            ], ['initially_expanded' => false]),
        ], ['border_radius' => 16]);

        if ($ticketFieldLabel !== null && ! empty($repairTicketOptions)) {
            $ticketOptions = array_merge(
                [['label' => 'Counter sale — no repair ticket', 'value' => '']],
                array_map(fn (array $ticket) => [
                    'label' => $ticket['label'],
                    'value' => (string) $ticket['id'],
                ], $repairTicketOptions)
            );
            $components[] = SchemaResponse::accordionGroup('Link Repair Intake Ticket', [
                SchemaResponse::dropdownSelect('ticket_id', $ticketFieldLabel, $ticketOptions, (string) ($selectedTicketId ?? '')),
                SchemaResponse::text('The selected ticket receives these parts and labor lines on checkout.', 'body_small', ['color' => '#64748b']),
            ], ['initially_expanded' => $selectedTicket !== null]);
        }

        if ($collectPrescription) {
            $rxComponents = [
                SchemaResponse::text('Required automatically when any Schedule H, Rx, or narcotic item is present.', 'body_small', ['color' => '#64748b']),
            ];
            if (! empty($prescriptionOptions)) {
                $rxComponents[] = SchemaResponse::dropdownSelect('prescription_id', 'Attach from Prescriptions Queue', array_merge(
                    [['label' => 'Attach new doctor details', 'value' => '']],
                    array_map(fn (array $rx) => ['label' => $rx['label'], 'value' => (string) $rx['id']], $prescriptionOptions)
                ), '');
                $rxComponents[] = SchemaResponse::text('Or replace this cart with a queued prescription:', 'body_small', ['color' => '#64748b']);
                $rxComponents[] = SchemaResponse::wrap(array_map(
                    fn (array $rx) => SchemaResponse::buttonOutlined(
                        'Load '.$rx['label'],
                        SchemaResponse::openRemoteSheetAction(
                            "/api/tenant/pharmacy/prescriptions/{$rx['id']}/checkout-sheet",
                            'Order Cart'
                        ),
                        'receipt_long',
                        ['full_width' => false]
                    ),
                    array_slice($prescriptionOptions, 0, 5)
                ));
            }
            $rxComponents[] = SchemaResponse::textInput('patient_name', 'Patient Full Name', '');
            $rxComponents[] = SchemaResponse::textInput('doctor_name', 'Prescribing Doctor Name', '');
            $rxComponents[] = SchemaResponse::textInput('doctor_registration_no', 'Doctor Registration # (Optional)', '');
            $components[] = SchemaResponse::accordionGroup('Attach Doctor & Rx Details', $rxComponents, [
                'subtitle' => 'Controlled-drug compliance',
                'initially_expanded' => false,
            ]);
        }

        if (! empty($specialistOptions)) {
            $components[] = SchemaResponse::accordionGroup('Staff Allocation', [
                SchemaResponse::dropdownSelect('specialist_id', 'Apply Stylist / Specialist to All Services', array_merge(
                    [['label' => 'Keep per-service assignments', 'value' => '']],
                    array_map(fn (array $staff) => ['label' => $staff['name'], 'value' => (string) $staff['id']], $specialistOptions)
                ), (string) ($defaultSpecialistId ?? '')),
                SchemaResponse::text('Per-service selections shown under each booked service take priority for commission tracking.', 'body_small', ['color' => '#64748b']),
            ], ['initially_expanded' => false]);
        }

        // Payment Method Selector
        $components[] = SchemaResponse::card([
            SchemaResponse::text('Payment Method', 'label_large', ['bold' => true]),
            SchemaResponse::wrap(array_map(function (array $method) use ($selectedPaymentMethod, $refreshSheet) {
                $action = SchemaResponse::openRemoteSheetAction($refreshSheet([
                    'selected_payment_method' => $method['value'],
                ]), 'Order Cart');

                $isSelected = ($method['value'] === $selectedPaymentMethod)
                    || ($method['value'] === 'transfer' && $selectedPaymentMethod === 'upi')
                    || ($method['value'] === 'upi' && $selectedPaymentMethod === 'transfer');

                return $isSelected
                    ? SchemaResponse::buttonPrimary($method['label'], $action, $method['icon'], ['full_width' => false, 'border_radius' => 20, 'background_color' => '#2563eb'])
                    : SchemaResponse::buttonOutlined($method['label'], $action, $method['icon'], ['full_width' => false, 'border_radius' => 20]);
            }, $paymentMethods)),
            SchemaResponse::dropdownSelect('payment_method', 'Select Tender', $paymentMethods, $selectedPaymentMethod),
        ], ['border_radius' => 16]);

        // Cash Tendered + CHANGE DUE TO CUSTOMER + Quick Cash Suggestions
        $components[] = SchemaResponse::card([
            SchemaResponse::textInput('tendered', 'Cash Tendered by Customer', number_format($selectedTendered, 2, '.', ''), [
                'keyboard_type' => 'number',
                'prefix_icon' => 'payments',
            ]),
            SchemaResponse::container([
                SchemaResponse::row([
                    SchemaResponse::column([
                        SchemaResponse::text('CHANGE DUE TO CUSTOMER', 'label_small', ['bold' => true, 'color' => '#166534']),
                        SchemaResponse::text('Change Due to Customer', 'body_small', ['color' => '#15803d']),
                    ]),
                    SchemaResponse::text($currency.number_format($changeDue, 2), 'title_large', ['bold' => true, 'color' => '#166534']),
                ], ['main_axis_alignment' => 'space_between']),
            ], ['padding' => 12, 'color' => '#ecfdf5', 'border_color' => '#86efac', 'border_radius' => 10]),
            SchemaResponse::wrap(array_map(function (array $suggestion) use ($selectedTendered, $refreshSheet) {
                $action = SchemaResponse::openRemoteSheetAction($refreshSheet([
                    'selected_tendered' => number_format($suggestion['amount'], 2, '.', ''),
                ]), 'Order Cart');

                return abs($suggestion['amount'] - $selectedTendered) < 0.001
                    ? SchemaResponse::buttonPrimary($suggestion['display'], $action, null, ['full_width' => false, 'border_radius' => 16, 'background_color' => '#166534'])
                    : SchemaResponse::buttonOutlined($suggestion['display'], $action, null, ['full_width' => false, 'border_radius' => 16]);
            }, $quickCash)),
            SchemaResponse::dropdownSelect('quick_cash_tendered', 'Quick Cash Amount', array_map(
                fn (array $suggestion) => ['label' => $suggestion['display'], 'value' => number_format($suggestion['amount'], 2, '.', '')],
                $quickCash
            ), number_format($selectedTendered, 2, '.', '')),
        ], ['border_radius' => 16, 'border_color' => '#bbf7d0']);

        // Split Payment Section
        $components[] = SchemaResponse::accordionGroup('Split Payment', [
            SchemaResponse::dropdownSelect('payment_1_method', 'Payment 1 Method', [
                ['label' => 'Cash', 'value' => 'cash'],
                ['label' => 'Card', 'value' => 'card'],
                ['label' => 'Transfer', 'value' => 'transfer'],
            ], 'cash'),
            SchemaResponse::textInput('payment_1_amount', 'Payment 1 Amount', '', ['keyboard_type' => 'number']),
            SchemaResponse::dropdownSelect('payment_2_method', 'Payment 2 Method', [
                ['label' => 'Cash', 'value' => 'cash'],
                ['label' => 'Card', 'value' => 'card'],
                ['label' => 'Transfer', 'value' => 'transfer'],
            ], 'card'),
            SchemaResponse::textInput('payment_2_amount', 'Payment 2 Amount', '', ['keyboard_type' => 'number']),
        ], ['initially_expanded' => false]);

        // Settlement Footer
        $totalRows = [
            SchemaResponse::row([
                SchemaResponse::text('Subtotal', 'body_medium', ['color' => '#475569']),
                SchemaResponse::text($currency.number_format($billingSubtotal, 2), 'body_medium', ['bold' => true]),
            ], ['main_axis_alignment' => 'space_between']),
            SchemaResponse::row([
                SchemaResponse::text($company->tax_id_label ?: 'Tax', 'body_medium', ['color' => '#475569']),
                SchemaResponse::text($currency.number_format($taxAmount, 2), 'body_medium', ['bold' => true]),
            ], ['main_axis_alignment' => 'space_between']),
        ];
        if ($module === 'repair') {
            $totalRows[] = SchemaResponse::row([
                SchemaResponse::text('Advance Deposit Paid', 'body_medium', ['color' => '#15803d']),
                SchemaResponse::text('-'.$currency.number_format($advancePaid, 2), 'body_medium', ['bold' => true, 'color' => '#15803d']),
            ], ['main_axis_alignment' => 'space_between']);
        }
        $totalRows[] = SchemaResponse::divider();
        $totalRows[] = SchemaResponse::row([
            SchemaResponse::text($module === 'repair' ? 'Balance Due' : 'Grand Total', 'title_medium', ['bold' => true]),
            SchemaResponse::text($currency.number_format($grandTotal, 2), 'title_large', ['bold' => true, 'color' => '#166534']),
        ], ['main_axis_alignment' => 'space_between']);
        $components[] = SchemaResponse::container($totalRows, [
            'padding' => 14,
            'color' => '#f8fafc',
            'border_color' => '#cbd5e1',
            'border_radius' => 14,
        ]);

        $components[] = SchemaResponse::buttonPrimary('Complete Sale / Collect Payment', SchemaResponse::formSubmitAction(
            $submitEndpoint,
            'POST',
            'Payment collected and sale completed successfully.',
            reload: true
        ), 'payments', ['background_color' => '#166534', 'border_radius' => 14]);

        $schema = self::sheet('Order Cart', $components);
        $schema['type'] = 'sheet';
        $schema['presentation'] = 'native_pos_checkout_drawer';
        $schema['module'] = $module;
        $schema['order_summary'] = [
            'line_item_count' => $itemCount,
            'subtotal' => $billingSubtotal,
            'existing_ticket_total' => round($existingTicketTotal, 2),
            'tax' => $taxAmount,
            'advance_paid' => round($advancePaid, 2),
            'grand_total' => $grandTotal,
            'currency_symbol' => $currency,
        ];
        $schema['customer_actions'] = ['add_customer', 'hold', 'note', 'discount', 'split_payment'];
        $schema['action_pills'] = [
            ['label' => 'Add Customer', 'key' => 'add_customer', 'color' => '#2563eb'],
            ['label' => 'Hold', 'key' => 'hold', 'color' => '#d97706'],
            ['label' => 'Note', 'key' => 'note', 'color' => '#475569'],
            ['label' => 'Discount', 'key' => 'discount', 'color' => '#7c3aed'],
            ['label' => 'Split Payment', 'key' => 'split_payment', 'color' => '#059669'],
        ];
        $schema['payment_methods'] = $paymentMethods;
        $schema['quick_cash'] = [
            'tendered_field' => 'tendered',
            'quick_field' => 'quick_cash_tendered',
            'suggestions' => $quickCash,
            'change_due_expression' => 'max(0, tendered - grand_total)',
            'change_due_label' => 'Change Due to Customer',
            'selected_tendered' => round($selectedTendered, 2),
            'change_due' => $changeDue,
        ];
        $schema['bottom_bar'] = [
            'fixed' => true,
            'subtotal' => $billingSubtotal,
            'tax' => $taxAmount,
            'advance_paid' => round($advancePaid, 2),
            'grand_total' => $grandTotal,
            'primary_action_label' => 'Complete Sale / Collect Payment',
            'primary_color' => '#166534',
        ];

        return $schema;
    }

    /** @param  array<string, string|int|float>  $query */
    public static function appendQuery(string $endpoint, array $query): string
    {
        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').http_build_query($query);
    }

    /** @return list<array{label: string, amount: float, display: string}> */
    public static function quickCashSuggestions(float $total, string $currency): array
    {
        $amounts = [
            ['label' => 'Exact', 'amount' => $total],
            ['label' => '+'.$currency.'5', 'amount' => $total + 5],
            ['label' => '+'.$currency.'10', 'amount' => $total + 10],
            ['label' => '+'.$currency.'20', 'amount' => $total + 20],
            ['label' => 'Next Round '.$currency.'50', 'amount' => ceil(max($total, 0.01) / 50) * 50],
        ];

        return array_map(static fn (array $item) => [
            'label' => $item['label'],
            'amount' => round((float) $item['amount'], 2),
            'display' => $item['label'].' · '.$currency.number_format((float) $item['amount'], 2),
        ], $amounts);
    }

    /** @param  list<array<string, mixed>>  $categories */
    public static function collectCategory(Product $product, array &$categories, array &$seen): void
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
    public static function envelope(
        string $title,
        Company $company,
        string $searchPlaceholder,
        array $categories,
        array $items,
        string $checkoutSheetEndpoint,
    ): array {
        $registerOpen = CashRegister::openFor($company->id) !== null;

        $catalogCards = [];
        foreach ($items as $item) {
            $badgeText = $item['badge']['text'] ?? 'Item';
            $badgeColor = $item['badge']['color'] ?? '#10b981';
            $catalogCards[] = SchemaResponse::card([
                SchemaResponse::row([
                    SchemaResponse::icon('inventory_2', ['color' => '#1d4ed8', 'size' => 20]),
                    SchemaResponse::badge($badgeText, $badgeColor, 'subtle'),
                ], ['main_axis_alignment' => 'space_between']),
                SchemaResponse::text($item['title'] ?? 'Product', 'title_small', ['bold' => true]),
                SchemaResponse::text((string) ($item['subtitle'] ?? ''), 'body_small', ['color' => '#64748b']),
                SchemaResponse::row([
                    SchemaResponse::text($company->currency_symbol.number_format((float) ($item['price'] ?? 0), 2), 'title_medium', ['bold' => true, 'color' => '#059669']),
                    SchemaResponse::badge($company->currency_symbol.number_format((float) ($item['price'] ?? 0), 2), '#1d4ed8', 'subtle'),
                ], ['main_axis_alignment' => 'space_between']),
                SchemaResponse::buttonPrimary('Add to Cart', $item['on_tap'] ?? SchemaResponse::popAction(), 'add_shopping_cart'),
            ]);
        }

        $fallbackComponents = [
            SchemaResponse::card([
                SchemaResponse::textInput('search_product', $searchPlaceholder, '', [
                    'placeholder' => $searchPlaceholder,
                ]),
                SchemaResponse::divider(),
                SchemaResponse::wrap(array_map(fn ($c) => SchemaResponse::badge((string) $c['label'], '#1d4ed8', $c['id'] === null ? 'solid' : 'subtle'), $categories)),
            ]),
            SchemaResponse::card([
                SchemaResponse::text('Product Catalog', 'title_medium', ['bold' => true]),
                SchemaResponse::divider(),
                ! empty($catalogCards)
                    ? SchemaResponse::gridView($catalogCards, 2, ['spacing' => 10, 'run_spacing' => 10])
                    : SchemaResponse::text('No active products found in catalog.', 'body_medium', ['color' => '#64748b']),
            ]),
            SchemaResponse::card([
                SchemaResponse::row([
                    SchemaResponse::column([
                        SchemaResponse::text('Cart: 0 items', 'label_medium', ['color' => '#64748b']),
                        SchemaResponse::text($company->currency_symbol.'0.00', 'title_large', ['bold' => true, 'color' => '#059669']),
                    ]),
                    SchemaResponse::buttonPrimary('Open Cart / Checkout', SchemaResponse::openRemoteSheetAction($checkoutSheetEndpoint, 'Checkout & Settlement'), 'shopping_cart_checkout'),
                ], ['main_axis_alignment' => 'space_between']),
            ]),
        ];

        $schema = SchemaResponse::screen($title, $fallbackComponents);
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
        $schema['categories'] = array_values($categories);
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
    public static function sheet(string $title, array $components): array
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
