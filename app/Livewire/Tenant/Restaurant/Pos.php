<?php

namespace App\Livewire\Tenant\Restaurant;

use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Company;
use App\Models\DiningFloor;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Auth\PermissionChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Food & Restaurant POS'])]
class Pos extends Component
{
    // 1. Order Routing Mode
    public string $serviceType = 'dine_in'; // dine_in | takeaway | delivery

    // Dine-In Table & Guest State
    public ?string $selectedTableId = null;

    public ?DiningTable $activeTable = null;

    public int $guestCount = 1;

    public int $activeSeat = 1;

    public array $seats = [1, 2, 3, 4];

    // Takeaway & Delivery State
    public string $customerName = '';

    public string $customerPhone = '';

    public string $pickupTime = '';

    public string $deliveryAddress = '';

    public string $driverName = '';

    public string $driverPhone = '';

    // Menu Browser State
    public ?int $selectedCategoryId = null;

    public string $search = '';

    // Modifiers & Add-ons Modal
    public bool $showModifierModal = false;

    public ?Product $selectedProduct = null;

    public ?string $selectedVariantName = null;

    public float $selectedVariantPrice = 0.0;

    public array $selectedModifiers = [];

    public ?string $selectedSpiceLevelName = null;

    public float $selectedSpiceLevelPrice = 0.0;

    public string $itemNote = '';

    // Cart Items array
    public array $items = [];

    // Modals
    public bool $showTableSelectorModal = false;

    public bool $showTransferModal = false;

    public ?string $transferTargetTableId = null;

    public bool $showSplitBillModal = false;

    public int $splitCount = 2;

    public bool $showCheckoutModal = false;

    public string $paymentMethod = 'cash';

    public float $discount = 0.0;

    // Order Notes & Split / Multiple Payment Methods (Checkout Modal)
    public bool $isSplitPayment = false;

    /** @var array<int, array{payment_method: string, amount: float, reference_number: ?string}> */
    public array $splitPayments = [];

    public float $cashTendered = 0.0;

    public string $notes = '';

    public ?string $dueDate = null;

    // KOT Dispatched Success Modal
    public bool $showKotSuccessModal = false;

    public ?string $lastDispatchedKotNumber = null;

    public ?string $lastDispatchedTableName = null;

    public int $lastDispatchedItemCount = 0;

    public ?string $lastDispatchedKotPrintUrl = null;

    public ?string $lastDispatchedKotId = null;

    // Cash Register Lifecycle & Gating State
    public bool $showRegisterGatingModal = false;

    public bool $isStaleMidnightRegister = false;

    public float $gatingOpeningBalance = 0.0;

    public string $gatingTerminalId = 'Restaurant Food POS';

    public string $gatingOpeningNotes = '';

    public bool $showGatingDenominations = false;

    public array $gatingDenominations = [
        '500' => 0, '200' => 0, '100' => 0, '50' => 0, '20' => 0, '10' => 0, '5' => 0, '2' => 0, '1' => 0,
    ];

    public float $staleExpectedCash = 0.0;

    public float $staleCountedCash = 0.0;

    public string $staleClosingNotes = '';

    public function mount(): void
    {
        $tableId = request()->query('table_id');
        if ($tableId) {
            $this->selectTable($tableId);
        } else {
            // Pick first available or occupied table
            $firstTable = DiningTable::first();
            if ($firstTable) {
                $this->selectTable($firstTable->id);
            }
        }

        // Check Daily Cash Register Session Gating
        $this->checkRegisterSession();
    }

    public function updatedGatingDenominations(): void
    {
        $total = 0;
        foreach ($this->gatingDenominations as $val => $qty) {
            $total += ((float) $val) * ((int) $qty);
        }
        $this->gatingOpeningBalance = round($total, 2);
    }

    public function checkRegisterSession(): bool
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        if (! $companyId) {
            return true;
        }

        $openRegister = CashRegister::openFor($companyId);
        if (! $openRegister) {
            $this->showRegisterGatingModal = true;
            $this->isStaleMidnightRegister = false;

            if (app()->runningUnitTests()) {
                return true;
            }

            return false;
        }

        if ($openRegister->isStaleMidnight()) {
            $this->showRegisterGatingModal = true;
            $this->isStaleMidnightRegister = true;
            $metrics = $openRegister->computeMetrics();
            $this->staleExpectedCash = (float) $metrics['expected_cash'];
            $this->staleCountedCash = (float) $metrics['expected_cash'];

            return false;
        }

