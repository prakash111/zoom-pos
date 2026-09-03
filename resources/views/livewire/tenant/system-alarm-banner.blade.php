<div wire:poll.5s="refreshAlarms" class="fixed right-3 top-16 z-40 w-[min(26rem,calc(100vw-1.5rem))] space-y-2" aria-live="assertive">
    @foreach ($orderAlarms as $alarm)
        <div class="flex items-center gap-3 rounded-2xl border-2 border-rose-500 bg-rose-600 p-3 text-white shadow-2xl shadow-rose-950/30 animate-pulse">
            <span class="text-xl">🚨</span>
            <a wire:navigate href="{{ route('tenant.restaurant.kds') }}" class="min-w-0 flex-1">
                <strong class="block truncate text-sm">{{ __('Kitchen alarm') }} · {{ $alarm['number'] }}</strong>
                <span class="block truncate text-xs text-rose-100">{{ $alarm['location'] }}</span>
            </a>
            <button type="button" wire:click="dismissOrder('{{ $alarm['id'] }}')" class="rounded-xl bg-white/20 px-3 py-2 text-[10px] font-black hover:bg-white/30">{{ __('Dismiss') }}</button>
        </div>
    @endforeach

    @foreach ($invoiceAlarms as $alarm)
        <div class="flex items-center gap-3 rounded-2xl border border-indigo-400 bg-indigo-700 p-3 text-white shadow-2xl shadow-indigo-950/30">
            <span class="text-xl">🔔</span>
            <a wire:navigate href="{{ route('tenant.financials.receivables') }}" class="min-w-0 flex-1">
                <strong class="block truncate text-sm">{{ __('Invoice due') }} · {{ $alarm['number'] }}</strong>
                <span class="block truncate text-xs text-indigo-100">{{ $alarm['customer'] }} · {{ $alarm['amount'] }}</span>
            </a>
            <button type="button" wire:click="dismissInvoice({{ $alarm['id'] }})" class="rounded-xl bg-white/20 px-3 py-2 text-[10px] font-black hover:bg-white/30">{{ __('Dismiss') }}</button>
        </div>
    @endforeach

    @script
    <script>
        $wire.on('system-alarm-chime', (event) => {
            try {
                const context = window.__systemAlarmAudio || (window.__systemAlarmAudio = new (window.AudioContext || window.webkitAudioContext)());
                const order = event.order === true;
                const pattern = order ? [880, 660, 880, 660] : [1046, 1318];
                let start = context.currentTime;
                pattern.forEach((frequency) => {
                    const oscillator = context.createOscillator();
                    const gain = context.createGain();
                    oscillator.frequency.value = frequency;
                    gain.gain.setValueAtTime(0.22, start);
                    gain.gain.exponentialRampToValueAtTime(0.001, start + 0.18);
                    oscillator.connect(gain).connect(context.destination);
                    oscillator.start(start);
                    oscillator.stop(start + 0.18);
                    start += 0.2;
                });
            } catch (_) {}
        });
    </script>
    @endscript
</div>
