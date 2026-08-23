<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <title>{{ $table->table_number }} Digital Menu — {{ $company->name }}</title>
    @if ($company->favicon)
        <link rel="icon" href="{{ $company->favicon }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen pb-28 antialiased selection:bg-lime-500 selection:text-black font-sans"
      x-data="{
          activeCategory: 'all',
          searchQuery: '',
          cartOpen: false,
          modalOpen: false,
          activeItem: null,
          selectedVariant: null,
          selectedModifiers: [],
          itemNote: '',
          guestName: '',
          guestCount: {{ $table->guest_count ?: 1 }},
          specialInstructions: '',
          cart: [],

          openItemModal(product) {
              this.activeItem = product;
              this.selectedVariant = product.variants && product.variants.length ? product.variants[0] : null;
              this.selectedModifiers = [];
              this.itemNote = '';
              this.modalOpen = true;
          },

          toggleModifier(mod) {
              const idx = this.selectedModifiers.findIndex(m => m.name === mod.name);
              if (idx > -1) {
                  this.selectedModifiers.splice(idx, 1);
              } else {
                  this.selectedModifiers.push(mod);
              }
          },

          getCurrentModalPrice() {
              if (!this.activeItem) return 0;
              let base = this.selectedVariant ? parseFloat(this.selectedVariant.price) : parseFloat(this.activeItem.sale_price);
              let mods = this.selectedModifiers.reduce((acc, m) => acc + parseFloat(m.price || 0), 0);
              return base + mods;
          },

          addToCartFromModal() {
              const price = this.getCurrentModalPrice();
              const cartItem = {
                  product_id: this.activeItem.id,
                  name: this.activeItem.name,
                  price: price,
                  quantity: 1,
                  variant: this.selectedVariant ? this.selectedVariant.name : null,
                  modifiers: JSON.parse(JSON.stringify(this.selectedModifiers)),
                  note: this.itemNote,
                  seat: 1
              };

              this.cart.push(cartItem);
              this.modalOpen = false;
          },

          addItemDirect(product) {
              if ((product.variants && product.variants.length > 0) || (product.modifiers && product.modifiers.length > 0)) {
                  this.openItemModal(product);
                  return;
              }

              const existing = this.cart.find(i => i.product_id === product.id && !i.variant && (!i.modifiers || i.modifiers.length === 0));
              if (existing) {
                  existing.quantity++;
              } else {
                  this.cart.push({
                      product_id: product.id,
                      name: product.name,
                      price: parseFloat(product.sale_price),
                      quantity: 1,
                      variant: null,
                      modifiers: [],
                      note: '',
                      seat: 1
                  });
              }
          },

          removeFromCart(index) {
              this.cart.splice(index, 1);
              if (this.cart.length === 0) this.cartOpen = false;
          },

          getCartTotal() {
              return this.cart.reduce((acc, item) => acc + (item.price * item.quantity), 0);
          },

          getCartCount() {
              return this.cart.reduce((acc, item) => acc + item.quantity, 0);
          }
      }">

    <!-- Sticky Table & Restaurant Header -->
    <header class="sticky top-0 z-30 bg-slate-900/95 backdrop-blur-md border-b border-slate-800 px-4 py-3 shadow-lg">
        <div class="max-w-3xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if ($company->logo)
                    <img src="{{ $company->logo }}" alt="{{ $company->name }}" class="w-10 h-10 object-contain rounded-2xl bg-white/10 p-1">
                @else
                    <div class="w-10 h-10 rounded-2xl bg-lime-500 text-slate-950 font-black text-sm flex items-center justify-center shadow-md">
                        🍴
                    </div>
                @endif
                <div>
                    <h1 class="font-extrabold text-sm sm:text-base text-white tracking-tight leading-tight">
                        {{ $company->name }}
                    </h1>
                    <div class="flex items-center gap-1.5 text-xs text-lime-400 font-bold mt-0.5">
                        <span>🪑 {{ $table->table_number }}</span>
                        @if ($table->floor)
                            <span class="text-slate-500">&bull;</span>
                            <span class="text-slate-400">{{ $table->floor->name }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- View Order Button -->
            <button type="button"
                    x-show="cart.length > 0"
                    x-on:click="cartOpen = true"
                    class="px-3.5 py-2 rounded-2xl bg-lime-400 hover:bg-lime-500 text-slate-950 font-black text-xs shadow-lg shadow-lime-500/20 active:scale-95 transition-all flex items-center gap-1.5">
                <span>🛒</span>
                <span x-text="getCartCount() + ' items'"></span>
            </button>
        </div>
    </header>

    <!-- Main Menu Area -->
    <main class="max-w-3xl mx-auto px-4 pt-4 space-y-4">
        
        <!-- Order Success Notice -->
        @if (session('order_success'))
            <div class="p-4 rounded-3xl bg-lime-950/80 border border-lime-500/40 text-lime-300 font-bold text-xs sm:text-sm shadow-xl flex items-center gap-3">
                <span class="text-2xl">🎉</span>
                <div>{{ session('order_success') }}</div>
            </div>
        @endif

        <!-- Live Product Search Bar -->
        <div class="relative w-full">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
            <input type="text"
                   x-model="searchQuery"
                   placeholder="Search dishes, drinks, appetizers, desserts…"
                   class="w-full pl-10 pr-10 py-3 rounded-2xl bg-slate-900 border border-slate-800 text-xs sm:text-sm text-white placeholder-slate-500 focus:ring-2 focus:ring-lime-400 focus:border-lime-400 transition shadow-inner font-medium">
            <button type="button"
                    x-show="searchQuery.length > 0"
                    x-on:click="searchQuery = ''"
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white text-xs font-bold cursor-pointer">
                ✕
            </button>
        </div>

        <!-- Category Filter Pills -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1">
            <button type="button"
                    x-on:click="activeCategory = 'all'"
                    :class="activeCategory === 'all' ? 'bg-lime-400 text-slate-950 font-black shadow-md' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800 font-bold'"
                    class="px-4 py-2 rounded-2xl text-xs whitespace-nowrap transition-all cursor-pointer">
                All Items ({{ count($products) }})
            </button>

            @foreach ($categories as $cat)
                @php
                    $catCount = $products->where('category_id', $cat->id)->count();
                @endphp
                <button type="button"
                        x-on:click="activeCategory = '{{ $cat->id }}'"
                        :class="activeCategory === '{{ $cat->id }}' ? 'bg-lime-400 text-slate-950 font-black shadow-md' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800 font-bold'"
                        class="px-4 py-2 rounded-2xl text-xs whitespace-nowrap transition-all cursor-pointer">
                    {{ $cat->name }} ({{ $catCount }})
                </button>
            @endforeach
        </div>

        <!-- Food Items Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3.5">
            @forelse ($products as $p)
                @php
                    $searchTerms = strtolower($p->name . ' ' . ($p->description ?? '') . ' ' . ($p->sku ?? ''));
                @endphp
                <div x-show="(activeCategory === 'all' || activeCategory === '{{ $p->category_id }}') && (!searchQuery || {{ json_encode($searchTerms) }}.includes(searchQuery.toLowerCase().trim()))"
                     class="bg-slate-900/90 rounded-3xl overflow-hidden border border-slate-800/80 flex flex-col justify-between shadow-md hover:border-slate-700 transition">
                    
                    <!-- Food Photo Thumbnail -->
                    <div class="relative h-32 w-full bg-slate-800 overflow-hidden cursor-pointer group"
                         x-on:click="openItemModal({{ json_encode($p) }})">
                        @if ($p->image_url)
                            <img src="{{ $p->image_url }}" alt="{{ $p->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-slate-600 text-2xl font-black">
                                🍽️
                            </div>
                        @endif

                        @if (!empty($p->variants) && count($p->variants) > 0)
                            <span class="absolute bottom-2 left-2 px-2 py-0.5 rounded-full text-[9px] font-black bg-black/75 text-white backdrop-blur-xs">
                                Options
                            </span>
                        @endif
                    </div>

                    <!-- Title & Price -->
                    <div class="p-3.5 space-y-2 flex-1 flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold text-xs sm:text-sm text-white leading-tight line-clamp-1">
                                {{ $p->name }}
                            </h3>
                            <div class="text-xs font-black text-lime-400 mt-1">
                                ${{ number_format($p->sale_price, 2) }}
                            </div>
                        </div>

                        <!-- Add Button -->
                        <button type="button"
                                x-on:click="addItemDirect({{ json_encode($p) }})"
                                class="w-full py-2 rounded-2xl bg-slate-800 hover:bg-lime-400 hover:text-slate-950 text-slate-200 font-extrabold text-xs transition active:scale-95 flex items-center justify-center gap-1 cursor-pointer">
                            <span>+ Add</span>
                        </button>
                    </div>

                </div>
            @empty
                <div class="col-span-full py-12 text-center text-slate-500 text-xs">
                    No food items available at this time.
                </div>
            @endforelse
        </div>

    </main>

    <!-- Sticky Bottom Cart Bar -->
    <div x-show="cart.length > 0"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         class="fixed bottom-0 inset-x-0 z-40 p-4 bg-slate-950/90 backdrop-blur-md border-t border-slate-800">
        <div class="max-w-3xl mx-auto flex items-center justify-between gap-3">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Order Subtotal</div>
                <div class="text-lg font-black text-white" x-text="'$' + getCartTotal().toFixed(2)"></div>
            </div>

            <button type="button"
                    x-on:click="cartOpen = true"
                    class="px-6 py-3 rounded-2xl bg-lime-400 hover:bg-lime-500 text-slate-950 font-black text-xs sm:text-sm shadow-xl shadow-lime-500/25 active:scale-95 transition-all flex items-center gap-2">
                <span>Review & Place Order</span>
                <span class="w-5 h-5 rounded-full bg-slate-950 text-lime-400 text-xs flex items-center justify-center font-black" x-text="getCartCount()"></span>
            </button>
        </div>
    </div>

    <!-- Item Modifier Modal -->
    <div x-show="modalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/75 backdrop-blur-xs">
        <div class="bg-slate-900 rounded-t-[2.5rem] sm:rounded-[2.5rem] max-w-md w-full p-6 space-y-4 border border-slate-800 max-h-[90vh] overflow-y-auto"
             x-on:click.outside="modalOpen = false">
            
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-base sm:text-lg font-black text-white" x-text="activeItem ? activeItem.name : ''"></h2>
                    <div class="text-xs font-black text-lime-400" x-text="'$' + getCurrentModalPrice().toFixed(2)"></div>
                </div>
                <button type="button" x-on:click="modalOpen = false" class="text-slate-400 hover:text-white text-2xl font-bold">&times;</button>
            </div>

            <!-- Variants (Size / Style) -->
            <template x-if="activeItem && activeItem.variants && activeItem.variants.length > 0">
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Choose Option</label>
                    <div class="grid grid-cols-1 gap-2">
                        <template x-for="(v, vIdx) in activeItem.variants" :key="vIdx">
                            <button type="button"
                                    x-on:click="selectedVariant = v"
                                    :class="selectedVariant && selectedVariant.name === v.name ? 'border-lime-500 bg-lime-500/15 text-white' : 'border-slate-800 bg-slate-800/60 text-slate-300'"
                                    class="w-full p-3 rounded-2xl border text-xs font-bold flex justify-between items-center transition">
                                <span x-text="v.name"></span>
                                <span class="text-lime-400 font-extrabold" x-text="'$' + parseFloat(v.price).toFixed(2)"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Modifiers & Add-ons -->
            <template x-if="activeItem && activeItem.modifiers && activeItem.modifiers.length > 0">
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Add-ons & Extras</label>
                    <div class="space-y-1.5">
                        <template x-for="(m, mIdx) in activeItem.modifiers" :key="mIdx">
                            <label class="p-3 rounded-2xl border border-slate-800 bg-slate-800/40 text-xs font-bold flex items-center justify-between cursor-pointer hover:border-slate-700">
                                <div class="flex items-center gap-2.5">
                                    <input type="checkbox"
                                           :checked="selectedModifiers.some(mod => mod.name === m.name)"
                                           x-on:change="toggleModifier(m)"
                                           class="w-4 h-4 rounded text-lime-500 focus:ring-lime-500 bg-slate-700 border-slate-600">
                                    <span class="text-slate-200" x-text="m.name"></span>
                                </div>
                                <span class="text-lime-400 font-extrabold" x-text="'+$' + parseFloat(m.price).toFixed(2)"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Special Note -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Kitchen Note (Optional)</label>
                <input type="text"
                       x-model="itemNote"
                       placeholder="e.g. No onion, dressing on the side"
                       class="w-full rounded-2xl bg-slate-800 border-slate-700 text-xs focus:ring-lime-500 text-white">
            </div>

            <!-- Add Button -->
            <button type="button"
                    x-on:click="addToCartFromModal()"
                    class="w-full py-3.5 rounded-2xl bg-lime-400 hover:bg-lime-500 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-lime-500/25 active:scale-95 transition">
                Add to Table Order &bull; <span x-text="'$' + getCurrentModalPrice().toFixed(2)"></span>
            </button>

        </div>
    </div>

    <!-- Slide-over Cart Drawer -->
    <div x-show="cartOpen"
         x-cloak
         class="fixed inset-0 z-50 flex justify-end bg-black/80 backdrop-blur-xs">
        <div class="bg-slate-900 w-full max-w-md h-full flex flex-col justify-between p-6 border-l border-slate-800"
             x-on:click.outside="cartOpen = false">
            
            <!-- Drawer Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div>
                    <h2 class="text-base font-black text-white">Table Order Review</h2>
                    <p class="text-xs text-lime-400 font-bold">🪑 {{ $table->table_number }}</p>
                </div>
                <button type="button" x-on:click="cartOpen = false" class="text-slate-400 hover:text-white text-2xl font-bold">&times;</button>
            </div>

            <!-- Cart Items List -->
            <div class="flex-1 overflow-y-auto py-4 space-y-3 pr-1">
                <template x-for="(item, idx) in cart" :key="idx">
                    <div class="bg-slate-800/70 rounded-2xl p-3.5 border border-slate-700/60 space-y-2">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-bold text-xs sm:text-sm text-white" x-text="item.name"></h4>
                                <template x-if="item.variant">
                                    <div class="text-[11px] text-slate-400" x-text="'Size: ' + item.variant"></div>
                                </template>
                                <template x-if="item.modifiers && item.modifiers.length > 0">
                                    <div class="text-[10px] text-lime-400" x-text="'+ ' + item.modifiers.map(m => m.name).join(', ')"></div>
                                </template>
                                <template x-if="item.note">
                                    <div class="text-[10px] text-amber-300" x-text="'Note: ' + item.note"></div>
                                </template>
                            </div>
                            <div class="text-xs font-black text-white" x-text="'$' + (item.price * item.quantity).toFixed(2)"></div>
                        </div>

                        <!-- Steppers & Remove -->
                        <div class="flex justify-between items-center pt-1 border-t border-slate-700/50">
                            <button type="button" x-on:click="removeFromCart(idx)" class="text-[11px] text-rose-400 hover:text-rose-300 font-bold">Remove</button>
                            <div class="flex items-center gap-2">
                                <button type="button" x-on:click="if (item.quantity > 1) item.quantity--; else removeFromCart(idx);" class="w-6 h-6 rounded-lg bg-slate-700 text-white font-bold flex items-center justify-center text-xs">-</button>
                                <span class="font-black text-xs text-white" x-text="item.quantity"></span>
                                <button type="button" x-on:click="item.quantity++" class="w-6 h-6 rounded-lg bg-slate-700 text-white font-bold flex items-center justify-center text-xs">+</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Form Submission for Table Order -->
            <form method="POST" action="{{ route('restaurant.table.order.place', ['token' => $table->qr_token]) }}" class="space-y-3 pt-3 border-t border-slate-800">
                @csrf
                
                <input type="hidden" name="guest_count" :value="guestCount">

                <!-- Hidden Items Array JSON -->
                <template x-for="(item, idx) in cart" :key="idx">
                    <div>
                        <input type="hidden" :name="'items['+idx+'][product_id]'" :value="item.product_id">
                        <input type="hidden" :name="'items['+idx+'][name]'" :value="item.name">
                        <input type="hidden" :name="'items['+idx+'][quantity]'" :value="item.quantity">
                        <input type="hidden" :name="'items['+idx+'][price]'" :value="item.price">
                        <input type="hidden" :name="'items['+idx+'][variant]'" :value="item.variant">
                        <input type="hidden" :name="'items['+idx+'][note]'" :value="item.note">
                        <template x-for="(mod, modIdx) in item.modifiers" :key="modIdx">
                            <input type="hidden" :name="'items['+idx+'][modifiers]['+modIdx+'][name]'" :value="mod.name">
                        </template>
                    </div>
                </template>

                <div>
                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Your Name (Optional)</label>
                    <input type="text" name="guest_name" x-model="guestName" placeholder="e.g. Alex" class="w-full rounded-xl bg-slate-800 border-slate-700 text-xs text-white focus:ring-lime-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Special Order Instructions</label>
                    <input type="text" name="special_instructions" x-model="specialInstructions" placeholder="e.g. Bring appetizer first" class="w-full rounded-xl bg-slate-800 border-slate-700 text-xs text-white focus:ring-lime-500">
                </div>

                <button type="submit"
                        :disabled="cart.length === 0"
                        class="w-full py-4 rounded-2xl bg-lime-400 hover:bg-lime-500 disabled:opacity-50 text-slate-950 font-black text-sm shadow-xl shadow-lime-500/25 active:scale-95 transition flex items-center justify-center gap-2">
                    <span>Send Order to Kitchen &bull;</span>
                    <span x-text="'$' + getCartTotal().toFixed(2)"></span>
                </button>
            </form>

        </div>
    </div>

</body>
</html>
