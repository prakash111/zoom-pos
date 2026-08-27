<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use BelongsToCompany;
    use SyncableModel;

    protected $fillable = [
        'company_id',
        'external_id',
        'terminal_id',
        'opened_by',
        'closed_by',
        'opening_balance',
        'opening_denominations',
        'expected_closing_balance',
        'counted_closing_balance',
        'closing_denominations',
        'cash_difference',
        'status',
        'opened_at',
        'closed_at',
        'notes',
        'opening_notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'expected_closing_balance' => 'decimal:2',
            'counted_closing_balance' => 'decimal:2',
            'cash_difference' => 'decimal:2',
            'opening_denominations' => 'array',
            'closing_denominations' => 'array',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function transactions()
    {
        return $this->hasMany(CashRegisterTransaction::class)->orderByDesc('created_at');
    }

    public static function openFor(?string $companyId): ?self
    {
        if (empty($companyId)) {
            return null;
        }

        return static::where('company_id', $companyId)->where('status', 'open')->latest('opened_at')->first();
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Determine if the active cash register session has crossed midnight into a new day.
     */
    public function isStaleMidnight(): bool
    {
        if (! $this->isOpen() || ! $this->opened_at) {
            return false;
        }

        return ! $this->opened_at->isToday();
    }

    /**
     * Compute real-time granular financial shift metrics for X/Z Reports.
     */
    public function computeMetrics(): array
    {
        $windowStart = $this->opened_at;
        $windowEnd = $this->closed_at ?? now();

        $paymentsByMethod = OrderPayment::query()
            ->where('company_id', $this->company_id)
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $cashSales = (float) ($paymentsByMethod->get('cash')->total ?? 0);
        $cardSales = (float) ($paymentsByMethod->get('card')->total ?? 0);
        $upiSales = (float) ($paymentsByMethod->get('upi')->total ?? 0);
        $creditSales = (float) ($paymentsByMethod->get('credit')->total ?? 0);
        $totalSales = (float) $paymentsByMethod->sum('total');
        $saleCount = (int) $paymentsByMethod->sum('count');
        $nonCashSales = max(0, $totalSales - $cashSales);

        $cashIn = (float) $this->transactions()->where('type', 'cash_in')->sum('amount');
        $cashOut = (float) $this->transactions()->where('type', 'cash_out')->sum('amount');

        $expectedCash = round((float) $this->opening_balance + $cashSales + $cashIn - $cashOut, 2);
        $countedCash = $this->counted_closing_balance !== null ? (float) $this->counted_closing_balance : $expectedCash;
        $variance = $this->cash_difference !== null ? (float) $this->cash_difference : round($countedCash - $expectedCash, 2);

        return [
            'register_id' => $this->id,
            'status' => $this->status,
            'is_stale' => $this->isStaleMidnight(),
            'terminal_id' => $this->terminal_id ?? 'Main POS',
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'opened_by_name' => $this->opener?->name ?? 'Cashier',
            'closed_by_name' => $this->closer?->name ?? '—',
            'opening_balance' => (float) $this->opening_balance,
            'opening_denominations' => $this->opening_denominations ?? [],
            'closing_denominations' => $this->closing_denominations ?? [],
            'payments_by_method' => $paymentsByMethod->map(fn ($row) => ['total' => (float) $row->total, 'count' => (int) $row->count])->all(),
            'total_sales' => $totalSales,
            'cash_sales' => $cashSales,
            'card_sales' => $cardSales,
            'upi_sales' => $upiSales,
            'credit_sales' => $creditSales,
            'non_cash_sales' => $nonCashSales,
            'sale_count' => $saleCount,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $expectedCash,
            'counted_cash' => $countedCash,
            'variance' => $variance,
            'notes' => $this->notes,
            'opening_notes' => $this->opening_notes,
        ];
    }
}
