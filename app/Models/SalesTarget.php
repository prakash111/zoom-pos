<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTarget extends Model
{
    use BelongsToCompany;
    use SyncableModel;

    protected $fillable = [
        'company_id',
        'external_id',
        'user_id',
        'year',
        'month',
        'target_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'target_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Compute target achievement for a specific company / user and month.
     *
     * @return array{target: float, achieved: float, percentage: float, remaining: float}
     */
    public static function getProgress(string $companyId, ?string $userId = null, ?int $year = null, ?int $month = null): array
    {
        $year = $year ?? (int) now()->year;
        $month = $month ?? (int) now()->month;

        $targetRecord = static::where('company_id', $companyId)
            ->when($userId, fn ($q) => $q->where('user_id', $userId), fn ($q) => $q->whereNull('user_id'))
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $targetAmount = (float) ($targetRecord?->target_amount ?? 0);

        $startDate = now()->setYear($year)->setMonth($month)->startOfMonth();
        $endDate = now()->setYear($year)->setMonth($month)->endOfMonth();

        $achieved = (float) Sale::where('company_id', $companyId)
            ->where('operation_type', 'sale')
            ->where('status', 'completed')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total');

        $percentage = $targetAmount > 0 ? round(($achieved / $targetAmount) * 100, 1) : 0.0;
        $remaining = max(0, $targetAmount - $achieved);

        $salesCount = (int) Sale::where('company_id', $companyId)
            ->where('operation_type', 'sale')
            ->where('status', 'completed')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        return [
            'target' => $targetAmount,
            'achieved' => $achieved,
            'percentage' => $percentage,
            'remaining' => $remaining,
            'sales_count' => $salesCount,
        ];
    }
}
