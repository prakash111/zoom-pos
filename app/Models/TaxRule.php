<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;

class TaxRule extends Model
{
    use BelongsToCompany, HasLegacyStringId;

    protected $fillable = [
        'company_id', 'tax_name', 'tax_code', 'rate', 'type', 'country', 'region',
        'category', 'calc_type', 'is_inclusive', 'is_compound', 'is_default',
        'sub_components', 'priority', 'description', 'effective_from', 'active', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:3',
            'is_inclusive' => 'boolean',
            'is_compound' => 'boolean',
            'is_default' => 'boolean',
            'is_demo' => 'boolean',
            'sub_components' => 'array',
            'priority' => 'integer',
            'effective_from' => 'date',
            'active' => 'boolean',
        ];
    }

    public function idPrefix(): string
    {
        return 'tax_';
    }

    protected static function booted(): void
    {
        static::creating(function (TaxRule $rule) {
            if (empty($rule->country)) {
                $rule->country = 'US';
            }
            if (empty($rule->calc_type)) {
                $rule->calc_type = $rule->is_inclusive ? 'inclusive' : 'exclusive';
            }
            if (empty($rule->type)) {
                $rule->type = 'percentage';
            }
        });
    }

    /**
     * Get the sub-components breakdown or single default rate component.
     *
     * @return array<int, array{name: string, rate: float, code?: string}>
     */
    public function getComponents(): array
    {
        if (! empty($this->sub_components) && is_array($this->sub_components)) {
            return $this->sub_components;
        }

        return [
            [
                'name' => $this->tax_name,
                'rate' => (float) $this->rate,
                'code' => $this->tax_code ?: $this->tax_name,
            ],
        ];
    }
}
