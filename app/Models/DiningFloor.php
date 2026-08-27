<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;

class DiningFloor extends Model
{
    use BelongsToCompany, HasLegacyStringId;

    protected $fillable = [
        'company_id', 'name', 'order_index', 'is_active',
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
        return 'flr_';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function tables()
    {
        return $this->hasMany(DiningTable::class, 'dining_floor_id')->orderBy('table_number');
    }
}
