<div class="space-y-6">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('Repair Workbench') }}</h2>
            <p class="text-xs text-slate-400">{{ $openCount }} {{ __('open tickets') }} · {{ number_format($pendingReceivables, 2) }} {{ __('pending receivables') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('tenant.repair.tickets') }}" wire:navigate class="px-4 py-2 rounded-2xl text-xs font-bold bg-sky-600 hover:bg-sky-700 text-white transition">{{ __('All tickets') }}</a>
            <a href="{{ route('tenant.repair.categories') }}" wire:navigate class="px-4 py-2 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition">{{ __('Device categories') }}</a>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
        @foreach (\App\Models\RepairTicket::STATUSES as $key => $label)
            <a href="{{ route('tenant.repair.tickets', ['status' => $key]) }}" wire:navigate
               class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-md transition">
                <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</div>
                <div class="mt-1 text-xl font-black text-slate-900 dark:text-white">{{ $counts[$key] ?? 0 }}</div>
            </a>
        @endforeach
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-x-auto">
        <table class="w-full text-xs sm:text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3.5">{{ __('Ticket') }}</th>
                    <th class="px-5 py-3.5">{{ __('Device') }}</th>
                    <th class="px-5 py-3.5">{{ __('Status') }}</th>
                    <th class="px-5 py-3.5">{{ __('Technician') }}</th>
                    <th class="px-5 py-3.5 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @forelse ($recent as $t)
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3.5 font-bold text-slate-800 dark:text-slate-200">{{ $t->ticket_number }}<span class="block text-[11px] font-normal text-slate-400">{{ $t->customer_name }}</span></td>
                        <td class="px-5 py-3.5 text-slate-500">{{ trim(($t->brand ?? '').' '.($t->model ?? '')) ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-slate-500">{{ \App\Models\RepairTicket::STATUSES[$t->status] ?? $t->status }}</td>
                        <td class="px-5 py-3.5 text-slate-500">{{ $t->technician?->name ?? __('Unassigned') }}</td>
                        <td class="px-5 py-3.5 text-right"><a href="{{ route('tenant.repair.ticket', $t->id) }}" wire:navigate class="text-xs font-bold text-sky-600 hover:underline">{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-slate-400 text-xs">{{ __('No repair tickets yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
