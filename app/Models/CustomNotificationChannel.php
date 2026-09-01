<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CustomNotificationChannel extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'url', 'method', 'headers',
        'auth_type', 'auth_value', 'payload_template', 'event_types', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'event_types' => 'array',
            'is_active' => 'boolean',
            'auth_value' => 'encrypted',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function handlesEvent(string $eventType): bool
    {
        return $this->is_active && in_array($eventType, (array) ($this->event_types ?? []), true);
    }
}
