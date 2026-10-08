@extends('layouts.tenant')

@section('title', 'Create Staff Notification')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('tenant.notifications.index') }}" class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Create Staff Notification</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Broadcast an announcement to store employee screens & POS tablets</p>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Form Card -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
        <form action="{{ route('tenant.notifications.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <!-- Notification Title -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Announcement Title <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="title" required value="{{ old('title') }}"
                       placeholder="e.g. End of Day Cash Register Reconciliation Protocol"
                       class="w-full px-3.5 py-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none transition">
            </div>

            <!-- Message Body -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Notification Message <span class="text-rose-500">*</span>
                </label>
                <textarea name="message" rows="4" required
                          placeholder="Write the full briefing message or instructions for store staff..."
                          class="w-full px-3.5 py-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500 outline-none transition">{{ old('message') }}</textarea>
            </div>

            <!-- Target Audience Selector -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
                    Target Audience <span class="text-rose-500">*</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-data="{ targetType: '{{ old('target_type', 'all_staff') }}' }">
                    <label class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition"
                           :class="targetType === 'all_staff' ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-400' : 'bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700 text-slate-400'">
                        <input type="radio" name="target_type" value="all_staff" x-model="targetType" class="text-emerald-500 focus:ring-emerald-500">
                        <div>
                            <div class="text-xs font-bold text-slate-900 dark:text-white">All Store Staff</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400">Broadcasts to all cashiers, managers, and staff</div>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition"
                           :class="targetType === 'selected_staff' ? 'bg-sky-500/10 border-sky-500/40 text-sky-400' : 'bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700 text-slate-400'">
                        <input type="radio" name="target_type" value="selected_staff" x-model="targetType" class="text-sky-500 focus:ring-sky-500">
                        <div>
                            <div class="text-xs font-bold text-slate-900 dark:text-white">Specific Staff Members</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400">Only selected employee IDs receive this alert</div>
                        </div>
                    </label>

                    <!-- Staff Selection Box (Conditional) -->
                    <div x-show="targetType === 'selected_staff'" class="sm:col-span-2 mt-2 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 space-y-2">
                        <div class="text-xs font-bold text-slate-800 dark:text-slate-200 mb-2">Select Staff Recipients</div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto pr-1">
                            @forelse($staffList as $st)
                                <label class="flex items-center gap-2 p-2 rounded-lg bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 text-xs cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                    <input type="checkbox" name="staff_ids[]" value="{{ $st->id }}" class="rounded text-emerald-500 focus:ring-emerald-500">
                                    <span class="font-semibold text-slate-900 dark:text-white truncate">{{ $st->name }}</span>
                                    <span class="text-[10px] text-slate-400 ml-auto capitalize">({{ $st->role ?? 'Staff' }})</span>
                                </label>
                            @empty
                                <div class="text-xs text-slate-500 py-2 sm:col-span-2">No other store staff registered yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Optional Media Banner -->
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                    Attach Notice Banner Image <span class="text-slate-400 font-normal">(Optional)</span>
                </label>
                <input type="file" name="banner_image" accept="image/*"
                       class="w-full text-xs text-slate-500 dark:text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-200 hover:file:bg-slate-200 cursor-pointer">
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-3">
                <a href="{{ route('tenant.notifications.index') }}" class="px-4 py-2.5 text-xs font-semibold rounded-xl text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-600/20 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Dispatch Notification Now
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
