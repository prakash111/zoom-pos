{{--
    Native contact form for the public marketing site.

    Works with JavaScript disabled: a plain POST to `contact.store`, which
    redirects back with a `contact_success` flash. When Alpine is present it
    submits via fetch() for an inline success panel with no page reload.
--}}
@props([])

<div class="max-w-xl mx-auto"
     x-data="{
        sending: false,
        sent: {{ session()->has('contact_success') ? 'true' : 'false' }},
        message: @js(session('contact_success') ?: ''),
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
         class="rounded-3xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 p-8 text-center">
        <div class="text-3xl mb-2">✅</div>
        <h3 class="text-lg font-black text-emerald-700 dark:text-emerald-300">{{ __('Message sent!') }}</h3>
        <p class="text-sm text-emerald-600 dark:text-emerald-400 mt-1" x-text="message">
            {{ __("Thanks for reaching out — we'll get back to you shortly.") }}
        </p>
        <button type="button" x-on:click="sent = false"
                class="mt-4 text-xs font-bold text-emerald-700 dark:text-emerald-300 hover:underline">
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

        @if ($errors->any())
            <div class="rounded-xl border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 text-xs font-semibold text-rose-700 dark:text-rose-300">
                {{ __('Please check the highlighted fields and try again.') }}
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
            <!-- Name -->
            <div>
                <label for="cf_name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                    {{ __('Name') }} <span class="text-rose-500">*</span>
                </label>
                <input id="cf_name" type="text" name="name" required minlength="2" value="{{ old('name') }}" placeholder="John Doe"
                       class="contact-form-input w-full px-4 py-3 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors">
                @error('name') <p class="text-rose-600 dark:text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Business Email -->
            <div>
                <label for="cf_email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                    {{ __('Business Email') }} <span class="text-rose-500">*</span>
                </label>
                <input id="cf_email" type="email" name="email" required value="{{ old('email') }}"
                       placeholder="you@yourstore.com"
                       class="contact-form-input w-full px-4 py-3 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors">
                @error('email') <p class="text-rose-600 dark:text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Store Type -->
            <div>
                <label for="cf_store_type" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                    {{ __('Store Type') }}
                </label>
                <select id="cf_store_type" name="store_type"
                        class="contact-form-input w-full px-4 py-3 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors">
                    <option value="">{{ __('Select your business type…') }}</option>
                    <option value="retail">{{ __('Retail & Supermarket') }}</option>
                    <option value="restaurant">{{ __('Restaurant / Cafe / QSR') }}</option>
                    <option value="salon">{{ __('Salon & Spa') }}</option>
                    <option value="pharmacy">{{ __('Pharmacy') }}</option>
                    <option value="service">{{ __('Service Business') }}</option>
                    <option value="other">{{ __('Other') }}</option>
                </select>
            </div>

            <!-- Phone -->
            <div>
                <label for="cf_phone" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                    {{ __('Phone') }}
                </label>
                <input id="cf_phone" type="tel" name="phone" value="{{ old('phone') }}" placeholder="+1 (555) 000-0000"
                       class="contact-form-input w-full px-4 py-3 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors">
            </div>
        </div>

        <!-- Message -->
        <div class="mb-6">
            <label for="cf_message" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                {{ __('Message') }} <span class="text-rose-500">*</span>
            </label>
            <textarea id="cf_message" name="message" rows="4" required minlength="10"
                      placeholder="{{ __("Tell us a bit about your business and what you're looking for…") }}"
                      class="contact-form-input w-full px-4 py-3 rounded-lg border text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors">{{ old('message') }}</textarea>
            @error('message') <p class="text-rose-600 dark:text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <!-- Submit Button -->
        <button type="submit" x-bind:disabled="sending"
                class="w-full py-3.5 px-6 rounded-lg font-bold text-sm tracking-wide text-slate-900 bg-emerald-400 hover:bg-emerald-300 transition-colors shadow-md disabled:opacity-60">
            <span x-show="!sending">{{ __('Send Message') }}</span>
            <span x-show="sending" x-cloak>{{ __('Sending Message...') }}</span>
        </button>
    </form>
</div>
