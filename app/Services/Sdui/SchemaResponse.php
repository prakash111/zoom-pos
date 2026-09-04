<?php

namespace App\Services\Sdui;

use App\Models\Company;
use App\Models\Configuration;
use App\Models\SduiScreen;
use App\Services\Modular\ModuleRegistry;
use App\Services\Navigation\TenantNavigationConfigService;
use App\Services\Navigation\TenantNavRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized Server-Driven UI (SDUI) Schema Response Builder.
 *
 * Dictates layout, components, interactive forms, actions, and validation
 * entirely on the Laravel server via declarative JSON schema payloads.
 */
class SchemaResponse
{
    public const SCHEMA_VERSION = 1;

    public const LAYOUT_TYPES = ['column', 'grid', 'grid_view', 'tabs', 'scroll_view'];

    public const COMPONENT_TYPES = [
        'container', 'card', 'scroll_view', 'grid_view', 'accordion_group',
        'accordion', 'column', 'row', 'tabs', 'text', 'image_network',
        'badge', 'icon', 'divider', 'text_input', 'dropdown_select',
        'checkbox', 'toggle_switch', 'date_time_picker', 'color_picker',
        'line_item_tile', 'table_grid', 'step_counter', 'button_primary',
        'button_outlined', 'fab', 'action_sheet_trigger', 'navigation_builder', 'tree_builder',
        'wrap',
    ];

    public const INPUT_TYPES = [
        'text_input', 'dropdown_select', 'checkbox', 'toggle_switch',
        'date_time_picker', 'color_picker', 'step_counter',
    ];

    public const ACTION_COMPONENT_TYPES = [
        'button_primary', 'button_outlined', 'fab',
    ];

    public const ACTION_TYPES = [
        'navigate', 'form_submit', 'api_post', 'open_modal', 'navigate_back', 'pop',
    ];

    // =========================================================================
    // Layout Primitives
    // =========================================================================

    public static function container(array $components, array $props = []): array
    {
        return array_merge([
            'type' => 'container',
            'components' => $components,
        ], $props);
    }

    public static function card(array $components, array $props = []): array
    {
        return array_merge([
            'type' => 'card',
            'components' => $components,
        ], $props);
    }

    public static function scrollView(array $components, array $props = []): array
    {
        return array_merge([
            'type' => 'scroll_view',
            'components' => $components,
        ], $props);
    }

    public static function gridView(array $components, int $columns = 2, array $props = []): array
    {
        return array_merge([
            'type' => 'grid_view',
            'cross_axis_count' => $columns,
            'components' => $components,
        ], $props);
    }

    public static function accordionGroup(string $title, array $components, array $props = []): array
    {
        return array_merge([
            'type' => 'accordion_group',
            'title' => $title,
            'components' => $components,
        ], $props);
    }

    public static function column(array $components, array $props = []): array
    {
        return array_merge([
            'type' => 'column',
            'components' => $components,
        ], $props);
    }

    public static function row(array $components, array $props = []): array
    {
        return array_merge([
            'type' => 'row',
            'components' => $components,
        ], $props);
    }

    public static function wrap(array $components, array $props = []): array
    {
        return array_merge([
            'type' => 'wrap',
            'components' => $components,
            'spacing' => 8,
            'run_spacing' => 8,
        ], $props);
    }

    public static function tabs(array $tabs): array
    {
        return [
            'type' => 'tabs',
            'tabs' => $tabs,
        ];
    }

    // =========================================================================
    // Display Primitives
    // =========================================================================

    public static function text(string $text, string $style = 'body_medium', array $props = []): array
    {
        return array_merge([
            'type' => 'text',
            'text' => $text,
            'style' => $style,
        ], $props);
    }

    public static function imageNetwork(string $url, array $props = []): array
    {
        return array_merge([
            'type' => 'image_network',
            'url' => $url,
        ], $props);
    }

    public static function badge(string $label, string $color = '#10b981', string $style = 'subtle', array $props = []): array
    {
        return array_merge([
            'type' => 'badge',
            'label' => $label,
            'color' => $color,
            'badge_style' => $style,
        ], $props);
    }

    public static function icon(string $icon, array $props = []): array
    {
        return array_merge([
            'type' => 'icon',
            'icon' => $icon,
        ], $props);
    }

    public static function divider(array $props = []): array
    {
        return array_merge([
            'type' => 'divider',
        ], $props);
    }

    // =========================================================================
    // Form & Input Primitives
    // =========================================================================

    public static function textInput(string $name, string $label, mixed $initialValue = '', array $props = []): array
    {
        return array_merge([
            'type' => 'text_input',
            'name' => $name,
            'label' => $label,
            'initial_value' => (string) ($initialValue ?? ''),
        ], $props);
    }

