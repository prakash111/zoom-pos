<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Smart SaaS') }}</title>

    <!-- Fonts & Styles (Tracked for SPA Cache) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <!-- Inline Loading Bar Styling -->
    <style>
        #nprogress .bar {
            background: #2563eb !important;
            height: 3px !important;
            z-index: 99999 !important;
        }
        #nprogress .peg {
            box-shadow: 0 0 10px #2563eb, 0 0 5px #2563eb !important;
        }
    </style>
</head>
<body class="bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-100 antialiased min-h-screen">

    <!-- 1. ZERO-LATENCY APP INITIAL PRELOADER -->
    <div id="app-global-loader" 
         style="position: fixed; inset: 0; z-index: 999999; display: flex; flex-direction: column; align-items: center; justify-content: center; background-color: #0f172a; transition: opacity 0.35s ease, visibility 0.35s ease;">
        
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

            // Dismiss as soon as all critical assets are ready
            if (document.readyState === 'complete') {
                dismissLoader();
            } else {
                window.addEventListener('load', dismissLoader);
            }

            // Safety timeout: Never trap user if a third-party asset hangs
            setTimeout(dismissLoader, 1500);
        })();
    </script>

    <!-- Main Application Canvas -->
    <div id="app" class="relative">
        {{ $slot ?? '' }}
        @yield('content')
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
