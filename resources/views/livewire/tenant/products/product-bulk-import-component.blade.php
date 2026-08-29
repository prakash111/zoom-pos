<div>
    <button type="button" wire:click="$set('showImportModal', true)" class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 shadow-sm flex items-center justify-center gap-2">⇧ {{ __('Bulk Import') }}</button>

    <x-modal wire:model="showImportModal" maxWidth="lg" :title="__('Bulk Product Import')" :subtitle="__('Import CSV, pipe-delimited TXT, or DOCX product data (up to 10 MB).')">
        <div class="rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 p-5"><input type="file" wire:model="importFile" accept=".csv,.txt,.docx" class="w-full text-xs text-slate-500 file:rounded-xl file:border-0 file:px-4 file:py-2 file:font-bold file:bg-indigo-50 file:text-indigo-700">@error('importFile')<p class="text-xs text-rose-600 mt-2">{{ $message }}</p>@enderror</div>
        <label class="flex items-start gap-3 p-3 rounded-2xl bg-purple-50/70 dark:bg-purple-950/30"><input type="checkbox" wire:model="autoGenerateAiImages" class="mt-0.5 rounded text-purple-600"><span><span class="block text-xs font-bold text-purple-800 dark:text-purple-300">{{ __('Generate missing images with AI') }}</span><span class="block text-[11px] text-purple-600/80 dark:text-purple-400">{{ __('Generation runs in the background using the default provider.') }}</span></span></label>
        <div class="text-[11px] text-slate-500">{{ __('Demo:') }} <button wire:click="downloadDemoData('csv')" class="font-bold text-blue-600">CSV</button> · <button wire:click="downloadDemoData('txt')" class="font-bold text-blue-600">TXT</button></div>

        <x-slot:footer>
            <button type="button" @click="open = false" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer">
                {{ __('Cancel') }}
            </button>
            <x-ui.button wire:click="processImport" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="processImport">{{ __('Import Products') }}</span>
                <span wire:loading wire:target="processImport">{{ __('Importing...') }}</span>
            </x-ui.button>
        </x-slot:footer>
    </x-modal>
</div>
