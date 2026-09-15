<div class="space-y-5">
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold shadow-sm">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ __('Device Categories') }}</h2>
            <p class="text-xs text-slate-400">{{ __('Identifier type, supported brands and default intake checklist') }}</p>
        </div>
        <div class="flex gap-2">
            <button wire:click="seedDefaults" type="button" class="px-4 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition">{{ __('Add defaults') }}</button>
            <button wire:click="newCategory" type="button" class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-sky-600 hover:bg-sky-700 text-white shadow-md active:scale-95 transition">+ {{ __('New Category') }}</button>
        </div>
    </div>

    @if ($showForm)
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-black text-slate-900 dark:text-white">{{ $editingId ? __('Edit category') : __('Add category') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Category name *') }}</label>
                    <input type="text" wire:model="name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    @error('name') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Identifier type') }}</label>
                    <input type="text" wire:model="identifierType" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Brands (comma separated)') }}</label>
                    <input type="text" wire:model="brands" placeholder="Apple, Samsung, Xiaomi…" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Default checklist items (one per line)') }}</label>
                    <textarea wire:model="checklistItems" rows="4" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2.5 pt-1">
                <button wire:click="$set('showForm', false)" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition">{{ __('Cancel') }}</button>
                <button wire:click="save" type="button" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-sky-600 hover:bg-sky-700 text-white shadow-md active:scale-95 transition">{{ __('Save Category') }}</button>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-x-auto">
        <table class="w-full text-xs sm:text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3.5">{{ __('Category') }}</th>
                    <th class="px-5 py-3.5">{{ __('Identifier') }}</th>
                    <th class="px-5 py-3.5">{{ __('Checklist points') }}</th>
                    <th class="px-5 py-3.5 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @forelse ($categories as $c)
                    @php $meta = (array) ($c->metadata ?? []); @endphp
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                        <td class="px-5 py-3.5 font-bold text-slate-800 dark:text-slate-200">{{ $c->name }}</td>
                        <td class="px-5 py-3.5 text-slate-500">{{ $meta['identifier_type'] ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-slate-500">{{ count((array) ($meta['checklist_items'] ?? [])) }}</td>
                        <td class="px-5 py-3.5 text-right space-x-2">
                            <button wire:click="edit({{ $c->id }})" type="button" class="text-xs font-bold text-sky-600 hover:underline">{{ __('Edit') }}</button>
                            <button wire:click="delete({{ $c->id }})" wire:confirm="{{ __('Archive this category?') }}" type="button" class="text-xs font-bold text-rose-600 hover:underline">{{ __('Archive') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-12 text-center text-slate-400 text-xs">{{ __('No device categories. Click "Add defaults" to start.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
