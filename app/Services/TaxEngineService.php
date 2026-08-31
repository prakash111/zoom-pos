<?php

namespace App\Services;

use App\Models\Company;

class TaxEngineService
{
    /**
     * Whether a company's fiscal documents should use India's GST
     * conventions (GSTIN identifier, CGST/SGST split) — mirrors the
     * identical check duplicated across documents/template.blade.php and
     * the mobile app's CompanyModel.isIndia.
     */
    public static function isIndia(?Company $company): bool
    {
        if (! $company) {
            return false;
        }

        $country = strtoupper(trim((string) $company->country));

        return in_array($country, ['IN', 'IND', 'INDIA'], true)
            || str_contains($country, 'INDIA')
            || strtoupper((string) $company->currency) === 'INR'
            || $company->currency_symbol === '₹';
    }

    /**
     * Build a tax_summary_table (the same shape TaxCalculationService::
     * calculateCartTotals produces, see Sale::tax_breakdown) by grouping
     * already-priced line items by the tax rate a client applied to each
     * one. Used when the client (mobile POS checkout / quotation builder)
     * computed tax per item from each product's own tax_rate rather than
     * resolving a TaxRule record server-side — grouping here is what turns
     * that flat per-item math back into a proper rate-labeled breakdown
     * (with a CGST/SGST split for India) for receipts/invoices to render.
     *
     * @param  array<int, array{taxable: float, rate: float, amount: float}>  $rateItems
     * @return array<int, array{tax_name: string, rate: float, is_inclusive: bool, taxable_amount: float, tax_amount: float, components: array}>
     */
    public static function buildTaxSummaryFromRates(array $rateItems, bool $isIndia, string $taxLabel = 'Tax'): array
    {
        $groups = [];
        foreach ($rateItems as $item) {
            $rate = round((float) ($item['rate'] ?? 0), 2);
            if ($rate <= 0) {
                continue;
            }
            $key = (string) $rate;
            if (! isset($groups[$key])) {
                $groups[$key] = ['rate' => $rate, 'taxable_amount' => 0.0, 'tax_amount' => 0.0];
            }
            $groups[$key]['taxable_amount'] += (float) ($item['taxable'] ?? 0);
            $groups[$key]['tax_amount'] += (float) ($item['amount'] ?? 0);
        }

        $name = $taxLabel !== '' ? $taxLabel : ($isIndia ? 'GST' : 'Tax');

        return array_values(array_map(static function (array $group) use ($isIndia, $name): array {
            $components = [];
            if ($isIndia) {
                $halfAmount = round($group['tax_amount'] / 2, 2);
                $halfRate = round($group['rate'] / 2, 2);
                $components = [
                    ['name' => 'CGST', 'rate' => $halfRate, 'amount' => $halfAmount],
                    ['name' => 'SGST', 'rate' => $halfRate, 'amount' => round($group['tax_amount'] - $halfAmount, 2)],
                ];
            }

            return [
                'tax_name' => $name,
                'rate' => $group['rate'],
                'is_inclusive' => false,
                'taxable_amount' => round($group['taxable_amount'], 2),
                'tax_amount' => round($group['tax_amount'], 2),
                'components' => $components,
            ];
        }, $groups));
    }

    /**
     * Resolve the sovereign fiscal identifier used on customer-facing documents.
     */
    public static function getTaxIdentifierLabel(?string $country = null, ?string $appliedRuleName = null): string
    {
        // The applied fiscal rule is the strongest signal when a stale company
        // profile still contains the provisioning default (commonly US).
        if (filled($appliedRuleName) && stripos($appliedRuleName, 'GST') !== false) {
            return 'GSTIN';
        }

        $countryValue = trim((string) $country);
        if ($countryValue === '') {
            $countryValue = trim((string) tenant_setting('store_country'));
        }
        if ($countryValue === '') {
            $countryValue = trim((string) tenant_setting('tax_country'));
        }
        if ($countryValue === '') {
            $countryValue = trim((string) tenant_setting('country'));
        }

        $normalized = strtoupper(preg_replace('/\s+/', ' ', $countryValue ?: 'IN'));

        return match (true) {
            in_array($normalized, ['IN', 'IND', 'INDIA'], true) || str_contains($normalized, 'INDIA') => 'GSTIN',
            in_array($normalized, ['BR', 'BRA', 'BRAZIL', 'BRASIL'], true) || str_contains($normalized, 'BRAZIL') || str_contains($normalized, 'BRASIL') => 'CNPJ / CPF',
            in_array($normalized, ['GB', 'GBR', 'UK', 'UNITED KINGDOM'], true) || str_contains($normalized, 'UNITED KINGDOM') => 'VAT Reg No',
            in_array($normalized, ['AE', 'ARE', 'UAE', 'SA', 'SAU', 'SAUDI ARABIA'], true) || str_contains($normalized, 'UNITED ARAB EMIRATES') || str_contains($normalized, 'SAUDI ARABIA') => 'TRN / VAT ID',
            in_array($normalized, ['CA', 'CAN', 'CANADA'], true) || str_contains($normalized, 'CANADA') => 'BN / GST No',
            in_array($normalized, ['AU', 'AUS', 'AUSTRALIA'], true) || str_contains($normalized, 'AUSTRALIA') => 'ABN',
            in_array($normalized, ['US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA'], true) || str_contains($normalized, 'UNITED STATES') => 'EIN / Tax ID',
            in_array($normalized, ['EU', 'EUROPEAN UNION'], true) || str_contains($normalized, 'EUROPEAN UNION') => 'VAT Reg No',
            default => 'GSTIN',
        };
    }

    /**
     * Normalize both persisted and legacy tax-breakdown shapes for documents.
     *
     * @return array<int, array{name: string, rate: float, taxable: float, amount: float, sub_components: array}>
     */
    public static function normalizeTaxBreakdown(mixed $breakdown): array
    {
        if (is_string($breakdown)) {
            $breakdown = json_decode($breakdown, true);
        }

        if (! is_array($breakdown)) {
            return [];
        }

        return array_values(array_map(static function (array $row): array {
            $components = $row['sub_components'] ?? $row['components'] ?? [];

            return [
                'name' => (string) ($row['rule_name'] ?? $row['tax_name'] ?? $row['name'] ?? 'Tax'),
                'rate' => (float) ($row['rate'] ?? 0),
                'taxable' => (float) ($row['taxable'] ?? $row['taxable_amount'] ?? 0),
                'amount' => (float) ($row['amount'] ?? $row['tax_amount'] ?? 0),
                'sub_components' => array_values(array_map(static fn (array $component): array => [
                    'name' => (string) ($component['name'] ?? 'Tax'),
                    'rate' => (float) ($component['rate'] ?? 0),
                    'amount' => (float) ($component['amount'] ?? 0),
                ], is_array($components) ? $components : [])),
            ];
        }, array_filter($breakdown, 'is_array')));
    }
}
