<?php

namespace App\Livewire\Tenant\Settings;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Configuration;
use App\Models\PaymentMethod;
use App\Models\TaxRule;
use App\Models\TenantApiKey;
use App\Services\Auth\PermissionChecker;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Navigation\TenantNavRegistry;
use App\Services\TaxCalculationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.tenant', ['title' => 'Settings'])]
class Index extends Component
{
    use WithFileUploads;

    public Company $company;

    public string $name = '';

    public string $slug = '';

    public string $customDomain = '';

    public string $tradeName = '';

    public string $taxId = '';

    public string $email = '';

    public string $phone = '';

    public string $website = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $postalCode = '';

    public string $country = 'US';

    public string $currency = 'USD';

    public string $currencySymbol = '$';

    public int $currencyDecimals = 2;

    public string $currencySymbolPosition = 'prefix'; // prefix | suffix

    /** @var array<int, array{code: string, name: string, symbol: string, exchange_rate: float}> */
    public array $otherCurrencies = [];

    public string $invoicePrefix = 'INV-';

    public string $quotationPrefix = 'QT-';

    public string $invoiceTerms = '';

    public string $quoteTerms = '';

    public string $bankDetails = '';

    // Branding Settings
    public $logoFile = null;

    public $faviconFile = null;

    public $drawerCoverFile = null;

    public $newLogo;

    public $newFavicon;

    public string $logo = '';

    public string $favicon = '';

    public string $drawerCover = '';

    public string $primaryColor = '#2d7a58';

    public string $themeColor = 'blue';

    public string $posLayout = 'standard';

    public string $receiptFormat = '80mm';

    public float $defaultCommissionRate = 0.0;

    public string $defaultCommissionType = 'percentage';

    public bool $enableConsignments = true;

    /// Read-only display of the tenant's operating mode — set once at
    /// registration and only ever changeable by a superadmin (via the
    /// `restaurant_mode_locked` override on the tenant's Company record).
    /// This property is hydrated in mount() but never written back to the
    /// database from this component.
    public string $posMode = 'general';

    // SMTP / Mail Configuration State
    public string $smtpHost = '';

    public ?int $smtpPort = 587;

    public string $smtpUsername = '';

    public string $smtpPassword = '';

    public string $smtpEncryption = 'tls';

    public string $smtpFromAddress = '';

    public string $smtpFromName = '';

    public bool $hasStoredSmtpPassword = false;

    public string $testEmailTo = '';

    // WhatsApp / Channel Settings
    public string $whatsappPhonePrefix = '';

    public string $whatsappCustomNote = '';

    // Restaurant / KDS order alert settings
    public int $restaurantAlertIntervalMinutes = 3;

    public string $restaurantAlertSoundPreset = 'chime';

    public string $restaurantAlertSoundUrl = '';

    // WhatsApp Cloud API (real automated sending — leave blank to keep the
    // wa.me manual-link fallback used when no credentials are configured)
    public string $whatsappPhoneNumberId = '';

    public string $whatsappApiToken = '';

    public bool $hasWhatsappApiToken = false;

    // Custom Notification Channels (Settings > Notifications > Custom Notification Channels)
    public bool $showChannelModal = false;

    public ?int $editingChannelId = null;

    public string $channelName = '';

    public string $channelIcon = 'webhook';

    public string $channelUrl = '';

    public string $channelMethod = 'POST';

    public string $channelHeaders = '';

    public string $channelAuthType = 'none';

    public string $channelAuthValue = '';

    public string $channelPayloadTemplate = '';

    /** @var array<int, string> */
    public array $channelEventTypes = [];

    public bool $channelIsActive = true;

    // Payment Methods Management Modal State
    public bool $showPaymentMethodModal = false;

    public ?string $editingPaymentMethodId = null;

    public string $pmName = '';

    public string $pmCode = '';

    public string $pmDescription = '';

    public bool $pmIsActive = true;

    public int $pmOrderIndex = 0;

    public string $pmBankName = '';

    public string $pmAccountNo = '';

    public string $pmIfscCode = '';

    public string $pmUpiId = '';

    public string $pmHolderName = '';

    // PIX & Merchant Fees & Scale Configuration
    public string $pixKeyType = 'cpf_cnpj'; // cpf_cnpj, email, phone, random

    public string $pixKey = '';

    public string $pixMerchantName = '';

    public string $pixMerchantCity = '';

    public string $pixQrImage = '';

    public $newPixQrImage = null;

    public float $cardFeeDebit = 1.50;

    public float $cardFeeCredit1x = 3.20;

    public float $cardFeeCreditInstallments = 4.50;

    public string $barcodeScalePrefix = '2';

    public string $barcodeScaleType = 'weight'; // weight | price

    // Tax Rules Management State
    public bool $showTaxRuleModal = false;

    public ?string $editingTaxRuleId = null;

    public string $taxRuleName = '';

    public string $taxRuleCode = '';

    public float $taxRuleRate = 0.0;

    public string $taxRuleType = 'percentage';

    public bool $taxRuleIsInclusive = false;

    public bool $taxRuleIsCompound = false;

    public bool $taxRuleIsDefault = false;

    public string $taxRuleDescription = '';

    /** @var array<int, array{name: string, rate: float, code?: string}> */
    public array $taxRuleSubComponents = [];

    // API Keys Management State
    public bool $showApiKeyModal = false;

    public string $newApiKeyName = '';

    public array $newApiKeyPermissions = ['tax:calculate', 'tax:invoices'];

    public ?string $recentlyGeneratedToken = null;

    public string $defaultAiProvider = 'openai';

    public string $openaiModel = 'dall-e-3';

    public string $geminiModel = 'imagen-3.0-generate-002';

    public string $claudeModel = 'claude-3-5-sonnet-20241022';

    public string $openaiApiKey = '';

    public string $geminiApiKey = '';

    public string $claudeApiKey = '';

    public bool $hasOpenaiApiKey = false;

    public bool $hasGeminiApiKey = false;

    public bool $hasClaudeApiKey = false;

    public string $activeSection = 'overview';

