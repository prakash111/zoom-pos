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

    <!-- Page Header & Tab Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <span>🌐 Multi-Language & Translation File Editor</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Manage supported platform languages, customize JSON translation files, and synchronize multilingual dictionaries</p>
        </div>

        <!-- Action / Tab Switcher -->
        <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 p-1 rounded-2xl">
            <button type="button"
                    wire:click="$set('activeTab', 'editor')"
                    @class([
                        'px-4 py-2 rounded-xl text-xs font-black transition cursor-pointer',
                        'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs' => $activeTab === 'editor',
                        'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' => $activeTab !== 'editor',
                    ])>
                📝 Translation File Editor
            </button>

            <button type="button"
                    wire:click="$set('activeTab', 'languages')"
                    @class([
                        'px-4 py-2 rounded-xl text-xs font-black transition cursor-pointer',
                        'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs' => $activeTab === 'languages',
                        'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' => $activeTab !== 'languages',
                    ])>
                🌍 Manage Languages ({{ $languages->count() }})
            </button>
        </div>
    </div>

    <!-- TAB 1: TRANSLATION FILE EDITOR -->
    @if ($activeTab === 'editor')
        <div class="space-y-5">
            
            <!-- Language Selector Bar -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Select Language File to Edit:</span>
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300">
                        Editing: <span class="font-mono text-blue-600 dark:text-blue-400">lang/{{ $selectedLocale }}.json</span> ({{ $totalKeyCount }} keys)
                    </span>
                </div>

                <!-- Language Pills Scrollable -->
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
                            <span class="text-[10px] uppercase font-mono opacity-80">({{ $lang->code }})</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Editor Action Bar & Search -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 rounded-3xl p-4 border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <!-- Search Filter -->
                <div class="relative flex-1 max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    <input type="text"
                           wire:model.live.debounce.250ms="searchQuery"
                           placeholder="Search keys or translated phrases…"
                           class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800 border-none text-xs sm:text-sm font-semibold text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button"
                            wire:click="openAddKeyModal"
                            class="px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs transition cursor-pointer flex items-center gap-1.5 shadow-2xs">
                        <span>➕ Add Key</span>
                    </button>

                    <button type="button"
                            wire:click="syncMissingFromEnglish"
                            wire:loading.attr="disabled"
                            class="px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs transition cursor-pointer flex items-center gap-1.5 shadow-2xs"
                            title="Import all missing keys from English template">
                        <span>🔄 Sync Keys</span>
                    </button>

                    <button type="button"
                            wire:click="saveTranslations"
                            wire:loading.attr="disabled"
                            class="px-6 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs shadow-lg shadow-blue-500/25 active:scale-95 transition cursor-pointer flex items-center gap-2">
                        <span wire:loading.remove>💾 Save Changes</span>
                        <span wire:loading>Saving...</span>
                    </button>
                </div>
            </div>

            <!-- Translation Key-Value Table -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400 font-extrabold uppercase tracking-wider text-[10px] bg-slate-50/50 dark:bg-slate-800/40">
                                <th class="py-3.5 pl-5 w-2/5">Translation Key (String)</th>
                                <th class="py-3.5 px-4 w-1/2">
                                    <span>Translation in {{ $currentLanguage?->name ?? strtoupper($selectedLocale) }}</span>
                                    <span class="text-slate-400 text-[10px] font-normal">({{ $currentLanguage?->flag }})</span>
                                </th>
                                <th class="py-3.5 pr-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($filteredTranslations as $key => $val)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                    <!-- Key -->
                                    <td class="py-3 pl-5 align-top">
                                        <div class="font-mono font-bold text-slate-900 dark:text-white text-xs select-all">
                                            {{ $key }}
                                        </div>
                                    </td>

                                    <!-- Value Editable Input -->
                                    <td class="py-3 px-4 align-top">
                                        <input type="text"
                                               value="{{ $val }}"
                                               wire:change="updateKey('{{ addslashes($key) }}', $event.target.value)"
                                               dir="{{ $currentLanguage?->direction === 'rtl' ? 'rtl' : 'ltr' }}"
                                               class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/80 text-xs font-semibold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:bg-white dark:focus:bg-slate-900 transition">
                                    </td>

                                    <!-- Delete Button -->
                                    <td class="py-3 pr-5 text-right align-middle">
                                        <button type="button"
                                                wire:click="deleteKey('{{ addslashes($key) }}')"
                                                wire:confirm="Are you sure you want to delete this translation key?"
                                                class="px-2.5 py-1 rounded-xl text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-bold transition cursor-pointer"
                                                title="Delete translation key">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-12 text-center text-slate-400 text-xs">
                                        No translation keys found matching "{{ $searchQuery }}". Click "+ Add Key" to create one.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Footer Stats -->
                <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 flex items-center justify-between text-xs text-slate-400">
                    <span>Showing {{ count($filteredTranslations) }} of {{ $totalKeyCount }} keys in [{{ $selectedLocale }}]</span>
                    <button type="button" wire:click="saveTranslations" class="font-bold text-blue-600 hover:underline cursor-pointer">
                        💾 Save All Edits
                    </button>
                </div>
            </div>

        </div>
    @endif

    <!-- TAB 2: MANAGE PLATFORM LANGUAGES -->
    @if ($activeTab === 'languages')
        <div class="space-y-5">
            
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Supported Platform Languages</h3>
                    <p class="text-xs text-slate-400">Enable, disable or add new multilingual languages to the system</p>
                </div>

                <button type="button"
                        wire:click="$set('showCreateModal', true)"
                        class="px-5 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs shadow-lg shadow-blue-500/25 active:scale-95 transition cursor-pointer flex items-center gap-1.5">
                    <span>➕ Add New Language</span>
                </button>
            </div>

            <!-- Language Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach ($languages as $lang)
                    <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col justify-between space-y-4 hover:border-slate-300 dark:hover:border-slate-700 transition">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <span class="text-3xl">{{ $lang->flag ?: '🌐' }}</span>
                                <div>
                                    <h4 class="font-black text-sm text-slate-900 dark:text-white">{{ $lang->name }}</h4>
                                    <p class="text-xs text-slate-400 font-medium">{{ $lang->native_name }}</p>
                                </div>
                            </div>

                            <span @class([
                                'px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => $lang->is_active,
                                'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! $lang->is_active,
                            ])>
                                {{ $lang->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </div>

                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 text-xs space-y-1">
                            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                                <span>Code:</span>
                                <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ $lang->code }}</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                                <span>Layout Direction:</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200 uppercase">{{ $lang->direction }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                            <button type="button"
                                    wire:click="selectLocale('{{ $lang->code }}')"
                                    x-on:click="$wire.set('activeTab', 'editor')"
                                    class="text-xs font-bold text-blue-600 hover:underline cursor-pointer">
                                ✏️ Edit File
                            </button>

                            <button type="button"
                                    wire:click="toggleLanguageStatus({{ $lang->id }})"
                                    class="text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-white cursor-pointer">
                                {{ $lang->is_active ? 'Disable' : 'Enable' }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    @endif

    <!-- MODAL: ADD NEW TRANSLATION KEY -->
    @if ($showAddKeyModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white flex items-center gap-2">
                        <span>➕ Add Translation Key</span>
                    </h3>
                    <button type="button" wire:click="$set('showAddKeyModal', false)" class="text-slate-400 hover:text-white text-xl font-bold p-1 cursor-pointer">&times;</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Key Name (Exact String) *</label>
                        <input type="text"
                               wire:model="newKey"
                               placeholder="e.g. Return Policy, Print Tax Invoice"
                               class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-mono focus:ring-2 focus:ring-blue-500">
                        @error('newKey') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Default English Translation</label>
                        <input type="text"
                               wire:model="newValue"
                               placeholder="e.g. Return Policy"
                               class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showAddKeyModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400">Cancel</button>
                    <button type="button"
                            wire:click="addKey"
                            class="px-5 py-2.5 rounded-xl text-xs font-black bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition cursor-pointer">
                        Add Key
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL: ADD NEW LANGUAGE -->
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 max-w-md w-full shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🌍 Add New Platform Language</span>
                    </h3>
                    <button type="button" wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-white text-xl font-bold p-1 cursor-pointer">&times;</button>
                </div>

                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Language Code (ISO) *</label>
                            <input type="text"
                                   wire:model="newCode"
                                   placeholder="e.g. nl, ja, it"
                                   class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-mono uppercase focus:ring-2 focus:ring-blue-500">
                            @error('newCode') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Flag Emoji</label>
                            <input type="text"
                                   wire:model="newFlag"
                                   placeholder="🇳🇱"
                                   class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-center focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">English Name *</label>
                        <input type="text"
                               wire:model="newName"
                               placeholder="e.g. Dutch"
                               class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-2 focus:ring-blue-500">
                        @error('newName') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Native Name</label>
                        <input type="text"
                               wire:model="newNativeName"
                               placeholder="e.g. Nederlands"
                               class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Text Direction</label>
                        <select wire:model="newDirection" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="ltr">Left-to-Right (LTR)</option>
                            <option value="rtl">Right-to-Left (RTL - Arabic, Hebrew)</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400">Cancel</button>
                    <button type="button"
                            wire:click="createLanguage"
                            class="px-5 py-2.5 rounded-xl text-xs font-black bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition cursor-pointer">
                        Create Language
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
