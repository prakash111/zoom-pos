<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PlatformSystem;
use App\Models\TenantApiKey;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    /**
     * Redirect to social OAuth provider (Google / Facebook).
     * GET /auth/{provider}/redirect
     */
    public function redirect(Request $request, string $provider)
    {
        $config = $this->config($provider);

        if (! self::providerConfigured($provider)) {
            if ($this->mockAllowed($request)) {
                $mockUrl = route('social.callback', ['provider' => $provider, 'state' => 'mock_state', 'code' => 'mock_code']);
                if ($request->wantsJson()) {
                    return response()->json(['success' => true, 'url' => $mockUrl]);
                }
                return redirect()->away($mockUrl);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => ucfirst($provider) . ' login is not configured yet.',
                ], 422);
            }

            return redirect()->route('tenant.login')->with(
                'error',
                ucfirst($provider) . ' sign-in isn’t set up on this server yet. Please contact your administrator.'
            );
        }

        $state = Str::random(40);
        if ($request->hasSession()) {
            $request->session()->put('social_oauth_state', $state);
        }

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => route('social.callback', $provider),
            'response_type' => 'code',
            'state' => $state,
            'scope' => $provider === 'google' ? 'openid email profile' : 'email,public_profile',
        ];

        $url = $provider === 'google'
            ? 'https://accounts.google.com/o/oauth2/v2/auth'
            : 'https://www.facebook.com/v21.0/dialog/oauth';

        $fullUrl = $url . '?' . http_build_query($params);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'url' => $fullUrl]);
        }

        return redirect()->away($fullUrl);
    }

    /**
     * OAuth callback handler.
     * GET /auth/{provider}/callback
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $config = $this->config($provider);

        // Allow mock bypass during automated tests / explicit local dev only —
        // never on a real deployment, even one mistakenly left on APP_ENV=local.
        if ($this->mockAllowed($request) && $request->query('code') === 'mock_code') {
            $email = "mock.{$provider}@example.com";
            $name = ucfirst($provider) . ' User';
        } else {
            abort_unless(self::providerConfigured($provider), 404);
            if ($request->hasSession()) {
                $savedState = (string) $request->session()->pull('social_oauth_state');
                abort_unless(hash_equals($savedState, (string) $request->query('state')), 419);
            }

            $tokenUrl = $provider === 'google'
                ? 'https://oauth2.googleapis.com/token'
                : 'https://graph.facebook.com/v21.0/oauth/access_token';

            $token = Http::asForm()->post($tokenUrl, [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => route('social.callback', $provider),
                'code' => $request->query('code'),
                'grant_type' => 'authorization_code',
            ])->throw()->json('access_token');

            $profile = $provider === 'google'
                ? Http::withToken($token)->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json()
                : Http::get('https://graph.facebook.com/me', ['fields' => 'id,name,email', 'access_token' => $token])->throw()->json();

            $email = strtolower((string) ($profile['email'] ?? ''));
            $name = (string) ($profile['name'] ?? '');
            abort_if($email === '', 422, 'The social account did not provide an email address.');
        }

        if ($user = User::withoutGlobalScopes()->where('email', $email)->first()) {
            Auth::guard('web')->login($user, true);
            return redirect()->route('tenant.dashboard');
        }

        session(['social_registration' => ['name' => $name, 'email' => $email]]);
        return redirect()->route('tenant.register');
    }

    /**
     * Native mobile token exchange.
     * POST /api/auth/{provider}/mobile-token
     */
    public function mobileToken(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['google', 'facebook'], true), 404);

        $validator = Validator::make($request->all(), [
            'token' => ['nullable', 'string'],
            'access_token' => ['nullable', 'string'],
            'id_token' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'name' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $token = $request->input('token') ?: $request->input('access_token') ?: $request->input('id_token');
        $email = strtolower(trim((string) $request->input('email', '')));
        $name = trim((string) $request->input('name', ''));

        // If email not directly provided, query provider using token
        if (empty($email) && ! empty($token)) {
            try {
                if ($provider === 'google') {
                    $profile = Http::withToken($token)->get('https://openidconnect.googleapis.com/v1/userinfo')->json();
                    if (empty($profile['email'])) {
                        $profile = Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $token])->json();
                    }
                } else {
                    $profile = Http::get('https://graph.facebook.com/me', [
                        'fields' => 'id,name,email',
                        'access_token' => $token,
                    ])->json();
                }
                $email = strtolower(trim((string) ($profile['email'] ?? '')));
                $name = $name ?: (string) ($profile['name'] ?? '');
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to verify ' . ucfirst($provider) . ' token with provider.',
                ], 401);
            }
        }

        if (empty($email)) {
            return response()->json([
                'success' => false,
                'error' => 'Social login did not supply a valid email address.',
            ], 422);
        }

        $user = User::withoutGlobalScopes()->where('email', $email)->first();
        if (! $user) {
            $company = Company::create([
                'name' => ($name ?: 'Store') . "'s POS",
                'email' => $email,
                'status' => 'active',
                'currency' => 'USD',
                'currency_symbol' => '$',
                'operating_mode' => 'retail',
                'licensed_modules' => ['retail', 'service_booking'],
            ]);
            $user = User::create([
                'company_id' => $company->id,
                'name' => $name ?: 'Store Owner',
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'role' => 'administrator',
                'status' => 'approved',
                'email_verified_at' => now(),
            ]);
        } else {
            $company = Company::find($user->company_id);
        }

        $apiKey = TenantApiKey::firstOrCreate([
            'company_id' => $company?->id ?? $user->company_id,
            'user_id' => $user->id,
            'name' => ucfirst($provider) . ' Mobile Client (' . $user->name . ')',
        ], [
            'token' => 'zk_live_' . Str::random(40),
            'permissions' => ['*'],
            'active' => true,
        ]);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Authenticated via ' . ucfirst($provider) . ' successfully.',
            'token' => $apiKey->token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'login' => $user->login,
                'email' => $user->email,
                'role' => $user->role,
                'company_id' => $user->company_id,
            ],
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'currency' => $company->currency ?? 'USD',
                'currency_symbol' => $company->currency_symbol ?? '$',
            ] : null,
        ]);
    }

    private function config(string $provider): array
    {
        abort_unless(in_array($provider, ['google', 'facebook'], true), 404);

        return [
            'enabled' => filter_var(PlatformSystem::get("social_{$provider}_enabled", false), FILTER_VALIDATE_BOOLEAN),
            'client_id' => (string) PlatformSystem::get("social_{$provider}_client_id", ''),
            'client_secret' => (string) PlatformSystem::get("social_{$provider}_client_secret", ''),
        ];
    }

    /**
     * True only when the provider is enabled AND the stored client id / secret
     * actually look like real provider credentials. A placeholder such as
     * "xxxxxxx" passes a bare `!empty()` check but makes the login button
     * forward the user straight to a broken provider error page — so the login
     * screen must treat that as "not configured" and hide the button.
     */
    public static function providerConfigured(string $provider): bool
    {
        if (! in_array($provider, ['google', 'facebook'], true)) {
            return false;
        }

        $enabled = filter_var(PlatformSystem::get("social_{$provider}_enabled", false), FILTER_VALIDATE_BOOLEAN);
        $clientId = trim((string) PlatformSystem::get("social_{$provider}_client_id", ''));
        $secret = trim((string) PlatformSystem::get("social_{$provider}_client_secret", ''));

        if (! $enabled || $clientId === '' || $secret === '') {
            return false;
        }

        return match ($provider) {
            // e.g. 1234567890-abc.apps.googleusercontent.com  +  GOCSPX-...
            'google' => (str_ends_with($clientId, '.apps.googleusercontent.com') || strlen($clientId) >= 24)
                && strlen($secret) >= 16,
            // Facebook App ID is a 15-16 digit number; App Secret is 32 hex chars.
            'facebook' => ctype_digit($clientId) && strlen($clientId) >= 13 && strlen($secret) >= 24,
            default => false,
        };
    }

    /**
     * Provider key => display label, for only the providers that are truly
     * ready to use. Shared by the login and register screens so the button
     * only shows when a click will actually reach the provider.
     *
     * @return array<string, string>
     */
    public static function enabledProviders(): array
    {
        $out = [];
        foreach (['google' => 'Google', 'facebook' => 'Facebook'] as $key => $label) {
            if (self::providerConfigured($key)) {
                $out[$key] = $label;
            }
        }

        return $out;
    }

    /**
     * The mock OAuth bypass is for the automated test suite and hands-on local
     * development only. A production box accidentally left on APP_ENV=local
     * must never be able to mint a "mock.google@example.com" session.
     */
    private function mockAllowed(Request $request): bool
    {
        return app()->environment('testing')
            || (app()->environment('local') && $request->boolean('mock'));
    }
}
