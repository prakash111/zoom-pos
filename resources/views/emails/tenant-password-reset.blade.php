<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reset your password</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 18px; padding: 32px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; }
        .brand { font-size: 22px; font-weight: 800; color: #1d4ed8; margin: 0 0 16px 0; }
        .action-button { display: inline-block; background-color: #1d4ed8; color: #ffffff !important; font-weight: 700; font-size: 14px; text-decoration: none; padding: 12px 24px; border-radius: 12px; margin: 20px 0; text-align: center; }
        .footer { margin-top: 32px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h1 class="brand">{{ $company->trade_name ?? $company->name ?? 'Store' }}</h1>
        <p>Hi {{ $user->name }},</p>
        <p>We received a request to reset the password for your account. Click the button below to choose a new password. This link expires in 60 minutes.</p>

        <div style="text-align: center;">
            <a href="{{ $resetUrl }}" target="_blank" class="action-button">Reset Password &rarr;</a>
        </div>

        <p style="font-size: 12px; color: #64748b;">If you didn't request this, you can safely ignore this email — your password will not change.</p>

        <div class="footer">
            <p>{{ $company->name ?? 'Store' }}{{ $company->website ? ' • ' . $company->website : '' }}</p>
        </div>
    </div>
</body>
</html>
