<div class="space-y-6">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border dark:border-emerald-800 text-xs sm:text-sm font-semibold shadow-sm">{{ session('status') }}</div>
    @endif

    {{-- Top Header --}}
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-sky-500 dark:text-[#38BDF8]">{{ __('Salon & Spa Management') }}</div>
            <h2 class="text-xl font-extrabold text-slate-900 dark:text-[#F8FAFC]">{{ __('Service Booking & Appointment Desk') }}</h2>
            <p class="text-xs text-slate-500 dark:text-[#94A3B8]">{{ __('Stylist schedules, service catalog & omnichannel guest dispatch') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="newBooking" type="button" class="px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-md active:scale-95 transition flex items-center gap-1.5">
                <span>+</span> {{ __('Book Appointment') }}
            </button>
        </div>
    </div>

    {{-- Top: Date Picker + Stylist Assignment Switcher Bar --}}
    <div class="bg-white dark:bg-[#131D2D] border border-slate-200 dark:border-[#1E293B] rounded-2xl p-3 sm:p-4 shadow-sm flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-[#94A3B8] mr-1">{{ __('Date') }}:</span>
            <button wire:click="shiftDay(-1)" type="button" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-[#1E293B] text-slate-600 dark:text-[#F8FAFC] hover:bg-slate-200 dark:hover:bg-slate-700 transition">←</button>
            <input type="date" wire:model.live="date" class="rounded-xl border border-slate-200 dark:border-[#1E293B] bg-slate-50 dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm px-3 py-1.5 focus:ring-sky-500">
            <button wire:click="shiftDay(1)" type="button" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-[#1E293B] text-slate-600 dark:text-[#F8FAFC] hover:bg-slate-200 dark:hover:bg-slate-700 transition">→</button>
        </div>

        {{-- Stylist Assignment Switcher --}}
        <div class="flex items-center gap-1.5 overflow-x-auto py-1 max-w-full">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-[#94A3B8] mr-1 shrink-0">{{ __('Stylist') }}:</span>
            <button wire:click="$set('stylistFilter', null)" type="button"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold shrink-0 transition {{ is_null($stylistFilter) ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-[#1E293B] text-slate-600 dark:text-[#94A3B8] hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                {{ __('All Stylists') }}
            </button>
            @foreach ($specialists as $sp)
                <button wire:click="$set('stylistFilter', '{{ $sp->id }}')" type="button"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold shrink-0 transition {{ $stylistFilter == $sp->id ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-[#1E293B] text-slate-600 dark:text-[#94A3B8] hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                    {{ $sp->name }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Appointment Booking Form Modal / Expandable Card --}}
    @if ($showForm)
        <div class="bg-white dark:bg-[#131D2D] rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-[#1E293B] space-y-4">
            <div class="flex justify-between items-center">
                <div>
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-sky-500 dark:text-[#38BDF8]">{{ __('New Intake') }}</span>
                    <h3 class="text-base font-black text-slate-900 dark:text-[#F8FAFC]">{{ __('Book Service / Treatment') }}</h3>
                </div>
                <button wire:click="$set('showForm', false)" type="button" class="text-slate-400 hover:text-slate-200 text-lg">&times;</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Service *') }}</label>
                    <select wire:model="serviceId" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                        <option value="">{{ __('Select service…') }}</option>
                        @foreach ($services as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} ({{ (int) ($s->duration_minutes ?: 30) }}m) - ₹{{ number_format((float) $s->sale_price, 2) }}</option>
                        @endforeach
                    </select>
                    @error('serviceId') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Stylist / Specialist *') }}</label>
                    <select wire:model="specialistId" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                        <option value="">{{ __('Select specialist…') }}</option>
                        @foreach ($specialists as $sp)
                            <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                        @endforeach
                    </select>
                    @error('specialistId') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Customer Name *') }}</label>
                    <input type="text" wire:model="customerName" placeholder="e.g. Priya Sharma" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                    @error('customerName') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Customer Phone') }}</label>
                    <input type="text" wire:model="customerPhone" placeholder="+91 98765 43210" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Date & Slot Time *') }}</label>
                    <div class="flex gap-2">
                        <input type="date" wire:model="appointmentDate" class="w-1/2 rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs">
                        <input type="time" wire:model="appointmentTime" class="w-1/2 rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs">
                    </div>
                    @error('appointmentDate') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                    @error('appointmentTime') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-[#F8FAFC] mb-1">{{ __('Advance Deposit (₹)') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="advancePaid" class="w-full rounded-xl border-slate-200 dark:border-[#1E293B] dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs sm:text-sm">
                </div>
            </div>
            <div class="flex justify-end gap-2.5 pt-2">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-[#1E293B] text-slate-700 dark:text-[#94A3B8] hover:bg-slate-200 transition">{{ __('Cancel') }}</button>
                <button wire:click="book" type="button" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-md active:scale-95 transition">{{ __('Confirm Booking') }}</button>
            </div>
        </div>
    @endif

    {{-- Body: Categorized Service Catalog Tabs & Interactive AppCard Tiles --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-sky-500 dark:text-[#38BDF8]">{{ __('Select Services & Treatments') }}</span>
            <span class="text-xs text-slate-400">{{ count($cartServices) }} {{ __('selected') }}</span>
        </div>

        {{-- Categorized Tabs (Hair, Spa, Facials, Add-ons) --}}
        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach ($categories as $cat)
                <button wire:click="selectCategory('{{ $cat }}')" type="button"
                        class="px-4 py-2 rounded-xl text-xs font-extrabold transition {{ $activeCategory === $cat ? 'bg-sky-600 text-white shadow-sm' : 'bg-white dark:bg-[#131D2D] border border-slate-200 dark:border-[#1E293B] text-slate-600 dark:text-[#94A3B8] hover:bg-slate-50 dark:hover:bg-[#1E293B]' }}">
                    {{ $cat }}
                </button>
            @endforeach
        </div>

        {{-- AppCard Style Service Tiles --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ($services as $srv)
                <div wire:click="toggleCartService({{ $srv->id }})"
                     class="cursor-pointer rounded-2xl border transition p-4 flex flex-col justify-between {{ in_array($srv->id, $cartServices, true) ? 'bg-sky-50 dark:bg-sky-950/40 border-sky-500 dark:border-sky-500 shadow-sm' : 'bg-white dark:bg-[#131D2D] border-slate-200 dark:border-[#1E293B] hover:border-slate-300 dark:hover:border-slate-600' }}">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <h4 class="font-bold text-sm text-slate-900 dark:text-[#F8FAFC] line-clamp-1">{{ $srv->name }}</h4>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold {{ in_array($srv->id, $cartServices, true) ? 'bg-sky-500 text-white' : 'bg-slate-100 dark:bg-[#1E293B] text-slate-500 dark:text-[#94A3B8]' }}">
                                {{ (int) ($srv->duration_minutes ?: 30) }}m
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 dark:text-[#94A3B8] mt-1">{{ __('Stylist slot available') }}</p>
                    </div>
                    <div class="mt-3 pt-2 border-t border-slate-100 dark:border-[#1E293B] flex items-center justify-between">
                        <span class="text-sm font-extrabold text-emerald-600 dark:text-[#10B981]">₹{{ number_format((float) $srv->sale_price, 2) }}</span>
                        <span class="text-xs font-bold {{ in_array($srv->id, $cartServices, true) ? 'text-sky-500' : 'text-slate-400' }}">
                            {{ in_array($srv->id, $cartServices, true) ? '✓ Added' : '+ Add' }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Today's Scheduled Appointments Register --}}
    <div class="space-y-3 pt-2">
        <div class="flex items-center justify-between">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-sky-500 dark:text-[#38BDF8]">{{ __('Day Appointments') }} ({{ $appointments->count() }})</span>
        </div>

        <div class="bg-white dark:bg-[#131D2D] rounded-2xl shadow-sm border border-slate-200 dark:border-[#1E293B] divide-y divide-slate-100 dark:divide-[#1E293B]">
            @forelse ($appointments as $a)
                <div class="p-4 sm:p-5 flex flex-wrap items-center justify-between gap-3 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-extrabold text-slate-900 dark:text-[#F8FAFC] text-sm">
                                {{ $a->starts_at->timezone($timezone)->format('g:i A') }} · {{ $a->customer_name }}
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase
                                {{ $a->status === 'completed' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : '' }}
                                {{ $a->status === 'in_progress' ? 'bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-400' : '' }}
                                {{ $a->status === 'scheduled' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400' : '' }}
                                {{ in_array($a->status, ['cancelled', 'no_show']) ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400' : '' }}">
                                {{ str_replace('_', ' ', $a->status) }}
                            </span>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-[#94A3B8] mt-1 flex items-center gap-2">
                            <span>{{ $a->service?->name ?? __('Service') }}</span>
                            @if ($a->specialist) <span>• Stylist: <strong>{{ $a->specialist->name }}</strong></span> @endif
                            @if ($a->appointment_number) <span>• <code>{{ $a->appointment_number }}</code></span> @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- Trigger Unified Document Dispatch Bottom Sheet --}}
                        <button type="button"
                                x-data
                                x-on:click="$dispatch('open-sdui-sheet', { endpoint: '/tenant/documents/appointment/{{ $a->id }}/preview-modal' })"
                                class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-slate-100 dark:bg-[#1E293B] text-slate-700 dark:text-[#F8FAFC] hover:bg-slate-200 dark:hover:bg-slate-700 transition flex items-center gap-1.5 shadow-sm">
                            <span>🧾</span> {{ __('Dispatch') }}
                        </button>
                        <select wire:change="setStatus({{ $a->id }}, $event.target.value)" class="rounded-xl border-slate-200 dark:border-[#1E293B] bg-slate-50 dark:bg-[#0B1120] text-slate-900 dark:text-[#F8FAFC] text-xs font-bold py-1.5">
                            @foreach (\App\Livewire\Tenant\Salon\Calendar::STATUSES as $st)
                                <option value="{{ $st }}" @selected($a->status === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @empty
                <div class="px-5 py-12 text-center text-slate-400 dark:text-[#94A3B8] text-xs">{{ __('No appointments for this day.') }}</div>
            @endforelse
        </div>
    </div>

    {{-- Bottom Sheet / Checkout Dock: Floating Summary Bar with Direct Dispatch Trigger --}}
    @if (count($cartServices) > 0)
        <div class="fixed bottom-4 inset-x-4 max-w-4xl mx-auto z-40 bg-[#131D2D] border border-[#1E293B] shadow-2xl rounded-2xl p-4 flex items-center justify-between gap-4 text-white animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center font-black text-sm">
                    {{ count($cartServices) }}
                </div>
                <div>
                    <div class="text-xs text-[#94A3B8]">{{ __('Total Service Estimate') }}</div>
                    <div class="text-base font-black text-[#F8FAFC]">₹{{ number_format((float) $cartTotal, 2) }}</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button wire:click="clearCart" type="button" class="px-3 py-2 rounded-xl text-xs font-bold text-[#94A3B8] hover:text-white transition">
                    {{ __('Clear') }}
                </button>
                <button type="button"
                        wire:click="newBooking"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold bg-[#10B981] hover:bg-emerald-400 text-slate-950 font-black shadow-lg transition active:scale-95">
                    {{ __('Proceed to Booking') }} →
                </button>
            </div>
        </div>
    @endif
</div>
