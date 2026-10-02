<?php

require_once __DIR__.'/../lib/bootstrap.php';
require_once __DIR__.'/_guard.php';
require_once __DIR__.'/_layout.php';
require_schema_web();

$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $curr = get_landing_config();

        // Branding
        $curr['brand_name'] = trim($_POST['brand_name'] ?? $curr['brand_name']);
        $curr['brand_tagline'] = trim($_POST['brand_tagline'] ?? $curr['brand_tagline']);
        $curr['support_email'] = trim($_POST['support_email'] ?? $curr['support_email']);
        $curr['currency_code'] = strtoupper(substr(trim($_POST['currency_code'] ?? 'USD'), 0, 3));
        $curr['currency_symbol'] = trim($_POST['currency_symbol'] ?? '$');

        // Top Navigation Menu Settings
        $curr['show_top_nav'] = !empty($_POST['show_top_nav']);
        $curr['sticky_top_nav'] = !empty($_POST['sticky_top_nav']);
        $curr['nav_show_brand'] = !empty($_POST['nav_show_brand']);
        $curr['nav_show_links'] = !empty($_POST['nav_show_links']);
        $curr['nav_show_language'] = !empty($_POST['nav_show_language']);
        $curr['nav_show_themes'] = !empty($_POST['nav_show_themes']);
        $curr['nav_show_dark_toggle'] = !empty($_POST['nav_show_dark_toggle']);
        $curr['nav_show_demo_btn'] = !empty($_POST['nav_show_demo_btn']);
        $curr['nav_show_buy_btn'] = !empty($_POST['nav_show_buy_btn']);

        // Hero
        $curr['hero_badge'] = trim($_POST['hero_badge'] ?? $curr['hero_badge']);
        $curr['hero_title'] = trim($_POST['hero_title'] ?? $curr['hero_title']);
        $curr['hero_subtitle'] = trim($_POST['hero_subtitle'] ?? $curr['hero_subtitle']);
        $curr['cta_primary'] = trim($_POST['cta_primary'] ?? $curr['cta_primary']);
        $curr['cta_secondary'] = trim($_POST['cta_secondary'] ?? $curr['cta_secondary']);
        $curr['cta_verify'] = trim($_POST['cta_verify'] ?? $curr['cta_verify']);

        // Multi-Platform Demos & Clients
        $curr['demo_flutter_web_url'] = trim($_POST['demo_flutter_web_url'] ?? $curr['demo_flutter_web_url'] ?? '');
        $curr['demo_flutter_web_title'] = trim($_POST['demo_flutter_web_title'] ?? $curr['demo_flutter_web_title'] ?? 'Flutter Web POS');
        $curr['demo_flutter_web_desc'] = trim($_POST['demo_flutter_web_desc'] ?? $curr['demo_flutter_web_desc'] ?? '');

        $curr['demo_flutter_windows_url'] = trim($_POST['demo_flutter_windows_url'] ?? $curr['demo_flutter_windows_url'] ?? '');
        $curr['demo_flutter_windows_title'] = trim($_POST['demo_flutter_windows_title'] ?? $curr['demo_flutter_windows_title'] ?? 'Flutter Windows Desktop App');
        $curr['demo_flutter_windows_desc'] = trim($_POST['demo_flutter_windows_desc'] ?? $curr['demo_flutter_windows_desc'] ?? '');

        $curr['demo_flutter_android_url'] = trim($_POST['demo_flutter_android_url'] ?? $curr['demo_flutter_android_url'] ?? '');
        $curr['demo_flutter_android_title'] = trim($_POST['demo_flutter_android_title'] ?? $curr['demo_flutter_android_title'] ?? 'Flutter Android POS App');
        $curr['demo_flutter_android_desc'] = trim($_POST['demo_flutter_android_desc'] ?? $curr['demo_flutter_android_desc'] ?? '');

        $curr['demo_admin_url'] = trim($_POST['demo_admin_url'] ?? $curr['demo_admin_url']);
        $curr['demo_admin_title'] = trim($_POST['demo_admin_title'] ?? $curr['demo_admin_title'] ?? 'SuperAdmin SaaS Portal');
        $curr['demo_admin_desc'] = trim($_POST['demo_admin_desc'] ?? $curr['demo_admin_desc'] ?? '');

        $curr['demo_store_url'] = trim($_POST['demo_store_url'] ?? $curr['demo_store_url']);
        $curr['demo_store_title'] = trim($_POST['demo_store_title'] ?? $curr['demo_store_title'] ?? 'Store & Cashier Backoffice');
        $curr['demo_store_desc'] = trim($_POST['demo_store_desc'] ?? $curr['demo_store_desc'] ?? '');

        $curr['documentation_url'] = trim($_POST['documentation_url'] ?? $curr['documentation_url']);
        $curr['documentation_title'] = trim($_POST['documentation_title'] ?? $curr['documentation_title'] ?? 'Documentation & Setup Guide');
        $curr['documentation_desc'] = trim($_POST['documentation_desc'] ?? $curr['documentation_desc'] ?? '');

        // Custom Other Demo Links
        $cleanedOtherLinks = [];
        if (!empty($_POST['demo_other_links']) && is_array($_POST['demo_other_links'])) {
            foreach ($_POST['demo_other_links'] as $ol) {
                $t = trim($ol['title'] ?? '');
                $u = trim($ol['url'] ?? '');
                if ($t !== '' && $u !== '') {
                    $cleanedOtherLinks[] = [
                        'title' => $t,
                        'desc' => trim($ol['desc'] ?? ''),
                        'url' => $u,
                        'icon' => trim($ol['icon'] ?? '🔗'),
                        'badge' => trim($ol['badge'] ?? 'Custom Demo'),
                        'btn_text' => trim($ol['btn_text'] ?? 'Open Demo ↗'),
                    ];
                }
            }
        }
        $curr['demo_other_links'] = $cleanedOtherLinks;

        // Metrics
        $curr['metric_1_val'] = trim($_POST['metric_1_val'] ?? $curr['metric_1_val']);
        $curr['metric_1_label'] = trim($_POST['metric_1_label'] ?? $curr['metric_1_label']);
        $curr['metric_2_val'] = trim($_POST['metric_2_val'] ?? $curr['metric_2_val']);
        $curr['metric_2_label'] = trim($_POST['metric_2_label'] ?? $curr['metric_2_label']);
        $curr['metric_3_val'] = trim($_POST['metric_3_val'] ?? $curr['metric_3_val']);
        $curr['metric_3_label'] = trim($_POST['metric_3_label'] ?? $curr['metric_3_label']);
        $curr['metric_4_val'] = trim($_POST['metric_4_val'] ?? $curr['metric_4_val']);
        $curr['metric_4_label'] = trim($_POST['metric_4_label'] ?? $curr['metric_4_label']);

        // Discounts
        $curr['discount_tier_1'] = max(0, min(100, (int) ($_POST['discount_tier_1'] ?? 10)));
        $curr['discount_tier_2'] = max(0, min(100, (int) ($_POST['discount_tier_2'] ?? 15)));
        $curr['discount_tier_3'] = max(0, min(100, (int) ($_POST['discount_tier_3'] ?? 20)));

        // Features (6 items)
        if (!empty($_POST['features']) && is_array($_POST['features'])) {
            $cleanedFeatures = [];
            foreach ($_POST['features'] as $f) {
                if (!empty($f['title'])) {
                    $cleanedFeatures[] = [
                        'icon' => trim($f['icon'] ?? '⚡'),
                        'title' => trim($f['title']),
                        'desc' => trim($f['desc'] ?? ''),
                    ];
                }
            }
            if (!empty($cleanedFeatures)) {
                $curr['features'] = $cleanedFeatures;
            }
        }

        // FAQs
        if (!empty($_POST['faqs']) && is_array($_POST['faqs'])) {
            $cleanedFaqs = [];
            foreach ($_POST['faqs'] as $faq) {
                if (!empty($faq['q'])) {
                    $cleanedFaqs[] = [
                        'q' => trim($faq['q']),
                        'a' => trim($faq['a'] ?? ''),
                    ];
                }
            }
            if (!empty($cleanedFaqs)) {
                $curr['faqs'] = $cleanedFaqs;
            }
        }

        // White-Label Cloud App Builder Showcase Section
        $defBuilder = default_landing_config()['app_builder'];
        $postedBuilder = $_POST['app_builder'] ?? [];
        $builderFeatures = [];
        if (!empty($postedBuilder['features']) && is_array($postedBuilder['features'])) {
            foreach ($postedBuilder['features'] as $f) {
                if (is_array($f) && (!empty($f['title']) || !empty($f['body']))) {
                    $builderFeatures[] = [
                        'icon' => trim((string) ($f['icon'] ?? '⚡')) ?: '⚡',
                        'title' => trim((string) ($f['title'] ?? '')),
                        'body' => trim((string) ($f['body'] ?? '')),
                        'badge' => trim((string) ($f['badge'] ?? '')),
                        'color' => trim((string) ($f['color'] ?? 'blue')),
                    ];
                }
            }
        }
        if (empty($builderFeatures)) {
            $builderFeatures = $defBuilder['features'];
        }

        $curr['app_builder'] = [
            'enabled' => !empty($postedBuilder['enabled']),
            'badge' => trim((string) ($postedBuilder['badge'] ?? $defBuilder['badge'])),
            'title' => trim((string) ($postedBuilder['title'] ?? $defBuilder['title'])),
            'subtitle' => trim((string) ($postedBuilder['subtitle'] ?? $defBuilder['subtitle'])),
            'doc_button_text' => trim((string) ($postedBuilder['doc_button_text'] ?? $defBuilder['doc_button_text'])),
            'doc_button_url' => trim((string) ($postedBuilder['doc_button_url'] ?? $defBuilder['doc_button_url'])),
            'launch_button_text' => trim((string) ($postedBuilder['launch_button_text'] ?? $defBuilder['launch_button_text'])),
            'launch_button_url' => trim((string) ($postedBuilder['launch_button_url'] ?? $defBuilder['launch_button_url'])),
            'eligibility_title' => trim((string) ($postedBuilder['eligibility_title'] ?? $defBuilder['eligibility_title'])),
            'eligibility_text' => trim((string) ($postedBuilder['eligibility_text'] ?? $defBuilder['eligibility_text'])),
            'eligibility_badge' => trim((string) ($postedBuilder['eligibility_badge'] ?? $defBuilder['eligibility_badge'])),
            'bg_mode' => trim((string) ($postedBuilder['bg_mode'] ?? 'theme_matching')),
            'bg_color_start' => trim((string) ($postedBuilder['bg_color_start'] ?? '#0d1428')),
            'bg_color_end' => trim((string) ($postedBuilder['bg_color_end'] ?? '#070b1a')),
            'border_color' => trim((string) ($postedBuilder['border_color'] ?? 'rgba(59, 130, 246, 0.28)')),
            'accent_color' => trim((string) ($postedBuilder['accent_color'] ?? '#3b82f6')),
            'features' => $builderFeatures,
        ];

        // Section 9: Business Types & Verticals Showcase (6 Cards)
        if (!empty($_POST['business_types_cards']) && is_array($_POST['business_types_cards'])) {
            $defCards = default_business_types_cards();
            $cleanedCards = [];
            foreach ($defCards as $cKey => $cDef) {
                $pCard = $_POST['business_types_cards'][$cKey] ?? [];

                // Parse features
                $cleanedFeats = [];
                if (!empty($pCard['features'])) {
                    if (is_array($pCard['features'])) {
                        foreach ($pCard['features'] as $f) {
                            $tf = trim((string) $f);
                            if ($tf !== '') {
                                $cleanedFeats[] = $tf;
                            }
                        }
                    } else {
                        $lines = explode("\n", (string) $pCard['features']);
                        foreach ($lines as $line) {
                            $tl = trim($line);
                            if ($tl !== '') {
                                $cleanedFeats[] = $tl;
                            }
                        }
                    }
                }
                if (empty($cleanedFeats)) {
                    $cleanedFeats = $cDef['features'];
                }

                $dispMode = trim((string) ($pCard['display_mode'] ?? $cDef['display_mode']));
                if (!in_array($dispMode, ['image', 'icon'], true)) {
                    $dispMode = 'image';
                }

                $cleanedCards[$cKey] = [
                    'key' => $cKey,
                    'title' => trim((string) ($pCard['title'] ?? $cDef['title'])),
                    'icon' => trim((string) ($pCard['icon'] ?? $cDef['icon'])),
                    'tag_text' => trim((string) ($pCard['tag_text'] ?? $cDef['tag_text'])),
                    'tag_class' => trim((string) ($pCard['tag_class'] ?? $cDef['tag_class'])),
                    'display_mode' => $dispMode,
                    'image_url' => trim((string) ($pCard['image_url'] ?? $cDef['image_url'])),
                    'icon_bg' => trim((string) ($pCard['icon_bg'] ?? $cDef['icon_bg'])),
                    'icon_color' => trim((string) ($pCard['icon_color'] ?? $cDef['icon_color'])),
                    'badge_label' => trim((string) ($pCard['badge_label'] ?? $cDef['badge_label'])),
                    'badge_sub' => trim((string) ($pCard['badge_sub'] ?? $cDef['badge_sub'])),
                    'btn_text' => trim((string) ($pCard['btn_text'] ?? $cDef['btn_text'])),
                    'btn_type' => in_array(($pCard['btn_type'] ?? ''), ['link', 'checkout'], true) ? $pCard['btn_type'] : $cDef['btn_type'],
                    'btn_url' => trim((string) ($pCard['btn_url'] ?? $cDef['btn_url'])),
                    'module_slug' => trim((string) ($pCard['module_slug'] ?? $cDef['module_slug'])),
                    'features' => $cleanedFeats,
                ];
            }
            $curr['business_types_cards'] = $cleanedCards;
        }

        // Section Background Colors (Dark & Light)
        if (!empty($_POST['section_colors']) && is_array($_POST['section_colors'])) {
            $defSecs = default_section_colors();
            $cleanedSecColors = [];
            foreach ($defSecs as $secKey => $secDef) {
                $postedSec = $_POST['section_colors'][$secKey] ?? [];
                $dark = trim((string) ($postedSec['dark_bg'] ?? $secDef['dark_bg'])) ?: $secDef['dark_bg'];
                $light = trim((string) ($postedSec['light_bg'] ?? $secDef['light_bg'])) ?: $secDef['light_bg'];
                $cleanedSecColors[$secKey] = [
                    'name' => $secDef['name'],
                    'icon' => $secDef['icon'] ?? '🎨',
                    'dark_bg' => $dark,
                    'light_bg' => $light,
                ];
            }
            $curr['section_colors'] = $cleanedSecColors;
        }

        // Default Theme Mode & Active Color Preset
        $postedMode = trim((string) ($_POST['default_theme_mode'] ?? 'dark'));
        $curr['default_theme_mode'] = in_array($postedMode, ['dark', 'light'], true) ? $postedMode : 'dark';

        $postedPreset = trim((string) ($_POST['active_color_preset'] ?? 'midnight_obsidian'));
        $presets = landing_color_presets();
        $curr['active_color_preset'] = isset($presets[$postedPreset]) ? $postedPreset : 'custom';

        save_landing_config($curr);
        header('Location: landing.php?msg=' . urlencode('Landing page content & pricing settings saved successfully!'));
        exit;
    }
}

