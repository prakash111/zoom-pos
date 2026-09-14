<div>
    <h2 class="text-lg font-semibold mb-4">{{ __("Step 2 — Environment & Database") }}</h2>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">{{ __("App URL") }}</label>
            <input type="text" wire:model="appUrl" class="w-full rounded-lg border-slate-300 text-sm" placeholder="https://crm.zoomnearby.com">
            @error('appUrl') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">{{ __("Database Driver") }}</label>
            <select wire:model.live="dbConnection" class="w-full rounded-lg border-slate-300 text-sm">
                <option value="mysql">MySQL</option>
                <option value="sqlite">SQLite</option>
            </select>
        </div>

        @if ($dbConnection === 'mysql')
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __("Host") }}</label>
                    <input type="text" wire:model="dbHost" class="w-full rounded-lg border-slate-300 text-sm" placeholder="127.0.0.1">
                    @error('dbHost') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __("Port") }}</label>
                    <input type="text" wire:model="dbPort" class="w-full rounded-lg border-slate-300 text-sm" placeholder="3306">
                    @error('dbPort') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __("Username") }}</label>
                <input type="text" wire:model="dbUsername" class="w-full rounded-lg border-slate-300 text-sm" placeholder="root">
                @error('dbUsername') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __("Password") }}</label>
                <input type="password" wire:model="dbPassword" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Database password">
                @error('dbPassword') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium mb-1">{{ __("Database Name") }}{{ $dbConnection === "sqlite" ? " (file path, e.g. database/database.sqlite)" : "" }}</label>
            <input type="text" wire:model="dbDatabase" class="w-full rounded-lg border-slate-300 text-sm" placeholder="zoom_pos_crm">
            @error('dbDatabase') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        @if (! is_null($connectionOk))
            <div @class([
                'p-4 rounded-xl text-sm flex items-start gap-3 mt-4 transition-all duration-200 shadow-sm',
                'bg-emerald-50 text-emerald-900 border border-emerald-300' => $connectionOk,
                'bg-rose-50 text-rose-900 border border-rose-300' => ! $connectionOk,
            ])>
                @if ($connectionOk)
                    <span class="w-5 h-5 rounded-full bg-emerald-200 text-emerald-800 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">✓</span>
                @else
                    <span class="w-5 h-5 rounded-full bg-rose-200 text-rose-800 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">✕</span>
                @endif
                <div class="font-medium text-xs sm:text-sm break-all">
                    {{ $connectionMessage }}
                </div>
            </div>
        @endif
    </div>

    <div class="flex justify-between items-center mt-6 pt-4 border-t border-slate-200">
        <button wire:click="testConnection" wire:loading.attr="disabled" type="button"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition bg-slate-100 hover:bg-slate-200 text-slate-800 active:scale-95 disabled:opacity-50 cursor-pointer">
            <span wire:loading.remove wire:target="testConnection">🔌 {{ __("Test Connection") }}</span>
            <span wire:loading wire:target="testConnection" class="inline-flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-slate-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                {{ __("Testing Connection...") }}
            </span>
        </button>

        <button wire:click="save" wire:loading.attr="disabled" type="button"
                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-500/20 active:scale-95 disabled:opacity-50 cursor-pointer">
            <span wire:loading.remove wire:target="save">{{ __("Save & Continue") }} &rarr;</span>
            <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                {{ __("Saving...") }}
            </span>
        </button>
    </div>
</div>
