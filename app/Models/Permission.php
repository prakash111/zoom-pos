<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use BelongsToCompany, HasLegacyStringId;

    protected $fillable = ['user_id', 'company_id', 'module', 'action', 'allowed'];

    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }

    public function idPrefix(): string
    {
        return 'perm_';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
