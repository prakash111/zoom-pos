<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $catalog->title }} — {{ $company?->name ?? 'Digital Catalog' }}</title>
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
    <link rel="stylesheet" href="{{ secure_asset('css/app.css') }}" onerror="this.onerror=null;this.href='{{ asset('css/app.css') }}'">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen antialiased flex flex-col justify-between"
      x-data="catalogCart({
          storeName: {{ json_encode($company?->name ?? 'Store') }},
          storePhone: {{ json_encode(data_get($catalog->meta, 'whatsapp_number') ?: ($company?->phone ?? '')) }},
          currency: {{ json_encode($company?->currency ?? 'USD') }},
          catalogTitle: {{ json_encode($catalog->title) }}
      })">

    <!-- Top Store Header -->
    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-xs px-4 sm:px-6 py-3.5">
        <div class="max-w-6xl mx-auto flex items-center justify-between gap-4">
            
            <!-- Store Branding -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-black text-base flex items-center justify-center shadow-md">
                    {{ substr($company?->name ?? 'S', 0, 1) }}
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="font-extrabold text-sm sm:text-base text-slate-900 leading-tight">{{ $company?->name ?? 'Online Catalog' }}</h1>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-700">
                            Verified Store
                        </span>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2">
                        @if ($company?->city)
                            <span>📍 {{ $company->city }}{{ $company->state ? ', ' . $company->state : '' }}</span>
                        @endif
                        @if ($company?->phone)
                            <span>📞 {{ $company->phone }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Cart Trigger Button -->
            <div class="flex items-center gap-3">
                <button type="button"
                        x-on:click="cartOpen = true"
                        class="relative px-4 py-2.5 rounded-2xl bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 hover:from-blue-500 hover:via-indigo-500 hover:to-blue-600 text-white font-extrabold text-xs sm:text-sm flex items-center gap-2 shadow-md shadow-indigo-500/20 active:scale-[0.97] transition duration-150 ease-out cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                    <span>{{ __("Cart") }}</span>
                    <span x-show="totalItemCount > 0"
                          x-text="totalItemCount"
                          class="px-2 py-0.5 rounded-full bg-white text-blue-600 font-black text-xs shadow-xs">
                    </span>
                    <span x-show="totalAmount > 0" class="hidden sm:inline font-mono border-l border-white/30 pl-2">
                        $<span x-text="totalAmount.toFixed(2)"></span>
                    </span>
                </button>
            </div>

        </div>
    </header>

    <!-- Main Content Container -->
    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8 flex-1 w-full space-y-8">
        
        <!-- Hero / Catalog Banner -->
        <div class="bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 rounded-3xl p-6 sm:p-10 text-white shadow-xl relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
            
            <div class="max-w-2xl space-y-3 relative z-10">
                <span class="px-3 py-1 rounded-full text-xs font-black bg-white/20 backdrop-blur-xs text-white uppercase tracking-wider">
                    Digital Store Catalog
                </span>
                <h2 class="text-2xl sm:text-4xl font-black tracking-tight leading-tight">
                    {{ $catalog->title }}
                </h2>
                <p class="text-xs sm:text-sm text-blue-100 font-medium">
                    {{ $catalog->description ?: 'Select your items below, add them to your cart, and place your order directly via WhatsApp in 1-click.' }}
                </p>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="relative max-w-md">
            <input type="text"
                   x-model="search"
                   placeholder="Search products in catalog..."
                   class="w-full pl-11 pr-4 py-3 bg-white rounded-2xl border-none shadow-[0_2px_15px_rgb(0,0,0,0.04)] text-xs sm:text-sm font-medium focus:ring-2 focus:ring-blue-500 text-slate-800 placeholder-slate-400">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
            @forelse ($products as $product)
                <div x-show="matchesSearch({{ json_encode($product->name) }}, {{ json_encode($product->code ?? '') }})"
                     class="bg-white rounded-3xl p-4 sm:p-5 shadow-sm hover:shadow-xl border border-slate-200/60 flex flex-col items-center text-center justify-between transition-all group duration-200">
                    
                    <div class="w-full aspect-square rounded-2xl bg-slate-50 overflow-hidden mb-3 flex items-center justify-center relative">
                        @if ($product->image_url)
                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @else
                            <x-pos-product-icon :name="$product->name" size="lg" />
                        @endif
                    </div>

                    <div class="w-full space-y-1">
                        <div class="font-extrabold text-sm sm:text-base text-slate-800 truncate" title="{{ $product->name }}">
                            {{ $product->name }}
                        </div>
                        @if ($product->code)
                            <div class="text-[10px] font-mono text-slate-400">{{ $product->code }}</div>
                        @endif
                        @if ($product->description)
                            <p class="text-[11px] text-slate-500 line-clamp-2 text-left leading-relaxed mt-1" title="{{ $product->description }}">
                                {{ $product->description }}
                            </p>
                        @endif
                        <div class="text-base sm:text-lg font-black text-blue-600 pt-1">
                            ${{ number_format($product->sale_price, 2) }}
                        </div>
                    </div>

                    <!-- Add to Cart / Quantity Stepper Button -->
                    <div class="w-full pt-4">
                        <template x-if="!getItem({{ $product->id }})">
                            <button type="button"
                                    x-on:click="addToCart({{ $product->id }}, {{ json_encode($product->name) }}, {{ (float)$product->sale_price }})"
                                    class="w-full py-2.5 px-3 rounded-2xl bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white font-extrabold text-xs transition-all duration-150 ease-out active:scale-[0.97] flex items-center justify-center gap-1.5 shadow-xs cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                <span>{{ __("Add to Cart") }}</span>
                            </button>
                        </template>

                        <template x-if="getItem({{ $product->id }})">
                            <div class="flex items-center justify-between bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-2xl p-1 shadow-md">
                                <button type="button"
                                        x-on:click="decreaseQty({{ $product->id }})"
                                        class="w-7 h-7 rounded-xl bg-white/20 hover:bg-white/30 text-white flex items-center justify-center font-black text-sm active:scale-[0.9] transition duration-150 cursor-pointer">
                                    -
                                </button>
                                <span class="font-black text-xs px-2" x-text="getItem({{ $product->id }}).quantity"></span>
                                <button type="button"
                                        x-on:click="increaseQty({{ $product->id }})"
                                        class="w-7 h-7 rounded-xl bg-white/20 hover:bg-white/30 text-white flex items-center justify-center font-black text-sm active:scale-[0.9] transition duration-150 cursor-pointer">
                                    +
                                </button>
                            </div>
                        </template>
                    </div>

                </div>
            @empty
                <div class="col-span-full py-16 text-center text-slate-400">
                    No products available in this catalog.
                </div>
            @endforelse
        </div>

    </main>

    <!-- Slide-over Cart Drawer -->
    <div x-show="cartOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-hidden"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-xs" x-on:click="cartOpen = false"></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div class="w-screen max-w-md bg-white shadow-2xl p-6 flex flex-col justify-between"
                 x-transition:enter="transform transition ease-in-out duration-300"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in-out duration-300"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full">
                
                <!-- Drawer Header -->
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                        </div>
                        <h3 class="font-extrabold text-base text-slate-900">{{ __("Your Order Cart") }}</h3>
                    </div>
                    <button type="button" x-on:click="cartOpen = false" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:text-slate-800 flex items-center justify-center font-bold text-base transition">
                        &times;
                    </button>
                </div>

                <!-- Cart Items List -->
                <div class="flex-1 overflow-y-auto py-4 space-y-3">
                    <template x-for="item in cart" :key="item.id">
                        <div class="flex items-center justify-between bg-slate-50 p-3 rounded-2xl border border-slate-100">
                            <div>
                                <div class="font-bold text-xs sm:text-sm text-slate-800" x-text="item.name"></div>
                                <div class="text-xs text-blue-600 font-extrabold mt-0.5">
                                    $<span x-text="item.price.toFixed(2)"></span> &bull; Total: $<span x-text="(item.price * item.quantity).toFixed(2)"></span>
                                </div>
                            </div>

                            <!-- Steppers -->
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        x-on:click="decreaseQty(item.id)"
                                        class="w-6 h-6 rounded-full bg-white text-slate-600 shadow-xs flex items-center justify-center font-black text-xs hover:bg-rose-50 hover:text-rose-600 transition">
                                    -
                                </button>
                                <span class="font-black text-xs min-w-4 text-center" x-text="item.quantity"></span>
                                <button type="button"
                                        x-on:click="increaseQty(item.id)"
                                        class="w-6 h-6 rounded-full bg-white text-slate-600 shadow-xs flex items-center justify-center font-black text-xs hover:bg-emerald-50 hover:text-emerald-600 transition">
                                    +
                                </button>
                            </div>
                        </div>
                    </template>

                    <template x-if="cart.length === 0">
                        <div class="py-16 text-center text-slate-400 space-y-2">
                            <div class="text-3xl">🛒</div>
                            <div class="text-xs font-semibold">{{ __("Your cart is currently empty.") }}</div>
                            <div class="text-[11px] text-slate-400">{{ __("Click on any product to add it.") }}</div>
                        </div>
                    </template>
                </div>

                <!-- Checkout Details & WhatsApp Order Button -->
                <div class="pt-4 border-t border-slate-100 space-y-4">
                    
                    <!-- Customer Inputs -->
                    <template x-if="cart.length > 0">
                        <div class="space-y-2.5">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">{{ __("Your Name *") }}</label>
                                <input type="text" x-model="customerName" placeholder="e.g. Sarah Connor" class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">{{ __("Your Phone / WhatsApp Number") }}</label>
                                <input type="text" x-model="customerPhone" placeholder="e.g. +1 555-0199" class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">{{ __("Delivery Address / Order Notes") }}</label>
                                <input type="text" x-model="customerNotes" placeholder="Delivery address or special requests..." class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500">
                            </div>
                        </div>
                    </template>

                    <!-- Total Breakdown -->
                    <div class="flex items-baseline justify-between pt-2 border-t border-slate-100">
                        <span class="text-sm font-extrabold text-slate-700">{{ __("Total Order Amount:") }}</span>
                        <span class="text-2xl font-black text-slate-900">$<span x-text="totalAmount.toFixed(2)"></span></span>
                    </div>

                    <!-- WhatsApp Order CTA -->
                    <button type="button"
                            x-on:click="orderViaWhatsApp()"
                            :disabled="cart.length === 0"
                            class="w-full py-3.5 rounded-2xl bg-[#25D366] hover:bg-[#1EBE5D] disabled:opacity-40 disabled:cursor-not-allowed text-white font-extrabold text-xs sm:text-sm tracking-wide shadow-lg shadow-emerald-500/25 active:scale-95 transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.158.57 4.184 1.564 5.941l-1.657 6.059 6.223-1.632c1.705.932 3.654 1.465 5.73 1.465 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>
                        <span>{{ __("Order via WhatsApp") }}</span>
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- Store Footer matching branding -->
    <footer class="bg-white border-t border-slate-200/80 mt-16 py-8 px-4 sm:px-6">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
            <div class="flex items-center gap-2">
                <span class="font-extrabold text-slate-800">{{ $company?->name }}</span>
                @if ($company?->address)
                    <span>&bull; {{ $company->address }}</span>
                @endif
            </div>

            <div class="flex items-center gap-4">
                @if ($company?->phone)
                    <a href="tel:{{ $company->phone }}" class="hover:text-blue-600 font-bold">{{ __("Call:") }} {{ $company->phone }}</a>
                @endif
                @if ($company?->email)
                    <a href="mailto:{{ $company->email }}" class="hover:text-blue-600 font-bold">{{ __("Email Us") }}</a>
                @endif
            </div>
        </div>
    </footer>

    <!-- Alpine Cart Data Store Script -->
    <script>
        function catalogCart(config) {
            return {
                storeName: config.storeName || 'Store',
                storePhone: config.storePhone || '',
                currency: config.currency || 'USD',
                catalogTitle: config.catalogTitle || 'Digital Catalog',
                search: '',
                cartOpen: false,
                cart: [],
                customerName: '',
                customerPhone: '',
                customerNotes: '',

                get totalItemCount() {
                    return this.cart.reduce((acc, item) => acc + item.quantity, 0);
                },

                get totalAmount() {
                    return this.cart.reduce((acc, item) => acc + (item.price * item.quantity), 0);
                },

                matchesSearch(name, code) {
                    if (!this.search.trim()) return true;
                    const q = this.search.toLowerCase();
                    return (name && name.toLowerCase().includes(q)) || (code && code.toLowerCase().includes(q));
                },

                getItem(productId) {
                    return this.cart.find(i => i.id === productId);
                },

                addToCart(id, name, price) {
                    const existing = this.getItem(id);
                    if (existing) {
                        existing.quantity++;
                    } else {
                        this.cart.push({ id: id, name: name, price: price, quantity: 1 });
                    }
                },

                increaseQty(id) {
                    const item = this.getItem(id);
                    if (item) {
                        item.quantity++;
                    }
                },

                decreaseQty(id) {
                    const item = this.getItem(id);
                    if (item) {
                        item.quantity--;
                        if (item.quantity <= 0) {
                            this.cart = this.cart.filter(i => i.id !== id);
                        }
                    }
                },

                async orderViaWhatsApp() {
                    if (this.cart.length === 0) return;

                    // Synchronize order to POS as pending
                    try {
                        await fetch('/c/{{ $catalog->id }}/order', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                customer_name: this.customerName,
                                customer_phone: this.customerPhone,
                                customer_notes: this.customerNotes,
                                items: this.cart
                            })
                        });
                    } catch (e) {
                        console.warn('POS order recording notice:', e);
                    }

                    let itemsSummary = '';
                    this.cart.forEach(item => {
                        itemsSummary += `• ${item.name} (x${item.quantity}) - $${(item.price * item.quantity).toFixed(2)}\n`;
                    });

                    const clientName = this.customerName.trim() || 'Customer';
                    const clientPhone = this.customerPhone.trim() ? `\n*Phone:* ${this.customerPhone.trim()}` : '';
                    const clientNotes = this.customerNotes.trim() ? `\n*Notes / Address:* ${this.customerNotes.trim()}` : '';

                    const message = `🛒 *NEW ORDER FROM DIGITAL CATALOG*\n`
                        + `*Store:* ${this.storeName}\n`
                        + `*Catalog:* ${this.catalogTitle}\n`
                        + `*Customer:* ${clientName}${clientPhone}${clientNotes}\n\n`
                        + `*Order Items:*\n${itemsSummary}\n`
                        + `----------------------------\n`
                        + `*Total Order Amount:* $${this.totalAmount.toFixed(2)} ${this.currency}\n`
                        + `----------------------------\n`
                        + `Please confirm my order and send payment / delivery details. Thank you! ✨`;

                    const sanitizedPhone = this.storePhone.replace(/[^0-9]/g, '');
                    let waUrl = '';
                    if (sanitizedPhone) {
                        waUrl = `https://wa.me/${sanitizedPhone}?text=${encodeURIComponent(message)}`;
                    } else {
                        waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(message)}`;
                    }

                    window.open(waUrl, '_blank');
                }
            };
        }
    </script>
</body>
</html>
