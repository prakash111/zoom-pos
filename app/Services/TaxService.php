<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;

class TaxService
{
    /**
     * Central static calculate method wrapping TaxCalculationService.
     *
     * @param  array<int, array>  $items
     * @return array{subtotal: float, discount: float, tax_amount: float, total: float, tax_summary_table: array, items: array}
     */
    public static function calculate(
        array $items,
        ?Company $company = null,
        ?Customer $customer = null,
        float $discount = 0.0,
        string $discountType = 'fixed'
    ): array {
        /** @var TaxCalculationService $service */
        $service = app(TaxCalculationService::class);

        return $service->calculateCartTotals($items, $company, $customer, $discount, $discountType);
    }

    /**
     * Compute tax breakdown for an individual line item.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function calculateLine(
        array $item,
        ?Company $company = null,
        ?Customer $customer = null
    ): array {
        /** @var TaxCalculationService $service */
        $service = app(TaxCalculationService::class);

        return $service->calculateLineItemTax($item, $company, $customer);
    }
}
