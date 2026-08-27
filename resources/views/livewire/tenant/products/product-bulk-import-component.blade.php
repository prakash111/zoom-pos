<div>
    <button type="button" wire:click="$set('showImportModal', true)" class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 shadow-sm flex items-center justify-center gap-2">⇧ {{ __('Bulk Import') }}</button>

    @if($showImportModal)
        <div class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-lg rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-6 space-y-5">
                <div class="flex items-start justify-between"><div><h3 class="font-black text-slate-900 dark:text-white">{{ __('Bulk Product Import') }}</h3><p class="text-xs text-slate-400 mt-1">{{ __('Import CSV, pipe-delimited TXT, or DOCX product data (up to 10 MB).') }}</p></div><button type="button" wire:click="$set('showImportModal', false)" class="text-slate-400">✕</button></div>
                <div class="rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 p-5"><input type="file" wire:model="importFile" accept=".csv,.txt,.docx" class="w-full text-xs text-slate-500 file:rounded-xl file:border-0 file:px-4 file:py-2 file:font-bold file:bg-indigo-50 file:text-indigo-700">@error('importFile')<p class="text-xs text-rose-600 mt-2">{{ $message }}</p>@enderror</div>
                <label class="flex items-start gap-3 p-3 rounded-2xl bg-purple-50/70 dark:bg-purple-950/30"><input type="checkbox" wire:model="autoGenerateAiImages" class="mt-0.5 rounded text-purple-600"><span><span class="block text-xs font-bold text-purple-800 dark:text-purple-300">{{ __('Generate missing images with AI') }}</span><span class="block text-[11px] text-purple-600/80 dark:text-purple-400">{{ __('Generation runs in the background using the default provider.') }}</span></span></label>
                <div class="flex items-center justify-between gap-3"><div class="text-[11px] text-slate-500">{{ __('Demo:') }} <button wire:click="downloadDemoData('csv')" class="font-bold text-blue-600">CSV</button> · <button wire:click="downloadDemoData('txt')" class="font-bold text-blue-600">TXT</button></div><button type="button" wire:click="processImport" wire:loading.attr="disabled" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold disabled:opacity-50"><span wire:loading.remove wire:target="processImport">{{ __('Import Products') }}</span><span wire:loading wire:target="processImport">{{ __('Importing...') }}</span></button></div>
            </div>
        </div>
    @endif
</div>
