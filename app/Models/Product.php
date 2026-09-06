<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksSyncState;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToCompany;
    use TracksSyncState;

    protected $fillable = [
        'company_id', 'external_id', 'code', 'sku', 'barcode', 'name', 'image_url', 'category_id', 'category_name',
        'brand_id', 'brand_name', 'unit', 'cost_price', 'sale_price', 'variants', 'modifiers', 'spice_levels', 'profit_margin',
        'current_stock', 'minimum_stock', 'active', 'is_demo', 'batch_number', 'mfg_date', 'expiry_date',
        'requires_prescription', 'narcotic_schedule', 'generic_name', 'composition', 'duration_minutes', 'follow_up_days', 'hsn_code', 'sac_code', 'tax_rate',
        'taxable', 'tax_exempt', 'zero_rate', 'reverse_charge',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_demo' => 'boolean',
            'requires_prescription' => 'boolean',
            'duration_minutes' => 'integer',
            'follow_up_days' => 'integer',
            'mfg_date' => 'date',
            'expiry_date' => 'date',
            'taxable' => 'boolean',
            'tax_exempt' => 'boolean',
            'zero_rate' => 'boolean',
            'reverse_charge' => 'boolean',
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'current_stock' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'variants' => 'array',
            'modifiers' => 'array',
            'spice_levels' => 'array',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function pharmacyBatches()
    {
        return $this->hasMany(PharmacyBatch::class);
    }

    public function activePharmacyBatches()
    {
        return $this->hasMany(PharmacyBatch::class)
            ->where('is_active', true)
            ->where('stock_qty', '>', 0)
            ->orderBy('expiry_date', 'asc');
    }

    public function repairParts()
    {
        return $this->hasMany(RepairTicketPart::class);
    }

    public function isService(): bool
    {
        if ($this->unit === 'service') {
            return true;
        }

        if ($this->duration_minutes !== null && (int) $this->duration_minutes > 0) {
            return true;
        }

        if ($this->category && in_array($this->category->type, ['salon', 'service'], true)) {
            return true;
        }

        $catName = strtolower($this->category_name ?? $this->category?->name ?? '');
        if (str_contains($catName, 'hair') || str_contains($catName, 'styling') || str_contains($catName, 'facial') || str_contains($catName, 'spa')) {
            return true;
        }

        return false;
    }

    public function scopeSpareParts($query)
    {
        return $query->where(function ($q) {
            $q->where('unit', '!=', 'service')
                ->orWhereNull('unit');
        })
        ->where(function ($q) {
            $q->whereNull('duration_minutes')
                ->orWhere('duration_minutes', '<=', 0);
        })
        ->whereDoesntHave('category', function ($cq) {
            $cq->whereIn('type', ['salon', 'service'])
                ->orWhereIn('name', ['Hair & Styling', 'Facials & Skincare', 'Spa & Body Treatments']);
        });
    }

    public function getImageUrlOrDefault(): string
    {
        if (! empty($this->image_url)) {
            return $this->image_url;
        }

        $name = strtolower($this->name);
        $cat = strtolower($this->category_name ?? $this->category?->name ?? '');

        $map = [
            'burger' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=500&auto=format&fit=crop&q=80',
            'cheeseburger' => 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=500&auto=format&fit=crop&q=80',
            'pizza' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=500&auto=format&fit=crop&q=80',
            'taco' => 'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?w=500&auto=format&fit=crop&q=80',
            'pasta' => 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?w=500&auto=format&fit=crop&q=80',
            'linguine' => 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?w=500&auto=format&fit=crop&q=80',
            'salad' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=500&auto=format&fit=crop&q=80',
            'cake' => 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=500&auto=format&fit=crop&q=80',
            'doughnut' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=500&auto=format&fit=crop&q=80',
            'donut' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=500&auto=format&fit=crop&q=80',
            'bread' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=500&auto=format&fit=crop&q=80',
            'apple' => 'https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?w=500&auto=format&fit=crop&q=80',
            'carrot' => 'https://images.unsplash.com/photo-1598170845058-32b9d6a5da37?w=500&auto=format&fit=crop&q=80',
            'milk' => 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=500&auto=format&fit=crop&q=80',
            'lemon' => 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?w=500&auto=format&fit=crop&q=80',
            'melon' => 'https://images.unsplash.com/photo-1571575179705-4d7226e27682?w=500&auto=format&fit=crop&q=80',
            'pisang' => 'https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?w=500&auto=format&fit=crop&q=80',
            'banana' => 'https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?w=500&auto=format&fit=crop&q=80',
            'semangka' => 'https://images.unsplash.com/photo-1587049352846-4a222e784d38?w=500&auto=format&fit=crop&q=80',
            'watermelon' => 'https://images.unsplash.com/photo-1587049352846-4a222e784d38?w=500&auto=format&fit=crop&q=80',
            'strawberry' => 'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=500&auto=format&fit=crop&q=80',
            'terong' => 'https://images.unsplash.com/photo-1528825871115-3581a5387919?w=500&auto=format&fit=crop&q=80',
            'eggplant' => 'https://images.unsplash.com/photo-1528825871115-3581a5387919?w=500&auto=format&fit=crop&q=80',
            'tomato' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=500&auto=format&fit=crop&q=80',
            'jeruk' => 'https://images.unsplash.com/photo-1582979512210-99b6a53386f9?w=500&auto=format&fit=crop&q=80',
            'orange' => 'https://images.unsplash.com/photo-1582979512210-99b6a53386f9?w=500&auto=format&fit=crop&q=80',
            'coffee' => 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=500&auto=format&fit=crop&q=80',
            'tea' => 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=500&auto=format&fit=crop&q=80',
            'juice' => 'https://images.unsplash.com/photo-1613478223719-2ab802602423?w=500&auto=format&fit=crop&q=80',
            'beverage' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=500&auto=format&fit=crop&q=80',
            'drink' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?w=500&auto=format&fit=crop&q=80',
            'dessert' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?w=500&auto=format&fit=crop&q=80',
            'fruit' => 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?w=500&auto=format&fit=crop&q=80',
        ];

        foreach ($map as $key => $url) {
            if (str_contains($name, $key) || str_contains($cat, $key)) {
                return $url;
            }
        }

        return 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=500&auto=format&fit=crop&q=80';
    }

    public function decrementStock(float $quantity, string $reason = ''): bool
    {
        $this->decrement('current_stock', $quantity);

        return true;
    }

    public function incrementStock(float $quantity, string $reason = ''): bool
    {
        $this->increment('current_stock', $quantity);

        return true;
    }

    public function getSkuAttribute(): ?string
    {
        return $this->attributes['code'] ?? null;
    }

    public function setSkuAttribute(?string $value): void
    {
        $this->attributes['code'] = $value;
    }
}
