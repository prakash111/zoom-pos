<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomatedReminderDispatch extends Model
{
    use BelongsToCompany;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'company_id',
        'sale_id',
        'document_type',
        'channel',
        'recipient',
        'cycle_key',
        'dispatch_key',
        'scheduled_for',
        'status',
        'attempts',
        'last_error',
        'dispatched_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'dispatched_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
