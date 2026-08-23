<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Team Invitation — {{ $company->name }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 24px 12px;
            margin: 0;
        }
        .email-container {
            max-width: 560px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 20px;
            padding: 36px 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }
        .header {
            text-align: center;
            padding-bottom: 24px;
            border-bottom: 1px solid #f1f5f9;
        }
        .brand-name {
            font-size: 24px;
            font-weight: 900;
            color: #2563eb;
            letter-spacing: -0.5px;
        }
        .badge {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: #eff6ff;
            color: #1d4ed8;
        }
        .content {
            padding: 24px 0;
            line-height: 1.6;
            font-size: 14px;
            color: #334155;
        }
        .code-box {
            background-color: #f1f5f9;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
            border: 1px dashed #cbd5e1;
        }
        .code-title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .code-value {
            font-family: monospace;
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 4px;
            color: #2563eb;
        }
        .btn-container {
            text-align: center;
            margin: 28px 0;
        }
        .btn-accept {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            font-size: 14px;
            font-weight: 800;
            padding: 14px 28px;
            border-radius: 14px;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
        }
    </style>
</head>
<body>

    <div class="email-container">
        
        <div class="header">
            <div class="brand-name">{{ $company->name }}</div>
            <div class="badge">Team Invitation</div>
        </div>

        <div class="content">
            <p>Hello <strong>{{ $user->name }}</strong>,</p>
            <p>You have been invited to join the team at <strong>{{ $company->name }}</strong> with the role of <strong>{{ ucfirst($user->role) }}</strong>.</p>
            <p>Use your one-time invitation code below or click the button to set up your password and access the store dashboard.</p>

            <div class="code-box">
                <div class="code-title">Your One-Time Invitation Code</div>
                <div class="code-value">{{ $plainCode }}</div>
            </div>

            <div class="btn-container">
                <a href="{{ $inviteUrl }}" class="btn-accept">Accept Invitation & Join Team &rarr;</a>
            </div>

            <p style="font-size: 12px; color: #64748b; text-align: center;">
                This invitation link and code will expire in 7 days.<br>
                Direct link: <a href="{{ $inviteUrl }}" style="color: #2563eb; word-break: break-all;">{{ $inviteUrl }}</a>
            </p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ $company->name }}. All rights reserved.<br>
            Powered by {{ config('app.name') }} POS & Digital Store.
        </div>

    </div>

</body>
</html>
