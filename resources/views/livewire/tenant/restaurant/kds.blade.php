<div wire:poll.5s class="space-y-6">

    <!-- Top Status Alerts -->
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            {{ session('status') }}
        </div>
    @endif

    <!-- KDS Header & Live Stats -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <span>🍳 {{ __("Kitchen Display System (KDS)") }}</span>
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">{{ __("Real-time live kitchen order tracker & ticket bump screen (Auto-refreshing every 5s)") }}</p>
        </div>

        <!-- Service Mode & Status Filter Pills -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center bg-white dark:bg-slate-800 rounded-2xl p-1 border border-slate-200 dark:border-slate-700 text-xs font-bold">
                <button type="button" wire:click="$set('filterServiceType', 'all')" @class(['px-3 py-1.5 rounded-xl transition', 'bg-slate-900 text-white dark:bg-lime-400 dark:text-slate-950 font-black' => $filterServiceType === 'all', 'text-slate-500' => $filterServiceType !== 'all'])>{{ __("All") }}</button>
                <button type="button" wire:click="$set('filterServiceType', 'dine_in')" @class(['px-3 py-1.5 rounded-xl transition', 'bg-slate-900 text-white dark:bg-lime-400 dark:text-slate-950 font-black' => $filterServiceType === 'dine_in', 'text-slate-500' => $filterServiceType !== 'dine_in'])>🍽️ {{ __("Dine-In") }}</button>
                <button type="button" wire:click="$set('filterServiceType', 'takeaway')" @class(['px-3 py-1.5 rounded-xl transition', 'bg-slate-900 text-white dark:bg-lime-400 dark:text-slate-950 font-black' => $filterServiceType === 'takeaway', 'text-slate-500' => $filterServiceType !== 'takeaway'])>🛍️ {{ __("Takeaway") }}</button>
                <button type="button" wire:click="$set('filterServiceType', 'delivery')" @class(['px-3 py-1.5 rounded-xl transition', 'bg-slate-900 text-white dark:bg-lime-400 dark:text-slate-950 font-black' => $filterServiceType === 'delivery', 'text-slate-500' => $filterServiceType !== 'delivery'])>🛵 {{ __("Delivery") }}</button>
            </div>

            <a wire:navigate.hover href="{{ route('tenant.restaurant.pos') }}" class="px-4 py-2.5 rounded-2xl text-xs font-extrabold bg-lime-500 hover:bg-lime-600 text-slate-950 shadow-md">
                🍽️ {{ __("POS Terminal") }}
            </a>
        </div>
    </div>

    <!-- Live Status Counts Bar -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-3.5 flex items-center justify-between">
            <div>
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-amber-500">{{ __("Pending Orders") }}</div>
                <div class="text-xl font-black text-amber-500">{{ $pendingCount }}</div>
            </div>
            <div class="text-2xl">⏳</div>
        </div>

        <div class="bg-blue-500/10 border border-blue-500/30 rounded-2xl p-3.5 flex items-center justify-between">
            <div>
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-blue-500">{{ __("In Cooking") }}</div>
                <div class="text-xl font-black text-blue-500">{{ $preparingCount }}</div>
            </div>
            <div class="text-2xl">🍳</div>
        </div>

        <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-3.5 flex items-center justify-between">
            <div>
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-500">{{ __("Ready to Serve") }}</div>
                <div class="text-xl font-black text-emerald-500">{{ $readyCount }}</div>
            </div>
            <div class="text-2xl">🔔</div>
        </div>

        <div class="bg-purple-500/10 border border-purple-500/30 rounded-2xl p-3.5 flex items-center justify-between">
            <div>
                <div class="text-[10px] font-extrabold uppercase tracking-wider text-purple-500">{{ __("Total Active KOTs") }}</div>
                <div class="text-xl font-black text-purple-500">{{ $tickets->count() }}</div>
            </div>
            <div class="text-2xl">📋</div>
        </div>
    </div>

    <!-- Kitchen Tickets Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse ($tickets as $kot)
            @php
                $mins = $kot->getElapsedMinutes();
            @endphp
            <div data-kot-id="{{ $kot->id }}" @class([
                'rounded-3xl p-5 border-2 transition-all flex flex-col justify-between shadow-md relative',
                'bg-white dark:bg-slate-900 border-amber-500/60 shadow-amber-500/10' => $kot->status === 'pending',
                'bg-white dark:bg-slate-900 border-blue-500/60 shadow-blue-500/10' => $kot->status === 'preparing',
                'bg-white dark:bg-slate-900 border-emerald-500/80 shadow-emerald-500/15 ring-2 ring-emerald-500/30' => $kot->status === 'ready',
            ])>
                
                <!-- Ticket Top Header -->
                <div>
                    <div class="flex items-start justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3 mb-3">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <h3 class="text-base font-black text-slate-900 dark:text-white">
                                    {{ $kot->kot_number }}
                                </h3>
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-[10px] font-black uppercase',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $kot->status === 'pending',
                                    'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' => $kot->status === 'preparing',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $kot->status === 'ready',
                                ])>
                                    {{ $kot->status }}
                                </span>
                            </div>
                            <div class="text-xs font-black text-lime-600 dark:text-lime-400 mt-0.5">
                                {{ $kot->table_name ?: ucfirst(str_replace('_', ' ', __($kot->service_type))) }}
                            </div>
                        </div>

                        <!-- Elapsed Timer Badge -->
                        <div @class([
                            'px-2.5 py-1 rounded-xl text-xs font-black flex items-center gap-1 whitespace-nowrap',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $mins < 10,
                            'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $mins >= 10 && $mins < 20,
                            'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 animate-bounce' => $mins >= 20,
                        ])>
                            <span>⏱️</span>
                            <span>{{ $mins }}{{ __("m ago") }}</span>
                        </div>
                    </div>

                    @if ($kot->target_completion_at)
                        <div class="kds-countdown-badge mb-3 px-2.5 py-1 rounded-xl text-[11px] font-black inline-flex items-center gap-1 {{ $kot->isOverdue() ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 animate-pulse' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}"
                             data-target="{{ $kot->target_completion_at->toIso8601String() }}">
                            🎯 <span class="kds-countdown-text">{{ __('Calculating...') }}</span>
                        </div>
                    @endif

                    <!-- Items List -->
                    <div class="space-y-2.5 py-1">
                        @foreach ($kot->items ?? [] as $item)
                            <div class="bg-slate-50 dark:bg-slate-800/60 rounded-2xl p-2.5 border border-slate-100 dark:border-slate-800 space-y-1">
                                <div class="flex justify-between items-start font-black text-xs sm:text-sm text-slate-900 dark:text-white">
                                    <span class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-lg bg-lime-400 text-slate-950 text-xs font-black flex items-center justify-center">
                                            {{ $item['quantity'] ?? 1 }}x
                                        </span>
                                        <span>{{ $item['name'] ?? __('Food Item') }}</span>
                                    </span>
                                    @if (!empty($item['seat']))
                                        <span class="text-[10px] font-bold text-slate-400">{{ __("Seat") }} {{ $item["seat"] }}</span>
                                    @endif
                                </div>

                                @if (!empty($item['variant']))
                                    <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 pl-8">
                                        &bull; {{ __("Option:") }} {{ $item["variant"] }}
                                    </div>
                                @endif

                                @if (!empty($item['modifiers']) && is_array($item['modifiers']))
                                    <div class="text-[11px] font-bold text-blue-600 dark:text-blue-400 pl-8">
                                        + {{ implode(', ', array_column($item['modifiers'], 'name')) }}
                                    </div>
                                @endif

                                @if (!empty($item['spice_level']))
                                    <div class="text-[11px] font-black text-rose-600 dark:text-rose-400 pl-8">
                                        🌶 {{ $item['spice_level'] }}
                                    </div>
                                @endif

                                @if (!empty($item['note']))
                                    <div class="text-[11px] font-extrabold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/50 p-1.5 rounded-xl ml-8">
                                        ⚠️ {{ $item['note'] }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($kot->kitchen_notes)
                        <div class="mt-3 p-2.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 text-xs font-bold text-amber-800 dark:text-amber-300">
                            <strong>{{ __("Note:") }}</strong> {{ $kot->kitchen_notes }}
                        </div>
                    @endif
                </div>

                <!-- Ticket Action Buttons -->
                <div class="pt-4 border-t border-slate-100 dark:border-slate-800 mt-4 space-y-2">
                    
                    @if ($kot->status === 'pending')
                        <button type="button"
                                wire:click="startPreparing('{{ $kot->id }}')"
                                class="w-full py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs shadow-md shadow-blue-500/20 active:scale-95 transition flex items-center justify-center gap-1.5">
                            <span>🍳 {{ __("Start Preparing") }}</span>
                        </button>
                    @elseif ($kot->status === 'preparing')
                        <button type="button"
                                wire:click="markReady('{{ $kot->id }}')"
                                class="w-full py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs shadow-md shadow-emerald-500/20 active:scale-95 transition flex items-center justify-center gap-1.5">
                            <span>🔔 {{ __("Mark as Ready") }}</span>
                        </button>
                    @elseif ($kot->status === 'ready')
                        <button type="button"
                                wire:click="markServed('{{ $kot->id }}')"
                                class="w-full py-2.5 rounded-2xl bg-slate-900 dark:bg-lime-400 hover:bg-lime-500 text-white dark:text-slate-950 font-black text-xs shadow-md active:scale-95 transition flex items-center justify-center gap-1.5">
                            <span>✅ {{ __("Mark as Served") }}</span>
                        </button>
                    @endif

                    <div class="flex items-center justify-between text-xs font-bold text-slate-400 pt-1">
                        <a href="{{ route('tenant.restaurant.kot.print', $kot) }}" target="_blank" class="hover:text-blue-500 flex items-center gap-1">
                            <span>🖨️ {{ __("Print Ticket") }}</span>
                        </a>
                        <button type="button" wire:click="cancelKot('{{ $kot->id }}')" wire:confirm="{{ __("Cancel KOT") }} {{ $kot->kot_number }}?" class="hover:text-rose-500">{{ __("Cancel") }}</button>
                    </div>

                </div>

            </div>
        @empty
            <div class="col-span-full py-16 text-center bg-white dark:bg-slate-900 rounded-3xl border border-slate-100 dark:border-slate-800 space-y-3">
                <div class="text-4xl">🍽️</div>
                <h3 class="font-extrabold text-base text-slate-900 dark:text-white">{{ __("All Kitchen Orders Cleared!") }}</h3>
                <p class="text-xs text-slate-400">{{ __("New orders from Dine-In tables, Takeaway, Delivery, or QR Digital Menu will appear here in real-time.") }}</p>
            </div>
        @endforelse
    </div>

</div>

<script>
(function () {
    const alertIntervalMs = {{ (int) $alertIntervalMinutes }} * 60000;
    const soundPreset = @js($alertSoundPreset);
    const soundUrl = @js($alertSoundUrl);

    function playPresetTone(pattern) {
        try {
            const ctx = window.__kdsAudioCtx || (window.__kdsAudioCtx = new (window.AudioContext || window.webkitAudioContext)());
            let t = ctx.currentTime;
            pattern.forEach(([freq, duration]) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.2, t);
                gain.gain.exponentialRampToValueAtTime(0.001, t + duration);
                osc.connect(gain).connect(ctx.destination);
                osc.start(t);
                osc.stop(t + duration);
                t += duration;
            });
        } catch (e) { /* Web Audio unavailable — fail silently */ }
    }

    function playAlert() {
        if (soundUrl) {
            const audio = new Audio(soundUrl);
            audio.play().catch(() => {});
            return;
        }
        const patterns = {
            chime: [[880, 0.15], [1175, 0.2]],
            bell: [[1046, 0.35]],
            alert: [[660, 0.12], [660, 0.12], [660, 0.12]],
        };
        playPresetTone(patterns[soundPreset] || patterns.chime);
    }

    function formatRemaining(ms) {
        const overdue = ms < 0;
        const abs = Math.abs(ms);
        const mins = Math.floor(abs / 60000);
        const secs = Math.floor((abs % 60000) / 1000);
        const label = mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
        return overdue ? `Overdue by ${label}` : `Due in ${label}`;
    }

    function tickCountdowns() {
        document.querySelectorAll('.kds-countdown-badge').forEach((badge) => {
            const target = new Date(badge.dataset.target).getTime();
            const remaining = target - Date.now();
            const textEl = badge.querySelector('.kds-countdown-text');
            if (textEl) textEl.textContent = formatRemaining(remaining);
            badge.classList.toggle('bg-rose-100', remaining < 0);
            badge.classList.toggle('text-rose-700', remaining < 0);
            badge.classList.toggle('animate-pulse', remaining < 0);
        });
    }

    function checkForNewTickets() {
        const currentIds = new Set(
            Array.from(document.querySelectorAll('[data-kot-id]')).map((el) => el.dataset.kotId)
        );
        if (window.__kdsKnownIds) {
            let hasNew = false;
            currentIds.forEach((id) => {
                if (!window.__kdsKnownIds.has(id)) hasNew = true;
            });
            if (hasNew) playAlert();
        }
        window.__kdsKnownIds = currentIds;
    }

    function checkForOverdueChime() {
        const anyOverdue = document.querySelector('[data-kot-id] .kds-countdown-badge.bg-rose-100');
        if (anyOverdue) playAlert();
    }

    checkForNewTickets();
    setInterval(tickCountdowns, 1000);
    setInterval(checkForNewTickets, 5000);
    if (alertIntervalMs > 0) setInterval(checkForOverdueChime, alertIntervalMs);
})();
</script>