    public static function dropdownSelect(string $name, string $label, array $options, mixed $initialValue = null, array $props = []): array
    {
        $normalizedOptions = [];
        foreach ($options as $key => $val) {
            if (is_array($val)) {
                $normalizedOptions[] = [
                    'label' => (string) ($val['label'] ?? $val['name'] ?? $key),
                    'value' => (string) ($val['value'] ?? $val['code'] ?? $key),
                ];
            } else {
                $normalizedOptions[] = [
                    'label' => (string) $val,
                    'value' => (string) (is_numeric($key) ? $val : $key),
                ];
            }
        }

        return array_merge([
            'type' => 'dropdown_select',
            'name' => $name,
            'label' => $label,
            'options' => $normalizedOptions,
            'initial_value' => (string) ($initialValue ?? ($normalizedOptions[0]['value'] ?? '')),
        ], $props);
    }

    public static function checkbox(string $name, string $label, bool $initialValue = false, array $props = []): array
    {
        return array_merge([
            'type' => 'checkbox',
            'name' => $name,
            'label' => $label,
            'initial_value' => $initialValue,
        ], $props);
    }

    public static function toggleSwitch(string $name, string $label, bool $initialValue = false, array $props = []): array
    {
        return array_merge([
            'type' => 'toggle_switch',
            'name' => $name,
            'label' => $label,
            'initial_value' => $initialValue,
        ], $props);
    }

    public static function dateTimePicker(string $name, string $label, mixed $initialValue = null, string $mode = 'date', array $props = []): array
    {
        return array_merge([
            'type' => 'date_time_picker',
            'name' => $name,
            'label' => $label,
            'mode' => $mode,
            'initial_value' => (string) ($initialValue ?? ''),
        ], $props);
    }