$c = get_landing_config();
$token = csrf_token();
lm_header('landing', 'Landing Page Editor & Content Engine');
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
        <h2 style="font-size:20px;font-weight:800;color:#0f172a;margin-bottom:4px">🎨 Central Landing Page Editor</h2>
        <p class="muted" style="margin-bottom:0;font-size:13px">
            All text, hero sections, demo links, discount tiers, features, and FAQs edited here are pushed live to your marketing landing page across ANY hosted domain automatically via <code>/api/landing.php</code>.
        </p>
    </div>
    <div style="display:flex;gap:10px">
        <a href="../../marketing/" target="_blank" class="outline-secondary" style="padding:8px 16px;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
            👁️ Preview Live Landing Page
        </a>
        <a href="../api/landing.php" target="_blank" class="outline-secondary" style="padding:8px 16px;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
            📡 View Live JSON API
        </a>
    </div>
</div>

<form method="post" action="landing.php">
    <input type="hidden" name="csrf" value="<?= e($token) ?>">
    <input type="hidden" name="action" value="save">

    <!-- Section 1: Branding & Global Identity -->
    <section class="card" style="margin-bottom:24px">
        <h3 class="card-title"><span>🏷️</span> Branding &amp; Site Identity</h3>
        <div class="grid">
            <label>Brand / Product Display Name
                <input name="brand_name" value="<?= e($c['brand_name']) ?>" required>
            </label>
            <label>Support Email Address
                <input type="email" name="support_email" value="<?= e($c['support_email']) ?>" required>
            </label>
            <label>Currency Code (3 Letters)
                <input name="currency_code" value="<?= e($c['currency_code']) ?>" maxlength="3" required>
            </label>
            <label>Currency Symbol
                <input name="currency_symbol" value="<?= e($c['currency_symbol']) ?>" maxlength="8" required>
            </label>
            <label style="grid-column:1/-1">Site Tagline / Headline
                <input name="brand_tagline" value="<?= e($c['brand_tagline']) ?>" required>
            </label>
        </div>
    </section>

    <!-- Section 1B: Top Navigation Menu Display & Visibility Options -->
    <?php
    $showTopNav = !isset($c['show_top_nav']) || !empty($c['show_top_nav']);
    $stickyTopNav = !isset($c['sticky_top_nav']) || !empty($c['sticky_top_nav']);
    $navShowBrand = !isset($c['nav_show_brand']) || !empty($c['nav_show_brand']);
    $navShowLinks = !isset($c['nav_show_links']) || !empty($c['nav_show_links']);
    $navShowLanguage = !isset($c['nav_show_language']) || !empty($c['nav_show_language']);
    $navShowThemes = !isset($c['nav_show_themes']) || !empty($c['nav_show_themes']);
    $navShowDarkToggle = !isset($c['nav_show_dark_toggle']) || !empty($c['nav_show_dark_toggle']);
    $navShowDemoBtn = !isset($c['nav_show_demo_btn']) || !empty($c['nav_show_demo_btn']);
    $navShowBuyBtn = !isset($c['nav_show_buy_btn']) || !empty($c['nav_show_buy_btn']);
    ?>
    <section class="card" style="margin-bottom:24px;border:1px solid #bfdbfe;background:linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);" id="top-nav-settings-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
            <div>
                <h3 class="card-title" style="margin-bottom:4px;color:#1e3a8a;"><span>🧭</span> Top Navigation Menu Display &amp; Visibility</h3>
                <p class="muted" style="margin-bottom:0;font-size:12px">
                    Control visibility of the top navigation header on the marketing landing page, with options to show/hide the entire bar or individual items.
                </p>
            </div>
            <label style="display:inline-flex;align-items:center;gap:8px;font-weight:800;font-size:13px;cursor:pointer;background:#ffffff;padding:8px 16px;border-radius:9999px;border:2px solid #2563eb;box-shadow:0 2px 8px rgba(37,99,235,0.12);">
                <input type="checkbox" name="show_top_nav" id="show_top_nav" value="1" <?= $showTopNav ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:#2563eb;" onchange="toggleTopNavSettings(this.checked)">
                <span style="color:#1e3a8a;">Show Top Navigation Bar</span>
            </label>
        </div>

        <div id="top-nav-options-wrap" style="opacity:<?= $showTopNav ? '1' : '0.45' ?>;pointer-events:<?= $showTopNav ? 'auto' : 'none' ?>;transition:all 0.2s ease;">
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:14px;">
                <div style="font-size:12px;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:12px;">Header Behavior &amp; Positioning</div>
                <label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#1e293b;cursor:pointer;">
                    <input type="checkbox" name="sticky_top_nav" value="1" <?= $stickyTopNav ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#2563eb;">
                    <span>Sticky Navigation Header (Keeps the menu bar floating at top when scrolling down)</span>
                </label>
            </div>

            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                <div style="font-size:12px;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:12px;">Granular Navigation Elements Visibility</div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:12px;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#1e293b;cursor:pointer;background:#f8fafc;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;">
                        <input type="checkbox" name="nav_show_brand" value="1" <?= $navShowBrand ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#2563eb;">
                        <span>🛒 Show Brand Logo &amp; Name</span>
                    </label>

                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#1e293b;cursor:pointer;background:#f8fafc;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;">
                        <input type="checkbox" name="nav_show_links" value="1" <?= $navShowLinks ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#2563eb;">
                        <span>🔗 Show Menu Section Links (Home, Overview, Demos, Pricing, FAQ...)</span>
                    </label>

                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#1e293b;cursor:pointer;background:#f8fafc;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;">
                        <input type="checkbox" name="nav_show_language" value="1" <?= $navShowLanguage ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#2563eb;">
                        <span>🌐 Show Language Switcher Dropdown</span>
                    </label>

                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#1e293b;cursor:pointer;background:#f8fafc;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;">
                        <input type="checkbox" name="nav_show_themes" value="1" <?= $navShowThemes ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#2563eb;">
                        <span>🎨 Show 10 Color Theme Preset Selector</span>
                    </label>

                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#1e293b;cursor:pointer;background:#f8fafc;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;">
                        <input type="checkbox" name="nav_show_dark_toggle" value="1" <?= $navShowDarkToggle ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#2563eb;">
                        <span>☀️/🌙 Show Dark / Light Mode Switch Button</span>
                    </label>

                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#1e293b;cursor:pointer;background:#f8fafc;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;">
                        <input type="checkbox" name="nav_show_demo_btn" value="1" <?= $navShowDemoBtn ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#2563eb;">
                        <span>📱 Show "Live Demo" CTA Button</span>
                    </label>

                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#1e293b;cursor:pointer;background:#f8fafc;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;">
                        <input type="checkbox" name="nav_show_buy_btn" value="1" <?= $navShowBuyBtn ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#2563eb;">
                        <span>💳 Show "Buy Now" CTA Button</span>
                    </label>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 2: Hero Section -->
    <section class="card" style="margin-bottom:24px">
        <h3 class="card-title"><span>🚀</span> Hero Section (Top Banner)</h3>
        <div class="grid">
            <label style="grid-column:1/-1">Top Pill / Highlight Badge Text
                <input name="hero_badge" value="<?= e($c['hero_badge']) ?>" required>
            </label>
            <label style="grid-column:1/-1">Main Hero Headline (Use &lt;span&gt;word&lt;/span&gt; for glowing gradient text)
                <input name="hero_title" value="<?= e($c['hero_title']) ?>" required>
            </label>
            <label style="grid-column:1/-1">Hero Subtitle Paragraph
                <textarea name="hero_subtitle" rows="3" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;font-size:14px;"><?= e($c['hero_subtitle']) ?></textarea>
            </label>
            <label>Primary CTA Button Text
                <input name="cta_primary" value="<?= e($c['cta_primary']) ?>" required>
            </label>
            <label>Secondary CTA Button Text
                <input name="cta_secondary" value="<?= e($c['cta_secondary']) ?>" required>
            </label>
        </div>
    </section>

    <!-- Section 3: Multi-Platform Live Demos & Flutter Apps -->
    <section class="card" style="margin-bottom:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
            <div>
                <h3 class="card-title" style="margin-bottom:4px"><span>📱</span> Multi-Platform Live Demos &amp; Flutter Apps</h3>
                <p class="muted" style="margin-bottom:0;font-size:12px">
                    Manage all interactive live demos, downloadable native apps (Flutter Web, Windows, Android), and custom demo links.
                </p>
            </div>
        </div>

        <!-- 3.1 Flutter Web POS -->
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:18px;margin-bottom:18px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                <span style="font-size:22px;">🌐</span>
                <div>
                    <strong style="color:#166534;font-size:14px;">1. Flutter Web POS Live Demo</strong>
                    <span style="background:#dcfce7;color:#15803d;font-size:10px;font-weight:800;padding:2px 8px;border-radius:9999px;margin-left:8px;text-transform:uppercase;">Browser Client</span>
                </div>
            </div>
            <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px;margin-bottom:10px;">
                <label>Display Title
                    <input name="demo_flutter_web_title" value="<?= e($c['demo_flutter_web_title'] ?? 'Flutter Web POS') ?>" required>
                </label>
                <label>Flutter Web Demo URL
                    <input type="url" name="demo_flutter_web_url" value="<?= e($c['demo_flutter_web_url'] ?? 'https://saas.zoomnearby.com/pos-web/') ?>" placeholder="https://yourdomain.com/pos-web/">
                </label>
                <label style="grid-column:1/-1">Short Description / Subtitle
                    <input name="demo_flutter_web_desc" value="<?= e($c['demo_flutter_web_desc'] ?? 'Instant browser-based POS terminal with touch UI, barcode scanning & receipt printing.') ?>">
                </label>
            </div>
        </div>

        <!-- 3.2 Flutter Windows Desktop -->
        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:12px;padding:18px;margin-bottom:18px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                <span style="font-size:22px;">🪟</span>
                <div>
                    <strong style="color:#0369a1;font-size:14px;">2. Flutter Windows Desktop App (.EXE)</strong>
                    <span style="background:#e0f2fe;color:#0284c7;font-size:10px;font-weight:800;padding:2px 8px;border-radius:9999px;margin-left:8px;text-transform:uppercase;">Desktop Installer</span>
                </div>
            </div>
            <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px;margin-bottom:10px;">
                <label>Display Title
                    <input name="demo_flutter_windows_title" value="<?= e($c['demo_flutter_windows_title'] ?? 'Flutter Windows Desktop App') ?>" required>
                </label>
                <label>Windows Installer (.exe) Download URL
                    <input type="url" name="demo_flutter_windows_url" value="<?= e($c['demo_flutter_windows_url'] ?? 'https://saas.zoomnearby.com/zoom-sales-crm-software-1.0.2.exe') ?>" placeholder="https://yourdomain.com/zoom-pos-windows.exe">
                </label>
                <label style="grid-column:1/-1">Short Description / Subtitle
                    <input name="demo_flutter_windows_desc" value="<?= e($c['demo_flutter_windows_desc'] ?? 'Native 64-bit Windows desktop installer with ESC/POS thermal receipt printer integration.') ?>">
                </label>
            </div>
        </div>

        <!-- 3.3 Flutter Android App -->
        <div style="background:#fefce8;border:1px solid #fef08a;border-radius:12px;padding:18px;margin-bottom:18px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                <span style="font-size:22px;">📱</span>
                <div>
                    <strong style="color:#854d0e;font-size:14px;">3. Flutter Android App (.APK)</strong>
                    <span style="background:#fef9c3;color:#a16207;font-size:10px;font-weight:800;padding:2px 8px;border-radius:9999px;margin-left:8px;text-transform:uppercase;">Android APK</span>
                </div>
            </div>
            <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px;margin-bottom:10px;">
                <label>Display Title
                    <input name="demo_flutter_android_title" value="<?= e($c['demo_flutter_android_title'] ?? 'Flutter Android POS App') ?>" required>
                </label>
                <label>Android Package (.apk) Download URL
                    <input type="url" name="demo_flutter_android_url" value="<?= e($c['demo_flutter_android_url'] ?? 'https://saas.zoomnearby.com/zoom-pos-v1.0.2.apk') ?>" placeholder="https://yourdomain.com/zoom-pos-android.apk">
                </label>
                <label style="grid-column:1/-1">Short Description / Subtitle
                    <input name="demo_flutter_android_desc" value="<?= e($c['demo_flutter_android_desc'] ?? 'Native Android APK build optimized for handheld wireless terminals, smartphones, and tablets.') ?>">
                </label>
            </div>
        </div>

        <!-- 3.4 Web Backoffice & SuperAdmin -->
        <div style="background:#fdf2f8;border:1px solid #fbcfe8;border-radius:12px;padding:18px;margin-bottom:18px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                <span style="font-size:22px;">👑</span>
                <div>
                    <strong style="color:#9d174d;font-size:14px;">4. SaaS Web Management Portals</strong>
                    <span style="background:#fce7f3;color:#be185d;font-size:10px;font-weight:800;padding:2px 8px;border-radius:9999px;margin-left:8px;text-transform:uppercase;">Web Admin</span>
                </div>
            </div>
            <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px;margin-bottom:12px;">
                <div>
                    <label>SuperAdmin Display Title
                        <input name="demo_admin_title" value="<?= e($c['demo_admin_title'] ?? 'SuperAdmin SaaS Portal') ?>">
                    </label>
                    <label style="margin-top:6px;">SuperAdmin Demo URL
                        <input type="url" name="demo_admin_url" value="<?= e($c['demo_admin_url']) ?>" placeholder="https://saas.yourdomain.com/login">
                    </label>
                    <label style="margin-top:6px;">SuperAdmin Description
                        <input name="demo_admin_desc" value="<?= e($c['demo_admin_desc'] ?? 'Manage SaaS subscription packages, tenant stores, payment gateways, and system settings.') ?>">
                    </label>
                </div>
                <div>
                    <label>Store POS Backoffice Title
                        <input name="demo_store_title" value="<?= e($c['demo_store_title'] ?? 'Store & Cashier Backoffice') ?>">
                    </label>
                    <label style="margin-top:6px;">Store POS Demo URL
                        <input type="url" name="demo_store_url" value="<?= e($c['demo_store_url']) ?>" placeholder="https://saas.yourdomain.com/store/login">
                    </label>
                    <label style="margin-top:6px;">Store Description
                        <input name="demo_store_desc" value="<?= e($c['demo_store_desc'] ?? 'Staff and cashier portal for catalog, orders, table floorplans, and billing settlement.') ?>">
                    </label>
                </div>
                <div style="grid-column:1/-1;border-top:1px dashed #f472b6;padding-top:12px;margin-top:4px;">
                    <label>Documentation / Setup Guide URL
                        <input type="url" name="documentation_url" value="<?= e($c['documentation_url']) ?>" placeholder="https://saas.yourdomain.com/documentation">
                    </label>
                </div>
            </div>
        </div>

        <!-- 3.5 Custom Other Demo Links (Dynamic Repeater) -->
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <div>
                    <strong style="color:#0f172a;font-size:14px;">5. Custom Other Demo Links</strong>
                    <p class="muted" style="margin-bottom:0;font-size:12px">
                        Add any additional demo or preview links (e.g. Online Storefront, KDS Kitchen Display, Waiter App, API Documentation) with custom title and description.
                    </p>
                </div>
                <button type="button" id="add-other-demo-btn" class="outline-secondary" style="font-size:12px;padding:6px 14px;background:#ffffff;cursor:pointer;">
                    ➕ Add Custom Demo Link
                </button>
            </div>

            <div id="other-demos-container" style="display:flex;flex-direction:column;gap:12px;">
                <?php 
                $otherLinks = $c['demo_other_links'] ?? [];
                foreach ($otherLinks as $idx => $ol): 
                ?>
                    <div class="other-demo-row" style="background:#ffffff;border:1px solid #cbd5e1;border-radius:10px;padding:14px;position:relative;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <span style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;">Custom Link #<span class="row-num"><?= $idx + 1 ?></span></span>
                            <button type="button" class="btn-remove-demo-row" style="background:transparent;border:0;color:#ef4444;font-size:12px;font-weight:700;cursor:pointer;">✖ Remove</button>
                        </div>
                        <div class="grid" style="grid-template-columns:80px 1.5fr 1fr;gap:10px;margin-bottom:8px;">
                            <label>Icon
                                <input type="text" name="demo_other_links[<?= $idx ?>][icon]" value="<?= e($ol['icon'] ?? '🔗') ?>" style="text-align:center;font-size:16px;" placeholder="🔗">
                            </label>
                            <label>Title
                                <input type="text" name="demo_other_links[<?= $idx ?>][title]" value="<?= e($ol['title'] ?? '') ?>" placeholder="e.g. Customer Ordering Web App" required>
                            </label>
                            <label>Badge
                                <input type="text" name="demo_other_links[<?= $idx ?>][badge]" value="<?= e($ol['badge'] ?? 'Custom Demo') ?>" placeholder="e.g. Web App">
                            </label>
                        </div>
                        <div class="grid" style="grid-template-columns:1.5fr 1fr;gap:10px;margin-bottom:8px;">
                            <label>Demo URL
                                <input type="url" name="demo_other_links[<?= $idx ?>][url]" value="<?= e($ol['url'] ?? '') ?>" placeholder="https://..." required>
                            </label>
                            <label>Button Label
                                <input type="text" name="demo_other_links[<?= $idx ?>][btn_text]" value="<?= e($ol['btn_text'] ?? 'Open Demo ↗') ?>" placeholder="Open Demo ↗">
                            </label>
                        </div>
                        <div>
                            <label>Description
                                <textarea name="demo_other_links[<?= $idx ?>][desc]" rows="2" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font:inherit;font-size:12px;" placeholder="Brief description of this live demo environment..."><?= e($ol['desc'] ?? '') ?></textarea>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p id="no-other-demos-msg" style="display:<?= empty($otherLinks) ? 'block' : 'none' ?>;color:#94a3b8;font-size:13px;font-style:italic;margin-top:6px;">
                No custom extra demo links configured yet. Click "+ Add Custom Demo Link" above to add your own.
            </p>
        </div>
    </section>

    <!-- Section 4: Metrics Strip -->
    <section class="card" style="margin-bottom:24px">
        <h3 class="card-title"><span>📊</span> Trust Metrics Strip</h3>
        <div class="grid">
            <label>Metric 1 Value
                <input name="metric_1_val" value="<?= e($c['metric_1_val']) ?>">
            </label>
            <label>Metric 1 Label
                <input name="metric_1_label" value="<?= e($c['metric_1_label']) ?>">
            </label>

            <label>Metric 2 Value
                <input name="metric_2_val" value="<?= e($c['metric_2_val']) ?>">
            </label>
            <label>Metric 2 Label
                <input name="metric_2_label" value="<?= e($c['metric_2_label']) ?>">
            </label>

            <label>Metric 3 Value
                <input name="metric_3_val" value="<?= e($c['metric_3_val']) ?>">
            </label>
            <label>Metric 3 Label
                <input name="metric_3_label" value="<?= e($c['metric_3_label']) ?>">
            </label>

            <label>Metric 4 Value
                <input name="metric_4_val" value="<?= e($c['metric_4_val']) ?>">
            </label>
            <label>Metric 4 Label
                <input name="metric_4_label" value="<?= e($c['metric_4_label']) ?>">
            </label>
        </div>
    </section>

    <!-- Section 5: Custom Bundle Configurator Discount Percentages -->
    <section class="card" style="margin-bottom:24px">
        <h3 class="card-title"><span>🧩</span> Custom Interactive Bundle Builder — Discounts</h3>
        <p class="muted" style="margin-top:-6px;margin-bottom:14px;font-size:12px">
            When buyers choose their own add-on modules on the landing page, these discount percentages are automatically deducted from the total price in real time.
        </p>
        <div class="grid">
            <label>1 Module Added Discount (%)
                <input type="number" name="discount_tier_1" value="<?= (int)$c['discount_tier_1'] ?>" min="0" max="100">
            </label>
            <label>2 Modules Added Discount (%)
                <input type="number" name="discount_tier_2" value="<?= (int)$c['discount_tier_2'] ?>" min="0" max="100">
            </label>
            <label>3+ Modules Added Discount (%)
                <input type="number" name="discount_tier_3" value="<?= (int)$c['discount_tier_3'] ?>" min="0" max="100">
            </label>
        </div>
    </section>

    <!-- Section 6: Core Features (6 Cards) -->
    <section class="card" style="margin-bottom:24px">
        <h3 class="card-title"><span>✨</span> Key Features (6 Cards on Landing Page)</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:16px;">
            <?php foreach ($c['features'] as $idx => $f): ?>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                    <div style="display:flex;gap:8px;margin-bottom:10px;">
                        <input type="text" name="features[<?= $idx ?>][icon]" value="<?= e($f['icon']) ?>" style="width:54px;text-align:center;font-size:18px;" title="Icon / Emoji">
                        <input type="text" name="features[<?= $idx ?>][title]" value="<?= e($f['title']) ?>" style="flex:1;" placeholder="Feature Title">
                    </div>
                    <textarea name="features[<?= $idx ?>][desc]" rows="3" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font:inherit;font-size:13px;"><?= e($f['desc']) ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Section 7: FAQs -->
    <section class="card" style="margin-bottom:24px">
        <h3 class="card-title"><span>❓</span> Frequently Asked Questions (FAQ)</h3>
        <div id="faq-container" style="display:flex;flex-direction:column;gap:14px;">
            <?php foreach ($c['faqs'] as $idx => $faq): ?>
                <div class="faq-row" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                    <div style="margin-bottom:8px;">
                        <label style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Question <?= $idx + 1 ?></label>
                        <input type="text" name="faqs[<?= $idx ?>][q]" value="<?= e($faq['q']) ?>" style="width:100%;font-weight:600;" required>
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Answer</label>
                        <textarea name="faqs[<?= $idx ?>][a]" rows="2" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;font:inherit;font-size:13px;"><?= e($faq['a']) ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Section 8: White-Label Cloud App Builder Showcase Card -->
    <?php
    $ab = $c['app_builder'] ?? default_landing_config()['app_builder'];
    $abEnabled = !empty($ab['enabled']);
    $abFeatures = $ab['features'] ?? default_landing_config()['app_builder']['features'];
    ?>
    <section class="card" style="margin-bottom:24px;border:1px solid #93c5fd;background:linear-gradient(180deg, #ffffff 0%, #f0f7ff 100%);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
            <div>
                <h3 class="card-title" style="margin-bottom:4px;color:#1e3a8a;"><span>🔨</span> White-Label Cloud App Builder Showcase Card</h3>
                <p class="muted" style="margin-bottom:0;font-size:12px">
                    Promotional showcase card displayed directly below the pricing tables on the <code>/marketing</code> landing page.
                </p>
            </div>
            <label style="display:inline-flex;align-items:center;gap:8px;font-weight:700;font-size:13px;cursor:pointer;background:#ffffff;padding:6px 14px;border-radius:9999px;border:1px solid #bfdbfe;">
                <input type="checkbox" name="app_builder[enabled]" value="1" <?= $abEnabled ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:#2563eb;">
                <span>Display on /marketing Landing Page</span>
            </label>
        </div>

        <!-- Hero Header Inputs -->
        <div class="grid" style="grid-template-columns:1fr 2fr;gap:14px;margin-bottom:14px;">
            <label>Badge Text
                <input name="app_builder[badge]" value="<?= e($ab['badge']) ?>" required placeholder="White-Label Cloud App Builder">
            </label>
            <label>Main Headline
                <input name="app_builder[title]" value="<?= e($ab['title']) ?>" required placeholder="Build Your Branded Mobile & Desktop Apps Without Local SDKs">
            </label>
            <label style="grid-column:1/-1">Subtitle Description
                <textarea name="app_builder[subtitle]" rows="2" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;font-size:13px;" required><?= e($ab['subtitle']) ?></textarea>
            </label>
        </div>

        <!-- Section Background Color & Theme Matching Pattern -->
        <div style="background:#ffffff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                <div style="font-size:12px;font-weight:800;color:#1e40af;text-transform:uppercase;letter-spacing:0.04em;">🎨 Section Background Color &amp; Dark/Light Theme Pattern</div>
                <span class="muted" style="font-size:12px">Customize card background colors or enable automated theme-matching with frontend dark/light switch.</span>
            </div>

            <div class="grid" style="grid-template-columns:1.4fr 1fr 1fr 1fr 1fr;gap:12px;align-items:end;">
                <div>
                    <label style="font-weight:700;font-size:12px;display:block;margin-bottom:4px;">Theme Matching Pattern</label>
                    <select name="app_builder[bg_mode]" id="ab_bg_mode" style="width:100%;padding:8px 12px;font-size:13px;border-radius:6px;border:1px solid #cbd5e1;font-weight:600;" onchange="updateAbPreview()">
                        <option value="theme_matching" <?= ($ab['bg_mode'] ?? 'theme_matching') === 'theme_matching' ? 'selected' : '' ?>>✨ Theme Matching (Auto Dark/Light)</option>
                        <option value="dark" <?= ($ab['bg_mode'] ?? '') === 'dark' ? 'selected' : '' ?>>🌙 Always Dark Mode (Midnight Navy)</option>
                        <option value="light" <?= ($ab['bg_mode'] ?? '') === 'light' ? 'selected' : '' ?>>☀️ Always Light Mode (Clean Slate/Ice)</option>
                        <option value="custom" <?= ($ab['bg_mode'] ?? '') === 'custom' ? 'selected' : '' ?>>🎨 Custom Gradient / Solid Color</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight:700;font-size:12px;display:block;margin-bottom:4px;">Background Start</label>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <input type="color" id="ab_color_start_picker" value="<?= e($ab['bg_color_start'] ?? '#0d1428') ?>" style="width:34px;height:34px;padding:0;border:1px solid #cbd5e1;border-radius:6px;cursor:pointer;" oninput="document.getElementById('ab_color_start').value = this.value; updateAbPreview()">
                        <input type="text" name="app_builder[bg_color_start]" id="ab_color_start" value="<?= e($ab['bg_color_start'] ?? '#0d1428') ?>" style="font-family:monospace;font-size:12px;padding:6px 8px;flex:1;" oninput="document.getElementById('ab_color_start_picker').value = this.value; updateAbPreview()">
                    </div>
                </div>
                <div>
                    <label style="font-weight:700;font-size:12px;display:block;margin-bottom:4px;">Background End</label>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <input type="color" id="ab_color_end_picker" value="<?= e($ab['bg_color_end'] ?? '#070b1a') ?>" style="width:34px;height:34px;padding:0;border:1px solid #cbd5e1;border-radius:6px;cursor:pointer;" oninput="document.getElementById('ab_color_end').value = this.value; updateAbPreview()">
                        <input type="text" name="app_builder[bg_color_end]" id="ab_color_end" value="<?= e($ab['bg_color_end'] ?? '#070b1a') ?>" style="font-family:monospace;font-size:12px;padding:6px 8px;flex:1;" oninput="document.getElementById('ab_color_end_picker').value = this.value; updateAbPreview()">
                    </div>
                </div>
                <div>
                    <label style="font-weight:700;font-size:12px;display:block;margin-bottom:4px;">Border Color</label>
                    <input type="text" name="app_builder[border_color]" id="ab_border_color" value="<?= e($ab['border_color'] ?? 'rgba(59, 130, 246, 0.28)') ?>" style="font-family:monospace;font-size:12px;padding:6px 8px;width:100%;" oninput="updateAbPreview()">
                </div>
                <div>
                    <label style="font-weight:700;font-size:12px;display:block;margin-bottom:4px;">Accent Color</label>
                    <div style="display:flex;align-items:center;gap:6px;">
                        <input type="color" id="ab_accent_color_picker" value="<?= e($ab['accent_color'] ?? '#3b82f6') ?>" style="width:34px;height:34px;padding:0;border:1px solid #cbd5e1;border-radius:6px;cursor:pointer;" oninput="document.getElementById('ab_accent_color').value = this.value; updateAbPreview()">
                        <input type="text" name="app_builder[accent_color]" id="ab_accent_color" value="<?= e($ab['accent_color'] ?? '#3b82f6') ?>" style="font-family:monospace;font-size:12px;padding:6px 8px;flex:1;" oninput="document.getElementById('ab_accent_color_picker').value = this.value; updateAbPreview()">
                    </div>
                </div>
            </div>

            <!-- Live Mini Preview -->
            <div id="ab_preview_box" style="margin-top:14px;padding:16px 20px;border-radius:12px;display:flex;align-items:center;justify-content:space-between;transition:all 0.3s ease;">
                <div>
                    <span id="ab_preview_badge" style="display:inline-block;padding:3px 10px;border-radius:9999px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.04em;">White-Label Cloud App Builder</span>
                    <div id="ab_preview_title" style="font-size:15px;font-weight:800;margin-top:6px;">Build Your Branded Mobile &amp; Desktop Apps</div>
                    <div id="ab_preview_sub" style="font-size:12px;margin-top:2px;">Live appearance preview based on selected theme &amp; background settings.</div>
                </div>
                <div style="display:flex;gap:8px;">
                    <span id="ab_preview_btn1" style="padding:6px 14px;border-radius:6px;font-size:11px;font-weight:700;">Launch Builder</span>
                    <span id="ab_preview_btn2" style="padding:6px 14px;border-radius:6px;font-size:11px;font-weight:700;">Documentation</span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="background:#ffffff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;margin-bottom:16px;">
            <div style="font-size:12px;font-weight:800;color:#1e40af;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.04em;">Call-To-Action (CTA) Buttons</div>
            <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label style="font-weight:700;font-size:12px;">Primary Launch Button Text
                        <input name="app_builder[launch_button_text]" value="<?= e($ab['launch_button_text'] ?? 'Launch Builder') ?>" required>
                    </label>
                    <label style="font-weight:700;font-size:12px;margin-top:6px;">Primary Launch URL
                        <input type="url" name="app_builder[launch_button_url]" value="<?= e($ab['launch_button_url'] ?? 'https://saas.zoomnearby.com/app-builder/') ?>" required>
                    </label>
                </div>
                <div>
                    <label style="font-weight:700;font-size:12px;">Documentation Button Text
                        <input name="app_builder[doc_button_text]" value="<?= e($ab['doc_button_text'] ?? 'Builder Documentation') ?>" required>
                    </label>
                    <label style="font-weight:700;font-size:12px;margin-top:6px;">Documentation URL
                        <input type="url" name="app_builder[doc_button_url]" value="<?= e($ab['doc_button_url'] ?? 'https://saas.zoomnearby.com/documentation/index.html#app-builder-guide-and-eligibility') ?>" required>
                    </label>
                </div>
            </div>
        </div>

        <!-- 4 Feature Cards -->
        <div style="margin-bottom:16px;">
            <div style="font-size:12px;font-weight:800;color:#1e40af;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.04em;">Feature Showcase Cards (4 Items)</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:14px;">
                <?php
                $colorOptions = ['blue' => 'Blue', 'purple' => 'Purple', 'emerald' => 'Emerald', 'amber' => 'Amber'];
                for ($fi = 0; $fi < 4; $fi++):
                    $feat = $abFeatures[$fi] ?? [
                        'icon' => '⚡',
                        'title' => 'Feature ' . ($fi + 1),
                        'body' => '',
                        'badge' => '',
                        'color' => 'blue',
                    ];
                ?>
                    <div style="background:#ffffff;border:1px solid #cbd5e1;border-radius:10px;padding:14px;display:flex;flex-direction:column;gap:8px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;">Card #<?= $fi + 1 ?></span>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <label style="font-size:11px;font-weight:700;margin:0;">Theme:</label>
                                <select name="app_builder[features][<?= $fi ?>][color]" style="padding:2px 8px;font-size:11px;border-radius:6px;border:1px solid #cbd5e1;">
                                    <?php foreach ($colorOptions as $cVal => $cLabel): ?>
                                        <option value="<?= $cVal ?>" <?= ($feat['color'] ?? 'blue') === $cVal ? 'selected' : '' ?>><?= $cLabel ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;">
                            <input type="text" name="app_builder[features][<?= $fi ?>][icon]" value="<?= e($feat['icon'] ?? '⚡') ?>" style="width:48px;text-align:center;font-size:18px;" title="Icon Emoji">
                            <input type="text" name="app_builder[features][<?= $fi ?>][title]" value="<?= e($feat['title'] ?? '') ?>" style="flex:1;font-weight:700;" placeholder="Title" required>
                        </div>
                        <textarea name="app_builder[features][<?= $fi ?>][body]" rows="3" style="width:100%;padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;font:inherit;font-size:12px;" placeholder="Card description..."><?= e($feat['body'] ?? '') ?></textarea>
                        <div>
                            <input type="text" name="app_builder[features][<?= $fi ?>][badge]" value="<?= e($feat['badge'] ?? '') ?>" style="width:100%;font-size:11px;" placeholder="Footer check tag (e.g. Zero local configuration)">
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Eligibility Callout -->
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;">
            <div style="font-size:12px;font-weight:800;color:#1e40af;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.04em;">Eligibility &amp; License Entitlement Note</div>
            <div class="grid" style="grid-template-columns:1fr 2fr 1fr;gap:12px;">
                <label style="font-size:12px;font-weight:700;">Note Title
                    <input name="app_builder[eligibility_title]" value="<?= e($ab['eligibility_title'] ?? 'Eligibility Note:') ?>" required>
                </label>
                <label style="font-size:12px;font-weight:700;">Note Description
                    <input name="app_builder[eligibility_text]" value="<?= e($ab['eligibility_text'] ?? '') ?>" required>
                </label>
                <label style="font-size:12px;font-weight:700;">Badge Label
                    <input name="app_builder[eligibility_badge]" value="<?= e($ab['eligibility_badge'] ?? 'Core Script: Included') ?>" required>
                </label>
            </div>
        </div>
    </section>

    <!-- Section 9: Business Types & Verticals Showcase (6 Cards) -->
    <?php
    $bizCards = $c['business_types_cards'] ?? default_business_types_cards();
    $defBizCards = default_business_types_cards();
    ?>
    <section class="card" style="margin-bottom:24px" id="business-types-showcase-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px;flex-wrap:wrap;gap:12px">
            <div>
                <h3 class="card-title" style="margin-bottom:4px"><span>🏢</span> Section 9: Business Types &amp; Verticals Showcase (6 Cards)</h3>
                <p class="muted" style="margin-bottom:0;font-size:13px;max-width:820px">
                    Customize the 6 industry cards rendered on <code>/marketing#business-types</code>. Select whether each card displays a <strong>3D Mockup Image</strong> or a <strong>Badge Box with custom Icon &amp; Colors</strong>. Custom icon background colors reflect in real-time across both Dark and Light themes.
                </p>
            </div>
            <div>
                <span class="badge" style="background:#e0e7ff;color:#3730a3;font-weight:700;font-size:12px;padding:6px 12px;border-radius:20px;">
                    6 Cards Configured
                </span>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(480px, 1fr));gap:20px;">
            <?php foreach ($bizCards as $cKey => $card): 
                $defCard = $defBizCards[$cKey] ?? $card;
                $cardFeatures = !empty($card['features']) && is_array($card['features']) ? implode("\n", $card['features']) : '';
                $dispMode = $card['display_mode'] ?? 'image';
            ?>
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05);display:flex;flex-direction:column;gap:14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f1f5f9;padding-bottom:12px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:24px;width:38px;height:38px;display:flex;align-items:center;justify-content:center;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;"><?= e($card['icon']) ?></span>
                        <div>
                            <div style="font-weight:800;color:#0f172a;font-size:14px;"><?= e($card['title']) ?></div>
                            <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">Key: <code><?= e($cKey) ?></code></div>
                        </div>
                    </div>
                    <span class="badge" style="background:<?= $card['tag_class'] === 'tag-core' ? '#eef2ff;color:#4f46e5;' : '#ecfdf5;color:#059669;' ?>font-size:11px;font-weight:700;padding:4px 10px;border-radius:12px;">
                        <?= e($card['tag_text'] ?: 'Vertical') ?>
                    </span>
                </div>

                <!-- Display Mode Selection -->
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;">
                    <label style="font-size:12px;font-weight:700;color:#1e293b;margin-bottom:8px;display:block;">Display Mode</label>
                    <div style="display:flex;gap:16px;">
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;font-weight:600;">
                            <input type="radio" name="business_types_cards[<?= e($cKey) ?>][display_mode]" value="image" <?= $dispMode === 'image' ? 'checked' : '' ?>>
                            🖼️ Card Image Mockup (Recommended)
                        </label>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;font-weight:600;">
                            <input type="radio" name="business_types_cards[<?= e($cKey) ?>][display_mode]" value="icon" <?= $dispMode === 'icon' ? 'checked' : '' ?>>
                            🎨 Icon Badge Box
                        </label>
                    </div>
                </div>

                <!-- Image Configuration -->
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <label style="font-size:12px;font-weight:700;color:#334155;">
                        Card Mockup Image URL
                        <input type="text" name="business_types_cards[<?= e($cKey) ?>][image_url]" value="<?= e($card['image_url'] ?? $defCard['image_url']) ?>" placeholder="assets/images/pharmacy-pos-mockup.png" style="margin-top:4px;font-family:monospace;font-size:12px;">
                    </label>
                    <div style="font-size:11px;color:#64748b;">
                        Default: <code><?= e($defCard['image_url']) ?></code> (Relative to assets/ or full URL).
                    </div>
                </div>

                <!-- Icon, Background & Text Colors -->
                <div style="background:#fdfdfe;border:1px solid #e2e8f0;border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:10px;">
                    <div style="font-size:12px;font-weight:800;color:#0f172a;text-transform:uppercase;letter-spacing:0.04em;">🎨 Icon Badge Box Styling</div>
                    
                    <div class="grid" style="grid-template-columns:80px 1fr 1fr;gap:10px;align-items:end;">
                        <label style="font-size:11px;font-weight:700;">Icon Emoji
                            <input type="text" name="business_types_cards[<?= e($cKey) ?>][icon]" value="<?= e($card['icon'] ?? $defCard['icon']) ?>" style="text-align:center;font-size:18px;margin-top:4px;" maxlength="4">
                        </label>

                        <label style="font-size:11px;font-weight:700;">Icon Background Color
                            <div style="display:flex;gap:6px;align-items:center;margin-top:4px;">
                                <input type="color" value="<?= e($card['icon_bg'] ?? $defCard['icon_bg']) ?>" style="width:36px;height:36px;padding:2px;border:1px solid #cbd5e1;border-radius:6px;cursor:pointer;" onchange="this.nextElementSibling.value = this.value">
                                <input type="text" name="business_types_cards[<?= e($cKey) ?>][icon_bg]" value="<?= e($card['icon_bg'] ?? $defCard['icon_bg']) ?>" style="font-family:monospace;font-size:12px;" onchange="this.previousElementSibling.value = this.value">
                            </div>
                        </label>

                        <label style="font-size:11px;font-weight:700;">Icon Text Color
                            <div style="display:flex;gap:6px;align-items:center;margin-top:4px;">
                                <input type="color" value="<?= e($card['icon_color'] ?? $defCard['icon_color']) ?>" style="width:36px;height:36px;padding:2px;border:1px solid #cbd5e1;border-radius:6px;cursor:pointer;" onchange="this.nextElementSibling.value = this.value">
                                <input type="text" name="business_types_cards[<?= e($cKey) ?>][icon_color]" value="<?= e($card['icon_color'] ?? $defCard['icon_color']) ?>" style="font-family:monospace;font-size:12px;" onchange="this.previousElementSibling.value = this.value">
                            </div>
                        </label>
                    </div>

                    <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px;">
                        <label style="font-size:11px;font-weight:700;">Badge Label
                            <input type="text" name="business_types_cards[<?= e($cKey) ?>][badge_label]" value="<?= e($card['badge_label'] ?? $defCard['badge_label']) ?>" style="margin-top:4px;font-size:12px;">
                        </label>
                        <label style="font-size:11px;font-weight:700;">Badge Subtitle
                            <input type="text" name="business_types_cards[<?= e($cKey) ?>][badge_sub]" value="<?= e($card['badge_sub'] ?? $defCard['badge_sub']) ?>" style="margin-top:4px;font-size:12px;">
                        </label>
                    </div>
                </div>

                <!-- Titles, Tags & Buttons -->
                <div class="grid" style="grid-template-columns:1.2fr 1fr;gap:10px;">
                    <label style="font-size:11px;font-weight:700;">Card Heading Title
                        <input type="text" name="business_types_cards[<?= e($cKey) ?>][title]" value="<?= e($card['title'] ?? $defCard['title']) ?>" style="margin-top:4px;font-size:12px;">
                    </label>
                    <label style="font-size:11px;font-weight:700;">Card Tag Badge
                        <input type="text" name="business_types_cards[<?= e($cKey) ?>][tag_text]" value="<?= e($card['tag_text'] ?? $defCard['tag_text']) ?>" style="margin-top:4px;font-size:12px;">
                    </label>
                </div>

                <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="font-size:11px;font-weight:700;">Action Button Text
                        <input type="text" name="business_types_cards[<?= e($cKey) ?>][btn_text]" value="<?= e($card['btn_text'] ?? $defCard['btn_text']) ?>" style="margin-top:4px;font-size:12px;">
                    </label>
                    <label style="font-size:11px;font-weight:700;">Action Button Type
                        <select name="business_types_cards[<?= e($cKey) ?>][btn_type]" style="margin-top:4px;font-size:12px;">
                            <option value="checkout" <?= ($card['btn_type'] ?? '') === 'checkout' ? 'selected' : '' ?>>🛒 Open Checkout Modal</option>
                            <option value="link" <?= ($card['btn_type'] ?? '') === 'link' ? 'selected' : '' ?>>🔗 Anchor / URL Link</option>
                        </select>
                    </label>
                </div>

                <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px;">
                    <label style="font-size:11px;font-weight:700;">Link URL (if Anchor)
                        <input type="text" name="business_types_cards[<?= e($cKey) ?>][btn_url]" value="<?= e($card['btn_url'] ?? $defCard['btn_url']) ?>" placeholder="#pricing" style="margin-top:4px;font-size:12px;">
                    </label>
                    <label style="font-size:11px;font-weight:700;">Checkout Module Slug (if Modal)
                        <input type="text" name="business_types_cards[<?= e($cKey) ?>][module_slug]" value="<?= e($card['module_slug'] ?? $defCard['module_slug']) ?>" placeholder="pharmacy" style="margin-top:4px;font-size:12px;">
                    </label>
                </div>

                <!-- Features Checklist -->
                <div>
                    <label style="font-size:11px;font-weight:700;display:block;margin-bottom:4px;">Feature Checklist (1 item per line, up to 8)</label>
                    <textarea name="business_types_cards[<?= e($cKey) ?>][features]" rows="4" style="font-size:12px;font-family:inherit;width:100%;resize:vertical;"><?= e($cardFeatures) ?></textarea>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Section: 10 Curated Color Matching Presets & Section Backgrounds -->
    <?php
    $secColors = $c['section_colors'] ?? default_section_colors();
    $defSecColors = default_section_colors();
    $colorPresets = landing_color_presets();
    $activePresetKey = $c['active_color_preset'] ?? 'midnight_obsidian';
    $defaultThemeMode = $c['default_theme_mode'] ?? 'dark';
    ?>
    <section class="card" style="margin-bottom:24px" id="section-colors-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px;flex-wrap:wrap;gap:14px">
            <div>
                <h3 class="card-title" style="margin-bottom:6px"><span>🎨</span> 10 Curated Color Matching Presets &amp; Section Colors</h3>
                <p class="muted" style="margin-bottom:0;font-size:13px;max-width:750px">
                    Easily switch the entire marketing landing page color scheme in <strong>one click</strong>. Each preset provides a mathematically harmonized palette for both Dark and Light themes with balanced typography, button contrasts, and card depths.
                </p>
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="active_color_preset" id="active_color_preset" value="<?= e($activePresetKey) ?>">
                <button type="button" class="btn" style="padding:8px 16px;font-size:12px;background:#f8fafc;border:1px solid #cbd5e1;color:#334155;border-radius:8px;cursor:pointer;font-weight:700;display:inline-flex;align-items:center;gap:6px;" onclick="selectColorPreset('midnight_obsidian')">
                    <span>↺</span> Reset to Default (Preset #1)
                </button>
            </div>
        </div>

        <!-- Default Theme Mode Control Bar -->
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
            <div>
                <strong style="color:#1e40af;font-size:13px;display:block;margin-bottom:2px;">🌐 Landing Page Default Theme Mode</strong>
                <span style="color:#475569;font-size:12px;">Choose whether visitors see the Dark or Light theme when opening your marketing landing page for the first time.</span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <label style="font-size:12px;font-weight:700;color:#1e3a8a;margin:0;">Default Theme:</label>
                <select name="default_theme_mode" id="default_theme_mode" style="padding:8px 14px;border-radius:6px;border:1px solid #93c5fd;font-weight:700;font-size:13px;background:#ffffff;color:#1e40af;cursor:pointer;">
                    <option value="dark" <?= $defaultThemeMode === 'dark' ? 'selected' : '' ?>>🌙 Dark Mode (Default)</option>
                    <option value="light" <?= $defaultThemeMode === 'light' ? 'selected' : '' ?>>☀️ Light Mode</option>
                </select>
            </div>
        </div>

        <!-- Preset Selection Notification Toast -->
        <div id="preset-applied-toast" style="display:none;background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;font-weight:700;align-items:center;justify-content:space-between;">
            <span id="preset-applied-text">✅ Preset applied!</span>
            <span style="font-size:11px;font-weight:600;color:#047857;">Click "Save Landing Page" below to publish live.</span>
        </div>

        <!-- 10 Curated Presets Grid -->
        <div style="margin-bottom:20px;">
            <div style="font-size:12px;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;">
                <span>Select 1-Click Color Matching Pattern (10 Presets Available):</span>
                <span style="font-size:11px;color:#64748b;font-weight:600;text-transform:none;">Click any preset card to immediately populate all sections</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:14px;" id="presets-grid-container">
                <?php
                $pi = 0;
                foreach ($colorPresets as $pKey => $p):
                    $pi++;
                    $isActive = ($activePresetKey === $pKey);
                    $d = $p['dark'];
                    $l = $p['light'];
                ?>
                    <div class="preset-card <?= $isActive ? 'is-active' : '' ?>" id="preset-card-<?= e($pKey) ?>" onclick="selectColorPreset('<?= e($pKey) ?>')" style="background:#ffffff;border:2px solid <?= $isActive ? '#2563eb' : '#e2e8f0' ?>;border-radius:12px;padding:14px;cursor:pointer;transition:all 0.2s ease;display:flex;flex-direction:column;justify-content:space-between;box-shadow:<?= $isActive ? '0 4px 14px rgba(37, 99, 235, 0.16)' : 'none' ?>;position:relative;">
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;gap:6px;">
                                <strong style="font-size:13px;color:#0f172a;display:flex;align-items:center;gap:6px;">
                                    <span><?= $p['icon'] ?></span> <?= $pi ?>. <?= e($p['name']) ?>
                                </strong>
                                <span class="preset-active-badge" style="display:<?= $isActive ? 'inline-flex' : 'none' ?>;align-items:center;gap:2px;font-size:10px;font-weight:800;color:#ffffff;background:#2563eb;padding:2px 8px;border-radius:9999px;">
                                    ✓ ACTIVE
                                </span>
                            </div>
                            <p style="font-size:11px;color:#64748b;margin-bottom:12px;line-height:1.4;">
                                <?= e($p['description']) ?>
                            </p>
                        </div>

                        <div>
                            <!-- Visual Swatches Strip -->
                            <div style="display:flex;flex-direction:column;gap:4px;background:#f8fafc;padding:8px 10px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:10px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;">
                                    <span style="font-size:10px;font-weight:700;color:#64748b;">🌙 Dark Base &amp; Cards</span>
                                    <div style="display:flex;gap:4px;">
                                        <span title="Dark Primary: <?= $d['primary'] ?>" style="width:18px;height:18px;border-radius:4px;background:<?= $d['primary'] ?>;border:1px solid rgba(255,255,255,0.2);display:inline-block;"></span>
                                        <span title="Dark Cards: <?= $d['card_bg'] ?>" style="width:18px;height:18px;border-radius:4px;background:<?= $d['card_bg'] ?>;border:1px solid rgba(255,255,255,0.2);display:inline-block;"></span>
                                        <span title="Accent: <?= $p['accent'] ?>" style="width:18px;height:18px;border-radius:4px;background:<?= $p['accent'] ?>;display:inline-block;"></span>
                                    </div>
                                </div>
                                <div style="display:flex;align-items:center;justify-content:space-between;">
                                    <span style="font-size:10px;font-weight:700;color:#64748b;">☀️ Light Base &amp; Cards</span>
                                    <div style="display:flex;gap:4px;">
                                        <span title="Light Cards: <?= $l['card_bg'] ?>" style="width:18px;height:18px;border-radius:4px;background:<?= $l['card_bg'] ?>;border:1px solid #cbd5e1;display:inline-block;"></span>
                                        <span title="Light Alternate: <?= $l['secondary'] ?>" style="width:18px;height:18px;border-radius:4px;background:<?= $l['secondary'] ?>;border:1px solid #cbd5e1;display:inline-block;"></span>
                                        <span title="Accent: <?= $p['accent'] ?>" style="width:18px;height:18px;border-radius:4px;background:<?= $p['accent'] ?>;display:inline-block;"></span>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="btn preset-apply-btn" style="width:100%;padding:6px 0;font-size:11px;font-weight:700;border-radius:6px;border:1px solid <?= $isActive ? '#2563eb' : '#cbd5e1' ?>;background:<?= $isActive ? '#2563eb' : '#ffffff' ?>;color:<?= $isActive ? '#ffffff' : '#334155' ?>;cursor:pointer;">
                                <?= $isActive ? '✓ Selected Preset' : 'Apply Preset in 1-Click' ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Optional Collapsible Details: Individual 13 Section Color Editors -->
        <details style="border-top:1px solid #e2e8f0;padding-top:16px;" id="custom-section-colors-details">
            <summary style="cursor:pointer;font-weight:700;font-size:13px;color:#2563eb;user-select:none;display:inline-flex;align-items:center;gap:6px;padding:4px 0;">
                <span>⚙️ Optional: Fine-Tune Individual Section Colors (13 Sections)</span>
            </summary>
            <p class="muted" style="margin-top:8px;margin-bottom:14px;font-size:12px;">
                Selecting any preset above automatically populates these 13 section color fields. You can also customize individual section hex values or CSS gradients if desired.
            </p>

            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(360px, 1fr));gap:16px;">
                <?php foreach ($defSecColors as $sKey => $sDef):
                    $curDark = $secColors[$sKey]['dark_bg'] ?? $sDef['dark_bg'];
                    $curLight = $secColors[$sKey]['light_bg'] ?? $sDef['light_bg'];
                    $sIcon = $sDef['icon'] ?? '🎨';
                    $sName = $sDef['name'];
                    $isDarkGrad = strpos($curDark, 'gradient') !== false;
                    $isLightGrad = strpos($curLight, 'gradient') !== false;
                    $darkPickerVal = (!$isDarkGrad && strlen($curDark) === 7 && $curDark[0] === '#') ? $curDark : '#070a1a';
                    $lightPickerVal = (!$isLightGrad && strlen($curLight) === 7 && $curLight[0] === '#') ? $curLight : '#ffffff';
                ?>
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px;display:flex;flex-direction:column;gap:10px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="font-size:18px;"><?= $sIcon ?></span>
                                <strong style="font-size:13px;color:#0f172a;"><?= e($sName) ?></strong>
                            </div>
                            <span style="font-size:10px;font-family:monospace;color:#64748b;background:#e2e8f0;padding:2px 6px;border-radius:4px;"><?= e($sKey) ?></span>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <!-- Dark Mode BG -->
                            <div style="background:#070a1a;border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:10px;color:#ffffff;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                    <span style="font-size:11px;font-weight:700;color:#94a3b8;">🌙 Dark Theme</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <input type="color" value="<?= e($darkPickerVal) ?>" style="width:28px;height:28px;padding:0;border:1px solid #334155;border-radius:4px;cursor:pointer;background:transparent;" oninput="this.nextElementSibling.value = this.value; markCustomPreset()">
                                    <input type="text" name="section_colors[<?= $sKey ?>][dark_bg]" value="<?= e($curDark) ?>" style="font-family:monospace;font-size:11px;padding:4px 6px;flex:1;background:#0f172a;color:#ffffff;border:1px solid #334155;border-radius:4px;" oninput="if(this.value.length === 7 && this.value[0] === '#') this.previousElementSibling.value = this.value; markCustomPreset()">
                                </div>
                            </div>

                            <!-- Light Mode BG -->
                            <div style="background:#ffffff;border:1px solid #cbd5e1;border-radius:8px;padding:10px;color:#0f172a;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                    <span style="font-size:11px;font-weight:700;color:#475569;">☀️ Light Theme</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <input type="color" value="<?= e($lightPickerVal) ?>" style="width:28px;height:28px;padding:0;border:1px solid #cbd5e1;border-radius:4px;cursor:pointer;background:transparent;" oninput="this.nextElementSibling.value = this.value; markCustomPreset()">
                                    <input type="text" name="section_colors[<?= $sKey ?>][light_bg]" value="<?= e($curLight) ?>" style="font-family:monospace;font-size:11px;padding:4px 6px;flex:1;background:#f8fafc;color:#0f172a;border:1px solid #cbd5e1;border-radius:4px;" oninput="if(this.value.length === 7 && this.value[0] === '#') this.previousElementSibling.value = this.value; markCustomPreset()">
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    </section>

    <div style="position:sticky;bottom:16px;background:rgba(255,255,255,0.92);backdrop-filter:blur(8px);border:1px solid #cbd5e1;box-shadow:0 10px 25px -5px rgba(0,0,0,0.2);border-radius:12px;padding:16px 24px;display:flex;justify-content:space-between;align-items:center;">
        <div>
            <strong style="color:#0f172a;font-size:14px">Ready to update your landing page?</strong>
            <p style="margin:2px 0 0;font-size:12px;color:#64748b">Changes apply instantly to all domains hosting the marketing script.</p>
        </div>
        <button type="submit" style="padding:12px 30px;font-size:15px;box-shadow:0 4px 14px rgba(79,70,229,0.4)">
            💾 Save All Landing Page Settings
        </button>
    </div>
