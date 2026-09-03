<form wire:submit="savePushNotifications" class="space-y-6">
    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-start justify-between gap-6">
            <div>
                <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ __('Global push gateway') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('These credentials are platform-wide. Tenant owners and POS users never receive or edit server credentials.') }}</p>
            </div>
            <label class="flex shrink-0 items-center gap-3 rounded-2xl bg-slate-100 px-4 py-3 text-sm font-bold dark:bg-slate-800">
                <input type="checkbox" wire:model="pushEnabled" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                {{ __('Enabled') }}
            </label>
        </div>

        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            <label class="space-y-2 text-sm font-bold text-slate-700 dark:text-slate-300">
                <span>{{ __('Firebase Project ID') }}</span>
                <input wire:model="fcmProjectId" type="text" autocomplete="off" class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                @error('fcmProjectId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="space-y-2 text-sm font-bold text-slate-700 dark:text-slate-300">
                <span>{{ __('Legacy Server Key') }} @if($hasFcmServerKey)<span class="text-emerald-600">({{ __('stored') }})</span>@endif</span>
                <input wire:model="fcmServerKey" type="password" autocomplete="new-password" placeholder="{{ $hasFcmServerKey ? __('Leave blank to keep current key') : '' }}" class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                @error('fcmServerKey') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="space-y-2 text-sm font-bold text-slate-700 dark:text-slate-300 lg:col-span-2">
                <span>{{ __('FCM Service Account JSON') }} @if($hasFcmServiceAccount)<span class="text-emerald-600">({{ __('encrypted and stored') }})</span>@endif</span>
                <textarea wire:model="fcmServiceAccountJson" rows="8" spellcheck="false" autocomplete="off" placeholder="{{ $hasFcmServiceAccount ? __('Leave blank to keep the current service account') : __('Paste the complete Firebase service-account JSON') }}" class="w-full rounded-xl border-slate-300 font-mono text-xs dark:border-slate-700 dark:bg-slate-950"></textarea>
                @error('fcmServiceAccountJson') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ __('Android application configuration') }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Used by the mobile app for dynamic Firebase initialization. The API key is never returned to tenant settings screens.') }}</p>
        <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <label class="space-y-2 text-sm font-bold text-slate-700 dark:text-slate-300">
                <span>{{ __('Android API Key') }} @if($hasAndroidApiKey)<span class="text-emerald-600">({{ __('stored') }})</span>@endif</span>
                <input wire:model="androidApiKey" type="password" autocomplete="new-password" placeholder="{{ $hasAndroidApiKey ? __('Leave blank to keep') : '' }}" class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            </label>
            <label class="space-y-2 text-sm font-bold text-slate-700 dark:text-slate-300">
                <span>{{ __('Android App ID') }}</span>
                <input wire:model="androidAppId" type="text" class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            </label>
            <label class="space-y-2 text-sm font-bold text-slate-700 dark:text-slate-300">
                <span>{{ __('Messaging Sender ID') }}</span>
                <input wire:model="messagingSenderId" type="text" class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            </label>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-lg font-black text-slate-900 dark:text-white">{{ __('High-importance Android channels') }}</h2>
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            @foreach ([['orderChannelId','orderChannelName','orderSound',__('Delayed orders')], ['invoiceChannelId','invoiceChannelName','invoiceSound',__('Due invoices')]] as [$id, $name, $sound, $heading])
                <div class="space-y-4 rounded-2xl border border-slate-200 p-5 dark:border-slate-700">
                    <h3 class="font-black text-slate-900 dark:text-white">{{ $heading }}</h3>
                    <label class="block space-y-2 text-sm font-bold text-slate-700 dark:text-slate-300"><span>{{ __('Channel ID') }}</span><input wire:model="{{ $id }}" type="text" class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                    <label class="block space-y-2 text-sm font-bold text-slate-700 dark:text-slate-300"><span>{{ __('Channel name') }}</span><input wire:model="{{ $name }}" type="text" class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                    <label class="block space-y-2 text-sm font-bold text-slate-700 dark:text-slate-300"><span>{{ __('System sound preset') }}</span><select wire:model="{{ $sound }}" class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950"><option value="alarm">{{ __('Alarm') }}</option><option value="notification">{{ __('Notification') }}</option><option value="ringtone">{{ __('Ringtone') }}</option></select></label>
                </div>
            @endforeach
            <label class="space-y-2 text-sm font-bold text-slate-700 dark:text-slate-300 lg:col-span-2">
                <span>{{ __('Recurring alarm interval (seconds)') }}</span>
                <input wire:model="alarmRepeatSeconds" type="number" min="15" max="600" class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950 lg:w-56">
            </label>
        </div>
    </section>

    <div class="flex justify-end">
        <button type="submit" wire:loading.attr="disabled" class="rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-black text-white shadow-lg hover:bg-indigo-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="savePushNotifications">{{ __('Save push settings') }}</span>
            <span wire:loading wire:target="savePushNotifications">{{ __('Saving…') }}</span>
        </button>
    </div>
</form>
