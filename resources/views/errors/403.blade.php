<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Permission Denied | {{ config('app.name', 'Store') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-[#0c101d] text-slate-100 min-h-screen flex items-center justify-center p-4 sm:p-6 antialiased selection:bg-rose-500 selection:text-white relative overflow-hidden">

    <!-- Ambient Gradient Backdrops -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-rose-600/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-blue-600/15 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Central Security Card -->
    <div class="max-w-md w-full bg-[#161c2e]/90 backdrop-blur-xl rounded-[2.5rem] p-8 sm:p-10 border border-slate-800/80 shadow-[0_20px_50px_rgba(0,0,0,0.5)] text-center space-y-6 relative z-10">
        
        <!-- Glowing Shield Icon -->
        <div class="relative inline-flex items-center justify-center">
            <div class="absolute inset-0 bg-rose-500/25 rounded-3xl blur-xl"></div>
            <div class="relative w-20 h-20 rounded-3xl bg-gradient-to-tr from-rose-600 to-amber-600 text-white flex items-center justify-center shadow-lg shadow-rose-500/30">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
        </div>

        <!-- Headings & Status Pill -->
        <div class="space-y-2">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-rose-500/10 text-rose-400 border border-rose-500/20">
                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                Error 403 &bull; Access Restricted
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                Permission Denied
            </h1>
            
            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed font-medium">
                {{ !empty($exception?->getMessage()) && !str_starts_with($exception->getMessage(), 'This action is') ? $exception->getMessage() : 'You do not have the required permissions to access this page or perform this action.' }}
            </p>
        </div>

        <!-- Logged-in User Context Info (if authenticated) -->
        @if (auth()->check())
            <div class="bg-slate-900/80 rounded-2xl p-3.5 border border-slate-800/80 text-xs flex items-center justify-between text-left">
                <div class="space-y-0.5">
                    <div class="text-[10px] font-bold uppercase text-slate-500">Active Account</div>
                    <div class="font-extrabold text-slate-200 truncate max-w-[180px]">{{ auth()->user()->name }}</div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] font-bold uppercase text-slate-500">Assigned Role</div>
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-500/15 text-blue-400 border border-blue-500/30 capitalize">
                        {{ auth()->user()->role ?? 'Staff' }}
                    </span>
                </div>
            </div>
        @endif

        <!-- Help Advice Note -->
        <div class="text-[11px] text-slate-400 bg-slate-900/40 rounded-xl p-3 border border-slate-800/50">
            💡 If you need access to this section, please request permission from your store <strong class="text-slate-200 font-bold">Administrator</strong>.
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
            <button type="button"
                    onclick="history.back()"
                    class="w-full py-3 px-4 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs sm:text-sm transition-all active:scale-95">
                &larr; Go Back
            </button>

            @if (auth('platform_web')->check())
                <a href="{{ route('superadmin.dashboard') }}"
                   class="w-full py-3 px-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-blue-500/25 transition-all active:scale-95 text-center">
                    SuperAdmin Dashboard
                </a>
            @elseif (auth('web')->check())
                <a href="{{ route('tenant.dashboard') }}"
                   class="w-full py-3 px-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-blue-500/25 transition-all active:scale-95 text-center">
                    Dashboard
                </a>
            @else
                <a href="{{ url('/') }}"
                   class="w-full py-3 px-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-blue-500/25 transition-all active:scale-95 text-center">
                    Home Page
                </a>
            @endif
        </div>

    </div>

</body>
</html>
