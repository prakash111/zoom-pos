{{--
    Native contact form for the public marketing site and contact page.

    Works with JavaScript disabled: a plain POST to `contact.store`, which
    redirects back with a `contact_success` flash. When Alpine is present it
    submits via fetch() for an inline success panel with no page reload.
    Renders custom dynamic fields configured by Superadmin, defaulting to the
    First Name, Last Name, Work Email, Phone (+Country Code), Job Title, Company Name,
    and Message layout.
--}}
@props([
    'fields' => null,
    'settings' => null,
])

@php
    $fields = $fields ?? get_contact_form_fields();
    $settings = $settings ?? get_contact_form_settings();
    $submitBtnText = $settings['submit_button_text'] ?: __('Submit');

    $hasExplicitName = collect($fields)->contains('name', 'name');
@endphp

<div class="w-full"
     x-data="{
        sending: false,
        sent: {{ session()->has('contact_success') ? 'true' : 'false' }},
        message: @js(session('contact_success') ?: ($settings['success_message'] ?? 'Message sent.')),
        firstName: @js(old('first_name', '')),
        lastName: @js(old('last_name', '')),
        rawName: @js(old('name', '')),
        get fullName() {
            const combined = [this.firstName, this.lastName].filter(Boolean).join(' ').trim();
            return combined || this.rawName;
        },
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
                this.firstName = '';
                this.lastName = '';
            } catch (_) {
                this.$refs.form.submit();
                return;
            } finally {
                this.sending = false;
            }
        }
     }">

    <!-- Success Feedback Alert -->
    <div x-show="sent" x-cloak
         class="rounded-2xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 p-8 text-center animate-in fade-in mb-4">
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
          x-on:submit.prevent="submit" class="space-y-4 sm:space-y-5" novalidate>
        @csrf

        {{-- Honeypot anti-spam protection --}}
        <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
            <label>Company website<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
        </div>

        {{-- Synced name field for backward compatibility --}}
        @if (! $hasExplicitName)
            <input type="hidden" name="name" :value="fullName">
        @endif

        @if (isset($errors) && $errors->any())
            <div class="rounded-xl border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-xs font-semibold text-rose-700 dark:text-rose-300">
                {{ __('Please check the highlighted fields and try again.') }}
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
            @foreach ($fields as $field)
                @php
                    $isFull = ($field['width'] ?? 'half') === 'full' || ($field['type'] ?? '') === 'textarea';
                    $colClass = $isFull ? 'col-span-1 sm:col-span-2' : 'col-span-1';
                    $fieldName = $field['name'];
                    $fieldId = 'cf_' . $fieldName;
                    $fieldLabel = $field['label'] ?? ucfirst(str_replace('_', ' ', $fieldName));
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
                                   class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 cursor-pointer">
                            <label for="{{ $fieldId }}" class="text-xs sm:text-sm font-semibold text-slate-800 dark:text-slate-200 cursor-pointer">
                                {{ $fieldLabel }}
                                @if ($isRequired)
                                    <span class="text-rose-500">*</span>
                                @endif
                            </label>
                        </div>
                    @elseif ($type === 'textarea')
                        <label for="{{ $fieldId }}" class="block text-xs sm:text-sm font-semibold text-slate-800 dark:text-slate-200 mb-1.5">
                            {{ $fieldLabel }}
                            @if ($isRequired)
                                <span class="text-rose-500">*</span>
                            @endif
                        </label>
                        <textarea id="{{ $fieldId }}"
                                  name="{{ $fieldName }}"
                                  rows="4"
                                  {{ $isRequired ? 'required minlength=10' : '' }}
                                  placeholder="{{ $placeholder ?: __('Enter message') }}"
                                  class="contact-form-input w-full px-4 py-2.5 sm:py-3 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-900/60 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-colors">{{ old($fieldName) }}</textarea>
                    @elseif ($type === 'select')
                        <label for="{{ $fieldId }}" class="block text-xs sm:text-sm font-semibold text-slate-800 dark:text-slate-200 mb-1.5">
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
                                class="contact-form-input w-full px-4 py-2.5 sm:py-3 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-900/60 text-sm text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-colors cursor-pointer">
                            <option value="">{{ $placeholder ?: __('Select…') }}</option>
                            @foreach ($optionsList as $opt)
                                <option value="{{ $opt }}" {{ old($fieldName) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                    @elseif ($type === 'tel' || $fieldName === 'phone')
                        <label for="{{ $fieldId }}" class="block text-xs sm:text-sm font-semibold text-slate-800 dark:text-slate-200 mb-1.5">
                            {{ $fieldLabel }}
                            @if ($isRequired)
                                <span class="text-rose-500">*</span>
                            @endif
                        </label>
                        @php
                            $defaultDialCode = \App\Services\Localization\PlatformRegionalService::defaultDialCode();
                            $selectedDialCode = old('phone_country', $defaultDialCode);
                            $dialCodesList = [
                                '+91' => '+91',
                                '+1' => '+1',
                                '+44' => '+44',
                                '+971' => '+971',
                                '+966' => '+966',
                                '+61' => '+61',
                                '+65' => '+65',
                                '+60' => '+60',
                                '+49' => '+49',
                                '+33' => '+33',
                                '+81' => '+81',
                                '+55' => '+55',
                                '+27' => '+27',
                                '+234' => '+234',
                                '+254' => '+254',
                                '+880' => '+880',
                                '+92' => '+92',
                            ];
                            if (! isset($dialCodesList[$defaultDialCode])) {
                                $dialCodesList = [$defaultDialCode => $defaultDialCode] + $dialCodesList;
                            }
                        @endphp
                        <div class="contact-phone-wrapper flex rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-900/60 overflow-hidden focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20 transition-colors">
                            <div class="relative flex items-center bg-slate-50 dark:bg-slate-800/80 border-r border-slate-200 dark:border-slate-700/80">
                                <select name="phone_country"
                                        aria-label="{{ __('Country Code') }}"
                                        class="contact-phone-select appearance-none bg-transparent pl-3 pr-6 py-2.5 sm:py-3 text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-300 focus:outline-none cursor-pointer">
                                    @foreach ($dialCodesList as $dCode => $dLabel)
                                        <option value="{{ $dCode }}" {{ $selectedDialCode === $dCode ? 'selected' : '' }}>{{ $dLabel }}</option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-1.5 text-slate-400">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>
                            <input id="{{ $fieldId }}"
                                   type="tel"
                                   name="{{ $fieldName }}"
                                   {{ $isRequired ? 'required' : '' }}
                                   value="{{ old($fieldName) }}"
                                   x-on:input="if ($event.target.value.startsWith('0')) $event.target.value = $event.target.value.replace(/^0+/, '')"
                                   placeholder="{{ $placeholder ?: __('Enter phone number') }}"
                                   class="contact-form-input flex-1 px-3.5 py-2.5 sm:py-3 bg-transparent text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 outline-none border-0 focus:ring-0">
                        </div>
                    @else
                        <label for="{{ $fieldId }}" class="block text-xs sm:text-sm font-semibold text-slate-800 dark:text-slate-200 mb-1.5">
                            {{ $fieldLabel }}
                            @if ($isRequired)
                                <span class="text-rose-500">*</span>
                            @endif
                        </label>
                        <input id="{{ $fieldId }}"
                               type="{{ $type }}"
                               name="{{ $fieldName }}"
                               @if ($fieldName === 'first_name') x-model="firstName" @endif
                               @if ($fieldName === 'last_name') x-model="lastName" @endif
                               @if ($fieldName === 'name') x-model="rawName" @endif
                               {{ $isRequired ? 'required' : '' }}
                               value="{{ old($fieldName) }}"
                               placeholder="{{ $placeholder }}"
                               class="contact-form-input w-full px-4 py-2.5 sm:py-3 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-900/60 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-colors">
                    @endif

                    @error($fieldName)
                        <p class="text-rose-600 dark:text-rose-400 text-xs mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
            <button type="submit" x-bind:disabled="sending"
                    class="w-full py-3.5 px-6 rounded-xl sm:rounded-2xl font-bold text-sm sm:text-base tracking-wide text-white bg-gradient-to-r from-blue-600 via-blue-600 to-blue-500 hover:from-blue-700 hover:to-blue-600 focus:ring-4 focus:ring-blue-500/25 shadow-lg shadow-blue-500/25 active:scale-[0.99] transition-all duration-200 cursor-pointer disabled:opacity-60">
                <span x-show="!sending">{{ $submitBtnText }}</span>
                <span x-show="sending" x-cloak class="inline-flex items-center gap-2">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    {{ __('Sending Message...') }}
                </span>
            </button>
        </div>
    </form>
</div>
