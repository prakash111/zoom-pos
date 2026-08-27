<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\TaxRule;

class TaxCalculationService
{
    /**
     * Standard Global Country Tax Jurisdiction Presets & Compliance Schemes.
     */
    public const JURISDICTIONS = [
        'IN' => [
            'country' => 'India',
            'system' => 'GST (Goods and Services Tax)',
            'standard_rate' => 18.0,
            'rules' => [
                [
                    'name' => 'GST 18% (Intra-State)',
                    'code' => 'GST_18_INTRA',
                    'rate' => 18.0,
                    'is_inclusive' => false,
                    'is_default' => true,
                    'sub_components' => [
                        ['name' => 'CGST', 'rate' => 9.0, 'code' => 'CGST_9'],
                        ['name' => 'SGST', 'rate' => 9.0, 'code' => 'SGST_9'],
                    ],
                    'description' => 'Standard 18% GST split into 9% Central GST + 9% State GST for intra-state sales',
                ],
                [
                    'name' => 'IGST 18% (Inter-State)',
                    'code' => 'IGST_18',
                    'rate' => 18.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [
                        ['name' => 'IGST', 'rate' => 18.0, 'code' => 'IGST_18'],
                    ],
                    'description' => 'Integrated GST 18% for inter-state transactions',
                ],
                [
                    'name' => 'GST 5% (Essentials)',
                    'code' => 'GST_5',
                    'rate' => 5.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [
                        ['name' => 'CGST', 'rate' => 2.5, 'code' => 'CGST_2.5'],
                        ['name' => 'SGST', 'rate' => 2.5, 'code' => 'SGST_2.5'],
                    ],
                    'description' => '5% GST for packaged essential food items and services',
                ],
                [
                    'name' => 'GST 12% (Standard Low)',
                    'code' => 'GST_12',
                    'rate' => 12.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [
                        ['name' => 'CGST', 'rate' => 6.0, 'code' => 'CGST_6'],
                        ['name' => 'SGST', 'rate' => 6.0, 'code' => 'SGST_6'],
                    ],
                    'description' => '12% GST for processed foods, apparel, and hardware',
                ],
                [
                    'name' => 'GST 28% (Luxury / De-merit)',
                    'code' => 'GST_28',
                    'rate' => 28.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [
                        ['name' => 'CGST', 'rate' => 14.0, 'code' => 'CGST_14'],
                        ['name' => 'SGST', 'rate' => 14.0, 'code' => 'SGST_14'],
                    ],
                    'description' => '28% GST for luxury electronics, automobiles, and carbonated beverages',
                ],
                [
                    'name' => 'GST 0% (Zero-Rated / Agriculture)',
                    'code' => 'GST_0',
                    'rate' => 0.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [],
                    'description' => 'Zero-rated agricultural produce, unbranded grains, fresh milk',
                ],
            ],
        ],
        'US' => [
            'country' => 'United States',
            'system' => 'State & Local Sales Tax',
            'standard_rate' => 8.25,
            'rules' => [
                [
                    'name' => 'Combined State & Local Tax (8.25%)',
                    'code' => 'US_SALES_825',
                    'rate' => 8.25,
                    'is_inclusive' => false,
                    'is_default' => true,
                    'sub_components' => [
                        ['name' => 'State Sales Tax', 'rate' => 6.25, 'code' => 'STATE_TAX'],
                        ['name' => 'City / County Tax', 'rate' => 2.0, 'code' => 'LOCAL_TAX'],
                    ],
                    'description' => 'Standard combined state and municipal sales tax',
                ],
                [
                    'name' => 'Zero-Rated / Tax-Exempt Resale',
                    'code' => 'US_EXEMPT',
                    'rate' => 0.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [],
                    'description' => 'Exempt B2B resale with Form ST-120 or state exemption certificate',
                ],
            ],
        ],
        'GB' => [
            'country' => 'United Kingdom',
            'system' => 'HMRC Value Added Tax (VAT)',
            'standard_rate' => 20.0,
            'rules' => [
                [
                    'name' => 'Standard VAT 20%',
                    'code' => 'UK_VAT_20',
                    'rate' => 20.0,
                    'is_inclusive' => false,
                    'is_default' => true,
                    'sub_components' => [
                        ['name' => 'VAT', 'rate' => 20.0, 'code' => 'VAT_20'],
                    ],
                    'description' => 'Standard UK VAT rate for most commercial goods and services',
                ],
                [
                    'name' => 'Reduced Rate VAT 5%',
                    'code' => 'UK_VAT_5',
                    'rate' => 5.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [
                        ['name' => 'Reduced VAT', 'rate' => 5.0, 'code' => 'VAT_5'],
                    ],
                    'description' => 'Reduced VAT rate for domestic energy, children car seats',
                ],
                [
                    'name' => 'Zero-Rated 0% (Food & Books)',
                    'code' => 'UK_VAT_0',
                    'rate' => 0.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [],
                    'description' => 'Zero-rated basic food, publications, and children clothing',
                ],
            ],
        ],
        'AE' => [
            'country' => 'United Arab Emirates',
            'system' => 'Federal Tax Authority (FTA) VAT',
            'standard_rate' => 5.0,
            'rules' => [
                [
                    'name' => 'Standard VAT 5%',
                    'code' => 'UAE_VAT_5',
                    'rate' => 5.0,
                    'is_inclusive' => false,
                    'is_default' => true,
                    'sub_components' => [
                        ['name' => 'VAT', 'rate' => 5.0, 'code' => 'VAT_5'],
                    ],
                    'description' => 'Standard UAE 5% VAT across retail, dining, and professional services',
                ],
                [
                    'name' => 'Zero-Rated 0% (Designated Free Zone / Export)',
                    'code' => 'UAE_VAT_0',
                    'rate' => 0.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [],
                    'description' => 'Direct international exports and qualified designated free zone transactions',
                ],
            ],
        ],
        'SA' => [
            'country' => 'Saudi Arabia',
            'system' => 'ZATCA VAT & Phase 2 E-Invoicing',
            'standard_rate' => 15.0,
            'rules' => [
                [
                    'name' => 'Standard VAT 15% (ZATCA Compliant)',
                    'code' => 'KSA_VAT_15',
                    'rate' => 15.0,
                    'is_inclusive' => false,
                    'is_default' => true,
                    'sub_components' => [
                        ['name' => 'VAT (ضريبة القيمة المضافة)', 'rate' => 15.0, 'code' => 'VAT_15'],
                    ],
                    'description' => 'Standard 15% VAT for KSA with Phase 2 Fatoora cryptographic signing',
                ],
                [
                    'name' => 'Zero-Rated Export / Healthcare (0%)',
                    'code' => 'KSA_VAT_0',
                    'rate' => 0.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [],
                    'description' => 'Zero-rated qualified medical supplies and international transport',
                ],
            ],
        ],
        'CA' => [
            'country' => 'Canada',
            'system' => 'CRA GST/HST/PST',
            'standard_rate' => 13.0,
            'rules' => [
                [
                    'name' => 'Ontario HST 13%',
                    'code' => 'CA_HST_13',
                    'rate' => 13.0,
                    'is_inclusive' => false,
                    'is_default' => true,
                    'sub_components' => [
                        ['name' => 'Federal GST', 'rate' => 5.0, 'code' => 'FED_GST'],
                        ['name' => 'Provincial HST', 'rate' => 8.0, 'code' => 'PROV_HST'],
                    ],
                    'description' => '13% Harmonized Sales Tax for Ontario',
                ],
                [
                    'name' => 'Federal GST Only (5%)',
                    'code' => 'CA_GST_5',
                    'rate' => 5.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [
                        ['name' => 'GST', 'rate' => 5.0, 'code' => 'GST_5'],
                    ],
                    'description' => '5% GST for Alberta, Yukon, NWT, Nunavut',
                ],
            ],
        ],
        'AU' => [
            'country' => 'Australia',
            'system' => 'ATO Goods and Services Tax (GST)',
            'standard_rate' => 10.0,
            'rules' => [
                [
                    'name' => 'Standard GST 10% (Inclusive/Exclusive)',
                    'code' => 'AU_GST_10',
                    'rate' => 10.0,
                    'is_inclusive' => true,
                    'is_default' => true,
                    'sub_components' => [
                        ['name' => 'GST', 'rate' => 10.0, 'code' => 'GST_10'],
                    ],
                    'description' => 'Australian 10% Goods and Services Tax',
                ],
            ],
        ],
        'EU' => [
            'country' => 'European Union',
            'system' => 'EU VAT & One-Stop-Shop (OSS)',
            'standard_rate' => 21.0,
            'rules' => [
                [
                    'name' => 'Standard EU VAT 21%',
                    'code' => 'EU_VAT_21',
                    'rate' => 21.0,
                    'is_inclusive' => false,
                    'is_default' => true,
                    'sub_components' => [
                        ['name' => 'VAT', 'rate' => 21.0, 'code' => 'VAT_21'],
                    ],
                    'description' => 'Standard EU VAT rate for cross-border OSS',
                ],
                [
                    'name' => 'B2B Intra-Community Reverse Charge (0%)',
                    'code' => 'EU_REVERSE_CHARGE',
                    'rate' => 0.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [],
                    'description' => 'Zero-rated reverse charge with validated VIES VAT identification number',
                ],
            ],
        ],
        'SG' => [
            'country' => 'Singapore',
            'system' => 'IRAS Goods and Services Tax (GST)',
            'standard_rate' => 9.0,
            'rules' => [
                [
                    'name' => 'Standard GST 9%',
                    'code' => 'SG_GST_9',
                    'rate' => 9.0,
                    'is_inclusive' => false,
                    'is_default' => true,
                    'sub_components' => [
                        ['name' => 'GST', 'rate' => 9.0, 'code' => 'GST_9'],
                    ],
                    'description' => 'Singapore standard 9% GST rate',
                ],
            ],
        ],
        'BR' => [
            'country' => 'Brazil',
            'system' => 'ICMS / PIS / COFINS',
            'standard_rate' => 18.0,
            'rules' => [
                [
                    'name' => 'ICMS 18%',
                    'code' => 'BR_ICMS_18',
                    'rate' => 18.0,
                    'is_inclusive' => false,
                    'is_default' => true,
                    'sub_components' => [
                        ['name' => 'ICMS', 'rate' => 18.0, 'code' => 'ICMS_18'],
                    ],
                    'description' => 'Standard state-level ICMS consumption tax',
                ],
            ],
        ],
        'MX' => [
            'country' => 'Mexico',
            'system' => 'SAT Impuesto al Valor Agregado (IVA)',
            'standard_rate' => 16.0,
            'rules' => [
                [
                    'name' => 'Standard IVA 16%',
                    'code' => 'MX_IVA_16',
                    'rate' => 16.0,
                    'is_inclusive' => false,
                    'is_default' => true,
                    'sub_components' => [
                        ['name' => 'IVA', 'rate' => 16.0, 'code' => 'IVA_16'],
                    ],
                    'description' => 'Standard Mexican 16% IVA value-added tax',
                ],
                [
                    'name' => 'Northern Border Zone IVA 8%',
                    'code' => 'MX_IVA_8',
                    'rate' => 8.0,
                    'is_inclusive' => false,
                    'is_default' => false,
                    'sub_components' => [
                        ['name' => 'IVA Fronterizo', 'rate' => 8.0, 'code' => 'IVA_8'],
                    ],
                    'description' => 'Preferential 8% IVA for registered Northern Border businesses',
                ],
            ],
        ],
    ];

