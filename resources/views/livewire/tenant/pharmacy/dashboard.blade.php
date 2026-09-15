<div class="space-y-6">
    <div>
        <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('Pharmacy') }}</h2>
        <p class="text-xs text-slate-400">{{ __('Batches, expiry tracking and the prescription queue') }}</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="{{ route('tenant.pharmacy.batches') }}" wire:navigate
           class="bg-white dark:bg-slate-900 rounded-3xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-md transition">
            <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ __('Drug Batches') }}</div>
            <div class="mt-1 text-2xl font-black text-slate-900 dark:text-white">{{ number_format($totalBatches) }}</div>
            <div class="mt-1 text-[11px] text-slate-400">{{ number_format($stockUnits) }} {{ __('units in stock') }}</div>
        </a>

        <a href="{{ route('tenant.pharmacy.batches', ['filter' => 'near_expiry']) }}" wire:navigate
           class="bg-white dark:bg-slate-900 rounded-3xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-md transition">
            <div class="text-[11px] font-bold uppercase tracking-wide text-amber-500">{{ __('Expiring ≤ 30 days') }}</div>
            <div class="mt-1 text-2xl font-black text-amber-600 dark:text-amber-400">{{ number_format($expiringSoon) }}</div>
            <div class="mt-1 text-[11px] text-slate-400">{{ __('batches need attention') }}</div>
        </a>

        <a href="{{ route('tenant.pharmacy.batches', ['filter' => 'expired']) }}" wire:navigate
           class="bg-white dark:bg-slate-900 rounded-3xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-md transition">
            <div class="text-[11px] font-bold uppercase tracking-wide text-rose-500">{{ __('Expired') }}</div>
            <div class="mt-1 text-2xl font-black text-rose-600 dark:text-rose-400">{{ number_format($expired) }}</div>
            <div class="mt-1 text-[11px] text-slate-400">{{ __('remove from shelf') }}</div>
        </a>

        <a href="{{ route('tenant.pharmacy.prescriptions') }}" wire:navigate
           class="bg-white dark:bg-slate-900 rounded-3xl p-5 border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-md transition">
            <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-500">{{ __('Pending Prescriptions') }}</div>
            <div class="mt-1 text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($pendingRx) }}</div>
            <div class="mt-1 text-[11px] text-slate-400">{{ number_format($dispensedToday) }} {{ __('dispensed today') }}</div>
        </a>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-100 dark:border-slate-800 shadow-sm space-y-3">
        <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ __('Quick actions') }}</h3>
        <div class="flex flex-wrap gap-2.5">
            <a href="{{ route('tenant.pharmacy.prescriptions') }}" wire:navigate class="px-4 py-2 rounded-2xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition">+ {{ __('New Prescription Intake') }}</a>
            <a href="{{ route('tenant.pharmacy.batches') }}" wire:navigate class="px-4 py-2 rounded-2xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white transition">+ {{ __('Register Drug Batch') }}</a>
            <a href="{{ route('tenant.sales.create') }}" wire:navigate class="px-4 py-2 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition">{{ __('Open POS & Checkout') }}</a>
        </div>
    </div>
</div>
