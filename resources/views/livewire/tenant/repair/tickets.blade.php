<div class="space-y-6">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border dark:border-emerald-800 text-xs sm:text-sm font-semibold shadow-sm">{{ session('status') }}</div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-sky-500 dark:text-[#38BDF8]">{{ __('Service & Repairs') }}</div>
            <h2 class="text-xl font-extrabold text-slate-900 dark:text-[#F8FAFC]">{{ __('Repair Ticket Matrix & Workbench') }}</h2>
            <p class="text-xs text-slate-500 dark:text-[#94A3B8]">{{ __('Job sheet intake, technician assignment & thermal token dispatch') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="newTicket" type="button" class="px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-sky-600 hover:bg-sky-500 text-white shadow-md active:scale-95 transition flex items-center gap-1.5">
                <span>+</span> {{ __('New Intake Ticket') }}
            </button>
        </div>
    </div>

    {{-- Filter & View Switcher Bar --}}
    <div class="bg-white dark:bg-[#131D2D] border border-slate-200 dark:border-[#1E293B] rounded-2xl p-4 shadow-sm space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            {{-- Universal Search Input --}}
            <div class="relative flex-1 min-w-[240px]">
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search ticket #, customer, device, brand, defect…') }}"
                       class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 dark:border-[#1E293B] bg-slate-50 dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm focus:ring-sky-500">
                <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
            </div>

            {{-- View Mode Toggle (Kanban vs Table) --}}
            <div class="inline-flex rounded-xl bg-slate-100 dark:bg-[#0B1120] p-1 border border-slate-200 dark:border-[#1E293B]">
                <button wire:click="$set('viewMode', 'kanban')" type="button"
                        class="px-3 py-1.5 text-xs font-extrabold rounded-lg transition {{ $viewMode === 'kanban' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 dark:text-[#94A3B8] hover:text-slate-900 dark:hover:text-white' }}">
                    <span>📊</span> {{ __('Kanban Matrix') }}
                </button>
                <button wire:click="$set('viewMode', 'list')" type="button"
                        class="px-3 py-1.5 text-xs font-extrabold rounded-lg transition {{ $viewMode === 'list' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 dark:text-[#94A3B8] hover:text-slate-900 dark:hover:text-white' }}">
                    <span>📋</span> {{ __('Register Table') }}
                </button>
            </div>
        </div>

        {{-- Status Filter Badges (in List Mode) --}}
        @if ($viewMode === 'list')
            <div class="flex flex-wrap items-center gap-2 pt-1">
                <button wire:click="$set('status', 'all')" type="button"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $status === 'all' ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-[#1E293B] text-slate-600 dark:text-[#94A3B8] hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                    {{ __('All') }}
                </button>
                @foreach (\App\Models\RepairTicket::STATUSES as $key => $label)
                    <button wire:click="$set('status', '{{ $key }}')" type="button"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $status === $key ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-[#1E293B] text-slate-600 dark:text-[#94A3B8] hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- New Ticket Intake Form Modal/Card --}}
    @if ($showForm)
        <div class="bg-white dark:bg-[#131D2D] rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-[#1E293B] space-y-4">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-sky-500 dark:text-[#38BDF8]">{{ __('Device Intake') }}</span>
                    <h3 class="text-base font-black text-slate-900 dark:text-[#F8FAFC]">{{ __('New Repair Job Sheet') }}</h3>
                </div>
                <button wire:click="$set('showForm', false)" type="button" class="text-slate-400 hover:text-slate-200 text-lg">&times;</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Customer Name') }}</label>
                    <input type="text" wire:model="customerName" placeholder="{{ __('Walk-in if blank') }}" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Customer Phone') }}</label>
                    <input type="text" wire:model="customerPhone" placeholder="+91 98765 00000" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Device Category') }}</label>
                    <select wire:model="categoryId" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                        <option value="">{{ __('Select category…') }}</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Brand') }}</label>
                    <input type="text" wire:model="brand" placeholder="e.g. Apple, Samsung, Dell" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Model') }}</label>
                    <input type="text" wire:model="model" placeholder="e.g. iPhone 15 Pro / Galaxy S24" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Serial / IMEI') }}</label>
                    <input type="text" wire:model="serial" placeholder="Serial / IMEI number" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Priority') }}</label>
                    <select wire:model="priority" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                        <option value="low">{{ __('Low') }}</option>
                        <option value="normal">{{ __('Normal') }}</option>
                        <option value="high">{{ __('High') }}</option>
                        <option value="urgent">{{ __('Urgent') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Technician Estimate (₹)') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="estimatedCost" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Diagnostic Fee (₹)') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="diagnosticFee" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Reported Problem & Defect *') }}</label>
                    <textarea wire:model="problemReported" rows="2" placeholder="e.g. Broken display glass, battery drains quickly, pattern lock: L-shape" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm"></textarea>
                    @error('problemReported') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-2.5 pt-2">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-[#1E293B] text-slate-700 dark:text-[#94A3B8] hover:bg-slate-200 transition">{{ __('Cancel') }}</button>
                <button wire:click="create" type="button" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-sky-600 hover:bg-sky-500 text-white shadow-md active:scale-95 transition">{{ __('Create Ticket') }}</button>
            </div>
        </div>
    @endif

    {{-- VIEW 1: KANBAN / STATUS MATRIX (Intake -> Diagnostics -> Parts Sourced -> Completed) --}}
    @if ($viewMode === 'kanban')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
            @foreach ($kanbanColumns as $colKey => $column)
                <div class="bg-white dark:bg-[#131D2D] rounded-2xl border border-slate-200 dark:border-[#1E293B] shadow-sm overflow-hidden flex flex-col">
                    {{-- Column Header --}}
                    <div class="p-3.5 border-b border-slate-100 dark:border-[#1E293B] flex items-center justify-between bg-slate-50/50 dark:bg-[#0B1120]/40">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ $column['accent'] === 'sky' ? 'bg-sky-500' : ($column['accent'] === 'amber' ? 'bg-amber-500' : ($column['accent'] === 'indigo' ? 'bg-indigo-500' : 'bg-emerald-500')) }}"></span>
                            <h3 class="font-extrabold text-xs text-slate-800 dark:text-[#F8FAFC] uppercase tracking-wide">{{ $column['title'] }}</h3>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-100 dark:bg-[#1E293B] text-slate-600 dark:text-[#94A3B8]">
                            {{ $column['tickets']->count() }}
                        </span>
                    </div>

                    {{-- Column Cards --}}
                    <div class="p-3 space-y-3 min-h-[360px] max-h-[70vh] overflow-y-auto">
                        @forelse ($column['tickets'] as $ticket)
                            <div class="bg-slate-50 dark:bg-[#0B1120] border border-slate-200 dark:border-[#1E293B] rounded-xl p-3.5 shadow-sm space-y-2.5 hover:border-sky-500 transition">
                                {{-- Ticket # and Priority --}}
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-xs font-black text-sky-600 dark:text-[#38BDF8]">
                                        {{ $ticket->ticket_number }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase
                                        {{ $ticket->priority === 'urgent' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400' : '' }}
                                        {{ $ticket->priority === 'high' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400' : '' }}
                                        {{ in_array($ticket->priority, ['normal', 'low']) ? 'bg-slate-200 dark:bg-[#1E293B] text-slate-600 dark:text-[#94A3B8]' : '' }}">
                                        {{ $ticket->priority }}
                                    </span>
                                </div>

                                {{-- Device Brand & Model --}}
                                <div>
                                    <div class="font-bold text-xs text-slate-900 dark:text-[#F8FAFC]">
                                        {{ trim(($ticket->brand ?? '').' '.($ticket->model ?? '')) ?: 'Device' }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 dark:text-[#94A3B8]">
                                        {{ $ticket->customer_name }}
                                        @if ($ticket->customer_phone) · {{ $ticket->customer_phone }} @endif
                                    </div>
                                </div>

                                {{-- Reported Defect & Pattern Lock --}}
                                <div class="text-[11px] text-slate-600 dark:text-[#94A3B8] bg-white dark:bg-[#131D2D] p-2 rounded-lg border border-slate-100 dark:border-[#1E293B]">
                                    <div class="line-clamp-2"><strong>Defect:</strong> {{ $ticket->problem_reported }}</div>
                                    @if ($ticket->security_lock_pattern || $ticket->security_pin)
                                        <div class="mt-1 text-[10px] text-amber-600 dark:text-amber-400 font-bold">
                                            🔒 Pattern/PIN Recorded
                                        </div>
                                    @endif
                                </div>

                                {{-- Technician Estimate & Footer Actions --}}
                                <div class="pt-1 flex items-center justify-between border-t border-slate-200/60 dark:border-[#1E293B]">
                                    <div>
                                        <span class="text-[10px] text-slate-400 block">{{ __('Estimate') }}</span>
                                        <span class="text-xs font-black text-emerald-600 dark:text-[#10B981]">
                                            ₹{{ number_format((float) $ticket->total_amount, 2) }}
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-1.5">
                                        {{-- Direct Trigger for Unified Document Dispatch Sheet --}}
                                        <button type="button"
                                                x-data
                                                x-on:click="$dispatch('open-sdui-sheet', { endpoint: '/tenant/documents/repair/{{ $ticket->id }}/preview-modal' })"
                                                title="{{ __('Intake Slip & Dispatch') }}"
                                                class="px-2.5 py-1.5 rounded-lg text-[11px] font-extrabold bg-slate-200 dark:bg-[#1E293B] text-slate-700 dark:text-[#F8FAFC] hover:bg-slate-300 dark:hover:bg-slate-700 transition">
                                            🧾
                                        </button>
                                        <a href="{{ route('tenant.repair.ticket', $ticket->id) }}" wire:navigate
                                           class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold bg-sky-600 text-white hover:bg-sky-500 transition">
                                            {{ __('Open') }}
                                        </a>
                                    </div>
                                </div>

                                {{-- Move to Next Stage Dropdown --}}
                                <div class="pt-1">
                                    <select wire:change="updateTicketStatus({{ $ticket->id }}, $event.target.value)"
                                            class="w-full rounded-lg border-slate-200 dark:border-[#1E293B] bg-white dark:bg-[#131D2D] text-slate-700 dark:text-[#94A3B8] text-[10px] py-1 px-1.5 font-bold">
                                        <option value="" disabled selected>{{ __('Move status…') }}</option>
                                        @foreach (\App\Models\RepairTicket::STATUSES as $stKey => $stLabel)
                                            <option value="{{ $stKey }}" @selected($ticket->status === $stKey)>{{ $stLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-slate-400 dark:text-[#94A3B8] text-xs italic">
                                {{ __('No tickets in this stage.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- VIEW 2: REGISTER TABLE --}}
        <div class="bg-white dark:bg-[#131D2D] rounded-2xl shadow-sm border border-slate-200 dark:border-[#1E293B] overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-[#0B1120] text-left text-slate-500 dark:text-[#94A3B8] font-bold border-b border-slate-200 dark:border-[#1E293B]">
                    <tr>
                        <th class="px-5 py-3.5">{{ __('Ticket') }}</th>
                        <th class="px-5 py-3.5">{{ __('Device') }}</th>
                        <th class="px-5 py-3.5">{{ __('Defect') }}</th>
                        <th class="px-5 py-3.5">{{ __('Status') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('Estimate') }}</th>
                        <th class="px-5 py-3.5 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-[#1E293B] font-medium">
                    @forelse ($tickets as $t)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                            <td class="px-5 py-3.5 font-bold text-slate-800 dark:text-[#F8FAFC]">
                                <span class="font-mono text-sky-600 dark:text-[#38BDF8]">{{ $t->ticket_number }}</span>
                                <span class="block text-[11px] font-normal text-slate-400 dark:text-[#94A3B8]">{{ $t->customer_name }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-700 dark:text-[#F8FAFC]">
                                {{ trim(($t->brand ?? '').' '.($t->model ?? '')) ?: '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-slate-500 dark:text-[#94A3B8] max-w-xs truncate">
                                {{ $t->problem_reported }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase
                                    {{ in_array($t->status, ['ready', 'delivered']) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 dark:bg-[#1E293B] text-slate-600 dark:text-[#94A3B8]' }}">
                                    {{ \App\Models\RepairTicket::STATUSES[$t->status] ?? $t->status }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right font-black text-slate-800 dark:text-[#10B981]">
                                ₹{{ number_format((float) $t->total_amount, 2) }}
                            </td>
                            <td class="px-5 py-3.5 text-right space-x-1.5">
                                {{-- Direct Trigger for Unified Document Dispatch Sheet --}}
                                <button type="button"
                                        x-data
                                        x-on:click="$dispatch('open-sdui-sheet', { endpoint: '/tenant/documents/repair/{{ $t->id }}/preview-modal' })"
                                        title="{{ __('Intake Slip & Dispatch') }}"
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-slate-100 dark:bg-[#1E293B] text-slate-700 dark:text-[#F8FAFC] hover:bg-slate-200 transition">
                                    🧾 {{ __('Dispatch') }}
                                </button>
                                <a href="{{ route('tenant.repair.ticket', $t->id) }}" wire:navigate class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-sky-600 text-white hover:bg-sky-500 transition">{{ __('Open') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400 dark:text-[#94A3B8] text-xs">{{ __('No tickets in this view.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