    /**
     * Calculate line item tax with complete fiscal decomposition.
     *
     * @param  array{product_id?: ?int, name?: string, quantity: float, price: float, tax_rate?: ?float, is_inclusive?: ?bool, tax_id?: ?string, hsn_sac_code?: ?string}  $itemData
     * @return array{taxable_amount: float, tax_amount: float, effective_rate: float, is_inclusive: bool, tax_name: string, hsn_sac_code: ?string, components: array, line_total: float}
     */
    public function calculateLineItemTax(
        array $itemData,
        ?Company $company = null,
        ?Customer $customer = null,
        ?TaxRule $explicitRule = null
    ): array {
        $qty = max(0.001, (float) ($itemData['quantity'] ?? 1));
        $unitPrice = (float) ($itemData['price'] ?? 0);
        $lineRawTotal = round($qty * $unitPrice, 4);

        // Check customer-level exemption (B2B Tax Exemption / Reverse Charge)
        if ($customer && ($customer->is_tax_exempt || (filled($customer->tax_id) && filled($itemData['reverse_charge'] ?? false)))) {
            return [
                'taxable_amount' => round($lineRawTotal, 2),
                'tax_amount' => 0.0,
                'effective_rate' => 0.0,
                'is_inclusive' => false,
                'tax_name' => 'Tax Exempt / B2B Reverse Charge',
                'tax_code' => 'EXEMPT',
                'hsn_sac_code' => $itemData['hsn_sac_code'] ?? ($itemData['hsn_code'] ?? null),
                'components' => [],
                'line_total' => round($lineRawTotal, 2),
            ];
        }

        // Resolve Product and Tax Rule
        $product = ! empty($itemData['product_id']) ? Product::find($itemData['product_id']) : null;
        if ($product) {
            if ($product->tax_exempt || $product->zero_rate || $product->taxable === false) {
                return [
                    'taxable_amount' => round($lineRawTotal, 2),
                    'tax_amount' => 0.0,
                    'effective_rate' => 0.0,
                    'is_inclusive' => false,
                    'tax_name' => 'Zero-Rated / Exempt Supply',
                    'tax_code' => 'ZERO_RATE',
                    'hsn_sac_code' => $product->hsn_code ?: $product->sac_code,
                    'components' => [],
                    'line_total' => round($lineRawTotal, 2),
                ];
            }
        }

        $rule = $explicitRule;
        if (! $rule && $product && $product->tax_rule_id) {
            $rule = TaxRule::find($product->tax_rule_id);
        }
        if (! $rule && $company) {
            $rule = TaxRule::where('company_id', $company->id)->where('active', true)->where('is_default', true)->first();
        }
        if (! $rule && ! empty($itemData['tax_rule_id'])) {
            $rule = TaxRule::find($itemData['tax_rule_id']);
        }

        // Determine rate and inclusive flag
        $rate = 0.0;
        $isInclusive = false;
        $taxName = 'Tax';
        $taxCode = 'TAX';
        $components = [];

        if ($rule) {
            $rate = (float) $rule->rate;
            $isInclusive = (bool) $rule->is_inclusive;
            $taxName = $rule->tax_name;
            $taxCode = $rule->tax_code ?: $rule->tax_name;
            $components = $rule->getComponents();
        } elseif (isset($itemData['tax_rate'])) {
            $rate = (float) $itemData['tax_rate'];
            $isInclusive = ! empty($itemData['is_inclusive']) || ! empty($itemData['is_tax_inclusive']);
            $taxName = $itemData['tax_name'] ?? "Tax ({$rate}%)";
            $taxCode = 'TAX_'.(int) $rate;
        } elseif ($product && $product->tax_rate !== null) {
            $rate = (float) $product->tax_rate;
            $isInclusive = (bool) $product->is_tax_inclusive;
            $taxName = "Tax ({$rate}%)";
            $taxCode = 'TAX_'.(int) $rate;
        }

        $hsnSac = $itemData['hsn_sac_code'] ?? ($product ? ($product->hsn_code ?: $product->sac_code) : null);

        if ($rate <= 0) {
            return [
                'taxable_amount' => round($lineRawTotal, 2),
                'tax_amount' => 0.0,
                'effective_rate' => 0.0,
                'is_inclusive' => false,
                'tax_name' => 'Non-Taxable',
                'tax_code' => 'NONE',
                'hsn_sac_code' => $hsnSac,
                'components' => [],
                'line_total' => round($lineRawTotal, 2),
            ];
        }

        if ($isInclusive) {
            // Formula: Tax = Total - (Total / (1 + Rate/100))
            $taxableAmount = $lineRawTotal / (1 + ($rate / 100));
            $taxAmount = $lineRawTotal - $taxableAmount;
            $lineTotal = $lineRawTotal;
        } else {
            // Formula: Tax = Base * (Rate/100)
            $taxableAmount = $lineRawTotal;
            $taxAmount = $taxableAmount * ($rate / 100);
            $lineTotal = $taxableAmount + $taxAmount;
        }

        // Calculate breakdown for sub-components (e.g. CGST + SGST)
        $calculatedComponents = [];
        if (! empty($components)) {
            $totalCompRate = array_sum(array_column($components, 'rate')) ?: $rate;
            foreach ($components as $comp) {
                $compRate = (float) ($comp['rate'] ?? 0);
                $compRatio = $totalCompRate > 0 ? ($compRate / $totalCompRate) : 0;
                $compAmount = round($taxAmount * $compRatio, 2);
                $calculatedComponents[] = [
                    'name' => $comp['name'] ?? $taxName,
                    'code' => $comp['code'] ?? ($comp['name'] ?? ''),
                    'rate' => $compRate,
                    'amount' => $compAmount,
                ];
            }
        } else {
            $calculatedComponents[] = [
                'name' => $taxName,
                'code' => $taxCode,
                'rate' => $rate,
                'amount' => round($taxAmount, 2),
            ];
        }

        return [
            'taxable_amount' => round($taxableAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'effective_rate' => round($rate, 3),
            'is_inclusive' => $isInclusive,
            'tax_name' => $taxName,
            'tax_code' => $taxCode,
            'hsn_sac_code' => $hsnSac,
            'components' => $calculatedComponents,
            'line_total' => round($lineTotal, 2),
        ];
    }

    /**
     * Compute entire cart/order financial totals with multi-item tax aggregation.
     *
     * @param  array<int, array>  $items
     * @return array{subtotal: float, discount: float, tax_amount: float, total: float, tax_summary_table: array, items: array}
     */
    public function calculateCartTotals(
        array $items,
        ?Company $company = null,
        ?Customer $customer = null,
        float $discount = 0.0,
        string $discountType = 'fixed'
    ): array {
        $processedItems = [];
        $rawSubtotal = 0.0;
        $totalTax = 0.0;
        $taxSummaryGroups = [];

        foreach ($items as $item) {
            if (empty($item['name']) && empty($item['product_id'])) {
                continue;
            }

            $taxResult = $this->calculateLineItemTax($item, $company, $customer);
            $enrichedItem = array_merge($item, [
                'taxable_amount' => $taxResult['taxable_amount'],
                'tax_amount' => $taxResult['tax_amount'],
                'tax_rate' => $taxResult['effective_rate'],
                'is_inclusive' => $taxResult['is_inclusive'],
                'tax_name' => $taxResult['tax_name'],
                'tax_code' => $taxResult['tax_code'] ?? 'TAX',
                'hsn_sac_code' => $taxResult['hsn_sac_code'],
                'tax_components' => $taxResult['components'],
                'line_total' => $taxResult['line_total'],
            ]);

            $processedItems[] = $enrichedItem;
            $rawSubtotal += (float) ($taxResult['is_inclusive'] ? $taxResult['taxable_amount'] : ($item['quantity'] * $item['price']));
            $totalTax += $taxResult['tax_amount'];

            // Aggregate into Tax Summary Table Grouping (only if rate or tax > 0)
            if ($taxResult['effective_rate'] > 0 || $taxResult['tax_amount'] > 0) {
                // Do not merge distinct jurisdiction rules that happen to share a rate.
                $rateKey = ($taxResult['tax_code'] ?? $taxResult['tax_name']).'_'.number_format($taxResult['effective_rate'], 2).'_'.($taxResult['is_inclusive'] ? 'inc' : 'exc');
                if (! isset($taxSummaryGroups[$rateKey])) {
                    $taxSummaryGroups[$rateKey] = [
                        'rate' => $taxResult['effective_rate'],
                        'tax_name' => $taxResult['tax_name'],
                        'is_inclusive' => $taxResult['is_inclusive'],
                        'taxable_amount' => 0.0,
                        'tax_amount' => 0.0,
                        'components' => [],
                    ];
                }

                $taxSummaryGroups[$rateKey]['taxable_amount'] += $taxResult['taxable_amount'];
                $taxSummaryGroups[$rateKey]['tax_amount'] += $taxResult['tax_amount'];

                foreach ($taxResult['components'] as $c) {
                    $cName = $c['name'];
                    if (! isset($taxSummaryGroups[$rateKey]['components'][$cName])) {
                        $taxSummaryGroups[$rateKey]['components'][$cName] = [
                            'name' => $cName,
                            'rate' => $c['rate'],
                            'amount' => 0.0,
                        ];
                    }
                    $taxSummaryGroups[$rateKey]['components'][$cName]['amount'] += $c['amount'];
                }
            }
        }

        $calcDiscount = $discountType === 'percent'
            ? round(($rawSubtotal * min(100, max(0, $discount))) / 100, 2)
            : min($rawSubtotal, max(0, $discount));

        $grandTotal = max(0, round($rawSubtotal - $calcDiscount + $totalTax, 2));

        // Format tax summary table list
        $taxSummaryTable = array_values(array_map(function ($group) {
            return [
                'tax_name' => $group['tax_name'],
                'rate' => round($group['rate'], 2),
                'is_inclusive' => $group['is_inclusive'],
                'taxable_amount' => round($group['taxable_amount'], 2),
                'tax_amount' => round($group['tax_amount'], 2),
                'components' => array_values($group['components']),
            ];
        }, $taxSummaryGroups));

        return [
            'subtotal' => round($rawSubtotal, 2),
            'discount' => round($calcDiscount, 2),
            'tax_amount' => round($totalTax, 2),
            'total' => $grandTotal,
            'tax_summary_table' => $taxSummaryTable,
            'items' => $processedItems,
        ];
    }

    /**
     * Pre-seed standard jurisdiction tax rules for a company.
     */
    public function seedTenantDefaultTaxRules(Company $company, ?string $countryCode = null): void
    {
        $country = strtoupper($countryCode ?: ($company->country ?: 'US'));
        $presets = self::JURISDICTIONS[$country]['rules'] ?? self::JURISDICTIONS['US']['rules'];

        // Reset previous default rules so the new country's designated default takes effect
        TaxRule::where('company_id', $company->id)->update(['is_default' => false]);

        foreach ($presets as $p) {
            TaxRule::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'tax_code' => $p['code'],
                ],
                [
                    'tax_name' => $p['name'],
                    'rate' => $p['rate'],
                    'type' => 'percentage',
                    'country' => $country,
                    'calc_type' => $p['is_inclusive'] ? 'inclusive' : 'exclusive',
                    'is_inclusive' => $p['is_inclusive'],
                    'is_default' => (bool) ($p['is_default'] ?? false),
                    'sub_components' => $p['sub_components'] ?? [],
                    'description' => $p['description'] ?? null,
                    'active' => true,
                ]
            );
        }
    }

    /**
     * Get jurisdiction presets dictionary.
     */
    public function getJurisdictionPresets(?string $countryCode = null): array
    {
        if ($countryCode) {
            $code = strtoupper($countryCode);

            return self::JURISDICTIONS[$code] ?? self::JURISDICTIONS['US'];
        }

        return self::JURISDICTIONS;
    }
}
