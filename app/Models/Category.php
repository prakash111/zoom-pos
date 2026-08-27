<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksSyncState;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToCompany;
    use TracksSyncState;

    protected $fillable = ['company_id', 'external_id', 'name', 'color', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
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
