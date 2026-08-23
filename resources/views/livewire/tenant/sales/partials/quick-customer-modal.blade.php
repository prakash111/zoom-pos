@if ($showQuickCustomerModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 max-w-md w-full shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 animate-in fade-in zoom-in-95">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <h3 class="font-extrabold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                    <span>👤 Quick Add Customer</span>
                </h3>
                <button type="button" wire:click="$set('showQuickCustomerModal', false)" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
            </div>

            <form wire:submit.prevent="createQuickCustomer" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Customer Full Name *</label>
                    <input type="text"
                           wire:model="newCustomerName"
                           placeholder="e.g. Michael Scott"
                           class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-semibold focus:ring-2 focus:ring-blue-500">
                    @error('newCustomerName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Phone Number</label>
                    <input type="text"
                           wire:model="newCustomerPhone"
                           placeholder="+1 555 0199"
                           class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-semibold focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Email Address</label>
                    <input type="email"
                           wire:model="newCustomerEmail"
                           placeholder="client@example.com"
                           class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-semibold focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showQuickCustomerModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400">Cancel</button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="px-5 py-2.5 rounded-xl text-xs font-black bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
                        <span wire:loading.remove>Save & Select Customer</span>
                        <span wire:loading>Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
