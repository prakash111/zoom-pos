<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Page Not Found | {{ config('app.name', 'Store') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-[#0c101d] text-slate-100 min-h-screen flex items-center justify-center p-4 sm:p-6 antialiased selection:bg-blue-500 selection:text-white relative overflow-hidden">

    <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-blue-600/15 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-md w-full bg-[#161c2e]/90 backdrop-blur-xl rounded-[2.5rem] p-8 sm:p-10 border border-slate-800/80 shadow-[0_20px_50px_rgba(0,0,0,0.5)] text-center space-y-6 relative z-10">
        
        <div class="relative inline-flex items-center justify-center">
            <div class="absolute inset-0 bg-blue-500/25 rounded-3xl blur-xl"></div>
            <div class="relative w-20 h-20 rounded-3xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center shadow-lg shadow-blue-500/30">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>

        <div class="space-y-2">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-blue-500/10 text-blue-400 border border-blue-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                Error 404 &bull; Page Not Found
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                Page Not Found
            </h1>
            
            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed font-medium">
                {{ !empty($exception?->getMessage()) ? $exception->getMessage() : 'The page or resource you are looking for might have been moved or removed.' }}
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
            <button type="button"
                    onclick="history.back()"
                    class="w-full py-3 px-4 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs sm:text-sm transition-all active:scale-95">
                &larr; Go Back
            </button>

            <a href="{{ auth()->check() ? route('tenant.dashboard') : url('/') }}"
               class="w-full py-3 px-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-blue-500/25 transition-all active:scale-95 text-center">
                Return Home
            </a>
        </div>

    </div>

</body>
</html>
