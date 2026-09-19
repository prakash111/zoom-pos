<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reminder extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'company_id',
        'tenant_id',
        'user_id',
        'customer_id',
        'type',
        'title',
        'subject',
        'notes',
        'description',
        'call_script',
        'due_date',
        'due_at',
        'status',
        'remindable_type',
        'remindable_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function setNotesAttribute($value): void
    {
        $this->attributes['notes'] = $value;
        $this->attributes['description'] = $value;
        $this->attributes['call_script'] = $value;
    }

    public function setDescriptionAttribute($value): void
    {
        $this->attributes['description'] = $value;
        $this->attributes['notes'] = $value;
        $this->attributes['call_script'] = $value;
    }

    public function setCallScriptAttribute($value): void
    {
        $this->attributes['call_script'] = $value;
        $this->attributes['notes'] = $value;
        $this->attributes['description'] = $value;
    }

    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = $value;
        if (! isset($this->attributes['subject']) || empty($this->attributes['subject'])) {
            $this->attributes['subject'] = $value;
        }
    }

    public function setSubjectAttribute($value): void
    {
        $this->attributes['subject'] = $value;
        if (! isset($this->attributes['title']) || empty($this->attributes['title'])) {
            $this->attributes['title'] = $value;
        }
    }

    public function setDueDateAttribute($value): void
    {
        $this->attributes['due_date'] = $value;
        if (! isset($this->attributes['due_at']) || empty($this->attributes['due_at'])) {
            $this->attributes['due_at'] = $value;
        }
    }

    public function setDueAtAttribute($value): void
    {
        $this->attributes['due_at'] = $value;
        if (! isset($this->attributes['due_date']) || empty($this->attributes['due_date'])) {
            $this->attributes['due_date'] = $value;
        }
    }

    public function setTenantIdAttribute($value): void
    {
        $this->attributes['tenant_id'] = $value;
        if (! isset($this->attributes['company_id']) || empty($this->attributes['company_id'])) {
            $this->attributes['company_id'] = $value;
        }
    }

    public function setCompanyIdAttribute($value): void
    {
        $this->attributes['company_id'] = $value;
        if (! isset($this->attributes['tenant_id']) || empty($this->attributes['tenant_id'])) {
            $this->attributes['tenant_id'] = $value;
        }
    }

    public function getNotesAttribute($value): ?string
    {
        return $value ?? $this->attributes['description'] ?? $this->attributes['call_script'] ?? null;
    }

    public function getDescriptionAttribute($value): ?string
    {
        return $value ?? $this->attributes['notes'] ?? $this->attributes['call_script'] ?? null;
    }

    public function getCallScriptAttribute($value): ?string
    {
        return $value ?? $this->attributes['notes'] ?? $this->attributes['description'] ?? null;
    }

    public function getTitleAttribute($value): ?string
    {
        return $value ?? $this->attributes['subject'] ?? null;
    }

    public function getSubjectAttribute($value): ?string
    {
        return $value ?? $this->attributes['title'] ?? null;
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && ! empty($model->company_id)) {
                $model->tenant_id = $model->company_id;
            }
            if (empty($model->company_id) && ! empty($model->tenant_id)) {
                $model->company_id = $model->tenant_id;
            }
        });
    }

    public function newEloquentBuilder($query)
    {
        return new class($query) extends \Illuminate\Database\Eloquent\Builder {
            public function where($column, $operator = null, $value = null, $boolean = 'and')
            {
                if ($column === 'tenant_id' || $column === 'reminders.tenant_id') {
                    $actualOperator = $value === null ? '=' : $operator;
                    $actualValue = $value === null ? $operator : $value;

                    return parent::where(function ($q) use ($actualOperator, $actualValue) {
                        $q->toBase()->where(function ($base) use ($actualOperator, $actualValue) {
                            $base->where('reminders.tenant_id', $actualOperator, $actualValue)
                                 ->orWhere('reminders.company_id', $actualOperator, $actualValue);
                        });
                    }, null, null, $boolean);
                }

                return parent::where($column, $operator, $value, $boolean);
            }
        };
    }
}
