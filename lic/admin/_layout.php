<?php

function lm_header(string $active, string $title = 'License Manager'): void
{
    $tabs = [
        'index' => ['label' => 'Licenses', 'icon' => '🔑'],
        'products' => ['label' => 'Products', 'icon' => '📦'],
        'payments' => ['label' => 'Orders', 'icon' => '💳'],
        'redeem' => ['label' => 'Redeem', 'icon' => '🎟️'],
        'settings' => ['label' => 'Settings', 'icon' => '⚙️'],
    ];

    $userName = defined('ADMIN_USER') ? ADMIN_USER : 'Admin';
    $userInitial = strtoupper(substr($userName, 0, 1)) ?: 'A';

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">';
    echo '<meta name="theme-color" content="#ffffff">';
    echo '<meta name="apple-mobile-web-app-capable" content="yes">';
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">';
    echo '<title>'.e($title).' — Quantro License Manager</title>';
    echo '<link rel="stylesheet" href="assets/app.css">';
    echo '</head><body>';
    echo '<div id="spa-loader"></div>';
    echo '<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleSidebar(false)"></div>';
    
    echo '<div class="app-shell">';
    
    // Left Sidebar
    echo '<aside class="app-sidebar" id="app-sidebar">';
    echo '<div class="sidebar-header">';
    echo '<a href="index.php" class="sidebar-logo">';
    echo '<div class="sidebar-logo-icon">Q</div>';
    echo '<span>Quantro</span>';
    echo '</a>';
    echo '</div>';

    echo '<div class="sidebar-content">';
    echo '<div class="sidebar-label">Main</div>';
    echo '<nav class="sidebar-nav">';
    foreach ($tabs as $file => $meta) {
        $cls = $file === $active ? 'sidebar-link on' : 'sidebar-link';
        echo '<a href="'.$file.'.php" class="'.$cls.'" data-tab="'.$file.'">';
        echo '<span class="icon">'.$meta['icon'].'</span>';
        echo '<span>'.e($meta['label']).'</span>';
        echo '</a>';
    }
    echo '</nav>';
    echo '</div>';

    echo '<div class="sidebar-footer">';
    echo '<div class="sidebar-user">';
    echo '<div class="user-avatar">'.$userInitial.'</div>';
    echo '<div class="user-meta">';
    echo '<div class="user-name">'.e($userName).'</div>';
    echo '<div class="user-email">admin@zoomnearby.com</div>';
    echo '</div>';
    echo '<a href="logout.php" title="Sign out" data-no-spa style="color:#9ca3af;text-decoration:none;font-size:15px;padding:4px">⇥</a>';
    echo '</div>';
    echo '</div>';
    echo '</aside>';

    // Main App Area
    echo '<div class="app-main-wrapper">';
    
    // Top Bar
    echo '<header class="app-topbar">';
    echo '<div style="display:flex;align-items:center;gap:12px">';
    echo '<button type="button" class="mobile-menu-toggle" onclick="toggleSidebar()" aria-label="Toggle menu">☰</button>';
    echo '<div style="color:#10b981;font-size:12px;font-weight:600;display:flex;align-items:center;gap:6px">';
    echo '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 6px #10b981"></span>';
    echo '<span style="color:#4b5563">License Server</span>';
    echo '</div>';
    echo '</div>';

    echo '<div class="topbar-search">';
    echo '<span class="search-icon">🔍</span>';
    echo '<input type="text" id="topbar-search-input" placeholder="Search licenses, products, domains..." value="'.e($_GET['q'] ?? '').'" onkeydown="if(event.key===\'Enter\'){spaNavigate(\'index.php?q=\'+encodeURIComponent(this.value));}">';
    echo '<span class="kbd">⌘K</span>';
    echo '</div>';

    $notifications = fetch_recent_notifications(8);
    $recentCount = 0;
    foreach ($notifications as $n) {
        if (strtotime($n['created_at']) >= (time() - 86400)) {
            $recentCount++;
        }
    }

    echo '<div class="topbar-actions">';
    echo '<a href="index.php" class="topbar-btn" title="Refresh" data-no-spa>↻</a>';
    echo '<div class="notif-wrap" id="notif-wrap">';
    echo '<button type="button" class="topbar-btn" id="notif-bell-btn" title="Notifications" onclick="toggleNotifications(event)">🔔';
    if ($recentCount > 0) {
        echo '<span class="notif-badge">'.($recentCount > 9 ? '9+' : $recentCount).'</span>';
    }
    echo '</button>';
    echo '<div class="notif-panel" id="notif-panel">';
    echo '<div class="notif-panel-title">Recent activity</div>';
    if (empty($notifications)) {
        echo '<div class="notif-empty">Nothing yet — new licenses and orders will show up here.</div>';
    } else {
        foreach ($notifications as $n) {
            echo '<a href="'.e($n['url']).'" class="notif-item">';
            echo '<span class="notif-icon">'.$n['icon'].'</span>';
            echo '<span class="notif-body"><span class="notif-item-title">'.e($n['title']).'</span>';
            echo '<span class="notif-item-sub">'.e($n['subtitle']).'</span></span>';
            echo '<span class="notif-time">'.e(time_ago($n['created_at'])).'</span>';
            echo '</a>';
        }
    }
    echo '</div>';
    echo '</div>';
    echo '<a href="logout.php" class="signout-btn" data-no-spa>Sign out</a>';
    echo '</div>';
    echo '</header>';

    // Content container
    echo '<main id="app-main" class="app-content">';

    if (! empty($_GET['msg'])) {
        echo '<div class="ok"><span>✓</span> '.e($_GET['msg']).'</div>';
    }
    if (! empty($_GET['err'])) {
        echo '<div class="err"><span>⚠️</span> '.e($_GET['err']).'</div>';
    }
}

