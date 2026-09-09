<div class="space-y-5">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold shadow-sm">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('Service Catalog & Rates') }}</h2>
            <p class="text-xs text-slate-400">{{ __('Bookable services, their price and duration') }}</p>
        </div>
        <button wire:click="newService" type="button" class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-violet-600 hover:bg-violet-700 text-white shadow-md active:scale-95 transition">+ {{ __('New Service') }}</button>
    </div>

    @if ($showForm)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-black text-slate-900 dark:text-white">{{ $editingId ? __('Edit service') : __('Add new service') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Service name *') }}</label>
                    <input type="text" wire:model="name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Rate / price') }}</label>
                    <input type="number" step="0.01" min="0" wire:model="price" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Duration (minutes) *') }}</label>
                    <input type="number" min="1" wire:model="durationMinutes" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    @error('durationMinutes') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Description') }}</label>
                    <textarea wire:model="description" rows="2" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2.5 pt-1">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition">{{ __('Cancel') }}</button>
                <button wire:click="save" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-violet-600 hover:bg-violet-700 text-white shadow-md active:scale-95 transition">{{ __('Save Service') }}</button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-x-auto">
        <table class="w-full text-xs sm:text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3.5">{{ __('Service') }}</th>
                    <th class="px-5 py-3.5 text-right">{{ __('Rate') }}</th>
                    <th class="px-5 py-3.5 text-right">{{ __('Duration') }}</th>
                    <th class="px-5 py-3.5 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @forelse ($services as $s)
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3.5 font-bold text-slate-800 dark:text-slate-200">{{ $s->name }}
                            @if ($s->description)<span class="block text-[11px] font-normal text-slate-400">{{ $s->description }}</span>@endif
                        </td>
                        <td class="px-5 py-3.5 text-right">{{ number_format((float) ($s->sale_price ?: $s->price), 2) }}</td>
                        <td class="px-5 py-3.5 text-right text-slate-500">{{ (int) ($s->duration_minutes ?: 30) }} {{ __('min') }}</td>
                        <td class="px-5 py-3.5 text-right space-x-2">
                            <button wire:click="edit({{ $s->id }})" type="button" class="text-xs font-bold text-violet-600 hover:underline">{{ __('Edit') }}</button>
                            <button wire:click="delete({{ $s->id }})" wire:confirm="{{ __('Archive this service?') }}" type="button" class="text-xs font-bold text-rose-600 hover:underline">{{ __('Archive') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-12 text-center text-slate-400 text-xs">{{ __('No services yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
