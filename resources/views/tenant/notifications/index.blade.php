@extends('layouts.tenant')

@section('title', 'Staff Notifications & Bulletins')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                </svg>
                Staff Notifications & Bulletins
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Broadcast instant announcements, shift briefings, and store alerts to your employee POS terminals.
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if (Route::has('tenant.chat.index'))
            <a href="{{ route('tenant.chat.index') }}" class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-slate-800 text-slate-200 hover:bg-slate-700 transition flex items-center gap-1.5">
                <svg class="w-4 h-4 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                Live Staff Chat
            </a>
            @endif
            @if (Route::has('tenant.notifications.create'))
            <a href="{{ route('tenant.notifications.create') }}" class="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white shadow-lg shadow-emerald-600/20 transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Staff Notification
            </a>
            @endif
        </div>
    </div>

    @if(session('status'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold flex items-center justify-between">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Dispatched</div>
            <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $totalCount }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active Bulletins</div>
            <div class="text-2xl font-black text-emerald-500 mt-1">{{ $activeCount }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Target Channels</div>
            <div class="text-2xl font-black text-sky-500 mt-1">POS & Staff App</div>
        </div>
    </div>

    <!-- Announcements List -->
    <div class="space-y-4">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center justify-between">
            <span>Dispatched Staff Notices</span>
            <span class="text-xs font-normal text-slate-500">{{ $announcements->total() }} total</span>
        </h2>

        @forelse($announcements as $item)
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-md {{ $item->target_type === 'all_staff' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-sky-500/15 text-sky-400' }}">
                                {{ $item->target_type === 'all_staff' ? 'All Staff' : 'Selected Staff (' . count($item->target_ids ?? []) . ')' }}
                            </span>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $item->title }}</h3>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ $item->message }}</p>
                    </div>

                    <form action="{{ route('tenant.notifications.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Delete this announcement?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-rose-500 hover:text-rose-400 text-xs font-medium px-2 py-1 rounded-lg hover:bg-rose-500/10 transition">
                            Delete
                        </button>
                    </form>
                </div>

                @if($item->banner_image_url)
                    <div class="rounded-xl overflow-hidden max-h-48 border border-slate-200 dark:border-slate-800">
                        <img src="{{ $item->banner_image_url }}" alt="Banner" class="w-full h-auto object-cover">
                    </div>
                @endif

                <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800/80 text-[11px] text-slate-400">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Dispatched {{ $item->created_at?->format('d M Y, h:i A') }} ({{ $item->created_at?->diffForHumans() }})
                    </span>
                    <span class="text-emerald-400 font-semibold">
                        ✓ Delivered to POS Terminals
                    </span>
                </div>
            </div>
        @empty
            <div class="p-12 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mx-auto text-xl">
                    📢
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">No Staff Notifications Dispatched</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                    Keep your staff updated with shift handover notes, inventory warnings, and store policy alerts.
                </p>
                <a href="{{ route('tenant.notifications.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold rounded-xl bg-emerald-600 text-white hover:bg-emerald-500 transition">
                    + Create First Notification
                </a>
            </div>
        @endforelse

        {{ $announcements->links() }}
    </div>
</div>
@endsection
