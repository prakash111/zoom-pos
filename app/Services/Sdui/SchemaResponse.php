<?php

namespace App\Services\Sdui;

use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Company;
use App\Models\Configuration;
use App\Models\Customer;
use App\Models\CustomNotificationChannel;
use App\Models\PharmacyBatch;
use App\Models\PharmacyPrescription;
use App\Models\Product;
use App\Models\RepairDeviceCategory;
use App\Models\RepairTicket;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalonAppointment;
use App\Models\SduiScreen;
use App\Models\TenantApiKey;
use App\Models\TenantSession;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Localization\PlatformRegionalService;
use App\Services\Modular\ModuleRegistry;
use App\Services\Navigation\TenantNavigationConfigService;
use App\Services\Navigation\TenantNavRegistry;
use Carbon\Carbon;
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
        'accordion', 'column', 'row', 'tabs', 'stepper', 'text', 'image_network',
        'badge', 'icon', 'divider', 'text_input', 'dropdown_select',
        'checkbox', 'toggle_switch', 'date_time_picker', 'color_picker', 'file_upload',
        'line_item_tile', 'table_grid', 'step_counter', 'button_primary',
        'button_outlined', 'button_danger', 'fab', 'action_sheet_trigger', 'navigation_builder', 'tree_builder',
        'wrap', 'cash_tendered_field',
    ];

    public const INPUT_TYPES = [
        'text_input', 'dropdown_select', 'checkbox', 'toggle_switch',
        'date_time_picker', 'color_picker', 'file_upload', 'step_counter',
        'cash_tendered_field',
    ];

    public const ACTION_COMPONENT_TYPES = [
        'button_primary', 'button_outlined', 'button_danger', 'fab',
    ];

    public const ACTION_TYPES = [
        'navigate', 'form_submit', 'api_post', 'open_modal', 'navigate_back', 'pop',
        'add_to_cart', 'open_remote_sheet', 'open_url', 'show_post_sale_sheet',
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

    public static function text(?string $text, string $style = 'body_medium', array $props = []): array
    {
        return array_merge([
            'type' => 'text',
            'text' => (string) ($text ?? ''),
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

    public static function badge(?string $label, ?string $color = '#10b981', string $style = 'subtle', array $props = []): array
    {
        return array_merge([
            'type' => 'badge',
            'label' => (string) ($label ?? ''),
            'color' => $color ?: '#10b981',
            'badge_style' => $style,
        ], $props);
    }

    public static function icon(?string $icon, array $props = []): array
    {
        return array_merge([
            'type' => 'icon',
            'icon' => (string) ($icon ?? 'widgets') ?: 'widgets',
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

    /**
     * A self-contained cash-tendered input that computes CHANGE DUE and
     * handles quick-cash chips entirely on the client — no network round
     * trip, no sheet reload. Writes the tendered amount into `$name` so the
     * checkout submit picks it up.
     *
     * @param  list<float|int>  $quickCash  suggested tender amounts
     * @param  array<string, mixed>  $props
     */
    public static function cashTenderedField(string $name, float $total, string $currency, float $initial, array $quickCash = [], array $props = []): array
    {
        return array_merge([
            'type' => 'cash_tendered_field',
            'name' => $name,
            'label' => 'Cash Tendered by Customer',
            'total' => round($total, 2),
            'currency' => $currency,
            'initial_value' => number_format($initial, 2, '.', ''),
            'quick_cash' => array_values(array_map(static fn ($v) => round((float) $v, 2), $quickCash)),
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
            'custom_label' => 'Choose Custom Hex Color',
            'hue_label' => 'Hue',
            'saturation_label' => 'Saturation',
            'brightness_label' => 'Brightness',
            'cancel_label' => 'Cancel',
            'apply_label' => 'Apply Color',
        ], $props);
    }

    public static function fileUpload(string $name, string $label, ?string $currentUrl, string $uploadEndpoint, array $props = []): array
    {
        return array_merge([
            'type' => 'file_upload',
            'name' => $name,
            'label' => $label,
            'current_url' => $currentUrl,
            'upload_endpoint' => $uploadEndpoint,
            'field_name' => $name,
            'select_label' => 'Choose File',
            'remove_label' => 'Remove',
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
            // System-standard forest green — every primary button renders the
            // same colour regardless of the tenant's Material seed. Callers
            // that need a different fill pass their own background_color.
            'background_color' => '#166534',
            'foreground_color' => '#ffffff',
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
            // Forest-green outline + label to match the primary buttons.
            'color' => '#15803d',
            'full_width' => $props['full_width'] ?? true,
        ], $props);
    }

    public static function buttonDanger(string $label, array $action, ?string $icon = null, array $props = []): array
    {
        return array_merge([
            'type' => 'button_danger',
            'label' => $label,
            'action' => $action,
            'icon' => $icon,
            'color' => '#dc2626',
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

    /**
     * Payload for the native `show_post_sale_sheet` action — everything the
     * Flutter client needs to drive its own bottom sheet (Preview & Print,
     * Bluetooth thermal, Share via WhatsApp, Send via Email) entirely
     * in-app. No signed web URLs: "Preview & Print" fetches
     * `/api/tenant/invoices/{id}/pdf-stream` with the normal bearer token
     * and renders it through the native PDF viewer.
     *
     * @return array<string, mixed>
     */
    public static function postSaleActionData(Sale $sale, ?string $whatsAppUrl = null): array
    {
        $sale->loadMissing(['company', 'customer']);
        $company = $sale->company;
        $currency = $company?->currency_symbol ?: '$';

        $total = round((float) ($sale->net_amount ?: $sale->total), 2);
        $tax = round((float) ($sale->tax_amount ?? 0), 2);
        $discount = round((float) ($sale->discount ?? 0), 2);
        $subtotal = round((float) $sale->total - $tax + $discount, 2);
        $paid = round((float) ($sale->paid_amount ?: $total), 2);
        $due = round((float) ($sale->due_amount ?? 0), 2);
        $taxBase = max(0.01, $subtotal - $discount);
        $taxRate = $tax > 0 ? round($tax / $taxBase * 100, 2) : 0.0;

        $lines = [];
        foreach ((array) ($sale->items ?? []) as $it) {
            if (! is_array($it)) {
                continue;
            }
            $qty = (float) ($it['quantity'] ?? $it['qty'] ?? 1);
            $unit = (float) ($it['price'] ?? $it['unit_price'] ?? 0);
            $lines[] = [
                'name' => (string) ($it['name'] ?? $it['product_name'] ?? 'Item'),
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => (float) ($it['line_total'] ?? $it['subtotal'] ?? round($qty * $unit, 2)),
            ];
        }

        $country = strtoupper(trim((string) ($company?->country ?? '')));
        $isIndia = in_array($country, ['IN', 'IND', 'INDIA'], true) || $currency === '₹';

        $publicLink = '';
        try {
            $publicLink = route('sales.public', $sale->sale_number);
        } catch (\Throwable) {
            // route helper unavailable in some contexts — omit the link
        }

        return [
            'invoice_number' => $sale->sale_number,
            'sale_id' => $sale->id,
            'customer_name' => (string) ($sale->customer?->name ?? $sale->customer_name ?? ''),
            'customer_phone' => preg_replace('/[^0-9+]/', '', (string) ($sale->customer?->phone ?? $sale->customer_phone ?? '')),
            'customer_email' => (string) ($sale->customer?->email ?? ''),
            'company_name' => (string) ($company?->trade_name ?: $company?->name ?: ''),
            'currency_symbol' => $currency,
            'total' => $total,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'tax_rate' => $taxRate,
            'tax_id' => (string) ($company?->tax_id ?? ''),
            'tax_label' => (string) ($company?->tax_id_label ?: 'Tax'),
            'is_india' => $isIndia,
            'paid_amount' => $paid,
            'due_amount' => $due,
            'pdf_endpoint' => "/api/tenant/invoices/{$sale->id}/pdf-stream",
            'share_text' => trim(sprintf(
                'Thank you for your business! Your receipt for %s%s%s',
                $sale->sale_number,
                $total > 0 ? ' ('.$currency.number_format($total, 2).')' : '',
                $publicLink !== '' ? ': '.$publicLink : ''
            )),
            'whatsapp_url' => $whatsAppUrl,
            'lines' => $lines,
        ];
    }

    /**
     * The raw `show_post_sale_sheet` action envelope returned by every
     * checkout / settle endpoint under the `post_sale_sheet` response key.
     * The Flutter client opens its native bottom sheet from this — no web
     * page is ever launched.
     *
     * @return array{action: string, data: array<string, mixed>}
     */
    public static function postSaleActionResponse(Sale $sale, ?string $whatsAppUrl = null): array
    {
        return [
            'action' => 'show_post_sale_sheet',
            'data' => self::postSaleActionData($sale, $whatsAppUrl),
        ];
    }

    /**
     * Compact post-sale summary card prepended to a settled ticket's
     * workbench view: an invoice / timestamp / status strip, a financial
     * pill row, and a single forest-green primary button that fires the
     * native `show_post_sale_sheet` action (Preview & Print, Bluetooth
     * thermal, WhatsApp, Email). No 2x2 outline grid, no signed web URLs.
     *
     * @return array<string, mixed> a ready-to-prepend `card` component
     */
    public static function postSaleActionSheet(Sale $sale, ?string $whatsAppUrl = null): array
    {
        $sale->loadMissing(['payments', 'company', 'customer']);
        $data = self::postSaleActionData($sale, $whatsAppUrl);
        $currency = $data['currency_symbol'];
        $total = (float) $data['total'];

        $advance = 0.0;
        try {
            $advance = round((float) $sale->payments->where('payment_method', 'advance_deposit')->sum('amount'), 2);
        } catch (\Throwable) {
            // payments relation unavailable — advance strip simply reads 0.00
        }
        $balancePaid = max(0, round($total - $advance, 2));
        $settledAt = optional($sale->created_at)->format('d M Y · g:i A') ?: '';

        return self::card([
            self::row([
                self::badge('#'.$sale->sale_number, '#166534', 'subtle'),
                self::text($settledAt, 'label_small', ['color' => '#64748b']),
                self::badge('PAID · DELIVERED', '#15803d', 'subtle'),
            ], ['main_axis_alignment' => 'space_between', 'cross_axis_alignment' => 'center']),
            self::divider(),
            self::wrap([
                self::badge('Total '.$currency.number_format($total, 2), '#475569', 'subtle'),
                self::badge('Advance -'.$currency.number_format($advance, 2), '#15803d', 'subtle'),
                self::badge('Balance Paid '.$currency.number_format($balancePaid, 2), '#166534', 'subtle'),
            ]),
            self::divider(),
            self::buttonPrimary('Invoice & Receipt Options', [
                'type' => 'show_post_sale_sheet',
                'data' => $data,
            ], 'receipt_long', ['background_color' => '#166534', 'border_radius' => 10]),
        ], ['color' => '#f1f5f9', 'border_color' => '#e2e8f0', 'border_radius' => 12, 'elevation' => 0]);
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

    public static function formSubmitAction(string $endpoint, string $method = 'POST', string $successToast = 'Settings saved successfully', bool $navigateBack = false, bool $reload = false, ?array $payload = null): array
    {
        $action = [
            'type' => 'form_submit',
            'endpoint' => $endpoint,
            'method' => strtoupper($method),
            'success_toast' => $successToast,
            'navigate_back' => $navigateBack,
        ];
        if ($reload) {
            $action['reload'] = true;
        }
        if ($payload !== null) {
            $action['payload'] = $payload;
        }

        return $action;
    }

    public static function apiPostAction(string $endpoint, array $payload = [], string $successToast = 'Action performed', bool $reload = false): array
    {
        $action = [
            'type' => 'api_post',
            'endpoint' => $endpoint,
            // An empty PHP array always json_encodes to `[]`, but the Flutter
            // dispatcher casts payload straight to Map<String, dynamic> and
            // throws on a JSON list — force `{}` so a no-argument action
            // (e.g. "Print / PDF") never crashes on tap.
            'payload' => $payload === [] ? (object) [] : $payload,
            'success_toast' => $successToast,
        ];
        if ($reload) {
            $action['reload'] = true;
        }

        return $action;
    }

    /**
     * @param  array<string, mixed>  $props  extra action flags, e.g.
     *                                       ['keep_parent_sheet' => true] so the SDUI client stacks this modal
     *                                       OVER the sheet that opened it instead of dismissing it, and
     *                                       ['refresh_in_place' => true, 'refresh_endpoint' => '/api/...'] so the
     *                                       parent sheet re-fetches itself in place once the modal closes.
     */
    public static function openModalAction(string $title, array $components, array $props = []): array
    {
        return array_merge([
            'type' => 'open_modal',
            'title' => $title,
            'components' => $components,
        ], $props);
    }

    /**
     * Adds an item to the client's local POS cart. Handled entirely
     * client-side (never hits the network) — used by universal POS catalog
     * cards and sheets (e.g. a pharmacy FEFO batch picker) to build up a
     * real cart before checkout.
     *
     * @param  array<string, mixed>  $item
     */
    public static function addToCartAction(array $item): array
    {
        return [
            'type' => 'add_to_cart',
            'item' => $item,
        ];
    }

    /**
     * Fetches a small component tree from $sheetEndpoint and renders it in
     * a modal bottom sheet — the remote counterpart to open_modal, used
     * when the sheet's content depends on live server data (e.g. a
     * product's current batches) rather than being known up front.
     */
    /**
     * @param  array<string, mixed>  $props  extra action flags — most notably
     *                                       ['refresh_in_place' => true], which tells the SDUI client to re-fetch
     *                                       $sheetEndpoint and rebuild the CURRENT sheet's body in place (no
     *                                       dismiss, no parent-page reload, keyboard & scroll preserved) instead
     *                                       of popping and re-opening a fresh sheet.
     */
    public static function openRemoteSheetAction(string $sheetEndpoint, string $title = '', array $props = []): array
    {
        return array_merge([
            'type' => 'open_remote_sheet',
            'sheet_endpoint' => $sheetEndpoint,
            'title' => $title,
        ], $props);
    }

    public static function popAction(): array
    {
        return [
            'type' => 'pop',
        ];
    }

    public static function openUrlAction(string $url): array
    {
        return [
            'type' => 'open_url',
            'url' => $url,
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

    /**
     * Multi-step form wizard. Each step is ['title' => ..., 'subtitle' => ...,
     * 'components' => [...]]. All steps share one form scope on the client, and
     * the final "Submit" fires $submitAction with the whole accumulated form.
     *
     * @param  list<array<string, mixed>>  $steps
     * @param  array<string, mixed>  $submitAction
     */
    public static function stepper(array $steps, array $submitAction, string $submitLabel = 'Submit', array $props = []): array
    {
        return array_merge([
            'type' => 'stepper',
            'steps' => array_values(array_map(static function (array $step): array {
                return [
                    'title' => (string) ($step['title'] ?? ''),
                    'subtitle' => isset($step['subtitle']) ? (string) $step['subtitle'] : null,
                    'icon' => $step['icon'] ?? null,
                    'components' => array_values($step['components'] ?? []),
                ];
            }, $steps)),
            'submit_action' => $submitAction,
            'submit_label' => $submitLabel,
            'next_label' => $props['next_label'] ?? 'Next',
            'back_label' => $props['back_label'] ?? 'Back',
        ], array_diff_key($props, ['next_label' => 1, 'back_label' => 1]));
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

    public static function dashboardView(Company $company): array
    {
        $items = [];
        foreach (TenantNavRegistry::getEffectiveNavForTenant($company) as $section) {
            foreach ($section['items'] ?? [] as $item) {
                if (! is_array($item) || ! empty($item['children'])) {
                    continue;
                }
                $endpoint = (string) ($item['target_endpoint'] ?? '');
                if ($endpoint === '') {
                    continue;
                }
                $items[] = self::lineItemTile(
                    (string) ($item['title'] ?? $item['label'] ?? $item['key'] ?? ''),
                    '',
                    (string) ($item['icon'] ?? 'widgets'),
                    self::navigateAction($endpoint, 'dynamic_page', (string) ($item['title'] ?? $item['label'] ?? '')),
                );
            }
        }

        return self::screen($company->trade_name ?: $company->name, [
            self::gridView($items, 2),
        ]);
    }

    public static function profileView(Company $company): array
    {
        return self::screen('Store Profile', [
            self::card([
                self::text('Store Identity', 'title_medium', ['bold' => true]),
                self::text('Configure your business trade name, tax registration number, and address.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::textInput('name', 'Business Name', $company->name, ['required' => true]),
                self::textInput('trade_name', 'Trading Name (DBA)', $company->trade_name),
                self::textInput('tax_id', 'Tax ID / GSTIN / VAT', $company->tax_id),
                self::fileUpload('logo', 'Store Logo', $company->getLogoUrl(), '/api/v1/pos/settings/profile/logo', [
                    'delete_endpoint' => '/api/v1/pos/settings/profile/logo',
                    'accept' => ['image/png', 'image/jpeg', 'image/webp'],
                    'response_url_path' => 'logo_url',
                ]),
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
                self::dropdownSelect('default_locale', 'Store Primary Language', PlatformRegionalService::languageOptions(), $company->default_locale ?: ($company->language ?: 'en')),
                self::dropdownSelect('timezone', 'Store Timezone', PlatformRegionalService::timezoneOptions(), $company->timezone ?: $company->resolveTimezone()),
            ]),
            self::buttonPrimary('Save Store Profile', self::formSubmitAction(
                '/api/tenant/settings/profile',
                'POST',
                'Store profile updated successfully'
            ), 'save'),
            self::card([
                self::text('Demo Data Reset', 'title_medium', ['bold' => true, 'color' => '#dc2626']),
                self::text('Clear all auto-seeded sample products, categories, floor plans, and sample transactions. Your store profile and configuration will remain untouched.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::buttonDanger('Clear Sample Demo Data', [
                    'type' => 'form_submit',
                    'endpoint' => '/api/tenant/demo-data',
                    'method' => 'DELETE',
                    'success_toast' => 'Sample demo data cleared successfully.',
                    'confirm_message' => 'Are you sure you want to delete all sample demo items? Real products and settings will not be affected.',
                    'reload' => true,
                ], 'delete_forever'),
            ], ['color' => '#fef2f2', 'border_color' => '#fecaca']),
        ]);
    }

    public static function brandingView(Company $company): array
    {
        return self::screen('Store Branding & Colors', [
            self::card([
                self::text('Accent Colors', 'title_medium', ['bold' => true]),
                self::divider(),
                self::colorPicker('primary_color', 'Primary Accent Color', $company->primary_color ?? '#4F46E5'),
                self::colorPicker('accent_color', 'Secondary Accent Color', $company->accent_color ?? '#D97706'),
            ]),
            self::card([
                self::text('Sidebar & Drawer Background', 'title_medium', ['bold' => true]),
                self::divider(),
                self::colorPicker('drawer_bg', 'Sidebar / Drawer Background', $company->drawer_bg ?? '#1e293b'),
                self::toggleSwitch('drawer_gradient_enabled', 'Enable Gradient Background', (bool) ($company->drawer_gradient_enabled ?? false)),
                self::colorPicker('drawer_gradient_start', 'Gradient Start Color', $company->drawer_gradient_start ?? ($company->drawer_bg ?? '#1e293b')),
                self::colorPicker('drawer_gradient_end', 'Gradient End Color', $company->drawer_gradient_end ?? '#0f172a'),
                self::dropdownSelect('drawer_gradient_direction', 'Gradient Direction', [
                    ['label' => 'Linear Top-to-Bottom', 'value' => 'top_to_bottom'],
                    ['label' => 'Linear Diagonal', 'value' => 'diagonal'],
                    ['label' => 'Radial', 'value' => 'radial'],
                ], $company->drawer_gradient_direction ?? 'top_to_bottom'),
            ]),
            self::buttonPrimary('Save Branding & Colors', self::formSubmitAction(
                '/api/tenant/settings/branding',
                'POST',
                'Branding and colors updated successfully'
            ), 'palette'),
        ]);
    }

    public static function advancedView(Company $company): array
    {
        return self::screen('Advanced & Danger Zone', [
            self::card([
                self::text('Demo Data Reset', 'title_medium', ['bold' => true, 'color' => '#dc2626']),
                self::text('Clear all auto-seeded sample products, categories, floor plans, and sample transactions. Your store profile and configuration will remain untouched.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::buttonDanger('Clear Sample Demo Data', [
                    'type' => 'form_submit',
                    'endpoint' => '/api/tenant/demo-data',
                    'method' => 'DELETE',
                    'success_toast' => 'Sample demo data cleared successfully.',
                    'confirm_message' => 'Are you sure you want to delete all sample demo items? Real products and settings will not be affected.',
                    'reload' => true,
                ], 'delete_forever'),
            ], ['color' => '#fef2f2', 'border_color' => '#fecaca']),
        ]);
    }

    public static function restaurantTablesView(Company $company): array
    {
        return self::screen('Floor Plan & Tables', [
            self::card([
                self::row([
                    self::icon('table_restaurant', ['color' => '#4d7c0f', 'size' => 28]),
                    self::column([
                        self::text('Floor Plan & Table Management', 'title_medium', ['bold' => true]),
                        self::text('Live dining tables, occupancy status, and active order tickets.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::badge('Restaurant Operations Active', '#4d7c0f', 'subtle'),
            ]),
        ]);
    }

    public static function restaurantKdsView(Company $company): array
    {
        return self::screen('Kitchen Display (KDS)', [
            self::card([
                self::row([
                    self::icon('soup_kitchen', ['color' => '#0284c7', 'size' => 28]),
                    self::column([
                        self::text('Kitchen Display System (KDS)', 'title_medium', ['bold' => true]),
                        self::text('Live preparation queue and kitchen order ticket dispatching.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::badge('KDS Queue Connected', '#0284c7', 'subtle'),
            ]),
        ]);
    }

    // =========================================================================
    // Pharmacy POS Module Views
    // =========================================================================

    public static function pharmacyPosView(Company $company): array
    {
        return PosScreenBuilder::pharmacyPosScreen($company);
    }

    public static function pharmacyBatchesView(Company $company): array
    {
        $batches = PharmacyBatch::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with('product')
            ->orderBy('expiry_date', 'asc')
            ->limit(25)
            ->get();

        $today = now()->toDateString();
        $in30Days = now()->addDays(30)->toDateString();
        $in90Days = now()->addDays(90)->toDateString();

        $totalBatches = PharmacyBatch::withoutGlobalScope('company')->where('company_id', $company->id)->count();
        $expiredCount = PharmacyBatch::withoutGlobalScope('company')->where('company_id', $company->id)->where('expiry_date', '<', $today)->count();
        $critical30Count = PharmacyBatch::withoutGlobalScope('company')->where('company_id', $company->id)->whereBetween('expiry_date', [$today, $in30Days])->count();
        $nearExpiryCount = PharmacyBatch::withoutGlobalScope('company')->where('company_id', $company->id)->whereBetween('expiry_date', [$today, $in90Days])->count();
        $safeCount = PharmacyBatch::withoutGlobalScope('company')->where('company_id', $company->id)->where('expiry_date', '>', $in90Days)->count();

        $batchCards = [];
        foreach ($batches as $b) {
            $days = $b->days_until_expiry;
            $statusLabel = $days < 0 ? "EXPIRED ({$days}d)" : ($days <= 90 ? "EXPIRING SOON ({$days}d left)" : "SAFE ({$days}d)");

            $batchCards[] = self::card([
                self::row([
                    self::icon('medication', ['color' => $b->expiry_color, 'size' => 24]),
                    self::column([
                        self::text("Batch #{$b->batch_number}", 'title_medium', ['bold' => true]),
                        self::text(($b->product?->name ?? 'Unknown Medicine').' ('.($b->product?->generic_name ?: 'Standard').')', 'body_small', ['color' => '#64748b']),
                    ]),
                    self::badge($statusLabel, $b->expiry_color, 'subtle'),
                ]),
                self::divider(),
                self::row([
                    self::text("Stock: {$b->stock_qty} units", 'label_large', ['bold' => true]),
                    self::text('Cost: '.number_format((float) $b->cost_price, 2), 'body_small'),
                    self::text('MRP: '.number_format((float) $b->selling_price, 2), 'body_small', ['color' => '#059669', 'bold' => true]),
                    self::text("Exp: {$b->expiry_date?->format('Y-m-d')} · Rack: ".($b->rack_location ?: 'Unassigned'), 'body_small', ['color' => '#64748b']),
                ]),
                self::divider(),
                self::row([
                    self::buttonOutlined('Print Barcode', self::openUrlAction("/api/tenant/pharmacy/batches/{$b->id}/barcode"), 'qr_code_2', ['full_width' => false]),
                    self::buttonOutlined('Audit Stock', self::openModalAction("Audit Batch #{$b->batch_number}", [
                        self::text('Medicine: '.($b->product?->name ?? 'Item'), 'body_medium', ['bold' => true]),
                        self::text("Recorded Quantity: {$b->stock_qty} units", 'body_small', ['color' => '#64748b']),
                        self::divider(),
                        self::textInput('batch_id', 'Batch ID Number', (string) $b->id),
                        self::textInput('new_stock_qty', 'New Audited Quantity', (string) $b->stock_qty),
                        self::textInput('reason', 'Adjustment Reason', 'Physical inventory verification'),
                        self::divider(),
                        self::buttonPrimary('Save Stock Adjustment', self::formSubmitAction(
                            '/api/tenant/pharmacy/batches/adjust',
                            'POST',
                            'Batch stock adjusted.',
                            reload: true
                        ), 'tune'),
                    ]), 'tune', ['full_width' => false]),
                    self::buttonDanger('Vendor Return', self::openModalAction("Vendor Return #{$b->batch_number}", [
                        self::text('Medicine: '.($b->product?->name ?? 'Item'), 'body_medium', ['bold' => true]),
                        self::text("Batch #{$b->batch_number} · Available: {$b->stock_qty}", 'body_small', ['color' => '#64748b']),
                        self::divider(),
                        self::textInput('batch_id', 'Batch ID Number', (string) $b->id),
                        self::textInput('new_stock_qty', 'Remaining Quantity After Return', '0'),
                        self::textInput('reason', 'Return Reason (e.g. Expired / Damaged / Distributor Recall)', 'Vendor Return'),
                        self::divider(),
                        self::buttonDanger('Confirm Return', self::formSubmitAction(
                            '/api/tenant/pharmacy/batches/return',
                            'POST',
                            'Vendor return recorded.',
                            reload: true
                        ), 'keyboard_return'),
                    ]), 'keyboard_return', ['full_width' => false]),
                ]),
            ]);
        }

        $productOptions = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->pluck('name', 'id')
            ->toArray();

        if (empty($productOptions)) {
            $productOptions = ['1' => 'General Medicine Item'];
        }

        return self::screen('Batch & Expiry Manager', [
            self::card([
                self::row([
                    self::icon('medication', ['color' => '#059669', 'size' => 28]),
                    self::column([
                        self::text('Medicine Batches & Expiry Tracking', 'title_medium', ['bold' => true]),
                        self::text('Track batch numbers, manufacturing dates, and upcoming expirations under FEFO.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::wrap([
                    self::badge("Total Batches: {$totalBatches}", '#0284c7', 'subtle'),
                    self::badge("Safe (>90d): {$safeCount}", '#10b981', 'subtle'),
                    self::badge("Expiring (≤90d): {$nearExpiryCount}", '#f59e0b', 'subtle'),
                    self::badge("Critical (≤30d): {$critical30Count}", '#ea580c', 'subtle'),
                    self::badge("Expired: {$expiredCount}", '#ef4444', 'subtle'),
                ]),
            ]),

            self::gridView([
                self::card([
                    self::row([
                        self::icon('inventory_2', ['color' => '#0284c7', 'size' => 22]),
                        self::text((string) $totalBatches, 'headline_small', ['bold' => true, 'color' => '#0284c7']),
                    ]),
                    self::text('Total Batches', 'label_large', ['bold' => true]),
                    self::text('Registered medicine lots', 'body_small', ['color' => '#64748b']),
                ]),
                self::card([
                    self::row([
                        self::icon('verified', ['color' => '#10b981', 'size' => 22]),
                        self::text((string) $safeCount, 'headline_small', ['bold' => true, 'color' => '#10b981']),
                    ]),
                    self::text('Safe Batches', 'label_large', ['bold' => true]),
                    self::text('> 90 days validity', 'body_small', ['color' => '#64748b']),
                ]),
                self::card([
                    self::row([
                        self::icon('notification_important', ['color' => '#ea580c', 'size' => 22]),
                        self::text((string) $critical30Count, 'headline_small', ['bold' => true, 'color' => '#ea580c']),
                    ]),
                    self::text('Expiring in 30 Days', 'label_large', ['bold' => true]),
                    self::text('Urgent FEFO attention', 'body_small', ['color' => '#64748b']),
                ]),
                self::card([
                    self::row([
                        self::icon('block', ['color' => '#ef4444', 'size' => 22]),
                        self::text((string) $expiredCount, 'headline_small', ['bold' => true, 'color' => '#ef4444']),
                    ]),
                    self::text('Expired Lots', 'label_large', ['bold' => true]),
                    self::text('Do not dispense to patients', 'body_small', ['color' => '#64748b']),
                ]),
            ], 2),

            self::accordionGroup('Register New Medicine Batch', [
                self::dropdownSelect('product_id', 'Select Medicine / Drug', $productOptions),
                self::textInput('batch_number', 'Batch Number (e.g. BTH-2026-908)', ''),
                self::textInput('rack_location', 'Rack / Shelf Location', ''),
                self::dateTimePicker('manufacturing_date', 'Manufacturing Date', mode: 'date'),
                self::dateTimePicker('expiry_date', 'Expiry Date (FEFO Sorted)', mode: 'date'),
                self::textInput('cost_price', 'Cost Price (Per Unit)', '0.00'),
                self::textInput('selling_price', 'Selling Price (MRP / Unit)', '0.00'),
                self::textInput('stock_qty', 'Initial Received Stock Quantity', '100'),
                self::textInput('alert_days_before_expiry', 'Alert Days Before Expiry', '90'),
                self::divider(),
                self::buttonPrimary('Save Batch to Inventory', self::formSubmitAction(
                    '/api/tenant/pharmacy/batches',
                    'POST',
                    'Batch registered and stock updated.',
                    reload: true
                ), 'add_circle'),
            ]),

            self::card([
                self::text('Batch Stock Adjustment / Vendor Return', 'label_large', ['bold' => true]),
                self::text('Adjust damaged stock or record returns to pharmaceutical distributors.', 'body_small', ['color' => '#64748b']),
                self::divider(),
                self::textInput('batch_id', 'Batch ID Number', ''),
                self::textInput('new_stock_qty', 'New Audited Quantity', '0'),
                self::textInput('reason', 'Adjustment Reason (Damaged / Return / Discrepancy)', 'Audit verification'),
                self::divider(),
                self::row([
                    self::buttonOutlined('Adjust Stock', self::formSubmitAction(
                        '/api/tenant/pharmacy/batches/adjust',
                        'POST',
                        'Batch stock adjusted.',
                        reload: true
                    ), 'tune'),
                    self::buttonDanger('Vendor Return', self::formSubmitAction(
                        '/api/tenant/pharmacy/batches/return',
                        'POST',
                        'Vendor return recorded.',
                        reload: true
                    ), 'keyboard_return'),
                ]),
            ]),

            self::card([
                self::text('Active Medicine Batches (FEFO Order)', 'title_medium', ['bold' => true]),
                self::column(! empty($batchCards) ? $batchCards : [
                    self::text('No active batches registered yet. Use the form above to add a new batch.', 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    public static function pharmacyPrescriptionsView(Company $company): array
    {
        $statusFilter = request('status', 'all');
        $query = PharmacyPrescription::withoutGlobalScope('company')
            ->where('company_id', $company->id);

        if (in_array($statusFilter, ['pending', 'dispensed'], true)) {
            $query->where('status', $statusFilter);
        }

        $prescriptions = $query->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $totalRx = PharmacyPrescription::withoutGlobalScope('company')->where('company_id', $company->id)->count();
        $pendingRx = PharmacyPrescription::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', 'pending')->count();
        $dispensedRx = PharmacyPrescription::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', 'dispensed')->count();

        $rxCards = [];
        foreach ($prescriptions as $rx) {
            $isPending = $rx->status === 'pending';
            $badgeColor = $isPending ? '#f59e0b' : '#10b981';

            $rxCards[] = self::card([
                self::row([
                    self::icon('receipt_long', ['color' => $badgeColor, 'size' => 24]),
                    self::column([
                        self::text("Rx #{$rx->prescription_number}", 'title_medium', ['bold' => true]),
                        self::text("Patient: {$rx->patient_name} | Doctor: {$rx->doctor_name}", 'body_small', ['color' => '#64748b']),
                    ]),
                    self::badge(strtoupper($rx->status), $badgeColor, 'subtle'),
                ]),
                self::divider(),
                self::text('Diagnosis / Notes: '.($rx->diagnosis ?: ($rx->notes ?: 'General prescription')), 'body_small'),
                self::text('Date: '.($rx->prescription_date?->format('Y-m-d') ?? 'Today'), 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                $isPending ? self::buttonPrimary('Load Prescription into POS', self::openRemoteSheetAction(
                    "/api/tenant/pharmacy/prescriptions/{$rx->id}/checkout-sheet",
                    "Dispense Rx #{$rx->prescription_number}"
                ), 'point_of_sale') : self::badge('Dispensed Successfully', '#10b981', 'subtle'),
            ]);
        }

        return self::screen('Prescriptions Queue', [
            self::card([
                self::row([
                    self::icon('receipt_long', ['color' => '#059669', 'size' => 28]),
                    self::column([
                        self::text('Prescriptions & Patient Queue', 'title_medium', ['bold' => true]),
                        self::text('Doctor referrals, prescription intake, and controlled drug verification.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::row([
                    self::badge("Total Prescriptions: {$totalRx}", '#0284c7', 'subtle'),
                    self::badge("Pending: {$pendingRx}", '#f59e0b', 'subtle'),
                    self::badge("Dispensed: {$dispensedRx}", '#10b981', 'subtle'),
                ]),
            ]),

            self::row([
                $statusFilter === 'all'
                    ? self::buttonPrimary("All ({$totalRx})", self::navigateAction('/api/tenant/views/pharmacy-prescriptions?status=all', title: 'All Prescriptions'), 'receipt_long', ['full_width' => false])
                    : self::buttonOutlined("All ({$totalRx})", self::navigateAction('/api/tenant/views/pharmacy-prescriptions?status=all', title: 'All Prescriptions'), 'receipt_long', ['full_width' => false]),
                $statusFilter === 'pending'
                    ? self::buttonPrimary("Pending ({$pendingRx})", self::navigateAction('/api/tenant/views/pharmacy-prescriptions?status=pending', title: 'Pending Prescriptions'), 'hourglass_top', ['full_width' => false])
                    : self::buttonOutlined("Pending ({$pendingRx})", self::navigateAction('/api/tenant/views/pharmacy-prescriptions?status=pending', title: 'Pending Prescriptions'), 'hourglass_top', ['full_width' => false]),
                $statusFilter === 'dispensed'
                    ? self::buttonPrimary("Dispensed ({$dispensedRx})", self::navigateAction('/api/tenant/views/pharmacy-prescriptions?status=dispensed', title: 'Dispensed Prescriptions'), 'task_alt', ['full_width' => false])
                    : self::buttonOutlined("Dispensed ({$dispensedRx})", self::navigateAction('/api/tenant/views/pharmacy-prescriptions?status=dispensed', title: 'Dispensed Prescriptions'), 'task_alt', ['full_width' => false]),
            ]),

            self::accordionGroup('New Prescription Intake', [
                self::textInput('patient_name', 'Patient Full Name', ''),
                self::textInput('patient_phone', 'Patient Contact Phone #', ''),
                self::textInput('age', 'Age (Optional)', '', ['keyboard_type' => 'number']),
                self::dropdownSelect('gender', 'Gender (Optional)', [
                    ['label' => 'Not specified', 'value' => ''],
                    ['label' => 'Female', 'value' => 'female'],
                    ['label' => 'Male', 'value' => 'male'],
                    ['label' => 'Other', 'value' => 'other'],
                ], ''),
                self::textInput('allergies', 'Known Allergies (Optional)', ''),
                self::textInput('doctor_name', 'Prescribing Doctor Name', ''),
                self::textInput('doctor_registration_no', 'Doctor Registration / License #', ''),
                self::dateTimePicker('prescription_date', 'Prescription Date', mode: 'date'),
                self::textInput('diagnosis', 'Diagnosis / Clinical Indications', ''),
                self::textInput('notes', 'Prescribed Medicines, Dosages & Frequency', ''),
                self::textInput('dosage_duration_days', 'Days Supply / Dosage Duration', '30', ['keyboard_type' => 'number']),
                self::textInput('rx_image_url', 'Scanned Rx Image URL (Optional)', ''),
                self::divider(),
                self::buttonPrimary('Save to Prescription Queue', self::formSubmitAction(
                    '/api/tenant/pharmacy/prescriptions',
                    'POST',
                    'Prescription logged to queue.',
                    reload: true
                ), 'post_add'),
            ]),

            self::card([
                self::text('Prescription Queue', 'title_medium', ['bold' => true]),
                self::column(! empty($rxCards) ? $rxCards : [
                    self::text('No prescriptions currently logged matching this status. Use the form above to add a new Rx.', 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    // =========================================================================
    // Repair & Technician POS Module Views
    // =========================================================================

    public static function repairDashboardView(Company $company): array
    {
        $allTickets = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['parts', 'technician:id,name'])
            ->orderByDesc('created_at')
            ->limit(60)
            ->get();

        $activeCount = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->whereIn('status', ['received', 'active'])->count();
        $diagCount = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', 'diagnosing')->count();
        $partsCount = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', 'waiting_parts')->count();
        $inProgCount = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', 'in_progress')->count();
        $repairedCount = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->whereIn('status', ['ready', 'repaired'])->count();
        $deliveredCount = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->where('status', 'delivered')->count();

        $renderKanbanCard = function (RepairTicket $t, string $stage) {
            $actions = [];

            if (in_array($stage, ['active', 'received'])) {
                $actions[] = self::buttonOutlined('Start Diagnostics', self::apiPostAction(
                    "/api/tenant/repair/tickets/{$t->id}/status",
                    ['status' => 'diagnosing'],
                    'Ticket moved to Diagnosing',
                    reload: true
                ), 'biotech', ['full_width' => false]);
            } elseif ($stage === 'diagnosing') {
                $actions[] = self::buttonOutlined('In Progress', self::apiPostAction(
                    "/api/tenant/repair/tickets/{$t->id}/status",
                    ['status' => 'in_progress'],
                    'Ticket moved to In Progress',
                    reload: true
                ), 'construction', ['full_width' => false]);
                $actions[] = self::buttonOutlined('Wait Parts', self::apiPostAction(
                    "/api/tenant/repair/tickets/{$t->id}/status",
                    ['status' => 'waiting_parts'],
                    'Status set to Waiting Parts',
                    reload: true
                ), 'hourglass_top', ['full_width' => false]);
            } elseif ($stage === 'waiting_parts') {
                $actions[] = self::buttonOutlined('Parts Ready / Resume', self::apiPostAction(
                    "/api/tenant/repair/tickets/{$t->id}/status",
                    ['status' => 'in_progress'],
                    'Ticket resumed to In Progress',
                    reload: true
                ), 'construction', ['full_width' => false]);
            } elseif ($stage === 'in_progress') {
                $actions[] = self::buttonPrimary('Mark Repaired & Ready', self::apiPostAction(
                    "/api/tenant/repair/tickets/{$t->id}/status",
                    ['status' => 'ready'],
                    'Marked as Repaired & Ready',
                    reload: true
                ), 'task_alt', ['full_width' => false]);
            } elseif (in_array($stage, ['repaired', 'ready'])) {
                $actions[] = self::buttonPrimary('Deliver & Settle', self::openRemoteSheetAction(
                    "/api/tenant/repair/tickets/{$t->id}/checkout-sheet",
                    "Deliver & Settle #{$t->ticket_number}"
                ), 'payments', ['full_width' => false]);
            }

            $actions[] = self::buttonOutlined('Workbench', self::navigateAction(
                "/api/tenant/views/repair-detail?ticket_id={$t->id}",
                title: "Workbench #{$t->ticket_number}"
            ), 'build', ['full_width' => false]);

            return self::card([
                self::row([
                    self::badge("#{$t->ticket_number}", '#0284c7', 'subtle'),
                    self::badge(strtoupper(str_replace('_', ' ', (string) ($t->status ?? 'received'))), $t->status_color, 'subtle'),
                    self::badge((string) ($t->priority ?? 'normal'), $t->priority_color, 'subtle'),
                ], ['main_axis_alignment' => 'space_between']),
                self::row([
                    self::icon('handyman', ['color' => $t->status_color, 'size' => 20]),
                    self::text(trim("{$t->brand} {$t->model}") ?: 'Unspecified Device', 'title_medium', ['bold' => true]),
                ]),
                self::row([
                    self::icon('person', ['color' => '#64748b', 'size' => 16]),
                    self::text((string) ($t->customer_name ?: 'Walk-in Customer'), 'body_small', ['bold' => true]),
                    self::badge((string) ($t->customer_phone ?: 'No phone on file'), '#475569', 'subtle'),
                ]),
                self::divider(),
                self::card([
                    self::text("Fault: \"{$t->issue_description}\"", 'body_medium', ['italic' => true]),
                ], ['color' => '#f8fafc', 'border_color' => '#e2e8f0']),
                self::row([
                    self::text('Total: '.number_format((float) $t->total_amount, 2), 'label_large', ['bold' => true]),
                    self::text('Advance: '.number_format((float) $t->advance_paid, 2), 'body_small', ['color' => '#64748b']),
                    self::text('Due: '.number_format((float) $t->balance_due, 2), 'label_large', ['color' => $t->balance_due > 0 ? '#dc2626' : '#10b981', 'bold' => true]),
                ], ['main_axis_alignment' => 'space_between']),
                self::divider(),
                self::wrap($actions),
            ], ['padding' => 14, 'border_radius' => 14]);
        };

        $stageActiveCards = $allTickets->filter(fn ($t) => in_array($t->status, ['received', 'active']))->map(fn ($t) => $renderKanbanCard($t, 'received'))->values()->all();
        $stageDiagCards = $allTickets->where('status', 'diagnosing')->map(fn ($t) => $renderKanbanCard($t, 'diagnosing'))->values()->all();
        $stagePartsCards = $allTickets->where('status', 'waiting_parts')->map(fn ($t) => $renderKanbanCard($t, 'waiting_parts'))->values()->all();
        $stageInProgCards = $allTickets->where('status', 'in_progress')->map(fn ($t) => $renderKanbanCard($t, 'in_progress'))->values()->all();
        $stageRepairedCards = $allTickets->filter(fn ($t) => in_array($t->status, ['ready', 'repaired']))->map(fn ($t) => $renderKanbanCard($t, 'ready'))->values()->all();
        $stageDeliveredCards = $allTickets->where('status', 'delivered')->map(fn ($t) => $renderKanbanCard($t, 'delivered'))->values()->all();

        return self::screen('Repair Workbench', [
            self::card([
                self::row([
                    self::icon('handyman', ['color' => '#0284c7', 'size' => 28]),
                    self::column([
                        self::text('Repair & Technician Workbench', 'title_medium', ['bold' => true]),
                        self::text('Hardware diagnostics, spare parts billing, labor calculation, and POS settlement.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::wrap([
                    self::badge("1. Received: {$activeCount}", '#0284c7', 'subtle'),
                    self::badge("2. Diagnosing: {$diagCount}", '#8b5cf6', 'subtle'),
                    self::badge("3. Waiting Parts: {$partsCount}", '#f59e0b', 'subtle'),
                    self::badge("4. In Progress: {$inProgCount}", '#3b82f6', 'subtle'),
                    self::badge("5. Repaired & Ready: {$repairedCount}", '#10b981', 'subtle'),
                    self::badge("6. Delivered: {$deliveredCount}", '#059669', 'subtle'),
                ]),
            ]),

            self::gridView([
                self::card([
                    self::row([
                        self::icon('pending_actions', ['color' => '#0284c7', 'size' => 22]),
                        self::text((string) $activeCount, 'headline_small', ['bold' => true, 'color' => '#0284c7']),
                    ]),
                    self::text('Active Intake', 'label_large', ['bold' => true]),
                    self::text('Awaiting diagnostics', 'body_small', ['color' => '#64748b']),
                ]),
                self::card([
                    self::row([
                        self::icon('biotech', ['color' => '#8b5cf6', 'size' => 22]),
                        self::text((string) $diagCount, 'headline_small', ['bold' => true, 'color' => '#8b5cf6']),
                    ]),
                    self::text('Diagnosing', 'label_large', ['bold' => true]),
                    self::text('Under bench testing', 'body_small', ['color' => '#64748b']),
                ]),
                self::card([
                    self::row([
                        self::icon('hourglass_top', ['color' => '#f59e0b', 'size' => 22]),
                        self::text((string) $partsCount, 'headline_small', ['bold' => true, 'color' => '#f59e0b']),
                    ]),
                    self::text('Waiting Parts', 'label_large', ['bold' => true]),
                    self::text('Parts on order / hold', 'body_small', ['color' => '#64748b']),
                ]),
                self::card([
                    self::row([
                        self::icon('task_alt', ['color' => '#10b981', 'size' => 22]),
                        self::text((string) $repairedCount, 'headline_small', ['bold' => true, 'color' => '#10b981']),
                    ]),
                    self::text('Repaired & Ready', 'label_large', ['bold' => true]),
                    self::text('Ready for pickup & settlement', 'body_small', ['color' => '#64748b']),
                ]),
            ], 2),

            self::card([
                self::text('Workshop Operations & Navigation', 'label_large', ['bold' => true]),
                self::divider(),
                self::lineItemTile('New Intake Ticket', 'Check in a device, record specs & print tag', 'add_task', self::navigateAction('/api/tenant/views/repair-create-ticket', title: 'New Repair Ticket')),
                self::lineItemTile('Repair Ticket Register', 'Complete register of all customer tickets & status', 'receipt_long', self::navigateAction('/api/tenant/views/repair-tickets', title: 'Repair Ticket Register')),
                self::lineItemTile('My Assigned Jobs', 'Technician workbench for active diagnostics & status', 'engineering', self::navigateAction('/api/tenant/views/repair-my-jobs', title: 'Assigned Jobs')),
                self::lineItemTile('Inventory & Device Categories', 'Centralized product, hardware, and parts classification', 'sell', self::navigateAction('/api/tenant/views/categories', title: 'Categories')),
            ]),

            self::buttonPrimary('Quick Device Intake', self::navigateAction('/api/tenant/views/repair-create-ticket', title: 'New Repair Ticket'), 'add_task'),

            self::card([
                self::row([
                    self::icon('view_kanban', ['color' => '#0284c7', 'size' => 24]),
                    self::column([
                        self::text('6-Stage Workbench Kanban', 'title_medium', ['bold' => true]),
                        self::text('Live ticket workflow progression across all workshop stages.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
            ]),

            self::accordionGroup("1. Received / Intake ({$activeCount})", ! empty($stageActiveCards) ? $stageActiveCards : [
                self::text('No tickets in Received / Intake stage.', 'body_small', ['color' => '#64748b']),
            ], ['initially_expanded' => $activeCount > 0]),

            self::accordionGroup("2. Under Diagnostics ({$diagCount})", ! empty($stageDiagCards) ? $stageDiagCards : [
                self::text('No tickets under diagnostics.', 'body_small', ['color' => '#64748b']),
            ], ['initially_expanded' => $diagCount > 0]),

            self::accordionGroup("3. Waiting on Parts ({$partsCount})", ! empty($stagePartsCards) ? $stagePartsCards : [
                self::text('No tickets waiting for spare parts.', 'body_small', ['color' => '#64748b']),
            ], ['initially_expanded' => $partsCount > 0]),

            self::accordionGroup("4. In Progress ({$inProgCount})", ! empty($stageInProgCards) ? $stageInProgCards : [
                self::text('No tickets currently in active repair.', 'body_small', ['color' => '#64748b']),
            ], ['initially_expanded' => $inProgCount > 0]),

            self::accordionGroup("5. Repaired & Ready for Pickup ({$repairedCount})", ! empty($stageRepairedCards) ? $stageRepairedCards : [
                self::text('No repaired tickets waiting for pickup.', 'body_small', ['color' => '#64748b']),
            ], ['initially_expanded' => $repairedCount > 0]),

            self::accordionGroup("6. Delivered & Settled ({$deliveredCount})", ! empty($stageDeliveredCards) ? $stageDeliveredCards : [
                self::text('No delivered tickets in this workshop view.', 'body_small', ['color' => '#64748b']),
            ], ['initially_expanded' => false]),
        ]);
    }

    public static function repairCreateTicketView(Company $company): array
    {
        $categories = Category::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            foreach (RepairDeviceCategory::defaultPresets() as $preset) {
                Category::firstOrCreate([
                    'company_id' => $company->id,
                    'name' => $preset['name'],
                ], [
                    'type' => 'device',
                    'color' => '#0284c7',
                    'description' => $preset['description'] ?? null,
                    'metadata' => [
                        'identifier_type' => $preset['identifier_type'] ?? 'Serial / IMEI',
                        'brands' => $preset['brands'] ?? [],
                        'checklist_items' => $preset['checklist_items'] ?? [],
                    ],
                    'active' => true,
                    'is_demo' => false,
                ]);
            }
            $categories = Category::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('active', true)
                ->orderBy('name')
                ->get();
        }

        $categoryOptions = [];
        foreach ($categories as $cat) {
            $categoryOptions[] = [
                'label' => $cat->name,
                'value' => (string) $cat->id,
            ];
        }
        $firstCatId = ! empty($categoryOptions) ? $categoryOptions[0]['value'] : '';

        $categoryBadges = [];
        foreach ($categories as $cat) {
            $categoryBadges[] = self::badge($cat->name, $cat->color ?: '#0284c7', 'subtle');
        }

        $customers = Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->limit(100)
            ->get();

        $customerOptions = [
            ['label' => '➕ Walk-in / New Customer (type below)', 'value' => ''],
        ];
        foreach ($customers as $c) {
            $phonePart = $c->phone ? " ({$c->phone})" : '';
            $customerOptions[] = [
                'label' => "{$c->name}{$phonePart}",
                'value' => (string) $c->id,
            ];
        }

        $checkOptions = [
            ['label' => 'Pass', 'value' => 'pass'],
            ['label' => 'Fail', 'value' => 'fail'],
            ['label' => 'Not Tested', 'value' => 'not_tested'],
        ];

        return self::screen('New Repair Ticket', [
            // Compact intro strip — no card chrome so the wizard sits close to
            // the app bar (matches the standard 12/16px content inset).
            self::container([
                self::row([
                    self::icon('add_task', ['color' => '#0284c7', 'size' => 20]),
                    self::column([
                        self::text('Device Intake & Job Creation', 'label_large', ['bold' => true]),
                        self::text('Complete each step, then create the ticket & print the tag.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
            ], ['padding' => [4, 0, 4, 8]]),

            self::stepper([
                // Step 1 — Customer
                [
                    'title' => 'Customer',
                    'subtitle' => 'Select an existing client from the CRM directory or register a walk-in customer.',
                    'components' => [
                        self::buttonOutlined('+ Quick Add New Customer', self::openModalAction('Register New Customer', [
                            self::text('Register a new client in the core CRM database.', 'body_small', ['color' => '#64748b']),
                            self::textInput('name', 'Customer Full Name *', ''),
                            self::textInput('phone', 'Phone Number *', '', ['keyboard_type' => 'phone']),
                            self::textInput('email', 'Email Address (Optional)', '', ['keyboard_type' => 'email']),
                            self::textInput('address', 'Street / Billing Address (Optional)', ''),
                            self::textInput('city', 'City (Optional)', ''),
                            self::divider(),
                            self::buttonPrimary('Save Customer', self::formSubmitAction(
                                '/api/tenant/customers',
                                'POST',
                                'Customer registered successfully.',
                                reload: true
                            ), 'person_add'),
                        ]), 'person_add'),
                        self::dropdownSelect('customer_id', 'Search & Select Existing Customer', $customerOptions, ''),
                        self::textInput('customer_name', 'Customer Full Name (leave blank for a Walk-in Customer)', ''),
                        self::textInput('customer_phone', 'Contact Phone Number (for WhatsApp / SMS updates)', '', ['keyboard_type' => 'phone']),
                    ],
                ],

                // Step 2 — Device
                [
                    'title' => 'Device Specifications',
                    'subtitle' => 'Pick the hardware category and record the device identifiers.',
                    'components' => [
                        self::buttonOutlined('+ Add Category', self::openModalAction('Create Device Category', [
                            self::text('Register a new category in core inventory categories.', 'body_small', ['color' => '#64748b']),
                            self::textInput('name', 'Category Name * (e.g. Drone, Wearable)', ''),
                            self::textInput('code', 'Category Code / SKU Prefix (Optional)', ''),
                            self::textInput('description', 'Description (Optional)', ''),
                            self::textInput('type', 'Category Type', 'device', ['read_only' => true]),
                            self::divider(),
                            self::buttonPrimary('Save Category', self::formSubmitAction(
                                '/api/tenant/categories',
                                'POST',
                                'Device category created successfully.',
                                reload: true
                            ), 'add_circle'),
                        ]), 'add'),
                        self::dropdownSelect('device_category_id', 'Device Category *', $categoryOptions, $firstCatId, ['required' => true]),
                        self::textInput('brand', 'Brand (e.g. Apple, Samsung, Dell, HP)', ''),
                        self::textInput('model', 'Model Name / Number (e.g. iPhone 14 Pro, Galaxy S23)', ''),
                        self::textInput('serial_or_imei', 'Serial Number or IMEI (Optional)', ''),
                        self::textInput('passcode_or_pattern', 'Device Screen Lock Passcode / Pattern', ''),
                        self::wrap($categoryBadges),
                    ],
                ],

                // Step 3 — Diagnosis & Estimate
                [
                    'title' => 'Fault & Estimate',
                    'subtitle' => 'Describe the reported problem and the commercial terms.',
                    'components' => [
                        self::textInput('issue_description', 'Customer Reported Defect / Fault Description *', '', ['required' => true, 'max_lines' => 3]),
                        self::textInput('physical_condition_notes', 'Physical Condition (Scratches, Dents, Cracks)', '', ['max_lines' => 2]),
                        self::dropdownSelect('priority', 'Repair Priority Level', [
                            ['label' => 'Normal Priority', 'value' => 'normal'],
                            ['label' => 'Low Priority', 'value' => 'low'],
                            ['label' => 'High Priority', 'value' => 'high'],
                            ['label' => 'Urgent / Express Service', 'value' => 'urgent'],
                        ], 'normal'),
                        self::textInput('estimated_cost', 'Estimated Repair Cost', '0.00', ['keyboard_type' => 'number']),
                        self::textInput('diagnostic_fee', 'Upfront Diagnostic Fee', '0.00', ['keyboard_type' => 'number']),
                        self::textInput('advance_paid', 'Advance Deposit Paid', '0.00', ['keyboard_type' => 'number']),
                    ],
                ],

                // Step 4 — Inspection Checklist
                [
                    'title' => 'Intake Inspection Checklist',
                    'subtitle' => 'Verify the working state of common components before disassembly.',
                    'components' => [
                        self::dropdownSelect('check_power', '1. Power On / Boot Up State', $checkOptions, 'pass'),
                        self::dropdownSelect('check_display', '2. Display & Touchscreen', $checkOptions, 'pass'),
                        self::dropdownSelect('check_cameras', '3. Front & Back Cameras', $checkOptions, 'pass'),
                        self::dropdownSelect('check_charging', '4. Charging Port & Battery', $checkOptions, 'pass'),
                        self::dropdownSelect('check_speakers', '5. Audio, Mic & Speakers', $checkOptions, 'pass'),
                        self::dropdownSelect('check_battery', '6. Battery Health & State', $checkOptions, 'pass'),
                    ],
                ],
            ], self::formSubmitAction(
                '/api/tenant/repair/tickets',
                'POST',
                'Repair intake ticket created successfully.',
                reload: true
            ), 'Create Intake Ticket & Print Tag'),
        ]);
    }

    public static function repairTicketsView(Company $company): array
    {
        $tickets = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['parts', 'technician:id,name'])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $ticketCards = [];
        foreach ($tickets as $t) {
            $ticketCards[] = self::card([
                self::row([
                    self::badge("#{$t->ticket_number}", '#0284c7', 'subtle'),
                    self::badge(strtoupper(str_replace('_', ' ', (string) ($t->status ?? 'received'))), $t->status_color, 'subtle'),
                    self::badge((string) ($t->priority ?? 'normal'), $t->priority_color, 'subtle'),
                ], ['main_axis_alignment' => 'space_between']),
                self::row([
                    self::icon('receipt_long', ['color' => $t->status_color, 'size' => 20]),
                    self::text(trim("{$t->brand} {$t->model}") ?: 'Unspecified Device', 'title_medium', ['bold' => true]),
                ]),
                self::row([
                    self::icon('person', ['color' => '#64748b', 'size' => 16]),
                    self::text((string) ($t->customer_name ?: 'Walk-in Customer'), 'body_small', ['bold' => true]),
                    self::badge((string) ($t->customer_phone ?: 'No phone on file'), '#475569', 'subtle'),
                ]),
                self::divider(),
                self::card([
                    self::text("Defect: \"{$t->issue_description}\"", 'body_medium', ['italic' => true]),
                ], ['color' => '#f8fafc', 'border_color' => '#e2e8f0']),
                self::row([
                    self::text('Total: '.number_format((float) $t->total_amount, 2), 'label_large', ['bold' => true]),
                    self::text('Advance: '.number_format((float) $t->advance_paid, 2), 'body_small', ['color' => '#64748b']),
                    self::text('Due: '.number_format((float) $t->balance_due, 2), 'label_large', ['color' => $t->balance_due > 0 ? '#dc2626' : '#10b981', 'bold' => true]),
                ], ['main_axis_alignment' => 'space_between']),
                self::divider(),
                // One dedicated action per card: the Workbench owns parts,
                // labor and settlement via its own "Open Parts & Labor
                // Checkout" drawer. No separate repair-checkout catalog.
                self::buttonPrimary('Open Workbench', self::navigateAction("/api/tenant/views/repair-detail?ticket_id={$t->id}", title: "Workbench #{$t->ticket_number}"), 'build'),
            ], ['padding' => 14, 'border_radius' => 14]);
        }

        return self::screen('Repair Ticket Register', [
            self::card([
                self::row([
                    self::icon('receipt_long', ['color' => '#0284c7', 'size' => 28]),
                    self::column([
                        self::text('Complete Repair Ticket Register', 'title_medium', ['bold' => true]),
                        self::text('Filter and manage lifecycle from device check-in to final pickup.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
            ]),

            self::card([
                self::text('Search Tickets', 'label_large', ['bold' => true]),
                self::textInput('ticket_search', 'Search Ticket #, Customer Name, Phone, or Serial/IMEI', ''),
            ]),

            self::column(! empty($ticketCards) ? $ticketCards : [
                self::card([
                    self::text('No repair tickets found in register.', 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    public static function repairMyJobsView(Company $company): array
    {
        $tickets = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereIn('status', ['active', 'diagnosing', 'waiting_parts', 'in_progress', 'repaired'])
            ->with(['parts'])
            ->orderByDesc('priority')
            ->limit(20)
            ->get();

        $jobCards = [];
        foreach ($tickets as $t) {
            $jobCards[] = self::card([
                self::row([
                    self::icon('engineering', ['color' => $t->status_color, 'size' => 24]),
                    self::column([
                        self::text("#{$t->ticket_number} • {$t->brand} {$t->model}", 'title_medium', ['bold' => true]),
                        self::text("Category: {$t->device_type} | Serial/IMEI: ".($t->serial_or_imei ?: 'N/A'), 'body_small', ['color' => '#64748b']),
                    ]),
                    self::badge(strtoupper((string) ($t->status ?? 'received')), $t->status_color, 'subtle'),
                ]),
                self::divider(),
                self::card([
                    self::text("Issue: \"{$t->issue_description}\"", 'body_medium', ['italic' => true]),
                    self::text('Lock/Passcode: '.($t->passcode_or_pattern ?: 'None (Unlocked)'), 'body_small', ['color' => '#dc2626', 'bold' => true]),
                ], ['color' => '#f8fafc', 'border_color' => '#e2e8f0']),
                self::divider(),
                self::buttonPrimary('Mark Repaired & Ready', self::apiPostAction(
                    "/api/tenant/repair/tickets/{$t->id}/status",
                    ['status' => 'repaired'],
                    'Marked as Repaired & Ready',
                    reload: true
                ), 'task_alt'),
                self::row([
                    self::buttonOutlined('Diagnose', self::apiPostAction(
                        "/api/tenant/repair/tickets/{$t->id}/status",
                        ['status' => 'diagnosing'],
                        'Status set to Diagnosing',
                        reload: true
                    ), 'biotech'),
                    self::buttonOutlined('Wait Parts', self::apiPostAction(
                        "/api/tenant/repair/tickets/{$t->id}/status",
                        ['status' => 'waiting_parts'],
                        'Status set to Waiting Parts',
                        reload: true
                    ), 'hourglass_top'),
                ]),
                self::buttonOutlined('Open Workbench', self::navigateAction("/api/tenant/views/repair-detail?ticket_id={$t->id}", title: "Workbench #{$t->ticket_number}"), 'build'),
            ]);
        }

        return self::screen('Technician Assigned Jobs', [
            self::card([
                self::row([
                    self::icon('engineering', ['color' => '#0284c7', 'size' => 28]),
                    self::column([
                        self::text('Technician Active Workbench', 'title_medium', ['bold' => true]),
                        self::text('Quick status transition and diagnostics for open assigned hardware repairs.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
            ]),

            self::column(! empty($jobCards) ? $jobCards : [
                self::card([
                    self::text('No pending jobs on the workbench.', 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    public static function repairDetailView(Company $company): array
    {
        $ticketId = request('ticket_id');
        $query = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['parts', 'technician:id,name', 'finalSale.customer', 'finalSale.company']);

        $ticket = $ticketId ? $query->find($ticketId) : $query->orderByDesc('created_at')->first();

        if (! $ticket) {
            return self::screen('Repair Workbench', [
                self::card([
                    self::text('No active repair ticket selected.', 'title_medium', ['bold' => true]),
                    self::text('Please select a ticket from the register or create a new intake ticket.', 'body_small', ['color' => '#64748b']),
                    self::divider(),
                    self::buttonPrimary('Create New Ticket', self::navigateAction('/api/tenant/views/repair-create-ticket', title: 'New Repair Ticket'), 'add_task'),
                ]),
            ]);
        }

        $partsCards = [];
        foreach ($ticket->parts as $p) {
            $partsCards[] = self::row([
                self::text("• {$p->part_name} (x{$p->quantity})", 'body_medium'),
                self::text(number_format((float) $p->subtotal, 2), 'body_medium', ['bold' => true]),
            ]);
        }

        $checklistItems = [];
        $rawChecklist = (array) ($ticket->inspection_checklist ?? []);
        foreach ($rawChecklist as $c) {
            $itemName = is_array($c) ? ($c['item_name'] ?? $c['name'] ?? 'Checklist Item') : (string) $c;
            $status = is_array($c) ? ($c['status'] ?? 'pending') : 'pending';
            $statusColor = match ($status) {
                'pass' => '#10b981',
                'fail' => '#ef4444',
                default => '#64748b',
            };
            $checklistItems[] = self::row([
                self::text($itemName, 'body_small', ['expanded' => true, 'max_lines' => 2]),
                self::badge(strtoupper($status), $statusColor, 'subtle'),
            ], ['main_axis_alignment' => 'space_between', 'cross_axis_alignment' => 'center']);
        }

        // Once the ticket is settled & handed over, lead with the native
        // Post-Sale Action Sheet so the cashier can print / share the invoice
        // without the app ever bouncing out to a browser login.
        $postSale = [];
        if ($ticket->status === RepairTicket::STATUS_DELIVERED && $ticket->finalSale) {
            $waUrl = null;
            try {
                $waUrl = app(InvoiceDeliveryService::class)
                    ->generateInvoiceWhatsAppUrl($ticket->finalSale);
            } catch (\Throwable) {
                // A missing WhatsApp config must not hide the rest of the sheet.
            }
            $postSale[] = self::postSaleActionSheet($ticket->finalSale, $waUrl);
        }

        return self::screen("Workbench: #{$ticket->ticket_number}", array_merge($postSale, [
            self::card([
                self::row([
                    self::icon('handyman', ['color' => $ticket->status_color, 'size' => 28]),
                    self::column([
                        self::text("Ticket #{$ticket->ticket_number}", 'title_large', ['bold' => true]),
                        self::text(trim("{$ticket->brand} {$ticket->model}").' ('.($ticket->device_type ?: 'Device').')', 'body_medium', ['color' => '#64748b']),
                    ]),
                    self::badge(strtoupper((string) ($ticket->status ?? 'received')), $ticket->status_color, 'subtle'),
                ]),
                self::divider(),
                self::row([
                    self::text("Customer: {$ticket->customer_name}", 'body_medium', ['bold' => true]),
                    self::text("Phone: {$ticket->customer_phone}", 'body_small'),
                ]),
                self::text('Hardware Serial / IMEI: '.($ticket->serial_or_imei ?: 'N/A'), 'body_small', ['color' => '#64748b']),
                self::text('Passcode / Unlock Pattern: '.($ticket->passcode_or_pattern ?: 'None'), 'body_small', ['color' => '#dc2626']),
            ]),

            self::card([
                self::text('Reported Defect / Issue', 'label_large', ['bold' => true]),
                self::text($ticket->issue_description ?: 'No issue description recorded.', 'body_medium'),
                self::divider(),
                self::text('Physical Condition & Housing Notes', 'label_large', ['bold' => true]),
                self::text($ticket->physical_condition_notes ?: 'No pre-existing damages noted.', 'body_small', ['color' => '#64748b']),
            ]),

            self::card([
                self::text('Intake Diagnostic Checklist', 'title_medium', ['bold' => true]),
                self::column(! empty($checklistItems) ? $checklistItems : [
                    self::text('No checklist verified at intake.', 'body_small', ['color' => '#64748b']),
                ]),
            ]),

            self::accordionGroup('Installed Spare Parts & Consumables', [
                self::column(! empty($partsCards) ? $partsCards : [
                    self::text('No replacement parts billed yet.', 'body_small', ['color' => '#64748b']),
                ]),
                self::divider(),
                self::text('Add Replacement Part / Material', 'label_large', ['bold' => true]),
                self::textInput('part_name', 'Part / Component Name', ''),
                self::textInput('unit_price', 'Customer Part Price', '0.00'),
                self::textInput('quantity', 'Quantity', '1'),
                self::buttonPrimary('Add Part to Ticket', self::formSubmitAction(
                    "/api/tenant/repair/tickets/{$ticket->id}/parts",
                    'POST',
                    'Part added to ticket.',
                    reload: true
                ), 'add_circle'),
            ]),

            self::card([
                self::text('Labor Fee & Financial Breakdown', 'title_medium', ['bold' => true]),
                self::row([
                    self::text('Labor Fee:', 'body_medium'),
                    self::text(number_format((float) $ticket->labor_fee, 2), 'body_medium', ['bold' => true]),
                ]),
                self::row([
                    self::text('Parts Subtotal:', 'body_medium'),
                    self::text(number_format((float) $ticket->parts_cost, 2), 'body_medium', ['bold' => true]),
                ]),
                ...((float) $ticket->diagnostic_fee > 0 ? [self::row([
                    self::text('Diagnostic Fee:', 'body_medium'),
                    self::text(number_format((float) $ticket->diagnostic_fee, 2), 'body_medium', ['bold' => true]),
                ])] : []),
                self::divider(),
                self::row([
                    self::text('Total Ticket Bill:', 'title_medium', ['bold' => true]),
                    self::text(number_format((float) $ticket->total_amount, 2), 'title_medium', ['bold' => true, 'color' => '#0284c7']),
                ]),
                self::row([
                    self::text('Advance Deposit Paid:', 'body_small'),
                    self::text(number_format((float) $ticket->advance_paid, 2), 'body_small'),
                ]),
                self::row([
                    self::text('Remaining Balance Due:', 'title_medium', ['bold' => true, 'color' => '#dc2626']),
                    self::text(number_format((float) $ticket->balance_due, 2), 'title_medium', ['bold' => true, 'color' => '#dc2626']),
                ]),
                self::divider(),
                self::textInput('labor_fee', 'Update Technician Labor Fee', (string) $ticket->labor_fee),
                self::buttonOutlined('Update Labor Fee', self::formSubmitAction(
                    "/api/tenant/repair/tickets/{$ticket->id}/labor",
                    'POST',
                    'Labor fee updated.',
                    reload: false
                ), 'build'),
            ]),

            self::card([
                self::text('Final Delivery & POS Settlement', 'title_medium', ['bold' => true]),
                self::text('Record customer payment, complete handover, and generate retail POS receipt.', 'body_small', ['color' => '#64748b']),
                self::divider(),
                self::wrap([
                    self::badge('Parts + Labor', '#0284c7', 'subtle'),
                    self::badge('Advance Deducted', '#10b981', 'subtle'),
                    self::badge('Tax Receipt', '#7c3aed', 'subtle'),
                ]),
                self::divider(),
                self::buttonPrimary('Open Parts & Labor Checkout', self::openRemoteSheetAction(
                    "/api/tenant/repair/tickets/{$ticket->id}/checkout-sheet",
                    "Checkout Repair #{$ticket->ticket_number}"
                ), 'point_of_sale'),
            ]),
        ]));
    }

    public static function repairCategoriesView(Company $company): array
    {
        $hasSort = Schema::hasColumn('categories', 'sort_order');
        $categories = RepairDeviceCategory::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->when($hasSort, fn ($q) => $q->orderBy('sort_order')->orderBy('name'), fn ($q) => $q->orderBy('name'))
            ->get();

        if ($categories->isEmpty()) {
            foreach (RepairDeviceCategory::defaultPresets() as $preset) {
                RepairDeviceCategory::create(array_merge($preset, [
                    'company_id' => $company->id,
                    'tenant_id' => $company->id,
                    'is_active' => true,
                    'is_demo' => false,
                ]));
            }
            $categories = RepairDeviceCategory::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->when($hasSort, fn ($q) => $q->orderBy('sort_order')->orderBy('name'), fn ($q) => $q->orderBy('name'))
                ->get();
        }

        $categoryCards = [];
        foreach ($categories as $cat) {
            $brandBadges = array_map(
                fn ($b) => self::badge((string) $b, '#0284c7', 'subtle'),
                array_slice((array) ($cat->brands ?? []), 0, 8)
            );
            $checklistBadges = array_map(
                fn ($c) => self::badge((string) $c, '#10b981', 'subtle'),
                array_slice((array) ($cat->checklist_items ?? []), 0, 8)
            );

            $categoryCards[] = self::card([
                self::row([
                    self::icon($cat->icon ?: 'devices', ['color' => '#0284c7', 'size' => 24]),
                    self::column([
                        self::text($cat->name, 'title_medium', ['bold' => true]),
                        self::text("Identifier: {$cat->identifier_type} • ".count((array) ($cat->brands ?? [])).' brands', 'body_small', ['color' => '#64748b']),
                    ]),
                    self::badge($cat->is_active ? 'ACTIVE' : 'INACTIVE', $cat->is_active ? '#10b981' : '#ef4444', 'subtle'),
                ]),
                self::divider(),
                self::text('Supported Brands / Manufacturers:', 'label_large', ['bold' => true]),
                self::wrap($brandBadges),
                self::divider(),
                self::text('Intake Inspection Checklist Points:', 'label_large', ['bold' => true]),
                self::wrap($checklistBadges),
            ]);
        }

        return self::screen('Device Categories & Specs', [
            self::card([
                self::row([
                    self::icon('category', ['color' => '#0284c7', 'size' => 28]),
                    self::column([
                        self::text('Hardware Categories & Checklists', 'title_medium', ['bold' => true]),
                        self::text('Define supported device types, brand catalogs, diagnostic checklist points, and hardware identifiers.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::row([
                    self::badge('Total Categories: '.count($categories), '#0284c7', 'subtle'),
                    self::badge('Custom Specs Active', '#10b981', 'subtle'),
                ]),
            ]),

            self::accordionGroup('Add New Device Category', [
                self::textInput('name', 'Category Name', '', ['placeholder' => 'e.g. Smart Watch / Wearable, Drone, POS Terminal']),
                self::textInput('identifier_type', 'Hardware Identifier Name', 'Serial Number', ['placeholder' => 'e.g. IMEI / Serial Number, MAC Address, Service Tag']),
                self::textInput('brands', 'Supported Brands (comma-separated)', '', ['placeholder' => 'e.g. Apple, Samsung, Garmin, Fitbit, Other']),
                self::textInput('checklist_items', 'Intake Inspection Points (comma-separated)', '', ['placeholder' => 'e.g. Power On, Touch Screen, Sensors, Battery, Charging Port']),
                self::textInput('common_issues', 'Common Faults & Symptoms (comma-separated)', '', ['placeholder' => 'e.g. Broken Screen, Battery Draining, Sensor Error']),
                self::textInput('description', 'Description & Workshop Notes', ''),
                self::divider(),
                self::buttonPrimary('Save Category to Workshop', self::formSubmitAction(
                    '/api/tenant/repair/categories',
                    'POST',
                    'Device category created successfully.',
                    reload: true
                ), 'add_circle'),
            ], ['initially_expanded' => false]),

            self::card([
                self::text('Registered Device Categories', 'title_medium', ['bold' => true]),
                self::column(! empty($categoryCards) ? $categoryCards : [
                    self::text('No categories configured yet.', 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    public static function categoriesView(Company $company): array
    {
        $categories = Category::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        $activeCount = $categories->where('active', true)->count();
        $totalCount = $categories->count();

        $categoryCards = [];
        foreach ($categories as $cat) {
            $typeLabel = match ($cat->type) {
                'device' => 'Device Model',
                'spare_part' => 'Spare Part',
                'service' => 'Service / Labor',
                default => 'Retail Product',
            };
            $typeColor = match ($cat->type) {
                'device' => '#0284c7',
                'spare_part' => '#f59e0b',
                'service' => '#8b5cf6',
                default => '#10b981',
            };

            $categoryCards[] = self::card([
                self::row([
                    self::icon('category', ['color' => $cat->color ?: '#4f46e5', 'size' => 24]),
                    self::column([
                        self::text($cat->name, 'title_medium', ['bold' => true]),
                        self::text($cat->description ?: 'Central inventory & catalog classification', 'body_small', ['color' => '#64748b']),
                    ]),
                    self::badge($cat->active ? 'ACTIVE' : 'INACTIVE', $cat->active ? '#10b981' : '#ef4444', 'subtle'),
                ]),
                self::divider(),
                self::row([
                    self::badge($typeLabel, $typeColor, 'subtle'),
                    self::text("Products / Items: {$cat->products_count}", 'body_small', ['bold' => true, 'color' => '#475569']),
                ], ['main_axis_alignment' => 'space_between']),
                self::divider(),
                self::row([
                    self::buttonDanger('Delete', self::formSubmitAction(
                        "/api/tenant/categories/{$cat->id}",
                        'DELETE',
                        'Category deleted.',
                        reload: true
                    ), ['full_width' => false]),
                ], ['main_axis_alignment' => 'end']),
            ], ['padding' => 14, 'border_radius' => 14]);
        }

        return self::screen('Categories', [
            self::card([
                self::row([
                    self::icon('sell', ['color' => '#4f46e5', 'size' => 28]),
                    self::column([
                        self::text('Products & Inventory Categories', 'title_medium', ['bold' => true]),
                        self::text('Centralized categories for retail stock, spare parts, repair devices, and services.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::row([
                    self::badge("Total Categories: {$totalCount}", '#4f46e5', 'subtle'),
                    self::badge("Active: {$activeCount}", '#10b981', 'subtle'),
                ]),
            ]),

            self::accordionGroup('+ Add New Category', [
                self::textInput('name', 'Category Name', '', ['placeholder' => 'e.g. Gaming Consoles, Screens, Wearables']),
                self::dropdownSelect('type', 'Classification / Module Context', [
                    ['label' => 'Standard Retail Product', 'value' => 'retail'],
                    ['label' => 'Repair Device Hardware (Intake)', 'value' => 'device'],
                    ['label' => 'Spare Part / Component', 'value' => 'spare_part'],
                    ['label' => 'Service / Diagnostic Labor', 'value' => 'service'],
                ], 'retail'),
                self::textInput('description', 'Description (Optional)', '', ['placeholder' => 'Category specifications or notes']),
                self::divider(),
                self::buttonPrimary('Save Category', self::formSubmitAction(
                    '/api/tenant/categories',
                    'POST',
                    'Category created successfully.',
                    reload: true
                ), 'add_circle'),
            ], ['initially_expanded' => $totalCount === 0]),

            self::column(! empty($categoryCards) ? $categoryCards : [
                self::card([
                    self::text('No categories created yet. Click "+ Add New Category" above to create your first category.', 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    public static function serviceCalendarView(Company $company): array
    {
        $timezone = $company->resolveTimezone();
        $selectedDate = request('date', now($timezone)->toDateString());
        $day = Carbon::parse($selectedDate, $timezone);
        // The calendar is an optional enhancement to the counter POS. During
        // a rolling deploy the appointments table may briefly lag behind the
        // application code; render an empty calendar instead of a 500/blank
        // screen until migrations have caught up.
        $appointments = Schema::hasTable('salon_appointments')
            ? SalonAppointment::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->whereBetween('starts_at', [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()])
                ->with(['service:id,name,duration_minutes,sale_price', 'specialist:id,name'])
                ->orderBy('starts_at')
                ->get()
            : collect();
        $services = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->whereNotNull('duration_minutes')
            ->orderBy('name')
            ->get(['id', 'name', 'duration_minutes', 'sale_price']);
        $specialists = User::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('is_specialist', true)
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name']);
        $currency = $company->currency_symbol ?: '$';

        $appointmentCards = [];
        foreach ($appointments as $appointment) {
            $localStart = $appointment->starts_at->copy()->setTimezone($timezone);
            $localEnd = $appointment->ends_at->copy()->setTimezone($timezone);
            $statusColor = match ($appointment->status) {
                'checked_in' => '#0284c7',
                'completed' => '#10b981',
                'cancelled', 'no_show' => '#ef4444',
                default => '#7c3aed',
            };
            $floorStatus = match ($appointment->status) {
                'scheduled' => 'WAITING',
                'checked_in' => 'CHECKED IN',
                'in_progress' => 'IN CHAIR',
                'completed' => $appointment->sale_id ? 'COMPLETED' : 'COMPLETED / UNPAID',
                default => strtoupper(str_replace('_', ' ', $appointment->status)),
            };
            $actions = [];
            if ($appointment->status === 'scheduled') {
                $actions[] = self::buttonOutlined('Check In', self::apiPostAction(
                    "/api/tenant/salon/appointments/{$appointment->id}/status",
                    ['status' => 'checked_in'],
                    'Client checked in.',
                    reload: true
                ), 'how_to_reg');
            }
            if (in_array($appointment->status, ['scheduled', 'checked_in', 'in_progress'], true)
                || ($appointment->status === 'completed' && ! $appointment->sale_id)) {
                $actions[] = self::buttonPrimary('Settle Bill / Checkout', self::openRemoteSheetAction(
                    "/api/tenant/salon/checkout-sheet?appointment_id={$appointment->id}",
                    "Settle Appointment #{$appointment->id}"
                ), 'payments');
            }

            $appointmentCards[] = self::card([
                self::row([
                    self::container([
                        self::column([
                            self::text($localStart->format('g:i'), 'title_medium', ['bold' => true, 'color' => '#7c3aed', 'max_lines' => 1]),
                            self::text($localStart->format('A'), 'body_small', ['color' => '#64748b', 'max_lines' => 1]),
                        ], ['cross_axis_alignment' => 'center']),
                    ], ['width' => 64, 'flexible' => false, 'padding' => 8, 'color' => '#f5f3ff', 'border_radius' => 10]),
                    self::column([
                        self::text($appointment->service?->name ?? 'Salon Service', 'title_medium', ['bold' => true, 'max_lines' => 2]),
                        self::text('Client: '.$appointment->customer_name, 'body_small', ['color' => '#475569', 'max_lines' => 2]),
                        self::text('Staff: '.($appointment->specialist?->name ?? 'Unassigned'), 'body_small', ['color' => '#64748b', 'max_lines' => 2]),
                        self::text('Duration: '.$localStart->format('g:i A').' – '.$localEnd->format('g:i A'), 'body_small', ['color' => '#94a3b8', 'max_lines' => 2]),
                    ], ['expanded' => true, 'spacing' => 2]),
                    self::column([
                        self::badge($floorStatus, $statusColor, 'subtle', ['max_width' => 124]),
                        ...($appointment->chair_label ? [self::badge('Chair: '.$appointment->chair_label, '#475569', 'subtle', ['max_width' => 124])] : []),
                        ...((float) $appointment->advance_paid > 0 ? [
                            self::badge("Advance: {$currency}".number_format((float) $appointment->advance_paid, 2), '#059669', 'subtle', ['max_width' => 124]),
                        ] : []),
                    ], ['flexible' => false, 'cross_axis_alignment' => 'end', 'spacing' => 4]),
                ], ['spacing' => 8, 'cross_axis_alignment' => 'start']),
                ! empty($actions) ? self::wrap($actions) : self::badge('Appointment closed', $statusColor, 'subtle'),
            ]);
        }

        $serviceOptions = $services->map(fn (Product $service) => [
            'label' => "{$service->name} · {$service->duration_minutes} min · {$currency}".number_format((float) $service->sale_price, 2),
            'value' => (string) $service->id,
        ])->all();
        $specialistOptions = $specialists->map(fn ($specialist) => [
            'label' => $specialist->name,
            'value' => (string) $specialist->id,
        ])->all();
        $timeOptions = [];
        for ($minutes = 9 * 60; $minutes < 20 * 60; $minutes += 30) {
            $time = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            $timeOptions[] = ['label' => Carbon::createFromFormat('H:i', $time)->format('g:i A'), 'value' => $time];
        }

        return self::screen('Service Booking Calendar', [
            self::card([
                self::row([
                    self::icon('event_available', ['color' => '#7c3aed', 'size' => 28]),
                    self::column([
                        self::text('Service Appointments Calendar', 'title_medium', ['bold' => true]),
                        self::text("{$day->format('l, M j')} · {$timezone} · technician time-slot booking", 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::wrap([
                    self::badge('Appointments: '.$appointments->count(), '#7c3aed', 'subtle'),
                    self::badge('Checked In: '.$appointments->where('status', 'checked_in')->count(), '#0284c7', 'subtle'),
                    self::badge('Available Specialists: '.$specialists->count(), '#10b981', 'subtle'),
                ]),
            ]),
            self::accordionGroup('Book Appointment / Reserve Time Slot', [
                self::dropdownSelect('service_id', 'Service & Duration', $serviceOptions),
                self::dropdownSelect('specialist_id', 'Stylist / Specialist', $specialistOptions),
                self::dateTimePicker('appointment_date', 'Appointment Date', $day->toDateString(), 'date'),
                self::dropdownSelect('appointment_time', 'Start Time', $timeOptions, '09:00'),
                self::textInput('customer_name', 'Client Name', ''),
                self::textInput('customer_phone', 'Client Phone', '', ['keyboard_type' => 'phone']),
                self::textInput('advance_paid', 'Advance Deposit Amount (Optional)', '0.00', ['keyboard_type' => 'decimal']),
                self::dropdownSelect('deposit_payment_method', 'Advance Deposit Payment Method', [
                    ['label' => 'Cash', 'value' => 'cash'],
                    ['label' => 'Card', 'value' => 'card'],
                    ['label' => 'UPI / QR', 'value' => 'upi'],
                    ['label' => 'Bank Transfer', 'value' => 'bank_transfer'],
                ], 'cash'),
                self::textInput('notes', 'Booking Notes', '', ['max_lines' => 2]),
                self::buttonPrimary('Confirm Appointment', self::formSubmitAction(
                    '/api/tenant/salon/appointments',
                    'POST',
                    'Appointment booked successfully.',
                    reload: true
                ), 'event_available'),
            ], ['initially_expanded' => $appointments->isEmpty()]),
            self::card([
                self::text('Daily Appointment Timeline', 'title_medium', ['bold' => true]),
                self::column($appointmentCards ?: [
                    self::text('No appointments booked for this date. Use the booking panel above to reserve a specialist.', 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    public static function serviceStylistsView(Company $company): array
    {
        return PosScreenBuilder::specialistRosterScreen($company);
    }

    public static function restaurantPosView(Company $company): array
    {
        return UniversalPosBuilder::restaurantPosScreen($company);
    }

    public static function diningHistoryView(Company $company): array
    {
        return self::screen('KOT Register & Live Orders', [
            self::card([
                self::row([
                    self::icon('receipt_long', ['color' => '#4d7c0f', 'size' => 28]),
                    self::column([
                        self::text('Kitchen Order Tickets & Dining History', 'title_medium', ['bold' => true]),
                        self::text('Track active table tickets, kitchen dispatches, and completed dining tabs.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::badge('KOT Register Active', '#4d7c0f', 'subtle'),
            ]),
        ]);
    }

    public static function serviceOrdersView(Company $company): array
    {
        $currency = $company->currency_symbol ?: '$';
        $services = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->whereNotNull('duration_minutes')
            ->orderBy('name')
            ->get();
        $cards = $services->map(fn (Product $service) => self::card([
            self::row([
                self::icon('spa', ['color' => '#7c3aed', 'size' => 24]),
                self::badge("{$service->duration_minutes} min", '#7c3aed', 'subtle'),
            ], ['main_axis_alignment' => 'space_between']),
            self::text($service->name, 'title_medium', ['bold' => true]),
            self::text($service->description ?: 'Professional salon service', 'body_small', ['color' => '#64748b']),
            self::row([
                self::text($currency.number_format((float) $service->sale_price, 2), 'title_medium', ['bold' => true, 'color' => '#166534']),
                self::buttonPrimary('Sell / Book', self::navigateAction('/api/tenant/views/salon-pos', title: 'Salon & Service POS'), 'add_shopping_cart'),
            ], ['main_axis_alignment' => 'space_between']),
        ]))->all();

        return self::screen('Service Catalog & Rates', [
            self::card([
                self::row([
                    self::icon('spa', ['color' => '#7c3aed', 'size' => 28]),
                    self::column([
                        self::text('Service Catalog & Appointment Bookings', 'title_medium', ['bold' => true]),
                        self::text('Service packages, durations, tiered pricing, and active bookings.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::wrap([
                    self::badge('Services: '.$services->count(), '#7c3aed', 'subtle'),
                    self::badge('Duration-based scheduling active', '#10b981', 'subtle'),
                ]),
            ]),
            self::gridView($cards ?: [self::text('No timed services configured yet.', 'body_medium', ['color' => '#64748b'])], 2),
        ]);
    }

    public static function salonPosView(Company $company): array
    {
        return UniversalPosBuilder::salonPosScreen($company);
    }

    public static function retailPosView(Company $company): array
    {
        return UniversalPosBuilder::retailPosScreen($company);
    }

    public static function repairPosView(Company $company): array
    {
        return UniversalPosBuilder::repairPosScreen($company);
    }

    public static function posView(Company $company): array
    {
        $mode = $company->operating_mode ?? $company->pos_mode ?? 'retail';

        return UniversalPosBuilder::posScreenForModule($mode, $company);
    }

    public static function salesView(Company $company): array
    {
        $sales = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['customer', 'payments'])
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $currency = $company->currency_symbol ?: '$';
        $totalSales = $sales->sum(fn ($s) => (float) $s->total);
        $totalPaid = $sales->sum(fn ($s) => (float) ($s->paid_amount ?: $s->total));
        $totalDue = $sales->sum(fn ($s) => (float) ($s->due_amount ?: 0));

        $saleCards = [];
        foreach ($sales as $s) {
            $isPaid = ($s->payment_status === 'paid' || $s->status === 'completed') && (float) $s->due_amount <= 0;
            $isPartial = (float) $s->due_amount > 0 && (float) $s->paid_amount > 0;
            $isVoid = in_array($s->status, ['voided', 'cancelled'], true);

            $statusBadge = $isVoid
                ? self::badge('VOIDED', '#64748b', 'subtle')
                : ($isPaid
                    ? self::badge('PAID', '#10b981', 'subtle')
                    : ($isPartial
                        ? self::badge('PARTIAL DUE', '#f59e0b', 'subtle')
                        : self::badge('UNPAID', '#ef4444', 'subtle')));

            $methodBadge = self::badge(strtoupper($s->payment_method ?: 'CASH'), '#0284c7', 'subtle');
            $customerName = $s->customer?->name ?? $s->customer_name ?? 'Walk-in Customer';
            $itemsCount = is_array($s->items) ? count($s->items) : 1;

            $saleCards[] = self::card([
                self::row([
                    self::icon('receipt_long', ['color' => '#059669', 'size' => 24]),
                    self::column([
                        self::text("Invoice #{$s->sale_number}", 'title_medium', ['bold' => true]),
                        self::text("{$customerName} • {$itemsCount} items", 'body_small', ['color' => '#64748b']),
                        self::text($s->created_at?->format('M d, Y · h:i A') ?? 'Recent', 'body_small', ['color' => '#94a3b8']),
                    ]),
                    self::column([
                        self::text($currency.number_format((float) $s->total, 2), 'title_large', ['bold' => true, 'color' => '#059669']),
                        $statusBadge,
                    ]),
                ]),
                self::divider(),
                self::row([
                    self::text('Paid: '.$currency.number_format((float) ($s->paid_amount ?: $s->total), 2), 'body_small', ['bold' => true]),
                    self::text('Due: '.$currency.number_format((float) ($s->due_amount ?: 0), 2), 'body_small', ['color' => (float) $s->due_amount > 0 ? '#ef4444' : '#64748b', 'bold' => true]),
                    $methodBadge,
                ]),
                self::divider(),
                self::wrap([
                    self::buttonOutlined('WhatsApp', self::apiPostAction(
                        "/api/tenant/sales/{$s->id}/send-invoice",
                        ['channel' => 'whatsapp'],
                        'WhatsApp receipt dispatched.',
                        reload: false
                    ), 'chat'),
                    self::buttonOutlined('SMS', self::apiPostAction(
                        "/api/tenant/sales/{$s->id}/send-invoice",
                        ['channel' => 'sms'],
                        'SMS receipt dispatched.',
                        reload: false
                    ), 'sms'),
                    self::buttonOutlined('Email', self::apiPostAction(
                        "/api/tenant/sales/{$s->id}/send-invoice",
                        ['channel' => 'email'],
                        'Email receipt sent.',
                        reload: false
                    ), 'email'),
                    self::buttonPrimary('Print / PDF', self::apiPostAction(
                        "/api/tenant/sales/{$s->id}/print",
                        [],
                        'Preparing invoice PDF...',
                        reload: false
                    ), 'print'),
                ]),
            ]);
        }

        return self::screen('Sales History & Invoices', [
            self::card([
                self::row([
                    self::icon('receipt_long', ['color' => '#059669', 'size' => 28]),
                    self::column([
                        self::text('Sales & Invoice Register', 'title_medium', ['bold' => true]),
                        self::text('Chronological transaction register, post-invoicing WhatsApp/SMS dispatch, and PDF printing.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::wrap([
                    self::badge("Transactions: {$sales->count()}", '#0284c7', 'subtle'),
                    self::badge("Total Revenue: {$currency}".number_format($totalSales, 2), '#059669', 'subtle'),
                    self::badge("Receivables Due: {$currency}".number_format($totalDue, 2), $totalDue > 0 ? '#ef4444' : '#10b981', 'subtle'),
                ]),
            ]),

            self::gridView([
                self::card([
                    self::row([
                        self::icon('payments', ['color' => '#059669', 'size' => 22]),
                        self::text($currency.number_format($totalSales, 2), 'headline_small', ['bold' => true, 'color' => '#059669']),
                    ]),
                    self::text('Total Sales', 'label_large', ['bold' => true]),
                    self::text('Registered sales volume', 'body_small', ['color' => '#64748b']),
                ]),
                self::card([
                    self::row([
                        self::icon('account_balance_wallet', ['color' => $totalDue > 0 ? '#ef4444' : '#10b981', 'size' => 22]),
                        self::text($currency.number_format($totalDue, 2), 'headline_small', ['bold' => true, 'color' => $totalDue > 0 ? '#ef4444' : '#10b981']),
                    ]),
                    self::text('Khata / Due Balance', 'label_large', ['bold' => true]),
                    self::text('Pending customer balance', 'body_small', ['color' => '#64748b']),
                ]),
            ], 2),

            self::card([
                self::text('Transaction History', 'title_medium', ['bold' => true]),
                self::column(! empty($saleCards) ? $saleCards : [
                    self::text('No sales recorded yet. Completed POS transactions will appear here.', 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    public static function quotationsView(Company $company): array
    {
        $quotes = Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('operation_type', 'quotation')
            ->with(['customer'])
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $currency = $company->currency_symbol ?: '$';
        $totalQuotes = $quotes->count();
        $totalValue = $quotes->sum(fn ($q) => (float) $q->total);

        $quoteCards = [];
        foreach ($quotes as $q) {
            $status = strtolower((string) ($q->status ?: 'draft'));
            $statusBadge = match ($status) {
                'accepted', 'converted' => self::badge('ACCEPTED', '#10b981', 'subtle'),
                'sent' => self::badge('SENT', '#0284c7', 'subtle'),
                'rejected', 'expired' => self::badge(strtoupper($status), '#ef4444', 'subtle'),
                default => self::badge('DRAFT', '#f59e0b', 'subtle'),
            };

            $customerName = $q->customer?->name ?? $q->customer_name ?? 'Walk-in Customer';
            $itemsCount = is_array($q->items) ? count($q->items) : 1;

            $quoteCards[] = self::card([
                self::row([
                    self::icon('description', ['color' => '#0284c7', 'size' => 24]),
                    self::column([
                        self::text("Quotation #{$q->sale_number}", 'title_medium', ['bold' => true]),
                        self::text("{$customerName} • {$itemsCount} items", 'body_small', ['color' => '#64748b']),
                        self::text($q->created_at?->format('M d, Y · h:i A') ?? 'Recent', 'body_small', ['color' => '#94a3b8']),
                    ]),
                    self::column([
                        self::text($currency.number_format((float) $q->total, 2), 'title_large', ['bold' => true, 'color' => '#0284c7']),
                        $statusBadge,
                    ]),
                ]),
                self::divider(),
                self::wrap([
                    self::buttonPrimary('Convert to Sale', self::apiPostAction(
                        "/api/tenant/quotations/{$q->id}/convert",
                        [],
                        'Quotation converted to active sale invoice.',
                        reload: true
                    ), 'shopping_cart_checkout'),
                    self::buttonOutlined('Print / PDF', self::apiPostAction(
                        "/api/tenant/quotations/{$q->id}/pdf",
                        [],
                        'Opening quotation PDF...',
                        reload: false
                    ), 'print'),
                ]),
            ]);
        }

        return self::screen('Quotations & Estimates', [
            self::card([
                self::row([
                    self::icon('description', ['color' => '#0284c7', 'size' => 28]),
                    self::column([
                        self::text('Quotations & Estimates Register', 'title_medium', ['bold' => true]),
                        self::text('Formal price estimates, quotation drafting, PDF exports, and one-tap checkout conversion.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::wrap([
                    self::badge("Total Quotes: {$totalQuotes}", '#0284c7', 'subtle'),
                    self::badge("Total Pipeline: {$currency}".number_format($totalValue, 2), '#059669', 'subtle'),
                ]),
            ]),

            self::card([
                self::row([
                    self::column([
                        self::textInput('search_quotes', 'Search Quotations', '', [
                            'placeholder' => 'Search by quote number or customer name...',
                        ]),
                    ]),
                    self::buttonPrimary('+ New Quote', self::openModalAction('Draft New Quotation', [
                        self::text('Draft Formal Quotation / Estimate', 'title_medium', ['bold' => true]),
                        self::divider(),
                        self::textInput('customer_name', 'Customer / Client Name', ''),
                        self::textInput('customer_phone', 'Phone Number', ''),
                        self::textInput('valid_until', 'Valid Until (YYYY-MM-DD)', date('Y-m-d', strtotime('+30 days'))),
                        self::textInput('notes', 'Terms & Remarks', 'Valid for 30 days. Standard warranty applies.'),
                        self::buttonPrimary('Save & Create Quote', self::formSubmitAction(
                            '/api/tenant/quotations',
                            'POST',
                            'Quotation drafted successfully.',
                            reload: true
                        ), 'check'),
                    ]), 'add'),
                ], ['spacing' => 8]),
            ]),

            self::card([
                self::text('Quotation History', 'title_medium', ['bold' => true]),
                self::column(! empty($quoteCards) ? $quoteCards : [
                    self::text('No quotations drafted yet. Tap "+ New Quote" to create an estimate.', 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    public static function customersView(Company $company): array
    {
        $customers = Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $currency = $company->currency_symbol ?: '$';
        $totalCustomers = Customer::withoutGlobalScope('company')->where('company_id', $company->id)->count();
        $totalDue = (float) Customer::withoutGlobalScope('company')->where('company_id', $company->id)->sum('due_balance');

        $customerCards = [];
        foreach ($customers as $c) {
            $due = (float) $c->due_balance;
            $dueBadge = $due > 0
                ? self::badge("Due: {$currency}".number_format($due, 2), '#ef4444', 'subtle')
                : self::badge('No Due', '#10b981', 'subtle');

            $contactInfo = trim(($c->phone ?: '').($c->phone && $c->email ? ' • ' : '').($c->email ?: ''));
            if ($contactInfo === '') {
                $contactInfo = 'No contact details';
            }

            $details = [];
            if ($contactInfo !== '') {
                $details[] = self::text($contactInfo, 'body_small', ['color' => '#64748b']);
            }
            if (! empty($c->address)) {
                $details[] = self::text((string) $c->address, 'body_small', ['color' => '#94a3b8']);
            }

            $customerCards[] = self::card([
                self::row([
                    self::icon('person', ['color' => '#0284c7', 'size' => 24]),
                    self::column(array_merge([
                        self::text($c->name, 'title_medium', ['bold' => true]),
                    ], $details)),
                    self::column([
                        $dueBadge,
                        self::text('Pts: '.(int) $c->loyalty_points, 'body_small', ['color' => '#8b5cf6']),
                    ]),
                ]),
                self::divider(),
                self::wrap([
                    self::buttonPrimary('Record Payment', self::openModalAction("Record Payment - {$c->name}", [
                        self::text("Customer Khata Settlement: {$c->name}", 'title_medium', ['bold' => true]),
                        self::text("Current Due Balance: {$currency}".number_format($due, 2), 'body_medium', ['color' => $due > 0 ? '#ef4444' : '#10b981']),
                        self::divider(),
                        self::textInput('amount', 'Payment Amount', $due > 0 ? number_format($due, 2, '.', '') : '0.00'),
                        self::dropdownSelect('payment_method', 'Payment Mode', [
                            ['label' => 'Cash Payment', 'value' => 'cash'],
                            ['label' => 'Debit / Credit Card', 'value' => 'card'],
                            ['label' => 'UPI / QR Code', 'value' => 'upi'],
                            ['label' => 'Bank Transfer', 'value' => 'bank_transfer'],
                        ], 'cash'),
                        self::textInput('notes', 'Payment Reference / Note', 'Customer Khata settlement'),
                        self::buttonPrimary('Confirm Settlement', self::formSubmitAction(
                            "/api/tenant/customers/{$c->id}/payment",
                            'POST',
                            'Customer payment recorded successfully.',
                            reload: true
                        ), 'check'),
                    ]), 'payment'),
                    self::buttonOutlined('Ledger', self::openModalAction("Ledger History - {$c->name}", [
                        self::text("Khata Statement: {$c->name}", 'title_medium', ['bold' => true]),
                        self::text("Outstanding Balance: {$currency}".number_format($due, 2), 'body_small', ['color' => '#64748b']),
                        self::divider(),
                        self::text('View complete ledger transactions and audit logs in financial reports.', 'body_medium'),
                    ]), 'menu_book'),
                ]),
            ]);
        }

        return self::screen('Customers & CRM', [
            self::card([
                self::row([
                    self::icon('people', ['color' => '#0284c7', 'size' => 28]),
                    self::column([
                        self::text('Customer Directory & Khata Register', 'title_medium', ['bold' => true]),
                        self::text('Client directory, credit khata tracking, loyalty points, and receivables settlement.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::wrap([
                    self::badge("Total Customers: {$totalCustomers}", '#0284c7', 'subtle'),
                    self::badge("Total Khata Due: {$currency}".number_format($totalDue, 2), $totalDue > 0 ? '#ef4444' : '#10b981', 'subtle'),
                ]),
            ]),

            self::card([
                self::row([
                    self::column([
                        self::textInput('search_customers', 'Search Directory', '', [
                            'placeholder' => 'Search by customer name or phone number...',
                        ]),
                    ]),
                    self::buttonPrimary('+ Add Customer', self::openModalAction('Register New Customer', [
                        self::text('Register Customer / Client', 'title_medium', ['bold' => true]),
                        self::divider(),
                        self::textInput('name', 'Full Name', ''),
                        self::textInput('phone', 'Phone Number', ''),
                        self::textInput('email', 'Email Address (Optional)', ''),
                        self::textInput('address', 'Billing / Street Address', ''),
                        self::textInput('city', 'City', ''),
                        self::textInput('gstin', 'GSTIN / Tax ID (Optional)', ''),
                        self::buttonPrimary('Save Customer', self::formSubmitAction(
                            '/api/tenant/customers',
                            'POST',
                            'Customer registered successfully.',
                            reload: true
                        ), 'check'),
                    ]), 'person_add'),
                ], ['spacing' => 8]),
            ]),

            self::card([
                self::text('Customer Directory', 'title_medium', ['bold' => true]),
                self::column(! empty($customerCards) ? $customerCards : [
                    self::text('No customer records found. Tap "+ Add Customer" to register clients.', 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    public static function cashRegisterView(Company $company): array
    {
        $currency = $company->currency_symbol ?: '$';

        $activeRegister = CashRegister::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        $historyRegisters = CashRegister::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['opener', 'closer'])
            ->orderByDesc('opened_at')
            ->limit(10)
            ->get();

        $historyCards = [];
        foreach ($historyRegisters as $reg) {
            $isOpen = $reg->status === 'open';
            $regBadge = $isOpen
                ? self::badge('CURRENT SHIFT', '#10b981', 'solid')
                : self::badge('CLOSED', '#64748b', 'subtle');

            $openedText = $reg->opened_at?->format('M d, Y · h:i A') ?? 'Unknown';
            $closedText = $reg->closed_at?->format('M d, Y · h:i A') ?? ($isOpen ? 'In Progress' : 'Closed');
            $openerName = $reg->opener?->name ?? 'Staff';

            $diff = (float) ($reg->cash_difference ?? 0);
            $diffBadge = $diff == 0
                ? self::badge('Balanced', '#10b981', 'subtle')
                : ($diff > 0
                    ? self::badge("Over: +{$currency}".number_format($diff, 2), '#3b82f6', 'subtle')
                    : self::badge("Short: -{$currency}".number_format(abs($diff), 2), '#ef4444', 'subtle'));

            $historyCards[] = self::card([
                self::row([
                    self::icon('savings', ['color' => $isOpen ? '#10b981' : '#64748b', 'size' => 24]),
                    self::column([
                        self::text("Shift #{$reg->id} • {$openerName}", 'title_medium', ['bold' => true]),
                        self::text("Opened: {$openedText}", 'body_small', ['color' => '#64748b']),
                        self::text("Closed: {$closedText}", 'body_small', ['color' => '#94a3b8']),
                    ]),
                    self::column([
                        $regBadge,
                        $diffBadge,
                    ]),
                ]),
                self::divider(),
                self::row([
                    self::text("Opening: {$currency}".number_format((float) $reg->opening_balance, 2), 'body_small'),
                    self::text("Closing: {$currency}".number_format((float) ($reg->counted_closing_balance ?? $reg->expected_closing_balance ?? 0), 2), 'body_small', ['bold' => true]),
                ], ['main_axis_alignment' => 'space_between']),
            ]);
        }

        $activeSectionComponents = [];
        if ($activeRegister !== null) {
            $activeSectionComponents = [
                self::row([
                    self::icon('point_of_sale', ['color' => '#10b981', 'size' => 28]),
                    self::column([
                        self::text('Register Shift Open', 'title_medium', ['bold' => true, 'color' => '#10b981']),
                        self::text("Opened by {$activeRegister->opener?->name} at {$activeRegister->opened_at?->format('M d, Y · h:i A')}", 'body_small', ['color' => '#64748b']),
                    ]),
                    self::badge('ACTIVE', '#10b981', 'solid'),
                ], ['main_axis_alignment' => 'space_between']),
                self::divider(),
                self::gridView([
                    self::card([
                        self::text('Opening Cash Float', 'label_medium', ['color' => '#64748b']),
                        self::text($currency.number_format((float) $activeRegister->opening_balance, 2), 'title_large', ['bold' => true, 'color' => '#0f766e']),
                    ]),
                    self::card([
                        self::text('Expected Drawer Cash', 'label_medium', ['color' => '#64748b']),
                        self::text($currency.number_format((float) $activeRegister->expected_closing_balance, 2), 'title_large', ['bold' => true, 'color' => '#10b981']),
                    ]),
                ], 2),
                self::divider(),
                self::wrap([
                    self::buttonPrimary('Cash In / Cash Out', self::openModalAction('Record Drawer Transaction', [
                        self::text('Cash Drawer Deposit / Withdrawal', 'title_medium', ['bold' => true]),
                        self::divider(),
                        self::dropdownSelect('type', 'Transaction Type', [
                            ['label' => 'Cash In (Deposit / Float Add)', 'value' => 'cash_in'],
                            ['label' => 'Cash Out (Expense / Drawer Drop)', 'value' => 'cash_out'],
                        ], 'cash_in'),
                        self::dropdownSelect('category', 'Drawer Entry Category', [
                            ['label' => 'Petty Cash', 'value' => 'petty_cash'],
                            ['label' => 'Bank Deposit / Withdrawal', 'value' => 'bank'],
                            ['label' => 'Supplier / Expense', 'value' => 'expense'],
                            ['label' => 'Cash Float Adjustment', 'value' => 'float_adjustment'],
                        ], 'petty_cash'),
                        self::textInput('amount', 'Amount', '0.00'),
                        self::textInput('reason', 'Reason / Receipt Reference', ''),
                        self::buttonPrimary('Confirm Drawer Entry', self::formSubmitAction(
                            "/api/tenant/cash-register/{$activeRegister->id}/transaction",
                            'POST',
                            'Drawer cash transaction recorded.',
                            reload: true
                        ), 'check'),
                    ]), 'payments'),
                    self::buttonOutlined('Close Register / End Shift', self::openModalAction('Close Cash Register Shift', [
                        self::text('End Shift & Reconcile Cash Drawer', 'title_medium', ['bold' => true]),
                        self::text("Expected Cash in Drawer: {$currency}".number_format((float) $activeRegister->expected_closing_balance, 2), 'body_medium', ['color' => '#0f766e']),
                        self::divider(),
                        self::textInput('counted_closing_balance', 'Physical Counted Cash in Drawer', number_format((float) $activeRegister->expected_closing_balance, 2, '.', '')),
                        self::textInput('notes', 'Shift Closing Remarks', 'Shift completed successfully.'),
                        self::buttonPrimary('Close Register & Print Z-Report', self::formSubmitAction(
                            '/api/tenant/cash-register/close',
                            'POST',
                            'Cash register shift closed successfully.',
                            reload: true
                        ), 'lock'),
                    ]), 'lock_clock'),
                ]),
            ];
        } else {
            $activeSectionComponents = [
                self::row([
                    self::icon('lock', ['color' => '#f59e0b', 'size' => 28]),
                    self::column([
                        self::text('Cash Drawer is Closed', 'title_medium', ['bold' => true]),
                        self::text('No active cashier shift is running on this terminal. Open the drawer to begin.', 'body_small', ['color' => '#64748b']),
                    ]),
                    self::badge('CLOSED', '#64748b', 'subtle'),
                ], ['main_axis_alignment' => 'space_between']),
                self::divider(),
                self::buttonPrimary('Open Cash Register Shift', self::openModalAction('Start Cashier Shift', [
                    self::text('Open Register & Declare Opening Float', 'title_medium', ['bold' => true]),
                    self::divider(),
                    self::textInput('opening_balance', 'Opening Cash Float', '0.00'),
                    self::textInput('opening_notes', 'Shift Notes (Optional)', 'Morning Shift'),
                    self::buttonPrimary('Open Register Drawer', self::formSubmitAction(
                        '/api/tenant/cash-register/open',
                        'POST',
                        'Register opened successfully. Shift started.',
                        reload: true
                    ), 'point_of_sale'),
                ]), 'savings'),
            ];
        }

        return self::screen('Cash Register & Shifts', [
            self::card($activeSectionComponents),

            self::card([
                self::text('Shift & Register History', 'title_medium', ['bold' => true]),
                self::column(! empty($historyCards) ? $historyCards : [
                    self::text('No past register shifts found.', 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    public static function devicesView(Company $company): array
    {
        $devices = collect();

        // 1. Web sessions
        $webSessions = TenantSession::query()->active()
            ->where('company_id', $company->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        foreach ($webSessions as $s) {
            $ua = strtolower((string) $s->user_agent);
            $platform = 'Web Browser';
            $icon = 'language';
            $color = '#8b5cf6';
            if (str_contains($ua, 'android')) {
                $platform = 'Android Web';
                $icon = 'android';
                $color = '#10b981';
            } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ios')) {
                $platform = 'iOS Web';
                $icon = 'phone_iphone';
                $color = '#0284c7';
            } elseif (str_contains($ua, 'macintosh') || str_contains($ua, 'mac os')) {
                $platform = 'macOS Web';
                $icon = 'laptop_mac';
                $color = '#475569';
            } elseif (str_contains($ua, 'windows')) {
                $platform = 'Windows Web';
                $icon = 'desktop_windows';
                $color = '#0284c7';
            }

            $devices->push([
                'token' => $s->token,
                'name' => 'Web Dashboard - '.($s->user?->name ?? 'User'),
                'platform' => $platform,
                'icon' => $icon,
                'color' => $color,
                'user_name' => $s->user?->name ?? 'Staff User',
                'ip' => $s->ip ?: '127.0.0.1',
                'is_current' => false,
                'created_at' => $s->created_at?->format('M d, Y · h:i A') ?? 'Active',
            ]);
        }

        // 2. POS Terminals & Mobile API Keys
        $terminalKeys = TenantApiKey::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->with('user')
            ->orderByDesc('last_used_at')
            ->get();

        foreach ($terminalKeys as $index => $k) {
            $nameLower = strtolower($k->name ?? '');
            $platform = 'Mobile POS';
            $icon = 'point_of_sale';
            $color = '#059669';
            if (str_contains($nameLower, 'android')) {
                $platform = 'Android POS';
                $icon = 'android';
                $color = '#10b981';
            } elseif (str_contains($nameLower, 'ios') || str_contains($nameLower, 'iphone') || str_contains($nameLower, 'ipad')) {
                $platform = 'iOS POS';
                $icon = 'phone_iphone';
                $color = '#0284c7';
            } elseif (str_contains($nameLower, 'desktop') || str_contains($nameLower, 'windows') || str_contains($nameLower, 'mac')) {
                $platform = 'Desktop POS';
                $icon = 'desktop_windows';
                $color = '#0284c7';
            }

            $devices->push([
                'token' => (string) $k->id,
                'name' => $k->name ?: ($platform.' ('.($k->user?->name ?? 'Staff').')'),
                'platform' => $platform,
                'icon' => $icon,
                'color' => $color,
                'user_name' => $k->user?->name ?? 'Staff Terminal',
                'ip' => '127.0.0.1',
                'is_current' => $index === 0,
                'created_at' => $k->last_used_at?->format('M d, Y · h:i A') ?? $k->created_at?->format('M d, Y · h:i A') ?? 'Active',
            ]);
        }

        $terminalCount = $terminalKeys->count();
        $webCount = $webSessions->count();
        $totalCount = $devices->count();

        $deviceCards = [];
        foreach ($devices as $d) {
            $deviceCards[] = self::card([
                self::row([
                    self::icon($d['icon'], ['color' => $d['color'], 'size' => 28]),
                    self::column([
                        self::text($d['name'], 'title_medium', ['bold' => true]),
                        self::text("User: {$d['user_name']} • IP: {$d['ip']}", 'body_small', ['color' => '#64748b']),
                        self::text("Active: {$d['created_at']}", 'body_small', ['color' => '#94a3b8']),
                    ]),
                    self::column([
                        $d['is_current']
                            ? self::badge('CURRENT DEVICE', '#10b981', 'solid')
                            : self::badge($d['platform'], $d['color'], 'subtle'),
                    ]),
                ]),
                self::divider(),
                self::row([
                    self::text("Platform: {$d['platform']}", 'label_medium', ['bold' => true]),
                    self::buttonDanger('Revoke / Log Out', self::apiPostAction(
                        '/api/devices/'.$d['token'].'/revoke',
                        [],
                        'Device session revoked successfully.',
                        reload: true
                    ), 'logout'),
                ]),
            ]);
        }

        return self::screen('Terminals & Devices', [
            self::card([
                self::row([
                    self::icon('devices_other', ['color' => '#0284c7', 'size' => 28]),
                    self::column([
                        self::text('Active Terminals & Signed-In Devices', 'title_medium', ['bold' => true]),
                        self::text('Manage mobile POS terminals, desktop counter clients, and web dashboard sessions.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::wrap([
                    self::badge("Total Devices: {$totalCount}", '#0284c7', 'subtle'),
                    self::badge("POS Terminals: {$terminalCount}", '#059669', 'subtle'),
                    self::badge("Web Sessions: {$webCount}", '#8b5cf6', 'subtle'),
                ]),
            ]),

            self::gridView([
                self::card([
                    self::row([
                        self::icon('point_of_sale', ['color' => '#059669', 'size' => 22]),
                        self::text((string) $terminalCount, 'headline_small', ['bold' => true, 'color' => '#059669']),
                    ]),
                    self::text('POS Terminals', 'label_large', ['bold' => true]),
                    self::text('Mobile & Desktop POS', 'body_small', ['color' => '#64748b']),
                ]),
                self::card([
                    self::row([
                        self::icon('language', ['color' => '#8b5cf6', 'size' => 22]),
                        self::text((string) $webCount, 'headline_small', ['bold' => true, 'color' => '#8b5cf6']),
                    ]),
                    self::text('Web Sessions', 'label_large', ['bold' => true]),
                    self::text('Browser Dashboard Logins', 'body_small', ['color' => '#64748b']),
                ]),
            ], 2),

            self::card([
                self::text('Active Terminal & Session Register', 'title_medium', ['bold' => true]),
                self::column(! empty($deviceCards) ? $deviceCards : [
                    self::text('No active devices found. Sign in from a POS terminal or browser to register.', 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    public static function changePasswordView(Company $company): array
    {
        return self::screen('Change Password', [
            self::card([
                self::row([
                    self::icon('tune', ['color' => '#475569', 'size' => 28]),
                    self::column([
                        self::text('Account Security', 'title_medium', ['bold' => true]),
                        self::text('Update your login password to secure your tenant account.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::textInput('current_password', 'Current Password', '', ['obscure_text' => true]),
                self::textInput('new_password', 'New Password', '', ['obscure_text' => true]),
                self::textInput('new_password_confirmation', 'Confirm New Password', '', ['obscure_text' => true]),
                self::buttonPrimary(
                    'Update Password',
                    self::formSubmitAction('/api/tenant/profile/change-password', 'POST', 'Password updated successfully')
                ),
            ]),
        ]);
    }

    /**
     * Custom role creation & granular permissions. Lists the built-in system
     * roles and the tenant's custom roles, then a grouped-permission form that
     * POSTs a new role to /api/tenant/roles.
     */
    public static function rolesView(Company $company): array
    {
        $roles = Role::query()
            ->forTenant($company->id)
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        $roleCards = [];
        foreach ($roles as $role) {
            $map = Role::permissionMapFor($company->id, $role->slug) ?? [];
            $grantCount = 0;
            foreach ($map as $moduleActions) {
                $grantCount += is_array($moduleActions) ? count($moduleActions) : 0;
            }

            $badges = [
                self::badge($role->is_system ? 'System' : 'Custom', $role->is_system ? '#475569' : '#0284c7', 'subtle'),
                self::badge("{$grantCount} permissions", '#0f766e', 'subtle'),
            ];

            $rowComponents = [
                self::row([
                    self::text($role->name, 'title_small', ['bold' => true]),
                    self::wrap($badges),
                ], ['main_axis_alignment' => 'space_between']),
            ];
            if (! empty($role->description)) {
                $rowComponents[] = self::text($role->description, 'body_small', ['color' => '#64748b']);
            }
            if (! $role->is_system) {
                $rowComponents[] = self::buttonDanger(
                    'Delete Role',
                    self::formSubmitAction("/api/tenant/roles/{$role->id}", 'DELETE', 'Role deleted.', reload: true),
                    'delete',
                    ['full_width' => false]
                );
            }

            $roleCards[] = self::card($rowComponents, ['padding' => 12, 'border_radius' => 12]);
        }

        // Grouped permission toggles — one collapsed accordion per module.
        $permissionGroups = [];
        foreach (PermissionChecker::MODULES as $moduleSlug => $moduleLabel) {
            $checks = [];
            foreach (PermissionChecker::getActionsForModule($moduleSlug) as $actionSlug => $actionLabel) {
                $checks[] = self::checkbox("perm__{$moduleSlug}__{$actionSlug}", $actionLabel, false);
            }
            $permissionGroups[] = self::accordionGroup($moduleLabel, $checks, ['initially_expanded' => false]);
        }

        return self::screen('Manage Roles', [
            self::card([
                self::row([
                    self::icon('admin_panel_settings', ['color' => '#0284c7', 'size' => 24]),
                    self::column([
                        self::text('Custom Roles & Granular Permissions', 'title_medium', ['bold' => true]),
                        self::text('Build a role from module-level permissions, then assign it to staff from the invite screen.', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
            ]),

            self::accordionGroup('Existing Roles ('.count($roleCards).')', ! empty($roleCards) ? $roleCards : [
                self::text('No roles defined yet.', 'body_small', ['color' => '#64748b']),
            ], ['initially_expanded' => true]),

            self::card([
                self::text('Create a New Role', 'title_medium', ['bold' => true]),
                self::textInput('name', 'Role Name (e.g. Senior Technician, Floor Supervisor)', '', ['required' => true]),
                self::textInput('description', 'Description (optional)', '', ['max_lines' => 2]),
                self::divider(),
                self::text('Permissions', 'label_large', ['bold' => true]),
                self::text('Tap a group to expand its actions. Leave a group untouched to grant nothing there.', 'body_small', ['color' => '#64748b']),
                ...$permissionGroups,
                self::divider(),
                self::buttonPrimary('Create Role', self::formSubmitAction(
                    '/api/tenant/roles',
                    'POST',
                    'Role created successfully.',
                    reload: true
                ), 'add_moderator'),
            ]),
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
                self::textInput('repair_prefix', 'Repair Ticket Prefix', $company->repair_prefix ?? 'REP-'),
                self::textInput('prescription_prefix', 'Prescription / Rx Prefix', $company->prescription_prefix ?? 'RX-'),
                self::textInput('salon_prefix', 'Salon Booking Prefix', $company->salon_prefix ?? 'SAL-'),
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
                self::dropdownSelect('currency', 'Base Currency Code', PlatformRegionalService::currencyOptions(), $company->currency ?? 'USD'),
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

    public static function localizationView(Company $company): array
    {
        return self::screen('Localization & Region', [
            self::card([
                self::text('Regional Localization & Store Defaults', 'title_medium', ['bold' => true]),
                self::text('Configure primary store language and operating timezone inherited or overridden from platform baseline.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::dropdownSelect('default_locale', 'Store Primary Language', PlatformRegionalService::languageOptions(), $company->default_locale ?: ($company->language ?: 'en')),
                self::dropdownSelect('timezone', 'Store Operating Timezone', PlatformRegionalService::timezoneOptions(), $company->timezone ?: $company->resolveTimezone()),
            ]),
            self::buttonPrimary('Save Localization Settings', self::formSubmitAction(
                '/api/tenant/settings/profile',
                'POST',
                'Localization settings updated successfully'
            ), 'save'),
        ]);
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
        [$menuStructure, $navConfig] = self::effectiveNavigationBuilderData($company, $activeMode);

        return self::screen('Navigation Menu Customization', [
            [
                'type' => 'tree_builder',
                'active_mode' => $activeMode,
                'menu_structure' => $menuStructure,
                'sections' => $menuStructure,
                // The explicit collection consumed by current tree-builder
                // clients. Keep the aliases above for older app releases.
                'tree_data' => $menuStructure,
                'nav_config' => $navConfig,
                'items' => $navConfig['items'] ?? [],
            ],
        ]);
    }

    /**
     * Build the complete editable navigation hierarchy for the active store.
     *
     * normalizedNavConfig() intentionally returns an empty override set for a
     * tenant that has never customized its menu. A navigation editor cannot
     * render overrides alone, however, so this method always starts with the
     * active-mode registry tree, recursively includes its children, and then
     * overlays any saved placement/order/visibility values. The returned
     * tree therefore never becomes blank merely because nav_config is null.
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>}
     */
    private static function effectiveNavigationBuilderData(Company $company, string $activeMode): array
    {
        $catalog = TenantNavRegistry::getBaseNavSectionsForTenant($company);
        if ($catalog === []) {
            $catalog = TenantNavRegistry::menuStructureForMode($activeMode ?: 'retail');
        }

        $sectionMeta = [];
        $sectionIndexes = [];
        $catalogItems = [];
        $seenItems = [];

        $collectItems = function (mixed $rawItems, string $sectionKey, ?string $parentKey = null) use (&$collectItems, &$catalogItems, &$seenItems): void {
            if (! is_array($rawItems)) {
                return;
            }

            foreach (array_values($rawItems) as $order => $rawItem) {
                if (! is_array($rawItem)) {
                    continue;
                }

                $item = TenantNavRegistry::normalizeItem($rawItem);
                $key = trim((string) ($item['key'] ?? ''));
                if ($key === '' || isset($seenItems[$key])) {
                    continue;
                }

                $seenItems[$key] = true;
                $catalogItems[$key] = [
                    'meta' => $item,
                    'section' => $sectionKey,
                    'parent' => $parentKey,
                    'order' => $order,
                ];

                // Null/missing children are deliberately normalized to an
                // empty list so every emitted node has a stable collection.
                $collectItems(
                    is_array($item['children'] ?? null) ? $item['children'] : [],
                    $sectionKey,
                    $key
                );
            }
        };

        foreach (array_values($catalog) as $sectionIndex => $rawSection) {
            if (! is_array($rawSection)) {
                continue;
            }
            $section = TenantNavRegistry::normalizeSection($rawSection);
            $sectionKey = trim((string) ($section['key'] ?? ''));
            if ($sectionKey === '' || isset($sectionMeta[$sectionKey])) {
                continue;
            }

            $sectionMeta[$sectionKey] = $section;
            $sectionIndexes[$sectionKey] = $sectionIndex;
            $collectItems($section['items'] ?? [], $sectionKey);
        }

        $storedConfig = $company->normalizedNavConfig();
        $itemOverrides = [];
        foreach ($storedConfig['items'] ?? [] as $item) {
            if (is_array($item) && ! empty($item['key'])) {
                $itemOverrides[(string) $item['key']] = $item;
            }
        }
        $sectionOverrides = [];
        foreach ($storedConfig['sections'] ?? [] as $section) {
            if (is_array($section) && ! empty($section['key'])) {
                $sectionOverrides[(string) $section['key']] = max(0, (int) ($section['order'] ?? 0));
            }
        }

        $resolvedItems = [];
        foreach ($catalogItems as $key => $catalogItem) {
            $override = $itemOverrides[$key] ?? null;
            // Old hidden_tiles-only payloads have no placement information.
            // They should change visibility without accidentally un-nesting
            // an item from its registry-defined parent.
            $hasPlacementOverride = is_array($override)
                && isset($override['section'])
                && trim((string) $override['section']) !== '';
            $requestedSection = $hasPlacementOverride ? (string) $override['section'] : $catalogItem['section'];
            $section = isset($sectionMeta[$requestedSection]) ? $requestedSection : $catalogItem['section'];
            $parent = $hasPlacementOverride
                ? ($override['parent_id'] ?? $override['parent'] ?? null)
                : $catalogItem['parent'];

            $resolvedItems[] = [
                'key' => $key,
                'section' => $section,
                'parent' => $parent,
                'parent_id' => $parent,
                'order' => is_array($override) && array_key_exists('order', $override) && $override['order'] !== null
                    ? max(0, (int) $override['order'])
                    : $catalogItem['order'],
                'visible' => is_array($override) ? (bool) ($override['visible'] ?? true) : true,
            ];
        }

        $sections = [];
        foreach ($sectionMeta as $key => $_section) {
            $sections[] = [
                'key' => $key,
                'order' => $sectionOverrides[$key] ?? $sectionIndexes[$key],
            ];
        }

        $effectiveConfig = app(TenantNavigationConfigService::class)->normalize([
            'sections' => $sections,
            'items' => $resolvedItems,
        ]);

        $decorateNodes = function (mixed $nodes) use (&$decorateNodes, $catalogItems): array {
            $decorated = [];
            foreach (is_array($nodes) ? $nodes : [] as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $key = (string) ($node['key'] ?? '');
                if ($key === '' || ! isset($catalogItems[$key])) {
                    continue;
                }
                $meta = $catalogItems[$key]['meta'];
                $decorated[] = array_merge($meta, $node, [
                    'id' => $key,
                    'key' => $key,
                    'title' => $meta['title'] ?? $meta['label'] ?? $key,
                    'label' => $meta['label'] ?? $meta['title'] ?? $key,
                    'children' => $decorateNodes($node['children'] ?? []),
                ]);
            }

            return $decorated;
        };

        $activeTree = [];
        foreach ($effectiveConfig['tree'] ?? [] as $treeSection) {
            if (! is_array($treeSection)) {
                continue;
            }
            $key = (string) ($treeSection['key'] ?? '');
            if ($key === '' || ! isset($sectionMeta[$key])) {
                continue;
            }
            $meta = $sectionMeta[$key];
            $activeTree[] = array_merge($meta, $treeSection, [
                'id' => $key,
                'key' => $key,
                'title' => $meta['title'] ?? $meta['label'] ?? $key,
                'label' => $meta['label'] ?? $meta['title'] ?? $key,
                'items' => $decorateNodes($treeSection['items'] ?? []),
            ]);
        }

        return [$activeTree, $effectiveConfig];
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
        $channels = CustomNotificationChannel::where('company_id', $company->id)->get();
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
            ['key' => 'dashboard', 'title' => 'Dashboard', 'endpoint' => '/api/tenant/views/dashboard', 'permission' => 'pos.view'],
            ['key' => 'settings-profile', 'title' => 'Store Profile', 'endpoint' => '/api/tenant/views/settings-profile', 'permission' => 'settings.view'],
            ['key' => 'settings-branding', 'title' => 'Store Branding & Colors', 'endpoint' => '/api/tenant/views/settings-branding', 'permission' => 'settings.view'],
            ['key' => 'settings-receipts', 'title' => 'Receipt Prefixes & Bank Terms', 'endpoint' => '/api/tenant/views/settings-receipts', 'permission' => 'settings.view'],
            ['key' => 'settings-financial', 'title' => 'Financial & Currency', 'endpoint' => '/api/tenant/views/settings-financial', 'permission' => 'settings.view'],
            ['key' => 'settings-localization', 'title' => 'Localization & Region', 'endpoint' => '/api/tenant/views/settings-localization', 'permission' => 'settings.view'],
            ['key' => 'settings-taxes', 'title' => 'Taxes & Compliance', 'endpoint' => '/api/tenant/views/settings-taxes', 'permission' => 'settings.view'],
            ['key' => 'settings-api', 'title' => 'API & Integrations', 'endpoint' => '/api/tenant/views/settings-api', 'permission' => 'settings.view'],
            ['key' => 'settings-navigation', 'title' => 'Navigation Menu', 'endpoint' => '/api/tenant/views/settings-navigation', 'permission' => 'settings.view'],
            ['key' => 'settings-notifications', 'title' => 'Custom Notification Gateways', 'endpoint' => '/api/tenant/views/settings-notifications', 'permission' => 'settings.view'],
            ['key' => 'settings-advanced', 'title' => 'Advanced & Danger Zone', 'endpoint' => '/api/tenant/views/settings-advanced', 'permission' => 'settings.view'],
            ['key' => 'sales', 'title' => 'Sales & Invoices History', 'endpoint' => '/api/tenant/views/sales', 'permission' => 'sales.view'],
            ['key' => 'quotations', 'title' => 'Quotations & Estimates', 'endpoint' => '/api/tenant/views/quotations', 'permission' => 'quotes.view'],
            ['key' => 'customers', 'title' => 'Customers & CRM', 'endpoint' => '/api/tenant/views/customers', 'permission' => 'customers.view'],
            ['key' => 'cash-register', 'title' => 'Cash Register', 'endpoint' => '/api/tenant/views/cash-register', 'permission' => 'cash_register.view'],
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
        if (in_array($normalized, ['change-password', 'password', 'devices', 'device-sessions', 'terminals'], true)) {
            return null;
        }

        if (str_starts_with($normalized, 'settings-')
            || in_array($normalized, ['mode', 'profile', 'branding', 'receipts', 'financial', 'localization', 'taxes', 'api', 'api-integrations', 'navigation', 'navigation-menu', 'notifications', 'custom-notifications'], true)) {
            return 'settings.view';
        }

        if (in_array($normalized, ['restaurant-tables', 'restaurant-kds', 'restaurant-pos'], true)) {
            return 'pos.view';
        }

        if (in_array($normalized, ['sales', 'invoices', 'sales-invoices', 'pos-sales', 'dining-history', 'kot-history', 'pharmacy-prescriptions'], true)) {
            return 'sales.view';
        }

        if (in_array($normalized, ['quotations', 'quotes', 'estimates'], true)) {
            return 'quotes.view';
        }

        if (in_array($normalized, ['customers', 'crm', 'clients'], true)) {
            return 'customers.view';
        }

        if (in_array($normalized, ['cash-register', 'cash_register', 'register'], true)) {
            return 'cash_register.view';
        }

        if (in_array($normalized, ['pharmacy-batches'], true)) {
            return 'products.view';
        }

        if (in_array($normalized, ['repair-dashboard', 'repair-create-ticket', 'repair-tickets', 'repair-my-jobs', 'repair-detail', 'repair-categories', 'repair-pos'], true)) {
            return 'pos.view';
        }

        if (in_array($normalized, ['service-calendar', 'service-orders'], true)) {
            return 'service_orders.view';
        }

        if (in_array($normalized, ['service-stylists'], true)) {
            return 'users.view';
        }

        if (in_array($normalized, ['roles', 'roles-create', 'manage-roles', 'staff', 'staff-access', 'users'], true)) {
            return 'users.view';
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

            // Database-authored screens provide layout/content, while these
            // tenant-owned controls still require live company data on every
            // request. Without this hydration, a stored navigation screen can
            // render its headings but has no rows to give the Flutter builder.
            if (in_array($normalized, ['settings-navigation', 'navigation', 'navigation-menu'], true)) {
                $schema = self::hydrateStoredNavigationSchema($schema, $company);
            } elseif (in_array($normalized, ['settings-branding', 'branding', 'settings-profile', 'profile'], true)) {
                $schema = self::hydrateStoredBrandingColorPickers($schema, $company);
            }

            return self::schemaResponse($normalized, $schema);
        }

        $schema = match ($normalized) {
            'settings-mode', 'mode' => self::modeView($company),
            'dashboard' => self::dashboardView($company),
            'settings-profile', 'profile' => self::profileView($company),
            'settings-branding', 'branding' => self::brandingView($company),
            'settings-receipts', 'receipts' => self::receiptsView($company),
            'settings-financial', 'financial' => self::financialView($company),
            'settings-localization', 'localization' => self::localizationView($company),
            'settings-taxes', 'taxes' => self::taxesView($company),
            'settings-api', 'api', 'api-integrations' => self::apiView($company),
            'settings-navigation', 'navigation', 'navigation-menu' => self::navigationView($company),
            'settings-notifications', 'notifications', 'custom-notifications' => self::notificationsView($company),
            'settings-advanced', 'advanced', 'danger-zone' => self::advancedView($company),
            'restaurant-tables', 'tables', 'floor-plan' => self::restaurantTablesView($company),
            'restaurant-kds', 'kds', 'kitchen-display' => self::restaurantKdsView($company),
            'restaurant-pos' => self::restaurantPosView($company),
            'dining-history', 'kot-history' => self::diningHistoryView($company),
            'pharmacy-pos' => self::pharmacyPosView($company),
            'pharmacy-batches', 'batches' => self::pharmacyBatchesView($company),
            'pharmacy-prescriptions', 'prescriptions' => self::pharmacyPrescriptionsView($company),
            'repair-dashboard', 'repair' => self::repairDashboardView($company),
            'repair-create-ticket', 'repair-ticket-create' => self::repairCreateTicketView($company),
            'repair-tickets' => self::repairTicketsView($company),
            'repair-my-jobs' => self::repairMyJobsView($company),
            'repair-detail' => self::repairDetailView($company),
            'repair-categories' => self::repairCategoriesView($company),
            'repair-pos' => self::repairPosView($company),
            'service-calendar', 'calendar' => self::serviceCalendarView($company),
            'service-stylists', 'stylists' => self::serviceStylistsView($company),
            'service-orders' => self::serviceOrdersView($company),
            'change-password', 'password' => self::changePasswordView($company),
            'roles', 'roles-create', 'manage-roles' => self::rolesView($company),
            'pos', 'point-of-sale' => self::posView($company),
            'retail-pos' => self::retailPosView($company),
            'salon-pos', 'service-pos', 'spa-pos' => self::salonPosView($company),
            'sales', 'invoices', 'sales-invoices', 'pos-sales' => self::salesView($company),
            'quotations', 'quotes', 'estimates' => self::quotationsView($company),
            'customers', 'crm', 'clients' => self::customersView($company),
            'cash-register', 'cash_register', 'register' => self::cashRegisterView($company),
            'devices', 'device-sessions', 'terminals' => self::devicesView($company),
            'categories', 'product-categories', 'inventory-categories' => self::categoriesView($company),
            default => null,
        };

        if ($schema === null && str_ends_with($normalized, '-pos')) {
            $modKey = substr($normalized, 0, -4);
            $schema = UniversalPosBuilder::posScreenForModule($modKey, $company);
        }

        if ($schema === null) {
            $module = ModuleRegistry::find($normalized);
            if ($module !== null && in_array($normalized, ModuleRegistry::availableModes($company), true)) {
                $schema = self::moduleView($normalized, $company);
            } else {
                $navItem = self::findNavigationItem($normalized, $company);
                if ($navItem !== null) {
                    // Existing destinations can be progressively replaced by
                    // database-authored SduiScreen records. Until then, the
                    // endpoint still returns a valid server-owned shell and
                    // never asks Flutter to instantiate a vertical class.
                    $schema = self::screen(
                        (string) ($navItem['title'] ?? $navItem['label'] ?? $normalized),
                        [],
                    );
                }
            }

            if ($schema === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'SDUI view not found.',
                ], 404);
            }
        }

        return self::schemaResponse($normalized, $schema);
    }

    private static function findNavigationItem(string $viewKey, Company $company): ?array
    {
        $needle = str_replace('-', '_', $viewKey);
        $search = function (array $items) use (&$search, $needle): ?array {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $key = str_replace('-', '_', (string) ($item['key'] ?? $item['id'] ?? ''));
                if ($key === $needle) {
                    return $item;
                }
                $found = $search(is_array($item['children'] ?? null) ? $item['children'] : []);
                if ($found !== null) {
                    return $found;
                }
            }

            return null;
        };

        foreach (TenantNavRegistry::getEffectiveNavForTenant($company) as $section) {
            $found = $search(is_array($section['items'] ?? null) ? $section['items'] : []);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Inject live navigation collections into a database-authored screen.
     */
    private static function hydrateStoredNavigationSchema(array $schema, Company $company): array
    {
        $canonical = self::navigationView($company);
        $treeBuilder = collect($canonical['components'] ?? [])->first(
            fn ($component) => is_array($component) && ($component['type'] ?? null) === 'tree_builder'
        );
        if (! is_array($treeBuilder)) {
            return $schema;
        }

        $found = false;
        $hydrate = function (mixed $nodes) use (&$hydrate, &$found, $treeBuilder): mixed {
            if (! is_array($nodes)) {
                return $nodes;
            }

            foreach ($nodes as $index => $node) {
                if (! is_array($node)) {
                    continue;
                }
                $type = strtolower(trim((string) ($node['type'] ?? '')));
                if (in_array($type, ['tree_builder', 'navigation_builder'], true)) {
                    // Preserve presentation fields authored in the stored
                    // screen, but make the live hierarchy/config authoritative.
                    $nodes[$index] = array_merge($node, [
                        'active_mode' => $treeBuilder['active_mode'],
                        'menu_structure' => $treeBuilder['menu_structure'],
                        'sections' => $treeBuilder['sections'],
                        'tree_data' => $treeBuilder['tree_data'],
                        'nav_config' => $treeBuilder['nav_config'],
                        'items' => $treeBuilder['items'],
                    ]);
                    $found = true;

                    continue;
                }

                foreach (['components', 'children', 'tabs'] as $childKey) {
                    if (isset($node[$childKey]) && is_array($node[$childKey])) {
                        $node[$childKey] = $hydrate($node[$childKey]);
                    }
                }
                $nodes[$index] = $node;
            }

            return $nodes;
        };

        $schema['components'] = $hydrate($schema['components'] ?? []);
        if (! $found) {
            $schema['components'][] = $treeBuilder;
        }

        return $schema;
    }

    /**
     * Upgrade legacy database-authored branding inputs to visual pickers.
     */
    private static function hydrateStoredBrandingColorPickers(array $schema, Company $company): array
    {
        $colors = [
            'primary_color' => ['Primary Accent Color', $company->primary_color ?? '#4F46E5'],
            'accent_color' => ['Secondary Accent Color', $company->accent_color ?? '#D97706'],
            'drawer_bg' => ['Sidebar / Drawer Background', $company->drawer_bg ?? '#1e293b'],
        ];

        $hydrate = function (mixed $nodes) use (&$hydrate, $colors): mixed {
            if (! is_array($nodes)) {
                return $nodes;
            }

            foreach ($nodes as $index => $node) {
                if (! is_array($node)) {
                    continue;
                }
                $name = (string) ($node['name'] ?? '');
                if (isset($colors[$name])) {
                    [$label, $fallback] = $colors[$name];
                    $nodes[$index] = array_merge($node, self::colorPicker(
                        $name,
                        (string) ($node['label'] ?? $label),
                        (string) ($node['initial_value'] ?? $fallback)
                    ));

                    continue;
                }

                foreach (['components', 'children', 'tabs'] as $childKey) {
                    if (isset($node[$childKey]) && is_array($node[$childKey])) {
                        $node[$childKey] = $hydrate($node[$childKey]);
                    }
                }
                $nodes[$index] = $node;
            }

            return $nodes;
        };

        $schema['components'] = $hydrate($schema['components'] ?? []);

        return $schema;
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
