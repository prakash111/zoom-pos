<div class="printable-sheet bg-white text-slate-900 p-8 max-w-2xl mx-auto rounded-3xl border border-slate-200 shadow-sm">
    
    <!-- Ticket Header -->
    <div class="text-center pb-4 border-b border-slate-200">
        <h2 class="text-lg font-black tracking-tight uppercase">{{ $order->company?->trade_name ?? $order->company?->name ?? 'Zoom POS & Market' }}</h2>
        <p class="text-xs text-slate-500 mt-0.5">{{ $order->company?->address ?? '742 Evergreen Terrace' }} • Tel: {{ $order->company?->phone ?? '+1-555-0199' }}</p>
        <div class="inline-block mt-2 px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-bold uppercase tracking-wider">
            Service Order / Repair Ticket #{{ $order->ticket_number }}
        </div>
    </div>

    <!-- Customer & Equipment Info Grid -->
    <div class="grid grid-cols-2 gap-4 py-4 border-b border-slate-200 text-xs">
        <div>
            <span class="font-bold text-slate-400 uppercase tracking-wider text-[10px] block">{{ __('Customer Details') }}</span>
            <div class="font-bold text-slate-800 text-sm mt-0.5">{{ $order->customer_name }}</div>
            <div class="text-slate-600">{{ $order->customer_phone }}</div>
            <div class="text-slate-500">{{ $order->customer_email }}</div>
        </div>
        <div class="text-right">
            <span class="font-bold text-slate-400 uppercase tracking-wider text-[10px] block">{{ __('Equipment Specs') }}</span>
            <div class="font-bold text-slate-800 text-sm mt-0.5">{{ $order->equipment_name }}</div>
            <div class="text-slate-600">{{ $order->equipment_brand }}</div>
            <div class="text-slate-500 font-mono">IMEI/SN: {{ $order->serial_number }}</div>
        </div>
    </div>

    <!-- Reported Defect Box -->
    <div class="py-3 border-b border-slate-200 text-xs">
        <span class="font-bold text-slate-700 uppercase tracking-wider text-[10px]">{{ __('Reported Defect:') }}</span>
        <p class="text-slate-600 mt-1 bg-slate-50 p-2.5 rounded-xl border border-slate-100">{{ $order->reported_defect }}</p>
    </div>

    @if($order->technical_diagnosis)
        <div class="py-3 border-b border-slate-200 text-xs">
            <span class="font-bold text-slate-700 uppercase tracking-wider text-[10px]">{{ __('Technical Diagnosis:') }}</span>
            <p class="text-slate-600 mt-1 bg-slate-50 p-2.5 rounded-xl border border-slate-100">{{ $order->technical_diagnosis }}</p>
        </div>
    @endif

    <!-- Parts & Labor Summary -->
    <div class="py-3 border-b border-slate-200 text-xs space-y-1.5">
        @if((float)$order->parts_subtotal > 0)
            <div class="flex justify-between font-semibold text-slate-600">
                <span>{{ __('Parts Subtotal:') }}</span>
                <span class="font-mono">${{ number_format($order->parts_subtotal, 2) }}</span>
            </div>
        @endif
        @if((float)$order->labor_cost > 0)
            <div class="flex justify-between font-semibold text-slate-600">
                <span>{{ __('Labor / Technical Service:') }}</span>
                <span class="font-mono">${{ number_format($order->labor_cost, 2) }}</span>
            </div>
        @endif
        @if((float)$order->discount > 0)
            <div class="flex justify-between font-semibold text-rose-600">
                <span>{{ __('Discount:') }}</span>
                <span class="font-mono">-${{ number_format($order->discount, 2) }}</span>
            </div>
        @endif
        <div class="flex justify-between font-black text-slate-900 text-sm pt-2 border-t border-slate-100">
            <span>{{ __('TOTAL:') }}</span>
            <span class="font-mono text-blue-600">${{ number_format($order->grand_total, 2) }}</span>
        </div>
    </div>

    <!-- Warranty Terms -->
    @if($order->warranty_period || $order->warranty_terms)
        <div class="py-3 border-b border-slate-200 text-[10px] text-slate-400 space-y-1">
            <div>
                <strong class="text-slate-600 font-bold">{{ __('Warranty Period:') }}</strong> {{ $order->warranty_period ?: '90 days' }}
            </div>
            <div class="leading-relaxed">
                {{ $order->warranty_terms ?: 'Warranty covers technical repair and replaced parts only. Physical damage, liquid contact, and broken seals void warranty.' }}
            </div>
        </div>
    @endif

    <!-- Signatures -->
    <div class="grid grid-cols-2 gap-8 pt-8 text-center text-xs">
        <div>
            <div class="border-t border-slate-300 pt-1 text-slate-500">{{ __('Client Signature') }}</div>
        </div>
        <div>
            <div class="border-t border-slate-300 pt-1 text-slate-500">{{ __('Technician Signature') }}</div>
        </div>
    </div>

    <!-- On-Screen Action Controls (Hidden During Print) -->
    <div class="no-print mt-6 flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
        <button type="button" onclick="window.print()" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md transition flex items-center gap-2 cursor-pointer">
            <span>🖨️</span> {{ __('Print Ticket / Slip') }}
        </button>
    </div>
</div>
