<div class="space-y-6">
    <!-- Header with controls -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('tenant.settings') }}" class="text-sm text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
                    &larr; {{ __('Store Settings') }}
                </a>
                <span class="text-slate-300 dark:text-slate-600">/</span>
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ __('Product Ratings & Reviews') }}</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white mt-1 flex items-center gap-2">
                <span>⭐</span> {{ __('Product Ratings & Customer Reviews') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ __('Manage customer feedback, star ratings, and review visibility across your storefront.') }}
            </p>
        </div>

        <!-- Direct Store Setting Toggles -->
        <div class="flex flex-wrap items-center gap-3">
            <button type="button"
                    wire:click="toggleReviewsEnabled"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm cursor-pointer border {{ $enable_product_reviews ? 'bg-emerald-50 dark:bg-emerald-950/60 border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400' }}">
                <span class="w-2.5 h-2.5 rounded-full {{ $enable_product_reviews ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                <span>{{ $enable_product_reviews ? __('Ratings & Reviews: ENABLED') : __('Ratings & Reviews: DISABLED') }}</span>
            </button>

            <button type="button"
                    wire:click="toggleReviewApproval"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm cursor-pointer border {{ $require_review_approval ? 'bg-amber-50 dark:bg-amber-950/60 border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-300' : 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400' }}">
                <span>🛡️</span>
                <span>{{ $require_review_approval ? __('Moderation Required: ON') : __('Auto-Publish Reviews: ON') }}</span>
            </button>
        </div>
    </div>

    @if ($successMessage)
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-sm font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✓</span>
                <span>{{ $successMessage }}</span>
            </div>
            <button type="button" wire:click="$set('successMessage', null)" class="text-emerald-600 hover:text-emerald-800 cursor-pointer">&times;</button>
        </div>
    @endif

    <!-- Metric KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Total Reviews') }}</div>
            <div class="text-3xl font-black text-slate-900 dark:text-white mt-2">{{ $totalReviews }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ __('Submitted by customers') }}</div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-amber-500">{{ __('Pending Moderation') }}</div>
            <div class="text-3xl font-black text-amber-600 dark:text-amber-400 mt-2">{{ $pendingReviews }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ __('Awaiting approval') }}</div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-emerald-500">{{ __('Average Rating') }}</div>
            <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-2 flex items-center gap-1.5">
                <span>★</span>
                <span>{{ number_format($avgRating, 1) }}</span>
                <span class="text-xs font-normal text-slate-400">/ 5.0</span>
            </div>
            <div class="text-xs text-slate-500 mt-1">{{ __('Across all approved reviews') }}</div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-indigo-500">{{ __('Storefront Visibility') }}</div>
            <div class="text-xl font-bold mt-2 flex items-center gap-2 {{ $enable_product_reviews ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">
                <span>{{ $enable_product_reviews ? __('Active on Storefront') : __('Disabled') }}</span>
            </div>
            <div class="text-xs text-slate-500 mt-1">{{ __('Customer rating switch') }}</div>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col md:flex-row items-center gap-3">
        <div class="relative flex-1 w-full">
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('Search customer, product name, or review content...') }}"
                   class="w-full pl-9 pr-4 py-2 rounded-xl text-sm border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500">
            <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
        </div>

        <div class="flex items-center gap-2 w-full md:w-auto">
            <select wire:model.live="statusFilter"
                    class="px-3 py-2 rounded-xl text-xs font-semibold border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100">
                <option value="all">{{ __('All Statuses') }}</option>
                <option value="approved">{{ __('Approved Only') }}</option>
                <option value="pending">{{ __('Pending Approval') }}</option>
            </select>

            <select wire:model.live="ratingFilter"
                    class="px-3 py-2 rounded-xl text-xs font-semibold border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100">
                <option value="all">{{ __('All Star Ratings') }}</option>
                <option value="5">⭐⭐⭐⭐⭐ 5 Stars</option>
                <option value="4">⭐⭐⭐⭐ 4 Stars</option>
                <option value="3">⭐⭐⭐ 3 Stars</option>
                <option value="2">⭐⭐ 2 Stars</option>
                <option value="1">⭐ 1 Star</option>
            </select>
        </div>
    </div>

    <!-- Reviews Listing -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        @if ($reviews->isEmpty())
            <div class="py-16 text-center">
                <div class="text-4xl mb-3">💬</div>
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('No reviews found') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto mt-1">
                    {{ __('No customer reviews match your search filter. Customers can rate products directly on your storefront.') }}
                </p>
            </div>
        @else
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach ($reviews as $review)
                    <div class="p-5 flex flex-col md:flex-row md:items-start justify-between gap-4 hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition">
                        <div class="space-y-2 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-amber-500 font-bold text-sm">
                                    {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                                </span>
                                <span class="text-xs font-black text-slate-900 dark:text-white">{{ $review->rating }}.0</span>

                                @if ($review->is_verified_purchase)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300">
                                        ✓ {{ __('Verified Purchase') }}
                                    </span>
                                @endif

                                @if ($review->is_approved)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-blue-100 dark:bg-blue-950 text-blue-800 dark:text-blue-300">
                                        {{ __('Published') }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300">
                                        ⏳ {{ __('Pending Approval') }}
                                    </span>
                                @endif
                            </div>

                            @if ($review->title)
                                <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ $review->title }}</h4>
                            @endif

                            <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">{{ $review->comment }}</p>

                            <div class="flex items-center gap-3 text-[11px] text-slate-400">
                                <span><strong>{{ $review->customer_name }}</strong></span>
                                <span>•</span>
                                <span>{{ __('Product:') }} <strong class="text-slate-600 dark:text-slate-300">{{ $review->product?->name ?? 'Item #'.$review->product_id }}</strong></span>
                                <span>•</span>
                                <span>{{ $review->created_at->format('M d, Y h:i A') }}</span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2 shrink-0 self-end md:self-center">
                            @if (! $review->is_approved)
                                <button type="button"
                                        wire:click="approve({{ $review->id }})"
                                        class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-sm cursor-pointer">
                                    {{ __('Approve & Publish') }}
                                </button>
                            @else
                                <button type="button"
                                        wire:click="reject({{ $review->id }})"
                                        class="px-3 py-1.5 rounded-xl bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 text-xs font-semibold transition cursor-pointer">
                                    {{ __('Hide') }}
                                </button>
                            @endif

                            <button type="button"
                                    wire:click="delete({{ $review->id }})"
                                    wire:confirm="{{ __('Are you sure you want to permanently delete this review?') }}"
                                    class="px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 text-rose-600 hover:bg-rose-100 text-xs font-bold transition cursor-pointer">
                                {{ __('Delete') }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="p-4 border-t border-slate-100 dark:border-slate-700">
                {{ $reviews->links() }}
            </div>
        @endif
    </div>
</div>
