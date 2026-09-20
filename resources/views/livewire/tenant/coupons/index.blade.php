<div class="w-full space-y-6">
    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <span>🎟️</span>
                <span>{{ __('Coupons & Promo Codes') }}</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Create promotional coupons, percentage discounts, and order limits for your digital storefront.') }}
            </p>
        </div>

        <button type="button"
                wire:click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-black shadow-md shadow-emerald-600/20 transition active:scale-95 cursor-pointer self-start sm:self-auto">
            <span>+</span>
            <span>{{ __('Create New Coupon') }}</span>
        </button>
    </div>

    <!-- Flash Status Messages -->
    @if (session('status'))
        <div class="px-5 py-3 rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs sm:text-sm font-semibold flex items-center gap-2 shadow-xs">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- 3 Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400">{{ __('Active Coupons') }}</span>
                <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">{{ $stats['total_active'] }}</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl">
                🏷️
            </div>
        </div>

        <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400">{{ __('Times Redeemed') }}</span>
                <div class="text-2xl font-black text-slate-900 dark:text-white font-mono">{{ $stats['total_used'] }}</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xl">
                🛒
            </div>
        </div>

        <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-400">{{ __('Savings Given') }}</span>
                <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono">${{ number_format($stats['total_discount_given'], 2) }}</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-teal-50 dark:bg-teal-950/50 text-teal-600 dark:text-teal-400 flex items-center justify-center text-xl">
                💰
            </div>
        </div>
    </div>

    <!-- Search and Filters Bar -->
    <div class="p-4 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Search code or description...') }}"
                   class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-500">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            </div>
        </div>

        <div class="flex items-center gap-2 self-end sm:self-auto">
            <label class="text-xs font-bold text-slate-400">{{ __('Status:') }}</label>
            <select wire:model.live="statusFilter" class="text-xs font-bold rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-100 py-1.5 pl-3 pr-8 focus:ring-2 focus:ring-emerald-500">
                <option value="all">{{ __('All Status') }}</option>
                <option value="active">{{ __('Active Only') }}</option>
                <option value="inactive">{{ __('Inactive Only') }}</option>
            </select>
        </div>
    </div>

    <!-- Coupons Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-3xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200/60 dark:border-slate-800 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                        <th class="py-3.5 px-4 sm:px-6">{{ __('Coupon Code') }}</th>
                        <th class="py-3.5 px-4">{{ __('Discount') }}</th>
                        <th class="py-3.5 px-4">{{ __('Order Thresholds') }}</th>
                        <th class="py-3.5 px-4">{{ __('Usage') }}</th>
                        <th class="py-3.5 px-4">{{ __('Validity') }}</th>
                        <th class="py-3.5 px-4">{{ __('Status') }}</th>
                        <th class="py-3.5 px-4 text-right pr-6">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    @forelse ($coupons as $coupon)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3.5 px-4 sm:px-6">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 font-mono font-black text-xs">
                                        {{ $coupon->code }}
                                    </span>
                                </div>
                                @if ($coupon->description)
                                    <p class="text-[11px] text-slate-400 mt-0.5 max-w-xs truncate">{{ $coupon->description }}</p>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-800 dark:text-slate-200">
                                @if ($coupon->discount_type === 'percentage')
                                    <span class="text-emerald-600 font-black">{{ (float)$coupon->discount_value }}% OFF</span>
                                    @if ($coupon->max_discount_amount)
                                        <div class="text-[10px] text-slate-400">{{ __('Max: $:amt', ['amt' => number_format($coupon->max_discount_amount, 2)]) }}</div>
                                    @endif
                                @else
                                    <span class="text-emerald-600 font-black">${{ number_format($coupon->discount_value, 2) }} OFF</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                                <div>{{ __('Min Order:') }} <span class="font-bold">${{ number_format($coupon->min_order_amount, 2) }}</span></div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-mono font-bold text-slate-800 dark:text-slate-100">
                                    {{ $coupon->used_count }} / {{ $coupon->usage_limit_total ?: '∞' }}
                                </div>
                                <div class="text-[10px] text-slate-400">{{ __('Limit/Cust: :count', ['count' => $coupon->usage_limit_per_customer]) }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 text-[11px]">
                                @if ($coupon->starts_at || $coupon->expires_at)
                                    <div>{{ $coupon->starts_at ? $coupon->starts_at->format('M d') : 'Anytime' }} &rarr; {{ $coupon->expires_at ? $coupon->expires_at->format('M d, Y') : 'Ongoing' }}</div>
                                @else
                                    <span class="text-slate-400">{{ __('Always valid') }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <button type="button"
                                        wire:click="toggleStatus({{ $coupon->id }})"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-black transition cursor-pointer {{ $coupon->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                    {{ $coupon->is_active ? __('Active') : __('Inactive') }}
                                </button>
                            </td>
                            <td class="py-3.5 px-4 text-right pr-6">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button"
                                            wire:click="openEditModal({{ $coupon->id }})"
                                            class="p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 text-blue-600 transition"
                                            title="{{ __('Edit Coupon') }}">
                                        ✏️
                                    </button>
                                    <button type="button"
                                            wire:click="deleteCoupon({{ $coupon->id }})"
                                            wire:confirm="{{ __('Are you sure you want to delete this coupon code?') }}"
                                            class="p-1.5 rounded-xl hover:bg-rose-50 dark:hover:bg-rose-950/40 text-rose-500 transition"
                                            title="{{ __('Delete') }}">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 space-y-2">
                                <div class="text-3xl">🎟️</div>
                                <div class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ __('No coupons found') }}</div>
                                <p class="text-xs">{{ __('Click "Create New Coupon" to set up promo codes for your online storefront.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($coupons->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800">
                {{ $coupons->links() }}
            </div>
        @endif
    </div>

    <!-- Create / Edit Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="w-full max-w-xl bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden p-6 space-y-5 animate-in fade-in max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-black text-slate-900 dark:text-white">
                        {{ $editingCouponId ? __('Edit Coupon Code') : __('Create New Coupon Code') }}
                    </h3>
                    <button type="button" wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form wire:submit.prevent="saveCoupon" class="space-y-4 text-xs">
                    <!-- Code & Type -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Coupon Code *') }}</label>
                            <input type="text"
                                   wire:model="code"
                                   placeholder="e.g. SUMMER20"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-mono font-bold uppercase focus:ring-2 focus:ring-emerald-500">
                            @error('code') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Discount Type *') }}</label>
                            <select wire:model.live="discount_type" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-bold focus:ring-2 focus:ring-emerald-500">
                                <option value="percentage">{{ __('Percentage Discount (%)') }}</option>
                                <option value="fixed_amount">{{ __('Fixed Amount Discount ($)') }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Discount Value & Max Cap -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">
                                {{ $discount_type === 'percentage' ? __('Discount Percentage (%) *') : __('Fixed Discount Amount ($) *') }}
                            </label>
                            <input type="number"
                                   step="0.01"
                                   wire:model="discount_value"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-mono font-bold focus:ring-2 focus:ring-emerald-500">
                            @error('discount_value') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                        </div>

                        @if ($discount_type === 'percentage')
                            <div>
                                <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Max Discount Cap ($) (Optional)') }}</label>
                                <input type="number"
                                       step="0.01"
                                       wire:model="max_discount_amount"
                                       placeholder="e.g. 50.00"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-mono font-bold focus:ring-2 focus:ring-emerald-500">
                            </div>
                        @else
                            <div>
                                <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Min Order Amount ($)') }}</label>
                                <input type="number"
                                       step="0.01"
                                       wire:model="min_order_amount"
                                       placeholder="0.00"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-mono font-bold focus:ring-2 focus:ring-emerald-500">
                            </div>
                        @endif
                    </div>

                    @if ($discount_type === 'percentage')
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Min Order Amount ($)') }}</label>
                            <input type="number"
                                   step="0.01"
                                   wire:model="min_order_amount"
                                   placeholder="0.00"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-mono font-bold focus:ring-2 focus:ring-emerald-500">
                        </div>
                    @endif

                    <!-- Limits -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Total Redemptions Limit (Optional)') }}</label>
                            <input type="number"
                                   wire:model="usage_limit_total"
                                   placeholder="{{ __('Leave empty for unlimited') }}"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-mono font-bold focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Redemption Limit Per Customer') }}</label>
                            <input type="number"
                                   wire:model="usage_limit_per_customer"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 font-mono font-bold focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Starts At (Optional)') }}</label>
                            <input type="datetime-local"
                                   wire:model="starts_at"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Expires At (Optional)') }}</label>
                            <input type="datetime-local"
                                   wire:model="expires_at"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">{{ __('Description / Promotional Note') }}</label>
                        <textarea wire:model="description"
                                  rows="2"
                                  placeholder="e.g. 15% off on all summer electronics for new customers"
                                  class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium focus:ring-2 focus:ring-emerald-500"></textarea>
                    </div>

                    <!-- Is Active toggle -->
                    <div class="flex items-center gap-3 pt-1">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="sr-only peer">
                            <div class="w-10 h-6 bg-slate-200 peer-focus:outline-hidden rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                        <span class="font-bold text-slate-700 dark:text-slate-200">{{ __('Coupon is active and redeemable') }}</span>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                        <button type="button"
                                wire:click="$set('showModal', false)"
                                class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold transition">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit"
                                class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black shadow-md shadow-emerald-600/20 transition active:scale-95 cursor-pointer">
                            {{ $editingCouponId ? __('Update Coupon') : __('Create Coupon') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
