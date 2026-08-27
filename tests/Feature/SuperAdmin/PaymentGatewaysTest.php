<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\PaymentGateways\Index;
use App\Models\PaymentGatewaySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class PaymentGatewaysTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    public function test_non_super_admin_role_is_blocked(): void
    {
        $this->actingAsSupportAdmin();

        // mount()'s abort_unless(403) is rendered as a real error page by
        // Livewire's testing harness rather than propagating as a thrown
        // exception here — assert on that rendered response instead.
        Livewire::test(Index::class)->assertSee('403');
    }

    public function test_super_admin_can_save_and_secret_is_encrypted_at_rest(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Index::class)
            ->set('gateways.stripe.enabled', true)
            ->set('gateways.stripe.mode', 'test')
            ->set('gateways.stripe.public_key', 'pk_test_123')
            ->set('gateways.stripe.secret_key', 'sk_test_supersecret')
            ->call('save');

        $setting = PaymentGatewaySetting::where('gateway', 'stripe')->firstOrFail();
        $this->assertTrue($setting->enabled);
        $this->assertSame('sk_test_supersecret', $setting->secret_key); // decrypted via cast
        $this->assertStringNotContainsString('sk_test_supersecret', (string) $setting->getRawOriginal('secret_key'));
    }

    public function test_blank_secret_on_save_keeps_the_previously_stored_one(): void
    {
        $this->actingAsSuperAdmin();
        PaymentGatewaySetting::create(['gateway' => 'stripe', 'enabled' => true, 'mode' => 'test', 'secret_key' => 'sk_original']);

        Livewire::test(Index::class)
            ->set('gateways.stripe.public_key', 'pk_updated')
            ->call('save');

        $setting = PaymentGatewaySetting::where('gateway', 'stripe')->firstOrFail();
        $this->assertSame('sk_original', $setting->secret_key);
        $this->assertSame('pk_updated', $setting->public_key);
    }

    public function test_payment_gateway_screen_does_not_show_social_login_configuration(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Index::class)
            ->assertDontSee('Social Login')
            ->assertDontSee('Callback URL');
    }
}
