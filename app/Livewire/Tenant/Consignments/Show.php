<?php

namespace App\Livewire\Tenant\Consignments;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Auth\PermissionChecker;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Consignment Details'])]
class Show extends Component
{
    public Consignment $consignment;

    public string $paymentMethod = 'cash';

    public bool $showFinalizeModal = false;

    /** @var array<int, array{id: int, product_id: ?int, product_name: string, dispatched_qty: float, returned_qty: float, sold_qty: float, unit_price: float, billed_total: float}> */
    public array $items = [];

    /** @var array<int, array{id: int, returned: float, sold: float}> */
    public array $reconciliationInputs = [];

    public function mount(Consignment $consignment): void
    {
        $user = auth('web')->user();
        if ($user && ! PermissionChecker::can($user, 'consignments', 'view') && ! PermissionChecker::can($user, 'sales', 'view')) {
            abort(403, 'Unauthorized.');
        }

        $this->consignment = $consignment->load(['customer', 'items.product', 'user', 'company']);
        $this->loadItems();
    }

    public function loadItems(): void
    {
        $this->items = [];
        $this->reconciliationInputs = [];

        foreach ($this->consignment->items as $item) {
            $dispatched = (float) $item->dispatched_quantity;
            $returned = (float) ($item->returned_quantity ?? 0);

            if ($this->consignment->status === 'finalized') {
                $sold = (float) $item->sold_quantity;
            } else {
                // Rule: Sold Qty = Dispatched Qty - Returned Qty
                // If Returned Qty = 0, then Sold Qty = Dispatched Qty
                $sold = max(0, $dispatched - $returned);
            }

            $unitPrice = (float) $item->unit_price;
            $billedTotal = round($sold * $unitPrice, 2);

            $this->items[$item->id] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name ?? ($item->product?->name ?? 'Unknown Item'),
                'dispatched_qty' => $dispatched,
                'returned_qty' => $returned,
                'sold_qty' => $sold,
                'unit_price' => $unitPrice,
                'billed_total' => $billedTotal,
            ];

            $this->reconciliationInputs[$item->id] = [
                'id' => $item->id,
                'returned' => $returned,
                'sold' => $sold,
            ];
        }
    }

    // Reactive updates when user edits returned quantity
    public function updatedItems($value, $key): void
    {
        if (str_ends_with($key, 'returned_qty')) {
            $itemId = explode('.', $key)[0];

            if (isset($this->items[$itemId])) {
                $dispatched = (float) $this->items[$itemId]['dispatched_qty'];
                $val = is_numeric($value) ? (float) $value : 0;
                $returned = max(0, min($dispatched, $val));
                $sold = max(0, $dispatched - $returned);
                $unitPrice = (float) $this->items[$itemId]['unit_price'];
                $billedTotal = round($sold * $unitPrice, 2);

                $this->items[$itemId]['returned_qty'] = $returned;
                $this->items[$itemId]['sold_qty'] = $sold;
                $this->items[$itemId]['billed_total'] = $billedTotal;

                $this->reconciliationInputs[$itemId] = [
                    'id' => (int) $itemId,
                    'returned' => $returned,
                    'sold' => $sold,
                ];
            }
        }
    }

    public function updatedReconciliationInputs($value, $key): void
    {
        $parts = explode('.', $key);
        $itemId = $parts[0];

        if (isset($this->items[$itemId])) {
            $dispatched = (float) $this->items[$itemId]['dispatched_qty'];
            $unitPrice = (float) $this->items[$itemId]['unit_price'];

            if (str_ends_with($key, 'returned')) {
                $val = is_numeric($value) ? (float) $value : 0;
                $returned = max(0, min($dispatched, $val));
                $sold = max(0, $dispatched - $returned);
            } elseif (str_ends_with($key, 'sold')) {
                $val = is_numeric($value) ? (float) $value : 0;
                $sold = max(0, min($dispatched, $val));
                $returned = max(0, $dispatched - $sold);
            } else {
                return;
            }

            $billedTotal = round($sold * $unitPrice, 2);

            $this->items[$itemId]['returned_qty'] = $returned;
            $this->items[$itemId]['sold_qty'] = $sold;
            $this->items[$itemId]['billed_total'] = $billedTotal;

            $this->reconciliationInputs[$itemId] = [
                'id' => (int) $itemId,
                'returned' => $returned,
                'sold' => $sold,
            ];
        }
    }

    #[Computed]
    public function soldRevenue(): float
    {
        return round((float) collect($this->items)->sum('billed_total'), 2);
    }

    #[Computed]
    public function returnedAmount(): float
    {
        return round((float) collect($this->items)->sum(function ($item) {
            return (float) ($item['returned_qty'] ?? 0) * (float) ($item['unit_price'] ?? 0);
        }), 2);
    }

    #[Computed]
    public function totalDispatchedAmount(): float
    {
        return round((float) collect($this->items)->sum(function ($item) {
            return (float) ($item['dispatched_qty'] ?? 0) * (float) ($item['unit_price'] ?? 0);
        }), 2);
    }

    public function dispatchGoods(): void
    {
        if ($this->consignment->status !== 'draft') {
            return;
        }

        $this->consignment->update([
            'status' => 'dispatched',
            'dispatched_at' => now(),
        ]);

        AuditLog::record('consignment.dispatched', $this->consignment->company_id, auth('web')->id(), [
            'consignment_id' => $this->consignment->id,
            'consignment_number' => $this->consignment->consignment_number,
        ]);

        session()->flash('status', "Consignment {$this->consignment->consignment_number} dispatched to client.");
        $this->consignment->refresh();
        $this->loadItems();
    }

    public function saveReconciliation(): void
    {
        foreach ($this->items as $itemId => $itemData) {
            $dispatched = (float) $itemData['dispatched_qty'];
            $returned = max(0, min($dispatched, (float) ($itemData['returned_qty'] ?? 0)));
            $sold = max(0, $dispatched - $returned);
            $billedTotal = round($sold * (float) $itemData['unit_price'], 2);

            $this->items[$itemId]['returned_qty'] = $returned;
            $this->items[$itemId]['sold_qty'] = $sold;
            $this->items[$itemId]['billed_total'] = $billedTotal;

            $this->consignment->items()->where('id', $itemId)->update([
                'returned_quantity' => $returned,
                'sold_quantity' => $sold,
                'sold_total' => $billedTotal,
            ]);
        }

        $this->consignment->status = 'reconciled';
        $this->consignment->reconciled_at = now();
        $this->consignment->total_sold_amount = $this->soldRevenue;
        $this->consignment->total_returned_amount = $this->returnedAmount;
        $this->consignment->total_dispatched_amount = $this->totalDispatchedAmount;
        $this->consignment->save();

        AuditLog::record('consignment.reconciled', $this->consignment->company_id, auth('web')->id(), [
            'consignment_id' => $this->consignment->id,
            'consignment_number' => $this->consignment->consignment_number,
            'total_sold' => $this->consignment->total_sold_amount,
            'total_returned' => $this->consignment->total_returned_amount,
        ]);

        session()->flash('status', 'Consignment reconciliation saved.');
        $this->dispatch('toast', ['message' => 'Consignment reconciliation saved.', 'type' => 'success']);
        $this->dispatch('notify', ['message' => 'Consignment reconciliation saved.', 'type' => 'success']);
        $this->consignment->refresh();
        $this->loadItems();
    }

    public function openFinalizeModal(): void
    {
        if ($this->soldRevenue <= 0) {
            $this->dispatch('toast', [
                'message' => 'All items marked as returned ($0.00 sold). Nothing to bill.',
                'type' => 'warning',
            ]);
            $this->dispatch('notify', [
                'message' => 'All items marked as returned ($0.00 sold). Nothing to bill.',
                'type' => 'warning',
            ]);
            session()->flash('error', 'All items marked as returned ($0.00 sold). Nothing to bill.');

            return;
        }

        $this->showFinalizeModal = true;
    }

    public function confirmAndGenerateInvoice(): void
    {
        if ($this->soldRevenue <= 0) {
            $this->dispatch('toast', [
                'message' => 'All items marked as returned ($0.00 sold). Nothing to bill.',
                'type' => 'warning',
            ]);
            $this->dispatch('notify', [
                'message' => 'All items marked as returned ($0.00 sold). Nothing to bill.',
                'type' => 'warning',
            ]);
            session()->flash('error', 'All items marked as returned ($0.00 sold). Nothing to bill.');

            return;
        }

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : (auth('web')->user()?->company_id ?? $this->consignment->company_id);

        $company = Company::find($companyId);
        $prefix = $company?->invoice_prefix ?: 'INV-';
        $count = Sale::where('operation_type', 'sale')->count() + 1;
        $saleNumber = $prefix.sprintf('%04d', $count);

        $sale = DB::transaction(function () use ($companyId, $saleNumber) {
            $soldItemsList = [];
            foreach ($this->items as $itemData) {
                if ((float) ($itemData['sold_qty'] ?? 0) > 0) {
                    $soldItemsList[] = [
                        'product_id' => $itemData['product_id'] ?? null,
                        'name' => $itemData['product_name'] ?? 'Unknown Item',
                        'quantity' => (float) $itemData['sold_qty'],
                        'price' => (float) $itemData['unit_price'],
                        'total' => (float) $itemData['billed_total'],
                    ];

                    // Deduct stock for sold items
                    if (! empty($itemData['product_id'])) {
                        Product::find($itemData['product_id'])?->decrement('current_stock', (float) $itemData['sold_qty']);
                    }
                }

                // Update Consignment Item Record
                $this->consignment->items()->where('id', $itemData['id'])->update([
                    'returned_quantity' => (float) ($itemData['returned_qty'] ?? 0),
                    'sold_quantity' => (float) ($itemData['sold_qty'] ?? 0),
                    'sold_total' => (float) ($itemData['billed_total'] ?? 0),
                ]);
            }

            $paymentMethodNormalized = strtolower($this->paymentMethod);

            // 1. Create finalized Sale Invoice
            $sale = Sale::create([
                'company_id' => $companyId,
                'sale_number' => $saleNumber,
                'customer_id' => $this->consignment->customer_id,
                'customer_name' => $this->consignment->customer_name,
                'user_id' => auth('web')->id(),
                'total' => $this->soldRevenue,
                'net_amount' => $this->soldRevenue,
                'paid_amount' => $this->soldRevenue,
                'due_amount' => 0.00,
                'discount' => 0.00,
                'payment_method' => $paymentMethodNormalized,
                'agreed_payment_method' => $this->paymentMethod,
                'payment_status' => 'paid',
                'status' => 'completed',
                'operation_type' => 'sale',
                'service_type' => 'dine_in',
                'items' => $soldItemsList,
                'notes' => "Generated from Consignment #{$this->consignment->consignment_number}. ".($this->consignment->notes ?? ''),
            ]);

            OrderPayment::create([
                'company_id' => $companyId,
                'sale_id' => $sale->id,
                'payment_method' => $paymentMethodNormalized,
                'amount' => $sale->total,
                'net_amount' => $sale->total,
            ]);

            // 2. Update Consignment Status & Totals
            $this->consignment->update([
                'status' => 'finalized',
                'sale_id' => $sale->id,
                'total_sold_amount' => $this->soldRevenue,
                'total_returned_amount' => $this->returnedAmount,
                'reconciled_at' => now(),
            ]);

            return $sale;
        });

        AuditLog::record('consignment.finalized_to_sale', $companyId, auth('web')->id(), [
            'consignment_id' => $this->consignment->id,
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
        ]);

        $this->showFinalizeModal = false;
        $this->dispatch('toast', ['message' => 'Consignment invoice generated successfully!', 'type' => 'success']);
        $this->dispatch('notify', ['message' => 'Consignment invoice generated successfully!', 'type' => 'success']);
        session()->flash('status', "Consignment {$this->consignment->consignment_number} finalized to Sale {$sale->sale_number}.");

        $this->redirectRoute('tenant.sales.show', $sale, navigate: true);
    }

    public function finalizeToSale(): void
    {
        $this->confirmAndGenerateInvoice();
    }

    public function render()
    {
        $company = Company::find($this->consignment->company_id);

        return view('livewire.tenant.consignments.show', [
            'company' => $company,
        ]);
    }
}

if (! class_exists(ConsignmentShow::class, false)) {
    class_alias(Show::class, ConsignmentShow::class);
}
