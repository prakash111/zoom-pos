<div class="space-y-6">

    <!-- Top Alerts -->
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            {{ session('status') }}
        </div>
    @endif

    <!-- Page Header & Floor Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <span>🪑 {{ __("Floor Plan & Tables") }}</span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-lime-500/15 text-lime-600 dark:text-lime-400 border border-lime-500/30">{{ __("Restaurant Mode") }}</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">{{ __("Manage seating floor areas, table occupancy statuses, and QR code digital menus") }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a wire:navigate.hover href="{{ route('tenant.restaurant.pos') }}"
               class="px-4 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-lime-500 hover:bg-lime-600 text-slate-950 shadow-md shadow-lime-500/20 active:scale-95 transition flex items-center gap-1.5">
                <span>🍽️ {{ __("Open Restaurant POS") }}</span>
            </a>

            <button type="button"
                    wire:click="openAddFloor"
                    class="px-4 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm border border-slate-200 dark:border-slate-700 transition">
                + {{ __("Add Floor") }}
            </button>

            <button type="button"
                    wire:click="openAddTable"
                    class="px-4 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/25 active:scale-95 transition">
                + {{ __("Add Table") }}
            </button>
        </div>
    </div>

    <!-- Floor Filter Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
        <button type="button"
                wire:click="$set('selectedFloorId', 'all')"
                @class([
                    'px-4 py-2 rounded-2xl text-xs font-extrabold transition-all whitespace-nowrap shadow-2xs',
                    'bg-slate-900 text-white dark:bg-white dark:text-slate-900' => $selectedFloorId === 'all',
                    'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' => $selectedFloorId !== 'all',
                ])>
            {{ __("All Floor Areas") }}
        </button>

        @foreach ($floors as $floor)
            <div class="flex items-center bg-white dark:bg-slate-800 rounded-2xl p-0.5 border border-slate-200/80 dark:border-slate-700">
                <button type="button"
                        wire:click="$set('selectedFloorId', '{{ $floor->id }}')"
                        @class([
                            'px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap',
                            'bg-lime-400 text-slate-950 font-black' => $selectedFloorId === $floor->id,
                            'text-slate-700 dark:text-slate-200 hover:text-black dark:hover:text-white' => $selectedFloorId !== $floor->id,
                        ])>
                    {{ $floor->name }} ({{ $floor->tables->count() }})
                </button>
                <button type="button" wire:click="openEditFloor('{{ $floor->id }}')" class="px-1.5 text-slate-400 hover:text-blue-500 text-xs">✏️</button>
            </div>
        @endforeach
    </div>

    <!-- Live Status Legend Bar -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-3 border border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-4 text-xs font-bold">
        <div class="flex items-center gap-4">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> {{ __("Available") }} ({{ $tables->where("status", "available")->count() }})</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> {{ __("Occupied") }} ({{ $tables->where("status", "occupied")->count() }})</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> {{ __("Reserved") }} ({{ $tables->where("status", "reserved")->count() }})</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> {{ __("Billed") }} ({{ $tables->where("status", "billed")->count() }})</span>
        </div>
        <div class="text-slate-400 text-[11px]">{{ __("Total Tables:") }} {{ $tables->count() }}</div>
    </div>

    <!-- Tables Visual Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
        @forelse ($tables as $tbl)
            <div @class([
                'rounded-3xl p-5 border-2 transition-all flex flex-col justify-between shadow-sm relative overflow-hidden group',
                'bg-white dark:bg-slate-900 border-emerald-500/40 hover:border-emerald-500' => $tbl->status === 'available',
                'bg-blue-50/50 dark:bg-blue-950/30 border-blue-500/60 hover:border-blue-500' => $tbl->status === 'occupied',
                'bg-amber-50/50 dark:bg-amber-950/30 border-amber-500/60 hover:border-amber-500' => $tbl->status === 'reserved',
                'bg-purple-50/50 dark:bg-purple-950/30 border-purple-500/60 hover:border-purple-500' => $tbl->status === 'billed',
            ])>
                
                <div>
                    <!-- Card Top -->
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                            {{ $tbl->floor?->name ?? __('Floor') }}
                        </span>
                        
                        <!-- Status Badge Dropdown -->
                        <select wire:change="setTableStatus('{{ $tbl->id }}', $event.target.value)"
                                class="text-[10px] font-black uppercase rounded-lg border-0 py-0.5 px-2 cursor-pointer bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-0">
                            <option value="available" @selected($tbl->status === "available")>🟢 {{ __("Available") }}</option>
                            <option value="occupied" @selected($tbl->status === "occupied")>🔵 {{ __("Occupied") }}</option>
                            <option value="reserved" @selected($tbl->status === "reserved")>🟡 {{ __("Reserved") }}</option>
                            <option value="billed" @selected($tbl->status === "billed")>🟣 {{ __("Billed") }}</option>
                        </select>
                    </div>

                    <!-- Table Number & Capacity -->
                    <div class="space-y-1 my-3">
                        <h3 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white tracking-tight">
                            {{ $tbl->table_number }}
                        </h3>
                        <div class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-2">
                            <span>👤 {{ __("Capacity:") }} {{ $tbl->seating_capacity }}</span>
                            @if ($tbl->status === 'occupied' && $tbl->guest_count > 0)
                                <span class="text-blue-600 dark:text-blue-400 font-extrabold">&bull; {{ $tbl->guest_count }} {{ __("Guests") }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Card Actions -->
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 space-y-2">
                    
                    <a wire:navigate.hover href="{{ route('tenant.restaurant.pos', ['table_id' => $tbl->id]) }}"
                       class="w-full py-2 rounded-xl bg-slate-900 dark:bg-slate-800 hover:bg-lime-500 dark:hover:bg-lime-400 hover:text-slate-950 dark:hover:text-slate-950 text-white text-xs font-extrabold transition flex items-center justify-center gap-1.5">
                        <span>🍽️ {{ __("Open Table POS") }}</span>
                    </a>

                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-500 pt-1">
                        <!-- QR Code Stand Link -->
                        <a href="{{ route('tenant.restaurant.table.qr', $tbl) }}"
                           target="_blank"
                           class="hover:text-blue-600 dark:hover:text-blue-400 flex items-center gap-1">
                            <span>📱 {{ __("QR Stand") }}</span>
                        </a>

                        <div class="space-x-1.5">
                            <button type="button" wire:click="openEditTable('{{ $tbl->id }}')" class="hover:text-blue-500">{{ __("Edit") }}</button>
                            <button type="button" wire:click="deleteTable('{{ $tbl->id }}')" wire:confirm="{{ __("Remove table") }} {{ $tbl->table_number }}?" class="hover:text-rose-500">{{ __("Delete") }}</button>
                        </div>
                    </div>

                </div>

            </div>
        @empty
            <div class="col-span-full py-12 text-center text-slate-400 text-xs">
                {{ __('No tables registered for this floor. Click "+ Add Table" to start.') }}
            </div>
        @endforelse
    </div>

    <!-- Floor Modal -->
    <x-modal wire:model="showFloorModal" maxWidth="sm" :title="$editingFloorId ? __('Edit Floor Area') : __('Add Floor Area')">
        <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Area Name *") }}</label>
            <input type="text" wire:model="floorName" placeholder="{{ __("e.g. Rooftop Patio, Bar, Hall") }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
            @error('floorName') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <x-slot:footer>
            <button type="button" @click="open = false" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer">
                {{ __("Cancel") }}
            </button>
            <x-ui.button wire:click="saveFloor">{{ __("Save Floor") }}</x-ui.button>
        </x-slot:footer>
    </x-modal>

    <!-- Table Modal -->
    <x-modal wire:model="showTableModal" maxWidth="md" :title="$editingTableId ? __('Edit Table') : __('Add Table')">
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Table Name / Number *") }}</label>
                <input type="text" wire:model="tableNumber" placeholder="{{ __("e.g. Table 04, VIP-1, Bar-02") }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                @error('tableNumber') <p class="text-rose-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Floor Area") }}</label>
                    <select wire:model="tableFloorId" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                        <option value="">{{ __("No Floor Assigned") }}</option>
                        @foreach ($floors as $f)
                            <option value="{{ $f->id }}">{{ $f->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Capacity (Seats)") }}</label>
                    <input type="number" wire:model="tableCapacity" min="1" max="50" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Initial Status") }}</label>
                <select wire:model="tableStatus" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    <option value="available">{{ __("Available") }}</option>
                    <option value="occupied">{{ __("Occupied") }}</option>
                    <option value="reserved">{{ __("Reserved") }}</option>
                    <option value="billed">{{ __("Billed") }}</option>
                </select>
            </div>
        </div>

        <x-slot:footer>
            <button type="button" @click="open = false" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer">
                {{ __("Cancel") }}
            </button>
            <x-ui.button wire:click="saveTable">{{ __("Save Table") }}</x-ui.button>
        </x-slot:footer>
    </x-modal>

</div>
