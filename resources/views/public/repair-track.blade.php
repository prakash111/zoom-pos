@php
    $currency = $company?->currency_symbol ?: '$';
    $device = trim(($ticket->brand ?? '') . ' ' . ($ticket->model ?? ''));
    if ($device === '') {
        $device = $ticket->device_type ?: 'Device';
    }
    $isCancelled = $ticket->status === \App\Models\RepairTicket::STATUS_CANCELLED;
    $isDelivered = $ticket->status === \App\Models\RepairTicket::STATUS_DELIVERED;
    $accent = $company?->primary_color ?: '#0284c7';
    $receivedAt = $ticket->intake_at ?? $ticket->created_at;
    $problem = $ticket->problem_reported ?: $ticket->issue_description;

    if ($isDelivered) {
        $estimated = (float) ($ticket->total_amount ?: $ticket->estimated_cost);
        $balanceLabel = 'Balance due';
        $balance = (float) $ticket->balance_due;
    } else {
        $estimated = (float) $ticket->estimated_cost;
        $balanceLabel = 'Estimated balance';
        $balance = max(0, round($estimated - (float) $ticket->advance_deposit, 2));
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Repair {{ $ticket->ticket_number }} — {{ $company?->name ?? 'Repair Tracking' }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen antialiased">
    <div class="max-w-xl mx-auto px-4 py-6 sm:py-10">

        {{-- Store header --}}
        <div class="flex items-center gap-3 mb-5">
            @if ($company?->getLogoUrl())
                <img src="{{ $company->getLogoUrl() }}" alt="" class="w-11 h-11 rounded-xl object-cover border border-slate-200 bg-white">
            @else
                <div class="w-11 h-11 rounded-xl text-white font-black text-lg flex items-center justify-center shadow-sm"
                     style="background-color: {{ $accent }}">
                    {{ strtoupper(substr($company?->name ?? 'R', 0, 1)) }}
                </div>
            @endif
            <div class="min-w-0">
                <div class="font-extrabold text-slate-900 leading-tight truncate">{{ $company?->name ?? 'Repair Service' }}</div>
                <div class="text-xs text-slate-500">Repair status tracking</div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

            {{-- Ticket summary --}}
            <div class="p-5 border-b border-slate-100">
                <div class="text-xs font-semibold tracking-wide text-slate-400 uppercase">Ticket</div>
                <div class="text-xl font-black text-slate-900">#{{ $ticket->ticket_number }}</div>
                <div class="mt-1 text-sm text-slate-600">{{ $device }}</div>

                <div class="mt-4">
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-bold text-white"
                          style="background-color: {{ $ticket->status_color }}">
                        {{ $statusLabels[$ticket->status] ?? \Illuminate\Support\Str::headline($ticket->status) }}
                    </span>
                </div>
            </div>

            {{-- Progress --}}
            <div class="p-5 border-b border-slate-100">
                @if ($isCancelled)
                    <div class="rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-medium px-4 py-3">
                        This repair ticket has been cancelled. Please contact the store if you have questions.
                    </div>
                @else
                    <ol class="space-y-3">
                        @foreach ($flow as $i => $step)
                            @php
                                $done = $i < $currentIndex;
                                $active = $i === $currentIndex;
                            @endphp
                            <li class="flex items-center gap-3">
                                <span class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-[11px] font-bold
                                    {{ $done ? 'bg-emerald-500 text-white' : ($active ? 'text-white' : 'bg-slate-200 text-slate-500') }}"
                                    @if ($active) style="background-color: {{ $ticket->status_color }}" @endif>
                                    {{ $done ? '✓' : $i + 1 }}
                                </span>
                                <span class="text-sm {{ $active ? 'font-bold text-slate-900' : ($done ? 'text-slate-600' : 'text-slate-400') }}">
                                    {{ $statusLabels[$step] ?? \Illuminate\Support\Str::headline($step) }}
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            {{-- Details --}}
            <div class="p-5 grid grid-cols-2 gap-4 text-sm border-b border-slate-100">
                <div>
                    <div class="text-slate-400 text-xs uppercase tracking-wide font-semibold">Received</div>
                    <div class="font-medium text-slate-800">{{ $receivedAt?->format('d M Y') ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-slate-400 text-xs uppercase tracking-wide font-semibold">
                        {{ $isDelivered ? 'Delivered' : 'Expected delivery' }}
                    </div>
                    <div class="font-medium text-slate-800">
                        @if ($isDelivered)
                            {{ $ticket->delivered_at?->format('d M Y') ?? '—' }}
                        @else
                            {{ $ticket->expected_delivery_at?->format('d M Y') ?? 'To be confirmed' }}
                        @endif
                    </div>
                </div>
                @if ($problem)
                    <div class="col-span-2">
                        <div class="text-slate-400 text-xs uppercase tracking-wide font-semibold">Reported issue</div>
                        <div class="font-medium text-slate-800 whitespace-pre-line">{{ $problem }}</div>
                    </div>
                @endif
            </div>

            {{-- Costs --}}
            <div class="p-5 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">{{ $isDelivered ? 'Total' : 'Estimated cost' }}</span>
                    <span class="font-semibold text-slate-900">{{ $currency }}{{ number_format($estimated, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Advance paid</span>
                    <span class="font-semibold text-emerald-600">{{ $currency }}{{ number_format((float) $ticket->advance_deposit, 2) }}</span>
                </div>
                <div class="flex justify-between border-t border-slate-100 pt-2">
                    <span class="text-slate-600 font-semibold">{{ $balanceLabel }}</span>
                    <span class="font-black text-slate-900">{{ $currency }}{{ number_format($balance, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="mt-5 text-center text-xs text-slate-500 space-y-1">
            @if ($company?->phone)
                <div>Questions? Call <a href="tel:{{ $company->phone }}" class="font-semibold text-slate-700">{{ $company->phone }}</a></div>
            @endif
            <div>Last updated {{ $ticket->updated_at?->diffForHumans() }}. Keep this link to check back anytime.</div>
        </div>
    </div>
</body>
</html>
