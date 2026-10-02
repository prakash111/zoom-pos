<div class="space-y-6 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <span>📬</span> {{ __('Online Store Inquiries') }}
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('View, manage and respond to customer inquiries and contact requests submitted through your online storefront.') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            @if(isset($tenantCompany) && $tenantCompany->exists)
                <a href="{{ $tenantCompany->getStorefrontUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition">
                    <span>↗</span> {{ __('View Live Store') }}
                </a>
            @endif
        </div>
    </div>

    <!-- Stats & Filters Bar -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <button type="button" wire:click="setFilter('all')" class="p-3.5 rounded-2xl border transition text-left cursor-pointer {{ $statusFilter === 'all' ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-300 dark:border-blue-700 shadow-sm' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300' }}">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('All Inquiries') }}</div>
            <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">{{ number_format($counts['all'] ?? 0) }}</div>
        </button>
        <button type="button" wire:click="setFilter('unread')" class="p-3.5 rounded-2xl border transition text-left cursor-pointer {{ $statusFilter === 'unread' ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-300 dark:border-amber-700 shadow-sm' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300' }}">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">{{ __('Unread / New') }}</span>
                @if(($counts['unread'] ?? 0) > 0)
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                @endif
            </div>
            <div class="text-xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ number_format($counts['unread'] ?? 0) }}</div>
        </button>
        <button type="button" wire:click="setFilter('contacted')" class="p-3.5 rounded-2xl border transition text-left cursor-pointer {{ $statusFilter === 'contacted' ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-700 shadow-sm' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300' }}">
            <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __('Contacted') }}</div>
            <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($counts['contacted'] ?? 0) }}</div>
        </button>
        <button type="button" wire:click="setFilter('closed')" class="p-3.5 rounded-2xl border transition text-left cursor-pointer {{ $statusFilter === 'closed' ? 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 shadow-sm' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300' }}">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Closed / Resolved') }}</div>
            <div class="text-xl font-extrabold text-slate-700 dark:text-slate-300 mt-1">{{ number_format($counts['closed'] ?? 0) }}</div>
        </button>
    </div>

    <!-- Search & List Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
        <!-- Search bar -->
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="relative w-full sm:w-80">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by sender, email, phone, subject...') }}" class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:outline-hidden">
                <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
            </div>
            <div class="text-xs text-slate-400 dark:text-slate-500">
                {{ __('Showing') }} <span class="font-bold text-slate-700 dark:text-slate-300">{{ $inquiries->total() }}</span> {{ __('inquiries') }}
            </div>
        </div>

        <!-- Table / List -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <th class="py-3 px-4">{{ __('Sender') }}</th>
                        <th class="py-3 px-4">{{ __('Subject & Message') }}</th>
                        <th class="py-3 px-4">{{ __('Contact') }}</th>
                        <th class="py-3 px-4">{{ __('Date') }}</th>
                        <th class="py-3 px-4">{{ __('Status') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs text-slate-700 dark:text-slate-300">
                    @forelse($inquiries as $inquiry)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition {{ $inquiry->status === 'unread' ? 'font-medium bg-amber-50/20 dark:bg-amber-950/10' : '' }}">
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                    @if($inquiry->status === 'unread')
                                        <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>
                                    @endif
                                    <span>{{ $inquiry->name }}</span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 max-w-xs sm:max-w-md">
                                <div class="font-bold text-slate-900 dark:text-white truncate">{{ $inquiry->subject ?: __('(No Subject)') }}</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ $inquiry->message }}</div>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($inquiry->email)
                                    <div><a href="mailto:{{ $inquiry->email }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $inquiry->email }}</a></div>
                                @endif
                                @if($inquiry->phone)
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5"><a href="tel:{{ $inquiry->phone }}" class="hover:underline">{{ $inquiry->phone }}</a></div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap text-slate-500 dark:text-slate-400 text-[11px]">
                                <div>{{ $inquiry->created_at?->diffForHumans() }}</div>
                                <div class="text-[10px] text-slate-400 dark:text-slate-500">{{ $inquiry->created_at?->format('M d, Y h:i A') }}</div>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($inquiry->status === 'unread')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-950/70 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-700">
                                        {{ __('Unread') }}
                                    </span>
                                @elseif($inquiry->status === 'contacted')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/70 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-700">
                                        {{ __('Contacted') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700">
                                        {{ __('Closed') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" wire:click="viewInquiry({{ $inquiry->id }})" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900 transition cursor-pointer">
                                        {{ __('View') }}
                                    </button>
                                    @if($inquiry->status !== 'contacted')
                                        <button type="button" wire:click="updateStatus({{ $inquiry->id }}, 'contacted')" title="{{ __('Mark Contacted') }}" class="p-1 rounded-lg text-slate-400 hover:text-emerald-600 transition cursor-pointer">
                                            ✓
                                        </button>
                                    @endif
                                    <button type="button" wire:click="deleteInquiry({{ $inquiry->id }})" title="{{ __('Delete') }}" onclick="return confirm('Delete this inquiry?') || event.stopImmediatePropagation()" class="p-1 rounded-lg text-slate-400 hover:text-rose-600 transition cursor-pointer">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400 dark:text-slate-500">
                                <div class="text-3xl mb-2">📬</div>
                                <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('No inquiries found') }}</div>
                                <div class="text-xs text-slate-400 mt-1">{{ __('Customer messages submitted via your storefront contact form will appear here.') }}</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inquiries->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $inquiries->links() }}
            </div>
        @endif
    </div>

    <!-- Detail Modal -->
    @if($showDetailModal && $viewingInquiry)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" wire:keydown.escape="closeModal">
            <div class="relative bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 w-full max-w-2xl p-6 shadow-2xl space-y-4">
                <div class="flex items-start justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>💬</span> {{ $viewingInquiry->subject ?: __('Inquiry Details') }}
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ __('Received :time from :name', ['time' => $viewingInquiry->created_at?->diffForHumans() ?? '', 'name' => $viewingInquiry->name]) }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Sender Info Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-slate-50 dark:bg-slate-800/40 p-3.5 rounded-2xl border border-slate-100 dark:border-slate-800 text-xs">
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Sender Name') }}</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $viewingInquiry->name }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Email Address') }}</span>
                        @if($viewingInquiry->email)
                            <a href="mailto:{{ $viewingInquiry->email }}" class="font-bold text-blue-600 dark:text-blue-400 hover:underline">{{ $viewingInquiry->email }}</a>
                        @else
                            <span class="text-slate-400">{{ __('None provided') }}</span>
                        @endif
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Phone Number') }}</span>
                        @if($viewingInquiry->phone)
                            <a href="tel:{{ $viewingInquiry->phone }}" class="font-bold text-blue-600 dark:text-blue-400 hover:underline">{{ $viewingInquiry->phone }}</a>
                        @else
                            <span class="text-slate-400">{{ __('None provided') }}</span>
                        @endif
                    </div>
                    <div>
                        <span class="text-slate-400 block text-[10px] uppercase font-bold">{{ __('Date & IP') }}</span>
                        <span class="text-slate-600 dark:text-slate-400">{{ $viewingInquiry->created_at?->format('M d, Y h:i A') }} {{ $viewingInquiry->ip_address ? '('.$viewingInquiry->ip_address.')' : '' }}</span>
                    </div>
                </div>

                <!-- Message Body -->
                <div>
                    <label class="text-[10px] uppercase font-bold text-slate-400 block mb-1.5">{{ __('Inquiry Message') }}</label>
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-relaxed">
                        {{ $viewingInquiry->message }}
                    </div>
                </div>

                <!-- Status Update & Modal Actions -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs text-slate-400 font-bold mr-1">{{ __('Status:') }}</span>
                        <button type="button" wire:click="updateStatus({{ $viewingInquiry->id }}, 'unread')" class="px-2.5 py-1 text-xs rounded-lg font-bold border transition cursor-pointer {{ $viewingInquiry->status === 'unread' ? 'bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-400 border-amber-300 dark:border-amber-700' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-transparent hover:border-slate-300' }}">
                            {{ __('Unread') }}
                        </button>
                        <button type="button" wire:click="updateStatus({{ $viewingInquiry->id }}, 'contacted')" class="px-2.5 py-1 text-xs rounded-lg font-bold border transition cursor-pointer {{ $viewingInquiry->status === 'contacted' ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-700' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-transparent hover:border-slate-300' }}">
                            {{ __('Contacted') }}
                        </button>
                        <button type="button" wire:click="updateStatus({{ $viewingInquiry->id }}, 'closed')" class="px-2.5 py-1 text-xs rounded-lg font-bold border transition cursor-pointer {{ $viewingInquiry->status === 'closed' ? 'bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 border-slate-300 dark:border-slate-600' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-transparent hover:border-slate-300' }}">
                            {{ __('Closed') }}
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        @if($viewingInquiry->email)
                            <a href="mailto:{{ $viewingInquiry->email }}?subject={{ rawurlencode('Re: ' . ($viewingInquiry->subject ?: 'Inquiry')) }}" class="px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition">
                                ✉️ {{ __('Reply Email') }}
                            </a>
                        @endif
                        <button type="button" wire:click="deleteInquiry({{ $viewingInquiry->id }})" onclick="return confirm('Delete this inquiry?') || event.stopImmediatePropagation()" class="px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 text-rose-600 dark:text-rose-400 text-xs font-bold border border-rose-200 dark:border-rose-800 transition cursor-pointer">
                            🗑️ {{ __('Delete') }}
                        </button>
                        <button type="button" wire:click="closeModal" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold hover:bg-slate-200 transition cursor-pointer">
                            {{ __('Close') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