    public function mount(): void
    {
        $routeName = request()->route()?->getName() ?? '';
        if (str_ends_with($routeName, '.mode') || request()->query('section') === 'mode') {
            $this->activeSection = 'mode';
        } elseif (str_ends_with($routeName, '.profile') || request()->query('section') === 'profile') {
            $this->activeSection = 'profile';
        } elseif (str_ends_with($routeName, '.receipts') || request()->query('section') === 'receipts') {
            $this->activeSection = 'receipts';
        } elseif (str_ends_with($routeName, '.financial') || request()->query('section') === 'financial') {
            $this->activeSection = 'financial';
        } elseif (str_ends_with($routeName, '.taxes') || request()->query('section') === 'taxes') {
            $this->activeSection = 'taxes';
        } elseif (str_ends_with($routeName, '.api') || request()->query('section') === 'api') {
            $this->activeSection = 'api';
        } elseif (str_ends_with($routeName, '.navigation') || request()->query('section') === 'navigation') {
            $this->activeSection = 'navigation';
        } else {
            $this->activeSection = request()->query('section', 'overview');
            if (! in_array($this->activeSection, ['overview', 'mode', 'profile', 'receipts', 'financial', 'taxes', 'api', 'navigation'])) {
                $this->activeSection = 'overview';
            }
        }

        $this->company = auth('web')->user()->company;
        $this->name = $this->company->name;
        $this->slug = (string) ($this->company->slug ?? '');
        $this->customDomain = (string) ($this->company->custom_domain ?? '');
        $this->tradeName = (string) $this->company->trade_name;
        $this->taxId = (string) $this->company->tax_id;
        $this->email = (string) $this->company->email;
        $this->phone = (string) $this->company->phone;
        $this->website = (string) ($this->company->website ?? '');
        $this->address = (string) $this->company->address;
        $this->city = (string) $this->company->city;
        $this->state = (string) $this->company->state;
        $this->postalCode = (string) $this->company->postal_code;
        $this->country = $this->company->country;
        $this->currency = $this->company->currency;
        $this->currencySymbol = (string) ($this->company->currency_symbol ?: '$');
        $this->currencyDecimals = (int) ($this->company->currency_decimals ?? 2);
        $this->currencySymbolPosition = (string) ($this->company->currency_symbol_position ?: 'prefix');
        $this->otherCurrencies = $this->company->other_currencies ?: [];
        $this->invoicePrefix = (string) $this->company->invoice_prefix;
        $this->quotationPrefix = (string) $this->company->quotation_prefix;
        $this->invoiceTerms = (string) $this->company->invoice_terms;
        $this->quoteTerms = (string) $this->company->quote_terms;
        $this->bankDetails = (string) $this->company->bank_details;

        // Branding
        $this->logo = (string) ($this->company->logo ?? '');
        $this->favicon = (string) ($this->company->favicon ?? '');
        $this->drawerCover = (string) ($this->company->drawer_cover ?? '');
        $this->primaryColor = (string) ($this->company->primary_color ?: '#2d7a58');
        $this->themeColor = (string) ($this->company->theme_color ?: 'blue');
        $this->posLayout = (string) ($this->company->pos_layout ?: 'standard');
        $this->receiptFormat = (string) ($this->company->receipt_format ?: '80mm');
        $this->defaultCommissionRate = (float) ($this->company->default_commission_rate ?? 0);
        $this->defaultCommissionType = (string) ($this->company->default_commission_type ?: 'percentage');
        $this->enableConsignments = (bool) ($this->company->enable_consignments ?? true);
        $this->posMode = (string) ($this->company->pos_mode ?: 'general');

        // PIX & Card Fees & Scale
        $this->pixKeyType = (string) ($this->company->pix_key_type ?: 'cpf_cnpj');
        $this->pixKey = (string) ($this->company->pix_key ?: '');
        $this->pixMerchantName = (string) ($this->company->pix_merchant_name ?: $this->company->name);
        $this->pixMerchantCity = (string) ($this->company->pix_merchant_city ?: ($this->company->city ?: 'Brasilia'));
        $this->pixQrImage = (string) ($this->company->pix_qr_image ?: '');
        $this->cardFeeDebit = (float) ($this->company->card_fee_debit ?? 1.50);
        $this->cardFeeCredit1x = (float) ($this->company->card_fee_credit_1x ?? 3.20);
        $rawInstallments = $this->company->card_fee_credit_installments;
        if (is_numeric($rawInstallments)) {
            $this->cardFeeCreditInstallments = (float) $rawInstallments;
        } elseif (is_array($rawInstallments) && isset($rawInstallments['base'])) {
            $this->cardFeeCreditInstallments = (float) $rawInstallments['base'];
        } else {
            $this->cardFeeCreditInstallments = (float) tenant_setting('card_fee_credit_installments', 4.50);
        }
        $this->barcodeScalePrefix = (string) ($this->company->barcode_scale_prefix ?: '2');
        $this->barcodeScaleType = (string) ($this->company->barcode_scale_type ?: 'weight');

        // Load configs from configurations table
        $configs = Configuration::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->pluck('value', 'key')
            ->all();

        $this->smtpHost = (string) ($configs['smtp_host'] ?? '');
        $this->smtpPort = ! empty($configs['smtp_port']) ? (int) $configs['smtp_port'] : 587;
        $this->smtpUsername = (string) ($configs['smtp_username'] ?? '');
        $this->smtpEncryption = (string) ($configs['smtp_encryption'] ?? 'tls');
        $this->smtpFromAddress = (string) ($configs['smtp_from_address'] ?? $this->email);
        $this->smtpFromName = (string) ($configs['smtp_from_name'] ?? $this->name);
        $this->hasStoredSmtpPassword = ! empty($configs['smtp_password']);

        $this->whatsappPhonePrefix = (string) ($configs['whatsapp_phone_prefix'] ?? '');
        $this->whatsappCustomNote = (string) ($configs['whatsapp_custom_note'] ?? '');
        $this->restaurantAlertIntervalMinutes = (int) ($configs['restaurant_alert_interval_minutes'] ?? 3);
        $this->restaurantAlertSoundPreset = (string) ($configs['restaurant_alert_sound_preset'] ?? 'chime');
        $this->restaurantAlertSoundUrl = (string) ($configs['restaurant_alert_sound_url'] ?? '');
        $this->whatsappPhoneNumberId = (string) ($configs['whatsapp_phone_number_id'] ?? '');
        $this->hasWhatsappApiToken = filled($configs['whatsapp_api_token'] ?? null);

        $this->defaultAiProvider = (string) ($configs['default_ai_provider'] ?? 'openai');
        $this->openaiModel = (string) ($configs['openai_model'] ?? 'dall-e-3');
        $this->geminiModel = (string) ($configs['gemini_model'] ?? 'imagen-3.0-generate-002');
        $this->claudeModel = (string) ($configs['claude_model'] ?? 'claude-3-5-sonnet-20241022');
        $this->hasOpenaiApiKey = filled($configs['openai_api_key'] ?? null);
        $this->hasGeminiApiKey = filled($configs['gemini_api_key'] ?? null);
        $this->hasClaudeApiKey = filled($configs['claude_api_key'] ?? null);

        $this->testEmailTo = auth('web')->user()?->email ?? $this->email;

        // Ensure default payment methods exist
        PaymentMethod::getForCompany($this->company->id);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'min:2', 'max:60', 'regex:/^[a-z0-9-]+$/',
                Rule::notIn(Company::RESERVED_SLUGS),
                Rule::unique('companies', 'slug')->ignore($this->company->id),
            ],
            'customDomain' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
            'currencySymbol' => ['required', 'string', 'max:8'],
            'currencyDecimals' => ['required', 'integer', 'min:0', 'max:4'],
            'currencySymbolPosition' => ['required', 'in:prefix,suffix'],
            'otherCurrencies.*.code' => ['nullable', 'string', 'max:10'],
            'otherCurrencies.*.name' => ['nullable', 'string', 'max:100'],
            'otherCurrencies.*.symbol' => ['nullable', 'string', 'max:8'],
            'otherCurrencies.*.exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'primaryColor' => ['required', 'string', 'max:20'],
            'themeColor' => ['required', 'string', 'max:30'],
            'posLayout' => ['required', 'in:standard,touch,stand'],
            'receiptFormat' => ['required', 'in:80mm,58mm'],
            'defaultCommissionRate' => ['numeric', 'min:0'],
            'defaultCommissionType' => ['required', 'in:percentage,fixed,profit_percentage,profit'],
            'logo' => ['nullable', 'string', 'max:500'],
            'favicon' => ['nullable', 'string', 'max:500'],
            'drawerCover' => ['nullable', 'string', 'max:500'],
            'logoFile' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'faviconFile' => ['nullable', 'file', 'mimes:png,ico,svg,jpg,jpeg,webp', 'max:1024'],
            'drawerCoverFile' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'defaultAiProvider' => ['required', 'in:openai,gemini,claude'],
            'openaiModel' => ['required', Rule::in(array_column($this->modelPresets['openai'], 'id'))],
            'geminiModel' => ['required', Rule::in(array_column($this->modelPresets['gemini'], 'id'))],
            'claudeModel' => ['required', Rule::in(array_column($this->modelPresets['claude'], 'id'))],
            'openaiApiKey' => ['nullable', 'string', 'max:500'],
            'geminiApiKey' => ['nullable', 'string', 'max:500'],
            'claudeApiKey' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function setQuotationColor(string $color): void
    {
        $this->primaryColor = $color;
    }

