<?php

namespace App\Observers;

use App\Models\Sale;
use App\Services\Financial\CustomerLedgerService;

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
        // Consignment dues are tracked via the Consignment model instead of
        // the customer receivables ledger.
        if ($sale->payment_method === 'consignment' || (float) $sale->due_amount <= 0) {
            return;
        }

        app(CustomerLedgerService::class)->recordInvoice($sale);
    }
}
