<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New Contact Inquiry</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 32px; border: 1px solid #e2e8f0;">

        <div style="text-align: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 24px;">
            <h2 style="margin: 0; color: #2563eb; font-size: 22px;">📬 New Website Contact Inquiry</h2>
            <p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;">Submitted {{ $inquiry->created_at->format('d M Y, H:i') }}</p>
        </div>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; color: #64748b; width: 110px;">Name:</td>
                    <td style="padding: 6px 0; font-weight: bold; color: #0f172a;">{{ $inquiry->name }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Email:</td>
                    <td style="padding: 6px 0; font-weight: bold; color: #0f172a;">{{ $inquiry->email }}</td>
                </tr>
                @if ($inquiry->store_type)
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Store Type:</td>
                        <td style="padding: 6px 0; font-weight: bold; color: #0f172a;">{{ $inquiry->store_type }}</td>
                    </tr>
                @endif
                @if ($inquiry->phone)
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Phone:</td>
                        <td style="padding: 6px 0; font-weight: bold; color: #0f172a;">{{ $inquiry->phone }}</td>
                    </tr>
                @endif
                @if ($inquiry->subject)
                    <tr>
                        <td style="padding: 6px 0; color: #64748b;">Subject:</td>
                        <td style="padding: 6px 0; font-weight: bold; color: #0f172a;">{{ $inquiry->subject }}</td>
                    </tr>
                @endif
            </table>
        </div>

        <p style="font-size: 13px; color: #64748b; margin: 0 0 6px 0;">Message:</p>
        <p style="font-size: 14px; line-height: 1.6; color: #1e293b; white-space: pre-wrap; margin: 0 0 24px 0;">{{ $inquiry->message }}</p>

        <p style="font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 16px; margin: 0;">
            Reply directly to this email to respond to {{ $inquiry->name }}.
        </p>
    </div>
</body>
</html>
