{{-- ======================================================== --}}
{{-- 1. HRM & STAFF MANAGEMENT SECTION                         --}}
{{-- ======================================================== --}}
@canany(['hrm.module.access', 'hrm.employees.view', 'hrm.attendance.view', 'hrm.leaves.view', 'hrm.payroll.view'])
<div class="pt-4 mt-4 border-t border-slate-800">
    <p class="px-3 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
        {{ __('HRM & Staff Management') }}
    </p>
    <div class="mt-2 space-y-1">
        @can('hrm.employees.view')
        <a href="{{ route('tenant.hrm.employees.index') }}"
           class="flex items-center px-3 py-2 text-xs font-medium rounded-xl transition {{ request()->routeIs('tenant.hrm.employees.*') ? 'bg-emerald-500/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
            <svg class="w-4 h-4 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
            </svg>
            <span>{{ __('Staff Directory') }}</span>
        </a>
        @endcan

        @can('hrm.attendance.view')
        <a href="{{ route('tenant.hrm.attendance.index') }}"
           class="flex items-center px-3 py-2 text-xs font-medium rounded-xl transition {{ request()->routeIs('tenant.hrm.attendance.*') ? 'bg-emerald-500/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
            <svg class="w-4 h-4 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ __('Attendance Roster') }}</span>
        </a>
        @endcan

        @can('hrm.leaves.view')
        <a href="{{ route('tenant.hrm.leaves.index') }}"
           class="flex items-center px-3 py-2 text-xs font-medium rounded-xl transition {{ request()->routeIs('tenant.hrm.leaves.*') ? 'bg-emerald-500/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
            <svg class="w-4 h-4 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span>{{ __('Leave Requests') }}</span>
        </a>
        @endcan

        @can('hrm.payroll.view')
        <a href="{{ route('tenant.hrm.payroll.index') }}"
           class="flex items-center px-3 py-2 text-xs font-medium rounded-xl transition {{ request()->routeIs('tenant.hrm.payroll.*') ? 'bg-emerald-500/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
            <svg class="w-4 h-4 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span>{{ __('Payroll & Commissions') }}</span>
        </a>
        @endcan
    </div>
</div>
@endcanany

{{-- ======================================================== --}}
{{-- 2. LOYALTY & CUSTOMER WALLET SECTION                      --}}
{{-- ======================================================== --}}
@canany(['loyalty.module.access', 'loyalty.customer.balance_view', 'loyalty.tiers.manage', 'loyalty.settings.edit'])
<div class="pt-4 mt-4 border-t border-slate-800">
    <p class="px-3 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
        {{ __('Loyalty & Customer Wallet') }}
    </p>
    <div class="mt-2 space-y-1">
        @can('loyalty.customer.balance_view')
        <a href="{{ route('tenant.loyalty.wallets.index') }}"
           class="flex items-center px-3 py-2 text-xs font-medium rounded-xl transition {{ request()->routeIs('tenant.loyalty.wallets.*') ? 'bg-emerald-500/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
            <svg class="w-4 h-4 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
            <span>{{ __('Customer Balances & Top-up') }}</span>
        </a>
        @endcan

        @can('loyalty.tiers.manage')
        <a href="{{ route('tenant.loyalty.tiers.index') }}"
           class="flex items-center px-3 py-2 text-xs font-medium rounded-xl transition {{ request()->routeIs('tenant.loyalty.tiers.*') ? 'bg-emerald-500/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
            <svg class="w-4 h-4 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
            </svg>
            <span>{{ __('VIP Membership Tiers') }}</span>
        </a>
        @endcan

        @can('loyalty.settings.edit')
        <a href="{{ route('tenant.loyalty.settings.index') }}"
           class="flex items-center px-3 py-2 text-xs font-medium rounded-xl transition {{ request()->routeIs('tenant.loyalty.settings.*') ? 'bg-emerald-500/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
            <svg class="w-4 h-4 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span>{{ __('Points Earning Rules') }}</span>
        </a>
        @endcan
    </div>
</div>
@endcanany

{{-- ======================================================== --}}
{{-- 3. STAFF CHAT & SUPPORT                                  --}}
{{-- ======================================================== --}}
@if (Route::has('tenant.chat.index'))
<div class="pt-4 mt-4 border-t border-slate-800">
    <p class="px-3 text-[11px] font-bold tracking-wider text-slate-400 uppercase">
        {{ __('Staff Chat & Support') }}
    </p>
    <div class="mt-2 space-y-1">
        {{-- 1. Live Staff Chat --}}
        <a href="{{ route('tenant.chat.index') }}"
           class="flex items-center px-3 py-2 text-xs font-medium rounded-xl transition {{ request()->routeIs('tenant.chat.*') ? 'bg-emerald-500/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
            <svg class="w-4 h-4 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <span>{{ __('Live Staff Chat') }}</span>
        </a>

        {{-- 2. Send Staff Notification (Only for managers/admins) --}}
        @if (Route::has('tenant.notifications.index'))
        @can('hrm.employees.create')
        <a href="{{ route('tenant.notifications.index') }}"
           class="flex items-center px-3 py-2 text-xs font-medium rounded-xl transition {{ request()->routeIs('tenant.notifications.*') ? 'bg-emerald-500/10 text-emerald-400 font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
            <svg class="w-4 h-4 mr-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span>{{ __('Send Staff Notification') }}</span>
        </a>
        @endcan
        @endif
    </div>
</div>
@endif

