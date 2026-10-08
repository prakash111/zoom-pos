<?php

function builder_documentation_info(): array
{
    // 1. Try to load landing configuration helper if available
    if (!function_exists('get_landing_config')) {
        $helperCandidates = [
            dirname(__DIR__, 2) . '/lib/landing_helper.php',
            dirname(__DIR__, 3) . '/lib/landing_helper.php',
        ];
        foreach ($helperCandidates as $cand) {
            if (file_exists($cand)) {
                require_once $cand;
                break;
            }
        }
    }

    if (function_exists('get_landing_config')) {
        try {
            $landingCfg = get_landing_config();
            $docUrl = $landingCfg['documentation_url'] ?? '';
            if (!empty($docUrl)) {
                return [
                    'url'   => $docUrl,
                    'title' => 'Official Software Documentation & Guides',
                ];
            }
        } catch (Throwable $e) {}
    }

    // 2. Fallback to SaaS portal documentation
    $host = $_SERVER['HTTP_HOST'] ?? 'saas.zoomnearby.com';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';

    return [
        'url'   => "{$scheme}://{$host}/documentation/index.html",
        'title' => 'Comprehensive App Builder & API Guide',
    ];
}

function builder_header(string $activeTab, string $pageTitle = 'App Builder'): void
{
    $license = Auth::license();
    $licenseKey = $license['license_key'] ?? 'No License';
    $clientEmail = $license['client_email'] ?? 'licensee@zoomnearby.com';
    $planName = ucfirst($license['plan'] ?? 'Regular');

    $docInfo = builder_documentation_info();
    $maskedLicense = (strlen($licenseKey) > 10)
        ? substr($licenseKey, 0, 5) . '-•••••-' . substr($licenseKey, -4)
        : ($licenseKey ?: 'DEMO-LICENSE');

    $tabs = [
        'dashboard' => ['label' => 'Dashboard', 'url' => 'index.php', 'icon' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>'],
        'new-build' => ['label' => 'New build', 'url' => 'new-build.php', 'icon' => '<path d="M12 5v14M5 12h14"/>'],
        'builds'    => ['label' => 'Build history', 'url' => 'builds.php', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        'guide'     => ['label' => 'Documentation', 'url' => $docInfo['url'], 'target' => '_blank', 'icon' => '<path d="M4 5a2 2 0 0 1 2-2h12v18H6a2 2 0 0 1-2-2z"/><path d="M8 7h6"/>'],
    ];

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($pageTitle) . ' · App Builder</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<style>
:root{--bg:#f6f7fb;--surface:#fff;--ink:#12142a;--muted:#5d6280;--line:#e4e6f0;--brand:#4f46e5;--brand-ink:#3730a3;--brand-soft:#eef0ff;--ok:#0f9d6a;--ok-soft:#e6f6ef;--warn:#b4540a;--warn-soft:#fff3e4;--danger:#dc2626;--danger-soft:#fee2e2;--r:12px;--font:\'Onest\',system-ui,-apple-system,\'Segoe UI\',sans-serif;--mono:ui-monospace,\'SF Mono\',Menlo,monospace}
@media (prefers-color-scheme:dark){:root:not([data-theme=light]){--bg:#0e1020;--surface:#171a30;--ink:#eef0ff;--muted:#a0a5c6;--line:#2a2e4d;--brand:#7c78ff;--brand-ink:#c9c7ff;--brand-soft:#23265a;--ok:#34d399;--ok-soft:#10382c;--warn:#fbbf6a;--warn-soft:#3a2a12;--danger:#f87171;--danger-soft:#3f1418}}
:root[data-theme=dark]{--bg:#0e1020;--surface:#171a30;--ink:#eef0ff;--muted:#a0a5c6;--line:#2a2e4d;--brand:#7c78ff;--brand-ink:#c9c7ff;--brand-soft:#23265a;--ok:#34d399;--ok-soft:#10382c;--warn:#fbbf6a;--warn-soft:#3a2a12;--danger:#f87171;--danger-soft:#3f1418}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 var(--font);-webkit-font-smoothing:antialiased}
button,input,select{font:inherit;color:inherit}
:focus-visible{outline:2px solid var(--brand);outline-offset:2px}
.app{display:grid;grid-template-columns:232px 1fr;min-height:100vh}
aside{position:sticky;top:0;height:100vh;background:var(--surface);border-right:1px solid var(--line);padding:20px 14px;display:flex;flex-direction:column;gap:20px}
.logo{display:flex;align-items:center;gap:10px;font-weight:700;font-size:17px;padding:0 8px;color:var(--ink);text-decoration:none}
.logo i{width:32px;height:32px;border-radius:9px;background:var(--brand);display:grid;place-items:center}
.logo svg{width:18px;height:18px;stroke:#fff}
nav a{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:9px;color:var(--muted);text-decoration:none;font-weight:500}
nav a svg{width:18px;height:18px;flex:none}
nav a:hover{background:var(--bg)}
nav a[aria-current]{background:var(--brand-soft);color:var(--brand-ink);font-weight:600}
.lic{margin-top:auto;border:1px solid var(--line);border-radius:var(--r);padding:12px}
.lic small{color:var(--muted);display:block}
.lic b{display:block;font-family:var(--mono);font-size:13px;margin:2px 0 8px}
.tag{display:inline-block;font-size:12px;font-weight:600;padding:2px 9px;border-radius:99px;background:var(--ok-soft);color:var(--ok)}
.tag.warn{background:var(--warn-soft);color:var(--warn)}
.tag.danger{background:var(--danger-soft);color:var(--danger)}
.tag.brand{background:var(--brand-soft);color:var(--brand)}
main{min-width:0}
.top{position:sticky;top:0;z-index:20;display:flex;align-items:center;gap:12px;padding:12px 28px;background:color-mix(in srgb,var(--bg) 88%,transparent);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
.dot{width:8px;height:8px;border-radius:50%;background:var(--ok)}
.top .sp{flex:1}
.btn{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--line);background:var(--surface);padding:8px 14px;border-radius:10px;font-weight:600;cursor:pointer;font-size:14px;color:inherit;text-decoration:none}
.btn:hover{border-color:var(--brand)}
.btn.pri{background:var(--brand);border-color:var(--brand);color:#fff}
.btn.pri:hover{filter:brightness(1.08)}
.btn svg{width:16px;height:16px}
.wrap{padding:28px;max-width:1320px}
h1{font-size:28px;line-height:1.2;margin:0 0 6px;letter-spacing:-.02em}
.lead{color:var(--muted);margin:0 0 24px;max-width:65ch}
.card{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:22px;margin-bottom:20px}
.status-pill{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:2px 9px;border-radius:99px}
.status-pill.completed{background:var(--ok-soft);color:var(--ok)}
.status-pill.building,.status-pill.preparing{background:var(--brand-soft);color:var(--brand)}
.status-pill.failed{background:var(--danger-soft);color:var(--danger)}
.status-pill.queued{background:var(--warn-soft);color:var(--warn)}
@media (max-width:860px){.app{grid-template-columns:1fr}.app>aside{display:none}.wrap{padding:16px}.top{padding:10px 16px}}
</style>';
    echo '</head><body>';
    echo '<div class="app">';

    // Left Sidebar
    echo '<aside>';
    echo '<a href="index.php" class="logo"><i><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14.7 6.3 3 3M4 20l1-4L16 5a2.1 2.1 0 0 1 3 3L8 19z"/></svg></i>App Builder</a>';
    echo '<nav aria-label="Main">';
    foreach ($tabs as $key => $tab) {
        $cur = ($key === $activeTab) ? ' aria-current="page"' : '';
        $target = !empty($tab['target']) ? ' target="' . e($tab['target']) . '"' : '';
        echo '<a href="' . e($tab['url']) . '"' . $cur . $target . '>';
        echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $tab['icon'] . '</svg>';
        echo e($tab['label']);
        echo '</a>';
    }
    echo '</nav>';
    echo '<div class="lic"><small>Active license</small><b>' . e($maskedLicense) . '</b><span class="tag">' . e($planName) . ' plan</span></div>';
    echo '</aside>';

    // Main App Container
    echo '<main>';
    echo '<div class="top">';
    echo '<span class="dot"></span><span style="font-weight:500;font-size:14px">Build engine online</span>';
    echo '<span class="sp"></span>';
    echo '<button class="btn" id="theme" type="button">Theme</button>';
    echo '<a href="new-build.php" class="btn pri"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>New build</a>';
    echo '</div>';

    echo '<div class="wrap">';
}

function builder_footer(): void
{
    echo '</div>'; // .wrap
    echo '</main>';
    echo '</div>'; // .app
    echo '<script>
const themeBtn = document.getElementById("theme");
if (themeBtn) {
  themeBtn.onclick = () => {
    const r = document.documentElement;
    const isDark = getComputedStyle(r).getPropertyValue("--bg").trim() == "#0e1020";
    const next = isDark ? "light" : "dark";
    r.dataset.theme = next;
    localStorage.setItem("zn_theme", next);
  };
}
const savedTheme = localStorage.getItem("zn_theme");
if (savedTheme) {
  document.documentElement.dataset.theme = savedTheme;
}
</script>';
    echo '</body></html>';
}
