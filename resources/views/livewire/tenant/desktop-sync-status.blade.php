<div>
    @if ($isDesktop)
        <div wire:poll.3s="refreshStatus"
             class="px-2.5 py-1.5 rounded-xl flex items-center gap-1.5 text-[11px] font-bold
                @if ($status === 'synced') bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400
                @elseif ($status === 'syncing') bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400
                @elseif ($status === 'online') bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400
                @else bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400
                @endif"
             title="{{ $lastSyncedAt ? __('Last synced :time', ['time' => $lastSyncedAt]) : __('Not yet synced') }}">
            <span class="w-1.5 h-1.5 rounded-full
                @if ($status === 'synced') bg-emerald-500
                @elseif ($status === 'syncing') bg-blue-500 animate-pulse
                @elseif ($status === 'online') bg-amber-500
                @else bg-slate-400
                @endif"></span>
            <span>
                @if ($status === 'synced') {{ __('Synced') }}
                @elseif ($status === 'syncing') {{ __('Syncing…') }}
                @elseif ($status === 'online') {{ __('Online') }}
                @else {{ __('Offline') }}
                @endif
            </span>
        </div>
    @endif
</div>
