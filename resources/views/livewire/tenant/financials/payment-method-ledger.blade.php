<div class="space-y-4 text-xs font-sans">

    <!-- Top Action & Breadcrumb Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-3 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-lg">💳</span>
                <h1 class="text-base font-black text-slate-900 dark:text-white tracking-tight">{{ $paymentMethod->name }} {{ __('Ledger') }}</h1>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">{{ __('Every transaction recorded against this payment method.') }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('tenant.settings.index') }}" class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-extrabold text-xs active:scale-95 transition cursor-pointer">
                &larr; {{ __('Back to Settings') }}
            </a>
            <button type="button" wire:click="exportCsv" class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md shadow-blue-500/20 active:scale-95 transition cursor-pointer">
                {{ __('Export CSV') }}
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="flex flex-wrap items-end gap-3 bg-white dark:bg-slate-900 p-3 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div>
            <label class="block text-[10px] font-bold text-slate-500 mb-1">{{ __('From') }}</label>
            <input type="date" wire:model.live="dateFrom" class="rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
        </div>
        <div>
            <label class="block text-[10px] font-bold text-slate-500 mb-1">{{ __('To') }}</label>
            <input type="date" wire:model.live="dateTo" class="rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-[10px] uppercase tracking-wider text-slate-500 font-extrabold">
                <tr>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ __('Order ID') }}</th>
                    <th class="px-4 py-3">{{ __('Customer') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                    <th class="px-4 py-3">{{ __('Reference No') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($transactions as $payment)
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                        <td class="px-4 py-3.5 text-slate-500">{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3.5 font-bold text-slate-800 dark:text-slate-100">{{ $payment->sale?->sale_number ?? $payment->sale_id }}</td>
                        <td class="px-4 py-3.5 text-slate-500">{{ $payment->sale?->customer?->name ?? $payment->sale?->customer_name ?? '—' }}</td>
                        <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-900 dark:text-white">${{ number_format((float) $payment->amount, 2) }}</td>
                        <td class="px-4 py-3.5 text-slate-400 font-mono text-[11px]">{{ $payment->reference_number ?: '—' }}</td>
                        <td class="px-4 py-3.5 text-slate-400">{{ $payment->sale?->payment_status ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">{{ __('No transactions recorded for this payment method yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $transactions->links() }}</div>
</div>
