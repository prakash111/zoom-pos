<!-- Standard Scanner Cart Line Item Row -->
<div class="flex items-center justify-between gap-3 py-2 border-b border-slate-100 dark:border-slate-800 text-xs">
    <!-- Product Info & Editable Unit Price -->
    <div class="flex-1 min-w-0">
        <div class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ $item['name'] }}</div>
        <div class="mt-1 flex items-center gap-2">
            <!-- Clean Unit Price Input (No outer $ symbol) -->
            <input type="number" 
                   step="0.01" 
                   wire:model.live.debounce.250ms="cartItems.{{ $index }}.price" 
                   class="w-18 px-2 py-0.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-bold text-slate-800 dark:text-slate-100 focus:ring-1 focus:ring-blue-500">
        </div>
    </div>

    <!-- Quantity Counter -->
    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 rounded-xl p-0.5 border border-slate-200 dark:border-slate-700">
        <button type="button" wire:click="decrementQty({{ $index }})" class="w-6 h-6 flex items-center justify-center text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 rounded-lg transition">−</button>
        <span class="px-2 text-xs font-bold text-slate-800 dark:text-white">{{ $item['quantity'] }}</span>
        <button type="button" wire:click="incrementQty({{ $index }})" class="w-6 h-6 flex items-center justify-center text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 rounded-lg transition">+</button>
    </div>

    <!-- Line Total & Remove Action -->
    <div class="text-right flex items-center gap-2">
        <span class="font-mono font-black text-slate-800 dark:text-slate-100">${{ number_format($item['price'] * $item['quantity'], 2) }}</span>
        <button type="button" wire:click="removeFromCart({{ $index }})" class="text-slate-400 hover:text-rose-500 transition text-sm">✕</button>
    </div>
</div>
