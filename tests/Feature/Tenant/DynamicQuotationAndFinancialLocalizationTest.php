<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Quotes\Create as QuotesCreate;
use App\Livewire\Tenant\Quotes\Edit as QuotesEdit;
use App\Livewire\Tenant\Settings\Index as SettingsIndex;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TenantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class DynamicQuotationAndFinancialLocalizationTest extends TestCase
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

    public function test_quotation_creation_and_edit_dynamically_lists_active_payment_methods_only(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        // Remove any default payment methods and create controlled set
        PaymentMethod::withoutGlobalScopes()->where('company_id', $company->id)->delete();

        $wire = PaymentMethod::create([
            'company_id' => $company->id,
            'name' => 'Bank Wire Transfer',
            'code' => 'transfer',
            'is_active' => true,
            'order_index' => 1,
        ]);

        $cash = PaymentMethod::create([
            'company_id' => $company->id,
            'name' => 'Cash in Hand',
            'code' => 'cash',
            'is_active' => true,
            'order_index' => 2,
        ]);

        $inactiveCrypto = PaymentMethod::create([
            'company_id' => $company->id,
            'name' => 'Disabled Crypto Gateway',
            'code' => 'crypto',
            'is_active' => false,
            'order_index' => 3,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Dynamic Solutions Ltd',
            'email' => 'contact@dynamicsolutions.test',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Server Setup',
            'sale_price' => 500,
            'active' => true,
        ]);

        // 1. Livewire Quotes Create should list only active payment methods
        $createComponent = Livewire::test(QuotesCreate::class)
            ->assertSee('Bank Wire Transfer')
            ->assertSee('Cash in Hand')
            ->assertDontSee('Disabled Crypto Gateway');

        // First active method alphabetically is Bank Wire Transfer ('transfer')
        $this->assertSame('transfer', $createComponent->get('agreedPaymentMethod'));

        // 2. Select cash in hand and save quote
        $createComponent
            ->set('customerId', $customer->id)
            ->set('agreedPaymentMethod', 'cash')
            ->set('items.0.product_id', $product->id)
            ->set('items.0.name', 'Server Setup')
            ->set('items.0.quantity', 1)
            ->set('items.0.price', 500)
            ->call('save')
            ->assertRedirect();

        $quote = Sale::where('operation_type', 'quotation')->latest('id')->firstOrFail();
        $this->assertSame('cash', $quote->agreed_payment_method);
        $this->assertSame('cash', $quote->payment_method);

        // 3. Edit quote should have 'cash' pre-selected and only list active methods
        $editComponent = Livewire::test(QuotesEdit::class, ['quote' => $quote])
            ->assertSee('Bank Wire Transfer')
            ->assertSee('Cash in Hand')
            ->assertDontSee('Disabled Crypto Gateway');

        $this->assertSame('cash', $editComponent->get('agreedPaymentMethod'));

        // Update payment method to wire transfer
        $editComponent
            ->set('agreedPaymentMethod', 'transfer')
            ->call('save')
            ->assertRedirect();

        $this->assertSame('transfer', $quote->fresh()->agreed_payment_method);
        $this->assertSame('transfer', $quote->fresh()->payment_method);
    }

    public function test_quotation_creation_preloads_and_allows_customizing_quote_terms(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        $defaultTerms = '<p>Standard Terms: 30-day validity. 50% deposit upon acceptance.</p>';
        $company->update(['quote_terms' => $defaultTerms]);

        // Verify helper functions and TenantSetting wrapper
        $this->assertSame($defaultTerms, tenant_setting('quote_default_terms'));
        $this->assertSame($defaultTerms, tenant_setting('quote_terms'));
        $this->assertSame($defaultTerms, TenantSetting::get('quote_default_terms'));

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Consulting Package',
            'sale_price' => 300,
            'active' => true,
        ]);

        // 1. When opening Create Quote, terms are pre-loaded
        $createComponent = Livewire::test(QuotesCreate::class);
        $this->assertSame($defaultTerms, $createComponent->get('quoteNotes'));
        $this->assertSame($defaultTerms, $createComponent->get('notes'));

        // 2. Custom terms can be entered per quote
        $customTerms = '<p>Custom Agreement: Net 15 days, 100% on delivery.</p>';
        $createComponent
            ->set('quoteNotes', $customTerms)
            ->set('items.0.product_id', $product->id)
            ->set('items.0.name', 'Consulting Package')
            ->set('items.0.quantity', 2)
            ->set('items.0.price', 300)
            ->call('save')
            ->assertRedirect();

        $quote = Sale::where('operation_type', 'quotation')->latest('id')->firstOrFail();
        $this->assertSame($customTerms, $quote->notes);

        // 3. Edit quote loads the custom terms
        $editComponent = Livewire::test(QuotesEdit::class, ['quote' => $quote]);
        $this->assertSame($customTerms, $editComponent->get('quoteNotes'));
        $this->assertSame($customTerms, $editComponent->get('notes'));
    }

    public function test_financial_settings_labels_strictly_localize_in_english_and_portuguese(): void
    {
        [$company, $user] = $this->actingAsTenantAdmin();

        // 1. Strict English localization test
        App::setLocale('en');
        app()->setLocale('en');

        $enComponent = Livewire::test(SettingsIndex::class);

        // English labels MUST be present
        $enComponent->assertSee(__('PIX Key Type'))
            ->assertSee(__('PIX Key'))
            ->assertSee(__('Account Holder / Store Name'))
            ->assertSee(__('Account Holder City'))
            ->assertSee(__('Card Processing Fees'))
            ->assertSee(__('Standard Debit Fee (%)'))
            ->assertSee(__('Credit 1x / Single Installment Fee (%)'))
            ->assertSee(__('Scale Barcode Integration'))
            ->assertSee(__('Total Price (Price in Cents - e.g. 2 + CCCCC + VVVVV + D)'))
            ->assertSee(__('Scale Prefix Digit'))
            ->assertSee(__('Embedded Data Format'));

        // Hardcoded Portuguese strings MUST NOT appear in EN locale
        $enComponent->assertDontSee('Tipo de Chave PIX')
            ->assertDontSee('Nome do Titular / Loja')
            ->assertDontSee('Cidade do Titular')
            ->assertDontSee('Taxas da Maquininha')
            ->assertDontSee('Taxa Débito Padrão (%)')
            ->assertDontSee('Taxa Crédito 1x / À Vista (%)')
            ->assertDontSee('Balanças de Etiquetas')
            ->assertDontSee('Chave Aleatória (EVP)');

        // 2. Portuguese localization test
        App::setLocale('pt');
        app()->setLocale('pt');

        $ptComponent = Livewire::test(SettingsIndex::class);

        $ptComponent->assertSee('Tipo de Chave PIX')
            ->assertSee('Chave PIX')
            ->assertSee('Nome do Titular / Loja')
            ->assertSee('Cidade do Titular')
            ->assertSee('Taxas da Maquininha')
            ->assertSee('Taxa Débito Padrão (%)')
            ->assertSee('Taxa Crédito 1x / À Vista (%)')
            ->assertSee('Balanças de Etiquetas')
            ->assertSee('Preço Total (Preço em Centavos - ex: 2 + CCCCC + VVVVV + D)');
    }
}
