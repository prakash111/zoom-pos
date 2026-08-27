<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksSyncState;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToCompany;
    use TracksSyncState;

    protected $fillable = [
        'company_id', 'external_id', 'name', 'document', 'person_type', 'email', 'phone',
        'address', 'city', 'state', 'loyalty_points', 'state_code', 'gstin', 'taxpayer_type', 'tax_id_label',
        'tax_id', 'is_tax_exempt',
    ];

    protected $casts = [
        'is_tax_exempt' => 'boolean',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function getTotalDueAttribute(): float
    {
        return (float) $this->sales()
            ->where('status', '!=', 'cancelled')
            ->where('due_amount', '>', 0)
            ->sum('due_amount');
    }
}
