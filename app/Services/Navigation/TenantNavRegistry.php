<?php

namespace App\Services\Navigation;

use App\Models\Company;
use App\Models\SduiModule;
use App\Services\Modular\ModuleRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The nav tree for the tenant sidebar/drawer and mobile client,
 * supporting general retail, restaurant & cafe, pharmacy, service booking,
 * and future pluggable business modules.
 */
class TenantNavRegistry
{
    /**
     * The primary, mutually-exclusive business verticals. A store selects one
     * of these at registration; a tenant with an explicit licensed_modules
     * whitelist is never shown the nav for a primary vertical it did not pick.
     * Keyed by canonical mode id (see ModuleRegistry::canonicalKey()).
     */
    private const PRIMARY_VERTICALS = ['retail', 'restaurant', 'pharmacy', 'service_booking', 'repair_technician'];

    /**
     * Resolve all normalized active and licensed modes for a tenant.
     *
     * @return list<string>
     */
    public static function resolveTenantModes(mixed $tenant): array
    {
        if ($tenant instanceof Company) {
            $modes = (array) ($tenant->licensed_modules ?: []);
            if ($tenant->operating_mode) {
                $modes[] = $tenant->operating_mode;
            }
            if ($tenant->pos_mode) {
                $modes[] = $tenant->pos_mode;
            }
        } elseif (is_object($tenant)) {
            $modes = (array) ($tenant->licensed_modules ?? []);
            if (isset($tenant->operating_mode)) {
                $modes[] = $tenant->operating_mode;
            }
            if (isset($tenant->pos_mode)) {
                $modes[] = $tenant->pos_mode;
            }
        } elseif (is_string($tenant) && trim($tenant) !== '') {
            $modes = [trim($tenant)];
        } else {
            $modes = ['retail'];
        }

        $normalized = [];
        foreach ($modes as $item) {
            if (is_string($item)) {
                $norm = strtolower(trim($item));
                $norm = ModuleRegistry::canonicalKey($norm);
                $norm = match ($norm) {
                    'general', 'general_retail' => 'retail',
                    'food_restaurant' => 'restaurant',
                    'repair', 'repairs', 'technician', 'repair_technician', 'repairtechnician' => 'repair_technician',
                    'salon', 'service_booking' => 'service_booking',
                    'lead', 'leads', 'lead_management' => 'leadmanagement',
                    default => $norm,
                };
                if ($norm !== '') {
                    $normalized[] = $norm;
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Enforce strict domain boundaries across navigation menus.
     * Repair equipment tickets (#SO-...) must only exist in repair/technician modes.
     * Salon, spa, wellness, and general retail must never render repair service orders.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public static function filterDomainMismatches(array $sections, mixed $tenant): array
    {
        $modes = self::resolveTenantModes($tenant);
        $isRepair = false;
        foreach ($modes as $m) {
            if (in_array($m, ['repair', 'repairs', 'repair_technician', 'automotive', 'electronics_service'], true)) {
                $isRepair = true;
                break;
            }
        }

        $isSalon = false;
        foreach ($modes as $m) {
            if (in_array($m, ['salon', 'spa', 'wellness', 'service_booking', 'beauty'], true)) {
                $isSalon = true;
                break;
            }
        }

        $hasModuleWhitelist = $tenant instanceof Company && ! empty($tenant->licensed_modules);
        $isLeadManagement = false;
        foreach ($modes as $m) {
            if (in_array($m, ['leadmanagement', 'lead_management', 'leads'], true)) {
                $isLeadManagement = true;
                break;
            }
        }
        if ($tenant instanceof Company) {
            $isLeadManagement = $tenant->hasModule('leadmanagement');
        } else {
            $isLeadManagement = false;
        }

        $filterItems = function (array $items, string $currentSecKey = '') use (&$filterItems, $isRepair, $isSalon, $isLeadManagement): array {
            $filtered = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $key = strtolower(trim((string) ($item['key'] ?? $item['id'] ?? '')));
                $component = strtolower(trim((string) ($item['component'] ?? '')));
                $target = strtolower(trim((string) ($item['target_endpoint'] ?? '')));
                $title = strtolower(trim((string) ($item['title'] ?? $item['label'] ?? '')));

                // Keep the optional Lead Management extension in its own section.
                if ($currentSecKey !== 'lead_ops' && (
                    $key === 'lead_management'
                    || $key === 'leads'
                    || str_starts_with($key, 'lead_')
                    || $component === 'lead_management'
                    || $component === 'leads'
                    || str_starts_with($component, 'lead_')
                    || $target === '/api/tenant/views/leads'
                    || $target === '/tenant/views/leads'
                    || str_starts_with($target, '/api/tenant/lead-module')
                    || str_contains($key, 'lead')
                    || str_contains($title, 'lead')
                )) {
                    continue;
                }

                // If tenant does not license Lead Management, strip any lead items anywhere
                if (! $isLeadManagement) {
                    if ($key === 'lead_management'
                        || str_starts_with($key, 'lead_')
                        || $component === 'lead_management'
                        || str_starts_with($component, 'lead_')
                        || str_starts_with($target, '/api/tenant/lead-module')
                        || $target === '/api/tenant/views/leads'
                    ) {
                        continue;
                    }
                }

                // Service orders (equipment/warranty repair tickets) are strictly gated to repair workbenches
                if (! $isRepair) {
                    if (in_array($key, ['service_orders', 'new_service_order', 'repair_orders'], true)
                        || in_array($component, ['service_orders'], true)
                        || ($target === '/api/v1/pos/service-orders')
                        || ($title === 'service orders' && ! str_contains($target, 'service-catalog'))
                        || $title === 'new service order') {
                        continue;
                    }
                }

                // In salon/spa modes, strip any repair tickets or equipment intake only if tenant does not license repair
                if ($isSalon && ! $isRepair) {
                    if (in_array($key, ['service_orders', 'repair_tickets', 'repair_create_ticket', 'repair_dashboard', 'repair_my_jobs', 'repair_detail'], true)
                        || in_array($component, ['service_orders', 'repair_tickets', 'repair_create_ticket', 'repair_dashboard'], true)
                        || in_array($title, ['service orders', 'repair ticket register', 'new intake ticket', 'repair workbench'], true)) {
                        continue;
                    }
                }

                if (! empty($item['children']) && is_array($item['children'])) {
                    $item['children'] = $filterItems($item['children'], $currentSecKey);
                }

                $filtered[] = $item;
            }

            return array_values($filtered);
        };

        $result = [];
        $specialized = $isRepair || $isSalon || in_array('pharmacy', $modes, true) || in_array('restaurant', $modes, true);
        if ($specialized) {
            $consignmentItems = [];
            foreach ($sections as $idx => $candidate) {
                $key = strtolower(trim((string) ($candidate['key'] ?? $candidate['id'] ?? '')));
                if ($key !== 'cashier_sales') continue;
                foreach ((array) ($candidate['items'] ?? []) as $item) {
                    if (strtolower((string) ($item['key'] ?? $item['id'] ?? '')) === 'consignments') $consignmentItems[] = $item;
                }
                unset($sections[$idx]);
            }
            if ($consignmentItems) {
                foreach ($sections as &$candidate) {
                    $key = strtolower(trim((string) ($candidate['key'] ?? $candidate['id'] ?? '')));
                    if ($key !== 'administration' && ! empty($candidate['items'])) {
                        $candidate['items'] = array_merge($candidate['items'], $consignmentItems);
                        break;
                    }
                }
                unset($candidate);
            }
            $sections = array_values($sections);
        }
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $secKey = strtolower(trim((string) ($section['key'] ?? $section['id'] ?? '')));
            // Specialized verticals already expose their own checkout and
            // customer/sales entries. Drop legacy fallback sections that used
            // to be emitted as isolated "CUSTOMERS POS" / "SALES POS" blocks.
            if ($specialized
                && in_array($secKey, ['customers_operations', 'customer_operations', 'sales_operations', 'customers_pos', 'sales_pos'], true)) {
                continue;
            }
            if (($isRepair || $isSalon || in_array('pharmacy', $modes, true) || in_array('restaurant', $modes, true))
                && count((array) ($section['items'] ?? [])) === 1
                && str_ends_with(strtolower((string) ($section['title'] ?? $section['label'] ?? '')), ' pos')) {
                continue;
            }
            if ($isSalon && ! $isRepair && in_array($secKey, ['repair_operations', 'repair_service', 'spare_parts_inventory'], true)) {
                continue;
            }
            if (! $isLeadManagement && ($secKey === 'lead_ops' || str_starts_with($secKey, 'lead_'))) {
                continue;
            }
            if (isset($section['items']) && is_array($section['items'])) {
                $section['items'] = $filterItems($section['items'], $secKey);
            }
            if (empty($section['items']) && ! in_array($secKey, ['administration', 'settings'], true)) {
                continue;
            }
            $result[] = $section;
        }

        return array_values($result);
    }

    /**
     * Return guaranteed non-empty navigation sections for a tenant or mode,
     * maintaining a clean modular hierarchy at first load with licensed verticals
     * grouped in strict sequence and Administration anchored at the bottom.
     *
     * @param  Company|string|null  $tenant
     * @param  string|null  $selectedColor
     * @return list<array<string, mixed>>
     */
    public static function getEffectiveNavForTenant(mixed $tenant, ?string $selectedColor = null): array
    {
        if ($selectedColor === null) {
            $tenantId = $tenant instanceof Company ? $tenant->id : (is_numeric($tenant) ? $tenant : null);
            if ($tenantId) {
                $preferences = \App\Models\TenantSetting::get($tenantId, 'app_preferences', []);
                if (is_array($preferences)) {
                    $selectedColor = $preferences['drawer_text_icon_color']
                        ?? $preferences['drawer_text_and_icons']
                        ?? $preferences['drawer_icon_color']
                        ?? $preferences['drawer_text_color']
                        ?? null;
                }
            }
        }

        $labels = [];
        if ($tenant instanceof Company && is_array($tenant->navigation_labels)) {
            $labels = $tenant->navigation_labels;
        } elseif (is_object($tenant) && isset($tenant->navigation_labels) && is_array($tenant->navigation_labels)) {
            $labels = $tenant->navigation_labels;
        }

        // If tenant already rearranged menus via drag-and-drop, serve their custom layout
        if ($tenant instanceof Company) {
            $custom = self::buildCustomNavTree($tenant);
            if (! empty($custom)) {
                $sections = array_values(array_map([self::class, 'normalizeSection'], $custom));
                $sections = self::consolidateCoreSections($sections);
                $sections = self::filterDomainMismatches($sections, $tenant);

                return self::withActionableSectionParents(
                    self::applyNavigationLabels($sections, $labels),
                    $selectedColor
                );
            }
        } elseif (is_object($tenant) && ! empty($tenant->navigation_menu_customization)) {
            $sections = array_values(array_map([self::class, 'normalizeSection'], (array) $tenant->navigation_menu_customization));
            $sections = self::consolidateCoreSections($sections);
            $sections = self::filterDomainMismatches($sections, $tenant);

            return self::withActionableSectionParents(
                self::applyNavigationLabels($sections, $labels),
                $selectedColor
            );
        }

        $sections = self::getBaseNavSectionsForTenant($tenant);
        $sections = self::filterDomainMismatches($sections, $tenant);

        return self::withActionableSectionParents(
            self::applyNavigationLabels($sections, $labels),
            $selectedColor
        );
    }

    /**
     * Return guaranteed non-empty navigation sections for a tenant or mode,
     * maintaining a clean modular hierarchy at first load with licensed verticals
     * grouped in strict sequence and Administration anchored at the bottom.
     *
     * @param  Company|string|null  $tenant
     * @return list<array<string, mixed>>
     */
    public static function getBaseNavSectionsForTenant(mixed $tenant): array
    {
        $sections = [];

        // Determine licensed modules
        //
        // $hasModuleWhitelist marks a tenant that carries an *explicit*
        // licensed_modules list (self-registration stores exactly the vertical
        // picked at signup, e.g. ['pharmacy']). Such a tenant must not be
        // shown the nav for another primary vertical it never chose. Tenants
        // without a whitelist keep the legacy "active package is visible to
        // everyone" behaviour.
        $hasModuleWhitelist = $tenant instanceof Company && ! empty($tenant->licensed_modules);

        if ($tenant instanceof Company) {
            $licensedRaw = $tenant->licensed_modules;
            if (empty($licensedRaw)) {
                $licensedRaw = [$tenant->operating_mode ?? $tenant->pos_mode ?? 'retail'];
            }
        } elseif (is_string($tenant) && trim($tenant) !== '') {
            $licensedRaw = [trim($tenant)];
        } else {
            $licensedRaw = ['retail'];
        }

        $licensed = [];
        foreach ((array) $licensedRaw as $item) {
            if (is_string($item)) {
                $norm = strtolower(trim($item));
                $norm = ModuleRegistry::canonicalKey($norm);
                $norm = match ($norm) {
                    'general', 'general_retail' => 'retail',
                    'food_restaurant' => 'restaurant',
                    'repair', 'repairs', 'technician', 'repair_technician', 'repairtechnician' => 'repair_technician',
                    'salon', 'service_booking' => 'service_booking',
                    'lead', 'leads', 'lead_management' => 'leadmanagement',
                    default => $norm,
                };
                if ($norm !== '') {
                    $licensed[] = $norm;
                }
            }
        }
        if ($tenant instanceof Company && $tenant->restaurant_mode_locked) {
            $licensed = array_values(array_diff($licensed, ['restaurant']));
        }
        $licensed = array_values(array_unique($licensed));
        if (empty($licensed)) {
            $licensed = ['retail'];
        }

        // 1. Core Retail / POS Sections. Specialized verticals provide their
        // own checkout and sales entries, so do not add the generic cashier
        // section alongside pharmacy/restaurant/salon navigation.
        $specializedVertical = (bool) array_intersect($licensed, ['pharmacy', 'restaurant', 'service_booking', 'repair_technician']);
        if (in_array('retail', $licensed, true) && ! $specializedVertical) {
            $sections[] = self::getRetailSalesSection();
            $sections[] = self::getInventorySection();
            $sections[] = self::getFinancialSection();
        }

        // 2. Restaurant Module Section
        if (in_array('restaurant', $licensed, true)) {
            $sections[] = self::normalizeSection([
                'id' => 'restaurant_operations',
                'key' => 'restaurant_operations',
                'title' => 'Restaurant Operations',
                'label' => 'Restaurant Operations',
                'color' => '#4d7c0f',
                'items' => self::getRestaurantMenuItems(),
            ]);
        }

        // 3. Pharmacy Module Section
        if (in_array('pharmacy', $licensed, true)) {
            $sections[] = self::normalizeSection([
                'id' => 'pharmacy_management',
                'key' => 'pharmacy_management',
                'title' => 'PHARMACY OPERATIONS',
                'label' => 'PHARMACY OPERATIONS',
                'color' => '#059669',
                'items' => self::getPharmacyMenuItems(),
            ]);
        }

        // 4. Salon / Service Booking Module Section
        if (in_array('service_booking', $licensed, true)) {
            $sections[] = self::normalizeSection([
                'id' => 'salon_bookings',
                'key' => 'salon_bookings',
                'title' => 'Salon & Bookings',
                'label' => 'Salon & Bookings',
                'color' => '#7c3aed',
                'items' => self::getSalonMenuItems(),
            ]);
        }

        // 5. Repair & Technician Module Section
        if (in_array('repair_technician', $licensed, true)) {
            $sections[] = self::normalizeSection([
                'id' => 'repair_service',
                'key' => 'repair_service',
                'title' => 'REPAIR OPERATIONS & SALES',
                'label' => 'REPAIR OPERATIONS & SALES',
                'color' => '#0284c7',
                'items' => self::getRepairMenuItems(),
            ]);
        }

        // 6. Future Dynamic Modules (Auto-registered via ModuleRegistry)
        foreach ($licensed as $mod) {
            if (! in_array($mod, ['retail', 'restaurant', 'pharmacy', 'service_booking', 'repair_technician'], true)) {
                if (ModuleRegistry::isExtension($mod) && (! $tenant instanceof Company || ! $tenant->hasModule($mod))) {
                    continue;
                }
                $generic = self::buildGenericModuleSection($mod);
                if ($generic !== null) {
                    $sections[] = self::normalizeSection($generic);
                }
            }
        }

        // 7. Active Package Modules (Perfex CRM pattern)
        if (Schema::hasTable('sdui_modules')) {
            try {
                $activePackageModules = SduiModule::query()
                    ->where('is_active', true)
                    ->where('source_type', 'package')
                    ->orderBy('sort_order')
                    ->get();

                foreach ($activePackageModules as $pkgModule) {
                    $pkgSlug = $pkgModule->slug;

                    if ($pkgModule->isExtension() && (! $tenant instanceof Company || ! $tenant->hasModule($pkgSlug))) {
                        continue;
                    }

                    // A package row can be flagged active in the DB while its
                    // files never landed under modules/<key>/ (fresh deploy,
                    // half-finished install, seeded row). Its routes are then
                    // NOT registered by ModuleServiceProvider, so injecting its
                    // navigation only produces "route ... could not be found"
                    // 404s (e.g. /api/tenant/repair-module/views/tickets). Skip
                    // it — the built-in vertical section already covers the
                    // licensed mode with working /api/tenant/views/* endpoints.
                    if (! self::packageIsInstalledOnDisk($pkgModule)) {
                        Log::warning('TenantNavRegistry: skipping nav for active package module without on-disk routes.', [
                            'slug' => $pkgSlug,
                            'package_path' => $pkgModule->package_path,
                        ]);

                        continue;
                    }

                    // The primary business verticals are mutually exclusive — a
                    // store picks one at registration and licensed_modules
                    // records it. An active vertical package must NOT be
                    // injected into a whitelisted tenant that chose a different
                    // one (a pharmacy store must not get salon/repair menus).
                    // Additive third-party add-ons (not a known vertical) still
                    // auto-hydrate for everyone, as do tenants with no explicit
                    // whitelist. Slugs may be pre-alias ("salon",
                    // "repairtechnician"), so match the canonical mode id too.
                    $pkgMode = ModuleRegistry::canonicalKey($pkgSlug);
                    if ($hasModuleWhitelist
                        && ! in_array($pkgMode, $licensed, true)
                        && ! in_array(strtolower(trim((string) $pkgSlug)), $licensed, true)) {
                        continue;
                    }

                    $pkgNavKeys = [];
                    if (is_array($pkgModule->navigation)) {
                        foreach ($pkgModule->navigation as $pnSec) {
                            if (! empty($pnSec['key'])) {
                                $pkgNavKeys[] = $pnSec['key'];
                            }
                        }
                    }

                    $alreadyAdded = false;
                    foreach ($sections as $sec) {
                        $secKey = $sec['key'] ?? $sec['id'] ?? '';
                        if (
                            str_starts_with($secKey, $pkgSlug)
                            || str_starts_with($secKey, $pkgMode)
                            || $secKey === $pkgSlug
                            || $secKey === $pkgMode
                            || in_array($secKey, $pkgNavKeys, true)
                            || ($pkgMode === 'repair_technician' && str_starts_with($secKey, 'repair_'))
                            || ($pkgMode === 'service_booking' && str_starts_with($secKey, 'salon_'))
                        ) {
                            $alreadyAdded = true;
                            break;
                        }
                    }

                    if (! $alreadyAdded) {
                        $nav = $pkgModule->navigation;
                        if (is_array($nav) && ! empty($nav)) {
                            $validated = self::validatedCustomNavigation($nav);
                            if (! empty($validated)) {
                                foreach ($validated as $sec) {
                                    $secKey = trim((string) ($sec['key'] ?? $sec['id'] ?? ''));
                                    if ($secKey !== 'administration') {
                                        $sections[] = self::normalizeSection($sec);
                                    }
                                }

                                continue;
                            }
                        }

                        $generic = self::buildGenericModuleSection($pkgSlug);
                        if ($generic !== null) {
                            $sections[] = self::normalizeSection($generic);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Table might not exist or be accessible in early bootstrapping
            }
        }

        // Single-mode vertical stores (e.g. restaurant-only) without retail still receive
        // inventory and financial management sections.
        if (! in_array('retail', $licensed, true) || $specializedVertical) {
            if (! in_array('products_inventory', array_column($sections, 'key'), true)) {
                $sections[] = self::getInventorySection();
            }
            if (! in_array('financial_management', array_column($sections, 'key'), true)) {
                $sections[] = self::getFinancialSection();
            }
        }

        // 8. Administration & Settings (Strictly at the bottom)
        $sections[] = self::getAdministrationSection();

        return self::filterDomainMismatches(self::consolidateCoreSections(array_values($sections)), $tenant);
    }

    /** Consolidate dynamically registered core modules into the standard drawer groups. */
    private static function consolidateCoreSections(array $sections): array
    {
        $targets = [
            'sales' => 'cashier_sales', 'quotations' => 'cashier_sales', 'consignments' => 'cashier_sales',
            'customers' => 'cashier_sales', 'inventory' => 'products_inventory', 'digital_catalog' => 'products_inventory',
            'finance' => 'financial_management', 'dispatch_omnichannel' => 'cashier_sales',
            'api_integrations' => 'administration', 'api' => 'administration',
        ];
        $index = [];
        foreach ($sections as $i => $section) {
            $index[(string) ($section['key'] ?? '')] = $i;
        }
        foreach ($sections as $i => $section) {
            $key = strtolower((string) ($section['key'] ?? ''));
            $module = preg_replace('/_operations$/', '', $key);
            if (! isset($targets[$module]) || ! isset($index[$targets[$module]]) || $targets[$module] === $key) continue;
            $target = $index[$targets[$module]];
            foreach ((array) ($section['items'] ?? []) as $item) {
                $itemKey = (string) ($item['key'] ?? $item['id'] ?? '');
                if ($itemKey !== '' && collect($sections[$target]['items'] ?? [])->contains(fn ($existing) => (string) ($existing['key'] ?? $existing['id'] ?? '') === $itemKey)) continue;
                $title = (string) ($item['title'] ?? $item['label'] ?? '');
                if ($module === 'api_integrations' || $module === 'api') {
                    $title = 'API & Webhook Integrations';
                }
                $item['title'] = $item['label'] = preg_replace('/\s+POS$/i', '', $title);
                $sections[$target]['items'][] = $item;
            }
            unset($sections[$i]);
        }
        // Consignments is a peer transaction action, never a quotation
        // submenu. Promote it from legacy nested children in saved layouts.
        if (isset($index['cashier_sales'])) {
            $cashier = $index['cashier_sales'];
            $promoted = [];
            $walk = function (array &$items) use (&$walk, &$promoted): void {
                foreach ($items as &$item) {
                    if (strtolower((string) ($item['key'] ?? '')) === 'consignments') {
                        $promoted[] = $item;
                        $item = null;
                        continue;
                    }
                    if (! empty($item['children']) && is_array($item['children'])) {
                        $walk($item['children']);
                        $item['children'] = array_values(array_filter($item['children']));
                        if ($item['children'] === []) unset($item['children']);
                    }
                }
                $items = array_values(array_filter($items));
            };
            $items = $sections[$cashier]['items'] ?? [];
            $walk($items);
            foreach ($promoted as $item) {
                $sections[$cashier]['items'][] = $item;
            }
        }
        return array_values($sections);
    }

    /**
     * Builds and decorates custom navigation tree for tenant if customized.
     *
     * @return list<array<string, mixed>>|null
     */
    public static function buildCustomNavTree(Company $company): ?array
    {
        $raw = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('tenant_settings')) {
            $customSetting = \Illuminate\Support\Facades\DB::table('tenant_settings')
                ->where('tenant_id', $company->id)
                ->where('key', 'navigation_menu_custom')
                ->value('value');
            if (! empty($customSetting)) {
                $decoded = json_decode($customSetting, true);
                if (is_array($decoded) && ! empty($decoded)) {
                    $raw = $decoded;
                }
            }
        }
        if (empty($raw)) {
            $raw = $company->nav_config ?? $company->navigation_menu_customization;
        }
        if (! is_array($raw) || empty($raw)) {
            return null;
        }

        $tree = null;
        if (! empty($raw['custom_tree']) && is_array($raw['custom_tree'])) {
            $tree = $raw['custom_tree'];
        } elseif (! empty($raw['tree']) && is_array($raw['tree'])) {
            $tree = $raw['tree'];
        } elseif (! empty($raw['items']) && is_array($raw['items'])) {
            $normalized = app(TenantNavigationConfigService::class)->normalize($raw);
            $tree = $normalized['tree'] ?? null;
        } elseif (isset($raw[0]['key']) && isset($raw[0]['items'])) {
            $tree = $raw;
        } elseif (! empty($raw['sections']) && is_array($raw['sections'])) {
            $normalized = app(TenantNavigationConfigService::class)->normalize($raw);
            $tree = $normalized['tree'] ?? $raw['sections'];
        }

        if (empty($tree)) {
            return null;
        }

        $baseSections = self::getBaseNavSectionsForTenant($company);
        $sectionMeta = [];
        $catalogItems = [];

        $indexItems = function (array $items, string $sectionKey, ?string $parentId = null) use (&$indexItems, &$catalogItems): void {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $k = trim((string) ($item['key'] ?? $item['id'] ?? ''));
                if ($k === '') {
                    continue;
                }
                if (! isset($catalogItems[$k])) {
                    $catalogItems[$k] = $item;
                }
                if (! empty($item['children']) && is_array($item['children'])) {
                    $indexItems($item['children'], $sectionKey, $k);
                }
            }
        };

        foreach ($baseSections as $sec) {
            $secKey = trim((string) ($sec['key'] ?? $sec['id'] ?? ''));
            if ($secKey !== '') {
                $sectionMeta[$secKey] = $sec;
                $indexItems($sec['items'] ?? [], $secKey);
            }
        }

        $decorateNode = function (array $node, ?string $parentId = null) use (&$decorateNode, $catalogItems): ?array {
            $k = trim((string) ($node['key'] ?? $node['id'] ?? ''));
            if ($k === '') {
                return null;
            }
            if (isset($node['visible']) && $node['visible'] === false) {
                return null;
            }

            $meta = $catalogItems[$k] ?? [
                'key' => $k,
                'id' => $k,
                'title' => ucwords(str_replace('_', ' ', $k)),
                'label' => ucwords(str_replace('_', ' ', $k)),
                'icon' => 'widgets',
                'component' => $k,
                'target_endpoint' => '/api/tenant/views/'.str_replace('_', '-', $k),
                'permission' => null,
            ];

            $decorated = array_merge($meta, $node);
            $decorated['id'] = $k;
            $decorated['key'] = $k;
            $decorated['title'] = $meta['title'] ?? $meta['label'] ?? $k;
            $decorated['label'] = $meta['label'] ?? $meta['title'] ?? $k;
            $decorated['icon'] = $meta['icon'] ?? 'widgets';
            $decorated['component'] = $meta['component'] ?? $k;
            $decorated['target_endpoint'] = $meta['target_endpoint'] ?? ('/api/tenant/views/'.str_replace('_', '-', $k));
            $decorated['permission'] = $meta['permission'] ?? null;

            // Store Settings must always be an independent root item (never nested under subscription)
            if ($k === 'settings') {
                $parentId = null;
            }

            if ($parentId !== null && $k !== 'settings') {
                $decorated['parent'] = $parentId;
                $decorated['parent_id'] = $parentId;
                $decorated['type'] = 'link';
            } else {
                $decorated['parent'] = null;
                $decorated['parent_id'] = null;
            }

            $children = [];
            foreach ($node['children'] ?? [] as $child) {
                if (is_array($child)) {
                    $childKey = trim((string) ($child['key'] ?? $child['id'] ?? ''));
                    if ($childKey === 'settings') {
                        continue;
                    }
                    $decChild = $decorateNode($child, $k);
                    if ($decChild !== null) {
                        $children[] = $decChild;
                    }
                }
            }

            $decorated['children'] = $children;
            if (! empty($children)) {
                $decorated['type'] = 'accordion';
                $decorated = array_merge($decorated, self::collapsedFlags());
            } else {
                $decorated['type'] = 'link';
            }

            return $decorated;
        };

        $customSections = [];
        foreach ($tree as $treeSection) {
            if (! is_array($treeSection)) {
                continue;
            }
            $secKey = trim((string) ($treeSection['key'] ?? $treeSection['id'] ?? ''));
            if ($secKey === '') {
                continue;
            }
            $meta = $sectionMeta[$secKey] ?? [
                'id' => $secKey,
                'key' => $secKey,
                'title' => ucwords(str_replace('_', ' ', $secKey)),
                'label' => ucwords(str_replace('_', ' ', $secKey)),
                'color' => '#475569',
            ];

            $decoratedItems = [];
            $extractedRoots = [];

            foreach ($treeSection['items'] ?? [] as $itemNode) {
                if (is_array($itemNode)) {
                    $extractSettings = function (array &$node) use (&$extractSettings, &$extractedRoots): void {
                        if (! empty($node['children']) && is_array($node['children'])) {
                            $cleanChildren = [];
                            foreach ($node['children'] as $child) {
                                if (is_array($child)) {
                                    $ck = trim((string) ($child['key'] ?? $child['id'] ?? ''));
                                    if ($ck === 'settings') {
                                        $extractedRoots[] = $child;
                                    } else {
                                        $cleanChildren[] = $child;
                                        $extractSettings($child);
                                    }
                                }
                            }
                            $node['children'] = $cleanChildren;
                        }
                    };
                    $copyNode = $itemNode;
                    $extractSettings($copyNode);
                    $dec = $decorateNode($copyNode, null);
                    if ($dec !== null) {
                        $decoratedItems[] = $dec;
                    }
                }
            }

            foreach ($extractedRoots as $ext) {
                $decExt = $decorateNode($ext, null);
                if ($decExt !== null) {
                    $decoratedItems[] = $decExt;
                }
            }

            if ($secKey === 'cashier_sales') {
                $decoratedItems = array_values(array_filter($decoratedItems, static function (array $item): bool {
                    $itemKey = strtolower((string) ($item['key'] ?? ''));

                    return $itemKey !== 'lead_management'
                        && $itemKey !== 'leads'
                        && ! str_starts_with($itemKey, 'lead_')
                        && ! str_contains($itemKey, 'lead');
                }));

                $existingKeys = [];
                $collectKeys = function (array $items) use (&$collectKeys, &$existingKeys): void {
                    foreach ($items as $item) {
                        if (! is_array($item)) {
                            continue;
                        }
                        $existingKeys[] = $item['key'] ?? null;
                        $collectKeys(is_array($item['children'] ?? null) ? $item['children'] : []);
                    }
                };
                $collectKeys($decoratedItems);
                foreach (['pos', 'sales', 'quotations', 'consignments', 'customers'] as $coreKey) {
                    if (! in_array($coreKey, $existingKeys, true) && isset($catalogItems[$coreKey])) {
                        $decoratedItems[] = $catalogItems[$coreKey];
                    }
                }
            }

            if (! empty($decoratedItems)) {
                $customTitle = trim((string) ($treeSection['custom_title'] ?? ''));
                if ($customTitle === '') {
                    $customTitle = trim((string) ($decoratedItems[0]['title'] ?? $decoratedItems[0]['label'] ?? ''));
                }
                $customSections[] = array_merge($meta, [
                    'id' => $secKey,
                    'key' => $secKey,
                    'custom_title' => $customTitle,
                    'items' => $decoratedItems,
                ]);
            }
        }

        // Reconcile missing licensed sections: if any section from getBaseNavSectionsForTenant
        // is missing from customSections (e.g. newly enabled/licensed modules), inject them before administration.
        $existingKeys = array_column($customSections, 'key');
        $missingSections = [];
        foreach ($baseSections as $bSec) {
            $bKey = trim((string) ($bSec['key'] ?? $bSec['id'] ?? ''));
            if ($bKey !== '' && $bKey !== 'administration' && ! in_array($bKey, $existingKeys, true)) {
                $missingSections[] = $bSec;
                $existingKeys[] = $bKey;
            }
        }

        if (! empty($missingSections)) {
            $adminIndex = null;
            foreach ($customSections as $idx => $cs) {
                if (($cs['key'] ?? $cs['id'] ?? '') === 'administration') {
                    $adminIndex = $idx;
                    break;
                }
            }
            if ($adminIndex !== null) {
                array_splice($customSections, $adminIndex, 0, $missingSections);
            } else {
                $customSections = array_merge($customSections, $missingSections);
            }
        }

        // Anchor administration if not present in custom sections
        if (! in_array('administration', array_column($customSections, 'key'), true)) {
            $customSections[] = self::getAdministrationSection();
        }

        // Reconcile: mandatory Administration rows shipped after this tenant
        // last saved their custom layout (e.g. "Printer & Hardware Setup")
        // would otherwise stay invisible forever. Re-add any base
        // administration leaf row whose key never appears anywhere in the
        // saved tree — unless the tenant explicitly hid it — just before
        // "Change Password".
        $seenKeys = [];
        $hiddenKeys = [];
        $walk = function (array $items, bool $fromRawTree) use (&$walk, &$seenKeys, &$hiddenKeys): void {
            foreach ($items as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $k = trim((string) ($node['key'] ?? $node['id'] ?? ''));
                if ($k !== '') {
                    $seenKeys[$k] = true;
                    if ($fromRawTree && ($node['visible'] ?? true) === false) {
                        $hiddenKeys[$k] = true;
                    }
                }
                if (! empty($node['children']) && is_array($node['children'])) {
                    $walk($node['children'], $fromRawTree);
                }
            }
        };
        foreach ($customSections as $cs) {
            $walk($cs['items'] ?? [], false);
        }
        foreach ($tree as $ts) {
            if (is_array($ts)) {
                $walk($ts['items'] ?? [], true);
            }
        }

        foreach ($customSections as $i => $cs) {
            if (($cs['key'] ?? null) !== 'administration') {
                continue;
            }
            $baseAdmin = self::getAdministrationSection();
            $missing = [];
            foreach ($baseAdmin['items'] ?? [] as $baseItem) {
                if (! is_array($baseItem) || ! empty($baseItem['children'])) {
                    continue;
                }
                $k = trim((string) ($baseItem['key'] ?? ''));
                if ($k === '' || isset($seenKeys[$k]) || isset($hiddenKeys[$k])) {
                    continue;
                }
                $missing[] = $baseItem;
            }
            if ($missing === []) {
                break;
            }
            $items = $cs['items'];
            $cpPos = null;
            foreach ($items as $p => $it) {
                if (($it['key'] ?? null) === 'change_password') {
                    $cpPos = $p;
                    break;
                }
            }
            if ($cpPos !== null) {
                array_splice($items, $cpPos, 0, $missing);
            } else {
                $items = array_merge($items, $missing);
            }
            $customSections[$i]['items'] = $items;
            break;
        }

        return empty($customSections) ? null : array_values($customSections);
    }

    /**
     * Cashier & Sales section for core retail operations.
     *
     * @return array<string, mixed>
     */
    public static function getRetailSalesSection(): array
    {
        return self::normalizeSection([
            'id' => 'cashier_sales',
            'key' => 'cashier_sales',
            'label' => 'Cashier & Sales',
            'title' => 'Cashier & Sales',
            'color' => '#1d4ed8',
            'items' => [
                ['key' => 'pos', 'label' => 'Point of Sale', 'title' => 'Point of Sale', 'icon' => 'point_of_sale', 'component' => 'pos', 'permission' => 'pos', 'target_endpoint' => '/api/tenant/views/pos'],
                ['key' => 'sales', 'label' => 'Sales & Invoices', 'title' => 'Sales & Invoices', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales', 'target_endpoint' => '/api/tenant/views/sales'],
                ['key' => 'quotations', 'label' => 'Quotations & Proposals', 'title' => 'Quotations & Proposals', 'icon' => 'description', 'component' => 'quotations', 'permission' => 'quotes', 'target_endpoint' => '/api/tenant/views/quotations'],
                ['key' => 'consignments', 'label' => 'Consignments', 'title' => 'Consignments', 'icon' => 'local_shipping', 'component' => 'consignments', 'permission' => 'consignments', 'target_endpoint' => '/api/tenant/views/consignments'],
                ['key' => 'customers', 'label' => 'Customers & CRM', 'title' => 'Customers & CRM', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers', 'target_endpoint' => '/api/tenant/views/customers'],
            ],
        ]);
    }

    /**
     * Products & Inventory section for catalog management.
     *
     * @return array<string, mixed>
     */
    public static function getInventorySection(): array
    {
        return self::normalizeSection([
            'id' => 'products_inventory',
            'key' => 'products_inventory',
            'label' => 'Products & Inventory',
            'title' => 'Products & Inventory',
            'color' => '#b45309',
            'items' => [
                ['key' => 'inventory', 'label' => 'Product Catalog', 'title' => 'Product Catalog', 'icon' => 'inventory_2', 'component' => 'inventory', 'permission' => 'products', 'target_endpoint' => '/api/tenant/views/inventory'],
                ['key' => 'categories', 'label' => 'Categories', 'title' => 'Categories', 'icon' => 'sell', 'component' => 'categories', 'permission' => 'categories', 'target_endpoint' => '/api/tenant/views/categories'],
                ['key' => 'brands', 'label' => 'Brands & Manufacturers', 'title' => 'Brands & Manufacturers', 'icon' => 'auto_awesome', 'component' => 'brands', 'permission' => 'categories', 'target_endpoint' => '/api/tenant/views/brands'],
                ['key' => 'units', 'label' => 'Units of Measure', 'title' => 'Units of Measure', 'icon' => 'straighten', 'component' => 'units', 'permission' => 'units', 'target_endpoint' => '/api/tenant/views/units'],
                ['key' => 'suppliers', 'label' => 'Suppliers & Vendors', 'title' => 'Suppliers & Vendors', 'icon' => 'local_shipping', 'component' => 'suppliers', 'permission' => 'suppliers', 'target_endpoint' => '/api/tenant/views/suppliers'],
                ['key' => 'taxes', 'label' => 'Taxes & Compliance', 'title' => 'Taxes & Compliance', 'icon' => 'percent', 'component' => 'taxes', 'permission' => 'settings', 'target_endpoint' => '/api/tenant/views/settings-taxes'],
                ['key' => 'catalog', 'label' => 'Online Digital Catalog', 'title' => 'Online Digital Catalog', 'icon' => 'qr_code', 'component' => 'catalog', 'permission' => 'catalog', 'target_endpoint' => '/api/tenant/views/catalog'],
            ],
        ]);
    }

    /**
     * Financial Management section.
     *
     * @return array<string, mixed>
     */
    public static function getFinancialSection(): array
    {
        return self::normalizeSection([
            'id' => 'financial_management',
            'key' => 'financial_management',
            'label' => 'Finance & Targets',
            'title' => 'Finance & Targets',
            'color' => '#0f766e',
            'items' => [
                ['key' => 'cash_register', 'label' => 'Cash Register', 'title' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register', 'target_endpoint' => '/api/tenant/views/cash-register'],
                ['key' => 'due_receivables', 'label' => 'Accounts Receivable', 'title' => 'Accounts Receivable', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance', 'target_endpoint' => '/api/tenant/views/due-receivables'],
                ['key' => 'payables', 'label' => 'Accounts Payable', 'title' => 'Accounts Payable', 'icon' => 'request_quote', 'component' => 'payables', 'permission' => 'finance', 'target_endpoint' => '/api/tenant/views/payables'],
                ['key' => 'sales_targets', 'label' => 'Sales Targets', 'title' => 'Sales Targets', 'icon' => 'flag', 'component' => 'sales_targets', 'permission' => 'targets', 'target_endpoint' => '/api/tenant/views/sales-targets'],
                ['key' => 'reports', 'label' => 'Reports', 'title' => 'Reports', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports', 'target_endpoint' => '/api/tenant/views/reports'],
                ['key' => 'analytics', 'label' => 'Analytics', 'title' => 'Analytics', 'icon' => 'bar_chart', 'component' => 'analytics', 'permission' => 'reports', 'target_endpoint' => '/api/tenant/views/analytics'],
            ],
        ]);
    }

    /**
     * Sub-menu items for Restaurant Operations.
     *
     * @return list<array<string, mixed>>
     */
    public static function getRestaurantMenuItems(): array
    {
        return [
            [
                'key' => 'restaurant_pos',
                'label' => 'Restaurant POS Terminal',
                'title' => 'Restaurant POS Terminal',
                'icon' => 'restaurant',
                'component' => 'restaurant_pos',
                'permission' => 'pos',
                'target_endpoint' => '/api/tenant/views/restaurant-pos',
            ],
            [
                'key' => 'dining_history',
                'label' => 'KOT Register & Live Orders',
                'title' => 'KOT Register & Live Orders',
                'icon' => 'receipt_long',
                'component' => 'dining_history',
                'permission' => 'sales',
                'target_endpoint' => '/api/tenant/views/dining-history',
            ],
            [
                'key' => 'sales',
                'label' => 'Sales & Invoices History',
                'title' => 'Sales & Invoices History',
                'icon' => 'receipt',
                'component' => 'sales',
                'permission' => 'sales',
                'target_endpoint' => '/api/tenant/views/sales',
            ],
            [
                'key' => 'quotations',
                'label' => 'Quotations & Party Orders',
                'title' => 'Quotations & Party Orders',
                'icon' => 'description',
                'component' => 'quotations',
                'permission' => 'quotes',
                'target_endpoint' => '/api/tenant/views/quotations',
            ],
            [
                'key' => 'customers',
                'label' => 'Customers & CRM',
                'title' => 'Customers & CRM',
                'icon' => 'people',
                'component' => 'customers',
                'permission' => 'customers',
                'target_endpoint' => '/api/tenant/views/customers',
            ],
            [
                'key' => 'cash_register',
                'label' => 'Cash Register',
                'title' => 'Cash Register',
                'icon' => 'savings',
                'component' => 'cash_register',
                'permission' => 'cash_register',
                'target_endpoint' => '/api/tenant/views/cash-register',
            ],
            [
                'key' => 'floor_plan',
                'label' => 'Dining Tables & Floor Plan',
                'title' => 'Dining Tables & Floor Plan',
                'icon' => 'table_restaurant',
                'component' => 'floor_plan',
                'permission' => 'pos',
                'target_endpoint' => '/api/tenant/views/restaurant-tables',
            ],
            [
                'key' => 'kitchen_display',
                'label' => 'Kitchen Display (KDS)',
                'title' => 'Kitchen Display (KDS)',
                'icon' => 'soup_kitchen',
                'component' => 'kitchen_display',
                'permission' => 'pos',
                'target_endpoint' => '/api/tenant/views/restaurant-kds',
            ],
        ];
    }

    /**
     * Sub-menu items for Pharmacy Management.
     *
     * @return list<array<string, mixed>>
     */
    public static function getPharmacyMenuItems(): array
    {
        return [
            [
                'id' => 'pharmacy_pos',
                'key' => 'pharmacy_pos',
                'title' => 'Pharmacy POS & Checkout',
                'label' => 'Pharmacy POS & Checkout',
                'icon' => 'point_of_sale',
                'component' => 'pos',
                'permission' => 'pos',
                // Opens the core native POS resolver directly — NOT the retired
                // "Pharmacy Counter POS" SDUI screen at
                // /api/tenant/views/pharmacy-pos.
                'route' => 'pos',
                'target_endpoint' => 'pos',
            ],
            [
                'id' => 'new_prescription_intake',
                'key' => 'new_prescription_intake',
                'title' => 'New Prescription Intake',
                'label' => 'New Prescription Intake',
                'icon' => 'note_add',
                'component' => 'new_prescription_intake',
                'permission' => 'sales',
                'route' => '/api/tenant/views/pharmacy-rx-create',
                'target_endpoint' => '/api/tenant/views/pharmacy-rx-create',
            ],
            [
                'id' => 'prescriptions_queue',
                'key' => 'prescriptions_queue',
                'title' => 'Prescriptions & Patient Queue',
                'label' => 'Prescriptions & Patient Queue',
                'icon' => 'medical_information',
                'component' => 'pharmacy_prescriptions',
                'permission' => 'sales',
                'route' => '/api/tenant/views/pharmacy-prescriptions',
                'target_endpoint' => '/api/tenant/views/pharmacy-prescriptions',
            ],
            [
                'id' => 'batch_inventory',
                'key' => 'batch_inventory',
                'title' => 'Drug Batches & Expiry Tracker',
                'label' => 'Drug Batches & Expiry Tracker',
                'icon' => 'inventory_2',
                'component' => 'pharmacy_batches',
                'permission' => 'products',
                'route' => '/api/tenant/views/pharmacy-batches',
                'target_endpoint' => '/api/tenant/views/pharmacy-batches',
            ],
            [
                'id' => 'sales',
                'key' => 'sales',
                'title' => 'Sales & Invoices History',
                'label' => 'Sales & Invoices History',
                'icon' => 'receipt_long',
                'component' => 'sales',
                'permission' => 'sales',
                'route' => '/api/tenant/views/sales',
                'target_endpoint' => '/api/tenant/views/sales',
            ],
            [
                'id' => 'quotations',
                'key' => 'quotations',
                'title' => 'Quotations & Estimates',
                'label' => 'Quotations & Estimates',
                'icon' => 'description',
                'component' => 'quotations',
                'permission' => 'quotes',
                'route' => '/api/tenant/views/quotations',
                'target_endpoint' => '/api/tenant/views/quotations',
            ],
            [
                'id' => 'customers',
                'key' => 'customers',
                'title' => 'Patients & Doctors',
                'label' => 'Patients & Doctors',
                'icon' => 'people',
                'component' => 'customers',
                'permission' => 'customers',
                'route' => '/api/tenant/views/customers',
                'target_endpoint' => '/api/tenant/views/customers',
            ],
            [
                'id' => 'cash_register',
                'key' => 'cash_register',
                'title' => 'Cash Register',
                'label' => 'Cash Register',
                'icon' => 'savings',
                'component' => 'cash_register',
                'permission' => 'cash_register',
                'route' => '/api/tenant/views/cash-register',
                'target_endpoint' => '/api/tenant/views/cash-register',
            ],
        ];
    }

    /**
     * Sub-menu items for Repair & Technician Operations.
     *
     * @return list<array<string, mixed>>
     */
    public static function getRepairMenuItems(): array
    {
        return [
            [
                'key' => 'pos',
                'label' => 'Point of Sale',
                'title' => 'Point of Sale',
                'icon' => 'point_of_sale',
                'component' => 'pos',
                'permission' => 'pos',
                'target_endpoint' => '/api/tenant/views/pos',
            ],
            [
                'key' => 'repair_dashboard',
                'label' => 'Repair Workbench',
                'title' => 'Repair Workbench',
                'icon' => 'handyman',
                'component' => 'repair_dashboard',
                'permission' => 'repair',
                'target_endpoint' => '/api/tenant/views/repair-dashboard',
            ],
            [
                'key' => 'repair_create_ticket',
                'label' => 'New Intake Ticket',
                'title' => 'New Intake Ticket',
                'icon' => 'add_task',
                'component' => 'repair_create_ticket',
                'permission' => 'repair',
                'target_endpoint' => '/api/tenant/views/repair-create-ticket',
            ],
            [
                'key' => 'repair_tickets',
                'label' => 'Repair Ticket Register',
                'title' => 'Repair Ticket Register',
                'icon' => 'receipt_long',
                'component' => 'repair_tickets',
                'permission' => 'repair',
                'target_endpoint' => '/api/tenant/views/repair-tickets',
            ],
            [
                'key' => 'sales',
                'label' => 'Sales & Invoices',
                'title' => 'Sales & Invoices',
                'icon' => 'receipt_long',
                'component' => 'sales',
                'permission' => 'sales',
                'target_endpoint' => '/api/tenant/views/sales',
            ],
            [
                'key' => 'quotations',
                'label' => 'Quotations & Proposals',
                'title' => 'Quotations & Proposals',
                'icon' => 'description',
                'component' => 'quotations',
                'permission' => 'quotes',
                'target_endpoint' => '/api/tenant/views/quotations',
            ],
            [
                'key' => 'customers',
                'label' => 'Customers & CRM',
                'title' => 'Customers & CRM',
                'icon' => 'people',
                'component' => 'customers',
                'permission' => 'customers',
                'target_endpoint' => '/api/tenant/views/customers',
            ],
        ];
    }

    /**
     * Sub-menu items for Salon & Bookings.
     *
     * @return list<array<string, mixed>>
     */
    public static function getSalonMenuItems(): array
    {
        return [
            [
                'id' => 'salon_pos',
                'key' => 'salon_pos',
                'title' => 'Salon POS & Checkout',
                'label' => 'Salon POS & Checkout',
                'icon' => 'point_of_sale',
                'component' => 'pos',
                'permission' => 'pos',
                'route' => '/api/tenant/views/salon-pos',
                'target_endpoint' => '/api/tenant/views/salon-pos',
            ],
            [
                'id' => 'book_appointment',
                'key' => 'book_appointment',
                'title' => 'Book Service / Appointment',
                'label' => 'Book Service / Appointment',
                'icon' => 'edit_calendar',
                'component' => 'book_appointment',
                'permission' => 'service_orders',
                'route' => '/api/tenant/views/salon-booking-create',
                'target_endpoint' => '/api/tenant/views/salon-booking-create',
            ],
            [
                'id' => 'booking_calendar',
                'key' => 'booking_calendar',
                'title' => 'Service Booking Calendar',
                'label' => 'Service Booking Calendar',
                'icon' => 'calendar_month',
                'component' => 'service_calendar',
                'permission' => 'service_orders',
                'route' => '/api/tenant/views/salon-calendar',
                'target_endpoint' => '/api/tenant/views/salon-calendar',
            ],
            [
                'id' => 'service_catalog',
                'key' => 'service_catalog',
                'title' => 'Service Catalog & Rates',
                'label' => 'Service Catalog & Rates',
                'icon' => 'format_list_bulleted',
                'component' => 'service_catalog',
                'permission' => 'service_orders',
                'route' => '/api/tenant/views/service-catalog',
                'target_endpoint' => '/api/tenant/views/service-catalog',
            ],
            [
                'id' => 'add_new_service',
                'key' => 'add_new_service',
                'title' => 'Add New Service',
                'label' => 'Add New Service',
                'icon' => 'add_circle_outline',
                'component' => 'service_create',
                'permission' => 'service_orders',
                'route' => '/api/tenant/views/service-create',
                'target_endpoint' => '/api/tenant/views/service-create',
            ],
            [
                'id' => 'service_stylists',
                'key' => 'service_stylists',
                'title' => 'Stylists & Staff Assignments',
                'label' => 'Stylists & Staff Assignments',
                'icon' => 'badge',
                'component' => 'staff',
                'permission' => 'users',
                'route' => '/api/tenant/views/service-stylists',
                'target_endpoint' => '/api/tenant/views/service-stylists',
            ],
            [
                'id' => 'sales',
                'key' => 'sales',
                'title' => 'Sales & Invoices History',
                'label' => 'Sales & Invoices History',
                'icon' => 'receipt_long',
                'component' => 'sales',
                'permission' => 'sales',
                'route' => '/api/tenant/views/sales',
                'target_endpoint' => '/api/tenant/views/sales',
            ],
            [
                'id' => 'quotations',
                'key' => 'quotations',
                'title' => 'Quotations & Estimates',
                'label' => 'Quotations & Estimates',
                'icon' => 'description',
                'component' => 'quotations',
                'permission' => 'quotes',
                'route' => '/api/tenant/views/quotations',
                'target_endpoint' => '/api/tenant/views/quotations',
            ],
            [
                'id' => 'customers',
                'key' => 'customers',
                'title' => 'Clients & CRM',
                'label' => 'Clients & CRM',
                'icon' => 'people',
                'component' => 'customers',
                'permission' => 'customers',
                'route' => '/api/tenant/views/customers',
                'target_endpoint' => '/api/tenant/views/customers',
            ],
            [
                'id' => 'cash_register',
                'key' => 'cash_register',
                'title' => 'Cash Register',
                'label' => 'Cash Register',
                'icon' => 'savings',
                'component' => 'cash_register',
                'permission' => 'cash_register',
                'route' => '/api/tenant/views/cash-register',
                'target_endpoint' => '/api/tenant/views/cash-register',
            ],
        ];
    }

    /**
     * Builds generic standalone section for dynamic server modules.
     *
     * @return array<string, mixed>|null
     */
    public static function buildGenericModuleSection(string $mod): ?array
    {
        $normalizedMod = strtolower(trim($mod));
        $dbModule = ModuleRegistry::find($normalizedMod);
        $navigation = $dbModule['navigation'] ?? null;
        if (is_array($navigation) && $navigation !== []) {
            $validated = self::validatedCustomNavigation($navigation);
            if (! empty($validated)) {
                foreach ($validated as $sec) {
                    $secKey = trim((string) ($sec['key'] ?? $sec['id'] ?? ''));
                    if ($secKey !== 'administration') {
                        return self::normalizeSection($sec);
                    }
                }
            }
        }

        $title = ucwords(str_replace(['_', '-'], ' ', $normalizedMod));

        return self::normalizeSection([
            'id' => $normalizedMod.'_operations',
            'key' => $normalizedMod.'_operations',
            'label' => $title.' Operations',
            'title' => $title.' Operations',
            'color' => '#6366f1',
            'items' => [
                [
                    'key' => $normalizedMod.'_pos',
                    'label' => $title.' POS',
                    'title' => $title.' POS',
                    'icon' => 'widgets',
                    'component' => 'pos',
                    'permission' => 'pos',
                    'target_endpoint' => '/api/tenant/views/'.$normalizedMod.'-pos',
                ],
            ],
        ]);
    }

    /**
     * Return enriched navigation section for an individual licensed module.
     *
     * @return array<string, mixed>|null
     */
    public static function menuStructureForModule(string $module): ?array
    {
        $mod = strtolower(trim($module));
        $mod = match ($mod) {
            'general', 'general_retail' => 'retail',
            'food_restaurant' => 'restaurant',
            default => $mod,
        };

        return match ($mod) {
            'restaurant' => self::normalizeSection([
                'id' => 'restaurant_operations',
                'key' => 'restaurant_operations',
                'title' => 'Restaurant Operations',
                'label' => 'Restaurant Operations',
                'color' => '#4d7c0f',
                'items' => self::getRestaurantMenuItems(),
            ]),
            'pharmacy' => self::normalizeSection([
                'id' => 'pharmacy_management',
                'key' => 'pharmacy_management',
                'title' => 'PHARMACY OPERATIONS',
                'label' => 'PHARMACY OPERATIONS',
                'color' => '#059669',
                'items' => self::getPharmacyMenuItems(),
            ]),
            'service_booking' => self::normalizeSection([
                'id' => 'salon_bookings',
                'key' => 'salon_bookings',
                'title' => 'Salon & Bookings',
                'label' => 'Salon & Bookings',
                'color' => '#7c3aed',
                'items' => self::getSalonMenuItems(),
            ]),
            'repair_technician' => self::normalizeSection([
                'id' => 'repair_service',
                'key' => 'repair_service',
                'title' => 'REPAIR OPERATIONS & SALES',
                'label' => 'REPAIR OPERATIONS & SALES',
                'color' => '#0284c7',
                'items' => self::getRepairMenuItems(),
            ]),
            'retail' => self::getRetailSalesSection(),
            default => self::buildGenericModuleSection($mod),
        };
    }

    /**
     * Return normalized common administration section.
     *
     * @return array<string, mixed>
     */
    public static function getAdministrationSection(): array
    {
        return self::normalizeSection(self::administrationSection());
    }

    /**
     * @param  bool|string  $isRestaurantOrMode
     * @param  string|null  $selectedColor
     * @return list<array<string, mixed>>
     */
    public static function sectionsFor(bool|string $isRestaurantOrMode, ?string $selectedColor = null): array
    {
        if (is_bool($isRestaurantOrMode)) {
            $raw = $isRestaurantOrMode ? self::restaurantSections() : self::retailSections();

            return self::withActionableSectionParents(
                array_values(array_map([self::class, 'normalizeSection'], $raw)),
                $selectedColor
            );
        }

        $mode = strtolower(trim((string) $isRestaurantOrMode));
        $mode = match ($mode) {
            'general', 'general_retail' => 'retail',
            'food_restaurant' => 'restaurant',
            'repair', 'repairs', 'technician', 'repair_technician' => 'repair_technician',
            default => $mode,
        };
        $fallback = match ($mode) {
            'restaurant' => self::restaurantSections(),
            'pharmacy' => self::pharmacySections(),
            'service_booking' => self::serviceBookingSections(),
            'repair_technician' => self::repairTechnicianSections(),
            default => self::retailSections(),
        };

        $sections = $fallback;
        $module = ModuleRegistry::find($mode);
        $navigation = $module['navigation'] ?? null;
        if (is_array($navigation) && $navigation !== []) {
            $validated = self::validatedCustomNavigation($navigation);
            if ($validated !== null && $validated !== []) {
                $sections = $validated;
            } else {
                Log::warning('Invalid database SDUI navigation; using core menu fallback.', [
                    'mode' => $mode,
                ]);
            }
        } elseif (($module['source'] ?? null) === 'database') {
            Log::warning('Empty database SDUI navigation; using core menu fallback.', [
                'mode' => $mode,
            ]);
        }

        if (empty($sections)) {
            $sections = self::retailSections();
        }

        $normalized = array_values(array_map([self::class, 'normalizeSection'], $sections));

        return self::withActionableSectionParents(
            self::filterDomainMismatches($normalized, $isRestaurantOrMode),
            $selectedColor
        );
    }

    /**
     * Return enriched menu structure for a given mode.
     *
     * @param  string  $mode
     * @param  string|null  $selectedColor
     * @return list<array<string, mixed>>
     */
    public static function menuStructureForMode(string $mode, ?string $selectedColor = null): array
    {
        return self::getEffectiveNavForTenant($mode, $selectedColor);
    }

    /**
     * Add the cross-client section-parent contract after permissions, tenant
     * ordering, domain filtering, and custom labels have all been resolved.
     * The legacy `items` list remains intact for older clients.
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  string|null  $selectedColor
     * @return list<array<string, mixed>>
     */
    public static function withActionableSectionParents(array $sections, ?string $selectedColor = null): array
    {
        $textColor = $selectedColor ?: '#F97316';

        return array_values(array_map(function (array $section) use ($textColor, $selectedColor): array {
            $items = array_values(array_filter(
                $section['items'] ?? [],
                static fn ($item): bool => is_array($item)
            ));

            $asActionableItem = static function (array $item) use ($selectedColor): array {
                $route = trim((string) ($item['route'] ?? $item['target_endpoint'] ?? $item['endpoint'] ?? ''));
                $iconName = (string) ($item['icon'] ?? 'widgets');

                if ($selectedColor !== null && $selectedColor !== '') {
                    $item['icon_color'] = $selectedColor;
                    $item['leading'] = [
                        'type' => 'icon',
                        'name' => $iconName,
                        'color' => $selectedColor,
                    ];
                    $item['style'] = array_merge((array) ($item['style'] ?? []), [
                        'textColor' => $selectedColor,
                        'iconColor' => $selectedColor,
                    ]);
                }

                if (isset($item['children']) && is_array($item['children'])) {
                    $formattedChildren = [];
                    foreach ($item['children'] as $child) {
                        if (is_array($child)) {
                            $childIcon = (string) ($child['icon'] ?? 'widgets');
                            if ($selectedColor !== null && $selectedColor !== '') {
                                $child['icon_color'] = $selectedColor;
                                $child['leading'] = [
                                    'type' => 'icon',
                                    'name' => $childIcon,
                                    'color' => $selectedColor,
                                ];
                                $child['style'] = array_merge((array) ($child['style'] ?? []), [
                                    'textColor' => $selectedColor,
                                    'iconColor' => $selectedColor,
                                ]);
                            }
                            $formattedChildren[] = $child;
                        }
                    }
                    $item['children'] = $formattedChildren;
                }

                if ($route === '') {
                    return $item;
                }

                return array_merge($item, [
                    'type' => 'list_tile',
                    'route' => $route,
                    'target_endpoint' => $route,
                    'action_type' => 'NAVIGATE_TO',
                    'action' => [
                        'type' => 'NAVIGATE_TO',
                        'action_type' => 'NAVIGATE_TO',
                        'route' => $route,
                        'endpoint' => $route,
                    ],
                ]);
            };

            $firstItem = isset($items[0]) ? $asActionableItem($items[0]) : null;
            if ($firstItem !== null) {
                $firstItem['type'] = 'list_tile';
                $firstItem['style'] = array_merge([
                    'fontWeight' => 'bold',
                    'textColor' => $textColor,
                ], (array) ($firstItem['style'] ?? []));
                $firstItem['style']['textColor'] = $textColor;
                if ($selectedColor !== null && $selectedColor !== '') {
                    $firstItem['style']['iconColor'] = $selectedColor;
                    $firstItem['icon_color'] = $selectedColor;
                    $firstItem['leading'] = [
                        'type' => 'icon',
                        'name' => (string) ($firstItem['icon'] ?? 'widgets'),
                        'color' => $selectedColor,
                    ];
                }
            }

            $subItems = array_map($asActionableItem, array_slice($items, 1));
            $firstRoute = $firstItem ? ($firstItem['route'] ?? $firstItem['target_endpoint'] ?? '') : '';
            $firstIcon = $firstItem['icon'] ?? ($section['icon'] ?? 'folder');

            $decoratedSection = array_merge($section, [
                'type' => 'list_tile',
                'action_type' => 'NAVIGATE_TO',
                'route' => $firstRoute,
                'target_endpoint' => $firstRoute,
                'icon' => $firstIcon,
                'style' => [
                    'fontWeight' => 'bold',
                    'textColor' => $textColor,
                ],
                'action' => [
                    'type' => 'NAVIGATE_TO',
                    'action_type' => 'NAVIGATE_TO',
                    'route' => $firstRoute,
                    'endpoint' => $firstRoute,
                ],
                'divider' => ['type' => 'divider'],
                'top_divider' => ['type' => 'divider'],
                'first_item' => $firstItem,
                'sub_items' => array_values($subItems),
                'show_top_divider' => true,
                'divider_style' => [
                    'color' => 'theme.divider',
                    'alpha' => 0.12,
                    'thickness' => 1,
                    'horizontal_padding' => 16,
                    'vertical_padding' => 8,
                ],
            ]);

            if ($selectedColor !== null && $selectedColor !== '') {
                $decoratedSection['style']['iconColor'] = $selectedColor;
                $decoratedSection['icon_color'] = $selectedColor;
                $decoratedSection['leading'] = [
                    'type' => 'icon',
                    'name' => $firstIcon,
                    'color' => $selectedColor,
                ];
                $decoratedSection['items'] = array_map(function (array $item) use ($asActionableItem): array {
                    return $asActionableItem($item);
                }, $items);
            }

            return $decoratedSection;
        }, $sections));
    }

    /**
     * Format a drawer item ensuring explicit icon color and leading component
     * adopt the tenant's configured preference color token without hardcoding orange.
     *
     * @param  array<string, mixed>  $item
     * @param  string|null  $selectedColor
     * @return array<string, mixed>
     */
    public static function formatDrawerItem(array $item, ?string $selectedColor = null): array
    {
        $iconName = (string) ($item['icon'] ?? 'widgets');
        $item['icon'] = $iconName;

        if ($selectedColor !== null && $selectedColor !== '') {
            $item['icon_color'] = $selectedColor;
            $item['leading'] = [
                'type' => 'icon',
                'name' => $iconName,
                'color' => $selectedColor,
            ];
            $style = (array) ($item['style'] ?? []);
            $style['textColor'] = $selectedColor;
            $style['iconColor'] = $selectedColor;
            $item['style'] = $style;
        } else {
            if (isset($item['icon_color']) && ($item['icon_color'] === '#F97316' || $item['icon_color'] === '#EA580C')) {
                unset($item['icon_color']);
            }
            if (isset($item['leading']) && is_array($item['leading']) && isset($item['leading']['color']) && ($item['leading']['color'] === '#F97316' || $item['leading']['color'] === '#EA580C')) {
                $item['leading']['color'] = null;
            }
        }

        if (isset($item['children']) && is_array($item['children'])) {
            $formattedChildren = [];
            foreach ($item['children'] as $child) {
                if (is_array($child)) {
                    $formattedChildren[] = self::formatDrawerItem($child, $selectedColor);
                }
            }
            $item['children'] = $formattedChildren;
        }

        return $item;
    }

    /**
     * Overrides section and item display titles if tenant customized them in navigation_labels.
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, string>  $labels
     * @return list<array<string, mixed>>
     */
    public static function applyNavigationLabels(array $sections, array $labels): array
    {
        if (empty($labels)) {
            return $sections;
        }

        $resolveLabel = function (string ...$candidates) use ($labels): ?string {
            foreach ($candidates as $cand) {
                if ($cand !== '') {
                    if (! empty($labels[$cand])) {
                        return (string) $labels[$cand];
                    }
                    $k1 = str_replace('-', '_', $cand);
                    if (! empty($labels[$k1])) {
                        return (string) $labels[$k1];
                    }
                    $k2 = str_replace('_', '-', $cand);
                    if (! empty($labels[$k2])) {
                        return (string) $labels[$k2];
                    }
                }
            }

            return null;
        };

        $applyToItem = function (array $item) use (&$applyToItem, $resolveLabel): array {
            $key = (string) ($item['key'] ?? $item['id'] ?? '');
            $id = (string) ($item['id'] ?? '');
            $component = (string) ($item['component'] ?? '');
            $labelSlug = Str::snake(strtolower($item['label'] ?? ''));
            $candidates = array_unique(array_filter([$key, $id, $component, $labelSlug]));
            if ($key === 'repair_dashboard' || $key === 'repair_workbench') {
                $candidates[] = 'repair_workbench';
                $candidates[] = 'repair_dashboard';
            }
            if ($key === 'pharmacy_prescriptions' || $id === 'prescriptions_queue') {
                $candidates[] = 'prescriptions_queue';
                $candidates[] = 'pharmacy_prescriptions';
            }
            if ($key === 'pharmacy_batches' || $id === 'batch_inventory') {
                $candidates[] = 'batch_inventory';
                $candidates[] = 'pharmacy_batches';
            }
            if ($key === 'new_prescription_intake' || $id === 'new_prescription_intake') {
                $candidates[] = 'new_rx_intake';
                $candidates[] = 'new_prescription_intake';
                $candidates[] = 'pharmacy_rx_create';
            }
            if ($key === 'pharmacy_pos' || $id === 'pharmacy_pos') {
                $candidates[] = 'pharmacy_pos';
            }
            $custom = $resolveLabel(...$candidates);
            if ($custom !== null) {
                $item['title'] = $custom;
                $item['label'] = $custom;
            }
            if (! empty($item['children']) && is_array($item['children'])) {
                $item['children'] = array_map($applyToItem, $item['children']);
            }

            return $item;
        };

        return array_values(array_map(function ($section) use ($resolveLabel, $applyToItem) {
            if (! is_array($section)) {
                return $section;
            }
            $key = (string) ($section['key'] ?? $section['id'] ?? '');
            $custom = $resolveLabel($key);
            if ($custom !== null) {
                $section['title'] = $custom;
                $section['label'] = $custom;
            }
            if (! empty($section['items']) && is_array($section['items'])) {
                $section['items'] = array_map($applyToItem, $section['items']);
            }

            return $section;
        }, $sections));
    }

    /**
     * Normalizes a nav section ensuring id/key, label/title parity.
     *
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    public static function normalizeSection(array $section): array
    {
        $key = trim((string) ($section['key'] ?? $section['id'] ?? ''));
        $title = trim((string) ($section['title'] ?? $section['label'] ?? $key));
        $color = $section['color'] ?? $section['header_color'] ?? '#475569';

        $items = [];
        foreach ($section['items'] ?? $section['children'] ?? [] as $item) {
            if (is_array($item)) {
                $items[] = self::normalizeItem($item);
            }
        }

        // The section heading is independent from its first actionable menu
        // item. New tenants start with that first parent's name and can then
        // rename only the heading without changing the parent's route label.
        $customTitle = trim((string) ($section['custom_title'] ?? ''));
        if ($customTitle === '' && isset($items[0])) {
            $customTitle = trim((string) ($items[0]['title'] ?? $items[0]['label'] ?? ''));
        }

        return array_merge($section, [
            'id' => $key,
            'key' => $key,
            'title' => $title,
            'label' => $title,
            'custom_title' => $customTitle !== '' ? $customTitle : $title,
            'color' => $color,
            'items' => $items,
        ], self::collapsedFlags());
    }

    /**
     * Every parent/accordion node in the drawer must render closed on mount and
     * only open when the user taps it. Emit every flag name the various client
     * releases have looked at so none of them can fall back to "expanded".
     *
     * @return array<string, bool>
     */
    public static function collapsedFlags(): array
    {
        return [
            'initially_expanded' => false,
            'initiallyExpanded' => false,
            'expanded' => false,
            'is_expanded' => false,
            'isExpanded' => false,
            'default_open' => false,
            'defaultOpen' => false,
            'auto_expand' => false,
        ];
    }

    /**
     * Normalizes a nav item ensuring id/key, label/title parity and recursing into children.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function normalizeItem(array $item, ?string $parentId = null): array
    {
        $key = trim((string) ($item['key'] ?? $item['id'] ?? ''));
        $title = trim((string) ($item['title'] ?? $item['label'] ?? $key));
        $icon = (string) ($item['icon'] ?? 'widgets');
        $component = (string) ($item['component'] ?? $key);

        $normalized = array_merge($item, [
            'id' => $key,
            'key' => $key,
            'title' => $title,
            'label' => $title,
            'icon' => $icon,
            'component' => $component,
        ]);

        if ($parentId !== null) {
            $normalized['parent'] = $parentId;
            $normalized['parent_id'] = $parentId;
            $normalized['type'] = $normalized['type'] ?? 'link';
        }

        if (isset($item['children']) && is_array($item['children'])) {
            $children = [];
            foreach ($item['children'] as $child) {
                if (is_array($child)) {
                    $children[] = self::normalizeItem($child, $key);
                }
            }
            $normalized['children'] = $children;
        }

        $hasChildren = ! empty($normalized['children']);
        if ($hasChildren || ($normalized['type'] ?? null) === 'accordion') {
            $normalized['type'] = 'accordion';
            $normalized = array_merge($normalized, self::collapsedFlags());
        } else {
            $normalized['type'] = $normalized['type'] ?? 'link';
        }

        $target = $normalized['target_endpoint'] ?? $normalized['route'] ?? null;
        if (! $hasChildren && empty($target)) {
            $routeKey = str_replace('_', '-', $key);
            $target = '/api/tenant/views/'.$routeKey;
        }
        if ($target !== null) {
            $normalized['target_endpoint'] = $target;
            $normalized['route'] = $target;
        }

        return $normalized;
    }

    /**
     * Validate database-authored navigation before it can replace the core
     * tree. One corrupted section must never turn the bootstrap menu into an
     * empty or partially unusable payload.
     *
     * @param  list<mixed>  $navigation
     * @return list<array<string, mixed>>|null
     */
    /**
     * True when a package module's files are physically present where
     * {@see \App\Providers\ModuleServiceProvider::bootModule()} loads routes
     * from — i.e. its endpoints will actually resolve. A DB row alone is not
     * enough.
     */
    private static function packageIsInstalledOnDisk(SduiModule $module): bool
    {
        $path = trim((string) ($module->package_path ?: $module->slug));
        if ($path === '') {
            return false;
        }

        $base = base_path('modules/'.$path);
        if (! File::isDirectory($base)) {
            $base = base_path('module-packages/'.$path);
            if (! File::isDirectory($base)) {
                return false;
            }
        }

        foreach (['routes.php', 'routes/api.php', 'routes/web.php', 'module.json'] as $marker) {
            if (File::exists($base.'/'.$marker)) {
                return true;
            }
        }

        return false;
    }

    private static function validatedCustomNavigation(array $navigation): ?array
    {
        $sections = [];
        $seen = [];

        foreach ($navigation as $section) {
            if (! is_array($section)) {
                return null;
            }

            $key = trim((string) ($section['key'] ?? $section['id'] ?? ''));
            $items = $section['items'] ?? $section['children'] ?? null;
            if ($key === '' || isset($seen[$key]) || ! is_array($items) || $items === []) {
                return null;
            }

            $validatedItems = self::validatedCustomItems($items);
            if ($validatedItems === null || $validatedItems === []) {
                return null;
            }

            $title = trim((string) ($section['title'] ?? $section['label'] ?? $key));
            $color = $section['color'] ?? $section['header_color'] ?? '#475569';

            $seen[$key] = true;
            $section['id'] = $key;
            $section['key'] = $key;
            $section['title'] = $title;
            $section['label'] = $title;
            $section['color'] = $color;
            $section['items'] = $validatedItems;
            $sections[] = $section;
        }

        return $sections === [] ? null : $sections;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array<string, mixed>>|null
     */
    private static function validatedCustomItems(array $items): ?array
    {
        $validated = [];
        $seen = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                return null;
            }

            $key = trim((string) ($item['key'] ?? $item['id'] ?? ''));
            if ($key === '') {
                if (! empty($item['title'])) {
                    $key = Str::slug($item['title'], '_');
                } elseif (! empty($item['label'])) {
                    $key = Str::slug($item['label'], '_');
                } elseif (! empty($item['route'])) {
                    $key = Str::slug(basename($item['route']), '_');
                }
            }
            if ($key === '' || isset($seen[$key])) {
                return null;
            }

            if (! empty($item['route']) && empty($item['target_endpoint'])) {
                $item['target_endpoint'] = $item['route'];
            }

            $children = $item['children'] ?? [];
            if ($children !== null && ! is_array($children)) {
                return null;
            }

            $validatedChildren = is_array($children) && $children !== []
                ? self::validatedCustomItems($children)
                : [];
            if ($validatedChildren === null) {
                return null;
            }

            $title = trim((string) ($item['title'] ?? $item['label'] ?? $key));
            $seen[$key] = true;
            $item['id'] = $key;
            $item['key'] = $key;
            $item['title'] = $title;
            $item['label'] = $title;
            if (array_key_exists('children', $item) || $validatedChildren !== []) {
                $item['children'] = $validatedChildren;
            }
            $validated[] = $item;
        }

        return $validated;
    }

    private static function retailSections(): array
    {
        return [
            [
                'key' => 'cashier_sales',
                'label' => 'Cashier & Sales',
                'color' => '#1d4ed8',
                'items' => [
                    ['key' => 'pos', 'label' => 'Point of Sale', 'icon' => 'point_of_sale', 'component' => 'pos', 'permission' => 'pos'],
                    ['key' => 'barcode_printing', 'label' => 'Barcode & Label Printing', 'icon' => 'qr_code', 'component' => 'inventory', 'permission' => 'products'],
                    ['key' => 'batch_tracking', 'label' => 'Batch & Expiry Tracking', 'icon' => 'batch_prediction', 'component' => 'inventory', 'permission' => 'products'],
                    ['key' => 'sales', 'label' => 'Sales & Invoices', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales'],
                    ['key' => 'quotations', 'label' => 'Quotations & Proposals', 'icon' => 'description', 'component' => 'quotations', 'permission' => 'quotes'],
                    ['key' => 'consignments', 'label' => 'Consignments', 'icon' => 'local_shipping', 'component' => 'consignments', 'permission' => 'consignments'],
                    ['key' => 'customers', 'label' => 'Customers & CRM', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'color' => '#0f766e',
                'items' => [
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register'],
                    ['key' => 'due_receivables', 'label' => 'Accounts Receivable', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance'],
                    ['key' => 'payables', 'label' => 'Accounts Payable', 'icon' => 'request_quote', 'component' => 'payables', 'permission' => 'finance'],
                    ['key' => 'sales_targets', 'label' => 'Sales Targets', 'icon' => 'flag', 'component' => 'sales_targets', 'permission' => 'targets'],
                    ['key' => 'reports', 'label' => 'Reports', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports'],
                    ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'bar_chart', 'component' => 'analytics', 'permission' => 'reports'],
                ],
            ],
            [
                'key' => 'products_inventory',
                'label' => 'Products & Inventory',
                'color' => '#b45309',
                'items' => [
                    ['key' => 'inventory', 'label' => 'All Products', 'icon' => 'inventory_2', 'component' => 'inventory', 'permission' => 'products'],
                    ['key' => 'categories', 'label' => 'Categories', 'icon' => 'sell', 'component' => 'categories', 'permission' => 'categories'],
                    ['key' => 'brands', 'label' => 'Brands & Manufacturers', 'icon' => 'auto_awesome', 'component' => 'brands', 'permission' => 'categories'],
                    ['key' => 'units', 'label' => 'Units of Measure', 'icon' => 'straighten', 'component' => 'units', 'permission' => 'units'],
                    ['key' => 'suppliers', 'label' => 'Suppliers & Vendors', 'icon' => 'local_shipping', 'component' => 'suppliers', 'permission' => 'suppliers'],
                    ['key' => 'taxes', 'label' => 'Taxes & Compliance', 'icon' => 'percent', 'component' => 'taxes', 'permission' => 'settings'],
                    ['key' => 'catalog', 'label' => 'Online Digital Catalog', 'icon' => 'qr_code', 'component' => 'catalog', 'permission' => 'catalog'],
                ],
            ],
            self::administrationSection(),
        ];
    }

    private static function restaurantSections(): array
    {
        return [
            [
                'key' => 'restaurant_operations',
                'label' => 'Restaurant Operations',
                'color' => '#4d7c0f',
                'items' => [
                    ['key' => 'restaurant_pos', 'label' => 'Restaurant POS Terminal', 'icon' => 'restaurant', 'component' => 'restaurant_pos', 'permission' => 'pos'],
                    ['key' => 'floor_plan', 'label' => 'Floor Plan & Tables', 'icon' => 'table_restaurant', 'component' => 'floor_plan', 'permission' => 'pos'],
                    ['key' => 'kitchen_display', 'label' => 'Kitchen Display (KDS)', 'icon' => 'soup_kitchen', 'component' => 'kitchen_display', 'permission' => 'pos'],
                ],
            ],
            [
                'key' => 'orders_cash',
                'label' => 'Orders & Cash',
                'color' => '#0284c7',
                'items' => [
                    ['key' => 'dining_history', 'label' => 'Dining & Sales History', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales'],
                    ['key' => 'sales', 'label' => 'Sales & Invoices History', 'icon' => 'receipt', 'component' => 'sales', 'permission' => 'sales'],
                    ['key' => 'quotations', 'label' => 'Quotations & Party Orders', 'icon' => 'description', 'component' => 'quotations', 'permission' => 'quotes'],
                    ['key' => 'customers', 'label' => 'Customers & CRM', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers'],
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'color' => '#0f766e',
                'items' => [
                    ['key' => 'due_receivables', 'label' => 'Accounts Receivable', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance'],
                    ['key' => 'payables', 'label' => 'Accounts Payable', 'icon' => 'request_quote', 'component' => 'payables', 'permission' => 'finance'],
                    ['key' => 'reports', 'label' => 'Reports & Analytics', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports'],
                    ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'bar_chart', 'component' => 'analytics', 'permission' => 'reports'],
                ],
            ],
            [
                'key' => 'kitchen_menu_catalog',
                'label' => 'Kitchen Menu & Catalog',
                'color' => '#d97706',
                'items' => [
                    ['key' => 'inventory', 'label' => 'Menu Dishes & Stock', 'icon' => 'restaurant_menu', 'component' => 'inventory', 'permission' => 'products'],
                    ['key' => 'categories', 'label' => 'Categories', 'icon' => 'sell', 'component' => 'categories', 'permission' => 'categories'],
                    ['key' => 'brands', 'label' => 'Brands & Modifiers', 'icon' => 'auto_awesome', 'component' => 'brands', 'permission' => 'categories'],
                    ['key' => 'units', 'label' => 'Units of Measure', 'icon' => 'straighten', 'component' => 'units', 'permission' => 'units'],
                    ['key' => 'suppliers', 'label' => 'Food Suppliers', 'icon' => 'local_shipping', 'component' => 'suppliers', 'permission' => 'suppliers'],
                    ['key' => 'catalog', 'label' => 'Online QR Menu', 'icon' => 'qr_code', 'component' => 'catalog', 'permission' => 'catalog'],
                ],
            ],
            self::administrationSection(),
        ];
    }

    private static function pharmacySections(): array
    {
        return [
            [
                'key' => 'pharmacy_dispensary',
                'label' => 'PHARMACY OPERATIONS',
                'color' => '#059669',
                'items' => [
                    ['key' => 'pharmacy_pos', 'id' => 'pharmacy_pos', 'label' => 'Pharmacy POS & Checkout', 'title' => 'Pharmacy POS & Checkout', 'icon' => 'point_of_sale', 'component' => 'pos', 'permission' => 'pos', 'route' => 'pos', 'target_endpoint' => 'pos'],
                    ['key' => 'new_prescription_intake', 'id' => 'new_prescription_intake', 'label' => 'New Prescription Intake', 'title' => 'New Prescription Intake', 'icon' => 'note_add', 'component' => 'new_prescription_intake', 'permission' => 'sales', 'route' => '/api/tenant/views/pharmacy-rx-create', 'target_endpoint' => '/api/tenant/views/pharmacy-rx-create'],
                    ['key' => 'prescriptions_queue', 'id' => 'prescriptions_queue', 'label' => 'Prescriptions & Patient Queue', 'title' => 'Prescriptions & Patient Queue', 'icon' => 'medical_information', 'component' => 'pharmacy_prescriptions', 'permission' => 'sales', 'route' => '/api/tenant/views/pharmacy-prescriptions', 'target_endpoint' => '/api/tenant/views/pharmacy-prescriptions'],
                    ['key' => 'batch_inventory', 'id' => 'batch_inventory', 'label' => 'Drug Batches & Expiry Tracker', 'title' => 'Drug Batches & Expiry Tracker', 'icon' => 'inventory_2', 'component' => 'pharmacy_batches', 'permission' => 'products', 'route' => '/api/tenant/views/pharmacy-batches', 'target_endpoint' => '/api/tenant/views/pharmacy-batches'],
                    ['key' => 'sales', 'id' => 'sales', 'label' => 'Dispensed Prescriptions', 'title' => 'Dispensed Prescriptions', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales', 'route' => '/api/tenant/views/sales', 'target_endpoint' => '/api/tenant/views/sales'],
                    ['key' => 'quotations', 'id' => 'quotations', 'label' => 'Quotations & Estimates', 'title' => 'Quotations & Estimates', 'icon' => 'description', 'component' => 'quotations', 'permission' => 'quotes', 'route' => '/api/tenant/views/quotations', 'target_endpoint' => '/api/tenant/views/quotations'],
                    ['key' => 'customers', 'id' => 'customers', 'label' => 'Patients & Doctors', 'title' => 'Patients & Doctors', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers', 'route' => '/api/tenant/views/customers', 'target_endpoint' => '/api/tenant/views/customers'],
                    ['key' => 'cash_register', 'id' => 'cash_register', 'label' => 'Cash Register', 'title' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register', 'route' => '/api/tenant/views/cash-register', 'target_endpoint' => '/api/tenant/views/cash-register'],
                ],
            ],
            [
                'key' => 'pharmacy_inventory',
                'label' => 'Medicines & Inventory',
                'color' => '#2563eb',
                'items' => [
                    ['key' => 'batch_inventory', 'id' => 'batch_inventory', 'label' => 'Drug Batches & Expiry Tracker', 'title' => 'Drug Batches & Expiry Tracker', 'icon' => 'inventory_2', 'component' => 'pharmacy_batches', 'permission' => 'products', 'route' => '/api/tenant/views/pharmacy-batches', 'target_endpoint' => '/api/tenant/views/pharmacy-batches'],
                    ['key' => 'prescriptions_queue', 'id' => 'prescriptions_queue', 'label' => 'Prescriptions & Patient Queue', 'title' => 'Prescriptions & Patient Queue', 'icon' => 'medical_information', 'component' => 'pharmacy_prescriptions', 'permission' => 'sales', 'route' => '/api/tenant/views/pharmacy-prescriptions', 'target_endpoint' => '/api/tenant/views/pharmacy-prescriptions'],
                    ['key' => 'new_prescription_intake', 'id' => 'new_prescription_intake', 'label' => 'New Prescription Intake', 'title' => 'New Prescription Intake', 'icon' => 'note_add', 'component' => 'new_prescription_intake', 'permission' => 'sales', 'route' => '/api/tenant/views/pharmacy-rx-create', 'target_endpoint' => '/api/tenant/views/pharmacy-rx-create'],
                    ['key' => 'inventory', 'id' => 'inventory', 'label' => 'Drugs & Formulations', 'title' => 'Drugs & Formulations', 'icon' => 'medication', 'component' => 'inventory', 'permission' => 'products', 'route' => '/api/tenant/views/inventory', 'target_endpoint' => '/api/tenant/views/inventory'],
                    ['key' => 'categories', 'id' => 'categories', 'label' => 'Therapeutic Categories', 'title' => 'Therapeutic Categories', 'icon' => 'sell', 'component' => 'categories', 'permission' => 'categories', 'route' => '/api/tenant/views/categories', 'target_endpoint' => '/api/tenant/views/categories'],
                    ['key' => 'suppliers', 'id' => 'suppliers', 'label' => 'Pharma Distributors', 'title' => 'Pharma Distributors', 'icon' => 'local_shipping', 'component' => 'suppliers', 'permission' => 'suppliers', 'route' => '/api/tenant/views/suppliers', 'target_endpoint' => '/api/tenant/views/suppliers'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'color' => '#0f766e',
                'items' => [
                    ['key' => 'cash_register', 'id' => 'cash_register', 'label' => 'Cash Register', 'title' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register', 'route' => '/api/tenant/views/cash-register', 'target_endpoint' => '/api/tenant/views/cash-register'],
                    ['key' => 'due_receivables', 'id' => 'due_receivables', 'label' => 'Patient Credit / Khata', 'title' => 'Patient Credit / Khata', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance', 'route' => '/api/tenant/views/due-receivables', 'target_endpoint' => '/api/tenant/views/due-receivables'],
                    ['key' => 'payables', 'id' => 'payables', 'label' => 'Supplier Payables', 'title' => 'Supplier Payables', 'icon' => 'request_quote', 'component' => 'payables', 'permission' => 'finance', 'route' => '/api/tenant/views/payables', 'target_endpoint' => '/api/tenant/views/payables'],
                    ['key' => 'reports', 'id' => 'reports', 'label' => 'Reports & Analytics', 'title' => 'Reports & Analytics', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports', 'route' => '/api/tenant/views/reports', 'target_endpoint' => '/api/tenant/views/reports'],
                ],
            ],
            self::administrationSection(),
        ];
    }

    private static function serviceBookingSections(): array
    {
        return [
            [
                'key' => 'service_operations',
                'label' => 'Appointments & Service',
                'color' => '#7c3aed',
                'items' => [
                    ['key' => 'salon_pos', 'id' => 'salon_pos', 'label' => 'Salon POS & Checkout', 'title' => 'Salon POS & Checkout', 'icon' => 'point_of_sale', 'component' => 'pos', 'permission' => 'pos', 'route' => '/api/tenant/views/salon-pos', 'target_endpoint' => '/api/tenant/views/salon-pos'],
                    ['key' => 'book_appointment', 'id' => 'book_appointment', 'label' => 'Book Service / Appointment', 'title' => 'Book Service / Appointment', 'icon' => 'edit_calendar', 'component' => 'book_appointment', 'permission' => 'service_orders', 'route' => '/api/tenant/views/salon-booking-create', 'target_endpoint' => '/api/tenant/views/salon-booking-create'],
                    ['key' => 'booking_calendar', 'id' => 'booking_calendar', 'label' => 'Service Booking Calendar', 'title' => 'Service Booking Calendar', 'icon' => 'calendar_month', 'component' => 'service_calendar', 'permission' => 'service_orders', 'route' => '/api/tenant/views/salon-calendar', 'target_endpoint' => '/api/tenant/views/salon-calendar'],
                    ['key' => 'sales', 'id' => 'sales', 'label' => 'Sales & Invoices History', 'title' => 'Sales & Invoices History', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales', 'route' => '/api/tenant/views/sales', 'target_endpoint' => '/api/tenant/views/sales'],
                    ['key' => 'quotations', 'id' => 'quotations', 'label' => 'Quotations & Estimates', 'title' => 'Quotations & Estimates', 'icon' => 'description', 'component' => 'quotations', 'permission' => 'quotes', 'route' => '/api/tenant/views/quotations', 'target_endpoint' => '/api/tenant/views/quotations'],
                    ['key' => 'customers', 'id' => 'customers', 'label' => 'Clients & Memberships', 'title' => 'Clients & Memberships', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers', 'route' => '/api/tenant/views/customers', 'target_endpoint' => '/api/tenant/views/customers'],
                    ['key' => 'cash_register', 'id' => 'cash_register', 'label' => 'Cash Register', 'title' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register', 'route' => '/api/tenant/views/cash-register', 'target_endpoint' => '/api/tenant/views/cash-register'],
                ],
            ],
            [
                'key' => 'products_staff',
                'label' => 'Supplies & Staff',
                'color' => '#d97706',
                'items' => [
                    ['key' => 'inventory', 'id' => 'inventory', 'label' => 'Products & Supplies', 'title' => 'Products & Supplies', 'icon' => 'inventory_2', 'component' => 'inventory', 'permission' => 'products', 'route' => '/api/tenant/views/inventory', 'target_endpoint' => '/api/tenant/views/inventory'],
                    ['key' => 'service_catalog', 'id' => 'service_catalog', 'label' => 'Service Catalog & Rates', 'title' => 'Service Catalog & Rates', 'icon' => 'format_list_bulleted', 'component' => 'service_catalog', 'permission' => 'service_orders', 'route' => '/api/tenant/views/service-catalog', 'target_endpoint' => '/api/tenant/views/service-catalog'],
                    ['key' => 'add_new_service', 'id' => 'add_new_service', 'label' => 'Add New Service', 'title' => 'Add New Service', 'icon' => 'add_circle_outline', 'component' => 'service_create', 'permission' => 'service_orders', 'route' => '/api/tenant/views/service-create', 'target_endpoint' => '/api/tenant/views/service-create'],
                    ['key' => 'staff', 'id' => 'staff', 'label' => 'Specialists & Stylists', 'title' => 'Specialists & Stylists', 'icon' => 'badge', 'component' => 'staff', 'permission' => 'users', 'route' => '/api/tenant/views/service-stylists', 'target_endpoint' => '/api/tenant/views/service-stylists'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'color' => '#0f766e',
                'items' => [
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register', 'target_endpoint' => '/api/tenant/views/cash-register'],
                    ['key' => 'due_receivables', 'label' => 'Client Due Receivables', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance', 'target_endpoint' => '/api/tenant/views/due-receivables'],
                    ['key' => 'reports', 'label' => 'Service Reports', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports', 'target_endpoint' => '/api/tenant/views/reports'],
                ],
            ],
            self::administrationSection(),
        ];
    }

    private static function repairTechnicianSections(): array
    {
        return [
            [
                'key' => 'repair_operations',
                'label' => 'REPAIR OPERATIONS & SALES',
                'color' => '#0284c7',
                'items' => [
                    ['key' => 'pos', 'label' => 'Point of Sale', 'icon' => 'point_of_sale', 'component' => 'pos', 'permission' => 'pos', 'target_endpoint' => '/api/tenant/views/pos'],
                    ['key' => 'repair_dashboard', 'label' => 'Repair Workbench', 'icon' => 'handyman', 'component' => 'repair_dashboard', 'permission' => 'repair', 'target_endpoint' => '/api/tenant/views/repair-dashboard'],
                    ['key' => 'repair_create_ticket', 'label' => 'New Intake Ticket', 'icon' => 'add_task', 'component' => 'repair_create_ticket', 'permission' => 'repair', 'target_endpoint' => '/api/tenant/views/repair-create-ticket'],
                    ['key' => 'repair_tickets', 'label' => 'Repair Ticket Register', 'icon' => 'receipt_long', 'component' => 'repair_tickets', 'permission' => 'repair', 'target_endpoint' => '/api/tenant/views/repair-tickets'],
                    ['key' => 'sales', 'label' => 'Sales & Invoices', 'icon' => 'receipt_long', 'component' => 'sales', 'permission' => 'sales', 'target_endpoint' => '/api/tenant/views/sales'],
                    ['key' => 'quotations', 'label' => 'Quotations & Proposals', 'icon' => 'description', 'component' => 'quotations', 'permission' => 'quotes', 'target_endpoint' => '/api/tenant/views/quotations'],
                    ['key' => 'customers', 'label' => 'Customers & CRM', 'icon' => 'people', 'component' => 'customers', 'permission' => 'customers', 'target_endpoint' => '/api/tenant/views/customers'],
                ],
            ],
            [
                'key' => 'spare_parts_inventory',
                'label' => 'Spare Parts & Inventory',
                'color' => '#d97706',
                'items' => [
                    ['key' => 'inventory', 'label' => 'Parts & Consumables', 'icon' => 'inventory_2', 'component' => 'inventory', 'permission' => 'products'],
                    ['key' => 'categories', 'label' => 'Categories', 'icon' => 'category', 'component' => 'categories', 'permission' => 'categories', 'target_endpoint' => '/api/tenant/views/categories'],
                    ['key' => 'suppliers', 'label' => 'Parts Vendors', 'icon' => 'local_shipping', 'component' => 'suppliers', 'permission' => 'suppliers'],
                ],
            ],
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'color' => '#0f766e',
                'items' => [
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'icon' => 'savings', 'component' => 'cash_register', 'permission' => 'cash_register'],
                    ['key' => 'due_receivables', 'label' => 'Repair Invoices Due', 'icon' => 'notifications_active', 'component' => 'due_receivables', 'permission' => 'finance'],
                    ['key' => 'reports', 'label' => 'Workshop Reports', 'icon' => 'insights', 'component' => 'reports', 'permission' => 'reports'],
                ],
            ],
            self::administrationSection(),
        ];
    }

    private static function administrationSection(): array
    {
        $tabs = self::settingsTabItems();

        return [
            'key' => 'administration',
            'label' => 'Administration & Settings',
            'title' => 'Administration & Settings',
            'color' => '#475569',
            'items' => [
                ['key' => 'subscription', 'label' => 'Subscription & Billing', 'title' => 'Subscription & Billing', 'icon' => 'workspace_premium', 'component' => 'subscription', 'type' => 'link', 'permission' => null],
                [
                    'key' => 'settings',
                    'label' => 'Store Settings',
                    'title' => 'Store Settings',
                    'icon' => 'settings',
                    'component' => 'settings',
                    'type' => 'accordion',
                    'permission' => 'settings',
                    'children' => $tabs,
                ],
                // Retain the flat rows for the existing navigation editor and
                // older clients. Current SDUI clients de-duplicate by key and
                // use the canonical children tree above for drawer rendering.
                ...$tabs,
                ['key' => 'languages', 'label' => 'Languages & Translations', 'title' => 'Languages & Translations', 'icon' => 'translate', 'component' => 'languages', 'type' => 'link', 'permission' => 'settings'],
                ['key' => 'staff', 'label' => 'Users & Permissions', 'title' => 'Users & Permissions', 'icon' => 'badge', 'component' => 'staff', 'type' => 'link', 'permission' => 'users'],
                ['key' => 'roles', 'label' => 'Roles & Access Levels', 'title' => 'Roles & Access Levels', 'icon' => 'admin_panel_settings', 'component' => 'roles', 'type' => 'link', 'permission' => 'users', 'target_endpoint' => '/api/tenant/views/roles'],
                ['key' => 'devices', 'label' => 'Terminals & Devices', 'title' => 'Terminals & Devices', 'icon' => 'devices_other', 'component' => 'devices', 'type' => 'link', 'permission' => null],
                // Hardware pairing is mode-agnostic — every operating mode
                // prints receipts / tokens, so this row is anchored in the
                // shared administration section for all tenants. `component`
                // resolves to the native GlobalPrinterSetupScreen on mobile;
                // `target_endpoint` is the graceful web / non-native fallback.
                ['key' => 'hardware_printer', 'label' => 'Printer & Hardware Setup', 'title' => 'Printer & Hardware Setup', 'icon' => 'print', 'component' => 'printer_setup', 'type' => 'link', 'permission' => null, 'route' => 'printer_setup', 'target_endpoint' => '/api/tenant/views/printer-setup'],
                ['key' => 'change_password', 'label' => 'Change Password', 'title' => 'Change Password', 'icon' => 'lock_reset', 'component' => 'change_password', 'type' => 'link', 'permission' => null, 'target_endpoint' => '/api/tenant/views/change-password'],
            ],
        ];
    }

    /**
     * Store Settings' seven tenant-owned tabs with declarative SDUI target endpoints.
     */
    public static function settingsTabItems(): array
    {
        return [
            [
                'key' => 'settings_mode',
                'label' => 'Store Operating Mode',
                'title' => 'Store Operating Mode',
                'icon' => 'flash',
                'component' => 'settings_mode',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-mode',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_profile',
                'label' => 'Store Profile',
                'title' => 'Store Profile',
                'icon' => 'storefront',
                'component' => 'settings_profile',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-profile',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_branding',
                'label' => 'Branding & Colors',
                'title' => 'Store Branding & Colors',
                'icon' => 'palette',
                'component' => 'settings_branding',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-branding',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_receipts',
                'label' => 'Receipt Prefixes & Bank Terms',
                'title' => 'Receipt Prefixes & Bank Terms',
                'icon' => 'receipt',
                'component' => 'settings_receipts',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-receipts',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_financial',
                'label' => 'Financial & Currency',
                'title' => 'Financial & Currency',
                'icon' => 'monetization_on',
                'component' => 'settings_financial',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-financial',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_taxes',
                'label' => 'Taxes & Compliance',
                'title' => 'Taxes & Compliance',
                'icon' => 'percent',
                'component' => 'settings_taxes',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-taxes',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_api',
                'label' => 'API & Integrations',
                'title' => 'API & Integrations',
                'icon' => 'api',
                'component' => 'settings_api',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-api',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                'key' => 'settings_navigation',
                'label' => 'Navigation Menu',
                'title' => 'Navigation Menu',
                'icon' => 'menu_open',
                'component' => 'settings_navigation',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-navigation',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => 'settings',
            ],
            [
                // Per-device: light/dark theme, page-move animation, nav-dock
                // placement. `component` resolves to the native
                // AppPreferencesScreen on the desktop/mobile app; the
                // target_endpoint is the graceful web fallback.
                'key' => 'app_preferences',
                'label' => 'App Preferences',
                'title' => 'App Preferences',
                'icon' => 'tune',
                'component' => 'app_preferences',
                'type' => 'link',
                'target_endpoint' => '/api/tenant/views/settings-appearance',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => null,
            ],
            [
                'key' => 'settings_audio_notifications',
                'label' => 'Notifications & Audio Alerts',
                'title' => 'Notifications & Audio Alerts',
                'icon' => 'notifications_active',
                'component' => 'notifications_audio',
                'type' => 'link',
                'route' => '/api/v1/tenant/settings/notifications-audio',
                'target_endpoint' => '/api/v1/tenant/settings/notifications-audio',
                'parent' => 'settings',
                'parent_id' => 'settings',
                'permission' => null,
            ],
        ];
    }
}
