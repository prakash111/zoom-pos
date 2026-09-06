<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationReminder extends Model
{
    use BelongsToCompany;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id', 'module_type', 'event_type', 'reference_type',
        'reference_id', 'customer_id', 'customer_name', 'recipient',
        'channels', 'message', 'payload', 'scheduled_at', 'sent_at',
        'status', 'attempts', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'payload' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
