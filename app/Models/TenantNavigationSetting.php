<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantNavigationSetting extends Model
{
    protected $table = 'tenant_navigation_settings';

    protected $fillable = [
        'tenant_id',
        'module_key',
        'group',
        'label',
        'icon',
        'route',
        'is_enabled',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }
}
