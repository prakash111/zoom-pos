<div class="max-w-xl mx-auto">
    @if ($submitted)
        <div class="rounded-3xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 p-8 text-center">
            <div class="text-3xl mb-2">✅</div>
            <h3 class="text-lg font-black text-emerald-700 dark:text-emerald-300">{{ __('Message sent!') }}</h3>
            <p class="text-sm text-emerald-600 dark:text-emerald-400 mt-1">{{ __("Thanks for reaching out — we'll get back to you shortly.") }}</p>
            <button type="button" wire:click="$set('submitted', false)" class="mt-4 text-xs font-bold text-emerald-700 dark:text-emerald-300 hover:underline">
                {{ __('Send another message') }}
            </button>
        </div>
    @else
        <form wire:submit="submit" x-data="{ touched: {} }" class="space-y-4" novalidate>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Name *') }}</label>
                    <input type="text" wire:model="name" required minlength="2"
                           x-on:blur="touched.name = true"
                           :class="touched.name && !$el.value ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-200 dark:border-slate-700 focus:border-blue-500 focus:ring-blue-500'"
                           class="w-full rounded-xl dark:bg-slate-800 text-sm transition-colors">
                    @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Business Email *') }}</label>
                    <input type="email" wire:model="email" required
                           x-on:blur="touched.email = true"
                           :class="touched.email && !$el.checkValidity() ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-200 dark:border-slate-700 focus:border-blue-500 focus:ring-blue-500'"
                           placeholder="you@yourstore.com"
                           class="w-full rounded-xl dark:bg-slate-800 text-sm transition-colors">
                    @error('email') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Store Type') }}</label>
                    <select wire:model="storeType"
                            class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">{{ __('Select your business type…') }}</option>
                        <option value="Retail Store">{{ __('Retail Store') }}</option>
                        <option value="Supermarket / Grocery">{{ __('Supermarket / Grocery') }}</option>
                        <option value="Restaurant / Cafe">{{ __('Restaurant / Cafe') }}</option>
                        <option value="Bar / Lounge">{{ __('Bar / Lounge') }}</option>
                        <option value="Service Business">{{ __('Service Business') }}</option>
                        <option value="Other">{{ __('Other') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Phone') }}</label>
                    <input type="text" wire:model="phone" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm focus:border-blue-500 focus:ring-blue-500 transition-colors">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Message *') }}</label>
                <textarea wire:model="message" rows="4" required minlength="10"
                          x-on:blur="touched.message = true"
                          :class="touched.message && !$el.value ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-200 dark:border-slate-700 focus:border-blue-500 focus:ring-blue-500'"
                          placeholder="{{ __("Tell us a bit about your business and what you're looking for…") }}"
                          class="w-full rounded-xl dark:bg-slate-800 text-sm transition-colors"></textarea>
                @error('message') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                    class="w-full py-3.5 rounded-full bg-brand-lime hover:bg-brand-lime-dark text-slate-950 font-black text-sm shadow-lg shadow-brand-lime/25 transition active:scale-95 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">{{ __('Send Message →') }}</span>
                <span wire:loading wire:target="submit">{{ __('Sending Message...') }}</span>
            </button>
        </form>
    @endif
</div>
