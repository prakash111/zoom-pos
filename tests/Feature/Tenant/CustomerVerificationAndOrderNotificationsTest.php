<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Settings\Index as SettingsIndex;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TenantNotificationGateway;
use App\Models\User;
use App\Services\Auth\CustomerVerificationService;
use App\Services\Notifications\StorefrontOrderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class CustomerVerificationAndOrderNotificationsTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    protected Company $company;
    protected User $user;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');

        $this->company = Company::create([
            'name' => 'Subodh Store',
            'slug' => 'subodh-store-novc',
            'email' => 'store@example.com',
            'phone' => '+15551234567',
            'status' => 'active',
            'pos_mode' => 'retail',
            'currency_symbol' => '$',
            'require_customer_verification' => false,
            'verification_channels' => ['email', 'sms', 'whatsapp'],
            'enable_order_notifications' => false,
            'order_notification_channels' => ['email', 'sms', 'whatsapp'],
            'order_notification_events' => ['placed', 'completed', 'cancelled'],
            'expires_at' => now()->addYear(),
        ]);

        $this->user = User::create([
            'company_id' => $this->company->id,
            'name' => 'Subodh Admin',
            'login' => 'subodhadmin',
            'email' => 'subodhksingh85@gmail.com',
            'password' => Hash::make('secret1234'),
            'role' => 'administrator',
            'status' => 'approved',
        ]);

        $this->product = Product::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Wireless Headphones',
            'code' => 'HEADPHONES-01',
            'price' => 50.00,
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_tenant_settings_can_save_notification_and_verification_preferences(): void
    {
        $this->actingAs($this->user, 'web');
        app()->instance('tenant.company_id', $this->company->id);

        Livewire::test(SettingsIndex::class)
            ->set('requireCustomerVerification', true)
            ->set('verificationChannels', ['email', 'whatsapp'])
            ->set('enableOrderNotifications', true)
            ->set('orderNotificationChannels', ['email', 'sms'])
            ->set('orderNotificationEvents', ['placed', 'completed'])
            ->call('saveNotificationPreferences')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->company->refresh();
        $this->assertTrue($this->company->require_customer_verification);
        $this->assertEquals(['email', 'whatsapp'], $this->company->verification_channels);
        $this->assertTrue($this->company->enable_order_notifications);
        $this->assertEquals(['email', 'sms'], $this->company->order_notification_channels);
        $this->assertEquals(['placed', 'completed'], $this->company->order_notification_events);
    }

    public function test_order_succeeds_without_otp_when_verification_is_disabled(): void
    {
        $this->company->update(['require_customer_verification' => false]);

        $response = $this->postJson('/store/order?store='.$this->company->slug, [
            'customer_name' => 'John Doe',
            'customer_phone' => '+15559876543',
            'customer_email' => 'johndoe@example.com',
            'delivery_address' => '123 Main St',
            'city' => 'Metropolis',
            'payment_method' => 'cod',
            'items' => [
                [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'quantity' => 1,
                    'price' => 50.00,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('sales', [
            'company_id' => $this->company->id,
        ]);
    }

    public function test_order_requires_otp_when_verification_is_enabled_for_unverified_customer(): void
    {
        $this->company->update([
            'require_customer_verification' => true,
            'verification_channels' => ['email'],
        ]);

        // 1. Initial order attempt without code -> returns verification_required
        $response = $this->postJson('/store/order?store='.$this->company->slug, [
            'customer_name' => 'Jane Unverified',
            'customer_phone' => '+15553334444',
            'customer_email' => 'jane@example.com',
            'delivery_address' => '456 Oak Ave',
            'city' => 'Gotham',
            'payment_method' => 'cod',
            'items' => [
                [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'quantity' => 2,
                    'price' => 50.00,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'verification_required' => true,
            ]);

        $customer = Customer::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('phone', '+15553334444')
            ->first();

        $this->assertNotNull($customer);
        $this->assertFalse($customer->isVerified());
        $this->assertNotNull($customer->verification_code);
        $otp = $customer->verification_code;

        // 2. Order attempt with WRONG code -> returns 422 verification_failed
        $wrongResponse = $this->postJson('/store/order?store='.$this->company->slug, [
            'customer_name' => 'Jane Unverified',
            'customer_phone' => '+15553334444',
            'customer_email' => 'jane@example.com',
            'delivery_address' => '456 Oak Ave',
            'city' => 'Gotham',
            'payment_method' => 'cod',
            'verification_code' => '000000',
            'items' => [
                [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'quantity' => 2,
                    'price' => 50.00,
                ],
            ],
        ]);

        $wrongResponse->assertStatus(422)
            ->assertJson([
                'success' => false,
                'verification_required' => true,
                'verification_failed' => true,
            ]);

        // 3. Order attempt with CORRECT code -> succeeds and customer is verified
        $correctResponse = $this->postJson('/store/order?store='.$this->company->slug, [
            'customer_name' => 'Jane Unverified',
            'customer_phone' => '+15553334444',
            'customer_email' => 'jane@example.com',
            'delivery_address' => '456 Oak Ave',
            'city' => 'Gotham',
            'payment_method' => 'cod',
            'verification_code' => $otp,
            'items' => [
                [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'quantity' => 2,
                    'price' => 50.00,
                ],
            ],
        ]);

        $correctResponse->assertOk()
            ->assertJson(['success' => true]);

        $customer->refresh();
        $this->assertTrue($customer->isVerified());
        $this->assertNull($customer->verification_code);
    }

    public function test_standalone_verification_endpoints_work(): void
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Alice Test',
            'phone' => '+15557778888',
            'email' => 'alice@example.com',
            'is_verified' => false,
        ]);

        // Send verification
        $sendResponse = $this->postJson('/store/auth/send-verification?store='.$this->company->slug, [
            'customer_id' => $customer->id,
            'phone' => $customer->phone,
        ]);

        $sendResponse->assertOk()
            ->assertJson(['success' => true]);

        $customer->refresh();
        $code = $customer->verification_code;
        $this->assertNotNull($code);

        // Verify invalid code
        $failVerify = $this->postJson('/store/auth/verify-code?store='.$this->company->slug, [
            'customer_id' => $customer->id,
            'code' => '999999',
        ]);

        $failVerify->assertStatus(422)
            ->assertJson(['success' => false]);

        // Verify correct code
        $successVerify = $this->postJson('/store/auth/verify-code?store='.$this->company->slug, [
            'customer_id' => $customer->id,
            'code' => $code,
        ]);

        $successVerify->assertOk()
            ->assertJson(['success' => true]);

        $customer->refresh();
        $this->assertTrue($customer->isVerified());
    }

    public function test_order_notification_service_dispatches_when_enabled(): void
    {
        $this->company->update([
            'enable_order_notifications' => true,
            'order_notification_channels' => ['email', 'sms', 'whatsapp'],
            'order_notification_events' => ['placed', 'completed', 'cancelled'],
        ]);

        $sale = Sale::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'sale_number' => 'WEB-SUB-0001',
            'tracking_code' => 'TRK-SUB-0001',
            'customer_name' => 'Alice Buyer',
            'customer_phone' => '+15557778888',
            'customer_email' => 'alice@example.com',
            'final_amount' => 100.00,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $notifService = app(StorefrontOrderNotificationService::class);

        // Notify placed
        $placedResult = $notifService->notifyOrderPlaced($sale);
        $this->assertIsArray($placedResult);

        // Notify completed
        $completedResult = $notifService->notifyOrderStatusChanged($sale, 'completed');
        $this->assertIsArray($completedResult);

        // Notify cancelled
        $cancelledResult = $notifService->notifyOrderStatusChanged($sale, 'cancelled', 'Customer requested cancellation.');
        $this->assertIsArray($cancelledResult);
    }
}
