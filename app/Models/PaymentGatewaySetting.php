<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewaySetting extends Model
{
    protected $fillable = ['gateway', 'enabled', 'mode', 'public_key', 'secret_key', 'webhook_secret', 'extra'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'secret_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'extra' => 'array',
        ];
    }
}
