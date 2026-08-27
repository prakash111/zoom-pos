// Global dark-mode store, decoupled from the tenant/superadmin/guest/public
// layouts' own local `x-data.dark` toggles (each still owns the actual
// localStorage/class toggling) — this just mirrors <html>.classList so any
// third-party script that expects a conventional `Alpine.store('darkMode').on`
// works without needing every layout to know about it. Used by the TinyMCE
// theme-handler script loaded on the SuperAdmin custom-pages editor.
document.addEventListener('alpine:init', () => {
    Alpine.store('darkMode', { on: document.documentElement.classList.contains('dark') });
});

// PWA lifecycle stays client-side so install/update prompts never trigger a
// Livewire request. Transaction and tenant-data requests remain network-only;
// the service worker is deliberately limited to public compiled assets.
let pendingPwaInstall = null;
let waitingServiceWorker = null;
let reloadingForServiceWorker = false;

document.addEventListener('alpine:init', () => {
    Alpine.store('pwa', {
        canInstall: false,
        updateAvailable: false,

        async install() {
            if (!pendingPwaInstall) return;

            pendingPwaInstall.prompt();
            await pendingPwaInstall.userChoice.catch(() => null);
            pendingPwaInstall = null;
            this.canInstall = false;
        },

        applyUpdate() {
            waitingServiceWorker?.postMessage({ type: 'SKIP_WAITING' });
        },

        dismiss() {
            this.canInstall = false;
            this.updateAvailable = false;
        },
    });
});

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    pendingPwaInstall = event;
    if (window.Alpine?.store('pwa')) Alpine.store('pwa').canInstall = true;
});

window.addEventListener('appinstalled', () => {
    pendingPwaInstall = null;
    if (window.Alpine?.store('pwa')) Alpine.store('pwa').canInstall = false;
});

if ('serviceWorker' in navigator && (window.isSecureContext || location.hostname === 'localhost')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).then((registration) => {
            if (registration.waiting) {
                waitingServiceWorker = registration.waiting;
                if (window.Alpine?.store('pwa')) Alpine.store('pwa').updateAvailable = true;
            }

            registration.addEventListener('updatefound', () => {
                const worker = registration.installing;
                if (!worker) return;

                worker.addEventListener('statechange', () => {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                        waitingServiceWorker = worker;
                        if (window.Alpine?.store('pwa')) Alpine.store('pwa').updateAvailable = true;
                    }
                });
            });
        }).catch(() => {
            // PWA support is an enhancement and must never interrupt POS boot.
        });
    }, { once: true });

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (reloadingForServiceWorker) return;
        reloadingForServiceWorker = true;
        window.location.reload();
    });
}
new MutationObserver(() => {
    if (window.Alpine?.store('darkMode')) {
        Alpine.store('darkMode').on = document.documentElement.classList.contains('dark');
    }
}).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

// Persistent fullscreen: a single global Alpine store (instead of the old
// per-page `x-data` blocks) so toggling fullscreen survives Livewire
// `wire:navigate` page swaps, and a localStorage intent flag drives a
// "Resume Fullscreen" nudge on the rare hard navigation that does exit it
// (browsers require a real user gesture to re-enter fullscreen, so this is
// the honest equivalent of "auto restore").
document.addEventListener('alpine:init', () => {
    Alpine.store('fullscreen', {
        active: !!document.fullscreenElement,
        showResumePill: false,

        init() {
            this.active = !!document.fullscreenElement;
            this.showResumePill = !this.active && localStorage.getItem('pos_fullscreen_intent') === '1';

            document.addEventListener('fullscreenchange', () => {
                this.active = !!document.fullscreenElement;
                if (this.active) {
                    this.showResumePill = false;
                }
            });
        },

        toggle() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen()
                    .then(() => {
                        this.active = true;
                        this.showResumePill = false;
                        localStorage.setItem('pos_fullscreen_intent', '1');
                    })
                    .catch(() => {});
            } else if (document.exitFullscreen) {
                document.exitFullscreen()
                    .then(() => {
                        this.active = false;
                        localStorage.setItem('pos_fullscreen_intent', '0');
                    })
                    .catch(() => {});
            }
        },

        resume() {
            document.documentElement.requestFullscreen()
                .then(() => {
                    this.active = true;
                    this.showResumePill = false;
                })
                .catch(() => {});
        },

        dismiss() {
            this.showResumePill = false;
            localStorage.setItem('pos_fullscreen_intent', '0');
        },
    });
});

// Add-to-cart beep: a short synthesized tone (no audio asset to load/host).
// AudioContext is created lazily on first use, which is always in response
// to a click/scan action, so no separate "unlock" gesture is needed; any
// failure (unsupported browser, autoplay restrictions) is swallowed so it
// never interrupts the checkout flow.
let posAudioContext = null;

