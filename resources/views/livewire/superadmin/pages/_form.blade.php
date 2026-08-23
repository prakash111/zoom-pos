<div class="space-y-6" x-data="{ slugTouched: {{ $slug ? 'true' : 'false' }} }">

    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Page Title *</label>
                <input type="text" wire:model="title" placeholder="About Us"
                       x-on:input="if (!slugTouched) { $wire.set('slug', $event.target.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')) }"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm focus:ring-blue-500 focus:border-blue-500">
                @error('title') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">URL Slug *</label>
                <div class="flex items-center rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 overflow-hidden focus-within:ring-2 focus-within:ring-blue-500">
                    <span class="pl-3 text-xs font-mono text-slate-400">/pages/</span>
                    <input type="text" wire:model="slug" x-on:input="slugTouched = true"
                           class="w-full border-none bg-transparent text-sm font-mono focus:ring-0 py-2.5 pr-3">
                </div>
                @error('slug') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Meta Description</label>
                <textarea wire:model="metaDescription" rows="2" placeholder="Shown in search engine results and social link previews"
                          class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs focus:ring-blue-500 focus:border-blue-500"></textarea>
                @error('metaDescription') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer">
                <input type="checkbox" wire:model="isActive" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Active (publicly viewable)</span>
            </label>

            <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer">
                <input type="checkbox" wire:model="showInFooter" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Show link in public site footer</span>
            </label>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Page Content</label>
        <textarea id="page-content" wire:model="content" rows="20"></textarea>
    </div>

    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('superadmin.pages.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs transition">
            Cancel
        </a>
        <button type="button"
                x-on:click="if (window.tinymce && tinymce.get('page-content')) { $wire.set('content', tinymce.get('page-content').getContent()) }; setTimeout(() => $wire.save(), 30)"
                class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs shadow-md shadow-blue-500/20 transition cursor-pointer">
            {{ $submitLabel }}
        </button>
    </div>

    <script src="{{ asset('assets/libs/tinymce/tinymce.min.js') }}"></script>
    <script src="{{ asset('assets/js/tinymce-theme-handler.js') }}"></script>
    <script>
        (function () {
            const initPageEditor = () => {
                if (!window.tinymce) return;
                if (tinymce.get('page-content')) {
                    tinymce.get('page-content').remove();
                }
                tinymce.init({
                    selector: '#page-content',
                    height: 480,
                    menubar: false,
                    plugins: 'lists link image table code fullscreen media',
                    toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link image media table | code fullscreen',
                    skin: document.documentElement.classList.contains('dark') ? 'oxide-dark' : 'oxide',
                    content_css: document.documentElement.classList.contains('dark') ? 'dark' : 'default',
                    setup: function (editor) {
                        if (typeof liquidTinyMCEThemeHandlerInit === 'function') {
                            try { liquidTinyMCEThemeHandlerInit(editor); } catch (e) {}
                        }
                        editor.on('change undo redo', () => {
                            @this.set('content', editor.getContent());
                        });
                    },
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initPageEditor);
            } else {
                initPageEditor();
            }

            document.addEventListener('livewire:navigated', initPageEditor);
        })();
    </script>
</div>
