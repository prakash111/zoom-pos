<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Settings\Index as SettingsIndex;
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
                    && collect($cashierSales['items'])->pluck('key')->all() === ['pos', 'sales', 'quotations', 'customers']
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
    public function test_navigation_tab_nests_the_eight_settings_tabs_under_store_settings_by_default(): void
    {
        $this->actingAsTenantAdmin();

        Livewire::test(SettingsIndex::class)
            ->assertViewHas('navSections', function (array $sections) {
                $administration = collect($sections)->firstWhere('key', 'administration');
                $settings = collect($administration['items'])->firstWhere('key', 'settings');

                $childKeys = collect($settings['children'] ?? [])->pluck('key')->all();

                // The eight tabs must be nested under "settings", not also
                // duplicated as separate root-level administration items.
                $rootKeys = collect($administration['items'])->pluck('key')->all();

                return $childKeys === [
                    'settings_mode', 'settings_profile', 'settings_receipts', 'settings_financial',
                    'settings_taxes', 'settings_api', 'settings_notifications', 'settings_navigation',
                ] && ! in_array('settings_mode', $rootKeys, true);
            });
    }

    public function test_save_nav_config_persists_nested_children_and_supports_un_nesting(): void
    {
        [$company] = $this->actingAsTenantAdmin();

        // A realistic save always round-trips the *entire* current tree (the
        // Alpine builder's `sections` state, seeded from buildNavSections()),
        // not just the items an admin touched — so every compiled item that
        // was on-screen must be present here too, just as the browser would
        // submit it after dragging "settings_navigation" out to the root.
        $payload = [
            [
                'key' => 'administration',
                'label' => 'Administration & Settings',
                'items' => [
                    ['key' => 'subscription', 'label' => 'Subscription & Billing', 'visible' => true, 'children' => []],
                    [
                        'key' => 'settings',
                        'label' => 'Store Settings',
                        'visible' => true,
                        'children' => [
                            ['key' => 'settings_mode', 'label' => 'Store Operating Mode', 'visible' => true],
                            ['key' => 'settings_profile', 'label' => 'Store Profile & Branding', 'visible' => true],
                            ['key' => 'settings_receipts', 'label' => 'Receipt Prefixes & Bank Terms', 'visible' => true],
                            ['key' => 'settings_financial', 'label' => 'Financial & Currency', 'visible' => true],
                            ['key' => 'settings_taxes', 'label' => 'Taxes & Compliance', 'visible' => true],
                            ['key' => 'settings_api', 'label' => 'API & Integrations', 'visible' => true],
                            ['key' => 'settings_notifications', 'label' => 'Notification & Dispatch', 'visible' => true],
                            // "settings_navigation" dragged out below, not listed here.
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

        $this->assertSame('settings', $itemsByKey['settings_profile']['parent']);
        $this->assertNull($itemsByKey['settings_navigation']['parent']);
        $this->assertSame('administration', $itemsByKey['settings_navigation']['section']);

        // Reloading the builder must reflect the un-nesting: "settings_profile"
        // stays a child of "settings", "settings_navigation" is back at root.
        // actingAs() keeps the same auth User instance alive for the rest of
        // the test, and Eloquent memoizes its ->company relation on first
        // access (mount() above already triggered that) — a real page reload
        // re-resolves the user from scratch, so drop the cached relation here
        // to reproduce that instead of reading pre-save data back.
        auth('web')->user()->unsetRelation('company');
        $sections = Livewire::test(SettingsIndex::class)->viewData('navSections');
        $administration = collect($sections)->firstWhere('key', 'administration');
        $settings = collect($administration['items'])->firstWhere('key', 'settings');
        $this->assertContains('settings_profile', collect($settings['children'])->pluck('key')->all());
        $this->assertNotContains('settings_navigation', collect($settings['children'])->pluck('key')->all());
        $this->assertContains('settings_navigation', collect($administration['items'])->pluck('key')->all());
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
            [['key' => 'financial_management', 'order' => 0], ['key' => 'cashier_sales', 'order' => 1]],
            $nav['sections']
        );

        $posItem = collect($nav['items'])->firstWhere('key', 'pos');
        $this->assertSame('financial_management', $posItem['section']);
        $this->assertSame(4, $posItem['order']);

        $quotationsItem = collect($nav['items'])->firstWhere('key', 'quotations');
        $this->assertFalse($quotationsItem['visible']);
    }

    /**
     * Regression test: @json() inside a double-quoted x-data="..." attribute
     * breaks on the JSON's own double quotes, prematurely closing the
     * attribute — everything after that point (the rest of the Alpine
     * component's JS) then renders as literal, visible page text instead of
     * being parsed as an attribute. @js() (Illuminate\Support\Js) escapes
     * quotes to "/' specifically so its output is safe to embed in
     * either a single- or double-quoted HTML attribute.
     */
    public function test_navigation_tab_x_data_attribute_is_not_broken_by_raw_json_quotes(): void
    {
        $this->actingAsTenantAdmin();

        $html = Livewire::test(SettingsIndex::class)->html();

        $this->assertStringContainsString('x-data="{', $html);
        $this->assertStringNotContainsString(
            'sections: {"key":',
            $html,
            'nav_config JSON leaked into the attribute as raw, unescaped double quotes — this breaks the attribute.'
        );

        // The rest of the Alpine component's JS must stay inside the
        // attribute, not spill out as visible page text.
        $this->assertStringNotContainsString('>{ try { s.destroy(); }', $html);
        $this->assertStringContainsString('initSortables()', $html);

        // The template markup that actually renders the section/item rows
        // must follow as real (child) HTML, not get swallowed into a
        // mis-parsed tag the way the broken attribute did.
        $this->assertStringContainsString('id="nav-sections-container"', $html);
        $this->assertStringContainsString('x-for="section in sections"', $html);
        $this->assertStringContainsString('x-for="item in section.items"', $html);
        $this->assertStringContainsString('x-model="item.visible"', $html);
        $this->assertStringContainsString('class="nav-section-drag-handle', $html);
        $this->assertStringContainsString('class="nav-item-drag-handle', $html);

        // Sub-Sub-Menu level (WordPress-style three-level nesting): a
        // Sub-Menu row must render its own nested grandchildren list.
        $this->assertStringContainsString('x-for="child in item.children"', $html);
        $this->assertStringContainsString('x-for="grandchild in child.children"', $html);
        $this->assertStringContainsString('nav-grandchildren-container', $html);
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

        $this->assertStringNotContainsString('forceFallback: true', $html);
        $this->assertStringNotContainsString('fallbackOnBody: true', $html);
        $this->assertStringContainsString('class="nav-children-container ml-8', $html);
    }

    /**
     * Regression test: this page also polls for desktop-sync-status
     * (livewire:tenant.desktop-sync-status), and Livewire's morph.updated
     * hook fires for *any* Livewire request completing anywhere on the
     * page, not just one scoped to this tab. Without a guard,
     * initSortables() destroying and recreating every Sortable instance
     * whenever that hook fires — including mid-drag, if a poll happens to
     * land while the user is dragging — leaves a native `dragover` event
     * still firing against an instance that was just destroy()'d, whose
     * own `el` reference is now null: SortableJS's internal _onDragOver
     * throws trying to read a property off it, repeatedly, until the drag
     * ends. initSortables() must skip re-running while a drag is active.
     */
    public function test_navigation_tab_does_not_reinit_sortable_instances_mid_drag(): void
    {
        $this->actingAsTenantAdmin();

        $html = Livewire::test(SettingsIndex::class)->html();

        $this->assertStringContainsString('if (this.dragging) return;', $html);
        $this->assertStringContainsString('onStart: () => { this.dragging = true; }', $html);
        $this->assertStringContainsString('this.dragging = false;', $html);
    }

    /**
     * Regression test: a stray literal `"` anywhere inside the Navigation
     * Menu tab's `x-data="{ ... }"` — even inside a `//` JS comment, not
     * just templated data — prematurely closes the double-quoted HTML
     * attribute, spilling the rest of initSortables()/syncFromDom()/save()
     * onto the page as visible text (this bug has now recurred twice: once
     * from unescaped @json() data, once from a comment literally containing
     * the word "in" in quotes). @js() only protects templated data, not
     * hand-written JS/comments in the template itself, so this asserts the
     * invariant directly: nothing between the tab's `x-data="{` and its
     * matching `x-init="` may contain a raw double-quote character.
     */
    public function test_navigation_tab_x_data_contains_no_stray_double_quotes(): void
    {
        $this->actingAsTenantAdmin();

        $html = Livewire::test(SettingsIndex::class)->html();

        $start = strpos($html, "x-show=\"activeTab === 'navigation'\"");
        $this->assertNotFalse($start, 'Navigation Menu tab wrapper not found.');
        $dataStart = strpos($html, 'x-data="{', $start);
        $this->assertNotFalse($dataStart);
        $initStart = strpos($html, 'x-init="', $dataStart);
        $this->assertNotFalse($initStart);

        // Everything from just after x-data=" up to (not including) x-init="
        // — trimmed of trailing whitespace, that must end in the attribute's
        // own closing quote, which is excluded below since it isn't part of
        // the JS body itself.
        $beforeInit = rtrim(substr($html, $dataStart + strlen('x-data="'), $initStart - ($dataStart + strlen('x-data="'))));
        $this->assertStringEndsWith('"', $beforeInit, 'x-data attribute does not close properly right before x-init.');
        $xDataBody = substr($beforeInit, 0, -1);

        $this->assertStringNotContainsString(
            '"',
            $xDataBody,
            'A raw double-quote inside x-data breaks out of the HTML attribute, leaking the rest of the JS onto the page as visible text.'
        );
    }

    public function test_save_nav_config_requires_settings_permission(): void
    {
        [$company, $staff] = $this->actingAsTenantStaff();
        // A staff role without the 'settings' permission by default.
        $this->assertFalse(\App\Services\Auth\PermissionChecker::can($staff, 'settings'));

        $response = Livewire::test(SettingsIndex::class)->call('saveNavConfig', [
            ['key' => 'cashier_sales', 'label' => 'Cashier & Sales', 'items' => []],
        ]);

        $response->assertStatus(403);
    }
}
