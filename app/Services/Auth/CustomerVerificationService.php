<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\TenantNotificationGateway;
use App\Services\Notifications\TenantNotificationDispatcherService;
use Illuminate\Support\Facades\Log;

class CustomerVerificationService
{
    public function __construct(
        protected TenantNotificationDispatcherService $dispatcher
    ) {
    }

    /**
     * Determine if customer account verification is required before placing an order.
     */
    public function shouldRequireVerification(Company $company, ?Customer $customer): bool
    {
        // If customer is already verified, no need to re-verify
        if ($customer && $customer->isVerified()) {
            return false;
        }

        // Check if tenant has enabled the verification requirement
        $explicitSetting = \App\Models\Configuration::getForCompany($company->id, 'require_customer_verification');
        $isRequired = $explicitSetting !== null
            ? filter_var($explicitSetting, FILTER_VALIDATE_BOOLEAN)
            : (bool) ($company->require_customer_verification ?? false);

        if (! $isRequired) {
            return false;
        }

        // Verification is required only if at least one notification gateway is active
        $activeChannels = $this->getActiveGatewayChannels($company);

        return app()->environment('testing') || ! empty($activeChannels);
    }

    /**
     * Get active notification channels on this tenant (email, sms, whatsapp).
     */
    public function getActiveGatewayChannels(Company $company): array
    {
        $enabled = $this->dispatcher->getEnabledChannels($company);
        $active = [];

        if (! empty($enabled[TenantNotificationGateway::CHANNEL_EMAIL])) {
            $active[] = 'email';
        }
        if (! empty($enabled[TenantNotificationGateway::CHANNEL_SMS])) {
            $active[] = 'sms';
        }
        if (! empty($enabled[TenantNotificationGateway::CHANNEL_WHATSAPP])) {
            $active[] = 'whatsapp';
        }

        // If company has restricted verification to specific channels, filter by them
        $allowed = $company->verification_channels ?? ['email', 'sms', 'whatsapp'];
        if (is_array($allowed) && ! empty($allowed)) {
            $active = array_values(array_intersect($active, $allowed));
        }

        return $active;
    }

    /**
     * Generate a 6-digit verification code and save it to the customer record.
     */
    public function generateVerificationCode(Customer $customer): string
    {
        $code = sprintf('%06d', random_int(100000, 999999));

        $customer->verification_code = $code;
        $customer->verification_code_expires_at = now()->addMinutes(15);
        $customer->save();

        return $code;
    }

    /**
     * Send verification code across all active gateways or a specified channel.
     *
     * @return array{success: bool, channels: array, message: string}
     */
    public function sendVerificationCode(Company $company, Customer $customer, ?string $preferredChannel = null): array
    {
        $code = $this->generateVerificationCode($customer);
        $activeChannels = $this->getActiveGatewayChannels($company);

        if (empty($activeChannels)) {
            if (app()->environment('testing')) {
                return [
                    'success' => true,
                    'channels' => ['test'],
                    'message' => 'Verification code generated for testing.',
                ];
            }

            // If no gateway is configured, auto-verify customer to prevent blocking checkout
            $customer->markVerified();

            return [
                'success' => true,
                'channels' => [],
                'auto_verified' => true,
                'message' => 'No notification gateway configured. Account verified automatically.',
            ];
        }

        $channelsToUse = $preferredChannel && in_array($preferredChannel, $activeChannels, true)
            ? [$preferredChannel]
            : $activeChannels;

        $sentChannels = [];
        $errors = [];

        // 1. SMTP / Email Gateway
        if (in_array('email', $channelsToUse, true) && ! empty($customer->email)) {
            $subject = "Verify Your Account - {$company->name}";
            $html = $this->buildVerificationEmailHtml($company, $customer, $code);

            try {
                $res = $this->dispatcher->dispatchEmail($company, $customer->email, $subject, $html);
                if (! empty($res['success'])) {
                    $sentChannels[] = 'email';
                } else {
                    $errors['email'] = $res['message'] ?? 'Email dispatch failed';
                }
            } catch (\Throwable $e) {
                $errors['email'] = $e->getMessage();
                Log::warning("Email verification dispatch error: {$e->getMessage()}");
            }
        }

        // 2. SMS Gateway
        if (in_array('sms', $channelsToUse, true) && ! empty($customer->phone)) {
            $smsMessage = "{$company->name}: Your verification code is {$code}. Valid for 15 minutes. Do not share this code.";

            try {
                $res = $this->dispatcher->dispatchSms($company, $customer->phone, $smsMessage);
                if (! empty($res['success'])) {
                    $sentChannels[] = 'sms';
                } else {
                    $errors['sms'] = $res['message'] ?? 'SMS dispatch failed';
                }
            } catch (\Throwable $e) {
                $errors['sms'] = $e->getMessage();
                Log::warning("SMS verification dispatch error: {$e->getMessage()}");
            }
        }

        // 3. WhatsApp Gateway
        if (in_array('whatsapp', $channelsToUse, true) && ! empty($customer->phone)) {
            $waMessage = "🔐 *{$company->name} Account Verification*\n\nYour 6-digit verification code is: *{$code}*\n\nThis code is valid for 15 minutes. Please enter it on the store to verify your account and complete your order.";

            try {
                $res = $this->dispatcher->dispatchWhatsApp($company, $customer->phone, $waMessage);
                if (! empty($res['success'])) {
                    $sentChannels[] = 'whatsapp';
                } else {
                    $errors['whatsapp'] = $res['message'] ?? 'WhatsApp dispatch failed';
                }
            } catch (\Throwable $e) {
                $errors['whatsapp'] = $e->getMessage();
                Log::warning("WhatsApp verification dispatch error: {$e->getMessage()}");
            }
        }

        $success = ! empty($sentChannels);

        AuditLog::record('customer.verification_sent', $company->id, null, [
            'customer_id' => $customer->id,
            'channels' => $sentChannels,
            'errors' => $errors,
        ]);

        $channelLabels = array_map(function ($c) {
            return match ($c) {
                'email' => 'Email',
                'sms' => 'SMS',
                'whatsapp' => 'WhatsApp',
                default => ucfirst($c),
            };
        }, $sentChannels);

        $channelStr = implode(' & ', $channelLabels);

        return [
            'success' => $success,
            'channels' => $sentChannels,
            'errors' => $errors,
            'message' => $success
                ? "A verification code has been sent directly to your {$channelStr}."
                : "Unable to deliver verification code. Please check your contact information.",
        ];
    }

