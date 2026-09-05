<?php

namespace App\Models;

/**
 * Legacy compatibility alias for RepairTicketItem.
 */
class RepairTicketPart extends RepairTicketItem
{
    protected static function booted(): void
    {
        static::addGlobalScope('spare_part', function ($builder) {
            $builder->where('item_type', 'spare_part');
        });

        static::creating(function ($model) {
            $model->item_type = 'spare_part';
            if (empty($model->item_name) && ! empty($model->part_name)) {
                $model->item_name = $model->part_name;
            }
        });
    }

    public function getPartNameAttribute(): ?string
    {
        return $this->item_name;
    }

    public function setPartNameAttribute(?string $value): void
    {
        $this->item_name = $value;
    }

    public function getRepairTicketIdAttribute()
    {
        return $this->ticket_id;
    }

    public function setRepairTicketIdAttribute($value): void
    {
        $this->ticket_id = $value;
    }
}
