<div class="space-y-5">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold shadow-sm">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('Service Booking Calendar') }}</h2>
            <p class="text-xs text-slate-400">{{ __('Appointments for the selected day') }}</p>
        </div>
        <button wire:click="newBooking" type="button" class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-violet-600 hover:bg-violet-700 text-white shadow-md active:scale-95 transition">+ {{ __('Book Appointment') }}</button>
    </div>

    <div class="flex items-center gap-2">
        <button wire:click="shiftDay(-1)" type="button" class="px-3 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition">←</button>
        <input type="date" wire:model.live="date" class="rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
        <button wire:click="shiftDay(1)" type="button" class="px-3 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition">→</button>
    </div>

    @if ($showForm)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __('Book service / appointment') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Service *') }}</label>
                    <select wire:model="serviceId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                        <option value="">{{ __('Select…') }}</option>
                        @foreach ($services as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} ({{ (int) ($s->duration_minutes ?: 30) }}m)</option>
                        @endforeach
                    </select>
                    @error('serviceId') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Specialist *') }}</label>
                    <select wire:model="specialistId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                        <option value="">{{ __('Select…') }}</option>
                        @foreach ($specialists as $sp)
                            <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                        @endforeach
                    </select>
                    @error('specialistId') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    @if ($specialists->isEmpty())
                        <p class="text-amber-600 text-[11px] mt-1">{{ __('Mark staff as specialists first under Stylists & Staff.') }}</p>
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Customer name *') }}</label>
                    <input type="text" wire:model="customerName" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    @error('customerName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Customer phone') }}</label>
                    <input type="text" wire:model="customerPhone" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Date *') }}</label>
                    <input type="date" wire:model="appointmentDate" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    @error('appointmentDate') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Time *') }}</label>
                    <input type="time" wire:model="appointmentTime" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    @error('appointmentTime') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Advance / deposit') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="advancePaid" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes') }}</label>
                    <textarea wire:model="notes" rows="2" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2.5 pt-1">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition">{{ __('Cancel') }}</button>
                <button wire:click="book" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-violet-600 hover:bg-violet-700 text-white shadow-md active:scale-95 transition">{{ __('Book') }}</button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800">
        @forelse ($appointments as $a)
            <div class="p-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="font-black text-slate-800 dark:text-slate-100 text-sm">
                        {{ $a->starts_at->timezone($timezone)->format('g:i A') }} · {{ $a->customer_name }}
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">
                        {{ $a->service?->name ?? __('Service') }}
                        @if ($a->specialist) · {{ $a->specialist->name }} @endif
                        @if ($a->appointment_number) · {{ $a->appointment_number }} @endif
                    </div>
                    @if ($a->notes)<div class="text-[11px] text-slate-400 mt-1">{{ $a->notes }}</div>@endif
                </div>
                <div class="flex items-center gap-2">
                    <select wire:change="setStatus({{ $a->id }}, $event.target.value)" class="rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-[11px] font-bold">
                        @foreach (\App\Livewire\Tenant\Salon\Calendar::STATUSES as $st)
                            <option value="{{ $st }}" @selected($a->status === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @empty
            <div class="px-5 py-12 text-center text-slate-400 text-xs">{{ __('No appointments for this day.') }}</div>
        @endforelse
    </div>
</div>
