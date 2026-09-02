<div class="h-full flex-1 flex flex-col lg:flex-row gap-4 antialiased overflow-hidden font-sans relative min-h-0"
     x-data
     x-on:item-added-to-cart.window="playAddToCartBeep()">
    
    <!-- Floating Toast Notifications -->
    @if (session('status'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
             class="fixed top-5 right-5 z-50 px-5 py-3.5 rounded-2xl bg-emerald-500 text-slate-950 text-xs sm:text-sm font-black flex items-center gap-3 shadow-2xl animate-bounce">
            <span>🎉</span>
            <span>{{ session('status') }}</span>
            <button type="button" @click="show = false" class="text-slate-950 font-bold ml-2">&times;</button>
        </div>
    @endif

    @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
             class="fixed top-5 right-5 z-50 px-5 py-3.5 rounded-2xl bg-rose-500 text-white text-xs sm:text-sm font-black flex items-center gap-3 shadow-2xl">
            <span>⚠️</span>
            <span>{{ session('error') }}</span>
            <button type="button" @click="show = false" class="text-white font-bold ml-2">&times;</button>
        </div>
    @endif

    <!-- LEFT & CENTER: Menu, Routing Tabs & Product Grid -->
    <div class="flex-1 flex flex-col bg-[#121829] dark:bg-[#090d16] rounded-3xl p-4 sm:p-5 border border-slate-800/80 shadow-2xl overflow-hidden">
        
        <!-- Top Controls: Service Routing Modes + Staff Badge -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-800/80">
            
            <!-- Service Mode Selector -->
            <div class="flex items-center gap-1.5 bg-slate-900/90 p-1 rounded-2xl border border-slate-800 text-xs font-black">
                <button type="button"
                        wire:click="setServiceType('dine_in')"
                        @class([
                            'px-3.5 py-2 rounded-xl transition-all flex items-center gap-1.5',
                            'bg-[#a3e635] text-slate-950 shadow-md' => $serviceType === 'dine_in',
                            'text-slate-400 hover:text-white' => $serviceType !== 'dine_in',
                        ])>
                    <span>🍽️ {{ __("Dine-In") }}</span>
                </button>

                <button type="button"
                        wire:click="setServiceType('takeaway')"
                        @class([
                            'px-3.5 py-2 rounded-xl transition-all flex items-center gap-1.5',
                            'bg-[#a3e635] text-slate-950 shadow-md' => $serviceType === 'takeaway',
                            'text-slate-400 hover:text-white' => $serviceType !== 'takeaway',
                        ])>
                    <span>🛍️ {{ __("Takeaway") }}</span>
                </button>

                <button type="button"
                        wire:click="setServiceType('delivery')"
                        @class([
                            'px-3.5 py-2 rounded-xl transition-all flex items-center gap-1.5',
                            'bg-[#a3e635] text-slate-950 shadow-md' => $serviceType === 'delivery',
                            'text-slate-400 hover:text-white' => $serviceType !== 'delivery',
                        ])>
                    <span>🛵 {{ __("Delivery") }}</span>
                </button>
            </div>

            <!-- Search & Staff Info -->
            <div class="flex items-center gap-3">
                <div class="relative w-44 sm:w-56">
                    <input type="text"
                           wire:model.live.debounce.250ms="search"
                           placeholder="{{ __("Search menu...") }}"
                           class="w-full pl-8 pr-3 py-1.5 bg-slate-900/80 border-slate-800 rounded-xl text-xs text-white placeholder-slate-500 focus:ring-1 focus:ring-lime-400">
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-500 text-xs">
                        🔍
                    </div>
                </div>

                <div class="text-right hidden sm:block">
                    <div class="text-xs font-black text-slate-200">{{ auth('web')->user()?->name ?? __('Staff') }}</div>
                    <div class="text-[10px] font-bold text-slate-400">{{ __("POS Server") }} &bull; {{ now()->format('h:i A') }}</div>
                </div>
            </div>
        </div>

        <!-- Takeaway / Delivery Details Bar (if selected) -->
        @if ($serviceType === 'takeaway')
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 bg-slate-900/80 p-2.5 rounded-2xl border border-slate-800 my-2 text-xs">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-0.5">{{ __("Customer Name") }}</label>
                    <input type="text" wire:model="customerName" placeholder="{{ __("Guest name") }}" class="w-full py-1 px-2.5 rounded-lg bg-slate-800 border-slate-700 text-xs text-white">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-0.5">{{ __("Phone Number") }}</label>
                    <input type="text" wire:model="customerPhone" placeholder="{{ __("Phone") }}" class="w-full py-1 px-2.5 rounded-lg bg-slate-800 border-slate-700 text-xs text-white">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-0.5">{{ __("Pickup Time") }}</label>
                    <input type="text" wire:model="pickupTime" placeholder="{{ __("e.g. 15 mins, 06:00 PM") }}" class="w-full py-1 px-2.5 rounded-lg bg-slate-800 border-slate-700 text-xs text-white">
                </div>
            </div>
        @elseif ($serviceType === 'delivery')
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 bg-slate-900/80 p-2.5 rounded-2xl border border-slate-800 my-2 text-xs">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-0.5">{{ __("Customer Name") }}</label>
                    <input type="text" wire:model="customerName" placeholder="{{ __("Customer name") }}" class="w-full py-1 px-2.5 rounded-lg bg-slate-800 border-slate-700 text-xs text-white">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-0.5">{{ __("Customer Phone") }}</label>
                    <input type="text" wire:model="customerPhone" placeholder="{{ __("Phone") }}" class="w-full py-1 px-2.5 rounded-lg bg-slate-800 border-slate-700 text-xs text-white">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-0.5">{{ __("Delivery Address") }}</label>
                    <input type="text" wire:model="deliveryAddress" placeholder="{{ __("Street & apt") }}" class="w-full py-1 px-2.5 rounded-lg bg-slate-800 border-slate-700 text-xs text-white">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-0.5">{{ __("Driver / Courier") }}</label>
                    <input type="text" wire:model="driverName" placeholder="{{ __("Driver name") }}" class="w-full py-1 px-2.5 rounded-lg bg-slate-800 border-slate-700 text-xs text-white">
                </div>
            </div>
        @endif

        <!-- Category Filter Pills (matching food-idea-pos.png) -->
        <div class="pos-categories-row tab-scroll-container flex items-center gap-2 overflow-x-auto no-scrollbar py-3">
            <button type="button"
                    wire:click="$set('selectedCategoryId', null)"
                    class="pos-category-btn"
                    role="tab"
                    aria-selected="{{ $selectedCategoryId === null ? 'true' : 'false' }}"
                    @class([
                        'px-4 py-2 rounded-2xl text-xs whitespace-nowrap transition-all cursor-pointer',
                        'bg-[#a3e635] text-slate-950 font-black shadow-lg shadow-lime-500/20' => $selectedCategoryId === null,
                        'bg-slate-900/90 text-slate-400 hover:text-white border border-slate-800 font-bold' => $selectedCategoryId !== null,
                    ])>
                {{ __("All Menu") }}
            </button>

            @foreach ($categories as $cat)
                <button type="button"
                        wire:click="$set('selectedCategoryId', {{ $cat->id }})"
                        class="pos-category-btn"
                        role="tab"
                        aria-selected="{{ $selectedCategoryId === $cat->id ? 'true' : 'false' }}"
                        @class([
                            'px-4 py-2 rounded-2xl text-xs whitespace-nowrap transition-all cursor-pointer',
                            'bg-[#a3e635] text-slate-950 font-black shadow-lg shadow-lime-500/20' => $selectedCategoryId === $cat->id,
                            'bg-slate-900/90 text-slate-400 hover:text-white border border-slate-800 font-bold' => $selectedCategoryId !== $cat->id,
                        ])>
                    {{ $cat->name }}
                </button>
            @endforeach
        </div>

        <!-- Skeleton Shimmer Placeholder for Food Menu -->
        <div wire:loading.grid wire:target="search,selectedCategoryId" class="flex-1 overflow-y-auto pr-1 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3.5 content-start">
            @for ($i = 0; $i < 8; $i++)
                <div class="animate-pulse bg-slate-900/90 rounded-3xl p-3 flex flex-col gap-2.5 border border-slate-800/80">
                    <div class="w-full aspect-square bg-slate-800 rounded-2xl"></div>
                    <div class="h-3.5 bg-slate-800 rounded-md w-3/4"></div>
                    <div class="h-3 bg-slate-800 rounded-md w-1/3"></div>
                </div>
            @endfor
        </div>

        <!-- Food Menu Grid (matching food-idea-pos.png) -->
        <div wire:loading.remove wire:target="search,selectedCategoryId" class="flex-1 overflow-y-auto pr-1 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3.5 content-start">
            @forelse ($products as $p)
                <div wire:click="addItemDirect({{ $p->id }})"
                     class="bg-slate-900/90 hover:bg-slate-800/90 rounded-3xl overflow-hidden border border-slate-800/80 hover:border-[#a3e635]/60 hover:shadow-lg hover:shadow-lime-500/10 transition-all duration-150 ease-out cursor-pointer group flex flex-col justify-between shadow-md active:scale-[0.97]">
                    
                    <!-- Food Photo Thumbnail with Fixed Aspect Ratio -->
                    <div class="relative w-full aspect-square bg-slate-800 overflow-hidden shrink-0">
                        <img src="{{ $p->getImageUrlOrDefault() }}"
                             alt="{{ $p->name }}"
                             loading="lazy"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">

                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent opacity-60 group-hover:opacity-30 transition-opacity"></div>

                        @if (!empty($p->variants) && count($p->variants) > 0)
                            <span class="absolute bottom-2 left-2 px-2 py-0.5 rounded-full text-[9px] font-black bg-black/80 text-[#a3e635] backdrop-blur-xs border border-lime-500/30">
                                {{ __("Options") }}
                            </span>
                        @endif

                        <span class="absolute bottom-2 right-2 px-2 py-0.5 rounded-full text-[10px] font-black bg-black/80 text-white backdrop-blur-xs">
                            {{ $company->formatMoney($p->sale_price) }}
                        </span>
                    </div>

                    <!-- Details -->
                    <div class="p-3">
                        <h4 class="font-bold text-xs sm:text-sm text-slate-100 line-clamp-1 leading-tight group-hover:text-[#a3e635] transition">
                            {{ $p->name }}
                        </h4>
                        <div class="text-[11px] font-semibold text-slate-400 truncate mt-0.5">
                            {{ $p->category_name ?? $p->category?->name ?? __('Food Menu') }}
                        </div>
                    </div>

                </div>
            @empty
                <div class="col-span-full py-16 text-center text-slate-500 text-xs">
                    {{ __('No food items found. Use "+ Add Product" in Products menu to create items.') }}
                </div>
            @endforelse
        </div>

    </div>

    <!-- RIGHT: Seat Breakdown, Cart Order & Kitchen Dispatch (matching food-idea-pos.png) -->
    <div class="w-full lg:w-96 xl:w-[420px] shrink-0 flex flex-col justify-between bg-[#121829] dark:bg-[#090d16] rounded-3xl p-5 border border-slate-800/80 shadow-2xl overflow-hidden h-full max-h-full sticky top-0 min-h-0">
        
        <!-- Order Panel Top: Table Info / Mode Header -->
        <div>
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                @if ($serviceType === 'dine_in')
                    <div class="flex items-center gap-2.5">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-black text-white">
                                    {{ $activeTable?->table_number ?? __('Select Table') }}
                                </h3>
                                <button type="button"
                                        wire:click="$set('showTableSelectorModal', true)"
                                        class="text-slate-400 hover:text-lime-400 text-xs p-1"
                                        title="{{ __("Switch Table") }}">
                                    ✏️
                                </button>
                            </div>
                            <div class="text-[11px] font-bold text-slate-400">
                                {{ $activeTable?->floor?->name ?? __('Floor Area') }}
                            </div>
                        </div>
                    </div>

                    <!-- Guests and Actions Badge -->
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-xl bg-slate-800 text-slate-300 text-xs font-black flex items-center gap-1">
                            <span>👥</span>
                            <span>{{ $guestCount }}</span>
                        </span>

                        <button type="button"
                                wire:click="$set('showTransferModal', true)"
                                class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs"
                                title="{{ __("Transfer Table") }}">
                            🔄
                        </button>
                    </div>
                @else
                    <div>
                        <h3 class="text-base font-black text-white capitalize">
                            {{ $serviceType }} {{ __("Order") }}
                        </h3>
                        <div class="text-xs font-bold text-lime-400">
                            {{ $customerName ?: __('Counter Checkout') }}
                        </div>
                    </div>
                @endif
            </div>

            <!-- Seat Tabs (🪑 S1, 🪑 S2, 🪑 S3, 🪑 S4, +) matching food-idea-pos.png -->
            @if ($serviceType === 'dine_in')
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-3 border-b border-slate-800/80">
                    @foreach ($seats as $seatNum)
                        <button type="button"
                                wire:click="setActiveSeat({{ $seatNum }})"
                                @class([
                                    'px-3 py-1.5 rounded-xl text-xs font-black transition-all flex items-center gap-1 whitespace-nowrap',
                                    'bg-[#a3e635] text-slate-950 shadow-md' => $activeSeat === $seatNum,
                                    'bg-slate-800/80 text-slate-400 hover:text-white' => $activeSeat !== $seatNum,
                                ])>
                            <span>🪑 S{{ $seatNum }}</span>
                            @php
                                $seatItemCount = count(array_filter($items, fn($i) => ($i['seat'] ?? 1) === $seatNum));
                            @endphp
                            @if ($seatItemCount > 0)
                                <span class="w-4 h-4 rounded-full bg-slate-950 text-[#a3e635] text-[9px] font-black flex items-center justify-center">
                                    {{ $seatItemCount }}
                                </span>
                            @endif
                        </button>
                    @endforeach

                    <button type="button"
                            wire:click="addSeat"
                            class="px-2.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-black text-xs">
                        +
                    </button>
                </div>
            @endif
        </div>

        <!-- Order Items List -->
        <div class="flex-1 min-h-[140px] overflow-y-auto py-3 space-y-2 pr-1 my-1">
            @forelse ($items as $item)
                <div class="bg-slate-900/90 rounded-2xl p-3 border border-slate-800 space-y-1.5">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-bold text-xs sm:text-sm text-white flex items-center gap-1.5">
                                <span>{{ $item['name'] }}</span>
                                @if (!empty($item['seat']) && $serviceType === 'dine_in')
                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-black bg-slate-800 text-lime-400">S{{ $item['seat'] }}</span>
                                @endif
                            </div>

                            @if (!empty($item['variant']))
                                <div class="text-[11px] font-bold text-slate-400">
                                    {{ __("Option:") }} {{ $item["variant"] }}
                                </div>
                            @endif

                            @if (!empty($item['modifiers']) && is_array($item['modifiers']))
                                <div class="text-[10px] font-bold text-blue-400">
                                    + {{ implode(', ', array_column($item['modifiers'], 'name')) }}
                                </div>
                            @endif

                            @if (!empty($item['spice_level']))
                                <div class="text-[10px] font-bold text-rose-400">
                                    🌶️ {{ $item['spice_level'] }}
                                </div>
                            @endif

                            @if (!empty($item['note']))
                                <div class="text-[10px] font-extrabold text-amber-400">
                                    {{ __("Note:") }} {{ $item["note"] }}
                                </div>
                            @endif
                        </div>

                        <!-- Steppers -->
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    wire:click="decrementItem('{{ $item['id'] }}')"
                                    class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-black text-xs flex items-center justify-center">
                                -
                            </button>
                            <span class="font-black text-xs text-white min-w-[16px] text-center">
                                {{ $item['quantity'] }}
                            </span>
                            <button type="button"
                                    wire:click="incrementItem('{{ $item['id'] }}')"
                                    class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-white font-black text-xs flex items-center justify-center">
                                +
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-between items-center text-xs pt-1 border-t border-slate-800/60">
                        <button type="button"
                                wire:click="removeItem('{{ $item['id'] }}')"
                                class="text-[10px] font-bold text-rose-400 hover:text-rose-300">
                            Remove
                        </button>

                        <div class="flex items-center gap-1.5">
                            @if ($this->canOverridePrice)
                                <span class="text-[10px] text-slate-500">$</span>
                                <input type="number" min="0" step="0.01" value="{{ $item['price'] }}"
                                       wire:change="applyPriceOverride('{{ $item['id'] }}', $event.target.value)"
                                       class="w-14 py-0.5 px-1 text-[10px] font-bold rounded-md border border-slate-700 bg-slate-800 text-white"
                                       title="{{ __("Override unit price") }}">
                                <span class="text-[10px] text-slate-500">{{ __("each") }}</span>
                            @endif
                            <span class="font-black text-white">
                                {{ $company->formatMoney(((float)$item['price']) * ((float)$item['quantity'])) }}
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-16 text-center text-slate-500 text-xs">
                    {{ __("No items selected for this order.") }}<br>{{ __("Click any menu card on the left to add food.") }}
                </div>
            @endforelse
        </div>

        <!-- Order Summary & Actions (matching food-idea-pos.png) -->
        <div class="pt-3 border-t border-slate-800 space-y-3 shrink-0">
            <div class="space-y-1 text-xs">
                <div class="flex justify-between text-slate-400">
                    <span>{{ __("Subtotal") }}</span>
                    <span class="font-bold text-white">{{ $company->formatMoney($this->subtotal) }}</span>
                </div>
                @if ($discount > 0)
                    <div class="flex justify-between text-rose-400">
                        <span>{{ __("Discount") }}</span>
                        <span class="font-bold">-{{ $company->formatMoney($discount) }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-base font-black text-white pt-1 border-t border-slate-800">
                    <span>{{ __("Total") }}</span>
                    <span class="text-[#a3e635]">{{ $company->formatMoney($this->total) }}</span>
                </div>
            </div>

            <!-- Estimated Preparation Time -->
            <div class="flex items-center gap-1.5 pb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase shrink-0">{{ __('Prep Time') }}</span>
                <div class="flex items-center gap-1 flex-wrap">
                    @foreach ([5, 10, 15, 20, 30] as $mins)
                        <button type="button"
                                wire:click="$set('prepMinutes', {{ $mins }})"
                                @class([
                                    'px-2.5 py-1 rounded-lg text-[10px] font-bold transition',
                                    'bg-lime-400 text-slate-950' => $prepMinutes === $mins,
                                    'bg-slate-800 text-slate-300 hover:bg-slate-700' => $prepMinutes !== $mins,
                                ])>
                            {{ $mins }}m
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Action Buttons matching food-idea-pos.png -->
            <div class="flex items-center gap-2">
                <button type="button"
                        wire:click="clearOrder"
                        wire:confirm="{{ __("Clear all items in this order?") }}"
                        class="w-1/3 py-3.5 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition active:scale-95 text-center">
                    {{ __("Cancel") }}
                </button>

                <button type="button"
                        wire:click="sendToKitchen"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-70 cursor-wait"
                        class="w-2/3 py-3.5 rounded-2xl bg-[#a3e635] hover:bg-[#84cc16] text-slate-950 font-black text-xs sm:text-sm shadow-xl shadow-lime-500/20 active:scale-95 transition flex items-center justify-center gap-2">
                    <span wire:loading.remove class="flex items-center gap-1.5">&rarr; Send to Kitchen</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-slate-950" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ __("Dispatching...") }}</span>
                    </span>
                </button>
            </div>

            <!-- Bill Settlement Action -->
            <button type="button"
                    wire:click="openCheckoutModal"
                    class="w-full py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md active:scale-95 transition flex items-center justify-center gap-1.5">
                <span>💳 {{ __("Pay & Settle Bill") }} &bull; {{ $company->formatMoney($this->total) }}</span>
            </button>
        </div>

    </div>

    <!-- Modifiers & Add-ons Modal -->
    @if ($showModifierModal && $selectedProduct)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs">
            <div class="bg-slate-900 rounded-3xl max-w-md w-full p-6 space-y-4 border border-slate-800 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-base font-black text-white">{{ $selectedProduct->name }}</h3>
                        <div class="text-xs font-black text-lime-400">{{ $company->formatMoney($selectedVariantPrice) }}</div>
                    </div>
                    <button type="button" wire:click="$set('showModifierModal', false)" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
                </div>

                <!-- Variants Selection -->
                @if (!empty($selectedProduct->variants) && count($selectedProduct->variants) > 0)
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider">{{ __("Size / Style") }}</label>
                        <div class="grid grid-cols-1 gap-1.5">
                            @foreach ($selectedProduct->variants as $v)
                                <button type="button"
                                        wire:click="selectVariant('{{ $v['name'] }}', {{ (float)$v['price'] }})"
                                        @class([
                                            'w-full p-3 rounded-2xl border text-xs font-bold flex justify-between items-center transition',
                                            'border-lime-500 bg-lime-500/15 text-white' => $selectedVariantName === $v['name'],
                                            'border-slate-800 bg-slate-800/60 text-slate-300' => $selectedVariantName !== $v['name'],
                                        ])>
                                    <span>{{ $v['name'] }}</span>
                                    <span class="text-lime-400 font-extrabold">{{ $company->formatMoney((float)$v['price']) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Modifiers / Add-ons -->
                @if (!empty($selectedProduct->modifiers) && count($selectedProduct->modifiers) > 0)
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider">{{ __("Add-ons & Extras") }}</label>
                        <div class="space-y-1.5">
                            @foreach ($selectedProduct->modifiers as $m)
                                @php
                                    $isSelected = in_array($m['name'], array_column($selectedModifiers, 'name'), true);
                                @endphp
                                <button type="button"
                                        wire:click="toggleModifier('{{ $m['name'] }}', {{ (float)$m['price'] }})"
                                        @class([
                                            'w-full p-3 rounded-2xl border text-xs font-bold flex justify-between items-center transition cursor-pointer',
                                            'border-lime-500 bg-lime-500/15 text-white' => $isSelected,
                                            'border-slate-800 bg-slate-800/40 text-slate-300' => !$isSelected,
                                        ])>
                                    <span class="flex items-center gap-2">
                                        <span class="w-4 h-4 rounded {{ $isSelected ? 'bg-lime-500 text-slate-950' : 'bg-slate-700' }} text-[10px] font-black flex items-center justify-center">
                                            {{ $isSelected ? '✓' : '' }}
                                        </span>
                                        <span>{{ $m['name'] }}</span>
                                    </span>
                                    <span class="text-lime-400 font-extrabold">+{{ $company->formatMoney((float)$m['price']) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Spice Level -->
                @if (!empty($selectedProduct->spice_levels) && count($selectedProduct->spice_levels) > 0)
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider">{{ __("Spice Level") }}</label>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($selectedProduct->spice_levels as $s)
                                <button type="button"
                                        wire:click="selectSpiceLevel('{{ $s['name'] }}', {{ (float)$s['price'] }})"
                                        @class([
                                            'px-3 py-2 rounded-2xl border text-xs font-bold transition',
                                            'border-lime-500 bg-lime-500/15 text-white' => $selectedSpiceLevelName === $s['name'],
                                            'border-slate-800 bg-slate-800/60 text-slate-300' => $selectedSpiceLevelName !== $s['name'],
                                        ])>
                                    {{ $s['name'] }}
                                    @if ((float) $s['price'] != 0)
                                        <span class="text-lime-400 font-extrabold">+{{ $company->formatMoney((float)$s['price']) }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Target Seat & Kitchen Note -->
                <div class="grid grid-cols-2 gap-3">
                    @if ($serviceType === 'dine_in')
                        <div>
                            <label class="block text-[11px] font-bold text-slate-400 mb-1">{{ __("Target Seat") }}</label>
                            <select wire:model="activeSeat" class="w-full rounded-xl bg-slate-800 border-slate-700 text-xs text-white">
                                @foreach ($seats as $s)
                                    <option value="{{ $s }}">{{ __("Seat") }} {{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="{{ $serviceType !== 'dine_in' ? 'col-span-2' : '' }}">
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">{{ __("Kitchen Note") }}</label>
                        <input type="text" wire:model="itemNote" placeholder="{{ __("e.g. No onion") }}" class="w-full rounded-xl bg-slate-800 border-slate-700 text-xs text-white">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showModifierModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400">Cancel</button>
                    <button type="button" wire:click="addCustomizedItemToCart" class="px-5 py-2.5 rounded-xl text-xs font-extrabold bg-[#a3e635] text-slate-950">{{ __("Add to Order") }}</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Table Selection Modal -->
    @if ($showTableSelectorModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs">
            <div class="bg-slate-900 rounded-3xl max-w-2xl w-full p-6 space-y-4 border border-slate-800 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center pb-2 border-b border-slate-800">
                    <h3 class="text-base font-black text-white">{{ __("Select Dine-In Table") }}</h3>
                    <button type="button" wire:click="$set('showTableSelectorModal', false)" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach ($tables as $t)
                        <button type="button"
                                wire:click="selectTable('{{ $t->id }}')"
                                @class([
                                    'p-4 rounded-2xl border-2 text-left transition flex flex-col justify-between gap-2',
                                    'border-lime-500 bg-lime-500/10' => $activeTable && $activeTable->id === $t->id,
                                    'border-emerald-500/40 bg-slate-800/60' => $t->status === 'available' && (!$activeTable || $activeTable->id !== $t->id),
                                    'border-blue-500/60 bg-blue-950/20' => $t->status === 'occupied' && (!$activeTable || $activeTable->id !== $t->id),
                                    'border-amber-500/60 bg-amber-950/20' => $t->status === 'reserved',
                                    'border-purple-500/60 bg-purple-950/20' => $t->status === 'billed',
                                ])>
                            <div class="flex justify-between items-start">
                                <span class="font-black text-sm text-white">{{ $t->table_number }}</span>
                                <span class="text-[10px] font-extrabold uppercase text-slate-400">{{ $t->status }}</span>
                            </div>
                            <div class="text-[11px] text-slate-400 font-bold">
                                {{ $t->floor?->name ?? 'Floor' }} &bull; 👤 {{ $t->seating_capacity }}
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Table Transfer Modal -->
    @if ($showTransferModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs">
            <div class="bg-slate-900 rounded-3xl max-w-md w-full p-6 space-y-4 border border-slate-800">
                <div class="flex justify-between items-center pb-2 border-b border-slate-800">
                    <h3 class="text-base font-black text-white">{{ __("Transfer Table Order") }}</h3>
                    <button type="button" wire:click="$set('showTransferModal', false)" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">{{ __("Select Destination Table") }}</label>
                    <select wire:model="transferTargetTableId" class="w-full rounded-xl bg-slate-800 border-slate-700 text-xs text-white">
                        <option value="">{{ __("Select Available Table") }}</option>
                        @foreach ($tables->where('status', 'available') as $t)
                            <option value="{{ $t->id }}">{{ $t->table_number }} ({{ $t->floor?->name }}) - 👤 {{ $t->seating_capacity }} {{ __("seats") }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showTransferModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400">Cancel</button>
                    <button type="button" wire:click="transferTable" class="px-5 py-2 rounded-xl text-xs font-extrabold bg-blue-600 text-white">{{ __("Transfer Order") }}</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Checkout / Settle Bill Modal -->
    @if ($showCheckoutModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs">
            <div class="bg-slate-900 rounded-3xl max-w-xl w-full p-6 space-y-4 border border-slate-800 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center pb-2 border-b border-slate-800">
                    <h3 class="text-base font-black text-white">{{ __("Settle Bill & Print Receipt") }}</h3>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                wire:click="toggleSplitPayment"
                                @class([
                                    'px-2.5 py-1 rounded-lg text-[10px] font-bold transition border',
                                    'bg-lime-400 text-slate-950 border-lime-400' => $isSplitPayment,
                                    'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700' => ! $isSplitPayment,
                                ])>
                            🔀 {{ __('Split:') }} {{ $isSplitPayment ? __('ON') : __('OFF') }}
                        </button>
                        <button type="button" wire:click="$set('showCheckoutModal', false)" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="bg-slate-800/80 rounded-2xl p-4 text-center space-y-1">
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">{{ __("Total Amount Due") }}</div>
                        <div class="text-3xl font-black text-lime-400">{{ $company->formatMoney($this->total) }}</div>
                    </div>

                    <!-- Customer Attachment -->
                    <div class="bg-slate-800/60 rounded-2xl p-3 flex items-center justify-between gap-2">
                        @if ($this->checkoutCustomer)
                            <div class="min-w-0">
                                <div class="text-[9px] font-extrabold uppercase text-slate-400">{{ __('Customer') }}</div>
                                <div class="text-xs font-bold text-white truncate">{{ $this->checkoutCustomer->name }}</div>
                                @if ($this->checkoutCustomer->phone)
                                    <div class="text-[10px] text-slate-400">{{ $this->checkoutCustomer->phone }}</div>
                                @endif
                            </div>
                            <button type="button" wire:click="clearCheckoutCustomer" class="text-[10px] font-bold text-rose-400 hover:text-rose-300 shrink-0">{{ __('Remove') }}</button>
                        @else
                            <div class="text-xs text-slate-400">{{ __('No customer assigned') }}</div>
                            <button type="button" wire:click="openCustomerPicker" class="px-3 py-1.5 rounded-xl bg-slate-700 hover:bg-slate-600 text-white text-[10px] font-bold shrink-0">👤 {{ __('Add Customer') }}</button>
                        @endif
                    </div>

                    @if (! $isSplitPayment)
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1.5">{{ __("Payment Method") }}</label>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($paymentMethods as $pm)
                                    @php $val = $pm->code ?: strtolower($pm->name); @endphp
                                    <button type="button"
                                            wire:click="$set('paymentMethod', '{{ $val }}')"
                                            @class([
                                                'py-2 px-2 rounded-xl text-xs font-extrabold transition truncate',
                                                'bg-lime-400 text-slate-950 shadow-md' => $paymentMethod === $val,
                                                'bg-slate-800 text-slate-300 hover:bg-slate-700' => $paymentMethod !== $val,
                                            ])>
                                        {{ $pm->name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @if ($paymentMethod === 'cash')
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 mb-1">{{ __("Cash Tendered ($)") }}</label>
                                    <input type="number" min="0" step="0.5" wire:model.live="cashTendered" class="w-full rounded-xl bg-slate-800 border-slate-700 text-xs text-white">
                                </div>
                                <div class="bg-lime-500/10 border border-lime-500/30 rounded-xl p-2 flex flex-col justify-center">
                                    <div class="text-[9px] font-extrabold uppercase text-lime-400">{{ __("Change Due") }}</div>
                                    <div class="text-sm font-black text-lime-400">{{ $company->formatMoney($this->changeDue) }}</div>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="space-y-2 p-3 rounded-2xl border border-lime-500/30 bg-lime-500/5">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-extrabold text-lime-400 uppercase tracking-wider">{{ __("Split Payment Entries") }}</span>
                                <button type="button" wire:click="addSplitRow" class="px-2 py-1 rounded-lg bg-lime-400 hover:bg-lime-500 text-slate-950 font-bold text-[10px]">+ {{ __("Add Split") }}</button>
                            </div>

                            @foreach ($splitPayments as $idx => $sp)
                                <div class="grid grid-cols-12 gap-1.5 items-center bg-slate-800/80 rounded-xl p-2">
                                    <select wire:model.live="splitPayments.{{ $idx }}.payment_method" class="col-span-4 text-[10px] rounded-lg bg-slate-900 border-slate-700 text-white py-1 px-1.5">
                                        @foreach ($paymentMethods as $pm)
                                            <option value="{{ $pm->code }}">{{ $pm->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" min="0" step="0.5" wire:model.live="splitPayments.{{ $idx }}.amount" class="col-span-4 text-[10px] rounded-lg bg-slate-900 border-slate-700 text-white py-1 px-1.5" placeholder="{{ __("Amount") }}">
                                    <input type="text" wire:model.live="splitPayments.{{ $idx }}.reference_number" class="col-span-3 text-[10px] rounded-lg bg-slate-900 border-slate-700 text-white py-1 px-1.5" placeholder="{{ __("Ref #") }}">
                                    <button type="button" wire:click="removeSplitRow({{ $idx }})" class="col-span-1 text-rose-400 hover:text-rose-300 font-black text-xs">✕</button>
                                </div>
                            @endforeach

                            <div class="grid grid-cols-3 gap-1.5 pt-1 text-center">
                                <div class="bg-slate-800 rounded-lg p-1.5">
                                    <div class="text-[9px] text-slate-400 font-bold uppercase">{{ __("Total") }}</div>
                                    <div class="text-xs font-black text-white">{{ $company->formatMoney($this->total) }}</div>
                                </div>
                                <div class="bg-slate-800 rounded-lg p-1.5">
                                    <div class="text-[9px] text-slate-400 font-bold uppercase">{{ __("Allocated") }}</div>
                                    <div class="text-xs font-black text-lime-400">{{ $company->formatMoney($this->splitTotalPaid) }}</div>
                                </div>
                                <div @class([
                                    'rounded-lg p-1.5',
                                    'bg-rose-500/20' => $this->remainingBalance > 0,
                                    'bg-emerald-500/20' => $this->remainingBalance <= 0,
                                ])>
                                    <div class="text-[9px] font-bold uppercase text-slate-300">
                                        {{ $this->remainingBalance > 0 ? __('Remaining') : ($this->remainingBalance < 0 ? __('Change Due') : __('Settled')) }}
                                    </div>
                                    <div class="text-xs font-black text-white">{{ $company->formatMoney(abs($this->remainingBalance)) }}</div>
                                </div>
                            </div>

                            @if ($this->remainingBalance > 0)
                                <div class="flex items-center justify-between gap-2 pt-1">
                                    <span class="text-[10px] text-amber-400 font-semibold">⚠️ {{ __("Balance logged to Accounts Receivable") }}</span>
                                    <input type="date" wire:model="dueDate" class="text-[10px] rounded-lg bg-slate-900 border-slate-700 text-white py-1 px-1.5">
                                </div>
                            @endif
                        </div>
                    @endif

                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">{{ __("Discount ($)") }}</label>
                        <input type="number" step="0.01" wire:model.live="discount" class="w-full rounded-xl bg-slate-800 border-slate-700 text-xs text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">{{ __("Order Notes & Remarks (Printed on Receipt)") }}</label>
                        <textarea wire:model="notes" rows="2" placeholder="{{ __("Special instructions, cashier remarks...") }}" class="w-full rounded-xl bg-slate-800 border-slate-700 text-xs text-white placeholder-slate-500"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                    <button type="button" wire:click="$set('showCheckoutModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400">Cancel</button>
                    <button type="button" wire:click="settleBill" class="px-6 py-2.5 rounded-xl text-xs font-black bg-lime-400 hover:bg-lime-500 text-slate-950 shadow-lg shadow-lime-500/20 active:scale-95 transition">
                        {{ __("Complete & Print Receipt") }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Customer Picker Modal (Settle Bill) -->
    @if ($showCustomerPickerModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs">
            <div class="bg-slate-900 rounded-3xl max-w-sm w-full p-5 space-y-3 border border-slate-800 max-h-[85vh] overflow-y-auto">
                <div class="flex justify-between items-center pb-2 border-b border-slate-800">
                    <h3 class="text-sm font-black text-white">{{ __('Select Customer') }}</h3>
                    <button type="button" wire:click="closeCustomerPicker" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
                </div>

                @if (! $showNewCustomerForm)
                    <input type="text" wire:model.live.debounce.250ms="customerSearchTerm" placeholder="{{ __('Search name or phone...') }}" class="w-full rounded-xl bg-slate-800 border-slate-700 text-xs text-white placeholder-slate-500">

                    <div class="max-h-64 overflow-y-auto space-y-1">
                        @forelse ($this->checkoutCustomerResults as $cust)
                            <button type="button" wire:click="selectCheckoutCustomer({{ $cust->id }})" class="w-full text-left px-3 py-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-xs">
                                <div class="font-bold text-white">{{ $cust->name }}</div>
                                <div class="text-[10px] text-slate-400">{{ $cust->phone ?: $cust->email ?: '—' }}</div>
                            </button>
                        @empty
                            <div class="text-center text-slate-500 text-xs py-4">{{ __('No customers found.') }}</div>
                        @endforelse
                    </div>

                    <button type="button" wire:click="openNewCheckoutCustomerForm" class="w-full py-2 rounded-xl bg-lime-400 hover:bg-lime-500 text-slate-950 font-black text-xs">+ {{ __('Add Customer') }}</button>
                @else
                    <div class="space-y-2">
                        <input type="text" wire:model="newCustomerQuickName" placeholder="{{ __('Name') }}" class="w-full rounded-xl bg-slate-800 border-slate-700 text-xs text-white placeholder-slate-500">
                        @error('newCustomerQuickName') <p class="text-rose-400 text-[10px]">{{ $message }}</p> @enderror
                        <input type="text" wire:model="newCustomerQuickPhone" placeholder="{{ __('Phone') }}" class="w-full rounded-xl bg-slate-800 border-slate-700 text-xs text-white placeholder-slate-500">
                        <input type="email" wire:model="newCustomerQuickEmail" placeholder="{{ __('Email') }}" class="w-full rounded-xl bg-slate-800 border-slate-700 text-xs text-white placeholder-slate-500">
                        @error('newCustomerQuickEmail') <p class="text-rose-400 text-[10px]">{{ $message }}</p> @enderror
                        <div class="flex gap-2 pt-1">
                            <button type="button" wire:click="$set('showNewCustomerForm', false)" class="flex-1 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold">{{ __('Back') }}</button>
                            <button type="button" wire:click="createCheckoutCustomer" class="flex-1 py-2 rounded-xl bg-lime-400 hover:bg-lime-500 text-slate-950 font-black text-xs">{{ __('Create & Select') }}</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Post-Settlement Dispatch Modal -->
    @if ($showSettledDispatchModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs">
            <div class="bg-slate-900 rounded-3xl max-w-sm w-full p-6 space-y-4 border border-lime-500/40 shadow-2xl text-center">
                <div class="w-14 h-14 rounded-full bg-lime-500/20 text-lime-400 text-2xl font-black flex items-center justify-center mx-auto ring-8 ring-lime-500/10">✅</div>
                <div>
                    <h3 class="text-base font-black text-white">{{ __('Bill Settled!') }}</h3>
                    <p class="text-xs text-slate-400 mt-1">{{ __('Invoice') }} #{{ $lastSettledSaleNumber }}</p>
                </div>

                <a href="{{ $lastSettledSaleId ? route('tenant.sales.pdf', $lastSettledSaleId) : '#' }}" target="_blank" class="block w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold">🖨️ {{ __('Print / PDF Receipt') }}</a>

                <div class="grid grid-cols-2 gap-2">
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $lastSettledCustomerPhone ?? '') }}" target="_blank" @class(['py-2 rounded-xl text-white text-xs font-bold' => true, 'bg-emerald-600 hover:bg-emerald-700' => $lastSettledCustomerPhone, 'bg-slate-800 opacity-50 pointer-events-none' => ! $lastSettledCustomerPhone])>💬 {{ __('WhatsApp') }}</a>

                    <div x-data="{ open: false, email: @js($lastSettledCustomerEmail ?? '') }" class="relative">
                        <button type="button" @click="open = !open" class="w-full py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold">✉️ {{ __('Email') }}</button>
                        <div x-show="open" x-on:click.outside="open = false" x-cloak class="absolute left-0 right-0 z-10 mt-1 bg-slate-800 rounded-xl p-2 shadow-xl space-y-1.5">
                            <input type="email" x-model="email" placeholder="{{ __('Email address') }}" class="w-full rounded-lg bg-slate-900 border-slate-700 text-[11px] text-white">
                            <button type="button" x-on:click="$wire.dispatchSettledInvoiceEmail(email); open = false" class="w-full py-1.5 rounded-lg bg-lime-400 hover:bg-lime-500 text-slate-950 text-[10px] font-black">{{ __('Send') }}</button>
                        </div>
                    </div>
                </div>

                @if ($this->settledDispatchChannels->isNotEmpty())
                    <div class="flex flex-wrap justify-center gap-2 pt-1">
                        @foreach ($this->settledDispatchChannels as $dc)
                            <button type="button" wire:click="dispatchSettledInvoiceCustomChannel({{ $dc->id }})" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-[10px] font-bold flex items-center gap-1.5">
                                @if ($dc->isIconUrl())
                                    <img src="{{ $dc->iconDisplay() }}" alt="" class="w-3.5 h-3.5 rounded object-cover">
                                @else
                                    <span class="leading-none">{{ $dc->iconDisplay() }}</span>
                                @endif
                                {{ $dc->name }}
                            </button>
                        @endforeach
                    </div>
                @endif

                <button type="button" wire:click="closeSettledDispatchModal" class="w-full py-2 rounded-xl text-slate-400 hover:text-white text-xs font-bold">{{ __('Done') }}</button>
            </div>
        </div>
    @endif

    <!-- KOT Dispatched Success Modal -->
    @if ($showKotSuccessModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-xs">
            <div class="bg-slate-900 rounded-3xl max-w-md w-full p-6 space-y-5 border border-lime-500/40 shadow-2xl text-center">
                
                <div class="w-16 h-16 rounded-full bg-lime-500/20 text-[#a3e635] text-3xl font-black flex items-center justify-center mx-auto ring-8 ring-lime-500/10">
                    🍳
                </div>

                <div>
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-black bg-lime-400 text-slate-950 mb-2 shadow-xs">
                        {{ $lastDispatchedKotNumber }} {{ __("DISPATCHED") }}
                    </span>
                    <h3 class="text-xl font-black text-white">{{ __("Order Sent to Kitchen!") }}</h3>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ __("Ticket is now active in the Kitchen Display System (KDS) queue.") }}
                    </p>
                </div>

                <!-- Summary Details -->
                <div class="bg-slate-800/80 rounded-2xl p-4 border border-slate-700/60 text-left space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-400">{{ __("Destination:") }}</span>
                        <span class="font-bold text-white">{{ $lastDispatchedTableName }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">{{ __("Food Items:") }}</span>
                        <span class="font-bold text-[#a3e635]">{{ $lastDispatchedItemCount }} {{ __("items") }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">{{ __("Ticket Status:") }}</span>
                        <span class="font-black text-amber-400 uppercase">⏳ {{ __("Pending / In Cooking") }}</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-2.5">
                    @if ($lastDispatchedKotPrintUrl)
                        <a href="{{ $lastDispatchedKotPrintUrl }}"
                           target="_blank"
                           class="w-full py-3.5 rounded-2xl bg-[#a3e635] hover:bg-lime-400 text-slate-950 font-black text-sm transition shadow-lg shadow-lime-500/20 flex items-center justify-center gap-2">
                            <span>🖨️ {{ __("Print Kitchen Ticket (KOT)") }}</span>
                        </a>
                    @endif

                    <div class="grid grid-cols-2 gap-2">
                        <a wire:navigate.hover href="{{ route('tenant.restaurant.kds') }}"
                           class="py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition text-center flex items-center justify-center gap-1.5">
                            <span>🍳 {{ __("Open KDS") }}</span>
                        </a>

                        <button type="button"
                                wire:click="closeKotModalAndResetOrder"
                                class="py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs transition text-center">
                            + Start Next {{ __("Order") }}
                        </button>
                    </div>

                    <button type="button"
                            wire:click="closeKotModalKeepOrder"
                            class="text-xs text-slate-400 hover:text-white font-bold py-1">
                        {{ __("Keep Order On Screen") }}
                    </button>
                </div>

            </div>
        </div>
    @endif

    <!-- Cash Register Shift Gating Modal -->
    @include('livewire.tenant.sales.partials.register-gating-modal')

</div>
