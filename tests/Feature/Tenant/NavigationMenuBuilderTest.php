<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Settings\Index as SettingsIndex;
use App\Services\Auth\PermissionChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class NavigationMenuBuilderTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_navigation_tab_initializes_from_the_compiled_in_retail_tree(): void
    {
        [$company] = $this->actingAsTenantAdmin();
        $this->assertFalse($company->isRestaurantMode());

        Livewire::test(SettingsIndex::class)
            ->assertViewHas('navSections', function (array $sections) {
                $cashierSales = collect($sections)->firstWhere('key', 'cashier_sales');

                return $cashierSales
                    && $cashierSales['label'] === 'Cashier & Sales'
                    && $cashierSales['custom_title'] === 'Point of Sale'
                    && collect($cashierSales['items'])->pluck('key')->all() === ['pos', 'barcode_printing', 'batch_tracking', 'sales', 'quotations', 'consignments', 'customers']
                    && collect($cashierSales['items'])->every(fn ($i) => $i['visible'] === true);
            });
    }

    /**
     * Store Settings' eight tabs are compiled in as root items nested under
     * "settings" by default (TenantNavRegistry::settingsTabItems()) — the
     * Navigation Menu tab must surface them as a `children` array on the
     * "settings" row, not as flat administration-section items, with no
     * nav_config saved yet.
     */
    public function test_navigation_tab_nests_the_tenant_owned_settings_tabs_under_store_settings_by_default(): void
    {
        $this->actingAsTenantAdmin();

        Livewire::test(SettingsIndex::class)
            ->assertViewHas('navSections', function (array $sections) {
                $administration = collect($sections)->firstWhere('key', 'administration');
                $settings = collect($administration['items'])->firstWhere('key', 'settings');

                $childKeys = collect($settings['children'] ?? [])->pluck('key')->all();

                // Tenant-owned tabs must be nested under "settings", not also
                // duplicated as separate root-level administration items.
                $rootKeys = collect($administration['items'])->pluck('key')->all();

                return $childKeys === [
                    'settings_mode', 'settings_profile', 'settings_branding', 'settings_receipts', 'settings_financial',
                    'settings_taxes', 'settings_api', 'settings_navigation', 'app_preferences', 'settings_audio_notifications',
                    'settings_storefront', 'settings_payments', 'settings_coupons', 'settings_faqs', 'settings_reviews',
                ] && ! in_array('settings_mode', $rootKeys, true);
            });
    }

    public function test_save_nav_config_persists_nested_children_and_relocks_stray_settings_tabs(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        // A realistic save round-trips the *entire* current tree. Here the
        // payload simulates a drag that pulled "settings_navigation" out from
        // under "Store Settings" to the section root and dropped "settings"
        // itself under "Subscription & Billing" — exactly the shape the mobile
        // tree editor was persisting. Every Store Settings tab is registry-
        // pinned to the "settings" accordion, so normalize() must snap them
        // back (TenantNavigationConfigService::FORCED_PARENTS / FORCED_ROOT).
        $payload = [
            [
                'key' => 'administration',
                'label' => 'Administration & Settings',
                'items' => [
                    [
                        'key' => 'subscription',
                        'label' => 'Subscription & Billing',
                        'visible' => true,
                        'children' => [
                            ['key' => 'settings', 'label' => 'Store Settings', 'visible' => true, 'children' => [
                                ['key' => 'settings_mode', 'label' => 'Store Operating Mode', 'visible' => true],
                                ['key' => 'settings_profile', 'label' => 'Store Profile & Branding', 'visible' => true],
                                ['key' => 'settings_receipts', 'label' => 'Receipt Prefixes & Bank Terms', 'visible' => true],
                                ['key' => 'settings_financial', 'label' => 'Financial & Currency', 'visible' => true, 'children' => [
                                    ['key' => 'settings_taxes', 'label' => 'Taxes & Compliance', 'visible' => true],
                                ]],
                                ['key' => 'settings_api', 'label' => 'API & Integrations', 'visible' => true],
                            ]],
                        ],
                    ],
                    // Dragged out from under "settings" to the section root.
                    ['key' => 'settings_navigation', 'label' => 'Navigation Menu', 'visible' => true, 'children' => []],
                    ['key' => 'languages', 'label' => 'Languages & Translations', 'visible' => true, 'children' => []],
                    ['key' => 'staff', 'label' => 'Users & Permissions', 'visible' => true, 'children' => []],
                    ['key' => 'devices', 'label' => 'Terminals & Devices', 'visible' => true, 'children' => []],
                ],
            ],
        ];

        Livewire::test(SettingsIndex::class)->call('saveNavConfig', $payload)->assertHasNoErrors();

        $nav = $company->fresh()->normalizedNavConfig();
        $itemsByKey = collect($nav['items'])->keyBy('key');

        // "settings" is a first-class parent again, never nested under another row.
        $this->assertNull($itemsByKey['settings']['parent']);
        // Every Store Settings tab is a direct child of "settings" — the
        // ejected "settings_navigation" and the over-nested "settings_taxes"
        // are both snapped back.
        foreach (['settings_mode', 'settings_profile', 'settings_receipts', 'settings_financial', 'settings_taxes', 'settings_api', 'settings_navigation'] as $tab) {
            $this->assertSame('settings', $itemsByKey[$tab]['parent'], "{$tab} should be a child of settings");
            $this->assertSame('administration', $itemsByKey[$tab]['section']);
            $this->assertSame(1, $itemsByKey[$tab]['level']);
        }

        // Reloading the builder reflects the relock. actingAs() keeps the same
        // auth User instance alive and Eloquent memoizes ->company; a real page
        // reload re-resolves the user, so drop the cached relation here.
        auth('web')->user()->unsetRelation('company');
        $sections = Livewire::test(SettingsIndex::class)->viewData('navSections');
        $administration = collect($sections)->firstWhere('key', 'administration');
        $settings = collect($administration['items'])->firstWhere('key', 'settings');
        $childKeys = collect($settings['children'])->pluck('key')->all();
        $this->assertContains('settings_profile', $childKeys);
        $this->assertContains('settings_navigation', $childKeys);
        $this->assertContains('settings_taxes', $childKeys);
        $this->assertNotContains('settings_navigation', collect($administration['items'])->pluck('key')->all());
    }

    /**
     * WordPress-style nesting: a Sub-Menu item can itself take a Sub-Sub-Menu
     * child, giving three levels total (Main Menu / Sub-Menu / Sub-Sub-Menu).
     * buildNavSections() must reconstruct that second nesting level on read,
     * and reject anything deeper by falling it back to the section root
     * rather than dropping it.
     */
    public function test_save_nav_config_persists_two_levels_of_nesting_and_caps_a_third(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $payload = [
            [
                'key' => 'cashier_sales',
                'label' => 'Cashier & Sales',
                'items' => [
                    [
                        'key' => 'pos',
                        'label' => 'Cashier POS Terminal',
                        'visible' => true,
                        'children' => [
                            [
                                'key' => 'sales',
                                'label' => 'Sales & Invoices',
                                'visible' => true,
                                // A Sub-Sub-Menu item nested under a Sub-Menu item.
                                'children' => [
                                    ['key' => 'quotations', 'label' => 'Quotations & Proposals', 'visible' => true],
                                ],
                            ],
                        ],
                    ],
                    ['key' => 'customers', 'label' => 'Customers & CRM', 'visible' => true, 'children' => []],
                ],
            ],
        ];

        Livewire::test(SettingsIndex::class)->call('saveNavConfig', $payload)->assertHasNoErrors();

        $nav = $company->fresh()->normalizedNavConfig();
        $itemsByKey = collect($nav['items'])->keyBy('key');

        $this->assertSame('pos', $itemsByKey['sales']['parent']);
        $this->assertSame('sales', $itemsByKey['quotations']['parent']);

        auth('web')->user()->unsetRelation('company');
        $sections = Livewire::test(SettingsIndex::class)->viewData('navSections');
        $cashierSales = collect($sections)->firstWhere('key', 'cashier_sales');
        $pos = collect($cashierSales['items'])->firstWhere('key', 'pos');
        $sales = collect($pos['children'])->firstWhere('key', 'sales');

        $this->assertSame(['quotations'], collect($sales['children'])->pluck('key')->all());

        // Now attempt a fourth level (quotations parenting something) —
        // buildNavSections() must cap it back to the section root, not
        // drop it or nest it another level deeper.
        $company->update(['nav_config' => [
            'sections' => [['key' => 'cashier_sales', 'order' => 0]],
            'items' => [
                ['key' => 'pos', 'section' => 'cashier_sales', 'parent' => null, 'order' => 0, 'visible' => true],
                ['key' => 'sales', 'section' => 'cashier_sales', 'parent' => 'pos', 'order' => 0, 'visible' => true],
                ['key' => 'quotations', 'section' => 'cashier_sales', 'parent' => 'sales', 'order' => 0, 'visible' => true],
                // Would be a 4th level (root -> pos -> sales -> quotations -> customers) — must be capped.
                ['key' => 'customers', 'section' => 'cashier_sales', 'parent' => 'quotations', 'order' => 0, 'visible' => true],
            ],
        ]]);
        auth('web')->user()->unsetRelation('company');
        $cappedSections = Livewire::test(SettingsIndex::class)->viewData('navSections');
        $cashierSalesCapped = collect($cappedSections)->firstWhere('key', 'cashier_sales');

        $this->assertContains('customers', collect($cashierSalesCapped['items'])->pluck('key')->all());
        $posCapped = collect($cashierSalesCapped['items'])->firstWhere('key', 'pos');
        $salesCapped = collect($posCapped['children'])->firstWhere('key', 'sales');
        $this->assertNotContains('customers', collect($salesCapped['children'])->pluck('key')->all());
    }

    public function test_save_nav_config_persists_hidden_items_reordered_sections_and_moved_items(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $payload = [
            // Financial Management dragged above Cashier & Sales.
            [
                'key' => 'financial_management',
                'label' => 'Financial Management',
                'items' => [
                    ['key' => 'cash_register', 'label' => 'Cash Register', 'visible' => true],
                    ['key' => 'due_receivables', 'label' => 'Accounts Receivable', 'visible' => true],
                    ['key' => 'payables', 'label' => 'Accounts Payable', 'visible' => true],
                    ['key' => 'reports', 'label' => 'Reports & Analytics', 'visible' => true],
                    // "pos" dragged in from Cashier & Sales.
                    ['key' => 'pos', 'label' => 'Cashier POS Terminal', 'visible' => true],
                ],
            ],
            [
                'key' => 'cashier_sales',
                'label' => 'Cashier & Sales',
                'items' => [
                    ['key' => 'sales', 'label' => 'Sales & Invoices', 'visible' => true],
                    // Hidden via the checkbox.
                    ['key' => 'quotations', 'label' => 'Quotations & Proposals', 'visible' => false],
                    ['key' => 'customers', 'label' => 'Customers & CRM', 'visible' => true],
                ],
            ],
        ];

        Livewire::test(SettingsIndex::class)->call('saveNavConfig', $payload)->assertHasNoErrors();

        $nav = $company->fresh()->normalizedNavConfig();

        $this->assertSame(
            [
                ['key' => 'financial_management', 'order' => 0, 'custom_title' => 'Cash Register'],
                ['key' => 'cashier_sales', 'order' => 1, 'custom_title' => 'Sales & Invoices'],
            ],
            $nav['sections']
        );

        $posItem = collect($nav['items'])->firstWhere('key', 'pos');
        $this->assertSame('financial_management', $posItem['section']);
        $this->assertSame(4, $posItem['order']);

        $quotationsItem = collect($nav['items'])->firstWhere('key', 'quotations');
        $this->assertFalse($quotationsItem['visible']);
    }

    public function test_navigation_menu_endpoint_persists_a_canonical_three_level_tree(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $payload = [
            'sections' => [['key' => 'cashier_sales', 'order' => 0]],
            'tree' => [[
                'key' => 'cashier_sales',
                'order' => 0,
                'items' => [[
                    'key' => 'pos',
                    'parent_id' => null,
                    'level' => 0,
                    'order' => 0,
                    'visible' => true,
                    'children' => [[
                        'key' => 'sales',
                        'parent_id' => 'pos',
                        'level' => 1,
                        'order' => 0,
                        'visible' => true,
                        'children' => [[
                            'key' => 'quotations',
                            'parent_id' => 'sales',
                            'level' => 2,
                            'order' => 0,
                            'visible' => false,
                            'children' => [],
                        ]],
                    ]],
                ]],
            ]],
        ];

        $this->postJson(route('tenant.settings.navigation-menu.store'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('nav.items.0.parent_id', null)
            ->assertJsonPath('nav.items.1.parent_id', 'pos')
            ->assertJsonPath('nav.items.1.level', 1)
            ->assertJsonPath('nav.items.2.parent_id', 'sales')
            ->assertJsonPath('nav.items.2.level', 2)
            ->assertJsonPath('nav.tree.0.items.0.children.0.children.0.key', 'quotations');

        $saved = $company->fresh()->normalizedNavConfig();
        $this->assertSame(['pos', 'sales', 'quotations'], array_column($saved['items'], 'key'));
        $this->assertSame([0, 1, 2], array_column($saved['items'], 'level'));
        $this->assertSame('quotations', $saved['tree'][0]['items'][0]['children'][0]['children'][0]['key']);
    }

    public function test_navigation_menu_endpoint_persists_custom_section_title_and_repairs_a_cleared_title(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $payload = [
            'sections' => [[
                'key' => 'cashier_sales',
                'order' => 0,
                'custom_title' => '',
            ]],
            'tree' => [[
                'key' => 'cashier_sales',
                'order' => 0,
                'custom_title' => '',
                'items' => [[
                    'key' => 'pos',
                    'title' => 'Point of Sale',
                    'visible' => true,
                    'children' => [],
                ]],
            ]],
        ];

        $this->postJson(route('tenant.settings.navigation-menu.store'), $payload)
            ->assertOk()
            ->assertJsonPath('nav.sections.0.custom_title', 'Point of Sale')
            ->assertJsonPath('nav.tree.0.custom_title', 'Point of Sale');

        $saved = $company->fresh()->normalizedNavConfig();
        $this->assertSame('Point of Sale', $saved['sections'][0]['custom_title']);

        $payload['sections'][0]['custom_title'] = 'Cashier & Sales';
        $payload['tree'][0]['custom_title'] = 'Cashier & Sales';

        $this->postJson(route('tenant.settings.navigation-menu.store'), $payload)
            ->assertOk()
            ->assertJsonPath('nav.sections.0.custom_title', 'Cashier & Sales');
    }

    /**
     * The tree builder is an external Alpine component. Its serialized
     * payload is passed with @js() so the attribute remains valid HTML even
     * when item labels or keys contain quotes.
     */
    public function test_navigation_tab_x_data_attribute_is_not_broken_by_raw_json_quotes(): void
    {
        $this->actingAsTenantAdmin();

        $html = Livewire::test(SettingsIndex::class)->html();

        $this->assertStringContainsString('x-data="tenantNavigationBuilder(', $html);
        $this->assertStringNotContainsString(
            'sections: {"key":',
            $html,
            'nav_config JSON leaked into the attribute as raw, unescaped double quotes — this breaks the attribute.'
        );

        $this->assertStringContainsString('id="nav-sections-container"', $html);
        $this->assertStringContainsString('x-for="section in sections"', $html);
        $this->assertStringContainsString('x-for="item in flattenedItems(section)"', $html);
        $this->assertStringContainsString('x-model="section.custom_title"', $html);
        $this->assertStringContainsString('x-model="item.visible"', $html);
        $this->assertStringContainsString('class="nav-section-drag-handle', $html);
        $this->assertStringContainsString('class="nav-item-drag-handle', $html);
        $this->assertStringContainsString('data-nav-level', $html);
        $this->assertStringContainsString('nav-depth-guide', $html);
        $this->assertStringNotContainsString('nav-grandchildren-container', $html);
    }

    /**
     * Regression test: forceFallback + fallbackOnBody were tried to fix a
     * cosmetic bug (the drag preview rendered pinned near the page's edge
     * instead of following the cursor), but forceFallback's preview is a
     * raw cloneNode() of the dragged row — it still carries that row's
     * Alpine directives (x-model, :data-item-key, etc.) even though it's
     * no longer inside the x-for scope that gave `item`/`child`/
     * `grandchild` meaning. Per SortableJS's own source, its internal
     * _dragStarted() clones the row *before* dispatching any public event
     * a plugin could hook to strip those directives first — so every
     * attempt to clean up the clone (reactively via onClone/a
     * MutationObserver, or preemptively via onStart) either lost the race
     * against Livewire's own page-load-registered mutation observer or
     * ran too late regardless, and Livewire kept throwing a reference
     * error on every drag frame, expensive enough that dragging looked
     * like it did nothing at all. Native drag-and-drop (SortableJS's
     * default without forceFallback) renders its drag image as a browser
     * snapshot, not a DOM clone, so it's categorically immune — this
     * guards against forceFallback/fallbackOnBody being reintroduced.
     */
    public function test_navigation_tab_does_not_use_sortables_force_fallback_mode(): void
    {
        $this->actingAsTenantAdmin();

        $html = Livewire::test(SettingsIndex::class)->html();
        $script = file_get_contents(public_path('assets/js/tenant-navigation-builder.js'));

        $this->assertIsString($script);
        $this->assertStringNotContainsString('forceFallback: true', $script);
        $this->assertStringNotContainsString('fallbackOnBody: true', $script);
        $this->assertStringContainsString('const TAB_SIZE = 32', $script);
        $this->assertStringContainsString('const MAX_LEVEL = 2', $script);
        $this->assertStringContainsString('Math.round(snappedOffset / TAB_SIZE)', $script);
        $this->assertStringContainsString('maxLevels: MAX_LEVEL + 1', $script);
        $this->assertStringContainsString('isTree: true', $script);
        $this->assertStringContainsString("draggable: '>[data-item-key]'", $script);
        $this->assertStringContainsString("CustomEvent('tenant-navigation-updated'", $script);
        $this->assertStringContainsString('nav-depth-guide', $html);
    }

    /**
     * Regression test: a document-global morph.updated hook survives
     * component navigation and also fires once per changed element for
     * unrelated Livewire components. A stale Alpine scope can therefore
     * attach another Sortable to the current navigation tree, then destroy
     * that instance during an active native drag. SortableJS sets `el` to
     * null in destroy(), but an already-dispatched dragover still reaches
     * _onDragOver and crashes in its lastElementChild helper. Initialization
     * must be component-scoped, idempotent, and guarded by Sortable's global
     * active instance in addition to the local Alpine flag.
     */
    public function test_navigation_tab_does_not_reinit_sortable_instances_mid_drag(): void
    {
        $this->actingAsTenantAdmin();

        $html = Livewire::test(SettingsIndex::class)->html();
        $script = file_get_contents(public_path('assets/js/tenant-navigation-builder.js'));

        $this->assertStringContainsString('id="nav-sections-container"', $html);
        $this->assertIsString($script);
        $this->assertStringContainsString('if (typeof Sortable === \'undefined\' || this.dragging || Sortable.active) return;', $script);
        $this->assertStringContainsString('onStart: () => { this.dragging = true; }', $script);
        $this->assertStringContainsString('whenSortableIdle', $script);
        $this->assertStringNotContainsString('Sortable.active && attempts <', $script);
        $this->assertStringContainsString('this.dragging = false;', $script);
        $this->assertStringContainsString('$wire.$hook(\'morphed\'', $script);
        $this->assertStringNotContainsString('Livewire.hook(\'morph.updated\'', $script);
        $this->assertStringContainsString('const root = this.$root;', $script);
        $this->assertStringContainsString('Sortable.get(element) || Sortable.create(element, options)', $script);

        $dragEnd = substr($script, strpos($script, 'finishItemDrag(event)'));
        $timeoutPos = strpos($dragEnd, 'whenSortableIdle');
        $draggingFalsePos = strpos($dragEnd, 'this.dragging = false;');
        $syncPos = strpos($dragEnd, 'this.syncFromDom();');

        $this->assertNotFalse($timeoutPos);
        $this->assertGreaterThan($timeoutPos, $draggingFalsePos);
        $this->assertGreaterThan($draggingFalsePos, $syncPos);
    }

    /** Ensure the external component invocation is a complete HTML attribute. */
    public function test_navigation_tab_x_data_contains_no_stray_double_quotes(): void
    {
        $this->actingAsTenantAdmin();

        $html = Livewire::test(SettingsIndex::class)->html();

        $start = strpos($html, "x-show=\"activeTab === 'navigation'\"");
        $this->assertNotFalse($start, 'Navigation Menu tab wrapper not found.');
        $dataStart = strpos($html, 'x-data="tenantNavigationBuilder(', $start);
        $this->assertNotFalse($dataStart);
        $attributeEnd = strpos($html, '">', $dataStart);
        $this->assertNotFalse($attributeEnd, 'Navigation builder x-data attribute is not closed.');
        $attribute = substr($html, $dataStart, $attributeEnd - $dataStart + 2);
        $this->assertStringContainsString('tenantNavigationBuilder(', $attribute);
        $this->assertStringContainsString('JSON.parse', $attribute);
        $this->assertStringNotContainsString('x-init=', $attribute);
    }

    public function test_save_nav_config_requires_settings_permission(): void
    {
        [$company, $staff] = $this->actingAsTenantStaff();
        // A staff role without the 'settings' permission by default.
        $this->assertFalse(PermissionChecker::can($staff, 'settings'));

        $response = Livewire::test(SettingsIndex::class)->call('saveNavConfig', [
            ['key' => 'cashier_sales', 'label' => 'Cashier & Sales', 'items' => []],
        ]);

        $response->assertStatus(403);

        $this->postJson(route('tenant.settings.navigation-menu.store'), [
            'sections' => [],
            'items' => [],
        ])->assertForbidden();
    }

    /**
     * A nav_config the mobile tree editor had already mangled — "settings"
     * dropped under "subscription", "settings_taxes" buried under "Financial &
     * Currency", "settings_api" / "settings_navigation" ejected to Main Menu —
     * must self-heal on the next read: "Store Settings" back to Main Menu with
     * all eight tabs as its direct children.
     */
    public function test_a_previously_mangled_settings_tree_self_heals_on_read(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        $company->update(['nav_config' => [
            'sections' => [['key' => 'administration', 'order' => 0]],
            'items' => [
                ['key' => 'subscription', 'section' => 'administration', 'parent' => null, 'order' => 0, 'visible' => true],
                ['key' => 'settings', 'section' => 'administration', 'parent' => 'subscription', 'order' => 1, 'visible' => true],
                ['key' => 'settings_mode', 'section' => 'administration', 'parent' => 'subscription', 'order' => 2, 'visible' => true],
                ['key' => 'settings_profile', 'section' => 'administration', 'parent' => 'subscription', 'order' => 3, 'visible' => true],
                ['key' => 'settings_branding', 'section' => 'administration', 'parent' => 'subscription', 'order' => 4, 'visible' => true],
                ['key' => 'settings_receipts', 'section' => 'administration', 'parent' => 'subscription', 'order' => 5, 'visible' => true],
                ['key' => 'settings_financial', 'section' => 'administration', 'parent' => 'subscription', 'order' => 6, 'visible' => true],
                ['key' => 'settings_taxes', 'section' => 'administration', 'parent' => 'settings_financial', 'order' => 7, 'visible' => true],
                ['key' => 'settings_api', 'section' => 'administration', 'parent' => null, 'order' => 8, 'visible' => true],
                ['key' => 'settings_navigation', 'section' => 'administration', 'parent' => null, 'order' => 9, 'visible' => true],
            ],
        ]]);

        $itemsByKey = collect($company->fresh()->normalizedNavConfig()['items'])->keyBy('key');

        $this->assertNull($itemsByKey['settings']['parent']);
        $this->assertSame(0, $itemsByKey['settings']['level']);
        foreach ([
            'settings_mode', 'settings_profile', 'settings_branding', 'settings_receipts',
            'settings_financial', 'settings_taxes', 'settings_api', 'settings_navigation',
        ] as $tab) {
            $this->assertSame('settings', $itemsByKey[$tab]['parent'], "{$tab} must be a child of settings");
            $this->assertSame(1, $itemsByKey[$tab]['level']);
        }

        auth('web')->user()->unsetRelation('company');
        $sections = Livewire::test(SettingsIndex::class)->viewData('navSections');
        $administration = collect($sections)->firstWhere('key', 'administration');
        $settings = collect($administration['items'])->firstWhere('key', 'settings');
        $childKeys = collect($settings['children'])->pluck('key')->sort()->values()->all();
        $this->assertSame([
            'app_preferences', 'settings_api', 'settings_audio_notifications', 'settings_branding', 'settings_coupons',
            'settings_faqs', 'settings_financial', 'settings_mode', 'settings_navigation', 'settings_payments',
            'settings_profile', 'settings_receipts', 'settings_reviews', 'settings_storefront', 'settings_taxes',
        ], $childKeys);
    }
}
