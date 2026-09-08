<div class="space-y-6">
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800">
        <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ __('Licensing') }}</h3>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            {{ __('Choose how license keys are verified. "CodeCanyon" validates purchase codes against the Envato Market API; "Custom" validates against your self-hosted license server. Modules re-verify daily and auto-deactivate if a key is revoked or expires.') }}
        </p>
    </div>

    @if ($licenseOfflineFallback)
        <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 text-xs font-bold border border-amber-200 dark:border-amber-800">
            {{ __('Custom driver is active but no license server URL is set — module keys are only format-checked. Set a URL below for strict verification.') }}
        </div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-200 dark:border-slate-800 space-y-5">
        <div>
            <label class="block mb-1 text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('License driver') }}</label>
            <select wire:model="licenseDriver" class="w-full sm:w-72 rounded-xl dark:bg-slate-800 text-sm">
                <option value="custom">{{ __('Custom License Server') }}</option>
                <option value="codecanyon">{{ __('CodeCanyon / Envato Market API') }}</option>
            </select>
            @error('licenseDriver') <p class="text-[11px] text-rose-600 font-bold mt-1">{{ $message }}</p> @enderror
            <p class="text-[11px] text-slate-400 mt-1">{{ __('Effective driver in use') }}: <code>{{ $licenseDriverEffective }}</code></p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block mb-1 text-xs font-bold">{{ __('Envato API Personal Token') }}</label>
                <input type="password" wire:model="envatoApiToken"
                       placeholder="{{ $hasEnvatoApiToken ? '•••••••• (leave blank to keep)' : __('Not configured') }}"
                       class="w-full rounded-xl dark:bg-slate-800">
                @error('envatoApiToken') <p class="text-[11px] text-rose-600 font-bold mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block mb-1 text-xs font-bold">{{ __('License Server URL') }}</label>
                <input type="url" wire:model="licenseServerUrl" placeholder="https://license.example.com"
                       class="w-full rounded-xl dark:bg-slate-800">
                @error('licenseServerUrl') <p class="text-[11px] text-rose-600 font-bold mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block mb-1 text-xs font-bold">{{ __('License Server Secret') }}</label>
                <input type="password" wire:model="licenseServerSecret"
                       placeholder="{{ $hasLicenseServerSecret ? '•••••••• (leave blank to keep)' : __('Not configured') }}"
                       class="w-full rounded-xl dark:bg-slate-800">
                @error('licenseServerSecret') <p class="text-[11px] text-rose-600 font-bold mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="button" wire:click="saveLicensing"
                    class="px-6 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-extrabold shadow-lg">
                {{ __('Save Licensing Settings') }}
            </button>
        </div>
    </div>

    {{-- Core product license (read-only) --}}
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
        <div class="flex items-center justify-between">
            <h4 class="font-extrabold text-slate-900 dark:text-white">{{ __('Core product license') }}</h4>
            @php
                $coreClass = match ($coreLicense['status']) {
                    'ok' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300',
                    'warn' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300',
                    default => 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400',
                };
            @endphp
            <span class="px-2.5 py-1 rounded-full text-[11px] font-extrabold {{ $coreClass }}">{{ strtoupper($coreLicense['status']) }}</span>
        </div>
        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-2 text-xs">
            <div><dt class="text-slate-400 font-bold">{{ __('License type') }}</dt><dd class="text-slate-700 dark:text-slate-300">{{ $coreLicense['type'] }}</dd></div>
            <div><dt class="text-slate-400 font-bold">{{ __('Buyer') }}</dt><dd class="text-slate-700 dark:text-slate-300">{{ $coreLicense['buyer'] }}</dd></div>
            <div><dt class="text-slate-400 font-bold">{{ __('Purchase code') }}</dt><dd class="font-mono text-slate-700 dark:text-slate-300">{{ $coreLicense['masked_code'] }}</dd></div>
            <div><dt class="text-slate-400 font-bold">{{ __('Last checked') }}</dt><dd class="text-slate-700 dark:text-slate-300">{{ $coreLicense['last_checked'] ?? '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-slate-400 font-bold">{{ __('Message') }}</dt><dd class="text-slate-700 dark:text-slate-300">{{ $coreLicense['message'] ?: '—' }}</dd></div>
        </dl>
        <p class="text-[11px] text-slate-400">{{ __('A core-license warning never disables the platform — it is informational only.') }}</p>
        <div>
            <button type="button" wire:click="recheckCoreLicense"
                    class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-extrabold hover:bg-slate-200 dark:hover:bg-slate-700">
                {{ __('Re-check now') }}
            </button>
        </div>
    </div>

    {{-- Module licenses --}}
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
            <h4 class="font-extrabold text-slate-900 dark:text-white">{{ __('Module licenses') }}</h4>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-extrabold text-left">
                    <tr>
                        <th class="px-6 py-3">{{ __('Module') }}</th>
                        <th class="px-6 py-3">{{ __('Status') }}</th>
                        <th class="px-6 py-3">{{ __('Driver') }}</th>
                        <th class="px-6 py-3">{{ __('Verified') }}</th>
                        <th class="px-6 py-3">{{ __('Expires') }}</th>
                        <th class="px-6 py-3">{{ __('Active') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($moduleLicenses as $ml)
                        <tr>
                            <td class="px-6 py-3 font-bold text-slate-800 dark:text-slate-200">{{ $ml->name }} <span class="text-slate-400 font-mono">({{ $ml->slug }})</span></td>
                            <td class="px-6 py-3">{{ ucfirst($ml->license_status) }}</td>
                            <td class="px-6 py-3">{{ $ml->license_driver ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $ml->license_verified_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $ml->license_expires_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $ml->is_active ? __('Yes') : __('No') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">{{ __('No license-managed modules installed.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
