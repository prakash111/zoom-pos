<div class="space-y-6">

    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2 border border-emerald-200 dark:border-emerald-800 shadow-sm animate-in fade-in">
            <svg class="w-5 h-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 text-xs sm:text-sm font-bold flex items-center gap-2 border border-rose-200 dark:border-rose-800 shadow-sm animate-in fade-in">
            <svg class="w-5 h-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Upload Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4">
        <div>
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>🧩 {{ __("Install a Module Package") }}</span>
            </h3>
            <p class="text-xs text-slate-400">{{ __("Upload a .zip containing module.json to register a new business module. It installs inactive — review it, then Activate.") }}</p>
        </div>

        <form wire:submit="install" class="flex flex-wrap gap-4 items-end pt-2">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Module Archive (.zip, max 10MB)") }}</label>
                <input type="file" wire:model="zipFile" accept=".zip" class="text-xs sm:text-sm font-medium text-slate-600 dark:text-slate-300 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-300">
                @error('zipFile')
                    <p class="text-xs text-rose-600 font-bold mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="zipFile,install" class="px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/25 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
                <span wire:loading.remove wire:target="install">⬆ {{ __("Install Module") }}</span>
                <span wire:loading wire:target="install">{{ __("Installing…") }}</span>
            </button>
        </form>
    </div>

    <!-- Installed Modules Table -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
            <h4 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">{{ __("Installed Modules") }}</h4>
            <p class="text-xs text-slate-400">{{ __("Package-based modules installed via ZIP upload") }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-extrabold text-left border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-3.5">{{ __("Name") }}</th>
                        <th class="px-6 py-3.5">{{ __("Slug") }}</th>
                        <th class="px-6 py-3.5">{{ __("Version") }}</th>
                        <th class="px-6 py-3.5">{{ __("Author") }}</th>
                        <th class="px-6 py-3.5">{{ __("Status") }}</th>
                        <th class="px-6 py-3.5">{{ __("Installed At") }}</th>
                        <th class="px-6 py-3.5 text-right">{{ __("Actions") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($modules as $module)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">{{ $module->name }}</td>
                            <td class="px-6 py-4 font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ $module->slug }}</td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ $module->version ?? '—' }}</td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ $module->author ?? '—' }}</td>
                            <td class="px-6 py-4">
                                @if ($module->is_active)
                                    <span class="px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-[11px] font-extrabold">{{ __("Active") }}</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 text-[11px] font-extrabold">{{ __("Inactive") }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-slate-400 text-xs font-mono">
                                {{ $module->installed_at?->format('Y-m-d H:i:s') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                                @if ($module->is_active)
                                    <button wire:click="deactivate({{ $module->id }})" wire:confirm="{{ __("Deactivate this module? Its navigation and routes will stop working, but its data is kept.") }}" type="button" class="text-amber-600 hover:underline font-bold">{{ __("Deactivate") }}</button>
                                @else
                                    <button wire:click="activate({{ $module->id }})" wire:confirm="{{ __("Activate this module and run its migrations?") }}" type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold">{{ __("Activate") }}</button>
                                @endif
                                <button wire:click="uninstall({{ $module->id }}, false)" wire:confirm="{{ __("Uninstall this module? Its files will be removed but its data tables are kept.") }}" type="button" class="text-rose-600 hover:underline font-bold">{{ __("Uninstall") }}</button>
                                <button wire:click="uninstall({{ $module->id }}, true)" wire:confirm="{{ __("Uninstall this module AND drop its data tables? This cannot be undone.") }}" type="button" class="text-rose-800 hover:underline font-bold">{{ __("Uninstall + Drop Data") }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                {{ __("No package-based modules installed yet. Upload a .zip above to get started.") }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
