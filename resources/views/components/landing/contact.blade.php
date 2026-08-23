@props([])

<div id="contact" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 scroll-mt-20">
    <div class="max-w-3xl mx-auto rounded-3xl bg-slate-900/80 backdrop-blur-xl border border-white/10 p-8 sm:p-12 shadow-2xl relative overflow-hidden">
        <div class="absolute -top-20 -right-20 w-60 h-60 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="text-center mb-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-3">
                {{ __('Get In Touch') }}
            </span>
            <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-white">{{ __('Questions before you sign up?') }}</h2>
            <p class="mt-2 text-sm text-slate-400">{{ __("Send our enterprise POS & inventory solutions team a note and we'll reply within 24 hours.") }}</p>
        </div>
        <livewire:public.contact-form />
    </div>
</div>
