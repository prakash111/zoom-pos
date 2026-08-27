import { createApp, h, ref } from 'vue';

function livewireComponent(element) {
    const id = element.dataset.livewireId;

    return id && window.Livewire ? window.Livewire.find(id) : null;
}

function decodeMethods(encoded) {
    try {
        return JSON.parse(atob(encoded));
    } catch {
        return [];
    }
}

function paymentIcon(code) {
    const paths = {
        cash: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
        card: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        transfer: 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z',
        credit: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        pix: 'M13 2L3 14h8l-1 8 11-14h-8z',
        consignment: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    };
    const normalized = code === 'consign' ? 'consignment'
        : (code === 'deferred' ? 'credit'
            : (code === 'bank_transfer' ? 'transfer'
                : (code.startsWith('card_') ? 'card' : code)));

    return h('svg', {
        class: ['w-5 h-5', normalized === 'consignment' ? 'text-amber-500' : ''],
        fill: 'none',
        stroke: 'currentColor',
        viewBox: '0 0 24 24',
        'aria-hidden': 'true',
    }, [h('path', {
        'stroke-linecap': 'round',
        'stroke-linejoin': 'round',
        'stroke-width': '2',
        d: paths[normalized] || paths.card,
    })]);
}

const PaymentSelector = {
    props: {
        initialMethod: { type: String, default: 'cash' },
        methods: { type: Array, default: () => [] },
        host: { type: Object, required: true },
    },
    setup(props) {
        const selected = ref(props.initialMethod);

        const choose = (code) => {
            if (selected.value === code) return;
            selected.value = code;
            livewireComponent(props.host)?.set('paymentMethod', code, true);
        };

        return () => h('div', {
            class: 'grid grid-cols-[repeat(auto-fit,minmax(120px,1fr))] gap-2',
            role: 'radiogroup',
            'aria-label': props.host.dataset.label,
        }, props.methods.map((method) => h('button', {
            key: method.code,
            type: 'button',
            role: 'radio',
            'aria-checked': selected.value === method.code ? 'true' : 'false',
            class: [
                'min-h-14 py-2.5 px-3 rounded-xl text-xs font-bold text-center transition-colors capitalize cursor-pointer border flex flex-col items-center justify-center gap-1.5',
                selected.value === method.code
                    ? 'bg-blue-600 border-blue-600 text-white shadow-sm'
                    : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-blue-400',
            ],
            onClick: () => choose(method.code),
        }, [paymentIcon(method.code), h('span', { class: 'leading-tight' }, method.name)])));
    },
};

const CashCalculator = {
    props: {
        host: { type: Object, required: true },
        total: { type: Number, required: true },
        initialTendered: { type: Number, default: 0 },
        decimals: { type: Number, default: 2 },
        symbol: { type: String, default: '$' },
        symbolPosition: { type: String, default: 'prefix' },
    },
    setup(props) {
        const tendered = ref(props.initialTendered || '');

        const formatMoney = (amount) => {
            const formatted = Number(amount).toLocaleString(undefined, {
                minimumFractionDigits: props.decimals,
                maximumFractionDigits: props.decimals,
            });

            return props.symbolPosition === 'suffix'
                ? `${formatted}${props.symbol}`
                : `${props.symbol}${formatted}`;
        };

        const changeDue = () => Math.max(0, Number(tendered.value || 0) - props.total);
        const syncDeferred = () => livewireComponent(props.host)?.set('cashTendered', Number(tendered.value || 0), false);
        const setTendered = (amount) => {
            tendered.value = Number(amount).toFixed(props.decimals);
            syncDeferred();
        };

        return () => h('div', { class: 'space-y-3' }, [
            h('div', { class: 'grid grid-cols-1 sm:grid-cols-2 gap-3' }, [
                h('div', {}, [
                    h('label', { class: 'block font-bold text-slate-400 mb-1', for: 'vue-cash-tendered' }, props.host.dataset.inputLabel),
                    h('input', {
                        id: 'vue-cash-tendered',
                        type: 'number',
                        inputmode: 'decimal',
                        min: '0',
                        step: '0.5',
                        value: tendered.value,
                        placeholder: props.total.toFixed(props.decimals),
                        class: 'w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm font-mono font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500',
                        onInput: (event) => {
                            tendered.value = event.target.value;
                            syncDeferred();
                        },
                    }),
                ]),
                h('div', { class: 'bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/80 rounded-xl p-2.5 flex flex-col justify-center shadow-sm', 'aria-live': 'polite' }, [
                    h('div', { class: 'text-[10px] font-extrabold uppercase tracking-wider text-emerald-700 dark:text-emerald-400' }, props.host.dataset.changeLabel),
                    h('div', { class: 'text-lg font-black text-emerald-600 dark:text-emerald-300 font-mono' }, formatMoney(changeDue())),
                ]),
            ]),
            h('div', { class: 'flex flex-wrap items-center gap-1.5' }, [
                h('span', { class: 'text-[10px] font-bold text-slate-400 mr-1' }, props.host.dataset.presetsLabel),
                ...[
                    { label: props.host.dataset.exactLabel, amount: props.total },
                    { label: formatMoney(Math.ceil(props.total)), amount: Math.ceil(props.total) },
                    { label: formatMoney(Math.ceil(props.total / 10) * 10), amount: Math.ceil(props.total / 10) * 10 },
                    { label: formatMoney(Math.ceil(props.total / 50) * 50), amount: Math.ceil(props.total / 50) * 50 },
                ].filter((preset, index, list) => list.findIndex((item) => item.amount === preset.amount) === index)
                    .map((preset) => h('button', {
                        key: preset.amount,
                        type: 'button',
                        class: 'px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-[11px] font-bold font-mono hover:bg-slate-100 dark:hover:bg-slate-800',
                        onClick: () => setTendered(preset.amount),
                    }, preset.label)),
            ]),
        ]);
    },
};

function mountPaymentSelector(element) {
    if (element.__vueApp) return;

    const app = createApp(PaymentSelector, {
        host: element,
        initialMethod: element.dataset.selected,
        methods: decodeMethods(element.dataset.methods),
    });

    element.__vueApp = app;
    app.mount(element);
}

function mountCashCalculator(element) {
    if (element.__vueApp) return;

    const app = createApp(CashCalculator, {
        host: element,
        total: Number(element.dataset.total),
        initialTendered: Number(element.dataset.tendered),
        decimals: Number(element.dataset.decimals),
        symbol: element.dataset.symbol,
        symbolPosition: element.dataset.symbolPosition,
    });

    element.__vueApp = app;
    app.mount(element);
}

export function mountCheckoutIslands(root = document) {
    const scope = root instanceof Element ? root : document;

    scope.querySelectorAll('[data-vue-payment-selector]').forEach(mountPaymentSelector);
    scope.querySelectorAll('[data-vue-cash-calculator]').forEach(mountCashCalculator);

    if (scope.matches?.('[data-vue-payment-selector]')) mountPaymentSelector(scope);
    if (scope.matches?.('[data-vue-cash-calculator]')) mountCashCalculator(scope);
}

let livewireHookRegistered = false;

export function registerCheckoutIslands() {
    mountCheckoutIslands();

    if (!livewireHookRegistered && window.Livewire) {
        livewireHookRegistered = true;
        window.Livewire.hook('morph.updated', ({ el }) => mountCheckoutIslands(el));
    }
}
