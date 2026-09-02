<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;

class KitchenTicket extends Model
{
    use BelongsToCompany, HasLegacyStringId;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_READY = 'ready';

    public const STATUS_SERVED = 'served';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id', 'sale_id', 'kot_number', 'dining_table_id',
        'table_name', 'service_type', 'status', 'server_name',
        'items', 'kitchen_notes', 'prepared_at', 'ready_at', 'served_at',
        'prep_minutes', 'target_completion_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'prepared_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
            'prep_minutes' => 'integer',
            'target_completion_at' => 'datetime',
        ];
    }

    /**
     * Whether this ticket has breached its estimated prep time — drives the
     * KDS/dashboard's recurring "delayed order" chime.
     */
    public function isOverdue(): bool
    {
        return $this->target_completion_at !== null
            && $this->target_completion_at->isPast()
            && ! in_array($this->status, [self::STATUS_SERVED, self::STATUS_CANCELLED], true);
    }

    public function idPrefix(): string
    {
        return 'kot_';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function table()
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function getElapsedMinutes(): int
    {
        return (int) round($this->created_at->diffInMinutes(now()));
    }
}
