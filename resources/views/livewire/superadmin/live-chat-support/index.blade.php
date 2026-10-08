<div class="flex-1 flex flex-col min-h-0 h-full w-full" 
     wire:poll.4s="pollMessages"
     x-data="{
        zoomImage: null,
        zoomScale: 1,
        activePdfUrl: null,
        activePdfName: '',
        activeReactionMsgId: null,
        openZoom(url, name) {
            this.zoomImage = { url, name: name || 'Photo' };
            this.zoomScale = 1;
        },
        closeZoom() {
            this.zoomImage = null;
            this.zoomScale = 1;
        },
        zoomIn() {
            this.zoomScale = Math.min(this.zoomScale + 0.25, 4);
        },
        zoomOut() {
            this.zoomScale = Math.max(this.zoomScale - 0.25, 0.5);
        },
        resetZoom() {
            this.zoomScale = 1;
        },
        openPdf(url, name) {
            this.activePdfUrl = url;
            this.activePdfName = name || 'Document.pdf';
        },
        closePdf() {
            this.activePdfUrl = null;
            this.activePdfName = '';
        }
     }"
     @keydown.escape.window="closeZoom(); closePdf(); activeReactionMsgId = null">

    <!-- Top Mobile Header / Status Bar -->
    <div class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 py-3 flex items-center justify-between shrink-0 shadow-xs lg:hidden">
        <div class="flex items-center gap-2">
            <span class="text-xl">🎧</span>
            <div>
                <h1 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Live Chat Support') }}</h1>
                <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ __('Tenant & Staff Live Desk Messages') }}</p>
            </div>
        </div>
        @if ($activeConversation && $tenant)
            <button type="button" 
                    wire:click="$toggle('showTenantDetailsMobile')" 
                    class="px-2.5 py-1 rounded-xl text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 flex items-center gap-1">
                <span>🏢</span>
                <span>{{ __('Tenant Info') }}</span>
            </button>
        @endif
    </div>

    <!-- Main Workspace: 3 Columns Grid -->
    <div class="flex-1 flex overflow-hidden">

        <!-- ========================================================================= -->
        <!-- 1. Left Panel: Conversations List (No "+" Button)                         -->
        <!-- ========================================================================= -->
        <div class="w-full sm:w-80 md:w-96 shrink-0 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col {{ $activeConversationId ? 'hidden md:flex' : 'flex' }}">
            
            <!-- List Header with Title and Search (NO "+" BUTTON) -->
            <div class="p-3.5 border-b border-slate-200 dark:border-slate-800 space-y-2.5 bg-slate-50/50 dark:bg-slate-950/30">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-sm font-bold shadow-2xs">
                            🎧
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">{{ __('Live Chat Support') }}</h2>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ trans_choice('{0} No conversations|{1} :count conversation|[2,*] :count conversations', $conversations->count(), ['count' => $conversations->count()]) }}
                            </p>
                        </div>
                    </div>

                    <!-- Read Filter Status Pills -->
                    <div class="flex items-center gap-1 bg-slate-200/60 dark:bg-slate-800 p-0.5 rounded-lg text-xs">
                        <button type="button" 
                                wire:click="$set('filter', 'all')" 
                                class="px-2 py-0.5 rounded-md text-[11px] font-semibold transition {{ $filter === 'all' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-2xs' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                            {{ __('All') }}
                        </button>
                        <button type="button" 
                                wire:click="$set('filter', 'unread')" 
                                class="px-2 py-0.5 rounded-md text-[11px] font-semibold transition {{ $filter === 'unread' ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-2xs' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                            {{ __('Unread') }}
                        </button>
                    </div>
                </div>

                <!-- Search Input -->
                <div class="relative">
                    <input type="text" 
                           wire:model.live.debounce.300ms="search" 
                           placeholder="{{ __('Search tenant, staff, message...') }}" 
                           class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition shadow-inner">
                    <svg class="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

            <!-- Conversations Scroll List -->
            <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60 p-1.5 space-y-1">
                @forelse ($conversations as $conv)
                    @php
                        $isActive = $activeConversationId === $conv->id;
                        $convTenant = $conv->tenant;
                        $initiator = $conv->participants->firstWhere('user.role', '!=', 'super_admin')?->user ?? $conv->participants->first()?->user;
                        $latest = $conv->latestMessage;
                        
                        // Unread messages from tenant count
                        $unreadCount = $conv->messages
                            ->where('sender_type', '!=', 'super_admin')
                            ->filter(fn($m) => empty($m->metadata['read_by_admin']))
                            ->count();
                    @endphp
                    <button type="button" 
                            wire:click="selectConversation({{ $conv->id }})" 
                            class="w-full p-2.5 rounded-2xl flex items-start gap-3 transition text-left border cursor-pointer {{ $isActive ? 'bg-indigo-50/70 dark:bg-indigo-950/40 border-indigo-200 dark:border-indigo-800/60 shadow-xs' : 'hover:bg-slate-50 dark:hover:bg-slate-800/40 border-transparent' }}">
                        
                        <!-- Tenant Initials Avatar -->
                        <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white font-extrabold flex items-center justify-center shrink-0 shadow-xs text-xs uppercase relative">
                            {{ substr($convTenant?->name ?? 'TN', 0, 2) }}
                            @if ($unreadCount > 0)
                                <span class="absolute -top-1 -right-1 w-3 h-3 bg-emerald-500 border-2 border-white dark:border-slate-900 rounded-full"></span>
                            @endif
                        </div>

                        <!-- Info Summary -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1 mb-0.5">
                                <span class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                    {{ $convTenant?->name ?? $conv->title ?? __('Live Support Desk') }}
                                </span>
                                <span class="text-[10px] text-slate-400 dark:text-slate-500 shrink-0 font-medium">
                                    {{ $conv->last_message_at ? $conv->last_message_at->shortRelativeDiffForHumans() : '' }}
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400 truncate">
                                    {{ $initiator?->name ?? __('Staff Member') }}
                                </span>
                                @if ($initiator?->role)
                                    <span class="text-[9px] px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 capitalize">
                                        {{ $initiator->role }}
                                    </span>
                                @endif
                            </div>

                            <!-- Last Message Preview -->
                            <div class="flex items-center justify-between gap-1">
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate flex-1">
                                    @if ($latest)
                                        @if ($latest->sender_type === 'super_admin')
                                            <span class="text-indigo-600 dark:text-indigo-400 font-semibold">{{ __('You:') }}</span>
                                        @endif
                                        {{ $latest->message ?: ($latest->attachment_name ? '📎 ' . $latest->attachment_name : __('Attachment sent')) }}
                                    @else
                                        <span class="italic text-slate-400">{{ __('No messages yet') }}</span>
                                    @endif
                                </p>
                                @if ($unreadCount > 0)
                                    <span class="px-1.5 py-0.2 rounded-full text-[9px] font-extrabold bg-emerald-500 text-white shrink-0">
                                        {{ $unreadCount }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="p-8 text-center">
                        <div class="w-12 h-12 mx-auto mb-2 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xl">
                            💬
                        </div>
                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ __('No support chats found') }}</p>
                        <p class="text-[11px] text-slate-400 mt-1">
                            {{ __('Support conversations will appear here when tenants or staff initiate a session through Help & Support → Live Desk.') }}
                        </p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 2. Middle Panel: Active Conversation Thread                               -->
        <!-- ========================================================================= -->
        <div class="flex-1 bg-slate-100/70 dark:bg-slate-950/60 flex flex-col overflow-hidden relative {{ $activeConversationId ? 'flex' : 'hidden md:flex' }}">
            @if ($activeConversation)
                <!-- Chat Header -->
                <div class="px-4 py-3 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between shrink-0 shadow-2xs">
                    <div class="flex items-center gap-3 min-w-0">
                        <button type="button" 
                                wire:click="$set('activeConversationId', null)" 
                                class="p-1 rounded-lg text-slate-500 hover:text-slate-900 dark:hover:text-white md:hidden">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white font-extrabold flex items-center justify-center shrink-0 shadow-xs text-xs uppercase">
                            {{ substr($tenant?->name ?? 'TN', 0, 2) }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate">
                                    {{ $tenant?->name ?? $activeConversation->title ?? __('Live Support Desk') }}
                                </h3>
                                @if ($activePlan)
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                        {{ $activePlan->name ?? $tenant->plan_name }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate flex items-center gap-1.5">
                                <span>{{ $initiatorUser?->name ?? __('Staff Member') }}</span>
                                @if ($initiatorUser?->email)
                                    <span>•</span>
                                    <span>{{ $initiatorUser->email }}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <!-- Quick View Tenant Details & Fullscreen Button -->
                    <div class="flex items-center gap-2">
                        <button type="button"
                                x-on:click="$store.fullscreen.toggle()"
                                class="hidden sm:inline-flex px-2.5 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition items-center gap-1 cursor-pointer shadow-2xs"
                                title="{{ __('Toggle Fullscreen') }}">
                            <template x-if="!$store.fullscreen.active">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                                    <span>{{ __('Fullscreen') }}</span>
                                </span>
                            </template>
                            <template x-if="$store.fullscreen.active">
                                <span class="flex items-center gap-1 text-amber-500">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    <span>{{ __('Exit') }}</span>
                                </span>
                            </template>
                        </button>
                        <button type="button" 
                                wire:click="$toggle('showTenantDetailsMobile')" 
                                class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition flex items-center gap-1.5 xl:hidden cursor-pointer">
                            <span>🏢</span>
                            <span>{{ __('Tenant Details') }}</span>
                        </button>
                        @if ($tenant)
                            <a href="{{ route('superadmin.tenants.show', $tenant->id) }}" 
                               target="_blank" 
                               class="hidden sm:flex px-3 py-1.5 rounded-xl text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/50 hover:bg-indigo-100 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 transition items-center gap-1">
                                <span>{{ __('Open Tenant Record') }}</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Messages Thread Area (WhatsApp Style Chat Inbox) -->
                <div class="flex-1 overflow-y-auto p-4 space-y-3 flex flex-col-reverse bg-slate-100/70 dark:bg-slate-950/80" id="chat-messages-scroll-area">
                    @forelse ($activeConversation->messages->reverse() as $msg)
                        @php
                            $isSuperAdmin = $msg->sender_type === 'super_admin';
                            $reactions = $msg->reactions ?? [];
                            $currentAdminKey = 'super_admin:' . (auth()->guard('platform_web')->id() ?? 'padm_admin');
                        @endphp
                        <div class="flex flex-col {{ $isSuperAdmin ? 'items-end' : 'items-start' }} group/msg relative my-1">
                            
                            <!-- WhatsApp Style Bubble Surface -->
                            <div class="max-w-[85%] sm:max-w-[70%] p-3 rounded-2xl text-xs shadow-xs relative {{ $isSuperAdmin ? 'bg-[#D9FDD3] dark:bg-[#005C4B] text-[#111B21] dark:text-[#E9EDEF] border border-[#C1E6BB] dark:border-transparent rounded-tr-xs' : 'bg-white dark:bg-[#202C33] text-[#111B21] dark:text-[#E9EDEF] border border-slate-200/90 dark:border-slate-800 rounded-tl-xs' }}">
                                
                                <!-- Floating WhatsApp Reaction Trigger Bar (Visible on Hover / Focus) -->
                                <div class="absolute -top-3.5 {{ $isSuperAdmin ? 'left-2' : 'right-2' }} z-20 opacity-0 group-hover/msg:opacity-100 transition-opacity duration-150 flex items-center gap-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-full px-1.5 py-0.5 shadow-md">
                                    @foreach (['👍', '❤️', '😂', '😮', '😢', '🙏'] as $quickEmoji)
                                        <button type="button" 
                                                wire:click="toggleReaction({{ $msg->id }}, '{{ $quickEmoji }}')" 
                                                title="{{ $quickEmoji }}"
                                                class="hover:scale-125 transition-transform text-xs p-0.5 cursor-pointer leading-none">
                                            {{ $quickEmoji }}
                                        </button>
                                    @endforeach
                                </div>

                                <!-- Sender Name Header for incoming messages -->
                                @if (!$isSuperAdmin)
                                    <div class="flex items-center justify-between gap-2 mb-1.5 font-bold text-[11px] text-emerald-700 dark:text-emerald-400">
                                        <span>{{ $msg->sender?->name ?? $initiatorUser?->name ?? __('Tenant Staff') }}</span>
                                        @if ($msg->sender?->role)
                                            <span class="px-1.5 py-0.2 rounded text-[9px] bg-slate-200/80 dark:bg-slate-700 capitalize font-medium text-slate-700 dark:text-slate-300">
                                                {{ $msg->sender->role }}
                                            </span>
                                        @endif
                                    </div>
                                @endif

                                <!-- File Attachment Preview (if any) -->
                                @if ($msg->attachment_url)
                                    <div class="mb-2">
                                        @if ($msg->attachment_type === 'image')
                                            <!-- Image with Click to Zoom Modal -->
                                            <div class="relative group/img cursor-pointer overflow-hidden rounded-xl border border-black/10 dark:border-white/10"
                                                 @click="openZoom('{{ $msg->attachment_url }}', '{{ addslashes($msg->attachment_name ?: 'Photo') }}')">
                                                <img src="{{ $msg->attachment_url }}" class="max-h-64 w-full object-cover group-hover/img:scale-102 transition duration-200" alt="Attachment">
                                                <div class="absolute inset-0 bg-black/35 opacity-0 group-hover/img:opacity-100 transition flex items-center justify-center gap-2 text-white">
                                                    <span class="px-2.5 py-1 rounded-lg bg-black/60 text-[11px] font-bold flex items-center gap-1.5 backdrop-blur-xs">
                                                        <span>🔍</span>
                                                        <span>{{ __('Click to Zoom') }}</span>
                                                    </span>
                                                </div>
                                            </div>
                                        @elseif ($msg->attachment_type === 'pdf' || str_ends_with(strtolower($msg->attachment_url), '.pdf'))
                                            <!-- PDF with In-Chat Viewer Modal -->
                                            <div class="flex items-center gap-2.5 p-2.5 rounded-xl cursor-pointer transition border {{ $isSuperAdmin ? 'bg-emerald-950/20 dark:bg-black/20 hover:bg-emerald-950/30 border-emerald-600/30 text-[#111B21] dark:text-[#E9EDEF]' : 'bg-slate-50 dark:bg-slate-800/80 hover:bg-slate-100 dark:hover:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white' }}"
                                                 @click="openPdf('{{ $msg->attachment_url }}', '{{ addslashes($msg->attachment_name ?: 'Document.pdf') }}')">
                                                <div class="w-9 h-9 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold text-base shrink-0">
                                                    📄
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="font-bold truncate text-[11px]">{{ $msg->attachment_name ?: __('PDF Document') }}</p>
                                                    <p class="text-[9px] opacity-75 uppercase flex items-center gap-1">
                                                        <span>{{ __('PDF') }}</span>
                                                        <span>•</span>
                                                        <span class="text-emerald-700 dark:text-emerald-300 font-semibold">{{ __('View in Chat') }} &rarr;</span>
                                                    </p>
                                                </div>
                                                <span class="p-1.5 rounded-lg hover:bg-black/10 text-xs shrink-0" title="{{ __('View PDF') }}">
                                                    👁️
                                                </span>
                                            </div>
                                        @else
                                            <!-- Other Documents (downloadable) -->
                                            <a href="{{ $msg->attachment_url }}" target="_blank" download class="flex items-center gap-2.5 p-2.5 rounded-xl transition border {{ $isSuperAdmin ? 'bg-emerald-950/20 dark:bg-black/20 hover:bg-emerald-950/30 border-emerald-600/30 text-[#111B21] dark:text-[#E9EDEF]' : 'bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-900 dark:text-white border border-slate-200 dark:border-slate-700' }}">
                                                <span class="text-lg">📎</span>
                                                <div class="min-w-0 flex-1">
                                                    <p class="font-bold truncate text-[11px]">{{ $msg->attachment_name ?: __('Download Document') }}</p>
                                                    <p class="text-[9px] opacity-75 uppercase">{{ $msg->attachment_type }}</p>
                                                </div>
                                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                            </a>
                                        @endif
                                    </div>
                                @endif

                                <!-- Message Text -->
                                @if (!empty($msg->message))
                                    <div class="whitespace-pre-wrap leading-relaxed {{ $isSuperAdmin ? 'text-[#111B21] dark:text-[#E9EDEF]' : 'text-[#111B21] dark:text-[#E9EDEF]' }}">
                                        {{ $msg->message }}
                                    </div>
                                @endif

                                <!-- WhatsApp Message Meta: Time & Delivery Status -->
                                <div class="flex items-center justify-end gap-1 mt-1 text-[10px] {{ $isSuperAdmin ? 'text-slate-500 dark:text-emerald-200/70' : 'text-slate-400 dark:text-slate-400' }} select-none">
                                    <span>{{ $msg->created_at->format('H:i') }}</span>
                                    @if ($isSuperAdmin)
                                        <span class="text-[#53BDEB] font-bold text-[11px] leading-none" title="{{ __('Read by tenant') }}">✓✓</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Attached WhatsApp Reactions Badges (Floating on bottom edge of bubble) -->
                            @if (!empty($reactions))
                                <div class="flex flex-wrap items-center gap-1 mt-1 {{ $isSuperAdmin ? 'justify-end pr-1' : 'justify-start pl-1' }}">
                                    @foreach ($reactions as $reaction)
                                        @php
                                            $hasReacted = in_array($currentAdminKey, $reaction['users'] ?? [], true);
                                        @endphp
                                        <button type="button" 
                                                wire:click="toggleReaction({{ $msg->id }}, '{{ $reaction['emoji'] }}')" 
                                                title="{{ __('Click to toggle reaction') }}"
                                                class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border transition flex items-center gap-1 cursor-pointer active:scale-95 shadow-2xs {{ $hasReacted ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300' : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300' }}">
                                            <span>{{ $reaction['emoji'] }}</span>
                                            <span class="text-[10px] font-bold">{{ $reaction['count'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-400">
                            {{ __('No messages yet in this support channel.') }}
                        </div>
                    @endforelse
                </div>

                <!-- Attachment Upload Preview Chip (Before Sending) -->
                @if ($replyAttachment)
                    <div class="px-4 py-2 bg-indigo-50 dark:bg-indigo-950/60 border-t border-indigo-200 dark:border-indigo-800 flex items-center justify-between text-xs text-indigo-700 dark:text-indigo-300 shrink-0">
                        <div class="flex items-center gap-2 truncate">
                            <span class="text-base">📎</span>
                            <span class="font-semibold truncate">{{ $replyAttachment->getClientOriginalName() }}</span>
                            <span class="text-[10px] text-slate-500">({{ number_format($replyAttachment->getSize() / 1024, 1) }} KB)</span>
                        </div>
                        <button type="button" wire:click="clearAttachment" class="p-1 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900 text-indigo-600 dark:text-indigo-300">
                            ✕
                        </button>
                    </div>
                @endif

                <!-- Docked Emoji Drawer -->
                @if ($showEmojiPicker)
                    <div class="border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shrink-0 shadow-inner flex flex-col">
                        <div class="px-3 py-1.5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50 dark:bg-slate-950/50">
                            <div class="flex items-center gap-1 overflow-x-auto no-scrollbar">
                                @foreach ($emojiCategories as $catKey => $catData)
                                    <button type="button" 
                                            wire:click="$set('activeEmojiTab', '{{ $catKey }}')" 
                                            class="px-2 py-0.5 rounded-lg text-xs font-semibold flex items-center gap-1 transition cursor-pointer {{ $activeEmojiTab === $catKey ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-2xs border border-slate-200 dark:border-slate-700' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white' }}">
                                        <span>{{ $catData['icon'] }}</span>
                                        <span>{{ $catData['name'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <button type="button" wire:click="toggleEmojiPicker" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-xs px-1">
                                ✕
                            </button>
                        </div>
                        <div class="p-3 max-h-36 overflow-y-auto grid grid-cols-8 sm:grid-cols-12 gap-1 text-center">
                            @foreach ($emojiCategories[$activeEmojiTab]['emojis'] ?? [] as $emo)
                                <button type="button" 
                                        wire:click="insertEmoji('{{ $emo }}')" 
                                        class="p-1.5 text-xl rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 hover:scale-125 transition cursor-pointer">
                                    {{ $emo }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- AI Smart Reply Suggestions Quick Chips -->
                @if (!empty($aiSuggestions))
                    <div class="px-3.5 py-2 bg-indigo-50/70 dark:bg-slate-900/95 border-t border-slate-200/80 dark:border-slate-800 flex items-center gap-2 overflow-x-auto no-scrollbar shrink-0">
                        <div class="flex items-center gap-1.5 text-[11px] font-extrabold text-indigo-600 dark:text-indigo-400 shrink-0 select-none">
                            <span>✨</span>
                            <span class="hidden sm:inline">{{ __('AI Suggestions') }}:</span>
                        </div>
                        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                            @foreach ($aiSuggestions as $suggestion)
                                <button type="button" 
                                        wire:click="useAiSuggestion(@js($suggestion))"
                                        title="{{ __('Click to insert reply') }}"
                                        class="px-3 py-1 rounded-full text-xs font-semibold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400 hover:border-indigo-300 dark:hover:border-indigo-700 border border-slate-200/90 dark:border-slate-700 shadow-2xs transition whitespace-nowrap cursor-pointer active:scale-95">
                                    {{ $suggestion }}
                                </button>
                            @endforeach
                        </div>
                        <button type="button" 
                                wire:click="loadAiSuggestions" 
                                wire:loading.attr="disabled"
                                title="{{ __('Regenerate suggestions') }}"
                                class="p-1.5 rounded-lg hover:bg-indigo-100 dark:hover:bg-slate-800 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition cursor-pointer shrink-0 ml-auto">
                            <svg wire:loading.remove wire:target="loadAiSuggestions" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <span wire:loading wire:target="loadAiSuggestions" class="text-[10px] animate-spin">⏳</span>
                        </button>
                    </div>
                @endif

                <!-- Floating AI Toast Notification -->
                @if (!empty($aiToast))
                    <div x-data="{ show: true }" 
                         x-init="setTimeout(() => show = false, 3200)" 
                         x-show="show" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 translate-y-2"
                         class="absolute bottom-20 right-6 px-3.5 py-1.5 rounded-full bg-slate-900/95 dark:bg-slate-850 text-white text-xs font-bold shadow-2xl border border-slate-700/80 flex items-center gap-1.5 z-50 backdrop-blur-sm pointer-events-none">
                        <span class="text-amber-400">✨</span>
                        <span>{{ $aiToast }}</span>
                    </div>
                @endif

                <!-- Super Admin Response Input Bar -->
                <div class="p-3 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 shrink-0">
                    <form wire:submit.prevent="sendReply" class="flex items-end gap-2">
                        
                        <!-- File Upload Button -->
                        <div class="relative">
                            <input type="file" 
                                   wire:model="replyAttachment" 
                                   id="sa-reply-file" 
                                   accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" 
                                   class="hidden">
                            <label for="sa-reply-file" 
                                   title="{{ __('Attach photo or PDF') }}"
                                   class="p-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 transition cursor-pointer flex items-center justify-center border border-slate-200 dark:border-slate-700 shadow-2xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                            </label>
                        </div>

                        <!-- Emoji Toggle Button -->
                        <button type="button" 
                                wire:click="toggleEmojiPicker" 
                                title="{{ __('Insert Emoji') }}"
                                class="p-2.5 rounded-2xl transition border cursor-pointer shadow-2xs {{ $showEmojiPicker ? 'bg-amber-100 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border-amber-300 dark:border-amber-800' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700' }}">
                            <span class="text-sm">😊</span>
                        </button>

                        <!-- Message Text Field -->
                        <div class="flex-1 relative">
                            <textarea wire:model="replyMessage" 
                                      wire:keydown.enter.prevent="sendReply"
                                      rows="1" 
                                      placeholder="{{ __('Type a reply to the tenant... (Press Enter to send)') }}" 
                                      class="w-full px-4 py-2.5 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs sm:text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 resize-none shadow-inner"></textarea>
                        </div>

                        <!-- AI Polish & Auto-Correct Button -->
                        <button type="button" 
                                wire:click="autoCorrectMessage" 
                                wire:loading.attr="disabled"
                                wire:target="autoCorrectMessage"
                                title="{{ __('Polish & Auto-Correct with AI') }}"
                                class="p-2.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/60 text-amber-600 dark:text-amber-400 border border-amber-200/80 dark:border-amber-800/60 transition cursor-pointer flex items-center justify-center shadow-2xs group shrink-0">
                            <span wire:loading.remove wire:target="autoCorrectMessage" class="text-sm group-hover:scale-125 transition-transform">✨</span>
                            <span wire:loading wire:target="autoCorrectMessage" class="text-xs animate-spin">⏳</span>
                        </button>

                        <!-- Send Button (Flicker-Free with explicit wire:target="sendReply") -->
                        <button type="submit" 
                                wire:loading.attr="disabled"
                                wire:target="sendReply"
                                class="px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 transition shadow-sm cursor-pointer disabled:opacity-50">
                            <span wire:loading.remove wire:target="sendReply">{{ __('Send') }}</span>
                            <span wire:loading wire:target="sendReply" class="flex items-center gap-1">
                                <span class="animate-spin text-xs">⏳</span>
                                <span>{{ __('Sending...') }}</span>
                            </span>
                            <svg wire:loading.remove wire:target="sendReply" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        </button>
                    </form>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-400">
                    <span class="text-5xl mb-3">🎧</span>
                    <h3 class="text-base font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('No Conversation Selected') }}</h3>
                    <p class="text-xs max-w-sm">
                        {{ __('Select a Live Desk conversation from the left to view messages and respond to the tenant.') }}
                    </p>
                </div>
            @endif
        </div>

        <!-- ========================================================================= -->
        <!-- 3. Right Panel: Tenant & Subscription Information (Requirement 4)         -->
        <!-- ========================================================================= -->
        @if ($activeConversation && $tenant)
            <div class="w-80 md:w-96 bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 flex-col overflow-y-auto p-4 shrink-0 {{ $showTenantDetailsMobile ? 'flex fixed inset-y-0 right-0 z-50 shadow-2xl xl:static xl:shadow-none' : 'hidden xl:flex' }}">
                
                <!-- Close Button on Mobile Drawer -->
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 mb-3 xl:hidden">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Tenant Information') }}</h4>
                    <button type="button" wire:click="$set('showTenantDetailsMobile', false)" class="text-slate-400 hover:text-slate-700 text-sm p-1">
                        ✕
                    </button>
                </div>

                <!-- Tenant Overview Card -->
                <div class="p-3.5 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/60 mb-4">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-11 h-11 rounded-xl bg-indigo-600 text-white font-extrabold flex items-center justify-center text-sm shadow-xs uppercase shrink-0">
                            {{ substr($tenant->name, 0, 2) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $tenant->name }}</h4>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ $tenant->slug }}.{{ parse_url(config('app.url'), PHP_URL_HOST) ?? 'saas' }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-xs pt-2 border-t border-indigo-100 dark:border-indigo-900/60">
                        <span class="text-[11px] text-slate-500">{{ __('Account Status') }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $tenant->status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' }}">
                            {{ $tenant->status }}
                        </span>
                    </div>
                </div>

                <!-- 1. Tenant Details -->
                <div class="space-y-3 mb-5">
                    <h5 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1.5">
                        <span>🏢</span>
                        <span>{{ __('Account & Contact Details') }}</span>
                    </h5>

                    <div class="space-y-2 text-xs">
                        <!-- Tenant Name -->
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] text-slate-400 block">{{ __('Tenant / Company Name') }}</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $tenant->name }}</span>
                            @if ($tenant->trade_name && $tenant->trade_name !== $tenant->name)
                                <span class="text-[10px] text-slate-400 block mt-0.5">({{ $tenant->trade_name }})</span>
                            @endif
                        </div>

                        <!-- Registered Email -->
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] text-slate-400 block">{{ __('Registered Email Address') }}</span>
                            <a href="mailto:{{ $tenant->email }}" class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $tenant->email ?: __('Not specified') }}
                            </a>
                        </div>

                        <!-- Contact Number -->
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] text-slate-400 block">{{ __('Contact Number') }}</span>
                            <a href="tel:{{ $tenant->phone }}" class="font-semibold text-slate-800 dark:text-slate-200 hover:underline">
                                {{ $tenant->phone ?: __('Not specified') }}
                            </a>
                        </div>

                        <!-- Store / Branch -->
                        @if ($activeConversation->store)
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                                <span class="text-[10px] text-slate-400 block">{{ __('Origin Branch / Store') }}</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $activeConversation->store->name }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 2. Subscription Details -->
                <div class="space-y-3 mb-5">
                    <h5 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1.5">
                        <span>💳</span>
                        <span>{{ __('Subscription & Plan') }}</span>
                    </h5>

                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-400">{{ __('Current Plan') }}</span>
                            <span class="font-extrabold text-indigo-600 dark:text-indigo-400">
                                {{ $activePlan?->name ?? $tenant->plan_name ?? __('Standard Plan') }}
                            </span>
                        </div>

                        <!-- Subscription Start Date -->
                        <div class="flex items-center justify-between pt-1 border-t border-slate-200/50 dark:border-slate-700/50">
                            <span class="text-[11px] text-slate-400">{{ __('Subscription Start Date') }}</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200">
                                @if ($currentSubscription?->started_at)
                                    {{ $currentSubscription->started_at->format('d M Y') }}
                                @elseif ($tenant->registered_at)
                                    {{ $tenant->registered_at->format('d M Y') }}
                                @else
                                    {{ __('N/A') }}
                                @endif
                            </span>
                        </div>

                        <!-- Subscription Expiration Date -->
                        <div class="flex items-center justify-between pt-1 border-t border-slate-200/50 dark:border-slate-700/50">
                            <span class="text-[11px] text-slate-400">{{ __('Subscription Expiration') }}</span>
                            <span class="font-semibold {{ $tenant->expires_at && $tenant->expires_at->isPast() ? 'text-rose-600 font-bold' : 'text-slate-800 dark:text-slate-200' }}">
                                @if ($currentSubscription?->expires_at)
                                    {{ $currentSubscription->expires_at->format('d M Y') }}
                                @elseif ($tenant->expires_at)
                                    {{ $tenant->expires_at->format('d M Y') }}
                                @else
                                    {{ __('Lifetime / Active') }}
                                @endif
                            </span>
                        </div>

                        <!-- Auto Renew -->
                        @if ($currentSubscription)
                            <div class="flex items-center justify-between pt-1 border-t border-slate-200/50 dark:border-slate-700/50">
                                <span class="text-[11px] text-slate-400">{{ __('Auto Renew') }}</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $currentSubscription->auto_renew ? __('Enabled') : __('Disabled') }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 3. Initiator User / Contact Person -->
                @if ($initiatorUser)
                    <div class="space-y-3 mb-5">
                        <h5 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-1.5">
                            <span>👤</span>
                            <span>{{ __('Initiating Staff Member') }}</span>
                        </h5>

                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $initiatorUser->name }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] bg-slate-200 dark:bg-slate-700 capitalize font-semibold">
                                    {{ $initiatorUser->role }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500">{{ $initiatorUser->email }}</p>
                            @if ($initiatorUser->phone)
                                <p class="text-[11px] text-slate-500">{{ $initiatorUser->phone }}</p>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Manage Tenant Button -->
                <div class="mt-auto pt-3 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('superadmin.tenants.show', $tenant->id) }}" 
                       target="_blank"
                       class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition shadow-sm">
                        <span>{{ __('View Tenant in Super Admin') }}</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    </a>
                </div>

            </div>
        @endif

    </div>

    <!-- ========================================================================= -->
    <!-- 4. Interactive Image Zoom Lightbox Modal                                   -->
    <!-- ========================================================================= -->
    <div x-show="zoomImage" 
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-black/90 backdrop-blur-md flex flex-col justify-between p-3 sm:p-6"
         @click.self="closeZoom()">
        
        <!-- Top Action Bar -->
        <div class="flex items-center justify-between text-white z-10 bg-slate-900/90 px-4 py-2.5 rounded-2xl border border-white/10 backdrop-blur-sm max-w-3xl mx-auto w-full shadow-2xl">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-base">🖼️</span>
                <span class="text-xs font-semibold truncate max-w-xs" x-text="zoomImage?.name || '{{ __('Image') }}'"></span>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-slate-300 font-mono" x-text="Math.round(zoomScale * 100) + '%'"></span>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <!-- Zoom In -->
                <button type="button" 
                        @click="zoomIn()" 
                        title="{{ __('Zoom In (+)') }}" 
                        class="p-2 rounded-xl bg-white/10 hover:bg-white/20 transition cursor-pointer text-xs font-bold flex items-center gap-1">
                    <span>➕</span>
                </button>
                <!-- Zoom Out -->
                <button type="button" 
                        @click="zoomOut()" 
                        title="{{ __('Zoom Out (-)') }}" 
                        class="p-2 rounded-xl bg-white/10 hover:bg-white/20 transition cursor-pointer text-xs font-bold flex items-center gap-1">
                    <span>➖</span>
                </button>
                <!-- Reset Zoom -->
                <button type="button" 
                        @click="resetZoom()" 
                        title="{{ __('Reset Zoom (1:1)') }}" 
                        class="px-2.5 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 transition cursor-pointer text-xs font-semibold">
                    1:1
                </button>
                <!-- Download -->
                <a :href="zoomImage?.url" 
                   download 
                   target="_blank" 
                   title="{{ __('Download Original') }}" 
                   class="p-2 rounded-xl bg-white/10 hover:bg-white/20 transition cursor-pointer text-xs">
                    ⬇️
                </a>
                <!-- Close Button -->
                <button type="button" 
                        @click="closeZoom()" 
                        title="{{ __('Close (Esc)') }}" 
                        class="p-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/40 text-rose-300 transition cursor-pointer text-xs font-bold ml-1.5">
                    ✕
                </button>
            </div>
        </div>

        <!-- Scalable Image Canvas -->
        <div class="flex-1 flex items-center justify-center overflow-auto p-4 select-none" @click.self="closeZoom()">
            <img :src="zoomImage?.url" 
                 :style="'transform: scale(' + zoomScale + '); transform-origin: center center; transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);'" 
                 class="max-w-[90vw] max-h-[80vh] object-contain rounded-xl shadow-2xl cursor-zoom-in"
                 @click="zoomIn()"
                 alt="Image Preview">
        </div>

        <!-- Footer Caption / Controls Hint -->
        <div class="text-center text-[11px] text-slate-400 select-none pb-1">
            {{ __('Click image to zoom in • Press ESC to close') }}
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 5. In-Chat PDF Viewer Modal Interface                                      -->
    <!-- ========================================================================= -->
    <div x-show="activePdfUrl" 
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-2 sm:p-6"
         @click.self="closePdf()">
        
        <div class="bg-white dark:bg-slate-900 rounded-3xl w-full max-w-5xl h-[92vh] flex flex-col shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden"
             @click.stop>
            
            <!-- PDF Header Bar -->
            <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center text-sm font-bold shrink-0">
                        📄
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white truncate" x-text="activePdfName"></h3>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400">{{ __('In-Chat Document Preview') }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <a :href="activePdfUrl" 
                       target="_blank" 
                       title="{{ __('Open in New Tab') }}" 
                       class="px-3 py-1.5 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold flex items-center gap-1 transition">
                        <span>↗</span>
                        <span class="hidden sm:inline">{{ __('New Tab') }}</span>
                    </a>
                    <a :href="activePdfUrl" 
                       download 
                       title="{{ __('Download Document') }}" 
                       class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold flex items-center gap-1 transition">
                        <span>⬇</span>
                        <span class="hidden sm:inline">{{ __('Download') }}</span>
                    </a>
                    <button type="button" 
                            @click="closePdf()" 
                            title="{{ __('Close (Esc)') }}" 
                            class="p-2 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-rose-500 hover:text-white text-slate-500 dark:text-slate-400 transition cursor-pointer ml-1">
                        ✕
                    </button>
                </div>
            </div>

            <!-- Embedded PDF Iframe -->
            <div class="flex-1 w-full h-full bg-slate-100 dark:bg-slate-950 relative">
                <iframe :src="activePdfUrl ? activePdfUrl + '#toolbar=1' : ''" 
                        class="w-full h-full border-0 rounded-b-3xl" 
                        title="PDF Viewer"></iframe>
            </div>
        </div>
    </div>
</div>
