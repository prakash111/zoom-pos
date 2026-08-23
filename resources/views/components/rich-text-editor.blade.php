@props([
    'id' => null,
    'placeholder' => '',
    'height' => 180,
])
@php
    $id = $id ?? 'tinymce-' . str_replace('.', '-', $attributes->wire('model')->value() ?: uniqid());
@endphp

<div wire:ignore
     x-data="{
         value: @entangle($attributes->wire('model')),
         instance: null,
         initEditor() {
             if (!window.tinymce) return;
             if (tinymce.get('{{ $id }}')) {
                 try { tinymce.get('{{ $id }}').remove(); } catch(e) {}
             }
             tinymce.init({
                 selector: '#{{ $id }}',
                 height: {{ $height }},
                 menubar: false,
                 statusbar: false,
                 placeholder: '{{ addslashes($placeholder) }}',
                 plugins: 'lists link code',
                 toolbar: 'bold italic underline | bullist numlist | link | removeformat code',
                 skin: document.documentElement.classList.contains('dark') ? 'oxide-dark' : 'oxide',
                 content_css: document.documentElement.classList.contains('dark') ? 'dark' : 'default',
                 content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; font-size: 13px; line-height: 1.5; }',
                 setup: (editor) => {
                     this.instance = editor;
                     editor.on('init', () => {
                         if (this.value) {
                             editor.setContent(this.value);
                         }
                     });
                     editor.on('change keyup undo redo blur', () => {
                         this.value = editor.getContent();
                     });
                 }
             });
         }
     }"
     x-init="
         $nextTick(() => initEditor());
         $watch('value', (newVal) => {
             if (instance && instance.getContent() !== newVal) {
                 instance.setContent(newVal || '');
             }
         });
     "
     class="w-full">
    <textarea id="{{ $id }}" {{ $attributes->except(['wire:model', 'wire:model.live', 'wire:model.defer', 'wire:model.blur']) }} class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500"></textarea>
</div>
