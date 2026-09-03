<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Mail\TenantPasswordResetMail;
use App\Models\Company;
use App\Models\Configuration;
use App\Models\PlatformBranding;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Tenant staff "forgot password" flow — POST /api/tenant/password/email
 * (mobile Sign-in screen & web login) sends a reset link via the tenant's
 * configured SMTP mailer (falling back to platform SMTP, same resolution
 * InvoiceDeliveryService::getSmtpConfig() uses for invoices/reminders), and
 * POST /api/tenant/password/reset consumes it. Uses a dedicated
 * password_reset_tokens table rather than Laravel's Password broker, since
 * that broker assumes a single `users` auth provider/guard and this app's
 * User model is deliberately queried without its tenant scope here (a
 * password-reset lookup must not be limited to whichever company happens to
 * be bound to the current request).
 */
class TenantPasswordResetController extends Controller
{
    use ResolvesTenantSyncContext;

    private const TOKEN_TTL_MINUTES = 60;

    public function sendResetLink(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $email = trim($request->input('email'));
        $user = User::withoutGlobalScope('company')->where('email', $email)->first();

        // Always respond the same way regardless of whether the email is on
        // file, so this endpoint can't be used to enumerate registered staff.
        $generic = ['success' => true, 'message' => 'If an account exists for that email, a password reset link has been sent.'];

        if (! $user) {
            return response()->json($generic);
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        $company = $user->company ?? Company::find($user->company_id);
        $resetUrl = url(route('tenant.password.reset-form', [
            'token' => $token,
            'email' => $email,
        ], false));

        try {
            $this->sendViaTenantSmtp($user, $resetUrl, $company);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json($generic);
    }

    public function reset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        $email = trim($request->input('email'));
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $row || ! Hash::check($request->input('token'), $row->token)) {
            return response()->json(['success' => false, 'error' => 'This password reset link is invalid.'], 422);
        }

        if (now()->diffInMinutes($row->created_at) > self::TOKEN_TTL_MINUTES) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json(['success' => false, 'error' => 'This password reset link has expired. Please request a new one.'], 422);
        }

        $user = User::withoutGlobalScope('company')->where('email', $email)->first();
        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Account not found.'], 404);
        }

        $user->update(['password' => $request->input('password')]);
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json(['success' => true, 'message' => 'Your password has been reset. You can now sign in.']);
    }

    /**
     * PUT /api/v1/pos/profile/change-password — authenticated tenant staff
     * changing their own password from Settings > Profile / Security.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        if (! $user) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'Validation error.', 'details' => $validator->errors()], 422);
        }

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json(['success' => false, 'error' => 'Current password is incorrect.'], 422);
        }

        $user->update(['password' => $request->input('new_password')]);

        return response()->json(['success' => true, 'message' => 'Password updated successfully.']);
    }

    private function sendViaTenantSmtp(User $user, string $resetUrl, ?Company $company): void
    {
        $mailable = new TenantPasswordResetMail($user, $resetUrl, $company);

        if (app()->environment('testing') || config('mail.default') === 'array') {
            Mail::to($user->email)->send($mailable);

            return;
        }

        $tenantConfigs = $company
            ? Configuration::withoutGlobalScopes()->where('company_id', $company->id)->where('key', 'like', 'smtp_%')->pluck('value', 'key')->all()
            : [];

        $host = $tenantConfigs['smtp_host'] ?? null;
        $port = ! empty($tenantConfigs['smtp_port']) ? (int) $tenantConfigs['smtp_port'] : null;
        $username = $tenantConfigs['smtp_username'] ?? null;
        $password = $tenantConfigs['smtp_password'] ?? null;
        $encryption = $tenantConfigs['smtp_encryption'] ?? 'tls';

        if (empty($host)) {
            $branding = PlatformBranding::current();
            $host = $branding?->smtp_host;
            $port = $branding?->smtp_port ?? 587;
            $username = $branding?->smtp_username;
            $password = $branding?->smtp_password;
            $encryption = $branding?->smtp_encryption ?? 'tls';
        }

        if (empty($host)) {
            Mail::to($user->email)->send($mailable);

            return;
        }

        Config::set('mail.mailers.tenant_dynamic', [
            'transport' => 'smtp',
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'timeout' => 15,
        ]);

        Mail::mailer('tenant_dynamic')->to($user->email)->send($mailable);
    }
}
