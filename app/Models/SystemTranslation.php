<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemTranslation extends Model
{
    protected $fillable = [
        'locale',
        'module',
        'key',
        'value',
        'version',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
