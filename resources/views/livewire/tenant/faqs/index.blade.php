<div class="space-y-6">

    <!-- Store Settings Subnav Navigation (Seamlessly integrated under Store Settings) -->
    <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
            <a href="{{ route('tenant.settings.index') }}" wire:navigate class="hover:text-blue-600">⚙️ {{ __('Store Settings') }}</a>
            <span>/</span>
            <span class="text-slate-900 dark:text-white font-extrabold">{{ __('Store FAQs & Help Center') }}</span>
        </div>
        <a href="{{ route('tenant.settings.index') }}" wire:navigate class="text-xs font-bold text-blue-600 hover:text-blue-700 flex items-center gap-1">
            <span>←</span> {{ __('Back to Settings Overview') }}
        </a>
    </div>

    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-emerald-900 via-teal-950 to-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-xl border border-emerald-900/50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">
        <div class="space-y-1 max-w-xl">
            <div class="flex items-center gap-2">
                <span class="w-10 h-10 rounded-2xl bg-emerald-500/20 text-emerald-300 font-black text-xl flex items-center justify-center">❓</span>
                <h1 class="text-xl sm:text-2xl font-black tracking-tight">{{ __('Store FAQs & Help Center') }}</h1>
            </div>
            <p class="text-xs sm:text-sm text-emerald-100/90 leading-relaxed">
                {{ __('Manage customer-facing frequently asked questions displayed on your online storefront. Help buyers quickly understand delivery terms, return guidelines, and payment options.') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
            @if ($totalFaqs === 0)
                <button type="button"
                        wire:click="seedDefaults"
                        class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-bold text-xs transition cursor-pointer">
                    ✨ {{ __('Initialize Recommended FAQs') }}
                </button>
            @endif

            <button type="button"
                    wire:click="openCreateModal"
                    class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs shadow-lg shadow-emerald-500/30 transition flex items-center gap-1.5 cursor-pointer">
                <span>+</span>
                <span>{{ __('Add New FAQ') }}</span>
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-xs font-bold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✓</span>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif
    @if (session()->has('info'))
        <div class="p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300 text-xs font-bold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>ℹ️</span>
                <span>{{ session('info') }}</span>
            </div>
        </div>
    @endif

    <!-- Filters & Search Bar -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
        <div class="relative w-full sm:w-80">
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Search questions, answers, categories...') }}"
                   class="w-full pl-9 pr-4 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                🔍
            </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
            <select wire:model.live="categoryFilter"
                    class="rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs py-2 px-3 text-slate-700 dark:text-slate-200 font-semibold">
                <option value="all">{{ __('All Categories') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- FAQ Cards List -->
    @if ($faqs->isEmpty())
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-12 text-center border border-slate-200/80 dark:border-slate-800 space-y-3">
            <span class="text-4xl block">❓</span>
            <h3 class="font-extrabold text-sm text-slate-800 dark:text-slate-200">{{ __('No store FAQs found') }}</h3>
            <p class="text-xs text-slate-400">{{ __('Get started by creating your first FAQ or initialize our recommended defaults.') }}</p>
            <div class="pt-2 flex justify-center gap-2">
                <button type="button" wire:click="openCreateModal" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs">
                    + {{ __('Add FAQ') }}
                </button>
                <button type="button" wire:click="seedDefaults" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs">
                    ✨ {{ __('Initialize Recommended') }}
                </button>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($faqs as $faq)
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 shadow-xs hover:shadow-md transition space-y-3 flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                📂 {{ $faq->category }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase {{ $faq->is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                {{ $faq->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </div>
                        <h4 class="font-extrabold text-sm text-slate-900 dark:text-white leading-snug">
                            {{ $faq->question }}
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-3 leading-relaxed">
                            {{ $faq->answer }}
                        </p>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                        <span class="text-[11px] text-slate-400 font-mono">{{ __('Order:') }} #{{ $faq->sort_order }}</span>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="toggleActive({{ $faq->id }})" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg text-slate-500 transition" title="{{ __('Toggle active') }}">
                                {{ $faq->is_active ? '👁️' : '🕶️' }}
                            </button>
                            <button type="button" wire:click="edit({{ $faq->id }})" class="p-1.5 hover:bg-blue-50 dark:hover:bg-blue-950/60 rounded-lg text-blue-600 transition" title="{{ __('Edit') }}">
                                ✏️
                            </button>
                            <button type="button"
                                    wire:click="delete({{ $faq->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this FAQ?') }}"
                                    class="p-1.5 hover:bg-rose-50 dark:hover:bg-rose-950/60 rounded-lg text-rose-600 transition"
                                    title="{{ __('Delete') }}">
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pt-2">
            {{ $faqs->links() }}
        </div>
    @endif

    <!-- Create / Edit Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 max-w-lg w-full border border-slate-200 dark:border-slate-800 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-black text-slate-900 dark:text-white">
                        {{ $editingFaqId ? __('Edit Store FAQ') : __('Add New Store FAQ') }}
                    </h3>
                    <button type="button" wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Category') }} *</label>
                        <input type="text"
                               wire:model="category"
                               placeholder="{{ __('e.g. Delivery, Payment, Returns, Orders, General') }}"
                               class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs py-2.5 px-3">
                        @error('category') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Question') }} *</label>
                        <input type="text"
                               wire:model="question"
                               placeholder="{{ __('e.g. How can I track my package in real time?') }}"
                               class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs py-2.5 px-3">
                        @error('question') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Answer') }} *</label>
                        <textarea wire:model="answer"
                                  rows="4"
                                  placeholder="{{ __('Provide clear, helpful instructions for customers...') }}"
                                  class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs py-2.5 px-3"></textarea>
                        @error('answer') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Sort Order') }}</label>
                            <input type="number"
                                   wire:model="sort_order"
                                   min="0"
                                   class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs py-2.5 px-3">
                        </div>
                        <div class="space-y-1 flex flex-col justify-end">
                            <label class="inline-flex items-center gap-2 cursor-pointer pb-2">
                                <input type="checkbox" wire:model="is_active" class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('Active on Store') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md">
                            {{ $editingFaqId ? __('Update FAQ') : __('Create FAQ') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
