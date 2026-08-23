<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Account Verification Code') }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #090d16; color: #ffffff; padding: 40px 20px; margin: 0;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; background-color: #0f172a; border-radius: 24px; border: 1px solid rgba(255,255,255,0.1); overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.5);">
        
        <!-- Header -->
        <tr>
            <td style="padding: 32px 36px; background: linear-gradient(135deg, rgba(79, 70, 229, 0.2), rgba(16, 185, 129, 0.1)); border-bottom: 1px solid rgba(255,255,255,0.08); text-align: center;">
                <div style="display: inline-block; width: 44px; height: 44px; line-height: 44px; border-radius: 14px; background-color: #d7f24e; color: #090d16; font-size: 22px; font-weight: 900; margin-bottom: 12px;">
                    ⚡
                </div>
                <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 800; letter-spacing: -0.5px;">
                    {{ $platformName }}
                </h1>
                <p style="margin: 6px 0 0; color: #94a3b8; font-size: 13px; font-weight: 500;">
                    {{ __('Account Security & Verification') }}
                </p>
            </td>
        </tr>

        <!-- Main Body Content -->
        <tr>
            <td style="padding: 36px 36px 28px;">
                <p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #cbd5e1;">
                    {{ __('Hello') }} <strong>{{ $recipientName }}</strong>,
                </p>
                
                <p style="margin: 0 0 24px; font-size: 14px; line-height: 1.6; color: #94a3b8;">
                    {{ __('Please use the following 6-digit verification code to verify your account and access your store dashboard.') }}
                </p>

                <!-- OTP Code Display Card -->
                <div style="margin: 28px 0; padding: 24px; background-color: #1e293b; border-radius: 16px; border: 1px dashed #d7f24e; text-align: center;">
                    <span style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: #d7f24e; margin-bottom: 8px;">
                        {{ __('Your Verification Code') }}
                    </span>
                    <span style="font-family: monospace, 'Courier New', Courier; font-size: 38px; font-weight: 900; letter-spacing: 8px; color: #ffffff; display: inline-block; padding: 4px 8px;">
                        {{ $otp }}
                    </span>
                    <span style="display: block; font-size: 12px; color: #64748b; margin-top: 8px;">
                        {{ __('Valid for :minutes minutes', ['minutes' => $validMinutes]) }}
                    </span>
                </div>

                <div style="background-color: rgba(244, 63, 94, 0.1); border: 1px solid rgba(244, 63, 94, 0.2); border-radius: 12px; padding: 14px 18px; margin-bottom: 24px;">
                    <p style="margin: 0; font-size: 12px; line-height: 1.5; color: #fda4af;">
                        🛡️ <strong>{{ __('Security Reminder') }}:</strong> {{ __('Never share this code with anyone. Platform support staff will never ask for your verification code.') }}
                    </p>
                </div>

                <p style="margin: 0; font-size: 13px; color: #64748b; line-height: 1.5;">
                    {{ __('If you did not request this verification code, please disregard this email or contact support.') }}
                </p>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td style="padding: 24px 36px; background-color: #0b1120; border-top: 1px solid rgba(255,255,255,0.06); text-align: center;">
                <p style="margin: 0; font-size: 12px; color: #64748b;">
                    &copy; {{ date('Y') }} {{ $platformName }}. {{ __('All rights reserved.') }}
                </p>
            </td>
        </tr>

    </table>
</body>
</html>
