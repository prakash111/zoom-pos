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

    <div class="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
        <span>{{ __("License driver") }}:</span>
        <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 uppercase tracking-wide">{{ $licenseDriver }}</span>
        <a href="{{ route('superadmin.settings.index', ['tab' => 'licensing']) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __("Change") }}</a>
    </div>

    @if ($offlineFallback)
        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 text-xs font-bold border border-amber-200 dark:border-amber-800">
            {{ __("License server is not configured — module keys are only format-checked. Set a license server URL under Settings → Licensing for strict verification.") }}
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

    @if (! empty($orphans))
        <div class="bg-amber-50 dark:bg-amber-950/30 rounded-3xl p-6 border border-amber-200 dark:border-amber-800 space-y-3">
            <h4 class="font-extrabold text-sm text-amber-800 dark:text-amber-300 flex items-center gap-2">
                ⚠ {{ __("Leftover module files") }}
            </h4>
            <p class="text-xs text-amber-700/80 dark:text-amber-400/80">
                {{ __("These directories are on disk under modules/ but have no registered module. Safe to delete — they are not loaded or shown as store types.") }}
            </p>
            <ul class="divide-y divide-amber-200/70 dark:divide-amber-800/60">
                @foreach ($orphans as $slug => $path)
                    <li class="flex items-center justify-between py-2.5 gap-4">
                        <code class="text-xs font-bold text-amber-900 dark:text-amber-200">modules/{{ $slug }}</code>
                        <button wire:click="pruneOrphan('{{ $slug }}')"
                                wire:confirm="{{ __('Permanently delete the modules/:slug directory from disk?', ['slug' => $slug]) }}"
                                type="button"
                                class="text-rose-700 dark:text-rose-400 hover:underline font-bold text-xs shrink-0">{{ __("Delete files") }}</button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

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
                                <div class="flex flex-col gap-1 items-start">
                                    @if ($module->is_active)
                                        <span class="px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-[11px] font-extrabold">{{ __("Active") }}</span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 text-[11px] font-extrabold">{{ __("Inactive") }}</span>
                                    @endif

                                    @if ($module->requires_license)
                                        @php
                                            $ls = $module->license_status;
                                            $lsClass = match ($ls) {
                                                'active' => 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300',
                                                'expired' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300',
                                                'revoked' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300',
                                                default => 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400',
                                            };
                                        @endphp
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-extrabold {{ $lsClass }}">
                                            {{ __("License") }}: {{ ucfirst($ls) }}
                                        </span>
                                        @if ($ls === 'active')
                                            <span class="text-[10px] font-mono text-slate-400">
                                                {{ $module->license_driver ?? '—' }} ·
                                                {{ $module->license_expires_at ? $module->license_expires_at->format('Y-m-d') : __('no expiry') }}
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-400 text-xs font-mono">
                                {{ $module->installed_at?->format('Y-m-d H:i:s') ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap align-top">
                                @php $needsKey = $module->requires_license && $module->license_status !== 'active'; @endphp

                                @if ($module->is_active)
                                    <div class="space-x-3">
                                        <button wire:click="deactivate({{ $module->id }})" wire:confirm="{{ __("Deactivate this module? Its navigation and routes stop working (and it is removed from selectable store types), but its data is kept.") }}" type="button" class="text-amber-600 hover:underline font-bold">{{ __("Deactivate") }}</button>
                                        @if ($module->requires_license)
                                            <button wire:click="revalidateLicense({{ $module->id }})" type="button" class="text-slate-500 hover:underline font-bold">{{ __("Re-check license") }}</button>
                                        @endif
                                    </div>
                                @elseif ($needsKey)
                                    <div class="flex flex-col items-end gap-1.5 max-w-xs ml-auto">
                                        <input type="text" wire:model.defer="licenseKeys.{{ $module->id }}"
                                               placeholder="{{ __('Enter license key') }}"
                                               class="w-56 text-xs font-mono rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-slate-800 dark:text-slate-100">
                                        @error('licenseKeys.'.$module->id)
                                            <p class="text-[11px] text-rose-600 font-bold text-right">{{ $message }}</p>
                                        @enderror
                                        <div class="flex items-center gap-3">
                                            @if (($catalog[$module->id]['buy_enabled'] ?? false) && ($catalog[$module->id]['price'] ?? 0) > 0)
                                                <a href="{{ route('superadmin.modules.buy', $module->slug) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline font-bold text-xs">
                                                    {{ __("Buy") }} {{ $catalog[$module->id]['currency'] }} {{ number_format($catalog[$module->id]['price'], 2) }}
                                                </a>
                                            @endif
                                            <button wire:click="activate({{ $module->id }})" type="button" class="px-3 py-1.5 rounded-xl text-xs font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white">{{ __("Verify & Activate") }}</button>
                                        </div>
                                    </div>
                                @else
                                    <div class="space-x-3">
                                        <button wire:click="activate({{ $module->id }})" wire:confirm="{{ __("Activate this module and run its migrations?") }}" type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold">{{ __("Activate") }}</button>
                                        @if ($module->requires_license)
                                            <button wire:click="revalidateLicense({{ $module->id }})" type="button" class="text-slate-500 hover:underline font-bold">{{ __("Re-check license") }}</button>
                                        @endif
                                    </div>
                                @endif

                                <div class="space-x-3 mt-2">
                                    <button wire:click="uninstall({{ $module->id }}, false)" wire:confirm="{{ __("Uninstall this module? Its files will be removed but its data tables are kept.") }}" type="button" class="text-rose-600 hover:underline font-bold">{{ __("Uninstall") }}</button>
                                    <button wire:click="uninstall({{ $module->id }}, true)" wire:confirm="{{ __("Uninstall this module AND drop its data tables? This cannot be undone.") }}" type="button" class="text-rose-800 hover:underline font-bold">{{ __("Uninstall + Drop Data") }}</button>
                                </div>
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
