<?php

namespace App\Livewire\SuperAdmin\PaymentGateways;

use App\Models\AuditLog;
use App\Models\PaymentGatewaySetting;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Gateway credentials belong to the platform operator (collecting
 * subscription payments from tenants) — real Stripe/PayPal/Razorpay
 * integration is Milestone 4; this section stores/masks the settings.
 * Mutating this is super_admin-only, matching the legacy privilege split.
 */
#[Layout('layouts.superadmin', ['title' => 'Payment Gateways'])]
class Index extends Component
{
    public array $gateways = [
        'stripe' => ['enabled' => false, 'mode' => 'test', 'public_key' => '', 'secret_key' => ''],
        'paypal' => ['enabled' => false, 'mode' => 'test', 'public_key' => '', 'secret_key' => ''],
        'razorpay' => ['enabled' => false, 'mode' => 'test', 'public_key' => '', 'secret_key' => ''],
        'mercadopago' => ['enabled' => false, 'mode' => 'test', 'public_key' => '', 'secret_key' => ''],
    ];

    protected array $hasStoredSecret = ['stripe' => false, 'paypal' => false, 'razorpay' => false, 'mercadopago' => false];

    public function mount(): void
    {
        abort_unless(auth('platform_web')->user()->hasRole('super_admin'), 403);

        foreach (PaymentGatewaySetting::all() as $setting) {
            if (! isset($this->gateways[$setting->gateway])) {
                continue;
            }
            $this->gateways[$setting->gateway] = [
                'enabled' => $setting->enabled,
                'mode' => $setting->mode,
                'public_key' => (string) $setting->public_key,
                'secret_key' => '',
            ];
            $this->hasStoredSecret[$setting->gateway] = filled($setting->secret_key);
        }
    }

    public function hasStoredSecret(string $gateway): bool
    {
        return $this->hasStoredSecret[$gateway] ?? false;
    }

    public function save(): void
    {
        foreach ($this->gateways as $gateway => $config) {
            $setting = PaymentGatewaySetting::firstOrNew(['gateway' => $gateway]);
            $setting->enabled = (bool) $config['enabled'];
            $setting->mode = $config['mode'];
            $setting->public_key = $config['public_key'] ?: null;
            if (filled($config['secret_key'])) {
                $setting->secret_key = $config['secret_key'];
            }
            $setting->save();
        }
        AuditLog::record('payment_gateways.updated', null, auth('platform_web')->id());
        session()->flash('status', 'Payment gateway settings saved.');
        $this->mount();
    }

    public function render()
    {
        return view('livewire.superadmin.payment-gateways.index');
    }
}
