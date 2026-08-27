<div class="space-y-6">

    @if (session('status'))
        <div class="px-5 py-3.5 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 text-xs sm:text-sm font-bold flex items-center gap-2.5 shadow-sm border border-emerald-100 dark:border-emerald-900/50">
            <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Page Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center text-lg font-black shadow-md shadow-blue-500/20">
                    🛠️
                </span>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                        {{ __("Service Orders & Warranty Repairs") }}
                    </h1>
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ __("Track customer equipment intake, serial/IMEI, technical diagnosis, parts replacement & warranty delivery.") }}
                    </p>
                </div>
            </div>
        </div>

        @if (auth('web')->user()?->hasPermission('service_orders', 'create'))
            <button type="button"
                    wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-blue-500/25 active:scale-95 transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                <span>+ {{ __("New Service Order (OS)") }}</span>
            </button>
        @endif
    </div>

    <!-- Status Flow Lifecycle Quick Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
        @php
            $statuses = [
                'all' => ['label' => __('All Orders'), 'icon' => '📋', 'count' => $counts['all'] ?? 0],
                \App\Models\ServiceOrder::STATUS_RECEIVED => ['label' => __('Received'), 'icon' => '📥', 'count' => $counts[\App\Models\ServiceOrder::STATUS_RECEIVED] ?? 0],
                \App\Models\ServiceOrder::STATUS_UNDER_DIAGNOSIS => ['label' => __('Under Diagnosis'), 'icon' => '🔍', 'count' => $counts[\App\Models\ServiceOrder::STATUS_UNDER_DIAGNOSIS] ?? 0],
                \App\Models\ServiceOrder::STATUS_WAITING_PARTS_APPROVAL => ['label' => __('Waiting Parts / Approval'), 'icon' => '⏳', 'count' => $counts[\App\Models\ServiceOrder::STATUS_WAITING_PARTS_APPROVAL] ?? 0],
                \App\Models\ServiceOrder::STATUS_READY_FOR_PICKUP => ['label' => __('Ready for Pickup'), 'icon' => '✅', 'count' => $counts[\App\Models\ServiceOrder::STATUS_READY_FOR_PICKUP] ?? 0],
                \App\Models\ServiceOrder::STATUS_DELIVERED_SETTLED => ['label' => __('Delivered & Settled'), 'icon' => '🤝', 'count' => $counts[\App\Models\ServiceOrder::STATUS_DELIVERED_SETTLED] ?? 0],
            ];
        @endphp

        @foreach ($statuses as $sKey => $sInfo)
            <button type="button"
                    wire:click="$set('statusFilter', '{{ $sKey }}')"
                    @class([
                        'px-4 py-2 rounded-2xl text-xs font-black whitespace-nowrap transition shadow-2xs cursor-pointer flex items-center gap-2 border',
                        'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20' => $statusFilter === $sKey,
                        'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-100 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700' => $statusFilter !== $sKey,
                    ])>
                <span>{{ $sInfo['icon'] }}</span>
                <span>{{ $sInfo['label'] }}</span>
                <span @class([
                    'px-1.5 py-0.5 rounded-full text-[10px] font-bold',
                    'bg-white/20 text-white' => $statusFilter === $sKey,
                    'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300' => $statusFilter !== $sKey,
                ])>
                    {{ $sInfo['count'] }}
                </span>
            </button>
        @endforeach
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-4 sm:p-5 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Search input -->
        <div class="relative flex-1 max-w-md">
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="{{ __("Search by OS #, customer, equipment, serial / IMEI...") }}"
                   class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-800/70 rounded-2xl border-none text-xs sm:text-sm font-medium focus:ring-2 focus:ring-blue-500 text-slate-800 dark:text-slate-100">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
        </div>

        <!-- Priority & Technician filters -->
        <div class="flex items-center gap-2">
            <select wire:model.live="priorityFilter" class="rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 focus:ring-blue-500 py-2">
                <option value="all">{{ __("All Priorities") }}</option>
                <option value="urgent">🔴 {{ __("Urgent") }}</option>
                <option value="high">🟠 {{ __("High") }}</option>
                <option value="normal">🔵 {{ __("Normal") }}</option>
                <option value="low">⚪ {{ __("Low") }}</option>
            </select>

            <select wire:model.live="technicianFilter" class="rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 focus:ring-blue-500 py-2">
                <option value="all">{{ __("All Technicians") }}</option>
                @foreach ($technicians as $tech)
                    <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Service Orders Grid / Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @forelse ($serviceOrders as $so)
            @php
                $statusInfo = $so->getStatusInfo();
                $priorityInfo = \App\Models\ServiceOrder::PRIORITIES[$so->priority] ?? ['label' => ucfirst($so->priority), 'color' => 'slate'];
            @endphp
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-5 shadow-[0_4px_25px_rgb(0,0,0,0.03)] border border-slate-100 dark:border-slate-800 space-y-4 hover:border-blue-500/40 transition flex flex-col justify-between">
                
                <!-- Card Header: OS #, Priority, Status Badge -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-black text-sm text-blue-600 dark:text-blue-400">
                                #{{ $so->order_number }}
                            </span>
                            <span @class([
                                'px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider',
                                'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' => $so->priority === 'urgent',
                                'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $so->priority === 'high',
                                'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300' => $so->priority === 'normal',
                                'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => $so->priority === 'low',
                            ])>
                                {{ $priorityInfo['label'] }}
                            </span>
                        </div>

                        <!-- Status Badge -->
                        <span class="px-2.5 py-1 rounded-full text-xs font-black border {{ $statusInfo['badge_classes'] }} flex items-center gap-1">
                            <span>{{ $statusInfo['icon'] }}</span>
                            <span>{{ $statusInfo['label'] }}</span>
                        </span>
                    </div>

                    <!-- Equipment Details -->
                    <div class="pt-1">
                        <h3 class="font-black text-slate-900 dark:text-white text-base">
                            {{ $so->equipment_name }}
                        </h3>
                        <div class="text-xs text-slate-400 flex items-center gap-2 mt-0.5">
                            @if ($so->brand_model)
                                <span>{{ $so->brand_model }}</span>
                            @endif
                            @if ($so->serial_number)
                                <span>&bull; SN/IMEI: <code class="font-mono text-slate-600 dark:text-slate-300">{{ $so->serial_number }}</code></span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Defect & Customer Details -->
                <div class="p-3 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800/80 space-y-2 text-xs">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400">{{ __("Reported Defect") }}:</span>
                        <p class="text-slate-700 dark:text-slate-300 font-medium line-clamp-2 mt-0.5">
                            {{ $so->reported_defect }}
                        </p>
                    </div>

                    @if ($so->technical_diagnosis)
                        <div class="pt-1.5 border-t border-slate-200/60 dark:border-slate-700/60">
                            <span class="text-[10px] uppercase font-bold text-indigo-400">{{ __("Diagnosis") }}:</span>
                            <p class="text-slate-600 dark:text-slate-400 italic line-clamp-1 mt-0.5">
                                {{ $so->technical_diagnosis }}
                            </p>
                        </div>
                    @endif

                    <div class="pt-1.5 border-t border-slate-200/60 dark:border-slate-700/60 flex items-center justify-between text-[11px] text-slate-500">
                        <span class="font-bold text-slate-700 dark:text-slate-200 truncate">👤 {{ $so->customer_name }}</span>
                        @if ($so->customer_phone)
                            <span class="font-mono text-slate-400">{{ $so->customer_phone }}</span>
                        @endif
                    </div>
                </div>

                <!-- Pricing & Action Bar -->
                <div class="space-y-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <div class="text-xs text-slate-400">
                            {{ __("Warranty:") }} <strong class="text-slate-700 dark:text-slate-300">{{ $so->warranty_period ?: '90 days' }}</strong>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] uppercase font-bold text-slate-400">{{ __("Service Total") }}</div>
                            <div class="text-base font-black text-slate-900 dark:text-white font-mono">
                                {{ $company->formatMoney($so->total_amount) }}
                            </div>
                        </div>
                    </div>

                    <!-- Workflow Status Selector & Actions -->
                    <div class="flex items-center justify-between gap-2 pt-1">
                        <div class="flex-1">
                            <select wire:change="updateOrderStatus({{ $so->id }}, $event.target.value)"
                                    class="w-full py-1.5 px-2.5 rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-[11px] font-bold text-slate-800 dark:text-slate-200 focus:ring-blue-500">
                                @foreach (\App\Models\ServiceOrder::STATUSES as $stKey => $st)
                                    <option value="{{ $stKey }}" @selected($so->status === $stKey)>
                                        {{ $st['icon'] }} {{ $st['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="button"
                                wire:click="openViewModal({{ $so->id }})"
                                class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 hover:bg-blue-100 transition"
                                title="{{ __("Print Ticket / View Details") }}">
                            🖨️
                        </button>

                        @if (auth('web')->user()?->hasPermission('service_orders', 'edit'))
                            <button type="button"
                                    wire:click="openEditModal({{ $so->id }})"
                                    class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition"
                                    title="{{ __("Edit Order") }}">
                                ✏️
                            </button>
                        @endif

                        @if (auth('web')->user()?->hasPermission('service_orders', 'delete'))
                            <button type="button"
                                    wire:click="deleteOrder({{ $so->id }})"
                                    wire:confirm="{{ __("Delete service order #") }}{{ $so->order_number }}?"
                                    class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 hover:bg-rose-100 transition"
                                    title="{{ __("Delete Order") }}">
                                🗑️
                            </button>
                        @endif
                    </div>
                </div>

            </div>
        @empty
            <div class="col-span-full py-16 bg-white dark:bg-slate-900 rounded-3xl p-8 text-center border border-slate-100 dark:border-slate-800 space-y-3">
                <div class="text-4xl">🛠️</div>
                <h3 class="font-extrabold text-slate-800 dark:text-slate-200 text-base">
                    {{ __("No Service Orders Found") }}
                </h3>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">
                    {{ __("Create a new service order when a customer brings in equipment for maintenance, warranty repair, or technical diagnosis.") }}
                </p>
                @if (auth('web')->user()?->hasPermission('service_orders', 'create'))
                    <button type="button"
                            wire:click="openCreateModal"
                            class="px-5 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md shadow-blue-500/25 transition">
                        + {{ __("New Service Order") }}
                    </button>
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $serviceOrders->links() }}
    </div>

    <!-- =========================================================================
         CREATE / EDIT SERVICE ORDER MASTER MODAL
         ========================================================================= -->
    @include('tenant.service-orders.modal')

    <!-- =========================================================================
         VIEW SERVICE ORDER & PRINTABLE TICKET MASTER MODAL
         ========================================================================= -->
    <x-modal wire:model="showViewModal" maxWidth="2xl" :title="__('Service Order / Repair Ticket') . ($viewingOrder ? ' #' . $viewingOrder->order_number : '')" subtitle="{{ __('Printable repair receipt and technician diagnosis') }}" icon="🖨️">
        @if ($viewingOrder)
            @include('tenant.service-orders.ticket', ['order' => $viewingOrder])
        @endif

        <x-slot:footer>
            <button type="button"
                    onclick="window.print()"
                    class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md shadow-blue-500/25 active:scale-95 transition flex items-center gap-2 cursor-pointer">
                <span>🖨️</span>
                <span>{{ __("Print Ticket / Slip") }}</span>
            </button>

            <button type="button" @click="open = false" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition cursor-pointer">
                {{ __("Close") }}
            </button>
        </x-slot:footer>
    </x-modal>

</div>
