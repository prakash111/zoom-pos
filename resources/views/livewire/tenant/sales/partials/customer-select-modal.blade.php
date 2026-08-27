<!-- Customer Selection Modal -->
<x-modal wire:model="showCustomerSelectModal" maxWidth="lg" :title="__('Select Customer / Member')" :subtitle="__('Search and attach a customer to this sale.')" icon="👤">

    <!-- Search & Quick Create Bar -->
    <div class="flex items-center gap-2">
        <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
            <input type="text"
                   wire:model.live.debounce.250ms="customerSearch"
                   placeholder="{{ __('Search by customer name, phone, or email...') }}"
                   class="w-full pl-9 pr-3 py-2 bg-slate-100 dark:bg-slate-800 border-none rounded-xl text-xs sm:text-sm font-medium focus:ring-2 focus:ring-blue-500 text-slate-900 dark:text-white">
        </div>

        <button type="button"
                wire:click="openQuickCustomerModal; $set('showCustomerSelectModal', false)"
                class="px-4 py-2 rounded-xl bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 hover:from-blue-500 hover:via-indigo-500 hover:to-blue-600 text-white font-extrabold text-xs shadow-md shadow-indigo-500/20 active:scale-[0.97] transition duration-150 ease-out flex items-center gap-1.5 shrink-0 cursor-pointer">
            <span>+ {{ __('New') }}</span>
        </button>
    </div>

    <!-- Customer List -->
    <div class="space-y-2 max-h-[50vh] overflow-y-auto pr-1">

        <!-- Option 1: Walk-in / Unassigned -->
        <button type="button"
                wire:click="selectCustomer(null)"
                @class([
                    'w-full p-3 rounded-2xl border text-left flex items-center justify-between transition cursor-pointer',
                    'border-blue-500 bg-blue-50/50 dark:bg-blue-950/40 ring-1 ring-blue-500/30' => is_null($customerId),
                    'border-slate-200/80 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800' => !is_null($customerId),
                ])>
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-200 dark:bg-slate-700 flex items-center justify-center font-black text-xs text-slate-600 dark:text-slate-300">
                    🚶
                </div>
                <div>
                    <div class="font-black text-xs sm:text-sm text-slate-800 dark:text-slate-100">{{ __('Walk-in Regular Customer') }}</div>
                    <div class="text-[11px] text-slate-400">{{ __('Default general walk-in sale') }}</div>
                </div>
            </div>

            @if (is_null($customerId))
                <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-blue-600 text-white">{{ __('Selected ✓') }}</span>
            @endif
        </button>

        <!-- Customer Items -->
        @forelse ($customers as $c)
            <button type="button"
                    wire:click="selectCustomer({{ $c->id }})"
                    @class([
                        'w-full p-3 rounded-2xl border text-left flex items-center justify-between transition cursor-pointer',
                        'border-blue-500 bg-blue-50/50 dark:bg-blue-950/40 ring-1 ring-blue-500/30' => $customerId === $c->id,
                        'border-slate-200/80 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800' => $customerId !== $c->id,
                    ])>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300 flex items-center justify-center font-black text-xs">
                        {{ strtoupper(substr($c->name, 0, 2)) }}
                    </div>
                    <div>
                        <div class="font-extrabold text-xs sm:text-sm text-slate-800 dark:text-slate-100">{{ $c->name }}</div>
                        <div class="text-[11px] text-slate-400 flex items-center gap-2">
                            @if ($c->phone) <span>📞 {{ $c->phone }}</span> @endif
                            @if ($c->email) <span>✉️ {{ $c->email }}</span> @endif
                        </div>
                    </div>
                </div>

                @if ($customerId === $c->id)
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-blue-600 text-white">{{ __('Selected ✓') }}</span>
                @else
                    <span class="text-xs font-bold text-slate-400 hover:text-blue-500">{{ __('Select') }} &rarr;</span>
                @endif
            </button>
        @empty
            <div class="py-8 text-center text-slate-400 text-xs">
                {{ __('No customers found matching') }} "{{ $customerSearch }}".
            </div>
        @endforelse

    </div>

    <!-- Footer -->
    <x-slot:footer>
        <button type="button" @click="open = false" class="px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
            {{ __('Close') }}
        </button>
    </x-slot:footer>
</x-modal>
