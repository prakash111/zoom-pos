@props(['branding' => null])

@php
    $branding = $branding ?? \App\Models\PlatformBranding::current();
    $badge = $branding->getSectionBadge('faq', __('Answers & Clarity'));
    $title = $branding->getSectionTitle('faq', __('Frequently Asked Questions'));
    $subtitle = $branding->getSectionSubtitle('faq', __('Everything you need to know about scaling online sales, hardware setup, and offline POS reliability.'));
    $faqs = $branding->landingFaqs();
@endphp

@if (count($faqs))
    <section id="faq" class="landing-sec-faq py-20 sm:py-28 scroll-mt-20 transition-colors duration-300">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-bold uppercase tracking-wider mb-3">
                    {{ $badge }}
                </span>
                <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-slate-900 dark:text-white">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="mt-3 text-sm sm:text-base text-slate-600 dark:text-slate-400 max-w-xl mx-auto">{{ $subtitle }}</p>
                @endif
            </div>
            <div class="space-y-4">
                @foreach ($faqs as $faq)
                    <details class="group rounded-2xl bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 p-5 sm:p-6 transition-all shadow-sm">
                        <summary class="flex items-center justify-between gap-4 cursor-pointer list-none text-sm sm:text-base font-black text-slate-900 dark:text-white">
                            <span>{{ $faq['q'] ?? ($faq['question'] ?? '') }}</span>
                            <span class="shrink-0 text-slate-400 transition-transform group-open:rotate-45 text-xl leading-none">+</span>
                        </summary>
                        <p class="mt-3 text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed font-normal">{{ $faq['a'] ?? ($faq['answer'] ?? '') }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endif
