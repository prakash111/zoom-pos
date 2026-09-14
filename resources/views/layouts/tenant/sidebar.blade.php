{{-- Tenant Cashier & Sales Drawer Item Partial for Lead Management --}}
@if ($canLeads ?? true)
    <x-nav.drawer-item 
        item-key="lead_management" 
        :route="route('tenant.leads.index')" 
        title="{{ __('Lead Management') }}" 
        subtitle="{{ __('Pipeline, follow-ups & auto-sync CRM') }}">
        🎯
    </x-nav.drawer-item>
@endif

@if (auth()->check())
<div class="mt-auto border-t border-slate-200 dark:border-slate-800 p-3 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center font-bold text-xs">
            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
        </div>
        <div class="text-xs">
            <p class="font-medium text-slate-800 dark:text-slate-200">{{ auth()->user()->name }}</p>
            <p class="text-slate-400 truncate max-w-[120px]">{{ auth()->user()->email }}</p>
        </div>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="p-2 text-rose-500 hover:bg-rose-500/10 rounded-lg text-xs font-semibold flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            {{ __('Sign Out') }}
        </button>
    </form>
</div>
@endif
