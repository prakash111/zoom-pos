<div class="w-full space-y-6">
    
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">Provision New Store</h3>
            <p class="text-xs text-slate-400">{{ __("Create and initialize an isolated tenant store environment with initial admin account") }}</p>
        </div>
        <a wire:navigate.hover href="{{ route('superadmin.tenants.index') }}" class="text-xs font-bold text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center gap-1">
            &larr; Back to Stores
        </a>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        
        <!-- Store Information -->
        <div class="space-y-4">
            <h4 class="text-sm font-extrabold text-slate-900 dark:text-white flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                <span>🏢 Store & Business Details</span>
            </h4>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Company / Store Name *") }}</label>
                    <input type="text" wire:model="name" placeholder="Acme Retail Store" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                    @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Business Contact Email") }}</label>
                    <input type="email" wire:model="email" placeholder="contact@acmestore.com" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Contact Phone") }}</label>
                    <input type="text" wire:model="phone" placeholder="+1 (555) 019-9234" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Country (ISO 2-letter) *") }}</label>
                    <input type="text" wire:model="country" maxlength="2" placeholder="US" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500 uppercase">
                    @error('country') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Initial Subscription Plan") }}</label>
                    <select wire:model="planName" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                        <option value="">{{ __("No Plan (Unlimited / Manual)") }}</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->name }}">{{ $plan->display_name }} (${{ number_format($plan->price, 2) }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Initial Admin Account -->
        <div class="space-y-4 pt-2">
            <h4 class="text-sm font-extrabold text-slate-900 dark:text-white flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                <span>👤 Primary Store Administrator Account</span>
            </h4>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Admin Full Name *") }}</label>
                    <input type="text" wire:model="adminName" placeholder="Jane Doe" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                    @error('adminName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Admin Login / Username *") }}</label>
                    <input type="text" wire:model="adminLogin" placeholder="admin" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                    @error('adminLogin') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Admin Login Email *") }}</label>
                    <input type="email" wire:model="adminEmail" placeholder="admin@acmestore.com" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                    @error('adminEmail') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Admin Password *") }}</label>
                    <input type="password" wire:model="adminPassword" placeholder="••••••••••••" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                    @error('adminPassword') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3">
            <a wire:navigate.hover href="{{ route('superadmin.tenants.index') }}" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition">Cancel</a>
            <button wire:click="save" type="button" class="px-6 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/25 active:scale-95 transition cursor-pointer">
                Provision Store &rarr;
            </button>
        </div>

    </div>

</div>
