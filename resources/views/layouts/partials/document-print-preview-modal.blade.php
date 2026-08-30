<div x-data="{
        open: false,
        url: '',
        title: @js(__('Document Preview')),
        openPreview(detail) {
            const source = typeof detail === 'string' ? detail : detail?.url;
            if (!source) return;

            const previewUrl = new URL(source, window.location.origin);
            previewUrl.searchParams.set('embed', '1');
            this.url = previewUrl.toString();
            this.title = detail?.title || @js(__('Document Preview'));
            this.open = true;
            document.documentElement.classList.add('overflow-hidden');
        },
        closePreview() {
            this.open = false;
            this.url = '';
            document.documentElement.classList.remove('overflow-hidden');
        },
        printPreview() {
            this.$refs.documentPreviewFrame?.contentWindow?.print();
        }
    }"
     x-on:open-print-preview.window="openPreview($event.detail)"
     x-on:keydown.escape.window="if (open) closePreview()">
    <div x-show="open" x-cloak class="fixed inset-0 z-[10000] flex items-center justify-center p-2 sm:p-6">
        <div class="absolute inset-0 bg-slate-950/75 backdrop-blur-sm" x-on:click="closePreview()"></div>
        <section class="relative flex h-[96dvh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:h-[92dvh] sm:rounded-3xl"
                 role="dialog" aria-modal="true" aria-labelledby="document-preview-title">
            <header class="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-slate-200 px-4 dark:border-slate-700 sm:h-16 sm:px-6">
                <div class="min-w-0">
                    <h2 id="document-preview-title" class="truncate text-sm font-black text-slate-900 dark:text-white sm:text-base" x-text="title"></h2>
                    <p class="truncate text-[10px] text-slate-500 dark:text-slate-400 sm:text-xs">{{ __('Review the document, then print it when ready.') }}</p>
                </div>
                <button type="button" x-on:click="closePreview()" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-lg text-slate-500 hover:text-slate-900 dark:bg-slate-800 dark:hover:text-white" aria-label="{{ __('Close preview') }}">&times;</button>
            </header>
            <div class="min-h-0 flex-1 bg-slate-100 p-1.5 dark:bg-slate-950 sm:p-4">
                <iframe x-ref="documentPreviewFrame" :src="url" class="h-full w-full rounded-xl border border-slate-200 bg-white dark:border-slate-700" title="{{ __('Document preview') }}"></iframe>
            </div>
            <footer class="flex shrink-0 justify-end border-t border-slate-200 px-4 py-3 dark:border-slate-700 sm:px-6 sm:py-4">
                <button type="button" x-on:click="printPreview()" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#006aff] px-7 py-3.5 text-sm font-black text-white shadow-lg shadow-blue-500/25 transition hover:bg-[#0055d6] active:scale-[0.98] sm:w-auto sm:min-w-52 sm:text-base">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z" /></svg>
                    <span>{{ __('Print') }}</span>
                </button>
            </footer>
        </section>
    </div>
</div>
