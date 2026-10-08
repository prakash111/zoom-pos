@extends('layouts.superadmin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                Super Admin Promotional Broadcasts
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Dispatch promotional cards, official announcements, banners and downloadable PDF guides to tenant POS & staff notification trays.
            </p>
        </div>
    </div>

    @if(session('status'))
        <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Broadcast Dispatcher Form -->
        <div class="lg:col-span-1 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>📢</span> Create New Broadcast Announcement
            </h2>
            <form action="{{ route('superadmin.broadcasts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Announcement Title</label>
                    <input type="text" name="title" required placeholder="e.g. Festive Offer or Release Notes"
                           class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Message Content</label>
                    <textarea name="message" rows="3" required placeholder="Write announcement details..."
                              class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Target Audience</label>
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <label class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer text-xs">
                            <input type="radio" name="target_type" value="all_tenants" checked onchange="document.getElementById('tenantSelectorBox').classList.add('hidden')">
                            <span class="text-slate-800 dark:text-slate-200 font-semibold">All Tenants</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 cursor-pointer text-xs">
                            <input type="radio" name="target_type" value="selected_tenants" onchange="document.getElementById('tenantSelectorBox').classList.remove('hidden')">
                            <span class="text-slate-800 dark:text-slate-200 font-semibold">Selected Tenants</span>
                        </label>
                    </div>
                    <div id="tenantSelectorBox" class="hidden space-y-1">
                        <label class="block text-[11px] text-slate-500">Select Specific Tenants</label>
                        <select name="target_ids[]" multiple class="w-full px-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white" size="4">
                            @foreach($tenants ?? [] as $t)
                                <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->id }})</option>
                            @endforeach
                        </select>
                        <span class="text-[10px] text-slate-400">Hold Ctrl / Cmd to select multiple</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Banner Image (File or URL)</label>
                    <input type="file" name="banner_image" accept="image/*"
                           class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-200 mb-1.5">
                    <input type="url" name="banner_image_url" placeholder="Or paste image URL"
                           class="w-full px-3 py-1.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">PDF Product Brochure (File or URL)</label>
                    <input type="file" name="pdf_file" accept=".pdf"
                           class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-200 mb-1.5">
                    <input type="url" name="pdf_url" placeholder="Or paste brochure PDF URL"
                           class="w-full px-3 py-1.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">CTA Button Label</label>
                        <input type="text" name="cta_label" placeholder="e.g. Learn More"
                               class="w-full px-3 py-1.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">CTA URL</label>
                        <input type="url" name="cta_url" placeholder="https://..."
                               class="w-full px-3 py-1.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white">
                    </div>
                </div>

                <button type="submit" class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-600/20 transition">
                    Dispatch Promotional Broadcast
                </button>
            </form>
        </div>

        <!-- Broadcast Feed & History -->
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center justify-between">
                <span>Active & Past Announcements</span>
                <span class="text-xs font-normal text-slate-500">{{ $broadcasts->total() }} total</span>
            </h2>

            @forelse($broadcasts as $bc)
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md {{ $bc->is_active ? 'bg-emerald-500/15 text-emerald-500' : 'bg-slate-500/15 text-slate-400' }}">
                                    {{ $bc->is_active ? 'ACTIVE' : 'INACTIVE' }}
                                </span>
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-md bg-blue-500/15 text-blue-500">
                                    {{ $bc->target_type === 'selected_tenants' ? 'Targeted (' . count($bc->target_ids ?? []) . ' tenants)' : ($bc->target_type === 'all_staff' || $bc->target_type === 'selected_staff' ? 'Store Staff' : 'All Tenants') }}
                                </span>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $bc->title }}</h3>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $bc->message }}</p>
                        </div>
                        <form action="{{ route('superadmin.broadcasts.destroy', $bc->id) }}" method="POST" onsubmit="return confirm('Delete announcement?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-rose-500 hover:text-rose-400 text-xs font-medium">Delete</button>
                        </form>
                    </div>

                    @if($bc->banner_image_url)
                        <div class="rounded-xl overflow-hidden max-h-48 border border-slate-200 dark:border-slate-800">
                            <img src="{{ $bc->banner_image_url }}" alt="Banner" class="w-full h-auto object-cover">
                        </div>
                    @endif

                    <div class="flex items-center gap-3 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                        @if($bc->pdf_url)
                            <a href="{{ $bc->pdf_url }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-500/10 text-rose-500 rounded-lg font-semibold hover:bg-rose-500/20">
                                📄 Download PDF Brochure
                            </a>
                        @endif
                        @if($bc->cta_url)
                            <a href="{{ $bc->cta_url }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500/10 text-blue-500 rounded-lg font-semibold hover:bg-blue-500/20">
                                🔗 {{ $bc->cta_label ?: 'View Link' }}
                            </a>
                        @endif
                        <span class="text-slate-400 text-[11px] ml-auto">
                            Dispatched {{ $bc->created_at->diffForHumans() }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                    <p class="text-xs text-slate-500">No promotional announcements dispatched yet.</p>
                </div>
            @endforelse

            {{ $broadcasts->links() }}
        </div>
    </div>
</div>
@endsection
