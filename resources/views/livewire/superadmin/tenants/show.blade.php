<div class="w-full space-y-6">
    
    <!-- Flash Status -->
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2 border border-emerald-200 dark:border-emerald-800 shadow-sm animate-in fade-in">
            <svg class="w-5 h-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-black flex items-center justify-center text-sm shrink-0">
                {{ strtoupper(substr($company->name, 0, 2)) }}
            </div>
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">{{ $company->name }}</h3>
                <p class="text-xs text-slate-400 font-mono">{{ $company->unique_account_id }} &bull; {{ $userCount }} active staff user(s)</p>
            </div>
        </div>

        <a wire:navigate.hover href="{{ route('superadmin.tenants.index') }}" class="text-xs font-bold text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 flex items-center gap-1">
            &larr; Back to Stores
        </a>
    </div>

    <!-- Management Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-6">
        
        <!-- Action Quick-bar -->
        <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500">{{ __("Live Status:") }}</span>
                @if ($status === 'active')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">{{ __("Active Store") }}</span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">{{ __("Suspended") }}</span>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if ($status === 'active')
                    <button wire:click="suspend" type="button" class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-950 dark:text-amber-300 transition">
                        Suspend Store
                    </button>
                @else
                    <button wire:click="activate" type="button" class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold bg-emerald-600 hover:bg-emerald-700 text-white transition">
                        Activate Store
                    </button>
                @endif

                <button wire:click="extendExpiry" type="button" class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 transition">
                    + Extend (+30d)
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Company / Store Name</label>
                <input type="text" wire:model="name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Contact Email</label>
                <input type="email" wire:model="email" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Contact Phone") }}</label>
                <input type="text" wire:model="phone" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Subscription Plan</label>
                <select wire:model="planName" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                    <option value="">{{ __("No Plan (Unlimited / Manual)") }}</option>
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->name }}">{{ $plan->display_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Store Status</label>
                <select wire:model="status" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Subscription Expiration Date") }}</label>
                <input type="date" wire:model="expiresAt" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Max Users Limit") }}</label>
                <input type="number" wire:model="maxUsers" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Max POS Devices Limit") }}</label>
                <input type="number" wire:model="maxDevices" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-indigo-500">
            </div>
        </div>

        <!-- Operating Mode & Licensed Modules (SuperAdmin Override) -->
        <div class="pt-5 border-t border-slate-100 dark:border-slate-800 space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __("Store Operating Mode (SuperAdmin Override)") }}</label>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __("Strict mode locking is active for tenants. Regular tenants cannot switch operating modes. Only SuperAdmins can override the store mode.") }}
                </p>
            </div>
            <div>
                <select wire:model="posMode" class="w-full sm:w-80 rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-semibold focus:ring-indigo-500">
                    @foreach (\App\Services\Modular\ModuleRegistry::allModules() as $mKey => $mVal)
                        <option value="{{ $mKey }}">{{ __($mVal['title']) }} ({{ $mKey }})</option>
                    @endforeach
                </select>
                @error('posMode') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="pt-3">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Tenant Licensed & Visible Modules") }}</label>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-2">
                    {{ __("Select which specific modules are licensed and visible in this tenant's workspace navigation.") }}
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach (\App\Services\Modular\ModuleRegistry::allModules() as $mKey => $mVal)
                        @php $isLic = in_array($mKey, $licensedModules, true); @endphp
                        <label class="flex items-center justify-between p-3 rounded-xl border cursor-pointer {{ $isLic ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-700 opacity-70 hover:opacity-100' }}">
                            <div class="flex items-center gap-2">
                                <span class="text-base">
                                    @if ($mKey === 'restaurant') 🍽️ @elseif ($mKey === 'pharmacy') 💊 @elseif ($mKey === 'service_booking') ✂️ @else 🏪 @endif
                                </span>
                                <div>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __($mVal['title']) }}</span>
                                    <span class="block text-[10px] text-slate-500 dark:text-slate-400">{{ $mVal['layout_type'] }}</span>
                                </div>
                            </div>
                            <input type="checkbox" wire:model="licensedModules" value="{{ $mKey }}" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                        </label>
                    @endforeach
                </div>
                @error('licensedModules') <p class="text-[11px] text-rose-500 font-bold mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
            <button wire:click="save" type="button" class="px-6 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/25 active:scale-95 transition cursor-pointer">
                Save Tenant Changes
            </button>
        </div>

    </div>

</div>
