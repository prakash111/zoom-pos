<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Auth\TenantRegister;
use App\Livewire\Tenant\Billing\Index as BillingIndex;
use App\Models\ActivationCode;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DiningFloor;
use App\Models\DiningTable;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class SelfRegistrationAndSubscriptionTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        // Seed default plans if not already in database
        Plan::firstOrCreate(['name' => 'trial'], [
            'display_name' => 'Trial',
            'billing_cycle' => 'trial',
            'duration_days' => 14,
            'price' => 0.00,
            'currency' => 'USD',
            'active' => true,
        ]);
        Plan::firstOrCreate(['name' => 'starter'], [
            'display_name' => 'Starter',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'price' => 19.00,
            'currency' => 'USD',
            'active' => true,
        ]);
        Plan::firstOrCreate(['name' => 'professional'], [
            'display_name' => 'Professional',
            'billing_cycle' => 'yearly',
            'duration_days' => 365,
            'price' => 199.00,
            'currency' => 'USD',
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_tenant_can_self_register_with_free_trial_and_general_pos_mode(): void
    {
        Livewire::test(TenantRegister::class)
            ->set('storeName', 'Apex Electronics')
            ->set('slug', 'apex-electronics')
            ->set('posMode', 'general')
            ->set('ownerName', 'Jane Doe')
            ->set('email', 'jane@apexelectronics.com')
            ->set('phone', '+1234567890')
            ->set('taxId', 'TAX-APEX-9988')
            ->set('password', 'Secret123!')
            ->set('password_confirmation', 'Secret123!')
            ->set('planName', 'trial')
            ->call('register')
            ->assertRedirect(route('tenant.dashboard'));

        $company = Company::where('slug', 'apex-electronics')->firstOrFail();
        $this->assertSame('Apex Electronics', $company->name);
        $this->assertSame('general', $company->pos_mode);
        $this->assertSame('trial', $company->plan_name);
        $this->assertSame('active', $company->status);
        $this->assertNotNull($company->expires_at);
        $this->assertTrue($company->expires_at->isFuture());

        // Default Payment methods provisioned
        $this->assertCount(3, PaymentMethod::where('company_id', $company->id)->get());

        // User admin provisioned
        $admin = User::where('company_id', $company->id)->where('email', 'jane@apexelectronics.com')->firstOrFail();
        $this->assertSame(User::ROLE_ADMINISTRATOR, $admin->role);

        // Subscription record created
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'plan_name' => 'trial',
            'status' => 'active',
        ]);

        // Compliant Tax Invoice generated
        $invoice = SubscriptionInvoice::where('company_id', $company->id)->firstOrFail();
        $this->assertStringStartsWith('INV-SUB-', $invoice->invoice_number);
        $this->assertSame('0.00', (string) $invoice->total);
        $this->assertSame('free_trial', $invoice->payment_method);
        $this->assertSame('paid', $invoice->status);

        // Auto-imported Demo Data for General Retail
        $this->assertGreaterThan(0, Category::withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertGreaterThan(0, Product::withoutGlobalScopes()->where('company_id', $company->id)->count());
        $this->assertGreaterThan(0, Customer::withoutGlobalScopes()->where('company_id', $company->id)->count());
    }

    public function test_tenant_can_self_register_with_restaurant_mode_and_pro_plan(): void
    {
        Livewire::test(TenantRegister::class)
            ->set('storeName', 'Gourmet Italian Bistro')
            ->set('slug', 'gourmet-bistro')
            ->set('posMode', 'restaurant')
            ->set('ownerName', 'Mario Rossi')
            ->set('email', 'mario@gourmetbistro.com')
            ->set('taxId', 'GSTIN-BISTRO-4455')
            ->set('password', 'Bistro2026!')
            ->set('password_confirmation', 'Bistro2026!')
            ->set('planName', 'professional')
            ->call('register')
            ->assertRedirect(route('tenant.dashboard'));

        $company = Company::where('slug', 'gourmet-bistro')->firstOrFail();
        $this->assertSame('restaurant', $company->pos_mode);
        $this->assertSame('professional', $company->plan_name);

        // Floor and tables provisioned automatically for restaurant
        $floor = DiningFloor::where('company_id', $company->id)->firstOrFail();
        $this->assertSame('Main Dining Area', $floor->name);
        $this->assertGreaterThanOrEqual(4, DiningTable::where('company_id', $company->id)->count());

        // Restaurant Menu Demo Items provisioned
        $this->assertGreaterThan(0, Category::where('company_id', $company->id)->count());
        $this->assertGreaterThan(0, Product::where('company_id', $company->id)->count());

        // Tax Invoice check with 18% GST
        $invoice = SubscriptionInvoice::where('company_id', $company->id)->firstOrFail();
        $this->assertSame('199.00', (string) $invoice->subtotal);
        $this->assertSame('35.82', (string) $invoice->tax_amount);
        $this->assertSame('234.82', (string) $invoice->total);
        $this->assertSame('GST', $invoice->tax_type);
        $this->assertNotNull($invoice->tax_breakdown);
        $this->assertEquals(9.00, $invoice->tax_breakdown['cgst_rate']);
    }

    public function test_tenant_can_self_register_with_activation_code(): void
    {
        $rawCode = 'AGY-TEST-KEY1';
        $code = ActivationCode::create([
            'code_hash' => Hash::make($rawCode),
            'code_prefix' => substr($rawCode, 0, 7),
            'plan_name' => 'professional',
            'max_uses' => 1,
            'validity_days' => 365,
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        Livewire::test(TenantRegister::class)
            ->set('storeName', 'License Store')
            ->set('slug', 'license-store')
            ->set('ownerName', 'Code Owner')
            ->set('email', 'owner@licensestore.com')
            ->set('password', 'Secret123!')
            ->set('password_confirmation', 'Secret123!')
            ->set('hasActivationCode', true)
            ->set('activationCode', $rawCode)
            ->call('register')
            ->assertRedirect(route('tenant.dashboard'));

        $company = Company::where('slug', 'license-store')->firstOrFail();
        $this->assertSame('professional', $company->plan_name);

        // Code uses incremented
        $this->assertSame(1, $code->fresh()->current_uses);

        // Invoice has activation_key payment method
        $invoice = SubscriptionInvoice::where('company_id', $company->id)->firstOrFail();
        $this->assertSame('activation_key', $invoice->payment_method);
    }

    public function test_tenant_can_redeem_activation_code_from_billing_dashboard(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $rawCode = 'KEY-UPGRADE-99';
        $code = ActivationCode::create([
            'code_hash' => Hash::make($rawCode),
            'code_prefix' => substr($rawCode, 0, 7),
            'plan_name' => 'professional',
            'max_uses' => 1,
            'validity_days' => 180,
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        Livewire::test(BillingIndex::class)
            ->set('activationCode', $rawCode)
            ->call('redeemCode')
            ->assertHasNoErrors()
            ->assertSee('License code activated successfully');

        $this->assertSame('professional', $company->fresh()->plan_name);
        $this->assertSame(1, $code->fresh()->current_uses);

        // A new subscription invoice was generated for this activation
        $invoice = SubscriptionInvoice::where('company_id', $company->id)->latest('id')->firstOrFail();
        $this->assertSame('activation_key', $invoice->payment_method);
        $this->assertSame('Professional', $invoice->plan_name);
    }

    public function test_expired_tenant_is_redirected_to_billing_by_subscription_middleware(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        // Expire subscription
        $company->update([
            'expires_at' => now()->subDay(),
            'status' => 'active',
        ]);

        // Attempting to access POS create is blocked and redirected to billing
        $response = $this->get(route('tenant.sales.create'));
        $response->assertRedirect(route('tenant.billing.index'));

        // Billing page itself is accessible without redirect loops
        $billingResponse = $this->get(route('tenant.billing.index'));
        $billingResponse->assertStatus(200);

        // Settings page is accessible for store config
        $settingsResponse = $this->get(route('tenant.settings.index'));
        $settingsResponse->assertStatus(200);
    }

    public function test_tenant_can_view_and_download_tax_invoice_pdf(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $invoice = SubscriptionInvoice::create([
            'company_id' => $company->id,
            'invoice_number' => 'INV-SUB-2026-9999',
            'plan_name' => 'Professional',
            'billing_cycle' => 'yearly',
            'currency' => 'USD',
            'subtotal' => 199.00,
            'tax_rate' => 18.00,
            'tax_amount' => 35.82,
            'tax_type' => 'GST',
            'tax_breakdown' => [
                'cgst_rate' => 9.00,
                'cgst_amount' => 17.91,
                'sgst_rate' => 9.00,
                'sgst_amount' => 17.91,
            ],
            'total' => 234.82,
            'payment_method' => 'activation_key',
            'status' => 'paid',
            'invoice_date' => now()->toDateString(),
            'seller_details' => [
                'company_name' => 'Smart SaaS Corp',
                'legal_name' => 'Smart Inventory Platform Inc.',
                'tax_id' => 'GSTIN-SELLER-1234',
            ],
            'buyer_details' => [
                'store_name' => $company->name,
                'tax_id' => 'GSTIN-BUYER-5678',
            ],
        ]);

        $response = $this->get(route('tenant.billing.invoices.pdf', $invoice));
        $response->assertStatus(200);
        $response->assertSee('OFFICIAL TAX INVOICE');
        $response->assertSee('INV-SUB-2026-9999');
        $response->assertSee('Smart Inventory Platform Inc.');
        $response->assertSee('234.82');
        $response->assertSee('CGST (9%)');
        $response->assertSee('SGST (9%)');
    }

    public function test_multiple_plan_switches_generate_unique_consecutive_invoices_without_integrity_constraint_violation(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        // 1. Initial invoice already exists (e.g. INV-SUB-2026-0001)
        SubscriptionInvoice::create([
            'company_id' => $company->id,
            'invoice_number' => 'INV-SUB-'.date('Y').'-0001',
            'plan_name' => 'Trial',
            'billing_cycle' => 'trial',
            'currency' => 'USD',
            'subtotal' => 0.00,
            'tax_rate' => 0.00,
            'tax_amount' => 0.00,
            'tax_type' => 'GST',
            'total' => 0.00,
            'payment_method' => 'free_trial',
            'status' => 'paid',
            'invoice_date' => now()->toDateString(),
        ]);

        // 2. Tenant initiates switch to Starter plan -> modal opens
        Livewire::test(BillingIndex::class)
            ->call('selectPlanToUpgrade', 'starter')
            ->assertSet('showPaymentModal', true)
            ->assertSet('selectedPlanId', 'starter')
            ->set('paymentGateway', 'credit_card')
            ->set('cardHolder', 'Jane Admin')
            ->set('cardNumber', '4242424242424242')
            ->set('cardExpiry', '12/28')
            ->set('cardCvv', '123')
            ->call('processSubscriptionPayment')
            ->assertHasNoErrors()
            ->assertSet('showPaymentModal', false);

        // 3. Tenant switches plan again to Professional with payment
        Livewire::test(BillingIndex::class)
            ->call('selectPlanToUpgrade', 'professional')
            ->assertSet('showPaymentModal', true)
            ->assertSet('selectedPlanId', 'professional')
            ->set('paymentGateway', 'credit_card')
            ->set('cardHolder', 'Jane Admin')
            ->set('cardNumber', '4242424242424242')
            ->set('cardExpiry', '12/28')
            ->set('cardCvv', '123')
            ->call('processSubscriptionPayment')
            ->assertHasNoErrors()
            ->assertSet('showPaymentModal', false);

        $invoices = SubscriptionInvoice::where('company_id', $company->id)->get();
        $this->assertCount(3, $invoices);

        $invoiceNumbers = $invoices->pluck('invoice_number')->toArray();
        $this->assertCount(3, array_unique($invoiceNumbers));
        $this->assertContains('INV-SUB-'.date('Y').'-0001', $invoiceNumbers);
        $this->assertContains('INV-SUB-'.date('Y').'-0002', $invoiceNumbers);
        $this->assertContains('INV-SUB-'.date('Y').'-0003', $invoiceNumbers);
    }

    public function test_razorpay_order_initiation_and_signature_verification_activates_subscription(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $company->update(['plan_name' => 'trial']);

        // Ensure Razorpay test gateway is enabled in database
        PaymentGatewaySetting::updateOrCreate(
            ['gateway' => 'razorpay'],
            [
                'enabled' => true,
                'mode' => 'test',
                'public_key' => 'rzp_test_TREMaRBSsygL4w',
                'secret_key' => 'cXysb5ytiW29qyvMHfaU7osJ',
            ]
        );

        $orderId = 'order_test_99999';
        $paymentId = 'pay_test_88888';
        $secret = 'cXysb5ytiW29qyvMHfaU7osJ';
        $validSignature = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);

        Livewire::test(BillingIndex::class)
            ->set('selectedPlanId', 'professional')
            ->set('showPaymentModal', true)
            ->call('verifyAndActivateRazorpayPayment', $paymentId, $orderId, $validSignature)
            ->assertHasNoErrors()
            ->assertSet('showPaymentModal', false);

        $this->assertSame('professional', $company->fresh()->plan_name);
        $this->assertDatabaseHas('subscription_invoices', [
            'company_id' => $company->id,
            'plan_name' => 'Professional',
            'payment_method' => 'razorpay',
            'payment_reference' => $paymentId,
            'status' => 'paid',
        ]);
    }

    public function test_paid_plan_cannot_be_activated_without_payment_details(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        $company->update(['plan_name' => 'trial']);

        // Selecting a paid plan should not automatically upgrade company
        Livewire::test(BillingIndex::class)
            ->call('selectPlanToUpgrade', 'starter')
            ->assertSet('showPaymentModal', true);

        // Company plan must still be trial because payment was not completed
        $this->assertSame('trial', $company->fresh()->plan_name);

        // Submitting with empty card should fail validation and not upgrade
        Livewire::test(BillingIndex::class)
            ->set('showPaymentModal', true)
            ->set('selectedPlanId', 'starter')
            ->set('paymentGateway', 'credit_card')
            ->set('cardNumber', '')
            ->call('processSubscriptionPayment')
            ->assertHasErrors(['cardNumber']);

        $this->assertSame('trial', $company->fresh()->plan_name);
    }

    public function test_tenant_registration_supports_optional_subdomain_and_custom_domain(): void
    {
        Livewire::test(TenantRegister::class)
            ->set('storeName', 'Boutique Coffee')
            ->set('slug', 'boutique-coffee')
            ->set('customDomain', 'pos.boutiquecoffee.com')
            ->set('posMode', 'restaurant')
            ->set('ownerName', 'Barista Bob')
            ->set('email', 'bob@boutiquecoffee.com')
            ->set('password', 'Secret123!')
            ->set('password_confirmation', 'Secret123!')
            ->set('planName', 'trial')
            ->call('register')
            ->assertRedirect(route('tenant.dashboard'));

        $company = Company::where('slug', 'boutique-coffee')->firstOrFail();
        $this->assertSame('pos.boutiquecoffee.com', $company->custom_domain);
        $this->assertSame('boutique-coffee', $company->slug);
        $this->assertSame('restaurant', $company->pos_mode);
    }

    public function test_public_table_order_qr_view_renders_product_search_bar(): void
    {
        $comp = Company::create(['name' => 'Pizza House', 'status' => 'active', 'pos_mode' => 'restaurant']);
        [$company, $admin] = $this->actingAsTenantAdmin($comp);

        $floor = DiningFloor::create([
            'company_id' => $company->id,
            'name' => 'Main Terrace',
        ]);

        $table = DiningTable::create([
            'company_id' => $company->id,
            'floor_id' => $floor->id,
            'table_number' => 'T-07',
            'qr_token' => 'qr_test_token_123',
            'status' => 'available',
        ]);

        Product::create([
            'company_id' => $company->id,
            'name' => 'Artisan Margherita Pizza',
            'sale_price' => 14.50,
            'active' => true,
        ]);

        $response = $this->get(route('restaurant.table.order', 'qr_test_token_123'));
        $response->assertStatus(200);
        $response->assertSee('Search dishes, drinks, appetizers, desserts…');
        $response->assertSee('Artisan Margherita Pizza');
        $response->assertSee('T-07');
    }
}
