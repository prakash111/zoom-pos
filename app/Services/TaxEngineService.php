<?php

namespace App\Services;

class TaxEngineService
{
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
