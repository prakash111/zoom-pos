@props(['branding' => null, 'contactSettings' => null, 'fields' => null])

@php
    $branding = $branding ?? \App\Models\PlatformBranding::current();
    $contactSettings = $contactSettings ?? \App\Services\ContactFormService::getSettings();
    $fields = $fields ?? \App\Services\ContactFormService::getFields();

    $rawBadge = $branding->getSectionBadge('contact', __('CONTACT US'));
    $badge = ($rawBadge === 'Get In Touch' || empty($rawBadge)) ? __('CONTACT US') : $rawBadge;

    // Title handling: default to 2-line split (Blue "Get In Touch" / Slate "With Our Team") matching reference design,
    // or render customized title configured in Superadmin Studio
    $rawTitle = $branding->getSectionTitle('contact', 'Get In Touch With Our Team');
    if (in_array($rawTitle, ['Get In Touch With Our Team', 'Speak with an Omnichannel POS Specialist', ''], true)) {
        $titleLine1 = __('Get In Touch');
        $titleLine2 = __('With Our Team');
    } elseif (str_contains($rawTitle, "\n")) {
        $parts = explode("\n", $rawTitle, 2);
        $titleLine1 = $parts[0];
        $titleLine2 = $parts[1];
    } else {
        $titleLine1 = $rawTitle;
        $titleLine2 = '';
    }

    $rawSubtitle = $branding->getSectionSubtitle('contact', __("Fill out the form below and our team will get back to you within 1-2 business days."));
    if (str_contains($rawSubtitle, 'multi-location retail, restaurant chains') || empty($rawSubtitle)) {
        $subtitle = __("Fill out the form below and our team will get back to you within 1-2 business days.");
    } else {
        $subtitle = $rawSubtitle;
    }

    // Information cards values (customizable via settings or branding)
    $headOffice = setting('contact_office_address', $branding->address ?: 'Metrotech Center, NY 11201');
    $callCenter = setting('contact_call_center', $branding->support_phone ?: '+1 4995 4919 4004');
    $email = setting('contact_email', $branding->support_email ?: 'hello@moniveo.com');
    $workingHours = setting('contact_working_hours', 'Monday - Friday (07 am - 05 pm)');
@endphp

<!-- Contact Section (Light / Dark Pattern Synchronized) -->
<section id="contact" class="landing-sec-contact scroll-mt-20 py-16 sm:py-24 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-start">
            
            <!-- Left Column: Heading, Subtitle & 2x2 Info Cards -->
            <div class="lg:col-span-5 xl:col-span-5 flex flex-col justify-between h-full">
                <div>
                    <!-- Badge: Pill with Phone Icon -->
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-50 dark:bg-blue-500/10 border border-blue-200/80 dark:border-blue-500/20 text-blue-600 dark:text-blue-400 text-xs font-bold uppercase tracking-wider mb-5">
                        <svg class="w-3.5 h-3.5 shrink-0 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        <span>{{ $badge }}</span>
                    </div>

                    <!-- 2-Line Headline -->
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight leading-tight mb-4">
                        <span class="block text-blue-600 dark:text-blue-500">{{ $titleLine1 }}</span>
                        <span class="block text-slate-900 dark:text-white">{{ $titleLine2 }}</span>
                    </h2>

                    <!-- Subtitle -->
                    <p class="text-sm sm:text-base text-slate-600 dark:text-slate-400 leading-relaxed max-w-lg mb-8">
                        {{ $subtitle }}
                    </p>
                </div>

                <!-- 2x2 Info Cards Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <!-- 1. Head Office -->
                    <div class="contact-info-card p-4 sm:p-5 rounded-2xl bg-white dark:bg-[#101726] border border-slate-200/80 dark:border-slate-800 shadow-sm transition-all duration-200">
                        <div class="flex items-center gap-2.5 text-slate-900 dark:text-white font-bold text-sm sm:text-base">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <span>{{ __('Head Office') }}</span>
                        </div>
                        <p class="mt-1.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-normal leading-snug">
                            {{ $headOffice }}
                        </p>
                    </div>

                    <!-- 2. Call Center -->
                    <div class="contact-info-card p-4 sm:p-5 rounded-2xl bg-white dark:bg-[#101726] border border-slate-200/80 dark:border-slate-800 shadow-sm transition-all duration-200">
                        <div class="flex items-center gap-2.5 text-slate-900 dark:text-white font-bold text-sm sm:text-base">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <span>{{ __('Call Center') }}</span>
                        </div>
                        <p class="mt-1.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-normal leading-snug">
                            {{ $callCenter }}
                        </p>
                    </div>

                    <!-- 3. Email -->
                    <div class="contact-info-card p-4 sm:p-5 rounded-2xl bg-white dark:bg-[#101726] border border-slate-200/80 dark:border-slate-800 shadow-sm transition-all duration-200">
                        <div class="flex items-center gap-2.5 text-slate-900 dark:text-white font-bold text-sm sm:text-base">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span>{{ __('Email') }}</span>
                        </div>
                        <p class="mt-1.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-normal leading-snug truncate">
                            {{ $email }}
                        </p>
                    </div>

                    <!-- 4. Working Hours -->
                    <div class="contact-info-card p-4 sm:p-5 rounded-2xl bg-white dark:bg-[#101726] border border-slate-200/80 dark:border-slate-800 shadow-sm transition-all duration-200">
                        <div class="flex items-center gap-2.5 text-slate-900 dark:text-white font-bold text-sm sm:text-base">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>{{ __('Working Hours') }}</span>
                        </div>
                        <p class="mt-1.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400 font-normal leading-snug">
                            {{ $workingHours }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right Column: Contact Form Card with Top Blue Header Stripe -->
            <div class="lg:col-span-7 xl:col-span-7">
                <div class="contact-form-card rounded-3xl bg-white dark:bg-[#101726] border border-slate-200 dark:border-slate-800 shadow-xl shadow-slate-200/50 dark:shadow-black/40 overflow-hidden transition-all duration-300">
                    <!-- Top Blue Header Stripe (Matches reference design) -->
                    <div class="h-3 sm:h-3.5 bg-gradient-to-r from-blue-600 via-blue-500 to-indigo-600 w-full"></div>
                    
                    <div class="p-6 sm:p-8 lg:p-10">
                        <x-landing.contact-form :fields="$fields" :settings="$contactSettings" />
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Scoped Style Guarantees for Contact Us Section across Light and Dark Themes -->
    <style>
        .contact-info-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            color: #0f172a;
        }
        html.dark .contact-info-card, [data-theme="dark"] .contact-info-card {
            background-color: #101726 !important;
            border-color: #1e293b !important;
            color: #f8fafc !important;
        }
        .contact-phone-wrapper .contact-form-input {
            border: 0 !important;
            background-color: transparent !important;
        }
        html.dark .contact-phone-wrapper .contact-form-input, [data-theme="dark"] .contact-phone-wrapper .contact-form-input {
            background-color: transparent !important;
        }
    </style>
</section>
