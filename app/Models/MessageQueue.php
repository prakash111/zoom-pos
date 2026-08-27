<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageQueue extends Model
{
    use BelongsToCompany;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const MAX_ATTEMPTS = 8;

    protected $table = 'message_queue';

    protected $fillable = [
        'company_id', 'sale_id', 'type', 'recipient', 'payload',
        'status', 'attempts', 'last_error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function isRetryable(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_FAILED], true)
            && $this->attempts < self::MAX_ATTEMPTS;
    }
}
