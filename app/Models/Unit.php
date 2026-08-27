<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksSyncState;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use BelongsToCompany;
    use TracksSyncState;

    protected $fillable = ['company_id', 'external_id', 'name', 'abbreviation'];
}
