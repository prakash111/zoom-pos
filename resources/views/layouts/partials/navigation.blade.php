<!-- Top Bar & Sub-Nav Links with SPA Directive -->
<header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 sm:px-6 py-3 flex items-center justify-between sticky top-0 z-30 shadow-xs">
    <div class="flex items-center gap-4 sm:gap-6">
        @if(Route::has('tenant.dashboard'))
            <a href="{{ route('tenant.dashboard') }}" wire:navigate class="flex items-center gap-2 font-black text-sm text-slate-900 dark:text-white">
                <span class="text-blue-600 font-black text-base">⚡</span>
                <span>{{ auth()->user()?->company?->name ?? config('app.name', 'Smart SaaS') }}</span>
            </a>
        @else
            <a href="{{ url('/') }}" wire:navigate class="flex items-center gap-2 font-black text-sm text-slate-900 dark:text-white">
                <span class="text-blue-600 font-black text-base">⚡</span>
                <span>{{ config('app.name', 'Smart SaaS') }}</span>
            </a>
        @endif

        <nav class="hidden md:flex items-center gap-1 text-xs font-bold text-slate-600 dark:text-slate-300">
            @if(Route::has('tenant.dashboard'))
                <a href="{{ route('tenant.dashboard') }}" wire:navigate class="px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 transition">{{ __('Dashboard') }}</a>
            @endif
            @if(Route::has('tenant.sales.create'))
                <a href="{{ route('tenant.sales.create') }}" wire:navigate class="px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 transition">{{ __('Point of Sale') }}</a>
            @endif
            @if(Route::has('tenant.invoices.index'))
                <a href="{{ route('tenant.invoices.index') }}" wire:navigate class="px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 transition">{{ __('Invoices') }}</a>
            @endif
            @if(Route::has('tenant.quotes.index'))
                <a href="{{ route('tenant.quotes.index') }}" wire:navigate class="px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 transition">{{ __('Quotes') }}</a>
            @endif
            @if(Route::has('tenant.consignments.index'))
                <a href="{{ route('tenant.consignments.index') }}" wire:navigate class="px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 transition">{{ __('Consignments') }}</a>
            @endif
            @if(Route::has('tenant.service-orders.index'))
                <a href="{{ route('tenant.service-orders.index') }}" wire:navigate class="px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 transition">{{ __('Repairs / OS') }}</a>
            @endif
            @if(Route::has('tenant.settings.index'))
                <a href="{{ route('tenant.settings.index') }}" wire:navigate class="px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 transition">{{ __('Settings') }}</a>
            @elseif(Route::has('tenant.settings'))
                <a href="{{ route('tenant.settings') }}" wire:navigate class="px-3 py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 transition">{{ __('Settings') }}</a>
            @endif
        </nav>
    </div>
</header>
