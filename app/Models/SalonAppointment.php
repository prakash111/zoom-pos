<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalonAppointment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'tenant_id',
        'appointment_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'product_id',
        'specialist_id',
        'starts_at',
        'ends_at',
        'status',
        'notes',
        'sale_id',
        'advance_paid',
        'deposit_payment_method',
        'chair_label',
        'service_items',
        'custom_fields',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'advance_paid' => 'decimal:2',
            'is_demo' => 'boolean',
            'service_items' => 'array',
            'custom_fields' => 'array',
        ];
    }

    public function getAdvanceDepositAttribute(): float
    {
        return (float) ($this->attributes['advance_paid'] ?? 0);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'specialist_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
