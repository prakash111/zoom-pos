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
    }

    /**
     * Regression test for a reported bug: dragging an item within the
     * builder rendered the SortableJS fallback drag-clone pinned near the
     * page's left edge, escaping the card into the sidebar gap, instead of
     * following the cursor — caused by a transformed ancestor elsewhere in
     * the shared tenant layout hijacking the clone's `position: fixed`
     * containing block. `fallbackOnBody: true` re-parents the clone onto
     * <body>, sidestepping any such ancestor — see initSortables()'s
     * `commonSortableOptions`. Also guards the indent/outdent drop-zone
     * geometry: `.nav-children-container` must carry a real `ml-8` margin
     * (which narrows its own hoverable box relative to the full-width root
     * list, letting SortableJS's own collision detection tell "dragged
     * right past the indent" from "dragged left past it" apart) rather than
     * just inner padding, which wouldn't narrow the box at all.
     */
    public function test_navigation_tab_configures_sortable_for_reliable_indent_outdent_dragging(): void
    {
        $this->actingAsTenantAdmin();

        $html = Livewire::test(SettingsIndex::class)->html();

        $this->assertStringContainsString('forceFallback: true', $html);
        $this->assertStringContainsString('fallbackOnBody: true', $html);
        $this->assertStringContainsString('class="nav-children-container ml-8', $html);
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
