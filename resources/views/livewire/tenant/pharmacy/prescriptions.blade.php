<div class="space-y-6">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border dark:border-emerald-800 text-xs sm:text-sm font-semibold shadow-sm">
            {{ session('status') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-sky-500 dark:text-[#38BDF8]">{{ __('Pharmacy & Healthcare') }}</div>
            <h2 class="text-xl font-extrabold text-slate-900 dark:text-[#F8FAFC]">{{ __('Prescriptions & Patient Dispense Queue') }}</h2>
            <p class="text-xs text-slate-500 dark:text-[#94A3B8]">{{ $pendingCount }} {{ __('prescriptions awaiting fulfillment') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="newIntake" type="button"
                    class="px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-md active:scale-95 transition flex items-center gap-1.5">
                <span>+</span> {{ __('New Prescription Intake') }}
            </button>
        </div>
    </div>

    {{-- Top: Dual Search Toggle (Brand / Generic Molecule) + Expiry Threshold Indicator --}}
    <div class="bg-white dark:bg-[#131D2D] border border-slate-200 dark:border-[#1E293B] rounded-2xl p-4 shadow-sm space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            {{-- Dual Search Mode Toggle --}}
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-[#94A3B8]">{{ __('Search Mode') }}:</span>
                <div class="inline-flex rounded-xl bg-slate-100 dark:bg-[#0B1120] p-1 border border-slate-200 dark:border-[#1E293B]">
                    <button wire:click="$set('searchMode', 'brand')" type="button"
                            class="px-3 py-1 text-xs font-extrabold rounded-lg transition {{ $searchMode === 'brand' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 dark:text-[#94A3B8] hover:text-slate-900 dark:hover:text-white' }}">
                        {{ __('Brand Name') }}
                    </button>
                    <button wire:click="$set('searchMode', 'generic')" type="button"
                            class="px-3 py-1 text-xs font-extrabold rounded-lg transition {{ $searchMode === 'generic' ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-600 dark:text-[#94A3B8] hover:text-slate-900 dark:hover:text-white' }}">
                        {{ __('Generic Molecule') }}
                    </button>
                </div>
            </div>

            {{-- Expiry Threshold Indicator --}}
            <div class="flex items-center gap-2">
                <div class="px-3 py-1.5 rounded-xl border border-amber-300 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 text-xs font-bold flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <span>{{ $nearExpiryCount }} {{ __('batches expiring within') }} {{ $expiryThresholdDays }} {{ __('days') }}</span>
                </div>
            </div>
        </div>

        {{-- Universal Filter / Search Bar --}}
        <div class="flex flex-wrap items-center gap-2 pt-1">
            <div class="relative flex-1 min-w-[240px]">
                <input type="search" wire:model.live.debounce.400ms="search"
                       placeholder="{{ $searchMode === 'brand' ? __('Search by Brand, Rx #, Patient, Doctor…') : __('Search by Generic Molecule, Active Formula, Rx #…') }}"
                       class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 dark:border-[#1E293B] bg-slate-50 dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm focus:ring-sky-500">
                <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
            </div>
            @foreach (['pending' => __('Pending'), 'dispensed' => __('Dispensed'), 'cancelled' => __('Cancelled'), 'all' => __('All')] as $key => $label)
                <button wire:click="$set('status', '{{ $key }}')" type="button"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition {{ $status === $key ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-[#1E293B] text-slate-600 dark:text-[#94A3B8] hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Prescription Intake Form Card --}}
    @if ($showForm)
        <div class="bg-white dark:bg-[#131D2D] rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-[#1E293B] space-y-4">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-sky-500 dark:text-[#38BDF8]">{{ __('Prescription Intake') }}</span>
                    <h3 class="text-base font-black text-slate-900 dark:text-[#F8FAFC]">{{ __('New Medical Rx Intake') }}</h3>
                </div>
                <button wire:click="$set('showForm', false)" type="button" class="text-slate-400 hover:text-slate-200 text-lg">&times;</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Patient Full Name *') }}</label>
                    <input type="text" wire:model="patientName" placeholder="e.g. Ramesh Verma" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                    @error('patientName') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Patient Phone') }}</label>
                    <input type="text" wire:model="patientPhone" placeholder="+91 98765 00000" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Prescribing Doctor *') }}</label>
                    <input type="text" wire:model="doctorName" placeholder="Dr. S. K. Gupta" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                    @error('doctorName') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Doctor Reg / License #') }}</label>
                    <input type="text" wire:model="doctorRegistrationNo" placeholder="MCI-19482" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Prescription Date') }}</label>
                    <input type="date" wire:model="prescriptionDate" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Dosage Duration (Days)') }}</label>
                    <input type="number" min="1" wire:model="dosageDurationDays" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Diagnosis / Notes') }}</label>
                    <textarea wire:model="diagnosis" rows="2" placeholder="Clinical observations…" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm"></textarea>
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Prescribed Drugs & Regimen *') }}</label>
                    <textarea wire:model="medicines" rows="3" placeholder="e.g. Paracetamol 650mg TDS x 5 days&#10;Amoxicillin 500mg BD x 7 days (Schedule H)" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm"></textarea>
                    @error('medicines') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-2.5 pt-2">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-[#1E293B] text-slate-700 dark:text-[#94A3B8] hover:bg-slate-200 transition">{{ __('Cancel') }}</button>
                <button wire:click="save" type="button" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-md active:scale-95 transition">{{ __('Save to Queue') }}</button>
            </div>
        </div>
    @endif

    {{-- Prescription Queue List / Cards --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-sky-500 dark:text-[#38BDF8]">{{ __('Prescription Queue') }} ({{ $prescriptions->count() }})</span>
        </div>

        <div class="bg-white dark:bg-[#131D2D] rounded-2xl shadow-sm border border-slate-200 dark:border-[#1E293B] divide-y divide-slate-100 dark:divide-[#1E293B]">
            @forelse ($prescriptions as $rx)
                <div class="p-4 sm:p-5 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-extrabold text-slate-900 dark:text-[#F8FAFC] text-sm">
                                    {{ $rx->patient_name }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-mono font-bold bg-slate-100 dark:bg-[#1E293B] text-slate-700 dark:text-[#38BDF8]">
                                    {{ $rx->prescription_number }}
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase
                                    {{ $rx->status === 'dispensed' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : '' }}
                                    {{ $rx->status === 'pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400' : '' }}
                                    {{ $rx->status === 'cancelled' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400' : '' }}">
                                    {{ ucfirst($rx->status) }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 dark:text-[#94A3B8] mt-1 flex flex-wrap items-center gap-2">
                                @if ($rx->doctor_name)
                                    <span>Dr. <strong>{{ $rx->doctor_name }}</strong></span>
                                @endif
                                <span>• {{ optional($rx->prescription_date)->format('d M Y') }}</span>
                                @if ($rx->patient_phone)
                                    <span>• 📞 {{ $rx->patient_phone }}</span>
                                @endif
                            </div>

                            {{-- Schedule-H Drug Warning Badge --}}
                            @php
                                $medText = is_array($rx->medicines) ? implode(' ', $rx->medicines) : (string) $rx->medicines;
                                $isScheduleH = str_contains(strtolower($medText), 'schedule h') || str_contains(strtolower($medText), 'amoxicillin') || str_contains(strtolower($medText), 'antibiotic');
                            @endphp
                            @if ($isScheduleH)
                                <div class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-600 dark:text-rose-300 text-[10px] font-black uppercase tracking-wide">
                                    <span>⚠️</span> {{ __('Schedule H Prescription Drug — Valid Doctor Rx Mandatory') }}
                                </div>
                            @endif

                            {{-- Quick Batch Selector preview --}}
                            @if ($batches->isNotEmpty() && $rx->status === 'pending')
                                <div class="mt-3 flex items-center gap-2">
                                    <span class="text-[11px] font-bold text-slate-400 dark:text-[#94A3B8]">{{ __('Batch Selector') }}:</span>
                                    <select class="rounded-lg border border-slate-200 dark:border-[#1E293B] bg-slate-50 dark:bg-[#0B1120] text-slate-700 dark:text-[#F8FAFC] text-[11px] py-1 px-2">
                                        @foreach ($batches as $b)
                                            <option value="{{ $b->id }}">
                                                {{ $b->batch_number }} (Qty: {{ (int) $b->stock_qty }} • Exp: {{ optional($b->expiry_date)->format('M y') }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>

                        {{-- Dispatch & Actions --}}
                        <div class="flex items-center gap-2">
                            {{-- Immediate Omnichannel Dispatch via UnifiedDocumentDispatchSheet --}}
                            <button type="button"
                                    x-data
                                    x-on:click="$dispatch('open-sdui-sheet', { endpoint: '/tenant/documents/prescription/{{ $rx->id }}/preview-modal' })"
                                    class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-slate-100 dark:bg-[#1E293B] text-slate-700 dark:text-[#F8FAFC] hover:bg-slate-200 dark:hover:bg-slate-700 transition flex items-center gap-1.5 shadow-sm">
                                <span>🧾</span> {{ __('Dispatch') }}
                            </button>

                            @if ($rx->status === 'pending')
                                <button wire:click="dispense({{ $rx->id }})" type="button"
                                        class="px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition">
                                    {{ __('Mark Dispensed') }}
                                </button>
                                <button wire:click="cancel({{ $rx->id }})" wire:confirm="{{ __('Cancel this prescription?') }}" type="button"
                                        class="px-3 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-rose-500 transition">
                                    {{ __('Cancel') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-5 py-12 text-center text-slate-400 dark:text-[#94A3B8] text-xs">
                    {{ __('No prescriptions match your filter.') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
