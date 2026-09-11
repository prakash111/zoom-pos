<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\SduiModule;
use App\Models\SduiScreen;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Modular\ModuleRegistry;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class SduiViewApiTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::create([
            'name' => 'trial',
            'display_name' => 'Free Trial',
            'price' => 0.00,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'duration_days' => 14,
            'features' => ['pos' => true, 'offline' => true],
            'limits' => ['products' => 500, 'users' => 5],
            'active' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Apex Retail Solutions',
            'trade_name' => 'Apex Mart',
            'slug' => 'apex-mart',
            'email' => 'admin@apexmart.com',
            'country' => 'US',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'plan_name' => 'trial',
            'expires_at' => now()->addDays(14),
            'licensed_modules' => ['retail', 'pharmacy'],
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'admin@apexmart.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
    }

    protected function token(): string
    {
        return $this->postJson('/api/v1/pos/auth/login', [
            'email' => 'admin@apexmart.com',
            'password' => 'secret123',
        ])->json('token');
    }

    public function test_sdui_settings_mode_view_returns_valid_schema(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-mode');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Store Operating Mode')
            ->assertJsonPath('schema.layout', 'scroll_view');

        $components = $response->json('schema.components');
        $this->assertNotEmpty($components);
    }

    public function test_sdui_settings_financial_view_returns_inputs_and_action(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-financial');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Financial & Currency');

        $this->assertStringContainsString('financial', json_encode($response->json()));
    }

    public function test_navigation_view_always_returns_a_populated_recursive_tree(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-navigation')
            ->assertOk()
            ->assertJsonPath('success', true);

        $builder = collect($response->json('schema.components'))
            ->firstWhere('type', 'tree_builder');

        $this->assertIsArray($builder);
        $this->assertNotEmpty($builder['tree_data']);
        $this->assertSame($builder['tree_data'], $builder['sections']);
        $this->assertNotEmpty($builder['nav_config']['items']);
        $this->assertSame('cashier_sales', $builder['tree_data'][0]['key']);
        $this->assertNotEmpty($builder['tree_data'][0]['items']);

        $settings = collect($builder['tree_data'])
            ->flatMap(fn (array $section) => $section['items'])
            ->firstWhere('key', 'settings');
        $this->assertNotEmpty($settings['children']);
        $this->assertSame('settings_profile', $settings['children'][1]['key']);
    }

    public function test_database_authored_navigation_screen_is_hydrated_with_tenant_tree_data(): void
    {
        SduiScreen::create([
            'key' => 'settings-navigation',
            'title' => 'Custom Navigation Header',
            'permission' => 'settings.view',
            'schema' => [
                'layout' => 'scroll_view',
                'components' => [
                    SchemaResponse::text('Custom navigation help'),
                    ['type' => 'tree_builder', 'title' => 'Menu editor'],
                ],
            ],
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-navigation')
            ->assertOk()
            ->assertJsonPath('schema.title', 'Custom Navigation Header');

        $builder = collect($response->json('schema.components'))
            ->firstWhere('type', 'tree_builder');
        $this->assertNotEmpty($builder['tree_data']);
        $this->assertNotEmpty($builder['nav_config']['items']);
    }

    public function test_profile_brand_colors_are_visual_color_picker_components(): void
    {
        $schema = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-branding')
            ->assertOk()
            ->json('schema');

        $componentsByName = [];
        $visit = function (mixed $nodes) use (&$visit, &$componentsByName): void {
            foreach (is_array($nodes) ? $nodes : [] as $node) {
                if (! is_array($node)) {
                    continue;
                }
                if (! empty($node['name'])) {
                    $componentsByName[$node['name']] = $node;
                }
                $visit($node['components'] ?? $node['children'] ?? []);
            }
        };
        $visit($schema['components']);

        foreach (['primary_color', 'accent_color', 'drawer_bg'] as $name) {
            $this->assertSame('color_picker', $componentsByName[$name]['type']);
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $componentsByName[$name]['initial_value']);
        }
        $this->assertSame('Sidebar / Drawer Background', $componentsByName['drawer_bg']['label']);
    }

    public function test_database_authored_profile_screen_upgrades_legacy_color_inputs(): void
    {
        SduiScreen::create([
            'key' => 'settings-profile',
            'title' => 'Custom Store Profile',
            'permission' => 'settings.view',
            'schema' => [
                'layout' => 'scroll_view',
                'components' => [
                    SchemaResponse::textInput('primary_color', 'Primary Accent Color', '#112233'),
                    SchemaResponse::textInput('accent_color', 'Secondary Accent Color', '#445566'),
                    SchemaResponse::textInput('drawer_bg', 'Sidebar / Drawer Background', '#778899'),
                ],
            ],
        ]);

        $components = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-profile')
            ->assertOk()
            ->json('schema.components');

        $this->assertSame(
            ['color_picker', 'color_picker', 'color_picker'],
            array_column($components, 'type')
        );
    }

    public function test_store_profile_country_dropdown_is_the_full_iso_list(): void
    {
        $raw = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-profile')
            ->assertOk()
            ->json('schema');

        $country = null;
        $walk = function ($node) use (&$walk, &$country) {
            if (! is_array($node)) {
                return;
            }
            if (($node['name'] ?? null) === 'country' && ($node['type'] ?? null) === 'dropdown_select') {
                $country = $node;
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($raw);

        $this->assertNotNull($country, 'country dropdown missing from Store Profile');
        $values = array_column($country['options'], 'value');
        $this->assertGreaterThan(180, count($values), 'country list is not the full ISO set');
        foreach (['BR', 'DE', 'NG', 'JP', 'AR', 'ZA'] as $code) {
            $this->assertContains($code, $values, "missing country: {$code}");
        }
    }

    public function test_store_profile_view_is_a_tabbed_screen_with_five_sections(): void
    {
        $schema = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-profile')
            ->assertOk()
            ->assertJsonPath('schema.title', 'Store Profile')
            ->json('schema');

        $tabs = collect($schema['components'])->firstWhere('type', 'tabs');
        $this->assertIsArray($tabs, 'Store Profile is not a tabbed screen');
        $this->assertSame(
            ['General Info', 'Address & Localization', 'Branding & Appearance', 'Receipt & Invoicing', 'Notifications & Sounds'],
            collect($tabs['tabs'])->pluck('label')->all()
        );
        $this->assertTrue($tabs['is_scrollable'] ?? false);

        // Field keys and form_submit endpoints are unchanged — the tabs are a
        // presentational regroup only.
        $body = json_encode($schema, JSON_UNESCAPED_SLASHES);
        foreach (['"name"', '"trade_name"', '"email"', '"phone"', '"website"',
            '"address"', '"city"', '"state"', '"postal_code"', '"country"', '"timezone"',
            '"logo"',
            '"invoice_prefix"', '"quotation_prefix"', '"invoice_terms"', '"bank_details"',
            '"order_sound_preset"', '"order_sound_custom_url"', '"delayed_order_sound"', '"sound_vibration_enabled"'] as $key) {
            $this->assertStringContainsString($key, $body, "profile field {$key} disappeared");
        }
        $this->assertStringContainsString('/api/tenant/settings/profile', $body);
        $this->assertStringContainsString('/api/tenant/settings/receipts', $body);
        $this->assertStringContainsString('/api/tenant/settings/notification-sounds', $body);

        // The tabs themselves carry no icon field (per product decision).
        foreach ($tabs['tabs'] as $tab) {
            $this->assertArrayNotHasKey('icon', $tab);
        }

        // Wizard action buttons: intermediate tabs continue, the last one
        // completes and opens the dashboard.
        $buttonOf = function (array $tab): array {
            foreach ($tab['components'] as $c) {
                if (($c['type'] ?? null) === 'button_primary') {
                    return $c;
                }
            }

            return [];
        };
        $general = $buttonOf($tabs['tabs'][0]);
        $receipts = $buttonOf($tabs['tabs'][3]);
        $final = $buttonOf($tabs['tabs'][4]);
        $this->assertSame('Save & Continue', $general['label']);
        $this->assertSame('arrow_forward', $general['icon']);
        $this->assertSame(0, $general['action']['payload']['wizard_tab_index']);
        // Receipt & Invoicing is no longer the last tab — it now continues.
        $this->assertSame('Save & Continue', $receipts['label']);
        $this->assertSame(3, $receipts['action']['payload']['wizard_tab_index']);
        $this->assertSame('Complete Setup & Open Dashboard', $final['label']);
        $this->assertSame('check_circle', $final['icon']);
        $this->assertSame(4, $final['action']['payload']['wizard_tab_index']);
        $this->assertSame(5, $final['action']['payload']['wizard_total_tabs']);
    }

    public function test_store_profile_wizard_intermediate_save_returns_advance_tab(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/profile', [
                'name' => 'Apex Mart',
                'wizard_tab_index' => 0,
                'wizard_total_tabs' => 4,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('next_action.type', 'ADVANCE_TAB')
            ->assertJsonPath('next_action.target_index', 1)
            ->assertJsonPath('next_action.is_final', false);

        $this->assertNull($response->json('next_action.redirect_url'));
        $this->assertFalse((bool) $this->company->fresh()->is_profile_completed);
    }

    public function test_store_profile_wizard_receipts_save_is_no_longer_final(): void
    {
        $this->assertFalse((bool) $this->company->fresh()->is_profile_completed);

        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/receipts', [
                'invoice_prefix' => 'INV-',
                'wizard_tab_index' => 3,
                'wizard_total_tabs' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('next_action.type', 'ADVANCE_TAB')
            ->assertJsonPath('next_action.is_final', false)
            ->assertJsonPath('next_action.target_index', 4);

        $this->assertFalse((bool) $this->company->fresh()->is_profile_completed);
    }

    public function test_store_profile_wizard_final_save_completes_onboarding_and_redirects(): void
    {
        $this->assertFalse((bool) $this->company->fresh()->is_profile_completed);

        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/notification-sounds', [
                'order_sound_preset' => 'chime',
                'delayed_order_sound' => 'alarm',
                'wizard_tab_index' => 4,
                'wizard_total_tabs' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('next_action.type', 'ADVANCE_TAB')
            ->assertJsonPath('next_action.is_final', true)
            ->assertJsonPath('next_action.target_index', 4)
            ->assertJsonPath('next_action.redirect_url', '/dashboard')
            ->assertJsonPath('is_profile_completed', true)
            ->assertJsonFragment(['message' => 'Store setup completed successfully!']);

        $this->assertTrue((bool) $this->company->fresh()->is_profile_completed);
    }

    public function test_plain_settings_save_carries_no_wizard_directive(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/profile', ['name' => 'Apex Mart'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertArrayNotHasKey('next_action', $response->json());
        $this->assertFalse((bool) $this->company->fresh()->is_profile_completed);
    }

    public function test_store_profile_wizard_does_not_advance_on_validation_failure(): void
    {
        // `name` is required-when-present; an empty string fails validation.
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/profile', [
                'name' => '',
                'wizard_tab_index' => 0,
                'wizard_total_tabs' => 5,
            ])
            ->assertStatus(422);

        $this->assertArrayNotHasKey('next_action', $response->json());
        $this->assertFalse((bool) $this->company->fresh()->is_profile_completed);
    }

    public function test_store_profile_branding_tab_has_no_colour_pickers(): void
    {
        $schema = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-profile')
            ->assertOk()
            ->json('schema');

        $branding = collect(collect($schema['components'])->firstWhere('type', 'tabs')['tabs'])
            ->firstWhere('id', 'branding_appearance');

        $types = [];
        $names = [];
        $walk = function ($node) use (&$walk, &$types, &$names) {
            if (! is_array($node)) {
                return;
            }
            if (isset($node['type'])) {
                $types[] = $node['type'];
            }
            if (isset($node['name'])) {
                $names[] = $node['name'];
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($branding);

        // Colour customisation now lives only under App Preferences.
        $this->assertNotContains('color_picker', $types);
        $this->assertNotContains('primary_color', $names);
        $this->assertNotContains('accent_color', $names);
        // Logo upload is still here and wired to its own endpoint.
        $this->assertContains('file_upload', $types);
        $this->assertContains('logo', $names);
        $brandingJson = json_encode($branding, JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('/api/v1/pos/settings/profile/logo', $brandingJson);

        // The "Theme & Colours" pointer card is gone — logo card, then the
        // wizard button, nothing else.
        $this->assertStringNotContainsString('Theme & Colours', $brandingJson);
        $this->assertStringNotContainsString('Open Branding & Colors', $brandingJson);
        $this->assertStringNotContainsString('/api/tenant/views/settings-branding', $brandingJson);
        $this->assertSame(
            ['card', 'button_primary'],
            array_column($branding['components'], 'type')
        );
    }

    public function test_store_profile_receipt_tab_ends_on_a_continue_button(): void
    {
        $schema = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-profile')
            ->assertOk()
            ->json('schema');

        $receipt = collect(collect($schema['components'])->firstWhere('type', 'tabs')['tabs'])
            ->firstWhere('id', 'receipt_invoicing');

        $receiptJson = json_encode($receipt, JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('Thermal Receipt & Printer Rules', $receiptJson);
        $this->assertStringNotContainsString('Printer & Hardware Setup', $receiptJson);
        $this->assertStringNotContainsString('printer_setup', $receiptJson);

        // Receipt & Invoicing now continues to the Notifications & Sounds
        // tab rather than completing the wizard.
        $components = $receipt['components'];
        $last = end($components);
        $this->assertSame('button_primary', $last['type']);
        $this->assertSame('Save & Continue', $last['label']);
    }

    public function test_store_profile_sounds_tab_ends_on_the_complete_button(): void
    {
        $schema = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-profile')
            ->assertOk()
            ->json('schema');

        $sounds = collect(collect($schema['components'])->firstWhere('type', 'tabs')['tabs'])
            ->firstWhere('id', 'notifications_sounds');

        $this->assertNotNull($sounds, 'notifications_sounds tab missing from Store Profile');

        $components = $sounds['components'];
        $last = end($components);
        $this->assertSame('button_primary', $last['type']);
        $this->assertSame('Complete Setup & Open Dashboard', $last['label']);
    }

    public function test_store_profile_view_tab_query_param_selects_the_initial_tab(): void
    {
        foreach (['general' => 0, 'address' => 1, 'branding' => 2, 'receipts' => 3, 'sounds' => 4] as $param => $index) {
            $tabs = collect($this->withHeader('Authorization', 'Bearer '.$this->token())
                ->getJson("/api/tenant/views/settings-profile?tab={$param}")
                ->assertOk()
                ->json('schema.components'))
                ->firstWhere('type', 'tabs');

            $this->assertSame($index, $tabs['initial_index'], "tab={$param} should open tab index {$index}");
        }
    }

    public function test_app_preferences_view_has_real_controls_not_a_notice(): void
    {
        $body = json_encode($this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-appearance')
            ->assertOk()
            ->json('schema'));

        $this->assertStringNotContainsString('stored on each device', $body);
        $this->assertStringNotContainsString('Theme, animations &', $body);
        // Functional controls are present.
        $this->assertStringContainsString('"type":"color_picker"', $body);
        $this->assertStringContainsString('"name":"primary_color"', $body);
        $this->assertStringContainsString('"name":"app_theme_mode"', $body);
        $this->assertStringContainsString('"name":"app_page_transition"', $body);
        $this->assertStringContainsString('"name":"app_nav_dock"', $body);
    }

    public function test_every_settings_panel_returns_a_valid_versioned_sdui_tree(): void
    {
        $token = $this->token();

        foreach (['mode', 'profile', 'branding', 'receipts', 'financial', 'taxes', 'api', 'navigation', 'appearance'] as $panel) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/tenant/views/settings-'.$panel)
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('schema.type', 'screen')
                ->assertJsonPath('schema.schema_version', SchemaResponse::SCHEMA_VERSION)
                ->assertJsonStructure(['schema' => ['title', 'layout', 'app_bar', 'components']]);
        }
    }

    public function test_sdui_plug_and_play_future_module_view_generates_dynamically(): void
    {
        // A packaged vertical (installed as an sdui_modules row) renders its
        // module view dynamically — no native code, no Flutter build.
        SduiModule::create([
            'name' => 'Pharmacy POS',
            'slug' => 'pharmacy',
            'description' => 'Batches, expiry dates, medicines',
            'icon' => 'medication',
            'source_type' => 'package',
            'features' => [],
            'routes' => [],
            'navigation' => [],
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/pharmacy');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Pharmacy POS');

        $salon = SduiModule::create([
            'name' => 'Salon & Spa',
            'slug' => 'salon',
            'description' => 'Appointments and stylist scheduling.',
            'icon' => 'spa',
            'features' => ['appointments' => true],
            'routes' => ['home' => '/api/tenant/views/salon-spa'],
            'navigation' => [],
            'is_active' => true,
        ]);
        SduiScreen::create([
            'sdui_module_id' => $salon->id,
            'key' => 'salon-spa',
            'title' => 'Salon Workspace',
            'permission' => 'pos.view',
            'schema' => [
                'layout' => 'scroll_view',
                'components' => [
                    SchemaResponse::text('Today’s appointments', 'title_large'),
                ],
            ],
        ]);
        $this->company->update(['licensed_modules' => ['retail', 'pharmacy', 'salon']]);

        // A database-authored future vertical needs no Flutter compile touchpoint.
        $futureResponse = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/salon-spa');

        $futureResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Salon Workspace')
            ->assertJsonPath('schema.schema_version', SchemaResponse::SCHEMA_VERSION);
    }

    public function test_sdui_form_submission_updates_financial_settings(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/financial', [
                'currency' => 'EUR',
                'currency_symbol' => '€',
                'currency_decimals' => 2,
                'currency_symbol_position' => 'suffix',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('EUR', $this->company->fresh()->currency);
        $this->assertSame('€', $this->company->fresh()->currency_symbol);
    }

    public function test_sdui_form_submission_for_mode_returns_403_forbidden(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/mode', [
                'mode' => 'restaurant',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_api_integration_settings_are_persisted_in_tenant_configuration(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->postJson('/api/tenant/settings/api', [
                'webhook_url' => 'https://example.test/order-hook',
                'ai_catalog_enrichment' => false,
                'ai_receipt_ocr' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('configurations', [
            'company_id' => $this->company->id,
            'key' => 'webhook_url',
            'value' => 'https://example.test/order-hook',
        ]);
        $this->assertDatabaseHas('configurations', [
            'company_id' => $this->company->id,
            'key' => 'ai_catalog_enrichment',
            'value' => '0',
        ]);
    }

    public function test_database_registered_module_controls_bootstrap_navigation_and_screen_directory(): void
    {
        $module = SduiModule::create([
            'name' => 'Laundry',
            'slug' => 'laundry',
            'icon' => 'local_laundry_service',
            'features' => ['pickup_tracking' => true],
            'navigation' => [[
                'key' => 'laundry_operations',
                'label' => 'Laundry Operations',
                'color' => '#2563eb',
                'items' => [[
                    'key' => 'laundry_queue',
                    'title' => 'Laundry Queue',
                    'icon' => 'local_laundry_service',
                    'type' => 'link',
                    'target_endpoint' => '/api/tenant/views/laundry-queue',
                    'permission' => 'pos',
                ]],
            ]],
            'is_active' => true,
        ]);
        SduiScreen::create([
            'sdui_module_id' => $module->id,
            'key' => 'laundry-queue',
            'title' => 'Laundry Queue',
            'permission' => 'pos.view',
            'schema' => [
                'layout' => 'column',
                'components' => [SchemaResponse::text('Orders awaiting wash')],
            ],
        ]);
        $this->company->update([
            'pos_mode' => 'laundry',
            'licensed_modules' => ['laundry'],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/app/bootstrap')
            ->assertOk()
            ->assertJsonPath('tenant.active_mode', 'laundry')
            ->assertJsonPath('modules.laundry.source', 'database')
            ->assertJsonPath('menu_structure.0.items.0.target_endpoint', '/api/tenant/views/laundry-queue')
            ->assertJsonFragment([
                'key' => 'laundry-queue',
                'endpoint' => '/api/tenant/views/laundry-queue',
                'permission' => 'pos.view',
            ]);
    }

    public function test_unknown_views_are_not_synthesized_and_invalid_schemas_cannot_be_registered(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/not-registered')
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->expectException(InvalidArgumentException::class);
        SduiScreen::create([
            'key' => 'broken',
            'title' => 'Broken',
            'schema' => [
                'layout' => 'scroll_view',
                'components' => [['type' => 'made_up_widget']],
            ],
        ]);
    }

    public function test_bootstrap_delivers_accordion_navigation_and_target_endpoints(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/app/bootstrap');

        $response->assertOk();
        $response->assertJsonPath('schema_contract.version', SchemaResponse::SCHEMA_VERSION)
            ->assertJsonPath('screens.0.endpoint', '/api/tenant/views/settings-mode');
        $menu = $response->json('menu_structure');
        $this->assertNotEmpty($menu);

        $adminSection = collect($menu)->firstWhere('key', 'administration');
        $this->assertNotNull($adminSection);

        $settingsItem = collect($adminSection['items'])->firstWhere('key', 'settings');
        $this->assertNotNull($settingsItem);
        $this->assertSame('accordion', $settingsItem['type']);
        $this->assertNotEmpty($settingsItem['children']);

        $firstTab = $settingsItem['children'][0];
        $this->assertArrayHasKey('target_endpoint', $firstTab);
        $this->assertSame('/api/tenant/views/settings-mode', $firstTab['target_endpoint']);
    }

    public function test_licensed_module_views_render_valid_sdui_screens(): void
    {
        $token = $this->token();
        $endpoints = [
            'restaurant-tables' => 'Floor Plan & Tables',
            'restaurant-kds' => 'Kitchen Display (KDS)',
            'restaurant-pos' => 'Restaurant POS Terminal',
            'dining-history' => 'KOT Register & Live Orders',
            'pharmacy-batches' => 'Batch & Expiry Manager',
            'pharmacy-prescriptions' => 'Prescriptions Queue',
            'service-calendar' => 'Service Booking Calendar',
            'service-stylists' => 'Specialists & Stylists',
            'service-orders' => 'Service Catalog & Rates',
            'service-catalog' => 'Service Catalog & Rates',
            'service-create' => 'Add New Service',
            'change-password' => 'Change Password',
        ];

        foreach ($endpoints as $viewKey => $expectedTitle) {
            $response = $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson("/api/tenant/views/{$viewKey}");

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('schema.title', $expectedTitle)
                ->assertJsonPath('schema.layout', 'scroll_view');
        }
    }

    public function test_financial_view_exposes_a_payment_methods_manager_entry(): void
    {
        $body = json_encode($this->withHeader('Authorization', 'Bearer '.$this->token())
            ->getJson('/api/tenant/views/settings-financial')
            ->assertOk()
            ->json(), JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('Manage Payment Methods', $body);
        $this->assertStringContainsString('/api/tenant/views/settings-payment-methods', $body);
    }

    public function test_payment_methods_view_and_create_view_render_valid_schemas(): void
    {
        $token = $this->token();

        // Seed one real method + one disabled so the per-row card path
        // (icon presentation, Edit / toggle / Remove buttons) is exercised.
        PaymentMethod::create(['company_id' => $this->company->id, 'name' => 'UPI / QR', 'code' => 'upi', 'is_active' => true, 'order_index' => 1]);
        PaymentMethod::create(['company_id' => $this->company->id, 'name' => 'Old Wallet', 'code' => 'wallet', 'is_active' => false, 'order_index' => 2]);

        $list = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tenant/views/settings-payment-methods');
        $list->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Payment Methods')
            ->assertJsonPath('schema.schema_version', SchemaResponse::SCHEMA_VERSION);
        $listBody = json_encode($list->json(), JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('+ Add Payment Method', $listBody);
        $this->assertStringContainsString('UPI / QR', $listBody);
        $this->assertStringContainsString('/api/tenant/settings/payment-methods/', $listBody); // edit-sheet / toggle / delete endpoints
        $this->assertStringContainsString('Disabled', $listBody);

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tenant/views/payment-method-create');
        $create->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('schema.title', 'Add Payment Method');
        $createBody = json_encode($create->json(), JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('/api/tenant/settings/payment-methods', $createBody);
        $this->assertStringContainsString('metadata[upi_id]', $createBody);
    }

    public function test_payment_methods_view_is_listed_in_the_screen_directory(): void
    {
        $keys = collect(SchemaResponse::screenDirectory($this->company))->pluck('key');
        $this->assertTrue($keys->contains('settings-payment-methods'));
    }

    public function test_add_payment_method_via_sdui_endpoint_persists_and_reaches_every_module(): void
    {
        $token = $this->token();

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tenant/settings/payment-methods', [
                'name' => 'UPI / QR',
                'code' => 'upi_qr',
                'description' => 'Scan & pay',
                'is_active' => true,
                'order_index' => 9,
                'metadata' => ['upi_id' => 'store@upi', 'bank_name' => '', 'account_no' => ''],
            ]);

        $create->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('payment_methods', [
            'company_id' => $this->company->id,
            'code' => 'upi_qr',
            'name' => 'UPI / QR',
            'is_active' => true,
        ]);

        // Blank metadata keys are dropped; the real one is kept.
        $pm = PaymentMethod::where('company_id', $this->company->id)->where('code', 'upi_qr')->firstOrFail();
        $this->assertSame(['upi_id' => 'store@upi'], $pm->metadata);

        // "Globally for all modules": the shared schema every module's POS /
        // app bootstrap reads now contains the tenant's method (previously it
        // always fell back to hardcoded presets because of an ORDER BY on a
        // non-existent column).
        $codes = collect(ModuleRegistry::paymentMethodsSchema($this->company->fresh()))
            ->pluck('code');
        $this->assertTrue($codes->contains('upi_qr'), 'Custom tender missing from the global payment schema.');

        $bootstrap = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/app/bootstrap');
        $this->assertStringContainsString('upi_qr', json_encode($bootstrap->json('ui_schema.payment_methods')));
    }

    public function test_payment_method_edit_sheet_renders_and_update_toggle_delete_work(): void
    {
        $token = $this->token();
        $pm = PaymentMethod::create([
            'company_id' => $this->company->id,
            'name' => 'Bank Transfer',
            'code' => 'bank',
            'is_active' => true,
            'order_index' => 5,
        ]);

        $sheet = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/tenant/settings/payment-methods/{$pm->id}/edit-sheet");
        $sheet->assertOk()->assertJsonPath('title', 'Edit Bank Transfer');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/tenant/settings/payment-methods/{$pm->id}", ['name' => 'Bank Wire', 'code' => 'bank'])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame('Bank Wire', $pm->fresh()->name);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/tenant/settings/payment-methods/{$pm->id}/toggle")
            ->assertOk();
        $this->assertFalse((bool) $pm->fresh()->is_active);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/tenant/settings/payment-methods/{$pm->id}/delete")
            ->assertOk();
        $this->assertDatabaseMissing('payment_methods', ['id' => $pm->id]);
    }

    public function test_taxes_view_is_a_tabbed_screen_with_config_saved_and_add_tabs(): void
    {
        $token = $this->token();

        $rule = TaxRule::create([
            'company_id' => $this->company->id, 'tax_name' => 'Standard VAT', 'rate' => 18,
            'is_default' => true, 'active' => true, 'type' => 'percentage', 'calc_type' => 'exclusive',
        ]);

        $schema = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/tenant/views/settings-taxes')
            ->assertOk()
            ->assertJsonPath('schema.title', 'Taxes & Compliance')
            ->json('schema');

        $tabs = collect($schema['components'])->firstWhere('type', 'tabs');
        $this->assertIsArray($tabs);
        $this->assertSame(
            ['Tax Configuration', 'Saved Tax Rules', 'Add New Tax Rule'],
            collect($tabs['tabs'])->pluck('label')->all()
        );
        $this->assertTrue($tabs['is_scrollable'] ?? false);

        $body = json_encode($schema, JSON_UNESCAPED_SLASHES);
        // Config tab keeps the fiscal settings form + save.
        $this->assertStringContainsString('Fiscal Tax Configuration', $body);
        $this->assertStringContainsString('/api/tenant/settings/taxes', $body);
        // Saved tab lists the rule with its row actions + a cross-tab add link.
        $this->assertStringContainsString('Saved Tax Rules', $body);
        $this->assertStringContainsString('+ Add New Tax Rule', $body);
        $this->assertStringContainsString('/api/tenant/views/settings-taxes?tab=add', $body);
        $this->assertStringContainsString('Standard VAT', $body);
        $this->assertStringContainsString("/api/tenant/settings/tax-rules/{$rule->id}/edit-sheet", $body);
        $this->assertStringContainsString("/api/tenant/settings/tax-rules/{$rule->id}/toggle", $body);
        // Add tab has the country auto-seed action + the manual form.
        $this->assertStringContainsString('/api/tenant/settings/tax-rules/seed-country', $body);
        $this->assertStringContainsString('Auto-add', $body);
        $this->assertStringContainsString('/api/tenant/settings/tax-rules', $body);
    }

    public function test_taxes_view_tab_query_param_selects_the_initial_tab(): void
    {
        $token = $this->token();

        foreach (['config' => 0, 'saved' => 1, 'add' => 2] as $param => $index) {
            $tabs = collect($this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson("/api/tenant/views/settings-taxes?tab={$param}")
                ->assertOk()
                ->json('schema.components'))
                ->firstWhere('type', 'tabs');

            $this->assertSame($index, $tabs['initial_index'], "tab={$param} should open tab index {$index}");
        }
    }

    public function test_auto_add_country_tax_rules_seeds_the_jurisdiction_presets(): void
    {
        $token = $this->token();
        $this->company->update(['country' => 'GB']);

        $this->assertDatabaseCount('tax_rules', 0);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tenant/settings/tax-rules/seed-country', ['country' => 'GB'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('country', 'GB');

        $rules = TaxRule::where('company_id', $this->company->id)->get();
        $this->assertGreaterThan(0, $rules->count());
        $this->assertTrue($rules->every(fn ($r) => $r->country === 'GB'));
        $this->assertSame(1, $rules->where('is_default', true)->count());
    }

    public function test_tax_rule_crud_through_the_tenant_settings_endpoints(): void
    {
        $token = $this->token();

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/tenant/settings/tax-rules', [
                'name' => 'City Sales Tax', 'rate' => 8.25, 'is_default' => true, 'active' => true,
            ]);
        $create->assertOk()->assertJsonPath('success', true);

        $rule = TaxRule::where('company_id', $this->company->id)->where('tax_name', 'City Sales Tax')->firstOrFail();
        $this->assertTrue((bool) $rule->is_default);

        $sheet = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/tenant/settings/tax-rules/{$rule->id}/edit-sheet");
        $sheet->assertOk()->assertJsonPath('title', 'Edit City Sales Tax');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/tenant/settings/tax-rules/{$rule->id}", ['name' => 'City & State Tax', 'rate' => 9.5])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame('City & State Tax', $rule->fresh()->tax_name);
        $this->assertSame('9.500', (string) $rule->fresh()->rate);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/tenant/settings/tax-rules/{$rule->id}/toggle")
            ->assertOk();
        $this->assertFalse((bool) $rule->fresh()->active);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/tenant/settings/tax-rules/{$rule->id}/delete")
            ->assertOk();
        $this->assertDatabaseMissing('tax_rules', ['id' => $rule->id]);
    }
}
