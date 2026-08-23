<div class="space-y-6">
    
    <!-- Flash Notifications -->
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2 border border-emerald-200 dark:border-emerald-800 shadow-sm animate-in fade-in">
            <svg class="w-5 h-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 text-xs sm:text-sm font-bold flex items-center gap-2 border border-rose-200 dark:border-rose-800 shadow-sm animate-in fade-in">
            <svg class="w-5 h-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Generated Code Flash Banner -->
    @if ($justGeneratedCode)
        <div class="p-6 rounded-3xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 space-y-2 shadow-sm animate-in fade-in">
            <div class="flex items-center gap-2 text-indigo-700 dark:text-indigo-300 font-extrabold text-xs uppercase tracking-wider">
                <span>🔑 License Key Generated Successfully</span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Copy this activation code now and provide it to the client. It will not be shown in full again for security:</p>
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-indigo-100 dark:border-indigo-900 font-mono text-base sm:text-lg font-black text-indigo-600 dark:text-indigo-400 select-all tracking-wider">
                {{ $justGeneratedCode }}
            </div>
        </div>
    @endif

    <!-- Header Toolbar -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">Activation License Keys</h3>
            <p class="text-xs text-slate-400">Generate and manage offline license vouchers for instant self-registration and renewals</p>
        </div>
        <button wire:click="$toggle('showForm')" type="button" class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/25 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
            <span>+ Generate Key</span>
        </button>
    </div>

    <!-- Generator Form Modal Card -->
    @if ($showForm)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5 animate-in fade-in">
            <h4 class="text-sm sm:text-base font-black text-slate-900 dark:text-white">
                Generate Offline License Voucher
            </h4>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Target Plan *</label>
                    <select wire:model="planName" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                        <option value="">Select a subscription plan…</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->name }}">{{ $plan->display_name }} (${{ number_format($plan->price, 2) }})</option>
                        @endforeach
                    </select>
                    @error('planName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Max Redemptions</label>
                    <input type="number" wire:model="maxUses" placeholder="1 (Default single use)" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Redemption Window (Days)</label>
                    <input type="number" wire:model="validityDays" placeholder="365 (Leave blank for no expiry)" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Internal Reference / Client Notes</label>
                    <input type="text" wire:model="notes" placeholder="Issued to John Doe for Annual Plan voucher…" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">Cancel</button>
                <button wire:click="generate" type="button" class="px-6 py-2.5 rounded-2xl text-xs font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-500/25 active:scale-95 transition">Generate Code</button>
            </div>
        </div>
    @endif

    <!-- License Codes Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-extrabold text-left border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-3.5">Code Prefix</th>
                        <th class="px-6 py-3.5">Subscription Plan</th>
                        <th class="px-6 py-3.5">Uses Count</th>
                        <th class="px-6 py-3.5">Valid Until</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($codes as $code)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="px-6 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                {{ $code->code_prefix }}••••••••
                            </td>

                            <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                                {{ $code->plan_name }}
                            </td>

                            <td class="px-6 py-4 text-slate-600 dark:text-slate-400">
                                <span class="font-bold">{{ $code->current_uses }}</span> / {{ $code->max_uses ?? '∞' }}
                            </td>

                            <td class="px-6 py-4 text-slate-500 dark:text-slate-400 text-xs font-mono">
                                {{ $code->expires_at?->format('Y-m-d') ?? 'Never (Lifetime)' }}
                            </td>

                            <td class="px-6 py-4">
                                @if ($code->revoked)
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">
                                        Revoked
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 flex items-center gap-1.5 w-max">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-right">
                                @unless ($code->revoked)
                                    <button wire:click="revoke('{{ $code->id }}')" wire:confirm="Revoke this license key?" type="button" class="text-rose-600 hover:underline font-bold text-xs">
                                        Revoke
                                    </button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                No activation codes generated yet. Click "+ Generate Key" above.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $codes->links() }}</div>

</div>
