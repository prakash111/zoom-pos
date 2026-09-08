<?php

namespace App\Livewire\SuperAdmin\Modules;

use App\Models\AuditLog;
use App\Models\PlatformSystem;
use App\Models\SduiModule;
use App\Services\License\LicenseService;
use App\Services\Modular\ModuleCatalog;
use App\Services\Modular\ModulePackageService;
use App\Services\Payment\PlatformCheckoutService;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.superadmin', ['title' => 'Buy Module'])]
class Checkout extends Component
{
    public string $slug = '';

    public ?SduiModule $module = null;

    public float $price = 0.0;

    public string $currency = 'USD';

    public bool $buyEnabled = false;

    public bool $owned = false;

    /** @var array<string, mixed> */
    public array $gateways = [];

    public string $selectedGateway = '';

    public function mount(string $slug): void
    {
        abort_unless(auth('platform_web')->user()?->hasRole('super_admin'), 403);

        $this->module = SduiModule::query()->where('slug', $slug)->where('source_type', 'package')->firstOrFail();
        $this->slug = $this->module->slug;

        $catalog = ModuleCatalog::for($this->slug);
        $this->price = $catalog['price'];
        $this->currency = $catalog['currency'];
        $this->buyEnabled = $catalog['buy_enabled'];
        $this->owned = $this->module->requires_license && $this->module->license_status === 'active';

        $this->gateways = app(PlatformCheckoutService::class)->getEnabledGateways();
        $this->selectedGateway = array_key_first($this->gateways) ?: '';
    }

    public function startRazorpay(PlatformCheckoutService $checkout): void
    {
        $this->guardBuyable();

        try {
            $order = $checkout->createRazorpayOrder($this->price, $this->currency, [
                'purpose' => 'module_purchase',
                'module_slug' => $this->slug,
                'buyer_email' => auth('platform_web')->user()?->email,
            ]);
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->dispatch('module-razorpay-checkout', [
            'key_id' => $order['key_id'],
            'order_id' => $order['order_id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'name' => config('app.name'),
            'description' => 'Module: '.$this->module->name,
        ]);
    }

    public function verifyRazorpay(string $paymentId, string $orderId, string $signature, PlatformCheckoutService $checkout, ModulePackageService $packages): void
    {
        $this->guardBuyable();

        try {
            $payment = $checkout->verifyRazorpayPayment($paymentId, $orderId, $signature);
        } catch (Throwable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        if ($this->price > $payment['amount'] + 0.01) {
            session()->flash('error', 'Paid amount is lower than the module price. Contact support with reference '.$paymentId.'.');

            return;
        }

        $this->finalizePurchase('razorpay', $paymentId, $payment['amount'], $payment['currency'], $packages);
    }

    private function finalizePurchase(string $gateway, string $reference, float $amount, string $currency, ModulePackageService $packages): void
    {
        $module = $this->module->fresh();
        $adminId = auth('platform_web')->id();
        $domain = LicenseService::currentDomain();
        $catalog = ModuleCatalog::for($this->slug);

        $issue = app(LicenseService::class)->issue([
            'gateway' => $gateway,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'payer_email' => auth('platform_web')->user()?->email,
        ], $this->slug, $domain, $catalog['buy_item_id']);

        if (! $issue['status'] || blank($issue['license_key'])) {
            PlatformSystem::set('module_purchase_pending_'.$this->slug, json_encode([
                'gateway' => $gateway,
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'at' => now()->toIso8601String(),
                'error' => $issue['message'],
            ]));
            AuditLog::record('module.purchase_issue_failed', null, $adminId, [
                'slug' => $this->slug,
                'gateway' => $gateway,
                'reference' => $reference,
            ]);
            session()->flash('error', "Payment succeeded but license issuance failed. Reference {$reference} — contact support. ({$issue['message']})");

            return;
        }

        $recorded = $packages->verifyAndRecordLicense($module, $issue['license_key'], $adminId);
        if (! $recorded['status']) {
            PlatformSystem::set('module_purchase_pending_'.$this->slug, json_encode([
                'gateway' => $gateway,
                'reference' => $reference,
                'license_key' => $issue['license_key'],
                'at' => now()->toIso8601String(),
                'error' => $recorded['message'],
            ]));
            session()->flash('error', "License issued but could not be verified back: {$recorded['message']} Reference {$reference}.");

            return;
        }

        PlatformSystem::query()->where('key', 'module_purchase_pending_'.$this->slug)->delete();
        $module->refresh();

        AuditLog::record('module.purchased', null, $adminId, [
            'slug' => $this->slug,
            'gateway' => $gateway,
            'reference' => $reference,
            'expires_at' => $recorded['expires_at'],
        ]);

        $filesPresent = $module->package_path
            && File::exists(base_path('modules/'.$module->package_path.'/module.json'));

        if ($filesPresent) {
            try {
                $packages->activate($module, $adminId);
                session()->flash('status', "\"{$module->name}\" purchased and activated.");
            } catch (Throwable $e) {
                session()->flash('status', "\"{$module->name}\" is licensed. Activation failed: ".$e->getMessage());
            }
        } else {
            session()->flash('status', "\"{$module->name}\" is licensed. Upload its module ZIP on the Modules screen to finish installing.");
        }

        $this->redirectRoute('superadmin.modules.index', navigate: true);
    }

    private function guardBuyable(): void
    {
        abort_unless(auth('platform_web')->user()?->hasRole('super_admin'), 403);

        if ($this->owned) {
            abort(409, 'Module is already licensed.');
        }
        if (! $this->buyEnabled || $this->price <= 0) {
            abort(422, 'This module is not available for purchase.');
        }
    }

    public function render()
    {
        return view('livewire.superadmin.modules.checkout');
    }
}
