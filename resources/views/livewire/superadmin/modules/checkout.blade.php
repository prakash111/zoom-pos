<div class="max-w-2xl mx-auto space-y-6">

    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-sm font-bold border border-emerald-200 dark:border-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 text-sm font-bold border border-rose-200 dark:border-rose-800">{{ session('error') }}</div>
    @endif

    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200 dark:border-slate-800 space-y-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-black text-slate-900 dark:text-white">{{ $module->name }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $module->description }}</p>
                <p class="text-[11px] text-slate-400 mt-1 font-mono">{{ $module->slug }} · v{{ $module->version ?? '1.0.0' }}</p>
            </div>
            <div class="text-right shrink-0">
                <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $currency }} {{ number_format($price, 2) }}</div>
                <div class="text-[11px] text-slate-400">{{ __('one-time') }}</div>
            </div>
        </div>

        @if ($owned)
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-sm font-bold">
                {{ __('You already own this module.') }}
                <a href="{{ route('superadmin.modules.index') }}" class="underline">{{ __('Back to Modules') }}</a>
            </div>
        @elseif (! $buyEnabled || $price <= 0)
            <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 text-sm font-bold">
                {{ __('This module is not available for self-serve purchase. Enter a license key on the Modules screen instead.') }}
            </div>
        @elseif (empty($gateways))
            <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 text-sm font-bold">
                {{ __('No payment gateway is enabled. Configure one under Settings → Payment Gateways.') }}
            </div>
        @else
            <div class="space-y-3">
                <p class="text-xs font-bold text-slate-600 dark:text-slate-300">{{ __('Pay with') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($gateways as $key => $gw)
                        <button type="button" wire:click="$set('selectedGateway', '{{ $key }}')"
                                @class([
                                    'px-4 py-2 rounded-xl text-xs font-extrabold border capitalize',
                                    'border-indigo-600 bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' => $selectedGateway === $key,
                                    'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' => $selectedGateway !== $key,
                                ])>{{ $key }}</button>
                    @endforeach
                </div>

                @if ($selectedGateway === 'razorpay')
                    <button type="button" wire:click="startRazorpay"
                            class="w-full px-6 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-extrabold shadow-lg">
                        ⚡ {{ __('Pay') }} {{ $currency }} {{ number_format($price, 2) }} {{ __('with Razorpay') }}
                    </button>
                @else
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('The :gw checkout is not wired for module purchases yet — use Razorpay, or enter a license key on the Modules screen.', ['gw' => ucfirst($selectedGateway)]) }}
                    </p>
                @endif
            </div>
        @endif
    </div>

    <a href="{{ route('superadmin.modules.index') }}" class="inline-block text-xs font-bold text-slate-500 hover:underline">← {{ __('Back to Modules') }}</a>

    @script
    <script>
        $wire.on('module-razorpay-checkout', (payload) => {
            const cfg = Array.isArray(payload) ? payload[0] : payload;
            const launch = () => {
                const rzp = new Razorpay({
                    key: cfg.key_id,
                    amount: cfg.amount,
                    currency: cfg.currency,
                    name: cfg.name,
                    description: cfg.description,
                    order_id: cfg.order_id,
                    handler: (res) => $wire.verifyRazorpay(res.razorpay_payment_id, res.razorpay_order_id, res.razorpay_signature),
                });
                rzp.on('payment.failed', (res) => alert('Payment failed: ' + (res.error?.description || 'unknown error')));
                rzp.open();
            };
            if (window.Razorpay) { launch(); return; }
            const s = document.createElement('script');
            s.src = 'https://checkout.razorpay.com/v1/checkout.js';
            s.onload = launch;
            document.head.appendChild(s);
        });
    </script>
    @endscript
</div>
