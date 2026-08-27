<?php

namespace App\Livewire\Auth;

use App\Models\PendingRegistration;
use App\Models\PlatformBranding;
use App\Services\Auth\OtpVerificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class VerifyOtp extends Component
{
    public string $otp = '';

    public string $errorMessage = '';

    public string $statusMessage = '';

    public string $userEmail = '';

    public function mount(OtpVerificationService $otpService): void
    {
        $branding = PlatformBranding::current();
        $user = Auth::guard('web')->user();

        if (! $user) {
            $this->redirect(route('tenant.login'));

            return;
        }

        $this->userEmail = (string) $user->email;

        // If SMTP is not configured or user is already verified, proceed to dashboard
        if (! $branding->isSmtpConfigured() || ! is_null($user->email_verified_at)) {
            $this->redirect(route('tenant.dashboard'));

            return;
        }

        // Send initial OTP if none exists or is expired
        $existing = PendingRegistration::where('email', strtolower(trim($user->email)))->first();
        if (! $existing || $existing->isExpired()) {
            try {
                $otpService->sendOtpForUser($user);
                $this->statusMessage = __('We have sent a 6-digit verification code to your email.');
            } catch (\Throwable $e) {
                $this->errorMessage = $e->getMessage();
            }
        }
    }

    public function verify(OtpVerificationService $otpService)
    {
        $this->errorMessage = '';
        $this->statusMessage = '';

        $this->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ], [
            'otp.required' => __('Please enter the 6-digit verification code.'),
            'otp.size' => __('The verification code must be exactly 6 digits.'),
            'otp.regex' => __('The verification code must contain only numbers.'),
        ]);

        $user = Auth::guard('web')->user();
        if (! $user) {
            return $this->redirect(route('tenant.login'));
        }

        try {
            $otpService->verifyUserOtp($user, $this->otp);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        session()->flash('status', __('Your email address has been verified successfully. Welcome to your store dashboard!'));

        return $this->redirect(route('tenant.dashboard'), navigate: false);
    }

    public function resend(OtpVerificationService $otpService): void
    {
        $this->errorMessage = '';
        $user = Auth::guard('web')->user();

        if (! $user) {
            $this->redirect(route('tenant.login'));

            return;
        }

        try {
            $otpService->sendOtpForUser($user);
            $this->statusMessage = __('A new 6-digit verification code has been sent to :email', ['email' => $user->email]);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
        $this->redirect(route('tenant.login'));
    }

    public function render()
    {
        return view('livewire.auth.verify-otp');
    }
}
