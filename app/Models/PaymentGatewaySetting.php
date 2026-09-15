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
            'secret_key' => \App\Casts\SafeEncryptedString::class,
            'webhook_secret' => \App\Casts\SafeEncryptedString::class,
            'extra' => 'array',
        ];
    }
}