</form>

<script>
(function() {
    var container = document.getElementById('other-demos-container');
    var addBtn = document.getElementById('add-other-demo-btn');
    var emptyMsg = document.getElementById('no-other-demos-msg');

    function updateRowNumbers() {
        if (!container) return;
        var rows = container.querySelectorAll('.other-demo-row');
        rows.forEach(function(row, idx) {
            var numEl = row.querySelector('.row-num');
            if (numEl) numEl.textContent = (idx + 1);
            row.querySelectorAll('input, textarea').forEach(function(inp) {
                var name = inp.getAttribute('name');
                if (name) {
                    inp.setAttribute('name', name.replace(/demo_other_links\[\d+\]/, 'demo_other_links[' + idx + ']'));
                }
            });
        });
        if (emptyMsg) {
            emptyMsg.style.display = rows.length === 0 ? 'block' : 'none';
        }
    }

    if (addBtn && container) {
        addBtn.addEventListener('click', function() {
            var idx = container.querySelectorAll('.other-demo-row').length;
            var div = document.createElement('div');
            div.className = 'other-demo-row';
            div.style.cssText = 'background:#ffffff;border:1px solid #cbd5e1;border-radius:10px;padding:14px;position:relative;margin-top:4px;';
            div.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">' +
                '<span style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;">Custom Link #<span class="row-num">' + (idx + 1) + '</span></span>' +
                '<button type="button" class="btn-remove-demo-row" style="background:transparent;border:0;color:#ef4444;font-size:12px;font-weight:700;cursor:pointer;">✖ Remove</button>' +
                '</div>' +
                '<div class="grid" style="grid-template-columns:80px 1.5fr 1fr;gap:10px;margin-bottom:8px;">' +
                '<label>Icon<input type="text" name="demo_other_links[' + idx + '][icon]" value="🔗" style="text-align:center;font-size:16px;" placeholder="🔗"></label>' +
                '<label>Title<input type="text" name="demo_other_links[' + idx + '][title]" value="" placeholder="e.g. Customer Ordering Web App" required></label>' +
                '<label>Badge<input type="text" name="demo_other_links[' + idx + '][badge]" value="Custom Demo" placeholder="e.g. Web App"></label>' +
                '</div>' +
                '<div class="grid" style="grid-template-columns:1.5fr 1fr;gap:10px;margin-bottom:8px;">' +
                '<label>Demo URL<input type="url" name="demo_other_links[' + idx + '][url]" value="" placeholder="https://..." required></label>' +
                '<label>Button Label<input type="text" name="demo_other_links[' + idx + '][btn_text]" value="Open Demo ↗" placeholder="Open Demo ↗"></label>' +
                '</div>' +
                '<div>' +
                '<label>Description<textarea name="demo_other_links[' + idx + '][desc]" rows="2" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font:inherit;font-size:12px;" placeholder="Brief description of this live demo environment..."></textarea></label>' +
                '</div>';
            container.appendChild(div);
            updateRowNumbers();
        });
    }

    if (container) {
        container.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('btn-remove-demo-row')) {
                var row = e.target.closest('.other-demo-row');
                if (row) {
                    row.remove();
                    updateRowNumbers();
                }
            }
        });
    }
})();

