<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Zoom POS — Dual-Mode Standalone Client</title>
    <link rel="icon" type="image/png" href="/launcher.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        @layer base {
            body { @apply bg-slate-950 text-slate-100 antialiased select-none font-sans; }
            ::-webkit-scrollbar { width: 6px; height: 6px; }
            ::-webkit-scrollbar-track { background: #0f172a; }
            ::-webkit-scrollbar-thumb { background: #334155; border-radius: 9999px; }
        }
    </style>
</head>
<body class="h-screen w-screen overflow-hidden bg-slate-950 text-slate-100">
    <div x-data="posApp()" x-init="initApp()" class="w-screen h-screen flex flex-col bg-slate-950 text-slate-100 select-none overflow-hidden font-sans">
        
        <!-- 1. Top Navigation Bar -->
        <header class="h-14 bg-slate-900/90 border-b border-slate-800 px-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-blue-600 flex items-center justify-center font-black text-white text-sm shadow-md shadow-blue-500/30">
                    ⚡
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xs font-black tracking-wide text-white uppercase">Zoom POS</h1>
                        <span class="px-1.5 py-0.5 rounded bg-blue-500/20 text-blue-400 border border-blue-500/30 text-[10px] font-bold">Dual-Mode</span>
                    </div>
                    <p class="text-[10px] text-slate-400 font-mono" x-text="statusText"></p>
                </div>
            </div>

            <!-- Center Search Input -->
            <div class="flex-1 max-w-md mx-4">
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
                    <input type="text" 
                           x-model="searchQuery" 
                           @input="filterProducts()" 
                           placeholder="Scan barcode or search product name / SKU (Ctrl+K)..." 
                           class="w-full pl-8 pr-4 py-1.5 bg-slate-800/80 border border-slate-700/80 rounded-xl text-xs text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
                </div>
            </div>

            <!-- Right Status & Settings -->
            <div class="flex items-center gap-2">
                <span class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold"
                      :class="isOnline ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'">
                    <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400'"></span>
                    <span x-text="isOnline ? 'Online' : 'Offline'"></span>
                </span>

                <button type="button" 
                        @click="syncNow()" 
                        :disabled="isSyncing"
                        class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-[11px] font-bold transition cursor-pointer disabled:opacity-50">
                    <span :class="{'animate-spin inline-block': isSyncing}">🔄</span>
                    <span>Sync Queue (<span x-text="unsyncedCount">0</span>)</span>
                </button>

                <button type="button" @click="showSettings = true" class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition cursor-pointer" title="Settings">
                    ⚙️
                </button>
            </div>
        </header>

        <!-- 2. Main Studio Grid (Catalog on Left / Cart Ledger on Right) -->
        <div class="flex-1 flex overflow-hidden">
            
            <!-- LEFT: Categories & Products Catalog (65%) -->
            <main class="flex-1 flex flex-col border-r border-slate-800 bg-slate-950 p-4 gap-4 overflow-hidden">
                
                <!-- Category Pills -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 shrink-0 scrollbar-none">
                    <button type="button" 
                            @click="selectedCategory = 'all'; filterProducts()" 
                            :class="selectedCategory === 'all' ? 'bg-blue-600 text-white font-bold' : 'bg-slate-900 text-slate-400 border border-slate-800 hover:text-white'"
                            class="px-3.5 py-1.5 rounded-xl text-xs shrink-0 transition cursor-pointer">
                        All Products (<span x-text="products.length"></span>)
                    </button>
                    <template x-for="cat in categories" :key="cat.id">
                        <button type="button" 
                                @click="selectedCategory = cat.name; filterProducts()" 
                                :class="selectedCategory === cat.name ? 'bg-blue-600 text-white font-bold' : 'bg-slate-900 text-slate-400 border border-slate-800 hover:text-white'"
                                class="px-3.5 py-1.5 rounded-xl text-xs shrink-0 transition cursor-pointer"
                                x-text="cat.name">
                        </button>
                    </template>
                </div>

                <!-- Product Cards Grid -->
                <div class="flex-1 overflow-y-auto pr-1">
                    <!-- Empty State -->
                    <template x-if="filteredProducts.length === 0">
                        <div class="h-full flex flex-col items-center justify-center text-center p-8 space-y-3">
                            <span class="text-4xl">📦</span>
                            <div class="text-sm font-bold text-slate-300">No products found in local database</div>
                            <p class="text-xs text-slate-500 max-w-sm">Connect your cloud account in settings or trigger a sync to download catalog items.</p>
                            <button type="button" @click="syncNow()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-lg shadow-blue-500/30 cursor-pointer">
                                Pull Catalog from Cloud
                            </button>
                        </div>
                    </template>

                    <!-- Grid List -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                        <template x-for="product in filteredProducts" :key="product.id">
                            <div @click="addToCart(product)" 
                                 class="group bg-slate-900/80 hover:bg-slate-800/90 border border-slate-800/80 hover:border-blue-500/50 rounded-2xl p-3 flex flex-col justify-between cursor-pointer transition-all duration-150 hover:shadow-lg active:scale-[0.98]">
                                
                                <!-- Product Image -->
                                <div class="w-full h-24 rounded-xl bg-slate-950/60 overflow-hidden flex items-center justify-center mb-2 border border-slate-800/50">
                                    <template x-if="product.image_url">
                                        <img :src="product.image_url" class="w-full h-full object-cover group-hover:scale-105 transition duration-200" onerror="this.src='/launcher.png'">
                                    </template>
                                    <template x-if="!product.image_url">
                                        <span class="text-2xl text-slate-600">🛍️</span>
                                    </template>
                                </div>

                                <!-- Info -->
                                <div>
                                    <span class="text-[10px] font-mono text-slate-400 block truncate" x-text="product.barcode || product.item_code || product.sku || 'NO-CODE'"></span>
                                    <h3 class="text-xs font-bold text-slate-100 truncate mt-0.5" x-text="product.name"></h3>
                                </div>

                                <!-- Price & Stock Badge -->
                                <div class="flex items-center justify-between mt-3 pt-2 border-t border-slate-800/60">
                                    <span class="text-xs font-black font-mono text-emerald-400" x-text="'$' + Number(product.price).toFixed(2)"></span>
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-800 text-slate-400" x-text="'x' + (product.stock ?? 0)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </main>

            <!-- RIGHT: Active Order Ledger & Settlement (35%) -->
            <aside class="w-96 bg-slate-900 flex flex-col justify-between overflow-hidden shrink-0 border-l border-slate-800">
                
                <!-- Cart Header -->
                <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xs font-black uppercase text-white tracking-wider">Current Order</h2>
                            <span class="text-[11px] font-mono text-blue-400" x-text="'#' + orderNumber"></span>
                        </div>
                    </div>
                    <button type="button" @click="clearCart()" class="text-xs font-bold text-rose-400 hover:text-rose-300 cursor-pointer">
                        Clear Cart
                    </button>
                </div>

                <!-- Cart Items Scroll Area -->
                <div class="flex-1 overflow-y-auto p-4 space-y-2.5">
                    <template x-if="cart.length === 0">
                        <div class="h-full flex flex-col items-center justify-center text-center text-slate-500 space-y-2">
                            <span class="text-3xl">🛒</span>
                            <span class="text-xs font-bold">Your cart is currently empty</span>
                        </div>
                    </template>

                    <template x-for="(item, idx) in cart" :key="item.id">
                        <div class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800 flex items-center justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-bold text-white truncate" x-text="item.name"></div>
                                <div class="text-[11px] font-mono text-slate-400" x-text="'$' + item.price.toFixed(2) + ' each'"></div>
                            </div>

                            <!-- Quantity Stepper -->
                            <div class="flex items-center gap-1 bg-slate-900 border border-slate-800 rounded-lg p-0.5">
                                <button type="button" @click="decrementQty(idx)" class="w-6 h-6 rounded bg-slate-800 text-white text-xs font-black hover:bg-slate-700 cursor-pointer">-</button>
                                <span class="w-6 text-center text-xs font-bold font-mono" x-text="item.qty"></span>
                                <button type="button" @click="incrementQty(idx)" class="w-6 h-6 rounded bg-slate-800 text-white text-xs font-black hover:bg-slate-700 cursor-pointer">+</button>
                            </div>

                            <span class="text-xs font-black font-mono text-white text-right w-16" x-text="'$' + (item.price * item.qty).toFixed(2)"></span>
                        </div>
                    </template>
                </div>

                <!-- Cart Settlement Panel -->
                <div class="p-4 bg-slate-950/80 border-t border-slate-800 space-y-3">
                    <div class="space-y-1.5 text-xs font-medium text-slate-400">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span class="font-mono text-slate-200" x-text="'$' + subtotal.toFixed(2)">$0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Tax (Calculated)</span>
                            <span class="font-mono text-slate-200" x-text="'$' + tax.toFixed(2)">$0.00</span>
                        </div>
                        <div class="pt-2 border-t border-slate-800 flex justify-between items-baseline">
                            <span class="font-bold text-white uppercase text-xs">Total Payable</span>
                            <span class="text-xl font-black text-emerald-400 font-mono" x-text="'$' + total.toFixed(2)">$0.00</span>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="grid grid-cols-4 gap-1.5">
                        <button type="button" @click="paymentMethod = 'cash'" :class="paymentMethod === 'cash' ? 'bg-blue-600 text-white' : 'bg-slate-900 text-slate-300 border border-slate-800'" class="py-1.5 rounded-lg text-xs font-bold transition cursor-pointer">💵 Cash</button>
                        <button type="button" @click="paymentMethod = 'card'" :class="paymentMethod === 'card' ? 'bg-blue-600 text-white' : 'bg-slate-900 text-slate-300 border border-slate-800'" class="py-1.5 rounded-lg text-xs font-bold transition cursor-pointer">💳 Card</button>
                        <button type="button" @click="paymentMethod = 'upi'" :class="paymentMethod === 'upi' ? 'bg-blue-600 text-white' : 'bg-slate-900 text-slate-300 border border-slate-800'" class="py-1.5 rounded-lg text-xs font-bold transition cursor-pointer">⚡ UPI/QR</button>
                        <button type="button" @click="paymentMethod = 'credit'" :class="paymentMethod === 'credit' ? 'bg-blue-600 text-white' : 'bg-slate-900 text-slate-300 border border-slate-800'" class="py-1.5 rounded-lg text-xs font-bold transition cursor-pointer">📅 Credit</button>
                    </div>

                    <!-- Complete Button -->
                    <button type="button" 
                            @click="completeSale()" 
                            :disabled="cart.length === 0"
                            class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-black text-xs shadow-lg shadow-emerald-500/20 disabled:opacity-40 transition active:scale-[0.98] cursor-pointer">
                        ⚡ Complete Sale & Print
                    </button>
                </div>
            </aside>
        </div>

        <!-- 3. Cloud Connection & API Settings Modal -->
        <div x-show="showSettings" x-cloak class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-md w-full space-y-4 text-left shadow-2xl">
                <div class="flex justify-between items-center pb-3 border-b border-slate-800">
                    <h3 class="text-sm font-black text-white uppercase">Cloud Server & API Settings</h3>
                    <button type="button" @click="showSettings = false" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">SaaS Server URL</label>
                        <input type="url" x-model="serverUrl" placeholder="https://yourdomain.com" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Terminal API Token</label>
                        <input type="password" x-model="apiToken" placeholder="zk_live_..." class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs font-mono text-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showSettings = false" class="px-4 py-2 text-xs font-bold text-slate-400 hover:text-white cursor-pointer">Cancel</button>
                    <button type="button" @click="saveSettings()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl cursor-pointer shadow-md shadow-blue-600/30">Save & Sync</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
