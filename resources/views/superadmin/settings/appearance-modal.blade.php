<div class="space-y-3">
    <div class="flex items-center justify-between">
        <div>
            <h5 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                5. {{ __('Customize Super Admin Visible Dock Items (Pinning)') }}
            </h5>
            <p class="text-[11px] text-slate-400">
                {{ __('Select the global control center tools pinned to the master administration dock.') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="typeof selectAllAdminItems === 'function' ? selectAllAdminItems() : selectAllItems()" class="text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">{{ __('Select All') }}</button>
            <span class="text-slate-300 dark:text-slate-700">|</span>
            <button type="button" @click="typeof resetAdminDefaultItems === 'function' ? resetAdminDefaultItems() : selectDefaultItems()" class="text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:underline cursor-pointer">{{ __('Default (Essential)') }}</button>
            <span class="text-slate-300 dark:text-slate-700">|</span>
            <button type="button" @click="typeof uncheckAllItems === 'function' ? uncheckAllItems() : null" class="text-[11px] font-bold text-rose-500 dark:text-rose-400 hover:underline cursor-pointer">{{ __('Reset / Clear') }}</button>
        </div>
    </div>

    <!-- Admin Scoped Grid (3 Columns) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 pt-2">
        <template x-for="item in (typeof adminDockItems !== 'undefined' && Array.isArray(adminDockItems) ? adminDockItems : (availableDockItems || []))" :key="item.key">
            <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-blue-500/40 cursor-pointer transition">
                <div class="flex items-center gap-2.5">
                    <span x-text="item.icon" class="text-base"></span>
                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200" x-text="item.label"></span>
                </div>
                <input type="checkbox" 
                       :value="item.key" 
                       :checked="typeof isItemVisible === 'function' ? isItemVisible(item.key) : (typeof visibleAdminItems !== 'undefined' && visibleAdminItems.includes(item.key))"
                       @change="typeof toggleItem === 'function' ? toggleItem(item.key) : (typeof persistAdminDock === 'function' ? persistAdminDock() : null)"
                       class="rounded border-slate-300 text-blue-600 focus:ring-0 cursor-pointer">
            </label>
        </template>
    </div>
</div>
