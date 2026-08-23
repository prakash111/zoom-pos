@props(['branding'])

<div id="about" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 scroll-mt-20">
    <div class="rounded-3xl bg-slate-900/80 backdrop-blur-xl border border-white/10 p-8 sm:p-14 text-center max-w-4xl mx-auto relative overflow-hidden shadow-2xl">
        <div class="absolute -top-16 -left-16 w-48 h-48 bg-teal-500/20 rounded-full blur-2xl"></div>
        <div class="absolute -bottom-16 -right-16 w-48 h-48 bg-brand-lime/20 rounded-full blur-2xl"></div>
        
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold uppercase tracking-wider mb-4">
            {{ __('Our Mission') }}
        </span>
        <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-white">{{ __('Built for high-velocity stores & modern commerce') }}</h2>
        <p class="mt-5 text-sm sm:text-base text-slate-300 leading-relaxed">
            {{ $branding->platform_name }} {{ __('exists to give retailers, restaurateurs, and growing enterprises modern point-of-sale and inventory infrastructure that keeps executing under peak pressure — from a single busy counter to nationwide multi-terminal operations. We architect every module to operate as one unified engine, so business leaders can accelerate growth without software friction.') }}
        </p>
    </div>
</div>
