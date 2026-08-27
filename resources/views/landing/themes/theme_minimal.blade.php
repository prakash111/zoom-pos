@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', $branding->platform_name . ' — Quick Launch POS & Inventory Funnel')
@section('meta_description', 'Launch your store POS, inventory tracking, and sales checkout in under 60 seconds with ' . $branding->platform_name)

@section('content')
<div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between" x-data="{ annual: false }">

    <!-- Top Minimal Header -->
    <div class="max-w-6xl mx-auto w-full px-4 sm:px-6 pt-8 pb-4 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-amber-400 text-slate-950 flex items-center justify-center font-black text-base shadow-md shadow-amber-400/20">
                ⚡
            </div>
            <span class="text-lg font-black text-white tracking-tight">{{ $branding->platform_name }}</span>
        </a>

        <div class="flex items-center gap-4 text-xs font-bold">
            <a href="{{ route('tenant.login') }}" class="text-slate-400 hover:text-white transition">{{ __('Sign In') }}</a>
            <a href="{{ route('tenant.register') }}" class="px-4 py-2 rounded-full bg-white text-slate-950 hover:bg-slate-200 transition shadow">
                {{ __('Get Started') }}
            </a>
        </div>
    </div>

    <!-- Main Funnel Hero -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-12 sm:py-20 text-center space-y-8">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-400/10 border border-amber-400/20 text-amber-400 text-xs font-black uppercase tracking-wider">
            <span>⚡ {{ __('Instant 60-Second Setup') }}</span>
        </div>

        <h1 class="text-4xl sm:text-6xl font-black text-white tracking-tight leading-tight">
            {{ __('The Fastest Way to Ring Up Sales & Manage Store Inventory') }}
        </h1>

        <p class="text-base sm:text-lg text-slate-400 max-w-2xl mx-auto font-normal leading-relaxed">
            {{ __('No credit card required. No hardware purchase needed. Ring up orders, track stock levels, and issue thermal receipts from your computer, tablet, or phone.') }}
        </p>

        <!-- Direct Instant Registration Trigger Card -->
        <div class="max-w-md mx-auto p-3 rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl flex flex-col sm:flex-row gap-2">
            <a href="{{ route('tenant.register') }}"
               class="w-full py-3.5 px-6 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-sm transition text-center shadow-lg shadow-amber-400/20 active:scale-95 flex items-center justify-center gap-2">
                <span>🚀 {{ __('Create Free Store Workspace') }}</span>
                <span>→</span>
            </a>
        </div>

        <div class="flex items-center justify-center gap-6 text-xs text-slate-500 font-medium">
            <span class="flex items-center gap-1.5"><strong class="text-slate-300">✓</strong> {{ __('Instant Access') }}</span>
            <span class="flex items-center gap-1.5"><strong class="text-slate-300">✓</strong> {{ __('Zero Setup Fees') }}</span>
            <span class="flex items-center gap-1.5"><strong class="text-slate-300">✓</strong> {{ __('Works On Any Device') }}</span>
        </div>
    </div>

    <!-- 3 Key Speed Pillars -->
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-12 grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="p-6 rounded-3xl bg-slate-900/60 border border-slate-800 text-left space-y-2">
            <div class="text-2xl">⚡</div>
            <div class="text-sm font-black text-white">{{ __('Sub-Second Barcode Scan') }}</div>
            <p class="text-xs text-slate-400 leading-relaxed">{{ __('Add items to cart in milliseconds. Works with any standard USB or Bluetooth barcode scanner.') }}</p>
        </div>

        <div class="p-6 rounded-3xl bg-slate-900/60 border border-slate-800 text-left space-y-2">
            <div class="text-2xl">🖨️</div>
            <div class="text-sm font-black text-white">{{ __('One-Click Thermal Receipt') }}</div>
            <p class="text-xs text-slate-400 leading-relaxed">{{ __('Instant 80mm & 58mm receipts with automatic cash drawer kick and WhatsApp receipt dispatch.') }}</p>
        </div>

        <div class="p-6 rounded-3xl bg-slate-900/60 border border-slate-800 text-left space-y-2">
            <div class="text-2xl">💵</div>
            <div class="text-sm font-black text-white">{{ __('Daily Shift Reconcile') }}</div>
            <p class="text-xs text-slate-400 leading-relaxed">{{ __('Track cash float, drops, and blind counts with automated X & Z report summaries.') }}</p>
        </div>
    </div>

    <!-- Streamlined Pricing Section -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-12 text-center">
        <h3 class="text-2xl sm:text-3xl font-black text-white">{{ __('Simple, Transparent Tiers') }}</h3>
        
        <div class="flex items-center justify-center gap-3 my-6">
            <span class="text-xs font-bold" :class="!annual ? 'text-white' : 'text-slate-400'">{{ __('Monthly') }}</span>
            <button type="button" @click="annual = !annual"
                    :class="annual ? 'bg-amber-400' : 'bg-slate-800'"
                    class="relative w-12 h-6 rounded-full transition-colors p-0.5 cursor-pointer">
                <span class="block w-5 h-5 rounded-full bg-slate-950 shadow transition-transform"
                      :class="annual ? 'translate-x-6' : 'translate-x-0'"></span>
            </button>
            <span class="text-xs font-bold" :class="annual ? 'text-white' : 'text-slate-400'">{{ __('Annual (20% Off)') }}</span>
        </div>

        @if ($plans->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @foreach ($plans as $plan)
                    @php
                        $price = (float) $plan->price;
                        $annualPrice = round($price * 12 * 0.8);
                    @endphp
                    <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 flex flex-col justify-between text-left space-y-4">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wider text-amber-400">{{ $plan->display_name }}</div>
                            <div class="mt-2 text-2xl font-black text-white">
                                <span x-show="!annual">{{ $plan->currency }}{{ number_format($price, 2) }}</span>
                                <span x-show="annual" x-cloak>{{ $plan->currency }}{{ number_format($annualPrice, 2) }}</span>
                                <span class="text-xs font-normal text-slate-400" x-text="!annual ? '/{{ $plan->billing_cycle }}' : '/year'"></span>
                            </div>
                            <p class="text-xs text-slate-400 mt-2">{{ $plan->limits['usuarios'] ?? '∞' }} {{ __('Users') }} · {{ $plan->limits['dispositivos'] ?? '∞' }} {{ __('POS Devices') }}</p>
                        </div>
                        <a href="{{ route('tenant.register') }}?plan={{ $plan->name }}"
                           class="w-full py-2.5 rounded-xl bg-slate-800 hover:bg-amber-400 hover:text-slate-950 font-bold text-xs transition text-center text-white">
                            {{ __('Choose') }} {{ $plan->display_name }}
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Minimal Footer -->
    <div class="max-w-6xl mx-auto w-full px-4 sm:px-6 py-8 border-t border-slate-900 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
        <div>&copy; {{ now()->year }} {{ $branding->platform_name }}. {{ __('All rights reserved.') }}</div>
        <div class="flex items-center gap-4">
            <a href="{{ route('tenant.login') }}" class="hover:text-white transition">{{ __('Merchant Login') }}</a>
            <a href="{{ route('tenant.register') }}" class="hover:text-white transition">{{ __('Store Register') }}</a>
        </div>
    </div>

</div>
@endsection
