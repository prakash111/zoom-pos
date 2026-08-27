<!-- Horizontal Category Pill Scroller -->
<div class="pos-categories-container relative my-1 shrink-0">
    <div class="pos-categories-row flex items-center gap-2 overflow-x-auto py-1 px-0.5 no-scrollbar scrollbar-none snap-x snap-mandatory"
         style="-webkit-overflow-scrolling: touch; scrollbar-width: none;">
        
        <!-- 'All Items' Pill Button -->
        <button type="button"
                wire:click="selectCategory(null)"
                role="tab"
                aria-selected="{{ is_null($selectedCategoryId) ? 'true' : 'false' }}"
                class="pos-category-btn flex-shrink-0 snap-start px-4 py-2 rounded-full text-xs font-semibold transition-all duration-150 flex items-center gap-1.5 whitespace-nowrap cursor-pointer {{ is_null($selectedCategoryId) ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30 scale-100 ring-2 ring-blue-500/20 font-bold' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 active:scale-95 shadow-2xs border border-slate-200/60 dark:border-slate-700/60' }}">
            <span class="text-sm">✨</span>
            <span>{{ __("All Items") }}</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ is_null($selectedCategoryId) ? 'bg-blue-700 text-white font-bold' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 font-bold' }}">
                {{ $totalProductsCount }}
            </span>
        </button>

        <!-- Category Pill Button Component -->
        @foreach ($categories as $category)
            @php
                $categoryId = data_get($category, 'id');
                $categoryName = data_get($category, 'name');
                $categoryIcon = data_get($category, 'icon');
                $categoryProductsCount = data_get($category, 'products_count', 0);
            @endphp
            @continue(blank($categoryId) || blank($categoryName))
            <button type="button"
                    wire:click="selectCategory({{ (int) $categoryId }})"
                    role="tab"
                    aria-selected="{{ $selectedCategoryId === (int) $categoryId ? 'true' : 'false' }}"
                    class="pos-category-btn flex-shrink-0 snap-start px-4 py-2 rounded-full text-xs font-semibold transition-all duration-150 flex items-center gap-1.5 whitespace-nowrap cursor-pointer {{ $selectedCategoryId === (int) $categoryId ? 'bg-blue-600 text-white shadow-md shadow-blue-500/30 scale-100 ring-2 ring-blue-500/20 font-bold' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 active:scale-95 shadow-2xs border border-slate-200/60 dark:border-slate-700/60' }}">
                
                @if ($categoryIcon)
                    <span class="text-sm">{!! $categoryIcon !!}</span>
                @endif
                
                <span>{{ $categoryName }}</span>
                
                <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ $selectedCategoryId === (int) $categoryId ? 'bg-blue-700 text-white font-bold' : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 font-bold' }}">
                    {{ $categoryProductsCount }}
                </span>
            </button>
        @endforeach
    </div>
</div>
