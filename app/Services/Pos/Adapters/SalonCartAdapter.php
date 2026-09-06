<?php

namespace App\Services\Pos\Adapters;

use App\Models\Company;
use App\Models\Customer;
use App\Models\SalonAppointment;
use App\Services\Pos\SduiPosAdapterInterface;

final class SalonCartAdapter implements SduiPosAdapterInterface
{
    /** @param list<array<string, mixed>> $lineItems */
    public function __construct(
        private readonly Company $company,
        private readonly array $lineItems = [],
        private readonly ?SalonAppointment $appointment = null,
        private readonly array $context = [],
    ) {}

    public function getLineItems(): array
    {
        if ($this->lineItems !== [] || ! $this->appointment?->service) {
            return $this->lineItems;
        }

        return [[
            'id' => $this->appointment->product_id,
            'product_id' => $this->appointment->product_id,
            'title' => $this->appointment->service->name,
            'name' => $this->appointment->service->name,
            'subtitle' => 'Stylist: '.($this->appointment->specialist?->name ?? 'Unassigned'),
            'price' => (float) $this->appointment->service->sale_price,
            'unit_price' => (float) $this->appointment->service->sale_price,
            'quantity' => 1,
            'qty' => 1,
            'service_type' => 'service',
            'specialist_id' => $this->appointment->specialist_id,
        ]];
    }

    public function getCustomer(): ?Customer
    {
        return $this->appointment?->customer;
    }

    public function getPrepaidDeposit(): float
    {
        return max(0, (float) ($this->appointment?->advance_paid ?? 0));
    }

    public function getModuleContext(): array
    {
        return array_merge([
            'company' => $this->company,
            'module' => 'salon',
            'form_submit_endpoint' => '/api/tenant/salon/pos-checkout'.($this->appointment ? '?appointment_id='.$this->appointment->id : ''),
            'customer_field_label' => 'Client Name',
            'default_customer_name' => $this->appointment?->customer_name,
            'default_specialist_id' => $this->appointment?->specialist_id,
        ], $this->context);
    }
}