window.playAddToCartBeep = function playAddToCartBeep() {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return;

        posAudioContext = posAudioContext || new Ctx();
        if (posAudioContext.state === 'suspended') {
            posAudioContext.resume().catch(() => {});
        }

        const oscillator = posAudioContext.createOscillator();
        const gain = posAudioContext.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        gain.gain.setValueAtTime(0.15, posAudioContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.0001, posAudioContext.currentTime + 0.12);

        oscillator.connect(gain);
        gain.connect(posAudioContext.destination);
        oscillator.start();
        oscillator.stop(posAudioContext.currentTime + 0.12);
    } catch (e) {
        // Fail silently — a missing beep should never block a sale.
    }
};

import NProgress from 'nprogress';
import { dockableNav } from './dockable-nav';
import { scrollTabToCenter, syncActiveTabs, initMobileNavController } from './mobile-nav-controller';
import './pos-scanner';
import './hardware-barcode-listener';

let checkoutIslandsPromise = null;

function loadCheckoutIslandsWhenPresent() {
    if (!document.querySelector('[data-vue-payment-selector], [data-vue-cash-calculator]')) return;

    checkoutIslandsPromise ??= import('./vue/checkout-islands');
    checkoutIslandsPromise.then(({ registerCheckoutIslands }) => registerCheckoutIslands()).catch(() => {
        // The server-rendered controls remain usable if the optional island fails.
    });
}

document.addEventListener('DOMContentLoaded', loadCheckoutIslandsWhenPresent);
document.addEventListener('livewire:navigated', loadCheckoutIslandsWhenPresent);

if (typeof window !== 'undefined') {
    window.NProgress = NProgress;
}

window.dockableNav = dockableNav;
window.scrollTabToCenter = scrollTabToCenter;
window.syncActiveTabs = syncActiveTabs;

initMobileNavController();

// ==========================================================================
// Unified Page Init Dispatcher & Lifecycle Bridge for Third-Party Scripts
// ==========================================================================
function initializePageFeatures() {
    // 1. Dispatch custom event for Livewire components to hook into
    window.dispatchEvent(new CustomEvent('page-hydrated'));
    window.dispatchEvent(new CustomEvent('spa:page-loaded'));

    // 2. Trigger auto-centering on active sub-navigation tabs
    const activeTab = document.querySelector('.settings-subnav-container .active, .settings-subnav-container [aria-selected="true"]');
    if (activeTab) {
        activeTab.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }

    // 3. Sync scroll and active tabs on mobile/dock navigation
    if (typeof syncActiveTabs === 'function') {
        syncActiveTabs();
    }
}

window.initializePageFeatures = initializePageFeatures;

// Cold / First Page Load
document.addEventListener('DOMContentLoaded', () => {
    initializePageFeatures();
});

// ==========================================================================
// Instant Single-Page Navigation (SPA) Lifecycle Engine
// ==========================================================================
if (typeof window !== 'undefined') {
    // Configure NProgress if available
    if (window.NProgress) {
        window.NProgress.configure({
            showSpinner: false,
            trickleSpeed: 100,
            minimum: 0.15,
            speed: 300,
        });
    }

    // Connect NProgress to Livewire SPA Navigate Events
    document.addEventListener('livewire:navigating', () => {
        if (window.NProgress) {
            window.NProgress.start();
        }
    });

    // Livewire 3 SPA Internal Navigation Hooks
    document.addEventListener('livewire:navigated', () => {
        if (window.NProgress) {
            window.NProgress.done();
        }

        initializePageFeatures();

        // Trigger dynamic layout & chart resize recalculation
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 50);

        // Re-evaluate dark mode state on document
        const isDark = localStorage.getItem('theme') === 'dark';
        document.documentElement.classList.toggle('dark', isDark);
    });

    // Turbo remains a compatibility fallback for the super-admin surface.
    document.addEventListener('turbo:before-visit', () => window.NProgress?.start());
    document.addEventListener('turbo:load', () => {
        window.NProgress?.done();
        initializePageFeatures();
    });

    // Global SPA Link Interceptor: routes any same-origin link click through SPA navigation
    document.addEventListener('click', (event) => {
        // Ignore modified clicks or secondary button clicks
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const link = event.target.closest('a');
        if (!link) return;

        const rawHref = link.getAttribute('href');
        if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('javascript:') || rawHref.startsWith('mailto:') || rawHref.startsWith('tel:')) {
            return;
        }

        if (link.target && link.target !== '_self') return;
        if (link.hasAttribute('download')) return;
        if (link.dataset.noSpa === 'true' || link.dataset.turbo === 'false' || link.dataset.navigateIgnore === 'true') return;

        let targetUrl;
        try {
            targetUrl = new URL(link.href, window.location.href);
        } catch (e) {
            return;
        }

        if (targetUrl.origin !== window.location.origin) return;

        // Hash jump on the same page
        if (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search && targetUrl.hash) {
            return;
        }

        // Route through Livewire 3 SPA router or Turbo Drive
        if (window.Livewire && typeof window.Livewire.navigate === 'function') {
            if (!link.hasAttribute('wire:navigate') && !link.hasAttribute('wire:navigate.hover')) {
                event.preventDefault();
                window.Livewire.navigate(targetUrl.href);
            }
        } else if (window.Turbo && typeof window.Turbo.visit === 'function') {
            event.preventDefault();
            window.Turbo.visit(targetUrl.href);
        }
    });
}
