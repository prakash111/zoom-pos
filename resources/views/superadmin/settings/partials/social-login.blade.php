<div class="space-y-6">
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 dark:border-slate-800">
        <h3 class="text-lg font-black text-slate-900 dark:text-white">{{ __('Social Login') }}</h3>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Configure authentication providers shown on tenant login and registration pages. Providers appear only when enabled with complete credentials.') }}</p>
    </div>

    @foreach (['google' => ['Google', 'G'], 'facebook' => ['Facebook', 'f']] as $key => [$label, $icon])
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-200 dark:border-slate-800 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-950 text-blue-600 flex items-center justify-center text-lg font-black">{{ $icon }}</span>
                    <h4 class="font-extrabold text-slate-900 dark:text-white">{{ $label }}</h4>
                </div>
                <label class="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-300">
                    <input type="checkbox" wire:model="social.{{ $key }}.enabled" class="rounded text-indigo-600">
                    {{ __('Enable provider') }}
                </label>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div><label class="block mb-1 text-xs font-bold">{{ __('Client ID') }}</label><input wire:model="social.{{ $key }}.client_id" class="w-full rounded-xl dark:bg-slate-800"></div>
                <div><label class="block mb-1 text-xs font-bold">{{ __('Client Secret') }}</label><input type="password" wire:model="social.{{ $key }}.client_secret" placeholder="{{ \App\Models\PlatformSystem::get('social_'.$key.'_client_secret') ? '•••••••• (leave blank to keep)' : '' }}" class="w-full rounded-xl dark:bg-slate-800"></div>
            </div>
            <p class="text-[11px] text-slate-400">{{ __('Authorized callback URL') }}: <code class="select-all">{{ route('social.callback', $key) }}</code></p>
        </div>
    @endforeach

    <div class="flex justify-end"><button type="button" wire:click="saveSocialLogin" class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs sm:text-sm font-extrabold shadow-lg flex items-center justify-center">{{ __('Save Social Login Settings') }}</button></div>
</div>
