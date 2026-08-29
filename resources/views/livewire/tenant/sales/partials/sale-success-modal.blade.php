@if ($this->completedSale)
    @php $cSale = $this->completedSale; @endphp
    <x-modal wire:model="showSaleSuccessModal" maxWidth="lg" :title="__('Sale Completed!')" :subtitle="__('Invoice #') . $cSale->sale_number" icon="✓">

        <!-- Sale Summary Card -->
        <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800/80 space-y-2 text-xs">
            <div class="flex items-center justify-between">
                <span class="text-slate-500 dark:text-slate-400 font-medium">{{ __("Customer:") }}</span>
                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $cSale->customer_name ?: __('Walk-in Regular Customer') }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-500 dark:text-slate-400 font-medium">{{ __("Total Amount Paid:") }}</span>
                <span class="text-xl font-black text-emerald-600 dark:text-emerald-400 font-mono">{{ $cSale->company?->formatMoney($cSale->total) }}</span>
            </div>
            @if (!empty($cSale->flattened_tax_components))
                @foreach ($cSale->flattened_tax_components as $comp)
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500 dark:text-slate-400 font-medium">{{ $comp['name'] }} ({{ $comp['rate'] }}%):</span>
                        <span class="font-mono font-bold text-blue-600 dark:text-blue-400">+{{ $cSale->company?->formatMoney($comp['amount']) }}</span>
                    </div>
                @endforeach
            @elseif ((float)($cSale->tax_amount ?? 0) > 0)
                <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400 font-medium">{{ __("Tax Amount:") }}</span>
                    <span class="font-mono font-bold text-blue-600 dark:text-blue-400">+{{ $cSale->company?->formatMoney($cSale->tax_amount) }}</span>
                </div>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-slate-500 dark:text-slate-400 font-medium">{{ __("Payment Method:") }}</span>
                <span class="capitalize px-2.5 py-0.5 rounded-full text-[11px] font-black bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200">{{ $cSale->payment_method }}</span>
            </div>
            @if ($cSale->einvoice_status)
                <div class="flex items-center justify-between pt-1 border-t border-slate-200/60 dark:border-slate-700/60 text-[11px]">
                    <span class="text-slate-500 font-medium">{{ __("Fiscal E-Invoice:") }}</span>
                    <span class="px-2 py-0.5 rounded-full font-black text-[10px] uppercase {{ $cSale->einvoice_status === 'cleared' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-amber-100 text-amber-800' }}">
                        ✓ {{ $cSale->einvoice_status === 'cleared' ? 'E-Invoice Cleared' : ucfirst($cSale->einvoice_status) }}
                    </span>
                </div>
            @endif
            <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-slate-200/60 dark:border-slate-700/60 font-mono">
                <span>{{ __("Completed At:") }}</span>
                <span>{{ $cSale->created_at->format('d M Y, H:i') }}</span>
            </div>
        </div>

        <!-- Receipt & Post-Sale Dispatch Actions -->
        <div class="space-y-3">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ __("Receipt & Dispatch Options") }}</div>

            <!-- 1. Print Now (desktop, silent) or Download / Print PDF Invoice -->
            @if ($this->completedSaleDesktopPrintReady)
                <button type="button"
                        wire:click="printCompletedSaleNow"
                        wire:loading.attr="disabled"
                        class="w-full py-2.5 px-4 rounded-xl bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 text-white font-extrabold text-xs shadow-md active:scale-[0.97] transition duration-150 ease-out flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z" /></svg>
                    <span>{{ __("Print Receipt") }}</span>
                </button>
            @else
                <a href="{{ route('tenant.sales.pdf', $cSale) }}"
                   target="_blank"
                   class="w-full py-2.5 px-4 rounded-xl bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 text-white font-extrabold text-xs shadow-md active:scale-[0.97] transition duration-150 ease-out flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span>{!! __("Download & Print PDF Invoice") !!}</span>
                </a>
            @endif

            <!-- 2. WhatsApp Direct Sharing -->
            <div class="space-y-1.5">
                <label class="block text-[11px] font-semibold text-slate-500">{{ __("Share Receipt on WhatsApp") }}</label>
                <div class="flex items-center gap-2">
                    <input type="text"
                           wire:model.live.debounce.250ms="sharePhone"
                           placeholder="{{ __("Phone number (+1 555 0199)") }}"
                           class="flex-1 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-emerald-500 font-mono">

                    @if ($this->completedSaleWhatsAppApiConfigured)
                        <button type="button"
                                wire:click="sendSaleWhatsApp"
                                wire:loading.attr="disabled"
                                class="py-2 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md shadow-emerald-500/20 active:scale-[0.97] transition duration-150 ease-out flex items-center gap-1.5 shrink-0 cursor-pointer">
                            <span wire:loading.remove>💬 WhatsApp</span>
                            <span wire:loading>{{ __("Sending...") }}</span>
                        </button>
                    @else
                        <a href="{{ $this->completedSaleWhatsAppUrl }}"
                           target="_blank"
                           class="py-2 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md shadow-emerald-500/20 active:scale-[0.97] transition duration-150 ease-out flex items-center gap-1.5 shrink-0 cursor-pointer">
                            <span>💬 WhatsApp</span>
                        </a>
                    @endif
                </div>
            </div>

            <!-- 3. Email PDF Invoice -->
            <div class="space-y-1.5">
                <label class="block text-[11px] font-semibold text-slate-500">{{ __("Send Invoice via Email") }}</label>
                <div class="flex items-center gap-2">
                    <input type="email"
                           wire:model="shareEmail"
                           placeholder="customer@example.com"
                           class="flex-1 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-purple-500">

                    <button type="button"
                            wire:click="sendSaleEmail"
                            wire:loading.attr="disabled"
                            class="py-2 px-4 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-extrabold text-xs shadow-md shadow-purple-500/20 active:scale-[0.97] transition duration-150 ease-out flex items-center gap-1.5 shrink-0 cursor-pointer">
                        <span wire:loading.remove>✉️ {{ __("Send") }}</span>
                        <span wire:loading>{{ __("Sending...") }}</span>
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

        <x-slot:footer>
            <div class="w-full space-y-2">
                <x-ui.button wire:click="startNextSale" full class="!font-black">
                    <span>➕ Start Next Order / New Sale &rarr;</span>
                </x-ui.button>

                <a wire:navigate.hover href="{{ route('tenant.sales.show', $cSale) }}"
                   class="block text-center text-[11px] font-semibold text-slate-400 hover:text-blue-500 hover:underline pt-1">
                    View Full Order Audit Log & Records
                </a>
            </div>
        </x-slot:footer>
    </x-modal>
@endif
