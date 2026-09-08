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

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="cf_name" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Name *') }}</label>
                <input id="cf_name" type="text" name="name" required minlength="2" value="{{ old('name') }}"
                       class="w-full rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 dark:border-white/15 dark:bg-white/5 dark:text-white text-sm focus:border-brand-lime focus:ring-brand-lime transition-colors">
                @error('name') <p class="text-rose-600 dark:text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="cf_email" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Business Email *') }}</label>
                <input id="cf_email" type="email" name="email" required value="{{ old('email') }}"
                       placeholder="you@yourstore.com"
                       class="w-full rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 dark:border-white/15 dark:bg-white/5 dark:text-white text-sm focus:border-brand-lime focus:ring-brand-lime transition-colors">
                @error('email') <p class="text-rose-600 dark:text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="cf_store_type" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Store Type') }}</label>
                <select id="cf_store_type" name="store_type"
                        class="w-full rounded-xl border border-slate-300 bg-white text-slate-900 dark:border-white/15 dark:bg-white/5 dark:text-white text-sm focus:border-brand-lime focus:ring-brand-lime">
                    <option value="" class="bg-white dark:bg-slate-900">{{ __('Select your business type…') }}</option>
                    @foreach (['Retail Store', 'Supermarket / Grocery', 'Restaurant / Cafe', 'Bar / Lounge', 'Service Business', 'Other'] as $type)
                        <option value="{{ $type }}" class="bg-white dark:bg-slate-900" @selected(old('store_type') === $type)>{{ __($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="cf_phone" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Phone') }}</label>
                <input id="cf_phone" type="text" name="phone" value="{{ old('phone') }}"
                       class="w-full rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 dark:border-white/15 dark:bg-white/5 dark:text-white text-sm focus:border-brand-lime focus:ring-brand-lime transition-colors">
            </div>
        </div>

        <div>
            <label for="cf_message" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Message *') }}</label>
            <textarea id="cf_message" name="message" rows="4" required minlength="10"
                      placeholder="{{ __("Tell us a bit about your business and what you're looking for…") }}"
                      class="w-full rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 dark:border-white/15 dark:bg-white/5 dark:text-white text-sm focus:border-brand-lime focus:ring-brand-lime transition-colors">{{ old('message') }}</textarea>
            @error('message') <p class="text-rose-600 dark:text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit" x-bind:disabled="sending"
                class="w-full py-3.5 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 font-black text-sm shadow-lg shadow-brand-lime/25 transition active:scale-95 disabled:opacity-60">
            <span x-show="!sending">{{ __('Send Message →') }}</span>
            <span x-show="sending" x-cloak>{{ __('Sending Message...') }}</span>
        </button>
    </form>
</div>
