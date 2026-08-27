<div class="space-y-6" x-data="{ slugTouched: {{ $slug ? 'true' : 'false' }} }">

    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Page Title *") }}</label>
                <input type="text" wire:model="title" placeholder="About Us"
                       x-on:input="if (!slugTouched) { $wire.set('slug', $event.target.value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')) }"
                       class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm focus:ring-blue-500 focus:border-blue-500">
                @error('title') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("URL Slug *") }}</label>
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
        <div class="space-y-2" wire:ignore>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                {{ __("Page Content") }}
            </label>

            <div x-data="tinyMCEEditor(@entangle('content'))"
                 x-init="init()"
                 class="w-full">
                <textarea x-ref="editorTextarea" id="page-content-editor" class="hidden">{!! $content !!}</textarea>
            </div>
        </div>
        @error('content') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center justify-between gap-3">
        <a wire:navigate.hover href="{{ route('superadmin.pages.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs transition">
            {{ __("Cancel") }}
        </a>
        <button type="button"
                wire:click="save"
                wire:loading.attr="disabled"
                class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs shadow-md shadow-blue-500/20 transition cursor-pointer">
            <span wire:loading.remove>{{ $submitLabel }}</span>
            <span wire:loading>{{ __('Saving...') }}</span>
        </button>
    </div>
</div>

@push('scripts')
<script>
function tinyMCEEditor(wireContent) {
    return {
        content: wireContent,
        editorInstance: null,

        init() {
            // Ensure TinyMCE script is available before mounting
            if (typeof tinymce === 'undefined') {
                setTimeout(() => this.init(), 50);
                return;
            }

            // Destroy existing instance if present (prevents ghost instances)
            if (tinymce.get('page-content-editor')) {
                tinymce.get('page-content-editor').remove();
            }

            this.$nextTick(() => {
                tinymce.init({
                    target: this.$refs.editorTextarea,
                    license_key: 'gpl',
                    height: 500,
                    menubar: 'file edit view insert format tools table',
                    plugins: [
                        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                        'insertdatetime', 'media', 'table', 'help', 'wordcount', 'directionality'
                    ],
                    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | removeformat code fullscreen',
                    font_size_formats: '8pt 10pt 12pt 14pt 16pt 18pt 24pt 36pt 48pt',
                    color_map: [
                        '000000', 'Black',
                        '1E293B', 'Dark Slate',
                        '2563EB', 'Primary Blue',
                        '16A34A', 'Emerald Green',
                        'DC2626', 'Danger Red',
                        'D97706', 'Amber',
                        '9333EA', 'Purple',
                        'FFFFFF', 'White'
                    ],
                    skin: (document.documentElement.getAttribute('data-theme') === 'dark' || document.documentElement.classList.contains('dark')) ? 'oxide-dark' : 'oxide',
                    content_css: (document.documentElement.getAttribute('data-theme') === 'dark' || document.documentElement.classList.contains('dark')) ? 'dark' : 'default',
                    branding: false,
                    promotion: false,
                    setup: (editor) => {
                        this.editorInstance = editor;

                        // Explicitly populate editor with saved content upon initialization
                        editor.on('init', () => {
                            if (this.content) {
                                editor.setContent(this.content);
                            }
                        });

                        // Sync changes to Livewire model
                        editor.on('change keyup NodeChange input', () => {
                            this.content = editor.getContent();
                        });

                        editor.on('blur', () => {
                            this.content = editor.getContent();
                        });
                    }
                });
            });

            // Watch for Livewire model updates from backend
            this.$watch('content', (newVal) => {
                if (this.editorInstance && newVal !== this.editorInstance.getContent()) {
                    this.editorInstance.setContent(newVal || '');
                }
            });
        }
    };
}

// Clean up instance before Turbo/SPA navigation caches the page
document.addEventListener('turbo:before-cache', () => {
    if (typeof tinymce !== 'undefined' && tinymce.get('page-content-editor')) {
        tinymce.get('page-content-editor').remove();
    }
});
</script>
@endpush
