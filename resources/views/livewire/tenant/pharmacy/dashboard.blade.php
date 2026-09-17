<div class="space-y-6">
    <div>
        <div class="text-[11px] font-extrabold uppercase tracking-wider text-sky-500 dark:text-[#38BDF8]">{{ __('Healthcare & Dispensary') }}</div>
        <h2 class="text-xl font-extrabold text-slate-900 dark:text-[#F8FAFC]">{{ __('Pharmacy') }}</h2>
        <p class="text-xs text-slate-500 dark:text-[#94A3B8]">{{ __('Batches, expiry tracking and the prescription queue') }}</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="{{ route('tenant.pharmacy.batches') }}" wire:navigate
           class="bg-white dark:bg-[#131D2D] rounded-2xl p-5 border border-slate-200 dark:border-[#1E293B] shadow-sm hover:shadow-md transition">
            <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400 dark:text-[#94A3B8]">{{ __('Drug Batches') }}</div>
            <div class="mt-1 text-2xl font-black text-slate-900 dark:text-[#F8FAFC]">{{ number_format($totalBatches) }}</div>
            <div class="mt-1 text-[11px] text-slate-500 dark:text-[#94A3B8]">{{ number_format($stockUnits) }} {{ __('units in stock') }}</div>
        </a>

        <a href="{{ route('tenant.pharmacy.batches', ['filter' => 'near_expiry']) }}" wire:navigate
           class="bg-white dark:bg-[#131D2D] rounded-2xl p-5 border border-slate-200 dark:border-[#1E293B] shadow-sm hover:shadow-md transition">
            <div class="text-[11px] font-bold uppercase tracking-wide text-amber-500 dark:text-[#F59E0B]">{{ __('Expiring ≤ 30 days') }}</div>
            <div class="mt-1 text-2xl font-black text-amber-600 dark:text-amber-400">{{ number_format($expiringSoon) }}</div>
            <div class="mt-1 text-[11px] text-slate-500 dark:text-[#94A3B8]">{{ __('batches need attention') }}</div>
        </a>

        <a href="{{ route('tenant.pharmacy.batches', ['filter' => 'expired']) }}" wire:navigate
           class="bg-white dark:bg-[#131D2D] rounded-2xl p-5 border border-slate-200 dark:border-[#1E293B] shadow-sm hover:shadow-md transition">
            <div class="text-[11px] font-bold uppercase tracking-wide text-rose-500 dark:text-[#EF4444]">{{ __('Expired') }}</div>
            <div class="mt-1 text-2xl font-black text-rose-600 dark:text-rose-400">{{ number_format($expired) }}</div>
            <div class="mt-1 text-[11px] text-slate-500 dark:text-[#94A3B8]">{{ __('remove from shelf') }}</div>
        </a>

        <a href="{{ route('tenant.pharmacy.prescriptions') }}" wire:navigate
           class="bg-white dark:bg-[#131D2D] rounded-2xl p-5 border border-slate-200 dark:border-[#1E293B] shadow-sm hover:shadow-md transition">
            <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-500 dark:text-[#10B981]">{{ __('Pending Prescriptions') }}</div>
            <div class="mt-1 text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($pendingRx) }}</div>
            <div class="mt-1 text-[11px] text-slate-500 dark:text-[#94A3B8]">{{ number_format($dispensedToday) }} {{ __('dispensed today') }}</div>
        </a>
    </div>

    <div class="bg-white dark:bg-[#131D2D] rounded-2xl p-6 border border-slate-200 dark:border-[#1E293B] shadow-sm space-y-3">
        <h3 class="text-sm font-black text-slate-900 dark:text-[#F8FAFC]">{{ __('Quick actions') }}</h3>
        <div class="flex flex-wrap gap-2.5">
            <a href="{{ route('tenant.pharmacy.prescriptions') }}" wire:navigate class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition">+ {{ __('New Prescription Intake') }}</a>
            <a href="{{ route('tenant.pharmacy.batches') }}" wire:navigate class="px-4 py-2 rounded-xl text-xs font-bold bg-sky-600 hover:bg-sky-700 text-white transition">+ {{ __('Register Drug Batch') }}</a>
            <a href="{{ route('tenant.sales.create') }}" wire:navigate class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-[#1E293B] text-slate-700 dark:text-[#F8FAFC] hover:bg-slate-200 dark:hover:bg-slate-700 transition">{{ __('Open POS & Checkout') }}</a>
        </div>
    </div>
</div>
