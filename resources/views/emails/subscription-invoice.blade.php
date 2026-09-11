<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Subscription Tax Invoice #{{ $invoice->invoice_number }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 32px; border: 1px solid #e2e8f0;">
        
        <!-- Header -->
        <div style="text-align: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 24px;">
            <h2 style="margin: 0; color: #2563eb; font-size: 22px;">⚡ {{ $invoice->seller_details['company_name'] ?? config('app.name') }}</h2>
            <p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;">Official Subscription Tax Invoice Receipt</p>
        </div>

        <p style="font-size: 14px; line-height: 1.6; margin: 0 0 16px 0;">
            Hello <strong>{{ $invoice->buyer_details['contact_name'] ?? $company->name }}</strong>,
        </p>

        <p style="font-size: 14px; line-height: 1.6; margin: 0 0 20px 0; color: #475569;">
            Thank you for subscribing to <strong>{{ config('app.name') }}</strong>. Your payment/activation for <strong>{{ $invoice->plan_name }}</strong> has been processed successfully.
        </p>

        <!-- Invoice Details Box -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Invoice Number:</td>
                    <td style="padding: 6px 0; font-weight: bold; text-align: right; color: #0f172a;">#{{ $invoice->invoice_number }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Store Account:</td>
                    <td style="padding: 6px 0; font-weight: bold; text-align: right; color: #0f172a;">{{ $company->name }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Plan:</td>
                    <td style="padding: 6px 0; font-weight: bold; text-align: right; color: #0f172a;">{{ $invoice->plan_name }} ({{ ucfirst($invoice->billing_cycle) }})</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Invoice Date:</td>
                    <td style="padding: 6px 0; font-weight: bold; text-align: right; color: #0f172a;">{{ $invoice->invoice_date->format('d M Y') }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Payment Method:</td>
                    <td style="padding: 6px 0; font-weight: bold; text-align: right; color: #0f172a; text-transform: capitalize;">{{ str_replace('_', ' ', $invoice->payment_method) }}</td>
                </tr>
                <tr style="border-top: 1px solid #e2e8f0;">
                    <td style="padding: 10px 0 4px 0; color: #0f172a; font-weight: bold; font-size: 15px;">Total Paid:</td>
                    <td style="padding: 10px 0 4px 0; font-weight: 800; text-align: right; color: #2563eb; font-size: 16px;">${{ $invoice->getFormattedTotal() }} {{ $invoice->currency }}</td>
                </tr>
            </table>
        </div>

        <!-- Action Button -->
        <div style="text-align: center; margin-bottom: 28px;">
            <a href="{{ route('tenant.billing.invoices.pdf', $invoice) }}"
               style="display: inline-block; background-color: #2563eb; color: #ffffff; padding: 12px 28px; border-radius: 12px; font-weight: bold; font-size: 14px; text-decoration: none;">
                📄 View / Download Tax Invoice (PDF)
            </a>
        </div>

        <!-- Footer -->
        <div style="border-top: 1px solid #f1f5f9; padding-top: 16px; font-size: 12px; color: #94a3b8; text-align: center;">
            <p style="margin: 0;">Have questions? Contact our team at <a href="mailto:{{ $invoice->seller_details['support_email'] ?? 'support@example.com' }}" style="color: #2563eb;">{{ $invoice->seller_details['support_email'] ?? 'support@example.com' }}</a></p>
            <p style="margin: 4px 0 0 0;">© {{ date('Y') }} {{ $invoice->seller_details['company_name'] ?? config('app.name') }}. All rights reserved.</p>
        </div>

    </div>
</body>
</html>
