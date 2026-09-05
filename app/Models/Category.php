<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksSyncState;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToCompany;
    use TracksSyncState;

    protected $fillable = [
        'company_id',
        'external_id',
        'name',
        'type',
        'color',
        'description',
        'metadata',
        'active',
        'is_demo',
    ];

    protected $appends = [
        'slug',
        'brands',
        'checklist_items',
        'brands_list',
        'checklist_points',
        'identifier_type',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_demo' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function getSlugAttribute(): string
    {
        return \Illuminate\Support\Str::slug($this->name ?? '');
    }

    public function getBrandsAttribute(): array
    {
        return $this->brands_list;
    }

    public function getChecklistItemsAttribute(): array
    {
        return $this->checklist_points;
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForDevices($query)
    {
        return $query->where(function ($q) {
            $q->where('type', 'device')
                ->orWhereNull('type')
                ->orWhere('type', 'retail');
        });
    }

    public function getBrandsListAttribute(): array
    {
        $brands = $this->metadata['brands'] ?? null;
        if (is_string($brands)) {
            return array_values(array_filter(array_map('trim', explode(',', $brands))));
        }

        if (is_array($brands) && ! empty($brands)) {
            return array_values($brands);
        }

        return ['Apple', 'Samsung', 'Generic', 'Other'];
    }

    public function getChecklistPointsAttribute(): array
    {
        $items = $this->metadata['checklist_points'] ?? $this->metadata['checklist_items'] ?? null;
        if (is_string($items)) {
            return array_values(array_filter(array_map('trim', explode(',', $items))));
        }

        if (is_array($items) && ! empty($items)) {
            return array_values($items);
        }

        return ['Power On / Boot', 'Physical Housing Condition', 'Touch Screen / Display', 'Battery / Charging'];
    }

    public function getIdentifierTypeAttribute(): string
    {
        return (string) ($this->metadata['identifier_type'] ?? 'Serial / IMEI');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function getIconAttribute(): ?string
    {
        $name = strtolower($this->name ?? '');
        if (str_contains($name, 'bev') || str_contains($name, 'drink') || str_contains($name, 'coffee') || str_contains($name, 'tea')) {
            return '☕';
        }
        if (str_contains($name, 'burg') || str_contains($name, 'sandwich')) {
            return '🍔';
        }
        if (str_contains($name, 'pizz')) {
            return '🍕';
        }
        if (str_contains($name, 'dessert') || str_contains($name, 'sweet') || str_contains($name, 'cake') || str_contains($name, 'ice') || str_contains($name, 'pastr')) {
            return '🍰';
        }
        if (str_contains($name, 'fruit') || str_contains($name, 'veg') || str_contains($name, 'salad')) {
            return '🍎';
        }
        if (str_contains($name, 'meat') || str_contains($name, 'bbq') || str_contains($name, 'grill') || str_contains($name, 'steak')) {
            return '🥩';
        }
        if (str_contains($name, 'bread') || str_contains($name, 'bake')) {
            return '🍞';
        }
        if (str_contains($name, 'snack') || str_contains($name, 'chip') || str_contains($name, 'fast')) {
            return '🍟';
        }
        if (str_contains($name, 'cloth') || str_contains($name, 'apparel') || str_contains($name, 'wear')) {
            return '👕';
        }
        if (str_contains($name, 'elect') || str_contains($name, 'tech') || str_contains($name, 'phone')) {
            return '📱';
        }

        return '🏷️';
    }
}
