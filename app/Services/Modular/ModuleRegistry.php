<?php

namespace App\Services\Modular;

use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\PlatformSystem;
use App\Models\SduiModule;
use Illuminate\Support\Facades\Schema;

/**
 * Registry of pluggable business modules, schemas, and UI configurations.
 *
 * Powers Server-Driven UI (SDUI) across mobile and web clients: all modules,
 * layouts, feature toggles, cart settings, payment options, status labels,
 * and menu structures originate here, ensuring new business verticals can be
 * introduced server-side without mobile client binary recompilation.
 */
class ModuleRegistry
{
    /**
     * All registered business module schemas.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allModules(): array
    {
        $builtIn = [
            'retail' => [
                'id' => 'retail',
                'title' => 'Retail',
                'subtitle' => 'Shops, electronics, general stores',
                'description' => 'Shops, electronics, general stores',
                'layout_type' => 'standard_grid',
                'icon' => 'storefront',
                'features' => [
                    'has_tables' => false,
                    'has_kot' => false,
                    'has_barcode_scanner' => true,
                    'has_due_reminders' => true,
                    'prep_timer' => false,
                    'order_alerts' => false,
                ],
                'cart_configuration' => [
                    'show_customer_selector' => true,
                    'allow_split_payment' => true,
                    'tax_display' => 'country_default',
                    'allow_discounts' => true,
                    'allow_held_carts' => true,
                    'allow_notes' => true,
                ],
            ],
            'restaurant' => [
                'id' => 'restaurant',
                'title' => 'Cafe & Restaurant',
                'subtitle' => 'Tables, KOT, kitchen display',
                'description' => 'Tables, KOT, kitchen display',
                'layout_type' => 'table_floor_plan',
                'icon' => 'restaurant',
                'features' => [
                    'has_tables' => true,
                    'has_kot' => true,
                    'prep_timer' => true,
                    'order_alerts' => true,
                    'has_barcode_scanner' => false,
                    'has_due_reminders' => false,
                ],
                'cart_configuration' => [
                    'show_customer_selector' => true,
                    'allow_split_payment' => true,
                    'tax_display' => 'country_default',
                    'allow_discounts' => true,
                    'allow_held_carts' => true,
                    'allow_notes' => true,
                ],
            ],
            'pharmacy' => [
                'id' => 'pharmacy',
                'title' => 'Pharmacy POS',
                'subtitle' => 'Batches, expiry dates, medicines',
                'description' => 'Batches, expiry dates, medicines',
                'layout_type' => 'standard_grid',
                'icon' => 'medication',
                'features' => [
                    'has_tables' => false,
                    'has_kot' => false,
                    'has_barcode_scanner' => true,
                    'has_due_reminders' => true,
                    'batch_tracking' => true,
                    'expiry_tracking' => true,
                    'prescription_required' => true,
                    'prep_timer' => false,
                    'order_alerts' => false,
                ],
                'cart_configuration' => [
                    'show_customer_selector' => true,
                    'allow_split_payment' => true,
                    'tax_display' => 'country_default',
                    'allow_discounts' => true,
                    'allow_held_carts' => true,
                    'allow_notes' => true,
                ],
            ],
            'service_booking' => [
                'id' => 'service_booking',
                'title' => 'Service & Salon',
                'subtitle' => 'Appointments, stylist bookings',
                'description' => 'Appointments, stylist bookings',
                'layout_type' => 'service_booking_list',
                'icon' => 'content_cut',
                'features' => [
                    'has_tables' => false,
                    'has_kot' => false,
                    'has_barcode_scanner' => false,
                    'has_due_reminders' => true,
                    'appointment_scheduling' => true,
                    'staff_assignment' => true,
                    'prep_timer' => false,
                    'order_alerts' => true,
                ],
                'cart_configuration' => [
                    'show_customer_selector' => true,
                    'allow_split_payment' => true,
                    'tax_display' => 'country_default',
                    'allow_discounts' => true,
                    'allow_held_carts' => true,
                    'allow_notes' => true,
                ],
            ],
            'repair_technician' => [
                'id' => 'repair_technician',
                'title' => 'Repair & Service Workbench',
                'subtitle' => 'Tickets, parts billing, diagnostics, workbench',
                'description' => 'Tickets, technician workbench, spare parts billing, diagnostics',
                'layout_type' => 'repair_kanban',
                'icon' => 'handyman',
                'features' => [
                    'has_tables' => false,
                    'has_kot' => false,
                    'has_barcode_scanner' => true,
                    'has_due_reminders' => true,
                    'ticket_tracking' => true,
                    'parts_billing' => true,
                    'technician_workbench' => true,
                    'intake_checklist' => true,
                    'prep_timer' => false,
                    'order_alerts' => true,
                ],
                'cart_configuration' => [
                    'show_customer_selector' => true,
                    'allow_split_payment' => true,
                    'tax_display' => 'country_default',
                    'allow_discounts' => true,
                    'allow_held_carts' => true,
                    'allow_notes' => true,
                ],
            ],
        ];

        if (! Schema::hasTable('sdui_modules')) {
            return $builtIn;
        }

        try {
            $databaseModules = SduiModule::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->mapWithKeys(function (SduiModule $module): array {
                    $routes = $module->routes ?? [];

                    return [$module->slug => [
                        'id' => $module->slug,
                        'title' => $module->name,
                        'description' => $module->description ?? '',
                        'layout_type' => $module->layout_type,
                        'icon' => $module->icon,
                        'features' => $module->features ?? [],
                        'cart_configuration' => $routes['cart_configuration'] ?? [],
                        'routes' => $routes,
                        'navigation' => $module->navigation ?? [],
                        'source' => 'database',
                    ]];
                })
                ->all();

            // Database rows intentionally override built-ins with the same
            // slug, letting SuperAdmin change presentation without an app build.
            return array_replace($builtIn, $databaseModules);
        } catch (\Throwable) {
            // Bootstrap must remain available while migrations are running or
            // when an older installation has not created the SDUI tables yet.
            return $builtIn;
        }
    }

    /** @return array<string, mixed>|null */
    public static function find(string $modeId): ?array
    {
        $key = strtolower(trim($modeId));

        return self::allModules()[$key] ?? null;
    }

