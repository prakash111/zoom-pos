<div>
    <div class="mb-5">
        <h2 class="text-xl font-black text-slate-900 dark:text-white tracking-tight">{{ __("Step 4 — Administrator Account & License Registration") }}</h2>
        <p class="text-xs text-slate-500 mt-1">{{ __("Create the primary Super Admin owner and enter your Envato / CodeCanyon purchase code.") }}</p>
    </div>

    @if ($alreadyBootstrapped)
        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 text-xs font-semibold mb-5">
            {{ __("A Super Admin account has already been created for this database.") }}
        </div>
        <div class="flex justify-end">
            <a href="{{ route('install.finish') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl text-xs font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-md transition active:scale-95">
                <span>{{ __("Continue to Finish") }} &rarr;</span>
            </a>
        </div>
    @else
        <div class="space-y-4">
            <!-- Envato Purchase Code Registration Box -->
            <div class="p-4 rounded-2xl bg-blue-50/50 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/40 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-extrabold text-blue-900 dark:text-blue-200 flex items-center gap-1.5">
                        <span>🛡️</span> {{ __("Envato / CodeCanyon License Verification") }}
                    </span>
                    <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/60 px-2 py-0.5 rounded-full">{{ __("Commercial License") }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Purchase Code") }}</label>
                        <input type="text"
                               wire:model="purchaseCode"
                               placeholder="e.g. 12345678-abcd-1234-abcd-1234567890ab"
                               class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-mono font-medium focus:ring-2 focus:ring-blue-500 py-2 px-3">
                        @error('purchaseCode') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Envato Buyer / Organization") }}</label>
                        <input type="text"
                               wire:model="buyerUsername"
                               placeholder="e.g. your_company_name"
                               class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-medium focus:ring-2 focus:ring-blue-500 py-2 px-3">
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 leading-normal">{{ __("Enter your Item Purchase Code from your CodeCanyon license certificate to activate lifetime updates and product support.") }}</p>
            </div>

            <!-- Super Admin Account Details -->
            <div class="space-y-3 pt-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Super Admin Full Name") }} <span class="text-rose-500">*</span></label>
                    <input type="text" wire:model="name" placeholder="John Administrator" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-2 focus:ring-blue-500 py-2.5 px-3">
                    @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Super Admin Email Address") }} <span class="text-rose-500">*</span></label>
                    <input type="email" wire:model="email" placeholder="admin@example.com" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-2 focus:ring-blue-500 py-2.5 px-3">
                    @error('email') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Password (min 8 characters)") }} <span class="text-rose-500">*</span></label>
                        <input type="password" wire:model="password" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-2 focus:ring-blue-500 py-2.5 px-3">
                        @error('password') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Confirm Password") }} <span class="text-rose-500">*</span></label>
                        <input type="password" wire:model="password_confirmation" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-medium focus:ring-2 focus:ring-blue-500 py-2.5 px-3">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
            <button wire:click="save"
                    wire:loading.attr="disabled"
                    type="button"
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl text-xs sm:text-sm font-extrabold bg-blue-600 hover:bg-blue-700 text-white shadow-lg shadow-blue-500/25 active:scale-95 transition cursor-pointer">
                <span wire:loading.remove>{{ __("Create Super Admin & Lock Setup") }} &rarr;</span>
                <span wire:loading>{{ __("Verifying & Creating Account...") }}</span>
            </button>
        </div>
    @endif
</div>
