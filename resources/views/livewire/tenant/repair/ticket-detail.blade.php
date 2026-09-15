<div class="space-y-5">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold shadow-sm">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('tenant.repair.tickets') }}" wire:navigate class="text-xs font-bold text-sky-600 hover:underline">← {{ __('All tickets') }}</a>
            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white mt-1">{{ $ticket->ticket_number }}</h2>
            <p class="text-xs text-slate-400">
                {{ $ticket->customer_name }}@if ($ticket->customer_phone) · {{ $ticket->customer_phone }}@endif
                · {{ trim(($ticket->brand ?? '').' '.($ticket->model ?? '')) ?: __('Device') }}
                @if ($ticket->serial_number_or_imei) · {{ $ticket->serial_number_or_imei }}@endif
            </p>
        </div>
        <div class="text-right">
            <div class="text-[11px] uppercase font-bold text-slate-400">{{ __('Ticket total') }}</div>
            <div class="text-xl font-black text-slate-900 dark:text-white">{{ number_format((float) $ticket->total_amount, 2) }}</div>
            <div class="text-[11px] text-slate-400">{{ __('Balance due') }} {{ number_format((float) $ticket->balance_due, 2) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- Status + diagnosis --}}
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ __('Status & diagnosis') }}</h3>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Status') }}</label>
                <select wire:model="newStatus" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    @foreach (\App\Models\RepairTicket::STATUSES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('newStatus') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Technician diagnosis') }}</label>
                <textarea wire:model="technicianDiagnosis" rows="2" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm"></textarea>
            </div>
            <button wire:click="updateStatus" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-sky-600 hover:bg-sky-700 text-white transition">{{ __('Save status') }}</button>
        </div>

        {{-- Assign technician --}}
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ __('Assignment') }}</h3>
            <select wire:model="assignTechnicianId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                <option value="">{{ __('Unassigned') }}</option>
                @foreach ($technicians as $tech)
                    <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                @endforeach
            </select>
            @error('assignTechnicianId') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            <button wire:click="assign" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-900 dark:bg-white dark:text-slate-900 text-white transition">{{ __('Assign') }}</button>
            <p class="text-[11px] text-slate-400">{{ __('Reported problem') }}: {{ $ticket->problem_reported }}</p>
        </div>

        {{-- Parts --}}
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ __('Spare parts') }}</h3>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($parts as $p)
                    <div class="py-2 flex items-center justify-between text-xs">
                        <div><span class="font-bold text-slate-700 dark:text-slate-200">{{ $p->item_name }}</span> · {{ (float) $p->quantity }} × {{ number_format((float) $p->unit_price, 2) }}</div>
                        <div class="flex items-center gap-3">
                            <span class="font-bold">{{ number_format((float) $p->total, 2) }}</span>
                            <button wire:click="removePart({{ $p->id }})" type="button" class="text-rose-600 font-bold hover:underline">{{ __('Remove') }}</button>
                        </div>
                    </div>
                @empty
                    <p class="py-2 text-xs text-slate-400">{{ __('No parts added.') }}</p>
                @endforelse
            </div>
            <div class="grid grid-cols-2 gap-2 pt-2">
                <select wire:model="partProductId" class="col-span-2 rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                    <option value="">{{ __('Free-text part (or pick from stock)') }}</option>
                    @foreach ($stockProducts as $sp)
                        <option value="{{ $sp->id }}">{{ $sp->name }} ({{ (float) $sp->current_stock }} {{ __('in stock') }})</option>
                    @endforeach
                </select>
                <input type="text" wire:model="partName" placeholder="{{ __('Part name') }}" class="rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                <input type="number" step="0.01" min="0.01" wire:model="partQty" placeholder="{{ __('Qty') }}" class="rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                <input type="number" step="0.01" min="0" wire:model="partUnitPrice" placeholder="{{ __('Unit price') }}" class="rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                <button wire:click="addPart" type="button" class="rounded-lg text-xs font-bold bg-sky-600 hover:bg-sky-700 text-white px-3 py-2 transition">{{ __('Add part') }}</button>
            </div>
            @error('partQty') <p class="text-rose-600 text-xs">{{ $message }}</p> @enderror
            @error('partName') <p class="text-rose-600 text-xs">{{ $message }}</p> @enderror
        </div>

        {{-- Labor --}}
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ __('Labor charge') }}</h3>
            <div class="grid grid-cols-2 gap-2">
                <input type="number" step="0.01" min="0" wire:model="laborFee" placeholder="{{ __('Labor fee') }}" class="rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                <input type="text" wire:model="laborDescription" placeholder="{{ __('Description') }}" class="rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
            </div>
            @error('laborFee') <p class="text-rose-600 text-xs">{{ $message }}</p> @enderror
            <button wire:click="setLabor" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-sky-600 hover:bg-sky-700 text-white transition">{{ __('Set labor') }}</button>
            @if ($labor)<p class="text-[11px] text-slate-400">{{ __('Current') }}: {{ $labor->item_name }} — {{ number_format((float) $labor->total, 2) }}</p>@endif
        </div>

        {{-- Checklist --}}
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-3 lg:col-span-2">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ __('Inspection checklist') }}</h3>
            @php $checklist = collect($ticket->inspection_checklist ?? []); @endphp
            @forelse ($checklist as $row)
                @php
                    $name = is_array($row) ? ($row['item_name'] ?? $row['name'] ?? '') : (string) $row;
                    $key = is_array($row) ? ($row['key'] ?? \Illuminate\Support\Str::slug($name, '_')) : \Illuminate\Support\Str::slug($name, '_');
                    $status = is_array($row) ? ($row['status'] ?? 'pending') : 'pending';
                @endphp
                <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-100 dark:border-slate-800 last:border-0">
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $name }}</span>
                    <div class="flex gap-1.5">
                        @foreach (['pass' => 'Pass', 'fail' => 'Fail', 'not_applicable' => 'N/A', 'pending' => 'Pending'] as $val => $lbl)
                            <button wire:click="toggleChecklist('{{ $key }}', '{{ $val }}')" type="button"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition {{ $status === $val ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-200' }}">
                                {{ $lbl }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-xs text-slate-400">{{ __('No checklist on this ticket. Attach a device category with checklist points at intake.') }}</p>
            @endforelse
        </div>
    </div>
</div>