    /**
     * Resolve the active operating mode for the given company.
     */
    public static function resolveActiveMode(Company $company): string
    {
        if ($company->isRestaurantMode()) {
            return 'restaurant';
        }

        $rawMode = strtolower(trim((string) ($company->pos_mode ?: 'retail')));
        if (in_array($rawMode, ['general', 'general_retail', 'retail'], true)) {
            return 'retail';
        }

        $all = self::allModules();
        if (isset($all[$rawMode])) {
            return $rawMode;
        }

        return 'retail';
    }

    /**
     * Get globally enabled modules for tenant registration configured by SuperAdmin.
     *
     * @return list<string>
     */
    public static function enabledRegistrationModes(): array
    {
        $allKeys = array_keys(self::allModules());
        $raw = PlatformSystem::get('allowed_registration_modes', json_encode($allKeys));
        $databaseDefaults = [];
        if (Schema::hasTable('sdui_modules')) {
            try {
                $databaseDefaults = SduiModule::query()
                    ->where('is_active', true)
                    ->where('registration_allowed', true)
                    ->orderBy('sort_order')
                    ->pluck('slug')
                    ->all();
            } catch (\Throwable) {
                $databaseDefaults = [];
            }
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === 'both' || $trimmed === 'all') {
                return array_values(array_intersect(array_unique([...$allKeys, ...$databaseDefaults]), $allKeys));
            }
            if ($trimmed === 'retail_only') {
                return array_values(array_intersect(['retail'], $allKeys));
            }
            if ($trimmed === 'restaurant_only') {
                return array_values(array_intersect(['restaurant'], $allKeys));
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $filtered = array_values(array_intersect(array_unique([...$decoded, ...$databaseDefaults]), $allKeys));
                if (! empty($filtered)) {
                    return $filtered;
                }
            }
        } elseif (is_array($raw)) {
            $filtered = array_values(array_intersect(array_unique([...$raw, ...$databaseDefaults]), $allKeys));
            if (! empty($filtered)) {
                return $filtered;
            }
        }

