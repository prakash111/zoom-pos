@extends('layouts.public', ['branding' => $branding, 'footerPages' => $footerPages])

@section('title', ($contactSettings['page_title'] ?: __('Get in Touch')) . ' — ' . $branding->platform_name)
@section('meta_description', $contactSettings['page_subtitle'] ?: __('Contact our cloud POS solutions team for onboarding, inquiries, and technical support.'))

@section('content')
<div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white transition-colors duration-300">
    
    <!-- Hero Header -->
    <div class="relative overflow-hidden pt-12 pb-16 lg:pt-16 lg:pb-24 border-b border-slate-200/80 dark:border-slate-800">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-indigo-500/10 via-transparent to-transparent"></div>
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-4">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-black uppercase tracking-wider">
                <span>💬 {{ __('Direct Support & Inquiries') }}</span>
            </div>

            <h1 class="text-3xl sm:text-5xl font-black tracking-tight text-slate-900 dark:text-white leading-tight">
                {{ $contactSettings['page_title'] ?: __('Get in Touch with Our Team') }}
            </h1>

            <p class="text-sm sm:text-base text-slate-600 dark:text-slate-400 max-w-2xl mx-auto leading-relaxed">
                {{ $contactSettings['page_subtitle'] ?: __('Questions before you sign up or need a tailored enterprise POS setup? Our specialists are ready to help.') }}
            </p>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">
            
            <!-- Left Column: Contact Channels & Info -->
            <div class="lg:col-span-5 space-y-6">
                <div class="p-6 sm:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl space-y-6">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>🏢</span> {{ __('Contact Channels') }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                            {{ __('Reach out directly via email, phone, or fill out the form for a fast reply within 24 hours.') }}
                        </p>
                    </div>

                    <div class="space-y-4 text-xs font-semibold">
                        @if (filled($branding->support_email))
                            <div class="flex items-start gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                                <span class="text-lg shrink-0">✉️</span>
                                <div class="truncate">
                                    <span class="text-[10px] uppercase font-black tracking-wider text-slate-400 block">{{ __('Support & Sales Email') }}</span>
                                    <a href="mailto:{{ $branding->support_email }}" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline truncate block">
                                        {{ $branding->support_email }}
                                    </a>
                                </div>
                            </div>
                        @endif

                        @if (filled($branding->support_phone))
                            <div class="flex items-start gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                                <span class="text-lg shrink-0">📞</span>
                                <div>
                                    <span class="text-[10px] uppercase font-black tracking-wider text-slate-400 block">{{ __('Phone & WhatsApp Support') }}</span>
                                    <a href="tel:{{ $branding->support_phone }}" class="text-emerald-600 dark:text-emerald-400 font-bold hover:underline block">
                                        {{ $branding->support_phone }}
                                    </a>
                                </div>
                            </div>
                        @endif

                        <div class="flex items-start gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                            <span class="text-lg shrink-0">⏱️</span>
                            <div>
                                <span class="text-[10px] uppercase font-black tracking-wider text-slate-400 block">{{ __('Operating Hours') }}</span>
                                <span class="text-slate-700 dark:text-slate-300 font-medium">Monday — Friday: 9:00 AM – 6:00 PM (EST)</span>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                            <span class="text-lg shrink-0">🚀</span>
                            <div>
                                <span class="text-[10px] uppercase font-black tracking-wider text-slate-400 block">{{ __('Instant Onboarding') }}</span>
                                <span class="text-slate-700 dark:text-slate-300 font-medium">{{ __('Ready to launch right away without waiting?') }}</span>
                                <a href="{{ route('tenant.register') }}" class="text-indigo-600 dark:text-indigo-400 font-black hover:underline block mt-0.5">
                                    {{ __('Create free store workspace →') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Response Promise Card -->
                <div class="p-6 rounded-3xl bg-gradient-to-br from-indigo-500/10 to-purple-500/10 border border-indigo-200 dark:border-indigo-800/40 text-xs text-slate-600 dark:text-slate-300 space-y-2">
                    <div class="flex items-center gap-2 font-black text-slate-900 dark:text-white">
                        <span>🛡️</span>
                        <span>{{ __('Enterprise SLA & Privacy') }}</span>
                    </div>
                    <p class="leading-relaxed">
                        {{ __('All inquiries submitted through this portal are directly reviewed by dedicated technical engineers. Your information is strictly confidential and protected.') }}
                    </p>
                </div>
            </div>

            <!-- Right Column: Dynamic Contact Form -->
            <div class="lg:col-span-7">
                <div class="p-6 sm:p-10 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl space-y-6">
                    <div>
                        <span class="text-xs font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                            {{ __('Send a Direct Inquiry') }}
                        </span>
                        <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white mt-1">
                            {{ __('How can we help your business grow?') }}
                        </h2>
                    </div>

                    <x-landing.contact-form :fields="$fields" :settings="$contactSettings" />
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
