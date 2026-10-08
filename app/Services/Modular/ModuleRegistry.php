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
 *
 * The script ships with two native verticals — retail and restaurant. Every
 * other vertical (pharmacy, salon, repair, …) is delivered as an installable
 * package (see docs/MODULE_PACKAGES.md); its schema is registered here under
 * extendedSchemas() and only surfaces once its sdui_modules row is active.
 */
class ModuleRegistry
{
    /** Verticals bundled with the script. */
    public const NATIVE = ['retail', 'restaurant'];

    /**
     * Base schemas for verticals that ship as installable packages, keyed by
     * their canonical operating-mode id (which can differ from the package
     * slug — e.g. the "salon" package is the "service_booking" mode).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function extendedSchemas(): array
    {
        $cart = [
            'show_customer_selector' => true,
            'allow_split_payment' => true,
            'tax_display' => 'country_default',
            'allow_discounts' => true,
            'allow_held_carts' => true,
            'allow_notes' => true,
        ];

        return [
            'pharmacy' => [
                'id' => 'pharmacy',
                'title' => 'Pharmacy POS',
                'subtitle' => 'Batches, expiry dates, medicines',
                'description' => 'Batches, expiry dates, medicines',
                'layout_type' => 'standard_grid',
                'icon' => 'medication',
                'features' => [
                    'has_tables' => false, 'has_kot' => false, 'has_barcode_scanner' => true,
                    'has_due_reminders' => true, 'batch_tracking' => true, 'expiry_tracking' => true,
                    'prescription_required' => true, 'prep_timer' => false, 'order_alerts' => false,
                ],
                'cart_configuration' => $cart,
            ],
            'service_booking' => [
                'id' => 'service_booking',
                'title' => 'Service & Salon',
                'subtitle' => 'Appointments, stylist bookings',
                'description' => 'Appointments, stylist bookings',
                'layout_type' => 'service_booking_list',
                'icon' => 'content_cut',
                'features' => [
                    'has_tables' => false, 'has_kot' => false, 'has_barcode_scanner' => false,
                    'has_due_reminders' => true, 'appointment_scheduling' => true, 'staff_assignment' => true,
                    'prep_timer' => false, 'order_alerts' => true,
                ],
                'cart_configuration' => $cart,
            ],
            'repair_technician' => [
                'id' => 'repair_technician',
                'title' => 'Repair & Service Workbench',
                'subtitle' => 'Tickets, parts billing, diagnostics, workbench',
                'description' => 'Tickets, technician workbench, spare parts billing, diagnostics',
                'layout_type' => 'repair_kanban',
                'icon' => 'handyman',
                'features' => [
                    'has_tables' => false, 'has_kot' => false, 'has_barcode_scanner' => true,
                    'has_due_reminders' => true, 'ticket_tracking' => true, 'parts_billing' => true,
                    'technician_workbench' => true, 'intake_checklist' => true, 'prep_timer' => false,
                    'order_alerts' => true,
                ],
                'cart_configuration' => $cart,
            ],
            'leadmanagement' => [
                'id' => 'leadmanagement',
                'type' => SduiModule::TYPE_EXTENSION,
                'title' => 'Lead Management System',
                'subtitle' => 'Lead pipeline, follow-ups, attribution, auto-sync CRM',
                'description' => 'Optional CRM extension: lead pipeline, activity tracking, source attribution, and customer conversion.',
                'layout_type' => 'standard_grid',
                'icon' => 'leaderboard',
                'features' => [
                    'has_tables' => false, 'has_kot' => false, 'has_barcode_scanner' => false,
                    'has_due_reminders' => true, 'prep_timer' => false, 'order_alerts' => false,
                    'has_leads' => true, 'has_activities' => true, 'has_quotations_linking' => true,
                    'has_invoices_linking' => true, 'has_customer_autoprovision' => true,
                    'leads' => true, 'lead_management' => true,
                ],
                'navigation' => [
                    [
                        'key' => 'lead_ops',
                        'title' => 'Lead Management',
                        'icon' => 'leaderboard',
                        'items' => [
                            ['key' => 'lead_dashboard', 'title' => 'Leads Dashboard', 'icon' => 'dashboard', 'target_endpoint' => '/api/tenant/lead-module/views/dashboard'],
                            ['key' => 'lead_create', 'title' => 'Capture Lead', 'icon' => 'person_add', 'target_endpoint' => '/api/tenant/lead-module/views/create-lead'],
                            ['key' => 'lead_pipeline', 'title' => 'Leads Pipeline', 'icon' => 'view_kanban', 'target_endpoint' => '/api/tenant/lead-module/views/leads'],
                            ['key' => 'lead_activities', 'title' => 'Follow-ups & Activities', 'icon' => 'event_note', 'target_endpoint' => '/api/tenant/lead-module/views/activities'],
                            ['key' => 'lead_sources', 'title' => 'Lead Sources', 'icon' => 'source', 'target_endpoint' => '/api/tenant/lead-module/views/sources'],
                        ],
                    ],
                ],
                'cart_configuration' => $cart,
            ],
            'hrm' => [
                'id' => 'hrm',
                'type' => SduiModule::TYPE_EXTENSION,
                'title' => 'Human Resource Management & Payroll',
                'subtitle' => 'Employees, attendance, leave tracking, POS clock-in & payroll',
                'description' => 'Comprehensive employee directory, POS PIN clock-in/out attendance, leave tracking, and sales commission payroll.',
                'layout_type' => 'standard_grid',
                'icon' => 'groups',
                'features' => [
                    'has_tables' => false, 'has_kot' => false, 'has_barcode_scanner' => false,
                    'has_employees' => true, 'has_attendance' => true, 'has_leaves' => true,
                    'has_payroll' => true, 'has_pos_clock_in' => true, 'has_sales_commissions' => true,
                ],
                'navigation' => [
                    [
                        'key' => 'hrm_group',
                        'title' => str_starts_with(strtolower((string) (request()->header('X-App-Locale') ?? request()->header('Accept-Language') ?? app()->getLocale())), 'hi') ? 'कर्मचारी और वेतन (HRM & Staff)' : 'HRM & Staff Management',
                        'icon' => 'groups',
                        'items' => [
                            ['key' => 'hrm_employees', 'title' => 'Staff Directory', 'icon' => 'badge', 'target_endpoint' => '/api/tenant/hrm/views/employees'],
                            ['key' => 'hrm_attendance', 'title' => 'Attendance Roster', 'icon' => 'schedule', 'target_endpoint' => '/api/tenant/hrm/views/attendance'],
                            ['key' => 'hrm_leaves', 'title' => 'Leave Requests', 'icon' => 'event_busy', 'target_endpoint' => '/api/tenant/hrm/views/leaves'],
                            ['key' => 'hrm_payroll', 'title' => 'Payroll & Commissions', 'icon' => 'payments', 'target_endpoint' => '/api/tenant/hrm/views/payroll'],
                        ],
                    ],
                ],
                'cart_configuration' => $cart,
            ],
            'loyalty' => [
                'id' => 'loyalty',
                'type' => SduiModule::TYPE_EXTENSION,
                'title' => 'Customer Loyalty, Rewards & Wallet Engine',
                'subtitle' => 'Points accrual, VIP customer tiers, and prepaid store wallet accounts',
                'description' => 'Configurable points accrual, VIP customer tiers, and prepaid store wallet accounts with POS checkout deduction.',
                'layout_type' => 'standard_grid',
                'icon' => 'wallet',
                'features' => [
                    'has_tables' => false, 'has_kot' => false, 'has_barcode_scanner' => false,
                    'has_loyalty' => true, 'has_points' => true, 'has_wallet' => true,
                    'has_vip_tiers' => true, 'has_redemption' => true,
                ],
                'navigation' => [
                    [
                        'key' => 'loyalty_group',
                        'title' => str_starts_with(strtolower((string) (request()->header('X-App-Locale') ?? request()->header('Accept-Language') ?? app()->getLocale())), 'hi') ? 'लॉयल्टी और ग्राहक वॉलेट' : 'Loyalty & Customer Wallet',
                        'icon' => 'wallet',
                        'items' => [
                            ['key' => 'loyalty_wallets', 'title' => 'Customer Balances & Top-up', 'icon' => 'account_balance_wallet', 'target_endpoint' => '/api/tenant/loyalty/views/wallets'],
                            ['key' => 'loyalty_tiers', 'title' => 'VIP Membership Tiers', 'icon' => 'military_tech', 'target_endpoint' => '/api/tenant/loyalty/views/tiers'],
                            ['key' => 'loyalty_settings', 'title' => 'Points Earning Rules', 'icon' => 'tune', 'target_endpoint' => '/api/tenant/loyalty/views/settings'],
                        ],
                    ],
                ],
                'cart_configuration' => $cart,
            ],
            'chat' => [
                'id' => 'chat',
                'type' => SduiModule::TYPE_EXTENSION,
                'title' => 'Unified Internal Staff Chat & Live Support',
                'subtitle' => 'Team messaging, broadcasts & help desk',
                'description' => 'Real-time internal staff messaging, presence indicators, super admin promotional announcements, and live chat support.',
                'layout_type' => 'standard_grid',
                'icon' => 'chat',
                'features' => [
                    'has_tables' => false, 'has_kot' => false, 'has_barcode_scanner' => false,
                    'has_internal_chat' => true,
                    'has_broadcasts' => true, 'has_presence' => true,
                ],
                'navigation' => [
                    [
                        'key' => 'chat_group',
                        'title' => str_starts_with(strtolower((string) (request()->header('X-App-Locale') ?? request()->header('Accept-Language') ?? app()->getLocale())), 'hi') ? 'आंतरिक संदेश और सहायता' : 'Staff Chat & Live Support',
                        'icon' => 'chat',
                        'items' => [
                            ['key' => 'chat_messages', 'title' => 'Live Staff Chat', 'icon' => 'forum', 'target_endpoint' => '/api/tenant/chat/views/staff-chat'],
                            ['key' => 'send_staff_notification', 'title' => 'Send Staff Notification', 'icon' => 'send_to_mobile', 'target_endpoint' => '/api/tenant/chat/views/staff-notifications'],
                        ],
                    ],
                ],
                'cart_configuration' => $cart,
            ],
        ];
    }

    /** package slug => canonical operating-mode id, from config/modules.php. */
    private static function packageAliases(): array
    {
        $aliases = [];
        foreach ((array) config('modules.registration.premium', []) as $mode => $slug) {
            $aliases[strtolower((string) $slug)] = (string) $mode;
        }

        return $aliases;
    }