    public static function colorPicker(string $name, string $label, string $initialValue = '#1d4ed8', array $props = []): array
    {
        return array_merge([
            'type' => 'color_picker',
            'name' => $name,
            'label' => $label,
            'initial_value' => $initialValue,
            'presets' => ['#1d4ed8', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#8b5cf6', '#0284c7', '#0f766e'],
        ], $props);
    }

    // =========================================================================
    // Lists & Tables
    // =========================================================================

    public static function lineItemTile(string $title, string $subtitle = '', ?string $icon = null, ?array $action = null, array $props = []): array
    {
        return array_merge([
            'type' => 'line_item_tile',
            'title' => $title,
            'subtitle' => $subtitle,
            'leading_icon' => $icon,
            'action' => $action,
        ], $props);
    }

    public static function tableGrid(array $headers, array $rows, array $props = []): array
    {
        return array_merge([
            'type' => 'table_grid',
            'headers' => $headers,
            'rows' => $rows,
        ], $props);
    }

    public static function stepCounter(string $name, string $label, int $initialValue = 1, int $min = 0, int $max = 999, array $props = []): array
    {
        return array_merge([
            'type' => 'step_counter',
            'name' => $name,
            'label' => $label,
            'initial_value' => $initialValue,
            'min' => $min,
            'max' => $max,
        ], $props);
    }

    // =========================================================================
    // Actions & Buttons
    // =========================================================================

    public static function buttonPrimary(string $label, array $action, ?string $icon = null, array $props = []): array
    {
        return array_merge([
            'type' => 'button_primary',
            'label' => $label,
            'action' => $action,
            'icon' => $icon,
            'full_width' => $props['full_width'] ?? true,
        ], $props);
    }

    public static function buttonOutlined(string $label, array $action, ?string $icon = null, array $props = []): array
    {
        return array_merge([
            'type' => 'button_outlined',
            'label' => $label,
            'action' => $action,
            'icon' => $icon,
            'full_width' => $props['full_width'] ?? true,
        ], $props);
    }

    public static function fab(string $icon, array $action, ?string $label = null, array $props = []): array
    {
        return array_merge([
            'type' => 'fab',
            'icon' => $icon,
            'label' => $label,
            'action' => $action,
        ], $props);
    }

    public static function actionSheetTrigger(string $label, array $options, ?string $icon = null, array $props = []): array
    {
        return array_merge([
            'type' => 'action_sheet_trigger',
            'label' => $label,
            'icon' => $icon,
            'options' => $options,
        ], $props);
    }

    // =========================================================================
    // Action Descriptors
    // =========================================================================

    public static function navigateAction(string $endpoint, string $target = 'dynamic_page', ?string $title = null): array
    {
        return [
            'type' => 'navigate',
            'target' => $target,
            'endpoint' => $endpoint,
            'title' => $title,
        ];
    }

    public static function formSubmitAction(string $endpoint, string $method = 'POST', string $successToast = 'Settings saved successfully', bool $navigateBack = false): array
    {
        return [
            'type' => 'form_submit',
            'endpoint' => $endpoint,
            'method' => strtoupper($method),
            'success_toast' => $successToast,
            'navigate_back' => $navigateBack,
        ];
    }

    public static function apiPostAction(string $endpoint, array $payload = [], string $successToast = 'Action performed'): array
    {
        return [
            'type' => 'api_post',
            'endpoint' => $endpoint,
            'payload' => $payload,
            'success_toast' => $successToast,
        ];
    }

    public static function openModalAction(string $title, array $components): array
    {
        return [
            'type' => 'open_modal',
            'title' => $title,
            'components' => $components,
        ];
    }

    // =========================================================================
    // Screen Envelope
    // =========================================================================

    public static function screen(string $title, array $components, string $layout = 'scroll_view', array $options = []): array
    {
        return [
            'type' => 'screen',
            'schema_version' => self::SCHEMA_VERSION,
            'key' => $options['key'] ?? null,
            'title' => $title,
            'layout' => $layout,
            'app_bar' => [
                'title' => $title,
                'show_back_button' => $options['show_back_button'] ?? true,
                'actions' => $options['actions'] ?? [],
            ],
            'components' => $components,
            'fab' => $options['fab'] ?? null,
        ];
    }

    public static function jsonResponse(string $title, array $components, string $layout = 'scroll_view', array $options = []): JsonResponse
    {
        $schema = self::screen($title, $components, $layout, $options);
        $errors = app(SchemaValidator::class)->validate($schema);
        if ($errors !== []) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid SDUI schema.',
                'details' => ['schema' => $errors],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'schema' => $schema,
        ]);
    }

    /**
     * The renderer contract is delivered with bootstrap so clients can reject
     * an incompatible schema version explicitly.
     *
     * @return array<string, mixed>
     */
    public static function contract(): array
    {
        return [
            'version' => self::SCHEMA_VERSION,
            'layouts' => self::LAYOUT_TYPES,
            'components' => self::COMPONENT_TYPES,
            'actions' => self::ACTION_TYPES,
        ];
    }

    // =========================================================================
    // Prebuilt Domain Views (Settings & Pluggable Modules)
    // =========================================================================

    public static function modeView(Company $company): array
    {
        $activeMode = ModuleRegistry::resolveActiveMode($company);
        $module = ModuleRegistry::getModule($activeMode);
        $licensed = ModuleRegistry::availableModes($company);

        $featureChips = [];
        foreach ($module['features'] ?? [] as $feat => $enabled) {
            if ($enabled) {
                $featureChips[] = self::badge(
                    str_replace(['has_', '_'], ['', ' '], ucwords($feat)),
                    '#16a34a',
                    'subtle'
                );
            }
        }

        $licensedBadges = [];
        foreach ($licensed as $m) {
            $licensedBadges[] = self::badge(strtoupper($m), '#2563eb', 'solid');
        }

        return self::screen('Store Operating Mode', [
            self::card([
                self::row([
                    self::icon($module['icon'] ?? 'flash_on', ['size' => 32, 'color' => '#1d4ed8']),
                    self::column([
                        self::text($module['title'] ?? 'Store Mode', 'title_large', ['bold' => true]),
                        self::text('Operating Mode: '.strtoupper($activeMode), 'body_medium', ['color' => '#4b5563']),
                    ], ['spacing' => 4]),
                ], ['spacing' => 12]),
                self::divider(),
                self::text('Active Capabilities', 'title_small', ['bold' => true]),
                self::wrap($featureChips, ['spacing' => 8, 'run_spacing' => 8]),
            ]),
            self::card([
                self::text('Licensed Modules for Tenant', 'title_small', ['bold' => true]),
                self::text('Modules granted to this tenant account:', 'body_small', ['color' => '#6b7280']),
                self::wrap($licensedBadges, ['spacing' => 8, 'run_spacing' => 8]),
            ]),
        ]);
    }

    public static function profileView(Company $company): array
    {
        return self::screen('Store Profile & Branding', [
            self::card([
                self::text('Store Identity', 'title_medium', ['bold' => true]),
                self::text('Configure your business trade name, tax registration number, and address.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::textInput('name', 'Business Name', $company->name, ['required' => true]),
                self::textInput('trade_name', 'Trading Name (DBA)', $company->trade_name),
                self::textInput('tax_id', 'Tax ID / GSTIN / VAT', $company->tax_id),
                self::textInput('email', 'Store Email', $company->email, ['keyboard_type' => 'email']),
                self::textInput('phone', 'Store Phone', $company->phone, ['keyboard_type' => 'phone']),
                self::textInput('website', 'Store Website', $company->website),
            ]),
            self::card([
                self::text('Address & Localization', 'title_medium', ['bold' => true]),
                self::divider(),
                self::textInput('address', 'Street Address', $company->address),
                self::textInput('city', 'City', $company->city),
                self::textInput('state', 'State / Province', $company->state),
                self::textInput('postal_code', 'Postal / Zip Code', $company->postal_code),
                self::dropdownSelect('country', 'Country', [
                    'US' => 'United States',
                    'IN' => 'India',
                    'GB' => 'United Kingdom',
                    'CA' => 'Canada',
                    'AU' => 'Australia',
                    'AE' => 'United Arab Emirates',
                    'SA' => 'Saudi Arabia',
                ], $company->country ?? 'US'),
                self::colorPicker('primary_color', 'Primary Accent Color', $company->primary_color ?? '#4F46E5'),
                self::colorPicker('accent_color', 'Secondary Accent Color', $company->accent_color ?? '#D97706'),
                self::card([
                    self::text('Sidebar & Navigation Styling', 'title_medium', ['bold' => true]),
                    self::text('Configure solid background or multi-color gradients for the navigation drawer.', 'body_small', ['color' => '#6b7280']),
                    self::divider(),
                    self::colorPicker('drawer_bg', 'Solid Background Color', $company->drawer_bg ?? '#1e293b'),
                    self::toggleSwitch('drawer_gradient_enabled', 'Enable Gradient Background', (bool) ($company->drawer_gradient_enabled ?? false)),
                    self::colorPicker('drawer_gradient_start', 'Gradient Start Color', $company->drawer_gradient_start ?? ($company->drawer_bg ?? '#1e293b')),
                    self::colorPicker('drawer_gradient_end', 'Gradient End Color', $company->drawer_gradient_end ?? '#0f172a'),
                    self::dropdownSelect('drawer_gradient_direction', 'Gradient Direction / Style', [
                        ['label' => 'Linear Top-to-Bottom', 'value' => 'top_to_bottom'],
                        ['label' => 'Linear Diagonal', 'value' => 'diagonal'],
                        ['label' => 'Radial', 'value' => 'radial'],
                    ], $company->drawer_gradient_direction ?? 'top_to_bottom'),
                ], ['color' => '#f8fafc', 'border_color' => '#e2e8f0']),
            ]),
            self::buttonPrimary('Save Store Profile', self::formSubmitAction(
                '/api/tenant/settings/profile',
                'POST',
                'Store profile updated successfully'
            ), 'save'),
        ]);
    }

    public static function receiptsView(Company $company): array
    {
        return self::screen('Receipt Prefixes & Bank Terms', [
            self::card([
                self::text('Invoice & Quote Numbering', 'title_medium', ['bold' => true]),
                self::text('Define prefix tags used when generating official customer invoices.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::textInput('invoice_prefix', 'Invoice Prefix', $company->invoice_prefix ?? 'INV-'),
                self::textInput('quotation_prefix', 'Quotation Prefix', $company->quotation_prefix ?? 'QUO-'),
            ]),
            self::card([
                self::text('Receipt Footnotes & Terms', 'title_medium', ['bold' => true]),
                self::divider(),
                self::textInput('invoice_terms', 'Invoice Terms & Conditions', $company->invoice_terms, [
                    'max_lines' => 4,
                    'keyboard_type' => 'multiline',
                ]),
                self::textInput('quote_terms', 'Quotation Terms & Conditions', $company->quote_terms, [
                    'max_lines' => 3,
                    'keyboard_type' => 'multiline',
                ]),
                self::textInput('bank_details', 'Bank Account & Settlement Details', $company->bank_details, [
                    'max_lines' => 3,
                    'keyboard_type' => 'multiline',
                ]),
            ]),
            self::buttonPrimary('Save Receipt Settings', self::formSubmitAction(
                '/api/tenant/settings/receipts',
                'POST',
                'Receipt settings updated successfully'
            ), 'save'),
        ]);
    }

    public static function financialView(Company $company): array
    {
        $otherCurrencies = $company->other_currencies ?? [];
        $currencyRows = [];
        foreach ($otherCurrencies as $oc) {
            $currencyRows[] = [
                $oc['code'] ?? '',
                $oc['symbol'] ?? '',
                (string) ($oc['exchange_rate'] ?? '1.0'),
            ];
        }

        $components = [
            self::card([
                self::text('Base Operating Currency', 'title_medium', ['bold' => true]),
                self::text('Select default currency code and decimal display parameters.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::dropdownSelect('currency', 'Base Currency Code', [
                    'USD' => 'USD - US Dollar',
                    'INR' => 'INR - Indian Rupee',
                    'EUR' => 'EUR - Euro',
                    'GBP' => 'GBP - British Pound',
                    'AED' => 'AED - UAE Dirham',
                    'SAR' => 'SAR - Saudi Riyal',
                    'CAD' => 'CAD - Canadian Dollar',
                ], $company->currency ?? 'USD'),
                self::textInput('currency_symbol', 'Currency Symbol', $company->currency_symbol ?? '$'),
                self::dropdownSelect('currency_decimals', 'Decimal Precision', [
                    '0' => '0 (e.g. 100)',
                    '2' => '2 (e.g. 100.00)',
                    '3' => '3 (e.g. 100.000)',
                    '4' => '4 (e.g. 100.0000)',
                ], (string) ($company->currency_decimals ?? 2)),
                self::dropdownSelect('currency_symbol_position', 'Symbol Placement', [
                    'prefix' => 'Prefix (e.g. $100)',
                    'suffix' => 'Suffix (e.g. 100$)',
                ], $company->currency_symbol_position ?? 'prefix'),
            ]),
        ];

        if (! empty($currencyRows)) {
            $components[] = self::card([
                self::text('Multi-Currency Exchange Rates', 'title_medium', ['bold' => true]),
                self::tableGrid(['Code', 'Symbol', 'Exchange Rate'], $currencyRows),
            ]);
        }

        $components[] = self::buttonPrimary('Save Financial Settings', self::formSubmitAction(
            '/api/tenant/settings/financial',
            'POST',
            'Financial settings updated successfully'
        ), 'save');

        return self::screen('Financial & Currency', $components);
    }

    public static function taxesView(Company $company): array
    {
        $isIndia = ($company->country === 'IN');
        $taxLabel = $company->tax_id_label ?? ($isIndia ? 'GST' : 'Tax');

        $components = [
            self::card([
                self::text('Fiscal Tax Configuration', 'title_medium', ['bold' => true]),
                self::text('Manage tax identifiers, fiscal rates, and compliance parameters.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::textInput('tax_id', 'Tax Registration ID (GSTIN/VAT)', $company->tax_id),
                self::textInput('tax_label', 'Tax Label On Receipts', $taxLabel),
                self::toggleSwitch('tax_inclusive', 'Tax Inclusive Pricing', (bool) ($company->tax_settings['inclusive'] ?? false)),
                self::toggleSwitch('show_tax_summary', 'Show Detailed Tax Breakdown on Receipts', (bool) ($company->tax_settings['show_tax_summary'] ?? true)),
            ]),
        ];

        if ($isIndia) {
            $components[] = self::card([
                self::text('India GST Sub-Components', 'title_medium', ['bold' => true]),
                self::tableGrid(['Component', 'Split', 'Authority'], [
                    ['CGST', '50%', 'Central Government'],
                    ['SGST', '50%', 'State Government'],
                    ['IGST', '100%', 'Interstate Trade'],
                ]),
            ]);
        }

        $components[] = self::buttonPrimary('Save Tax Settings', self::formSubmitAction(
            '/api/tenant/settings/taxes',
            'POST',
            'Tax settings updated successfully'
        ), 'save');

        return self::screen('Taxes & Compliance', $components);
    }

    public static function apiView(Company $company): array
    {
        $configuration = Configuration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('key', [
                'webhook_url', 'ai_catalog_enrichment', 'ai_receipt_ocr',
                'webhook_platform', 'webhook_hmac_secret',
                'outbound_webhook_url', 'outbound_webhook_secret', 'outbound_events',
                'integration_default_locale', 'integration_multilingual_payloads',
            ])
            ->pluck('value', 'key');

        $inboundWebhookUrl = url('/api/v1/integrations/webhooks/'.($company->unique_account_id ?: $company->id).'/orders');
        $platform = $configuration->get('webhook_platform', 'shopify');
        $outboundEvents = json_decode($configuration->get('outbound_events', '[]'), true) ?: [];

        return self::screen('API & Integrations', [
            self::card([
                self::text('Sanctum API Access', 'title_medium', ['bold' => true]),
                self::text('Connect external ERPs, Shopify, WooCommerce, and mobile apps securely.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::text('Active Token Status: ENABLED', 'label_medium', ['bold' => true, 'color' => '#16a34a']),
            ]),
            self::card([
                self::text('Integration Language & Localization', 'title_medium', ['bold' => true]),
                self::text('Configure language defaults and multilingual serialization for external webhooks, SMS/WhatsApp receipts, and API payloads.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::dropdownSelect('integration_default_locale', 'Default Communication Language', [
                    ['label' => 'English (en)', 'value' => 'en'],
                    ['label' => 'Hindi (hi)', 'value' => 'hi'],
                    ['label' => 'Spanish (es)', 'value' => 'es'],
                    ['label' => 'Arabic (ar)', 'value' => 'ar'],
                    ['label' => 'French (fr)', 'value' => 'fr'],
                    ['label' => 'German (de)', 'value' => 'de'],
                    ['label' => 'Portuguese (pt)', 'value' => 'pt'],
                    ['label' => 'Chinese (zh)', 'value' => 'zh'],
                ], $configuration->get('integration_default_locale', 'en')),
                self::toggleSwitch('integration_multilingual_payloads', 'Multi-Language Webhook Payloads', filter_var($configuration->get('integration_multilingual_payloads', false), FILTER_VALIDATE_BOOL)),
            ]),
            self::card([
                self::text('E-Commerce Inbound Webhooks (Shopify / WooCommerce)', 'title_medium', ['bold' => true]),
                self::text('Receive sales orders in real-time. Automatically decodes payloads, decrements inventory, creates kitchen tickets, and sends push alerts.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::textInput('inbound_webhook_url', 'Your Unique Webhook Endpoint URL', $inboundWebhookUrl, [
                    'placeholder' => $inboundWebhookUrl,
                    'read_only' => true,
                ]),
                self::dropdownSelect('webhook_platform', 'E-Commerce Platform', [
                    ['label' => 'Shopify (HMAC-SHA256)', 'value' => 'shopify'],
                    ['label' => 'WooCommerce (HMAC-SHA256)', 'value' => 'woocommerce'],
                    ['label' => 'Generic JSON / Custom Store', 'value' => 'generic'],
                ], $platform),
                self::textInput('webhook_hmac_secret', 'Webhook Secret / HMAC Key', $configuration->get('webhook_hmac_secret', ''), [
                    'placeholder' => 'Enter shared secret key for signature verification',
                ]),
            ]),
            self::card([
                self::text('Outbound Webhook Subscriptions', 'title_medium', ['bold' => true]),
                self::text('Notify your external systems and third-party gateways when POS events take place.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::textInput('outbound_webhook_url', 'Outbound Webhook URL', $configuration->get('outbound_webhook_url', $configuration->get('webhook_url', '')), [
                    'placeholder' => 'https://example.com/webhooks/pos-events',
                ]),
                self::textInput('outbound_webhook_secret', 'Outbound HMAC Secret', $configuration->get('outbound_webhook_secret', ''), [
                    'placeholder' => 'Secret used to sign outbound X-Webhook-Signature headers',
                ]),
                self::checkbox('event_order_created', 'order.created (When a new sale or order is registered)', in_array('order.created', $outboundEvents, true)),
                self::checkbox('event_order_settled', 'order.settled (When payment is completed in full)', in_array('order.settled', $outboundEvents, true)),
                self::checkbox('event_order_cancelled', 'order.cancelled (When a sale is voided or cancelled)', in_array('order.cancelled', $outboundEvents, true)),
                self::checkbox('event_stock_low_alert', 'stock.low_alert (When an item reaches or drops below minimum stock)', in_array('stock.low_alert', $outboundEvents, true)),
            ]),
            self::card([
                self::text('AI Assistant Studio Integration', 'title_medium', ['bold' => true]),
                self::text('Empower point-of-sale catalog management with AI recommendations and OCR.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::toggleSwitch('ai_catalog_enrichment', 'Enable AI Product Description & Categorization', filter_var($configuration->get('ai_catalog_enrichment', true), FILTER_VALIDATE_BOOL)),
                self::toggleSwitch('ai_receipt_ocr', 'Enable Invoice & Bill OCR Scanner', filter_var($configuration->get('ai_receipt_ocr', true), FILTER_VALIDATE_BOOL)),
            ]),
            self::buttonPrimary('Save API Integrations', self::formSubmitAction(
                '/api/tenant/settings/api',
                'POST',
                'API settings saved successfully'
            ), 'save'),
        ]);
    }

    public static function navigationView(Company $company): array
    {
        $activeMode = ModuleRegistry::resolveActiveMode($company);
        $menuStructure = TenantNavRegistry::menuStructureForMode($activeMode);
        $navConfig = $company->normalizedNavConfig();

        // Ensure default items exist if tenant customizations are missing
        if (empty($navConfig['items'])) {
            $defaultItems = [];
            $order = 0;
            foreach ($menuStructure as $section) {
                foreach ($section['items'] ?? [] as $item) {
                    $defaultItems[] = [
                        'key' => $item['key'],
                        'section' => $section['key'],
                        'label' => $item['label'] ?? $item['title'] ?? $item['key'],
                        'parent' => $item['parent'] ?? null,
                        'parent_id' => $item['parent'] ?? null,
                        'level' => ! empty($item['parent']) ? 1 : 0,
                        'order' => $order++,
                        'visible' => true,
                    ];
                }
            }
            $navConfig['items'] = $defaultItems;
        }

        return self::screen('Navigation Menu Customization', [
            [
                'type' => 'tree_builder',
                'title' => 'Menu Hierarchy & Arrangement',
                'description' => 'Drag and drop items to re-order, indent right (+30px) to nest under previous item, drag left to outdent.',
                'active_mode' => $activeMode,
                'menu_structure' => $menuStructure,
                'sections' => $menuStructure,
                'nav_config' => $navConfig,
                'items' => $navConfig['items'] ?? [],
            ],
            self::card([
                self::text('Server-Driven Navigation Structure', 'title_medium', ['bold' => true]),
                self::text('The mobile app updates its menu hierarchy dynamically based on this server schema.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::lineItemTile('Cashier & Sales', 'Point of Sale, Sales History, Quotes', 'point_of_sale'),
                self::lineItemTile('Financial Management', 'Cash Register, Receivables, Payables, Reports', 'monetization_on'),
                self::lineItemTile('Products & Inventory', 'All Products, Categories, Brands, Suppliers', 'inventory_2'),
                self::lineItemTile('Administration & Settings', 'Store Settings (Expandable Accordion)', 'settings'),
            ]),
            self::text('Use the server navigation configuration to reorder or hide entries. Changes are reflected by the next bootstrap response.', 'body_small', ['color' => '#6b7280']),
        ]);
    }

    /**
     * Plug-and-Play Generic Module View for future verticals (Pharmacy, Clinic, Laundry, Salon, etc.)
     */
    public static function moduleView(string $moduleKey, Company $company): array
    {
        $module = ModuleRegistry::getModule($moduleKey);
        $title = $module['title'] ?? ucwords(str_replace('_', ' ', $moduleKey));
        $icon = $module['icon'] ?? 'apps';
        $desc = $module['description'] ?? 'Dynamic server-driven vertical module.';

        $featureChips = [];
        foreach ($module['features'] ?? [] as $feat => $enabled) {
            if ($enabled) {
                $featureChips[] = self::badge(
                    str_replace(['has_', '_'], ['', ' '], ucwords($feat)),
                    '#16a34a',
                    'subtle'
                );
            }
        }

        $components = [
            self::card([
                self::row([
                    self::icon($icon, ['size' => 36, 'color' => '#1d4ed8']),
                    self::column([
                        self::text($title, 'title_large', ['bold' => true]),
                        self::text($desc, 'body_medium', ['color' => '#4b5563']),
                    ], ['spacing' => 4]),
                ], ['spacing' => 12]),
                ...(! empty($featureChips) ? [
                    self::divider(),
                    self::text('Module Capabilities', 'title_small', ['bold' => true]),
                    self::row($featureChips, ['spacing' => 6]),
                ] : []),
            ]),
        ];

        $routeItems = [];
        foreach (($module['routes'] ?? []) as $routeKey => $route) {
            if ($routeKey === 'cart_configuration') {
                continue;
            }

            $definition = is_array($route) ? $route : ['endpoint' => $route];
            $endpoint = (string) ($definition['endpoint'] ?? '');
            if (! str_starts_with($endpoint, '/api/')) {
                continue;
            }

            $routeItems[] = self::lineItemTile(
                (string) ($definition['title'] ?? ucwords(str_replace(['_', '-'], ' ', (string) $routeKey))),
                (string) ($definition['description'] ?? ''),
                (string) ($definition['icon'] ?? 'arrow_forward'),
                self::navigateAction($endpoint, 'dynamic_page')
            );
        }

        if ($routeItems !== []) {
            $components[] = self::card([
                self::text('Module Actions', 'title_medium', ['bold' => true]),
                self::divider(),
                ...$routeItems,
            ]);
        }

        return self::screen($title, $components);
    }

    public static function notificationsView(Company $company): array
    {
        $channels = \App\Models\CustomNotificationChannel::where('company_id', $company->id)->get();
        $channelTiles = [];
        foreach ($channels as $channel) {
            $format = strtoupper($channel->payload_format ?: 'JSON');
            $channelTiles[] = self::lineItemTile(
                $channel->name,
                "{$channel->method} ({$format}) · {$channel->url}",
                $channel->icon ?: 'sms'
            );
        }

        return self::screen('Custom Notification Channels', [
            self::card([
                self::text('Custom SMS & Unofficial WhatsApp Gateways', 'title_medium', ['bold' => true]),
                self::text('Dispatch automated notifications via generic HTTP endpoints supporting JSON, Form-Data, and Query Params.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::text('Supported Dynamic Tags: {phone}, {customer_name}, {invoice_id}, {amount}, {order_link}', 'label_medium', ['bold' => true, 'color' => '#2563eb']),
            ]),
            ...(! empty($channelTiles) ? [
                self::card([
                    self::text('Configured Notification Channels', 'title_medium', ['bold' => true]),
                    ...$channelTiles,
                ]),
            ] : []),
            self::card([
                self::text('Send Test Message', 'title_medium', ['bold' => true]),
                self::text('Test your configured notification gateway with sample variables.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::textInput('phone', 'Recipient Phone Number', $company->phone ?: '+1234567890', ['placeholder' => '+1234567890']),
                self::textInput('customer_name', 'Customer Name', 'John Doe', ['placeholder' => 'John Doe']),
                self::textInput('amount', 'Amount', '150.00', ['placeholder' => '150.00']),
            ]),
            self::buttonPrimary('Send Test Message', self::formSubmitAction(
                '/api/tenant/settings/custom-notifications/test',
                'POST',
                'Test notification triggered'
            ), 'send'),
        ]);
    }

    /**
     * Lightweight screen directory shipped by /api/app/bootstrap. Navigation
     * remains the source of visual placement; this directory describes every
     * endpoint-backed screen the current tenant may request.
     *
     * @return list<array{key: string, title: string, endpoint: string, permission: ?string}>
     */
    public static function screenDirectory(Company $company): array
    {
        $screens = [
            ['key' => 'settings-mode', 'title' => 'Store Operating Mode', 'endpoint' => '/api/tenant/views/settings-mode', 'permission' => 'settings.view'],
            ['key' => 'settings-profile', 'title' => 'Store Profile & Branding', 'endpoint' => '/api/tenant/views/settings-profile', 'permission' => 'settings.view'],
            ['key' => 'settings-receipts', 'title' => 'Receipt Prefixes & Bank Terms', 'endpoint' => '/api/tenant/views/settings-receipts', 'permission' => 'settings.view'],
            ['key' => 'settings-financial', 'title' => 'Financial & Currency', 'endpoint' => '/api/tenant/views/settings-financial', 'permission' => 'settings.view'],
            ['key' => 'settings-taxes', 'title' => 'Taxes & Compliance', 'endpoint' => '/api/tenant/views/settings-taxes', 'permission' => 'settings.view'],
            ['key' => 'settings-api', 'title' => 'API & Integrations', 'endpoint' => '/api/tenant/views/settings-api', 'permission' => 'settings.view'],
            ['key' => 'settings-navigation', 'title' => 'Navigation Menu', 'endpoint' => '/api/tenant/views/settings-navigation', 'permission' => 'settings.view'],
            ['key' => 'settings-notifications', 'title' => 'Custom Notification Gateways', 'endpoint' => '/api/tenant/views/settings-notifications', 'permission' => 'settings.view'],
        ];

        if (! Schema::hasTable('sdui_screens')) {
            return $screens;
        }

        $licensed = ModuleRegistry::availableModes($company);
        $stored = SduiScreen::query()
            ->with('module:id,slug,is_active')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($stored as $screen) {
            if ($screen->module !== null
                && (! $screen->module->is_active || ! in_array($screen->module->slug, $licensed, true))) {
                continue;
            }

            $screens[] = [
                'key' => $screen->key,
                'title' => $screen->title,
                'endpoint' => '/api/tenant/views/'.$screen->key,
                'permission' => $screen->permission,
            ];
        }

        return $screens;
    }

    public static function normalizeViewKey(string $viewKey): string
    {
        return strtolower(trim(str_replace(['_', 'views/'], ['-', ''], $viewKey)));
    }

    public static function storedScreen(string $viewKey): ?SduiScreen
    {
        if (! Schema::hasTable('sdui_screens')) {
            return null;
        }

        return SduiScreen::query()
            ->with('module')
            ->where('key', self::normalizeViewKey($viewKey))
            ->where('is_active', true)
            ->first();
    }

    public static function requiredPermission(string $viewKey, ?SduiScreen $storedScreen = null): ?string
    {
        if ($storedScreen !== null) {
            return $storedScreen->permission;
        }

        $normalized = self::normalizeViewKey($viewKey);
        if (str_starts_with($normalized, 'settings-')
            || in_array($normalized, ['mode', 'profile', 'branding', 'receipts', 'financial', 'taxes', 'api', 'api-integrations', 'navigation', 'navigation-menu', 'notifications', 'custom-notifications'], true)) {
            return 'settings.view';
        }

        return 'pos.view';
    }

    /**
     * Centralized view dispatcher: routes any view key to its declarative SDUI schema.
     */
    public static function renderView(string $viewKey, Company $company): JsonResponse
    {
        $normalized = self::normalizeViewKey($viewKey);
        $stored = self::storedScreen($normalized);

        if ($stored !== null) {
            if ($stored->module !== null
                && (! $stored->module->is_active
                    || ! in_array($stored->module->slug, ModuleRegistry::availableModes($company), true))) {
                return response()->json([
                    'success' => false,
                    'error' => 'This screen is not enabled for the current tenant.',
                ], 404);
            }

            $schema = $stored->schema;
            $schema['title'] = $schema['title'] ?? $stored->title;
            $schema['type'] = 'screen';
            $schema['key'] = $stored->key;
            $schema['schema_version'] = self::SCHEMA_VERSION;
            $schema['layout'] = $schema['layout'] ?? 'scroll_view';
            $schema['app_bar'] = $schema['app_bar'] ?? [
                'title' => $schema['title'],
                'show_back_button' => true,
                'actions' => [],
            ];
            $schema['components'] = $schema['components'] ?? [];
            $schema['fab'] = $schema['fab'] ?? null;

            return self::schemaResponse($normalized, $schema);
        }

        $schema = match ($normalized) {
            'settings-mode', 'mode' => self::modeView($company),
            'settings-profile', 'profile', 'branding' => self::profileView($company),
            'settings-receipts', 'receipts' => self::receiptsView($company),
            'settings-financial', 'financial' => self::financialView($company),
            'settings-taxes', 'taxes' => self::taxesView($company),
            'settings-api', 'api', 'api-integrations' => self::apiView($company),
            'settings-navigation', 'navigation', 'navigation-menu' => self::navigationView($company),
            'settings-notifications', 'notifications', 'custom-notifications' => self::notificationsView($company),
            default => null,
        };

        if ($schema === null) {
            $module = ModuleRegistry::find($normalized);
            if ($module === null || ! in_array($normalized, ModuleRegistry::availableModes($company), true)) {
                return response()->json([
                    'success' => false,
                    'error' => 'SDUI view not found.',
                ], 404);
            }

            $schema = self::moduleView($normalized, $company);
        }

        return self::schemaResponse($normalized, $schema);
    }

    private static function schemaResponse(string $view, array $schema): JsonResponse
    {
        $errors = app(SchemaValidator::class)->validate($schema);
        if ($errors !== []) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid SDUI schema.',
                'details' => ['schema' => $errors],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'view' => $view,
            'schema' => $schema,
        ]);
    }
}
