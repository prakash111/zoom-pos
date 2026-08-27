<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Sales\Create as SalesCreate;
use App\Livewire\Tenant\Settings\Index as SettingsIndex;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CardFeeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class PosStandardScannerAndCardFeesTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

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

    public function test_standard_scanner_layout_maintains_rigid_viewport_and_visible_checkout_button_with_multiple_items(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        $category = Category::create([
            'company_id' => $company->id,
            'name' => 'Bakery',
            'code' => 'BAK',
        ]);

        $products = [];
        for ($i = 1; $i <= 10; $i++) {
            $products[] = Product::create([
                'company_id' => $company->id,
                'category_id' => $category->id,
                'name' => "Bakery Product {$i}",
                'code' => "BAK-{$i}",
                'sale_price' => 5.00 * $i,
                'cost_price' => 2.00 * $i,
                'current_stock' => 100,
                'unit' => 'pcs',
                'is_active' => true,
            ]);
        }

        $component = Livewire::test(SalesCreate::class)
            ->set('layout', 'standard');

        // Add 8+ items to cart
        foreach ($products as $prod) {
            $component->call('addProductToCart', $prod->id);
        }

        $component->assertCount('items', 10);

        // Verify HTML markup has rigid viewport container and pinned checkout button
        $component->assertSee('h-[calc(100vh-120px)]')
            ->assertSee('flex-1 min-h-0 overflow-y-auto')
            ->assertSee('Total Payable')
            ->assertSee('Charge')
            ->assertSee('F10');
    }

    public function test_modifying_card_merchant_fees_in_settings_updates_pos_checkout_calculation(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        // Save new custom fee rates via Settings Index
        Livewire::test(SettingsIndex::class)
            ->set('cardFeeDebit', 2.10)
            ->set('cardFeeCredit1x', 3.80)
            ->set('cardFeeCreditInstallments', 5.20)
            ->call('save')
            ->assertHasNoErrors();

        $company->refresh();
        $this->assertEquals(2.10, (float) $company->card_fee_debit);
        $this->assertEquals(3.80, (float) $company->card_fee_credit_1x);
        $this->assertEquals(5.20, (float) $company->card_fee_credit_installments);

        $category = Category::create([
            'company_id' => $company->id,
            'name' => 'Electronics',
            'code' => 'ELEC',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Mechanical Keyboard',
            'code' => 'KB-100',
            'sale_price' => 100.00,
            'cost_price' => 50.00,
            'current_stock' => 20,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        // Test POS Sales Create calculation with updated rates
        $pos = Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product->id)
            ->set('paymentMethod', 'card')
            ->set('cardType', 'credit')
            ->set('installments', 1);

        // 1x credit fee should be 3.80% -> $3.80 fee, $96.20 net
        $this->assertEquals(3.80, $pos->get('cardFeePercentage'));
        $this->assertEquals(3.80, $pos->get('merchantFeeAmount'));
        $this->assertEquals(96.20, $pos->get('netReceivableAmount'));

        // 2x credit fee should be base fee 5.20% -> $5.20 fee, $94.80 net
        $pos->set('installments', 2);
        $this->assertEquals(5.20, $pos->get('cardFeePercentage'));
        $this->assertEquals(5.20, $pos->get('merchantFeeAmount'));
        $this->assertEquals(94.80, $pos->get('netReceivableAmount'));

        // Debit card fee should be 2.10% -> $2.10 fee, $97.90 net
        $pos->set('cardType', 'debit');
        $this->assertEquals(2.10, $pos->get('cardFeePercentage'));
        $this->assertEquals(2.10, $pos->get('merchantFeeAmount'));
        $this->assertEquals(97.90, $pos->get('netReceivableAmount'));
    }

    public function test_card_fee_calculator_service_handles_1x_to_12x_breakdowns(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        $company->update([
            'card_fee_debit' => 1.50,
            'card_fee_credit_1x' => 3.20,
            'card_fee_credit_installments' => 4.50,
        ]);

        $calculator = new CardFeeCalculator;
        $options = $calculator->calculateInstallments(120.00, 'credit', 1);

        $this->assertCount(12, $options);

        // 1x installment
        $this->assertEquals(1, $options[1]['installments']);
        $this->assertEquals(120.00, $options[1]['installment_amount']);
        $this->assertEquals(3.20, $options[1]['fee_rate']);
        $this->assertEquals(3.84, $options[1]['fee_amount']);
        $this->assertEquals(116.16, $options[1]['net_receivable']);
        $this->assertStringContainsString('1x ($120.00)', $options[1]['label']);

        // 3x installment: 4.50 + ((3 - 2) * 0.4) = 4.90%
        $this->assertEquals(3, $options[3]['installments']);
        $this->assertEquals(40.00, $options[3]['installment_amount']);
        $this->assertEquals(4.90, $options[3]['fee_rate']);
        $this->assertEquals(5.88, $options[3]['fee_amount']);
        $this->assertEquals(114.12, $options[3]['net_receivable']);
        $this->assertStringContainsString('3x ($40.00/mo)', $options[3]['label']);
    }

    public function test_pos_checkout_modal_strict_english_localization(): void
    {
        App::setLocale('en');

        [$company, $user] = $this->actingAsTenantAdmin();

        $category = Category::create([
            'company_id' => $company->id,
            'name' => 'General',
            'code' => 'GEN',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Store Item',
            'sale_price' => 50.00,
            'cost_price' => 25.00,
            'current_stock' => 10,
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '123456789',
        ]);

        $component = Livewire::test(SalesCreate::class)
            ->call('addProductToCart', $product->id)
            ->set('customerId', $customer->id)
            ->call('openCheckoutModal')
            ->set('paymentMethod', 'card')
            ->set('cardType', 'credit')
            ->set('installments', 1);

        $html = $component->html();

        // Verify English labels exist
        $this->assertStringContainsString('Installments', $html);
        $this->assertStringContainsString('Single Installment', $html);
        $this->assertStringContainsString('Credit / Deferred Account', $html);
        $this->assertStringContainsString('Consignment Dispatch', $html);
        $this->assertStringContainsString('x-teleport="body"', $html);
        $this->assertStringContainsString('z-[70]', $html);
        $this->assertStringNotContainsString('📦 Consignment', $html);
        $this->assertStringNotContainsString('Ð', $html);

        // Verify Portuguese strings are absent in EN locale
        $this->assertStringNotContainsString('Parcelas', $html);
        $this->assertStringNotContainsString('À vista', $html);
        $this->assertStringNotContainsString('/mês', $html);
        $this->assertStringNotContainsString('A Prazo / Crediário', $html);
    }
}
