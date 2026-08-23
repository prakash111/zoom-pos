@if ($showSaleSuccessModal && $this->completedSale)
    @php $cSale = $this->completedSale; @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-xs">
        <div class="bg-white dark:bg-slate-900 rounded-[2rem] p-6 sm:p-7 max-w-lg w-full shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5 animate-in fade-in zoom-in-95 max-h-[90vh] overflow-y-auto">
            
            <!-- Modal Header with Success Icon -->
            <div class="flex items-start justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-2xl font-black shadow-sm">
                        ✓
                    </div>
                    <div>
                        <h3 class="font-black text-lg text-slate-900 dark:text-white leading-tight">Sale Completed!</h3>
                        <p class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400 mt-0.5">Invoice #{{ $cSale->sale_number }}</p>
                    </div>
                </div>

                <button type="button"
                        wire:click="startNextSale"
                        class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-slate-900 dark:hover:text-white text-lg font-bold transition cursor-pointer">
                    &times;
                </button>
            </div>

            <!-- Sale Summary Card -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80 space-y-2 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400 font-medium">Customer:</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200">{{ $cSale->customer_name ?: 'Walk-in Regular Customer' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400 font-medium">Total Amount Paid:</span>
                    <span class="text-xl font-black text-emerald-600 dark:text-emerald-400">${{ number_format($cSale->total, 2) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400 font-medium">Payment Method:</span>
                    <span class="capitalize px-2.5 py-0.5 rounded-full text-[11px] font-black bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200">{{ $cSale->payment_method }}</span>
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-slate-200/60 dark:border-slate-700/60 font-mono">
                    <span>{{ count($cSale->items ?? []) }} line items</span>
                    <span>{{ $cSale->created_at->format('M d, Y • H:i') }}</span>
                </div>
            </div>

            <!-- Fast Export & Sharing Actions -->
            <div class="space-y-3.5">
                
                <!-- 1. Download & Print PDF Invoice -->
                <div>
                    <a href="{{ route('tenant.sales.pdf', $cSale) }}"
                       target="_blank"
                       class="w-full py-3.5 px-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs shadow-lg shadow-blue-500/25 active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        <span>Download & Print PDF Invoice</span>
                    </a>
                </div>

                <!-- 2. WhatsApp Direct Sharing -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Share Receipt on WhatsApp</label>
                    <div class="flex items-center gap-2">
                        <input type="text"
                               wire:model.live.debounce.250ms="sharePhone"
                               placeholder="Phone number (+1 555 0199)"
                               class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 py-2.5 px-3">
                        
                        <a href="{{ $this->completedSaleWhatsAppUrl }}"
                           target="_blank"
                           class="py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md shadow-emerald-500/20 active:scale-95 transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                            <span>💬 WhatsApp</span>
                        </a>
                    </div>
                </div>

                <!-- 3. Email PDF Invoice -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Send Invoice via Email</label>
                    <div class="flex items-center gap-2">
                        <input type="email"
                               wire:model="shareEmail"
                               placeholder="customer@example.com"
                               class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-semibold focus:ring-2 focus:ring-purple-500 py-2.5 px-3">
                        
                        <button type="button"
                                wire:click="sendSaleEmail"
                                wire:loading.attr="disabled"
                                class="py-2.5 px-4 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-xs shadow-md shadow-purple-500/20 active:scale-95 transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                            <span wire:loading.remove>✉️ Send</span>
                            <span wire:loading>Sending...</span>
                        </button>
                    </div>

                    @if ($emailStatus)
                        <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400 mt-1">✓ {{ $emailStatus }}</p>
                    @endif

                    @if ($emailError)
                        <p class="text-xs font-bold text-rose-600 dark:text-rose-400 mt-1">✕ {{ $emailError }}</p>
                    @endif
                </div>

            </div>

            <!-- Bottom Primary Action: Next Order -->
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2">
                <button type="button"
                        wire:click="startNextSale"
                        class="w-full py-4 rounded-2xl bg-slate-900 hover:bg-black dark:bg-slate-700 dark:hover:bg-slate-600 text-white font-black text-sm shadow-xl active:scale-98 transition flex items-center justify-center gap-2 cursor-pointer">
                    <span>➕ Start Next Order / New Sale &rarr;</span>
                </button>

                <a href="{{ route('tenant.sales.show', $cSale) }}"
                   class="block text-center text-xs font-semibold text-slate-400 hover:text-blue-500 hover:underline pt-1">
                    View Full Order Audit Log & Records
                </a>
            </div>

        </div>
    </div>
@endif
