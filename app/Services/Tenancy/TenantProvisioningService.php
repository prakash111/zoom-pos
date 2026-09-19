<?php

namespace App\Services\Tenancy;

use App\Events\TenantRegistered;
use App\Models\ActivationCode;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlatformBranding;
use App\Models\PlatformSystem;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Localization\PlatformRegionalService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles complete multi-tenant provisioning, public self-registration,
 * subscription plan management, activation key redemption, and tax invoicing.
 */
class TenantProvisioningService
{
    /**
     * Public self-registration workflow for new tenants.
     */
    public function registerTenant(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $storeName = trim($data['store_name'] ?? $data['name'] ?? 'My Store');
            $slug = ! empty($data['slug'])
                ? Str::slug($data['slug'])
                : Str::slug($storeName).'-'.strtolower(Str::random(4));

            // Ensure unique slug
            if (Company::where('slug', $slug)->exists()) {
                $slug = Str::slug($storeName).'-'.strtolower(Str::random(6));
            }

            $posMode = $data['pos_mode'] ?? 'general';
            if (\App\Services\Modular\ModuleRegistry::isExtension($posMode)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'pos_mode' => 'Extensions cannot be selected as a registration operating mode.',
                ]);
            }
            $planName = $data['plan_name'] ?? 'trial';
            $activationCode = ! empty($data['activation_code']) ? trim($data['activation_code']) : null;
            $codeModel = null;

            // If activation code was supplied during registration, validate it
            if ($activationCode) {
                $codeModel = $this->findValidActivationCode($activationCode);
                if ($codeModel) {
                    $planName = $codeModel->plan_name ?: 'professional';
                }
            }

            $expiresAt = $codeModel && $codeModel->validity_days
                ? now()->addDays($codeModel->validity_days)
                : $this->calculateExpiry($planName);

            $customDomain = ! empty($data['custom_domain']) ? strtolower(trim(preg_replace('#^https?://#i', '', $data['custom_domain']))) : null;
            if ($customDomain && Company::where('custom_domain', $customDomain)->exists()) {
                $customDomain = null;
            }

            // Regional defaults from SuperAdmin or registration overrides
            $currency = ! empty($data['currency']) ? strtoupper(trim($data['currency'])) : PlatformRegionalService::defaultCurrency();
            $currencyInfo = PlatformRegionalService::getCurrencyDetails($currency);
            $language = ! empty($data['language']) ? strtolower(trim($data['language'])) : PlatformRegionalService::defaultLanguage();
            $timezone = ! empty($data['timezone']) ? trim($data['timezone']) : PlatformRegionalService::defaultTimezone();

            // 1. Create Company
            $company = Company::create([
                'name' => $storeName,
                'slug' => $slug,
                'custom_domain' => $customDomain,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'tax_id_label' => ! empty($data['tax_id']) ? 'GSTIN/Tax ID' : null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? 'US',
                'currency' => $currency,
                'currency_symbol' => $data['currency_symbol'] ?? ($currencyInfo['symbol'] ?? '$'),
                'currency_decimals' => isset($data['currency_decimals']) ? (int) $data['currency_decimals'] : ($currencyInfo['decimals'] ?? 2),
                'currency_symbol_position' => $data['currency_symbol_position'] ?? ($currencyInfo['position'] ?? 'prefix'),
                'language' => $language,
                'default_locale' => $data['default_locale'] ?? $language,
                'timezone' => $timezone,
                'pos_mode' => $posMode,
                'licensed_modules' => [$posMode === 'general' ? 'retail' : $posMode],
                'plan_name' => $planName,
                'status' => 'active',
                'expires_at' => $expiresAt,
                'tax_settings' => [
                    'tax_type' => 'GST',
                    'default_tax_rate' => 18,
                    'is_inclusive' => false,
                ],
            ]);

            // 1.1 Immediate Full-Menu Activation & Feature Population for Store Type
            try {
                app(\App\Services\Navigation\MenuService::class)->populateDefaultNavigation($company, $posMode);
            } catch (\Throwable $e) {
                Log::warning("Failed to auto-populate default navigation for tenant [{$company->id}]: ".$e->getMessage());
            }

            // 2. Create Default Payment Methods
            $defaults = [
                ['name' => 'Cash', 'code' => 'cash', 'order_index' => 1],
                ['name' => 'Card', 'code' => 'card', 'order_index' => 2],
                ['name' => 'Transfer', 'code' => 'transfer', 'order_index' => 3],
            ];
            foreach ($defaults as $pm) {
                PaymentMethod::create([
                    'company_id' => $company->id,
                    'name' => $pm['name'],
                    'code' => $pm['code'],
                    'order_index' => $pm['order_index'],
                    'is_active' => true,
                ]);
            }

            // 3. Create Initial Administrator User
            $ownerName = $data['owner_name'] ?? $data['admin_name'] ?? 'Business Owner';
            $adminEmail = $data['admin_email'] ?? $data['email'];
            $adminLogin = $data['admin_login'] ?? $data['login'] ?? $adminEmail;
            $adminPassword = $data['admin_password'] ?? $data['password'];

            $admin = User::create([
                'company_id' => $company->id,
                'name' => $ownerName,
                'login' => $adminLogin,
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'role' => User::ROLE_ADMINISTRATOR,
                'status' => 'approved',
                'locale' => $language,
                'email_verified_at' => now(),
            ]);

            // 4. Create Subscription Record
            $subscriptionOrigin = $codeModel ? 'codigo_ativacao_local' : ($planName === 'trial' ? 'trial' : 'checkout_direct');
            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_name' => $planName,
                'status' => 'active',
                'origin' => $subscriptionOrigin,
                'started_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            // Mark activation code as used if provided
            if ($codeModel) {
                $codeModel->increment('current_uses');
                AuditLog::record('activation_code.redeemed', $company->id, $admin->id, [
                    'code_id' => $codeModel->id,
                    'plan' => $planName,
                ]);
            }

            // 5. Generate Compliant Subscription Tax Invoice
            $paymentMethod = $codeModel ? 'activation_key' : ($planName === 'trial' ? 'free_trial' : 'credit_card');
            $invoice = $this->createSubscriptionInvoice($company, $planName, $paymentMethod, [
                'subscription_id' => $subscription->id,
                'user' => $admin,
                'activation_code' => $activationCode,
            ]);

            // 6. Automatically Import Default Demo Data tailored to the operating
            // mode — gated by the SuperAdmin-wide "Auto-Seed Demo Data on Signup"
            // toggle (Platform Settings ▸ General). When that platform toggle is
            // off, no new tenant is ever seeded, regardless of what the request
            // itself asked for; when it's on (the default), the existing
            // per-request opt-out still applies.
            $platformAllowsSeeding = filter_var(PlatformSystem::get('auto_seed_demo_data_on_registration', true), FILTER_VALIDATE_BOOLEAN);
            $requestWantsSeeding = ! array_key_exists('seed_demo_data', $data) || ! empty($data['seed_demo_data']);
            $shouldSeed = $platformAllowsSeeding && $requestWantsSeeding;
            if ($shouldSeed) {
                if (app()->environment('testing') || ! empty($data['sync_seed'])) {
                    $this->seedTenantDemoData($company, $posMode, $admin);
                } else {
                    event(new TenantRegistered($company, $admin, $posMode));
                }
            } elseif (! $platformAllowsSeeding) {
                // The platform-wide toggle is off — leave this tenant clean
                // *permanently*, not merely deferred: mark seeding "handled"
                // now so a later bootstrap call never lazily seeds it either
                // (a per-request opt-out with the platform toggle still on
                // is left as-is — that pre-existing behavior is unchanged).
                $company->update(['is_seeding_complete' => true]);
            }

            AuditLog::record('tenant.self_registered', $company->id, $admin->id, [
                'store_name' => $company->name,
                'plan' => $planName,
                'mode' => $posMode,
            ]);

            return [
                'company' => $company->fresh(),
                'user' => $admin->fresh(),
                'subscription' => $subscription->fresh(),
                'invoice' => $invoice->fresh(),
            ];
        });
    }

    /**
     * Automatically seed demo data for a newly registered tenant based on active POS mode.
     */
    public function seedTenantDemoData(Company $company, string $posMode = 'general', ?User $admin = null): void
    {
        try {
            app(TenantSampleDataService::class)->seed($company, $posMode, $admin);
        } catch (\Throwable $e) {
            Log::warning("Failed to auto-seed demo data for tenant [{$company->id}]: ".$e->getMessage());
        }
    }

    /**
     * Standard SuperAdmin Tenant Creation.
     */
    public function create(array $data): Company
    {
        $res = $this->registerTenant([
            'store_name' => $data['name'],
            'owner_name' => $data['admin_name'],
            'admin_login' => $data['admin_login'] ?? $data['login'] ?? null,
            'email' => $data['admin_email'] ?? $data['email'],
            'admin_email' => $data['admin_email'] ?? $data['email'],
            'password' => $data['admin_password'] ?? $data['password'],
            'admin_password' => $data['admin_password'] ?? $data['password'],
            'phone' => $data['phone'] ?? null,
            'country' => $data['country'] ?? 'US',
            'currency' => $data['currency'] ?? 'USD',
            'language' => $data['language'] ?? 'en',
            'plan_name' => $data['plan_name'] ?? 'professional',
            'pos_mode' => $data['pos_mode'] ?? 'general',
            'tax_id' => $data['tax_id'] ?? null,
            'max_users' => $data['max_users'] ?? null,
            'max_devices' => $data['max_devices'] ?? null,
        ]);

        return $res['company'];
    }

    /**
     * Redeem an activation key for an existing company.
     */
    public function redeemActivationCode(Company $company, string $rawCode, ?User $user = null): array
    {
        $codeModel = $this->findValidActivationCode($rawCode);
        if (! $codeModel) {
            throw new \InvalidArgumentException('Invalid, expired, or already revoked activation code.');
        }

        return DB::transaction(function () use ($company, $codeModel, $rawCode, $user) {
            $planName = $codeModel->plan_name ?: 'professional';

            // Calculate new expiry: extend from current if not expired, or from now
            $currentExpires = $company->expires_at;
            $baseTime = ($currentExpires && $currentExpires->isFuture()) ? $currentExpires : now();

            $newExpires = $codeModel->validity_days
                ? $baseTime->copy()->addDays($codeModel->validity_days)
                : null; // Lifetime

            $company->update([
                'plan_name' => $planName,
                'status' => 'active',
                'expires_at' => $newExpires,
            ]);

            $codeModel->increment('current_uses');

            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_name' => $planName,
                'status' => 'active',
                'origin' => 'codigo_ativacao_local',
                'started_at' => now(),
                'expires_at' => $newExpires,
            ]);

            $invoice = $this->createSubscriptionInvoice($company, $planName, 'activation_key', [
                'subscription_id' => $subscription->id,
                'user' => $user,
                'activation_code' => $rawCode,
            ]);

            AuditLog::record('activation_code.redeemed', $company->id, $user?->id, [
                'code_id' => $codeModel->id,
                'plan' => $planName,
                'validity_days' => $codeModel->validity_days,
            ]);

            return [
                'success' => true,
                'company' => $company->fresh(),
                'subscription' => $subscription,
                'invoice' => $invoice,
                'plan_name' => $planName,
                'expires_at' => $newExpires,
            ];
        });
    }

    /**
     * Generate a fully compliant tax invoice for a tenant subscription.
     */
    public function createSubscriptionInvoice(Company $company, Plan|string $plan, string $paymentMethod = 'free_trial', array $options = []): SubscriptionInvoice
    {
        $planModel = is_string($plan) ? Plan::find($plan) : $plan;
        $planName = $planModel?->display_name ?? (is_string($plan) ? ucfirst($plan) : 'Subscription');
        $billingCycle = $planModel?->billing_cycle ?? 'monthly';
        $basePrice = (float) ($planModel?->price ?? 0.00);

        // Tax calculation: default 18% GST (or 0% for free trial)
        $taxRate = $basePrice > 0 ? 18.00 : 0.00;
        $taxAmount = round(($basePrice * $taxRate) / 100, 2);
        $total = $basePrice + $taxAmount;

        $cgst = round($taxAmount / 2, 2);
        $sgst = round($taxAmount - $cgst, 2);

        $taxBreakdown = [
            'taxable_amount' => $basePrice,
            'tax_rate_total' => $taxRate,
            'cgst_rate' => 9.00,
            'cgst_amount' => $cgst,
            'sgst_rate' => 9.00,
            'sgst_amount' => $sgst,
            'igst_rate' => 0.00,
            'igst_amount' => 0.00,
        ];

        $branding = PlatformBranding::current();

        $sellerDetails = [
            'company_name' => $branding?->platform_name ?? 'Smart Inventory & POS SaaS',
            'legal_name' => $branding?->platform_name ?? 'Smart Inventory Platform Inc.',
            'tax_id' => 'GSTIN-PLATFORM-2026-991A',
            'support_email' => $branding?->support_email ?? 'support@example.com',
            'support_phone' => $branding?->support_phone ?? '+1 (800) 555-0199',
            'address' => '100 Innovation Blvd, Suite 400',
            'city' => 'San Francisco',
            'state' => 'CA',
            'country' => 'US',
        ];

        $buyerDetails = [
            'store_name' => $company->name,
            'contact_name' => $options['user']?->name ?? $company->users()->first()?->name ?? 'Business Admin',
            'email' => $options['user']?->email ?? $company->email,
            'phone' => $company->phone,
            'tax_id' => $company->tax_id,
            'address' => $company->address,
            'city' => $company->city,
            'state' => $company->state,
            'country' => $company->country,
        ];

        $notes = match ($paymentMethod) {
            'activation_key' => 'Redeemed via Activation / License Code: '.($options['activation_code'] ?? 'AGY-LICENSE'),
            'free_trial' => '14-Day Free Evaluation Trial License (No Charge).',
            'credit_card' => 'Online Subscription Payment via Credit Card / Payment Gateway.',
            default => 'Subscription Payment',
        };

        $attempts = 0;
        while ($attempts < 10) {
            $attempts++;
            $invoiceNumber = $this->generateNextInvoiceNumber();

            try {
                return SubscriptionInvoice::create([
                    'company_id' => $company->id,
                    'subscription_id' => $options['subscription_id'] ?? null,
                    'invoice_number' => $invoiceNumber,
                    'plan_name' => $planName,
                    'billing_cycle' => $billingCycle,
                    'currency' => $company->currency ?: 'USD',
                    'subtotal' => $basePrice,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'tax_type' => 'GST',
                    'tax_breakdown' => $taxBreakdown,
                    'total' => $total,
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $options['activation_code'] ?? strtoupper(Str::random(10)),
                    'status' => 'paid',
                    'invoice_date' => now()->toDateString(),
                    'due_date' => now()->toDateString(),
                    'paid_at' => now(),
                    'seller_details' => $sellerDetails,
                    'buyer_details' => $buyerDetails,
                    'notes' => $notes,
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                    continue;
                }
                throw $e;
            }
        }

        // Final fallback with microtime entropy to guarantee uniqueness
        $fallbackNumber = 'INV-SUB-'.date('Y').'-'.strtoupper(substr(md5(uniqid('', true)), 0, 8));

        return SubscriptionInvoice::create([
            'company_id' => $company->id,
            'subscription_id' => $options['subscription_id'] ?? null,
            'invoice_number' => $fallbackNumber,
            'plan_name' => $planName,
            'billing_cycle' => $billingCycle,
            'currency' => $company->currency ?: 'USD',
            'subtotal' => $basePrice,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'tax_type' => 'GST',
            'tax_breakdown' => $taxBreakdown,
            'total' => $total,
            'payment_method' => $paymentMethod,
            'payment_reference' => $options['activation_code'] ?? strtoupper(Str::random(10)),
            'status' => 'paid',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'paid_at' => now(),
            'seller_details' => $sellerDetails,
            'buyer_details' => $buyerDetails,
            'notes' => $notes,
        ]);
    }

    /**
     * Generate the next sequence-based invoice number with collision avoidance.
     */
    public function generateNextInvoiceNumber(): string
    {
        $year = date('Y');
        $prefix = "INV-SUB-{$year}-";

        $existingNumbers = SubscriptionInvoice::where('invoice_number', 'LIKE', "{$prefix}%")
            ->pluck('invoice_number')
            ->toArray();

        $maxSequence = 0;
        foreach ($existingNumbers as $num) {
            if (preg_match('/'.preg_quote($prefix, '/').'(\d+)/', $num, $matches)) {
                $seq = (int) $matches[1];
                if ($seq > $maxSequence) {
                    $maxSequence = $seq;
                }
            }
        }

        $nextSeq = $maxSequence + 1;
        $candidate = $prefix.sprintf('%04d', $nextSeq);

        while (SubscriptionInvoice::where('invoice_number', $candidate)->exists()) {
            $nextSeq++;
            $candidate = $prefix.sprintf('%04d', $nextSeq);
        }

        return $candidate;
    }

    /**
     * Find and validate a raw activation code.
     */
    protected function findValidActivationCode(string $rawCode): ?ActivationCode
    {
        $clean = strtoupper(trim($rawCode));
        $prefix = substr($clean, 0, 7);

        $candidates = ActivationCode::where('code_prefix', $prefix)
            ->where('revoked', false)
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($clean, $candidate->code_hash)) {
                // Check if expired
                if ($candidate->expires_at && $candidate->expires_at->isPast()) {
                    continue;
                }
                // Check uses
                if ($candidate->max_uses !== null && $candidate->current_uses >= $candidate->max_uses) {
                    continue;
                }

                return $candidate;
            }
        }

        return null;
    }

    /**
     * Activate a plan on a company and generate its invoice — the shared
     * tail end of every "plan becomes active" path (free-trial activation,
     * activation-code redemption, and each payment gateway's verified
     * purchase), previously duplicated inline at each call site.
     */
    public function activatePlan(Company $company, Plan $plan, string $paymentMethod, array $options = []): SubscriptionInvoice
    {
        $expiresAt = $this->calculateExpiry($plan->name);
        $company->update([
            'plan_name' => $plan->name,
            'status' => 'active',
            'expires_at' => $expiresAt,
        ]);

        return $this->createSubscriptionInvoice($company, $plan, $paymentMethod, $options);
    }

    /**
     * Calculate expiry date from plan duration.
     */
    public function calculateExpiry(?string $planName): ?Carbon
    {
        if (! $planName) {
            return null;
        }

        $plan = Plan::find($planName);
        if ($plan && $plan->duration_days) {
            return now()->addDays($plan->duration_days);
        }

        $name = strtolower($planName);

        return match (true) {
            str_contains($name, 'lifetime') || str_contains($name, 'perpetu') => null,
            str_contains($name, 'trial') || str_contains($name, 'free') => now()->addDays(14),
            str_contains($name, 'quarter') => now()->addDays(90),
            str_contains($name, 'annual') || str_contains($name, 'year')
                || str_contains($name, 'enterprise') || str_contains($name, 'professional') => now()->addDays(365),
            default => now()->addDays(30),
        };
    }
}