        return array_values(array_intersect(array_unique([...$allKeys, ...$databaseDefaults]), $allKeys));
    }

    /**
     * Return module schemas for active registration modes.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function registrationModules(): array
    {
        $enabled = self::enabledRegistrationModes();
        $all = self::allModules();
        $result = [];
        foreach ($enabled as $key) {
            if (isset($all[$key])) {
                $result[$key] = $all[$key];
            }
        }

        return $result;
    }

    /**
     * Return list of all available/licensed modes for a tenant.
     *
     * @return list<string>
     */
    public static function availableModes(Company $company): array
    {
        $all = array_keys(self::allModules());
        $licensed = $company->licensed_modules;

        if (is_array($licensed) && ! empty($licensed)) {
            $modes = array_values(array_intersect($licensed, $all));
        } else {
            // Default to permanent active mode
            $active = self::resolveActiveMode($company);
            $modes = [$active];
        }

        if ($company->restaurant_mode_locked) {
            $modes = array_values(array_diff($modes, ['restaurant']));
            if (empty($modes)) {
                $modes = ['retail'];
            }
        }

        return ! empty($modes) ? $modes : ['retail'];
    }

    /**
     * Get module schema by ID, falling back to a synthesized schema for unknown modes.
     *
     * @return array<string, mixed>
     */
    public static function getModule(string $modeId): array
    {
        $module = self::find($modeId);
        if ($module !== null) {
            return $module;
        }

        return [
            'id' => $modeId,
            'title' => ucwords(str_replace(['_', '-'], ' ', $modeId)),
            'description' => 'Dynamic business module',
            'layout_type' => 'standard_grid',
            'icon' => 'widgets',
            'features' => [
                'has_tables' => false,
                'has_kot' => false,
                'has_barcode_scanner' => true,
                'has_due_reminders' => true,
                'prep_timer' => false,
                'order_alerts' => false,
            ],
            'cart_configuration' => [
                'show_customer_selector' => true,
                'allow_split_payment' => true,
                'tax_display' => 'country_default',
                'allow_discounts' => true,
                'allow_held_carts' => true,
                'allow_notes' => true,
            ],
        ];
    }

    /**
     * Payment methods schema with icons, brand colors, and field requirements.
     *
     * @return list<array<string, mixed>>
     */
    public static function paymentMethodsSchema(Company $company): array
    {
        $methods = [];
        try {
            if (class_exists(PaymentMethod::class)) {
                $dbMethods = PaymentMethod::query()
                    ->where('company_id', $company->id)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();

                foreach ($dbMethods as $method) {
                    $code = strtolower((string) ($method->code ?: $method->name));
                    $meta = self::paymentMethodPresentation($code);
                    $methods[] = [
                        'id' => (string) $method->id,
                        'code' => $method->code ?: (string) $method->id,
                        'name' => $method->name,
                        'icon' => $meta['icon'],
                        'color' => $meta['color'],
                        'is_credit' => (bool) ($method->is_credit ?? str_contains($code, 'credit') || str_contains($code, 'due')),
                        'requires_customer' => (bool) ($method->requires_customer ?? (str_contains($code, 'credit') || str_contains($code, 'due'))),
                        'metadata' => $method->metadata ?? [],
                    ];
                }
            }
        } catch (\Throwable) {
            // Fall back to default presets
        }

        if (! empty($methods)) {
            return $methods;
        }

        return [
            [
                'id' => 'cash',
                'code' => 'cash',
                'name' => 'Cash',
                'icon' => 'payments',
                'color' => '#15803d',
                'is_credit' => false,
                'requires_customer' => false,
                'metadata' => [],
            ],
            [
                'id' => 'card',
                'code' => 'card',
                'name' => 'Credit / Debit Card',
                'icon' => 'credit_card',
                'color' => '#1d4ed8',
                'is_credit' => false,
                'requires_customer' => false,
                'metadata' => [],
            ],
            [
                'id' => 'upi',
                'code' => 'upi',
                'name' => 'UPI / QR Payment',
                'icon' => 'qr_code_2',
                'color' => '#7e22ce',
                'is_credit' => false,
                'requires_customer' => false,
                'metadata' => [],
            ],
            [
                'id' => 'bank_transfer',
                'code' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'icon' => 'account_balance',
                'color' => '#0f766e',
                'is_credit' => false,
                'requires_customer' => false,
                'metadata' => [],
            ],
            [
                'id' => 'credit',
                'code' => 'credit',
                'name' => 'Store Credit / Khata (Due)',
                'icon' => 'schedule',
                'color' => '#b45309',
                'is_credit' => true,
                'requires_customer' => true,
                'metadata' => [],
            ],
        ];
    }

    /**
     * Resolve icon and color presentation for a payment method code.
     *
     * @return array{icon: string, color: string}
     */
    public static function paymentMethodPresentation(string $code): array
    {
        $c = strtolower($code);
        if (str_contains($c, 'cash')) {
            return ['icon' => 'payments', 'color' => '#15803d'];
        }
        if (str_contains($c, 'card')) {
            return ['icon' => 'credit_card', 'color' => '#1d4ed8'];
        }
        if (str_contains($c, 'upi') || str_contains($c, 'qr') || str_contains($c, 'gpay') || str_contains($c, 'phonepe')) {
            return ['icon' => 'qr_code_2', 'color' => '#7e22ce'];
        }
        if (str_contains($c, 'credit') || str_contains($c, 'due') || str_contains($c, 'khata')) {
            return ['icon' => 'schedule', 'color' => '#b45309'];
        }
        if (str_contains($c, 'bank') || str_contains($c, 'transfer')) {
            return ['icon' => 'account_balance', 'color' => '#0f766e'];
        }

        return ['icon' => 'account_balance_wallet', 'color' => '#475569'];
    }

    /**
     * Domain status definitions (sales, orders, consignments, quotations, KDS tickets).
     *
     * @return array<string, array<string, array{label: string, color: string, icon: string}>>
     */
    public static function statusLabelsSchema(): array
    {
        return [
            'sale' => [
                'completed' => ['label' => 'Completed', 'color' => '#16a34a', 'icon' => 'check_circle'],
                'paid' => ['label' => 'Paid', 'color' => '#16a34a', 'icon' => 'check_circle'],
                'partial' => ['label' => 'Partial Due', 'color' => '#ca8a04', 'icon' => 'timelapse'],
                'due' => ['label' => 'Unpaid / Due', 'color' => '#dc2626', 'icon' => 'pending_actions'],
                'cancelled' => ['label' => 'Cancelled', 'color' => '#ef4444', 'icon' => 'cancel'],
                'draft' => ['label' => 'Draft', 'color' => '#6b7280', 'icon' => 'drafts'],
                'refunded' => ['label' => 'Refunded', 'color' => '#9333ea', 'icon' => 'assignment_return'],
            ],
            'consignment' => [
                'draft' => ['label' => 'Draft', 'color' => '#6b7280', 'icon' => 'edit_note'],
                'dispatched' => ['label' => 'Dispatched', 'color' => '#2563eb', 'icon' => 'local_shipping'],
                'in_transit' => ['label' => 'In Transit', 'color' => '#0891b2', 'icon' => 'commute'],
                'received' => ['label' => 'Received', 'color' => '#16a34a', 'icon' => 'inventory'],
                'reconciled' => ['label' => 'Reconciled', 'color' => '#059669', 'icon' => 'verified'],
                'finalized' => ['label' => 'Finalized', 'color' => '#15803d', 'icon' => 'task_alt'],
                'rejected' => ['label' => 'Rejected', 'color' => '#ef4444', 'icon' => 'block'],
            ],
            'quotation' => [
                'draft' => ['label' => 'Draft', 'color' => '#6b7280', 'icon' => 'edit_note'],
                'sent' => ['label' => 'Sent', 'color' => '#2563eb', 'icon' => 'send'],
                'accepted' => ['label' => 'Accepted', 'color' => '#16a34a', 'icon' => 'thumb_up'],
                'rejected' => ['label' => 'Rejected', 'color' => '#ef4444', 'icon' => 'thumb_down'],
                'converted' => ['label' => 'Converted', 'color' => '#7c3aed', 'icon' => 'receipt_long'],
            ],
            'service_order' => [
                'pending' => ['label' => 'Pending', 'color' => '#ea580c', 'icon' => 'schedule'],
                'in_progress' => ['label' => 'In Progress', 'color' => '#2563eb', 'icon' => 'engineering'],
                'completed' => ['label' => 'Completed', 'color' => '#16a34a', 'icon' => 'check_circle'],
                'cancelled' => ['label' => 'Cancelled', 'color' => '#ef4444', 'icon' => 'cancel'],
            ],
            'kitchen' => [
                'pending' => ['label' => 'Pending', 'color' => '#ea580c', 'icon' => 'schedule'],
                'preparing' => ['label' => 'Cooking', 'color' => '#ca8a04', 'icon' => 'soup_kitchen'],
                'ready' => ['label' => 'Ready to Serve', 'color' => '#16a34a', 'icon' => 'notifications_active'],
                'served' => ['label' => 'Served', 'color' => '#4b5563', 'icon' => 'done_all'],
                'cancelled' => ['label' => 'Cancelled', 'color' => '#ef4444', 'icon' => 'cancel'],
            ],
        ];
    }

    /**
     * Tax configuration schema for the store.
     *
     * @return array<string, mixed>
     */
    public static function taxConfigurationSchema(Company $company): array
    {
        $country = strtoupper(trim((string) ($company->country ?? 'US')));
        $isIndia = $country === 'IN';

        return [
            'tax_id' => (string) ($company->tax_id ?? ''),
            'tax_label' => (string) ($company->tax_label ?? ($isIndia ? 'GST' : 'Tax')),
            'display_mode' => 'country_default',
            'is_india' => $isIndia,
            'sub_components' => $isIndia ? [
                ['key' => 'cgst', 'label' => 'CGST', 'split' => 0.5],
                ['key' => 'sgst', 'label' => 'SGST', 'split' => 0.5],
            ] : [],
        ];
    }

    /**
     * Available action pills in cart/checkout.
     *
     * @return list<array{key: string, label: string, icon: string, enabled: bool}>
     */
    public static function actionPillsSchema(Company $company): array
    {
        $activeMode = self::resolveActiveMode($company);
        $module = self::getModule($activeMode);
        $features = $module['features'] ?? [];
        $cart = $module['cart_configuration'] ?? [];

        return [
            [
                'key' => 'customer',
                'label' => 'Customer',
                'icon' => 'person_add_outlined',
                'enabled' => (bool) ($cart['show_customer_selector'] ?? true),
            ],
            [
                'key' => 'hold',
                'label' => 'Hold Order',
                'icon' => 'pause_circle_outline',
                'enabled' => (bool) ($cart['allow_held_carts'] ?? true),
            ],
            [
                'key' => 'note',
                'label' => 'Order Note',
                'icon' => 'edit_note',
                'enabled' => (bool) ($cart['allow_notes'] ?? true),
            ],
            [
                'key' => 'discount',
                'label' => 'Discount',
                'icon' => 'local_offer_outlined',
                'enabled' => (bool) ($cart['allow_discounts'] ?? true),
            ],
            [
                'key' => 'due_date',
                'label' => 'Due Date',
                'icon' => 'event',
                'enabled' => (bool) ($features['has_due_reminders'] ?? true),
            ],
            [
                'key' => 'due_reminder',
                'label' => 'Reminder',
                'icon' => 'notification_add_outlined',
                'enabled' => (bool) ($features['has_due_reminders'] ?? true),
            ],
        ];
    }
}
