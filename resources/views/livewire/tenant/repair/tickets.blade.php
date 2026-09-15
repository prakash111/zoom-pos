<div class="space-y-5">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold shadow-sm">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('Repair Ticket Register') }}</h2>
            <p class="text-xs text-slate-400">{{ __('Device intake and workbench queue') }}</p>
        </div>
        <button wire:click="newTicket" type="button" class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-sky-600 hover:bg-sky-700 text-white shadow-md active:scale-95 transition">+ {{ __('New Intake Ticket') }}</button>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search ticket, customer, device…') }}"
               class="flex-1 min-w-[220px] rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-sky-500 focus:border-sky-500">
        <button wire:click="$set('status', 'all')" type="button" class="px-3.5 py-2 rounded-xl text-xs font-bold transition {{ $status === 'all' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200' }}">{{ __('All') }}</button>
        @foreach (\App\Models\RepairTicket::STATUSES as $key => $label)
            <button wire:click="$set('status', '{{ $key }}')" type="button" class="px-3.5 py-2 rounded-xl text-xs font-bold transition {{ $status === $key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($showForm)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __('New intake ticket') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Customer name') }}</label>
                    <input type="text" wire:model="customerName" placeholder="{{ __('Walk-in if blank') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Customer phone') }}</label>
                    <input type="text" wire:model="customerPhone" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Device category') }}</label>
                    <select wire:model="categoryId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Brand') }}</label>
                    <input type="text" wire:model="brand" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Model') }}</label>
                    <input type="text" wire:model="model" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Serial / IMEI') }}</label>
                    <input type="text" wire:model="serial" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Priority') }}</label>
                    <select wire:model="priority" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                        <option value="low">{{ __('Low') }}</option>
                        <option value="normal">{{ __('Normal') }}</option>
                        <option value="high">{{ __('High') }}</option>
                        <option value="urgent">{{ __('Urgent') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Estimated cost') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="estimatedCost" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Diagnostic fee') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="diagnosticFee" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Reported problem *') }}</label>
                    <textarea wire:model="problemReported" rows="2" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm"></textarea>
                    @error('problemReported') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-2.5 pt-1">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition">{{ __('Cancel') }}</button>
                <button wire:click="create" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-sky-600 hover:bg-sky-700 text-white shadow-md active:scale-95 transition">{{ __('Create Ticket') }}</button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-x-auto">
        <table class="w-full text-xs sm:text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3.5">{{ __('Ticket') }}</th>
                    <th class="px-5 py-3.5">{{ __('Device') }}</th>
                    <th class="px-5 py-3.5">{{ __('Status') }}</th>
                    <th class="px-5 py-3.5">{{ __('Priority') }}</th>
                    <th class="px-5 py-3.5 text-right">{{ __('Total') }}</th>
                    <th class="px-5 py-3.5 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @forelse ($tickets as $t)
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3.5 font-bold text-slate-800 dark:text-slate-200">{{ $t->ticket_number }}<span class="block text-[11px] font-normal text-slate-400">{{ $t->customer_name }}</span></td>
                        <td class="px-5 py-3.5 text-slate-500">{{ trim(($t->brand ?? '').' '.($t->model ?? '')) ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-slate-500">{{ \App\Models\RepairTicket::STATUSES[$t->status] ?? $t->status }}</td>
                        <td class="px-5 py-3.5 text-slate-500">{{ ucfirst($t->priority) }}</td>
                        <td class="px-5 py-3.5 text-right">{{ number_format((float) $t->total_amount, 2) }}</td>
                        <td class="px-5 py-3.5 text-right"><a href="{{ route('tenant.repair.ticket', $t->id) }}" wire:navigate class="text-xs font-bold text-sky-600 hover:underline">{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400 text-xs">{{ __('No tickets in this view.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
