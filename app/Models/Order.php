<?php

namespace App\Models;

/**
 * Compatibility model mapping Order references to POS Sale model.
 */
class Order extends Sale
{
    public function getNetTotalAttribute(): float
    {
        return (float) ($this->net_amount ?? $this->total ?? 0);
    }

    public function getOrderNumberAttribute(): string
    {
        return (string) ($this->sale_number ?? $this->id);
    }
}
