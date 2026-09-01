<?php

namespace App\Livewire\Tenant\Financials;

use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Dedicated transaction history for one payment method (Settings > Payment
 * Methods > [method] > "Ledger"). Matches order_payments.payment_method
 * against this method's code/name — there's no FK between the two tables
 * (see PaymentMethod model docs), so this is a best-effort string match.
 */
#[Layout('layouts.tenant', ['title' => 'Payment Method Ledger'])]
class PaymentMethodLedger extends Component
{
    use WithPagination;

    public PaymentMethod $paymentMethod;

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(PaymentMethod $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    protected function matchingCodes(): array
    {
        return array_values(array_unique(array_filter([$this->paymentMethod->code, $this->paymentMethod->name])));
    }

    protected function baseQuery()
    {
        $query = OrderPayment::with('sale.customer')
            ->where('company_id', $this->paymentMethod->company_id)
            ->whereIn('payment_method', $this->matchingCodes());

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        return $query->orderByDesc('created_at');
    }

    public function exportCsv()
    {
        $filename = 'payment-method-'.($this->paymentMethod->code ?: $this->paymentMethod->id).'-'.now()->format('Ymd-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $rows = $this->baseQuery()->get();

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'Time', 'Order ID', 'Payment Method', 'Customer', 'Amount', 'Reference No', 'Status']);
            foreach ($rows as $payment) {
                $sale = $payment->sale;
                fputcsv($file, [
                    $payment->created_at?->format('Y-m-d'),
                    $payment->created_at?->format('H:i:s'),
                    $sale?->sale_number ?? $payment->sale_id,
                    $payment->payment_method,
                    $sale?->customer?->name ?? $sale?->customer_name ?? '',
                    number_format((float) $payment->amount, 2),
                    $payment->reference_number ?? '',
                    $sale?->payment_status ?? '',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function render()
    {
        return view('livewire.tenant.financials.payment-method-ledger', [
            'transactions' => $this->baseQuery()->paginate(20),
        ]);
    }
}
