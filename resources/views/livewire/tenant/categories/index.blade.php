<div class="space-y-5">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('Product Categories') }}</h2>
            <p class="text-xs text-slate-400">{{ __('Organize products into POS categories') }}</p>
        </div>

        <button wire:click="newCategory"
                type="button"
                class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            <span>+ {{ __('New Category') }}</span>
        </button>
    </div>

    @if ($showForm)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-black text-slate-900 dark:text-white">
                {{ $editingId ? __('Edit Category') : __('Add New Category') }}
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Category Name *') }}</label>
                    <input type="text" wire:model="name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Badge Color') }}</label>
                    <div class="flex items-center gap-2">
                        <input type="color" wire:model="color" class="w-10 h-10 rounded-xl cursor-pointer border-0 p-0">
                        <input type="text" wire:model="color" class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-mono uppercase">
                    </div>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Description (optional)') }}</label>
                    <input type="text" wire:model="description" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div class="flex justify-end gap-2.5 pt-2">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition">
                    {{ __('Cancel') }}
                </button>
                <button wire:click="save" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition">
                    {{ __('Save Category') }}
                </button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_20px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <table class="w-full text-xs sm:text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3.5">{{ __('Category') }}</th>
                    <th class="px-5 py-3.5">{{ __('Description') }}</th>
                    <th class="px-5 py-3.5 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @forelse ($categories as $cat)
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3.5 flex items-center gap-3">
                            <x-pos-product-icon :name="$cat->name" :category="$cat->name" size="xs" />
                            <div class="font-bold text-slate-800 dark:text-slate-200">{{ $cat->name }}</div>
                        </td>
                        <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400 text-xs">{{ $cat->description ?: '—' }}</td>
                        <td class="px-5 py-3.5 text-right space-x-2">
                            <button wire:click="edit({{ $cat->id }})" type="button" class="text-xs font-bold text-blue-600 hover:underline px-2 py-1 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-950/40 transition">{{ __('Edit') }}</button>
                            <button wire:click="delete({{ $cat->id }})" wire:confirm="{{ __('Delete this category?') }}" type="button" class="text-xs font-bold text-rose-600 hover:underline px-2 py-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition">{{ __('Delete') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-5 py-12 text-center text-slate-400 text-xs">
                            {{ __('No categories yet. Click "+ New Category" to create one.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
