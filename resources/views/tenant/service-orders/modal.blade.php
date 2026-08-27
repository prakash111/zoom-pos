<x-modal wire:model="showModal" maxWidth="3xl" :title="$isEditing ? __('Edit Service Order') : __('Service Order & Warranty Intake')" subtitle="{{ __('Fill in equipment specs, customer details, defect diagnosis, and replacement parts.') }}" icon="🛠️">
    
    <!-- 1. Clean Group Card: Customer Information -->
    <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800/80 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                <span>👤</span> {{ __('Customer Information') }}
            </span>
            @if(isset($customers) && $customers->isNotEmpty())
                <select wire:change="selectCustomer($event.target.value)" class="rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs py-1 px-2.5 font-bold">
                    <option value="">{{ __("Select Existing Customer...") }}</option>
                    @foreach ($customers as $cust)
                        <option value="{{ $cust->id }}" @selected($customerId === $cust->id)>{{ $cust->name }} ({{ $cust->phone ?? $cust->email }})</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Customer Name') }} *</label>
                <input type="text" wire:model="customerName" placeholder="e.g. Maria Garcia" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500">
                @error('customerName') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Customer Phone') }}</label>
                <input type="text" wire:model="customerPhone" placeholder="+1-555-0104" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500 font-mono">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Customer Email') }}</label>
                <input type="email" wire:model="customerEmail" placeholder="client@example.com" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
    </div>

    <!-- 2. Clean Group Card: Equipment & Defect -->
    <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800/80 space-y-3">
        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
            <span>💻</span> {{ __('Equipment & Defect Description') }}
        </span>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Equipment Name / Type') }} *</label>
                <input type="text" wire:model="equipmentName" placeholder="e.g. MacBook Pro 14" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500">
                @error('equipmentName') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Brand & Model') }}</label>
                <input type="text" wire:model="brandModel" placeholder="e.g. Apple A2638" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Serial Number / IMEI') }}</label>
                <input type="text" wire:model="serialNumber" placeholder="e.g. 354829104829103" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500 font-mono">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Reported Defect (Client Issue)') }} *</label>
                <textarea wire:model="reportedDefect" rows="2" placeholder="{{ __('Describe symptoms reported by client...') }}" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500"></textarea>
                @error('reportedDefect') <span class="text-rose-500 text-[10px]">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Technical Diagnosis & Solution') }}</label>
                <textarea wire:model="technicalDiagnosis" rows="2" placeholder="{{ __('e.g. Replaced display OLED module and cleaned charging port...') }}" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
        </div>
    </div>

    <!-- 3. Clean Group Card: Replacement Parts & Inventory Integration -->
    <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800/80 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                <span>📦</span> {{ __('Parts Used (Inventory Stock Integration)') }}
            </span>
            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                {{ __('Parts Total:') }} <strong class="text-blue-600 dark:text-blue-400 font-mono">{{ $company->formatMoney($partsTotal) }}</strong>
            </span>
        </div>

        <!-- Search Part Input -->
        <div class="relative">
            <input type="text"
                   wire:model.live.debounce.250ms="partSearch"
                   placeholder="{{ __('Search product name, SKU or barcode to add replacement part...') }}"
                   class="w-full pl-9 pr-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                🔍
            </div>

            @if (!empty($searchedProducts) && count($searchedProducts) > 0)
                <div class="absolute left-0 right-0 top-full mt-1 z-30 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-2xl shadow-xl overflow-hidden divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($searchedProducts as $p)
                        <button type="button"
                                wire:click="addPart({{ $p->id }})"
                                class="w-full px-4 py-2 text-left hover:bg-blue-50 dark:hover:bg-blue-950/50 flex items-center justify-between text-xs transition">
                            <div>
                                <div class="font-bold text-slate-800 dark:text-slate-200">{{ $p->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">SKU: {{ $p->code ?? $p->sku ?? 'N/A' }} &bull; Stock: {{ $p->current_stock }}</div>
                            </div>
                            <span class="font-mono font-bold text-blue-600 dark:text-blue-400">
                                {{ $company->formatMoney($p->sale_price) }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Selected Parts Line Items -->
        @if(count($partsUsed) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="text-slate-400 font-bold border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="pb-1.5 text-left">{{ __("Part") }}</th>
                            <th class="pb-1.5 text-center w-20">{{ __("Qty") }}</th>
                            <th class="pb-1.5 text-right w-24">{{ __("Price") }}</th>
                            <th class="pb-1.5 text-right w-24">{{ __("Total") }}</th>
                            <th class="pb-1.5 w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @foreach ($partsUsed as $pIdx => $part)
                            <tr>
                                <td class="py-2 font-bold text-slate-800 dark:text-slate-200">{{ $part['name'] }}</td>
                                <td class="py-2 text-center">
                                    <input type="number" step="1" min="0.1" wire:model.live="partsUsed.{{ $pIdx }}.quantity" class="w-16 py-1 px-1 text-center rounded-lg border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-mono font-bold">
                                </td>
                                <td class="py-2 text-right">
                                    <input type="number" step="0.01" min="0" wire:model.live="partsUsed.{{ $pIdx }}.unit_price" class="w-20 py-1 px-1 text-right rounded-lg border border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-xs font-mono">
                                </td>
                                <td class="py-2 text-right font-mono font-bold text-slate-900 dark:text-white">
                                    {{ $company->formatMoney($part['total'] ?? 0) }}
                                </td>
                                <td class="py-2 text-center">
                                    <button type="button" wire:click="removePart({{ $pIdx }})" class="text-rose-500 hover:text-rose-700 font-black">&times;</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- 4. Financial Calculations -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800/80 items-center">
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Labor / Service Cost ($)') }}</label>
            <input type="number" step="0.5" min="0" wire:model.live="laborCost" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold font-mono focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Discount ($)') }}</label>
            <input type="number" step="0.5" min="0" wire:model.live="discount" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold font-mono focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="bg-blue-50 dark:bg-blue-950/60 p-2.5 rounded-xl border border-blue-100 dark:border-blue-900/50 flex flex-col justify-center text-right">
            <span class="text-[10px] font-extrabold uppercase text-blue-600 dark:text-blue-400 block">{{ __('Grand Total') }}</span>
            <span class="text-lg font-black text-blue-700 dark:text-blue-300 font-mono">{{ $company->formatMoney($totalAmount) }}</span>
        </div>
    </div>

    <!-- 5. Status, Priority, Warranty, Technician & Notes -->
    <div class="p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800/80 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Status') }}</label>
                <select wire:model="status" class="w-full px-2.5 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500">
                    @foreach (\App\Models\ServiceOrder::STATUSES as $stKey => $st)
                        <option value="{{ $stKey }}">{{ $st['icon'] }} {{ $st['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Priority') }}</label>
                <select wire:model="priority" class="w-full px-2.5 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500">
                    <option value="low">Low</option>
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Technician') }}</label>
                <select wire:model="technicianId" class="w-full px-2.5 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach ($technicians as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Warranty Period') }}</label>
                <input type="text" wire:model="warrantyPeriod" placeholder="e.g. 90 days" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ __('Internal Notes & Accessories Handed') }}</label>
            <input type="text" wire:model="notes" placeholder="e.g. Handed with original charger, scratched back cover, etc." class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs focus:ring-2 focus:ring-blue-500">
        </div>
    </div>

    <!-- Standard Master Modal Footer Slot -->
    <x-slot:footer>
        <button type="button" @click="open = false" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer">
            {{ __('Cancel') }}
        </button>
        <button type="button" wire:click="save" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-500/20 transition active:scale-[0.98] cursor-pointer">
            {{ $isEditing ? __('Update Service Order') : __('Save & Create Service Order') }}
        </button>
    </x-slot:footer>
</x-modal>