    /**
     * Collapse a package slug to its canonical operating-mode id so a module is
     * only ever represented once (e.g. the "salon" package === the
     * "service_booking" mode, "repairtechnician" === "repair_technician").
     * Anything without an alias — the native verticals, a future third-party
     * module — is returned unchanged.
     */
    public static function canonicalKey(string $key): string
    {
        $key = strtolower(trim($key));

        if (in_array($key, ['lead', 'leads', 'lead_management', 'lead-management'], true)) {
            return 'leadmanagement';
        }

        if (in_array($key, ['repair', 'repairs', 'repairtechnician', 'repair_technician'], true)) {
            return 'repair_technician';
        }

        if (in_array($key, ['salon', 'salons', 'service_booking'], true)) {
            return 'service_booking';
        }

        return self::packageAliases()[$key] ?? $key;
    }

    /**
     * All registered business module schemas.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allModules(): array
    {
        $cart = [
            'show_customer_selector' => true,
            'allow_split_payment' => true,
            'tax_display' => 'country_default',
            'allow_discounts' => true,
            'allow_held_carts' => true,
            'allow_notes' => true,
        ];

        $builtIn = [
            'retail' => [
                'id' => 'retail',
                'title' => 'Retail',
                'subtitle' => 'Shops, electronics, general stores',
                'description' => 'Shops, electronics, general stores',
                'layout_type' => 'standard_grid',
                'icon' => 'storefront',
                'features' => [
                    'has_tables' => false, 'has_kot' => false, 'has_barcode_scanner' => true,
                    'has_due_reminders' => true, 'prep_timer' => false, 'order_alerts' => false,
                ],
                'cart_configuration' => $cart,
            ],
            'restaurant' => [
                'id' => 'restaurant',
                'title' => 'Cafe & Restaurant',
                'subtitle' => 'Tables, KOT, kitchen display',
                'description' => 'Tables, KOT, kitchen display',
                'layout_type' => 'table_floor_plan',
                'icon' => 'restaurant',
                'features' => [
                    'has_tables' => true, 'has_kot' => true, 'prep_timer' => true,
                    'order_alerts' => true, 'has_barcode_scanner' => false, 'has_due_reminders' => false,
                ],
                'cart_configuration' => $cart,
            ],
        ];

        if (! Schema::hasTable('sdui_modules')) {
            return $builtIn;
        }

        try {
            $extended = self::extendedSchemas();
            $databaseModules = [];

            foreach (SduiModule::query()->where('is_active', true)->orderBy('sort_order')->get() as $module) {
                if ($module->isExtension() && (
                    ! $module->isLicensed() || $module->licenseIsExpired()
                    || $module->source_type !== 'package' || ! $module->package_path
                    || (! is_file(base_path('modules/'.$module->package_path.'/module.json'))
                        && ! is_file(base_path('module-packages/'.$module->package_path.'/module.json')))
                )) {
                    continue;
                }
                $routes = $module->routes ?? [];

                // One entry per physical module, keyed by its canonical
                // operating-mode id (so "salon"/"repairtechnician" don't show
                // up twice alongside "service_booking"/"repair_technician").
                $key = self::canonicalKey($module->slug);

                $base = isset($extended[$key])
                    ? array_replace($extended[$key], [
                        'features' => array_replace($extended[$key]['features'], $module->features ?? []),
                    ])
                    : [
                        'id' => $key,
                        'title' => $module->name,
                        'description' => $module->description ?? '',
                        'layout_type' => $module->layout_type,
                        'icon' => $module->icon,
                        'features' => $module->features ?? [],
                        'cart_configuration' => $routes['cart_configuration'] ?? [],
                    ];

                $databaseModules[$key] = array_replace($base, array_filter([
                    'title' => $module->name,
                    'navigation' => $module->navigation ?: null,
                    'routes' => $routes ?: null,
                ], fn ($v) => $v !== null), [
                    'id' => $key,
                    'slug' => $module->slug,
                    'source' => 'database',
                    'type' => $module->isExtension() ? SduiModule::TYPE_EXTENSION : SduiModule::TYPE_CORE,
                ]);
            }

            // Database rows override built-ins with the same slug, letting
            // SuperAdmin change presentation without an app build.
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
        $key = self::canonicalKey($modeId);

        return self::allModules()[$key] ?? null;
    }

    /** Active business operating modes, excluding additive extensions. */
    public static function operatingModules(): array
    {
        return array_filter(self::allModules(), fn ($module) => ($module['type'] ?? SduiModule::TYPE_CORE) !== SduiModule::TYPE_EXTENSION);
    }

