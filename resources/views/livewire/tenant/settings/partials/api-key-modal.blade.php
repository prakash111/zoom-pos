<!-- API Key Creation Modal -->
<x-modal wire:model="showApiKeyModal" maxWidth="md" :title="__('Generate Developer API Key')" :subtitle="__('Create and configure secure access tokens for external integrations.')" icon="🔑">
    <div class="space-y-4 text-xs">
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('API Key Label / App Name *') }}</label>
            <input type="text"
                   wire:model="newApiKeyName"
                   placeholder="{{ __('e.g. Shopify Store, ERP Next, Accounting Sync') }}"
                   class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500">
            @error('newApiKeyName') <p class="text-rose-600 text-[10px] mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-1.5">{{ __('Permissions / Scopes') }}</label>
            <div class="space-y-2 p-3 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800/80 text-xs text-slate-700 dark:text-slate-300 font-medium">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="newApiKeyPermissions" value="tax:calculate" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500">
                    <span><code>tax:calculate</code> — {{ __('Calculate dynamic taxes & cart breakdowns') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="newApiKeyPermissions" value="tax:invoices" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500">
                    <span><code>tax:invoices</code> — {{ __('Issue cleared fiscal tax invoices & orders') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="newApiKeyPermissions" value="*" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500">
                    <span><code>*</code> — {{ __('Full Administrative API Access (All endpoints)') }}</span>
                </label>
            </div>
        </div>
    </div>

    <x-slot:footer>
        <button type="button" @click="open = false" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer">
            {{ __('Cancel') }}
        </button>
        <button type="button" wire:click="createApiKey" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-500/20 transition active:scale-[0.98] cursor-pointer">
            {{ __('Generate Key') }}
        </button>
    </x-slot:footer>
</x-modal>
