<?php

namespace App\Services\Auth;

use App\Mail\OtpVerificationMail;
use App\Models\AuditLog;
use App\Models\PendingRegistration;
use App\Models\PlatformBranding;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OtpVerificationService
{
    public const OTP_EXPIRY_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    /**
     * Determine if OTP email verification is required based on SuperAdmin SMTP status.
     */
    public function isOtpRequired(): bool
    {
        return PlatformBranding::current()->isSmtpConfigured();
    }

    /**
     * Dynamically configure Laravel's mailer from PlatformBranding singleton.
     */
    public function configurePlatformMail(): void
    {
        $branding = PlatformBranding::current();
        if ($branding->isSmtpConfigured()) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $branding->smtp_host,
                'mail.mailers.smtp.port' => $branding->smtp_port ?: 587,
                'mail.mailers.smtp.username' => $branding->smtp_username,
                'mail.mailers.smtp.password' => $branding->smtp_password,
                'mail.mailers.smtp.encryption' => $branding->smtp_encryption ?: 'tls',
                'mail.from.address' => $branding->smtp_from_address ?: ($branding->support_email ?: config('mail.from.address')),
                'mail.from.name' => $branding->smtp_from_name ?: ($branding->platform_name ?: config('mail.from.name')),
            ]);
        }
    }

    /**
     * Generate a cryptographically secure 6-digit OTP code.
     */
    public function generateOtp(): string
    {
        return sprintf('%06d', random_int(100000, 999999));
    }

    /**
     * Park registration payload and send 6-digit OTP code via email.
     */
    public function sendOtpForRegistration(array $payload): PendingRegistration
    {
        $email = strtolower(trim($payload['email'] ?? $payload['admin_email'] ?? ''));
        if (empty($email)) {
            throw new RuntimeException(__('A valid email address is required for verification.'));
        }

        $otp = $this->generateOtp();
        $expiresAt = now()->addMinutes(self::OTP_EXPIRY_MINUTES);

        $pending = PendingRegistration::updateOrCreate(
            ['email' => $email],
            [
                'payload' => $payload,
                'otp_hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => $expiresAt,
            ]
        );

        $this->dispatchOtpEmail(
            email: $email,
            otp: $otp,
            recipientName: $payload['owner_name'] ?? $payload['store_name'] ?? 'Store Owner'
        );

        return $pending;
    }

    /**
     * Send OTP for an existing unverified user.
     */
    public function sendOtpForUser(User $user): PendingRegistration
    {
        $otp = $this->generateOtp();
        $expiresAt = now()->addMinutes(self::OTP_EXPIRY_MINUTES);

        $pending = PendingRegistration::updateOrCreate(
            ['email' => strtolower(trim($user->email))],
            [
                'payload' => ['user_id' => $user->id, 'company_id' => $user->company_id],
                'otp_hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => $expiresAt,
            ]
        );

        $this->dispatchOtpEmail(
            email: $user->email,
            otp: $otp,
            recipientName: $user->name ?: 'Administrator'
        );

        return $pending;
    }

    /**
     * Verify OTP for a pending registration.
     */
    public function verifyRegistrationOtp(string $email, string $otp): PendingRegistration
    {
        $email = strtolower(trim($email));
        $pending = PendingRegistration::where('email', $email)->first();

        if (! $pending) {
            throw new RuntimeException(__('No pending registration found for this email. Please register again.'));
        }

        if ($pending->isExpired()) {
            throw new RuntimeException(__('The verification code has expired. Please request a new code.'));
        }

        if ($pending->attempts >= self::MAX_ATTEMPTS) {
            throw new RuntimeException(__('Too many failed attempts. Please request a new verification code.'));
        }

        if (! Hash::check($otp, $pending->otp_hash)) {
            $pending->increment('attempts');
            throw new RuntimeException(__('Invalid 6-digit verification code. Please try again.'));
        }

        return $pending;
    }

    /**
     * Verify OTP for an existing authenticated user.
     */
    public function verifyUserOtp(User $user, string $otp): bool
    {
        $email = strtolower(trim($user->email));
        $pending = PendingRegistration::where('email', $email)->first();

        if (! $pending) {
            throw new RuntimeException(__('No verification code was requested. Please click Resend Code.'));
        }

        if ($pending->isExpired()) {
            throw new RuntimeException(__('The verification code has expired. Please request a new code.'));
        }

        if ($pending->attempts >= self::MAX_ATTEMPTS) {
            throw new RuntimeException(__('Too many failed attempts. Please request a new verification code.'));
        }

        if (! Hash::check($otp, $pending->otp_hash)) {
            $pending->increment('attempts');
            throw new RuntimeException(__('Invalid 6-digit verification code. Please try again.'));
        }

        $user->update(['email_verified_at' => now()]);
        $pending->delete();

        AuditLog::record('user.email_verified', $user->company_id, $user->id, [
            'email' => $user->email,
        ]);

        return true;
    }

    /**
     * Helper to dispatch the OTP email with configured SMTP settings.
     */
    protected function dispatchOtpEmail(string $email, string $otp, string $recipientName = 'Administrator'): void
    {
        $this->configurePlatformMail();
        $branding = PlatformBranding::current();

        Mail::to($email)->send(new OtpVerificationMail(
            otp: $otp,
            recipientName: $recipientName,
            platformName: $branding->platform_name ?: config('app.name', 'Smart Inventory & Sales'),
            validMinutes: self::OTP_EXPIRY_MINUTES,
            fromEmail: $branding->smtp_from_address ?: ($branding->support_email ?: config('mail.from.address')),
            fromSenderName: $branding->smtp_from_name ?: ($branding->platform_name ?: config('mail.from.name')),
        ));
    }
}
