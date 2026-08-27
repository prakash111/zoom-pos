<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksSyncState;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use BelongsToCompany;
    use TracksSyncState;

    protected $fillable = [
        'company_id', 'external_id', 'name', 'legal_name', 'trade_name', 'tax_id',
        'email', 'phone', 'city', 'state', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
