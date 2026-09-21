<div class="space-y-6 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6" 
     x-data="{
         sortableInstance: null,
         morphUnhook: null,
         dragging: false,
         initSortable() {
             if (typeof Sortable === 'undefined' || this.dragging || Sortable.active) return;
             const container = this.$root.querySelector('#sortable-menu-container');
             if (!container || !container.isConnected) return;

             const registered = Sortable.get(container);
             if (registered) {
                 this.sortableInstance = registered;
                 return;
             }

             if (this.sortableInstance && this.sortableInstance.el !== container) {
                 try { this.sortableInstance.destroy(); } catch(e) {}
             }
             this.sortableInstance = Sortable.create(container, {
                 animation: 200,
                 handle: '.menu-drag-item',
                 ghostClass: 'opacity-30',
                 chosenClass: 'scale-[1.01]',
                 dragClass: 'shadow-2xl',
                 onStart: () => { this.dragging = true; },
                 onEnd: () => {
                     setTimeout(() => {
                         this.dragging = false;
                         const orderedIds = Array.from(container.querySelectorAll('.menu-drag-item'))
                             .map(el => parseInt(el.getAttribute('data-id')))
                             .filter(id => !isNaN(id));
                         if (orderedIds.length > 0) {
                             this.$wire.updateMenuOrder(orderedIds);
                         }
                     }, 0);
                 }
             });
         },
         destroy() {
             if (this.morphUnhook) {
                 this.morphUnhook();
                 this.morphUnhook = null;
             }

             const instance = this.sortableInstance;
             this.sortableInstance = null;
             const destroyWhenIdle = () => {
                 if (typeof Sortable !== 'undefined' && Sortable.active) {
                     setTimeout(destroyWhenIdle, 50);
                     return;
                 }
                 try { if (instance && instance.el) instance.destroy(); } catch(e) {}
             };
             destroyWhenIdle();
         }
     }"
     x-init="
         $nextTick(() => initSortable());
         morphUnhook = $wire.$hook('morphed', () => $nextTick(() => initSortable()));
     ">
    
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <span>🧭</span> Storefront Navigation Menus & CMS Pages
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Customize header navigation menus, footer link columns, category jumps, and dynamic custom CMS pages for your online storefront.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" wire:click="openCreatePageModal" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition cursor-pointer">
                <span>➕</span> Create New CMS Page
            </button>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 text-xs font-bold border border-emerald-200 dark:border-emerald-800 shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Live Synced
            </span>
        </div>
    </div>

    <!-- Master Two-Column Flex Layout -->
    <div class="flex flex-col lg:flex-row gap-6 items-start w-full">
        
        <!-- Left Column: Source Selectors -->
        <div class="w-full lg:w-[360px] xl:w-[400px] shrink-0 space-y-4">
            
            <!-- 1. Custom CMS Pages -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm" x-data="{ open: true }">
                <div class="flex items-center justify-between w-full">
                    <button type="button" @click="open = !open" class="flex items-center gap-2 font-bold text-xs uppercase tracking-wider text-slate-700 dark:text-slate-300 cursor-pointer">
                        <span>📄 Custom CMS Pages</span>
                        <span x-text="open ? '−' : '+'" class="text-base font-bold"></span>
                    </button>
                    <button type="button" wire:click="openCreatePageModal" class="text-[11px] text-blue-600 dark:text-blue-400 font-bold hover:underline cursor-pointer">
                        + New
                    </button>
                </div>
                <div x-show="open" class="mt-3 space-y-2.5 pt-3 border-t border-slate-100 dark:border-slate-800 max-h-64 overflow-y-auto">
                    @forelse($availablePages as $page)
                        <div class="flex items-center justify-between gap-2 group">
                            <label class="flex items-center gap-2.5 text-xs text-slate-600 dark:text-slate-300 hover:text-blue-600 cursor-pointer select-none min-w-0 flex-1">
                                <input type="checkbox" wire:model="selectedPages" value="{{ $page->id }}" class="rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-0">
                                <span class="font-medium truncate">{{ $page->title }}</span>
                                <span class="text-[10px] text-slate-400 font-mono">(/page/{{ $page->slug }})</span>
                            </label>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                                <button type="button" wire:click="openEditPageModal({{ $page->id }})" class="p-1 text-slate-400 hover:text-blue-600" title="Edit Page">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button type="button" wire:click="deletePage({{ $page->id }})" class="p-1 text-slate-400 hover:text-rose-600" title="Delete Page" onclick="return confirm('Delete this custom page?') || event.stopImmediatePropagation()">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-[11px] text-slate-400 py-2">No custom CMS pages found.</div>
                    @endforelse
                    
                    @if(count($availablePages) > 0)
                        <button type="button" wire:click="addSelectedPages" class="w-full mt-3 py-2 px-3 bg-blue-50 dark:bg-slate-800 hover:bg-blue-100 dark:hover:bg-slate-700 text-blue-600 dark:text-blue-400 rounded-xl text-xs font-bold transition cursor-pointer">
                            + Add Selected Pages to Menu
                        </button>
                    @endif
                </div>
            </div>

            <!-- 2. Store Categories -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm" x-data="{ open: true }">
                <button type="button" @click="open = !open" class="flex items-center justify-between w-full font-bold text-xs uppercase tracking-wider text-slate-700 dark:text-slate-300 cursor-pointer">
                    <span class="flex items-center gap-2">🏷️ Store Categories</span>
                    <span x-text="open ? '−' : '+'" class="text-base font-bold"></span>
                </button>
                <div x-show="open" class="mt-3 space-y-2.5 pt-3 border-t border-slate-100 dark:border-slate-800 max-h-64 overflow-y-auto">
                    @forelse($availableCategories as $cat)
                        <label class="flex items-center gap-2.5 text-xs text-slate-600 dark:text-slate-300 hover:text-blue-600 cursor-pointer select-none">
                            <input type="checkbox" wire:model="selectedCategories" value="{{ $cat->id }}" class="rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-0">
                            <span class="font-medium truncate">{{ $cat->name }}</span>
                        </label>
                    @empty
                        <div class="text-[11px] text-slate-400 py-2">No categories available.</div>
                    @endforelse
                    
                    @if(count($availableCategories) > 0)
                        <button type="button" wire:click="addSelectedCategories" class="w-full mt-3 py-2 px-3 bg-blue-50 dark:bg-slate-800 hover:bg-blue-100 dark:hover:bg-slate-700 text-blue-600 dark:text-blue-400 rounded-xl text-xs font-bold transition cursor-pointer">
                            + Add Selected Categories to Menu
                        </button>
                    @endif
                </div>
            </div>

            <!-- 3. Section Jump Anchors (#) -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm" x-data="{ open: true }">
                <button type="button" @click="open = !open" class="flex items-center justify-between w-full font-bold text-xs uppercase tracking-wider text-slate-700 dark:text-slate-300 cursor-pointer">
                    <span class="flex items-center gap-2">⚓ Section Jump Anchors (#)</span>
                    <span x-text="open ? '−' : '+'" class="text-base font-bold"></span>
                </button>
                <div x-show="open" class="mt-3 space-y-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    @php
                        $storefrontAnchors = [
                            ['title' => 'Deals & Specials', 'url' => '#products-section'],
                            ['title' => "What's New", 'url' => '#products-section'],
                            ['title' => 'Services & Delivery', 'url' => '#services-section'],
                            ['title' => 'Customer Reviews', 'url' => '#reviews-section'],
                            ['title' => 'Store FAQs & Answers', 'url' => '#faq-section'],
                            ['title' => 'Contact Store', 'url' => '#contact-section'],
                        ];
                    @endphp
                    @foreach($storefrontAnchors as $anchor)
                        <div class="flex items-center justify-between py-1 text-xs text-slate-600 dark:text-slate-300">
                            <span>{{ $anchor['title'] }} <code class="text-[10px] text-blue-500 bg-blue-50 dark:bg-blue-900/30 px-1 py-0.5 rounded">{{ $anchor['url'] }}</code></span>
                            <button type="button" wire:click="addAnchorLink('{{ addslashes($anchor['title']) }}', '{{ $anchor['url'] }}')" class="text-xs text-blue-600 dark:text-blue-400 font-bold hover:underline cursor-pointer">
                                + Add
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 4. Custom / External Links -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-5 shadow-sm" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="flex items-center justify-between w-full font-bold text-xs uppercase tracking-wider text-slate-700 dark:text-slate-300 cursor-pointer">
                    <span class="flex items-center gap-2">🔗 Custom URL / External</span>
                    <span x-text="open ? '−' : '+'" class="text-base font-bold"></span>
                </button>
                <div x-show="open" class="mt-3 space-y-3 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Label / Title</label>
                        <input type="text" wire:model="customTitle" placeholder="e.g., Blog or Instagram" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs text-slate-900 dark:text-white">
                        @error('customTitle') <span class="text-[10px] text-rose-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Target URL</label>
                        <input type="text" wire:model="customUrl" placeholder="https://domain.com or /route" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs text-slate-900 dark:text-white font-mono">
                        @error('customUrl') <span class="text-[10px] text-rose-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="customTargetBlank" id="target_blank" class="rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-0">
                        <label for="target_blank" class="text-[11px] text-slate-500 dark:text-slate-400 cursor-pointer select-none">Open in new tab (<code>_blank</code>)</label>
                    </div>
                    <button type="button" wire:click="addCustomLink" class="w-full py-2 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition cursor-pointer shadow-sm">
                        + Add Custom Link to Menu
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Column: Canvas & Sortable Menu Tree -->
        <div class="w-full lg:flex-1 min-w-0 space-y-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-sm w-full">
                
                <!-- Location Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-5 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <span>📍</span> Active Menu Items
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">Re-order items using drag handles to update storefront layout.</p>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">Menu Placement:</label>
                        <select wire:model.live="activeLocation" class="px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-bold text-blue-600 dark:text-blue-400 cursor-pointer">
                            <option value="header_nav">Header Navigation</option>
                            <option value="footer_col_1">Footer: Department / Categories</option>
                            <option value="footer_col_2">Footer: Help & Support</option>
                            <option value="footer_col_3">Footer: Company & Legal</option>
                        </select>
                    </div>
                </div>

                <!-- Inline Edit Modal/Box (If item is selected for edit) -->
                @if($editingId)
                    <div class="my-4 p-4 rounded-2xl bg-blue-50/80 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/80 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-blue-900 dark:text-blue-200 flex items-center gap-1.5">
                                <span>✏️</span> Edit Menu Item
                            </span>
                            <button type="button" wire:click="cancelEdit" class="text-xs font-bold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                ✕ Cancel
                            </button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 mb-1">Label</label>
                                <input type="text" wire:model="editingTitle" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold">
                                @error('editingTitle') <span class="text-[10px] text-rose-500 font-bold block mt-0.5">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 mb-1">URL / Anchor</label>
                                <input type="text" wire:model="editingUrl" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-mono">
                                @error('editingUrl') <span class="text-[10px] text-rose-500 font-bold block mt-0.5">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 mb-1">Target</label>
                                <select wire:model="editingTarget" class="w-full px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold">
                                    <option value="_self">Same Tab (_self)</option>
                                    <option value="_blank">New Tab (_blank)</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" wire:click="cancelEdit" class="px-3 py-1.5 text-xs font-bold text-slate-500 hover:text-slate-700 dark:text-slate-400 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-800 transition">
                                Cancel
                            </button>
                            <button type="button" wire:click="saveEdit" class="px-4 py-1.5 text-xs font-black text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition shadow-xs">
                                Save Changes
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Sortable Item List -->
                <div id="sortable-menu-container" wire:ignore.self class="mt-5 space-y-3 min-h-[260px] w-full">
                    @forelse($menuItems as $item)
                        <div class="menu-drag-item group bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-2xl p-3.5 flex items-center justify-between transition hover:shadow-md cursor-grab active:cursor-grabbing w-full"
                             data-id="{{ $item->id }}"
                             wire:key="menu-item-{{ $item->id }}">
                            
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-slate-400 group-hover:text-blue-500 cursor-grab shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 flex-wrap">
                                        <span class="truncate">{{ $item->title }}</span>
                                        <span class="text-[9px] px-2 py-0.5 rounded-full font-bold uppercase 
                                            {{ $item->type === 'cms_page' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' : '' }}
                                            {{ $item->type === 'category' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : '' }}
                                            {{ $item->type === 'anchor' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : '' }}
                                            {{ $item->type === 'custom_url' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : '' }}">
                                            {{ str_replace('_', ' ', $item->type) }}
                                        </span>
                                        @if($item->target === '_blank')
                                            <span class="text-[9px] text-slate-400">↗ new tab</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono mt-0.5 truncate">{{ $item->target_url ?? $item->resolved_url }}</div>
                                </div>
                            </div>

                            <!-- Status & Delete Actions -->
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button" wire:click="startEdit({{ $item->id }})" class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/30 transition cursor-pointer" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button type="button" wire:click="toggleStatus({{ $item->id }})" 
                                        class="text-[11px] font-semibold px-2.5 py-1 rounded-lg transition cursor-pointer {{ $item->is_visible ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40' : 'bg-slate-200 text-slate-500 dark:bg-slate-700' }}">
                                    {{ $item->is_visible ? 'Visible' : 'Hidden' }}
                                </button>
                                <button type="button" wire:click="deleteItem({{ $item->id }})" class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition cursor-pointer" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center py-16 text-slate-400 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl text-xs w-full">
                            <span class="text-lg mb-1">🧭</span>
                            <span class="font-semibold text-slate-600 dark:text-slate-300">No navigation items found for this placement.</span>
                            <span class="text-[11px] text-slate-400 mt-1">Select CMS pages, categories, jump anchors, or custom URLs on the left to add items.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- CMS Page Create / Edit Modal -->
    @if($showPageModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-2xl w-full p-6 space-y-5" @click.away="$wire.closePageModal()">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>📄</span> {{ $editingPageId ? 'Edit Custom CMS Page' : 'Create New Custom CMS Page' }}
                    </h3>
                    <button type="button" wire:click="closePageModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg font-bold">
                        ✕
                    </button>
                </div>

                <div class="space-y-4 text-xs">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Page Title *</label>
                        <input type="text" wire:model="pageTitle" placeholder="e.g. Return & Refund Policy" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs text-slate-900 dark:text-white font-medium">
                        @error('pageTitle') <span class="text-[10px] text-rose-500 font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">URL Slug (optional, auto-generated from title)</label>
                        <div class="flex items-center">
                            <span class="px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-r-0 border-slate-200 dark:border-slate-700 rounded-l-xl text-slate-400 font-mono text-[11px]">/page/</span>
                            <input type="text" wire:model="pageSlug" placeholder="return-refund-policy" class="w-full px-3 py-2 rounded-r-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs text-slate-900 dark:text-white font-mono">
                        </div>
                        @error('pageSlug') <span class="text-[10px] text-rose-500 font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Page Content</label>
                        <textarea wire:model="pageContent" rows="8" placeholder="Write page content in HTML or plain text..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs text-slate-900 dark:text-white font-mono"></textarea>
                        @error('pageContent') <span class="text-[10px] text-rose-500 font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">SEO Meta Title</label>
                            <input type="text" wire:model="pageMetaTitle" placeholder="SEO Title" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs text-slate-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">SEO Meta Description</label>
                            <input type="text" wire:model="pageMetaDescription" placeholder="SEO Description summary" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs text-slate-900 dark:text-white">
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" wire:model="pageIsPublished" id="page_published" class="rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-0">
                        <label for="page_published" class="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                            Published & Accessible to Store Visitors
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" wire:click="closePageModal" class="px-4 py-2 text-xs font-bold text-slate-500 hover:text-slate-700 dark:text-slate-400 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        Cancel
                    </button>
                    <button type="button" wire:click="savePage" class="px-5 py-2 text-xs font-black text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition shadow-xs">
                        {{ $editingPageId ? 'Save Changes' : 'Create Page' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
