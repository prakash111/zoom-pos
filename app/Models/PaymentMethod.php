<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PaymentMethod extends Model
{
    use BelongsToCompany, HasLegacyStringId;

    protected $fillable = [
        'company_id', 'name', 'code', 'is_active', 'order_index', 'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order_index' => 'integer',
        ];
    }

    public function idPrefix(): string
    {
        return 'pm_';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get or initialize active payment methods for a company.
     *
     * @return Collection<int, PaymentMethod>
     */
    public static function getForCompany(?string $companyId): Collection
    {
        if (empty($companyId)) {
            return collect();
        }

        $existing = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('order_index')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing->where('is_active', true)->values();
        }

        // Initialize default 3 payment methods for company
        $defaults = [
            ['name' => 'Cash', 'code' => 'cash', 'description' => 'Physical cash payment', 'order_index' => 1],
            ['name' => 'Card', 'code' => 'card', 'description' => 'Credit or Debit Card', 'order_index' => 2],
            ['name' => 'Transfer', 'code' => 'transfer', 'description' => 'Bank Transfer / Wire', 'order_index' => 3],
        ];

        $created = collect();
        foreach ($defaults as $d) {
            $created->push(static::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'name' => $d['name'],
                'code' => $d['code'],
                'description' => $d['description'],
                'is_active' => true,
                'order_index' => $d['order_index'],
            ]));
        }

        return $created;
    }
}
