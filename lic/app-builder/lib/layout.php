<?php

function builder_documentation_info(): array
{
    // 1. Try to load landing configuration helper if available
    if (!function_exists('get_landing_config')) {
        $helperCandidates = [
            dirname(__DIR__, 2) . '/lib/landing_helper.php',
            dirname(__DIR__) . '/../lib/landing_helper.php',
            __DIR__ . '/../../lib/landing_helper.php',
        ];
        foreach ($helperCandidates as $hc) {
            if (file_exists($hc)) {
                require_once $hc;
                break;
            }
        }
    }

    if (function_exists('get_landing_config')) {
        $cfg = get_landing_config();
        $url = !empty($cfg['documentation_url']) ? (string)$cfg['documentation_url'] : '';
        $title = !empty($cfg['documentation_title']) ? (string)$cfg['documentation_title'] : 'Documentation';
        if (!empty($url)) {
            return ['url' => $url, 'title' => $title];
        }
    }

    // 2. Direct database landing_page_config JSON inspection
    $raw = function_exists('lic_setting') ? lic_setting('landing_page_config', '') : (function_exists('setting') ? setting('landing_page_config', '') : '');
    if (!empty($raw)) {
        $cfg = json_decode($raw, true);
        if (is_array($cfg) && !empty($cfg['documentation_url'])) {
            return [
                'url' => (string)$cfg['documentation_url'],
                'title' => !empty($cfg['documentation_title']) ? (string)$cfg['documentation_title'] : 'Documentation',
            ];
        }
    }

    // 3. Fallback to standalone setting or default
    return [
        'url' => (string)setting('documentation_url', 'https://saas.zoomnearby.com/documentation'),
        'title' => 'Documentation',
    ];
}

