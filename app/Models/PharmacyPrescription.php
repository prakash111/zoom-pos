<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyPrescription extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'tenant_id',
        'prescription_number',
        'customer_id',
        'patient_name',
        'patient_phone',
        'doctor_name',
        'doctor_registration_no',
        'prescription_date',
        'diagnosis',
        'medicines',
        'notes',
        'status',
        'dispensed_at',
        'dispensed_by_user_id',
        'sale_id',
        'is_demo',
        'rx_image_url',
        'dosage_duration_days',
        'refill_reminder_at',
        'refill_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'prescription_date' => 'date',
            'medicines' => 'array',
            'dispensed_at' => 'datetime',
            'is_demo' => 'boolean',
            'dosage_duration_days' => 'integer',
            'refill_reminder_at' => 'datetime',
            'refill_reminder_sent_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function dispensedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by_user_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
