<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\Sale;
use App\Services\Financial\CustomerLedgerService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

/**
 * Guarantees every due-creating sale lands a customer_ledgers entry and
 * updates customers.due_balance, regardless of which code path created the
 * Sale row (Livewire checkout, the mobile sync endpoint, a data import, a
 * test factory, ...) — a manually-called service at each call site can't
 * make that guarantee on its own.
 */
class SaleObserver
{
    public function created(Sale $sale): void
    {
        $this->syncSaleItems($sale);

        // Consignment dues are tracked via the Consignment model instead of
        // the customer receivables ledger.
        if ($sale->payment_method === 'consignment' || (float) $sale->due_amount <= 0) {
            return;
        }

        app(CustomerLedgerService::class)->recordInvoice($sale);
    }

    public function updated(Sale $sale): void
    {
        if ($sale->wasChanged('items')) {
            $this->syncSaleItems($sale);
        }
    }

    private function syncSaleItems(Sale $sale): void
    {
        if (! Schema::hasTable('sale_items')) {
            return;
        }

        $lines = array_values(array_filter((array) ($sale->items ?? []), 'is_array'));
        $candidateProductIds = collect($lines)
            ->pluck('product_id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $validProductIds = $candidateProductIds->isEmpty()
            ? collect()
            : Product::withoutGlobalScope('company')
                ->where('company_id', $sale->company_id)
                ->whereIn('id', $candidateProductIds)
                ->pluck('id')
                ->mapWithKeys(fn ($id) => [(string) $id => true]);

        $sale->saleItems()->delete();
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $quantity = max(0, (float) ($line['quantity'] ?? $line['qty'] ?? 1));
            $unitPrice = max(0, (float) ($line['unit_price'] ?? $line['price'] ?? 0));
            $subtotal = (float) ($line['subtotal'] ?? round($quantity * $unitPrice, 2));
            $lineType = (string) ($line['line_type'] ?? match (true) {
                isset($line['batch_id']) && $line['batch_id'] !== null => 'medication',
                isset($line['duration_minutes']) && $line['duration_minutes'] !== null => 'service',
                $sale->module_type === 'repair' && empty($line['product_id']) => 'service_labor',
                default => 'product',
            });
            $sourceProductId = $line['product_id'] ?? null;
            $productId = is_numeric($sourceProductId) && $validProductIds->has((string) ((int) $sourceProductId))
                ? (int) $sourceProductId
                : null;
            $metadata = Arr::except($line, [
                'product_id', 'batch_id', 'specialist_id', 'staff_id', 'line_type',
                'name', 'title', 'quantity', 'qty', 'unit_price', 'price', 'subtotal',
                'discount_amount', 'taxable_amount', 'tax_amount', 'total', 'notes',
            ]);
            if ($sourceProductId !== null && $productId === null) {
                $metadata['source_product_id'] = $sourceProductId;
            }

            $sale->saleItems()->create([
                'company_id' => $sale->company_id,
                'product_id' => $productId,
                'batch_id' => $line['batch_id'] ?? null,
                'staff_id' => $line['specialist_id'] ?? $line['staff_id'] ?? null,
                'line_type' => $lineType,
                'name' => (string) ($line['name'] ?? $line['title'] ?? 'Sale Item'),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'discount_amount' => (float) ($line['discount_amount'] ?? 0),
                'taxable_amount' => (float) ($line['taxable_amount'] ?? $subtotal),
                'tax_amount' => (float) ($line['tax_amount'] ?? 0),
                'total' => (float) ($line['total'] ?? round($subtotal + (float) ($line['tax_amount'] ?? 0), 2)),
                'notes' => $line['dosage_notes'] ?? $line['notes'] ?? null,
                'metadata' => $metadata,
            ]);
        }
    }
}
