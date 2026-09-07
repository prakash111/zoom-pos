<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Repair Intake Sheet #{{ $ticket->ticket_number }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 13px; color: #1e293b; margin: 20px; }
        .header { border-bottom: 2px solid #0284c7; padding-bottom: 12px; margin-bottom: 16px; }
        .title { font-size: 20px; font-weight: bold; color: #0f172a; }
        .company-name { font-size: 16px; font-weight: 600; color: #0284c7; }
        .ticket-badge { background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 6px; font-weight: bold; display: inline-block; }
        .grid { display: flex; gap: 20px; margin-bottom: 16px; }
        .box { flex: 1; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; }
        .box-title { font-weight: bold; font-size: 12px; text-transform: uppercase; color: #64748b; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .row { margin-bottom: 6px; }
        .label { font-weight: 600; color: #475569; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th, .table td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }
        .table th { background: #f8fafc; font-weight: 600; }
        .signatures { margin-top: 40px; display: flex; justify-content: space-between; }
        .sig-line { width: 45%; border-top: 1px solid #94a3b8; text-align: center; padding-top: 6px; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <div style="float: right; text-align: right;">
            <div class="ticket-badge">Ticket #{{ $ticket->ticket_number }}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Intake: {{ $ticket->created_at->format('Y-m-d H:i') }}</div>
        </div>
        <div class="company-name">{{ $company->name ?? 'Repair Center' }}</div>
        <div class="title">Equipment Intake & Service Receipt</div>
        <div style="font-size: 11px; color: #64748b;">{{ $company->phone ?? '' }} | {{ $company->email ?? '' }}</div>
    </div>

    <div class="grid">
        <div class="box">
            <div class="box-title">Customer Information</div>
            <div class="row"><span class="label">Name:</span> {{ $ticket->customer?->name ?: $ticket->customer_name }}</div>
            <div class="row"><span class="label">Phone:</span> {{ $ticket->customer?->phone ?: $ticket->customer_phone }}</div>
            @if($ticket->customer?->email)
                <div class="row"><span class="label">Email:</span> {{ $ticket->customer->email }}</div>
            @endif
        </div>
        <div class="box">
            <div class="box-title">Device Specifications</div>
            <div class="row"><span class="label">Device:</span> {{ $ticket->brand }} {{ $ticket->model }}</div>
            <div class="row"><span class="label">Serial / IMEI:</span> {{ $ticket->serial_number_or_imei ?: 'N/A' }}</div>
            <div class="row"><span class="label">Passcode / PIN:</span> {{ $ticket->passcode_pattern ?: 'N/A' }}</div>
            <div class="row"><span class="label">Priority:</span> <strong style="color: {{ $ticket->priority_color }}; text-transform: uppercase;">{{ $ticket->priority }}</strong></div>
        </div>
    </div>

    <div class="box" style="margin-bottom: 16px;">
        <div class="box-title">Reported Problem & Condition</div>
        <div class="row"><span class="label">Problem Reported:</span> {{ $ticket->problem_reported }}</div>
        @if($ticket->physical_condition_notes)
            <div class="row"><span class="label">Physical Condition:</span> {{ $ticket->physical_condition_notes }}</div>
        @endif
        @if($ticket->technician_diagnosis)
            <div class="row"><span class="label">Initial Diagnosis:</span> {{ $ticket->technician_diagnosis }}</div>
        @endif
    </div>

    @if(!empty($ticket->inspection_checklist) && is_array($ticket->inspection_checklist))
        <div class="box" style="margin-bottom: 16px;">
            <div class="box-title">Intake Inspection Checklist</div>
            <table class="table">
                <thead>
                    <tr><th>Inspection Item</th><th>Result</th></tr>
                </thead>
                <tbody>
                    @foreach($ticket->inspection_checklist as $chkKey => $chkVal)
                        @php
                            // Rows come in two shapes: a flat {key => 'pass'} map,
                            // or a list of {key,item_name,status} objects.
                            $chkName = is_array($chkVal)
                                ? ($chkVal['item_name'] ?? $chkVal['name'] ?? $chkVal['key'] ?? $chkKey)
                                : ucwords(str_replace('_', ' ', (string) $chkKey));
                            $chkStatus = is_array($chkVal) ? ($chkVal['status'] ?? 'pending') : $chkVal;
                        @endphp
                        <tr>
                            <td>{{ $chkName }}</td>
                            <td>
                                @if($chkStatus === true || $chkStatus === 'pass' || $chkStatus === 1 || $chkStatus === '1')
                                    <span style="color: #16a34a; font-weight: bold;">✔ PASS</span>
                                @elseif($chkStatus === false || $chkStatus === 'fail' || $chkStatus === 0 || $chkStatus === '0')
                                    <span style="color: #dc2626; font-weight: bold;">✘ FAIL</span>
                                @elseif($chkStatus === 'not_applicable')
                                    <span style="color: #64748b; font-weight: bold;">N/A</span>
                                @else
                                    <span>{{ ucfirst((string) $chkStatus) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="box">
        <div class="box-title">Financial Estimate & Advance Deposit</div>
        <div class="row"><span class="label">Estimated Cost:</span> {{ $company->formatMoney($ticket->estimated_cost) }}</div>
        <div class="row"><span class="label">Advance Deposit Received:</span> <strong>{{ $company->formatMoney($ticket->advance_deposit) }}</strong> ({{ strtoupper($ticket->advance_payment_method ?: 'cash') }})</div>
        <div class="row"><span class="label">Est. Balance Due:</span> {{ $company->formatMoney(max(0, $ticket->estimated_cost - $ticket->advance_deposit)) }}</div>
    </div>

    <div style="font-size: 11px; color: #64748b; margin-top: 16px; border: 1px dashed #cbd5e1; padding: 10px; border-radius: 6px;">
        <strong>Terms & Conditions:</strong> Unclaimed devices after 60 days are subject to disposal or resale. Not responsible for pre-existing data loss or logic board component degradation. Please backup your data prior to hardware repairs.
    </div>

    <div class="signatures">
        <div class="sig-line">Customer Signature</div>
        <div class="sig-line">Technician Signature ({{ $ticket->technician?->name ?: 'Staff' }})</div>
    </div>
</body>
</html>
