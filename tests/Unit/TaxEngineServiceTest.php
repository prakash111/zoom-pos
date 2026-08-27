<?php

namespace Tests\Unit;

use App\Services\TaxEngineService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaxEngineServiceTest extends TestCase
{
    #[DataProvider('countryLabels')]
    public function test_it_resolves_country_specific_tax_identifier_labels(string $country, string $label): void
    {
        $this->assertSame($label, TaxEngineService::getTaxIdentifierLabel($country));
    }

    public static function countryLabels(): array
    {
        return [
            ['IN', 'GSTIN'],
            ['Brazil', 'CNPJ / CPF'],
            ['UK', 'VAT Reg No'],
            ['UAE', 'TRN / VAT ID'],
            ['USA', 'EIN / Tax ID'],
            ['Canada', 'BN / GST No'],
            ['Australia', 'ABN'],
            ['EU', 'VAT Reg No'],
            ['Brasil', 'CNPJ / CPF'],
            ['India (IN)', 'GSTIN'],
            ['NZ', 'GSTIN'],
        ];
    }

    public function test_gst_rule_overrides_a_stale_us_company_country(): void
    {
        $this->assertSame(
            'GSTIN',
            TaxEngineService::getTaxIdentifierLabel('US', 'GST 18% (Intra-State)')
        );
    }

    public function test_it_normalizes_persisted_and_requested_breakdown_shapes(): void
    {
        $rows = TaxEngineService::normalizeTaxBreakdown([
            [
                'tax_name' => 'GST 18% (Intra-State)',
                'rate' => 18,
                'taxable_amount' => 21.44,
                'tax_amount' => 3.86,
                'components' => [
                    ['name' => 'CGST', 'rate' => 9, 'amount' => 1.93],
                    ['name' => 'SGST', 'rate' => 9, 'amount' => 1.93],
                ],
            ],
            [
                'rule_name' => 'VAT',
                'rate' => 5,
                'taxable' => 100,
                'amount' => 5,
                'sub_components' => [],
            ],
        ]);

        $this->assertSame('GST 18% (Intra-State)', $rows[0]['name']);
        $this->assertSame(21.44, $rows[0]['taxable']);
        $this->assertSame(3.86, $rows[0]['amount']);
        $this->assertSame('CGST', $rows[0]['sub_components'][0]['name']);
        $this->assertSame('VAT', $rows[1]['name']);
    }
}
