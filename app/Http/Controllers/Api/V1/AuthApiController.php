<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PlatformBranding;
use App\Models\TenantApiKey;
use App\Models\User;
use App\Services\Modular\ModuleRegistry;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthApiController extends Controller
{
    /**
     * Handle public tenant & user registration with optional email OTP verification.
     * POST /api/auth/register
     */
    public function register(Request $request, TenantProvisioningService $provisioner): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'store_name' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', 'unique:companies,slug'],
            'custom_domain' => ['nullable', 'string', 'max:100', 'unique:companies,custom_domain'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'pos_mode' => ['nullable', 'string'],
            'plan_name' => ['nullable', 'string', 'in:trial,starter,professional'],
            'activation_code' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => 'Validation error during registration.',
                'details' => $validator->errors(),
            ], 422);
        }

        $requestedMode = strtolower(trim((string) $request->input('pos_mode', 'general')));
        $normalizedMode = $requestedMode === 'general' ? 'retail' : $requestedMode;
        $enabledModes = ModuleRegistry::enabledRegistrationModes();

        if (! in_array($normalizedMode, $enabledModes, true)) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => "Registration for mode '{$requestedMode}' is currently disabled.",
            ], 422);
        }

        try {
            $regData = [
                'store_name' => $request->input('store_name'),
                'slug' => $request->input('slug'),
                'custom_domain' => $request->input('custom_domain'),
                'owner_name' => $request->input('name'),
                'admin_name' => $request->input('name'),
                'email' => $request->input('email'),
                'admin_email' => $request->input('email'),
                'password' => $request->input('password'),
                'admin_password' => $request->input('password'),
                'phone' => $request->input('phone'),
                'currency' => $request->filled('currency') ? $request->input('currency') : null,
                'country' => $request->filled('country') ? $request->input('country') : null,
                'language' => $request->filled('language') ? $request->input('language') : ($request->filled('locale') ? $request->input('locale') : null),
                'default_locale' => $request->filled('default_locale') ? $request->input('default_locale') : null,
                'timezone' => $request->filled('timezone') ? $request->input('timezone') : null,
                'pos_mode' => $request->input('pos_mode', 'general'),
                'plan_name' => $request->input('plan_name', 'trial'),
                'activation_code' => $request->input('activation_code'),
            ];

            $result = $provisioner->registerTenant($regData);
            /** @var Company $company */
            $company = $result['company'];
            /** @var User $user */
            $user = $result['user'];

            $branding = PlatformBranding::current();
            $smtpConfigured = ! empty($branding?->smtp_host);
            $otpRequired = ($branding?->otp_registration_enabled ?? false) || ($smtpConfigured && ($branding?->otp_registration_enabled !== false));

            if ($otpRequired) {
                $otp = (string) random_int(100000, 999999);
                $expiresAt = now()->addMinutes(10);

                $user->update([
                    'verification_code' => Hash::make($otp),
                    'verification_code_expires_at' => $expiresAt,
                    'status' => 'pending',
                    'email_verified_at' => null,
                ]);

                if (Schema::hasTable('email_verifications')) {
                    DB::table('email_verifications')->insert([
                        'email' => strtolower(trim($user->email)),
                        'otp_hash' => Hash::make($otp),
                        'expires_at' => $expiresAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                try {
                    $this->sendOtpEmail($user->email, $otp, $branding);
                } catch (\Throwable $e) {
                    Log::warning("Failed to send OTP verification email to {$user->email}: ".$e->getMessage());
                }

                return response()->json([
                    'success' => true,
                    'status' => 'requires_verification',
                    'requires_otp' => true,
                    'action' => 'navigate',
                    'route' => '/api/tenant/views/verify-otp',
                    'email' => $user->email,
                    'expires_in' => 600,
                    'message' => 'Enter the 6-digit code sent to your email.',
                    'arguments' => [
                        'email' => $user->email,
                        'message' => 'Enter the 6-digit code sent to your email.',
                    ],
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                ], 200);
            }

            // If OTP verification not required, issue API Key directly
            $apiKey = TenantApiKey::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'name' => 'Desktop/Mobile POS Client ('.$user->name.')',
                'token' => 'zk_live_'.Str::random(40),
                'permissions' => ['*'],
                'active' => true,
            ]);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Tenant registered and provisioned successfully.',
                'token' => $apiKey->token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'login' => $user->login,
                    'email' => $user->email,
                    'role' => $user->role,
                    'company_id' => $user->company_id,
                ],
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'currency' => $company->currency ?? 'USD',
                    'currency_symbol' => $company->currency_symbol ?? '$',
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => 'Registration failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate 6-digit email OTP and activate account.
     * POST /api/auth/verify-email-otp
     */
    public function verifyEmailOtp(Request $request): JsonResponse
    {
        if (! $request->filled('otp') && $request->filled('code')) {
            $request->merge(['otp' => $request->input('code')]);
        } elseif (! $request->filled('otp') && $request->filled('verification_code')) {
            $request->merge(['otp' => $request->input('verification_code')]);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $email = strtolower(trim((string) $request->input('email')));
        $otp = trim((string) $request->input('otp'));

        $user = User::withoutGlobalScopes()->where('email', $email)->first();
        if (! $user) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => 'No account associated with that email address.',
            ], 404);
        }

        $isValid = false;
        if (! empty($user->verification_code) && $user->verification_code_expires_at && now()->lt($user->verification_code_expires_at)) {
            if (Hash::check($otp, $user->verification_code)) {
                $isValid = true;
            }
        }

        if (! $isValid && Schema::hasTable('email_verifications')) {
            $recent = DB::table('email_verifications')
                ->where('email', $email)
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->limit(5)
                ->get();
            foreach ($recent as $record) {
                if (Hash::check($otp, $record->otp_hash)) {
                    $isValid = true;
                    break;
                }
            }
        }

        if (! $isValid) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => 'Invalid or expired OTP verification code.',
            ], 422);
        }

        $user->update([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
            'status' => 'approved',
        ]);

        if (Schema::hasTable('email_verifications')) {
            DB::table('email_verifications')->where('email', $email)->delete();
        }

        $company = Company::find($user->company_id);
        if (! $company) {
            $company = Company::create([
                'name' => ($user->name ?: 'Store')."'s POS",
                'slug' => Str::slug($user->name.'-'.Str::random(5)),
                'status' => 'active',
                'currency' => 'USD',
                'currency_symbol' => '$',
            ]);
            $user->update(['company_id' => $company->id]);
        }

        $apiKey = TenantApiKey::firstOrCreate([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'name' => 'Mobile/Desktop POS Client ('.$user->name.')',
        ], [
            'token' => 'zk_live_'.Str::random(40),
            'permissions' => ['*'],
            'active' => true,
        ]);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Email verified successfully!',
            'action' => 'navigate',
            'route' => '/dashboard',
            'token' => $apiKey->token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'login' => $user->login,
                'email' => $user->email,
                'role' => $user->role,
                'company_id' => $user->company_id,
            ],
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'currency' => $company->currency ?? 'USD',
                'currency_symbol' => $company->currency_symbol ?? '$',
            ],
        ]);
    }

    /**
     * Resend verification OTP code.
     * POST /api/auth/resend-otp
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $email = strtolower(trim((string) $request->input('email')));
        $user = User::withoutGlobalScopes()->where('email', $email)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => 'No account found with that email address.',
            ], 404);
        }

        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);

        $user->update([
            'verification_code' => Hash::make($otp),
            'verification_code_expires_at' => $expiresAt,
        ]);

        if (Schema::hasTable('email_verifications')) {
            DB::table('email_verifications')->insert([
                'email' => $email,
                'otp_hash' => Hash::make($otp),
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $branding = PlatformBranding::current();
        try {
            $this->sendOtpEmail($user->email, $otp, $branding);
        } catch (\Throwable $e) {
            Log::warning("Failed to resend OTP to {$user->email}: ".$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'A new 6-digit verification code has been sent to your email.',
        ]);
    }

    protected function sendOtpEmail(string $email, string $otp, ?PlatformBranding $branding): void
    {
        $host = $branding?->smtp_host;
        $port = $branding?->smtp_port ?? 587;
        $username = $branding?->smtp_username;
        $password = $branding?->smtp_password;
        $encryption = $branding?->smtp_encryption ?? 'tls';
        $fromAddress = $branding?->smtp_from_address ?: config('mail.from.address', 'noreply@example.com');
        $fromName = $branding?->smtp_from_name ?: ($branding?->platform_name ?: config('mail.from.name', 'ZoomNearby'));

        if (! empty($host)) {
            Config::set('mail.mailers.otp_smtp', [
                'transport' => 'smtp',
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'password' => $password,
                'timeout' => 10,
            ]);
            $mailer = Mail::mailer('otp_smtp');
        } else {
            $mailer = Mail::mailer();
        }

        $mailer->raw("Your email verification code is: {$otp}\n\nThis code will expire in 10 minutes. If you did not create an account, please disregard this message.", function ($message) use ($email, $fromAddress, $fromName) {
            $message->to($email)
                ->from($fromAddress, $fromName)
                ->subject('Your Email Verification Code');
        });
    }
}
