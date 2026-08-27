<div class="space-y-6">

    <!-- Top Flash Feedback Alerts -->
    @if (session('status') || $successMessage)
        <div class="px-5 py-3.5 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2.5 shadow-xs border border-emerald-200 dark:border-emerald-800 animate-in fade-in">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') ?: $successMessage }}</span>
        </div>
    @endif

    @if (session('error') || $errorMessage)
        <div class="px-5 py-3.5 rounded-2xl bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300 text-xs sm:text-sm font-semibold flex items-center gap-2.5 shadow-xs border border-rose-200 dark:border-rose-800 animate-in fade-in">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span>{{ session('error') ?: $errorMessage }}</span>
        </div>
    @endif

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <span>🌐 {{ __("Store Languages & Custom Translation Editor") }}</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">{{ __("Customize phrases, POS terms, receipt titles, and tax invoice wording specifically for your store") }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a wire:navigate.hover href="{{ route('tenant.settings.index') }}" class="px-4 py-2 rounded-2xl text-xs font-bold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 shadow-xs border border-slate-200 dark:border-slate-700 transition">
                ⚙️ {{ __("Store Settings") }}
            </a>
        </div>
    </div>

    <!-- 1. Store Default Language Setting Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex items-center gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-lg">
                🌍
            </div>
            <div>
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __("Store Primary Language") }}</h3>
                <p class="text-xs text-slate-400">{{ __("Default language for customer receipts, table QR orders, and staff POS terminals") }}</p>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <div class="flex-1">
                <select wire:model="storeDefaultLanguage" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 py-2.5">
                    @foreach ($languages as $lang)
                        <option value="{{ $lang->code }}">
                            {{ $lang->flag }} {{ $lang->name }} ({{ $lang->native_name }})
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="button"
                    wire:click="saveStoreDefaultLanguage"
                    class="px-6 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs shadow-md shadow-blue-500/20 active:scale-95 transition cursor-pointer">
                {{ __("Apply Primary Language") }}
            </button>
        </div>
    </div>

    <!-- 2. Store Translation Overrides Editor -->
    <div class="space-y-4">
        
        <!-- Language Selector Bar -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400">{{ __("Customize Phrases for Language:") }}</span>
                <span class="text-xs font-bold text-slate-600 dark:text-slate-300">
                    {{ __("Language:") }} <span class="font-mono text-blue-600 dark:text-blue-400">{{ $currentLanguage?->name }} ({{ $selectedLocale }})</span>
                </span>
            </div>

            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1">
                @foreach ($languages as $lang)
                    <button type="button"
                            wire:click="selectLocale('{{ $lang->code }}')"
                            @class([
                                'px-3.5 py-2 rounded-2xl text-xs font-bold whitespace-nowrap transition cursor-pointer flex items-center gap-2',
                                'bg-blue-600 text-white shadow-md shadow-blue-500/20' => $selectedLocale === $lang->code,
                                'bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' => $selectedLocale !== $lang->code,
                            ])>
                        <span class="text-base">{{ $lang->flag ?: '🌐' }}</span>
                        <span>{{ $lang->name }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Search & Custom Action Bar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 rounded-3xl p-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
            <div class="relative flex-1 max-w-md">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                <input type="text"
                       wire:model.live.debounce.250ms="searchQuery"
                       placeholder="{{ __("Search terms or phrases…") }}"
                       class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border-none text-xs sm:text-sm font-semibold text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button type="button"
                        wire:click="openAddModal"
                        class="px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs transition cursor-pointer flex items-center gap-1.5 shadow-2xs">
                    <span>➕ {{ __("Add Custom Phrase") }}</span>
                </button>

                <button type="button"
                        wire:click="saveAllOverrides"
                        wire:loading.attr="disabled"
                        class="px-6 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs shadow-lg shadow-blue-500/25 active:scale-95 transition cursor-pointer flex items-center gap-2">
                    <span wire:loading.remove>💾 {{ __("Save Store Overrides") }}</span>
                    <span wire:loading>{{ __("Saving...") }}</span>
                </button>
            </div>
        </div>

        <!-- Overrides Table -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-extrabold uppercase tracking-wider text-[10px] bg-slate-50/50 dark:bg-slate-800/40">
                            <th class="py-3.5 pl-5 w-1/3">{{ __("Term / Phrase") }}</th>
                            <th class="py-3.5 px-4 w-1/4">{{ __("System Default") }}</th>
                            <th class="py-3.5 px-4 w-1/3">{{ __("Your Custom Store Translation") }}</th>
                            <th class="py-3.5 pr-5 text-right">{{ __("Reset") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($filteredKeys as $key)
                            @php
                                $baseVal = $baseTranslations[$key] ?? $key;
                                $hasOverride = !empty($customOverrides[$key]);
                                $currentOverride = $editingOverrides[$key] ?? '';
                            @endphp
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                <!-- Key -->
                                <td class="py-3.5 pl-5 align-top">
                                    <div class="font-bold text-slate-900 dark:text-white text-xs">
                                        {{ $key }}
                                    </div>
                                    @if ($hasOverride)
                                        <span class="inline-block mt-0.5 px-2 py-0.2 rounded-md text-[9px] font-black bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                            {{ __("Customized") }}
                                        </span>
                                    @endif
                                </td>

                                <!-- Default -->
                                <td class="py-3.5 px-4 align-top text-slate-500 dark:text-slate-400 text-xs">
                                    {{ $baseVal }}
                                </td>

                                <!-- Override Editable Input -->
                                <td class="py-3 px-4 align-top">
                                    <input type="text"
                                           value="{{ $currentOverride ?: $baseVal }}"
                                           wire:change="updateOverride('{{ addslashes($key) }}', $event.target.value)"
                                           placeholder="{{ $baseVal }}"
                                           dir="{{ $currentLanguage?->direction === 'rtl' ? 'rtl' : 'ltr' }}"
                                           class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/80 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:bg-white dark:focus:bg-slate-900 transition">
                                </td>

                                <!-- Reset Action -->
                                <td class="py-3 pr-5 text-right align-middle">
                                    @if ($hasOverride)
                                        <button type="button"
                                                wire:click="clearOverride('{{ addslashes($key) }}')"
                                                class="text-xs font-bold text-slate-400 hover:text-rose-600 transition cursor-pointer"
                                                title="{{ __("Reset to system default") }}">
                                            ↺ {{ __("Reset") }}
                                        </button>
                                    @else
                                        <span class="text-[11px] text-slate-300 dark:text-slate-600">&bull;</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-12 text-center text-slate-400 text-xs">
                                    No phrases found matching "{{ $searchQuery }}".
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Footer Stats -->
            <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 flex items-center justify-between text-xs text-slate-400">
                <span>Showing {{ count($filteredKeys) }} phrases in [{{ $selectedLocale }}]</span>
                <button type="button" wire:click="saveAllOverrides" class="font-bold text-blue-600 hover:underline cursor-pointer">
                    💾 {{ __("Save All Custom Translations") }}
                </button>
            </div>
        </div>

    </div>

    <!-- MODAL: ADD CUSTOM PHRASE -->
    @if ($showAddModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white flex items-center gap-2">
                        <span>➕ {{ __("Add Custom Phrase") }}</span>
                    </h3>
                    <button type="button" wire:click="$set('showAddModal', false)" class="text-slate-400 hover:text-white text-xl font-bold p-1 cursor-pointer">&times;</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Phrase Key *") }}</label>
                        <input type="text"
                               wire:model="customKey"
                               placeholder="{{ __("e.g. Return Policy, Special Promo") }}"
                               class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-semibold focus:ring-2 focus:ring-blue-500">
                        @error('customKey') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Your Store Custom Translation *") }}</label>
                        <input type="text"
                               wire:model="customValue"
                               placeholder="{{ __("e.g. Política de Devoluciones") }}"
                               class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-semibold focus:ring-2 focus:ring-blue-500">
                        @error('customValue') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showAddModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400">Cancel</button>
                    <button type="button"
                            wire:click="addCustomPhrase"
                            class="px-5 py-2.5 rounded-xl text-xs font-black bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition cursor-pointer">
                        {{ __("Save Phrase") }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
