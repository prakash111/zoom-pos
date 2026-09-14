<?php

namespace Modules\leadmanagement\Models;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Reminder;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $table = 'lead_mod_leads';

    protected $guarded = [];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'expected_value' => 'decimal:2',
        'converted_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class, 'lead_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'lead_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'lead_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'lead_id');
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    /**
     * Pipeline stages supported by the CRM.
     */
    public const STAGES = [
        'new' => 'New Lead',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified Opportunity',
        'proposal_sent' => 'Proposal / Quote Sent',
        'won' => 'Closed / Won',
        'lost' => 'Closed / Lost',
    ];

    /**
     * Ensure estimated_value and expected_value stay synchronized.
     */
    public function setExpectedValueAttribute($value): void
    {
        $this->attributes['expected_value'] = $value;
        $this->attributes['estimated_value'] = $value;
    }

    public function setEstimatedValueAttribute($value): void
    {
        $this->attributes['estimated_value'] = $value;
        if (! isset($this->attributes['expected_value']) || (float) $this->attributes['expected_value'] === 0.0) {
            $this->attributes['expected_value'] = $value;
        }
    }
}
