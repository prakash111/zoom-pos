<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksSyncState;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use BelongsToCompany;
    use TracksSyncState;

    protected $fillable = ['company_id', 'external_id', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
