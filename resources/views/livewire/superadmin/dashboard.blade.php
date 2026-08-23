<div class="space-y-6">
    
    <!-- Top Stats Cards Grid matching tenant dashboard -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        
        <!-- 1. Total Tenants -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Total Stores</span>
                <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-sm font-bold">
                    🏢
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-3">{{ $totalTenants }}</div>
        </div>

        <!-- 2. Active Tenants -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">Active</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm font-bold">
                    ✓
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-3">{{ $activeTenants }}</div>
        </div>

        <!-- 3. Suspended -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-rose-500">Suspended</span>
                <div class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center text-sm font-bold">
                    ⛔
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-rose-600 dark:text-rose-400 mt-3">{{ $suspendedTenants }}</div>
        </div>

        <!-- 4. Expiring Soon -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-amber-500">Expiring (30d)</span>
                <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm font-bold">
                    ⏳
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400 mt-3">{{ $expiringSoon }}</div>
        </div>

        <!-- 5. Total Users -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Total Users</span>
                <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm font-bold">
                    👥
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-3">{{ $totalUsers }}</div>
        </div>

        <!-- 6. License Keys -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-purple-600 dark:text-purple-400">License Keys</span>
                <div class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-sm font-bold">
                    🔑
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-purple-600 dark:text-purple-400 mt-3">{{ $activationCodesAvailable }}</div>
        </div>

    </div>

    <!-- Quick Action Launch Bar -->
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('superadmin.tenants.create') }}" class="px-5 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-lg shadow-indigo-500/25 active:scale-95 transition flex items-center gap-2">
            <span>➕ Provision New Store</span>
        </a>
        <a href="{{ route('superadmin.activation-codes.index') }}" class="px-5 py-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-extrabold text-xs shadow-xs active:scale-95 transition flex items-center gap-2">
            <span>🔑 Generate License Keys</span>
        </a>
        <a href="{{ route('superadmin.plans.index') }}" class="px-5 py-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-extrabold text-xs shadow-xs active:scale-95 transition flex items-center gap-2">
            <span>💳 Manage Pricing Plans</span>
        </a>
        <a href="{{ route('superadmin.smtp.index') }}" class="px-5 py-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-extrabold text-xs shadow-xs active:scale-95 transition flex items-center gap-2">
            <span>✉️ SMTP & Email Server</span>
        </a>
    </div>

    <!-- Recent Tenants Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div>
                <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">Recently Provisioned Stores</h3>
                <p class="text-xs text-slate-400">Latest tenant accounts and live subscription status</p>
            </div>
            <a href="{{ route('superadmin.tenants.index') }}" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                View All Stores &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-extrabold text-left border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-3.5">Store / Tenant</th>
                        <th class="px-6 py-3.5">Subscription Plan</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Created</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($recentTenants as $tenant)
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
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 flex items-center gap-1.5 w-max">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-slate-400 text-xs font-mono">
                                {{ $tenant->created_at->diffForHumans() }}
                            </td>

                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('superadmin.tenants.show', $tenant) }}" class="px-3 py-1.5 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 font-extrabold text-xs transition">
                                    Manage &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                                No tenants provisioned yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
