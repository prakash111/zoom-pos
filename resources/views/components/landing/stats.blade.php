@props([])

<div class="border-y border-white/10 bg-slate-950/80 backdrop-blur-xl relative overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-r from-emerald-500/5 via-lime-500/5 to-teal-500/5 pointer-events-none"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 relative z-10">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-8 sm:gap-6 text-center"
             x-data="{
                counted: false,
                animate(el, target, suffix) {
                    const duration = 1400;
                    const startTime = performance.now();
                    const step = (now) => {
                        const progress = Math.min((now - startTime) / duration, 1);
                        el.textContent = Math.floor(progress * target).toLocaleString() + suffix;
                        if (progress < 1) requestAnimationFrame(step);
                    };
                    requestAnimationFrame(step);
                }
             }"
             x-intersect.once="counted = true">
            <div class="p-4 rounded-2xl bg-white/5 border border-white/5">
                <div class="text-3xl sm:text-5xl font-black tracking-tight text-white"
                     x-init="$watch('counted', v => v && animate($el, 2500000, '+'))">0</div>
                <div class="w-8 h-1 rounded-full bg-brand-lime mx-auto mt-3 mb-2"></div>
                <div class="text-xs sm:text-sm text-slate-400 font-semibold">{{ __('Transactions Processed') }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white/5 border border-white/5">
                <div class="text-3xl sm:text-5xl font-black tracking-tight text-white"
                     x-init="$watch('counted', v => v && animate($el, 1200, '+'))">0</div>
                <div class="w-8 h-1 rounded-full bg-emerald-400 mx-auto mt-3 mb-2"></div>
                <div class="text-xs sm:text-sm text-slate-400 font-semibold">{{ __('Active Business Outlets') }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white/5 border border-white/5">
                <div class="text-3xl sm:text-5xl font-black tracking-tight text-white">99.99%</div>
                <div class="w-8 h-1 rounded-full bg-brand-lime mx-auto mt-3 mb-2"></div>
                <div class="text-xs sm:text-sm text-slate-400 font-semibold">{{ __('Platform Uptime SLA') }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white/5 border border-white/5">
                <div class="text-3xl sm:text-5xl font-black tracking-tight text-white">&lt; 20ms</div>
                <div class="w-8 h-1 rounded-full bg-emerald-400 mx-auto mt-3 mb-2"></div>
                <div class="text-xs sm:text-sm text-slate-400 font-semibold">{{ __('Auth & Checkout Latency') }}</div>
            </div>
        </div>
    </div>
</div>
