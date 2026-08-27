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

        // The interface is usable at DOM ready; do not wait for fonts/images.
        if (document.readyState !== 'loading') {
            dismissLoader();
        } else {
            document.addEventListener('DOMContentLoaded', dismissLoader, { once: true });
        }

        // Safety timeout: Never trap user if a third-party asset hangs
        setTimeout(dismissLoader, 800);
    })();
</script>
