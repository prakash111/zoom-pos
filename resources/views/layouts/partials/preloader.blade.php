<!-- 1. ZERO-LATENCY APP INITIAL PRELOADER -->
<div id="app-global-loader" 
     role="status" aria-live="polite" aria-label="{{ __('Loading application') }}"
     style="position: fixed; inset: 0; z-index: 999999; display: flex; flex-direction: column; align-items: center; justify-content: center; background-color: #0f172a; transition: opacity 0.18s ease, visibility 0.18s ease;">
    
    <!-- Center Branding & Spinner -->
    <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
        
        <!-- Glowing Brand Ring -->
        <div style="position: relative; width: 64px; height: 64px;">
            <div style="position: absolute; inset: 0; border-radius: 50%; border: 3px solid rgba(59, 130, 246, 0.15);"></div>
            <div style="position: absolute; inset: 0; border-radius: 50%; border: 3px solid transparent; border-top-color: #3b82f6; animation: spin 0.8s linear infinite;"></div>
            
            <!-- Center Icon / Logo -->
            <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                ⚡
            </div>
        </div>

        <!-- Status Text & Subtle Pulse -->
        <div style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem;">
            <span style="color: #ffffff; font-size: 0.85rem; font-weight: 700; letter-spacing: 0.05em; font-family: ui-sans-serif, system-ui, sans-serif;">
                {{ config('app.name', 'Smart SaaS') }}
            </span>
            <span style="color: #64748b; font-size: 0.7rem; font-weight: 500; letter-spacing: 0.025em; font-family: ui-sans-serif, system-ui, sans-serif;">
                Initializing workspace...
            </span>
        </div>
    </div>
</div>

<!-- Keyframe Animation -->
<style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .loader-hidden {
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
    }
</style>

<!-- 2. DISMISSAL CONTROLLER (Handles Initial Load & SPA Hooks) -->
<script>
    (function() {
        const loader = document.getElementById('app-global-loader');
        if (!loader) return;

        function dismissLoader() {
            loader.classList.add('loader-hidden');
            setTimeout(() => {
                if (loader && loader.parentNode) {
                    loader.parentNode.removeChild(loader);
                }
            }, 400);
        }

        // This partial renders on EVERY full document response — the very
        // first visit this tab has ever made, and any later full (non-SPA)
        // reload mid-session: a logout redirect, the service-worker
        // controllerchange reload, a plain <a> without wire:navigate, etc.
        // wire:navigate itself never re-requests this HTML at all, so it
        // never reaches this script — this is purely the real-navigation
        // path. sessionStorage is what tells those two cases apart, since
        // both produce byte-identical server-rendered markup.
        var SHELL_SEEN_KEY = 'zoom_pos_app_shell_seen';
        var isReturnVisit = false;
        try {
            isReturnVisit = sessionStorage.getItem(SHELL_SEEN_KEY) === '1';
            sessionStorage.setItem(SHELL_SEEN_KEY, '1');
        } catch (e) {
            // Private browsing / storage disabled: fall back to always
            // treating this as a first visit, i.e. today's behavior.
        }

        if (!isReturnVisit) {
            // First load this session: nothing else has painted yet, so
            // showing this immediately is what avoids a blank flash — the
            // interface is usable at DOM ready; do not wait for fonts/images.
            if (document.readyState !== 'loading') {
                dismissLoader();
            } else {
                document.addEventListener('DOMContentLoaded', dismissLoader, { once: true });
            }
            setTimeout(dismissLoader, 800); // Safety timeout: never trap the user if a third-party asset hangs.
            return;
        }

        // A full reload mid-session: the previous page was already showing
        // something, so don't flash this loader for a reload that resolves
        // quickly — only reveal it if the new document is visibly taking a
        // moment, then let it play out its own fade rather than cutting it
        // off the instant the DOM is ready.
        loader.classList.add('loader-hidden');
        var revealTimer = setTimeout(function () {
            loader.classList.remove('loader-hidden');
        }, 220);

        function finishReturnVisitLoad() {
            clearTimeout(revealTimer);
            dismissLoader();
        }

        if (document.readyState !== 'loading') {
            finishReturnVisitLoad();
        } else {
            document.addEventListener('DOMContentLoaded', finishReturnVisitLoad, { once: true });
        }
        setTimeout(finishReturnVisitLoad, 1200);
    })();
</script>
