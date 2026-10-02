<?php

namespace Tests\Feature;

use App\Models\PlatformBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LicenseManagerAppBuilderShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_branding_and_views_do_not_contain_app_builder_showcase_manager(): void
    {
        $branding = PlatformBranding::current();
        $this->assertFalse(method_exists($branding, 'landingAppBuilderShowcase'));

        $studioBlade = file_get_contents(resource_path('views/superadmin/settings/partials/modular-sections-studio.blade.php'));
        $this->assertStringNotContainsString('appBuilderEnabled', $studioBlade);
        $this->assertStringNotContainsString('appBuilderFeatures', $studioBlade);

        $pricingBlade = file_get_contents(resource_path('views/landing/pricing.blade.php'));
        $this->assertStringNotContainsString('landingAppBuilderShowcase', $pricingBlade);
        $this->assertStringNotContainsString('marketing-app-builder-showcase', $pricingBlade);
    }

    public function test_license_manager_central_landing_config_and_api_payload(): void
    {
        $process = new Process(['php', '-r', '
            require_once "lic/lib/bootstrap.php";
            require_once "lic/lib/landing_helper.php";

            $defaultConfig = default_landing_config();
            if (!isset($defaultConfig["app_builder"]) || !$defaultConfig["app_builder"]["enabled"]) {
                exit(1);
            }
            if (count($defaultConfig["app_builder"]["features"]) !== 4) {
                exit(2);
            }

            $payload = build_landing_api_payload();
            if (!isset($payload["app_builder"]) || !$payload["app_builder"]["enabled"]) {
                exit(3);
            }
            if ($payload["app_builder"]["badge"] !== "White-Label Cloud App Builder") {
                exit(4);
            }
            if (count($payload["app_builder"]["features"]) !== 4) {
                exit(5);
            }
            echo "OK";
        '], base_path());
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Error: ' . $process->getErrorOutput());
        $this->assertEquals('OK', trim($process->getOutput()));
    }

    public function test_license_manager_admin_editor_contains_section_8_app_builder_editor(): void
    {
        $editorContent = file_get_contents(base_path('lic/admin/landing.php'));
        $this->assertStringContainsString('Section 8: White-Label Cloud App Builder Showcase Card', $editorContent);
        $this->assertStringContainsString('name="app_builder[enabled]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[badge]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[title]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[subtitle]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[launch_button_text]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[launch_button_url]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[doc_button_text]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[doc_button_url]"', $editorContent);
        $this->assertStringContainsString('app_builder[features]', $editorContent);
        $this->assertStringContainsString('name="app_builder[eligibility_title]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[eligibility_text]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[eligibility_badge]"', $editorContent);
    }

    public function test_marketing_landing_page_renders_showcase_card_and_toggles(): void
    {
        $process = new Process(['php', '-r', '
            require_once "vendor/autoload.php";
            require_once "lic/lib/bootstrap.php";
            require_once "lic/lib/landing_helper.php";

            // 1. Enable and verify
            $cfg = get_landing_config();
            $cfg["app_builder"]["enabled"] = true;
            save_landing_config($cfg);

            $_SERVER["REQUEST_METHOD"] = "GET";
            $_SERVER["HTTP_HOST"] = "localhost";
            $_SERVER["REQUEST_URI"] = "/marketing/index.php";

            ob_start();
            include "public/marketing/index.php";
            $html = ob_get_clean();

            $required = [
                "marketing-app-builder-showcase",
                "White-Label Cloud App Builder",
                "Build Your Branded Mobile &amp; Desktop Apps",
                "Zero-SDK Cloud Compilation",
                "100% White-Label Branding",
                "Multi-Platform Generation",
                "Build History &amp; Email Alerts",
                "Builder Documentation",
                "Launch Builder",
                "Eligibility Note:",
                "Core Script: Included",
            ];
            foreach ($required as $req) {
                if (!str_contains($html, $req)) {
                    echo "MISSING: " . $req . "\n";
                    exit(1);
                }
            }

            // 2. Disable and verify
            $cfg["app_builder"]["enabled"] = false;
            save_landing_config($cfg);

            // Re-render via separate process
            $procDisable = new \Symfony\Component\Process\Process(["php", "-r", "
                require_once \"lic/lib/bootstrap.php\";
                require_once \"lic/lib/landing_helper.php\";
                \$_SERVER[\"REQUEST_METHOD\"] = \"GET\";
                \$_SERVER[\"HTTP_HOST\"] = \"localhost\";
                \$_SERVER[\"REQUEST_URI\"] = \"/marketing/index.php\";
                ob_start();
                include \"public/marketing/index.php\";
                \$h = ob_get_clean();
                echo str_contains(\$h, \"marketing-app-builder-showcase\") ? \"SHOWN\" : \"HIDDEN\";
            "], "' . addslashes(base_path()) . '");
            $procDisable->run();
            if (trim($procDisable->getOutput()) !== "HIDDEN") {
                echo "FAILED TO HIDE WHEN DISABLED: " . $procDisable->getOutput() . "\n";
                exit(2);
            }

            // 3. Reset back to enabled for production
            $cfg["app_builder"]["enabled"] = true;
            save_landing_config($cfg);

            echo "ALL_PASSED";
        '], base_path());
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Process error: ' . $process->getErrorOutput() . ' ' . $process->getOutput());
        $this->assertEquals('ALL_PASSED', trim($process->getOutput()));
    }

    public function test_license_manager_admin_and_api_support_background_color_and_theme_mode(): void
    {
        $editorContent = file_get_contents(base_path('lic/admin/landing.php'));
        $this->assertStringContainsString('name="app_builder[bg_mode]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[bg_color_start]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[bg_color_end]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[border_color]"', $editorContent);
        $this->assertStringContainsString('name="app_builder[accent_color]"', $editorContent);
        $this->assertStringContainsString('updateAbPreview', $editorContent);

        $process = new Process(['php', '-r', '
            require_once "lic/lib/bootstrap.php";
            require_once "lic/lib/landing_helper.php";

            $payload = build_landing_api_payload();
            $ab = $payload["app_builder"];
            if (!isset($ab["bg_mode"]) || $ab["bg_mode"] !== "theme_matching") exit(1);
            if (!isset($ab["bg_color_start"]) || $ab["bg_color_start"] !== "#0d1428") exit(2);
            if (!isset($ab["bg_color_end"]) || $ab["bg_color_end"] !== "#070b1a") exit(3);
            if (!isset($ab["border_color"])) exit(4);
            if (!isset($ab["accent_color"]) || $ab["accent_color"] !== "#3b82f6") exit(5);
            echo "OK";
        '], base_path());
        $process->run();

        $this->assertTrue($process->isSuccessful());
        $this->assertEquals('OK', trim($process->getOutput()));
    }

    public function test_marketing_landing_page_theme_switcher_and_language_switcher(): void
    {
        $process = new Process(['php', '-r', '
            require_once "vendor/autoload.php";
            require_once "public/marketing/translations.php";

            $_SERVER["REQUEST_METHOD"] = "GET";
            $_SERVER["HTTP_HOST"] = "localhost";
            $_SERVER["REQUEST_URI"] = "/marketing/index.php";

            ob_start();
            include "public/marketing/index.php";
            $html = ob_get_clean();

            if (!str_contains($html, "theme-toggle-btn")) exit(1);
            if (!str_contains($html, "lang-dropdown-wrapper")) exit(2);
            if (!str_contains($html, "data-theme=\"dark\"")) exit(3);
            if (!str_contains($html, "ab-mode-theme_matching")) exit(4);
            echo "OK";
        '], base_path());
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertEquals('OK', trim($process->getOutput()));
    }

    public function test_marketing_landing_page_translations_and_rtl_for_arabic(): void
    {
        $process = new Process(['php', '-r', '
            require_once "vendor/autoload.php";
            require_once "public/marketing/translations.php";

            // Spanish test
            $_SERVER["REQUEST_METHOD"] = "GET";
            $_SERVER["HTTP_HOST"] = "localhost";
            $_SERVER["REQUEST_URI"] = "/marketing/index.php?lang=es";
            $_GET["lang"] = "es";

            ob_start();
            include "public/marketing/index.php";
            $htmlEs = ob_get_clean();

            if (!str_contains($htmlEs, "Inicio")) exit(1);
            if (!str_contains($htmlEs, "Precios")) exit(2);
            if (!str_contains($htmlEs, "Creador de Apps en la Nube Marca Blanca")) exit(3);

            echo "ES_OK";
        '], base_path());
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertEquals('ES_OK', trim($process->getOutput()));

        // Arabic test (RTL)
        $procAr = new Process(['php', '-r', '
            require_once "vendor/autoload.php";
            require_once "public/marketing/translations.php";

            $_SERVER["REQUEST_METHOD"] = "GET";
            $_SERVER["HTTP_HOST"] = "localhost";
            $_SERVER["REQUEST_URI"] = "/marketing/index.php?lang=ar";
            $_GET["lang"] = "ar";

            ob_start();
            include "public/marketing/index.php";
            $html = ob_get_clean();

            if (!str_contains($html, "dir=\"rtl\"")) exit(10);
            if (!str_contains($html, "الرئيسية")) exit(11);
            if (!str_contains($html, "منشئ التطبيقات السحابي بعلامتك التجارية")) exit(12);
            echo "AR_OK";
        '], base_path());
        $procAr->run();

        $this->assertTrue($procAr->isSuccessful(), $procAr->getErrorOutput());
        $this->assertEquals('AR_OK', trim($procAr->getOutput()));
    }

    public function test_section_colors_configuration_and_marketing_theme_matching(): void
    {
        $proc = new Process(['php', '-r', '
            require_once "lic/lib/bootstrap.php";
            require_once "lic/lib/landing_helper.php";

            $colors = default_section_colors();
            $expectedKeys = [
                "hero", "category_strip", "features", "business_types",
                "industries", "modules", "demos", "pricing",
                "why_us", "ecosystem", "cta_banner", "faq", "footer"
            ];
            foreach ($expectedKeys as $k) {
                if (!isset($colors[$k]["dark_bg"]) || !isset($colors[$k]["light_bg"])) {
                    echo "MISSING_KEY_$k";
                    exit(1);
                }
            }

            $payload = build_landing_api_payload();
            if (!isset($payload["section_colors"]) || count($payload["section_colors"]) < 13) {
                echo "PAYLOAD_COLORS_INVALID";
                exit(2);
            }

            $_SERVER["REQUEST_METHOD"] = "GET";
            $_SERVER["HTTP_HOST"] = "localhost";
            $_SERVER["REQUEST_URI"] = "/marketing/index.php";
            $_SERVER["SCRIPT_NAME"] = "/marketing/index.php";

            ob_start();
            include "public/marketing/index.php";
            $html = ob_get_clean();

            if (!str_contains($html, "Dynamic Section Background Colors")) {
                echo "MISSING_DYNAMIC_CSS";
                exit(3);
            }
            if (!str_contains($html, "[data-theme=\"dark\"] .ecosystem-section")) {
                echo "MISSING_DARK_ECO";
                exit(4);
            }
            if (!str_contains($html, "[data-theme=\"light\"] .ecosystem-section")) {
                echo "MISSING_LIGHT_ECO";
                exit(5);
            }

            $css = file_get_contents("public/marketing/assets/css/marketing.css");
            if (!str_contains($css, "[data-theme=\"dark\"] .ecosystem-section")) {
                echo "MISSING_CSS_DARK_ECO";
                exit(6);
            }
            if (!str_contains($css, "[data-theme=\"light\"] .modules-overview-section")) {
                echo "MISSING_CSS_LIGHT_MOD";
                exit(7);
            }

            echo "SECTION_COLORS_OK";
        '], base_path());
        $proc->run();

        $this->assertTrue($proc->isSuccessful(), $proc->getErrorOutput());
        $this->assertEquals('SECTION_COLORS_OK', trim($proc->getOutput()));
    }

    public function test_ten_curated_color_presets_and_default_theme_mode_selection(): void
    {
        $proc = new Process(['php', '-r', '
            require_once "lic/lib/landing_helper.php";
            $presets = landing_color_presets();
            if (count($presets) !== 10) {
                echo "PRESETS_COUNT_NOT_10";
                exit(1);
            }

            $expectedKeys = [
                "midnight_obsidian", "royal_navy", "cyber_emerald", "carbon_titanium",
                "deep_amethyst", "oceanic_teal", "crimson_velvet", "amber_charcoal",
                "midnight_indigo", "aurora_ice"
            ];

            foreach ($expectedKeys as $k) {
                if (!isset($presets[$k])) {
                    echo "MISSING_PRESET_" . $k;
                    exit(2);
                }
                $p = $presets[$k];
                if (empty($p["name"]) || empty($p["accent"]) || empty($p["dark"]) || empty($p["light"]) || empty($p["sections"])) {
                    echo "INVALID_PRESET_STRUCT_" . $k;
                    exit(3);
                }
                if (count($p["sections"]) !== 13) {
                    echo "INVALID_SECTIONS_COUNT_" . $k;
                    exit(4);
                }
            }

            // Test payload inclusion
            $payload = build_landing_api_payload();
            if (!isset($payload["color_presets"]) || count($payload["color_presets"]) !== 10) {
                echo "PAYLOAD_PRESETS_INVALID";
                exit(5);
            }
            if (!isset($payload["default_theme_mode"])) {
                echo "PAYLOAD_DEFAULT_MODE_MISSING";
                exit(6);
            }
            if (!isset($payload["active_color_preset"])) {
                echo "PAYLOAD_ACTIVE_PRESET_MISSING";
                exit(7);
            }

            // Test marketing landing page HTML contains palette picker
            $_SERVER["REQUEST_METHOD"] = "GET";
            $_SERVER["HTTP_HOST"] = "localhost";
            $_SERVER["REQUEST_URI"] = "/marketing/index.php";
            $_SERVER["SCRIPT_NAME"] = "/marketing/index.php";

            ob_start();
            include "public/marketing/index.php";
            $html = ob_get_clean();

            if (!str_contains($html, "palette-dropdown-wrapper")) {
                echo "MISSING_PALETTE_DROPDOWN";
                exit(8);
            }
            if (!str_contains($html, "palette-menu-btn")) {
                echo "MISSING_PALETTE_BTN";
                exit(9);
            }
            if (!str_contains($html, "window.POS_COLOR_PRESETS")) {
                echo "MISSING_JS_PRESETS";
                exit(10);
            }
            if (!str_contains($html, "data-preset=\"cyber_emerald\"")) {
                echo "MISSING_PRESET_ITEM";
                exit(11);
            }

            echo "TEN_PRESETS_OK";
        '], base_path());
        $proc->run();

        $this->assertTrue($proc->isSuccessful(), $proc->getErrorOutput());
        $this->assertEquals('TEN_PRESETS_OK', trim($proc->getOutput()));
    }

    public function test_business_types_cards_configuration_and_rendering(): void
    {
        $proc = new Process(['php', '-r', '
            require_once "lic/lib/bootstrap.php";
            require_once "lic/lib/landing_helper.php";

            // 1. Verify helper defaults
            $cards = default_business_types_cards();
            if (count($cards) !== 6) {
                echo "INVALID_CARD_COUNT";
                exit(1);
            }
            if (!isset($cards["pharmacy"], $cards["salon"], $cards["repair"], $cards["crm"])) {
                echo "MISSING_CARD_KEYS";
                exit(2);
            }

            // 2. Verify payload
            $payload = build_landing_api_payload();
            if (empty($payload["business_types_cards"]) || count($payload["business_types_cards"]) !== 6) {
                echo "INVALID_PAYLOAD_CARDS";
                exit(3);
            }

            // 3. Verify Admin Editor markup
            $editor = file_get_contents("lic/admin/landing.php");
            if (!str_contains($editor, "Section 9: Business Types &amp; Verticals Showcase (6 Cards)")) {
                echo "MISSING_ADMIN_SECTION_9";
                exit(4);
            }
            if (!str_contains($editor, "][display_mode]")) {
                echo "MISSING_DISPLAY_MODE_INPUT";
                exit(5);
            }
            if (!str_contains($editor, "][icon_bg]")) {
                echo "MISSING_ICON_BG_INPUT";
                exit(6);
            }

            // 4. Verify marketing page renders photorealistic mockups
            $_SERVER["REQUEST_METHOD"] = "GET";
            $_SERVER["HTTP_HOST"] = "localhost";
            $_SERVER["REQUEST_URI"] = "/marketing/index.php";

            ob_start();
            include "public/marketing/index.php";
            $html = ob_get_clean();

            $expectedImages = [
                "pharmacy-pos-mockup.png",
                "salon-pos-mockup.png",
                "repair-pos-mockup.png",
                "lead-crm-mockup.png",
                "retail-pos-mockup.png",
                "restaurant-pos-mockup.png",
            ];
            foreach ($expectedImages as $img) {
                if (!str_contains($html, $img)) {
                    echo "MISSING_MOCKUP_" . $img;
                    exit(7);
                }
            }

            // 5. Test toggle to icon mode with custom color
            $cfg = get_landing_config();
            $cfg["business_types_cards"]["salon"]["display_mode"] = "icon";
            $cfg["business_types_cards"]["salon"]["icon_bg"] = "#ffe4e6";
            $cfg["business_types_cards"]["salon"]["icon_color"] = "#e11d48";
            $cfg["business_types_cards"]["salon"]["badge_label"] = "Glamour Spa POS";
            save_landing_config($cfg);

            ob_start();
            include "public/marketing/index.php";
            $iconHtml = ob_get_clean();

            if (!str_contains($iconHtml, "Glamour Spa POS")) {
                echo "MISSING_CUSTOM_BADGE_LABEL";
                exit(8);
            }
            if (!str_contains($iconHtml, "background:#ffe4e6")) {
                echo "MISSING_CUSTOM_ICON_BG";
                exit(9);
            }

            // Reset back
            $cfg["business_types_cards"]["salon"]["display_mode"] = "image";
            save_landing_config($cfg);

            echo "BIZ_CARDS_OK";
        '], base_path());
        $proc->run();

        $this->assertTrue($proc->isSuccessful(), $proc->getErrorOutput());
        $this->assertEquals('BIZ_CARDS_OK', trim($proc->getOutput()));
    }

    public function test_app_builder_and_demo_buttons_color_matching_and_mobile_menu(): void
    {
        $proc = new Process(['php', '-r', '
            require_once "vendor/autoload.php";
            require_once "lic/lib/bootstrap.php";
            require_once "lic/lib/landing_helper.php";

            $_SERVER["REQUEST_METHOD"] = "GET";
            $_SERVER["HTTP_HOST"] = "localhost";
            $_SERVER["REQUEST_URI"] = "/marketing/index.php";

            ob_start();
            include "public/marketing/index.php";
            $html = ob_get_clean();

            // 1. App Builder Showcase Classes
            if (!str_contains($html, "ab-mode-theme_matching") || !str_contains($html, "ab-mode-theme-matching")) {
                echo "MISSING_AB_THEME_MATCHING_CLASSES";
                exit(1);
            }

            // 2. Light Theme Rules for App Builder
            if (!str_contains($html, "[data-theme=\"light\"] .marketing-app-builder-showcase.ab-mode-theme_matching")) {
                echo "MISSING_LIGHT_AB_CSS_IN_INDEX";
                exit(2);
            }

            $css = file_get_contents("public/marketing/assets/css/marketing.css");
            if (!str_contains($css, "[data-theme=\"light\"] .marketing-app-builder-showcase.ab-mode-theme_matching")) {
                echo "MISSING_LIGHT_AB_CSS_IN_MARKETING_CSS";
                exit(3);
            }

            // 3. Demo Buttons Color Matching in CSS and HTML
            if (!str_contains($html, ".btn-cta-demo") || !str_contains($html, "var(--btn-sec-bg)")) {
                echo "MISSING_DEMO_BUTTONS_SEC_BG_CSS";
                exit(4);
            }

            // 4. Mobile Menu Elements
            if (!str_contains($html, "mobile-menu-toggle")) {
                echo "MISSING_MOBILE_MENU_TOGGLE";
                exit(5);
            }
            if (!str_contains($html, "nav-drawer-actions")) {
                echo "MISSING_NAV_DRAWER_ACTIONS";
                exit(6);
            }
            if (!str_contains($html, "btn-drawer-demo") || !str_contains($html, "btn-drawer-buy")) {
                echo "MISSING_DRAWER_BUTTONS";
                exit(7);
            }

            // 5. Responsive Breakpoint in CSS
            if (!str_contains($css, "@media (max-width: 991px)") || !str_contains($css, ".btn-drawer-demo")) {
                echo "MISSING_RESPONSIVE_BREAKPOINT_CSS";
                exit(8);
            }

            echo "APP_BUILDER_AND_MOBILE_NAV_OK";
        '], base_path());
        $proc->run();

        $this->assertTrue($proc->isSuccessful(), $proc->getErrorOutput());
        $this->assertEquals('APP_BUILDER_AND_MOBILE_NAV_OK', trim($proc->getOutput()));
    }

    public function test_faq_pricing_dark_light_pattern_and_top_nav_visibility(): void
    {
        $proc = new Process(['php', '-r', '
            require_once "vendor/autoload.php";
            require_once "lic/lib/bootstrap.php";
            require_once "lic/lib/landing_helper.php";

            // 1. Check default landing config has top nav settings and FAQs
            $cfg = default_landing_config();
            if (!isset($cfg["show_top_nav"]) || !$cfg["show_top_nav"]) {
                echo "MISSING_DEFAULT_SHOW_TOP_NAV";
                exit(1);
            }
            if (empty($cfg["faqs"]) || count($cfg["faqs"]) < 6) {
                echo "MISSING_DEFAULT_FAQS";
                exit(2);
            }

            // 2. Check API payload has header_settings and faqs
            $payload = build_landing_api_payload();
            if (!isset($payload["show_top_nav"]) || !isset($payload["header_settings"])) {
                echo "MISSING_API_HEADER_SETTINGS";
                exit(3);
            }
            if (empty($payload["faqs"]) || count($payload["faqs"]) < 6) {
                echo "MISSING_API_FAQS";
                exit(4);
            }

            // 3. Check Admin Landing Page has Section 1B and show_top_nav fields
            $adminCode = file_get_contents("lic/admin/landing.php");
            if (!str_contains($adminCode, "Section 1B: Top Navigation Menu Display & Visibility Options") ||
                !str_contains($adminCode, "name=\"show_top_nav\"") ||
                !str_contains($adminCode, "name=\"sticky_top_nav\"") ||
                !str_contains($adminCode, "name=\"nav_show_language\"")) {
                echo "MISSING_ADMIN_TOP_NAV_FIELDS";
                exit(5);
            }

            // 4. Check CSS rules for pricing dark mode and FAQ
            $css = file_get_contents("public/marketing/assets/css/marketing.css");
            if (!str_contains($css, "[data-theme=\"dark\"] .price-tier-card") ||
                !str_contains($css, "[data-theme=\"dark\"] .how-it-works-card") ||
                !str_contains($css, "[data-theme=\"dark\"] .price-val-wrap") ||
                !str_contains($css, "[data-theme=\"dark\"] .btn-tier-outline")) {
                echo "MISSING_PRICING_DARK_MODE_CSS";
                exit(6);
            }
            if (!str_contains($css, "[data-theme=\"dark\"] .faq-q") ||
                !str_contains($css, "[data-theme=\"dark\"] .faq-a")) {
                echo "MISSING_FAQ_DARK_MODE_CSS";
                exit(7);
            }

            // 5. Test Landing Page Rendering with defaults
            $savedCfg = get_landing_config();
            $savedCfg["show_top_nav"] = true;
            $savedCfg["nav_show_language"] = true;
            save_landing_config($savedCfg);

            $_SERVER["REQUEST_METHOD"] = "GET";
            $_SERVER["HTTP_HOST"] = "localhost";
            $_SERVER["REQUEST_URI"] = "/marketing/index.php";

            ob_start();
            include "public/marketing/index.php";
            $html = ob_get_clean();

            // FAQ section check
            if (!str_contains($html, "id=\"faq\"") || !str_contains($html, "class=\"faq-item\"")) {
                echo "FAQ_SECTION_NOT_RENDERED";
                exit(8);
            }

            // Header presence check
            if (!str_contains($html, "<header class=\"header")) {
                echo "HEADER_NOT_RENDERED";
                exit(9);
            }

            // Pricing cards check
            if (!str_contains($html, "price-tier-card") || !str_contains($html, "how-it-works-card")) {
                echo "PRICING_CARDS_NOT_RENDERED";
                exit(10);
            }

            // 6. Test Show/Hide Top Navigation Toggle
            $savedCfg["show_top_nav"] = false;
            save_landing_config($savedCfg);

            ob_start();
            include "public/marketing/index.php";
            $htmlHidden = ob_get_clean();

            if (str_contains($htmlHidden, "<header class=\"header")) {
                echo "HEADER_NOT_HIDDEN_WHEN_DISABLED";
                exit(11);
            }
            if (!str_contains($htmlHidden, "no-top-nav")) {
                echo "MISSING_NO_TOP_NAV_CLASS";
                exit(12);
            }

            // Restore
            $savedCfg["show_top_nav"] = true;
            save_landing_config($savedCfg);

            echo "FAQ_PRICING_AND_NAV_ALL_TESTS_OK";
        '], base_path());
        $proc->run();

        $this->assertTrue($proc->isSuccessful(), $proc->getErrorOutput());
        $this->assertEquals('FAQ_PRICING_AND_NAV_ALL_TESTS_OK', trim($proc->getOutput()));
    }
}


