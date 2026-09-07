<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\TenantApiKey;
use App\Models\User;
use App\Services\Sdui\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The "Receipt Prefixes & Bank Terms" screen must only surface the document
 * prefix / disclaimer fields for the verticals a tenant actually runs.
 */
class ReceiptPrefixModeScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    private function tenant(array $overrides = []): array
    {
        $company = Company::create(array_merge([
            'name' => 'Scoped Store',
            'slug' => 'scoped-'.bin2hex(random_bytes(4)),
            'status' => 'active',
            'currency' => 'USD',
            'currency_symbol' => '$',
        ], $overrides));

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Owner',
            'email' => 'owner-'.$company->slug.'@example.test',
            'password' => Hash::make('password'),
            'role' => 'administrator',
            'status' => 'active',
        ]);

        $token = 'zk_live_'.bin2hex(random_bytes(16));
        TenantApiKey::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'name' => 'Device',
            'token' => $token,
            'permissions' => ['*'],
        ]);

        return [$company, ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']];
    }

    /** @return array{0: list<string>, 1: list<string>} [numberingFieldNames, termsFieldNames] */
    private function receiptFields(array $headers): array
    {
        $schema = $this->withHeaders($headers)
            ->getJson('/api/tenant/views/settings-receipts')
            ->assertOk()
            ->assertJsonPath('schema.title', 'Receipt Prefixes & Bank Terms')
            ->json('schema');

        $this->assertEmpty((new SchemaValidator())->validate($schema));

        $cards = array_values(array_filter(
            $schema['components'],
            fn ($c) => ($c['type'] ?? null) === 'card',
        ));

        $inputNames = static fn (array $card) => array_values(array_filter(array_map(
            fn ($c) => ($c['type'] ?? null) === 'text_input' ? $c['name'] : null,
            $card['components'],
        )));

        return [$inputNames($cards[0]), $inputNames($cards[1])];
    }

    public function test_pharmacy_only_tenant_sees_rx_prefix_but_not_repair_or_salon(): void
    {
        [, $headers] = $this->tenant(['pos_mode' => 'pharmacy']);
        [$numbering, $terms] = $this->receiptFields($headers);

        $this->assertSame(['invoice_prefix', 'quotation_prefix', 'prescription_prefix'], $numbering);
        $this->assertNotContains('repair_prefix', $numbering);
        $this->assertNotContains('salon_prefix', $numbering);

        $this->assertContains('dispensing_disclaimer', $terms);
        $this->assertNotContains('repair_warranty_terms', $terms);
        $this->assertNotContains('salon_policy_terms', $terms);
        // Universal terms + bank details always present.
        $this->assertSame(['invoice_terms', 'quote_terms', 'dispensing_disclaimer', 'bank_details'], $terms);
    }

    public function test_repair_only_tenant_sees_only_repair_vertical_fields(): void
    {
        [, $headers] = $this->tenant(['pos_mode' => 'repair']);
        [$numbering, $terms] = $this->receiptFields($headers);

        $this->assertSame(['invoice_prefix', 'quotation_prefix', 'repair_prefix'], $numbering);
        $this->assertSame(['invoice_terms', 'quote_terms', 'repair_warranty_terms', 'bank_details'], $terms);
    }

    public function test_plain_retail_tenant_sees_no_vertical_prefixes(): void
    {
        [, $headers] = $this->tenant(['pos_mode' => 'general']);
        [$numbering, $terms] = $this->receiptFields($headers);

        $this->assertSame(['invoice_prefix', 'quotation_prefix'], $numbering);
        $this->assertSame(['invoice_terms', 'quote_terms', 'bank_details'], $terms);
    }

    public function test_multi_module_tenant_sees_every_vertical_field(): void
    {
        [, $headers] = $this->tenant([
            'pos_mode' => 'general',
            'licensed_modules' => ['retail', 'pharmacy', 'repair_technician', 'service_booking'],
        ]);
        [$numbering, $terms] = $this->receiptFields($headers);

        $this->assertSame(
            ['invoice_prefix', 'quotation_prefix', 'prescription_prefix', 'repair_prefix', 'salon_prefix'],
            $numbering,
        );
        $this->assertContains('dispensing_disclaimer', $terms);
        $this->assertContains('repair_warranty_terms', $terms);
        $this->assertContains('salon_policy_terms', $terms);
    }

    public function test_vertical_disclaimer_round_trips_through_the_receipts_endpoint(): void
    {
        [$company, $headers] = $this->tenant(['pos_mode' => 'pharmacy']);

        $this->withHeaders($headers)->postJson('/api/tenant/settings/receipts', [
            'invoice_prefix' => 'INV-',
            'prescription_prefix' => 'RX2-',
            'dispensing_disclaimer' => 'Keep out of reach of children. Schedule-H drug: sold on registered practitioner prescription only.',
        ])->assertOk()->assertJsonPath('success', true);

        $company->refresh();
        $this->assertSame('RX2-', $company->prescription_prefix);
        $this->assertStringContainsString('Schedule-H', $company->dispensing_disclaimer);
    }
}
