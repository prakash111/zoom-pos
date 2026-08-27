<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Stand — {{ $table->table_number }} | {{ $company->name }}</title>
    @if ($company->favicon)
        <link rel="icon" href="{{ $company->favicon }}">
    @endif
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #0f172a;
            color: #1e293b;
            min-height: 100vh;
            padding: 24px 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .no-print-bar {
            max-width: 360px;
            width: 100%;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1e293b;
            padding: 12px 18px;
            border-radius: 16px;
            color: #ffffff;
        }
        .btn {
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            background: #84cc16;
            color: #000000;
        }
        .tent-card {
            max-width: 360px;
            width: 100%;
            background: #ffffff;
            border-radius: 28px;
            padding: 36px 28px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            border: 4px solid #84cc16;
        }
        .store-logo {
            max-height: 48px;
            max-width: 160px;
            object-fit: contain;
            margin: 0 auto 10px auto;
        }
        .store-name {
            font-size: 20px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .table-badge {
            display: inline-block;
            margin: 14px 0;
            background: #84cc16;
            color: #000000;
            font-size: 24px;
            font-weight: 900;
            padding: 6px 20px;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .scan-cta {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #64748b;
            margin-bottom: 16px;
        }
        .qr-box {
            background: #f8fafc;
            padding: 16px;
            border-radius: 20px;
            display: inline-block;
            border: 2px dashed #cbd5e1;
            margin-bottom: 16px;
        }
        .qr-image {
            width: 180px;
            height: 180px;
            display: block;
        }
        .instructions {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            line-height: 1.5;
        }
        @media print {
            body { background: #ffffff !important; padding: 0 !important; }
            .no-print-bar { display: none !important; }
            .tent-card { box-shadow: none !important; border: 2px solid #000000 !important; }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <strong>{{ __("Stand:") }} {{ $table->table_number }}</strong>
        <button onclick="window.print()" class="btn">🖨️ Print Card</button>
    </div>

    <div class="tent-card">
        @if ($company->logo)
            <img src="{{ $company->logo }}" alt="{{ $company->name }}" class="store-logo">
        @endif
        <div class="store-name">{{ $company->name }}</div>

        <div class="table-badge">
            {{ $table->table_number }}
        </div>

        <div class="scan-cta">{{ __("Scan to View Menu & Order") }}</div>

        <div class="qr-box">
            @php
                $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrUrl);
            @endphp
            <img src="{{ $qrApiUrl }}" alt="Scan QR Code" class="qr-image">
        </div>

        <div class="instructions">
            Point your phone's camera at the QR code above.<br>
            Browse our digital menu & order directly from your table! ✨
        </div>
    </div>

</body>
</html>