function builder_header(string $activeTab, string $pageTitle = 'App Builder'): void
{
    $license = Auth::license();
    $licenseKey = $license['license_key'] ?? 'No License';
    $clientEmail = $license['client_email'] ?? 'licensee@zoomnearby.com';
    $planName = ucfirst($license['plan'] ?? 'Regular');

    $docInfo = builder_documentation_info();

    $tabs = [
        'dashboard' => ['label' => 'Dashboard', 'icon' => '📊', 'url' => 'index.php'],
        'new-build' => ['label' => 'New Build', 'icon' => '🚀', 'url' => 'new-build.php'],
        'builds'    => ['label' => 'Build History', 'icon' => '📜', 'url' => 'builds.php'],
        'guide'     => ['label' => 'Documentation', 'icon' => '📖', 'url' => $docInfo['url'], 'title' => $docInfo['title'], 'target' => '_blank'],
    ];

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">';
    echo '<title>' . e($pageTitle) . ' — ZoomNearby Cloud App Builder</title>';
    $assetVer = '2.2.' . (file_exists(__DIR__ . '/../assets/preview.js') ? filemtime(__DIR__ . '/../assets/preview.js') : time());
    echo '<link rel="stylesheet" href="assets/app.css?v=' . $assetVer . '">';
    echo '<link rel="stylesheet" href="assets/preview.css?v=' . $assetVer . '">';
    echo '<script src="assets/builder.js?v=' . $assetVer . '"></script>';
    echo '<script src="assets/preview.js?v=' . $assetVer . '"></script>';
    echo '</head><body>';
    echo '<div id="spa-loader"></div>';
    
    // Backdrop for mobile sidebar drawer
    echo '<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleMobileSidebar(false)"></div>';

    echo '<div class="app-shell">';

    // Left Sidebar
    echo '<aside class="app-sidebar" id="app-sidebar">';
    echo '<div class="sidebar-header" style="justify-content:space-between;">';
    echo '<a href="index.php" class="sidebar-logo">';
    echo '<div class="sidebar-logo-icon" style="background:#4f46e5;color:#fff;display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;font-weight:900;">🔨</div>';
    echo '<span>App Builder</span>';
    echo '</a>';
    echo '<button type="button" class="sidebar-close-btn" onclick="toggleMobileSidebar(false)" aria-label="Close menu">✕</button>';
    echo '</div>';

    echo '<div class="sidebar-content">';
    echo '<div class="sidebar-label">Navigation</div>';
    echo '<nav class="sidebar-nav">';
    foreach ($tabs as $key => $tab) {
        $cls = ($key === $activeTab) ? 'sidebar-link on' : 'sidebar-link';
        $target = !empty($tab['target']) ? ' target="' . e($tab['target']) . '"' : '';
        $titleAttr = !empty($tab['title']) ? ' title="' . e($tab['title']) . '"' : '';
        echo '<a href="' . e($tab['url']) . '" class="' . $cls . '"' . $target . $titleAttr . '>';
        echo '<span class="icon">' . $tab['icon'] . '</span>';
        echo '<span>' . e($tab['label']) . '</span>';
        echo '</a>';
    }
    echo '</nav>';

    // License Badge in Sidebar
    $account = Auth::account();
    echo '<div style="margin:24px 16px 12px;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">';
    echo '<div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Active License</div>';
    echo '<div style="font-size:12px;font-weight:700;color:#0f172a;margin-top:2px;font-family:monospace;">' . e(substr($licenseKey, 0, 11)) . '•••••</div>';
    if (!empty($account['company_name'])) {
        echo '<div style="margin-top:6px;font-size:11.5px;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' . e($account['company_name']) . '">🏢 ' . e($account['company_name']) . '</div>';
    }
    echo '<div style="margin-top:6px;display:flex;gap:4px;">';
    echo '<span class="status-pill active" style="font-size:10px;padding:2px 6px;">● ' . e($planName) . ' Plan</span>';
    echo '</div>';
    echo '</div>';

    echo '</div>'; // sidebar-content

    echo '<div class="sidebar-footer">';
    echo '<div class="sidebar-user">';
    $avatarLetter = !empty($account['company_name']) ? substr($account['company_name'], 0, 1) : substr($clientEmail, 0, 1);
    echo '<div class="user-avatar" style="background:#6366f1;color:#fff;">' . strtoupper($avatarLetter) . '</div>';
    echo '<div class="user-meta">';
    $userDisplayTitle = !empty($account['company_name']) ? $account['company_name'] : $clientEmail;
    $userSubTitle = !empty($account['company_name']) ? $clientEmail : 'License Holder';
    echo '<div class="user-name" style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' . e($userDisplayTitle) . '">' . e($userDisplayTitle) . '</div>';
    echo '<div class="user-email" style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' . e($userSubTitle) . '">' . e($userSubTitle) . '</div>';
    echo '</div>';
    echo '<a href="logout.php" title="Change license" style="color:#ef4444;text-decoration:none;font-size:16px;padding:4px;font-weight:bold;">⇥</a>';
    echo '</div>';
    echo '</div>';
    echo '</aside>';

    // Main App Container
    echo '<div class="app-main-wrapper">';
    
    // Top Bar
    echo '<header class="app-topbar">';
    echo '<div style="display:flex;align-items:center;gap:10px;min-width:0;">';
    echo '<button type="button" class="mobile-menu-toggle" onclick="toggleMobileSidebar(true)" aria-label="Toggle menu">☰</button>';
    echo '<div class="topbar-status-badge" style="color:#10b981;font-size:12px;font-weight:600;display:flex;align-items:center;gap:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">';
    echo '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 6px #10b981;flex-shrink:0;"></span>';
    echo '<span class="engine-badge-text" style="color:#4b5563;">Cloud Build Engine (Online)</span>';
    echo '</div>';
    echo '</div>';

    echo '<div class="topbar-actions" style="display:flex;align-items:center;gap:8px;flex-shrink:0;">';
    echo '<a href="new-build.php" class="btn topbar-new-btn" style="background:#4f46e5;color:#fff;text-decoration:none;padding:7px 12px;border-radius:8px;font-weight:700;font-size:12px;white-space:nowrap;">+ <span class="new-build-text">Start New Build</span></a>';
    echo '<a href="index.php" class="topbar-btn" title="Refresh">↻</a>';
    echo '</div>';
    echo '</header>';

    echo '<main class="app-content">';
}

function builder_footer(): void
{
    echo '</main>'; // app-content
    echo '</div>';   // app-main-wrapper
    echo '</div>';   // app-shell
    echo '<script>
    function toggleMobileSidebar(open) {
        var sb = document.getElementById("app-sidebar");
        var bd = document.getElementById("sidebar-backdrop");
        if (!sb) return;
        var isOpen = (typeof open === "boolean") ? open : !sb.classList.contains("open");
        if (isOpen) {
            sb.classList.add("open");
            if (bd) bd.classList.add("active");
            document.body.style.overflow = "hidden";
        } else {
            sb.classList.remove("open");
            if (bd) bd.classList.remove("active");
            document.body.style.overflow = "";
        }
    }
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll(".sidebar-link").forEach(function(link) {
            link.addEventListener("click", function() {
                if (window.innerWidth <= 768) toggleMobileSidebar(false);
            });
        });
    });
    </script>';
    echo '</body></html>';
}