function updateAbPreview() {
    var mode = document.getElementById('ab_bg_mode').value;
    var startInput = document.getElementById('ab_color_start');
    var endInput = document.getElementById('ab_color_end');
    var borderInput = document.getElementById('ab_border_color');
    var accentInput = document.getElementById('ab_accent_color');
    var startPicker = document.getElementById('ab_color_start_picker');
    var endPicker = document.getElementById('ab_color_end_picker');
    var accentPicker = document.getElementById('ab_accent_color_picker');

    if (mode === 'dark') {
        startInput.value = '#0d1428';
        endInput.value = '#070b1a';
        borderInput.value = 'rgba(59, 130, 246, 0.28)';
        accentInput.value = '#3b82f6';
    } else if (mode === 'light') {
        startInput.value = '#f8fafc';
        endInput.value = '#eff6ff';
        borderInput.value = '#bfdbfe';
        accentInput.value = '#2563eb';
    } else if (mode === 'theme_matching') {
        // Keeps user colors or defaults
    }

    startPicker.value = startInput.value.length === 7 ? startInput.value : '#0d1428';
    endPicker.value = endInput.value.length === 7 ? endInput.value : '#070b1a';
    accentPicker.value = accentInput.value.length === 7 ? accentInput.value : '#3b82f6';

    var box = document.getElementById('ab_preview_box');
    var badge = document.getElementById('ab_preview_badge');
    var title = document.getElementById('ab_preview_title');
    var sub = document.getElementById('ab_preview_sub');
    var btn1 = document.getElementById('ab_preview_btn1');
    var btn2 = document.getElementById('ab_preview_btn2');

    var isLight = mode === 'light' || (mode === 'custom' && (startInput.value.toLowerCase() === '#ffffff' || startInput.value.toLowerCase() === '#f8fafc'));

    box.style.background = 'linear-gradient(135deg, ' + startInput.value + ' 0%, ' + endInput.value + ' 100%)';
    box.style.border = '1px solid ' + borderInput.value;

    if (isLight) {
        title.style.color = '#0f172a';
        sub.style.color = '#475569';
        badge.style.background = '#e0f2fe';
        badge.style.color = '#0369a1';
        badge.style.border = '1px solid #bae6fd';
        btn1.style.background = accentInput.value;
        btn1.style.color = '#ffffff';
        btn2.style.background = '#ffffff';
        btn2.style.color = '#0f172a';
        btn2.style.border = '1px solid #cbd5e1';
    } else {
        title.style.color = '#ffffff';
        sub.style.color = '#94a3b8';
        badge.style.background = 'rgba(59, 130, 246, 0.2)';
        badge.style.color = '#60a5fa';
        badge.style.border = '1px solid rgba(96, 165, 250, 0.4)';
        btn1.style.background = accentInput.value;
        btn1.style.color = '#ffffff';
        btn2.style.background = 'rgba(255, 255, 255, 0.1)';
        btn2.style.color = '#ffffff';
        btn2.style.border = '1px solid rgba(255, 255, 255, 0.2)';
    }
}
document.addEventListener('DOMContentLoaded', updateAbPreview);

