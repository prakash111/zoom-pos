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

    <!-- Header Toolbar -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">Subscription Plans</h3>
            <p class="text-xs text-slate-400">Configure public pricing tiers, limits, and billing frequencies</p>
        </div>
        <button wire:click="newPlan" type="button" class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/25 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
            <span>+ New Plan</span>
        </button>
    </div>

    <!-- Create / {{ __("Edit Plan") }} Modal Form -->
    @if ($showForm)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-5 animate-in fade-in">
            <h4 class="text-sm sm:text-base font-black text-slate-900 dark:text-white">
                {{ $editingName ? __('Edit Plan') . ': ' . $displayName : __('Create New Subscription Plan') }}
            </h4>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Plan Identifier Key *</label>
                    <input type="text" wire:model="name" @disabled($editingName) placeholder="pro_monthly" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500 disabled:opacity-60">
                    @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Display Title *</label>
                    <input type="text" wire:model="displayName" placeholder="Professional Store Plan" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                    @error('displayName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Billing Cycle</label>
                    <select wire:model="billingCycle" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                        <option value="trial">Free Trial</option>
                        <option value="monthly">{{ __("Monthly") }}</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="biannual">Biannual (6 Months)</option>
                        <option value="yearly">{{ __("Yearly") }}</option>
                        <option value="lifetime">Lifetime</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Duration (Days)</label>
                    <input type="number" wire:model="durationDays" @disabled($billingCycle === 'lifetime') class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500 disabled:opacity-60">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Price ($ USD) *</label>
                    <input type="number" step="0.01" wire:model="price" placeholder="29.00" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Max Users Limit</label>
                    <input type="number" wire:model="limitUsers" placeholder="5 (0 for unlimited)" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Max Devices Limit</label>
                    <input type="number" wire:model="limitDevices" placeholder="3" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Storage Quota (MB)</label>
                    <input type="number" wire:model="limitStorageMb" placeholder="2048" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                </div>

                <div class="flex items-center gap-2 pt-4">
                    <input type="checkbox" wire:model="featureMultiLocation" id="fml" class="rounded-lg text-indigo-600 focus:ring-indigo-500">
                    <label for="fml" class="text-xs font-bold text-slate-700 dark:text-slate-300">Multi-location Branches</label>
                </div>

                <div class="flex items-center gap-2 pt-4">
                    <input type="checkbox" wire:model="featureAutomaticBackup" id="fab" class="rounded-lg text-indigo-600 focus:ring-indigo-500">
                    <label for="fab" class="text-xs font-bold text-slate-700 dark:text-slate-300">Automatic Cloud Backups</label>
                </div>

                <div class="flex items-center gap-2 pt-4">
                    <input type="checkbox" wire:model="active" id="active" class="rounded-lg text-indigo-600 focus:ring-indigo-500">
                    <label for="active" class="text-xs font-bold text-slate-700 dark:text-slate-300">Active (Publicly Selectable)</label>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">Cancel</button>
                <button wire:click="save" type="button" class="px-6 py-2.5 rounded-2xl text-xs font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-500/25 active:scale-95 transition">{{ __("Save Plan") }}</button>
            </div>
        </div>
    @endif

    <!-- Pricing Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach ($plans as $plan)
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-4 hover:shadow-lg transition">
                <div>
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400 font-mono">{{ $plan->name }}</span>
                            <h4 class="text-lg font-black text-slate-900 dark:text-white leading-tight mt-0.5">{{ $plan->display_name }}</h4>
                        </div>
                        @if ($plan->active)
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">Active</span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-slate-100 dark:bg-slate-800 text-slate-400">Inactive</span>
                        @endif
                    </div>

                    <div class="mt-4 flex items-baseline gap-1">
                        <span class="text-3xl font-black text-slate-900 dark:text-white">${{ number_format($plan->price, 2) }}</span>
                        <span class="text-xs font-bold text-slate-400">/ {{ $plan->billing_cycle }}</span>
                    </div>

                    <ul class="mt-4 space-y-2 text-xs text-slate-600 dark:text-slate-400 font-medium">
                        <li class="flex items-center gap-2">
                            <span class="text-emerald-500 font-bold">✓</span>
                            <span>{{ $plan->limits['usuarios'] ?? '∞' }} Staff Users &middot; {{ $plan->limits['dispositivos'] ?? '∞' }} POS Devices</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-emerald-500 font-bold">✓</span>
                            <span>{{ $plan->limits['armazenamento_mb'] ?? '∞' }} MB Cloud Storage</span>
                        </li>
                        @if (!empty($plan->features['multi_location']))
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-500 font-bold">✓</span>
                                <span>Multi-location Branch Management</span>
                            </li>
                        @endif
                    </ul>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                    <button wire:click="edit('{{ $plan->name }}')" type="button" class="px-3.5 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 font-bold hover:bg-indigo-100 transition">
                        {{ __("Edit Plan") }}
                    </button>
                    <button wire:click="delete('{{ $plan->name }}')" wire:confirm="Delete this plan?" type="button" class="text-rose-600 hover:underline font-bold">
                        Delete
                    </button>
                </div>
            </div>
        @endforeach
    </div>

</div>
