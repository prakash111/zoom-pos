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

    <!-- Available modules (catalog from the License Manager) -->
    @if (isset($purchasable) && $purchasable->isNotEmpty())
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4">
            <div>
                <h4 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">{{ __("Available modules") }}</h4>
                <p class="text-xs text-slate-400">{{ __("Buy a module (a key is issued and this site fetches + installs it), or paste a key you already have to download and activate it now.") }}</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($purchasable as $p)
                    <div class="rounded-2xl border border-slate-100 dark:border-slate-800 p-4 flex flex-col gap-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $p['name'] }}</span>
                            <span class="font-mono text-[11px] text-slate-400">{{ $p['slug'] }}</span>
                        </div>
                        @if (($p['type'] ?? 'core') === 'extension')
                            <span class="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400">{{ __('Optional Extension') }} · {{ __('Super Admin activation only') }}</span>
                        @endif
                        @if (! empty($p['description']))
                            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2">{{ $p['description'] }}</p>
                        @endif
                        <div class="text-sm font-black text-slate-900 dark:text-white mt-1">
                            @if (($p['price'] ?? 0) > 0){{ $p['currency'] }} {{ number_format($p['price'], 2) }}@else<span class="text-slate-400 font-bold">{{ __('Price on the store') }}</span>@endif
                        </div>

                        @if (! empty($p['store_link']))
                            <a href="{{ $p['store_link'] }}" target="_blank" rel="noopener noreferrer"
                               class="mt-2 px-3 py-1.5 rounded-xl text-xs font-extrabold bg-emerald-600 hover:bg-emerald-700 text-white text-center">{{ __("Buy module") }} ↗</a>
                        @endif

                        <div class="mt-2 pt-2 border-t border-slate-100 dark:border-slate-800 space-y-1.5">
                            <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400">{{ __("Already have a key?") }}</p>
                            <input type="text" wire:model.defer="catalogKeys.{{ $p['slug'] }}"
                                   placeholder="{{ __('License key') }}"
                                   class="w-full text-xs font-mono rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2">
                            @error('catalogKeys.'.$p['slug'])
                                <p class="text-[11px] text-rose-600 font-bold">{{ $message }}</p>
                            @enderror
                            <button type="button" wire:click="getModule('{{ $p['slug'] }}')" wire:loading.attr="disabled"
                                    class="w-full px-3 py-1.5 rounded-xl text-xs font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white">
                                <span wire:loading.remove wire:target="getModule">{{ __("Download & Activate") }}</span>
                                <span wire:loading wire:target="getModule">{{ __("Working…") }}</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Installed Modules Table -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
            <h4 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">{{ __("Installed Modules") }}</h4>
            <p class="text-xs text-slate-400">{{ __("Modules fetched from the License Manager") }}</p>
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
                            <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                                {{ $module->name }}
                                @if ($module->isExtension())
                                    <span class="block mt-1 text-[10px] font-semibold text-indigo-600 dark:text-indigo-400">{{ __('Extension') }} · {{ __('Super Admin activation only') }}</span>
                                @endif
                            </td>
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
                                            @if (! empty($catalog[$module->id]['store_link']))
                                                <a href="{{ $catalog[$module->id]['store_link'] }}" target="_blank" rel="noopener noreferrer" class="text-emerald-600 dark:text-emerald-400 hover:underline font-bold text-xs">
                                                    {{ __("Buy") }}@if (($catalog[$module->id]['price'] ?? 0) > 0) {{ $catalog[$module->id]['currency'] }} {{ number_format($catalog[$module->id]['price'], 2) }}@endif ↗
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
                                {{ __("No modules installed yet — buy or activate one from Available modules above.") }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
