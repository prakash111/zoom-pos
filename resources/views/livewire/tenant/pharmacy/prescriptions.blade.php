<div class="space-y-5">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold shadow-sm">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('Prescriptions & Patient Queue') }}</h2>
            <p class="text-xs text-slate-400">{{ $pendingCount }} {{ __('pending intake') }}</p>
        </div>
        <button wire:click="newIntake" type="button"
                class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-md active:scale-95 transition">
            + {{ __('New Prescription Intake') }}
        </button>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search Rx #, patient, doctor…') }}"
               class="flex-1 min-w-[220px] rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-emerald-500 focus:border-emerald-500">
        @foreach (['pending' => __('Pending'), 'dispensed' => __('Dispensed'), 'cancelled' => __('Cancelled'), 'all' => __('All')] as $key => $label)
            <button wire:click="$set('status', '{{ $key }}')" type="button"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold transition {{ $status === $key ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($showForm)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __('New prescription') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Patient full name *') }}</label>
                    <input type="text" wire:model="patientName" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    @error('patientName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Patient phone') }}</label>
                    <input type="text" wire:model="patientPhone" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Prescribing doctor *') }}</label>
                    <input type="text" wire:model="doctorName" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    @error('doctorName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Doctor registration / license #') }}</label>
                    <input type="text" wire:model="doctorRegistrationNo" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Prescription date') }}</label>
                    <input type="date" wire:model="prescriptionDate" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Days supply / dosage duration') }}</label>
                    <input type="number" min="1" wire:model="dosageDurationDays" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Diagnosis / clinical indications') }}</label>
                    <textarea wire:model="diagnosis" rows="2" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm"></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Prescribed medicines, dosages & frequency *') }}</label>
                    <textarea wire:model="medicines" rows="3" placeholder="{{ __('e.g. Amoxicillin 500mg TDS x 7 days') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm"></textarea>
                    @error('medicines') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-2.5 pt-1">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition">{{ __('Cancel') }}</button>
                <button wire:click="save" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-md active:scale-95 transition">{{ __('Save to Queue') }}</button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800">
        @forelse ($prescriptions as $rx)
            <div class="p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <button wire:click="toggle({{ $rx->id }})" type="button" class="text-left">
                        <div class="font-black text-slate-800 dark:text-slate-100 text-sm">{{ $rx->prescription_number }} · {{ $rx->patient_name }}</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">
                            @if ($rx->doctor_name) Dr {{ $rx->doctor_name }} · @endif
                            {{ optional($rx->prescription_date)->format('Y-m-d') }}
                        </div>
                    </button>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold
                            {{ $rx->status === 'pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : '' }}
                            {{ $rx->status === 'dispensed' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : '' }}
                            {{ $rx->status === 'cancelled' ? 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' : '' }}">
                            {{ ucfirst($rx->status) }}
                        </span>
                        @if ($rx->status === 'pending')
                            <button wire:click="dispense({{ $rx->id }})" type="button" class="px-3 py-1.5 rounded-xl text-[11px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition">{{ __('Mark Dispensed') }}</button>
                            <button wire:click="cancel({{ $rx->id }})" wire:confirm="{{ __('Cancel this prescription?') }}" type="button" class="px-3 py-1.5 rounded-xl text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition">{{ __('Cancel') }}</button>
                        @endif
                    </div>
                </div>
                @if ($expandedId === $rx->id)
                    <div class="mt-3 rounded-2xl bg-slate-50 dark:bg-slate-800/50 p-4 text-xs text-slate-600 dark:text-slate-300 space-y-1.5">
                        @if ($rx->patient_phone)<div><span class="font-bold">{{ __('Phone') }}:</span> {{ $rx->patient_phone }}</div>@endif
                        @if ($rx->doctor_registration_no)<div><span class="font-bold">{{ __('Doctor reg #') }}:</span> {{ $rx->doctor_registration_no }}</div>@endif
                        @if ($rx->diagnosis)<div><span class="font-bold">{{ __('Diagnosis') }}:</span> {{ $rx->diagnosis }}</div>@endif
                        @if ($rx->dosage_duration_days)<div><span class="font-bold">{{ __('Days supply') }}:</span> {{ $rx->dosage_duration_days }}</div>@endif
                        <div class="whitespace-pre-line"><span class="font-bold">{{ __('Medicines') }}:</span> {{ $rx->notes }}</div>
                        @if ($rx->dispensed_at)<div class="text-emerald-600 dark:text-emerald-400">{{ __('Dispensed') }} {{ $rx->dispensed_at->format('Y-m-d H:i') }}</div>@endif
                    </div>
                @endif
            </div>
        @empty
            <div class="px-5 py-12 text-center text-slate-400 text-xs">{{ __('No prescriptions in this view.') }}</div>
        @endforelse
    </div>
</div>
