<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLegacyStringId;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use BelongsToCompany, HasLegacyStringId;

    protected $fillable = [
        'company_id', 'plan_name', 'gateway', 'gateway_ref', 'gateway_secondary_ref',
        'amount', 'currency', 'status', 'checkout_url', 'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'raw_payload' => 'array',
        ];
    }

    public function idPrefix(): string
    {
        return 'ptx_';
    }
}