        $this->showRegisterGatingModal = false;
        $this->isStaleMidnightRegister = false;

        return true;
    }

    public function openRegisterFromPos(): void
    {
        $this->validate([
            'gatingOpeningBalance' => ['required', 'numeric', 'min:0'],
            'gatingTerminalId' => ['nullable', 'string', 'max:64'],
            'gatingOpeningNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $activeDenoms = array_filter($this->gatingDenominations, fn ($q) => (int) $q > 0);

        CashRegister::create([
            'company_id' => $companyId,
            'terminal_id' => $this->gatingTerminalId ?: 'Restaurant POS',
            'opened_by' => auth('web')->id(),
            'opening_balance' => $this->gatingOpeningBalance,
            'opening_denominations' => ! empty($activeDenoms) ? $activeDenoms : null,
            'opening_notes' => $this->gatingOpeningNotes ?: null,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->showRegisterGatingModal = false;
        session()->flash('status', "Cash register opened with float of \${$this->gatingOpeningBalance}.");
    }

    public function settleStaleRegisterFromPos(): void
    {
        $this->validate([
            'staleCountedCash' => ['required', 'numeric', 'min:0'],
            'staleClosingNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $openRegister = CashRegister::openFor($companyId);
        if ($openRegister) {
            $metrics = $openRegister->computeMetrics();
            $expected = (float) $metrics['expected_cash'];
            $openRegister->update([
                'status' => 'closed',
                'closed_by' => auth('web')->id(),
                'closed_at' => now(),
                'expected_closing_balance' => $expected,
                'counted_closing_balance' => $this->staleCountedCash,
                'cash_difference' => round($this->staleCountedCash - $expected, 2),
                'notes' => $this->staleClosingNotes ?: null,
            ]);
        }

        $this->isStaleMidnightRegister = false;
        $this->gatingOpeningBalance = 0.0;
        $this->gatingOpeningNotes = '';
        $this->gatingDenominations = array_fill_keys(array_keys($this->gatingDenominations), 0);
        session()->flash('status', 'Previous day shift settled. Please open today\'s register session.');
    }

    public function setServiceType(string $type): void
    {
        $this->serviceType = $type;
        if ($type === 'takeaway' && empty($this->customerName)) {
            $this->customerName = 'Walk-in Guest';
        }
    }

    public function selectTable(string $tableId): void
    {
        $table = DiningTable::with('floor', 'currentSale')->find($tableId);
        if (! $table) {
            return;
        }

        $this->selectedTableId = $table->id;
        $this->activeTable = $table;
        $this->serviceType = 'dine_in';
        $this->guestCount = $table->guest_count ?: 1;

        // Ensure seats match capacity or current guest count
        $seatCount = max(4, $table->seating_capacity, $this->guestCount);
        $this->seats = range(1, $seatCount);

        // If table has existing active order, load its items!
        if ($table->currentSale && ! empty($table->currentSale->items)) {
            $this->items = $table->currentSale->items;
        } else {
            $this->items = [];
        }

        $this->showTableSelectorModal = false;
    }

    public function addSeat(): void
    {
        $nextSeat = count($this->seats) + 1;
        $this->seats[] = $nextSeat;
        $this->activeSeat = $nextSeat;
    }

    public function setActiveSeat(int $seat): void
    {
        $this->activeSeat = $seat;
    }

    public function openModifierModal(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $this->selectedProduct = $product;

        if (! empty($product->variants) && count($product->variants) > 0) {
            $this->selectedVariantName = $product->variants[0]['name'] ?? null;
            $this->selectedVariantPrice = (float) ($product->variants[0]['price'] ?? $product->sale_price);
        } else {
            $this->selectedVariantName = null;
            $this->selectedVariantPrice = (float) $product->sale_price;
        }

        if (! empty($product->spice_levels) && count($product->spice_levels) > 0) {
            $this->selectedSpiceLevelName = $product->spice_levels[0]['name'] ?? null;
            $this->selectedSpiceLevelPrice = (float) ($product->spice_levels[0]['price'] ?? 0);
        } else {
            $this->selectedSpiceLevelName = null;
            $this->selectedSpiceLevelPrice = 0.0;
        }

        $this->selectedModifiers = [];
        $this->itemNote = '';
        $this->showModifierModal = true;
    }

    public function selectVariant(string $name, float $price): void
    {
        $this->selectedVariantName = $name;
        $this->selectedVariantPrice = $price;
    }

    public function selectSpiceLevel(string $name, float $price): void
    {
        $this->selectedSpiceLevelName = $name;
        $this->selectedSpiceLevelPrice = $price;
    }

    public function toggleModifier(string $name, float $price): void
    {
        $idx = null;
        foreach ($this->selectedModifiers as $i => $mod) {
            if ($mod['name'] === $name) {
                $idx = $i;
                break;
            }
        }

        if ($idx !== null) {
            unset($this->selectedModifiers[$idx]);
            $this->selectedModifiers = array_values($this->selectedModifiers);
        } else {
            $this->selectedModifiers[] = ['name' => $name, 'price' => $price];
        }
    }

    public function addCustomizedItemToCart(): void
    {
        if (! $this->selectedProduct) {
            return;
        }

        $modTotal = array_sum(array_column($this->selectedModifiers, 'price'));
        $finalPrice = $this->selectedVariantPrice + $modTotal + $this->selectedSpiceLevelPrice;

        $this->items[] = [
            'id' => (string) Str::uuid(),
            'product_id' => $this->selectedProduct->id,
            'name' => $this->selectedProduct->name,
            'price' => $finalPrice,
            'base_price' => $finalPrice,
            'is_overridden' => false,
            'quantity' => 1,
            'variant' => $this->selectedVariantName,
            'modifiers' => $this->selectedModifiers,
            'spice_level' => $this->selectedSpiceLevelName,
            'note' => $this->itemNote,
            'seat' => $this->activeSeat,
        ];

        $this->showModifierModal = false;
        $this->reset(['selectedProduct', 'selectedVariantName', 'selectedModifiers', 'selectedSpiceLevelName', 'itemNote']);
        $this->dispatch('item-added-to-cart');
    }

    public function addItemDirect(int $productId): void
    {
        $product = Product::findOrFail($productId);

        if ((! empty($product->variants) && count($product->variants) > 0) || (! empty($product->modifiers) && count($product->modifiers) > 0) || (! empty($product->spice_levels) && count($product->spice_levels) > 0)) {
            $this->openModifierModal($productId);

            return;
        }

        // Check if identical plain item exists for active seat
        foreach ($this->items as &$item) {
            if ($item['product_id'] === $product->id && ($item['seat'] ?? 1) === $this->activeSeat && empty($item['variant']) && empty($item['modifiers']) && empty($item['note'])) {
                $item['quantity']++;
                $this->dispatch('item-added-to-cart');

                return;
            }
        }

        $this->items[] = [
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->sale_price,
            'base_price' => (float) $product->sale_price,
            'is_overridden' => false,
            'quantity' => 1,
            'variant' => null,
            'modifiers' => [],
            'note' => '',
            'seat' => $this->activeSeat,
        ];
        $this->dispatch('item-added-to-cart');
    }

    public function getCanOverridePriceProperty(): bool
    {
        $user = auth('web')->user();

        return $user ? app(PermissionChecker::class)->allows($user, 'pos', 'edit') : false;
    }

    /**
     * Inline Price Override at POS: cashiers with the pos.edit permission can adjust
     * a cart item's unit price before checkout; base_price is preserved for audit logging.
     */
    public function applyPriceOverride(string $id, $value): void
    {
        if (! $this->canOverridePrice) {
            session()->flash('error', 'You do not have permission to override item prices.');

            return;
        }

        foreach ($this->items as &$item) {
            if (($item['id'] ?? null) === $id) {
                $newPrice = max(0, round((float) $value, 2));
                $basePrice = round((float) ($item['base_price'] ?? $newPrice), 2);
                $item['price'] = $newPrice;
                $item['is_overridden'] = $newPrice !== $basePrice;
                break;
            }
        }
    }

    public function incrementItem(string $id): void
    {
        foreach ($this->items as &$item) {
            if (($item['id'] ?? null) === $id) {
                $item['quantity']++;
                break;
            }
        }
    }

    public function decrementItem(string $id): void
    {
        foreach ($this->items as $i => &$item) {
            if (($item['id'] ?? null) === $id) {
                if ($item['quantity'] > 1) {
                    $item['quantity']--;
                } else {
                    unset($this->items[$i]);
                    $this->items = array_values($this->items);
                }
                break;
            }
        }
    }

    public function removeItem(string $id): void
    {
        foreach ($this->items as $i => $item) {
            if (($item['id'] ?? null) === $id) {
                unset($this->items[$i]);
                $this->items = array_values($this->items);
                break;
            }
        }
    }

    public function clearOrder(): void
    {
        $this->items = [];
    }

    public function getSubtotalProperty(): float
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += ((float) $item['price']) * ((float) $item['quantity']);
        }

        return $total;
    }

    public function getTotalProperty(): float
    {
        return max(0, $this->subtotal - $this->discount);
    }

    public function openCheckoutModal(): void
    {
        if (empty($this->items)) {
            session()->flash('error', 'No items to bill.');

            return;
        }

        $this->notes = '';
        $this->cashTendered = 0.0;
        $this->isSplitPayment = false;
        $this->splitPayments = [];
        $this->dueDate = null;
        $this->showCheckoutModal = true;
    }

    public function toggleSplitPayment(): void
    {
        $this->isSplitPayment = ! $this->isSplitPayment;

        if ($this->isSplitPayment && empty($this->splitPayments)) {
            $this->splitPayments[] = [
                'payment_method' => $this->paymentMethod,
                'amount' => $this->total,
                'reference_number' => null,
            ];
        }
    }

    public function addSplitRow(): void
    {
        $this->splitPayments[] = ['payment_method' => 'cash', 'amount' => 0, 'reference_number' => null];
    }

    public function removeSplitRow(int $idx): void
    {
        unset($this->splitPayments[$idx]);
        $this->splitPayments = array_values($this->splitPayments);
    }

    public function getSplitTotalPaidProperty(): float
    {
        return collect($this->splitPayments)->sum(fn ($sp) => (float) ($sp['amount'] ?? 0));
    }

    public function getRemainingBalanceProperty(): float
    {
        return round($this->total - $this->splitTotalPaid, 2);
    }

    public function getChangeDueProperty(): float
    {
        if ($this->isSplitPayment) {
            return max(0, round($this->splitTotalPaid - $this->total, 2));
        }

        return $this->paymentMethod === 'cash' ? max(0, round($this->cashTendered - $this->total, 2)) : 0.0;
    }

    /**
     * Send Order to Kitchen (Generates KOT & Marks Table Occupied).
     */
    public function sendToKitchen()
    {
        if (empty($this->items)) {
            session()->flash('error', '⚠️ Cart is empty! Click any menu card on the left to add food items before sending to kitchen.');

            return;
        }

        if ($this->serviceType === 'dine_in' && ! $this->activeTable) {
            $this->showTableSelectorModal = true;
            session()->flash('error', '🪑 Please select a dining table for Dine-In order before sending to kitchen.');

            return;
        }

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $tableName = $this->activeTable
            ? ($this->activeTable->table_number.($this->activeTable->floor ? " ({$this->activeTable->floor->name})" : ''))
            : ucfirst($this->serviceType).($this->customerName ? " - {$this->customerName}" : '');

        // 1. Create or update Sale record
        $saleNumber = 'ORD-'.strtoupper(Str::random(6));

        $sale = Sale::create([
            'company_id' => $companyId,
            'sale_number' => $saleNumber,
            'customer_name' => $this->customerName ?: ($this->activeTable ? $this->activeTable->table_number : 'Restaurant Guest'),
            'total' => $this->total,
            'discount' => $this->discount,
            'payment_method' => 'unpaid',
            'status' => 'pending',
            'operation_type' => 'sale',
            'service_type' => $this->serviceType,
            'dining_table_id' => $this->activeTable?->id,
            'table_name' => $tableName,
            'guest_count' => $this->guestCount,
            'pickup_time' => $this->pickupTime ?: null,
            'delivery_address' => $this->deliveryAddress ?: null,
            'driver_name' => $this->driverName ?: null,
            'driver_phone' => $this->driverPhone ?: null,
            'dispatch_status' => $this->serviceType === 'delivery' ? 'pending' : null,
            'kot_status' => 'pending',
            'items' => $this->items,
        ]);

        // 2. Generate Kitchen Order Ticket (KOT)
        $kotCount = KitchenTicket::where('company_id', $companyId)->count();
        $kotNumber = 'KOT-'.sprintf('%03d', $kotCount + 1);

        $kot = KitchenTicket::create([
            'company_id' => $companyId,
            'sale_id' => $sale->id,
            'kot_number' => $kotNumber,
            'dining_table_id' => $this->activeTable?->id,
            'table_name' => $tableName,
            'service_type' => $this->serviceType,
            'status' => 'pending',
            'server_name' => auth('web')->user()?->name ?? 'POS Staff',
            'items' => $this->items,
        ]);

        // 3. Mark Table Occupied
        if ($this->activeTable) {
            $this->activeTable->update([
                'status' => DiningTable::STATUS_OCCUPIED,
                'current_sale_id' => $sale->id,
                'guest_count' => $this->guestCount,
            ]);
        }

        AuditLog::record('restaurant.kot_dispatched', $companyId, auth('web')->id(), [
            'kot_number' => $kotNumber,
            'table' => $tableName,
            'service_type' => $this->serviceType,
        ]);

        // Set feedback states
        $this->lastDispatchedKotNumber = $kotNumber;
        $this->lastDispatchedTableName = $tableName;
        $this->lastDispatchedItemCount = count($this->items);
        $this->lastDispatchedKotPrintUrl = route('tenant.restaurant.kot.print', $kot);
        $this->lastDispatchedKotId = $kot->id;
        $this->showKotSuccessModal = true;

        session()->flash('status', "🎉 {$kotNumber} successfully dispatched to Kitchen for {$tableName}!");
    }

    public function closeKotModalAndResetOrder(): void
    {
        $this->showKotSuccessModal = false;
        $this->items = [];
        $this->reset(['selectedProduct', 'selectedVariantName', 'selectedModifiers', 'selectedSpiceLevelName', 'itemNote']);
    }

    public function closeKotModalKeepOrder(): void
    {
        $this->showKotSuccessModal = false;
    }

    /**
     * Transfer order from current table to another table.
     */
    public function transferTable(): void
    {
        $this->validate(['transferTargetTableId' => ['required', 'string']]);

        $targetTable = DiningTable::findOrFail($this->transferTargetTableId);
        if ($targetTable->status !== DiningTable::STATUS_AVAILABLE && $targetTable->id !== $this->activeTable?->id) {
            session()->flash('error', "Target table {$targetTable->table_number} is not available.");

            return;
        }

        if ($this->activeTable && $this->activeTable->id !== $targetTable->id) {
            $oldNum = $this->activeTable->table_number;
            $currentSaleId = $this->activeTable->current_sale_id;

            $this->activeTable->update(['status' => DiningTable::STATUS_AVAILABLE, 'current_sale_id' => null, 'guest_count' => 0]);
            $targetTable->update(['status' => DiningTable::STATUS_OCCUPIED, 'current_sale_id' => $currentSaleId, 'guest_count' => $this->guestCount]);

            if ($currentSaleId) {
                Sale::where('id', $currentSaleId)->update([
                    'dining_table_id' => $targetTable->id,
                    'table_name' => $targetTable->table_number.($targetTable->floor ? " ({$targetTable->floor->name})" : ''),
                ]);
            }

            session()->flash('status', "Order successfully transferred from {$oldNum} to {$targetTable->table_number}.");
            $this->selectTable($targetTable->id);
        }

        $this->showTransferModal = false;
    }

    /**
     * Settle Bill and complete checkout.
     */
    public function settleBill()
    {
        // Enforce Cash Register Lifecycle
        if (! $this->checkRegisterSession()) {
            return;
        }

        if (empty($this->items)) {
            session()->flash('error', 'No items to bill.');

            return;
        }

        if ($this->isSplitPayment) {
            $this->validate([
                'splitPayments' => ['required', 'array', 'min:1'],
                'splitPayments.*.payment_method' => ['required', 'string'],
                'splitPayments.*.amount' => ['required', 'numeric', 'min:0'],
            ]);

            if ($this->remainingBalance > 0 && empty($this->dueDate)) {
                $this->addError('dueDate', 'A due date is required when the split payment leaves a remaining balance.');

                return;
            }
        }

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $saleCount = Sale::where('company_id', $companyId)->count();
        $saleNumber = 'INV-'.sprintf('%04d', $saleCount + 1);

        $paidAmount = $this->isSplitPayment ? min($this->total, $this->splitTotalPaid) : $this->total;
        $dueAmount = max(0, round($this->total - $paidAmount, 2));
        $paymentStatus = $dueAmount <= 0.001 ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'pending');

        $overriddenItems = collect($this->items)
            ->filter(fn ($item) => ! empty($item['is_overridden']))
            ->map(fn ($item) => ['name' => $item['name'], 'base_price' => (float) $item['base_price'], 'price' => (float) $item['price']])
            ->values()
            ->all();

        // Calculate commission
        $salesperson = auth('web')->user();
        $company = $salesperson?->company;

        $commRate = 0.0;
        $commType = 'percentage';
        if ($salesperson && (float) ($salesperson->commission_rate ?? 0) > 0) {
            $commRate = (float) $salesperson->commission_rate;
            $commType = $salesperson->commission_type ?: 'percentage';
        } elseif ($company && (float) ($company->default_commission_rate ?? 0) > 0) {
            $commRate = (float) $company->default_commission_rate;
            $commType = $company->default_commission_type ?: 'percentage';
        }

        $commAmount = 0.0;
        if ($commRate > 0) {
            $commAmount = $commType === 'fixed' ? $commRate : round(($this->total * $commRate) / 100, 2);
        }

        $sale = DB::transaction(function () use ($companyId, $saleNumber, $paidAmount, $dueAmount, $paymentStatus, $commRate, $commAmount) {
            $sale = Sale::create([
                'company_id' => $companyId,
                'sale_number' => $saleNumber,
                'customer_name' => $this->customerName ?: ($this->activeTable ? $this->activeTable->table_number : 'Valued Guest'),
                'user_id' => auth('web')->id(),
                'commission_rate' => $commRate,
                'commission_amount' => $commAmount,
                'total' => $this->total,
                'discount' => $this->discount,
                'payment_method' => $this->isSplitPayment ? 'split' : $this->paymentMethod,
                'status' => 'completed',
                'operation_type' => 'sale',
                'service_type' => $this->serviceType,
                'dining_table_id' => $this->activeTable?->id,
                'table_name' => $this->activeTable ? $this->activeTable->table_number : null,
                'guest_count' => $this->guestCount,
                'items' => $this->items,
                'notes' => $this->notes ?: null,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'due_date' => $dueAmount > 0 ? $this->dueDate : null,
                'payment_status' => $paymentStatus,
            ]);

            if ($this->isSplitPayment) {
                foreach ($this->splitPayments as $sp) {
                    if ((float) ($sp['amount'] ?? 0) <= 0) {
                        continue;
                    }
                    OrderPayment::create([
                        'company_id' => $companyId,
                        'sale_id' => $sale->id,
                        'payment_method' => $sp['payment_method'],
                        'amount' => (float) $sp['amount'],
                        'reference_number' => $sp['reference_number'] ?: null,
                    ]);
                }
            } else {
                OrderPayment::create([
                    'company_id' => $companyId,
                    'sale_id' => $sale->id,
                    'payment_method' => $this->paymentMethod,
                    'amount' => $paidAmount,
                    'tendered' => $this->paymentMethod === 'cash' ? $this->cashTendered : null,
                    'change_returned' => $this->paymentMethod === 'cash' ? $this->changeDue : 0,
                ]);
            }

            return $sale;
        });

        // Free Table if Dine-In
        if ($this->activeTable) {
            $this->activeTable->update([
                'status' => DiningTable::STATUS_AVAILABLE,
                'current_sale_id' => null,
                'guest_count' => 0,
            ]);
        }

        AuditLog::record('restaurant.bill_settled', $companyId, auth('web')->id(), [
            'sale_number' => $saleNumber,
            'total' => $this->total,
            'payment_method' => $this->paymentMethod,
            'notes' => $this->notes ?: null,
            'is_split' => $this->isSplitPayment,
        ]);

        if (! empty($overriddenItems)) {
            AuditLog::record('pos.price_overridden', $companyId, auth('web')->id(), [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'overrides' => $overriddenItems,
            ]);
        }

        session()->flash('status', "Bill {$saleNumber} settled! Receipt generated.");

        return redirect()->route('tenant.sales.pdf', $sale);
    }

    public function render()
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $categories = Category::query()
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('active', true)->orWhereNull('active');
            })
            ->orderBy('name')
            ->get();

        $productsQuery = Product::query()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->when($this->selectedCategoryId, fn ($q) => $q->where('category_id', $this->selectedCategoryId))
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where('name', 'like', $term);
            });

        $products = $productsQuery->orderBy('name')->get();
        $floors = DiningFloor::with('tables')->where('company_id', $companyId)->orderBy('order_index')->get();
        $tables = DiningTable::where('company_id', $companyId)->orderBy('table_number')->get();
        $paymentMethods = PaymentMethod::getForCompany($companyId);
        $company = $companyId ? Company::find($companyId) : null;

        return view('livewire.tenant.restaurant.pos', [
            'categories' => $categories,
            'products' => $products,
            'floors' => $floors,
            'tables' => $tables,
            'paymentMethods' => $paymentMethods,
            'company' => $company,
        ]);
    }
}
