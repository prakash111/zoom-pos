<div class="px-4 sm:px-6 py-2.5 flex items-center justify-between">
    <div class="flex items-center gap-3">
        @if(Route::has('tenant.dashboard'))
            <a href="{{ route('tenant.dashboard') }}" wire:navigate class="flex items-center gap-2.5 font-black text-sm text-white hover:opacity-90 transition">
                <span class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center text-base shadow-sm">⚡</span>
                <span class="tracking-tight">{{ auth()->user()?->company?->name ?? config('app.name', 'Zoom POS & Market') }}</span>
            </a>
        @else
            <a href="{{ url('/') }}" wire:navigate class="flex items-center gap-2.5 font-black text-sm text-white hover:opacity-90 transition">
                <span class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center text-base shadow-sm">⚡</span>
                <span class="tracking-tight">{{ config('app.name', 'Zoom POS & Market') }}</span>
            </a>
        @endif
    </div>

    <div class="flex items-center gap-3">
        @if(Route::has('tenant.sales.create'))
            <a href="{{ route('tenant.sales.create') }}" wire:navigate class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-sm transition">
                <span>🛒</span>
                <span>{{ __('POS Terminal') }}</span>
            </a>
        @endif

        @auth
            <div class="flex items-center gap-2 text-xs text-slate-300">
                <span class="font-bold text-slate-200">{{ auth()->user()->name }}</span>
                <button type="button"
                        class="relative p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition"
                        data-sdui-action="OPEN_BOTTOM_SHEET"
                        data-sdui-endpoint="/api/v1/tenant/notifications/feed"
                        x-on:click="$dispatch('open-sdui-sheet', { endpoint: '/api/v1/tenant/notifications/feed', title: 'System Alerts & Reminders' })"
                        aria-label="{{ __('Notifications') }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0h6z" />
                    </svg>
                </button>
            </div>
        @endauth
    </div>
</div>
