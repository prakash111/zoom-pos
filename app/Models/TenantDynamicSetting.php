<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class TenantDynamicSetting extends Model
{
    use BelongsToCompany;

    protected $table = 'dynamic_settings';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'group',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }
}