function lm_footer(): void
{
    $tabs = [
        'index' => ['label' => 'Licenses', 'icon' => '🔑'],
        'products' => ['label' => 'Products', 'icon' => '📦'],
        'payments' => ['label' => 'Orders', 'icon' => '💳'],
        'redeem' => ['label' => 'Redeem', 'icon' => '🎟️'],
        'settings' => ['label' => 'Settings', 'icon' => '⚙️'],
    ];

    $currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');

    echo '</main>';
    echo '</div><!-- /.app-main-wrapper -->';
    echo '</div><!-- /.app-shell -->';

    // Mobile Bottom App Bar
    echo '<nav class="mobile-bottom-nav">';
    foreach ($tabs as $file => $meta) {
        $cls = $file === $currentScript ? 'mobile-nav-item on' : 'mobile-nav-item';
        echo '<a href="'.$file.'.php" class="'.$cls.'" data-tab="'.$file.'">';
        echo '<span class="nav-icon">'.$meta['icon'].'</span>';
        echo '<span>'.e($meta['label']).'</span>';
        echo '</a>';
    }
    echo '</nav>';

    // Self-Loading SPA & Interactive Script
    ?>
    <script>
    function toggleSidebar(force) {
        var sb = document.getElementById("app-sidebar");
        var bd = document.getElementById("sidebar-backdrop");
        if (!sb) return;
        var isOpen = sb.classList.contains("open");
        var nextState = (force !== undefined) ? force : !isOpen;
        if (nextState) {
            sb.classList.add("open");
            if (bd) bd.classList.add("active");
        } else {
            sb.classList.remove("open");
            if (bd) bd.classList.remove("active");
        }
    }

    // Keyboard shortcut: Cmd+K or Ctrl+K or / to search
    window.addEventListener("keydown", function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
            e.preventDefault();
            var inp = document.getElementById("topbar-search-input");
            if (inp) { inp.focus(); inp.select(); }
        }
        if (e.key === "Escape") {
            toggleNotifications(null, false);
        }
    });

    function toggleNotifications(evt, force) {
        if (evt) evt.stopPropagation();
        var panel = document.getElementById("notif-panel");
        var badge = document.querySelector("#notif-bell-btn .notif-badge");
        if (!panel) return;
        var next = (force !== undefined) ? force : !panel.classList.contains("open");
        panel.classList.toggle("open", next);
        if (next && badge) badge.style.display = "none";
    }

    document.addEventListener("click", function(e) {
        var wrap = document.getElementById("notif-wrap");
        if (wrap && !wrap.contains(e.target)) {
            toggleNotifications(null, false);
        }
    });

    // When arriving on a license's Inspect view, bring the detail panel into
    // view instead of leaving the visitor stranded at the top of the list.
    document.addEventListener("DOMContentLoaded", scrollToLicenseDetail);
    function scrollToLicenseDetail() {
        if (window.location.search.indexOf("key=") === -1) return;
        var el = document.getElementById("license-detail");
        if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    (function() {
        var loader = document.getElementById("spa-loader");
        var main = document.getElementById("app-main");
        var pageCache = new Map();
        var progressTimer = null;

        function setProgress(percent) {
            if (!loader) return;
            loader.style.width = percent + "%";
            if (percent > 0) {
                loader.classList.add("loading");
            } else {
                loader.classList.remove("loading");
            }
        }

        function startProgress() {
            clearTimeout(progressTimer);
            setProgress(20);
            progressTimer = setTimeout(function() {
                setProgress(65);
                progressTimer = setTimeout(function() {
                    setProgress(85);
                }, 300);
            }, 150);
        }

        function finishProgress() {
            clearTimeout(progressTimer);
            setProgress(100);
            setTimeout(function() {
                if (loader) {
                    loader.style.opacity = "0";
                    setTimeout(function() {
                        setProgress(0);
                        loader.style.opacity = "";
                    }, 250);
                }
            }, 150);
        }

        function updateActiveNav(url) {
            var path = url.split("?")[0];
            var page = path.split("/").pop().replace(".php", "") || "index";
            document.querySelectorAll("[data-tab]").forEach(function(el) {
                if (el.getAttribute("data-tab") === page) {
                    el.classList.add("on");
                } else {
                    el.classList.remove("on");
                }
            });
            toggleSidebar(false);
        }

        function applyHtml(html, url, pushState) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, "text/html");
            var newMain = doc.getElementById("app-main") || doc.querySelector("main");
            var newTitle = doc.querySelector("title");

            if (!newMain) {
                window.location.href = url;
                return;
            }

            if (newTitle) {
                document.title = newTitle.innerText;
            }

            if (main) {
                main.innerHTML = newMain.innerHTML;
                main.classList.remove("spa-transitioning");

                // innerHTML never executes <script> tags, so any page-local script
                // (e.g. Products' editProduct()/resetForm()) would silently stop
                // working after a client-side navigation. Re-create each script
                // node so the browser actually runs it, in document order.
                var oldScripts = main.querySelectorAll("script");
                oldScripts.forEach(function(oldScript) {
                    var newScript = document.createElement("script");
                    for (var i = 0; i < oldScript.attributes.length; i++) {
                        var attr = oldScript.attributes[i];
                        newScript.setAttribute(attr.name, attr.value);
                    }
                    newScript.textContent = oldScript.textContent;
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
            }

            updateActiveNav(url);

            if (pushState) {
                window.history.pushState({ url: url }, "", url);
            }

            if (url.indexOf("key=") !== -1 && typeof scrollToLicenseDetail === "function") {
                scrollToLicenseDetail();
            } else {
                window.scrollTo({ top: 0, behavior: "instant" });
            }
            finishProgress();
        }

        window.spaNavigate = function(url, pushState) {
            if (pushState === undefined) pushState = true;
            if (url.indexOf("logout.php") !== -1 || url.indexOf("buy.php") !== -1 || url.indexOf("crm.zoomnearby.com") !== -1) {
                window.location.href = url;
                return;
            }

            startProgress();
            if (main) main.classList.add("spa-transitioning");

            if (pageCache.has(url)) {
                applyHtml(pageCache.get(url), url, pushState);
            }

            fetch(url, {
                headers: { "X-Requested-With": "SPA", "Accept": "text/html" }
            })
            .then(function(res) {
                if (!res.ok && res.status !== 302) {
                    throw new Error("HTTP " + res.status);
                }
                return res.text();
            })
            .then(function(html) {
                pageCache.set(url, html);
                applyHtml(html, url, pushState);
            })
            .catch(function() {
                finishProgress();
                window.location.href = url;
            });
        };

        function prefetch(url) {
            if (!url || url.indexOf("logout.php") !== -1 || pageCache.has(url)) return;
            fetch(url, { headers: { "X-Requested-With": "SPA" } })
                .then(function(r) { return r.text(); })
                .then(function(html) { pageCache.set(url, html); })
                .catch(function() {});
        }

        document.addEventListener("click", function(e) {
            var a = e.target.closest("a");
            if (!a || !a.href) return;
            if (a.hasAttribute("data-no-spa") || a.target === "_blank") return;
            if (a.origin !== window.location.origin) return;

            e.preventDefault();
            spaNavigate(a.href, true);
        });

        document.addEventListener("pointerenter", function(e) {
            var a = e.target.closest("a");
            if (a && a.href && a.origin === window.location.origin && !a.hasAttribute("data-no-spa") && a.target !== "_blank") {
                prefetch(a.href);
            }
        }, { passive: true });

        document.addEventListener("submit", function(e) {
            var form = e.target.closest("form");
            if (!form || form.hasAttribute("data-no-spa")) return;
            var rawMethod = form.getAttribute("method");
            var method = (rawMethod ? rawMethod.trim().toUpperCase() : "GET");
            var rawAction = form.getAttribute("action");
            var action;
            if (rawAction && rawAction.trim() !== "") {
                action = new URL(rawAction, window.location.href).href;
            } else {
                action = window.location.href;
            }

            e.preventDefault();
            startProgress();
            if (main) main.classList.add("spa-transitioning");

            if (method === "GET") {
                var params = new URLSearchParams(new FormData(form)).toString();
                var fullUrl = action.split("?")[0] + (params ? "?" + params : "");
                spaNavigate(fullUrl, true);
            } else {
                fetch(action, {
                    method: "POST",
                    body: new FormData(form),
                    headers: { "X-Requested-With": "SPA" }
                })
                .then(function(res) {
                    if (!res.ok && res.status !== 302) {
                        throw new Error("HTTP " + res.status);
                    }
                    var targetUrl = res.url || action;
                    return res.text().then(function(html) {
                        return { html: html, url: targetUrl };
                    });
                })
                .then(function(data) {
                    pageCache.clear();
                    applyHtml(data.html, data.url, true);
                })
                .catch(function() {
                    finishProgress();
                    HTMLFormElement.prototype.submit.call(form);
                });
            }
        });

        window.addEventListener("popstate", function() {
            spaNavigate(window.location.href, false);
        });
    })();

    function copyKey(text, btn) {
        if (!navigator.clipboard) {
            var ta = document.createElement("textarea");
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand("copy");
            document.body.removeChild(ta);
        } else {
            navigator.clipboard.writeText(text);
        }
        var orig = btn.innerText;
        btn.innerText = "Copied!";
        btn.style.color = "#059669";
        setTimeout(function() {
            btn.innerText = orig;
            btn.style.color = "";
        }, 1800);
    }
    </script>
</body></html>
<?php
}