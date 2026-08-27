<?php

namespace App\Livewire\Tenant\Billing;

use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformBranding;
use App\Models\SubscriptionInvoice;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Payment\SubscriptionPaymentGatewayService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Store Subscription & Billing'])]
class Index extends Component
{
    public function mount(SubscriptionPaymentGatewayService $gatewayService, TenantProvisioningService $provisioner): void
    {
        $paymentId = (string) request()->query('payment_id', request()->query('collection_id', ''));
        if (request()->query('mp_status') !== 'success' || $paymentId === '') {
            return;
        }
        try {
            $company = $this->getCompany();
            $payment = $gatewayService->verifyMercadoPagoPayment($paymentId, $company);
            $plan = Plan::findOrFail($payment['metadata']['plan_name']);
            $expiresAt = $provisioner->calculateExpiry($plan->name);
            $company->update(['plan_name' => $plan->name, 'status' => 'active', 'expires_at' => $expiresAt]);
            $provisioner->createSubscriptionInvoice($company, $plan, 'mercadopago', ['user' => auth('web')->user(), 'activation_code' => $paymentId]);
            $this->successMessage = __('Mercado Pago payment approved. Your subscription is active.');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public string $activationCode = '';

    public string $emailRecipient = '';

    public ?string $selectedInvoiceIdForEmail = null;

    public bool $showEmailModal = false;

    // Checkout & Payment Modal
    public bool $showPaymentModal = false;

    public ?string $selectedPlanId = null;

    public string $paymentGateway = 'credit_card'; // credit_card | paypal | razorpay | mercadopago | activation_key

    public string $cardHolder = '';

    public string $cardNumber = '';

    public string $cardExpiry = '';

    public string $cardCvv = '';

    public string $paymentActivationCode = '';

    public string $paymentError = '';

    public string $successMessage = '';

    public string $errorMessage = '';

    public function redeemCode(TenantProvisioningService $provisioner): void
    {
        $this->reset(['successMessage', 'errorMessage']);

        $this->validate([
            'activationCode' => ['required', 'string', 'min:5'],
        ]);

        $company = $this->getCompany();
        if (! $company) {
            $this->errorMessage = 'Unable to resolve store company context.';

            return;
        }

        try {
            $res = $provisioner->redeemActivationCode($company, $this->activationCode, auth('web')->user());
            $this->activationCode = '';
            $this->successMessage = "🎉 License code activated successfully! Your plan is now {$res['plan_name']}.";
            session()->flash('status', $this->successMessage);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function selectPlanToUpgrade(string $planName, TenantProvisioningService $provisioner): void
    {
        $this->reset(['successMessage', 'errorMessage', 'paymentError']);

        $company = $this->getCompany();
        if (! $company) {
            return;
        }

        $plan = Plan::find($planName);
        if (! $plan) {
            $this->errorMessage = 'Selected subscription plan does not exist.';

            return;
        }

        // If plan is free trial ($0.00), activate directly without requiring payment
        if ((float) $plan->price <= 0) {
            try {
                $expiresAt = $provisioner->calculateExpiry($planName);
                $company->update([
                    'plan_name' => $planName,
                    'status' => 'active',
                    'expires_at' => $expiresAt,
                ]);

                $provisioner->createSubscriptionInvoice($company, $plan, 'free_trial', [
                    'user' => auth('web')->user(),
                ]);

                $this->successMessage = "🎉 Free evaluation trial activated for {$plan->display_name}!";
                session()->flash('status', $this->successMessage);
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();
            }

            return;
        }

        // For paid plans, open payment checkout modal requiring payment
        $this->selectedPlanId = $planName;
        $this->cardHolder = auth('web')->user()?->name ?? $company->name ?? '';
        $this->cardNumber = '';
        $this->cardExpiry = '';
        $this->cardCvv = '';
        $this->paymentActivationCode = '';
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->reset(['selectedPlanId', 'cardNumber', 'cardHolder', 'cardExpiry', 'cardCvv', 'paymentActivationCode', 'paymentError']);
    }

    public function initiateRazorpayPayment(SubscriptionPaymentGatewayService $gatewayService): void
    {
        $this->paymentError = '';

        $company = $this->getCompany();
        if (! $company) {
            $this->paymentError = 'Company context not found.';

            return;
        }

        $plan = Plan::find($this->selectedPlanId);
        if (! $plan) {
            $this->paymentError = 'Invalid plan selected.';

            return;
        }

        try {
            $basePrice = (float) $plan->price;
            $taxRate = 18.00;
            $totalAmount = $basePrice + round(($basePrice * $taxRate) / 100, 2);

            $orderData = $gatewayService->createRazorpayOrder(
                $plan,
                $totalAmount,
                $company->currency ?: 'USD',
                $company,
                auth('web')->user()
            );

            $this->dispatch('initiate-razorpay-checkout', ...$orderData);
        } catch (\Throwable $e) {
            $this->paymentError = $e->getMessage();
        }
    }

    public function verifyAndActivateRazorpayPayment(
        string $paymentId,
        string $orderId,
        string $signature,
        SubscriptionPaymentGatewayService $gatewayService,
        TenantProvisioningService $provisioner
    ): void {
        $this->paymentError = '';

        $company = $this->getCompany();
        if (! $company) {
            $this->paymentError = 'Store company context not found.';

            return;
        }

        $plan = Plan::find($this->selectedPlanId);
        if (! $plan) {
            $this->paymentError = 'Subscription plan not found.';

            return;
        }

        try {
            // 1. Verify cryptographic HMAC signature and confirm payment capture
            $verified = $gatewayService->verifyRazorpayPayment($paymentId, $orderId, $signature);

            // 2. Activate store subscription
            $expiresAt = $provisioner->calculateExpiry($plan->name);
            $company->update([
                'plan_name' => $plan->name,
                'status' => 'active',
                'expires_at' => $expiresAt,
            ]);

            // 3. Generate official paid tax invoice with actual Razorpay reference
            $invoice = $provisioner->createSubscriptionInvoice($company, $plan, 'razorpay', [
                'user' => auth('web')->user(),
                'activation_code' => $paymentId,
            ]);

            $this->showPaymentModal = false;
            $this->reset(['selectedPlanId', 'cardNumber', 'cardHolder', 'cardExpiry', 'cardCvv', 'paymentActivationCode', 'paymentError']);

            $this->successMessage = "🎉 Payment of \${$invoice->getFormattedTotal()} successfully processed via Razorpay (Ref #{$paymentId})! Your store is now active on the {$plan->display_name} plan until {$expiresAt->format('d M Y')}.";
            session()->flash('status', $this->successMessage);
        } catch (\Throwable $e) {
            $this->paymentError = 'Payment Verification Failed: '.$e->getMessage();
        }
    }

    public function initiateMercadoPagoPayment(SubscriptionPaymentGatewayService $gatewayService): void
    {
        $company = $this->getCompany();
        $plan = Plan::find($this->selectedPlanId);
        if (! $company || ! $plan) {
            $this->paymentError = 'Invalid company or subscription plan.';

            return;
        }
        try {
            $preference = $gatewayService->createMercadoPagoPreference($plan, (float) $plan->price * 1.18, $company->currency ?: 'USD', $company, auth('web')->user());
            $this->dispatch('redirect-to-mercadopago', url: $preference['checkout_url']);
        } catch (\Throwable $e) {
            $this->paymentError = $e->getMessage();
        }
    }

    public function processSubscriptionPayment(
        SubscriptionPaymentGatewayService $gatewayService,
        TenantProvisioningService $provisioner
    ): void {
        $this->paymentError = '';

        $company = $this->getCompany();
        if (! $company) {
            $this->paymentError = 'Company context not found.';

            return;
        }

        if (! $this->selectedPlanId) {
            $this->paymentError = 'Please select a subscription plan.';

            return;
        }

        $plan = Plan::find($this->selectedPlanId);
        if (! $plan) {
            $this->paymentError = 'Invalid subscription plan selected.';

            return;
        }

        // If Razorpay gateway is selected, create order and trigger modal
        if ($this->paymentGateway === 'razorpay') {
            $this->initiateRazorpayPayment($gatewayService);

            return;
        }

        if ($this->paymentGateway === 'mercadopago') {
            $this->initiateMercadoPagoPayment($gatewayService);

            return;
        }

        if ($this->paymentGateway === 'activation_key') {
            $this->validate([
                'paymentActivationCode' => ['required', 'string', 'min:5'],
            ]);

            try {
                $res = $provisioner->redeemActivationCode($company, $this->paymentActivationCode, auth('web')->user());
                $this->showPaymentModal = false;
                $this->successMessage = "🎉 License code verified and applied! Store is now on {$res['plan_name']} plan.";
                session()->flash('status', $this->successMessage);
            } catch (\Throwable $e) {
                $this->paymentError = $e->getMessage();
            }

            return;
        }

        // Online Gateway Card Payment
        if ($this->paymentGateway === 'credit_card') {
            $this->validate([
                'cardHolder' => ['required', 'string', 'min:2', 'max:100'],
                'cardNumber' => ['required', 'string', 'min:13', 'max:23'],
                'cardExpiry' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/?([0-9]{2})$/'],
                'cardCvv' => ['required', 'string', 'min:3', 'max:4'],
            ], [
                'cardExpiry.regex' => 'Expiration date must be in MM/YY format (e.g. 12/28).',
                'cardNumber.min' => 'Please enter a valid 16-digit card number.',
                'cardCvv.min' => 'CVV must be 3 or 4 digits.',
            ]);

            // Validate card expiration is not in the past
            $cleanExpiry = str_replace(['/', ' '], '', $this->cardExpiry);
            $expMonth = (int) substr($cleanExpiry, 0, 2);
            $expYear = (int) ('20'.substr($cleanExpiry, 2, 2));
            $currentYear = (int) date('Y');
            $currentMonth = (int) date('m');

            if ($expYear < $currentYear || ($expYear === $currentYear && $expMonth < $currentMonth)) {
                $this->paymentError = 'The provided payment card has expired. Please use a valid card.';

                return;
            }
        }

        // Process secure payment transaction through gateway service
        try {
            $basePrice = (float) $plan->price;
            $taxRate = 18.00;
            $totalAmount = $basePrice + round(($basePrice * $taxRate) / 100, 2);

            $paymentResult = $gatewayService->processCardPayment(
                $plan,
                $totalAmount,
                $company->currency ?: 'USD',
                [
                    'holder' => $this->cardHolder,
                    'number' => $this->cardNumber,
                    'expiry' => $this->cardExpiry,
                    'cvv' => $this->cardCvv,
                ],
                $company,
                auth('web')->user()
            );

            $paymentRef = $paymentResult['payment_reference'];
            $expiresAt = $provisioner->calculateExpiry($plan->name);

            // 1. Update company subscription and status
            $company->update([
                'plan_name' => $plan->name,
                'status' => 'active',
                'expires_at' => $expiresAt,
            ]);

            // 2. Generate paid official subscription tax invoice
            $invoice = $provisioner->createSubscriptionInvoice($company, $plan, $this->paymentGateway, [
                'user' => auth('web')->user(),
                'activation_code' => $paymentRef,
            ]);

            $this->showPaymentModal = false;
            $this->reset(['selectedPlanId', 'cardNumber', 'cardHolder', 'cardExpiry', 'cardCvv', 'paymentActivationCode', 'paymentError']);

            $this->successMessage = "🎉 Payment of \${$invoice->getFormattedTotal()} successfully processed via ".ucfirst(str_replace('_', ' ', $this->paymentGateway))." (Ref #{$paymentRef})! Your store is now active on the {$plan->display_name} plan until {$expiresAt->format('d M Y')}.";
            session()->flash('status', $this->successMessage);
        } catch (\Throwable $e) {
            $this->paymentError = 'Payment Processing Error: '.$e->getMessage();
        }
    }

    public function openSendEmailModal(string $invoiceId): void
    {
        $this->selectedInvoiceIdForEmail = $invoiceId;
        $this->emailRecipient = auth('web')->user()?->email ?: $this->getCompany()?->email ?: '';
        $this->showEmailModal = true;
    }

    public function sendInvoiceEmail(InvoiceDeliveryService $delivery): void
    {
        $this->validate([
            'emailRecipient' => ['required', 'email'],
        ]);

        $invoice = SubscriptionInvoice::where('company_id', $this->getCompanyId())->findOrFail($this->selectedInvoiceIdForEmail);
        $company = $this->getCompany();

        try {
            $smtp = $delivery->getSmtpConfig($company);

            Config::set('mail.mailers.tenant_sub_mailer', [
                'transport' => 'smtp',
                'host' => $smtp['host'],
                'port' => $smtp['port'],
                'encryption' => $smtp['encryption'],
                'username' => $smtp['username'],
                'password' => $smtp['password'],
                'timeout' => 15,
            ]);

            Mail::mailer('tenant_sub_mailer')->send('emails.subscription-invoice', [
                'invoice' => $invoice,
                'company' => $company,
            ], function ($message) use ($invoice, $company, $smtp) {
                $message->to($this->emailRecipient)
                    ->from($smtp['from_address'], $smtp['from_name'])
                    ->subject("Subscription Tax Invoice #{$invoice->invoice_number} - {$company->name}");
            });

            $this->showEmailModal = false;
            session()->flash('status', "✉️ Tax invoice #{$invoice->invoice_number} sent to {$this->emailRecipient}");
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to dispatch email: '.$e->getMessage();
        }
    }

    public function getWhatsAppUrl(string $invoiceId): string
    {
        $invoice = SubscriptionInvoice::where('company_id', $this->getCompanyId())->findOrFail($invoiceId);
        $company = $this->getCompany();

        $text = "🧾 *SUBSCRIPTION TAX INVOICE*\n"
            .'*Platform:* '.($invoice->seller_details['company_name'] ?? config('app.name'))."\n"
            ."*Invoice No:* #{$invoice->invoice_number}\n"
            ."*Store:* {$company->name}\n"
            ."*Plan:* {$invoice->plan_name} ({$invoice->billing_cycle})\n"
            ."*Invoice Date:* {$invoice->invoice_date->format('d M Y')}\n"
            ."----------------------------\n"
            ."*Base Amount:* \${$invoice->getFormattedSubtotal()}\n"
            ."*Tax (18% GST):* \${$invoice->getFormattedTax()}\n"
            ."*Total Amount Paid:* \${$invoice->getFormattedTotal()} {$invoice->currency}\n"
            .'*Status:* '.strtoupper($invoice->status).' (Paid via '.ucfirst(str_replace('_', ' ', $invoice->payment_method)).")\n"
            ."----------------------------\n"
            .'View / Download Official PDF: '.route('tenant.billing.invoices.pdf', $invoice)."\n\n"
            .'Thank you for partnering with '.config('app.name').'! ✨';

        return 'https://api.whatsapp.com/send?text='.rawurlencode($text);
    }

    protected function getCompany(): ?Company
    {
        $id = $this->getCompanyId();

        return $id ? Company::find($id) : null;
    }

    protected function getCompanyId(): ?string
    {
        return app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;
    }

    public function render(SubscriptionPaymentGatewayService $gatewayService)
    {
        $company = $this->getCompany();
        $invoices = $company
            ? $company->subscriptionInvoices()->orderByDesc('created_at')->get()
            : collect();

        $plans = Plan::where('active', true)->orderBy('price')->get();
        $branding = PlatformBranding::current();
        $enabledGateways = $gatewayService->getEnabledGateways();

        // Default to razorpay if enabled and gateway not set yet
        if ($this->paymentGateway === 'credit_card' && isset($enabledGateways['razorpay']) && ! isset($enabledGateways['stripe'])) {
            $this->paymentGateway = 'razorpay';
        }

        return view('livewire.tenant.billing.index', [
            'company' => $company,
            'invoices' => $invoices,
            'plans' => $plans,
            'branding' => $branding,
            'enabledGateways' => $enabledGateways,
        ]);
    }
}
