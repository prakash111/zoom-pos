<div>
    <h2 class="text-lg font-semibold mb-4">{{ __("Step 2 — Environment & Database") }}</h2>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">{{ __("App URL") }}</label>
            <input type="text" wire:model="appUrl" class="w-full rounded-lg border-slate-300 text-sm">
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
                    <input type="text" wire:model="dbHost" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ __("Port") }}</label>
                    <input type="text" wire:model="dbPort" class="w-full rounded-lg border-slate-300 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __("Username") }}</label>
                <input type="text" wire:model="dbUsername" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">{{ __("Password") }}</label>
                <input type="password" wire:model="dbPassword" class="w-full rounded-lg border-slate-300 text-sm">
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium mb-1">{{ __("Database Name") }}{{ $dbConnection === "sqlite" ? " (file path, e.g. database/database.sqlite)" : "" }}</label>
            <input type="text" wire:model="dbDatabase" class="w-full rounded-lg border-slate-300 text-sm">
            @error('dbDatabase') <p class="text-rose-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        @if (! is_null($connectionOk))
            <p @class(['text-sm', 'text-emerald-600' => $connectionOk, 'text-rose-600' => ! $connectionOk])>
                {{ $connectionMessage }}
            </p>
        @endif
    </div>

    <div class="flex justify-between mt-6">
        <button wire:click="testConnection" type="button"
                class="px-4 py-2 rounded-lg text-sm font-medium bg-slate-100 hover:bg-slate-200">
            Test Connection
        </button>
        <button wire:click="save" type="button"
                class="px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-500">
            Save & Continue
        </button>
    </div>
</div>
