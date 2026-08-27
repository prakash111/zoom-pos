<div class="space-y-4 text-xs font-sans">
    
    <!-- Top Header & Breadcrumb Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-3 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-lg">📉</span>
                <h1 class="text-base font-black text-slate-900 dark:text-white tracking-tight">{{ __("Accounts Payable & Expense Management") }}</h1>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">{{ __("Manage vendor bills, operational expenses, utility costs, supplier settlements, and payment proofs.") }}</p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button"
                    wire:click="openCreateBillModal"
                    class="px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md shadow-blue-500/20 active:scale-95 transition flex items-center gap-1.5 cursor-pointer">
                <span>+ {{ __("Add Vendor Bill / Expense") }}</span>
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- 1. Total Due Today -->
        <div class="bg-white dark:bg-slate-900 p-3.5 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-500">{{ __("Total Due Today") }}</span>
                <div class="text-lg sm:text-xl font-black text-rose-600 dark:text-rose-400 font-mono mt-0.5">${{ number_format($totalDueToday, 2) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5 font-semibold">{{ __("Immediate settlement") }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center text-lg font-black">
                📅
            </div>
        </div>

        <!-- 2. Upcoming Expenses -->
        <div class="bg-white dark:bg-slate-900 p-3.5 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-500">{{ __("Upcoming (30 Days)") }}</span>
                <div class="text-lg sm:text-xl font-black text-amber-600 dark:text-amber-400 font-mono mt-0.5">${{ number_format($upcomingExpenses, 2) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5 font-semibold">{{ __("Scheduled outgoings") }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg font-black">
                ⏳
            </div>
        </div>

        <!-- 3. Settled Bills This Month -->
        <div class="bg-white dark:bg-slate-900 p-3.5 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __("Settled This Month") }}</span>
                <div class="text-lg sm:text-xl font-black text-emerald-600 dark:text-emerald-400 font-mono mt-0.5">${{ number_format($settledThisMonth, 2) }}</div>
                <div class="text-[10px] text-emerald-600 font-semibold mt-0.5">{{ now()->format('F Y') }} {{ __('Paid') }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg font-black">
                ✅
            </div>
        </div>

        <!-- 4. Total Outstanding Payables -->
        <div class="bg-white dark:bg-slate-900 p-3.5 sm:p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-purple-500">{{ __("Total Outstanding") }}</span>
                <div class="text-lg sm:text-xl font-black text-purple-600 dark:text-purple-400 font-mono mt-0.5">${{ number_format($totalOutstandingPayables, 2) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5 font-semibold">{{ __("All unpaid bills") }}</div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center text-lg font-black">
                📑
            </div>
        </div>
    </div>

    <!-- Status Alert Flash -->
    @if (session('status'))
        <div class="p-3 rounded-xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('status') }}</span>
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="p-3 rounded-xl bg-rose-50 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-xs font-bold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✕</span>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Main High-Density Data Container -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        
        <!-- Filter Toolbar -->
        <div class="p-3 sm:p-3.5 border-b border-slate-200 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-850">
            
            <!-- Left Search Input -->
            <div class="relative w-full sm:w-72">
                <input type="text"
                       wire:model.live.debounce.250ms="search"
                       placeholder="{{ __("Search bill #, vendor, title...") }}"
                       class="w-full pl-8 pr-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:ring-1 focus:ring-blue-500">
                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                    🔍
                </div>
            </div>

            <!-- Right Filters -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Category Filter -->
                <select wire:model.live="categoryFilter" class="py-1.5 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300">
                    <option value="">{{ __("All Categories") }}</option>
                    <option value="inventory">📦 {{ __("Inventory / Goods") }}</option>
                    <option value="utilities">⚡ {{ __("Utilities (Power/Water)") }}</option>
                    <option value="rent">🏢 {{ __("Rent & Premises") }}</option>
                    <option value="salaries">💼 {{ __("Payroll & Salaries") }}</option>
                    <option value="logistics">🚚 {{ __("Logistics & Freight") }}</option>
                    <option value="maintenance">🔧 {{ __("Maintenance & Repairs") }}</option>
                    <option value="marketing">📣 {{ __("Marketing & Ads") }}</option>
                    <option value="office">📎 {{ __("Office Supplies") }}</option>
                    <option value="tax_legal">⚖️ {{ __("Taxes & Professional") }}</option>
                    <option value="other">📑 {{ __("Other Expenses") }}</option>
                </select>

                <!-- Status Filter -->
                <select wire:model.live="statusFilter" class="py-1.5 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300">
                    <option value="">{{ __("All Statuses") }}</option>
                    <option value="pending">{{ __("Pending / Unpaid") }}</option>
                    <option value="partially_paid">{{ __("Partially Paid") }}</option>
                    <option value="overdue">{{ __("Overdue Bills") }}</option>
                    <option value="paid">{{ __("Settled (Paid)") }}</option>
                </select>

                <!-- Supplier Filter -->
                <select wire:model.live="supplierFilter" class="py-1.5 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-300 max-w-[150px] truncate">
                    <option value="">{{ __("All Suppliers") }}</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

        </div>

        <!-- High-Density Desktop ERP Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-100/80 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-extrabold uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-700 sticky top-0">
                    <tr>
                        <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700/60">{{ __("Bill #") }}</th>
                        <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700/60">{{ __("Vendor / Supplier") }}</th>
                        <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700/60">{{ __("Category & Title") }}</th>
                        <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700/60">{{ __("Bill Date") }}</th>
                        <th class="py-2.5 px-3 border-r border-slate-200 dark:border-slate-700/60">{{ __("Due Date") }}</th>
                        <th class="py-2.5 px-3 text-right border-r border-slate-200 dark:border-slate-700/60">{{ __("Bill Amount") }}</th>
                        <th class="py-2.5 px-3 text-right border-r border-slate-200 dark:border-slate-700/60">{{ __("Settled") }}</th>
                        <th class="py-2.5 px-3 text-right border-r border-slate-200 dark:border-slate-700/60">{{ __("Balance Due") }}</th>
                        <th class="py-2.5 px-3 text-center border-r border-slate-200 dark:border-slate-700/60">{{ __("Status") }}</th>
                        <th class="py-2.5 px-3 text-center border-r border-slate-200 dark:border-slate-700/60">{{ __("Proof") }}</th>
                        <th class="py-2.5 px-3 text-center">{{ __("Actions") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-[11px] font-medium">
                    @forelse ($bills as $bill)
                        @php
                            $isOverdue = $bill->isOverdue();
                            $totalVal = (float)$bill->amount + (float)$bill->tax_amount;
                        @endphp
                        <tr class="hover:bg-blue-50/40 dark:hover:bg-slate-800/50 transition">
                            <!-- Bill # -->
                            <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 font-mono font-bold text-blue-600 dark:text-blue-400">
                                {{ $bill->bill_number }}
                            </td>

                            <!-- Vendor -->
                            <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 font-bold text-slate-900 dark:text-white">
                                {{ $bill->effective_vendor_name }}
                            </td>

                            <!-- Category & Title -->
                            <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800">
                                <span class="text-slate-800 dark:text-slate-200 font-bold block truncate max-w-[200px]">{{ $bill->title }}</span>
                                <span class="text-[10px] text-slate-400 capitalize font-mono">{{ str_replace('_', ' ', $bill->category) }}</span>
                            </td>

                            <!-- Bill Date -->
                            <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 text-slate-500 font-mono">
                                {{ $bill->bill_date ? $bill->bill_date->format('Y-m-d') : '—' }}
                            </td>

                            <!-- Due Date -->
                            <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 font-mono">
                                @if ($bill->due_date)
                                    <span @class([
                                        'font-bold',
                                        'text-rose-600 dark:text-rose-400 font-black' => $isOverdue,
                                        'text-slate-600 dark:text-slate-300' => ! $isOverdue,
                                    ])>
                                        {{ $bill->due_date->format('Y-m-d') }}
                                        @if ($isOverdue)
                                            <span class="text-[9px] block text-rose-500 font-sans uppercase">OVERDUE</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-slate-400 font-normal">{{ __("Immediate") }}</span>
                                @endif
                            </td>

                            <!-- Total Bill Amount -->
                            <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 text-right font-mono font-bold text-slate-900 dark:text-white">
                                ${{ number_format($totalVal, 2) }}
                            </td>

                            <!-- Settled Paid Amount -->
                            <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                ${{ number_format($bill->paid_amount, 2) }}
                            </td>

                            <!-- Balance Due -->
                            <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 text-right font-mono font-extrabold">
                                @if ($bill->due_amount > 0)
                                    <span class="text-rose-600 dark:text-rose-400">${{ number_format($bill->due_amount, 2) }}</span>
                                @else
                                    <span class="text-slate-400 font-normal">$0.00</span>
                                @endif
                            </td>

                            <!-- Status Badge -->
                            <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 text-center">
                                @if ($bill->status === 'paid' || $bill->due_amount <= 0.001)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                        {{ __("PAID") }}
                                    </span>
                                @elseif ($isOverdue)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                        {{ __("OVERDUE") }}
                                    </span>
                                @elseif ($bill->paid_amount > 0)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                        {{ __("PARTIAL") }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        UN{{ __("PAID") }}
                                    </span>
                                @endif
                            </td>

                            <!-- Attachment Proof -->
                            <td class="py-2 px-3 border-r border-slate-100 dark:border-slate-800 text-center">
                                @if ($bill->attachment_path)
                                    <a href="{{ asset('storage/' . $bill->attachment_path) }}" target="_blank" class="text-blue-500 hover:underline font-bold text-xs" title="{{ __("View attached bill file") }}">
                                        📎 {{ __("Proof") }}
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            <!-- Action Buttons -->
                            <td class="py-2 px-3 text-center space-x-1.5 whitespace-nowrap">
                                @if ($bill->due_amount > 0)
                                    <button type="button"
                                            wire:click="openPaymentModal({{ $bill->id }})"
                                            class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] transition shadow-2xs active:scale-95 cursor-pointer">
                                        + {{ __("Pay Bill") }}
                                    </button>
                                @endif

                                <button type="button"
                                        wire:click="openEditBillModal({{ $bill->id }})"
                                        class="px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 font-bold text-[10px] transition" title="{{ __("Edit Bill") }}">
                                    ✏️
                                </button>

                                @if ($bill->paid_amount <= 0)
                                    <button type="button"
                                            wire:click="deleteBill({{ $bill->id }})"
                                            wire:confirm="{{ __("Are you sure you want to delete this bill?") }}"
                                            class="px-2 py-1 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 hover:bg-rose-100 font-bold text-[10px] transition" title="{{ __("Delete Bill") }}">
                                        ✕
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-12 text-center text-slate-400">
                                {{ __("No vendor bills or operational expenses match current filters.") }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="p-3 border-t border-slate-200 dark:border-slate-800">
            {{ $bills->links() }}
        </div>

    </div>

    <!-- Modal 1: Create / Edit Vendor Bill -->
    @if ($showBillModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4"
             x-data
             x-on:keydown.escape.window="$wire.closeBillModal()"
             x-cloak>
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-xl w-full p-5 sm:p-6 space-y-4">
                
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-black text-slate-900 dark:text-white">
                        {{ $editingBillId ? __('Edit Vendor Bill / Expense') : __('Create Vendor Bill / Expense') }}
                    </h3>
                    <button type="button" wire:click="closeBillModal" class="text-slate-400 hover:text-slate-700 text-lg font-bold">&times;</button>
                </div>

                <div class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Bill / Invoice Reference #") }}</label>
                            <input type="text" wire:model="billNumber" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-mono font-bold">
                            @error('billNumber') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Expense Category") }}</label>
                            <select wire:model="category" class="w-full py-2 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold">
                                <option value="inventory">📦 {{ __("Inventory / Raw Materials") }}</option>
                                <option value="utilities">⚡ {{ __("Utilities (Electricity, Water, Internet)") }}</option>
                                <option value="rent">🏢 {{ __("Rent & Facilities") }}</option>
                                <option value="salaries">💼 {{ __("Payroll & Staff Wages") }}</option>
                                <option value="logistics">🚚 {{ __("Logistics, Freight & Delivery") }}</option>
                                <option value="maintenance">🔧 {{ __("Maintenance & Repairs") }}</option>
                                <option value="marketing">📣 {{ __("Marketing & Promotions") }}</option>
                                <option value="office">📎 {{ __("Office Supplies & Software") }}</option>
                                <option value="tax_legal">⚖️ {{ __("Taxes & Professional Services") }}</option>
                                <option value="other">📑 {{ __("Other Expenses") }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Linked Supplier (Optional)") }}</label>
                            <select wire:model="supplierId" class="w-full py-2 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                                <option value="">— {{ __("Non-Supplier / General Vendor") }} —</option>
                                @foreach ($suppliers as $sup)
                                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Vendor Name (If not in suppliers)") }}</label>
                            <input type="text" wire:model="vendorName" placeholder="{{ __("e.g. City Power Corp, Apex Landlord") }}" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Bill Title / Description") }}</label>
                        <input type="text" wire:model="title" placeholder="{{ __("e.g. Monthly Warehouse Rent or Stock Restock Batch #4") }}" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold">
                        @error('title') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Base Amount ($)") }}</label>
                            <input type="number" wire:model="amount" step="0.01" min="0.01" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono font-bold">
                            @error('amount') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Tax Amount ($)") }}</label>
                            <input type="number" wire:model="taxAmount" step="0.01" min="0" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono font-bold">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Bill Date") }}</label>
                            <input type="date" wire:model="billDate" class="w-full py-2 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Due Date") }}</label>
                            <input type="date" wire:model="dueDate" class="w-full py-2 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Invoice / Receipt Attachment (PDF, Image)") }}</label>
                        <input type="file" wire:model="attachment" class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Internal Notes") }}</label>
                        <textarea wire:model="notes" rows="2" placeholder="{{ __("Payment terms, bank details, purchase order #...") }}" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs"></textarea>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
                    <button type="button" wire:click="closeBillModal" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-bold text-xs">
                        Cancel
                    </button>

                    <button type="button" wire:click="saveBill" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-black text-xs shadow-md shadow-blue-500/25 active:scale-95 transition">
                        {{ __("Save Bill / Expense") }}
                    </button>
                </div>

            </div>
        </div>
    @endif

    <!-- Modal 2: Record Settlement Payment -->
    @if ($showPaymentModal && $selectedBill)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4"
             x-data
             x-on:keydown.escape.window="$wire.closePaymentModal()"
             x-cloak>
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-lg w-full p-5 sm:p-6 space-y-4">
                
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">{{ __("Record Bill Settlement Payment") }}</h3>
                        <p class="text-xs text-slate-400">{{ __("Bill") }} #{{ $selectedBill->bill_number }} &bull; {{ $selectedBill->effective_vendor_name }}</p>
                    </div>
                    <button type="button" wire:click="closePaymentModal" class="text-slate-400 hover:text-slate-700 text-lg font-bold">&times;</button>
                </div>

                <!-- Snapshot -->
                <div class="grid grid-cols-3 gap-2 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 text-center font-mono">
                    <div>
                        <span class="text-[10px] text-slate-400 font-bold block uppercase">{{ __("Total Bill") }}</span>
                        <span class="text-xs font-black text-slate-900 dark:text-white">${{ number_format((float)$selectedBill->amount + (float)$selectedBill->tax_amount, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-emerald-600 font-bold block uppercase">{{ __("Paid so far") }}</span>
                        <span class="text-xs font-black text-emerald-600 dark:text-emerald-400">${{ number_format($selectedBill->paid_amount, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-rose-500 font-bold block uppercase">{{ __("Remaining Due") }}</span>
                        <span class="text-xs font-black text-rose-600 dark:text-rose-400">${{ number_format($selectedBill->due_amount, 2) }}</span>
                    </div>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Settlement Amount ($)") }}</label>
                        <input type="number" wire:model="settlementAmount" step="0.01" min="0.01" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono font-bold text-slate-900 dark:text-white">
                        @error('settlementAmount') <span class="text-rose-600 text-[10px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Payment Method") }}</label>
                            <select wire:model="settlementMethod" class="w-full py-2 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold">
                                @foreach ($paymentMethods as $pm)
                                    <option value="{{ $pm->code }}">{{ $pm->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Settlement Date") }}</label>
                            <input type="date" wire:model="settlementDate" class="w-full py-2 px-2.5 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Bank Reference / Wire TXN / Check #") }}</label>
                        <input type="text" wire:model="referenceNumber" placeholder="{{ __("e.g. WIRE-884401 or Check #1002") }}" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Payment Proof / Bank Receipt (Optional)") }}</label>
                        <input type="file" wire:model="settlementProof" class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __("Payment Remarks") }}</label>
                        <textarea wire:model="settlementNotes" rows="2" placeholder="{{ __("Settlement remarks or transfer notes...") }}" class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs"></textarea>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
                    <button type="button" wire:click="closePaymentModal" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 font-bold text-xs">
                        Cancel
                    </button>

                    <button type="button" wire:click="recordSettlement" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs shadow-md shadow-emerald-500/25 active:scale-95 transition">
                        {{ __("Confirm Settlement Payment") }}
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
