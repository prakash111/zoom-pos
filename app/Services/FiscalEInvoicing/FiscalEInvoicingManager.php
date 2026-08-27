<?php

namespace App\Services\FiscalEInvoicing;

use App\Models\Company;
use App\Models\Sale;

class FiscalEInvoicingManager
{
    /**
     * Resolve the appropriate e-invoicing compliance driver for a company/sale.
     */
    public function driverForCompany(?Company $company = null): EInvoicingDriverInterface
    {
        $country = strtoupper($company?->country ?: 'US');

        return match ($country) {
            'SA' => new ZatcaEInvoiceDriver,
            'IN' => new IndiaGstEInvoiceDriver,
            'GB', 'DE', 'FR', 'IT', 'ES', 'NL', 'EU' => new PeppolEInvoiceDriver,
            default => new ZatcaEInvoiceDriver, // Default robust universal TLV driver
        };
    }

    public function driverForSale(Sale $sale): EInvoicingDriverInterface
    {
        return $this->driverForCompany($sale->company);
    }
}
