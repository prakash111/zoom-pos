<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Mail\TenantPasswordResetMail;
use App\Models\Company;
use App\Models\User;
use App\Services\Invoice\InvoiceDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Forgot-password (request link + consume link) and authenticated
 * change-password flows for tenant users — serves both the web session
 * screens and the mobile/desktop API (POST /api/tenant/password/email,
 * POST /api/tenant/password/reset, POST /api/tenant/profile/change-password),
 * branching on whether the caller wants JSON.
 */
class PasswordResetController extends Controller
{
    use ResolvesTenantSyncContext;

    private const TOKEN_TTL_MINUTES = 60;

    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function sendResetLink(Request $request): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return $this->fail($request, 'Please provide a valid email address.', $validator->errors()->toArray());
        }

        $email = trim($request->input('email'));
        $user = User::withoutGlobalScope('company')->where('email', $email)->first();

        // Always report success even when no account matches — avoids
        // leaking which emails are registered.
        $genericMessage = "If an account exists for {$email}, a password reset link has been sent.";

        if (! $user) {
            return $this->success($request, $genericMessage);
        }

        $company = Company::find($user->company_id);
        $smtp = app(InvoiceDeliveryService::class)->getSmtpConfig($company);
        if (empty($smtp['host'])) {
            return $this->fail($request, 'Password reset email could not be sent: SMTP is not configured for this store.');
        }

        $plainToken = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($plainToken), 'created_at' => now()]
        );

        $resetUrl = route('tenant.password.reset.form', ['token' => $plainToken, 'email' => $email]);
        $mailable = new TenantPasswordResetMail($user, $resetUrl, $company);

        try {
            if (app()->environment('testing') || config('mail.default') === 'array') {
                Mail::to($email)->send($mailable);
            } else {
                Config::set('mail.mailers.tenant_dynamic', [
                    'transport' => 'smtp',
                    'host' => $smtp['host'],
                    'port' => $smtp['port'],
                    'encryption' => $smtp['encryption'],
                    'username' => $smtp['username'],
                    'password' => $smtp['password'],
                    'timeout' => 15,
                ]);
                Mail::mailer('tenant_dynamic')->to($email)->send($mailable);
            }
        } catch (\Throwable $e) {
            return $this->fail($request, 'Failed to send password reset email: '.$e->getMessage());
        }

        return $this->success($request, $genericMessage);
    }

    public function reset(Request $request): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return $this->fail($request, 'Validation error.', $validator->errors()->toArray());
        }

        $email = trim($request->input('email'));
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $row || ! Hash::check($request->input('token'), $row->token)) {
            return $this->fail($request, 'This password reset link is invalid.');
        }

        if (now()->diffInMinutes($row->created_at) > self::TOKEN_TTL_MINUTES) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return $this->fail($request, 'This password reset link has expired. Please request a new one.');
        }

        $user = User::withoutGlobalScope('company')->where('email', $email)->first();
        if (! $user) {
            return $this->fail($request, 'This password reset link is invalid.');
        }

        $user->update(['password' => Hash::make($request->input('password'))]);
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return $this->success($request, 'Your password has been reset. You can now sign in.', ['redirect' => route('tenant.login')], route('tenant.login'));
    }

    /**
     * Authenticated change-password — web (session guard) or mobile/desktop
     * API (bearer token via AuthenticateTenantApi, matching the resolveUser()
     * pattern used by the other tenant API controllers).
     */
    public function changePassword(Request $request): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return $this->fail($request, 'Validation error.', $validator->errors()->toArray());
        }

        $user = auth('web')->user();
        if (! $user && app()->bound('tenant.company_id')) {
            $user = $this->resolveUser($request, $this->resolveCompany($request));
        }

        if (! $user) {
            return $this->fail($request, 'Not authenticated.', [], 401);
        }

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return $this->fail($request, 'Your current password is incorrect.', ['current_password' => ['Incorrect password.']]);
        }

        $user->update(['password' => Hash::make($request->input('new_password'))]);

        return $this->success($request, 'Password changed successfully.');
    }

    private function success(Request $request, string $message, array $extra = [], ?string $redirectTo = null): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(array_merge(['success' => true, 'message' => $message], $extra));
        }

        return ($redirectTo ? redirect()->to($redirectTo) : redirect()->back())->with('status', $message);
    }

    private function fail(Request $request, string $message, array $errors = [], int $status = 422): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'error' => $message, 'details' => $errors], $status);
        }

        return redirect()->back()->withErrors($errors ?: ['email' => $message])->with('error', $message);
    }
}