    /**
     * Check if a module slug is physically installed, activated in the database,
     * and holds a valid active license.
     */
    public static function isModuleInstalledAndActive(string $slug): bool
    {
        if (! Schema::hasTable('sdui_modules')) {
            return false;
        }

        try {
            $slugs = [strtolower(trim($slug))];
            if ($slug === 'repairtechnician' || $slug === 'repair_technician') {
                $slugs = ['repairtechnician', 'repair_technician'];
            } elseif ($slug === 'salon' || $slug === 'service_booking') {
                $slugs = ['salon', 'service_booking'];
            }

            $module = SduiModule::query()
                ->whereIn('slug', $slugs)
                ->where('is_active', true)
                ->first();

            if (! $module) {
                return false;
            }

            return $module->isLicensed() && ! $module->licenseIsExpired();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Comprehensive business operating modes synchronized with installed license modules.
     * Core defaults (always unlocked): Retail, Cafe & Restaurant.
     * Gated verticals:
     * - PHARMACY requires module slug 'pharmacy' installed and active.
     * - REPAIR_TECHNICIAN requires module slug 'repairtechnician' installed and active.
     * - SERVICE_BOOKING (Salon) requires module slug 'salon' installed and active.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getAvailableModes(): array
    {
        $all = self::allModules();
        $extended = self::extendedSchemas();

        $pharmacyActive = self::isModuleInstalledAndActive('pharmacy');
        $repairActive = self::isModuleInstalledAndActive('repairtechnician');
        $salonActive = self::isModuleInstalledAndActive('salon');

        return [
            'retail' => array_replace($all['retail'] ?? [
                'id' => 'retail',
                'title' => 'Retail',
                'subtitle' => 'Shops, electronics, general stores',
                'description' => 'Barcode scanning, cash register, quotations, invoices, and standard stock management for retail shops.',
                'layout_type' => 'standard_grid',
                'icon' => 'storefront',
            ], [
                'id' => 'retail',
                'title' => 'Retail',
                'is_core' => true,
                'is_locked' => false,
                'lock_reason' => null,
                'required_module_slug' => null,
                'store_link' => null,
            ]),

            'restaurant' => array_replace($all['restaurant'] ?? [
                'id' => 'restaurant',
                'title' => 'Cafe & Restaurant',
                'subtitle' => 'Tables, KOT, kitchen display',
                'description' => 'Floor plans & live tables, KOT tickets, Kitchen Display (KDS), Dine-In/Takeaway routing, and QR table ordering.',
                'layout_type' => 'table_floor_plan',
                'icon' => 'restaurant',
            ], [
                'id' => 'restaurant',
                'title' => 'Cafe & Restaurant',
                'is_core' => true,
                'is_locked' => false,
                'lock_reason' => null,
                'required_module_slug' => null,
                'store_link' => null,
            ]),

            'pharmacy' => array_replace($all['pharmacy'] ?? $extended['pharmacy'] ?? [
                'id' => 'pharmacy',
                'title' => 'Pharmacy',
                'subtitle' => 'Batches, expiry dates, medicines',
                'description' => 'Drug batch & expiry tracking, prescription intake, FEFO stock and dispensing.',
                'layout_type' => 'standard_grid',
                'icon' => 'medication',
            ], [
                'id' => 'pharmacy',
                'title' => 'Pharmacy',
                'is_core' => false,
                'is_locked' => ! $pharmacyActive,
                'lock_reason' => ! $pharmacyActive ? 'Module Not Installed - Extended License / Add-on required' : null,
                'required_module_slug' => 'pharmacy',
                'store_link' => ModuleCatalog::storeLink('pharmacy'),
            ]),

            'repair_technician' => array_replace($all['repair_technician'] ?? $extended['repair_technician'] ?? [
                'id' => 'repair_technician',
                'title' => 'Repair Technician',
                'subtitle' => 'Tickets, technician workbench, spare parts billing',
                'description' => 'Device intake tickets, diagnostic checklist, parts & labor, technician workbench and pickup.',
                'layout_type' => 'repair_kanban',
                'icon' => 'handyman',
            ], [
                'id' => 'repair_technician',
                'title' => 'Repair Technician',
                'is_core' => false,
                'is_locked' => ! $repairActive,
                'lock_reason' => ! $repairActive ? 'Module Not Installed - Extended License / Add-on required' : null,
                'required_module_slug' => 'repairtechnician',
                'store_link' => ModuleCatalog::storeLink('repairtechnician'),
            ]),

            'service_booking' => array_replace($all['service_booking'] ?? $extended['service_booking'] ?? [
                'id' => 'service_booking',
                'title' => 'Salon & Bookings',
                'subtitle' => 'Appointments, stylist bookings',
                'description' => 'Service catalogue, stylists / specialists, appointment booking and lifecycle.',
                'layout_type' => 'service_booking_list',
                'icon' => 'content_cut',
            ], [
                'id' => 'service_booking',
                'title' => 'Salon & Bookings',
                'is_core' => false,
                'is_locked' => ! $salonActive,
                'lock_reason' => ! $salonActive ? 'Module Not Installed - Extended License / Add-on required' : null,
                'required_module_slug' => 'salon',
                'store_link' => ModuleCatalog::storeLink('salon'),
            ]),
        ];
    }

    /**
     * Modular add-ons and extensions (e.g. Lead Management / CRM).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getAvailableExtensions(): array
    {
        $catalog = collect(ModuleCatalog::available())->keyBy('slug');
        $extended = self::extendedSchemas();

        $extensions = [];
        $extensionSlugs = array_values(array_unique(array_merge(
            (array) config('modules.extensions', []),
            ['leadmanagement']
        )));

        if (Schema::hasTable('sdui_modules') && Schema::hasColumn('sdui_modules', 'type')) {
            $dbExts = SduiModule::query()->where('type', SduiModule::TYPE_EXTENSION)->pluck('slug')->all();
            $extensionSlugs = array_values(array_unique(array_merge($extensionSlugs, $dbExts)));
        }

        // Exclude features that are built into core
        $extensionSlugs = array_values(array_diff($extensionSlugs, ['whatsapp_api', 'custom_domain']));

        foreach ($extensionSlugs as $slug) {
            $catItem = $catalog->get($slug);
            $extSchema = $extended[$slug] ?? null;

            $module = null;
            if (Schema::hasTable('sdui_modules')) {
                $module = SduiModule::query()->where('slug', $slug)->first();
            }

            $isInstalled = $module !== null;
            $isActive = $module && $module->is_active && $module->isLicensed() && ! $module->licenseIsExpired();
            $isLocked = ! $isActive;

            $title = $extSchema['title'] ?? $catItem['name'] ?? ucwords(str_replace(['_', '-'], ' ', $slug));
            $description = $extSchema['description'] ?? $catItem['description'] ?? 'Optional platform extension managed by SuperAdmin.';
            $icon = $extSchema['icon'] ?? 'leaderboard';

            $extensions[$slug] = [
                'id' => $slug,
                'slug' => $slug,
                'title' => $title,
                'description' => $description,
                'icon' => $icon,
                'is_installed' => $isInstalled,
                'is_active' => (bool) $isActive,
                'is_locked' => $isLocked,
                'lock_reason' => $isLocked ? 'Extension Not Purchased or Not Activated - License Required' : null,
                'store_link' => ModuleCatalog::storeLink($slug),
            ];
        }

        return $extensions;
    }

    /** Includes inactive extensions so disabling a package cannot change its type. */
    public static function extensionKeys(): array
    {
        $keys = (array) config('modules.extensions', []);
        if (Schema::hasTable('sdui_modules') && Schema::hasColumn('sdui_modules', 'type')) {
            $keys = [...$keys, ...SduiModule::query()->where('type', SduiModule::TYPE_EXTENSION)->pluck('slug')->all()];
        }

        // Exclude features that are built into core
        $keys = array_diff($keys, ['whatsapp_api', 'custom_domain']);

        return array_values(array_unique(array_map([self::class, 'canonicalKey'], $keys)));
    }

    public static function isExtension(string $key): bool
    {
        return in_array(self::canonicalKey($key), self::extensionKeys(), true);
    }

    /** Tenant bootstrap keeps the existing core catalog and assigned extensions. */
    public static function modulesFor(Company $company): array
    {
        $licensed = $company->licensedModuleKeys();

        return array_filter(self::allModules(), fn ($module, $key) => ($module['type'] ?? SduiModule::TYPE_CORE) !== SduiModule::TYPE_EXTENSION
            || in_array($key, $licensed, true), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Is this module usable right now? True for a built-in vertical, or for a
     * package module whose sdui_modules row exists and is active. A
     * deactivated / uninstalled package returns false.
     *
     * Blade / controller gate for module-specific UI:
     *
     *   @if (\App\Services\Modular\ModuleRegistry::isActive('pharmacy')) ...
     */
    public static function isActive(string $key): bool
    {
        return self::find(strtolower(trim($key))) !== null;
    }

    /**
     * Is a package module present on disk / in the registry at all (active or
     * not)? False once it has been fully uninstalled. Built-ins are always
     * "installed".
     */
    public static function isInstalled(string $key): bool
    {
        $key = self::canonicalKey($key);

        if (in_array($key, self::NATIVE, true)) {
            return true;
        }

        if (! Schema::hasTable('sdui_modules')) {
            return false;
        }

        try {
            $aliases = array_flip(self::packageAliases()); // mode id => package slug
            $slug = $aliases[$key] ?? $key;

            return SduiModule::query()->where('slug', $slug)->orWhere('slug', $key)->exists();
        } catch (\Throwable) {
            return false;
        }
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

        $rawMode = self::canonicalKey($rawMode);
        $all = self::operatingModules();
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
        $allKeys = array_keys(self::operatingModules());
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

        // Stored values and package slugs may be pre-alias (e.g. "salon"); map
        // everything to canonical mode ids so they match $allKeys.
        $canon = fn (array $keys) => array_map([self::class, 'canonicalKey'], $keys);
        $databaseDefaults = $canon($databaseDefaults);

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
                $filtered = array_values(array_intersect(array_unique([...$canon($decoded), ...$databaseDefaults]), $allKeys));
                if (! empty($filtered)) {
                    return $filtered;
                }
            }
        } elseif (is_array($raw)) {
            $filtered = array_values(array_intersect(array_unique([...$canon($raw), ...$databaseDefaults]), $allKeys));
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
        $all = array_keys(self::operatingModules());
        $licensed = $company->licensed_modules;

        if (is_array($licensed) && ! empty($licensed)) {
            $licensed = array_map([self::class, 'canonicalKey'], $licensed);
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
     * Merged feature map across every mode currently available to the tenant
     * (built-ins + licensed, active package modules). Additive: a flag is on if
     * any active module turns it on. Lets the mobile app show/hide features
     * purely from server state — no client build.
     *
     * No license lookup is needed here: allModules() already only contains
     * package rows with is_active = true, and the daily license job guarantees
     * `is_active` ⇒ licensed.
     *
     * @return array<string, mixed>
     */
    public static function activeFeaturesFor(Company $company): array
    {
        $all = self::allModules();
        $keys = array_values(array_unique([
            ...self::availableModes($company),
            self::resolveActiveMode($company),
            ...array_intersect($company->licensedModuleKeys(), self::extensionKeys()),
        ]));

        $merged = [];
        foreach ($keys as $key) {
            foreach (($all[$key]['features'] ?? []) as $featureKey => $value) {
                $merged[$featureKey] = (($merged[$featureKey] ?? false) === true || $value === true)
                    ? true
                    : ($merged[$featureKey] ?? $value);
            }
        }

        $merged['pos'] = true;
        $merged['sales'] = true;
        $merged['quotes'] = true;
        $merged['quotations'] = true;
        $merged['consignments'] = true;
        $merged['customers'] = true;

        $hasLead = $company->hasModule('leadmanagement');

        $merged['leads'] = $hasLead;
        $merged['lead_management'] = $hasLead;
        $merged['has_leads'] = $hasLead;

        return $merged;
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
                // Single source of truth shared with the web Settings screen —
                // auto-seeds the 3 defaults for a brand-new company and returns
                // only the active methods, ordered. Keeps the tenant's
                // configured tenders identical across every module and client.
                $dbMethods = PaymentMethod::getForCompany($company->id);

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

    /**
     * Get default modules for a given store operating mode / store type.
     *
     * @return list<array{key: string, group: string, label: string, icon: string, route: string}>
     */
    public static function getModulesForStoreType(string $storeType): array
    {
        $type = strtoupper(trim($storeType));
        if (in_array($type, ['RESTAURANT', 'FOOD_RESTAURANT', 'CAFE', 'FOOD'], true)) {
            return [
                ['key' => 'point_of_sale', 'group' => 'cashier_sales', 'label' => 'Restaurant POS', 'icon' => 'restaurant', 'route' => 'tenant.restaurant.pos'],
                ['key' => 'sales_invoices', 'group' => 'cashier_sales', 'label' => 'Sales & Invoices', 'icon' => 'receipt_long', 'route' => 'tenant.sales.index'],
                ['key' => 'quotations', 'group' => 'cashier_sales', 'label' => 'Quotations & Party Orders', 'icon' => 'description', 'route' => 'tenant.quotes.index'],
                ['key' => 'crm_customers', 'group' => 'cashier_sales', 'label' => 'Customers & CRM', 'icon' => 'people', 'route' => 'tenant.customers.index'],
                ['key' => 'tables_floor_plan', 'group' => 'restaurant_operations', 'label' => 'Floor Plan & Tables', 'icon' => 'table_restaurant', 'route' => 'tenant.restaurant.tables'],
                ['key' => 'kot_orders', 'group' => 'restaurant_operations', 'label' => 'KOT Orders & Live Queue', 'icon' => 'receipt', 'route' => 'tenant.sales.index'],
                ['key' => 'kitchen_display_kds', 'group' => 'restaurant_operations', 'label' => 'Kitchen Display (KDS)', 'icon' => 'soup_kitchen', 'route' => 'tenant.restaurant.kds'],
                ['key' => 'cash_register', 'group' => 'financial_management', 'label' => 'Cash Register', 'icon' => 'savings', 'route' => 'tenant.financials.cash_register'],
                ['key' => 'accounts_receivable', 'group' => 'financial_management', 'label' => 'Accounts Receivable', 'icon' => 'notifications_active', 'route' => 'tenant.financials.receivables'],
                ['key' => 'accounts_payable', 'group' => 'financial_management', 'label' => 'Accounts Payable', 'icon' => 'request_quote', 'route' => 'tenant.financials.payables'],
                ['key' => 'reports', 'group' => 'financial_management', 'label' => 'Reports', 'icon' => 'insights', 'route' => 'tenant.reports.index'],
                ['key' => 'analytics', 'group' => 'financial_management', 'label' => 'Analytics', 'icon' => 'bar_chart', 'route' => 'tenant.reports.index'],
                ['key' => 'inventory_catalog', 'group' => 'kitchen_menu_catalog', 'label' => 'Menu Dishes & Stock', 'icon' => 'inventory_2', 'route' => 'tenant.products.index'],
                ['key' => 'categories', 'group' => 'kitchen_menu_catalog', 'label' => 'Categories', 'icon' => 'sell', 'route' => 'tenant.categories.index'],
                ['key' => 'brands', 'group' => 'kitchen_menu_catalog', 'label' => 'Brands & Modifiers', 'icon' => 'auto_awesome', 'route' => 'tenant.brands.index'],
                ['key' => 'units', 'group' => 'kitchen_menu_catalog', 'label' => 'Units of Measure', 'icon' => 'straighten', 'route' => 'tenant.units.index'],
                ['key' => 'suppliers', 'group' => 'kitchen_menu_catalog', 'label' => 'Food Suppliers', 'icon' => 'local_shipping', 'route' => 'tenant.suppliers.index'],
                ['key' => 'store_settings', 'group' => 'administration', 'label' => 'Store Settings', 'icon' => 'settings', 'route' => 'tenant.settings.index'],
            ];
        }

        if (in_array($type, ['PHARMACY'], true)) {
            return [
                ['key' => 'pharmacy_pos', 'group' => 'pharmacy_management', 'label' => 'Pharmacy POS & Checkout', 'icon' => 'point_of_sale', 'route' => 'pos'],
                ['key' => 'new_prescription_intake', 'group' => 'pharmacy_management', 'label' => 'New Prescription Intake', 'icon' => 'note_add', 'route' => '/api/tenant/views/pharmacy-rx-create'],
                ['key' => 'prescriptions_queue', 'group' => 'pharmacy_management', 'label' => 'Prescriptions & Patient Queue', 'icon' => 'medical_information', 'route' => 'tenant.pharmacy.prescriptions'],
                ['key' => 'batch_inventory', 'group' => 'pharmacy_management', 'label' => 'Drug Batches & Expiry Tracker', 'icon' => 'inventory_2', 'route' => 'tenant.pharmacy.batches'],
                ['key' => 'sales_invoices', 'group' => 'cashier_sales', 'label' => 'Sales & Invoices History', 'icon' => 'receipt_long', 'route' => 'tenant.sales.index'],
                ['key' => 'quotations', 'group' => 'cashier_sales', 'label' => 'Quotations & Estimates', 'icon' => 'description', 'route' => 'tenant.quotes.index'],
                ['key' => 'crm_customers', 'group' => 'cashier_sales', 'label' => 'Patients & Doctors', 'icon' => 'people', 'route' => 'tenant.customers.index'],
                ['key' => 'cash_register', 'group' => 'financial_management', 'label' => 'Cash Register', 'icon' => 'savings', 'route' => 'tenant.financials.cash_register'],
                ['key' => 'reports', 'group' => 'financial_management', 'label' => 'Reports', 'icon' => 'insights', 'route' => 'tenant.reports.index'],
                ['key' => 'store_settings', 'group' => 'administration', 'label' => 'Store Settings', 'icon' => 'settings', 'route' => 'tenant.settings.index'],
            ];
        }

        if (in_array($type, ['SERVICE_BOOKING', 'SALON'], true)) {
            return [
                ['key' => 'salon_pos', 'group' => 'salon_bookings', 'label' => 'Salon POS & Checkout', 'icon' => 'point_of_sale', 'route' => '/api/tenant/views/salon-pos'],
                ['key' => 'book_appointment', 'group' => 'salon_bookings', 'label' => 'Book Service / Appointment', 'icon' => 'edit_calendar', 'route' => '/api/tenant/views/salon-booking-create'],
                ['key' => 'booking_calendar', 'group' => 'salon_bookings', 'label' => 'Service Booking Calendar', 'icon' => 'calendar_month', 'route' => 'tenant.salon.calendar'],
                ['key' => 'service_catalog', 'group' => 'salon_bookings', 'label' => 'Service Catalog & Rates', 'icon' => 'format_list_bulleted', 'route' => 'tenant.salon.services'],
                ['key' => 'service_stylists', 'group' => 'salon_bookings', 'label' => 'Stylists & Staff Assignments', 'icon' => 'badge', 'route' => 'tenant.salon.stylists'],
                ['key' => 'sales_invoices', 'group' => 'cashier_sales', 'label' => 'Sales & Invoices History', 'icon' => 'receipt_long', 'route' => 'tenant.sales.index'],
                ['key' => 'crm_customers', 'group' => 'cashier_sales', 'label' => 'Clients & CRM', 'icon' => 'people', 'route' => 'tenant.customers.index'],
                ['key' => 'cash_register', 'group' => 'financial_management', 'label' => 'Cash Register', 'icon' => 'savings', 'route' => 'tenant.financials.cash_register'],
                ['key' => 'reports', 'group' => 'financial_management', 'label' => 'Reports', 'icon' => 'insights', 'route' => 'tenant.reports.index'],
                ['key' => 'store_settings', 'group' => 'administration', 'label' => 'Store Settings', 'icon' => 'settings', 'route' => 'tenant.settings.index'],
            ];
        }

        if (in_array($type, ['REPAIR_TECHNICIAN', 'REPAIRTECHNICIAN', 'REPAIR', 'TECHNICIAN'], true)) {
            return [
                ['key' => 'repair_dashboard', 'group' => 'repair_service', 'label' => 'Repair Workbench', 'icon' => 'handyman', 'route' => 'tenant.repair.dashboard'],
                ['key' => 'repair_create_ticket', 'group' => 'repair_service', 'label' => 'New Intake Ticket', 'icon' => 'add_task', 'route' => '/api/tenant/views/repair-create-ticket'],
                ['key' => 'repair_tickets', 'group' => 'repair_service', 'label' => 'Repair Ticket Register', 'icon' => 'receipt_long', 'route' => 'tenant.repair.tickets'],
                ['key' => 'repair_categories', 'group' => 'repair_service', 'label' => 'Device Categories', 'icon' => 'devices', 'route' => 'tenant.repair.categories'],
                ['key' => 'sales_invoices', 'group' => 'cashier_sales', 'label' => 'Sales & Invoices History', 'icon' => 'receipt_long', 'route' => 'tenant.sales.index'],
                ['key' => 'crm_customers', 'group' => 'cashier_sales', 'label' => 'Customers & CRM', 'icon' => 'people', 'route' => 'tenant.customers.index'],
                ['key' => 'cash_register', 'group' => 'financial_management', 'label' => 'Cash Register', 'icon' => 'savings', 'route' => 'tenant.financials.cash_register'],
                ['key' => 'reports', 'group' => 'financial_management', 'label' => 'Reports', 'icon' => 'insights', 'route' => 'tenant.reports.index'],
                ['key' => 'store_settings', 'group' => 'administration', 'label' => 'Store Settings', 'icon' => 'settings', 'route' => 'tenant.settings.index'],
            ];
        }

        if (in_array($type, ['LEADMANAGEMENT', 'LEAD_MANAGEMENT', 'LEAD'], true)) {
            return [
                ['key' => 'lead_dashboard', 'group' => 'lead_ops', 'label' => 'Leads Dashboard', 'icon' => 'dashboard', 'route' => '/api/tenant/lead-module/views/dashboard'],
                ['key' => 'lead_pipeline', 'group' => 'lead_ops', 'label' => 'Leads Pipeline', 'icon' => 'view_kanban', 'route' => '/api/tenant/lead-module/views/leads'],
                ['key' => 'lead_activities', 'group' => 'lead_ops', 'label' => 'Follow-ups & Activities', 'icon' => 'event_note', 'route' => '/api/tenant/lead-module/views/activities'],
                ['key' => 'lead_sources', 'group' => 'lead_ops', 'label' => 'Lead Sources', 'icon' => 'source', 'route' => '/api/tenant/lead-module/views/sources'],
            ];
        }

        // RETAIL default
        return [
            ['key' => 'point_of_sale', 'group' => 'cashier_sales', 'label' => 'Point of Sale', 'icon' => 'point_of_sale', 'route' => 'tenant.sales.create'],
            ['key' => 'barcode_printing', 'group' => 'cashier_sales', 'label' => 'Barcode & Label Printing', 'icon' => 'qr_code', 'route' => 'tenant.products.index'],
            ['key' => 'batch_tracking', 'group' => 'cashier_sales', 'label' => 'Batch & Expiry Tracking', 'icon' => 'batch_prediction', 'route' => 'tenant.products.index'],
            ['key' => 'sales_invoices', 'group' => 'cashier_sales', 'label' => 'Sales & Invoices', 'icon' => 'receipt_long', 'route' => 'tenant.sales.index'],
            ['key' => 'quotations', 'group' => 'cashier_sales', 'label' => 'Quotations & Proposals', 'icon' => 'description', 'route' => 'tenant.quotes.index'],
            ['key' => 'crm_customers', 'group' => 'cashier_sales', 'label' => 'Customers & CRM', 'icon' => 'people', 'route' => 'tenant.customers.index'],
            ['key' => 'cash_register', 'group' => 'financial_management', 'label' => 'Cash Register', 'icon' => 'savings', 'route' => 'tenant.financials.cash_register'],
            ['key' => 'accounts_receivable', 'group' => 'financial_management', 'label' => 'Accounts Receivable', 'icon' => 'notifications_active', 'route' => 'tenant.financials.receivables'],
            ['key' => 'accounts_payable', 'group' => 'financial_management', 'label' => 'Accounts Payable', 'icon' => 'request_quote', 'route' => 'tenant.financials.payables'],
            ['key' => 'reports', 'group' => 'financial_management', 'label' => 'Reports', 'icon' => 'insights', 'route' => 'tenant.reports.index'],
            ['key' => 'analytics', 'group' => 'financial_management', 'label' => 'Analytics', 'icon' => 'bar_chart', 'route' => 'tenant.reports.index'],
            ['key' => 'inventory_catalog', 'group' => 'products_inventory', 'label' => 'All Products', 'icon' => 'inventory_2', 'route' => 'tenant.products.index'],
            ['key' => 'categories', 'group' => 'products_inventory', 'label' => 'Categories', 'icon' => 'sell', 'route' => 'tenant.categories.index'],
            ['key' => 'brands', 'group' => 'products_inventory', 'label' => 'Brands & Manufacturers', 'icon' => 'auto_awesome', 'route' => 'tenant.brands.index'],
            ['key' => 'units', 'group' => 'products_inventory', 'label' => 'Units of Measure', 'icon' => 'straighten', 'route' => 'tenant.units.index'],
            ['key' => 'suppliers', 'group' => 'products_inventory', 'label' => 'Suppliers & Vendors', 'icon' => 'local_shipping', 'route' => 'tenant.suppliers.index'],
            ['key' => 'store_settings', 'group' => 'administration', 'label' => 'Store Settings', 'icon' => 'settings', 'route' => 'tenant.settings.index'],
        ];
    }
}
