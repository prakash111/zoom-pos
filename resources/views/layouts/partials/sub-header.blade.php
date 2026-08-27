<nav class="hidden lg:flex w-full items-center justify-between overflow-x-auto no-scrollbar gap-2" aria-label="{{ __('Point of sale shortcuts') }}">
    <div class="flex items-center gap-1 sm:gap-1.5 text-xs font-bold">
        @if(Route::has('tenant.dashboard'))
            <a href="{{ route('tenant.dashboard') }}" wire:navigate class="px-3 py-1.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->routeIs('tenant.dashboard') ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-extrabold' : '' }}">
                {{ __('Dashboard') }}
            </a>
        @endif
        @if(Route::has('tenant.sales.create'))
            <a href="{{ route('tenant.sales.create') }}" wire:navigate class="px-3 py-1.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->routeIs('tenant.sales.create') ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-extrabold' : '' }}">
                {{ __('Retail POS') }}
            </a>
        @endif
        @if(Route::has('tenant.sales.index'))
            <a href="{{ route('tenant.sales.index') }}" wire:navigate class="px-3 py-1.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->routeIs('tenant.sales.index') ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-extrabold' : '' }}">
                {{ __('Sales & Invoices') }}
            </a>
        @endif
        @if(Route::has('tenant.quotes.index'))
            <a href="{{ route('tenant.quotes.index') }}" wire:navigate class="px-3 py-1.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->routeIs('tenant.quotes.*') ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-extrabold' : '' }}">
                {{ __('Quotations') }}
            </a>
        @endif
        @if(Route::has('tenant.service-orders.index'))
            <a href="{{ route('tenant.service-orders.index') }}" wire:navigate class="px-3 py-1.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->routeIs('tenant.service-orders.*') ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-extrabold' : '' }}">
                {{ __('Repairs & OS') }}
            </a>
        @endif
        @if(Route::has('tenant.products.index'))
            <a href="{{ route('tenant.products.index') }}" wire:navigate class="px-3 py-1.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->routeIs('tenant.products.*') ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-extrabold' : '' }}">
                {{ __('Inventory') }}
            </a>
        @endif
        @if(Route::has('tenant.financials.cash_register'))
            <a href="{{ route('tenant.financials.cash_register') }}" wire:navigate class="px-3 py-1.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->routeIs('tenant.financials.*') ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-extrabold' : '' }}">
                {{ __('Cash Register') }}
            </a>
        @endif
        @if(Route::has('tenant.settings.index'))
            <a href="{{ route('tenant.settings.index') }}" wire:navigate class="px-3 py-1.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 dark:hover:text-blue-400 transition {{ request()->routeIs('tenant.settings.*') ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-extrabold' : '' }}">
                {{ __('Settings') }}
            </a>
        @endif
    </div>

    <div class="hidden md:flex items-center gap-2 text-xs font-semibold text-slate-400">
        <span>{{ now()->format('D, d M Y') }}</span>
    </div>
</nav>