var colorPresets = <?= json_encode($colorPresets) ?>;

function selectColorPreset(presetKey) {
    if (!colorPresets[presetKey]) return;
    var preset = colorPresets[presetKey];
    
    // Update hidden input
    var hiddenInp = document.getElementById('active_color_preset');
    if (hiddenInp) hiddenInp.value = presetKey;
    
    // Update UI preset cards
    document.querySelectorAll('.preset-card').forEach(function(card) {
        card.style.borderColor = '#e2e8f0';
        card.style.boxShadow = 'none';
        var badge = card.querySelector('.preset-active-badge');
        if (badge) badge.style.display = 'none';
        var btn = card.querySelector('.preset-apply-btn');
        if (btn) {
            btn.textContent = 'Apply Preset in 1-Click';
            btn.style.background = '#ffffff';
            btn.style.color = '#334155';
            btn.style.borderColor = '#cbd5e1';
        }
    });

    var activeCard = document.getElementById('preset-card-' + presetKey);
    if (activeCard) {
        activeCard.style.borderColor = '#2563eb';
        activeCard.style.boxShadow = '0 4px 14px rgba(37, 99, 235, 0.16)';
        var badge = activeCard.querySelector('.preset-active-badge');
        if (badge) badge.style.display = 'inline-flex';
        var btn = activeCard.querySelector('.preset-apply-btn');
        if (btn) {
            btn.textContent = '✓ Selected Preset';
            btn.style.background = '#2563eb';
            btn.style.color = '#ffffff';
            btn.style.borderColor = '#2563eb';
        }
    }

    // Populate all 13 sections
    if (preset.sections) {
        for (var secKey in preset.sections) {
            var sec = preset.sections[secKey];
            var darkInp = document.querySelector('input[name="section_colors[' + secKey + '][dark_bg]"]');
            var lightInp = document.querySelector('input[name="section_colors[' + secKey + '][light_bg]"]');
            if (darkInp) {
                darkInp.value = sec.dark;
                if (darkInp.previousElementSibling && sec.dark.length === 7 && sec.dark[0] === '#') {
                    darkInp.previousElementSibling.value = sec.dark;
                }
            }
            if (lightInp) {
                lightInp.value = sec.light;
                if (lightInp.previousElementSibling && sec.light.length === 7 && sec.light[0] === '#') {
                    lightInp.previousElementSibling.value = sec.light;
                }
            }
        }
    }

    // Toast feedback
    var toast = document.getElementById('preset-applied-toast');
    var text = document.getElementById('preset-applied-text');
    if (toast && text) {
        text.textContent = '✅ Applied "' + preset.name + '"! All section background colors updated.';
        toast.style.display = 'flex';
        setTimeout(function() {
            toast.style.display = 'none';
        }, 5000);
    }
}

function markCustomPreset() {
    var hiddenInp = document.getElementById('active_color_preset');
    if (hiddenInp) hiddenInp.value = 'custom';
}

function resetSectionColorsToDefault() {
    selectColorPreset('midnight_obsidian');
}

function toggleTopNavSettings(enabled) {
    var wrap = document.getElementById('top-nav-options-wrap');
    if (wrap) {
        wrap.style.opacity = enabled ? '1' : '0.45';
        wrap.style.pointerEvents = enabled ? 'auto' : 'none';
    }
}
</script>

<?php lm_footer(); ?>
