<?php

namespace App\Services\Sdui;

use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\V1\SettingsApiController;
use App\Http\Controllers\Api\V1\TenantAppPreferencesController;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Company;
use App\Models\Configuration;
use App\Models\Customer;
use App\Models\CustomNotificationChannel;
use App\Models\DiningFloor;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\PaymentMethod;
use App\Models\PharmacyBatch;
use App\Models\PharmacyPrescription;
use App\Models\Product;
use App\Models\RepairDeviceCategory;
use App\Models\RepairTicket;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalonAppointment;
use App\Models\SduiScreen;
use App\Models\TaxRule;
use App\Models\TenantApiKey;
use App\Models\TenantNotificationGateway;
use App\Models\TenantSession;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Localization\PlatformRegionalService;
use App\Services\Modular\ModuleRegistry;
use App\Services\Navigation\TenantNavigationConfigService;
use App\Services\Navigation\TenantNavRegistry;
use App\Services\TaxCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\leadmanagement\Services\LeadService;

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
        'creatable_select', 'search_bar',
        'checkbox', 'toggle_switch', 'date_time_picker', 'color_picker', 'file_upload',
        'file_picker',
        'line_item_tile', 'table_grid', 'step_counter', 'button_primary',
        'button_outlined', 'button_danger', 'fab', 'action_sheet_trigger', 'navigation_builder', 'tree_builder',
        'wrap', 'cash_tendered_field', 'customer_selector', 'chip',
        'entity_record_card', 'pipeline_stage_tracker', 'progress_bar_stat', 'segmented_filter_chips', 'fab_action',
        'segmented_tabs', 'document_preview_card', 'section_header', 'list_tile',
        'notification_item', 'empty_state',
        // Generic SDUI form component aliases
        'select', 'input', 'number', 'switch', 'button', 'hidden',
    ];

    public const INPUT_TYPES = [
        'text_input', 'dropdown_select', 'creatable_select', 'search_bar', 'checkbox', 'toggle_switch',
        'date_time_picker', 'color_picker', 'file_upload', 'file_picker', 'step_counter',
        'cash_tendered_field', 'customer_selector',
        // Generic SDUI input aliases
        'select', 'input', 'number', 'switch', 'hidden',
    ];

    public const ACTION_COMPONENT_TYPES = [
        'button_primary', 'button_outlined', 'button_danger', 'fab', 'chip',
        'button',
    ];

    public const ACTION_TYPES = [
        'navigate', 'navigate_to', 'form_submit', 'submit_form', 'api_post', 'open_modal', 'navigate_back', 'pop',
        'add_to_cart', 'open_remote_sheet', 'open_bottom_sheet', 'open_url', 'show_post_sale_sheet',
        'load_rx_to_pos', 'load_repair_to_pos', 'filter_view', 'show_ticket_share_sheet',
        'trigger_print', 'reload_component', 'refresh_sheet', 'refresh_dashboard', 'thermal_print', 'system_share_file',
        'open_receipt_preview', 'trigger_thermal_print',
    ];

    // =========================================================================
    // Dynamic Theme Tokens & Mode Resolution
    // =========================================================================

    public static function isDarkMode(?Request $request = null): bool
    {
        $req = $request ?: request();
        if ($req) {
            $header = strtolower((string) ($req->header('X-App-Theme') ?: ($req->header('X-Theme') ?: '')));
            if ($header === 'dark') {
                return true;
            }
            if ($header === 'light') {
                return false;
            }

            $param = strtolower((string) ($req->query('theme') ?: ($req->input('theme') ?: '')));
            if ($param === 'dark') {
                return true;
            }
            if ($param === 'light') {
                return false;
            }
        }

        return false;
    }

    public static function themeToken(string $token, ?Request $request = null): string
    {
        return match ($token) {
            'canvas', 'theme.canvas', 'background', 'theme.background' => 'theme.background',
            'surface', 'theme.surface', 'card_bg' => 'theme.surface',
            'surfaceVariant', 'theme.surfaceVariant', 'note_bg' => 'theme.surfaceVariant',
            'divider', 'theme.divider', 'border', 'theme.border' => 'theme.divider',
            'textPrimary', 'theme.textPrimary', 'text_primary', 'onSurface', 'theme.onSurface' => 'theme.onSurface',
            'textSecondary', 'theme.textSecondary', 'text_secondary' => 'theme.textSecondary',
            'accentText', 'theme.accentText', 'noteText', 'theme.noteText', 'note_text' => 'theme.accentText',
            default => $token,
        };
    }

    public static function themeColors(?Request $request = null): array
    {
        return [
            'surface' => 'theme.surface',
            'background' => 'theme.background',
            'on_surface' => 'theme.onSurface',
            'divider' => 'theme.divider',
        ];
    }

    // =========================================================================
    // Layout Primitives
    // =========================================================================

    public static function container(array $components, array $props = []): array
    {
        if (isset($props['color']) && (str_starts_with((string) $props['color'], 'theme.') || in_array($props['color'], ['card_bg', 'note_bg', 'surface']))) {
            unset($props['color']);
        }
        if (isset($props['border_color']) && (str_starts_with((string) $props['border_color'], 'theme.') || in_array($props['border_color'], ['border', 'divider']))) {
            unset($props['border_color']);
        }

        return array_merge([
            'type' => 'container',
            'components' => $components,
            'children' => $components,
        ], $props);
    }

    public static function card(array $components, array $props = []): array
    {
        if (isset($props['color']) && (str_starts_with((string) $props['color'], 'theme.') || in_array($props['color'], ['card_bg', 'note_bg', 'surface']))) {
            unset($props['color']);
        }
        if (isset($props['border_color']) && (str_starts_with((string) $props['border_color'], 'theme.') || in_array($props['border_color'], ['border', 'divider']))) {
            unset($props['border_color']);
        }
        if (isset($props['style']) && is_array($props['style'])) {
            unset($props['style']['backgroundColor'], $props['style']['borderColor']);
            if (empty($props['style'])) {
                unset($props['style']);
            }
        }

        return array_merge([
            'type' => 'card',
            'components' => $components,
            'children' => $components,
        ], $props);
    }

    public static function scrollView(array $components, array $props = []): array
    {
        return array_merge([
            'type' => 'scroll_view',
            'components' => $components,
            'children' => $components,
        ], $props);
    }

    public static function gridView(array $components, int $columns = 2, array $props = []): array
    {
        return array_merge([
            'type' => 'grid_view',
            'cross_axis_count' => $columns,
            'components' => $components,
            'children' => $components,
        ], $props);
    }

    public static function accordionGroup(string $title, array $components, array $props = []): array
    {
        return array_merge([
            'type' => 'accordion_group',
            'title' => $title,
            'components' => $components,
            'children' => $components,
        ], $props);
    }

    public static function column(array $components, array $props = []): array
    {
        return array_merge([
            'type' => 'column',
            'components' => $components,
            'children' => $components,
        ], $props);
    }

    public static function row(array $components, array $props = []): array
    {
        return array_merge([
            'type' => 'row',
            'components' => $components,
            'children' => $components,
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

    /**
     * @param  list<array<string, mixed>>  $tabs  each: ['id' => .., 'label'|'title' => .., 'icon' => .., 'components' => [..]]
     * @param  array<string, mixed>  $props  e.g. ['initial_index' => 1, 'is_scrollable' => true]
     */
    public static function tabs(array $tabs, array $props = []): array
    {
        return array_merge([
            'type' => 'tabs',
            'tabs' => array_values($tabs),
        ], $props);
    }

    // =========================================================================
    // Display Primitives
    // =========================================================================

    public static function text(?string $text, string $style = 'body_medium', array $props = []): array
    {
        if (isset($props['color']) && (str_starts_with((string) $props['color'], 'theme.') || in_array($props['color'], ['text_primary', 'text_secondary', 'textPrimary', 'textSecondary', 'onSurface']))) {
            unset($props['color']);
        }

        return array_merge([
            'type' => 'text',
            'text' => (string) ($text ?? ''),
            'style' => $style,
        ], $props);
    }

    public static function callout(string $text, string $variant = 'accent', array $props = []): array
    {
        return array_merge([
            'type' => 'container',
            'component_type' => 'callout',
            'text' => $text,
            'variant' => $variant,
            'border_radius' => 6,
            'padding' => [8, 12],
            'margin' => [4, 0, 0, 0],
            'components' => [
                self::text($text, 'body_small', ['bold' => true, 'variant' => 'bodySmall']),
            ],
            'children' => [
                self::text($text, 'body_small', ['bold' => true, 'variant' => 'bodySmall']),
            ],
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
        if ($color && str_starts_with($color, 'theme.')) {
            $color = null;
        }

        return array_merge([
            'type' => 'badge',
            'label' => (string) ($label ?? ''),
            'text' => (string) ($label ?? ''),
            'badge_style' => $style,
        ], $color ? ['color' => $color] : [], $props);
    }

    public static function icon(?string $icon, array $props = []): array
    {
        if (isset($props['color']) && str_starts_with((string) $props['color'], 'theme.')) {
            unset($props['color']);
        }

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

    public static function codeSnippet(string $code, ?string $description = null, array $props = []): array
    {
        $fieldId = 'endpoint_'.substr(md5($code), 0, 8);

        return self::textInput($fieldId, $description ?: $code, $code, array_merge([
            'read_only' => true,
            'copyable' => true,
            'copy_tooltip' => 'Copy to clipboard',
            'copy_toast' => 'Copied to clipboard!',
        ], $props));
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
            'value' => (string) ($initialValue ?? ''),
        ], $props);
    }

    /**
     * Full-width pill search field styled like the core POS search bar. The
     * value binds to $name; pressing the keyboard search key (or the inline
     * clear button) fires a `filter_view` against $filterEndpoint scoped to
     * $name, re-opening the current view with the query as a param.
     *
     * @param  array<string, mixed>  $props  e.g. ['initial_value' => $q, 'debounce_ms' => 300]
     */
    public static function searchBar(string $name, string $placeholder, string $filterEndpoint, array $props = []): array
    {
        return array_merge([
            'type' => 'search_bar',
            'name' => $name,
            'placeholder' => $placeholder,
            'prefix_icon' => 'search',
            'clearable' => true,
            'autofocus' => false,
            'debounce_ms' => 300,
            'initial_value' => '',
            'action' => self::filterViewAction($filterEndpoint, [$name]),
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

    /**
     * A preset dropdown plus a "+ Custom" entry that reveals a free-text
     * field on the client. The chosen preset value OR the typed text is bound
     * to $name as a plain string — the endpoint keeps treating it as free text.
     *
     * @param  list<array{label:string,value?:string}>|array<string,string>  $options
     */
    public static function creatableSelect(string $name, string $label, array $options, mixed $initialValue = null, array $props = []): array
    {
        $normalizedOptions = [];
        foreach ($options as $key => $val) {
            if (is_array($val)) {
                $optLabel = (string) ($val['label'] ?? $val['name'] ?? $key);
                $normalizedOptions[] = [
                    'label' => $optLabel,
                    'value' => (string) ($val['value'] ?? $val['code'] ?? $optLabel),
                ];
            } else {
                $normalizedOptions[] = [
                    'label' => (string) $val,
                    'value' => (string) (is_numeric($key) ? $val : $key),
                ];
            }
        }

        return array_merge([
            'type' => 'creatable_select',
            'name' => $name,
            'label' => $label,
            'options' => $normalizedOptions,
            'allow_custom' => true,
            'custom_value' => '__custom__',
            'custom_label' => '+ Other / Custom Reason',
            'placeholder' => 'Select or type a custom reason…',
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

    /**
     * Native document / photo attachment picker. The client renders an action
     * card offering camera, gallery and document sources, uploads the picked
     * file to $uploadEndpoint as multipart `file`, and binds the returned
     * storage URL into the form value $name so it is submitted with the record.
     *
     * Only non-executable image/PDF types are ever accepted — the client
     * filters by $allowedExtensions and the upload endpoint re-validates the
     * MIME type and rejects script/binary disguises server-side.
     */
    public static function filePicker(string $name, string $label, string $uploadEndpoint, array $props = []): array
    {
        return array_merge([
            'type' => 'file_picker',
            'name' => $name,
            'label' => $label,
            'hint' => 'Upload a photo or PDF — non-executable files only',
            'upload_endpoint' => $uploadEndpoint,
            'field_name' => 'file',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'heic'],
            'max_size_mb' => 10,
            'allow_camera' => true,
            'allow_gallery' => true,
            'allow_document' => true,
            'response_url_path' => 'url',
        ], $props);
    }

    public static function customerSelector(
        string $name = 'customer_id',
        string $label = 'Client / Customer Lookup',
        string $searchEndpoint = '/api/tenant/customers/search',
        array $fields = ['name_field' => 'customer_name', 'phone_field' => 'customer_phone'],
        array $props = []
    ): array {
        return array_merge([
            'type' => 'customer_selector',
            'component_type' => 'customer_search_picker',
            'name' => $name,
            'label' => $label,
            'search_endpoint' => $searchEndpoint,
            'endpoint' => $searchEndpoint,
            'query_param' => 'q',
            'min_chars' => 1,
            'fields' => $fields,
            'placeholder' => 'Search existing client by name, phone or email...',
            'autofill_targets' => [
                'customer_id' => 'id',
                'contact_name' => 'name',
                'client_name' => 'name',
                'phone_number' => 'phone',
                'email_address' => 'email',
                'company_name' => 'company_name',
            ],
            'style' => [
                'dropdownBackgroundColor' => 'theme.surface',
                'dropdown_surface' => '#1E293B',
                'popup_background' => '#1E293B',
                'dropdownItemHover' => 'theme.surfaceVariant',
                'borderColor' => 'theme.divider',
                'border_color' => '#334155',
                'backgroundColor' => 'theme.surface',
            ],
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

    public static function entityRecordCard(array $config): array
    {
        return array_merge([
            'type' => 'entity_record_card',
        ], $config);
    }

    public static function pipelineStageTracker(array $stages, string|int $currentStage, array $props = []): array
    {
        return array_merge([
            'type' => 'pipeline_stage_tracker',
            'stages' => array_values($stages),
            'current_stage' => $currentStage,
        ], $props);
    }

    public static function progressBarStat(string $label, float|int $percentage, ?string $value = null, ?string $count = null, array $props = []): array
    {
        return array_merge([
            'type' => 'progress_bar_stat',
            'label' => $label,
            'percentage' => $percentage,
            'value' => $value,
            'count' => $count,
        ], $props);
    }

    public static function segmentedFilterChips(array $chips, ?string $selectedId = null, array $props = []): array
    {
        return array_merge([
            'type' => 'segmented_filter_chips',
            'chips' => array_values($chips),
            'selected_id' => $selectedId,
        ], $props);
    }

    public static function fabAction(string $icon, array $action, ?string $label = null, array $props = []): array
    {
        return array_merge([
            'type' => 'fab_action',
            'icon' => $icon,
            'action' => $action,
            'label' => $label,
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

    public static function chip(string $label, ?array $action = null, bool $selected = false, array $props = []): array
    {
        return array_merge([
            'type' => 'chip',
            'label' => $label,
            'selected' => $selected,
            'action' => $action,
            'dense' => true,
            'border_radius' => 12,
            'selected_color' => '#166534',
            'background_color' => $selected ? '#166534' : '#1E293B',
            'text_color' => $selected ? '#ffffff' : '#F8FAFC',
            'border_color' => $selected ? '#22c55e' : '#334155',
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
        // Zero is meaningful for credit/receivable sales; only a genuinely
        // absent legacy value falls back to the settled total.
        $paid = round((float) ($sale->paid_amount ?? $total), 2);
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
        $documentType = $sale->operation_type === 'quotation'
            ? 'quotation'
            : (str_starts_with(strtoupper((string) $sale->sale_number), 'POS-') ? 'sale' : 'invoice');

        $publicLink = '';
        try {
            $publicLink = route('sales.public', $sale->sale_number);
        } catch (\Throwable) {
            // route helper unavailable in some contexts — omit the link
        }

        return [
            'document_id' => $sale->id,
            'document_type' => $documentType,
            'invoice_number' => $sale->sale_number,
            'sale_id' => $sale->id,
            'customer_name' => (string) ($sale->customer?->name ?? $sale->customer_name ?? ''),
            'customer_phone' => preg_replace('/[^0-9+]/', '', (string) ($sale->customer?->phone ?? $sale->customer_phone ?? '')),
            'customer_email' => (string) ($sale->customer?->email ?? ''),
            'company_name' => (string) ($company?->display_name ?: ''),
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
            'batch_dispatch_endpoint' => $documentType === 'quotation'
                ? null
                : '/api/v1/tenant/dispatch/batch',
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

    public static function formSubmitAction(string $endpoint, string $method = 'POST', string $successToast = 'Settings saved successfully', bool $navigateBack = false, bool $reload = false, ?array $payload = null, ?string $redirectRoute = null): array
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
        if ($redirectRoute !== null) {
            $action['redirect_route'] = $redirectRoute;
            $action['route'] = $redirectRoute;
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

    /**
     * Cross-client action contract for a server-provided modal bottom sheet.
     *
     * Older native clients dispatch the upper-case action aliases while the
     * current SDUI engine canonicalizes them to `open_remote_sheet`.  Emitting
     * both endpoint keys also keeps list cards, regular buttons, and app-bar
     * actions on the same backend-driven contract.
     */
    public static function openBottomSheetAction(string $endpoint, string $title = '', array $props = []): array
    {
        return array_merge([
            'type' => 'OPEN_BOTTOM_SHEET',
            'action_type' => 'OPEN_BOTTOM_SHEET',
            'title' => $title,
            'endpoint' => $endpoint,
            'sheet_endpoint' => $endpoint,
        ], $props);
    }

    /**
     * Opens the app's native quotation composer while keeping lead/customer
     * prefill data server-driven.  The explicit action token prevents clients
     * from treating quotation creation as an ordinary page navigation.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $props
     */
    public static function openQuotationModalAction(string $endpoint, array $data = [], array $props = []): array
    {
        return array_merge([
            'type' => 'OPEN_QUOTATION_MODAL',
            'action_type' => 'OPEN_QUOTATION_MODAL',
            'title' => 'New quotation',
            'endpoint' => $endpoint,
            'sheet_endpoint' => $endpoint,
            'url' => $endpoint,
            'data' => $data,
        ], $props);
    }

    public static function popAction(): array
    {
        return [
            'type' => 'pop',
        ];
    }

    /**
     * Hands a prescription's resolved line items, patient and doctor straight
     * to the native POS cart state, then opens the interactive POS screen —
     * no intermediate checkout sheet. Handled entirely client-side.
     *
     * @param  array<string, mixed>  $payload  {rx_id, rx_number, customer, doctor, diagnosis, items[]}
     */
    public static function loadRxToPosAction(array $payload): array
    {
        return [
            'type' => 'load_rx_to_pos',
            'payload' => $payload,
        ];
    }

    /**
     * Hands a repair ticket's labor, diagnostic fee, replaced parts and linked
     * customer straight into the core native POS cart, then opens the POS
     * screen — no detached checkout sheet. Handled entirely client-side.
     *
     * @param  array<string, mixed>  $payload  {ticket_id, ticket_number, customer, device, defect, labor_cost, diagnostic_fee, parts[]}
     */
    public static function loadRepairToPosAction(array $payload): array
    {
        return [
            'type' => 'load_repair_to_pos',
            'payload' => $payload,
        ];
    }

    /**
     * The cart-ready payload for a repair ticket's "Checkout & Bill" action.
     *
     * @return array<string, mixed>
     */
    public static function repairPosPayload(RepairTicket $ticket): array
    {
        $device = trim("{$ticket->brand} {$ticket->model}");
        $serial = trim((string) ($ticket->serial_or_imei ?? ''));
        $deviceLabel = ($device !== '' ? $device : 'Repair Service')
            .($serial !== '' ? " (SN: {$serial})" : '');

        $parts = $ticket->parts
            ->map(static fn ($p) => [
                'product_id' => $p->product_id,
                'name' => $p->item_name ?: 'Replacement part',
                'unit_price' => round((float) $p->unit_price, 2),
                'quantity' => max(1, (int) round((float) $p->quantity)),
            ])
            ->values()
            ->all();

        return [
            'ticket_id' => (string) $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'customer' => [
                'id' => $ticket->customer_id,
                'name' => $ticket->customer_name,
                'phone' => $ticket->customer_phone,
            ],
            'device' => $deviceLabel,
            'defect' => $ticket->issue_description ?: ($ticket->reported_defect ?? null),
            'labor_cost' => round((float) $ticket->labor_fee, 2),
            'diagnostic_fee' => round((float) $ticket->diagnostic_fee, 2),
            'parts' => $parts,
        ];
    }

    public static function openUrlAction(string $url): array
    {
        return [
            'type' => 'open_url',
            'url' => $url,
        ];
    }

    /**
     * Re-opens the current SDUI view with the named form fields appended as
     * query params — a lightweight in-place "search / filter this list"
     * primitive. Only $fields are forwarded (never the whole shared form
     * scope), and the client replaces the current page rather than stacking a
     * new one.
     *
     * @param  list<string>  $fields
     */
    public static function filterViewAction(string $endpoint, array $fields = []): array
    {
        return [
            'type' => 'filter_view',
            'endpoint' => $endpoint,
            'fields' => array_values($fields),
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

    public static function sheet(string $title, array $components, string $layout = 'scroll_view', array $options = []): array
    {
        return array_merge([
            'type' => 'sheet',
            'schema_version' => self::SCHEMA_VERSION,
            'title' => $title,
            'layout' => $layout,
            'components' => $components,
        ], $options);
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

        return self::screen($company->display_name, [
            self::gridView($items, 2),
        ]);
    }

    /** Number of tabs in the Store Profile setup wizard. */
    private const PROFILE_WIZARD_TAB_COUNT = 5;

    /**
     * Store Profile — an icon-free Tabbed View driven as a setup wizard. Each
     * tab keeps its own submit button pointed at the same settings endpoint it
     * always used, so every field key (`name`, `country`, `timezone`,
     * `invoice_prefix`, …) stays backward-compatible; the tabs are a
     * presentational regroup. Every intermediate tab's button reads
     * "Save & Continue" and, on a 200, the response carries a
     * `next_action: { type: ADVANCE_TAB, ... }` directive so the client moves
     * to the next tab; the final tab reads "Complete Setup & Open Dashboard",
     * flags `is_profile_completed`, and routes to the dashboard.
     *
     * `?tab=address|branding|receipts` deep-links straight to a tab.
     */
    public static function profileView(Company $company): array
    {
        $tabParam = strtolower(trim((string) request('tab', '')));
        $initialIndex = match ($tabParam) {
            'address', 'localization', 'location', 'address-localization' => 1,
            'branding', 'appearance', 'colors', 'brand' => 2,
            'receipt', 'receipts', 'invoicing', 'invoice' => 3,
            'sounds', 'notifications', 'notification-sounds', 'notifications-sounds', 'alerts' => 4,
            default => 0,
        };

        return self::screen('Store Profile', [
            self::tabs([
                ['id' => 'general_info', 'label' => 'General Info', 'components' => self::profileGeneralTab($company)],
                ['id' => 'address_localization', 'label' => 'Address & Localization', 'components' => self::profileAddressTab($company)],
                ['id' => 'branding_appearance', 'label' => 'Branding & Appearance', 'components' => self::profileBrandingTab($company)],
                ['id' => 'receipt_invoicing', 'label' => 'Receipt & Invoicing', 'components' => self::profileReceiptTab($company)],
                ['id' => 'notifications_sounds', 'label' => 'Notifications & Sounds', 'components' => self::profileSoundsTab($company)],
            ], ['initial_index' => $initialIndex, 'is_scrollable' => true]),
        ]);
    }

    /**
     * The wizard submit button for one Store Profile tab. Intermediate tabs
     * read "Save & Continue" (forward arrow); the last tab reads
     * "Complete Setup & Open Dashboard" (check). The `wizard_tab_index` /
     * `wizard_total_tabs` payload tells `SduiViewController::submitSettings`
     * to return the `ADVANCE_TAB` directive after a successful save.
     */
    private static function profileWizardSaveButton(int $tabIndex, string $endpoint, string $intermediateToast): array
    {
        $isFinal = $tabIndex >= self::PROFILE_WIZARD_TAB_COUNT - 1;

        return self::buttonPrimary(
            $isFinal ? 'Complete Setup & Open Dashboard' : 'Save & Continue',
            self::formSubmitAction($endpoint, 'POST', $isFinal ? 'Store setup completed successfully!' : $intermediateToast, payload: [
                'wizard_tab_index' => $tabIndex,
                'wizard_total_tabs' => self::PROFILE_WIZARD_TAB_COUNT,
            ]),
            $isFinal ? 'check_circle' : 'arrow_forward',
        );
    }

    /**
     * Tab 1 — store identity + contact. Saves to /settings/profile.
     *
     * @return list<array<string, mixed>>
     */
    private static function profileGeneralTab(Company $company): array
    {
        return [
            self::card([
                self::text('Store Identity', 'title_medium', ['bold' => true]),
                self::text('Your public trading name, tax registration ID, and contact details.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::textInput('name', 'Business Name', $company->name, ['required' => true]),
                self::textInput('trade_name', 'Trading Name (DBA)', Company::isDemoPlaceholderName($company->trade_name) ? $company->name : $company->trade_name),
                self::textInput('tax_id', 'Tax ID / GSTIN / VAT', $company->tax_id),
                self::textInput('email', 'Store Email', $company->email, ['keyboard_type' => 'email']),
                self::textInput('phone', 'Store Phone', $company->phone, ['keyboard_type' => 'phone']),
                self::textInput('website', 'Store Website', $company->website),
            ]),
            self::profileWizardSaveButton(0, '/api/tenant/settings/profile', 'General info saved'),
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
        ];
    }

    /**
     * Tab 2 — address lines + searchable country / language / timezone.
     * Saves to /settings/profile.
     *
     * @return list<array<string, mixed>>
     */
    private static function profileAddressTab(Company $company): array
    {
        return [
            self::card([
                self::text('Address', 'title_medium', ['bold' => true]),
                self::divider(),
                self::textInput('address', 'Street Address', $company->address),
                self::textInput('city', 'City', $company->city),
                self::textInput('state', 'State / Province', $company->state),
                self::textInput('postal_code', 'Postal / Zip Code', $company->postal_code),
            ]),
            self::card([
                self::text('Localization', 'title_medium', ['bold' => true]),
                self::divider(),
                self::dropdownSelect('country', 'Country', PlatformRegionalService::countryOptions(), $company->country ?? 'US', ['searchable' => true, 'search_hint' => 'Search country name or ISO code']),
                self::dropdownSelect('default_locale', 'Store Primary Language', PlatformRegionalService::languageOptions(), $company->default_locale ?: ($company->language ?: 'en')),
                self::dropdownSelect('timezone', 'Store Timezone', PlatformRegionalService::timezoneOptions(), $company->timezone ?: $company->resolveTimezone(), ['searchable' => true, 'search_hint' => 'Search city or region (e.g. Kolkata, New_York, Sao_Paulo)']),
            ]),
            self::profileWizardSaveButton(1, '/api/tenant/settings/profile', 'Address & localization saved'),
        ];
    }

    /**
     * Tab 3 — store logo only, followed immediately by the wizard's
     * "Save & Continue" button. The logo uploads through its own endpoint on
     * pick. Brand / accent colours and full theme customisation live entirely
     * under App Preferences ▸ Appearance and the dedicated Branding & Colors
     * screen, so nothing else is surfaced here.
     *
     * @return list<array<string, mixed>>
     */
    private static function profileBrandingTab(Company $company): array
    {
        return [
            self::card([
                self::text('Store Logo', 'title_medium', ['bold' => true]),
                self::text('Shown on receipts, invoices and the app drawer header. Uploads as soon as you pick a file.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::fileUpload('logo', 'Store Logo', $company->getLogoUrl(), '/api/v1/pos/settings/profile/logo', [
                    'delete_endpoint' => '/api/v1/pos/settings/profile/logo',
                    'accept' => ['image/png', 'image/jpeg', 'image/webp'],
                    'response_url_path' => 'logo_url',
                ]),
            ]),
            self::profileWizardSaveButton(2, '/api/tenant/settings/profile', 'Branding saved'),
        ];
    }

    /**
     * Tab 4 — invoice/quote numbering + header/footer terms, then the wizard's
     * terminal "Complete Setup & Open Dashboard" button. Mirrors the standalone
     * Receipt Settings screen; saves to /settings/receipts with the same field
     * keys. Thermal-printer pairing stays on its own dedicated screen.
     *
     * @return list<array<string, mixed>>
     */
    private static function profileReceiptTab(Company $company): array
    {
        $hasPharmacy = $company->hasModule('pharmacy');
        $hasRepair = $company->hasModule('repair_technician');
        $hasSalon = $company->hasModule('service_booking');

        $numbering = [
            self::text('Invoice & Quote Numbering', 'title_medium', ['bold' => true]),
            self::text('Prefix tags used when generating official customer documents.', 'body_small', ['color' => '#6b7280']),
            self::divider(),
            self::textInput('invoice_prefix', 'Invoice Prefix', $company->invoice_prefix ?? 'INV-', ['placeholder' => 'INV-']),
            self::textInput('quotation_prefix', 'Quotation Prefix', $company->quotation_prefix ?? 'QUO-', ['placeholder' => 'QUO-']),
        ];
        if ($hasPharmacy) {
            $numbering[] = self::textInput('prescription_prefix', 'Prescription / Rx Prefix', $company->prescription_prefix ?? 'RX-', ['placeholder' => 'RX-']);
        }
        if ($hasRepair) {
            $numbering[] = self::textInput('repair_prefix', 'Repair Ticket Prefix', $company->repair_prefix ?? 'REP-', ['placeholder' => 'REP-']);
        }
        if ($hasSalon) {
            $numbering[] = self::textInput('salon_prefix', 'Salon Booking Prefix', $company->salon_prefix ?? 'SAL-', ['placeholder' => 'SAL-']);
        }

        $terms = [
            self::text('Invoice Header, Footer & Terms', 'title_medium', ['bold' => true]),
            self::divider(),
            self::textInput('invoice_terms', 'Invoice Terms & Conditions (footer)', $company->invoice_terms, ['max_lines' => 4, 'keyboard_type' => 'multiline']),
            self::textInput('quote_terms', 'Quotation Terms & Conditions', $company->quote_terms, ['max_lines' => 3, 'keyboard_type' => 'multiline']),
        ];
        if ($hasPharmacy) {
            $terms[] = self::textInput('dispensing_disclaimer', 'Prescription / Drug Dispensing Disclaimer & Policies', $company->dispensing_disclaimer, ['max_lines' => 3, 'keyboard_type' => 'multiline']);
        }
        if ($hasRepair) {
            $terms[] = self::textInput('repair_warranty_terms', 'Equipment Repair Warranty Disclaimer', $company->repair_warranty_terms, ['max_lines' => 3, 'keyboard_type' => 'multiline']);
        }
        if ($hasSalon) {
            $terms[] = self::textInput('salon_policy_terms', 'Salon Cancellation & Service Policies', $company->salon_policy_terms, ['max_lines' => 3, 'keyboard_type' => 'multiline']);
        }
        $terms[] = self::textInput('bank_details', 'Bank Account & Settlement Details', $company->bank_details, ['max_lines' => 3, 'keyboard_type' => 'multiline']);

        return [
            self::card($numbering),
            self::card($terms),
            self::profileWizardSaveButton(3, '/api/tenant/settings/receipts', 'Receipt settings saved'),
        ];
    }

    /**
     * Tab 5 — per-tenant push-alert sound preferences, then the wizard's
     * terminal "Complete Setup & Open Dashboard" button. Saves to
     * /settings/notification-sounds. Consumed by
     * FirebasePushService::sendToCompany()/sendToUser(), which merge these
     * into every data-only FCM push payload alongside the platform's global
     * high-importance channel settings (SuperAdmin ▸ Push Notifications).
     *
     * The custom-URL field has no way to hide itself when a non-"custom"
     * preset is picked — this schema has no conditional-field-visibility
     * primitive — so it stays always visible with its placeholder/caption
     * explaining it's only used for the "Custom Audio URL" preset.
     *
     * @return list<array<string, mixed>>
     */
    private static function profileSoundsTab(Company $company): array
    {
        $configs = Configuration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('key', ['order_sound_preset', 'order_sound_custom_url', 'delayed_order_sound', 'sound_vibration_enabled'])
            ->pluck('value', 'key')
            ->all();
        $sounds = SettingsApiController::presentNotificationSounds($configs);

        return [
            self::card([
                self::text('Order & KDS Sound Alerts', 'title_medium', ['bold' => true]),
                self::text('Customize the sound alert for incoming orders and for delayed/overdue order reminders.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::dropdownSelect('order_sound_preset', 'New Order Alert Sound', [
                    ['label' => 'Default System Ringtone', 'value' => 'ringtone'],
                    ['label' => 'Standard Kitchen Chime', 'value' => 'chime'],
                    ['label' => 'High-Priority Alarm', 'value' => 'alarm'],
                    ['label' => 'Subtle Bell', 'value' => 'bell'],
                    ['label' => 'Custom Audio URL', 'value' => 'custom'],
                ], $sounds['order_sound_preset']),
                self::textInput('order_sound_custom_url', 'Custom Audio MP3/WAV URL', $sounds['order_sound_custom_url'], [
                    'placeholder' => 'https://your-domain.com/sounds/kitchen-alert.mp3',
                    'keyboard_type' => 'url',
                ]),
                self::text('Used only when "New Order Alert Sound" above is set to Custom Audio URL — a direct link to a short MP3/WAV file.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::dropdownSelect('delayed_order_sound', 'Delayed Order Alert Sound', [
                    ['label' => 'Continuous Alarm', 'value' => 'alarm'],
                    ['label' => 'Urgent Siren', 'value' => 'siren'],
                    ['label' => 'Loud Double Beep', 'value' => 'beep'],
                    ['label' => 'System Default', 'value' => 'default'],
                ], $sounds['delayed_order_sound']),
                self::toggleSwitch('sound_vibration_enabled', 'Haptic / Vibration Alert', (bool) $sounds['sound_vibration_enabled']),
            ]),
            self::profileWizardSaveButton(4, '/api/tenant/settings/notification-sounds', 'Sound preferences saved'),
        ];
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
        $floors = DiningFloor::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['tables' => function ($q) {
                $q->withoutGlobalScope('company')->with('currentSale');
            }])
            ->orderBy('order_index')
            ->get();

        $currency = $company->currency_symbol ?: '$';
        $statusColors = [
            'available' => '#10b981',
            'occupied' => '#f59e0b',
            'reserved' => '#3b82f6',
            'billed' => '#8b5cf6',
        ];

        $allTablesCount = 0;
        $occupiedCount = 0;
        $availableCount = 0;
        $floorSections = [];

        foreach ($floors as $floor) {
            $tableCards = [];
            foreach ($floor->tables as $table) {
                $allTablesCount++;
                $status = strtolower((string) ($table->status ?: 'available'));
                if ($status === 'occupied') {
                    $occupiedCount++;
                } elseif ($status === 'available') {
                    $availableCount++;
                }

                $badgeColor = $statusColors[$status] ?? '#64748b';
                $currentSale = $table->currentSale;
                $actionSheetUrl = "/api/tenant/tables/{$table->id}/actions-sheet";

                $cardRows = [
                    self::row([
                        self::row([
                            self::icon('table_restaurant', ['color' => $badgeColor, 'size' => 22]),
                            self::column([
                                self::text("{$table->table_number}", 'title_medium', ['bold' => true, 'color' => '#F8FAFC']),
                                self::text("{$table->seating_capacity} Seats", 'body_small', ['color' => '#94A3B8']),
                            ]),
                        ], ['spacing' => 8]),
                        self::row([
                            self::badge(strtoupper($status), $badgeColor, 'solid'),
                            // Explicit more_vert menu button opening action sheet (/tables/{id}/actions-sheet)
                            self::buttonOutlined(
                                '',
                                self::openRemoteSheetAction($actionSheetUrl, "Table {$table->table_number} Actions"),
                                'more_vert',
                                ['dense' => true, 'full_width' => false, 'border_radius' => 20]
                            ),
                        ], ['spacing' => 4]),
                    ], ['main_axis_alignment' => 'space_between', 'cross_axis_alignment' => 'center']),
                ];

                if ($currentSale && $status !== 'available') {
                    $cardRows[] = self::divider();
                    $cardRows[] = self::row([
                        self::column([
                            self::text($currentSale->sale_number, 'label_small', ['color' => '#94A3B8']),
                            self::text("Guests: {$table->guest_count}", 'body_small', ['color' => '#94A3B8']),
                        ]),
                        self::text($currency.number_format((float) $currentSale->total, 2), 'title_medium', ['bold' => true, 'color' => '#10b981']),
                    ], ['main_axis_alignment' => 'space_between']);
                }

                $tableCards[] = self::card($cardRows, [
                    'color' => '#1E293B',
                    'border_color' => $status === 'occupied' ? '#f59e0b' : '#334155',
                    'border_radius' => 14,
                    'padding' => 12,
                ]);
            }

            $floorSections[] = self::card([
                self::row([
                    self::icon('apartment', ['color' => '#3b82f6', 'size' => 20]),
                    self::text($floor->name, 'title_medium', ['bold' => true, 'color' => '#F8FAFC']),
                    self::badge(count($tableCards).' Tables', '#3b82f6', 'subtle'),
                ], ['spacing' => 8]),
                self::divider(),
                self::column(! empty($tableCards) ? $tableCards : [
                    self::text('No tables placed on this floor yet.', 'body_medium', ['color' => '#94A3B8']),
                ]),
            ], ['color' => '#0F172A', 'border_color' => '#334155', 'border_radius' => 16]);
        }

        return self::screen('Floor Plan & Tables', [
            self::card([
                self::row([
                    self::icon('table_restaurant', ['color' => '#10b981', 'size' => 28]),
                    self::column([
                        self::text('Floor Plan & Table Management', 'title_medium', ['bold' => true, 'color' => '#F8FAFC']),
                        self::text("{$allTablesCount} Tables Total · {$availableCount} Available · {$occupiedCount} Occupied", 'body_small', ['color' => '#94A3B8']),
                    ]),
                ], ['spacing' => 10]),
            ], ['color' => '#1E293B', 'border_color' => '#334155', 'border_radius' => 16]),
            ...$floorSections,
        ]);
    }

    /**
     * Bottom Action Sheet for a single dining table (/tables/{id}/actions-sheet).
     */
    public static function tableActionsSheet(DiningTable $table, Company $company): array
    {
        $table->loadMissing(['floor', 'currentSale']);
        $status = strtolower((string) ($table->status ?: 'available'));
        $statusColors = [
            'available' => '#10b981',
            'occupied' => '#f59e0b',
            'reserved' => '#3b82f6',
            'billed' => '#8b5cf6',
        ];
        $badgeColor = $statusColors[$status] ?? '#64748b';
        $currency = $company->currency_symbol ?: '$';

        $headerComponents = [
            self::row([
                self::icon('table_restaurant', ['color' => $badgeColor, 'size' => 28]),
                self::column([
                    self::text("Table {$table->table_number}", 'title_large', ['bold' => true, 'color' => '#F8FAFC']),
                    self::text(($table->floor?->name ?: 'Dining Area')." · Capacity: {$table->seating_capacity} seats", 'body_small', ['color' => '#94A3B8']),
                ]),
                self::badge(strtoupper($status), $badgeColor, 'solid'),
            ], ['main_axis_alignment' => 'space_between']),
        ];

        $currentSale = $table->currentSale;
        if ($currentSale && $status !== 'available') {
            $itemCount = is_array($currentSale->items) ? count($currentSale->items) : 0;
            $headerComponents[] = self::divider();
            $headerComponents[] = self::container([
                self::row([
                    self::column([
                        self::text("Active Order: {$currentSale->sale_number}", 'label_large', ['bold' => true, 'color' => '#F8FAFC']),
                        self::text("Guest Count: {$table->guest_count} · {$itemCount} items ordered", 'body_small', ['color' => '#94A3B8']),
                    ]),
                    self::text($currency.number_format((float) $currentSale->total, 2), 'title_large', ['bold' => true, 'color' => '#10b981']),
                ], ['main_axis_alignment' => 'space_between']),
            ], [
                'color' => '#0F172A',
                'border_color' => '#334155',
                'padding' => 12,
                'border_radius' => 12,
            ]);
        }

        $actions = [];

        // 1. Take Order / Open POS for this table
        $actions[] = self::buttonPrimary(
            $currentSale ? 'Add More Items / Order POS' : 'Take Order / Open POS',
            self::openRemoteSheetAction("/api/tenant/pos/checkout-sheet?module=restaurant&table_id={$table->id}", "Table {$table->table_number} Cart"),
            'add_shopping_cart',
            ['background_color' => '#166534', 'border_radius' => 12]
        );

        // 2. Settle Bill (if occupied with sale)
        if ($currentSale) {
            $actions[] = self::buttonPrimary(
                'Settle Bill & Free Table',
                self::openRemoteSheetAction("/api/tenant/pos/checkout-sheet?module=restaurant&table_id={$table->id}&sale_id={$currentSale->id}", "Settle Table {$table->table_number}"),
                'payments',
                ['background_color' => '#0284c7', 'border_radius' => 12]
            );
        }

        // 3. Quick Status Change Toggles
        $statusOptions = [
            ['label' => 'Mark Available', 'value' => 'available', 'icon' => 'check_circle', 'color' => '#10b981'],
            ['label' => 'Mark Occupied', 'value' => 'occupied', 'icon' => 'people', 'color' => '#f59e0b'],
            ['label' => 'Mark Reserved', 'value' => 'reserved', 'icon' => 'bookmark', 'color' => '#3b82f6'],
            ['label' => 'Mark Billed', 'value' => 'billed', 'icon' => 'receipt', 'color' => '#8b5cf6'],
        ];

        $statusButtons = [];
        foreach ($statusOptions as $opt) {
            if ($opt['value'] === $status) {
                continue;
            }
            $statusButtons[] = self::buttonOutlined(
                $opt['label'],
                self::apiPostAction("/api/tenant/restaurant/tables/{$table->id}/status", ['status' => $opt['value']], "Table {$table->table_number} marked as {$opt['value']}", reload: true),
                $opt['icon'],
                ['dense' => true, 'full_width' => false, 'border_radius' => 10]
            );
        }

        $components = [
            self::card($headerComponents, ['color' => '#1E293B', 'border_color' => '#334155', 'border_radius' => 16]),
            self::card([
                self::text('Table Operations', 'label_large', ['bold' => true, 'color' => '#F8FAFC']),
                self::divider(),
                ...$actions,
                self::text('Quick Status Change', 'body_small', ['color' => '#94A3B8']),
                self::wrap($statusButtons, ['spacing' => 8]),
            ], ['color' => '#1E293B', 'border_color' => '#334155', 'border_radius' => 16]),
            self::buttonOutlined('Close Sheet', self::popAction(), 'close', ['border_radius' => 12]),
        ];

        return self::sheet("Table {$table->table_number} Actions", $components);
    }

    /**
     * Thermal Print text formatter for KOT tickets.
     */
    public static function formatKotThermalText(KitchenTicket $kot, Company $company): string
    {
        $storeName = strtoupper($company->display_name ?: 'RESTAURANT');
        $kotNumber = $kot->kot_number;
        $tableOrType = $kot->table_name ?: ucfirst(str_replace('_', ' ', $kot->service_type));
        $server = $kot->server_name ?: 'Staff';
        $time = ($kot->sent_to_kitchen_at ?: now())->format('d M Y, h:i A');
        $prepMins = (int) ($kot->prep_minutes ?: 15);
        $alertMins = (int) ($kot->intimation_minutes ?: 0);
        $targetTime = $kot->target_completion_at ? $kot->target_completion_at->format('h:i A') : now()->addMinutes($prepMins)->format('h:i A');

        $lines = [];
        $lines[] = '========================================';
        $lines[] = str_pad($storeName, 40, ' ', STR_PAD_BOTH);
        $lines[] = str_pad('KITCHEN ORDER TICKET (KOT)', 40, ' ', STR_PAD_BOTH);
        $lines[] = '========================================';
        $lines[] = sprintf('KOT: %-15s Table: %s', $kotNumber, $tableOrType);
        $lines[] = sprintf('Server: %-12s Time: %s', $server, $time);
        $lines[] = '----------------------------------------';
        $lines[] = sprintf('%-4s %-24s %s', 'QTY', 'ITEM', 'NOTES');
        $lines[] = '----------------------------------------';

        foreach ((array) ($kot->items ?? []) as $item) {
            $qty = $item['quantity'] ?? 1;
            $name = mb_substr((string) ($item['name'] ?? 'Item'), 0, 24);
            $note = $item['note'] ?? ($item['variant'] ?? '');
            $lines[] = sprintf('%-4s %-24s %s', $qty, $name, $note ? mb_substr($note, 0, 10) : '');
            if (! empty($item['modifiers']) && is_array($item['modifiers'])) {
                foreach ($item['modifiers'] as $mod) {
                    $modName = is_array($mod) ? ($mod['name'] ?? '') : (string) $mod;
                    if ($modName) {
                        $lines[] = '  + '.mb_substr($modName, 0, 35);
                    }
                }
            }
        }

        $lines[] = '----------------------------------------';
        $lines[] = sprintf('Estimated Prep: %d mins (Target: %s)', $prepMins, $targetTime);
        if ($alertMins > 0) {
            $lines[] = sprintf('Kitchen Alert: %d mins before expiry', $alertMins);
        }
        $lines[] = '========================================';

        return implode("\n", $lines);
    }

    /**
     * SDUI Modal Bottom Sheet containing thermal ticket preview and direct TRIGGER_PRINT action.
     */
    public static function kotPrintModalSheet(KitchenTicket $kot, Company $company, ?string $thermalText = null): array
    {
        $thermalText = $thermalText ?: self::formatKotThermalText($kot, $company);
        $kotNumber = $kot->kot_number;
        $tableOrType = $kot->table_name ?: ucfirst(str_replace('_', ' ', $kot->service_type));

        $components = [
            self::row([
                self::icon('soup_kitchen', ['color' => '#10b981', 'size' => 24]),
                self::column([
                    self::text("KOT Dispatched: {$kotNumber}", 'title_medium', ['bold' => true, 'color' => '#F8FAFC']),
                    self::text("Destination: {$tableOrType}", 'body_small', ['color' => '#94A3B8']),
                ]),
                self::badge('IN KITCHEN', '#10b981', 'solid'),
            ], ['main_axis_alignment' => 'space_between']),
            self::divider(),
            self::container([
                self::text($thermalText, 'body_small', [
                    'font_family' => 'monospace',
                    'color' => '#E2E8F0',
                    'line_height' => 1.3,
                ]),
            ], [
                'color' => '#0F172A',
                'border_color' => '#334155',
                'padding' => 12,
                'border_radius' => 12,
            ]),
            self::divider(),
            self::row([
                self::buttonPrimary('Print Thermal Ticket', [
                    'type' => 'TRIGGER_PRINT',
                    'action' => 'TRIGGER_PRINT',
                    'target' => 'thermal_printer',
                    'kot_id' => (string) $kot->id,
                    'kot_number' => $kotNumber,
                    'raw_text' => $thermalText,
                    'endpoint' => "/api/tenant/restaurant/kot/{$kot->id}/print",
                ], 'print', [
                    'background_color' => '#166534',
                    'border_radius' => 12,
                ]),
            ]),
            self::buttonOutlined('Dismiss / Back to POS', self::popAction(), 'check', [
                'border_radius' => 12,
            ]),
        ];

        return self::sheet("Kitchen Order Ticket · {$kotNumber}", $components);
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

    /**
     * @deprecated Superseded by the core native POS screen (client route 'pos').
     * The pharmacy drawer entry and the "Pharmacy POS" quick-action in the
     * prescription queue both open the native POS directly now. This endpoint
     * stays only for backward compatibility with shipped app builds and the
     * pharmacy/repair POS parity contract test.
     */
    public static function pharmacyPosView(Company $company): array
    {
        return PosScreenBuilder::pharmacyPosScreen($company);
    }

    /**
     * Drug Batches & Expiry Tracker — one screen, three tabs (Active Batches /
     * Register Batch / Stock Adjust & Returns) sitting under a compact summary
     * banner, instead of two large forms stacked above the list.
     *
     * Deep links: `?tab=register|adjust|active` selects the opening tab and
     * `?tab=adjust&batch_id=<id>` pre-fills the adjustment form. `?q=<term>`
     * filters the Active Batches list.
     */
    public static function pharmacyBatchesView(Company $company): array
    {
        $today = now()->toDateString();
        $in30Days = now()->addDays(30)->toDateString();

        $base = static fn () => PharmacyBatch::withoutGlobalScope('company')->where('company_id', $company->id);

        $totalBatches = $base()->count();
        $expiredCount = $base()->where('expiry_date', '<', $today)->count();
        $critical30Count = $base()->whereBetween('expiry_date', [$today, $in30Days])->count();

        $tabParam = strtolower(trim((string) request('tab', 'active')));
        $initialIndex = match ($tabParam) {
            'register', 'new', 'create', 'register-batch' => 1,
            'adjust', 'returns', 'audit', 'stock-adjust', 'stock_adjust' => 2,
            default => 0,
        };
        $prefillBatchId = trim((string) request('batch_id', ''));
        // `search` is the current param; `q` kept as a legacy alias.
        $search = trim((string) (request('search') ?? request('q', '')));

        // ---- Tab 1: Active Batches (FEFO list + search) ----
        $batchQuery = $base()->with('product')->orderBy('expiry_date', 'asc');
        if ($search !== '') {
            $batchQuery->where(function ($w) use ($search) {
                $w->where('batch_number', 'like', "%{$search}%")
                    ->orWhere('rack_location', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($p) use ($search) {
                        $p->where('name', 'like', "%{$search}%")
                            ->orWhere('generic_name', 'like', "%{$search}%");
                    });
            });
        }
        $batches = $batchQuery->limit(50)->get();

        $batchCards = [];
        foreach ($batches as $b) {
            $days = $b->days_until_expiry;
            $statusLabel = $days < 0 ? 'EXPIRED' : ($days <= 90 ? "{$days}d LEFT" : "SAFE · {$days}d");

            $batchCards[] = self::card([
                self::row([
                    self::icon('medication', ['color' => $b->expiry_color, 'size' => 22], ['flexible' => false]),
                    self::column([
                        self::text($b->product?->name ?? 'Unknown Medicine', 'title_small', ['bold' => true]),
                        self::text("Batch #{$b->batch_number} · Rack ".($b->rack_location ?: '—'), 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                    self::badge($statusLabel, $b->expiry_color, 'subtle'),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
                self::divider(),
                self::wrap([
                    self::badge("Stock: {$b->stock_qty}", '#0284c7', 'subtle'),
                    self::badge('Exp: '.($b->expiry_date?->format('Y-m-d') ?? '—'), '#64748b', 'subtle'),
                    self::badge('MRP: '.number_format((float) $b->selling_price, 2), '#059669', 'subtle'),
                ]),
                self::row([
                    self::buttonOutlined('Adjust', self::navigateAction("/api/tenant/views/pharmacy-batches?tab=adjust&batch_id={$b->id}", title: 'Stock Adjust & Returns'), 'tune', ['full_width' => false, 'dense' => true]),
                    self::buttonOutlined('Details', self::openModalAction("Batch #{$b->batch_number}", [
                        self::text($b->product?->name ?? 'Medicine', 'title_small', ['bold' => true]),
                        self::divider(),
                        self::text('Batch Number: '.$b->batch_number, 'body_small'),
                        self::text('Manufactured: '.($b->manufacturing_date?->format('Y-m-d') ?? '—'), 'body_small'),
                        self::text('Expiry: '.($b->expiry_date?->format('Y-m-d') ?? '—'), 'body_small'),
                        self::text("Remaining Stock: {$b->stock_qty} units", 'body_small'),
                        self::text('Rack / Shelf: '.($b->rack_location ?: 'Unassigned'), 'body_small'),
                        self::text('Cost / Selling: '.number_format((float) $b->cost_price, 2).' / '.number_format((float) $b->selling_price, 2), 'body_small'),
                        self::divider(),
                        self::buttonOutlined('Print Barcode', self::openUrlAction("/api/tenant/pharmacy/batches/{$b->id}/barcode"), 'qr_code_2'),
                    ]), 'history', ['full_width' => false, 'dense' => true]),
                ], ['spacing' => 8]),
            ]);
        }

        $activeTabChildren = [
            self::searchBar(
                'search',
                'Search medicine name, batch #, or rack...',
                '/api/tenant/views/pharmacy-batches?tab=active',
                ['initial_value' => $search],
            ),
        ];
        if ($search !== '') {
            $activeTabChildren[] = self::row([
                self::badge('Filter: "'.$search.'"', '#0284c7', 'subtle'),
                self::buttonOutlined('Clear', self::navigateAction('/api/tenant/views/pharmacy-batches?tab=active', title: 'Active Batches'), 'close', ['full_width' => false, 'dense' => true]),
            ], ['spacing' => 8]);
        }

        if (! empty($batchCards)) {
            $activeTabChildren = array_merge($activeTabChildren, $batchCards);
        } else {
            $activeTabChildren[] = self::card([
                self::icon('inventory_2', ['color' => '#94a3b8', 'size' => 40], ['flexible' => false]),
                self::text($search !== '' ? 'No batches match your search.' : 'No medicine batches registered yet.', 'body_medium', ['bold' => true, 'color' => '#475569']),
                self::text($search !== '' ? 'Try a different medicine name, batch number or rack.' : 'Register your first batch to start FEFO expiry tracking.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::buttonPrimary('+ Register First Batch', self::navigateAction('/api/tenant/views/pharmacy-batches?tab=register', title: 'Register Batch'), 'add_box', ['background_color' => '#059669']),
            ]);
        }

        // ---- Tab 2: Register Batch (standalone form) ----
        // Options MUST carry the integer primary key as `value` — the backend
        // validates product_id as an integer, so sending the medicine name
        // (the old pluck('name','id') shape) fails "must be an integer".
        $productOptions = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn ($p) => ['label' => (string) $p->name, 'value' => (int) $p->id])
            ->all();
        if (empty($productOptions)) {
            $productOptions = [['label' => 'General Medicine Item', 'value' => 0]];
        }

        $registerTabChildren = [
            self::card([
                self::text('Register a New Medicine Batch', 'title_medium', ['bold' => true]),
                self::text('Batch intake fields only — stock adjustments live on the next tab.', 'body_small', ['color' => '#64748b']),
            ]),
            self::dropdownSelect('product_id', 'Medicine / Drug *', $productOptions),
            self::textInput('batch_number', 'Batch ID / Lot Number *', ''),
            self::row([
                self::dateTimePicker('manufacturing_date', 'Manufacturing Date', null, 'date'),
                self::dateTimePicker('expiry_date', 'Expiry Date (FEFO) *', null, 'date'),
            ], ['spacing' => 8]),
            self::textInput('rack_location', 'Rack / Shelf Location', ''),
            self::row([
                self::textInput('cost_price', 'Cost Price ($)', '0.00', ['keyboard_type' => 'number']),
                self::textInput('selling_price', 'Selling Price ($)', '0.00', ['keyboard_type' => 'number']),
            ], ['spacing' => 8]),
            self::row([
                self::textInput('stock_qty', 'Initial Quantity *', '100', ['keyboard_type' => 'number']),
                self::textInput('alert_days_before_expiry', 'Alert Days Before Expiry', '90', ['keyboard_type' => 'number']),
            ], ['spacing' => 8]),
            self::divider(),
            self::buttonPrimary('Save Batch to Inventory', self::formSubmitAction(
                '/api/tenant/pharmacy/batches',
                'POST',
                'Batch saved to inventory.',
                redirectRoute: '/api/tenant/views/pharmacy-batches?tab=active'
            ), 'add_circle', ['background_color' => '#059669']),
        ];

        // ---- Tab 3: Stock Adjust & Returns (audit workspace) ----
        $adjustBatch = $prefillBatchId !== '' ? $base()->with('product')->find($prefillBatchId) : null;

        $adjustTabChildren = [
            self::card([
                self::text('Stock Adjustment & Vendor Returns', 'title_medium', ['bold' => true]),
                self::text('Audit physical stock or record a distributor return against a batch.', 'body_small', ['color' => '#64748b']),
            ]),
            self::textInput('batch_id', 'Batch ID / Medicine Search', $prefillBatchId),
        ];
        if ($adjustBatch) {
            $adjustTabChildren[] = self::wrap([
                self::badge($adjustBatch->product?->name ?? 'Medicine', '#0284c7', 'subtle'),
                self::badge("Current Qty: {$adjustBatch->stock_qty}", '#f59e0b', 'subtle'),
                self::badge("Batch #{$adjustBatch->batch_number}", '#64748b', 'subtle'),
            ]);
        }
        $adjustTabChildren = array_merge($adjustTabChildren, [
            self::textInput('new_stock_qty', 'New Audited Quantity', $adjustBatch ? (string) $adjustBatch->stock_qty : '0', ['keyboard_type' => 'number']),
            self::creatableSelect('reason', 'Adjustment Reason *', [
                ['label' => 'Physical audit / discrepancy', 'value' => 'Physical audit / discrepancy'],
                ['label' => 'Damaged stock', 'value' => 'Damaged stock'],
                ['label' => 'Vendor return', 'value' => 'Vendor return'],
                ['label' => 'Expired disposal', 'value' => 'Expired disposal'],
                ['label' => 'Supplier recall / withdrawal', 'value' => 'Supplier recall / withdrawal'],
                ['label' => 'Temperature excursion', 'value' => 'Temperature excursion'],
                ['label' => 'Breakage during unboxing', 'value' => 'Breakage during unboxing'],
                ['label' => 'Internal laboratory testing', 'value' => 'Internal laboratory testing'],
            ], 'Physical audit / discrepancy', ['required' => true]),
            self::textInput('notes', 'Notes', '', ['max_lines' => 2, 'placeholder' => 'Optional extra detail — lot conditions, personnel, references…']),
            self::divider(),
            self::row([
                self::buttonOutlined('Adjust Stock', self::formSubmitAction(
                    '/api/tenant/pharmacy/batches/adjust',
                    'POST',
                    'Batch stock adjusted.',
                    redirectRoute: '/api/tenant/views/pharmacy-batches?tab=active'
                ), 'tune'),
                self::buttonDanger('Vendor Return', self::formSubmitAction(
                    '/api/tenant/pharmacy/batches/return',
                    'POST',
                    'Vendor return recorded.',
                    redirectRoute: '/api/tenant/views/pharmacy-batches?tab=active'
                ), 'keyboard_return'),
            ], ['spacing' => 8]),
        ]);

        return self::screen('Batch & Expiry Manager', [
            self::card([
                self::row([
                    self::icon('inventory_2', ['color' => '#059669', 'size' => 24], ['flexible' => false]),
                    self::column([
                        self::text('Drug Batches & Expiry Tracker', 'title_medium', ['bold' => true]),
                        self::text('FEFO stock control for every medicine lot.', 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
                self::divider(),
                self::row([
                    self::badge("Total Batches: {$totalBatches}", '#0284c7', 'subtle'),
                    self::badge("Expiring ≤30d: {$critical30Count}", '#ea580c', 'subtle'),
                    self::badge("Expired: {$expiredCount}", '#ef4444', 'subtle'),
                ], ['scrollable' => true, 'spacing' => 8]),
            ]),

            self::tabs([
                ['id' => 'active_batches', 'label' => 'Active Batches', 'icon' => 'inventory_2', 'components' => $activeTabChildren],
                ['id' => 'register_batch', 'label' => 'Register Batch', 'icon' => 'add_box', 'components' => $registerTabChildren],
                ['id' => 'stock_adjust', 'label' => 'Stock Adjust & Returns', 'icon' => 'tune', 'components' => $adjustTabChildren],
            ], ['initial_index' => $initialIndex, 'is_scrollable' => true]),
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

            $lineItems = $isPending ? self::resolveRxLineItems($company, $rx) : [];
            $itemCount = array_sum(array_map(static fn ($i) => (int) $i['quantity'], $lineItems));

            $cardChildren = [
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
            ];

            if ($isPending && $lineItems !== []) {
                $cardChildren[] = self::text(
                    $itemCount.' item(s): '.implode(', ', array_map(static fn ($i) => $i['product_name'].' ×'.$i['quantity'], $lineItems)),
                    'body_small',
                    ['color' => '#0f766e']
                );
            }

            $cardChildren[] = self::divider();
            $cardChildren[] = $isPending
                ? self::buttonPrimary('Load Prescription into POS', self::loadRxToPosAction([
                    'rx_id' => (string) $rx->id,
                    'rx_number' => $rx->prescription_number,
                    'diagnosis' => $rx->diagnosis,
                    'patient_name' => $rx->patient_name,
                    'customer' => [
                        'id' => $rx->customer_id,
                        'name' => $rx->patient_name,
                        'phone' => $rx->patient_phone,
                    ],
                    'doctor' => [
                        'name' => $rx->doctor_name,
                        'registration_no' => $rx->doctor_registration_no ?? '',
                    ],
                    'items' => $lineItems,
                ]), 'shopping_cart_checkout')
                : self::badge('Dispensed Successfully', '#10b981', 'subtle');

            $rxCards[] = self::card($cardChildren);
        }

        return self::screen('Prescriptions Queue', [
            self::card([
                self::row([
                    self::icon('medical_information', ['color' => '#059669', 'size' => 28], ['flexible' => false]),
                    self::column([
                        self::text('Prescriptions & Patient Queue', 'title_medium', ['bold' => true]),
                        self::text('Doctor referrals, prescription intake, and controlled drug verification.', 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
                self::divider(),
                self::row([
                    self::badge("Total Prescriptions: {$totalRx}", '#0284c7', 'subtle'),
                    self::badge("Pending: {$pendingRx}", '#f59e0b', 'subtle'),
                    self::badge("Dispensed: {$dispensedRx}", '#10b981', 'subtle'),
                ]),
            ]),

            // Two compact buttons that wrap to a second line on narrow phones
            // rather than being squeezed into "+ New Prescrip..." / "Pharmacy
            // PO...". `dense` keeps them content-sized; `wrap` gives the
            // fallback stack.
            self::row([
                self::buttonPrimary('+ New Prescription Intake', self::navigateAction('/api/tenant/views/pharmacy-rx-create', title: 'New Prescription Intake'), 'note_add', ['dense' => true, 'full_width' => false, 'background_color' => '#059669']),
                self::buttonOutlined('Pharmacy POS', self::navigateAction('pos', title: 'Pharmacy POS'), 'point_of_sale', ['dense' => true, 'full_width' => false]),
            ], ['spacing' => 8, 'wrap' => true]),

            // Filter chips: a horizontally scrollable strip so "Pending (1)"
            // and "Dispensed (2)" always render in full, never clipped.
            self::row([
                $statusFilter === 'all'
                    ? self::buttonPrimary("All ({$totalRx})", self::navigateAction('/api/tenant/views/pharmacy-prescriptions?status=all', title: 'All Prescriptions'), 'receipt_long', ['full_width' => false, 'dense' => true])
                    : self::buttonOutlined("All ({$totalRx})", self::navigateAction('/api/tenant/views/pharmacy-prescriptions?status=all', title: 'All Prescriptions'), 'receipt_long', ['full_width' => false, 'dense' => true]),
                $statusFilter === 'pending'
                    ? self::buttonPrimary("Pending ({$pendingRx})", self::navigateAction('/api/tenant/views/pharmacy-prescriptions?status=pending', title: 'Pending Prescriptions'), 'hourglass_top', ['full_width' => false, 'dense' => true])
                    : self::buttonOutlined("Pending ({$pendingRx})", self::navigateAction('/api/tenant/views/pharmacy-prescriptions?status=pending', title: 'Pending Prescriptions'), 'hourglass_top', ['full_width' => false, 'dense' => true]),
                $statusFilter === 'dispensed'
                    ? self::buttonPrimary("Dispensed ({$dispensedRx})", self::navigateAction('/api/tenant/views/pharmacy-prescriptions?status=dispensed', title: 'Dispensed Prescriptions'), 'task_alt', ['full_width' => false, 'dense' => true])
                    : self::buttonOutlined("Dispensed ({$dispensedRx})", self::navigateAction('/api/tenant/views/pharmacy-prescriptions?status=dispensed', title: 'Dispensed Prescriptions'), 'task_alt', ['full_width' => false, 'dense' => true]),
            ], ['scrollable' => true, 'spacing' => 8]),

            self::card([
                self::text('Prescription Queue', 'title_medium', ['bold' => true]),
                self::column(! empty($rxCards) ? $rxCards : [
                    self::text("No prescriptions currently logged matching this status. Tap '+ New Prescription Intake' above to log a new prescription.", 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    /**
     * Resolve a prescription's stored `medicines` array into concrete catalog
     * line items the native POS cart can load directly. Medicines that don't
     * match an active product are skipped (the cashier adds them manually).
     *
     * @return list<array{product_id:int,product_name:string,quantity:int,unit_price:float,dosage:?string,days_supply:mixed}>
     */
    private static function resolveRxLineItems(Company $company, PharmacyPrescription $rx): array
    {
        $medicines = is_array($rx->medicines) ? $rx->medicines : [];
        if ($medicines === []) {
            return [];
        }

        $items = [];
        foreach ($medicines as $medicine) {
            $medicine = is_array($medicine) ? $medicine : ['name' => (string) $medicine];
            $name = trim((string) ($medicine['name'] ?? $medicine['medicine_name'] ?? ''));
            $productId = (int) ($medicine['product_id'] ?? 0);
            $qty = max(1, (int) ($medicine['qty'] ?? $medicine['quantity'] ?? 1));

            $query = Product::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('active', true);

            $product = $productId > 0 ? (clone $query)->find($productId) : null;
            if (! $product && $name !== '') {
                $product = (clone $query)
                    ->where(function ($q) use ($name) {
                        $q->where('name', $name)
                            ->orWhere('generic_name', $name)
                            ->orWhere('name', 'like', '%'.$name.'%')
                            ->orWhere('generic_name', 'like', '%'.$name.'%');
                    })
                    ->first();
            }

            if (! $product) {
                continue;
            }

            $batch = PharmacyBatch::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->where('stock_qty', '>=', $qty)
                ->where('expiry_date', '>=', now()->toDateString())
                ->orderBy('expiry_date')
                ->first();

            $unitPrice = (float) ($batch && $batch->selling_price > 0
                ? $batch->selling_price
                : ($product->sale_price ?? 0));

            $items[] = [
                'product_id' => (int) $product->id,
                'product_name' => $product->name,
                'quantity' => $qty,
                'unit_price' => round($unitPrice, 2),
                'dosage' => $medicine['dosage'] ?? $medicine['dosage_notes'] ?? null,
                'days_supply' => $medicine['days_supply'] ?? $medicine['duration_days'] ?? $rx->dosage_duration_days,
            ];
        }

        return $items;
    }

    public static function pharmacyRxCreateView(Company $company): array
    {
        $timezone = $company->resolveTimezone();
        $todayDate = now($timezone)->toDateString();

        return self::screen('New Prescription Intake', [
            self::card([
                self::row([
                    self::icon('note_add', ['color' => '#059669', 'size' => 28], ['flexible' => false]),
                    self::column([
                        self::text('New Prescription Intake', 'title_medium', ['bold' => true]),
                        self::text('Log doctor prescriptions, patient demographics, clinical indications, and dosage schedule.', 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
                self::divider(),
                self::row([
                    self::buttonOutlined('Prescriptions Queue', self::navigateAction('/api/tenant/views/pharmacy-prescriptions', title: 'Prescriptions & Patient Queue'), 'medical_information', ['dense' => true, 'full_width' => false]),
                    self::buttonOutlined('Pharmacy POS', self::navigateAction('pos', title: 'Pharmacy POS'), 'point_of_sale', ['dense' => true, 'full_width' => false]),
                ], ['spacing' => 8, 'wrap' => true]),
            ]),

            self::card([
                self::column([
                    self::text('Patient Information', 'title_medium', ['bold' => true]),
                    self::divider(),
                    self::customerSelector(
                        'customer_id',
                        'Patient / Customer Lookup',
                        '/api/tenant/customers/search',
                        [
                            'name_field' => 'patient_name',
                            'phone_field' => 'patient_phone',
                        ],
                        [
                            'name_label' => 'Patient Full Name *',
                            'phone_label' => 'Patient Contact Phone #',
                            'placeholder' => 'Search existing patient or type name below...',
                            'required' => true,
                        ]
                    ),
                    self::row([
                        self::textInput('age', 'Age (Optional)', '', [
                            'keyboard_type' => 'number',
                            'placeholder' => 'e.g. 35',
                        ]),
                        self::dropdownSelect('gender', 'Gender (Optional)', [
                            ['label' => 'Not specified', 'value' => ''],
                            ['label' => 'Female', 'value' => 'female'],
                            ['label' => 'Male', 'value' => 'male'],
                            ['label' => 'Other', 'value' => 'other'],
                        ], ''),
                    ], ['spacing' => 10]),
                    self::textInput('allergies', 'Known Allergies (Optional)', '', [
                        'placeholder' => 'e.g. Penicillin, Sulfa, Latex...',
                    ]),
                ], ['spacing' => 12]),
            ]),

            self::card([
                self::column([
                    self::text('Prescribing Doctor & Clinical Details', 'title_medium', ['bold' => true]),
                    self::divider(),
                    self::textInput('doctor_name', 'Prescribing Doctor Name *', '', [
                        'required' => true,
                        'placeholder' => 'Dr. Full Name',
                    ]),
                    self::textInput('doctor_registration_no', 'Doctor Registration / License # (Optional)', '', [
                        'placeholder' => 'e.g. MED-84729',
                    ]),
                    self::dateTimePicker('prescription_date', 'Prescription Date *', $todayDate, 'date'),
                    self::textInput('diagnosis', 'Diagnosis / Clinical Indications (Optional)', '', [
                        'max_lines' => 2,
                        'placeholder' => 'e.g. Acute Bronchitis, Hypertension Stage 1...',
                    ]),
                    self::textInput('notes', 'Prescribed Medicines, Dosages & Frequency *', '', [
                        'max_lines' => 3,
                        'placeholder' => 'e.g. Amoxicillin 500mg TDS x 7 days, Paracetamol 650mg SOS...',
                    ]),
                    self::textInput('dosage_duration_days', 'Days Supply / Dosage Duration', '30', [
                        'keyboard_type' => 'number',
                        'placeholder' => '30',
                    ]),
                    self::filePicker(
                        'rx_attachment_url',
                        'Prescription Document / Photo (Optional)',
                        '/api/tenant/uploads/prescription-doc',
                        ['hint' => 'Upload prescription photo or PDF (non-executable only)']
                    ),
                    self::divider(),
                    self::buttonPrimary('Save to Prescription Queue', self::formSubmitAction(
                        '/api/tenant/pharmacy/prescriptions',
                        'POST',
                        'Prescription logged to queue successfully.',
                        navigateBack: false,
                        redirectRoute: '/api/tenant/views/pharmacy-prescriptions'
                    ), 'post_add', ['background_color' => '#059669']),
                ], ['spacing' => 12]),
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
                $actions[] = self::buttonPrimary('Checkout & Bill', self::loadRepairToPosAction(self::repairPosPayload($t)), 'point_of_sale', ['full_width' => false]);
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
            ['label' => 'Fail / Damaged', 'value' => 'fail'],
            ['label' => 'Pending / Untested', 'value' => 'pending'],
            ['label' => 'Not Applicable (N/A)', 'value' => 'not_applicable'],
        ];

        // Step 4 checkpoints are whatever this tenant configured for its
        // vertical (Settings → Repair Checklist), not a hardcoded phone list.
        $checklistFields = [];
        foreach ($company->repairChecklistSchema() as $index => $item) {
            $checklistFields[] = self::dropdownSelect(
                "check_{$item['key']}",
                ($index + 1).". {$item['label']}",
                $checkOptions,
                $item['default'] ?? 'pass',
            );
        }
        $checklistFields[] = self::textInput(
            'custom_checklist',
            'Additional checkpoints (one per line — e.g. S-Pen, FaceID, Hinge)',
            '',
            ['max_lines' => 3, 'placeholder' => "S-Pen detection\nFace ID / IR camera\nWaterproof seal"],
        );

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
                        self::textInput('brand', $company->resolveFormFieldLabel('repair_intake', 'brand', 'Brand (e.g. Apple, Samsung, Dell, HP)'), ''),
                        self::textInput('model', $company->resolveFormFieldLabel('repair_intake', 'model', 'Model Name / Number (e.g. iPhone 14 Pro, Galaxy S23)'), ''),
                        self::textInput('serial_or_imei', $company->resolveFormFieldLabel('repair_intake', 'serial_or_imei', 'Serial Number or IMEI (Optional)'), ''),
                        self::textInput('passcode_or_pattern', $company->resolveFormFieldLabel('repair_intake', 'passcode_or_pattern', 'Device Screen Lock Passcode / Pattern'), ''),
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

                // Step 4 — Inspection Checklist (tenant-configured checkpoints)
                [
                    'title' => 'Intake Inspection Checklist',
                    'subtitle' => 'Verify the working state of each checkpoint before disassembly. Configure your own list in Settings → Repair Checklist.',
                    'components' => $checklistFields,
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

        // Interactive checklist. Each row is an action-sheet trigger: tap it,
        // pick Pass / Fail / Pending / N-A, and the option api_posts
        // {key, value} to .../checklist and reloads the view.
        $rawChecklist = (array) ($ticket->inspection_checklist ?? []);
        if ($rawChecklist === []) {
            $rawChecklist = array_map(
                static fn ($item) => ['key' => $item['key'], 'item_name' => $item['label'], 'status' => 'pending'],
                $company->repairChecklistSchema(),
            );
        }

        $checklistStatusMeta = static function (string $status): array {
            return match (strtolower($status)) {
                'pass' => ['PASS', '#15803d', 'check_circle'],
                'fail' => ['FAIL', '#dc2626', 'cancel'],
                'not_applicable', 'na', 'n/a' => ['N/A', '#64748b', 'block'],
                default => ['PENDING', '#f59e0b', 'hourglass_top'],
            };
        };

        $checklistItems = [];
        foreach ($rawChecklist as $c) {
            $itemName = is_array($c) ? ($c['item_name'] ?? $c['name'] ?? 'Checklist Item') : (string) $c;
            $status = is_array($c) ? (string) ($c['status'] ?? 'pending') : 'pending';
            $key = is_array($c) && ! empty($c['key'])
                ? (string) $c['key']
                : (Str::slug($itemName, '_') ?: 'check');
            [$statusLabel, $statusColor, $statusIcon] = $checklistStatusMeta($status);

            $checklistItems[] = self::actionSheetTrigger(
                "{$itemName}   —   {$statusLabel}",
                array_map(
                    static fn (array $opt) => [
                        'label' => $opt[0],
                        'icon' => $opt[2],
                        'action' => self::apiPostAction(
                            "/api/tenant/repair/tickets/{$ticket->id}/checklist",
                            ['key' => $key, 'value' => $opt[1]],
                            "{$itemName}: {$opt[0]}",
                            reload: true,
                        ),
                    ],
                    [
                        ['Pass', 'pass', 'check_circle'],
                        ['Fail / Damaged', 'fail', 'cancel'],
                        ['Pending / Untested', 'pending', 'hourglass_top'],
                        ['Not Applicable (N/A)', 'not_applicable', 'block'],
                    ],
                ),
                $statusIcon,
                ['sheet_title' => "Set status — {$itemName}"],
            );
        }

        // Header lifecycle status picker — moves the ticket through the 6
        // workshop stages, one tap per transition.
        $statusSelector = self::actionSheetTrigger(
            'Change Ticket Status',
            array_map(
                static fn (array $s) => [
                    'label' => $s[1],
                    'icon' => $s[2],
                    'action' => self::apiPostAction(
                        "/api/tenant/repair/tickets/{$ticket->id}/status",
                        ['status' => $s[0]],
                        "Moved to {$s[1]}",
                        reload: true,
                    ),
                ],
                [
                    ['received', '1. Received / Intake', 'inbox'],
                    ['diagnosing', '2. Diagnosing', 'biotech'],
                    ['waiting_parts', '3. Waiting for Parts', 'hourglass_top'],
                    ['in_progress', '4. In Progress', 'construction'],
                    ['ready', '5. Ready for Pickup', 'task_alt'],
                    ['delivered', '6. Delivered & Closed', 'verified'],
                ],
            ),
            'swap_horiz',
            ['sheet_title' => 'Move ticket to stage'],
        );

        // "Share Ticket" — a form_submit whose server response carries
        // `action: show_ticket_share_sheet`, so the existing dispatcher pops
        // the native WhatsApp / Thermal Print / System Share bottom sheet.
        // No wa.me force-redirect, no Flutter changes.
        $shareButton = self::buttonOutlined(
            'Share Ticket',
            self::formSubmitAction(
                "/api/tenant/repair/tickets/{$ticket->id}/share",
                'POST',
                'Opening share options…',
            ),
            'share',
        );

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
                    self::badge(strtoupper(str_replace('_', ' ', (string) ($ticket->status ?? 'received'))), $ticket->status_color, 'subtle'),
                ]),
                self::divider(),
                self::row([
                    self::text("Customer: {$ticket->customer_name}", 'body_medium', ['bold' => true]),
                    self::text("Phone: {$ticket->customer_phone}", 'body_small'),
                ]),
                self::text('Hardware Serial / IMEI: '.($ticket->serial_or_imei ?: 'N/A'), 'body_small', ['color' => '#64748b']),
                self::text('Passcode / Unlock Pattern: '.($ticket->passcode_or_pattern ?: 'None'), 'body_small', ['color' => '#dc2626']),
                self::divider(),
                $statusSelector,
                $shareButton,
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
                self::text('Tap any checkpoint to set Pass / Fail / Pending / N-A.', 'body_small', ['color' => '#64748b']),
                self::divider(),
                self::column(! empty($checklistItems) ? $checklistItems : [
                    self::text('No checkpoints configured. Add them in Settings → Repair Checklist.', 'body_small', ['color' => '#64748b']),
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
                self::buttonPrimary('Checkout & Bill', self::loadRepairToPosAction(self::repairPosPayload($ticket)), 'point_of_sale'),
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

    /**
     * Settings screen for the tenant's custom repair intake checklist.
     * GET /api/tenant/views/repair-checklist-settings
     */
    public static function repairChecklistSettingsView(Company $company): array
    {
        $schema = $company->repairChecklistSchema();
        $isCustom = is_array($company->repair_checklist_schema) && $company->repair_checklist_schema !== [];

        $currentText = implode("\n", array_map(
            static fn ($item) => $item['label'].' | '.($item['default'] ?? 'pass'),
            $schema,
        ));

        $previewRows = [];
        foreach ($schema as $i => $item) {
            $previewRows[] = self::row([
                self::text(($i + 1).". {$item['label']}", 'body_small', ['expanded' => true]),
                self::badge(strtoupper($item['default'] ?? 'pass'), '#0284c7', 'subtle'),
            ], ['cross_axis_alignment' => 'center']);
        }

        return self::screen('Repair Intake Checklist', [
            self::card([
                self::row([
                    self::icon('checklist', ['color' => '#0284c7', 'size' => 24], ['flexible' => false]),
                    self::column([
                        self::text('Custom Diagnostic Checklist', 'title_medium', ['bold' => true]),
                        self::text('Define the checkpoints your technicians verify at intake. Applies to every new repair ticket.', 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
                self::divider(),
                self::badge($isCustom ? 'Using your custom checklist' : 'Using default checklist', $isCustom ? '#15803d' : '#64748b', 'subtle'),
            ]),

            self::card([
                self::text('Current Checkpoints', 'label_large', ['bold' => true]),
                self::column(! empty($previewRows) ? $previewRows : [
                    self::text('No checkpoints.', 'body_small', ['color' => '#64748b']),
                ]),
            ]),

            self::card([
                self::text('Edit Checklist', 'label_large', ['bold' => true]),
                self::text('One checkpoint per line. Optionally append " | pass", " | fail", " | pending" or " | not_applicable" to set its default state.', 'body_small', ['color' => '#64748b']),
                self::divider(),
                self::textInput('checklist_labels', 'Checkpoints', $currentText, [
                    'max_lines' => 10,
                    'keyboard_type' => 'multiline',
                    'placeholder' => "Sole & heel wear | pass\nStitching integrity | pass\nWaterproofing | not_applicable",
                ]),
                self::divider(),
                self::buttonPrimary('Save Checklist', self::formSubmitAction(
                    '/api/tenant/settings/repair-checklist',
                    'POST',
                    'Repair checklist saved.',
                    reload: true,
                ), 'save'),
                self::buttonOutlined('Reset to Defaults', self::apiPostAction(
                    '/api/tenant/settings/repair-checklist',
                    ['reset' => true],
                    'Checklist reset to defaults.',
                    reload: true,
                ), 'restart_alt'),
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
        $selectedDate = request('date', request('appointment_date', now($timezone)->toDateString()));
        $day = Carbon::parse($selectedDate, $timezone);
        $selectedDateStr = $day->toDateString();

        $todayStr = now($timezone)->toDateString();
        $yesterdayStr = now($timezone)->subDay()->toDateString();
        $tomorrowStr = now($timezone)->addDay()->toDateString();

        $isToday = $selectedDateStr === $todayStr;
        $isYesterday = $selectedDateStr === $yesterdayStr;
        $isTomorrow = $selectedDateStr === $tomorrowStr;

        // The calendar is an optional enhancement to the counter POS. During
        // a rolling deploy the appointments table may briefly lag behind the
        // application code; render an empty calendar instead of a 500/blank
        // screen until migrations have caught up.
        $appointments = Schema::hasTable('salon_appointments')
            ? SalonAppointment::withoutGlobalScope('company')
                ->where(function ($q) use ($company) {
                    $q->where('company_id', $company->id)
                        ->orWhere('tenant_id', $company->id);
                })
                ->whereBetween('starts_at', [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()])
                ->with(['service:id,name,duration_minutes,sale_price', 'specialist:id,name'])
                ->orderBy('starts_at')
                ->get()
            : collect();
        $services = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->where(function ($q) {
                $q->where('type', 'service')
                    ->orWhere('duration_minutes', '>', 0)
                    ->orWhere('category_type', 'salon');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'duration_minutes', 'price', 'sale_price']);
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

            $customBadges = [];
            if (! empty($appointment->custom_fields) && is_array($appointment->custom_fields)) {
                foreach ($appointment->custom_fields as $cfKey => $cfVal) {
                    if (is_scalar($cfVal) && (string) $cfVal !== '') {
                        $label = ucwords(str_replace('_', ' ', (string) $cfKey));
                        $customBadges[] = self::badge("{$label}: {$cfVal}", '#6366f1', 'subtle', ['max_width' => 140]);
                    }
                }
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
                        ...$customBadges,
                    ], ['flexible' => false, 'cross_axis_alignment' => 'end', 'spacing' => 4]),
                ], ['spacing' => 8, 'cross_axis_alignment' => 'start']),
                ! empty($actions) ? self::wrap($actions) : self::badge('Appointment closed', $statusColor, 'subtle'),
            ]);
        }

        return self::screen('Service Booking Calendar', [
            self::card([
                self::row([
                    self::icon('calendar_month', ['color' => '#7c3aed', 'size' => 28], ['flexible' => false]),
                    self::column([
                        self::text('Service Appointments Calendar', 'title_medium', ['bold' => true]),
                        self::text("{$day->format('l, M j, Y')} · {$timezone} · technician time-slot booking", 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
                self::divider(),
                self::row([
                    self::buttonOutlined('◀ Yesterday', self::navigateAction("/api/tenant/views/salon-calendar?date={$yesterdayStr}", title: 'Service Booking Calendar'), 'chevron_left', [
                        'expanded' => true,
                        'dense' => true,
                        'background_color' => $isYesterday ? '#ede9fe' : null,
                    ]),
                    self::buttonOutlined('Today', self::navigateAction("/api/tenant/views/salon-calendar?date={$todayStr}", title: 'Service Booking Calendar'), 'today', [
                        'expanded' => true,
                        'dense' => true,
                        'background_color' => $isToday ? '#ede9fe' : null,
                    ]),
                    self::buttonOutlined('Tomorrow ▶', self::navigateAction("/api/tenant/views/salon-calendar?date={$tomorrowStr}", title: 'Service Booking Calendar'), 'chevron_right', [
                        'expanded' => true,
                        'dense' => true,
                        'background_color' => $isTomorrow ? '#ede9fe' : null,
                    ]),
                ], ['spacing' => 8]),
                self::divider(),
                self::wrap([
                    self::badge('Appointments: '.$appointments->count(), '#7c3aed', 'subtle'),
                    self::badge('Checked In: '.$appointments->where('status', 'checked_in')->count(), '#0284c7', 'subtle'),
                    self::badge('Available Specialists: '.$specialists->count(), '#10b981', 'subtle'),
                ]),
                self::divider(),
                self::row([
                    self::buttonPrimary('+ Book New Appointment', self::navigateAction('/api/tenant/views/salon-booking-create', title: 'Book Appointment'), 'add', ['expanded' => true, 'background_color' => '#15803d']),
                    self::buttonOutlined('Manage Rates & Services', self::navigateAction('/api/tenant/views/service-catalog', title: 'Service Catalog & Rates'), 'format_list_bulleted', ['expanded' => true]),
                ], ['spacing' => 10]),
                self::divider(),
                self::row([
                    self::buttonOutlined('Customize Form Labels', self::navigateAction('/api/tenant/views/settings-form-labels', title: 'Form Labels'), 'tune', ['expanded' => true, 'dense' => true]),
                    self::buttonOutlined('Specialists Roster', self::navigateAction('/api/tenant/views/service-stylists', title: 'Stylists & Staff Assignments'), 'badge', ['expanded' => true, 'dense' => true]),
                ], ['spacing' => 10]),
            ]),
            self::card([
                self::row([
                    self::column([
                        self::text('Daily Appointment Timeline', 'title_medium', ['bold' => true]),
                        self::text("Viewing: {$day->format('l, F j, Y')}", 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                    self::badge("{$appointments->count()} Bookings", '#7c3aed', 'subtle'),
                ], ['main_axis_alignment' => 'space_between', 'cross_axis_alignment' => 'center']),
                self::divider(),
                self::column($appointmentCards ?: [
                    self::text("No appointments booked for {$day->format('l, M j')}. Tap \"+ Book New Appointment\" above to reserve a specialist.", 'body_medium', ['color' => '#64748b']),
                ]),
            ]),
        ]);
    }

    public static function salonBookingCreateView(Company $company): array
    {
        $timezone = $company->resolveTimezone();
        $selectedDate = request('date', now($timezone)->toDateString());
        $day = Carbon::parse($selectedDate, $timezone);

        $services = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->where(function ($q) {
                $q->where('type', 'service')
                    ->orWhere('duration_minutes', '>', 0)
                    ->orWhere('category_type', 'salon');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'duration_minutes', 'price', 'sale_price']);

        $specialists = User::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('is_specialist', true)
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name']);

        $currency = $company->currency_symbol ?: '$';

        $serviceOptions = $services->map(function (Product $service) use ($currency) {
            $duration = (int) ($service->duration_minutes ?: 30);
            $rate = (float) ($service->price ?: $service->sale_price);

            return [
                'label' => "{$service->name} · {$duration} min · {$currency}".number_format($rate, 2),
                'value' => (string) $service->id,
            ];
        })->all();

        $specialistOptions = $specialists->map(fn ($specialist) => [
            'label' => $specialist->name,
            'value' => (string) $specialist->id,
        ])->all();

        $timeOptions = [];
        for ($minutes = 9 * 60; $minutes < 20 * 60; $minutes += 30) {
            $time = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            $timeOptions[] = ['label' => Carbon::createFromFormat('H:i', $time)->format('g:i A'), 'value' => $time];
        }

        $serviceLabel = $company->resolveFormFieldLabel('service_booking', 'service', 'Service & Duration *');
        $specialistLabel = $company->resolveFormFieldLabel('service_booking', 'specialist', 'Stylist / Specialist *');
        $dateLabel = $company->resolveFormFieldLabel('service_booking', 'appointment_date', 'Appointment Date *');
        $timeLabel = $company->resolveFormFieldLabel('service_booking', 'appointment_time', 'Start Time *');
        $nameLabel = $company->resolveFormFieldLabel('service_booking', 'client_name', 'Client Name *');
        $phoneLabel = $company->resolveFormFieldLabel('service_booking', 'client_phone', 'Client Phone *');
        $depositLabel = $company->resolveFormFieldLabel('service_booking', 'advance_deposit', 'Advance Deposit Amount (Optional)');
        $notesLabel = $company->resolveFormFieldLabel('service_booking', 'booking_notes', 'Booking Notes (Optional)');

        return self::screen('Book Appointment', [
            self::card([
                self::row([
                    self::icon('add_task', ['color' => '#15803d', 'size' => 28], ['flexible' => false]),
                    self::column([
                        self::text('Book Service / Appointment', 'title_medium', ['bold' => true]),
                        self::text('Schedule a client appointment with assigned specialist, time slot, and optional advance deposit.', 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
                self::divider(),
                self::row([
                    self::buttonOutlined('Open Calendar', self::navigateAction('/api/tenant/views/salon-calendar', title: 'Service Booking Calendar'), 'calendar_month', ['expanded' => true]),
                    self::buttonOutlined('Service Catalog & Rates', self::navigateAction('/api/tenant/views/service-catalog', title: 'Service Catalog & Rates'), 'format_list_bulleted', ['expanded' => true]),
                ], ['spacing' => 10]),
            ]),

            self::card([
                self::column([
                    self::text('Reservation Details', 'title_medium', ['bold' => true]),
                    self::divider(),
                    self::dropdownSelect('service_id', $serviceLabel, $serviceOptions, $serviceOptions[0]['value'] ?? ''),
                    self::dropdownSelect('specialist_id', $specialistLabel, $specialistOptions, $specialistOptions[0]['value'] ?? ''),
                    self::dateTimePicker('appointment_date', $dateLabel, $day->toDateString(), 'date'),
                    self::dropdownSelect('appointment_time', $timeLabel, $timeOptions, '09:00'),
                    self::customerSelector(
                        'customer_id',
                        'Client / Customer Lookup',
                        '/api/tenant/customers/search',
                        [
                            'name_field' => 'customer_name',
                            'phone_field' => 'customer_phone',
                        ],
                        [
                            'name_label' => $nameLabel,
                            'phone_label' => $phoneLabel,
                            'required' => true,
                        ]
                    ),
                    self::textInput('advance_paid', $depositLabel, '0.00', [
                        'keyboard_type' => 'decimal',
                        'placeholder' => '0.00',
                    ]),
                    self::dropdownSelect('deposit_payment_method', 'Advance Deposit Payment Method', [
                        ['label' => 'Cash', 'value' => 'cash'],
                        ['label' => 'Card', 'value' => 'card'],
                        ['label' => 'UPI / QR', 'value' => 'upi'],
                        ['label' => 'Bank Transfer', 'value' => 'bank_transfer'],
                    ], 'cash'),
                    self::textInput('notes', $notesLabel, '', [
                        'max_lines' => 3,
                        'placeholder' => 'Any special requests or instructions...',
                    ]),
                    self::divider(),
                    self::buttonPrimary('Confirm Appointment', self::formSubmitAction(
                        '/api/tenant/salon/appointments',
                        'POST',
                        'Appointment booked successfully.',
                        navigateBack: false,
                        redirectRoute: '/api/tenant/views/salon-calendar'
                    ), 'add_task', ['background_color' => '#15803d']),
                ], ['spacing' => 12]),
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

    public static function serviceCatalogRatesView(Company $company): array
    {
        return self::serviceOrdersView($company);
    }

    public static function serviceOrdersView(Company $company): array
    {
        $currency = $company->currency_symbol ?: '$';
        $services = Product::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->where(function ($q) {
                $q->where('type', 'service')
                    ->orWhere('duration_minutes', '>', 0)
                    ->orWhere('category_type', 'salon');
            })
            ->orderBy('name')
            ->get();

        $serviceCards = $services->map(function (Product $service) use ($currency) {
            $duration = (int) ($service->duration_minutes ?: 30);
            $rate = (float) ($service->price ?: $service->sale_price);

            return self::card([
                self::row([
                    self::icon('spa', ['color' => '#7c3aed', 'size' => 24], ['flexible' => false]),
                    self::column([
                        self::text($service->name, 'title_medium', ['bold' => true, 'max_lines' => 2]),
                        self::text($service->description ?: 'Professional service offering', 'body_small', ['color' => '#64748b', 'max_lines' => 2]),
                    ], ['expanded' => true, 'spacing' => 2]),
                    self::column([
                        self::badge("{$duration} min", '#7c3aed', 'subtle'),
                        self::text("{$currency}".number_format($rate, 2), 'title_large', ['bold' => true, 'color' => '#166534']),
                    ], ['flexible' => false, 'cross_axis_alignment' => 'end', 'spacing' => 4]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'start']),
                self::divider(),
                self::row([
                    self::buttonOutlined('Edit Rate', self::openRemoteSheetAction(
                        "/api/tenant/salon/services/{$service->id}/edit-sheet",
                        "Edit {$service->name}"
                    ), 'edit', ['expanded' => true, 'dense' => true, 'color' => '#7c3aed']),
                    self::buttonDanger('Remove', self::apiPostAction(
                        "/api/tenant/salon/services/{$service->id}/delete",
                        [],
                        'Service removed.',
                        reload: true
                    ), 'delete_outline', ['expanded' => true, 'dense' => true]),
                ], ['spacing' => 10]),
            ], ['padding' => 14, 'border_radius' => 12]);
        })->all();

        return self::screen('Service Catalog & Rates', [
            self::card([
                self::row([
                    self::icon('spa', ['color' => '#7c3aed', 'size' => 28], ['flexible' => false]),
                    self::column([
                        self::text('Service Catalog & Pricing Rates', 'title_medium', ['bold' => true]),
                        self::text('Define service offerings, standard durations in minutes, and hourly / flat rates.', 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
                self::divider(),
                self::wrap([
                    self::badge('Services Configured: '.$services->count(), '#7c3aed', 'subtle'),
                    self::badge('Duration-based scheduling active', '#10b981', 'subtle'),
                ]),
                self::divider(),
                self::row([
                    self::buttonPrimary('+ Add New Service', self::navigateAction('/api/tenant/views/service-create', title: 'Add New Service'), 'add', ['expanded' => true]),
                    self::buttonOutlined('Open Calendar', self::navigateAction('/api/tenant/views/service-calendar', title: 'Service Calendar'), 'calendar_month', ['expanded' => true]),
                ], ['spacing' => 10]),
            ]),

            self::card([
                self::text('Active Services in Catalog', 'title_medium', ['bold' => true]),
                self::text('All services configured here appear instantly in the appointment calendar and POS registers.', 'body_small', ['color' => '#64748b']),
            ]),

            self::column($serviceCards ?: [
                self::card([
                    self::column([
                        self::text('No services configured yet.', 'title_medium', ['bold' => true, 'color' => '#64748b']),
                        self::text('Click "+ Add New Service" above to add your first service to the catalog.', 'body_small', ['color' => '#64748b']),
                        self::buttonPrimary('+ Add New Service', self::navigateAction('/api/tenant/views/service-create', title: 'Add New Service'), 'add', ['full_width' => false]),
                    ], ['spacing' => 8, 'cross_axis_alignment' => 'center']),
                ]),
            ], ['spacing' => 10]),
        ]);
    }

    public static function serviceCreateView(Company $company): array
    {
        $currency = $company->currency_symbol ?: '$';

        $categories = Category::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categoryOptions = [
            ['label' => 'General Service / Uncategorized', 'value' => ''],
        ];
        foreach ($categories as $cat) {
            $categoryOptions[] = [
                'label' => $cat->name,
                'value' => (string) $cat->id,
            ];
        }

        return self::screen('Add New Service', [
            self::card([
                self::row([
                    self::icon('playlist_add', ['color' => '#166534', 'size' => 28], ['flexible' => false]),
                    self::column([
                        self::text('Register New Service', 'title_medium', ['bold' => true]),
                        self::text('Add a bookable service or treatment to your catalog with standard duration and pricing.', 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
            ]),

            self::card([
                self::column([
                    self::text('Service Details', 'title_medium', ['bold' => true]),
                    self::divider(),
                    self::textInput('name', 'Service Name *', '', [
                        'placeholder' => 'e.g. Haircut & Styling, Deep Tissue Massage, Beard Grooming',
                        'required' => true,
                    ]),
                    self::textInput('price', "Price / Labor Rate * ({$currency})", '0.00', [
                        'keyboard_type' => 'decimal',
                        'placeholder' => '0.00',
                        'required' => true,
                    ]),
                    self::textInput('duration_minutes', 'Standard Duration (Minutes) *', '30', [
                        'keyboard_type' => 'number',
                        'placeholder' => '30',
                        'required' => true,
                    ]),
                    self::dropdownSelect('category_id', 'Category / Department', $categoryOptions, ''),
                    self::textInput('description', 'Service Description / Inclusions (Optional)', '', [
                        'max_lines' => 3,
                        'placeholder' => 'Describe what is included in this service offering...',
                    ]),
                    self::divider(),
                    self::buttonPrimary('Save & Add to Catalog', self::formSubmitAction(
                        '/api/tenant/salon/services',
                        'POST',
                        'Service added to catalog successfully.',
                        navigateBack: true,
                        reload: true
                    ), 'check_circle', ['color' => '#166534']),
                    self::buttonOutlined('View Service Catalog', self::navigateAction(
                        '/api/tenant/views/service-catalog',
                        title: 'Service Catalog & Rates'
                    ), 'format_list_bulleted'),
                ], ['spacing' => 12]),
            ]),
        ]);
    }

    public static function formLabelsView(Company $company): array
    {
        $customizations = $company->form_field_customizations ?? [];
        if (! is_array($customizations)) {
            $customizations = [];
        }

        $booking = $company->getFormFieldLabels('service_booking');
        $repair = $company->getFormFieldLabels('repair_intake');
        $customer = $company->getFormFieldLabels('customer');

        return self::screen('Form Field Customizations', [
            self::card([
                self::row([
                    self::icon('tune', ['color' => '#7c3aed', 'size' => 28]),
                    self::column([
                        self::text('Custom Form Labels & Fields', 'title_medium', ['bold' => true]),
                        self::text('Rename field placeholders and labels to tailor forms to your business vertical (Salon, HVAC, Auto, Retail).', 'body_small', ['color' => '#64748b']),
                    ]),
                ]),
                self::divider(),
                self::wrap([
                    self::badge('Service Booking', '#7c3aed', 'subtle'),
                    self::badge('Repair Intake', '#0284c7', 'subtle'),
                    self::badge('Customer CRM', '#10b981', 'subtle'),
                ]),
            ]),

            self::accordionGroup('Service & Salon Booking Form Labels', [
                self::text('Labels displayed on the Service Booking Calendar & appointment sheets.', 'body_small', ['color' => '#64748b']),
                self::textInput('service_booking[service]', 'Service Selector Label', $booking['service'] ?? 'Service & Duration'),
                self::textInput('service_booking[specialist]', 'Stylist / Specialist Label', $booking['specialist'] ?? 'Stylist / Specialist'),
                self::textInput('service_booking[client_name]', 'Client Name Field Label', $booking['client_name'] ?? 'Client Name'),
                self::textInput('service_booking[client_phone]', 'Client Phone Field Label', $booking['client_phone'] ?? 'Client Phone'),
                self::textInput('service_booking[appointment_date]', 'Appointment Date Label', $booking['appointment_date'] ?? 'Appointment Date'),
                self::textInput('service_booking[appointment_time]', 'Start Time Label', $booking['appointment_time'] ?? 'Start Time'),
                self::textInput('service_booking[advance_deposit]', 'Advance Deposit Label', $booking['advance_deposit'] ?? 'Advance Deposit Amount (Optional)'),
                self::textInput('service_booking[booking_notes]', 'Booking Notes Label', $booking['booking_notes'] ?? 'Booking Notes'),
                self::buttonPrimary('Save Booking Labels', self::formSubmitAction(
                    '/api/tenant/settings/form-labels',
                    'POST',
                    'Booking labels updated successfully.',
                    reload: true
                ), 'save'),
            ], ['initially_expanded' => true]),

            self::accordionGroup('Repair & Service Intake Form Labels', [
                self::text('Labels displayed on Repair Intake and Device Job creation screens.', 'body_small', ['color' => '#64748b']),
                self::textInput('repair_intake[brand]', 'Brand Field Label', $repair['brand'] ?? 'Brand (e.g. Apple, Samsung, Dell, HP)'),
                self::textInput('repair_intake[model]', 'Model Field Label', $repair['model'] ?? 'Model Name / Number (e.g. iPhone 14 Pro, Galaxy S23)'),
                self::textInput('repair_intake[serial_or_imei]', 'Serial / Identifier Field Label', $repair['serial_or_imei'] ?? 'Serial Number or IMEI (Optional)'),
                self::textInput('repair_intake[passcode_or_pattern]', 'Passcode / Security Note Label', $repair['passcode_or_pattern'] ?? 'Device Screen Lock Passcode / Pattern'),
                self::buttonPrimary('Save Repair Labels', self::formSubmitAction(
                    '/api/tenant/settings/form-labels',
                    'POST',
                    'Repair labels updated successfully.',
                    reload: true
                ), 'save'),
            ]),

            self::accordionGroup('Customer & CRM Form Labels', [
                self::text('Labels displayed on Customer creation and CRM modal dialogs.', 'body_small', ['color' => '#64748b']),
                self::textInput('customer[name]', 'Customer Name Label', $customer['name'] ?? 'Customer Full Name'),
                self::textInput('customer[phone]', 'Customer Phone Label', $customer['phone'] ?? 'Contact Phone Number'),
                self::buttonPrimary('Save Customer Labels', self::formSubmitAction(
                    '/api/tenant/settings/form-labels',
                    'POST',
                    'Customer labels updated successfully.',
                    reload: true
                ), 'save'),
            ]),
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
                        self::text("Invoice #{$s->sale_number}", 'title_medium', ['bold' => true, 'color' => '#F8FAFC']),
                        self::text("{$customerName} • {$itemsCount} items", 'body_small', ['color' => '#CBD5E1']),
                        self::text($s->created_at?->format('M d, Y · h:i A') ?? 'Recent', 'body_small', ['color' => '#94a3b8']),
                    ]),
                    self::column([
                        self::text($currency.number_format((float) $s->total, 2), 'title_large', ['bold' => true, 'color' => '#059669']),
                        $statusBadge,
                    ]),
                ]),
                self::divider(),
                self::row([
                    self::text('Paid: '.$currency.number_format((float) ($s->paid_amount ?: $s->total), 2), 'body_small', ['bold' => true, 'color' => '#E2E8F0']),
                    self::text('Due: '.$currency.number_format((float) ($s->due_amount ?: 0), 2), 'body_small', ['color' => (float) $s->due_amount > 0 ? '#FCA5A5' : '#CBD5E1', 'bold' => true]),
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
            ], [
                'color' => $isPaid ? '#132A24' : '#182230',
                'background_color' => $isPaid ? '#132A24' : '#182230',
                'border_color' => $isPaid ? '#10B981' : '#334155',
                'border_opacity' => $isPaid ? 0.3 : 1,
                'border_radius' => 10,
                'elevation' => 0,
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
                self::wrap(array_filter([
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
                    self::buttonOutlined('Share Quote', self::openModalAction("Share Quotation #{$q->sale_number}", (function () use ($company, $q, $currency) {
                        $enabled = [];
                        try {
                            $enabled = app(TenantNotificationDispatcherService::class)->getEnabledChannels($company);
                        } catch (\Throwable) {
                        }

                        $channelCheckboxes = [];
                        if (! empty($enabled['whatsapp'])) {
                            $channelCheckboxes[] = self::checkbox('channels[]', 'Send via WhatsApp', true);
                        }
                        if (! empty($enabled['sms'])) {
                            $channelCheckboxes[] = self::checkbox('channels[]', 'Send via SMS', true);
                        }
                        if (! empty($enabled['email'])) {
                            $channelCheckboxes[] = self::checkbox('channels[]', 'Send via Email (PDF Attached)', true);
                        }

                        $modal = [
                            self::text("Dispatch Quotation #{$q->sale_number}", 'title_medium', ['bold' => true]),
                            self::text("Total Amount: {$currency}".number_format((float) $q->total, 2), 'body_small', ['color' => '#64748b']),
                            self::divider(),
                        ];

                        if (! empty($channelCheckboxes)) {
                            $modal[] = self::text('Active Delivery Channels', 'label_medium', ['bold' => true]);
                            $modal = array_merge($modal, $channelCheckboxes);
                            $modal[] = self::divider();
                        }

                        $modal[] = self::textInput('recipient_phone', 'Recipient Mobile Number', (string) ($q->customer?->phone ?? ''), ['placeholder' => 'e.g. 919876543210', 'keyboard_type' => 'phone']);
                        $modal[] = self::textInput('recipient_email', 'Recipient Email Address', (string) ($q->customer?->email ?? ''), ['placeholder' => 'client@example.com', 'keyboard_type' => 'email']);
                        $modal[] = self::buttonPrimary('Dispatch Quotation', self::formSubmitAction(
                            '/api/v1/tenant/notifications/dispatch',
                            'POST',
                            'Quotation proposal dispatched across configured channels!',
                            payload: [
                                'document_type' => 'quotation',
                                'document_id' => (string) $q->id,
                            ]
                        ), 'send');

                        return $modal;
                    })()), 'share'),
                ])),
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
            ->with(['sales' => function ($query) use ($company) {
                $query->withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where('due_amount', '>', 0)
                    ->where(fn ($operation) => $operation->whereNull('operation_type')->orWhere('operation_type', 'sale'))
                    ->latest('created_at');
            }])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $currency = $company->currency_symbol ?: '$';
        $totalCustomers = Customer::withoutGlobalScope('company')->where('company_id', $company->id)->count();
        $totalDue = (float) Customer::withoutGlobalScope('company')->where('company_id', $company->id)->sum('due_balance');

        $customerCards = [];
        foreach ($customers as $c) {
            $due = (float) $c->due_balance;
            $dueDocument = $c->sales->first();
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
                self::wrap((function () use ($c, $due, $currency, $dueDocument) {
                    $btns = [
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
                    ];

                    if ($due > 0 && $dueDocument instanceof Sale) {
                        $reminderData = self::postSaleActionData($dueDocument);
                        $reminderData['actions_endpoint'] = "/api/v1/tenant/receivables/{$dueDocument->id}/reminder-sheet?document_type={$reminderData['document_type']}";
                        $btns[] = self::buttonOutlined('Due Reminder', [
                            'type' => 'show_post_sale_sheet',
                            'data' => $reminderData,
                        ], 'notification_important');
                    }

                    return $btns;
                })()),
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
                        self::text("Shift #{$reg->id} • {$openerName}", 'title_medium', ['bold' => true, 'color' => '#F8FAFC']),
                        self::text("Opened: {$openedText}", 'body_small', ['color' => '#94A3B8']),
                        self::text("Closed: {$closedText}", 'body_small', ['color' => '#94A3B8']),
                    ]),
                    self::column([
                        $regBadge,
                        $diffBadge,
                    ]),
                ]),
                self::divider(),
                self::row([
                    self::text("Opening: {$currency}".number_format((float) $reg->opening_balance, 2), 'body_small', ['color' => '#94A3B8']),
                    self::text("Closing: {$currency}".number_format((float) ($reg->counted_closing_balance ?? $reg->expected_closing_balance ?? 0), 2), 'body_small', ['bold' => true, 'color' => '#F8FAFC']),
                ], ['main_axis_alignment' => 'space_between']),
            ], ['color' => '#1E293B', 'border_color' => '#334155', 'border_radius' => 12]);
        }

        $activeSectionComponents = [];
        if ($activeRegister !== null) {
            $activeSectionComponents = [
                self::row([
                    self::icon('point_of_sale', ['color' => '#10b981', 'size' => 28]),
                    self::column([
                        self::text('Register Shift Open', 'title_medium', ['bold' => true, 'color' => '#10b981']),
                        self::text("Opened by {$activeRegister->opener?->name} at {$activeRegister->opened_at?->format('M d, Y · h:i A')}", 'body_small', ['color' => '#94A3B8']),
                    ]),
                    self::badge('ACTIVE', '#10b981', 'solid'),
                ], ['main_axis_alignment' => 'space_between']),
                self::divider(),
                self::gridView([
                    self::card([
                        self::text('Opening Cash Float', 'label_medium', ['color' => '#94A3B8']),
                        self::text($currency.number_format((float) $activeRegister->opening_balance, 2), 'title_large', ['bold' => true, 'color' => '#F8FAFC']),
                    ], ['color' => '#1E293B', 'border_color' => '#334155', 'border_radius' => 12]),
                    self::card([
                        self::text('Expected Drawer Cash', 'label_medium', ['color' => '#94A3B8']),
                        self::text($currency.number_format((float) $activeRegister->expected_closing_balance, 2), 'title_large', ['bold' => true, 'color' => '#10b981']),
                    ], ['color' => '#1E293B', 'border_color' => '#334155', 'border_radius' => 12]),
                ], 2),
                self::divider(),
                self::wrap([
                    self::buttonPrimary('Cash In / Cash Out', self::openModalAction('Record Drawer Transaction', [
                        self::text('Cash Drawer Deposit / Withdrawal', 'title_medium', ['bold' => true, 'color' => '#F8FAFC']),
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
                        self::text('End Shift & Reconcile Cash Drawer', 'title_medium', ['bold' => true, 'color' => '#F8FAFC']),
                        self::text("Expected Cash in Drawer: {$currency}".number_format((float) $activeRegister->expected_closing_balance, 2), 'body_medium', ['color' => '#10b981']),
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
                        self::text('Cash Drawer is Closed', 'title_medium', ['bold' => true, 'color' => '#F8FAFC']),
                        self::text('No active cashier shift is running on this terminal. Open the drawer to begin.', 'body_small', ['color' => '#94A3B8']),
                    ]),
                    self::badge('CLOSED', '#64748b', 'subtle'),
                ], ['main_axis_alignment' => 'space_between']),
                self::divider(),
                self::buttonPrimary('Open Cash Register Shift', self::openModalAction('Start Cashier Shift', [
                    self::text('Open Register & Declare Opening Float', 'title_medium', ['bold' => true, 'color' => '#F8FAFC']),
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
            self::card($activeSectionComponents, ['color' => '#1E293B', 'border_color' => '#334155', 'border_radius' => 16]),

            self::card([
                self::text('Shift & Register History', 'title_medium', ['bold' => true, 'color' => '#F8FAFC']),
                self::column(! empty($historyCards) ? $historyCards : [
                    self::text('No past register shifts found.', 'body_medium', ['color' => '#94A3B8']),
                ]),
            ], ['color' => '#1E293B', 'border_color' => '#334155', 'border_radius' => 16]),
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
        // Only surface the document prefix / disclaimer fields for the
        // verticals this tenant actually runs — a pharmacy never issues repair
        // tickets or salon bookings, so those inputs are domain clutter.
        $hasPharmacy = $company->hasModule('pharmacy');
        $hasRepair = $company->hasModule('repair_technician');
        $hasSalon = $company->hasModule('service_booking');

        $numbering = [
            self::text('Invoice & Quote Numbering', 'title_medium', ['bold' => true]),
            self::text('Define prefix tags used when generating official customer invoices.', 'body_small', ['color' => '#6b7280']),
            self::divider(),
            self::textInput('invoice_prefix', 'Invoice Prefix', $company->invoice_prefix ?? 'INV-', ['placeholder' => 'INV-']),
            self::textInput('quotation_prefix', 'Quotation Prefix', $company->quotation_prefix ?? 'QUO-', ['placeholder' => 'QUO-']),
        ];
        if ($hasPharmacy) {
            $numbering[] = self::textInput('prescription_prefix', 'Prescription / Rx Prefix', $company->prescription_prefix ?? 'RX-', ['placeholder' => 'RX-']);
        }
        if ($hasRepair) {
            $numbering[] = self::textInput('repair_prefix', 'Repair Ticket Prefix', $company->repair_prefix ?? 'REP-', ['placeholder' => 'REP-']);
        }
        if ($hasSalon) {
            $numbering[] = self::textInput('salon_prefix', 'Salon Booking Prefix', $company->salon_prefix ?? 'SAL-', ['placeholder' => 'SAL-']);
        }

        $terms = [
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
        ];
        if ($hasPharmacy) {
            $terms[] = self::textInput('dispensing_disclaimer', 'Prescription / Drug Dispensing Disclaimer & Policies', $company->dispensing_disclaimer, [
                'max_lines' => 3,
                'keyboard_type' => 'multiline',
            ]);
        }
        if ($hasRepair) {
            $terms[] = self::textInput('repair_warranty_terms', 'Equipment Repair Warranty Disclaimer', $company->repair_warranty_terms, [
                'max_lines' => 3,
                'keyboard_type' => 'multiline',
            ]);
        }
        if ($hasSalon) {
            $terms[] = self::textInput('salon_policy_terms', 'Salon Cancellation & Service Policies', $company->salon_policy_terms, [
                'max_lines' => 3,
                'keyboard_type' => 'multiline',
            ]);
        }
        $terms[] = self::textInput('bank_details', 'Bank Account & Settlement Details', $company->bank_details, [
            'max_lines' => 3,
            'keyboard_type' => 'multiline',
        ]);

        return self::screen('Receipt Prefixes & Bank Terms', [
            self::card($numbering),
            self::card($terms),
            self::buttonPrimary('Save Receipt Settings', self::formSubmitAction(
                '/api/tenant/settings/receipts',
                'POST',
                'Receipt settings updated successfully'
            ), 'save'),
            self::card([
                self::text('Hardware & Printer', 'title_medium', ['bold' => true]),
                self::text('Pair a Bluetooth, USB or network (LAN/WiFi) thermal printer and set the receipt paper width (58mm / 80mm). Applies to every operating mode.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::buttonOutlined(
                    'Printer & Hardware Setup',
                    self::navigateAction('printer_setup', 'native', 'Printer & Hardware Setup'),
                    'print',
                ),
            ]),
        ]);
    }

    /**
     * GET /api/tenant/views/printer-setup
     *
     * Native clients resolve the `printer_setup` route to the on-device
     * GlobalPrinterSetupScreen (Bluetooth / USB / network pairing) and never
     * fetch this endpoint. It exists so the same drawer row degrades to a
     * useful screen on web / older builds instead of an empty shell.
     */
    public static function hardwareSetupView(Company $company): array
    {
        $schema = self::screen('Printer & Hardware Setup', [
            [
                'type' => 'segmented_tabs',
                'param_name' => 'connection_type',
                'active_value' => 'bluetooth',
                'options' => [
                    ['label' => 'Bluetooth', 'value' => 'bluetooth', 'selected' => true],
                    ['label' => 'USB', 'value' => 'usb', 'selected' => false],
                    ['label' => 'Network', 'value' => 'network', 'selected' => false],
                ],
                'active_background_color' => '#10B981',
                'active_text_color' => '#0B1120',
                'inactive_background_color' => '#1E293B',
                'inactive_text_color' => '#94A3B8',
                'border_color' => '#334155',
                'style' => [
                    'activeBackgroundColor' => '#10B981',
                    'activeTextColor' => '#0B1120',
                    'inactiveBackgroundColor' => '#1E293B',
                    'inactiveTextColor' => '#94A3B8',
                ],
            ],
            [
                'type' => 'empty_state',
                'icon' => 'print_disabled',
                'title' => 'No Bluetooth printer paired',
                'message' => 'No paired Bluetooth printers found. Pair a printer in your device settings, then return here to refresh.',
                'background_color' => '#0F172A',
                'border_color' => '#334155',
                'text_color' => '#E2E8F0',
                'secondary_text_color' => '#94A3B8',
                'icon_color' => '#10B981',
                'style' => [
                    'backgroundColor' => '#0F172A',
                    'borderColor' => '#334155',
                    'textColor' => '#E2E8F0',
                ],
            ],
            self::card([
                self::text('Device pairing happens on the terminal', 'title_medium', ['bold' => true]),
                self::text('Open this screen from the ZoomNearby app on the phone or tablet that is physically connected to the printer. There you can pair a Bluetooth, USB (OTG) or network (LAN/WiFi, port 9100) thermal printer, choose 58mm or 80mm paper, and run a test print.', 'body_small', ['color' => '#CBD5E1']),
                self::divider(),
                self::text('The selected printer and paper width are stored on that device and used automatically for every receipt, invoice and repair/pickup token — across all operating modes.', 'body_small', ['color' => '#CBD5E1']),
            ], ['color' => '#182230', 'border_color' => '#334155']),
            self::card([
                self::text('Receipt content & footers', 'label_large', ['bold' => true]),
                self::text('Prefixes, disclaimers and bank details printed on those receipts are configured under Receipt Settings.', 'body_small', ['color' => '#CBD5E1']),
                self::divider(),
                self::buttonOutlined(
                    'Open Receipt Settings',
                    self::navigateAction('/api/tenant/views/settings-receipts', 'dynamic_page', 'Receipt Settings'),
                    'receipt_long',
                ),
            ], ['color' => '#182230', 'border_color' => '#334155']),
        ]);

        $schema['background_color'] = '#0B1120';
        $schema['surface_color'] = '#182230';

        return $schema;
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

        // Payment methods are company-wide and shared by every module's POS —
        // surface a manager entry here so the mobile app has parity with the
        // web "Financial & Currency" tab.
        $activeTenders = PaymentMethod::getForCompany($company->id);
        $components[] = self::card([
            self::text('Payment Methods', 'title_medium', ['bold' => true]),
            self::text('Tender types offered at checkout across every module (retail, restaurant, pharmacy, salon, repair). Add UPI handles, bank accounts, wallets or store credit.', 'body_small', ['color' => '#6b7280']),
            self::divider(),
            self::wrap(
                $activeTenders->isNotEmpty()
                    ? $activeTenders->map(fn (PaymentMethod $pm) => self::badge($pm->name, '#2563eb', 'subtle'))->all()
                    : [self::badge('No payment methods configured', '#b45309', 'subtle')]
            ),
            self::buttonPrimary('Manage Payment Methods', self::navigateAction(
                '/api/tenant/views/settings-payment-methods',
                title: 'Payment Methods'
            ), 'account_balance_wallet'),
        ]);

        return self::screen('Financial & Currency', $components);
    }

    /**
     * Company-wide payment method manager. Backed by the same
     * SettingsApiController endpoints the web Settings screen uses, so a method
     * added here is immediately available at every module's checkout.
     */
    public static function paymentMethodsView(Company $company): array
    {
        $methods = PaymentMethod::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('order_index')
            ->orderBy('name')
            ->get();

        $cards = $methods->map(function (PaymentMethod $pm) {
            $meta = $pm->metadata ?? [];
            $metaBits = array_filter([
                ($meta['bank_name'] ?? null) ? 'Bank: '.$meta['bank_name'] : null,
                ($meta['account_no'] ?? null) ? 'A/C: '.$meta['account_no'] : null,
                ($meta['ifsc_code'] ?? null) ? 'IFSC: '.$meta['ifsc_code'] : null,
                ($meta['upi_id'] ?? null) ? 'UPI: '.$meta['upi_id'] : null,
                ($meta['holder_name'] ?? null) ? 'Holder: '.$meta['holder_name'] : null,
            ]);

            return self::card([
                self::row([
                    self::icon(ModuleRegistry::paymentMethodPresentation((string) ($pm->code ?: $pm->name))['icon'], ['color' => '#2563eb', 'size' => 24], ['flexible' => false]),
                    self::column([
                        self::text($pm->name, 'title_medium', ['bold' => true, 'max_lines' => 1]),
                        self::text(trim(((string) $pm->code).($pm->description ? '  ·  '.$pm->description : '')), 'body_small', ['color' => '#64748b', 'max_lines' => 2]),
                    ], ['expanded' => true, 'spacing' => 2]),
                    self::badge($pm->is_active ? 'Active' : 'Disabled', $pm->is_active ? '#16a34a' : '#9ca3af', 'subtle'),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'start']),
                ...($metaBits !== [] ? [self::text(implode('   ·   ', $metaBits), 'label_medium', ['color' => '#475569'])] : []),
                self::divider(),
                self::row([
                    self::buttonOutlined('Edit', self::openRemoteSheetAction(
                        "/api/tenant/settings/payment-methods/{$pm->id}/edit-sheet",
                        "Edit {$pm->name}"
                    ), 'edit', ['expanded' => true, 'dense' => true]),
                    self::buttonOutlined($pm->is_active ? 'Disable' : 'Enable', self::apiPostAction(
                        "/api/tenant/settings/payment-methods/{$pm->id}/toggle",
                        [],
                        'Payment method updated.',
                        reload: true
                    ), $pm->is_active ? 'toggle_off' : 'toggle_on', ['expanded' => true, 'dense' => true]),
                    self::buttonDanger('Remove', self::apiPostAction(
                        "/api/tenant/settings/payment-methods/{$pm->id}/delete",
                        [],
                        'Payment method removed.',
                        reload: true
                    ), 'delete_outline', ['expanded' => true, 'dense' => true]),
                ], ['spacing' => 8]),
            ], ['padding' => 14, 'border_radius' => 12]);
        })->all();

        return self::screen('Payment Methods', [
            self::card([
                self::row([
                    self::icon('account_balance_wallet', ['color' => '#2563eb', 'size' => 28], ['flexible' => false]),
                    self::column([
                        self::text('Company Payment Methods', 'title_medium', ['bold' => true]),
                        self::text('One shared list of tender types used by every module at checkout. Cash is always available.', 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
                self::divider(),
                self::buttonPrimary('+ Add Payment Method', self::navigateAction(
                    '/api/tenant/views/payment-method-create',
                    title: 'Add Payment Method'
                ), 'add', ['expanded' => true]),
            ]),
            self::column($cards ?: [
                self::card([
                    self::text('No payment methods configured yet.', 'title_medium', ['bold' => true, 'color' => '#64748b']),
                    self::text('Tap "+ Add Payment Method" to add Cash, Card, UPI, Bank Transfer, a wallet, or store credit.', 'body_small', ['color' => '#64748b']),
                ]),
            ], ['spacing' => 10]),
        ]);
    }

    /**
     * Add-payment-method form screen (opened from paymentMethodsView).
     */
    public static function paymentMethodCreateView(Company $company): array
    {
        $nextOrder = PaymentMethod::withoutGlobalScopes()->where('company_id', $company->id)->count() + 1;

        return self::screen('Add Payment Method', [
            self::card([
                self::row([
                    self::icon('playlist_add', ['color' => '#166534', 'size' => 28], ['flexible' => false]),
                    self::column([
                        self::text('Register New Payment Method', 'title_medium', ['bold' => true]),
                        self::text('Available instantly at every module checkout once saved.', 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
            ]),
            self::card([
                self::column([
                    self::text('Method Details', 'title_medium', ['bold' => true]),
                    self::divider(),
                    self::textInput('name', 'Display Name *', '', [
                        'required' => true,
                        'placeholder' => 'e.g. UPI / QR, PhonePe, HDFC Bank Transfer, Store Credit',
                    ]),
                    self::textInput('code', 'Short Code (Optional)', '', [
                        'placeholder' => 'auto-generated from the name if left blank (e.g. upi_qr)',
                    ]),
                    self::textInput('description', 'Description (Optional)', '', [
                        'max_lines' => 2,
                        'placeholder' => 'Shown as a hint on the checkout tender list',
                    ]),
                    self::textInput('order_index', 'Display Order', (string) $nextOrder, ['keyboard_type' => 'number']),
                    self::toggleSwitch('is_active', 'Active (show at checkout)', true),
                ], ['spacing' => 12]),
            ]),
            self::card([
                self::column([
                    self::text('Bank / UPI Details (Optional)', 'title_medium', ['bold' => true]),
                    self::text('Printed on receipts and invoices for bank-transfer tenders.', 'body_small', ['color' => '#64748b']),
                    self::divider(),
                    self::textInput('metadata[bank_name]', 'Bank Name', ''),
                    self::textInput('metadata[account_no]', 'Account Number', ''),
                    self::textInput('metadata[ifsc_code]', 'IFSC / SWIFT Code', ''),
                    self::textInput('metadata[upi_id]', 'UPI ID / VPA', ''),
                    self::textInput('metadata[holder_name]', 'Account Holder Name', ''),
                    self::divider(),
                    self::buttonPrimary('Save Payment Method', self::formSubmitAction(
                        '/api/tenant/settings/payment-methods',
                        'POST',
                        'Payment method added successfully.',
                        navigateBack: true,
                        reload: true
                    ), 'check_circle', ['color' => '#166534']),
                    self::buttonOutlined('Back to Payment Methods', self::navigateAction(
                        '/api/tenant/views/settings-payment-methods',
                        title: 'Payment Methods'
                    ), 'format_list_bulleted'),
                ], ['spacing' => 12]),
            ]),
        ]);
    }

    /**
     * "App Preferences" — the brand colour (server-persisted, applies on every
     * device) plus the per-device theme / page-transition / dashboard-layout /
     * nav-placement selectors. The native client renders these live; this SDUI
     * tree is the web / preview fallback.
     */
    public static function appearanceView(Company $company): array
    {
        return self::screen('App Preferences', [
            self::card([
                self::text('Brand Colour', 'title_medium', ['bold' => true]),
                self::text('Applied to buttons, active menu items, badges and focus rings across every device.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::colorPicker('primary_color', 'Primary Colour', $company->primary_color ?? '#4F46E5'),
                self::colorPicker('accent_color', 'Accent Colour', $company->accent_color ?? '#D97706'),
                self::buttonPrimary('Save Brand Colour', self::formSubmitAction(
                    '/api/tenant/settings/profile',
                    'POST',
                    'Brand colour updated'
                ), 'palette'),
            ]),
            self::card([
                self::text('Theme Mode', 'title_medium', ['bold' => true]),
                self::divider(),
                self::dropdownSelect('app_theme_mode', 'Appearance', [
                    ['label' => 'Match device', 'value' => 'system'],
                    ['label' => 'Light', 'value' => 'light'],
                    ['label' => 'Dark', 'value' => 'dark'],
                ], 'system'),
            ]),
            self::card([
                self::text('Page Transition', 'title_medium', ['bold' => true]),
                self::divider(),
                self::dropdownSelect('app_page_transition', 'When opening a link or menu item', [
                    ['label' => 'Slide', 'value' => 'slide'],
                    ['label' => 'Fade through', 'value' => 'fade'],
                    ['label' => 'Zoom', 'value' => 'zoom'],
                    ['label' => 'Instant', 'value' => 'none'],
                ], 'slide'),
            ]),
            self::card([
                self::text('Navigation Menu Placement', 'title_medium', ['bold' => true]),
                self::divider(),
                self::dropdownSelect('app_nav_dock', 'Menu position', [
                    ['label' => 'Left sidebar', 'value' => 'left'],
                    ['label' => 'Top navigation bar', 'value' => 'top'],
                    ['label' => 'Right sidebar', 'value' => 'right'],
                    ['label' => 'Bottom bar', 'value' => 'bottom'],
                ], 'left'),
                self::text('Theme mode, page transition and menu placement are stored per device — the desktop / mobile app applies them the moment you change them.', 'body_small', ['color' => '#94a3b8']),
            ]),
            self::card([
                self::row([
                    self::icon('notifications_active', ['color' => '#10B981', 'size' => 24]),
                    self::column([
                        self::text('Notifications & Audio Alerts', 'title_medium', ['bold' => true]),
                        self::text('Custom sound alerts, recurring alarms, and vibration for delayed orders, online orders, invoices and stock alerts.', 'body_small', ['color' => '#94a3b8']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
                self::divider(),
                self::buttonPrimary('Manage Sound & Alert Preferences', self::navigateAction(
                    '/api/v1/tenant/settings/app-preferences/notifications',
                    title: 'Notification Preferences'
                ), 'volume_up'),
            ]),
        ]);
    }

    public static function localizationView(Company $company): array
    {
        return self::screen('Localization & Region', [
            self::card([
                self::text('Regional Localization & Store Defaults', 'title_medium', ['bold' => true]),
                self::text('Configure primary store language and operating timezone inherited or overridden from platform baseline.', 'body_small', ['color' => '#6b7280']),
                self::divider(),
                self::dropdownSelect('default_locale', 'Store Primary Language', PlatformRegionalService::languageOptions(), $company->default_locale ?: ($company->language ?: 'en')),
                self::dropdownSelect('timezone', 'Store Operating Timezone', PlatformRegionalService::timezoneOptions(), $company->timezone ?: $company->resolveTimezone(), ['searchable' => true, 'search_hint' => 'Search city or region (e.g. Kolkata, New_York, Sao_Paulo)']),
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
        $tabParam = strtolower(trim((string) request('tab', 'config')));
        $initialIndex = match ($tabParam) {
            'saved', 'rules', 'saved-rules', 'tax-rules' => 1,
            'add', 'new', 'create', 'add-rule', 'new-rule' => 2,
            default => 0,
        };

        return self::screen('Taxes & Compliance', [
            self::tabs([
                ['id' => 'tax_config', 'label' => 'Tax Configuration', 'icon' => 'tune', 'components' => self::taxConfigTabComponents($company)],
                ['id' => 'saved_tax_rules', 'label' => 'Saved Tax Rules', 'icon' => 'receipt_long', 'components' => self::taxRuleSavedTabComponents($company)],
                ['id' => 'add_tax_rule', 'label' => 'Add New Tax Rule', 'icon' => 'add_box', 'components' => self::taxRuleAddTabComponents($company)],
            ], ['initial_index' => $initialIndex, 'is_scrollable' => true]),
        ]);
    }

    /**
     * Tab 1 — fiscal identifiers, receipt label, inclusive/breakdown toggles.
     *
     * @return list<array<string, mixed>>
     */
    private static function taxConfigTabComponents(Company $company): array
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

        return $components;
    }

    /**
     * Tab 2 — the saved TaxRule records (same rows the web Settings > Taxes
     * tab manages), each with edit / default / enable / remove actions.
     *
     * @return list<array<string, mixed>>
     */
    private static function taxRuleSavedTabComponents(Company $company): array
    {
        $rules = TaxRule::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderBy('tax_name')
            ->get();

        $ruleCards = $rules->map(function (TaxRule $rule) {
            $badges = [self::badge($rule->active ? 'Active' : 'Disabled', $rule->active ? '#16a34a' : '#9ca3af', 'subtle')];
            if ($rule->is_default) {
                $badges[] = self::badge('Default', '#2563eb', 'solid');
            }

            return self::card([
                self::row([
                    self::icon('percent', ['color' => '#7c3aed', 'size' => 22], ['flexible' => false]),
                    self::column([
                        self::text($rule->tax_name, 'title_small', ['bold' => true, 'max_lines' => 2]),
                        self::text(number_format((float) $rule->rate, 3).'%'.($rule->tax_code ? '  ·  '.$rule->tax_code : ''), 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                    self::wrap($badges, ['spacing' => 6]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'start']),
                self::divider(),
                self::row([
                    self::buttonOutlined('Edit', self::openRemoteSheetAction(
                        "/api/tenant/settings/tax-rules/{$rule->id}/edit-sheet",
                        "Edit {$rule->tax_name}"
                    ), 'edit', ['expanded' => true, 'dense' => true]),
                    ...($rule->is_default ? [] : [self::buttonOutlined('Make Default', self::apiPostAction(
                        "/api/tenant/settings/tax-rules/{$rule->id}/set-default",
                        [],
                        'Default tax rule updated.',
                        reload: true
                    ), 'star_outline', ['expanded' => true, 'dense' => true])]),
                    self::buttonOutlined($rule->active ? 'Disable' : 'Enable', self::apiPostAction(
                        "/api/tenant/settings/tax-rules/{$rule->id}/toggle",
                        [],
                        'Tax rule updated.',
                        reload: true
                    ), $rule->active ? 'toggle_off' : 'toggle_on', ['expanded' => true, 'dense' => true]),
                    self::buttonDanger('Remove', self::apiPostAction(
                        "/api/tenant/settings/tax-rules/{$rule->id}/delete",
                        [],
                        'Tax rule removed.',
                        reload: true
                    ), 'delete_outline', ['expanded' => true, 'dense' => true]),
                ], ['spacing' => 8, 'wrap' => true]),
            ], ['padding' => 14, 'border_radius' => 12]);
        })->all();

        return [
            self::card([
                self::row([
                    self::icon('receipt_long', ['color' => '#7c3aed', 'size' => 26], ['flexible' => false]),
                    self::column([
                        self::text('Saved Tax Rules', 'title_medium', ['bold' => true]),
                        self::text('Named rates applied at checkout and to products. The default rate is applied automatically to new products.', 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
                self::divider(),
                self::buttonPrimary('+ Add New Tax Rule', self::navigateAction(
                    '/api/tenant/views/settings-taxes?tab=add',
                    title: 'Taxes & Compliance'
                ), 'add', ['expanded' => true]),
            ]),
            self::column($ruleCards ?: [
                self::card([
                    self::text('No tax rules yet.', 'title_small', ['bold' => true, 'color' => '#64748b']),
                    self::text('Open the "Add New Tax Rule" tab to create one, or auto-add your country\'s standard rules.', 'body_small', ['color' => '#64748b']),
                ]),
            ], ['spacing' => 10]),
        ];
    }

    /**
     * Tab 3 — "Add New Tax Rule": a one-tap "auto-add my country's standard
     * rules" card (same presets the web Settings > Taxes tab pre-seeds via
     * TaxCalculationService::seedTenantDefaultTaxRules) plus a manual form.
     *
     * @return list<array<string, mixed>>
     */
    private static function taxRuleAddTabComponents(Company $company): array
    {
        $country = strtoupper(trim((string) ($company->country ?: 'US')));
        $service = app(TaxCalculationService::class);
        $preset = $service->getJurisdictionPresets($country);
        $countryName = $preset['country'] ?? $country;
        $presetRules = is_array($preset['rules'] ?? null) ? $preset['rules'] : [];

        $presetPreview = [];
        foreach (array_slice($presetRules, 0, 8) as $r) {
            $presetPreview[] = self::badge(
                ($r['name'] ?? 'Rule').' · '.rtrim(rtrim(number_format((float) ($r['rate'] ?? 0), 2), '0'), '.').'%',
                '#7c3aed',
                'subtle'
            );
        }

        $hasDefault = TaxRule::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_default', true)
            ->exists();

        $components = [];

        if ($presetRules !== []) {
            $components[] = self::card([
                self::row([
                    self::icon('public', ['color' => '#0284c7', 'size' => 26], ['flexible' => false]),
                    self::column([
                        self::text("Auto-add standard rules for {$countryName}", 'title_medium', ['bold' => true]),
                        self::text(($preset['system'] ?? 'Standard fiscal rates').' — '.count($presetRules).' rule'.(count($presetRules) === 1 ? '' : 's').'. Existing rules with the same code are updated, not duplicated.', 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
                ...($presetPreview !== [] ? [self::divider(), self::wrap($presetPreview, ['spacing' => 6, 'run_spacing' => 6])] : []),
                self::divider(),
                self::buttonPrimary("Auto-add {$countryName} Tax Rules", self::apiPostAction(
                    '/api/tenant/settings/tax-rules/seed-country',
                    ['country' => $country],
                    "Standard tax rules for {$countryName} added.",
                    reload: true
                ), 'auto_awesome', ['background_color' => '#0284c7', 'expanded' => true]),
                self::text('Change the store country under Localization & Region first if it is wrong.', 'label_medium', ['color' => '#94a3b8']),
            ], ['padding' => 14, 'border_radius' => 12]);
        }

        $components[] = self::card([
            self::column([
                self::text('Or add a rule manually', 'title_medium', ['bold' => true]),
                self::divider(),
                self::textInput('name', 'Name *', '', [
                    'required' => true,
                    'placeholder' => 'e.g. Standard VAT, State Sales Tax, Zero-Rated',
                ]),
                self::textInput('rate', 'Rate (%) *', '0', [
                    'required' => true,
                    'keyboard_type' => 'decimal',
                    'placeholder' => 'e.g. 18 or 8.25',
                ]),
                self::toggleSwitch('is_default', 'Set as default'.($hasDefault ? ' (replaces the current default)' : ''), false),
                self::toggleSwitch('active', 'Active', true),
                self::divider(),
                self::buttonPrimary('Create Tax Rule', self::formSubmitAction(
                    '/api/tenant/settings/tax-rules',
                    'POST',
                    'Tax rule created.',
                    redirectRoute: '/api/tenant/views/settings-taxes?tab=saved'
                ), 'check_circle', ['color' => '#166534']),
            ], ['spacing' => 12]),
        ]);

        return $components;
    }

    /**
     * Standalone "New Tax Rule" screen (kept for the tax-rule-create view key
     * and any deep link). The canonical entry point is now the "Add New Tax
     * Rule" tab on taxesView.
     */
    public static function taxRuleCreateView(Company $company): array
    {
        return self::screen('New Tax Rule', self::taxRuleAddTabComponents($company));
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

        return self::apiIntegrationsTabbedView($company);
    }

    /**
     * Server-Driven UI Tabbed Layout for API & Integrations screen.
     * Mirrors Store Profile tabbed architecture (WhatsApp, SMS, Custom SMTP, Custom Webhook, E-Commerce).
     */
    public static function apiIntegrationsTabbedView(Company $company): array
    {
        $tabParam = strtolower(trim((string) request('tab', '')));
        $initialIndex = match ($tabParam) {
            'sms', 'sms-gateways', 'sms_gateways' => 1,
            'smtp', 'email', 'email_smtp', 'email-smtp', 'mail' => 2,
            'webhook', 'webhooks', 'custom_webhook', 'custom-webhook' => 3,
            'ai', 'ai_studio', 'ai-studio', 'ai_vision', 'ai-vision', 'vision' => 4,
            'developer', 'developer_api', 'developer-api', 'api_keys', 'api-keys', 'ecommerce', 'rest', 'rest_api' => 5,
            default => 0,
        };

        $tabItems = [
            [
                'id' => 'whatsapp',
                'label' => 'WhatsApp Business',
                'title' => 'WhatsApp Business',
                'icon' => 'chat',
                'endpoint' => '/api/v1/tenant/api-integrations/whatsapp',
                'components' => self::apiWhatsAppTab($company),
                'children' => self::apiWhatsAppTab($company),
            ],
            [
                'id' => 'sms',
                'label' => 'SMS Gateways',
                'title' => 'SMS Gateways',
                'icon' => 'sms',
                'endpoint' => '/api/v1/tenant/api-integrations/sms',
                'components' => self::apiSmsTab($company),
                'children' => self::apiSmsTab($company),
            ],
            [
                'id' => 'smtp',
                'label' => 'Custom SMTP',
                'title' => 'Custom SMTP',
                'icon' => 'mail',
                'endpoint' => '/api/v1/tenant/api-integrations/email',
                'components' => self::apiSmtpTab($company),
                'children' => self::apiSmtpTab($company),
            ],
            [
                'id' => 'webhook',
                'label' => 'Custom Webhook',
                'title' => 'Custom Webhook',
                'icon' => 'webhook',
                'endpoint' => '/api/v1/tenant/api-integrations/custom_webhook',
                'components' => self::apiWebhookTab($company),
                'children' => self::apiWebhookTab($company),
            ],
            [
                'id' => 'ai_studio',
                'label' => 'AI Studio & Vision',
                'title' => 'AI Studio & Vision',
                'icon' => 'auto_awesome',
                'endpoint' => '/api/v1/tenant/settings/ai-studio',
                'components' => self::apiAiStudioTab($company),
                'children' => self::apiAiStudioTab($company),
            ],
            [
                'id' => 'developer_api',
                'label' => 'Developer & REST API',
                'title' => 'Developer & REST API',
                'icon' => 'vpn_key',
                'endpoint' => '/api/tenant/settings/api',
                'components' => self::apiDeveloperTab($company),
                'children' => self::apiDeveloperTab($company),
            ],
        ];

        $tabViews = [
            'whatsapp' => [
                'type' => 'Form',
                'endpoint' => '/api/v1/tenant/api-integrations/whatsapp',
                'method' => 'POST',
                'children' => $tabItems[0]['components'],
                'components' => $tabItems[0]['components'],
            ],
            'sms' => [
                'type' => 'Form',
                'endpoint' => '/api/v1/tenant/api-integrations/sms',
                'method' => 'POST',
                'children' => $tabItems[1]['components'],
                'components' => $tabItems[1]['components'],
            ],
            'smtp' => [
                'type' => 'Form',
                'endpoint' => '/api/v1/tenant/api-integrations/email',
                'method' => 'POST',
                'children' => $tabItems[2]['components'],
                'components' => $tabItems[2]['components'],
            ],
            'webhook' => [
                'type' => 'Form',
                'endpoint' => '/api/v1/tenant/api-integrations/custom_webhook',
                'method' => 'POST',
                'children' => $tabItems[3]['components'],
                'components' => $tabItems[3]['components'],
            ],
            'ai_studio' => [
                'type' => 'Form',
                'endpoint' => '/api/v1/tenant/settings/ai-studio',
                'method' => 'POST',
                'children' => $tabItems[4]['components'],
                'components' => $tabItems[4]['components'],
            ],
            'developer_api' => [
                'type' => 'Form',
                'endpoint' => '/api/tenant/settings/api',
                'method' => 'POST',
                'children' => $tabItems[5]['components'],
                'components' => $tabItems[5]['components'],
            ],
        ];

        $tabsComponent = self::tabs($tabItems, ['initial_index' => $initialIndex, 'is_scrollable' => true]);

        $screen = self::screen('API & Integrations', [
            $tabsComponent,
        ], 'tabs', ['key' => 'settings-api']);

        $screen['screen'] = 'StoreSettingsScreen';
        $screen['title'] = 'API & Integrations';
        $screen['app_bar'] = [
            'title' => 'API & Integrations',
            'show_back_button' => true,
            'actions' => [],
        ];
        $screen['layout'] = 'tabs';
        $screen['is_scrollable'] = true;
        $screen['initial_index'] = $initialIndex;
        $screen['tabs'] = $tabItems;
        $screen['tab_views'] = $tabViews;
        $screen['components'] = [
            $tabsComponent,
        ];

        return $screen;
    }

    /**
     * Tab 1: WhatsApp Business (Meta Cloud API & Twilio WhatsApp).
     */
    private static function apiWhatsAppTab(Company $company): array
    {
        $gw = TenantNotificationGateway::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('channel', TenantNotificationGateway::CHANNEL_WHATSAPP)
            ->first();

        $creds = (array) ($gw?->credentials ?? []);
        if (empty($creds['phone_number_id'])) {
            $creds['phone_number_id'] = Configuration::withoutGlobalScopes()->where('company_id', $company->id)->where('key', 'whatsapp_phone_number_id')->value('value') ?? '';
        }
        if (empty($creds['access_token'])) {
            $creds['access_token'] = Configuration::withoutGlobalScopes()->where('company_id', $company->id)->where('key', 'whatsapp_access_token')->value('value') ?? '';
        }
        if (empty($creds['waba_id'])) {
            $creds['waba_id'] = Configuration::withoutGlobalScopes()->where('company_id', $company->id)->where('key', 'whatsapp_business_account_id')->value('value') ?? '';
        }

        return [
            self::card([
                self::row([
                    self::icon('chat', ['color' => '#16a34a', 'size' => 28]),
                    self::column([
                        self::text('WhatsApp Business Gateway', 'title_medium', ['bold' => true]),
                        self::text('Send digital receipts, invoices, quotations, and due reminders directly via WhatsApp.', 'body_small', ['color' => '#94a3b8']),
                    ]),
                ], ['spacing' => 12]),
                self::divider(),
                self::toggleSwitch('whatsapp_is_enabled', 'Enable WhatsApp Notifications', (bool) ($gw?->is_enabled ?? false)),
                self::dropdownSelect('whatsapp_provider', 'Active WhatsApp Provider', [
                    ['label' => 'Meta WhatsApp Cloud API (Official)', 'value' => 'meta_cloud_api'],
                    ['label' => 'Twilio WhatsApp API', 'value' => 'twilio'],
                ], $gw?->provider ?? 'meta_cloud_api'),
            ]),
            self::card([
                self::text('Meta WhatsApp Cloud API (Official)', 'title_medium', ['bold' => true]),
                self::text('Official Meta Graph API integration with phone number ID and system user access token.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::textInput('meta_phone_number_id', 'Phone Number ID', (string) ($creds['phone_number_id'] ?? ''), ['placeholder' => 'e.g. 104523456789012']),
                self::textInput('meta_waba_id', 'WhatsApp Business Account ID (WABA ID)', (string) ($creds['waba_id'] ?? ''), ['placeholder' => 'e.g. 108765432109876']),
                self::textInput('meta_access_token', 'Permanent System User Access Token', (string) ($creds['access_token'] ?? ''), ['placeholder' => 'EAAG...', 'is_password' => true]),
                self::textInput('meta_template_namespace', 'Template Namespace / Name (Optional)', (string) ($creds['template_namespace'] ?? ''), ['placeholder' => 'e.g. store_receipt_v1']),
            ]),
            self::card([
                self::text('Twilio WhatsApp Alternative', 'title_medium', ['bold' => true]),
                self::text('Deliver via Twilio Programmable Messaging WhatsApp sandbox or approved number.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::textInput('twilio_account_sid', 'Twilio Account SID', (string) ($creds['account_sid'] ?? ''), ['placeholder' => 'AC...']),
                self::textInput('twilio_auth_token', 'Twilio Auth Token', (string) ($creds['auth_token'] ?? ''), ['placeholder' => 'Auth Token', 'is_password' => true]),
                self::textInput('twilio_from_number', 'Twilio WhatsApp Sender Number', (string) ($creds['from_number'] ?? ''), ['placeholder' => 'e.g. +14155238886']),
            ]),
            self::buttonPrimary('Save WhatsApp Credentials', self::formSubmitAction(
                '/api/v1/tenant/api-integrations/whatsapp',
                'POST',
                'WhatsApp Business configuration saved successfully!'
            ), 'save'),
            self::card([
                self::text('Test WhatsApp Connection', 'title_medium', ['bold' => true, 'color' => '#f8fafc']),
                self::text('Send a live test verification ping to your mobile number.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::textInput('whatsapp_test_phone', 'Test Recipient Mobile Number', (string) ($company->phone ?? ''), ['placeholder' => 'e.g. 919876543210', 'keyboard_type' => 'phone']),
                self::buttonOutlined('Send Test Message', self::formSubmitAction(
                    '/api/v1/tenant/api-integrations/whatsapp/test',
                    'POST',
                    'Test WhatsApp message triggered!'
                ), 'send', ['color' => '#10b981', 'border_color' => '#10b981']),
            ], ['border_radius' => 16]),
        ];
    }

    /**
     * Tab 2: SMS Gateways (Twilio SMS, MSG91 India, Generic HTTP Gateway).
     */
    private static function apiSmsTab(Company $company): array
    {
        $gw = TenantNotificationGateway::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('channel', TenantNotificationGateway::CHANNEL_SMS)
            ->first();

        $creds = (array) ($gw?->credentials ?? []);
        $tenantSetting = function_exists('tenant_setting') ? tenant_setting($company->id, 'sms_gateway') : null;
        if (is_string($tenantSetting)) {
            $tenantSetting = json_decode($tenantSetting, true) ?: [];
        }
        if (is_array($tenantSetting)) {
            if (empty($creds['url']) && ! empty($tenantSetting['gateway_url'])) {
                $creds['url'] = $tenantSetting['gateway_url'];
            }
            if (empty($creds['method']) && ! empty($tenantSetting['method'])) {
                $creds['method'] = $tenantSetting['method'];
            }
            if (empty($creds['api_key']) && ! empty($tenantSetting['api_token'])) {
                $creds['api_key'] = $tenantSetting['api_token'];
            }
        }

        if (empty($creds['url'])) {
            $creds['url'] = 'https://sms.zoomnearby.com/api/v1/messages/send?phone={phone}&message={message}';
        }
        if (empty($creds['method'])) {
            $creds['method'] = 'GET';
        }
        if (empty($creds['api_key'])) {
            $creds['api_key'] = '4HIXpW0OPsnPpzzebeA5KI7rI4fnAi7utMu5jwYl8dada339';
        }

        $activeProvider = $gw?->provider ?? 'generic_http';
        $isEnabled = (bool) ($gw?->is_enabled ?? true);
        $testPhone = (string) ($company->phone ?: '+91 80 4111 8080');

        return [
            self::card([
                self::row([
                    self::icon('sms', ['color' => '#0284c7', 'size' => 28]),
                    self::column([
                        self::text('SMS Notification Gateways', 'title_medium', ['bold' => true]),
                        self::text('Send transactional SMS for receipts, balance due reminders, and OTP alerts.', 'body_small', ['color' => '#94a3b8']),
                    ]),
                ], ['spacing' => 12]),
                self::divider(),
                self::toggleSwitch('sms_is_enabled', 'Enable SMS Notifications', $isEnabled),
                self::dropdownSelect('sms_provider', 'Active SMS Provider', [
                    ['label' => 'Twilio SMS Gateway', 'value' => 'twilio'],
                    ['label' => 'MSG91 (India DLT Compliant)', 'value' => 'msg91'],
                    ['label' => 'Generic HTTP REST SMS Gateway', 'value' => 'generic_http'],
                ], $activeProvider),
            ]),
            self::card([
                self::text('Twilio SMS Gateway', 'title_medium', ['bold' => true]),
                self::text('Global SMS delivery via Twilio Programmable SMS.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::textInput('sms_twilio_sid', 'Twilio Account SID', (string) ($creds['account_sid'] ?? ''), ['placeholder' => 'AC...']),
                self::textInput('sms_twilio_token', 'Twilio Auth Token', (string) ($creds['auth_token'] ?? ''), ['placeholder' => 'Auth Token', 'is_password' => true]),
                self::textInput('sms_twilio_from', 'From Phone Number / Sender ID', (string) ($creds['from_number'] ?? ''), ['placeholder' => 'e.g. +12025550192']),
            ]),
            self::card([
                self::text('MSG91 (India DLT Compliant)', 'title_medium', ['bold' => true]),
                self::text('DLT compliant transactional SMS service for Indian telecom compliance.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::textInput('msg91_auth_key', 'MSG91 Auth Key', (string) ($creds['auth_key'] ?? ''), ['placeholder' => 'Auth Key', 'is_password' => true]),
                self::textInput('msg91_sender_id', 'Approved 6-Character Sender ID', (string) ($creds['sender_id'] ?? ''), ['placeholder' => 'e.g. ZOOMNB']),
                self::textInput('msg91_dlt_template_id', 'DLT Template / Flow ID', (string) ($creds['dlt_template_id'] ?? ''), ['placeholder' => 'e.g. 64b3...']),
            ]),
            self::card([
                self::text('Generic HTTP SMS Gateway', 'title_medium', ['bold' => true]),
                self::text('Integrate any REST SMS vendor using dynamic {phone} and {message} URL placeholders.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::textInput('generic_sms_url', 'Gateway Endpoint URL', (string) ($creds['url'] ?? ''), ['placeholder' => 'https://sms.zoomnearby.com/api/v1/messages/send?phone={phone}&message={message}']),
                self::dropdownSelect('generic_sms_method', 'HTTP Method', [
                    ['label' => 'POST', 'value' => 'POST'],
                    ['label' => 'GET', 'value' => 'GET'],
                ], $creds['method'] ?? 'GET'),
                self::textInput('generic_sms_api_key', 'API Key / Bearer Token (Optional)', (string) ($creds['api_key'] ?? ''), ['is_password' => true]),
            ]),
            self::buttonPrimary('Save SMS Credentials', self::formSubmitAction(
                '/api/v1/tenant/api-integrations/sms',
                'POST',
                'SMS gateway credentials saved successfully!'
            ), 'save'),
            self::card([
                self::text('Test SMS Gateway', 'title_medium', ['bold' => true, 'color' => '#f8fafc']),
                self::text('Dispatch a test SMS to confirm provider credentials and route deliverability.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::textInput('sms_test_phone', 'Test Recipient Mobile Number', $testPhone, ['placeholder' => '+91 80 4111 8080', 'keyboard_type' => 'phone']),
                self::buttonOutlined('Send Test SMS', self::formSubmitAction(
                    '/api/v1/tenant/api-integrations/sms/test',
                    'POST',
                    'Test SMS triggered!'
                ), 'send', ['color' => '#10b981', 'border_color' => '#10b981']),
            ], ['border_radius' => 16]),
        ];
    }

    /**
     * Tab 3: Custom SMTP Mailer.
     */
    private static function apiSmtpTab(Company $company): array
    {
        $gw = TenantNotificationGateway::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('channel', TenantNotificationGateway::CHANNEL_EMAIL)
            ->first();

        $creds = (array) ($gw?->credentials ?? []);
        if (empty($creds['host'])) {
            $fallback = app(InvoiceDeliveryService::class)->getSmtpConfig($company);
            $creds['host'] = $fallback['host'] ?? '';
            $creds['port'] = $fallback['port'] ?? 587;
            $creds['username'] = $fallback['username'] ?? '';
            $creds['password'] = $fallback['password'] ?? '';
            $creds['encryption'] = $fallback['encryption'] ?? 'tls';
            $creds['from_address'] = $fallback['from_address'] ?? $company->email;
            $creds['from_name'] = $fallback['from_name'] ?? $company->name;
        }

        return [
            self::card([
                self::row([
                    self::icon('mail', ['color' => '#ea580c', 'size' => 28]),
                    self::column([
                        self::text('Custom SMTP Mail Server', 'title_medium', ['bold' => true]),
                        self::text('Send branded PDF invoices, receipts, and quotations directly from your store email domain.', 'body_small', ['color' => '#94a3b8']),
                    ]),
                ], ['spacing' => 12]),
                self::divider(),
                self::toggleSwitch('smtp_is_enabled', 'Enable Custom SMTP Server', (bool) ($gw?->is_enabled ?? false)),
                self::textInput('smtp_host', 'SMTP Host Server', (string) ($creds['host'] ?? ''), ['placeholder' => 'smtp.gmail.com or mail.yourstore.com']),
                self::textInput('smtp_port', 'SMTP Port', (string) ($creds['port'] ?? 587), ['keyboard_type' => 'number', 'placeholder' => '587']),
                self::dropdownSelect('smtp_encryption', 'Encryption Protocol', [
                    ['label' => 'TLS (Port 587 - Recommended)', 'value' => 'tls'],
                    ['label' => 'SSL (Port 465)', 'value' => 'ssl'],
                    ['label' => 'None / Plain (Port 25)', 'value' => 'none'],
                ], $creds['encryption'] ?? 'tls'),
                self::textInput('smtp_username', 'SMTP Username / Login', (string) ($creds['username'] ?? ''), ['placeholder' => 'billing@yourstore.com']),
                self::textInput('smtp_password', 'SMTP Password / App Password', (string) ($creds['password'] ?? ''), ['placeholder' => '••••••••••••', 'is_password' => true]),
                self::textInput('smtp_from_address', 'From Email Address', (string) ($creds['from_address'] ?? $company->email), ['placeholder' => 'receipts@yourstore.com', 'keyboard_type' => 'email']),
                self::textInput('smtp_from_name', 'From Display Name', (string) ($creds['from_name'] ?? $company->name), ['placeholder' => 'Store Name']),
            ]),
            self::buttonPrimary('Save SMTP Settings', self::formSubmitAction(
                '/api/v1/tenant/api-integrations/email',
                'POST',
                'SMTP credentials saved successfully!'
            ), 'save'),
            self::card([
                self::text('Test Mail Connection', 'title_medium', ['bold' => true, 'color' => '#f8fafc']),
                self::text('Send a live verification email to check your server credentials.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::textInput('smtp_test_email', 'Test Recipient Email', (string) ($company->email ?? ''), ['placeholder' => 'you@example.com', 'keyboard_type' => 'email']),
                self::buttonOutlined('Send Test Email', self::formSubmitAction(
                    '/api/v1/tenant/api-integrations/email/test',
                    'POST',
                    'Test email dispatched!'
                ), 'send', ['color' => '#10b981', 'border_color' => '#10b981']),
            ], ['border_radius' => 16]),
        ];
    }

    /**
     * Tab 4: Custom Webhook Dispatcher.
     */
    private static function apiWebhookTab(Company $company): array
    {
        $gw = TenantNotificationGateway::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('channel', TenantNotificationGateway::CHANNEL_WEBHOOK)
            ->first();

        $creds = (array) ($gw?->credentials ?? []);
        $triggers = (array) ($creds['event_types'] ?? ['receipt_generated', 'invoice_created', 'quotation_sent', 'due_reminder']);

        return [
            self::card([
                self::row([
                    self::icon('webhook', ['color' => '#7c3aed', 'size' => 28]),
                    self::column([
                        self::text('Custom Webhook Dispatcher', 'title_medium', ['bold' => true]),
                        self::text('Stream POS transactions and financial events to external endpoints in real-time.', 'body_small', ['color' => '#94a3b8']),
                    ]),
                ], ['spacing' => 12]),
                self::divider(),
                self::toggleSwitch('webhook_is_enabled', 'Enable Outbound Webhooks', (bool) ($gw?->is_enabled ?? false)),
                self::textInput('webhook_url', 'Webhook Destination URL', (string) ($creds['url'] ?? ''), ['placeholder' => 'https://api.yourdomain.com/pos-events']),
                self::dropdownSelect('webhook_method', 'HTTP Method', [
                    ['label' => 'POST (JSON body)', 'value' => 'POST'],
                    ['label' => 'PUT (JSON body)', 'value' => 'PUT'],
                ], $creds['method'] ?? 'POST'),
                self::textInput('webhook_secret', 'HMAC SHA-256 Secret Key', (string) ($creds['secret'] ?? ''), ['placeholder' => 'Shared secret key for signature verification', 'is_password' => true]),
            ]),
            self::card([
                self::text('Subscribed Event Triggers', 'title_medium', ['bold' => true]),
                self::text('Automatically dispatch signed payloads when these events occur in POS.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::checkbox('trigger_receipt_generated', 'receipt_generated (When POS sale is completed)', in_array('receipt_generated', $triggers, true)),
                self::checkbox('trigger_invoice_created', 'invoice_created (When a tax invoice is created or updated)', in_array('invoice_created', $triggers, true)),
                self::checkbox('trigger_quotation_sent', 'quotation_sent (When quotation estimate is shared)', in_array('quotation_sent', $triggers, true)),
                self::checkbox('trigger_due_reminder', 'due_reminder (When customer due balance reminder is dispatched)', in_array('due_reminder', $triggers, true)),
            ]),
            self::buttonPrimary('Save Webhook Configuration', self::formSubmitAction(
                '/api/v1/tenant/api-integrations/custom_webhook',
                'POST',
                'Webhook settings saved successfully!'
            ), 'save'),
            self::card([
                self::text('Test Webhook Connection', 'title_medium', ['bold' => true, 'color' => '#f8fafc']),
                self::text('Dispatch a signed test event to your webhook destination.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::buttonOutlined('Send Test Webhook Ping', self::formSubmitAction(
                    '/api/v1/tenant/api-integrations/custom_webhook/test',
                    'POST',
                    'Test webhook ping dispatched!'
                ), 'send', ['color' => '#10b981', 'border_color' => '#10b981']),
            ], ['border_radius' => 16]),
        ];
    }

    /**
     * Tab 5: AI Studio & Vision (Exclusively for LLM & Image Generation models, presets, and API keys).
     */
    private static function apiAiStudioTab(Company $company): array
    {
        $configuration = Configuration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('key', [
                'default_ai_provider', 'openai_model', 'gemini_model', 'claude_model',
                'openai_api_key', 'gemini_api_key', 'claude_api_key',
            ])
            ->pluck('value', 'key');

        $defaultAi = (string) $configuration->get('default_ai_provider', 'openai');
        $openaiModel = (string) $configuration->get('openai_model', 'dall-e-3');
        $geminiModel = (string) $configuration->get('gemini_model', 'imagen-3.0-generate-002');
        $claudeModel = (string) $configuration->get('claude_model', 'claude-3-5-sonnet-20241022');

        $hasOpenaiKey = ! empty($configuration->get('openai_api_key'));
        $hasGeminiKey = ! empty($configuration->get('gemini_api_key'));
        $hasClaudeKey = ! empty($configuration->get('claude_api_key'));

        return [
            self::card([
                self::row([
                    self::icon('auto_awesome', ['color' => '#9333ea', 'size' => 28]),
                    self::column([
                        self::text('Generative AI Studio & Vision Engine', 'title_medium', ['bold' => true]),
                        self::text('Automate studio-grade commercial product imagery and descriptions from product titles and categories.', 'body_small', ['color' => '#94a3b8']),
                    ]),
                ], ['spacing' => 12]),
                self::divider(),
                self::dropdownSelect('default_ai_provider', 'Active Generative AI Provider', [
                    ['label' => 'OpenAI (DALL-E 3 / GPT-4o Vision)', 'value' => 'openai'],
                    ['label' => 'Google Gemini (1.5 Pro / Imagen 3)', 'value' => 'gemini'],
                    ['label' => 'Anthropic Claude (3.5 Sonnet / Vision)', 'value' => 'claude'],
                ], $defaultAi),
            ], ['border_radius' => 16]),

            self::card([
                self::text('OpenAI Studio Settings', 'title_medium', ['bold' => true]),
                self::text('High-definition neural generation via DALL-E 3 & GPT-4o Vision.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::dropdownSelect('openai_model', 'OpenAI Model Preset', [
                    ['label' => 'DALL-E 3 (1024×1024) — High-Quality', 'value' => 'dall-e-3'],
                    ['label' => 'DALL-E 2 (512×512) — Legacy Fast', 'value' => 'dall-e-2'],
                    ['label' => 'GPT-4o Mini + DALL-E 3 — Fast Prompting', 'value' => 'gpt-4o-mini'],
                    ['label' => 'GPT-4o Vision + DALL-E 3 — Multimodal', 'value' => 'gpt-4o'],
                ], $openaiModel),
                self::textInput('openai_api_key', 'OpenAI API Key', '', [
                    'placeholder' => $hasOpenaiKey ? '•••••••••••• (Saved)' : 'sk-proj-...',
                    'is_password' => true,
                ]),
            ], ['border_radius' => 16]),

            self::card([
                self::text('Google Gemini Studio Settings', 'title_medium', ['bold' => true]),
                self::text('Google DeepMind multimodal generation with Imagen 3 & Gemini 1.5 Pro.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::dropdownSelect('gemini_model', 'Gemini Model Preset', [
                    ['label' => 'Imagen 3 (Flagship Studio) — Latest', 'value' => 'imagen-3.0-generate-002'],
                    ['label' => 'Gemini 1.5 Pro + Imagen 3 — Pro Multimodal', 'value' => 'gemini-1.5-pro'],
                    ['label' => 'Gemini 1.5 Flash + Imagen 3 — Fast', 'value' => 'gemini-1.5-flash'],
                    ['label' => 'Gemini 2.5 Flash + Imagen 3 — Hybrid', 'value' => 'gemini-2.5-flash'],
                ], $geminiModel),
                self::textInput('gemini_api_key', 'Google Gemini API Key', '', [
                    'placeholder' => $hasGeminiKey ? '•••••••••••• (Saved)' : 'AIzaSy...',
                    'is_password' => true,
                ]),
            ], ['border_radius' => 16]),

            self::card([
                self::text('Anthropic Claude Settings', 'title_medium', ['bold' => true]),
                self::text('Claude reasoning engine for product cataloging and multimodal inspection.', 'body_small', ['color' => '#94a3b8']),
                self::divider(),
                self::dropdownSelect('claude_model', 'Claude Model Preset', [
                    ['label' => 'Claude 3.5 Sonnet (Vision Specialist) — Stable', 'value' => 'claude-3-5-sonnet-20241022'],
                    ['label' => 'Claude 3.7 Sonnet (Hybrid Reasoning) — Flagship', 'value' => 'claude-3-7-sonnet-latest'],
                    ['label' => 'Claude 3 Haiku (Budget / High Speed) — Fast', 'value' => 'claude-3-haiku-20240307'],
                ], $claudeModel),
                self::textInput('claude_api_key', 'Anthropic Claude API Key', '', [
                    'placeholder' => $hasClaudeKey ? '•••••••••••• (Saved)' : 'sk-ant-...',
                    'is_password' => true,
                ]),
            ], ['border_radius' => 16]),

            self::buttonPrimary('Save AI Studio Settings', self::formSubmitAction(
                '/api/v1/tenant/settings/ai-studio',
                'POST',
                'AI Studio configuration saved successfully!'
            ), 'save'),
        ];
    }

    /**
     * Tab 6: Developer & REST API (Bearer API Tokens, E-Commerce Platform Webhooks, cURL Quick Reference).
     */
    private static function apiDeveloperTab(Company $company): array
    {
        $configuration = Configuration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('key', [
                'webhook_url', 'webhook_platform', 'webhook_hmac_secret',
            ])
            ->pluck('value', 'key');

        $inboundWebhookUrl = url('/api/v1/integrations/webhooks/'.($company->unique_account_id ?: $company->id).'/orders');
        $platform = (string) $configuration->get('webhook_platform', 'shopify');

        $activeKey = TenantApiKey::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->latest()
            ->first();

        $activeKeySnippet = $activeKey ? $activeKey->token : 'No active keys yet (click Regenerate Token)';

        return [
            // E-Commerce & External Platform Card
            self::card([
                self::row([
                    self::icon('storefront', ['color' => '#2563eb', 'size' => 28]),
                    self::column([
                        self::text('E-Commerce & External Platform Integration', 'title_medium', ['bold' => true]),
                        self::text('Receive inbound real-time order synchronizations from online storefronts.', 'body_small', ['color' => '#94a3b8']),
                    ]),
                ], ['spacing' => 12]),
                self::divider(),
                self::textInput('inbound_webhook_url', 'Your Inbound Webhook Endpoint URL', $inboundWebhookUrl, [
                    'read_only' => true,
                    'copyable' => true,
                    'copy_tooltip' => 'Copy webhook endpoint',
                    'copy_toast' => 'Webhook URL copied to clipboard!',
                ]),
                self::dropdownSelect('webhook_platform', 'E-Commerce Platform', [
                    ['label' => 'Shopify (HMAC-SHA256)', 'value' => 'shopify'],
                    ['label' => 'WooCommerce', 'value' => 'woocommerce'],
                    ['label' => 'Custom Headless API', 'value' => 'generic'],
                ], $platform),
                self::textInput('webhook_hmac_secret', 'Webhook Secret / HMAC Key', (string) $configuration->get('webhook_hmac_secret', ''), [
                    'placeholder' => 'Enter shared secret key for signature verification',
                    'is_password' => true,
                ]),
                self::buttonPrimary('Save E-Commerce Settings', self::formSubmitAction(
                    '/api/tenant/settings/api',
                    'POST',
                    'E-Commerce settings saved successfully!'
                ), 'save'),
            ], ['border_radius' => 16]),

            // Active Bearer Token Card
            self::card([
                self::row([
                    self::icon('vpn_key', ['color' => '#10b981', 'size' => 28]),
                    self::column([
                        self::text('Active Bearer API Token', 'title_medium', ['bold' => true]),
                        self::text('Authenticate REST calls with permanent scoped bearer tokens.', 'body_small', ['color' => '#94a3b8']),
                    ]),
                ], ['spacing' => 12]),
                self::divider(),
                self::textInput('active_bearer_token', 'Active Bearer API Token', $activeKeySnippet, [
                    'read_only' => true,
                    'copyable' => (bool) $activeKey,
                    'copy_tooltip' => 'Copy API Token',
                    'copy_toast' => 'API Token copied to clipboard!',
                ]),
                self::buttonOutlined('Regenerate Token', self::formSubmitAction(
                    '/api/v1/tenant/api-keys/regenerate',
                    'POST',
                    'API Bearer Token regenerated successfully!'
                ), 'refresh', [
                    'color' => '#f59e0b',
                    'border_color' => '#f59e0b',
                    'confirm_title' => 'Regenerate API Token?',
                    'confirm_message' => 'Are you sure you want to revoke the existing token and generate a new one? External integrations using this key will stop working until updated.',
                ]),
            ], ['border_radius' => 16]),

            // Developer API Quick Reference Card
            self::card([
                self::row([
                    self::icon('api', ['color' => '#0284c7', 'size' => 28]),
                    self::column([
                        self::text('Developer API Quick Reference', 'title_medium', ['bold' => true, 'color' => '#f8fafc']),
                        self::text('cURL and REST endpoints for external accounting and tax calculation.', 'body_small', ['color' => '#94a3b8']),
                    ]),
                ], ['spacing' => 12]),
                self::divider(),
                self::text('Compute subtotals, customer exemptions, and itemized tax breakdowns.', 'body_small', ['color' => '#94a3b8']),
                self::textInput('endpoint_tax_calc', 'POST /api/v1/tax/calculate', 'curl -X POST '.url('/api/v1/tax/calculate'), [
                    'read_only' => true,
                    'copyable' => true,
                    'copy_tooltip' => 'Copy cURL command',
                    'copy_toast' => 'Tax calculation endpoint copied to clipboard!',
                ]),
                self::text('Directly creates a cleared tax invoice record inside the tenant database.', 'body_small', ['color' => '#94a3b8']),
                self::textInput('endpoint_tax_invoices', 'POST /api/v1/tax/invoices', 'curl -X POST '.url('/api/v1/tax/invoices'), [
                    'read_only' => true,
                    'copyable' => true,
                    'copy_tooltip' => 'Copy cURL command',
                    'copy_toast' => 'Tax invoice endpoint copied to clipboard!',
                ]),
                self::text('Omnichannel receipt & invoice dispatch (WhatsApp, SMS, Email).', 'body_small', ['color' => '#94a3b8']),
                self::textInput('endpoint_notifications_dispatch', 'POST /api/v1/tenant/notifications/dispatch', 'curl -X POST '.url('/api/v1/tenant/notifications/dispatch'), [
                    'read_only' => true,
                    'copyable' => true,
                    'copy_tooltip' => 'Copy cURL command',
                    'copy_toast' => 'Notification dispatch endpoint copied to clipboard!',
                ]),
            ], ['border_radius' => 16]),
        ];
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

    public static function drawerMenuView(Company $company): array
    {
        $components = app(NavigationController::class)
            ->getDrawerMenuComponents(company: $company);

        return self::screen('Navigation Drawer', $components, 'scroll_view');
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
                $sectionOverrides[(string) $section['key']] = $section;
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
        foreach ($sectionMeta as $key => $sectionMetaRow) {
            $override = $sectionOverrides[$key] ?? [];
            $customTitle = trim((string) ($override['custom_title'] ?? $sectionMetaRow['custom_title'] ?? ''));
            $sections[] = [
                'key' => $key,
                'order' => isset($override['order'])
                    ? max(0, (int) $override['order'])
                    : $sectionIndexes[$key],
                'custom_title' => $customTitle,
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
            ['key' => 'settings-payment-methods', 'title' => 'Payment Methods', 'endpoint' => '/api/tenant/views/settings-payment-methods', 'permission' => 'settings.view'],
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
                && (! $screen->module->is_active || ! in_array(ModuleRegistry::canonicalKey($screen->module->slug), $licensed, true))) {
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

    public static function verifyOtpView(Company $company): array
    {
        $defaultEmail = (string) request('email', '');

        return self::screen('Verify Email OTP', [
            self::card([
                self::row([
                    self::icon('mark_email_read', ['color' => '#15803d', 'size' => 32], ['flexible' => false]),
                    self::column([
                        self::text('Verify Your Email', 'title_medium', ['bold' => true]),
                        self::text('Please enter the 6-digit verification code sent to your registered email address.', 'body_small', ['color' => '#64748b']),
                    ], ['expanded' => true, 'spacing' => 2]),
                ], ['spacing' => 12, 'cross_axis_alignment' => 'center']),
            ]),
            self::card([
                self::column([
                    self::text('Account Activation', 'title_medium', ['bold' => true]),
                    self::divider(),
                    self::textInput('email', 'Registered Email Address', $defaultEmail, [
                        'required' => true,
                        'keyboard_type' => 'email',
                        'placeholder' => 'name@example.com',
                    ]),
                    self::textInput('otp', '6-Digit Verification Code', '', [
                        'required' => true,
                        'keyboard_type' => 'number',
                        'placeholder' => 'Enter 6-digit OTP (e.g. 123456)',
                    ]),
                    self::divider(),
                    self::buttonPrimary('Verify & Activate Account', self::formSubmitAction(
                        '/api/auth/verify-email-otp',
                        'POST',
                        'Email verified successfully! Welcome to your POS.',
                        navigateBack: false,
                        redirectRoute: '/api/tenant/views/dashboard'
                    ), 'verified', ['background_color' => '#15803d']),
                    self::buttonOutlined('Resend Verification Code', self::apiPostAction(
                        '/api/auth/resend-otp',
                        ['email' => $defaultEmail],
                        'A new verification code has been sent.'
                    ), 'refresh', ['dense' => true]),
                ], ['spacing' => 12]),
            ]),
        ]);
    }

    public static function loginView(Company $company): array
    {
        $brandName = $company->name ?: config('app.name', 'ZoomNearby POS');

        return self::screen("Sign In - {$brandName}", [
            self::card([
                self::column([
                    self::row([
                        self::icon('storefront', ['color' => '#15803d', 'size' => 32], ['flexible' => false]),
                        self::column([
                            self::text($brandName, 'title_large', ['bold' => true]),
                            self::text('Universal Cloud POS & Retail Terminal', 'body_small', ['color' => '#64748b']),
                        ], ['expanded' => true, 'spacing' => 2]),
                    ], ['spacing' => 12, 'cross_axis_alignment' => 'center']),
                    self::divider(),
                    self::textInput('email', 'Email Address / Username', '', [
                        'required' => true,
                        'keyboard_type' => 'email',
                        'placeholder' => 'admin@example.com',
                    ]),
                    self::textInput('password', 'Password', '', [
                        'required' => true,
                        'obscure_text' => true,
                        'placeholder' => 'Enter your password...',
                    ]),
                    self::divider(),
                    self::buttonPrimary('Sign In', self::formSubmitAction(
                        '/api/v1/pos/login',
                        'POST',
                        'Login successful!',
                        navigateBack: false,
                        redirectRoute: '/api/tenant/views/dashboard'
                    ), 'login', ['background_color' => '#15803d']),
                    self::divider(),
                    self::text('Or sign in with social account:', 'label_medium', ['color' => '#64748b']),
                    self::row([
                        self::buttonOutlined('Google', self::openUrlAction('/auth/google/redirect'), 'google', ['expanded' => true]),
                        self::buttonOutlined('Facebook', self::openUrlAction('/auth/facebook/redirect'), 'facebook', ['expanded' => true]),
                    ], ['spacing' => 10]),
                ], ['spacing' => 12]),
            ]),
        ]);
    }

    public static function registerView(Company $company): array
    {
        $brandName = $company->name ?: config('app.name', 'ZoomNearby POS');

        return self::screen("Register Store - {$brandName}", [
            self::card([
                self::column([
                    self::row([
                        self::icon('add_business', ['color' => '#15803d', 'size' => 32], ['flexible' => false]),
                        self::column([
                            self::text('Open Your Cloud Store', 'title_large', ['bold' => true]),
                            self::text('Register a new store and manage inventory, bookings, and sales.', 'body_small', ['color' => '#64748b']),
                        ], ['expanded' => true, 'spacing' => 2]),
                    ], ['spacing' => 12, 'cross_axis_alignment' => 'center']),
                    self::divider(),
                    self::textInput('store_name', 'Store / Business Name *', '', [
                        'required' => true,
                        'placeholder' => 'My Store or Salon',
                    ]),
                    self::textInput('name', 'Owner / Administrator Name *', '', [
                        'required' => true,
                        'placeholder' => 'Full Name',
                    ]),
                    self::textInput('email', 'Email Address *', '', [
                        'required' => true,
                        'keyboard_type' => 'email',
                        'placeholder' => 'owner@example.com',
                    ]),
                    self::textInput('password', 'Password *', '', [
                        'required' => true,
                        'obscure_text' => true,
                        'placeholder' => 'Create a strong password...',
                    ]),
                    self::textInput('phone', 'Contact Phone', '', [
                        'keyboard_type' => 'phone',
                        'placeholder' => '+1 (555) 000-0000',
                    ]),
                    self::divider(),
                    self::buttonPrimary('Register & Get Started', self::formSubmitAction(
                        '/api/auth/register',
                        'POST',
                        'Registration initiated successfully.',
                        navigateBack: false
                    ), 'rocket_launch', ['background_color' => '#15803d']),
                    self::divider(),
                    self::text('Or register with social account:', 'label_medium', ['color' => '#64748b']),
                    self::row([
                        self::buttonOutlined('Google', self::openUrlAction('/auth/google/redirect'), 'google', ['expanded' => true]),
                        self::buttonOutlined('Facebook', self::openUrlAction('/auth/facebook/redirect'), 'facebook', ['expanded' => true]),
                    ], ['spacing' => 10]),
                ], ['spacing' => 12]),
            ]),
        ]);
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
        if (in_array($normalized, ['change-password', 'password', 'devices', 'device-sessions', 'terminals', 'verify-otp', 'otp-verify', 'verify-email', 'login', 'auth-login', 'register-store', 'register-tenant', 'auth-register', 'signup'], true)) {
            return null;
        }

        if (str_starts_with($normalized, 'settings-')
            || in_array($normalized, ['mode', 'profile', 'branding', 'receipts', 'financial', 'localization', 'taxes', 'api', 'api-integrations', 'navigation', 'navigation-menu', 'notifications', 'custom-notifications', 'repair-checklist-settings'], true)) {
            return 'settings.view';
        }

        if (in_array($normalized, ['restaurant-tables', 'restaurant-kds', 'restaurant-pos'], true)) {
            return 'pos.view';
        }

        if (in_array($normalized, ['sales', 'invoices', 'sales-invoices', 'pos-sales', 'dining-history', 'kot-history', 'pharmacy-prescriptions', 'pharmacy-rx-create', 'pharmacy-prescription-create', 'new-prescription-intake', 'prescription-intake', 'rx-create'], true)) {
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

        if (in_array($normalized, ['service-calendar', 'service-orders', 'service-catalog', 'service-rates', 'service-create', 'add-service'], true)) {
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
                    || ! in_array(ModuleRegistry::canonicalKey($stored->module->slug), ModuleRegistry::availableModes($company), true))) {
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

            return self::schemaResponse($normalized, self::applyDemoLockdown($schema, $company, $normalized));
        }

        $schema = match ($normalized) {
            'settings-mode', 'mode' => self::modeView($company),
            'dashboard' => self::dashboardView($company),
            'settings-profile', 'profile' => self::profileView($company),
            'settings-branding', 'branding' => self::brandingView($company),
            'settings-receipts', 'receipts' => self::receiptsView($company),
            'printer-setup', 'hardware-printer', 'hardware-settings' => self::hardwareSetupView($company),
            'settings-financial', 'financial' => self::financialView($company),
            'settings-payment-methods', 'payment-methods', 'payment-method-list' => self::paymentMethodsView($company),
            'payment-method-create', 'add-payment-method', 'new-payment-method' => self::paymentMethodCreateView($company),
            'settings-localization', 'localization' => self::localizationView($company),
            'settings-appearance', 'appearance', 'app-preferences', 'preferences' => self::appearanceView($company),
            'settings-notification-sounds', 'notification-sounds', 'app-preferences-notifications', 'notifications-audio', 'settings-notifications-audio', 'settings-audio-notifications' => TenantAppPreferencesController::buildSduiSchema($company, TenantAppPreferencesController::getEffectivePreferences($company)),
            'settings-taxes', 'taxes' => self::taxesView($company),
            'tax-rule-create', 'add-tax-rule', 'new-tax-rule' => self::taxRuleCreateView($company),
            'settings-api', 'api', 'api-integrations' => self::apiView($company),
            'settings-navigation', 'navigation', 'navigation-menu' => self::navigationView($company),
            'drawer-menu', 'drawer', 'navigation-drawer' => self::drawerMenuView($company),
            'settings-form-labels', 'form-labels', 'custom-form-fields' => self::formLabelsView($company),
            'settings-notifications', 'notifications', 'custom-notifications' => self::notificationsView($company),
            'settings-advanced', 'advanced', 'danger-zone' => self::advancedView($company),
            'restaurant-tables', 'tables', 'floor-plan' => self::restaurantTablesView($company),
            'restaurant-kds', 'kds', 'kitchen-display' => self::restaurantKdsView($company),
            'restaurant-pos' => self::restaurantPosView($company),
            'dining-history', 'kot-history' => self::diningHistoryView($company),
            // DEPRECATED: the old "Pharmacy Counter POS" SDUI screen. Nothing in
            // the drawer or the prescription queue routes here any more — all
            // pharmacy checkouts now open the core native POS ('pos'). Kept only
            // so already-installed app builds and the parity contract test keep
            // resolving; do not add new navigation targets to it.
            'pharmacy-pos' => self::pharmacyPosView($company),
            'pharmacy-batches', 'batches' => self::pharmacyBatchesView($company),
            'pharmacy-prescriptions', 'prescriptions', 'prescriptions-queue' => self::pharmacyPrescriptionsView($company),
            'pharmacy-rx-create', 'pharmacy-prescription-create', 'new-prescription-intake', 'prescription-intake', 'rx-create' => self::pharmacyRxCreateView($company),
            'repair-dashboard', 'repair' => self::repairDashboardView($company),
            'repair-create-ticket', 'repair-ticket-create' => self::repairCreateTicketView($company),
            'repair-tickets' => self::repairTicketsView($company),
            'repair-my-jobs' => self::repairMyJobsView($company),
            'repair-detail' => self::repairDetailView($company),
            'repair-categories' => self::repairCategoriesView($company),
            'repair-checklist-settings', 'settings-repair-checklist', 'repair-checklist' => self::repairChecklistSettingsView($company),
            'salon-calendar', 'booking-calendar', 'service-calendar', 'service-booking-calendar', 'calendar' => self::serviceCalendarView($company),
            'salon-booking-create', 'service-booking-create', 'book-service-appointment', 'book-appointment', 'salon-booking' => self::salonBookingCreateView($company),
            'service-stylists', 'stylists' => self::serviceStylistsView($company),
            'service-orders', 'service-catalog', 'service-rates', 'service-catalog-rates' => self::serviceCatalogRatesView($company),
            'service-create', 'add-service', 'add-new-service' => self::serviceCreateView($company),
            'change-password', 'password' => self::changePasswordView($company),
            'roles', 'roles-create', 'manage-roles' => self::rolesView($company),
            'pos', 'point-of-sale' => self::posView($company),
            'retail-pos' => self::retailPosView($company),
            'salon-pos', 'service-pos', 'spa-pos' => self::salonPosView($company),
            'sales', 'invoices', 'sales-invoices', 'pos-sales' => self::salesView($company),
            'quotations', 'quotes', 'estimates' => self::quotationsView($company),
            'customers', 'crm', 'clients' => self::customersView($company),
            'leads', 'lead-management', 'leadmanagement', 'lead-module' => app(LeadService::class)->getTabbedLeadManagementSchema($company, request()->user(), request('tab')),
            'cash-register', 'cash_register', 'register' => self::cashRegisterView($company),
            'devices', 'device-sessions', 'terminals' => self::devicesView($company),
            'categories', 'product-categories', 'inventory-categories' => self::categoriesView($company),
            'verify-otp', 'otp-verify', 'verify-email' => self::verifyOtpView($company),
            'login', 'auth-login' => self::loginView($company),
            'register-store', 'register-tenant', 'tenant-register', 'auth-register', 'signup' => self::registerView($company),
            default => null,
        };

        if ($schema === null && preg_match('#(?:^|/)quotations/(\d+)#', $normalized, $qm)) {
            return app(QuotationController::class)->showSchema(request(), $qm[1]);
        }

        if ($schema === null && (str_contains($normalized, 'invoices/create') || str_contains($normalized, 'invoices-create'))) {
            return app(InvoiceController::class)->createSchema(request());
        }

        if ($schema === null && preg_match('#(?:^|/)leads/([A-Za-z0-9\-_]+)#', $normalized, $lm)) {
            return app(LeadController::class)->showSchema(request(), $lm[1]);
        }

        if ($schema === null && ($normalized === 'lead-detail' || str_ends_with($normalized, 'lead-detail'))) {
            return app(LeadController::class)->leadDetail(request());
        }

        if ($schema === null && ($normalized === 'create-lead' || str_ends_with($normalized, 'create-lead') || str_contains($normalized, 'leads/create'))) {
            return app(LeadController::class)->createSchema(request());
        }

        if ($schema === null && str_ends_with($normalized, '-pos')) {
            $modKey = substr($normalized, 0, -4);
            $schema = UniversalPosBuilder::posScreenForModule($modKey, $company);
        }

        if ($schema === null) {
            $module = ModuleRegistry::find($normalized);
            if ($module !== null && in_array(ModuleRegistry::canonicalKey($normalized), ModuleRegistry::availableModes($company), true)) {
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

        return self::schemaResponse($normalized, self::applyDemoLockdown($schema, $company, $normalized));
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

    /** Settings/profile-type views a demo tenant sees but cannot mutate. */
    private const DEMO_LOCKED_VIEWS = [
        'settings-profile', 'profile', 'settings-branding', 'branding',
        'settings-receipts', 'receipts', 'settings-financial', 'financial',
        'settings-localization', 'localization', 'settings-taxes', 'taxes',
        'tax-rule-create', 'settings-api', 'api', 'api-integrations',
        'settings-navigation', 'navigation', 'navigation-menu',
        'settings-form-labels', 'form-labels', 'custom-form-fields',
        'settings-notifications', 'notifications', 'custom-notifications',
        'settings-advanced', 'advanced', 'danger-zone',
        'settings-payment-methods', 'payment-methods', 'payment-method-create',
        'add-payment-method', 'printer-setup', 'hardware-printer',
        'hardware-settings', 'change-password', 'password',
        'settings-mode', 'mode',
    ];

    /**
     * When `DEMO_MODE` is on and this tenant is a demo account, make every
     * settings/profile screen view-only: a top notice card, disabled Save /
     * Update buttons, and locked file pickers. Emitted server-side so the
     * Flutter client needs no change (it already honours `disabled` /
     * `enabled`).
     */
    private static function applyDemoLockdown(array $schema, Company $company, string $view): array
    {
        if (! config('app.demo_mode')
            || ! (bool) ($company->is_demo ?? false)
            || ! in_array($view, self::DEMO_LOCKED_VIEWS, true)) {
            return $schema;
        }

        $notice = self::card([
            self::row([
                self::icon('lock', ['color' => '#B45309', 'size' => 20], ['flexible' => false]),
                self::column([
                    self::text('Notice: Running in Demo Mode', 'label_large', ['bold' => true, 'color' => '#92400E']),
                    self::text('Settings, file uploads, and credentials are view-only.', 'body_small', ['color' => '#B45309']),
                ], ['expanded' => true, 'spacing' => 2]),
            ], ['spacing' => 10, 'cross_axis_alignment' => 'center']),
        ], ['color' => '#FFFBEB', 'border_color' => '#FDE68A', 'dismissible' => true]);

        $walk = static function (&$node) use (&$walk) {
            if (! is_array($node)) {
                return;
            }
            $type = $node['type'] ?? null;

            if (in_array($type, ['file_upload', 'file_picker', 'image_upload'], true)) {
                $node['enabled'] = false;
                $node['disabled'] = true;
                $node['disabled_reason'] = 'File uploads are disabled in Demo Mode';
            }

            if (in_array($type, ['button_primary', 'button_danger'], true)) {
                $action = $node['action'] ?? null;
                $actionType = is_array($action) ? ($action['type'] ?? null) : null;
                if (in_array($actionType, ['form_submit', 'api_post'], true)
                    || (isset($node['endpoint']) && in_array(strtoupper((string) ($node['method'] ?? 'POST')), ['POST', 'PUT', 'PATCH', 'DELETE'], true))) {
                    $node['disabled'] = true;
                    $node['disabled_reason'] = 'Changes cannot be saved in Demo Mode';
                }
            }

            foreach (['components', 'children', 'tabs', 'steps'] as $key) {
                if (isset($node[$key]) && is_array($node[$key])) {
                    foreach ($node[$key] as &$child) {
                        $walk($child);
                    }
                    unset($child);
                }
            }
        };

        if (isset($schema['components']) && is_array($schema['components'])) {
            foreach ($schema['components'] as &$component) {
                $walk($component);
            }
            unset($component);
            array_unshift($schema['components'], $notice);
        }

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
