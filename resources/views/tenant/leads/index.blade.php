@extends('layouts.tenant')

@section('content')
<div class="space-y-6" x-data="{
    activeTab: '{{ $tab ?? 'pipeline' }}',
    showCaptureModal: false,
    editModalOpen: false,
    activityModalOpen: false,
    selectedLead: null,
    activityLeadId: null,
    activityLeadTitle: '',
    openEdit(lead) {
        this.selectedLead = lead;
        this.editModalOpen = true;
    },
    openActivity(id, title) {
        this.activityLeadId = id;
        this.activityLeadTitle = title;
        this.activityModalOpen = true;
    }
}">
    <!-- Status Notifications -->
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-xs border border-emerald-200/50 dark:border-emerald-800/50">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (isset($errors) && $errors->any())
        <div class="px-5 py-3 rounded-2xl bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 text-xs sm:text-sm font-semibold flex flex-col gap-1 shadow-xs border border-rose-200/50 dark:border-rose-800/50">
            @foreach ($errors->all() as $error)
                <div class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0"></span>
                    <span>{{ $error }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-2xl bg-blue-600/10 dark:bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xl shadow-xs">
                    🎯
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                        {{ __('Lead Management') }}
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Track pipeline stages, capture prospects, schedule follow-ups and convert to sales.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <button type="button"
                    @click="activeTab = 'capture'"
                    class="px-4 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>{{ __('Capture New Lead') }}</span>
            </button>
        </div>
    </div>

    <!-- Navigation Tabs (Matching Store Settings & Store Profile layout) -->
    <div class="border-b border-slate-200 dark:border-slate-800">
        <nav class="flex space-x-2 sm:space-x-4 overflow-x-auto pb-px" aria-label="Tabs">
            <button type="button"
                    @click="activeTab = 'pipeline'"
                    :class="activeTab === 'pipeline'
                        ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400 font-bold'
                        : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 font-medium'"
                    class="whitespace-nowrap py-3 px-3.5 border-b-2 text-xs sm:text-sm flex items-center gap-2 cursor-pointer transition-colors">
                <span>📊</span>
                <span>{{ __('Overview & Pipeline') }}</span>
                <span class="ml-1 text-[11px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold">
                    {{ $metrics['total'] }}
                </span>
            </button>

            <button type="button"
                    @click="activeTab = 'all'"
                    :class="activeTab === 'all'
                        ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400 font-bold'
                        : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 font-medium'"
                    class="whitespace-nowrap py-3 px-3.5 border-b-2 text-xs sm:text-sm flex items-center gap-2 cursor-pointer transition-colors">
                <span>📋</span>
                <span>{{ __('All Leads') }}</span>
                <span class="ml-1 text-[11px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold">
                    {{ $leads->total() }}
                </span>
            </button>

            <button type="button"
                    @click="activeTab = 'capture'"
                    :class="activeTab === 'capture'
                        ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400 font-bold'
                        : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 font-medium'"
                    class="whitespace-nowrap py-3 px-3.5 border-b-2 text-xs sm:text-sm flex items-center gap-2 cursor-pointer transition-colors">
                <span>➕</span>
                <span>{{ __('Capture Lead') }}</span>
            </button>

            <button type="button"
                    @click="activeTab = 'activities'"
                    :class="activeTab === 'activities'
                        ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400 font-bold'
                        : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 font-medium'"
                    class="whitespace-nowrap py-3 px-3.5 border-b-2 text-xs sm:text-sm flex items-center gap-2 cursor-pointer transition-colors">
                <span>🔔</span>
                <span>{{ __('Follow-ups & Reminders') }}</span>
                @if (count($pendingActivities) > 0)
                    <span class="ml-1 text-[11px] px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 font-bold">
                        {{ count($pendingActivities) }}
                    </span>
                @endif
            </button>
        </nav>
    </div>

    <!-- TAB 1: OVERVIEW & PIPELINE -->
    <div x-show="activeTab === 'pipeline'" class="space-y-6">
        <!-- KPI Metrics Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 border border-slate-100 dark:border-slate-800 shadow-[0_4px_25px_rgb(0,0,0,0.03)] flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total Leads') }}</span>
                    <span class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-sm">📈</span>
                </div>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">{{ $metrics['total'] }}</div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">{{ __('Across all active stages') }}</div>
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 border border-slate-100 dark:border-slate-800 shadow-[0_4px_25px_rgb(0,0,0,0.03)] flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Pipeline Value') }}</span>
                    <span class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm">💰</span>
                </div>
                <div class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400">
                    {{ number_format($metrics['pipeline_value'], 2) }}
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">{{ __('Weighted open opportunities') }}</div>
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 border border-slate-100 dark:border-slate-800 shadow-[0_4px_25px_rgb(0,0,0,0.03)] flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Proposals Sent') }}</span>
                    <span class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center text-sm">📑</span>
                </div>
                <div class="text-2xl sm:text-3xl font-black text-purple-600 dark:text-purple-400">{{ $metrics['proposal_sent'] }}</div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">{{ __('Awaiting customer decision') }}</div>
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 border border-slate-100 dark:border-slate-800 shadow-[0_4px_25px_rgb(0,0,0,0.03)] flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Won Deals') }}</span>
                    <span class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm">🏆</span>
                </div>
                <div class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400">{{ $metrics['won'] }}</div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">{{ number_format($metrics['won_value'], 2) }} {{ __('closed revenue') }}</div>
            </div>
        </div>

        <!-- Pipeline Stages Kanban-Style Overview -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-100 dark:border-slate-800 shadow-[0_4px_25px_rgb(0,0,0,0.03)]">
            <h2 class="text-base font-black text-slate-900 dark:text-white mb-4 flex items-center justify-between">
                <span>{{ __('Pipeline Distribution') }}</span>
                <span class="text-xs font-normal text-slate-400">{{ __('Live stage breakdown') }}</span>
            </h2>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <!-- Stage 1: New -->
                <a href="{{ route('tenant.leads.index', ['stage' => 'new', 'tab' => 'all']) }}"
                   class="p-4 rounded-2xl bg-blue-50/50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/30 hover:border-blue-300 dark:hover:border-blue-700 transition-all flex flex-col items-center text-center group">
                    <span class="w-3 h-3 rounded-full bg-blue-500 mb-2"></span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('New Leads') }}</span>
                    <span class="text-xl font-black text-blue-600 dark:text-blue-400 my-1 group-hover:scale-110 transition-transform">{{ $metrics['new'] }}</span>
                    <span class="text-[10px] text-slate-400 uppercase font-bold">{{ __('Initial Inquiry') }}</span>
                </a>

                <!-- Stage 2: Contacted -->
                <a href="{{ route('tenant.leads.index', ['stage' => 'contacted', 'tab' => 'all']) }}"
                   class="p-4 rounded-2xl bg-cyan-50/50 dark:bg-cyan-950/20 border border-cyan-100 dark:border-cyan-900/30 hover:border-cyan-300 dark:hover:border-cyan-700 transition-all flex flex-col items-center text-center group">
                    <span class="w-3 h-3 rounded-full bg-cyan-500 mb-2"></span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Contacted') }}</span>
                    <span class="text-xl font-black text-cyan-600 dark:text-cyan-400 my-1 group-hover:scale-110 transition-transform">{{ $metrics['contacted'] }}</span>
                    <span class="text-[10px] text-slate-400 uppercase font-bold">{{ __('In Touch') }}</span>
                </a>

                <!-- Stage 3: Qualified -->
                <a href="{{ route('tenant.leads.index', ['stage' => 'qualified', 'tab' => 'all']) }}"
                   class="p-4 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/30 hover:border-indigo-300 dark:hover:border-indigo-700 transition-all flex flex-col items-center text-center group">
                    <span class="w-3 h-3 rounded-full bg-indigo-500 mb-2"></span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Qualified') }}</span>
                    <span class="text-xl font-black text-indigo-600 dark:text-indigo-400 my-1 group-hover:scale-110 transition-transform">{{ $metrics['qualified'] }}</span>
                    <span class="text-[10px] text-slate-400 uppercase font-bold">{{ __('High Intent') }}</span>
                </a>

                <!-- Stage 4: Proposal Sent -->
                <a href="{{ route('tenant.leads.index', ['stage' => 'proposal_sent', 'tab' => 'all']) }}"
                   class="p-4 rounded-2xl bg-purple-50/50 dark:bg-purple-950/20 border border-purple-100 dark:border-purple-900/30 hover:border-purple-300 dark:hover:border-purple-700 transition-all flex flex-col items-center text-center group">
                    <span class="w-3 h-3 rounded-full bg-purple-500 mb-2"></span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Proposal Sent') }}</span>
                    <span class="text-xl font-black text-purple-600 dark:text-purple-400 my-1 group-hover:scale-110 transition-transform">{{ $metrics['proposal_sent'] }}</span>
                    <span class="text-[10px] text-slate-400 uppercase font-bold">{{ __('Quotation Sent') }}</span>
                </a>

                <!-- Stage 5: Won -->
                <a href="{{ route('tenant.leads.index', ['stage' => 'won', 'tab' => 'all']) }}"
                   class="p-4 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30 hover:border-emerald-300 dark:hover:border-emerald-700 transition-all flex flex-col items-center text-center group">
                    <span class="w-3 h-3 rounded-full bg-emerald-500 mb-2"></span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Closed Won') }}</span>
                    <span class="text-xl font-black text-emerald-600 dark:text-emerald-400 my-1 group-hover:scale-110 transition-transform">{{ $metrics['won'] }}</span>
                    <span class="text-[10px] text-slate-400 uppercase font-bold">{{ __('Converted') }}</span>
                </a>

                <!-- Stage 6: Lost -->
                <a href="{{ route('tenant.leads.index', ['stage' => 'lost', 'tab' => 'all']) }}"
                   class="p-4 rounded-2xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/30 hover:border-rose-300 dark:hover:border-rose-700 transition-all flex flex-col items-center text-center group">
                    <span class="w-3 h-3 rounded-full bg-rose-500 mb-2"></span>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Closed Lost') }}</span>
                    <span class="text-xl font-black text-rose-600 dark:text-rose-400 my-1 group-hover:scale-110 transition-transform">{{ $metrics['lost'] }}</span>
                    <span class="text-[10px] text-slate-400 uppercase font-bold">{{ __('Archived') }}</span>
                </a>
            </div>
        </div>

        <!-- Quick Actions & Recent Follow-ups -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- Upcoming Reminders -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-100 dark:border-slate-800 shadow-[0_4px_25px_rgb(0,0,0,0.03)]">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>⏰</span>
                        <span>{{ __('Upcoming Follow-ups') }}</span>
                    </h3>
                    <button type="button" @click="activeTab = 'activities'" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">
                        {{ __('View all') }} &rarr;
                    </button>
                </div>

                @if (count($pendingActivities) === 0)
                    <div class="py-8 text-center text-slate-400 dark:text-slate-500 text-xs">
                        <span class="text-2xl block mb-2">✨</span>
                        {{ __('No pending reminders scheduled. You are all caught up!') }}
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($pendingActivities->take(4) as $act)
                            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate">
                                        {{ $act->title }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-2 mt-0.5">
                                        @if ($act->lead)
                                            <span class="font-mono text-blue-600 dark:text-blue-400">{{ $act->lead->lead_code }}</span>
                                            <span>&bull;</span>
                                            <span>{{ $act->lead->name }}</span>
                                        @endif
                                        @if ($act->due_date)
                                            <span>&bull;</span>
                                            <span>{{ $act->due_date->format('M d, H:i') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <form action="{{ route('tenant.leads.activities.complete', $act->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-2.5 py-1 rounded-xl text-[11px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white active:scale-95 transition-all">
                                        ✓ {{ __('Done') }}
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Recent Activity Timeline -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-100 dark:border-slate-800 shadow-[0_4px_25px_rgb(0,0,0,0.03)]">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📝</span>
                        <span>{{ __('Recent Timeline') }}</span>
                    </h3>
                </div>

                @if (count($recentActivities) === 0)
                    <div class="py-8 text-center text-slate-400 dark:text-slate-500 text-xs">
                        {{ __('No recent activities recorded.') }}
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($recentActivities->take(4) as $act)
                            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 flex items-center gap-3">
                                <div class="w-7 h-7 rounded-xl bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-300 flex items-center justify-center text-xs shrink-0">
                                    @if ($act->type === 'call') 📞 @elseif($act->type === 'meeting') 👥 @elseif($act->type === 'status_change') 🔄 @else 📄 @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate">{{ $act->title }}</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                        {{ $act->description ?: ($act->lead ? $act->lead->name : '') }}
                                    </div>
                                </div>
                                <span class="text-[10px] text-slate-400 whitespace-nowrap">
                                    {{ $act->completed_at ? $act->completed_at->diffForHumans() : '' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- TAB 2: ALL LEADS TABLE -->
    <div x-show="activeTab === 'all'" class="space-y-4">
        <!-- Search & Filters -->
        <form method="GET" action="{{ route('tenant.leads.index') }}" class="bg-white dark:bg-slate-900 rounded-3xl p-4 sm:p-5 border border-slate-100 dark:border-slate-800 shadow-[0_4px_25px_rgb(0,0,0,0.03)] flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <input type="hidden" name="tab" value="all">

            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text"
                       name="q"
                       value="{{ $search }}"
                       placeholder="{{ __('Search leads by code, name, phone, company...') }}"
                       class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl py-2.5 pl-10 pr-4 text-xs sm:text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="flex items-center gap-2">
                <select name="stage" class="bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl py-2.5 px-3 text-xs text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500">
                    <option value="">{{ __('All Stages') }}</option>
                    <option value="new" @selected($stage === 'new')>{{ __('New Lead') }}</option>
                    <option value="contacted" @selected($stage === 'contacted')>{{ __('Contacted') }}</option>
                    <option value="qualified" @selected($stage === 'qualified')>{{ __('Qualified') }}</option>
                    <option value="proposal_sent" @selected($stage === 'proposal_sent')>{{ __('Proposal Sent') }}</option>
                    <option value="won" @selected($stage === 'won')>{{ __('Closed Won') }}</option>
                    <option value="lost" @selected($stage === 'lost')>{{ __('Closed Lost') }}</option>
                </select>

                <button type="submit" class="px-4 py-2.5 rounded-2xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white transition-all shadow-xs">
                    {{ __('Filter') }}
                </button>

                @if ($search || $stage || $assignedTo || $sourceId)
                    <a href="{{ route('tenant.leads.index', ['tab' => 'all']) }}" class="px-3 py-2.5 rounded-2xl text-xs font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                        {{ __('Clear') }}
                    </a>
                @endif
            </div>
        </form>

        <!-- Leads Table Card -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-[0_4px_25px_rgb(0,0,0,0.03)] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4">{{ __('Lead Code / Date') }}</th>
                            <th class="py-3.5 px-4">{{ __('Prospect / Contact') }}</th>
                            <th class="py-3.5 px-4">{{ __('Stage') }}</th>
                            <th class="py-3.5 px-4">{{ __('Expected Value') }}</th>
                            <th class="py-3.5 px-4">{{ __('Assigned Rep') }}</th>
                            <th class="py-3.5 px-4 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($leads as $lead)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="py-3.5 px-4">
                                    <div class="font-mono font-black text-blue-600 dark:text-blue-400 text-xs">{{ $lead->lead_code }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">{{ $lead->created_at->format('M d, Y') }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $lead->name }}</div>
                                    @if ($lead->company_name)
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">{{ $lead->company_name }}</div>
                                    @endif
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        {{ $lead->phone ?: $lead->email ?: 'No contact' }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    @php
                                        $stageBadge = match($lead->stage) {
                                            'new' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border-blue-200 dark:border-blue-800',
                                            'contacted' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800',
                                            'qualified' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
                                            'proposal_sent' => 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                                            'won' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                                            'lost' => 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                                            default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-[11px] font-bold border {{ $stageBadge }}">
                                        {{ ucfirst(str_replace('_', ' ', $lead->stage)) }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                    {{ number_format($lead->expected_value ?? 0, 2) }}
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-300">
                                    {{ $lead->assignedUser?->name ?? __('Unassigned') }}
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Add Activity / Reminder -->
                                        <button type="button"
                                                @click="openActivity({{ $lead->id }}, '{{ addslashes($lead->name) }}')"
                                                class="p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 hover:text-blue-600 transition-colors"
                                                title="{{ __('Log Activity') }}">
                                            ⏰
                                        </button>

                                        <!-- Edit Lead -->
                                        <button type="button"
                                                @click="openEdit({{ json_encode($lead) }})"
                                                class="p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 hover:text-blue-600 transition-colors"
                                                title="{{ __('Edit Lead') }}">
                                            ✏️
                                        </button>

                                        <!-- Delete Lead -->
                                        <form action="{{ route('tenant.leads.destroy', $lead->id) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to delete this lead?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded-xl hover:bg-rose-50 dark:hover:bg-rose-900/30 text-slate-400 hover:text-rose-600 transition-colors" title="{{ __('Delete') }}">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400 dark:text-slate-500 text-xs">
                                    <div class="text-3xl mb-2">🎯</div>
                                    <p class="font-bold text-slate-700 dark:text-slate-300">{{ __('No leads found') }}</p>
                                    <p class="text-[11px] mt-1">{{ __('Try adjusting your search criteria or capture a new lead.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($leads->hasPages())
                <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                    {{ $leads->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- TAB 3: CAPTURE NEW LEAD -->
    <div x-show="activeTab === 'capture'" class="max-w-3xl mx-auto">
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-100 dark:border-slate-800 shadow-[0_4px_25px_rgb(0,0,0,0.03)] space-y-6">
            <div>
                <h2 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <span>➕</span>
                    <span>{{ __('Capture New Lead') }}</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Link to an existing CRM customer or enter new prospect details. A CRM profile is automatically synced.') }}
                </p>
            </div>

            <form action="{{ route('tenant.leads.store') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Link to Existing CRM Customer -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('Link Existing CRM Customer (Optional)') }}
                    </label>
                    <select name="customer_id" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500">
                        <option value="">-- {{ __('Select customer or create new below') }} --</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->phone ?: $c->email ?: 'No contact' }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Prospect Name *') }}</label>
                        <input type="text" name="name" required class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500" placeholder="{{ __('e.g. John Doe') }}">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Company / Organization') }}</label>
                        <input type="text" name="company_name" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500" placeholder="{{ __('e.g. Acme Corp') }}">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Phone Number') }}</label>
                        <input type="text" name="phone" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500" placeholder="{{ __('e.g. +1 555 0192') }}">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Email Address') }}</label>
                        <input type="email" name="email" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500" placeholder="{{ __('e.g. contact@example.com') }}">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Initial Stage') }}</label>
                        <select name="stage" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500">
                            <option value="new">{{ __('New Lead') }}</option>
                            <option value="contacted">{{ __('Contacted') }}</option>
                            <option value="qualified">{{ __('Qualified') }}</option>
                            <option value="proposal_sent">{{ __('Proposal Sent') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Priority') }}</label>
                        <select name="priority" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500">
                            <option value="medium">{{ __('Medium') }}</option>
                            <option value="low">{{ __('Low') }}</option>
                            <option value="high">{{ __('High') }}</option>
                            <option value="urgent">{{ __('Urgent') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Expected Deal Value') }}</label>
                        <input type="number" step="0.01" name="expected_value" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500" placeholder="0.00">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Assign Sales Representative') }}</label>
                        <select name="assigned_to" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500">
                            <option value="">-- {{ __('Select Rep') }} --</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected(auth()->id() === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Lead Source') }}</label>
                        <select name="source_id" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500">
                            <option value="">-- {{ __('Select Source') }} --</option>
                            @foreach ($sources as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Requirement Summary & Notes') }}</label>
                        <textarea name="notes" rows="3" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500" placeholder="{{ __('Customer requirements, special terms, products of interest...') }}"></textarea>
                    </div>
                </div>

                <!-- Schedule Immediate Follow-up Section -->
                <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-3">
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        {{ __('Schedule Initial Follow-up (Optional)') }}
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Reminder Title') }}</label>
                            <input type="text" name="reminder_title" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500" placeholder="{{ __('e.g. Call to discuss product demo') }}">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Due Date & Time') }}</label>
                            <input type="datetime-local" name="reminder_due_date" class="w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-800 dark:text-slate-100 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-end gap-3">
                    <button type="button" @click="activeTab = 'all'" class="px-5 py-2.5 rounded-2xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-2xl text-xs sm:text-sm font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-500/20 active:scale-95 transition-all">
                        {{ __('Save & Capture Lead') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 4: FOLLOW-UPS & REMINDERS -->
    <div x-show="activeTab === 'activities'" class="space-y-4">
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-100 dark:border-slate-800 shadow-[0_4px_25px_rgb(0,0,0,0.03)]">
            <h3 class="text-base font-black text-slate-900 dark:text-white mb-4 flex items-center justify-between">
                <span>{{ __('Scheduled Follow-ups & Reminders') }}</span>
                <span class="text-xs text-slate-400">{{ count($pendingActivities) }} {{ __('pending') }}</span>
            </h3>

            @if (count($pendingActivities) === 0)
                <div class="py-12 text-center text-slate-400 dark:text-slate-500 text-xs">
                    <div class="text-3xl mb-2">🔔</div>
                    <p class="font-bold text-slate-700 dark:text-slate-300">{{ __('No pending follow-ups') }}</p>
                    <p class="text-[11px] mt-1">{{ __('Schedule reminders from the All Leads table or when capturing leads.') }}</p>
                </div>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($pendingActivities as $act)
                        <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-2xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center text-base shrink-0 mt-0.5">
                                    ⏰
                                </div>
                                <div>
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">{{ $act->title }}</h4>
                                    @if ($act->description)
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $act->description }}</p>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-2 mt-1 text-[11px] text-slate-400">
                                        @if ($act->lead)
                                            <span class="font-mono text-blue-600 dark:text-blue-400 font-bold">{{ $act->lead->lead_code }}</span>
                                            <span>&bull;</span>
                                            <span class="font-medium text-slate-600 dark:text-slate-300">{{ $act->lead->name }}</span>
                                        @endif
                                        @if ($act->due_date)
                                            <span>&bull;</span>
                                            <span class="font-bold text-amber-600 dark:text-amber-400">{{ __('Due') }}: {{ $act->due_date->format('M d, Y h:i A') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 sm:self-center self-end">
                                <form action="{{ route('tenant.leads.activities.complete', $act->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs active:scale-95 transition-all flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        <span>{{ __('Mark Completed') }}</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- MODAL: EDIT LEAD -->
    <div x-show="editModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="editModalOpen = false"
             class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 max-w-xl w-full border border-slate-100 dark:border-slate-800 shadow-2xl space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-black text-slate-900 dark:text-white">
                    {{ __('Edit Lead Details') }}
                </h3>
                <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>

            <form x-show="selectedLead" :action="'/tenant/leads/' + (selectedLead ? selectedLead.id : '')" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Prospect Name') }}</label>
                        <input type="text" name="name" :value="selectedLead ? selectedLead.name : ''" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Company Name') }}</label>
                        <input type="text" name="company_name" :value="selectedLead ? selectedLead.company_name : ''" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Phone') }}</label>
                        <input type="text" name="phone" :value="selectedLead ? selectedLead.phone : ''" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Email') }}</label>
                        <input type="email" name="email" :value="selectedLead ? selectedLead.email : ''" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Pipeline Stage') }}</label>
                        <select name="stage" :value="selectedLead ? selectedLead.stage : 'new'" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                            <option value="new">{{ __('New Lead') }}</option>
                            <option value="contacted">{{ __('Contacted') }}</option>
                            <option value="qualified">{{ __('Qualified') }}</option>
                            <option value="proposal_sent">{{ __('Proposal Sent') }}</option>
                            <option value="won">{{ __('Closed Won') }}</option>
                            <option value="lost">{{ __('Closed Lost') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Expected Value') }}</label>
                        <input type="number" step="0.01" name="expected_value" :value="selectedLead ? selectedLead.expected_value : 0" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Assignee') }}</label>
                        <select name="assigned_to" :value="selectedLead ? selectedLead.assigned_to : ''" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                            <option value="">-- {{ __('Unassigned') }} --</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Notes') }}</label>
                        <textarea name="notes" rows="3" :value="selectedLead ? selectedLead.notes : ''" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white">
                        {{ __('Update Lead') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD ACTIVITY -->
    <div x-show="activityModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="activityModalOpen = false"
             class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 max-w-md w-full border border-slate-100 dark:border-slate-800 shadow-2xl space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-black text-slate-900 dark:text-white">
                    {{ __('Log Follow-up / Activity') }}
                </h3>
                <button type="button" @click="activityModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>

            <form :action="'/tenant/leads/' + activityLeadId + '/activities'" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Activity Type') }}</label>
                    <select name="type" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                        <option value="call">{{ __('Phone Call') }}</option>
                        <option value="meeting">{{ __('Client Meeting / Demo') }}</option>
                        <option value="email">{{ __('Email Communication') }}</option>
                        <option value="note">{{ __('General Note') }}</option>
                        <option value="task">{{ __('Follow-up Task') }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Title *') }}</label>
                    <input type="text" name="title" required placeholder="{{ __('e.g. Schedule product demo call') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Scheduled Due Date & Time') }}</label>
                    <input type="datetime-local" name="due_date" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Details & Notes') }}</label>
                    <textarea name="description" rows="3" placeholder="{{ __('Discussion points, customer feedback...') }}" class="w-full rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="activityModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white">
                        {{ __('Save Activity') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
