<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use BelongsToCompany, HasLegacyStringId;

    protected $fillable = ['company_id', 'plan_name', 'status', 'origin', 'started_at', 'expires_at', 'auto_renew'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function idPrefix(): string
    {
        return 'sub_';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_name', 'name');
    }
}
