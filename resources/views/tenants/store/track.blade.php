@extends('tenants.store.layout')

@section('title', __('Track Order') . ' #' . $sale->sale_number . ' — ' . ($company->name ?? 'Storefront'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6 py-4">

    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">
        <a href="{{ url('/') }}" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition flex items-center gap-1">
            <span>🏠</span> {{ __('Store Home') }}
        </a>
        <span>/</span>
        <span>{{ __('Order Tracking') }}</span>
        <span>/</span>
        <span class="text-slate-900 dark:text-white font-mono font-extrabold">{{ $sale->sale_number }}</span>
    </nav>

    <!-- Tracking Header Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800 space-y-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-6 border-b border-slate-100 dark:border-slate-800">
            <div class="space-y-1">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl">📦</span>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                        {{ __('Order') }} #{{ $sale->sale_number }}
                    </h1>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Placed on') }} <span class="font-bold text-slate-700 dark:text-slate-300">{{ $sale->created_at ? $sale->created_at->format('M d, Y — h:i A') : now()->format('M d, Y') }}</span>
                </p>
            </div>

            <!-- Tracking Code Pill with Copy -->
            <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 px-3.5 py-2 rounded-2xl">
                <div>
                    <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ __('Tracking Code') }}</div>
                    <div class="font-mono font-black text-sm text-emerald-600 dark:text-emerald-400 select-all">{{ $sale->tracking_code ?: $sale->sale_number }}</div>
                </div>
                <button type="button"
                        onclick="navigator.clipboard.writeText('{{ $sale->tracking_code ?: $sale->sale_number }}'); alert('{{ __('Tracking code copied to clipboard!') }}');"
                        class="p-1.5 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl text-slate-500 transition cursor-pointer"
                        title="{{ __('Copy tracking code') }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                </button>
            </div>
        </div>

        @php
            $trackingStatus = $sale->getTrackingStatus();
            $steps = [
                'Placed' => ['label' => __('Order Placed'), 'icon' => '📝', 'desc' => __('Order received by store')],
                'Confirmed' => ['label' => __('Confirmed'), 'icon' => '✓', 'desc' => __('Verified & accepted')],
                'Preparing' => ['label' => __('Preparing Items'), 'icon' => '🍳', 'desc' => __('Packing & quality check')],
                'Out for Delivery' => ['label' => __('Out for Delivery'), 'icon' => '🚚', 'desc' => __('On the way to your door')],
                'Delivered' => ['label' => __('Delivered'), 'icon' => '🎉', 'desc' => __('Handed over successfully')],
            ];

            $orderOfSteps = array_keys($steps);
            $currentIndex = array_search($trackingStatus, $orderOfSteps);
            if ($currentIndex === false && $trackingStatus !== 'Cancelled') {
                $currentIndex = 0;
            }
        @endphp

        @if ($trackingStatus === 'Cancelled')
            <div class="p-5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-800 dark:text-rose-200 flex items-center gap-3">
                <span class="text-2xl">🚫</span>
                <div>
                    <h4 class="font-extrabold text-sm">{{ __('Order Cancelled') }}</h4>
                    <p class="text-xs text-rose-700 dark:text-rose-300 mt-0.5">{{ __('This order was cancelled or refunded. Please contact our support team if you have any questions.') }}</p>
                </div>
            </div>
        @else
            <!-- Timeline Stepper -->
            <div class="py-4">
                <div class="grid grid-cols-1 sm:grid-cols-5 gap-4 relative">
                    @foreach ($steps as $key => $stepData)
                        @php
                            $stepIndex = array_search($key, $orderOfSteps);
                            $isCompleted = $currentIndex !== false && $stepIndex <= $currentIndex;
                            $isCurrent = $currentIndex !== false && $stepIndex === $currentIndex;
                        @endphp
                        <div class="flex sm:flex-col items-center sm:items-center text-left sm:text-center gap-3 sm:gap-2 relative z-10">
                            <!-- Indicator Circle -->
                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all shrink-0
                                {{ $isCurrent ? 'bg-emerald-600 text-white ring-4 ring-emerald-500/30 shadow-lg shadow-emerald-500/30' : ($isCompleted ? 'bg-emerald-500 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 border border-slate-200 dark:border-slate-700') }}">
                                @if ($isCompleted && !$isCurrent)
                                    ✓
                                @else
                                    <span>{{ $stepData['icon'] }}</span>
                                @endif
                            </div>

                            <div class="space-y-0.5 min-w-0">
                                <h4 class="text-xs font-black {{ $isCompleted ? 'text-slate-900 dark:text-white' : 'text-slate-400' }}">
                                    {{ $stepData['label'] }}
                                </h4>
                                <p class="text-[10px] text-slate-400 dark:text-slate-500 leading-tight hidden sm:block">
                                    {{ $stepData['desc'] }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Order Details Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Left 2 Cols: Items Purchased -->
        <div class="md:col-span-2 bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-200/80 dark:border-slate-800 space-y-4">
            <h3 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                <span>🛒</span> {{ __('Items Ordered') }}
            </h3>

            @php
                $orderItems = ($sale->saleItems && $sale->saleItems->isNotEmpty())
                    ? $sale->saleItems
                    : (is_array($sale->items) ? $sale->items : (json_decode($sale->items ?? '[]', true) ?: []));
            @endphp

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($orderItems as $item)
                    @php
                        $itemName = is_array($item) ? ($item['name'] ?? $item['product_name'] ?? 'Item') : ($item->name ?? $item->product?->name ?? 'Item');
                        $itemQty = is_array($item) ? ($item['quantity'] ?? $item['qty'] ?? 1) : ($item->quantity ?? 1);
                        $itemPrice = is_array($item) ? ($item['unit_price'] ?? $item['price'] ?? 0) : ($item->unit_price ?? $item->price ?? 0);
                        $itemTotal = is_array($item) ? ($item['total'] ?? ($itemQty * $itemPrice)) : ($item->total ?? ($itemQty * $itemPrice));
                    @endphp
                    <div class="py-3 flex items-center justify-between gap-4">
                        <div class="space-y-0.5 min-w-0">
                            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate">
                                {{ $itemName }}
                            </h4>
                            <p class="text-[11px] text-slate-400">
                                {{ (float)$itemQty }} &times; ${{ number_format((float)$itemPrice, 2) }}
                            </p>
                        </div>
                        <span class="font-mono font-extrabold text-xs text-slate-900 dark:text-white shrink-0">
                            ${{ number_format((float)$itemTotal, 2) }}
                        </span>
                    </div>
                @empty
                    <div class="py-4 text-center text-xs text-slate-400">
                        {{ __('No item details available.') }}
                    </div>
                @endforelse
            </div>

            <!-- Financial Breakdown -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-1.5 text-xs">
                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                    <span>{{ __('Subtotal') }}</span>
                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200">${{ number_format((float)$sale->subtotal, 2) }}</span>
                </div>

                @if ((float)$sale->discount_amount > 0)
                    <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                        <span>{{ __('Discount') }}</span>
                        <span class="font-mono font-bold">-${{ number_format((float)$sale->discount_amount, 2) }}</span>
                    </div>
                @endif

                @if ((float)$sale->tax_amount > 0)
                    <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                        <span>{{ __('Taxes') }}</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200">${{ number_format((float)$sale->tax_amount, 2) }}</span>
                    </div>
                @endif

                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                    <span>{{ __('Delivery Fee') }}</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">{{ __('FREE') }}</span>
                </div>

                <div class="flex items-center justify-between text-sm font-black text-slate-900 dark:text-white pt-2 border-t border-slate-100 dark:border-slate-800">
                    <span>{{ __('Total Paid / Due') }}</span>
                    <span class="text-base font-mono text-emerald-600 dark:text-emerald-400 font-black">${{ number_format((float)$sale->total_amount, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Right 1 Col: Shipping & Payment Details -->
        <div class="space-y-6">

            <!-- Shipping Destination Card -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800 space-y-3">
                <h3 class="text-xs font-extrabold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                    <span>📍</span> {{ __('Delivery Destination') }}
                </h3>
                <div class="text-xs text-slate-600 dark:text-slate-300 space-y-1">
                    @if ($sale->customer_name)
                        <div class="font-bold text-slate-900 dark:text-white">{{ $sale->customer_name }}</div>
                    @endif
                    @if ($sale->customer_phone)
                        <div>📞 {{ $sale->customer_phone }}</div>
                    @endif
                    @if ($sale->delivery_address)
                        <div>🏠 {{ $sale->delivery_address }}</div>
                    @endif
                    @if ($sale->delivery_city)
                        <div>🏙️ {{ $sale->delivery_city }}</div>
                    @endif
                </div>
            </div>

            <!-- Payment Details Card -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800 space-y-3">
                <h3 class="text-xs font-extrabold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                    <span>💳</span> {{ __('Payment Method') }}
                </h3>
                <div class="text-xs space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">{{ __('Method:') }}</span>
                        <span class="font-extrabold uppercase text-slate-800 dark:text-slate-200">{{ strtoupper($sale->payment_method ?: 'COD') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400">{{ __('Payment Status:') }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase {{ in_array($sale->payment_status, ['paid', 'completed']) ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' }}">
                            {{ $sale->payment_status ?: __('Pending') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Help / WhatsApp Query Card -->
            <div class="bg-emerald-950/40 text-emerald-100 rounded-3xl p-6 border border-emerald-800/40 space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-emerald-300 flex items-center gap-1.5">
                    <span>💬</span> {{ __('Questions About This Order?') }}
                </h3>
                <p class="text-xs text-emerald-200/80 leading-relaxed">
                    {{ __('Chat with our support dispatch team on WhatsApp for real-time status updates.') }}
                </p>

                @php
                    $waMsg = urlencode("Hello, I am tracking my order #{$sale->sale_number} ({$sale->tracking_code}) and would like an update.");
                    $phoneSanitized = preg_replace('/[^0-9]/', '', $company->phone ?? '');
                    $waUrl = $phoneSanitized ? "https://wa.me/{$phoneSanitized}?text={$waMsg}" : "https://api.whatsapp.com/send?text={$waMsg}";
                @endphp
                <a href="{{ $waUrl }}" target="_blank"
                   class="w-full py-2.5 px-4 rounded-xl bg-[#25D366] hover:bg-[#1EBE5D] text-white font-extrabold text-xs flex items-center justify-center gap-2 shadow-sm transition">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.158.57 4.184 1.564 5.941l-1.657 6.059 6.223-1.632c1.705.932 3.654 1.465 5.73 1.465 6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/>
                    </svg>
                    <span>{{ __('Ask on WhatsApp') }}</span>
                </a>
            </div>

        </div>
    </div>
</div>
@endsection