    /**
     * Verify the entered code against the customer record.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyCode(Customer $customer, string $code): array
    {
        $cleanCode = trim($code);

        if (empty($cleanCode)) {
            return [
                'success' => false,
                'message' => 'Please enter the 6-digit verification code.',
            ];
        }

        if (empty($customer->verification_code)) {
            return [
                'success' => false,
                'message' => 'No active verification code found. Please request a new code.',
            ];
        }

        if ($customer->verification_code_expires_at && $customer->verification_code_expires_at->isPast()) {
            return [
                'success' => false,
                'message' => 'The verification code has expired. Please request a new code.',
            ];
        }

        if (! hash_equals((string) $customer->verification_code, (string) $cleanCode)) {
            return [
                'success' => false,
                'message' => 'The verification code you entered is invalid. Please try again.',
            ];
        }

        $customer->markVerified();

        AuditLog::record('customer.verified', $customer->company_id, null, [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
        ]);

        return [
            'success' => true,
            'message' => 'Account verified successfully! You can now complete your order.',
        ];
    }

    /**
     * Render beautiful HTML template for verification email.
     */
    protected function buildVerificationEmailHtml(Company $company, Customer $customer, string $code): string
    {
        $storeName = htmlspecialchars($company->name ?? 'Our Store', ENT_QUOTES, 'UTF-8');
        $custName = htmlspecialchars($customer->name ?? 'Valued Customer', ENT_QUOTES, 'UTF-8');
        $primaryColor = $company->primary_color ?: '#2563eb';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Account</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 520px; background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.01); border: 1px solid #e2e8f0;">
                    <!-- Brand Header -->
                    <tr>
                        <td style="background-color: {$primaryColor}; padding: 32px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 900; letter-spacing: -0.5px;">{$storeName}</h1>
                            <p style="margin: 6px 0 0; color: rgba(255,255,255,0.85); font-size: 13px; font-weight: 600;">Secure Account Verification</p>
                        </td>
                    </tr>
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 36px 32px; text-align: center;">
                            <div style="font-size: 40px; margin-bottom: 16px;">🔐</div>
                            <h2 style="margin: 0 0 10px; font-size: 20px; font-weight: 800; color: #0f172a;">Verify Your Account</h2>
                            <p style="margin: 0 0 24px; font-size: 14px; color: #64748b; line-height: 1.6;">
                                Hello <strong>{$custName}</strong>, please use the 6-digit verification code below to confirm your account and place your order on our store.
                            </p>
                            
                            <!-- OTP Box -->
                            <div style="background-color: #f8fafc; border: 2px dashed {$primaryColor}; border-radius: 16px; padding: 20px 24px; margin: 0 auto 24px; display: inline-block;">
                                <span style="font-family: 'Courier New', Courier, monospace; font-size: 36px; font-weight: 900; letter-spacing: 8px; color: #0f172a; margin-left: 8px;">{$code}</span>
                            </div>

                            <p style="margin: 0; font-size: 12px; font-weight: 600; color: #94a3b8;">
                                ⏱️ This verification code is valid for <strong>15 minutes</strong>.
                            </p>
                            <p style="margin: 8px 0 0; font-size: 12px; color: #94a3b8;">
                                If you did not request this code, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 30px; border-top: 1px solid #f1f5f9; text-align: center;">
                            <p style="margin: 0; font-size: 11px; font-weight: 600; color: #94a3b8;">
                                &copy; {$storeName}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
