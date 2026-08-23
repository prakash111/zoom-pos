@props([])

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
    <div class="relative overflow-hidden rounded-[2.5rem] sm:rounded-[3rem] bg-gradient-to-br from-[#0c5966] via-[#10707e] to-[#7dbf3d] px-8 sm:px-16 py-16 sm:py-20 text-center shadow-2xl border border-white/20">
        <!-- Glowing background orbs -->
        <div class="absolute -left-20 -top-20 w-80 h-80 rounded-full bg-teal-300/30 blur-3xl pointer-events-none"></div>
        <div class="absolute -right-20 -bottom-20 w-96 h-96 rounded-full bg-lime-300/35 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 max-w-2xl mx-auto">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-white/20 backdrop-blur-md text-white text-xs font-black uppercase tracking-wider mb-4 border border-white/20">
                {{ __('Instant Provisioning') }}
            </span>
            <h2 class="text-3xl sm:text-5xl font-black tracking-tight text-white leading-tight">
                {{ __('Ready to launch your modern card & commerce platform?') }}
            </h2>
            <p class="mt-4 text-sm sm:text-base text-white/90 leading-relaxed max-w-xl mx-auto">
                {{ __('Set up your business workspace, issue your first cards, and start processing orders across registers in minutes — no credit card required to start your free trial.') }}
            </p>
            <div class="mt-9 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('tenant.register') }}" class="w-full sm:w-auto px-8 py-4 rounded-full bg-slate-950 hover:bg-slate-900 text-white font-black text-sm shadow-2xl shadow-slate-950/40 transition active:scale-95 inline-flex items-center justify-center gap-2">
                    <span>{{ __('Create Your Workspace') }}</span>
                    <span>→</span>
                </a>
                <a href="#contact" class="w-full sm:w-auto px-8 py-4 rounded-full bg-white/20 hover:bg-white/30 backdrop-blur-md text-white font-bold text-sm transition border border-white/20">
                    {{ __('Talk to an Expert') }}
                </a>
            </div>
        </div>
    </div>
</div>