    public function setThemeColor(string $color): void
    {
        $this->themeColor = $color;
    }

    public function setPosLayout(string $layout): void
    {
        $this->posLayout = $layout;
    }

    public function setReceiptFormat(string $format): void
    {
        $this->receiptFormat = $format;
    }

    public function getRestaurantModeLockedProperty(): bool
    {
        return (bool) $this->company->restaurant_mode_locked;
    }

    public function getLogoPreviewUrlProperty(): ?string
    {
        if (! $this->logoFile) {
            return null;
        }
        try {
            return $this->logoFile->temporaryUrl();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getFaviconPreviewUrlProperty(): ?string
    {
        if (! $this->faviconFile) {
            return null;
        }
        try {
            return $this->faviconFile->temporaryUrl();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getDrawerCoverPreviewUrlProperty(): ?string
    {
        if (! $this->drawerCoverFile) {
            return null;
        }
        try {
            return $this->drawerCoverFile->temporaryUrl();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function updatedLogoFile(): void
    {
        try {
            $this->validateOnly('logoFile', [
                'logoFile' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            ]);
        } catch (ValidationException $e) {
            $this->logoFile = null;
            $msg = $e->validator->errors()->first('logoFile') ?: __('Invalid file format. Please upload a valid image file (.png, .jpg, .jpeg, .webp, .svg).');
            $this->dispatch('notify', ['type' => 'error', 'message' => $msg]);
            $this->addError('logoFile', $msg);
        } catch (\Throwable $e) {
            $this->logoFile = null;
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Invalid file format. Please upload a valid image file.')]);
        }
    }

    public function updatedFaviconFile(): void
    {
        try {
            $this->validateOnly('faviconFile', [
                'faviconFile' => ['nullable', 'file', 'mimes:png,ico,svg,jpg,jpeg,webp', 'max:1024'],
            ]);
        } catch (ValidationException $e) {
            $this->faviconFile = null;
            $msg = $e->validator->errors()->first('faviconFile') ?: __('Invalid favicon format. Please upload a valid .ico, .png, or .svg file under 1MB.');
            $this->dispatch('notify', ['type' => 'error', 'message' => $msg]);
            $this->addError('faviconFile', $msg);
        } catch (\Throwable $e) {
            $this->faviconFile = null;
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Invalid favicon format. Please upload a valid .ico, .png, or .svg file.')]);
        }
    }

    public function updatedDrawerCoverFile(): void
    {
        try {
            $this->validateOnly('drawerCoverFile', [
                'drawerCoverFile' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            ]);
        } catch (ValidationException $e) {
            $this->drawerCoverFile = null;
            $msg = $e->validator->errors()->first('drawerCoverFile') ?: __('Invalid image format. Please upload a valid image file (.png, .jpg, .jpeg, .webp) under 4MB.');
            $this->dispatch('notify', ['type' => 'error', 'message' => $msg]);
            $this->addError('drawerCoverFile', $msg);
        } catch (\Throwable $e) {
            $this->drawerCoverFile = null;
            $this->dispatch('notify', ['type' => 'error', 'message' => __('Invalid image format. Please upload a valid image file.')]);
        }
    }

    public function removeLogo(): void
    {
        if ($this->company->logo && ! filter_var($this->company->logo, FILTER_VALIDATE_URL)) {
            $cleanPath = preg_replace('#^/?storage/#', '', $this->company->logo);
            Storage::disk('public')->delete($cleanPath);
        }

        $this->company->update(['logo' => null]);
        $this->logo = '';
        $this->logoFile = null;
        $this->dispatch('company-logo-updated', logoUrl: null);
        session()->flash('status', 'Logo removed successfully.');
    }

    public function removeFavicon(): void
    {
        if ($this->company->favicon && ! filter_var($this->company->favicon, FILTER_VALIDATE_URL)) {
            $cleanPath = preg_replace('#^/?storage/#', '', $this->company->favicon);
            Storage::disk('public')->delete($cleanPath);
        }

        $this->company->update(['favicon' => null]);
        $this->favicon = '';
        $this->faviconFile = null;
        session()->flash('status', 'Favicon removed successfully.');
    }

    public function removeDrawerCover(): void
    {
        if ($this->company->drawer_cover && ! filter_var($this->company->drawer_cover, FILTER_VALIDATE_URL)) {
            $cleanPath = preg_replace('#^/?storage/#', '', $this->company->drawer_cover);
            Storage::disk('public')->delete($cleanPath);
        }

        $this->company->update(['drawer_cover' => null]);
        $this->drawerCover = '';
        $this->drawerCoverFile = null;
        session()->flash('status', 'Drawer cover image removed successfully.');
    }

    public function addOtherCurrency(): void
    {
        $this->otherCurrencies[] = ['code' => '', 'name' => '', 'symbol' => '', 'exchange_rate' => 1];
    }

    public function removeOtherCurrency(int $index): void
    {
        unset($this->otherCurrencies[$index]);
        $this->otherCurrencies = array_values($this->otherCurrencies);
    }

    public function save(): void
    {
        $user = auth('web')->user();
        if ($user && ! PermissionChecker::can($user, 'settings')) {
            abort(403, 'Unauthorized.');
        }

        $this->validate();

        // Handle Logo Upload
        if ($this->logoFile) {
            $path = $this->logoFile->store('tenant-logos', 'public');
            $this->logo = Storage::url($path);
            $this->logoFile = null;
        }

        // Handle Favicon Upload
        if ($this->faviconFile) {
            $path = $this->faviconFile->store('tenant-favicons', 'public');
            $this->favicon = Storage::url($path);
            $this->faviconFile = null;
        }

        // Handle Drawer Cover Upload
        if ($this->drawerCoverFile) {
            $path = $this->drawerCoverFile->store('tenant-drawer-covers', 'public');
            $this->drawerCover = Storage::url($path);
            $this->drawerCoverFile = null;
        }

        $this->company->update([
            'name' => $this->name,
            'slug' => $this->slug ?: null,
            'custom_domain' => $this->customDomain ?: null,
            'trade_name' => $this->tradeName ?: null,
            'tax_id' => $this->taxId ?: null,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'website' => $this->website ?: null,
            'address' => $this->address ?: null,
            'city' => $this->city ?: null,
            'state' => $this->state ?: null,
            'postal_code' => $this->postalCode ?: null,
            'country' => strtoupper($this->country),
            'currency' => strtoupper($this->currency),
            'currency_symbol' => $this->currencySymbol,
            'currency_decimals' => $this->currencyDecimals,
            'currency_symbol_position' => $this->currencySymbolPosition,
            'other_currencies' => collect($this->otherCurrencies)
                ->filter(fn ($c) => filled($c['code'] ?? null))
                ->map(fn ($c) => [
                    'code' => strtoupper($c['code']),
                    'name' => $c['name'] ?? '',
                    'symbol' => $c['symbol'] ?? '',
                    'exchange_rate' => (float) ($c['exchange_rate'] ?? 1),
                ])
                ->values()
                ->all(),
            'invoice_prefix' => $this->invoicePrefix ?: null,
            'quotation_prefix' => $this->quotationPrefix ?: null,
            'invoice_terms' => $this->invoiceTerms ?: null,
            'quote_terms' => $this->quoteTerms ?: null,
            'bank_details' => $this->bankDetails ?: null,
            'logo' => $this->logo ?: null,
            'favicon' => $this->favicon ?: null,
            'drawer_cover' => $this->drawerCover ?: null,
            'primary_color' => $this->primaryColor ?: '#2563eb',
            'theme_color' => $this->themeColor ?: 'blue',
            'pos_layout' => $this->posLayout ?: 'standard',
            'receipt_format' => $this->receiptFormat ?: '80mm',
            'default_commission_rate' => $this->defaultCommissionRate,
            'default_commission_type' => $this->defaultCommissionType ?: 'percentage',
            'enable_consignments' => $this->enableConsignments,
            'pix_key_type' => $this->pixKeyType,
            'pix_key' => $this->pixKey ?: null,
            'pix_merchant_name' => $this->pixMerchantName ?: null,
            'pix_merchant_city' => $this->pixMerchantCity ?: null,
            'pix_qr_image' => $this->pixQrImage ?: null,
            'card_fee_debit' => $this->cardFeeDebit,
            'card_fee_credit_1x' => $this->cardFeeCredit1x,
            'card_fee_credit_installments' => $this->cardFeeCreditInstallments,
            'barcode_scale_prefix' => $this->barcodeScalePrefix ?: '2',
            'barcode_scale_type' => $this->barcodeScaleType ?: 'weight',
        ]);

        $this->dispatch('set-ui-accent-color', color: $this->primaryColor ?: '#2563eb');

        // Save tenant-owned feature configurations. Delivery and push
        // gateway credentials are intentionally SuperAdmin-only.
        $configsToSave = [
            'default_ai_provider' => $this->defaultAiProvider,
            'openai_model' => $this->openaiModel,
            'gemini_model' => $this->geminiModel,
            'claude_model' => $this->claudeModel,
        ];

        foreach (['openai_api_key' => 'openaiApiKey', 'gemini_api_key' => 'geminiApiKey', 'claude_api_key' => 'claudeApiKey'] as $key => $property) {
            if (filled($this->{$property})) {
                $configsToSave[$key] = trim($this->{$property});
                $flag = 'has'.ucfirst($property);
                $this->{$flag} = true;
                $this->{$property} = '';
            }
        }

        foreach ($configsToSave as $key => $val) {
            Configuration::withoutGlobalScopes()->updateOrCreate([
                'company_id' => $this->company->id,
                'key' => $key,
            ], [
                'value' => $val,
            ]);
        }

        $this->dispatch('company-logo-updated', logoUrl: $this->company->getLogoUrl(), tradeName: $this->company->trade_name ?: $this->company->name);
        AuditLog::record('company.settings_updated', $this->company->id, $user->id);
        session()->flash('status', 'Store branding, colors, and settings saved successfully.');
    }

    public function getModelPresetsProperty(): array
    {
        return [
            'openai' => [
                ['id' => 'dall-e-2', 'name' => 'DALL-E 2 (Legacy / Fast 512×512)', 'badge' => 'Legacy'],
                ['id' => 'dall-e-3', 'name' => 'DALL-E 3 (Studio 1024×1024)', 'badge' => 'High Quality'],
                ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini + DALL-E 3 Pipeline', 'badge' => 'Fast Prompting'],
                ['id' => 'gpt-4o', 'name' => 'GPT-4o Vision + DALL-E 3 Pipeline', 'badge' => 'Multimodal'],
            ],
            'gemini' => [
                ['id' => 'imagegeneration@006', 'name' => 'Imagen 2 (Legacy Cloud)', 'badge' => 'Legacy'],
                ['id' => 'imagen-3.0-generate-002', 'name' => 'Imagen 3 (Flagship)', 'badge' => 'Latest'],
                ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash + Imagen 3', 'badge' => 'Legacy Fast'],
                ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash + Imagen 3', 'badge' => 'Fast Prompting'],
            ],
            'claude' => [
                ['id' => 'claude-3-haiku-20240307', 'name' => 'Claude 3 Haiku (Budget / High Speed)', 'badge' => 'Legacy Fast'],
                ['id' => 'claude-3-5-sonnet-20241022', 'name' => 'Claude 3.5 Sonnet (Vision Specialist)', 'badge' => 'Stable'],
                ['id' => 'claude-3-7-sonnet-latest', 'name' => 'Claude 3.7 Sonnet (Hybrid Reasoning)', 'badge' => 'Flagship'],
            ],
        ];
    }

    public function saveAiConfiguration(): void
    {
        $user = auth('web')->user();
        if ($user && ! PermissionChecker::can($user, 'settings')) {
            abort(403, 'Unauthorized.');
        }

        $this->validate([
            'defaultAiProvider' => ['required', 'in:openai,gemini,claude'],
            'openaiModel' => ['required', Rule::in(array_column($this->modelPresets['openai'], 'id'))],
            'geminiModel' => ['required', Rule::in(array_column($this->modelPresets['gemini'], 'id'))],
            'claudeModel' => ['required', Rule::in(array_column($this->modelPresets['claude'], 'id'))],
            'openaiApiKey' => ['nullable', 'string', 'max:500'],
            'geminiApiKey' => ['nullable', 'string', 'max:500'],
            'claudeApiKey' => ['nullable', 'string', 'max:500'],
        ]);

        $values = [
            'default_ai_provider' => $this->defaultAiProvider,
            'openai_model' => $this->openaiModel,
            'gemini_model' => $this->geminiModel,
            'claude_model' => $this->claudeModel,
        ];
        foreach (['openai_api_key' => 'openaiApiKey', 'gemini_api_key' => 'geminiApiKey', 'claude_api_key' => 'claudeApiKey'] as $key => $property) {
            if (filled($this->{$property})) {
                $values[$key] = trim($this->{$property});
                $flag = 'has'.ucfirst($property);
                $this->{$flag} = true;
                $this->{$property} = '';
            }
        }
        foreach ($values as $key => $value) {
            Configuration::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $this->company->id, 'key' => $key],
                ['value' => $value],
            );
        }

        AuditLog::record('company.ai_settings_updated', $this->company->id, $user?->id);
        session()->flash('status', __('AI Vision & model configuration updated successfully.'));
        $this->dispatch('toast', ['type' => 'success', 'message' => __('AI Vision & model configuration updated successfully.')]);
    }

    public function openAddChannelModal(): void
    {
        $this->reset(['editingChannelId', 'channelName', 'channelUrl', 'channelHeaders', 'channelAuthValue', 'channelPayloadTemplate', 'channelEventTypes']);
        $this->channelIcon = 'webhook';
        $this->channelMethod = 'POST';
        $this->channelAuthType = 'none';
        $this->channelIsActive = true;
        $this->showChannelModal = true;
    }

    public function openEditChannelModal(int $id): void
    {
        $channel = \App\Models\CustomNotificationChannel::where('company_id', $this->company->id)->findOrFail($id);
        $this->editingChannelId = $channel->id;
        $this->channelName = $channel->name;
        $this->channelIcon = $channel->icon ?: 'webhook';
        $this->channelUrl = $channel->url;
        $this->channelMethod = $channel->method;
        $this->channelHeaders = $channel->headers ? json_encode($channel->headers, JSON_PRETTY_PRINT) : '';
        $this->channelAuthType = $channel->auth_type;
        $this->channelAuthValue = '';
        $this->channelPayloadTemplate = (string) $channel->payload_template;
        $this->channelEventTypes = $channel->event_types ?? [];
        $this->channelIsActive = (bool) $channel->is_active;
        $this->showChannelModal = true;
    }

    public function saveChannel(): void
    {
        $this->validate([
            'channelName' => ['required', 'string', 'max:100'],
            'channelIcon' => ['nullable', 'string', 'max:255'],
            'channelUrl' => ['required', 'url', 'max:500'],
            'channelMethod' => ['required', 'in:POST,GET'],
            'channelHeaders' => ['nullable', 'string'],
            'channelAuthType' => ['required', 'in:none,bearer,api_key'],
            'channelPayloadTemplate' => ['nullable', 'string', 'max:5000'],
            'channelEventTypes' => ['array'],
        ]);

        $headers = null;
        if (filled($this->channelHeaders)) {
            $decoded = json_decode($this->channelHeaders, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('channelHeaders', 'Headers must be valid JSON, e.g. {"X-Api-Key": "..."}');

                return;
            }
            $headers = $decoded;
        }

        $data = [
            'name' => $this->channelName,
            'icon' => $this->channelIcon ?: 'webhook',
            'url' => $this->channelUrl,
            'method' => $this->channelMethod,
            'headers' => $headers,
            'auth_type' => $this->channelAuthType,
            'payload_template' => $this->channelPayloadTemplate ?: null,
            'event_types' => $this->channelEventTypes,
            'is_active' => $this->channelIsActive,
        ];

        if (filled($this->channelAuthValue)) {
            $data['auth_value'] = $this->channelAuthValue;
        }

        if ($this->editingChannelId) {
            $channel = \App\Models\CustomNotificationChannel::where('company_id', $this->company->id)->findOrFail($this->editingChannelId);
            $channel->update($data);
            session()->flash('status', "Notification channel {$channel->name} updated.");
        } else {
            \App\Models\CustomNotificationChannel::create(array_merge($data, ['company_id' => $this->company->id]));
            session()->flash('status', "Notification channel {$this->channelName} added.");
        }

        $this->showChannelModal = false;
        $this->reset(['editingChannelId', 'channelName', 'channelUrl', 'channelHeaders', 'channelAuthValue', 'channelPayloadTemplate', 'channelEventTypes']);
    }

    public function deleteChannel(int $id): void
    {
        $channel = \App\Models\CustomNotificationChannel::where('company_id', $this->company->id)->findOrFail($id);
        $name = $channel->name;
        $channel->delete();
        session()->flash('status', "Notification channel {$name} deleted.");
    }

    public function openAddPaymentMethodModal(): void
    {
        $this->reset(['editingPaymentMethodId', 'pmName', 'pmCode', 'pmDescription', 'pmBankName', 'pmAccountNo', 'pmIfscCode', 'pmUpiId', 'pmHolderName']);
        $this->pmIsActive = true;
        $this->pmOrderIndex = PaymentMethod::where('company_id', $this->company->id)->count() + 1;
        $this->showPaymentMethodModal = true;
    }

    public function openEditPaymentMethodModal(string $id): void
    {
        $pm = PaymentMethod::where('company_id', $this->company->id)->findOrFail($id);
        $this->editingPaymentMethodId = $pm->id;
        $this->pmName = $pm->name;
        $this->pmCode = (string) $pm->code;
        $this->pmDescription = (string) $pm->description;
        $this->pmIsActive = $pm->is_active;
        $this->pmOrderIndex = $pm->order_index;
        $metadata = $pm->metadata ?? [];
        $this->pmBankName = (string) ($metadata['bank_name'] ?? '');
        $this->pmAccountNo = (string) ($metadata['account_no'] ?? '');
        $this->pmIfscCode = (string) ($metadata['ifsc_code'] ?? '');
        $this->pmUpiId = (string) ($metadata['upi_id'] ?? '');
        $this->pmHolderName = (string) ($metadata['holder_name'] ?? '');
        $this->showPaymentMethodModal = true;
    }

    public function savePaymentMethod(): void
    {
        $this->validate([
            'pmName' => ['required', 'string', 'max:100'],
            'pmCode' => ['nullable', 'string', 'max:50'],
            'pmDescription' => ['nullable', 'string', 'max:255'],
            'pmOrderIndex' => ['integer', 'min:0'],
            'pmBankName' => ['nullable', 'string', 'max:150'],
            'pmAccountNo' => ['nullable', 'string', 'max:60'],
            'pmIfscCode' => ['nullable', 'string', 'max:20'],
            'pmUpiId' => ['nullable', 'string', 'max:100'],
            'pmHolderName' => ['nullable', 'string', 'max:150'],
        ]);

        $code = $this->pmCode ?: Str::slug($this->pmName, '_');
        $metadata = array_filter([
            'bank_name' => $this->pmBankName ?: null,
            'account_no' => $this->pmAccountNo ?: null,
            'ifsc_code' => $this->pmIfscCode ?: null,
            'upi_id' => $this->pmUpiId ?: null,
            'holder_name' => $this->pmHolderName ?: null,
        ]);

        if ($this->editingPaymentMethodId) {
            $pm = PaymentMethod::where('company_id', $this->company->id)->findOrFail($this->editingPaymentMethodId);
            $pm->update([
                'name' => $this->pmName,
                'code' => $code,
                'description' => $this->pmDescription ?: null,
                'is_active' => $this->pmIsActive,
                'order_index' => $this->pmOrderIndex,
                'metadata' => $metadata ?: null,
            ]);
            session()->flash('status', "Payment method {$pm->name} updated.");
        } else {
            $pm = PaymentMethod::create([
                'company_id' => $this->company->id,
                'name' => $this->pmName,
                'code' => $code,
                'description' => $this->pmDescription ?: null,
                'is_active' => $this->pmIsActive,
                'order_index' => $this->pmOrderIndex,
                'metadata' => $metadata ?: null,
            ]);
            session()->flash('status', "Payment method {$pm->name} added successfully.");
        }

        $this->showPaymentMethodModal = false;
        $this->reset(['editingPaymentMethodId', 'pmName', 'pmCode', 'pmDescription', 'pmBankName', 'pmAccountNo', 'pmIfscCode', 'pmUpiId', 'pmHolderName']);
    }

    public function togglePaymentMethodStatus(string $id): void
    {
        $pm = PaymentMethod::where('company_id', $this->company->id)->findOrFail($id);
        $pm->update(['is_active' => ! $pm->is_active]);

        session()->flash('status', "Payment method {$pm->name} is now ".($pm->is_active ? 'Active' : 'Disabled').'.');
    }

    public function deletePaymentMethod(string $id): void
    {
        $pm = PaymentMethod::where('company_id', $this->company->id)->findOrFail($id);
        $name = $pm->name;
        $pm->delete();

        session()->flash('status', "Payment method {$name} deleted.");
    }

    public function sendTestEmail(): void
    {
        $this->validate(['testEmailTo' => ['required', 'email']]);

        try {
            $service = app(InvoiceDeliveryService::class);
            $service->sendTestEmail($this->company, $this->testEmailTo, [
                'host' => $this->smtpHost,
                'port' => $this->smtpPort,
                'username' => $this->smtpUsername,
                'password' => $this->smtpPassword ?: null,
                'encryption' => $this->smtpEncryption,
                'from_address' => $this->smtpFromAddress,
                'from_name' => $this->smtpFromName,
            ]);

            session()->flash('status', "Test email sent successfully to {$this->testEmailTo}!");
        } catch (\Throwable $e) {
            session()->flash('error', 'SMTP Test Failed: '.$e->getMessage());
        }
    }

    public function newTaxRule(): void
    {
        $this->editingTaxRuleId = null;
        $this->taxRuleName = '';
        $this->taxRuleCode = '';
        $this->taxRuleRate = 0.0;
        $this->taxRuleType = 'percentage';
        $this->taxRuleIsInclusive = false;
        $this->taxRuleIsCompound = false;
        $this->taxRuleIsDefault = false;
        $this->taxRuleDescription = '';
        $this->taxRuleSubComponents = [];
        $this->showTaxRuleModal = true;
    }

    public function editTaxRule(string $id): void
    {
        $rule = TaxRule::where('company_id', $this->company->id)->findOrFail($id);
        $this->editingTaxRuleId = $rule->id;
        $this->taxRuleName = $rule->tax_name;
        $this->taxRuleCode = (string) ($rule->tax_code ?? '');
        $this->taxRuleRate = (float) $rule->rate;
        $this->taxRuleType = $rule->type ?: 'percentage';
        $this->taxRuleIsInclusive = (bool) $rule->is_inclusive;
        $this->taxRuleIsCompound = (bool) $rule->is_compound;
        $this->taxRuleIsDefault = (bool) $rule->is_default;
        $this->taxRuleDescription = (string) ($rule->description ?? '');
        $this->taxRuleSubComponents = $rule->sub_components ?: [];
        $this->showTaxRuleModal = true;
    }

    public function addTaxSubComponent(): void
    {
        $this->taxRuleSubComponents[] = ['name' => '', 'rate' => 0.0, 'code' => ''];
    }

    public function removeTaxSubComponent(int $index): void
    {
        unset($this->taxRuleSubComponents[$index]);
        $this->taxRuleSubComponents = array_values($this->taxRuleSubComponents);
    }

    public function saveTaxRule(): void
    {
        $this->validate([
            'taxRuleName' => ['required', 'string', 'max:255'],
            'taxRuleRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'taxRuleType' => ['required', 'string', 'in:percentage,fixed'],
        ]);

        if ($this->taxRuleIsDefault) {
            TaxRule::where('company_id', $this->company->id)->update(['is_default' => false]);
        }

        $components = array_values(array_filter($this->taxRuleSubComponents, fn ($c) => ! empty($c['name'])));

        if ($this->editingTaxRuleId) {
            $rule = TaxRule::where('company_id', $this->company->id)->findOrFail($this->editingTaxRuleId);
            $rule->update([
                'tax_name' => $this->taxRuleName,
                'tax_code' => $this->taxRuleCode ?: null,
                'rate' => $this->taxRuleRate,
                'type' => $this->taxRuleType,
                'is_inclusive' => $this->taxRuleIsInclusive,
                'calc_type' => $this->taxRuleIsInclusive ? 'inclusive' : 'exclusive',
                'is_compound' => $this->taxRuleIsCompound,
                'is_default' => $this->taxRuleIsDefault,
                'sub_components' => $components,
                'description' => $this->taxRuleDescription ?: null,
            ]);
            session()->flash('status', "Tax Rule {$rule->tax_name} updated successfully.");
        } else {
            $rule = TaxRule::create([
                'company_id' => $this->company->id,
                'tax_name' => $this->taxRuleName,
                'tax_code' => $this->taxRuleCode ?: null,
                'rate' => $this->taxRuleRate,
                'type' => $this->taxRuleType,
                'country' => $this->company->country ?: 'US',
                'is_inclusive' => $this->taxRuleIsInclusive,
                'calc_type' => $this->taxRuleIsInclusive ? 'inclusive' : 'exclusive',
                'is_compound' => $this->taxRuleIsCompound,
                'is_default' => $this->taxRuleIsDefault,
                'sub_components' => $components,
                'description' => $this->taxRuleDescription ?: null,
                'active' => true,
            ]);
            session()->flash('status', "Tax Rule {$rule->tax_name} created successfully.");
        }

        $this->showTaxRuleModal = false;
    }

    public function setDefaultTaxRule(string $id): void
    {
        TaxRule::where('company_id', $this->company->id)->update(['is_default' => false]);
        $rule = TaxRule::where('company_id', $this->company->id)->findOrFail($id);
        $rule->update(['is_default' => true]);

        session()->flash('status', "{$rule->tax_name} is now the default store tax rule.");
    }

    public function deleteTaxRule(string $id): void
    {
        $rule = TaxRule::where('company_id', $this->company->id)->findOrFail($id);
        $name = $rule->tax_name;
        $rule->delete();

        session()->flash('status', "Tax Rule {$name} deleted.");
    }

    public function seedJurisdictionTaxRules(): void
    {
        $this->preSeedTaxRules($this->company->country ?: 'US');
    }

    public function preSeedTaxRules(string $countryCode): void
    {
        $service = app(TaxCalculationService::class);
        $service->seedTenantDefaultTaxRules($this->company, $countryCode);

        $presets = $service->getJurisdictionPresets($countryCode);
        $countryName = $presets['country_name'] ?? $countryCode;

        session()->flash('status', "Standard fiscal tax rules for {$countryName} ({$countryCode}) have been pre-seeded successfully.");
    }

    // Developer API Key Actions
    public function createApiKey(): void
    {
        $this->validate([
            'newApiKeyName' => ['required', 'string', 'max:255'],
        ]);

        $token = TenantApiKey::generateToken();
        TenantApiKey::create([
            'company_id' => $this->company->id,
            'name' => $this->newApiKeyName,
            'token' => $token,
            'permissions' => $this->newApiKeyPermissions ?: ['*'],
            'active' => true,
        ]);

        $this->recentlyGeneratedToken = $token;
        $this->newApiKeyName = '';
        $this->showApiKeyModal = false;
        session()->flash('status', 'API Key generated successfully! Make sure to copy the secret token below.');
    }

    public function revokeApiKey(string $id): void
    {
        $key = TenantApiKey::where('company_id', $this->company->id)->findOrFail($id);
        $key->delete();
        session()->flash('status', "API key {$key->name} revoked.");
    }

    public function toggleApiKey(string $id): void
    {
        $key = TenantApiKey::where('company_id', $this->company->id)->findOrFail($id);
        $key->update(['active' => ! $key->active]);
        session()->flash('status', "API key {$key->name} is now ".($key->active ? 'Active' : 'Disabled').'.');
    }

    public function render()
    {
        $taxService = app(TaxCalculationService::class);

        return view('livewire.tenant.settings.index', [
            'paymentMethods' => PaymentMethod::where('company_id', $this->company->id)->orderBy('order_index')->get(),
            'taxRules' => TaxRule::where('company_id', $this->company->id)->orderByDesc('is_default')->orderBy('tax_name')->get(),
            'apiKeys' => TenantApiKey::where('company_id', $this->company->id)->orderByDesc('created_at')->get(),
            'jurisdictionPresets' => $taxService->getJurisdictionPresets($this->company->country),
            'notificationChannels' => \App\Models\CustomNotificationChannel::where('company_id', $this->company->id)->orderBy('name')->get(),
            'navSections' => $this->buildNavSections(),
        ]);
    }

    /**
     * The Navigation Menu tab's editable working state: TenantNavRegistry's
     * compiled-in tree for this store's mode, with this store's saved
     * nav_config (section/item order, item visibility, an item moved to a
     * different section, an item nested under another) applied on top — the
     * exact same merge DashboardScreen._sectionsFor and layouts/tenant.
     * blade.php's client-side reordering script do, so this tab always
     * shows what the live sidebar (both web and mobile) currently renders.
     * Up to two levels of nesting are supported (Main Menu / Sub-Menu /
     * Sub-Sub-Menu) — see the loop below that drops a parent link deeper
     * than that back to root rather than silently hiding the item.
     *
     * @return list<array{key: string, label: string, items: list<array{key: string, label: string, visible: bool, children: list<array{key: string, label: string, visible: bool, children: list<array{key: string, label: string, visible: bool}>}>}>}>
     */
    private function buildNavSections(): array
    {
        $compiled = TenantNavRegistry::sectionsFor($this->company->isRestaurantMode());
        $compiledByKey = collect($compiled)->keyBy('key');

        $navConfig = $this->company->normalizedNavConfig();
        $itemOverrides = collect($navConfig['items'])->keyBy('key');
        $sectionOrderOverrides = collect($navConfig['sections'])->pluck('order', 'key');

        // Resolve every compiled item's effective section/parent/order/
        // visibility, keyed by item key so parent lookups below are O(1).
        $resolved = [];
        foreach ($compiled as $section) {
            foreach ($section['items'] as $itemIndex => $item) {
                $override = $itemOverrides->get($item['key']);
                $targetSectionKey = ($override && $override['section'] && $compiledByKey->has($override['section']))
                    ? $override['section']
                    : $section['key'];

                $resolved[$item['key']] = [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'visible' => $override['visible'] ?? true,
                    'section' => $targetSectionKey,
                    'parent' => $override
                        ? ($override['parent_id'] ?? $override['parent'] ?? null)
                        : ($item['parent'] ?? null),
                    'order' => $override['order'] ?? $itemIndex,
                ];
            }
        }

        // A parent link only holds if the parent exists, resolves to the
        // same section, and its own depth doesn't already sit at the cap —
        // otherwise the item falls back to its section's root rather than
        // disappearing. Depth 0 = root (Main Menu), 1 = Sub-Menu, 2 =
        // Sub-Sub-Menu — the deepest level this builder supports, matching
        // the web/mobile UI's own nesting guards, so a parent already at
        // depth 2 can't take on more children.
        foreach ($resolved as $key => &$row) {
            if (! $row['parent']) {
                continue;
            }
            $parentRow = $resolved[$row['parent']] ?? null;
            if (! $parentRow || $parentRow['section'] !== $row['section']) {
                $row['parent'] = null;

                continue;
            }
            $parentDepth = 0;
            if (! empty($parentRow['parent'])) {
                $grandparentRow = $resolved[$parentRow['parent']] ?? null;
                $parentDepth = ($grandparentRow && $grandparentRow['section'] === $parentRow['section'] && empty($grandparentRow['parent']))
                    ? 1
                    : 2;
            }
            if ($parentDepth >= 2) {
                $row['parent'] = null;
            }
        }
        unset($row);

        // Root items (depth 0) first, then attach depth-1 items under their
        // (already-placed) root parent, then depth-2 items under their
        // (already-placed) depth-1 parent. Every row's parent link is
        // validated above to resolve to exactly one of these two passes —
        // a parent with no parent of its own is a root item (pass 1), a
        // parent that itself has a parent is a depth-1 item (pass 2) — so
        // this is exhaustive, not just "the common case".
        $grouped = [];
        foreach ($resolved as $row) {
            if ($row['parent']) {
                continue;
            }
            $grouped[$row['section']][$row['key']] = $row + ['children' => []];
        }
        foreach ($resolved as $row) {
            if (! $row['parent']) {
                continue;
            }
            $parentRow = $resolved[$row['parent']];
            if ($parentRow['parent']) {
                continue; // depth-2 — handled by the pass below instead.
            }
            if (! isset($grouped[$row['section']][$row['parent']])) {
                continue;
            }
            $grouped[$row['section']][$row['parent']]['children'][$row['key']] = $row + ['children' => []];
        }
        foreach ($resolved as $row) {
            if (! $row['parent']) {
                continue;
            }
            $parentRow = $resolved[$row['parent']];
            if (! $parentRow['parent']) {
                continue; // depth-1 — already attached above.
            }
            $grandparentKey = $parentRow['parent'];
            if (! isset($grouped[$row['section']][$grandparentKey]['children'][$row['parent']])) {
                continue;
            }
            $grouped[$row['section']][$grandparentKey]['children'][$row['parent']]['children'][$row['key']] = $row + ['children' => []];
        }

        $sections = [];
        foreach ($grouped as $sectionKey => $items) {
            $items = array_values($items);
            usort($items, fn ($a, $b) => $a['order'] <=> $b['order']);
            foreach ($items as &$item) {
                $item['children'] = array_values($item['children']);
                usort($item['children'], fn ($a, $b) => $a['order'] <=> $b['order']);
                foreach ($item['children'] as &$child) {
                    $child['children'] = array_values($child['children']);
                    usort($child['children'], fn ($a, $b) => $a['order'] <=> $b['order']);
                    $child['children'] = array_map(
                        fn ($gc) => ['key' => $gc['key'], 'label' => $gc['label'], 'visible' => $gc['visible']],
                        $child['children']
                    );
                }
                unset($child);
                $item['children'] = array_map(
                    fn ($c) => ['key' => $c['key'], 'label' => $c['label'], 'visible' => $c['visible'], 'children' => $c['children']],
                    $item['children']
                );
            }
            unset($item);

            $sections[] = [
                'key' => $sectionKey,
                'label' => $compiledByKey[$sectionKey]['label'],
                'items' => array_map(
                    fn ($i) => ['key' => $i['key'], 'label' => $i['label'], 'visible' => $i['visible'], 'children' => $i['children']],
                    $items
                ),
            ];
        }

        $compiledIndex = collect($compiled)->keys()->flip();
        usort($sections, function ($a, $b) use ($sectionOrderOverrides, $compiledIndex) {
            $orderA = $sectionOrderOverrides->get($a['key'], $compiledIndex->get($a['key'], 0));
            $orderB = $sectionOrderOverrides->get($b['key'], $compiledIndex->get($b['key'], 0));

            return $orderA <=> $orderB;
        });

        return $sections;
    }

    /**
     * POST target for Settings > Navigation Menu's Save button — see
     * resources/views/livewire/tenant/settings/index.blade.php's Alpine nav
     * builder. Accepts the complete current section/item tree (order,
     * section placement, visibility, and each item's nested `children`,
     * up to two levels deep — Main Menu, Sub-Menu, Sub-Sub-Menu) as one
     * payload rather than incremental diffs, mirroring how the mobile app's
     * NavMenuSettingsTab saves. Every item at any depth becomes its own
     * flat nav_config['items'] row carrying its parent's key, so
     * buildNavSections() can re-nest it on read. The shared normalizer also
     * repairs invalid links and promotes any over-deep row to the section
     * root, keeping this legacy Livewire entry point consistent with the
     * JSON endpoint used by the current web and mobile editors.
     */
    public function saveNavConfig(array $sections): void
    {
        $user = auth('web')->user();
        if ($user && ! PermissionChecker::can($user, 'settings')) {
            abort(403, 'Unauthorized.');
        }

        $tree = array_values(array_map(
            fn (array $section, int $order) => $section + ['order' => $order],
            $sections,
            array_keys($sections)
        ));
        $navConfig = app(\App\Services\Navigation\TenantNavigationConfigService::class)->normalize(['tree' => $tree]);

        $this->company->update(['nav_config' => $navConfig]);
        AuditLog::record('company.settings_updated', $this->company->id, $user?->id, ['section' => 'nav_config']);

        session()->flash('status', 'Navigation menu updated.');
    }
}
