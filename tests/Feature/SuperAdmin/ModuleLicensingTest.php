<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Modules\Checkout;
use App\Livewire\SuperAdmin\Modules\Index as ModulesIndex;
use App\Models\PlatformSystem;
use App\Models\SduiModule;
use App\Services\Modular\ModulePackageService;
use App\Services\Payment\PlatformCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class ModuleLicensingTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    private function packageRow(array $overrides = []): SduiModule
    {
        return SduiModule::create(array_merge([
            'name' => 'Widgets',
            'slug' => 'widgets',
            'source_type' => 'package',
            'package_path' => 'widgets',
            'installed_at' => now(),
            'is_active' => false,
            'requires_license' => true,
            'license_status' => 'unlicensed',
            'navigation' => [],
            'features' => [],
        ], $overrides));
    }

    public function test_activate_refuses_an_unlicensed_package_module(): void
    {
        $module = $this->packageRow();

        $this->expectException(RuntimeException::class);
        app(ModulePackageService::class)->activate($module, null);
    }

    public function test_verify_and_record_license_persists_the_licence_columns(): void
    {
        $module = $this->packageRow();

        $result = app(ModulePackageService::class)
            ->verifyAndRecordLicense($module, 'demo-abcdefghijklmnop', null);

        $this->assertTrue($result['status']);

        $module->refresh();
        $this->assertSame('active', $module->license_status);
        $this->assertSame('custom', $module->license_driver);
        $this->assertNotNull($module->license_verified_at);
        $this->assertSame('demo-abcde', $module->license_key_prefix); // substr(key, 0, 10)
        $this->assertTrue(Hash::check('demo-abcdefghijklmnop', $module->license_key_hash));
        $this->assertSame('demo-abcdefghijklmnop', $module->license_key_encrypted); // encrypted cast round-trips
    }

    public function test_modules_index_activate_requires_a_key_then_activates(): void
    {
        $this->actingAsSuperAdmin();
        $module = $this->packageRow();

        Livewire::test(ModulesIndex::class)
            ->call('activate', $module->id)
            ->assertHasErrors('licenseKeys.'.$module->id);

        $this->assertFalse($module->fresh()->is_active);

        Livewire::test(ModulesIndex::class)
            ->set('licenseKeys.'.$module->id, 'demo-abcdefghijklmnop')
            ->call('activate', $module->id)
            ->assertHasNoErrors();

        $this->assertTrue($module->fresh()->is_active);
        $this->assertSame('active', $module->fresh()->license_status);
    }

    public function test_revalidate_license_deactivates_a_revoked_module(): void
    {
        PlatformSystem::set('license_server_url', 'https://license.test');
        PlatformSystem::set('allowed_registration_modes', json_encode(['retail', 'widgets']));

        Http::fake([
            'license.test/*' => Http::response(['status' => false, 'message' => 'revoked'], 200),
        ]);

        $this->actingAsSuperAdmin();
        $module = $this->packageRow([
            'is_active' => true,
            'license_status' => 'active',
            'license_key_encrypted' => 'PH-1234-5678-ABCD',
            'license_driver' => 'custom',
        ]);

        Livewire::test(ModulesIndex::class)->call('revalidateLicense', $module->id);

        $module->refresh();
        $this->assertFalse($module->is_active);
        $this->assertSame('revoked', $module->license_status);
        $this->assertNotContains('widgets', json_decode(PlatformSystem::get('allowed_registration_modes'), true));
    }

    public function test_buy_module_checkout_finalizes_a_purchase(): void
    {
        $this->actingAsSuperAdmin();
        $module = $this->packageRow([
            'features' => ['catalog' => ['price' => 49, 'currency' => 'USD', 'buy_enabled' => true]],
        ]);

        $this->app->bind(PlatformCheckoutService::class, fn () => new class extends PlatformCheckoutService
        {
            public function getEnabledGateways(): array
            {
                return ['razorpay' => ['gateway' => 'razorpay', 'mode' => 'test', 'public_key' => 'rzp_test', 'has_secret' => true]];
            }

            public function verifyRazorpayPayment(string $paymentId, string $orderId, string $signature): array
            {
                return ['verified' => true, 'payment_id' => $paymentId, 'order_id' => $orderId, 'amount' => 49.0, 'currency' => 'USD', 'method' => 'razorpay'];
            }
        });

        Livewire::test(Checkout::class, ['slug' => 'widgets'])
            ->call('verifyRazorpay', 'pay_abc', 'order_abc', 'sig')
            ->assertHasNoErrors();

        $module->refresh();
        $this->assertSame('active', $module->license_status);
        $this->assertSame('custom', $module->license_driver);
        $this->assertDatabaseHas('audit_logs', ['action' => 'module.purchased']);
        $this->assertNull(PlatformSystem::get('module_purchase_pending_widgets'));
    }

    public function test_checkout_keeps_the_payment_when_license_issuance_fails(): void
    {
        $this->actingAsSuperAdmin();
        PlatformSystem::set('license_server_url', 'https://license.test');
        Http::fake([
            'license.test/api/v1/license/issue' => Http::response(['status' => false, 'message' => 'no stock'], 200),
        ]);

        $module = $this->packageRow([
            'features' => ['catalog' => ['price' => 49, 'currency' => 'USD', 'buy_enabled' => true]],
        ]);

        $this->app->bind(PlatformCheckoutService::class, fn () => new class extends PlatformCheckoutService
        {
            public function getEnabledGateways(): array
            {
                return ['razorpay' => ['gateway' => 'razorpay', 'mode' => 'test', 'public_key' => 'k', 'has_secret' => true]];
            }

            public function verifyRazorpayPayment(string $paymentId, string $orderId, string $signature): array
            {
                return ['verified' => true, 'payment_id' => $paymentId, 'order_id' => $orderId, 'amount' => 49.0, 'currency' => 'USD', 'method' => 'razorpay'];
            }
        });

        Livewire::test(Checkout::class, ['slug' => 'widgets'])
            ->call('verifyRazorpay', 'pay_fail', 'order_fail', 'sig');

        $this->assertSame('unlicensed', $module->fresh()->license_status);
        $this->assertFalse($module->fresh()->is_active);
        $this->assertNotNull(PlatformSystem::get('module_purchase_pending_widgets'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'module.purchase_issue_failed']);
    }
}
