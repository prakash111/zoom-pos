<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    use BelongsToCompany, HasLegacyStringId;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_BILLED = 'billed';

    protected $fillable = [
        'company_id', 'dining_floor_id', 'table_number', 'seating_capacity',
        'status', 'current_sale_id', 'guest_count', 'qr_token', 'is_active', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'seating_capacity' => 'integer',
            'guest_count' => 'integer',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DiningTable $table) {
            if (empty($table->qr_token)) {
                $table->qr_token = Str::lower(Str::random(24));
            }
        });
    }

    public function idPrefix(): string
    {
        return 'tbl_';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function floor()
    {
        return $this->belongsTo(DiningFloor::class, 'dining_floor_id');
    }

    public function currentSale()
    {
        return $this->belongsTo(Sale::class, 'current_sale_id');
    }

    public function getQrOrderUrl(): string
    {
        return route('restaurant.table.order', ['token' => $this->qr_token]);
    }
}
