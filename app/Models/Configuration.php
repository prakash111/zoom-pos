<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'key', 'value'];
}
