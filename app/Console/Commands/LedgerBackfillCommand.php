<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Sale;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off seed for customers.due_balance and customer_ledgers from
 * pre-existing sales/order_payments data, run once when this feature is
 * deployed so already-due customers aren't reset to a zero balance.
 */
#[Signature('ledger:backfill')]
#[Description('Seed customer due_balance and customer_ledgers from existing sales history')]
class LedgerBackfillCommand extends Command
{
    public function handle(): int
    {
        if (CustomerLedger::query()->exists()) {
            $this->warn('customer_ledgers already has entries — skipping to avoid double-counting. Truncate it first if you really want to re-run this.');

            return self::FAILURE;
        }

        $customers = Customer::withoutGlobalScope('company')->whereHas('sales', function ($q) {
            $q->where('status', '!=', 'cancelled')->where('due_amount', '>', 0);
        })->get();

        $this->info("Backfilling ledger for {$customers->count()} customers with outstanding dues...");

        foreach ($customers as $customer) {
            DB::transaction(function () use ($customer) {
                $dueSales = Sale::withoutGlobalScope('company')
                    ->where('customer_id', $customer->id)
                    ->where('status', '!=', 'cancelled')
                    ->where('due_amount', '>', 0)
                    ->orderBy('created_at')
                    ->get();

                $balance = 0.0;
                foreach ($dueSales as $sale) {
                    $balance = round($balance + (float) $sale->due_amount, 2);
                    CustomerLedger::create([
                        'company_id' => $sale->company_id,
                        'customer_id' => $customer->id,
                        'sale_id' => $sale->id,
                        'type' => 'invoice',
                        'amount' => $sale->due_amount,
                        'balance_after' => $balance,
                        'description' => 'Backfill: Sale '.($sale->sale_number ?: '#'.$sale->id),
                    ]);
                }

                $customer->update(['due_balance' => $balance]);
            });
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
