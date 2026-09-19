<div class="space-y-6">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
        <div>
            <div class="flex items-center gap-3">
                <span class="text-2xl">📬</span>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">
                    {{ __('Web Inquiries & Contact Form') }}
                </h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Manage visitor leads, inquiries from the website, and configure the custom contact form fields.') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ url('/contact') }}" target="_blank"
               class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition flex items-center gap-1.5 shadow-sm">
                <span>🌐</span>
                <span>{{ __('Open Public Page') }}</span>
                <span class="text-[10px] text-slate-400">↗</span>
            </a>

            @if ($tab === 'builder')
                <button type="button" wire:click="openAddFieldModal"
                        class="px-4 py-2.5 rounded-xl text-xs font-black bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1.5 shadow-md shadow-indigo-600/20 cursor-pointer">
                    <span>➕</span>
                    <span>{{ __('Add Custom Field') }}</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Metric Stats Strip -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl shrink-0">
                📬
            </div>
            <div>
                <span class="text-[10px] uppercase font-black tracking-wider text-slate-400 block">{{ __('Total Inquiries') }}</span>
                <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">{{ number_format($stats['total']) }}</span>
            </div>
        </div>

        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shrink-0">
                ✨
            </div>
            <div>
                <span class="text-[10px] uppercase font-black tracking-wider text-slate-400 block">{{ __('New / Unread') }}</span>
                <span class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ number_format($stats['new']) }}</span>
            </div>
        </div>

        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 flex items-center justify-center text-xl shrink-0">
                💬
            </div>
            <div>
                <span class="text-[10px] uppercase font-black tracking-wider text-slate-400 block">{{ __('Replied') }}</span>
                <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">{{ number_format($stats['replied']) }}</span>
            </div>
        </div>

        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl shrink-0">
                ⚙️
            </div>
            <div>
                <span class="text-[10px] uppercase font-black tracking-wider text-slate-400 block">{{ __('Active Fields') }}</span>
                <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">{{ $stats['custom_fields_count'] }}</span>
            </div>
        </div>
    </div>

    <!-- Navigation Tab Buttons -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-1.5 shadow-sm border border-slate-200/80 dark:border-slate-800 flex items-center gap-1.5">
        <button type="button" wire:click="setTab('inquiries')"
                class="flex-1 py-2 sm:py-2.5 px-4 rounded-xl text-xs sm:text-sm transition flex items-center justify-center gap-2 cursor-pointer {{ $tab === 'inquiries' ? 'bg-indigo-600 text-white font-black shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold' }}">
            <span>📥</span>
            <span>{{ __('Inquiries Received from Web') }}</span>
            @if ($stats['new'] > 0)
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-400 text-slate-950">
                    {{ $stats['new'] }}
                </span>
            @endif
        </button>

        <button type="button" wire:click="setTab('builder')"
                class="flex-1 py-2 sm:py-2.5 px-4 rounded-xl text-xs sm:text-sm transition flex items-center justify-center gap-2 cursor-pointer {{ $tab === 'builder' ? 'bg-indigo-600 text-white font-black shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold' }}">
            <span>🛠️</span>
            <span>{{ __('Contact Page & Form Builder') }}</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                {{ count($fields) }} fields
            </span>
        </button>

        <button type="button" wire:click="setTab('preview')"
                class="flex-1 py-2 sm:py-2.5 px-4 rounded-xl text-xs sm:text-sm transition flex items-center justify-center gap-2 cursor-pointer {{ $tab === 'preview' ? 'bg-indigo-600 text-white font-black shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold' }}">
            <span>👁️</span>
            <span>{{ __('Live Form Preview') }}</span>
        </button>
    </div>

    <!-- ============================================================= -->
    <!-- TAB 1: INQUIRIES RECEIVED FROM WEB                            -->
    <!-- ============================================================= -->
    @if ($tab === 'inquiries')
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xl overflow-hidden space-y-4">
            
            <!-- Filters & Bulk Actions -->
            <div class="p-4 sm:p-6 border-b border-slate-100 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
                
                <!-- Search & Status -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 flex-1">
                    <div class="relative flex-1 max-w-md">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">🔍</span>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by sender, email, subject, message…') }}"
                               class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm text-slate-900 dark:text-white focus:ring-indigo-500">
                    </div>

                    <select wire:model.live="statusFilter" class="px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs sm:text-sm font-bold text-slate-700 dark:text-slate-300">
                        <option value="all">{{ __('All Statuses') }}</option>
                        <option value="new">{{ __('New / Unread') }} ({{ $stats['new'] }})</option>
                        <option value="read">{{ __('Read') }} ({{ $stats['read'] }})</option>
                        <option value="replied">{{ __('Replied') }} ({{ $stats['replied'] }})</option>
                    </select>
                </div>

                <!-- Bulk Selection Actions -->
                @if (count($selectedInquiries) > 0)
                    <div class="flex items-center gap-2 animate-in fade-in">
                        <span class="text-xs font-bold text-slate-500">
                            {{ count($selectedInquiries) }} {{ __('selected') }}:
                        </span>
                        <button type="button" wire:click="bulkMarkAsRead"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                            {{ __('Mark Read') }}
                        </button>
                        <button type="button" wire:click="bulkMarkAsReplied"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold bg-sky-50 dark:bg-sky-950 text-sky-700 dark:text-sky-300 hover:bg-sky-100 transition cursor-pointer">
                            {{ __('Mark Replied') }}
                        </button>
                        <button type="button" wire:confirm="{{ __('Are you sure you want to delete selected inquiries?') }}" wire:click="bulkDelete"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold bg-rose-50 dark:bg-rose-950 text-rose-600 dark:text-rose-400 hover:bg-rose-100 transition cursor-pointer">
                            {{ __('Delete') }}
                        </button>
                    </div>
                @endif
            </div>

            <!-- Inquiries Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 uppercase font-black text-[10px] tracking-wider border-b border-slate-100 dark:border-slate-800">
                        <tr>
                            <th class="py-3 px-4 w-10">
                                <input type="checkbox" wire:model.live="selectAll" class="rounded text-indigo-600 focus:ring-indigo-500">
                            </th>
                            <th class="py-3 px-4">{{ __('Status') }}</th>
                            <th class="py-3 px-4">{{ __('Sender') }}</th>
                            <th class="py-3 px-4">{{ __('Subject & Details') }}</th>
                            <th class="py-3 px-4">{{ __('Custom Fields') }}</th>
                            <th class="py-3 px-4">{{ __('Received') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-700 dark:text-slate-300">
                        @forelse ($inquiries as $inq)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition {{ $inq->isNew() ? 'bg-emerald-50/20 dark:bg-emerald-950/10 font-semibold' : '' }}">
                                <td class="py-3.5 px-4">
                                    <input type="checkbox" wire:model.live="selectedInquiries" value="{{ $inq->id }}" class="rounded text-indigo-600 focus:ring-indigo-500">
                                </td>
                                <td class="py-3.5 px-4">
                                    @if ($inq->isNew())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            <span>{{ __('New') }}</span>
                                        </span>
                                    @elseif ($inq->isReplied())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300">
                                            <span>✓ {{ __('Replied') }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                            <span>{{ __('Read') }}</span>
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $inq->name }}</div>
                                    <a href="mailto:{{ $inq->email }}" class="text-indigo-600 dark:text-indigo-400 text-[11px] hover:underline block truncate max-w-[200px]">
                                        {{ $inq->email }}
                                    </a>
                                    @if ($inq->phone)
                                        <a href="tel:{{ $inq->phone }}" class="text-slate-500 text-[11px] hover:underline block">
                                            📞 {{ $inq->phone }}
                                        </a>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900 dark:text-white truncate max-w-xs">
                                        {{ $inq->subject ?: ($inq->store_type ? __('Store Type: ') . $inq->store_type : __('General Inquiry')) }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 line-clamp-1 max-w-xs">
                                        {{ $inq->message }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    @if (!empty($inq->custom_fields))
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800">
                                            <span>⚙️</span>
                                            <span>{{ count($inq->custom_fields) }} {{ __('custom fields') }}</span>
                                        </span>
                                    @else
                                        <span class="text-slate-400 text-[11px]">—</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-slate-500 text-[11px] whitespace-nowrap">
                                    <div>{{ $inq->created_at->format('M d, Y') }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $inq->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button" wire:click="viewInquiry({{ $inq->id }})"
                                                class="px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900 transition cursor-pointer">
                                            {{ __('View Details') }}
                                        </button>

                                        <button type="button" wire:confirm="{{ __('Delete inquiry from :name?', ['name' => $inq->name]) }}" wire:click="deleteInquiry({{ $inq->id }})"
                                                class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer" title="{{ __('Delete') }}">
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <div class="text-3xl mb-2">📭</div>
                                    <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ __('No inquiries found') }}</h4>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('When visitors submit your website contact form, inquiries will appear right here.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $inquiries->links() }}
            </div>
        </div>
    @endif

    <!-- ============================================================= -->
    <!-- TAB 2: CONTACT PAGE & FORM BUILDER                            -->
    <!-- ============================================================= -->
    @if ($tab === 'builder')
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Left: Fields List & Management (8 cols) -->
            <div class="lg:col-span-8 space-y-4">
                <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-200/80 dark:border-slate-800 shadow-xl space-y-6">
                    
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                                <span>📋</span> {{ __('Custom Form Fields Builder') }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ __('Add, edit, reorder, or remove inputs. These fields render dynamically on /contact and modular landing page themes.') }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="resetDefaultFields" wire:confirm="{{ __('Reset all fields to standard defaults?') }}"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 transition cursor-pointer">
                                <span>🔄</span> {{ __('Reset Defaults') }}
                            </button>
                            <button type="button" wire:click="openAddFieldModal"
                                    class="px-3.5 py-1.5 rounded-xl text-xs font-black bg-emerald-600 hover:bg-emerald-700 text-white transition flex items-center gap-1 shadow-sm cursor-pointer">
                                <span>➕</span> {{ __('Add New Field') }}
                            </button>
                        </div>
                    </div>

                    <!-- Fields List Cards -->
                    <div class="space-y-3">
                        @foreach ($fields as $idx => $f)
                            <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 hover:border-indigo-300 dark:hover:border-indigo-700 transition flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <!-- Reorder Controls -->
                                    <div class="flex flex-col items-center gap-1 shrink-0">
                                        <button type="button" wire:click="moveUp({{ $idx }})" {{ $idx === 0 ? 'disabled' : '' }}
                                                class="w-6 h-6 rounded flex items-center justify-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-[10px] text-slate-600 dark:text-slate-300 hover:bg-slate-100 disabled:opacity-30 cursor-pointer">
                                            ▲
                                        </button>
                                        <button type="button" wire:click="moveDown({{ $idx }})" {{ $idx === count($fields) - 1 ? 'disabled' : '' }}
                                                class="w-6 h-6 rounded flex items-center justify-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-[10px] text-slate-600 dark:text-slate-300 hover:bg-slate-100 disabled:opacity-30 cursor-pointer">
                                            ▼
                                        </button>
                                    </div>

                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-black text-slate-900 dark:text-white">
                                                {{ $f['label'] }}
                                            </span>
                                            @if (!empty($f['required']))
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                                    {{ __('Required') }}
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                                    {{ __('Optional') }}
                                                </span>
                                            @endif
                                            @if (!empty($f['is_system']))
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                                    {{ __('System Field') }}
                                                </span>
                                            @endif
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 mt-1 text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                                            <span>name: <strong>{{ $f['name'] }}</strong></span>
                                            <span>•</span>
                                            <span class="uppercase font-bold text-indigo-600 dark:text-indigo-400">{{ $f['type'] }}</span>
                                            <span>•</span>
                                            <span>{{ ($f['width'] ?? 'half') === 'half' ? __('50% Width') : __('100% Full Width') }}</span>
                                            @if (!empty($f['options']))
                                                <span>•</span>
                                                <span class="truncate max-w-[200px]" title="{{ $f['options'] }}">opts: {{ $f['options'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 self-end sm:self-center">
                                    <button type="button" wire:click="editField('{{ $f['id'] }}')"
                                            class="px-3 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                                        <span>✏️</span> {{ __('Edit') }}
                                    </button>
                                    @if (empty($f['is_system']))
                                        <button type="button" wire:confirm="{{ __('Delete field :label?', ['label' => $f['label']]) }}" wire:click="deleteField('{{ $f['id'] }}')"
                                                class="p-1.5 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer" title="{{ __('Delete') }}">
                                            🗑️
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                </div>
            </div>

            <!-- Right: General Form & Notification Settings (4 cols) -->
            <div class="lg:col-span-4 space-y-4">
                <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border border-slate-200/80 dark:border-slate-800 shadow-xl space-y-5">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-3.5">
                        <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>⚙️</span> {{ __('Page & Email Settings') }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ __('Configure form titles, notification recipient, and alerts.') }}</p>
                    </div>

                    <form wire:submit.prevent="saveFormSettings" class="space-y-4 text-xs">
                        <!-- Contact Form Enabled -->
                        <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                            <div>
                                <span class="font-bold text-slate-900 dark:text-white block">{{ __('Enable Contact Section') }}</span>
                                <span class="text-[10px] text-slate-500">{{ __('Show contact form across public pages') }}</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model="formEnabled" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:bg-slate-700 peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>

                        <!-- Page Title -->
                        <div>
                            <label class="block font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Form Header Title') }} *</label>
                            <input type="text" wire:model="pageTitle" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-white font-semibold">
                            @error('pageTitle') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                        </div>

                        <!-- Page Subtitle -->
                        <div>
                            <label class="block font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Form Subtitle / Prompt') }}</label>
                            <textarea wire:model="pageSubtitle" rows="2" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-white font-medium"></textarea>
                        </div>

                        <!-- Submit Button Text -->
                        <div>
                            <label class="block font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Submit Button Text') }} *</label>
                            <input type="text" wire:model="submitButtonText" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-white font-semibold">
                        </div>

                        <!-- Success Flash Message -->
                        <div>
                            <label class="block font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Success Confirmation Message') }} *</label>
                            <textarea wire:model="successMessage" rows="2" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-white font-medium"></textarea>
                        </div>

                        <!-- Destination Recipient Email -->
                        <div>
                            <label class="block font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Notification Email Recipient') }}</label>
                            <input type="email" wire:model="recipientEmail" placeholder="support@zoomnearby.com" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-white font-medium">
                            <span class="text-[10px] text-slate-400 block mt-1">{{ __('Leave blank to default to Platform Support Email.') }}</span>
                        </div>

                        <button type="submit"
                                class="w-full py-3 px-4 rounded-xl font-black text-xs text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-md shadow-indigo-600/20 cursor-pointer">
                            {{ __('Save Form Settings') }}
                        </button>
                    </form>
                </div>
            </div>

        </div>
    @endif

    <!-- ============================================================= -->
    <!-- TAB 3: LIVE PREVIEW                                           -->
    <!-- ============================================================= -->
    @if ($tab === 'preview')
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-10 border border-slate-200/80 dark:border-slate-800 shadow-xl max-w-3xl mx-auto space-y-6">
            <div class="text-center space-y-2 border-b border-slate-100 dark:border-slate-800 pb-6">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 text-xs font-black uppercase tracking-wider">
                    💬 {{ __('Interactive Preview') }}
                </span>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                    {{ $pageTitle ?: __('Get in Touch') }}
                </h2>
                <p class="text-xs sm:text-sm text-slate-500 max-w-lg mx-auto">
                    {{ $pageSubtitle ?: __('Questions before you sign up or need a tailored enterprise POS setup?') }}
                </p>
            </div>

            <x-landing.contact-form :fields="$fields" />
        </div>
    @endif

    <!-- ============================================================= -->
    <!-- MODAL: VIEW INQUIRY DETAILS                                   -->
    <!-- ============================================================= -->
    @if ($showDetailModal && $viewingInquiry)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md animate-in fade-in">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 max-w-2xl w-full shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
                
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">📬</span>
                        <div>
                            <h3 class="text-base font-black text-slate-900 dark:text-white">
                                {{ __('Inquiry from') }} {{ $viewingInquiry->name }}
                            </h3>
                            <span class="text-xs text-slate-400">
                                {{ $viewingInquiry->created_at->format('F d, Y \a\t h:i A') }} ({{ $viewingInquiry->created_at->diffForHumans() }})
                            </span>
                        </div>
                    </div>
                    <button type="button" wire:click="closeDetailModal" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-xl font-black cursor-pointer">&times;</button>
                </div>

                <!-- Status Pill & Quick Status Changer -->
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-slate-500">{{ __('Current Status:') }}</span>
                        @if ($viewingInquiry->isNew())
                            <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                {{ __('New / Unread') }}
                            </span>
                        @elseif ($viewingInquiry->isReplied())
                            <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300">
                                {{ __('Replied') }}
                            </span>
                        @else
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                {{ __('Read') }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button type="button" wire:click="updateStatus({{ $viewingInquiry->id }}, 'new')"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $viewingInquiry->isNew() ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 text-slate-700 dark:text-slate-300' }} cursor-pointer">
                            {{ __('Mark New') }}
                        </button>
                        <button type="button" wire:click="updateStatus({{ $viewingInquiry->id }}, 'read')"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $viewingInquiry->isRead() ? 'bg-slate-800 text-white dark:bg-slate-600' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 text-slate-700 dark:text-slate-300' }} cursor-pointer">
                            {{ __('Mark Read') }}
                        </button>
                        <button type="button" wire:click="updateStatus({{ $viewingInquiry->id }}, 'replied')"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $viewingInquiry->isReplied() ? 'bg-sky-600 text-white' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 text-slate-700 dark:text-slate-300' }} cursor-pointer">
                            {{ __('Mark Replied') }}
                        </button>
                    </div>
                </div>

                <!-- Sender Info Card -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">{{ __('Email Address') }}</span>
                        <a href="mailto:{{ $viewingInquiry->email }}" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline block truncate">
                            {{ $viewingInquiry->email }} ↗
                        </a>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">{{ __('Phone') }}</span>
                        @if ($viewingInquiry->phone)
                            <a href="tel:{{ $viewingInquiry->phone }}" class="text-emerald-600 dark:text-emerald-400 font-bold hover:underline block">
                                {{ $viewingInquiry->phone }} ↗
                            </a>
                        @else
                            <span class="text-slate-400 italic">{{ __('Not provided') }}</span>
                        @endif
                    </div>

                    @if ($viewingInquiry->store_type)
                        <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">{{ __('Store / Business Type') }}</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $viewingInquiry->store_type }}</span>
                        </div>
                    @endif

                    @if ($viewingInquiry->ip_address)
                        <div class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">{{ __('Visitor IP Address') }}</span>
                            <span class="font-mono text-slate-600 dark:text-slate-400">{{ $viewingInquiry->ip_address }}</span>
                        </div>
                    @endif
                </div>

                <!-- Subject & Message -->
                <div class="space-y-2">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">{{ __('Subject & Message Body') }}</span>
                    @if ($viewingInquiry->subject)
                        <div class="font-black text-sm text-slate-900 dark:text-white">
                            {{ $viewingInquiry->subject }}
                        </div>
                    @endif
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200 leading-relaxed whitespace-pre-wrap">
                        {{ $viewingInquiry->message }}
                    </div>
                </div>

                <!-- Custom Fields Section -->
                @if (!empty($viewingInquiry->custom_fields))
                    <div class="space-y-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <span class="text-xs font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                            <span>⚙️</span>
                            <span>{{ __('Submitted Custom Fields (:count)', ['count' => count($viewingInquiry->custom_fields)]) }}</span>
                        </span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            @foreach ($viewingInquiry->custom_fields as $key => $cf)
                                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 space-y-1">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">{{ $cf['label'] ?? ucfirst($key) }}</span>
                                    <span class="font-bold text-slate-900 dark:text-white block">
                                        {{ is_array($cf['value'] ?? '') ? implode(', ', $cf['value']) : ($cf['value'] ?? '-') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Modal Footer Actions -->
                <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:confirm="{{ __('Delete this inquiry permanently?') }}" wire:click="deleteInquiry({{ $viewingInquiry->id }})"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950 transition cursor-pointer">
                        {{ __('Delete Inquiry') }}
                    </button>

                    <div class="flex items-center gap-2">
                        <a href="mailto:{{ $viewingInquiry->email }}?subject=Re: {{ rawurlencode($viewingInquiry->subject ?: 'Your inquiry') }}"
                           class="px-4 py-2 rounded-xl text-xs font-black bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-1.5 shadow-sm">
                            <span>✉️</span>
                            <span>{{ __('Reply via Email') }}</span>
                        </a>
                        <button type="button" wire:click="closeDetailModal"
                                class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                            {{ __('Close') }}
                        </button>
                    </div>
                </div>

            </div>
        </div>
    @endif

    <!-- ============================================================= -->
    <!-- MODAL: ADD / EDIT CUSTOM FIELD                                -->
    <!-- ============================================================= -->
    @if ($showFieldModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md animate-in fade-in">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
                
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>{{ $isEditingField ? '✏️' : '➕' }}</span>
                        <span>{{ $isEditingField ? __('Edit Form Field') : __('Add Custom Form Field') }}</span>
                    </h3>
                    <button type="button" wire:click="closeFieldModal" class="text-slate-400 hover:text-slate-700 dark:hover:text-white text-xl font-black cursor-pointer">&times;</button>
                </div>

                <form wire:submit.prevent="saveField" class="space-y-4 text-xs">
                    <!-- Field Label -->
                    <div>
                        <label class="block font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Field Label') }} *</label>
                        <input type="text" wire:model.live="fieldLabel" placeholder="e.g. Number of Outlets"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-white font-bold">
                        @error('fieldLabel') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field Key Name -->
                    <div>
                        <label class="block font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Field Key Name (slug)') }} *</label>
                        <input type="text" wire:model="fieldName" placeholder="e.g. number_of_outlets"
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-white font-mono text-xs">
                        <span class="text-[10px] text-slate-400 block mt-1">{{ __('Unique identifier in submissions. Letters, numbers, and underscores only.') }}</span>
                        @error('fieldName') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <!-- Field Type -->
                    <div>
                        <label class="block font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Input Type') }} *</label>
                        <select wire:model.live="fieldType" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-white font-bold">
                            <option value="text">{{ __('Single-Line Text') }}</option>
                            <option value="email">{{ __('Email Address') }}</option>
                            <option value="tel">{{ __('Phone / Telephone Number') }}</option>
                            <option value="number">{{ __('Number (Quantity / Count / Budget)') }}</option>
                            <option value="textarea">{{ __('Multi-Line Textarea') }}</option>
                            <option value="select">{{ __('Select Dropdown (Choose One)') }}</option>
                            <option value="checkbox">{{ __('Checkbox (Single Confirmation / Consent)') }}</option>
                        </select>
                        @error('fieldType') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <!-- Options for Select -->
                    @if ($fieldType === 'select')
                        <div>
                            <label class="block font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Dropdown Choices (comma-separated)') }} *</label>
                            <textarea wire:model="fieldOptions" rows="3" placeholder="Retail & Grocery, Restaurant, Salon & Spa, Pharmacy, Wholesale"
                                      class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-white font-medium"></textarea>
                            <span class="text-[10px] text-slate-400 block mt-1">{{ __('Separate each selectable option with a comma.') }}</span>
                        </div>
                    @endif

                    <!-- Placeholder -->
                    <div>
                        <label class="block font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Placeholder Text') }}</label>
                        <input type="text" wire:model="fieldPlaceholder" placeholder="e.g. Enter estimated number..."
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-white font-medium">
                    </div>

                    <!-- Layout Width -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Field Width') }}</label>
                            <select wire:model="fieldWidth" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-900 dark:text-white font-semibold">
                                <option value="half">{{ __('Half Width (50% - 2 per row)') }}</option>
                                <option value="full">{{ __('Full Width (100% - 1 per row)') }}</option>
                            </select>
                        </div>

                        <!-- Required Toggle -->
                        <div>
                            <label class="block font-black uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">{{ __('Required?') }}</label>
                            <div class="flex items-center gap-2 pt-1.5">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model="fieldRequired" class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:bg-slate-700 peer-checked:bg-emerald-600"></div>
                                </label>
                                <span class="text-xs font-bold text-slate-600 dark:text-slate-400">{{ $fieldRequired ? __('Required') : __('Optional') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" wire:click="closeFieldModal"
                                class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 transition cursor-pointer">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit"
                                class="px-5 py-2 rounded-xl text-xs font-black text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-md shadow-indigo-600/20 cursor-pointer">
                            {{ $isEditingField ? __('Update Field') : __('Add Field to Form') }}
                        </button>
                    </div>
                </form>

            </div>
        </div>
    @endif

</div>
