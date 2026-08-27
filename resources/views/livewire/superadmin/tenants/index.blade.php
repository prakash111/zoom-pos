<div class="space-y-6">
    
    <!-- Top Action & Search Toolbar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="relative w-full sm:w-96">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="{{ __("Search stores by name, account ID, email, slug…") }}"
                   class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm font-medium focus:ring-2 focus:ring-indigo-500 shadow-xs text-slate-900 dark:text-white">
        </div>

        <a wire:navigate.hover href="{{ route('superadmin.tenants.create') }}"
           class="w-full sm:w-auto px-5 py-2.5 rounded-2xl text-xs sm:text-sm font-extrabold bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/25 active:scale-95 transition flex items-center justify-center gap-2 cursor-pointer">
            <span>➕ {{ __("Provision New Store") }}</span>
        </a>
    </div>

    <!-- Tenants Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-extrabold text-left border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-3.5">Store / Tenant</th>
                        <th class="px-6 py-3.5">Account ID</th>
                        <th class="px-6 py-3.5">Subscription Plan</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Expires</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($tenants as $tenant)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition">
                            <td class="px-6 py-4 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-black flex items-center justify-center text-xs shrink-0">
                                    {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $tenant->name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $tenant->slug ?? 'default' }}.{{ parse_url(config('app.url'), PHP_URL_HOST) ?? 'saas.zoomnearby.com' }}</div>
                                </div>
                            </td>

                            <td class="px-6 py-4 font-mono text-xs text-slate-600 dark:text-slate-400 font-bold">
                                {{ $tenant->unique_account_id ?? 'ACC-'.$tenant->id }}
                            </td>

                            <td class="px-6 py-4 font-bold text-slate-700 dark:text-slate-300">
                                <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                    {{ $tenant->plan_name ?? 'Free Trial' }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                @if ($tenant->status === 'suspended')
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">
                                        Suspended
                                    </span>
                                @elseif ($tenant->status === 'cancelled')
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                        Cancelled
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 flex items-center gap-1.5 w-max">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-slate-500 dark:text-slate-400 text-xs font-mono">
                                {{ $tenant->expires_at?->format('Y-m-d') ?? 'Never (Lifetime)' }}
                            </td>

                            <td class="px-6 py-4 text-right space-x-2">
                                <a wire:navigate.hover href="{{ route('superadmin.tenants.show', $tenant) }}"
                                   class="px-3.5 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 font-extrabold text-xs transition">
                                    Manage &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                                No tenants found. Click "+ Provision New Store" to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $tenants->links() }}</div>

</div>
