<!-- =========================================================================
     TAB: API & INTEGRATIONS (Sanctum Tokens, RESTful Webhooks & API Keys)
     ========================================================================= -->
<div x-show="activeTab === 'api'" x-cloak class="space-y-6">
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 flex items-center justify-center text-lg">✨</span>
                <div><h3 class="text-sm font-black text-slate-900 dark:text-white">{{ __('Generative AI Studio & Vision Engine') }}</h3><p class="text-xs text-slate-400">{{ __('Automate studio-grade commercial product imagery from product titles and categories.') }}</p></div>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">{{ __('Active Provider:') }} {{ strtoupper($defaultAiProvider) }}</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            @foreach([
                'openai' => ['OpenAI', 'DALL-E / GPT Vision', 'openaiModel', 'openaiApiKey', 'sk-proj-...', $hasOpenaiApiKey],
                'gemini' => ['Google Gemini', 'Imagen / Gemini', 'geminiModel', 'geminiApiKey', 'AIzaSy...', $hasGeminiApiKey],
                'claude' => ['Anthropic Claude', 'Vision + Prompt', 'claudeModel', 'claudeApiKey', 'sk-ant-...', $hasClaudeApiKey],
            ] as $provider => [$title, $family, $modelProperty, $keyProperty, $placeholder, $stored])
                <div @class(['p-4 rounded-2xl border transition-all duration-200 space-y-4', 'border-purple-500 bg-purple-50/20 dark:bg-purple-950/20 ring-2 ring-purple-500/20' => $defaultAiProvider === $provider, 'border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30' => $defaultAiProvider !== $provider])>
                    <div class="flex items-center justify-between gap-2">
                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="provider" wire:model.live="defaultAiProvider" value="{{ $provider }}" class="text-purple-600 focus:ring-0"><span class="text-xs font-black text-slate-800 dark:text-white">{{ $title }}</span></label>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $family }}</span>
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Model Preset (Legacy to Flagship)') }}</label>
                        <select wire:model="{{ $modelProperty }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200">
                            @foreach($this->modelPresets[$provider] as $model)
                                <option value="{{ $model['id'] }}">{{ $model['name'] }} — {{ $model['badge'] }}</option>
                            @endforeach
                        </select>
                        @error($modelProperty)<p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('API Key') }} @if($stored)<span class="text-emerald-600">· {{ __('Saved') }}</span>@endif</label>
                        <input type="password" wire:model="{{ $keyProperty }}" placeholder="{{ $stored ? __('Leave blank to keep saved key') : $placeholder }}" autocomplete="new-password" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-mono">
                    </div>
                </div>
            @endforeach
        </div>
        <div class="flex justify-end pt-3 border-t border-slate-100 dark:border-slate-800"><button type="button" wire:click="saveAiConfiguration" class="px-6 py-2.5 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-black text-xs shadow-sm transition active:scale-95">{{ __('Save AI Configuration') }}</button></div>
    </div>
    
    <!-- Top Header Bar -->
    <div class="p-6 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>⚡</span>
                <span>{{ __('Developer API Keys & E-Invoicing Gateway') }}</span>
            </h3>
            <p class="text-xs text-slate-400 mt-1">
                {{ __('Manage bearer API credentials for external ERPs, Shopify/WooCommerce webhooks, and third-party accounting integrations.') }}
            </p>
        </div>

        <button type="button"
                wire:click="$set('showApiKeyModal', true)"
                class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs flex items-center gap-1.5 transition active:scale-95 cursor-pointer shadow-md shadow-blue-500/20">
            <span>+</span>
            <span>{{ __('Generate New API Key') }}</span>
        </button>
    </div>

    <!-- Recently Generated Key Flash Alert -->
    @if ($recentlyGeneratedToken)
        <div class="p-5 rounded-3xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 space-y-2">
            <div class="flex items-center gap-2 text-xs font-black text-amber-800 dark:text-amber-300">
                <span>⚠️</span>
                <span>{{ __('Save your API Secret Token immediately!') }}</span>
            </div>
            <p class="text-xs text-amber-700 dark:text-amber-400">
                {{ __('This secret key is only displayed once. Please copy and store it in a secure environment.') }}
            </p>
            <div class="flex items-center gap-2 pt-1">
                <input type="text"
                       readonly
                       value="{{ $recentlyGeneratedToken }}"
                       class="w-full font-mono text-xs py-2 px-3 rounded-xl bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-700 text-slate-900 dark:text-white font-bold select-all">
                <button type="button"
                        onclick="navigator.clipboard.writeText('{{ $recentlyGeneratedToken }}'); alert('API Key copied to clipboard!');"
                        class="px-4 py-2 rounded-xl bg-amber-600 text-white font-extrabold text-xs hover:bg-amber-700 transition active:scale-95 whitespace-nowrap cursor-pointer">
                    {{ __('Copy Token') }}
                </button>
            </div>
        </div>
    @endif

    <!-- Active API Keys Table -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div class="font-extrabold text-sm text-slate-900 dark:text-white">
                {{ __('Active API Tokens') }}
            </div>
            <span class="text-xs text-slate-400 font-mono">
                {{ count($apiKeys) }} {{ __('keys active') }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-3.5">{{ __('Key Name') }}</th>
                        <th class="px-6 py-3.5">{{ __('Token Identifier') }}</th>
                        <th class="px-6 py-3.5">{{ __('Permissions / Scopes') }}</th>
                        <th class="px-6 py-3.5">{{ __('Last Used') }}</th>
                        <th class="px-6 py-3.5">{{ __('Status') }}</th>
                        <th class="px-6 py-3.5 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($apiKeys as $k)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">
                                {{ $k->name }}
                            </td>
                            <td class="px-6 py-4 font-mono text-slate-600 dark:text-slate-400">
                                {{ substr($k->token, 0, 14) }}••••••••••••••••
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($k->permissions ?? ['*'] as $perm)
                                        <span class="px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 text-[10px] font-mono font-bold">
                                            {{ $perm }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-500 font-mono text-[11px]">
                                {{ $k->last_used_at ? $k->last_used_at->diffForHumans() : __('Never used') }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold {{ $k->active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">
                                    {{ $k->active ? __('Active') : __('Disabled') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button type="button"
                                        wire:click="toggleApiKey('{{ $k->id }}')"
                                        class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 text-xs font-bold transition cursor-pointer">
                                    {{ $k->active ? __('Disable') : __('Enable') }}
                                </button>
                                <button type="button"
                                        wire:click="revokeApiKey('{{ $k->id }}')"
                                        wire:confirm="{{ __('Revoke this API Key? Any external systems using it will lose access.') }}"
                                        class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 text-xs font-bold transition cursor-pointer">
                                    {{ __('Revoke') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                                {{ __('No API keys generated yet. Click "Generate New API Key" above to integrate third-party systems.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- API Reference & Quick Testing Documentation -->
    <div class="p-6 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
        <h4 class="font-extrabold text-sm text-slate-900 dark:text-white flex items-center gap-2">
            <span>📖</span>
            <span>{{ __('Developer API Quick Reference') }}</span>
        </h4>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Calculate Tax Endpoint -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="px-2 py-0.5 rounded-md bg-blue-600 text-white font-mono text-[10px] font-black">POST</span>
                    <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">/api/v1/tax/calculate</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    {{ __('Computes subtotal, customer exemptions, and itemized tax breakdowns.') }}
                </p>
                <pre class="p-2.5 rounded-xl bg-slate-900 text-slate-200 font-mono text-[10px] overflow-x-auto"><code>curl -X POST "{{ url('/api/v1/tax/calculate') }}" \
  -H "Authorization: Bearer zk_live_YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"items":[{"name":"Item 1","quantity":2,"price":15.0}]}'</code></pre>
            </div>

            <!-- Issue Invoice Endpoint -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="px-2 py-0.5 rounded-md bg-emerald-600 text-white font-mono text-[10px] font-black">POST</span>
                    <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">/api/v1/tax/invoices</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    {{ __('Directly creates a cleared tax invoice inside the tenant database.') }}
                </p>
                <pre class="p-2.5 rounded-xl bg-slate-900 text-slate-200 font-mono text-[10px] overflow-x-auto"><code>curl -X POST "{{ url('/api/v1/tax/invoices') }}" \
  -H "Authorization: Bearer zk_live_YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"customer":{"name":"Acme Corp","tax_id":"27AA..."},"items":[{"name":"Service","quantity":1,"price":100}]}'</code></pre>
            </div>
        </div>
    </div>

</div>
