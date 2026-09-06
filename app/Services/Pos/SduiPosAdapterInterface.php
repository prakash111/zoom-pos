<?php

namespace App\Services\Pos;

use App\Models\Customer;

/**
 * Boundary between a domain work queue and the core POS drawer.
 *
 * Adapters only describe a cart. Pricing, tax, payment, CRM, and settlement
 * remain owned by the universal POS and central sales engine.
 */
interface SduiPosAdapterInterface
{
    /** @return list<array<string, mixed>> */
    public function getLineItems(): array;

    public function getCustomer(): ?Customer;

    public function getPrepaidDeposit(): float;

    /** @return array<string, mixed> */
    public function getModuleContext(): array;
}
