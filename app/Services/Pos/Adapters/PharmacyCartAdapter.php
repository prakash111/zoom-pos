<?php

namespace App\Services\Pos\Adapters;

use App\Models\Company;
use App\Models\Customer;
use App\Models\PharmacyPrescription;
use App\Services\Pos\SduiPosAdapterInterface;

final class PharmacyCartAdapter implements SduiPosAdapterInterface
{
    /** @param list<array<string, mixed>> $lineItems */
    public function __construct(
        private readonly Company $company,
        private readonly array $lineItems = [],
        private readonly ?PharmacyPrescription $prescription = null,
        private readonly array $context = [],
    ) {}

    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    public function getCustomer(): ?Customer
    {
        return $this->prescription?->customer;
    }

    public function getPrepaidDeposit(): float
    {
        return 0.0;
    }

    public function getModuleContext(): array
    {
        return array_merge([
            'company' => $this->company,
            'module' => 'pharmacy',
            'form_submit_endpoint' => $this->prescription
                ? "/api/tenant/pharmacy/prescriptions/{$this->prescription->id}/checkout"
                : '/api/tenant/pharmacy/checkout',
            'customer_field_label' => 'Customer / Patient Name',
            'collect_prescription' => true,
            'default_customer_name' => $this->prescription?->patient_name,
        ], $this->context);
    }
}
