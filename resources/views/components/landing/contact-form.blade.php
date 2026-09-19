{{--
    Native contact form for the public marketing site and contact page.

    Works with JavaScript disabled: a plain POST to `contact.store`, which
    redirects back with a `contact_success` flash. When Alpine is present it
    submits via fetch() for an inline success panel with no page reload.
    Renders custom dynamic fields configured by Superadmin.
--}}
@props([
    'fields' => null,
    'settings' => null,
])

@php
    $fields = $fields ?? get_contact_form_fields();
    $settings = $settings ?? get_contact_form_settings();
    $submitBtnText = $settings['submit_button_text'] ?: __('Send Message');
@endphp

<div class="max-w-2xl mx-auto"
     x-data="{
        sending: false,
        sent: {{ session()->has('contact_success') ? 'true' : 'false' }},
        message: @js(session('contact_success') ?: ($settings['success_message'] ?? 'Message sent.')),
        async submit(e) {
            if (!this.$refs.form.reportValidity()) return;
            this.sending = true;
            try {
                const res = await fetch(this.$refs.form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(this.$refs.form),
                });
                if (!res.ok) throw new Error('bad status');
                const data = await res.json();
                this.message = data.message || 'Message sent.';
                this.sent = true;
                this.$refs.form.reset();
            } catch (_) {
                this.$refs.form.submit(); // fall back to a full page POST
                return;
            } finally {
                this.sending = false;
            }
        }
     }">

    <div x-show="sent" x-cloak
         class="rounded-3xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 p-8 text-center animate-in fade-in">
        <div class="text-3xl mb-2">✅</div>
        <h3 class="text-lg font-black text-emerald-700 dark:text-emerald-300">{{ __('Message sent!') }}</h3>
        <p class="text-sm text-emerald-600 dark:text-emerald-400 mt-1" x-text="message">
            {{ $settings['success_message'] ?: __("Thanks for reaching out — we'll get back to you shortly.") }}
        </p>
        <button type="button" x-on:click="sent = false"
                class="mt-4 text-xs font-bold text-emerald-700 dark:text-emerald-300 hover:underline cursor-pointer">
            {{ __('Send another message') }}
        </button>
    </div>

    <form x-ref="form" x-show="!sent" method="POST" action="{{ route('contact.store') }}"
          x-on:submit.prevent="submit" class="space-y-4" novalidate>
        @csrf

        {{-- Honeypot — hidden from humans, catnip for bots. --}}
        <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
            <label>Company website<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
        </div>

        @if (isset($errors) && $errors->any())
            <div class="rounded-xl border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-xs font-semibold text-rose-700 dark:text-rose-300">
                {{ __('Please check the highlighted fields and try again.') }}
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
            @foreach ($fields as $field)
                @php
                    $isFull = ($field['width'] ?? 'half') === 'full' || ($field['type'] ?? '') === 'textarea';
                    $colClass = $isFull ? 'col-span-1 sm:col-span-2' : 'col-span-1';
                    $fieldName = $field['name'];
                    $fieldId = 'cf_' . $fieldName;
                    $fieldLabel = $field['label'] ?? ucfirst($fieldName);
                    $isRequired = !empty($field['required']);
                    $placeholder = $field['placeholder'] ?? '';
                    $type = $field['type'] ?? 'text';
                @endphp

                <div class="{{ $colClass }}">
                    @if ($type === 'checkbox')
                        <div class="flex items-center gap-3 pt-2">
                            <input id="{{ $fieldId }}"
                                   type="checkbox"
                                   name="{{ $fieldName }}"
                                   value="1"
                                   {{ old($fieldName) ? 'checked' : '' }}
                                   {{ $isRequired ? 'required' : '' }}
                                   class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300 dark:border-slate-700">
                            <label for="{{ $fieldId }}" class="text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                                {{ $fieldLabel }}
                                @if ($isRequired)
                                    <span class="text-rose-500">*</span>
                                @endif
                            </label>
                        </div>
                    @elseif ($type === 'textarea')
                        <label for="{{ $fieldId }}" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                            {{ $fieldLabel }}
                            @if ($isRequired)
                                <span class="text-rose-500">*</span>
                            @endif
                        </label>
                        <textarea id="{{ $fieldId }}"
                                  name="{{ $fieldName }}"
                                  rows="4"
                                  {{ $isRequired ? 'required minlength=10' : '' }}
                                  placeholder="{{ $placeholder }}"
                                  class="contact-form-input w-full px-4 py-3 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors">{{ old($fieldName) }}</textarea>
                    @elseif ($type === 'select')
                        <label for="{{ $fieldId }}" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                            {{ $fieldLabel }}
                            @if ($isRequired)
                                <span class="text-rose-500">*</span>
                            @endif
                        </label>
                        @php
                            $rawOptions = $field['options'] ?? '';
                            $optionsList = is_array($rawOptions) ? $rawOptions : array_filter(array_map('trim', explode(',', (string) $rawOptions)));
                        @endphp
                        <select id="{{ $fieldId }}"
                                name="{{ $fieldName }}"
                                {{ $isRequired ? 'required' : '' }}
                                class="contact-form-input w-full px-4 py-3 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors">
                            <option value="">{{ $placeholder ?: __('Select…') }}</option>
                            @foreach ($optionsList as $opt)
                                <option value="{{ $opt }}" {{ old($fieldName) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                    @else
                        <label for="{{ $fieldId }}" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                            {{ $fieldLabel }}
                            @if ($isRequired)
                                <span class="text-rose-500">*</span>
                            @endif
                        </label>
                        <input id="{{ $fieldId }}"
                               type="{{ $type }}"
                               name="{{ $fieldName }}"
                               {{ $isRequired ? 'required' : '' }}
                               value="{{ old($fieldName) }}"
                               placeholder="{{ $placeholder }}"
                               class="contact-form-input w-full px-4 py-3 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors">
                    @endif

                    @error($fieldName)
                        <p class="text-rose-600 dark:text-rose-400 text-xs mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <!-- Submit Button -->
        <button type="submit" x-bind:disabled="sending"
                class="w-full py-3.5 px-6 rounded-lg font-bold text-sm tracking-wide text-slate-900 bg-emerald-400 hover:bg-emerald-300 transition-colors shadow-md disabled:opacity-60 cursor-pointer">
            <span x-show="!sending">{{ $submitBtnText }}</span>
            <span x-show="sending" x-cloak>{{ __('Sending Message...') }}</span>
        </button>
    </form>
</div>
