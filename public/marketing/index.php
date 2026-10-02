<?php

// Ensure trailing slash when accessed as a directory so relative asset links always resolve
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
$reqPath = parse_url($reqUri, PHP_URL_PATH) ?? '';
if (!empty($reqPath) && substr($reqPath, -4) !== '.php' && substr($reqPath, -1) !== '/') {
    $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: ' . $reqPath . '/' . $qs, true, 301);
    exit;
}

if (!function_exists('marketing_asset')) {
    function marketing_asset($relPath) {
        $clean = ltrim($relPath, '/');
        $file = __DIR__ . '/assets/' . $clean;
        $ver = file_exists($file) ? filemtime($file) : '1.2.0';
        return 'assets/' . $clean . '?v=' . $ver;
    }
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/translations.php';

$currentLang = get_current_marketing_lang();
$supportedLangs = get_supported_marketing_languages();
$isRtl = is_marketing_rtl($currentLang);

$data = get_landing_page_data();

$branding = $data['branding'] ?? [];
$hero = $data['hero'] ?? [];
$urls = $data['urls'] ?? [];
$metrics = $data['metrics'] ?? [];
$discounts = $data['discounts'] ?? ['tier_1' => 10, 'tier_2' => 15, 'tier_3' => 20];
$core = $data['core_product'] ?? [
    'name' => 'Core SaaS Platform (Retail, Restaurant & Café)',
    'description' => 'Multi-tenant POS & Business SaaS platform core platform engine with built-in Retail POS, Restaurant (Dine-in, Tables & KOT), and Café / Quick-Service modules.',
    'price' => 49.00,
    'currency' => 'USD',
];
$modules = $data['modules'] ?? [];
$modMap = array_column($modules, null, 'slug');
$bundles = $data['bundles'] ?? [];
$features = $data['features'] ?? [];
$faqs = !empty($data['faqs']) ? $data['faqs'] : (function_exists('default_marketing_faqs') ? default_marketing_faqs() : []);
$headerSettings = $data['header_settings'] ?? [
    'show_top_nav' => !isset($data['show_top_nav']) || !empty($data['show_top_nav']),
    'sticky_top_nav' => !isset($data['sticky_top_nav']) || !empty($data['sticky_top_nav']),
    'nav_show_brand' => !isset($data['nav_show_brand']) || !empty($data['nav_show_brand']),
    'nav_show_links' => !isset($data['nav_show_links']) || !empty($data['nav_show_links']),
    'nav_show_language' => !isset($data['nav_show_language']) || !empty($data['nav_show_language']),
    'nav_show_themes' => !isset($data['nav_show_themes']) || !empty($data['nav_show_themes']),
    'nav_show_dark_toggle' => !isset($data['nav_show_dark_toggle']) || !empty($data['nav_show_dark_toggle']),
    'nav_show_demo_btn' => !isset($data['nav_show_demo_btn']) || !empty($data['nav_show_demo_btn']),
    'nav_show_buy_btn' => !isset($data['nav_show_buy_btn']) || !empty($data['nav_show_buy_btn']),
];
$showTopNav = !isset($headerSettings['show_top_nav']) || !empty($headerSettings['show_top_nav']);
$stickyTopNav = !isset($headerSettings['sticky_top_nav']) || !empty($headerSettings['sticky_top_nav']);
$navShowBrand = !isset($headerSettings['nav_show_brand']) || !empty($headerSettings['nav_show_brand']);
$navShowLinks = !isset($headerSettings['nav_show_links']) || !empty($headerSettings['nav_show_links']);
$navShowLanguage = !isset($headerSettings['nav_show_language']) || !empty($headerSettings['nav_show_language']);
$navShowThemes = !isset($headerSettings['nav_show_themes']) || !empty($headerSettings['nav_show_themes']);
$navShowDarkToggle = !isset($headerSettings['nav_show_dark_toggle']) || !empty($headerSettings['nav_show_dark_toggle']);
$navShowDemoBtn = !isset($headerSettings['nav_show_demo_btn']) || !empty($headerSettings['nav_show_demo_btn']);
$navShowBuyBtn = !isset($headerSettings['nav_show_buy_btn']) || !empty($headerSettings['nav_show_buy_btn']);

$appBuilder = !empty($data['app_builder']) ? $data['app_builder'] : (function_exists('default_app_builder_config') ? default_app_builder_config() : []);
$businessCards = !empty($data['business_types_cards']) ? $data['business_types_cards'] : (function_exists('default_business_types_cards') ? default_business_types_cards() : []);
$sectionColors = !empty($data['section_colors']) ? $data['section_colors'] : (function_exists('default_section_colors') ? default_section_colors() : []);
$colorPresets = !empty($data['color_presets']) ? $data['color_presets'] : (function_exists('landing_color_presets') ? landing_color_presets() : []);
$activePresetKey = !empty($data['active_color_preset']) ? $data['active_color_preset'] : 'midnight_obsidian';
$defaultThemeMode = !empty($data['default_theme_mode']) ? $data['default_theme_mode'] : 'dark';
if (!in_array($defaultThemeMode, ['dark', 'light'], true)) {
    $defaultThemeMode = 'dark';
}
$activePreset = $colorPresets[$activePresetKey] ?? ($colorPresets['midnight_obsidian'] ?? null);

$demoLinks = $data['demo_links'] ?? [
    'flutter_web' => [
        'key' => 'flutter_web',
        'title' => 'Flutter Web POS',
        'desc' => 'Instant browser-based POS terminal with touch UI, barcode scanning & receipt printing.',
        'url' => $urls['demo_flutter_web'] ?? 'https://saas.zoomnearby.com/pos-web/',
        'icon' => '🌐',
        'badge' => 'Flutter Web',
        'btn_text' => 'Launch Web POS ↗',
        'type' => 'web',
    ],
    'flutter_windows' => [
        'key' => 'flutter_windows',
        'title' => 'Flutter Windows Desktop App',
        'desc' => 'Native 64-bit Windows desktop installer with ESC/POS thermal receipt printer integration.',
        'url' => $urls['demo_flutter_windows'] ?? 'https://saas.zoomnearby.com/zoom-sales-crm-software-1.0.2.exe',
        'icon' => '🪟',
        'badge' => 'Windows .EXE',
        'btn_text' => 'Download Windows App ⬇',
        'type' => 'download',
    ],
    'flutter_android' => [
        'key' => 'flutter_android',
        'title' => 'Flutter Android POS App',
        'desc' => 'Native Android APK build optimized for handheld wireless terminals, smartphones, and tablets.',
        'url' => $urls['demo_flutter_android'] ?? 'https://saas.zoomnearby.com/zoom-pos-v1.0.2.apk',
        'icon' => '📱',
        'badge' => 'Android .APK',
        'btn_text' => 'Download Android APK ⬇',
        'type' => 'download',
    ],
    'superadmin' => [
        'key' => 'superadmin',
        'title' => 'SuperAdmin SaaS Portal',
        'desc' => 'Manage SaaS subscription packages, tenant stores, payment gateways, and system settings.',
        'url' => $urls['demo_admin'] ?? 'https://saas.zoomnearby.com/login',
        'icon' => '👑',
        'badge' => 'SaaS Portal',
        'btn_text' => 'Open SuperAdmin Demo ↗',
        'type' => 'web',
    ],
    'store' => [
        'key' => 'store',
        'title' => 'Store & Cashier Backoffice',
        'desc' => 'Staff and cashier portal for catalog, orders, table floorplans, and billing settlement.',
        'url' => $urls['demo_store'] ?? 'https://saas.zoomnearby.com/store/login',
        'icon' => '🏪',
        'badge' => 'Store Web',
        'btn_text' => 'Open Store Demo ↗',
        'type' => 'web',
    ],
];
$otherDemoLinks = $data['other_demo_links'] ?? [];

$currencySym = $branding['currency_symbol'] ?? '$';
$siteName = $branding['site_name'] ?? 'Zoom POS & Market';
$tagline = $branding['site_tagline'] ?? 'Smarter Business. Greater Control.';

$moduleMeta = [
    'leadmanagement' => [
        'icon' => '📊',
        'color' => 'purple',
        'features' => [
            'Drag-and-drop visual Kanban pipeline',
            'Web form, phone & walk-in lead capture',
            'Follow-up activity scheduling & reminders',
            'Quotations generation linked to deals',
            '1-click won deal auto-customer conversion',
        ],
    ],
    'pharmacy' => [
        'icon' => '💊',
        'color' => 'green',
        'features' => [
            'Batch / Lot number tracking on purchase & sale',
            'Real-time expiry alarms & color-coded warning',
            'Doctor prescription attachment & logs',
            'Generic medicine / salt substitute lookup',
            'Schedule H drug dispensing compliance audits',
        ],
    ],
    'salon' => [
        'icon' => '✂️',
        'color' => 'blue',
        'features' => [
            'Visual appointment booking calendar & slots',
            'Stylist, specialist & station allocation',
            'Service catalogue with durations & rates',
            'Staff commission calculation & tip reports',
            'SMS / WhatsApp appointment confirmations',
        ],
    ],
    'repairtechnician' => [
        'icon' => '🔧',
        'color' => 'orange',
        'features' => [
            'Intake tickets with physical inspection checklist',
            'Diagnosis findings & internal technician notes',
            'Spare parts consumption directly from stock',
            'Technician labor & repair charges billing',
            'Live repair lifecycle (Diagnosing → Ready)',
        ],
    ],
];

$orderComplete = isset($_GET['order']) && $_GET['order'] === 'complete';
$errMsg = trim($_GET['err'] ?? '');
?>
<!doctype html>
<html lang="<?= e_attr($currentLang) ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>" data-theme="<?= e_attr($defaultThemeMode) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e_attr($siteName) ?> — <?= e_attr($tagline) ?></title>
  <meta name="description" content="<?= e_attr($hero['subtitle'] ?? 'Retail, Restaurant & Service — sales, inventory, customers, reports and more in one self-hosted platform.') ?>">
  <script>
    (function() {
      try {
        var serverDefault = <?= json_encode($defaultThemeMode) ?> || 'dark';
        var saved = localStorage.getItem('pos_marketing_theme');
        var theme = saved ? saved : serverDefault;
        document.documentElement.setAttribute('data-theme', theme);
      } catch(e) {}
    })();
    window.POS_COLOR_PRESETS = <?= json_encode($colorPresets) ?>;
    window.POS_ACTIVE_PRESET = <?= json_encode($activePresetKey) ?>;
    window.POS_DEFAULT_THEME = <?= json_encode($defaultThemeMode) ?>;
  </script>
  <link rel="stylesheet" href="<?= marketing_asset('css/marketing.css') ?>">
  <style>
    /* Critical Base & Above-The-Fold Styles (Zero FOUC Resilience) */
    :root {
      --bg-dark: <?= $activePreset['dark']['primary'] ?? '#070a1a' ?>;
      --dark-surface: <?= $activePreset['dark']['secondary'] ?? '#0c1029' ?>;
      --card-bg: <?= $activePreset['dark']['card_bg'] ?? '#0f172a' ?>;
      --card-border: <?= $activePreset['dark']['card_border'] ?? 'rgba(255, 255, 255, 0.08)' ?>;
      --primary-purple: <?= $activePreset['accent'] ?? '#5850ec' ?>;
      --primary-hover: <?= $activePreset['accent'] ?? '#4f46e5' ?>;
      --text-white: <?= $activePreset['dark']['text_title'] ?? '#ffffff' ?>;
      --dark-muted: <?= $activePreset['dark']['text_body'] ?? '#94a3b8' ?>;
      --btn-pri-bg: <?= $activePreset['accent'] ?? '#5850ec' ?>;
      --btn-pri-color: #ffffff;
      --btn-sec-bg: <?= $activePreset['dark']['btn_sec_bg'] ?? 'rgba(255, 255, 255, 0.08)' ?>;
      --btn-sec-border: <?= $activePreset['dark']['btn_sec_border'] ?? 'rgba(255, 255, 255, 0.2)' ?>;
      --btn-sec-color: <?= $activePreset['dark']['btn_sec_color'] ?? '#ffffff' ?>;
      --footer-bg: <?= $activePreset['dark']['footer_bg'] ?? '#050714' ?>;
      --cta-gradient: <?= $activePreset['cta_gradient'] ?? 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)' ?>;
      --radius-md: 10px;
      --radius-pill: 9999px;
    }
    [data-theme="light"] {
      --bg-dark: <?= $activePreset['light']['primary'] ?? '#ffffff' ?>;
      --dark-surface: <?= $activePreset['light']['secondary'] ?? '#f8fafc' ?>;
      --card-bg: <?= $activePreset['light']['card_bg'] ?? '#ffffff' ?>;
      --card-border: <?= $activePreset['light']['card_border'] ?? '#e2e8f0' ?>;
      --primary-purple: <?= $activePreset['accent'] ?? '#5850ec' ?>;
      --primary-hover: <?= $activePreset['accent'] ?? '#4f46e5' ?>;
      --text-white: <?= $activePreset['light']['text_title'] ?? '#0f172a' ?>;
      --dark-muted: <?= $activePreset['light']['text_body'] ?? '#475569' ?>;
      --btn-pri-bg: <?= $activePreset['accent'] ?? '#5850ec' ?>;
      --btn-pri-color: #ffffff;
      --btn-sec-bg: <?= $activePreset['light']['btn_sec_bg'] ?? '#f1f5f9' ?>;
      --btn-sec-border: <?= $activePreset['light']['btn_sec_border'] ?? '#cbd5e1' ?>;
      --btn-sec-color: <?= $activePreset['light']['btn_sec_color'] ?? '#0f172a' ?>;
      --footer-bg: <?= $activePreset['light']['footer_bg'] ?? '#0f172a' ?>;
    }

    /* Cohesive High-Contrast Buttons & Cards */
    .btn-nav-demo,
    .btn-hero-demo,
    .btn-cta-demo,
    .btn-drawer-demo,
    .btn-biz-explore {
      background: var(--btn-sec-bg) !important;
      border: 1px solid var(--btn-sec-border) !important;
      color: var(--btn-sec-color) !important;
      font-weight: 600;
      transition: all 0.2s ease;
    }
    .btn-nav-demo:hover,
    .btn-hero-demo:hover,
    .btn-cta-demo:hover,
    .btn-drawer-demo:hover,
    .btn-biz-explore:hover {
      background: var(--btn-sec-border) !important;
      color: var(--btn-sec-color) !important;
      transform: translateY(-1px);
    }
    [data-theme="light"] .btn-nav-demo,
    [data-theme="light"] .btn-hero-demo,
    [data-theme="light"] .btn-cta-demo,
    [data-theme="light"] .btn-drawer-demo,
    [data-theme="light"] .btn-biz-explore {
      background: var(--btn-sec-bg) !important;
      border: 1px solid var(--btn-sec-border) !important;
      color: var(--btn-sec-color) !important;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08) !important;
    }
    [data-theme="light"] .btn-nav-demo:hover,
    [data-theme="light"] .btn-hero-demo:hover,
    [data-theme="light"] .btn-cta-demo:hover,
    [data-theme="light"] .btn-drawer-demo:hover,
    [data-theme="light"] .btn-biz-explore:hover {
      background: var(--btn-sec-border) !important;
      color: var(--btn-sec-color) !important;
      transform: translateY(-1px);
    }

    /* Vibrant Multi-Platform Demo Action Buttons (Consistent in Modal & Grid) */
    .btn-demo-action {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      width: 100% !important;
      padding: 10px 18px !important;
      border-radius: var(--radius-md) !important;
      font-size: 13px !important;
      font-weight: 700 !important;
      text-align: center !important;
      text-decoration: none !important;
      transition: all 0.2s ease !important;
      cursor: pointer !important;
    }
    .btn-demo-web { background: #10b981 !important; color: #ffffff !important; border: 1px solid #059669 !important; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35) !important; }
    .btn-demo-web:hover { background: #059669 !important; color: #ffffff !important; transform: translateY(-1px); }
    .btn-demo-win { background: #0284c7 !important; color: #ffffff !important; border: 1px solid #0369a1 !important; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35) !important; }
    .btn-demo-win:hover { background: #0369a1 !important; color: #ffffff !important; transform: translateY(-1px); }
    .btn-demo-android { background: #d97706 !important; color: #ffffff !important; border: 1px solid #b45309 !important; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.35) !important; }
    .btn-demo-android:hover { background: #b45309 !important; color: #ffffff !important; transform: translateY(-1px); }
    .btn-demo-admin { background: #e11d48 !important; color: #ffffff !important; border: 1px solid #be123c !important; box-shadow: 0 4px 12px rgba(225, 29, 72, 0.35) !important; }
    .btn-demo-admin:hover { background: #be123c !important; color: #ffffff !important; transform: translateY(-1px); }
    .btn-demo-store { background: #7c3aed !important; color: #ffffff !important; border: 1px solid #6d28d9 !important; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35) !important; }
    .btn-demo-store:hover { background: #6d28d9 !important; color: #ffffff !important; transform: translateY(-1px); }
    .btn-demo-storefront { background: #4f46e5 !important; color: #ffffff !important; border: 1px solid #4338ca !important; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35) !important; }
    .btn-demo-storefront:hover { background: #4338ca !important; color: #ffffff !important; transform: translateY(-1px); }
    .btn-demo-custom { background: var(--btn-pri-bg, #5850ec) !important; color: #ffffff !important; border: 1px solid transparent !important; box-shadow: 0 4px 12px rgba(88, 80, 236, 0.35) !important; }
    .btn-demo-custom:hover { background: var(--primary-hover, #4338ca) !important; color: #ffffff !important; transform: translateY(-1px); }
    .btn-cta-start, .btn-nav-buy, .btn-hero-buy, .btn-drawer-buy, .btn-mod-buy, .btn-tier-action {
      background: var(--btn-pri-bg) !important;
      color: #ffffff !important;
      border: 1px solid transparent !important;
    }

    /* Cards and Surface contrast */
    [data-theme="dark"] .cat-pill-card,
    [data-theme="dark"] .module-card,
    [data-theme="dark"] .demo-card,
    [data-theme="dark"] .pricing-card,
    [data-theme="dark"] .biz-split-card,
    [data-theme="dark"] .eco-node,
    [data-theme="dark"] .faq-item {
      background: var(--card-bg) !important;
      border: 1px solid var(--card-border) !important;
    }
    [data-theme="light"] .cat-pill-card,
    [data-theme="light"] .module-card,
    [data-theme="light"] .demo-card,
    [data-theme="light"] .pricing-card,
    [data-theme="light"] .biz-split-card,
    [data-theme="light"] .eco-node,
    [data-theme="light"] .faq-item {
      background: var(--card-bg) !important;
      border: 1px solid var(--card-border) !important;
      box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05) !important;
    }

    /* Headings & Text High Contrast */
    [data-theme="dark"] .sec-title-light,
    [data-theme="dark"] .sec-title-dark,
    [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4 {
      color: var(--text-white) !important;
    }
    [data-theme="light"] .sec-title-light,
    [data-theme="light"] .sec-title-dark,
    [data-theme="light"] h1, [data-theme="light"] h2, [data-theme="light"] h3, [data-theme="light"] h4 {
      color: var(--text-white) !important;
    }

    .cta-banner-section { background: var(--cta-gradient) !important; }
    .footer { background: var(--footer-bg) !important; }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
      background: var(--bg-dark);
      color: var(--text-white);
      line-height: 1.5;
      overflow-x: hidden;
      transition: background-color 0.25s ease, color 0.25s ease;
    }
    a { color: inherit; text-decoration: none; }
    .container { width: 100%; max-width: 1240px; margin: 0 auto; padding: 0 24px; }
    .header {
      position: sticky;
      top: 0;
      background: rgba(7, 10, 26, 0.94);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      z-index: 1000;
      border-bottom: 1px solid var(--dark-border);
      transition: background-color 0.25s ease, border-color 0.25s ease;
    }
    [data-theme="light"] .header {
      background: rgba(255, 255, 255, 0.94);
      border-bottom-color: #e2e8f0;
    }
    .header-container {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 74px;
      gap: 16px;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--text-white);
      font-size: 19px;
      font-weight: 800;
      letter-spacing: -0.4px;
      flex-shrink: 0;
    }
    .brand-icon {
      width: 38px;
      height: 38px;
      background: linear-gradient(135deg, #38bdf8, #6366f1);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      box-shadow: 0 4px 14px rgba(56, 189, 248, 0.35);
      flex-shrink: 0;
    }
    .brand-name { color: var(--text-white); }
    .brand-sub { color: #a5b4fc; font-weight: 600; }
    [data-theme="light"] .brand-sub { color: #6366f1; }
    .nav-links {
      display: flex;
      align-items: center;
      gap: clamp(10px, 1.6vw, 24px);
    }
    .nav-links a {
      color: var(--dark-muted);
      font-size: 14px;
      font-weight: 600;
      transition: color 0.15s ease;
      white-space: nowrap;
    }
    .nav-links a:hover {
      color: var(--text-white);
    }
    [data-theme="light"] .nav-links a:hover {
      color: #4f46e5;
    }
    .nav-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-shrink: 0;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 20px;
      font-size: 14px;
      font-weight: 600;
      border-radius: var(--radius-md);
      cursor: pointer;
      transition: all 0.2s ease;
      border: 1px solid transparent;
      white-space: nowrap;
    }
    .btn-nav-demo {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #ffffff;
    }
    .btn-nav-demo:hover {
      background: rgba(255, 255, 255, 0.16);
    }
    [data-theme="light"] .btn-nav-demo {
      background: #f1f5f9;
      border-color: #cbd5e1;
      color: #0f172a;
    }
    [data-theme="light"] .btn-nav-demo:hover {
      background: #e2e8f0;
    }
    .btn-nav-buy {
      background: var(--primary-purple);
      color: #ffffff !important;
      box-shadow: 0 4px 14px rgba(88, 80, 236, 0.4);
    }
    .btn-nav-buy:hover {
      background: var(--primary-hover);
      transform: translateY(-1px);
    }
    .lang-dropdown-wrapper {
      position: relative;
      display: inline-block;
    }
    .lang-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      height: 38px;
      border-radius: var(--radius-md);
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #ffffff;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s ease;
      user-select: none;
    }
    .lang-btn:hover { background: rgba(255, 255, 255, 0.16); }
    [data-theme="light"] .lang-btn {
      background: #f1f5f9;
      border-color: #cbd5e1;
      color: #0f172a;
    }
    .lang-chevron { transition: transform 0.2s ease; }
    .lang-btn[aria-expanded="true"] .lang-chevron { transform: rotate(180deg); }
    .lang-dropdown-menu {
      position: absolute;
      top: calc(100% + 8px);
      right: 0;
      min-width: 290px;
      background: #0c1229;
      border: 1px solid rgba(255, 255, 255, 0.14);
      border-radius: 14px;
      padding: 10px;
      box-shadow: 0 18px 40px -10px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
      z-index: 1050;
      display: none;
    }
    [data-theme="light"] .lang-dropdown-menu {
      background: #ffffff;
      border-color: #e2e8f0;
      box-shadow: 0 18px 40px -10px rgba(15, 23, 42, 0.12);
    }
    .lang-dropdown-menu.is-open { display: block !important; }
    .lang-dropdown-header {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #94a3b8;
      padding: 6px 10px 8px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      margin-bottom: 6px;
    }
    [data-theme="light"] .lang-dropdown-header { color: #64748b; border-bottom-color: #f1f5f9; }
    .lang-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 4px;
      max-height: 320px;
      overflow-y: auto;
    }
    .lang-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 7px 10px;
      border-radius: 8px;
      color: #e2e8f0;
      font-size: 13px;
      font-weight: 500;
      transition: background 0.15s ease;
    }
    [data-theme="light"] .lang-item { color: #1e293b; }
    .lang-item:hover, .lang-item.is-active {
      background: rgba(99, 102, 241, 0.18);
      color: #a5b4fc;
    }
    [data-theme="light"] .lang-item:hover, [data-theme="light"] .lang-item.is-active {
      background: #e0e7ff;
      color: #4338ca;
    }
    .lang-tag {
      margin-left: auto;
      font-size: 10px;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
    }
    /* Palette Presets Dropdown Styles */
    .palette-dropdown-wrapper { position: relative; display: inline-block; }
    .palette-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 12px;
      border-radius: var(--radius-md);
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #ffffff;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      transition: all 0.2s ease;
      user-select: none;
    }
    .palette-btn:hover { background: rgba(255, 255, 255, 0.16); }
    [data-theme="light"] .palette-btn {
      background: #f1f5f9;
      border-color: #cbd5e1;
      color: #0f172a;
    }
    .palette-dropdown-menu {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      width: 320px;
      background: #0c1029;
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 14px;
      padding: 12px;
      box-shadow: 0 18px 40px -10px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
      z-index: 1050;
      display: none;
    }
    [data-theme="light"] .palette-dropdown-menu {
      background: #ffffff;
      border-color: #e2e8f0;
      box-shadow: 0 18px 40px -10px rgba(15, 23, 42, 0.12);
    }
    .palette-dropdown-menu.is-open { display: block !important; }
    .palette-dropdown-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #94a3b8;
      padding: 4px 6px 8px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      margin-bottom: 8px;
    }
    [data-theme="light"] .palette-dropdown-header { color: #64748b; border-bottom-color: #f1f5f9; }
    .palette-reset-btn {
      background: transparent;
      border: none;
      color: #a5b4fc;
      font-size: 11px;
      font-weight: 700;
      cursor: pointer;
      padding: 2px 6px;
      border-radius: 4px;
    }
    .palette-reset-btn:hover { text-decoration: underline; }
    [data-theme="light"] .palette-reset-btn { color: #4f46e5; }
    .palette-grid {
      display: flex;
      flex-direction: column;
      gap: 6px;
      max-height: 380px;
      overflow-y: auto;
    }
    .palette-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
      padding: 8px 10px;
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      color: #e2e8f0;
      cursor: pointer;
      text-align: left;
      transition: all 0.15s ease;
    }
    [data-theme="light"] .palette-item {
      background: #f8fafc;
      border-color: #e2e8f0;
      color: #1e293b;
    }
    .palette-item:hover, .palette-item.is-active {
      background: rgba(99, 102, 241, 0.18);
      border-color: #6366f1;
    }
    [data-theme="light"] .palette-item:hover, [data-theme="light"] .palette-item.is-active {
      background: #e0e7ff;
      border-color: #4f46e5;
    }
    .palette-item-header {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      font-weight: 700;
      flex: 1;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .palette-item-swatches {
      display: flex;
      gap: 3px;
      margin-left: 8px;
      flex-shrink: 0;
    }
    .palette-item-swatches span {
      width: 12px;
      height: 12px;
      border-radius: 3px;
      display: inline-block;
      border: 1px solid rgba(255, 255, 255, 0.15);
    }
    [data-theme="light"] .palette-item-swatches span {
      border-color: rgba(0, 0, 0, 0.15);
    }

    .theme-toggle-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
      border-radius: var(--radius-md);
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #ffffff;
      cursor: pointer;
      font-size: 17px;
      transition: all 0.2s ease;
      user-select: none;
    }
    .theme-toggle-btn:hover { background: rgba(255, 255, 255, 0.16); }
    [data-theme="light"] .theme-toggle-btn {
      background: #f1f5f9;
      border-color: #cbd5e1;
      color: #0f172a;
    }
    .theme-icon-sun, .theme-icon-moon { line-height: 1; display: inline-block; }
    [data-theme="dark"] .theme-icon-sun { display: none !important; }
    [data-theme="dark"] .theme-icon-moon { display: inline-block !important; }
    [data-theme="light"] .theme-icon-sun { display: inline-block !important; }
    [data-theme="light"] .theme-icon-moon { display: none !important; }
    .mobile-menu-toggle {
      display: none;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      gap: 5px;
      width: 38px;
      height: 38px;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: var(--radius-md);
      cursor: pointer;
      padding: 6px;
      user-select: none;
      flex-shrink: 0;
    }
    .mobile-menu-toggle span {
      display: block;
      width: 20px;
      height: 2px;
      background: #ffffff;
      border-radius: 2px;
      transition: all 0.25s ease;
    }
    [data-theme="light"] .mobile-menu-toggle {
      background: #f1f5f9;
      border-color: #cbd5e1;
    }
    [data-theme="light"] .mobile-menu-toggle span {
      background: #0f172a;
    }
    .mobile-menu-toggle.is-active span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .mobile-menu-toggle.is-active span:nth-child(2) { opacity: 0; }
    .mobile-menu-toggle.is-active span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }
    @media (max-width: 991px) {
      .header-container { height: 62px; }
      .brand { font-size: 16px; gap: 8px; }
      .brand-icon { width: 32px; height: 32px; font-size: 16px; }
      .brand-sub { display: none; }
      .palette-label { display: none; }
      .palette-btn { padding: 6px 10px; }
      .btn-nav-demo, .btn-nav-buy { display: none !important; }
      .mobile-menu-toggle { display: flex !important; width: 36px; height: 36px; }
      .nav-links {
        display: none;
        position: fixed;
        top: 62px;
        left: 0;
        right: 0;
        background: var(--dark-surface, #0c1029);
        flex-direction: column;
        padding: 20px 22px;
        gap: 8px;
        border-bottom: 1px solid var(--card-border, rgba(255, 255, 255, 0.1));
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
        max-height: calc(100vh - 62px);
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        z-index: 1000;
      }
      [data-theme="light"] .nav-links {
        background: #ffffff;
        border-bottom-color: #e2e8f0;
        box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
      }
      .nav-links.is-open { display: flex !important; }
      .nav-links a {
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        color: #cbd5e1;
        text-decoration: none;
        display: flex;
        align-items: center;
        transition: all 0.2s ease;
      }
      .nav-links a:hover {
        background: rgba(255, 255, 255, 0.08);
        color: #ffffff;
      }
      [data-theme="light"] .nav-links a {
        color: #334155;
      }
      [data-theme="light"] .nav-links a:hover {
        background: #f1f5f9;
        color: #0f172a;
      }
      .nav-drawer-actions {
        display: flex !important;
        flex-direction: column;
        gap: 10px;
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid var(--card-border, rgba(255, 255, 255, 0.1));
      }
      [data-theme="light"] .nav-drawer-actions {
        border-top-color: #e2e8f0;
      }
      .btn-drawer-demo {
        width: 100%;
        justify-content: center;
        padding: 12px 18px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 14px;
      }
      .btn-drawer-buy {
        width: 100%;
        justify-content: center;
        padding: 12px 18px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 14px;
      }
      .nav-actions { gap: 6px; }
    }
    @media (min-width: 992px) {
      .nav-drawer-actions { display: none !important; }
    }
    .hero-section {
      position: relative;
      background: radial-gradient(circle at 65% 30%, #161f4d 0%, #070a1a 75%);
      padding: 60px 0 90px;
      overflow: hidden;
      color: var(--text-white);
    }
    [data-theme="light"] .hero-section {
      background: radial-gradient(circle at 65% 30%, #e0e7ff 0%, #f8fafc 75%);
      color: #0f172a;
    }
    .hero-container {
      display: grid;
      grid-template-columns: 1.05fr 0.95fr;
      gap: 36px;
      align-items: center;
    }
    .hero-badge-pill {
      display: inline-flex;
      align-items: center;
      background: rgba(99, 102, 241, 0.16);
      border: 1px solid rgba(99, 102, 241, 0.35);
      color: #a5b4fc;
      font-size: 12px;
      font-weight: 700;
      padding: 6px 14px;
      border-radius: var(--radius-pill);
      letter-spacing: 0.04em;
      text-transform: uppercase;
      margin-bottom: 20px;
    }
    [data-theme="light"] .hero-badge-pill {
      background: #e0e7ff;
      border-color: #c7d2fe;
      color: #4338ca;
    }
    .hero-title {
      font-size: 48px;
      font-weight: 900;
      line-height: 1.15;
      letter-spacing: -1px;
      margin-bottom: 20px;
      color: var(--text-white);
    }
    .hero-title-highlight {
      background: linear-gradient(135deg, #60a5fa, #a78bfa);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .hero-subtitle {
      font-size: 17px;
      color: var(--dark-muted);
      line-height: 1.6;
      margin-bottom: 30px;
      max-width: 520px;
    }
    .hero-btn-group {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 30px;
      flex-wrap: wrap;
    }
    .btn-hero-buy {
      background: var(--primary-purple);
      color: #ffffff !important;
      padding: 13px 28px;
      font-size: 15px;
      font-weight: 700;
      border-radius: var(--radius-md);
      box-shadow: 0 6px 20px rgba(88, 80, 236, 0.45);
    }
    .btn-hero-buy:hover { background: var(--primary-hover); transform: translateY(-1px); }
    .btn-hero-demo {
      padding: 13px 26px;
      font-size: 15px;
      border-radius: var(--radius-md);
    }
    .hero-trust-row {
      display: flex;
      align-items: center;
      gap: 20px;
      color: var(--dark-muted);
      font-size: 13px;
      font-weight: 600;
      flex-wrap: wrap;
    }
    .trust-item { display: inline-flex; align-items: center; gap: 6px; }
    .trust-check { color: #34d399; font-weight: 900; }
    .hero-device-wrapper {
      position: relative;
      width: 100%;
      max-width: 580px;
      margin: 0 auto;
      filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.5));
    }
    .hero-device-wrapper img {
      width: 100%;
      height: auto;
      display: block;
      border-radius: 12px;
    }
    @media (max-width: 900px) {
      .hero-container { grid-template-columns: 1fr; text-align: center; }
      .hero-subtitle { margin-left: auto; margin-right: auto; }
      .hero-btn-group, .hero-trust-row { justify-content: center; }
    }

    /* ================= Dynamic Section Background Colors (Configured via License Manager Admin) ================= */
    <?php if (!empty($sectionColors)): ?>
      <?php
        $secCssMap = [
          'hero' => '.hero-section',
          'category_strip' => '.category-strip-section',
          'features' => '.control-section',
          'business_types' => '.business-types-section',
          'industries' => '.industries-section',
          'modules' => '.modules-overview-section',
          'demos' => '.demos-section',
          'pricing' => '.pricing-showcase-section',
          'why_us' => '.why-us-section',
          'ecosystem' => '.ecosystem-section',
          'cta_banner' => '.cta-banner-section',
          'faq' => '.faq-section',
          'footer' => '.footer',
        ];
        foreach ($secCssMap as $sK => $sSel):
          if (!empty($sectionColors[$sK])):
            $dBg = trim((string)($sectionColors[$sK]['dark_bg'] ?? ''));
            $lBg = trim((string)($sectionColors[$sK]['light_bg'] ?? ''));
      ?>
        <?php if ($dBg !== ''): ?>
          [data-theme="dark"] <?= $sSel ?> { background: <?= $dBg ?> !important; }
        <?php endif; ?>
        <?php if ($lBg !== ''): ?>
          [data-theme="light"] <?= $sSel ?> { background: <?= $lBg ?> !important; }
        <?php endif; ?>
      <?php
          endif;
        endforeach;
      ?>
    <?php endif; ?>

    .header-static { position: relative !important; }
    body.no-top-nav .hero-section { padding-top: 60px !important; }

    /* Pricing Section Cards Dark/Light Matching Pattern */
    [data-theme="dark"] .pricing-showcase-section {
      background: var(--dark-surface, #0c1029) !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    [data-theme="dark"] .price-tier-card,
    [data-theme="dark"] .how-it-works-card {
      background: var(--card-bg, #0f172a) !important;
      border: 1px solid var(--card-border, rgba(255, 255, 255, 0.08)) !important;
      color: var(--text-white, #ffffff) !important;
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4) !important;
    }
    [data-theme="dark"] .price-tier-card:hover,
    [data-theme="dark"] .how-it-works-card:hover {
      border-color: rgba(99, 102, 241, 0.45) !important;
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5) !important;
    }
    [data-theme="dark"] .price-val-wrap,
    [data-theme="dark"] .price-val-wrap .price-currency,
    [data-theme="dark"] .price-val-wrap .price-number {
      color: #ffffff !important;
    }
    [data-theme="dark"] .price-freq {
      color: #94a3b8 !important;
    }
    [data-theme="dark"] .price-badge-pill:not(.pill-purple) {
      background: rgba(255, 255, 255, 0.08) !important;
      border: 1px solid rgba(255, 255, 255, 0.2) !important;
      color: #cbd5e1 !important;
    }
    [data-theme="dark"] .price-badge-pill.pill-purple {
      background: var(--btn-pri-bg, #6366f1) !important;
      color: #ffffff !important;
    }
    [data-theme="dark"] .price-tier-features li,
    [data-theme="dark"] .pricing-feature {
      color: #cbd5e1 !important;
    }
    [data-theme="dark"] .pricing-feature .feature-title,
    [data-theme="dark"] .price-tier-features li .feature-title {
      color: #ffffff !important;
    }
    [data-theme="dark"] .pricing-feature .feature-description,
    [data-theme="dark"] .price-tier-features li .feature-description {
      color: #94a3b8 !important;
    }
    [data-theme="dark"] .pricing-section-header,
    [data-theme="dark"] .feature-section-header {
      color: #94a3b8 !important;
      border-top-color: rgba(255, 255, 255, 0.1) !important;
    }
    [data-theme="dark"] .featured-business .pricing-section-header,
    [data-theme="dark"] .featured-business .feature-section-header {
      color: #a5b4fc !important;
      border-top-color: rgba(99, 102, 241, 0.35) !important;
    }
    [data-theme="dark"] .btn-tier-outline {
      background: var(--btn-sec-bg, rgba(255, 255, 255, 0.08)) !important;
      border-color: var(--btn-sec-border, rgba(255, 255, 255, 0.2)) !important;
      color: var(--btn-sec-color, #ffffff) !important;
    }
    [data-theme="dark"] .btn-tier-outline:hover {
      background: var(--btn-sec-border, rgba(255, 255, 255, 0.22)) !important;
      color: #ffffff !important;
    }
    [data-theme="dark"] .popular-ribbon {
      background: var(--btn-pri-bg, #6366f1) !important;
      color: #ffffff !important;
    }
    [data-theme="dark"] .how-it-works-card .hiw-title {
      color: #ffffff !important;
    }
    [data-theme="dark"] .how-it-works-card .hiw-sub {
      color: #94a3b8 !important;
    }
    [data-theme="dark"] .how-it-works-card .hiw-step-num {
      background: rgba(255, 255, 255, 0.08) !important;
      border: 1px solid rgba(255, 255, 255, 0.12) !important;
      color: var(--btn-pri-bg, #a5b4fc) !important;
    }
    [data-theme="dark"] .how-it-works-card .hiw-step-name {
      color: #ffffff !important;
    }
    [data-theme="dark"] .how-it-works-card .hiw-step-desc {
      color: #94a3b8 !important;
    }
    [data-theme="dark"] .pricing-not-included-block {
      background: rgba(15, 23, 42, 0.6) !important;
      border-color: rgba(255, 255, 255, 0.12) !important;
    }
    [data-theme="dark"] .not-included-header {
      border-bottom-color: rgba(255, 255, 255, 0.08) !important;
    }
    [data-theme="dark"] .not-included-title {
      color: #cbd5e1 !important;
    }
    [data-theme="dark"] .not-included-subtitle,
    [data-theme="dark"] .not-included-item {
      color: #94a3b8 !important;
    }
    /* FAQ Section Matching Pattern */
    [data-theme="dark"] .faq-section {
      background: var(--bg-dark, #070a1a) !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    [data-theme="dark"] .faq-item {
      background: var(--card-bg, #0f172a) !important;
      border: 1px solid var(--card-border, rgba(255, 255, 255, 0.08)) !important;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25) !important;
    }
    [data-theme="dark"] .faq-item:hover {
      border-color: rgba(99, 102, 241, 0.45) !important;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35) !important;
    }
    [data-theme="dark"] .faq-q, [data-theme="dark"] .faq-question {
      color: #ffffff !important;
    }
    [data-theme="dark"] .faq-a, [data-theme="dark"] .faq-answer {
      color: #94a3b8 !important;
    }
  </style>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🛒</text></svg>">
</head>
<body class="<?= !$showTopNav ? 'no-top-nav' : '' ?>">

  <!-- ================= Navigation Header ================= -->
  <?php if ($showTopNav): ?>
  <header class="header <?= !$stickyTopNav ? 'header-static' : '' ?>">
    <div class="container header-container">
      <?php if ($navShowBrand): ?>
      <a href="index.php" class="brand">
        <span class="brand-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
          </svg>
        </span>
        <span class="brand-name">Zoom POS <span class="brand-sub">&amp; Market</span></span>
      </a>
      <?php endif; ?>

      <?php if ($navShowLinks): ?>
      <nav class="nav-links">
        <a href="#home"><?= __t('Home') ?></a>
        <a href="#overview"><?= __t('Overview') ?></a>
        <a href="#business-types"><?= __t('Business Types') ?></a>
        <a href="#modules"><?= __t('Modules') ?></a>
        <a href="#live-demos"><?= __t('Live Demos') ?></a>
        <a href="#pricing"><?= __t('Pricing') ?></a>
        <a href="#why-us"><?= __t('Why Us') ?></a>
        <a href="#faq"><?= __t('FAQ') ?></a>
        <div class="nav-drawer-actions">
          <?php if ($navShowDemoBtn): ?>
            <a href="#live-demos" class="btn btn-drawer-demo open-demo-hub-btn"><?= __t('Live Demos & Apps') ?></a>
          <?php endif; ?>
          <?php if ($navShowBuyBtn): ?>
            <a href="#pricing" class="btn btn-drawer-buy"><?= __t('Buy Now') ?></a>
          <?php endif; ?>
        </div>
      </nav>
      <?php endif; ?>

      <div class="nav-actions">
        <?php if ($navShowLanguage): ?>
        <!-- Language Switcher Dropdown -->
        <div class="lang-dropdown-wrapper" id="lang-dropdown-wrapper">
          <button type="button" class="lang-btn" id="lang-menu-btn" aria-expanded="false" aria-label="<?= __t('Select Language') ?>" title="<?= __t('Select Language') ?>">
            <span class="lang-flag"><?= $supportedLangs[$currentLang]['flag'] ?? '🌐' ?></span>
            <span class="lang-code"><?= strtoupper(e($currentLang)) ?></span>
            <svg class="lang-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
          </button>
          <div class="lang-dropdown-menu" id="lang-dropdown-menu" style="display: none;">
            <div class="lang-dropdown-header"><?= __t('Select Language') ?></div>
            <div class="lang-grid">
              <?php foreach ($supportedLangs as $code => $info): ?>
                <a href="?lang=<?= e_attr($code) ?>" class="lang-item <?= $code === $currentLang ? 'is-active' : '' ?>" data-lang="<?= e_attr($code) ?>">
                  <span class="lang-flag"><?= $info['flag'] ?></span>
                  <span class="lang-native"><?= e($info['native']) ?></span>
                  <span class="lang-tag"><?= strtoupper(e($code)) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($navShowThemes): ?>
        <!-- Theme Palette Presets Switcher (1-Click) -->
        <div class="palette-dropdown-wrapper" id="palette-dropdown-wrapper">
          <button type="button" class="palette-btn" id="palette-menu-btn" aria-expanded="false" aria-label="Color Themes" title="Choose from 10 Color Themes">
            <span class="palette-icon">🎨</span>
            <span class="palette-label"><?= __t('Themes') ?></span>
          </button>
          <div class="palette-dropdown-menu" id="palette-dropdown-menu" style="display: none;">
            <div class="palette-dropdown-header">
              <span>🎨 <?= __t('10 Color Presets') ?></span>
              <button type="button" class="palette-reset-btn" id="palette-reset-btn" title="Reset to Server Default">↺ Reset</button>
            </div>
            <div class="palette-grid">
              <?php foreach ($colorPresets as $pk => $pv): ?>
                <button type="button" class="palette-item <?= $pk === $activePresetKey ? 'is-active' : '' ?>" data-preset="<?= e_attr($pk) ?>">
                  <div class="palette-item-header">
                    <span class="palette-item-icon"><?= $pv['icon'] ?></span>
                    <span class="palette-item-name"><?= e($pv['name']) ?></span>
                  </div>
                  <div class="palette-item-swatches">
                    <span style="background:<?= $pv['dark']['primary'] ?>" title="Dark Base"></span>
                    <span style="background:<?= $pv['dark']['card_bg'] ?>" title="Dark Cards"></span>
                    <span style="background:<?= $pv['accent'] ?>" title="Accent"></span>
                    <span style="background:<?= $pv['light']['card_bg'] ?>" title="Light Cards"></span>
                    <span style="background:<?= $pv['light']['secondary'] ?>" title="Light Base"></span>
                  </div>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($navShowDarkToggle): ?>
        <!-- Theme Toggle Button (Dark / Light) -->
        <button type="button" class="theme-toggle-btn" id="theme-toggle-btn" aria-label="<?= __t('Toggle Theme') ?>" title="<?= __t('Toggle Theme') ?>">
          <span class="theme-icon theme-icon-sun">☀️</span>
          <span class="theme-icon theme-icon-moon">🌙</span>
        </button>
        <?php endif; ?>

        <?php if ($navShowDemoBtn): ?>
        <a href="#live-demos" class="btn btn-nav-demo open-demo-hub-btn"><?= __t('Live Demo') ?></a>
        <?php endif; ?>

        <?php if ($navShowBuyBtn): ?>
        <a href="#pricing" class="btn btn-nav-buy"><?= __t('Buy Now') ?></a>
        <?php endif; ?>

        <?php if ($navShowLinks || $navShowDemoBtn || $navShowBuyBtn): ?>
        <!-- Mobile Menu Toggle Button -->
        <button type="button" class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="<?= __t('Toggle Menu') ?>" title="<?= __t('Toggle Menu') ?>">
          <span></span><span></span><span></span>
        </button>
        <?php endif; ?>
      </div>
    </div>
  </header>
  <?php endif; ?>

  <?php if ($errMsg): ?>
    <div style="background:#fee2e2;border-bottom:1px solid #fca5a5;color:#991b1b;padding:12px;text-align:center;font-size:13px;font-weight:700;">
      ⚠️ <?= e_attr($errMsg) ?>
    </div>
  <?php endif; ?>

  <?php if ($orderComplete): ?>
    <div style="background:#dcfce7;border-bottom:1px solid #86efac;color:#166534;padding:14px;text-align:center;font-size:14px;font-weight:700;">
      🎉 Thank you! Your purchase has been completed. Your license details and activation keys were delivered to your email.
    </div>
  <?php endif; ?>

  <!-- ================= 1. Hero Section ================= -->
  <section class="hero-section" id="home">
    <div class="container hero-container">
      <div class="hero-left">
        <div class="hero-badge-pill">
          <?= e_attr($hero['badge'] ?? 'Complete Business Management Platform') ?>
        </div>

        <h1 class="hero-title">
          Run Your Entire Business<br>
          From <span class="hero-title-highlight">One Powerful POS</span>
        </h1>

        <p class="hero-subtitle">
          <?= e_attr($hero['subtitle'] ?? 'Retail, Restaurant & Service — sales, inventory, customers, reports and more in one self-hosted platform.') ?>
        </p>

        <div class="hero-btn-group">
          <a href="#pricing" class="btn btn-hero-buy">Buy Now</a>
          <a href="#live-demos" class="btn btn-hero-demo open-demo-hub-btn">Live Demos &amp; Apps</a>
        </div>

        <div class="hero-trust-row">
          <span class="trust-item"><span class="trust-check">✔</span> One-time payment</span>
          <span class="trust-item"><span class="trust-check">✔</span> Self-hosted</span>
          <span class="trust-item"><span class="trust-check">✔</span> No monthly fee</span>
        </div>
      </div>

      <div class="hero-right">
        <div class="hero-device-wrapper">
          <img src="<?= marketing_asset('images/hero-devices.png') ?>" alt="Zoom POS & Market Terminal, Tablet, Printer and Dashboard Setup" width="1050" height="490">
        </div>
      </div>
    </div>
  </section>

  <!-- ================= 2. Category Strip ================= -->
  <section class="category-strip-section" id="overview">
    <div class="container">
      <div class="section-hdr text-center">
        <h2 class="sec-title-light">One Platform. Every Part of Your Business.</h2>
        <p class="sec-subtitle-light">Everything you need to manage, grow and scale — in a single platform.</p>
      </div>

      <div class="category-strip-grid">
        <div class="cat-pill-card">
          <div class="cat-icon-box cat-icon-retail">
            🛒
          </div>
          <div class="cat-info">
            <div class="cat-title">Retail</div>
            <div class="cat-desc">POS &amp; Billing</div>
          </div>
        </div>

        <div class="cat-pill-card">
          <div class="cat-icon-box cat-icon-restaurant">
            🍽️
          </div>
          <div class="cat-info">
            <div class="cat-title">Restaurant</div>
            <div class="cat-desc">Tables &amp; KOT</div>
          </div>
        </div>

        <div class="cat-pill-card">
          <div class="cat-icon-box cat-icon-pharmacy">
            💊
          </div>
          <div class="cat-info">
            <div class="cat-title">Pharmacy</div>
            <div class="cat-desc">Batch &amp; Expiry</div>
          </div>
        </div>

        <div class="cat-pill-card">
          <div class="cat-icon-box cat-icon-salon">
            ✂️
          </div>
          <div class="cat-info">
            <div class="cat-title">Salon &amp; Spa</div>
            <div class="cat-desc">Appointments &amp; Staff</div>
          </div>
        </div>

        <div class="cat-pill-card">
          <div class="cat-icon-box cat-icon-repair">
            🔧
          </div>
          <div class="cat-info">
            <div class="cat-title">Repair Service</div>
            <div class="cat-desc">Job Tickets &amp; Parts</div>
          </div>
        </div>

        <div class="cat-pill-card">
          <div class="cat-icon-box cat-icon-crm">
            📊
          </div>
          <div class="cat-info">
            <div class="cat-title">CRM &amp; Leads</div>
            <div class="cat-desc">Pipeline &amp; Deals</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= 3. Control Feature Section ================= -->
  <section class="control-section" id="features">
    <div class="container control-container">
      <div class="control-left">
        <h2 class="control-title">Built for businesses that want control</h2>

        <div class="control-grid">
          <div class="control-card">
            <div class="control-icon-box icon-selfhosted">
              🏠
            </div>
            <div>
              <h3 class="control-card-title">Self-Hosted</h3>
              <p class="control-card-desc">Your data stays on your infrastructure with full source code ownership.</p>
            </div>
          </div>

          <div class="control-card">
            <div class="control-icon-box icon-multitenant">
              🏢
            </div>
            <div>
              <h3 class="control-card-title">Multi-Tenant</h3>
              <p class="control-card-desc">Run multiple business branches or host a SaaS for client companies.</p>
            </div>
          </div>

          <div class="control-card">
            <div class="control-icon-box icon-offline">
              ⚡
            </div>
            <div>
              <h3 class="control-card-title">Offline Ready</h3>
              <p class="control-card-desc">Keep selling without interruption even when internet is temporarily down.</p>
            </div>
          </div>

          <div class="control-card">
            <div class="control-icon-box icon-modular">
              🧩
            </div>
            <div>
              <h3 class="control-card-title">Modular</h3>
              <p class="control-card-desc">Activate specialized industry vertical modules on demand as you scale.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="control-right">
        <div class="dashboard-preview-card">
          <img src="<?= marketing_asset('images/dashboard-preview.png') ?>" alt="Zoom POS & Market Dashboard Preview" width="880" height="390">
        </div>
      </div>
    </div>
  </section>

  <!-- ================= 4. Industry Verticals Grid (All Supported Business Types) ================= -->
  <section class="business-types-section" id="business-types">
    <div class="container">
      <div class="section-hdr text-center">
        <h2 class="sec-title-light">One Platform. Multiple Business Types.</h2>
        <p class="sec-subtitle-light">Choose the right setup for your industry and get everything you need to run it smoothly.</p>
      </div>

      <div class="business-split-grid">
        <?php foreach ($businessCards as $cKey => $card): 
          $dispMode = $card['display_mode'] ?? 'image';
          $title = $card['title'] ?? '';
          $icon = $card['icon'] ?? '🛒';
          $tagText = $card['tag_text'] ?? '';
          $tagClass = $card['tag_class'] ?? 'tag-core';
          $btnText = $card['btn_text'] ?? 'Explore &rarr;';
          $btnType = $card['btn_type'] ?? 'link';
          $btnUrl = $card['btn_url'] ?? '#pricing';
          $moduleSlug = $card['module_slug'] ?? '';
          $features = !empty($card['features']) && is_array($card['features']) ? $card['features'] : [];
          
          // Resolve module price / tag if vertical module and slug set
          if ($btnType === 'checkout' && $moduleSlug && isset($modMap[$moduleSlug])) {
              $modPrice = (float) ($modMap[$moduleSlug]['price'] ?? 0);
              if ($modPrice > 0 && strpos($tagText, $currencySym) === false) {
                  $tagText .= ' (' . $currencySym . number_format($modPrice, 0) . ')';
              }
              $checkoutTitle = $modMap[$moduleSlug]['name'] ?? $title;
          } else {
              $modPrice = 0;
              $checkoutTitle = $title;
          }

          // Split features into 2 columns
          $half = max(1, (int) ceil(count($features) / 2));
          $col1 = array_slice($features, 0, $half);
          $col2 = array_slice($features, $half);
        ?>
        <div class="biz-split-card" data-key="<?= htmlspecialchars($cKey) ?>">
          <div class="biz-split-content">
            <div>
              <?php if (!empty($tagText)): ?>
                <span class="biz-card-tag <?= htmlspecialchars($tagClass) ?>"><?= htmlspecialchars($tagText) ?></span>
              <?php endif; ?>
              <div class="biz-card-hdr">
                <span class="biz-hdr-icon"><?= htmlspecialchars($icon) ?></span>
                <h3 class="biz-hdr-title"><?= htmlspecialchars($title) ?></h3>
              </div>
              
              <div class="biz-checklist-2col">
                <div class="biz-check-col">
                  <?php foreach ($col1 as $item): ?>
                    <div class="biz-check-item"><span>✔</span> <?= htmlspecialchars($item) ?></div>
                  <?php endforeach; ?>
                </div>
                <div class="biz-check-col">
                  <?php foreach ($col2 as $item): ?>
                    <div class="biz-check-item"><span>✔</span> <?= htmlspecialchars($item) ?></div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <?php if ($btnType === 'checkout' && $moduleSlug): ?>
              <button type="button" class="btn btn-biz-explore open-checkout-btn" data-type="product" data-slug="<?= htmlspecialchars($moduleSlug) ?>" data-title="<?= htmlspecialchars($checkoutTitle) ?>" data-price="<?= $modPrice ?>" data-items="<?= htmlspecialchars($checkoutTitle) ?>"><?= htmlspecialchars($btnText) ?></button>
            <?php else: ?>
              <a href="<?= htmlspecialchars($btnUrl ?: '#pricing') ?>" class="btn btn-biz-explore"><?= htmlspecialchars($btnText) ?></a>
            <?php endif; ?>
          </div>

          <?php if ($dispMode === 'image' && !empty($card['image_url'])): 
            $imgSrc = $card['image_url'];
            if (!preg_match('#^(https?://|/)#i', $imgSrc)) {
                $imgSrc = marketing_asset(preg_replace('#^assets/#', '', $imgSrc));
            }
          ?>
            <div class="biz-split-img">
              <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($title) ?>" width="300" height="250" loading="lazy">
            </div>
          <?php else: 
            $iconBg = !empty($card['icon_bg']) ? $card['icon_bg'] : '#f8fafc';
            $iconColor = !empty($card['icon_color']) ? $card['icon_color'] : '#4f46e5';
            $badgeLabel = $card['badge_label'] ?? $title;
            $badgeSub = $card['badge_sub'] ?? '';
          ?>
            <div class="biz-badge-box">
              <div class="biz-badge-icon" style="background:<?= htmlspecialchars($iconBg) ?> !important;color:<?= htmlspecialchars($iconColor) ?> !important;">
                <?= htmlspecialchars($icon) ?>
              </div>
              <div class="biz-badge-label"><?= htmlspecialchars($badgeLabel) ?></div>
              <?php if (!empty($badgeSub)): ?>
                <div class="biz-badge-sub"><?= htmlspecialchars($badgeSub) ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ================= Dedicated Supported Industries Showcase ================= -->
  <section class="industries-section">
    <div class="container">
      <div class="section-hdr text-center">
        <h2 class="sec-title-light">Tailored For Every Commercial Industry</h2>
        <p class="sec-subtitle-light">Designed for independent businesses, multi-store retail chains, and SaaS platforms.</p>
      </div>

      <div class="industries-grid">
        <div class="industry-chip">
          <div class="industry-chip-icon">🏪</div>
          <div>
            <div class="industry-chip-title">Supermarkets &amp; Groceries</div>
            <div class="industry-chip-sub">Barcodes &amp; weigh scales</div>
          </div>
        </div>

        <div class="industry-chip">
          <div class="industry-chip-icon">👗</div>
          <div>
            <div class="industry-chip-title">Fashion &amp; Boutiques</div>
            <div class="industry-chip-sub">Sizes, colors &amp; variants</div>
          </div>
        </div>

        <div class="industry-chip">
          <div class="industry-chip-icon">📱</div>
          <div>
            <div class="industry-chip-title">Electronics &amp; Hardware</div>
            <div class="industry-chip-sub">Serial numbers &amp; warranty</div>
          </div>
        </div>

        <div class="industry-chip">
          <div class="industry-chip-icon">🍽️</div>
          <div>
            <div class="industry-chip-title">Dine-in Restaurants</div>
            <div class="industry-chip-sub">Tables, KOT &amp; courses</div>
          </div>
        </div>

        <div class="industry-chip">
          <div class="industry-chip-icon">☕</div>
          <div>
            <div class="industry-chip-title">Cafés, Bakeries &amp; QSR</div>
            <div class="industry-chip-sub">Quick orders &amp; modifiers</div>
          </div>
        </div>

        <div class="industry-chip">
          <div class="industry-chip-icon">💊</div>
          <div>
            <div class="industry-chip-title">Pharmacies &amp; Chemists</div>
            <div class="industry-chip-sub">Batches &amp; expiry alerts</div>
          </div>
        </div>

        <div class="industry-chip">
          <div class="industry-chip-icon">✂️</div>
          <div>
            <div class="industry-chip-title">Salons, Spas &amp; Barbers</div>
            <div class="industry-chip-sub">Stylists &amp; appointments</div>
          </div>
        </div>

        <div class="industry-chip">
          <div class="industry-chip-icon">🔧</div>
          <div>
            <div class="industry-chip-title">Device Repair Centers</div>
            <div class="industry-chip-sub">Intake tickets &amp; diagnostics</div>
          </div>
        </div>

        <div class="industry-chip">
          <div class="industry-chip-icon">📦</div>
          <div>
            <div class="industry-chip-title">Warehouses &amp; Depots</div>
            <div class="industry-chip-sub">Inter-branch transfers</div>
          </div>
        </div>

        <div class="industry-chip">
          <div class="industry-chip-icon">🏢</div>
          <div>
            <div class="industry-chip-title">B2B &amp; Service Sales</div>
            <div class="industry-chip-sub">Leads &amp; quotation billing</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= 5. Modules Showcase ================= -->
  <section class="modules-overview-section" id="modules">
    <div class="container">
      <div class="modules-sec-header">
        <div>
          <h2 class="sec-title-dark">Extend Your POS With Powerful Modules</h2>
          <p class="sec-subtitle-dark">Start with the core platform. Add only what your business needs.</p>
        </div>
        <a href="#pricing" class="browse-all-link">Browse Pricing &amp; Bundles &rarr;</a>
      </div>

      <div class="modules-cards-grid">
        
        <?php foreach ($modules as $mod): ?>
          <?php
          $mSlug = $mod['slug'];
          $mPrice = (float) $mod['price'];
          $mName = $mod['name'];
          $mDesc = $mod['description'] ?: 'Specialized vertical add-on module for POS & Business management.';
          $meta = $moduleMeta[$mSlug] ?? [
              'icon' => '📦',
              'color' => 'blue',
              'features' => [
                  'Modular plug-and-play architecture',
                  'Dedicated database schema & routes',
                  'Seamless SuperAdmin module activation',
                  'Instant license key delivery via email',
                  'Full unencrypted PHP source code',
              ],
          ];
          ?>
          <div class="mod-showcase-card">
            <div>
              <div class="mod-icon-badge mod-bg-<?= e_attr($meta['color']) ?>"><?= $meta['icon'] ?></div>
              <h3 class="mod-card-title"><?= e($mName) ?></h3>
              <p class="mod-card-desc"><?= e($mDesc) ?></p>
              <?php
              $mFeats = !empty($mod['custom_features']) && is_array($mod['custom_features']) ? $mod['custom_features'] : $meta['features'];
              ?>
              <ul class="mod-feature-bullets">
                <?php foreach ($mFeats as $f): ?>
                  <?= render_pricing_feature_item($f, true) ?>
                <?php endforeach; ?>
              </ul>
            </div>
            <div class="mod-price-row">
              <div>
                <span class="mod-price-val"><?= $currencySym . number_format($mPrice, 2) ?></span>
                <span style="font-size:11px;color:var(--dark-muted)"> / one-time</span>
              </div>
              <button type="button" class="btn btn-mod-buy open-checkout-btn"
                data-type="product" data-slug="<?= e_attr($mSlug) ?>" data-title="<?= e_attr($mName) ?>"
                data-price="<?= $mPrice ?>" data-items="<?= e_attr($mName) ?>">
                Buy Module (<?= $currencySym . number_format($mPrice, 0) ?>)
              </button>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- Built-in Module 1: Restaurant Dine-In (Core) -->
        <div class="mod-showcase-card">
          <div>
            <div class="mod-icon-badge mod-bg-green">🍽️</div>
            <h3 class="mod-card-title">Restaurant &amp; Dine-In Mode</h3>
            <p class="mod-card-desc">Complete restaurant workflow with floor tables, kitchen order tickets (KOT), and waiter ordering.</p>
            <ul class="mod-feature-bullets">
              <li><span>✓</span> Visual floor table management &amp; occupancy</li>
              <li><span>✓</span> Thermal KOT printing &amp; Kitchen Display (KDS)</li>
              <li><span>✓</span> Mobile / tablet waiter ordering interface</li>
              <li><span>✓</span> Recipe raw material stock auto-depletion</li>
              <li><span>✓</span> Split bill by seat or custom payment splits</li>
            </ul>
          </div>
          <div class="mod-price-row">
            <div>
              <span class="mod-included-tag">Built-in Core Platform</span>
            </div>
            <a href="#pricing" class="btn btn-mod-buy">Included in Core</a>
          </div>
        </div>

        <!-- Built-in Module 2: Retail POS Engine (Core) -->
        <div class="mod-showcase-card">
          <div>
            <div class="mod-icon-badge mod-bg-purple">🛒</div>
            <h3 class="mod-card-title">Retail POS &amp; Inventory Engine</h3>
            <p class="mod-card-desc">High-speed barcode scanner checkout, variant inventory, customer accounts, and thermal printing.</p>
            <ul class="mod-feature-bullets">
              <li><span>✓</span> Barcode scanning &amp; quick item search</li>
              <li><span>✓</span> Multi-store warehousing &amp; stock transfers</li>
              <li><span>✓</span> Customer credit ledgers &amp; payment accounts</li>
              <li><span>✓</span> ESC/POS 80mm &amp; 58mm thermal receipt printing</li>
              <li><span>✓</span> Barcode sticker generation &amp; price labels</li>
            </ul>
          </div>
          <div class="mod-price-row">
            <div>
              <span class="mod-included-tag">Built-in Core Platform</span>
            </div>
            <a href="#pricing" class="btn btn-mod-buy">Included in Core</a>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- ================= Multi-Platform Live Demos & Flutter Apps ================= -->
  <section class="demos-section" id="live-demos">
    <div class="container">
      <div class="section-hdr text-center">
        <h2 class="sec-title-dark">Experience Zoom POS Live on Any Platform</h2>
        <p class="sec-subtitle-dark">Try our instant web clients or download native Flutter cross-platform applications built for Windows and Android.</p>
      </div>

      <div class="demo-cards-grid">
        <?php foreach ($demoLinks as $key => $d): ?>
          <?php if (!empty($d['url'])): ?>
            <div class="demo-card">
              <div>
                <div class="demo-card-hdr">
                  <div class="demo-icon-box demo-icon-<?= e_attr(str_replace('_', '-', $key)) ?>">
                    <?= e_attr($d['icon'] ?? '📱') ?>
                  </div>
                  <span class="demo-badge"><?= e_attr($d['badge'] ?? 'Live Demo') ?></span>
                </div>
                <h3 class="demo-title"><?= e_attr($d['title']) ?></h3>
                <p class="demo-desc"><?= e_attr($d['desc']) ?></p>
              </div>
              <div class="demo-card-footer">
                <?php
                  $btnClass = 'btn-demo-web';
                  if ($key === 'flutter_windows') $btnClass = 'btn-demo-win';
                  elseif ($key === 'flutter_android') $btnClass = 'btn-demo-android';
                  elseif ($key === 'superadmin') $btnClass = 'btn-demo-admin';
                  elseif ($key === 'store') $btnClass = 'btn-demo-store';
                  elseif ($key === 'storefront') $btnClass = 'btn-demo-storefront';
                ?>
                <a href="<?= e_attr($d['url']) ?>" target="_blank" class="btn-demo-action <?= $btnClass ?>" <?= (!empty($d['type']) && $d['type'] === 'download') ? 'download' : '' ?>>
                  <?= e_attr($d['btn_text'] ?? 'Open Demo ↗') ?>
                </a>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php foreach ($otherDemoLinks as $ol): ?>
          <?php if (!empty($ol['url'])): ?>
            <div class="demo-card">
              <div>
                <div class="demo-card-hdr">
                  <div class="demo-icon-box demo-icon-custom">
                    <?= e_attr($ol['icon'] ?? '🔗') ?>
                  </div>
                  <span class="demo-badge"><?= e_attr($ol['badge'] ?? 'Custom Demo') ?></span>
                </div>
                <h3 class="demo-title"><?= e_attr($ol['title']) ?></h3>
                <p class="demo-desc"><?= e_attr($ol['desc'] ?? '') ?></p>
              </div>
              <div class="demo-card-footer">
                <a href="<?= e_attr($ol['url']) ?>" target="_blank" class="btn-demo-action btn-demo-custom">
                  <?= e_attr($ol['btn_text'] ?? 'Open Demo ↗') ?>
                </a>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ================= 6. Pricing Section ================= -->
  <section class="pricing-showcase-section" id="pricing">
    <div class="container">
      <div class="section-hdr text-center">
        <h2 class="sec-title-light">Choose How You Want to Launch</h2>
        <p class="sec-subtitle-light">Flexible one-time pricing. No forced monthly subscription. You own the software.</p>
      </div>

      <div class="pricing-cards-4col">
        
        <!-- Tier 1: CORE -->
        <div class="price-tier-card">
          <div class="price-tier-hdr">
            <span class="price-badge-pill">CORE</span>
            <div class="price-val-wrap">
              <span class="price-currency"><?= $currencySym ?></span>
              <span class="price-number"><?= number_format((float)$core['price'], 0) ?></span>
            </div>
            <span class="price-freq">One-time</span>
          </div>

          <ul class="price-tier-features">
            <?php
            $coreFeats = !empty($core['custom_features']) && is_array($core['custom_features']) ? $core['custom_features'] : [
                'Retail POS Module (Barcodes, variants & stock)',
                'Restaurant POS Module (Tables, KOT & KDS)',
                'Café & Quick-Service Module (Fast checkout)',
                'Multi-Store Warehousing & Stock Transfers',
                'Thermal Receipts (80/58mm) & Barcode Labels',
                'Unlimited Stores, Registers & Cashiers',
                '100% Unencrypted Full PHP Source Code',
            ];
            foreach ($coreFeats as $cf):
            ?>
              <?= render_pricing_feature_item($cf) ?>
            <?php endforeach; ?>
          </ul>

          <button type="button" class="btn btn-tier-action open-checkout-btn"
            data-type="product" data-slug="core" data-title="Core SaaS Platform (Retail, Restaurant &amp; Café)"
            data-price="<?= (float)$core['price'] ?>" data-items="Core SaaS Platform (Retail, Restaurant &amp; Café)">
            Buy Core (<?= $currencySym . number_format((float)$core['price'], 0) ?>)
          </button>
        </div>

        <?php if (!empty($bundles)): ?>
          <?php foreach ($bundles as $idx => $b): ?>
            <?php
            $bSlug = $b['slug'];
            $bPrice = (float) $b['price'];
            $bName = $b['name'];
            $bDesc = $b['description'] ?: $bName;
            $incMods = $b['included_modules'] ?? [];
            $isFeatured = ($bSlug === 'core-lead') || (!empty($b['is_featured'])) || ($idx === 0 && count($bundles) > 1);
            $pillClass = $isFeatured ? 'pill-purple' : '';
            $cardClass = $isFeatured ? 'featured-business' : '';
            $btnClass = $isFeatured ? 'btn-purple-solid' : 'btn-tier-outline';
            
            // Clean badge label
            $rawBadge = trim(preg_replace('/\s+/', ' ', preg_replace('/(bundle|platform|system)/i', '', $bName)));
            $badgeLabel = strtoupper($rawBadge) ?: 'BUNDLE';
            if (strlen($badgeLabel) > 20) {
                $badgeLabel = ($idx === 0) ? 'BUSINESS' : 'ENTERPRISE';
            }
            ?>
            <div class="price-tier-card <?= $cardClass ?>">
              <?php if ($isFeatured): ?>
                <div class="popular-ribbon">Most Popular</div>
              <?php endif; ?>
              <div class="price-tier-hdr">
                <span class="price-badge-pill <?= $pillClass ?>"><?= e($badgeLabel) ?></span>
                <div class="price-val-wrap">
                  <span class="price-currency"><?= $currencySym ?></span>
                  <span class="price-number"><?= number_format($bPrice, 0) ?></span>
                </div>
                <span class="price-freq">One-time</span>
              </div>

              <ul class="price-tier-features">
                <?php
                $bundleFeats = !empty($b['custom_features']) && is_array($b['custom_features']) ? $b['custom_features'] : [];
                if (empty($bundleFeats)) {
                    $bundleFeats[] = 'Everything in Core (Retail + Restaurant + Café)';
                    if (in_array('leadmanagement', $incMods, true)) {
                        $bundleFeats[] = 'Lead Management CRM Module Included';
                        $bundleFeats[] = 'Visual Kanban Sales Pipeline & Follow-ups';
                    }
                    if (in_array('pharmacy', $incMods, true)) {
                        $bundleFeats[] = 'Pharmacy POS Module Included (Batches & Expiry)';
                    }
                    if (in_array('salon', $incMods, true)) {
                        $bundleFeats[] = 'Salon & Spa System Included (Stylists & Slots)';
                    }
                    if (in_array('repairtechnician', $incMods, true)) {
                        $bundleFeats[] = 'Repair Service Workbench Included (Job Tickets)';
                    }
                    $bundleFeats[] = 'Multi-Tenant SaaS Billing & Domain Mapping';
                    $bundleFeats[] = 'Separate License Keys Emailed Instantly';
                    $bundleFeats[] = 'Zero Monthly or Annual Platform Fees';
                }
                foreach ($bundleFeats as $bf):
                ?>
                  <?= render_pricing_feature_item($bf) ?>
                <?php endforeach; ?>
              </ul>

              <button type="button" class="btn btn-tier-action <?= $btnClass ?> open-checkout-btn"
                data-type="bundle" data-slug="<?= e_attr($bSlug) ?>" data-title="<?= e_attr($bName) ?>"
                data-price="<?= $bPrice ?>" data-items="<?= e_attr($bDesc) ?>">
                Get <?= e($bName) ?> (<?= $currencySym . number_format($bPrice, 0) ?>)
              </button>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- Column 4: How It Works -->
        <div class="how-it-works-card">
          <h3 class="hiw-title">How It Works</h3>
          <p class="hiw-sub">Get your POS up and running in 3 simple steps.</p>

          <div class="hiw-steps-list">
            <div class="hiw-step-item">
              <div class="hiw-step-num">01</div>
              <div>
                <div class="hiw-step-name">Choose</div>
                <div class="hiw-step-desc">Select your platform package or build a custom module bundle.</div>
              </div>
            </div>

            <div class="hiw-step-item">
              <div class="hiw-step-num">02</div>
              <div>
                <div class="hiw-step-name">Install</div>
                <div class="hiw-step-desc">Deploy with 1-click on your own server, VPS, or cPanel hosting.</div>
              </div>
            </div>

            <div class="hiw-step-item">
              <div class="hiw-step-num">03</div>
              <div>
                <div class="hiw-step-name">Start Selling</div>
                <div class="hiw-step-desc">Configure stores, invite staff cashiers, and start processing orders.</div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ================= White-Label Cloud App Builder Showcase Card ================= -->
      <?php if (!empty($appBuilder) && (!isset($appBuilder['enabled']) || $appBuilder['enabled'])): ?>
        <?php
          $bBadge = trim((string) ($appBuilder['badge'] ?? 'White-Label Cloud App Builder')) ?: 'White-Label Cloud App Builder';
          $bTitle = trim((string) ($appBuilder['title'] ?? 'Build Your Branded Mobile & Desktop Apps Without Local SDKs')) ?: 'Build Your Branded Mobile & Desktop Apps Without Local SDKs';
          $bSubtitle = trim((string) ($appBuilder['subtitle'] ?? 'Compile production-ready Flutter apps directly in the cloud. Customize your app name, logo, color palette, and package ID, then let our automated GitHub Actions cloud pipeline generate Android APK/AAB, Windows Desktop (.exe), and Web PWA binaries.'));
          $bDocText = trim((string) ($appBuilder['doc_button_text'] ?? 'Builder Documentation')) ?: 'Builder Documentation';
          $bDocUrl = trim((string) ($appBuilder['doc_button_url'] ?? 'https://saas.zoomnearby.com/documentation/index.html#app-builder-guide-and-eligibility')) ?: 'https://saas.zoomnearby.com/documentation/index.html#app-builder-guide-and-eligibility';
          $bLaunchText = trim((string) ($appBuilder['launch_button_text'] ?? 'Launch Builder')) ?: 'Launch Builder';
          $bLaunchUrl = trim((string) ($appBuilder['launch_button_url'] ?? 'https://saas.zoomnearby.com/app-builder/')) ?: 'https://saas.zoomnearby.com/app-builder/';
          $bEligTitle = trim((string) ($appBuilder['eligibility_title'] ?? 'Eligibility Note:')) ?: 'Eligibility Note:';
          $bEligText = trim((string) ($appBuilder['eligibility_text'] ?? 'App Builder compilation quotas are tied to Core SaaS Script licenses and bundles containing Core. Standalone modules or plugins do not have independent build quotas.'));
          $bEligBadge = trim((string) ($appBuilder['eligibility_badge'] ?? 'Core Script: Included')) ?: 'Core Script: Included';
          $bFeatures = !empty($appBuilder['features']) && is_array($appBuilder['features']) ? $appBuilder['features'] : [];

          $bBgMode = trim((string) ($appBuilder['bg_mode'] ?? 'theme_matching'));
          $bBgStart = trim((string) ($appBuilder['bg_color_start'] ?? '#0d1428'));
          $bBgEnd = trim((string) ($appBuilder['bg_color_end'] ?? '#070b1a'));
          $bBorderColor = trim((string) ($appBuilder['border_color'] ?? 'rgba(59, 130, 246, 0.28)'));
          $bAccentColor = trim((string) ($appBuilder['accent_color'] ?? '#3b82f6'));

          $abClass = 'marketing-app-builder-showcase ab-mode-' . $bBgMode . ' ab-mode-' . str_replace('_', '-', $bBgMode);
          $abCustomStyle = '';
          if ($bBgMode === 'custom') {
              $abCustomStyle = 'style="background: linear-gradient(180deg, ' . e_attr($bBgStart) . ' 0%, ' . e_attr($bBgEnd) . ' 100%) !important; border-color: ' . e_attr($bBorderColor) . ' !important;"';
          }
        ?>
        <div class="<?= $abClass ?>" id="app-builder-showcase" <?= $abCustomStyle ?>>
          <!-- Top Row: Badge, Title, Subtitle & Action Buttons -->
          <div class="m-ab-header-row">
            <div class="m-ab-header-text">
              <div class="m-ab-pill">
                <span>🚀</span>
                <span><?= e(__t($bBadge, $bBadge)) ?></span>
              </div>
              <h3 class="m-ab-title"><?= e(__t($bTitle, $bTitle)) ?></h3>
              <?php if ($bSubtitle): ?>
                <p class="m-ab-subtitle"><?= e(__t($bSubtitle, $bSubtitle)) ?></p>
              <?php endif; ?>
            </div>
            <div class="m-ab-btn-group">
              <a href="<?= e_attr($bDocUrl) ?>" target="_blank" rel="noopener noreferrer" class="m-ab-btn m-ab-btn-doc">
                <span>📖</span>
                <span><?= e(__t($bDocText, $bDocText)) ?></span>
              </a>
              <a href="<?= e_attr($bLaunchUrl) ?>" target="_blank" rel="noopener noreferrer" class="m-ab-btn m-ab-btn-launch">
                <span>🔨</span>
                <span><?= e(__t($bLaunchText, $bLaunchText)) ?></span>
              </a>
            </div>
          </div>

          <!-- Divider -->
          <div class="m-ab-divider"></div>

          <!-- 4 Features Grid (2x2) -->
          <div class="m-ab-features-grid">
            <?php
            $colorMap = [
                'blue'    => ['icon_bg' => 'rgba(59, 130, 246, 0.15)', 'icon_border' => 'rgba(59, 130, 246, 0.35)', 'tag_color' => '#60a5fa'],
                'purple'  => ['icon_bg' => 'rgba(168, 85, 247, 0.15)', 'icon_border' => 'rgba(168, 85, 247, 0.35)', 'tag_color' => '#c084fc'],
                'emerald' => ['icon_bg' => 'rgba(16, 185, 129, 0.15)', 'icon_border' => 'rgba(16, 185, 129, 0.35)', 'tag_color' => '#34d399'],
                'amber'   => ['icon_bg' => 'rgba(245, 158, 11, 0.15)', 'icon_border' => 'rgba(245, 158, 11, 0.35)', 'tag_color' => '#fbbf24'],
            ];
            $defaultColors = ['blue', 'purple', 'emerald', 'amber'];
            foreach ($bFeatures as $fIdx => $feat):
                $cKey = $feat['color'] ?? $defaultColors[$fIdx % 4];
                $theme = $colorMap[$cKey] ?? $colorMap['blue'];
            ?>
              <div class="m-ab-feature-card">
                <div>
                  <div class="m-ab-feature-icon" style="background:<?= $theme['icon_bg'] ?>;border:1px solid <?= $theme['icon_border'] ?>;">
                    <?= e($feat['icon'] ?? '⚡') ?>
                  </div>
                  <h4 class="m-ab-feature-title"><?= e(__t($feat['title'] ?? '', $feat['title'] ?? '')) ?></h4>
                  <p class="m-ab-feature-desc"><?= e(__t($feat['body'] ?? '', $feat['body'] ?? '')) ?></p>
                </div>
                <?php if (!empty($feat['badge'])): ?>
                  <div class="m-ab-feature-tag" style="color:<?= $theme['tag_color'] ?>;">
                    <span>✓</span>
                    <span><?= e(__t($feat['badge'], $feat['badge'])) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Bottom Eligibility Callout -->
          <div class="m-ab-eligibility">
            <div class="m-ab-eligibility-left">
              <span class="m-ab-eligibility-icon">ℹ️</span>
              <div class="m-ab-eligibility-text">
                <span class="m-ab-eligibility-title"><?= e(__t($bEligTitle, $bEligTitle)) ?></span>
                <span><?= e(__t($bEligText, $bEligText)) ?></span>
              </div>
            </div>
            <?php if (!empty($bEligBadge)): ?>
              <div class="m-ab-eligibility-badge">
                <?= e($bEligBadge) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <style>
          .marketing-app-builder-showcase {
            margin-top: 48px;
            background: linear-gradient(180deg, var(--dark-surface, #0c1029) 0%, var(--bg-dark, #070a1a) 100%);
            border: 1px solid var(--card-border, rgba(59, 130, 246, 0.28));
            border-radius: 24px;
            padding: 38px 34px;
            color: var(--text-white, #ffffff);
            box-shadow: 0 20px 45px -15px rgba(2, 6, 23, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
            transition: background 0.3s ease, border-color 0.3s ease, color 0.3s ease;
          }
          .m-ab-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
          }
          .m-ab-header-text {
            flex: 1 1 540px;
          }
          .m-ab-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 9999px;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(96, 165, 250, 0.4);
            color: #60a5fa;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
          }
          .m-ab-title {
            margin: 14px 0 8px;
            font-size: 26px;
            font-weight: 900;
            line-height: 1.25;
            color: var(--text-white, #ffffff);
            letter-spacing: -0.02em;
          }
          .m-ab-subtitle {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: var(--dark-muted, #94a3b8);
            max-width: 760px;
          }
          .m-ab-btn-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 4px;
          }
          .m-ab-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
          }
          .m-ab-btn-doc {
            background: var(--btn-sec-bg, rgba(255, 255, 255, 0.08));
            color: var(--btn-sec-color, #ffffff) !important;
            border: 1px solid var(--btn-sec-border, rgba(255, 255, 255, 0.2));
          }
          .m-ab-btn-doc:hover {
            background: var(--btn-sec-border, rgba(255, 255, 255, 0.2));
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
          }
          .m-ab-btn-launch {
            background: var(--cta-gradient, linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%));
            color: #ffffff !important;
            border: 1px solid transparent;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
            font-weight: 800;
          }
          .m-ab-btn-launch:hover {
            opacity: 0.95;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.5);
          }
          .m-ab-divider {
            margin: 28px 0 24px;
            border-bottom: 1px solid var(--card-border, rgba(255, 255, 255, 0.08));
          }
          .m-ab-features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
          }
          .m-ab-feature-card {
            background: var(--card-bg, #0f1738);
            border: 1px solid var(--card-border, rgba(255, 255, 255, 0.08));
            border-radius: 16px;
            padding: 22px 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            transition: all 0.2s ease;
          }
          .m-ab-feature-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--btn-pri-bg, #60a5fa);
            transform: translateY(-2px);
          }
          .m-ab-feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 12px;
          }
          .m-ab-feature-title {
            margin: 0 0 6px;
            font-size: 15px;
            font-weight: 800;
            color: var(--text-white, #f8fafc);
          }
          .m-ab-feature-desc {
            margin: 0;
            font-size: 13px;
            line-height: 1.55;
            color: var(--dark-muted, #94a3b8);
          }
          .m-ab-feature-tag {
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
          }
          .m-ab-eligibility {
            margin-top: 24px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.28);
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
          }
          .m-ab-eligibility-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1 1 500px;
          }
          .m-ab-eligibility-icon {
            font-size: 22px;
            flex-shrink: 0;
          }
          .m-ab-eligibility-text {
            font-size: 13px;
            line-height: 1.5;
            color: #cbd5e1;
          }
          .m-ab-eligibility-title {
            font-weight: 800;
            color: #93c5fd;
            margin-right: 4px;
          }
          .m-ab-eligibility-badge {
            padding: 6px 14px;
            border-radius: 8px;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.45);
            color: #34d399;
            font-weight: 800;
            font-size: 12px;
            font-family: monospace;
            white-space: nowrap;
          }

          /* Light Theme Matching Pattern */
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching,
          .marketing-app-builder-showcase.ab-mode-light {
            background: linear-gradient(180deg, #ffffff 0%, var(--dark-surface, #f8fafc) 100%) !important;
            border: 1px solid var(--card-border, #e2e8f0) !important;
            color: var(--text-dark, #0f172a) !important;
            box-shadow: 0 16px 40px -10px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(15, 23, 42, 0.04) !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-pill,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-pill {
            background: #eff6ff !important;
            border-color: #bfdbfe !important;
            color: #1d4ed8 !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-title,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-title {
            color: #0f172a !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-subtitle,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-subtitle {
            color: #475569 !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-divider,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-divider {
            border-bottom-color: #e2e8f0 !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-feature-card,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-feature-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04) !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-feature-card:hover,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-feature-card:hover {
            background: #ffffff !important;
            border-color: var(--btn-pri-bg, #3b82f6) !important;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08) !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-feature-title,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-feature-title {
            color: #0f172a !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-feature-desc,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-feature-desc {
            color: #64748b !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-eligibility,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-eligibility {
            background: #eff6ff !important;
            border: 1px solid #bfdbfe !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-eligibility-title,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-eligibility-title {
            color: #1d4ed8 !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-eligibility-text,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-eligibility-text {
            color: #1e3a8a !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-eligibility-badge,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-eligibility-badge {
            background: #dcfce7 !important;
            border-color: #86efac !important;
            color: #15803d !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-btn-doc,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-btn-doc {
            background: var(--btn-sec-bg, #f1f5f9) !important;
            border-color: var(--btn-sec-border, #cbd5e1) !important;
            color: var(--btn-sec-color, #0f172a) !important;
          }
          [data-theme="light"] .marketing-app-builder-showcase.ab-mode-theme_matching .m-ab-btn-doc:hover,
          .marketing-app-builder-showcase.ab-mode-light .m-ab-btn-doc:hover {
            background: #e2e8f0 !important;
            color: #0f172a !important;
          }

          /* Always Dark mode */
          .marketing-app-builder-showcase.ab-mode-dark {
            background: linear-gradient(180deg, #0d1428 0%, #070b1a 100%) !important;
            border-color: rgba(59, 130, 246, 0.28) !important;
            color: #ffffff !important;
          }
          .marketing-app-builder-showcase.ab-mode-dark .m-ab-title { color: #ffffff !important; }
          .marketing-app-builder-showcase.ab-mode-dark .m-ab-subtitle { color: #94a3b8 !important; }
          .marketing-app-builder-showcase.ab-mode-dark .m-ab-feature-card { background: #0f1738 !important; border-color: rgba(255, 255, 255, 0.08) !important; }
          .marketing-app-builder-showcase.ab-mode-dark .m-ab-feature-title { color: #ffffff !important; }
          .marketing-app-builder-showcase.ab-mode-dark .m-ab-feature-desc { color: #94a3b8 !important; }

          @media (max-width: 900px) {
            .marketing-app-builder-showcase {
              padding: 26px 20px;
              border-radius: 18px;
            }
            .m-ab-features-grid {
              grid-template-columns: 1fr;
              gap: 14px;
            }
            .m-ab-title {
              font-size: 22px;
            }
          }
        </style>
      <?php endif; ?>

      <!-- Custom Bundle Builder Toggle / Accordion (Hidden per request) -->
      <div style="display:none;margin-top:40px;text-align:center;">
        <button type="button" class="btn" id="toggle-custom-builder-btn" style="background:#ffffff;border:1px solid #cbd5e1;color:var(--text-dark);font-size:13px;padding:10px 22px;border-radius:9999px;">
          🛠️ Need specific modules? Click to build custom bundle &rarr;
        </button>
      </div>

      <div id="custom-builder-wrapper" class="custom-builder-wrapper" style="display:none;margin-top:30px;background:#0c1229;border:1px solid var(--dark-border);border-radius:20px;color:#fff;">
        <div style="margin-bottom:24px;">
          <h3 style="font-size:22px;font-weight:800;margin-bottom:6px;">🛠️ Interactive Custom Bundle Builder</h3>
          <p style="font-size:13px;color:var(--dark-muted);">
            Select your desired modules. Bundle discounts (10% to 20% off) automatically apply as you add modules!
          </p>
        </div>

        <div class="custom-builder-grid">
          <div>
            <div style="background:#131c3f;border:1px solid rgba(99,102,241,0.3);border-radius:12px;padding:14px;display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
              <label style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:700;cursor:default;">
                <input type="checkbox" class="calc-module-checkbox" data-slug="core" data-name="Core Platform (Retail, Restaurant & Café)" data-price="<?= (float)$core['price'] ?>" checked disabled style="width:18px;height:18px;">
                ★ Core Platform (Retail, Restaurant &amp; Café Included)
              </label>
              <span style="font-weight:800;color:#818cf8;"><?= $currencySym ?><?= number_format((float)$core['price'], 2) ?></span>
            </div>

            <?php foreach ($modules as $idx => $m): ?>
              <div style="background:#0f1738;border:1px solid var(--dark-border);border-radius:12px;padding:14px;display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <label style="display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;cursor:pointer;">
                  <input type="checkbox" class="calc-module-checkbox" data-slug="<?= e_attr($m['slug']) ?>" data-name="<?= e_attr($m['name']) ?>" data-price="<?= (float)$m['price'] ?>" <?= ($idx === 0) ? 'checked' : '' ?> style="width:18px;height:18px;">
                  ＋ <?= e_attr($m['name']) ?>
                </label>
                <span style="font-weight:700;color:#38bdf8;">+<?= $currencySym ?><?= number_format((float)$m['price'], 2) ?></span>
              </div>
            <?php endforeach; ?>
          </div>

          <div style="background:#080d21;border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:22px;display:flex;flex-direction:column;justify-content:space-between;">
            <div>
              <div style="font-size:15px;font-weight:800;margin-bottom:14px;">Bundle Summary</div>
              <ul id="calc-selected-items" style="list-style:none;margin-bottom:18px;min-height:50px;"></ul>
              
              <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--dark-muted);margin-bottom:6px;">
                <span>Regular Subtotal:</span>
                <span id="calc-subtotal-val"><?= $currencySym ?>0.00</span>
              </div>
              <div style="display:none;justify-content:space-between;font-size:13px;color:#34d399;margin-bottom:6px;" id="calc-discount-row">
                <span>Bundle Discount:</span>
                <span id="calc-discount-val">-<?= $currencySym ?>0.00</span>
              </div>
            </div>

            <div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding-top:14px;border-top:1px solid rgba(255,255,255,0.1);margin-bottom:14px;">
                <span style="font-size:15px;font-weight:700;">Total Amount:</span>
                <span id="calc-total-val" style="font-size:24px;font-weight:900;color:#38bdf8;"><?= $currencySym ?>0.00</span>
              </div>
              <button type="button" class="btn btn-hero-buy open-checkout-btn" id="calc-buy-btn" style="width:100%" data-type="custom" data-title="Custom Module Bundle" data-price="0.00" data-modules="">
                Buy Custom Bundle &rarr;
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- ================= 7. Why Zoom POS? ================= -->
  <section class="why-us-section" id="why-us">
    <div class="container">
      <div class="section-hdr text-center">
        <h2 class="sec-title-dark">Why Zoom POS?</h2>
        <p class="sec-subtitle-dark">Built different from subscription POS.</p>
      </div>

      <div class="why-cards-grid">
        <div class="why-card">
          <div class="why-icon-box">🛡️</div>
          <h3 class="why-card-title">Own Your Platform</h3>
          <p class="why-card-desc">No dependency on a hosted third-party provider or surprise subscription price hikes.</p>
        </div>

        <div class="why-card">
          <div class="why-icon-box">🖥️</div>
          <h3 class="why-card-title">Your Infrastructure</h3>
          <p class="why-card-desc">Deploy on your own server, VPS or cloud host with complete data sovereignty.</p>
        </div>

        <div class="why-card">
          <div class="why-icon-box">🧩</div>
          <h3 class="why-card-title">Flexible Architecture</h3>
          <p class="why-card-desc">Native retail, restaurant and pluggable vertical modules in a single unified codebase.</p>
        </div>

        <div class="why-card">
          <div class="why-icon-box">📱</div>
          <h3 class="why-card-title">Multi-Device Ready</h3>
          <p class="why-card-desc">Responsive layouts for desktop monitors, touch POS terminals, tablets and mobile phones.</p>
        </div>

        <div class="why-card">
          <div class="why-icon-box">☁️</div>
          <h3 class="why-card-title">Offline Resilient</h3>
          <p class="why-card-desc">Continue taking customer orders and printing receipts without internet disruptions.</p>
        </div>

        <div class="why-card">
          <div class="why-icon-box">📈</div>
          <h3 class="why-card-title">Business Ready</h3>
          <p class="why-card-desc">Complete inventory control, multi-register cash drawers, customer credit ledgers &amp; tax reports.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= 8. Ecosystem Architecture Diagram ================= -->
  <section class="ecosystem-section">
    <div class="container">
      <div class="section-hdr text-center">
        <h2 class="sec-title-light">One Business. One Ecosystem.</h2>
        <p class="sec-subtitle-light">Modular architecture for endless commercial possibilities.</p>
      </div>

      <div class="ecosystem-diagram-card">
        <div class="eco-diagram-wrapper">
          <!-- Retail -->
          <div class="eco-node eco-node-retail">
            <div class="eco-node-title">Retail POS</div>
            <div class="eco-node-tags">Inventory • Barcodes<br>GST • Ledgers</div>
          </div>

          <!-- Restaurant -->
          <div class="eco-node eco-node-restaurant">
            <div class="eco-node-title">Restaurant &amp; Café</div>
            <div class="eco-node-tags">Tables • KOT • KDS<br>Recipes • Waiter App</div>
          </div>

          <div class="eco-connector"></div>

          <!-- Central Core Platform -->
          <div class="eco-node-center">
            <div class="eco-brand-badge">
              <span>🛒</span> Zoom POS
            </div>
            <div class="eco-core-card">
              <div class="eco-core-title">Core Platform</div>
              <div class="eco-core-sub">
                <span>Multi-Store</span> • <span>SaaS Engine</span> • <span>Thermal Printing</span>
              </div>
            </div>
          </div>

          <div class="eco-connector"></div>

          <!-- Pharmacy -->
          <div class="eco-node eco-node-pharmacy">
            <div class="eco-node-title">Pharmacy POS</div>
            <div class="eco-node-tags">Batches • Expiry<br>Prescriptions • Salts</div>
          </div>

          <!-- Salon -->
          <div class="eco-node eco-node-salon">
            <div class="eco-node-title">Salon &amp; Spa</div>
            <div class="eco-node-tags">Appointments • Chairs<br>Stylists • Tips</div>
          </div>

          <!-- Repair -->
          <div class="eco-node eco-node-repair">
            <div class="eco-node-title">Repair Service</div>
            <div class="eco-node-tags">Job Tickets • Parts<br>Diagnostics • Lifecycle</div>
          </div>

          <!-- CRM -->
          <div class="eco-node eco-node-crm">
            <div class="eco-node-title">Lead CRM</div>
            <div class="eco-node-tags">Pipeline • Deals<br>Follow-ups • Quotations</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= 9. Call to Action Banner ================= -->
  <section class="cta-banner-section">
    <div class="container">
      <div class="cta-banner-card">
        <div class="cta-banner-content">
          <div class="cta-rocket-icon">🚀</div>
          <div>
            <h2 class="cta-banner-title">Ready to Build Your Own Business Platform?</h2>
            <p class="cta-banner-subtitle">Launch a modern POS and business management system under your own infrastructure.</p>
          </div>
        </div>
        <div class="cta-banner-actions">
          <a href="#pricing" class="btn btn-cta-start">Get Started</a>
          <a href="#live-demos" class="btn btn-cta-demo open-demo-hub-btn">Test Drive Live Demo</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= 10. FAQ Section ================= -->
  <?php if (!empty($faqs)): ?>
  <section class="faq-section" id="faq">
    <div class="container">
      <div class="section-hdr text-center">
        <h2 class="sec-title-light">Frequently Asked Questions</h2>
        <p class="sec-subtitle-light">Everything you need to know about licensing, self-hosting, and lifetime updates.</p>
      </div>

      <div class="faq-grid">
        <?php foreach ($faqs as $faq): ?>
          <div class="faq-item">
            <div class="faq-q"><?= e_attr($faq['q'] ?? '') ?></div>
            <div class="faq-a"><?= e_attr($faq['a'] ?? '') ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ================= 11. Footer ================= -->
  <footer class="site-footer">
    <div class="container footer-container">
      <div class="footer-top">
        <div class="footer-brand-wrap">
          <div class="brand">
            <span class="brand-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
              </svg>
            </span>
            <span class="brand-name">Zoom POS &amp; Market</span>
          </div>
          <p class="footer-tagline"><?= e_attr($tagline) ?></p>
        </div>

        <nav class="footer-nav">
          <a href="#home">Home</a>
          <a href="#overview">Overview</a>
          <a href="#business-types">Business Types</a>
          <a href="#modules">Modules</a>
          <a href="#pricing">Pricing</a>
          <a href="#why-us">About</a>
          <a href="#faq">FAQ</a>
        </nav>

        <div class="footer-socials">
          <a href="#" class="footer-social-icon" aria-label="Facebook">f</a>
          <a href="#" class="footer-social-icon" aria-label="YouTube">▶</a>
          <a href="#" class="footer-social-icon" aria-label="LinkedIn">in</a>
          <a href="#" class="footer-social-icon" aria-label="Twitter">𝕏</a>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> Zoom POS &amp; Market. All rights reserved.</p>
        <div class="footer-meta-links">
          <a href="verify-license.php">Verify License Key</a>
          <span>&bull;</span>
          <?php if (!empty($urls['documentation'])): ?>
            <a href="<?= e_attr($urls['documentation']) ?>" target="_blank">Documentation</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </footer>

  <!-- ================= Checkout Modal ================= -->
  <div class="modal-backdrop" id="checkout-modal">
    <div class="modal-card">
      <button type="button" class="modal-close" id="modal-close-btn">&times;</button>
      
      <h3 class="modal-title">Complete Your Purchase</h3>
      <p class="modal-sub">License keys will be dispatched instantly to your registered email address.</p>

      <div class="modal-summary-box">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <strong id="modal-item-title" style="font-size:15px;color:var(--text-dark);">Selected Plan</strong>
          <strong id="modal-item-price" style="font-size:18px;color:var(--primary-purple);"><?= $currencySym ?>0.00</strong>
        </div>
        <div id="modal-item-list" style="font-size:12px;color:var(--text-muted);margin-top:6px;"></div>
      </div>

      <form action="checkout.php" method="post" id="modal-checkout-form">
        <input type="hidden" name="bundle" id="modal-bundle-input" value="">
        <input type="hidden" name="product" id="modal-product-input" value="">
        <input type="hidden" name="modules" id="modal-modules-input" value="">
        <input type="hidden" name="include_core" id="modal-include-core-input" value="1">

        <div class="modal-field">
          <label>Your Registered Email Address</label>
          <input type="email" name="email" id="modal-email-input" required placeholder="admin@yourcompany.com">
        </div>

        <div class="modal-field">
          <label>Target Domain For License Binding</label>
          <input type="text" name="domain" id="modal-domain-input" required placeholder="pos.yourcompany.com">
        </div>

        <button type="submit" class="btn btn-hero-buy" style="width:100%;margin-top:8px;">
          Proceed to Secure Checkout &rarr;
        </button>
      </form>
    </div>
  </div>

  <!-- ================= Live Demo Hub Modal ================= -->
  <div class="modal-backdrop" id="demo-hub-modal">
    <div class="modal-card modal-demo-hub-card">
      <button type="button" class="modal-close" id="demo-hub-modal-close-btn">&times;</button>
      
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
        <span style="font-size:26px;">🎯</span>
        <div>
          <h3 class="modal-title" style="margin-bottom:2px;">Experience Zoom POS Live</h3>
          <p class="modal-sub" style="margin-bottom:0;">Choose a platform below to test-drive features live or download native client apps.</p>
        </div>
      </div>

      <div class="modal-demo-grid">
        <?php foreach ($demoLinks as $key => $d): ?>
          <?php if (!empty($d['url'])): ?>
            <div class="modal-demo-card">
              <div>
                <div class="modal-demo-top">
                  <span class="modal-demo-icon"><?= e_attr($d['icon'] ?? '📱') ?></span>
                  <div>
                    <h4 class="modal-demo-title"><?= e_attr($d['title']) ?></h4>
                    <span class="modal-demo-badge"><?= e_attr($d['badge'] ?? 'Live Demo') ?></span>
                  </div>
                </div>
                <p class="modal-demo-desc"><?= e_attr($d['desc']) ?></p>
              </div>
              <div>
                <?php
                  $btnClass = 'btn-demo-web';
                  if ($key === 'flutter_windows') $btnClass = 'btn-demo-win';
                  elseif ($key === 'flutter_android') $btnClass = 'btn-demo-android';
                  elseif ($key === 'superadmin') $btnClass = 'btn-demo-admin';
                  elseif ($key === 'store') $btnClass = 'btn-demo-store';
                  elseif ($key === 'storefront') $btnClass = 'btn-demo-storefront';
                ?>
                <a href="<?= e_attr($d['url']) ?>" target="_blank" class="btn-demo-action <?= $btnClass ?>" <?= (!empty($d['type']) && $d['type'] === 'download') ? 'download' : '' ?>>
                  <?= e_attr($d['btn_text'] ?? 'Open Demo ↗') ?>
                </a>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php foreach ($otherDemoLinks as $ol): ?>
          <?php if (!empty($ol['url'])): ?>
            <div class="modal-demo-card">
              <div>
                <div class="modal-demo-top">
                  <span class="modal-demo-icon"><?= e_attr($ol['icon'] ?? '🔗') ?></span>
                  <div>
                    <h4 class="modal-demo-title"><?= e_attr($ol['title']) ?></h4>
                    <span class="modal-demo-badge"><?= e_attr($ol['badge'] ?? 'Custom Demo') ?></span>
                  </div>
                </div>
                <p class="modal-demo-desc"><?= e_attr($ol['desc'] ?? '') ?></p>
              </div>
              <div>
                <a href="<?= e_attr($ol['url']) ?>" target="_blank" class="btn-demo-action btn-demo-custom">
                  <?= e_attr($ol['btn_text'] ?? 'Open Demo ↗') ?>
                </a>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <script>
    window.LANDING_DATA = <?= json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  </script>
  <script src="<?= marketing_asset('js/marketing.js') ?>"></script>

</body>
</html>
