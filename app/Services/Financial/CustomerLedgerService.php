<?php

namespace App\Services\Financial;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\OrderPayment;
use App\Models\Sale;

/**
 * Single write path for the customer receivables ledger. Every caller that
 * creates a due sale or applies a payment against one should go through
 * this service instead of touching customers.due_balance directly, so the
 * cached balance and the customer_ledgers audit trail never drift apart.
 *
 * Callers are expected to already be inside a DB transaction.
 */
class CustomerLedgerService
{
    /**
     * Record the due portion of a newly created sale, if any.
     */
    public function recordInvoice(Sale $sale): ?CustomerLedger
    {
        if (empty($sale->customer_id) || (float) $sale->due_amount <= 0) {
            return null;
        }

        $customer = Customer::withoutGlobalScope('company')->find($sale->customer_id);
        if (! $customer) {
            return null;
        }

        $newBalance = round((float) $customer->due_balance + (float) $sale->due_amount, 2);
        $customer->update(['due_balance' => $newBalance]);

        return CustomerLedger::create([
            'company_id' => $sale->company_id,
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'type' => 'invoice',
            'amount' => $sale->due_amount,
            'balance_after' => $newBalance,
            'description' => 'Sale '.($sale->sale_number ?: '#'.$sale->id),
        ]);
    }

    /**
     * Record a payment applied against a sale's due balance.
     */
    public function recordPayment(Sale $sale, OrderPayment $payment): ?CustomerLedger
    {
        if (empty($sale->customer_id) || (float) $payment->amount <= 0) {
            return null;
        }

        $customer = Customer::withoutGlobalScope('company')->find($sale->customer_id);
        if (! $customer) {
            return null;
        }

        $newBalance = max(0, round((float) $customer->due_balance - (float) $payment->amount, 2));
        $customer->update(['due_balance' => $newBalance]);

        return CustomerLedger::create([
            'company_id' => $sale->company_id,
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'order_payment_id' => $payment->id,
            'type' => 'payment',
            'amount' => -$payment->amount,
            'balance_after' => $newBalance,
            'description' => 'Payment for '.($sale->sale_number ?: '#'.$sale->id),
        ]);
    }
}
