<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PlatformSystem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $config = $this->config($provider);
        abort_unless($config['enabled'] && filled($config['client_id']) && filled($config['client_secret']), 404);
        $state = Str::random(40);
        $request->session()->put('social_oauth_state', $state);
        $params = ['client_id' => $config['client_id'], 'redirect_uri' => route('social.callback', $provider), 'response_type' => 'code', 'state' => $state, 'scope' => $provider === 'google' ? 'openid email profile' : 'email,public_profile'];
        $url = $provider === 'google' ? 'https://accounts.google.com/o/oauth2/v2/auth' : 'https://www.facebook.com/v21.0/dialog/oauth';

        return redirect()->away($url.'?'.http_build_query($params));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(hash_equals((string) $request->session()->pull('social_oauth_state'), (string) $request->query('state')), 419);
        $config = $this->config($provider);
        $tokenUrl = $provider === 'google' ? 'https://oauth2.googleapis.com/token' : 'https://graph.facebook.com/v21.0/oauth/access_token';
        $token = Http::asForm()->post($tokenUrl, ['client_id' => $config['client_id'], 'client_secret' => $config['client_secret'], 'redirect_uri' => route('social.callback', $provider), 'code' => $request->query('code'), 'grant_type' => 'authorization_code'])->throw()->json('access_token');
        $profile = $provider === 'google' ? Http::withToken($token)->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json() : Http::get('https://graph.facebook.com/me', ['fields' => 'id,name,email', 'access_token' => $token])->throw()->json();
        $email = strtolower((string) ($profile['email'] ?? ''));
        abort_if($email === '', 422, 'The social account did not provide an email address.');
        if ($user = User::where('email', $email)->first()) {
            Auth::guard('web')->login($user, true);

            return redirect()->route('tenant.dashboard');
        }
        session(['social_registration' => ['name' => $profile['name'] ?? '', 'email' => $email]]);

        return redirect()->route('tenant.register');
    }

    private function config(string $provider): array
    {
        abort_unless(in_array($provider, ['google', 'facebook'], true), 404);

        return ['enabled' => filter_var(PlatformSystem::get("social_{$provider}_enabled", false), FILTER_VALIDATE_BOOLEAN), 'client_id' => (string) PlatformSystem::get("social_{$provider}_client_id", ''), 'client_secret' => (string) PlatformSystem::get("social_{$provider}_client_secret", '')];
    }
}
