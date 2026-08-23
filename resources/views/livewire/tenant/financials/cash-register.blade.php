<div class="space-y-4 text-xs font-sans">

    <!-- Top Action & Breadcrumb Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-3 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-lg">🗄️</span>
                <h1 class="text-base font-black text-slate-900 dark:text-white tracking-tight">Cash Register (Daily Register Management)</h1>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">Open/close the register, track cash in &amp; out, and generate X/Z shift reports.</p>
        </div>

        @if ($openRegister)
            <div class="flex items-center gap-2">
                <button type="button" wire:click="openMovementModal('cash_in')" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-md active:scale-95 transition cursor-pointer">
                    + Cash In
                </button>
                <button type="button" wire:click="openMovementModal('cash_out')" class="px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs shadow-md active:scale-95 transition cursor-pointer">
                    &minus; Cash Out
                </button>
                <button type="button" wire:click="openCloseModal" class="px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md active:scale-95 transition cursor-pointer">
                    Close Register
                </button>
            </div>
        @endif
    </div>

    <!-- Status Alert Flash -->
    @if (session('status'))
        <div class="p-3 rounded-xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center gap-2">
            <span>✓</span><span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="p-3 rounded-xl bg-rose-50 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-xs font-bold flex items-center gap-2">
            <span>⚠️</span><span>{{ session('error') }}</span>
        </div>
    @endif

    @if (! $openRegister)
        <!-- No Open Register: Prompt to Open -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-10 border border-slate-200 dark:border-slate-800 shadow-xs text-center space-y-4">
            <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 text-3xl flex items-center justify-center mx-auto">🗄️</div>
            <div>
                <h3 class="text-sm font-black text-slate-900 dark:text-white">No Cash Register Open</h3>
                <p class="text-xs text-slate-400 mt-1">Open a register with a starting cash float to begin tracking today's shift.</p>
            </div>
            <button type="button" wire:click="openRegisterModal" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md active:scale-95 transition cursor-pointer">
                Open Register
            </button>
        </div>
    @else
        <!-- X-Report: Live Shift Summary -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white dark:bg-slate-900 p-3.5 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Opening Float</span>
                <div class="text-lg sm:text-xl font-black text-slate-900 dark:text-white font-mono mt-0.5">${{ number_format($liveSummary['opening_balance'], 2) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5 font-semibold">Opened {{ $openRegister->opened_at->diffForHumans() }}</div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-3.5 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Cash Sales</span>
                <div class="text-lg sm:text-xl font-black text-emerald-600 dark:text-emerald-400 font-mono mt-0.5">${{ number_format($liveSummary['cash_sales'], 2) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5 font-semibold">{{ $liveSummary['sale_count'] }} sale(s) total, all methods</div>
            </div>
            <div class="bg-white dark:bg-slate-900 p-3.5 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-600 dark:text-amber-400">Cash In / Out</span>
                <div class="text-lg sm:text-xl font-black text-slate-900 dark:text-white font-mono mt-0.5">+${{ number_format($liveSummary['cash_in'], 2) }} / -${{ number_format($liveSummary['cash_out'], 2) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5 font-semibold">Manual movements this shift</div>
            </div>
            <div class="bg-blue-50 dark:bg-blue-950/40 p-3.5 sm:p-4 rounded-2xl border border-blue-200 dark:border-blue-900 shadow-xs">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600 dark:text-blue-400">Expected Cash in Drawer</span>
                <div class="text-lg sm:text-xl font-black text-blue-700 dark:text-blue-300 font-mono mt-0.5">${{ number_format($liveSummary['expected_cash'], 2) }}</div>
                <div class="text-[10px] text-blue-500 mt-0.5 font-semibold">X-Report (live)</div>
            </div>
        </div>

        <!-- Sales by Payment Method + Movement Log -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs p-4">
                <h3 class="text-xs font-black text-slate-900 dark:text-white mb-3">Sales by Payment Method (This Shift)</h3>
                @forelse ($liveSummary['payments_by_method'] as $method => $amount)
                    <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-slate-800 last:border-0">
                        <span class="font-semibold text-slate-600 dark:text-slate-300 capitalize">{{ $method }}</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white">${{ number_format($amount, 2) }}</span>
                    </div>
                @empty
                    <p class="text-slate-400 italic py-2">No sales recorded yet this shift.</p>
                @endforelse
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs p-4">
                <h3 class="text-xs font-black text-slate-900 dark:text-white mb-3">Cash Movement Log</h3>
                <div class="space-y-1.5 max-h-56 overflow-y-auto">
                    @forelse ($movements as $m)
                        <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <div>
                                <span @class(['font-bold', 'text-emerald-600 dark:text-emerald-400' => $m->type === 'cash_in', 'text-amber-600 dark:text-amber-400' => $m->type === 'cash_out'])>
                                    {{ $m->type === 'cash_in' ? 'Cash In' : 'Cash Out' }}
                                </span>
                                @if ($m->reason)
                                    <span class="text-slate-400"> &bull; {{ $m->reason }}</span>
                                @endif
                            </div>
                            <span class="font-mono font-bold">{{ $m->type === 'cash_in' ? '+' : '-' }}${{ number_format($m->amount, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-slate-400 italic py-2">No cash movements recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <!-- Closed Register History -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        <div class="p-3 sm:p-3.5 border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-850">
            <h3 class="text-xs font-black text-slate-900 dark:text-white">Register History (Closed Shifts / Z-Reports)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-[10px] uppercase font-extrabold text-slate-400">
                    <tr>
                        <th class="px-4 py-2.5">Opened</th>
                        <th class="px-4 py-2.5">Closed</th>
                        <th class="px-4 py-2.5">Opening</th>
                        <th class="px-4 py-2.5">Expected</th>
                        <th class="px-4 py-2.5">Counted</th>
                        <th class="px-4 py-2.5">Difference</th>
                        <th class="px-4 py-2.5">Closed By</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($history as $reg)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="px-4 py-2.5 font-semibold">{{ $reg->opened_at->format('d M Y, H:i') }}</td>
                            <td class="px-4 py-2.5">{{ $reg->closed_at?->format('d M Y, H:i') }}</td>
                            <td class="px-4 py-2.5 font-mono">${{ number_format($reg->opening_balance, 2) }}</td>
                            <td class="px-4 py-2.5 font-mono">${{ number_format($reg->expected_closing_balance, 2) }}</td>
                            <td class="px-4 py-2.5 font-mono">${{ number_format($reg->counted_closing_balance, 2) }}</td>
                            <td class="px-4 py-2.5 font-mono font-bold {{ $reg->cash_difference == 0 ? 'text-emerald-600' : ($reg->cash_difference < 0 ? 'text-rose-600' : 'text-amber-600') }}">
                                {{ $reg->cash_difference > 0 ? '+' : '' }}${{ number_format($reg->cash_difference, 2) }}
                            </td>
                            <td class="px-4 py-2.5">{{ $reg->closer?->name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button" wire:click="viewRegister({{ $reg->id }})" class="text-blue-600 hover:underline font-bold text-[11px] cursor-pointer">View Z-Report</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400 italic">No closed registers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 border-t border-slate-100 dark:border-slate-800">{{ $history->links() }}</div>
    </div>

    <!-- Open Register Modal -->
    @if ($showOpenModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" x-data x-on:keydown.escape.window="$wire.set('showOpenModal', false)">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-sm w-full p-6 space-y-4">
                <h3 class="text-sm font-black text-slate-900 dark:text-white">Open Cash Register</h3>
                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-400 mb-1">Opening Float ($)</label>
                    <input type="number" min="0" step="0.01" wire:model="openingBalance" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono font-bold focus:ring-2 focus:ring-blue-500">
                    @error('openingBalance') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showOpenModal', false)" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs cursor-pointer">Cancel</button>
                    <button type="button" wire:click="openRegister" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs cursor-pointer">Open Register</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Add Cash In / Out Modal -->
    @if ($showMovementModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" x-data x-on:keydown.escape.window="$wire.set('showMovementModal', false)">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-sm w-full p-6 space-y-4">
                <h3 class="text-sm font-black text-slate-900 dark:text-white">{{ $movementType === 'cash_in' ? 'Record Cash In' : 'Record Cash Out' }}</h3>
                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-400 mb-1">Amount ($)</label>
                    <input type="number" min="0.01" step="0.01" wire:model="movementAmount" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono font-bold focus:ring-2 focus:ring-blue-500">
                    @error('movementAmount') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-400 mb-1">Reason / Note</label>
                    <input type="text" wire:model="movementReason" placeholder="e.g. Change fund top-up, petty cash withdrawal" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showMovementModal', false)" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs cursor-pointer">Cancel</button>
                    <button type="button" wire:click="recordMovement" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs cursor-pointer">Save</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Close Register Modal -->
    @if ($showCloseModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" x-data x-on:keydown.escape.window="$wire.set('showCloseModal', false)">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-md w-full p-6 space-y-4">
                <h3 class="text-sm font-black text-slate-900 dark:text-white">Close Register &amp; Generate Z-Report</h3>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 flex items-center justify-between">
                    <span class="font-bold text-slate-500">Expected Cash (System)</span>
                    <span class="font-mono font-black text-slate-900 dark:text-white">${{ number_format($closeExpectedCash, 2) }}</span>
                </div>

                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-400 mb-1">Counted Cash in Drawer ($)</label>
                    <input type="number" min="0" step="0.01" wire:model.live="countedClosingBalance" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono font-bold focus:ring-2 focus:ring-blue-500">
                    @error('countedClosingBalance') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div @class([
                    'p-3 rounded-xl flex items-center justify-between border',
                    'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300' => $this->closeVariance == 0,
                    'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300' => $this->closeVariance < 0,
                    'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300' => $this->closeVariance > 0,
                ])>
                    <span class="font-bold uppercase text-[10px] tracking-wider">{{ $this->closeVariance == 0 ? 'Balanced' : ($this->closeVariance < 0 ? 'Short' : 'Over') }}</span>
                    <span class="font-mono font-black">{{ $this->closeVariance > 0 ? '+' : '' }}${{ number_format($this->closeVariance, 2) }}</span>
                </div>

                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-400 mb-1">Closing Notes (Optional)</label>
                    <textarea wire:model="closeNotes" rows="2" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" wire:click="$set('showCloseModal', false)" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs cursor-pointer">Cancel</button>
                    <button type="button" wire:click="closeRegister" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-black text-xs cursor-pointer">Close Register</button>
                </div>
            </div>
        </div>
    @endif

    <!-- View Z-Report Modal (Historical Closed Register) -->
    @if ($viewingRegister && $viewingSummary)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs" x-data x-on:keydown.escape.window="$wire.closeViewRegister()">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-lg w-full p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white">Z-Report &bull; {{ $viewingRegister->opened_at->format('d M Y') }}</h3>
                    <button type="button" wire:click="closeViewRegister" class="text-slate-400 hover:text-slate-700 dark:hover:text-white font-bold text-lg cursor-pointer">&times;</button>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60"><span class="block text-[10px] text-slate-400 font-bold uppercase">Opened</span>{{ $viewingRegister->opened_at->format('H:i') }} by {{ $viewingRegister->opener?->name ?? '—' }}</div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60"><span class="block text-[10px] text-slate-400 font-bold uppercase">Closed</span>{{ $viewingRegister->closed_at?->format('H:i') }} by {{ $viewingRegister->closer?->name ?? '—' }}</div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60"><span class="block text-[10px] text-slate-400 font-bold uppercase">Opening Float</span>${{ number_format($viewingSummary['opening_balance'], 2) }}</div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60"><span class="block text-[10px] text-slate-400 font-bold uppercase">Total Sales</span>${{ number_format($viewingSummary['total_sales'], 2) }} ({{ $viewingSummary['sale_count'] }})</div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60"><span class="block text-[10px] text-slate-400 font-bold uppercase">Cash In / Out</span>+${{ number_format($viewingSummary['cash_in'], 2) }} / -${{ number_format($viewingSummary['cash_out'], 2) }}</div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60"><span class="block text-[10px] text-slate-400 font-bold uppercase">Expected Cash</span>${{ number_format($viewingRegister->expected_closing_balance, 2) }}</div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60"><span class="block text-[10px] text-slate-400 font-bold uppercase">Counted Cash</span>${{ number_format($viewingRegister->counted_closing_balance, 2) }}</div>
                    <div @class([
                        'p-2.5 rounded-xl font-black',
                        'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300' => $viewingRegister->cash_difference == 0,
                        'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300' => $viewingRegister->cash_difference < 0,
                        'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300' => $viewingRegister->cash_difference > 0,
                    ])>
                        <span class="block text-[10px] font-bold uppercase">Difference</span>{{ $viewingRegister->cash_difference > 0 ? '+' : '' }}${{ number_format($viewingRegister->cash_difference, 2) }}
                    </div>
                </div>

                <div>
                    <h4 class="text-[11px] font-black text-slate-700 dark:text-slate-300 uppercase mb-1.5">Sales by Payment Method</h4>
                    @forelse ($viewingSummary['payments_by_method'] as $method => $amount)
                        <div class="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800 last:border-0">
                            <span class="capitalize font-semibold text-slate-600 dark:text-slate-300">{{ $method }}</span>
                            <span class="font-mono font-bold">${{ number_format($amount, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-slate-400 italic py-1">No sales recorded.</p>
                    @endforelse
                </div>

                @if ($viewingRegister->notes)
                    <div>
                        <h4 class="text-[11px] font-black text-slate-700 dark:text-slate-300 uppercase mb-1">Closing Notes</h4>
                        <p class="text-slate-600 dark:text-slate-300">{{ $viewingRegister->notes }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

</div>
