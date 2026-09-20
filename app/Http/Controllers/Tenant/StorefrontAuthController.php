<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class StorefrontAuthController extends Controller
{
    protected function resolveCompany(Request $request): ?Company
    {
        return (new StorefrontController())->resolveCompany($request);
    }

    public function redirectToProvider(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, ['google']), 404);

        $company = $this->resolveCompany($request);
        abort_unless($company, 404, 'Store not found');

        // Check if tenant has Google login enabled
        if (! $company->enable_google_login) {
            return redirect()->to(url('/store?store=' . $company->slug . '&auth_error=' . urlencode(__('Google login is not enabled for this store.'))));
        }

        // Configure Socialite dynamically if tenant provided custom credentials
        if (! empty($company->google_client_id) && ! empty($company->google_client_secret)) {
            Config::set('services.google.client_id', $company->google_client_id);
            Config::set('services.google.client_secret', $company->google_client_secret);
        }

        Config::set('services.google.redirect', url('/store/auth/google/callback?store=' . $company->slug));

        session(['storefront_auth_company' => $company->slug]);

        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, ['google']), 404);

        $storeSlug = $request->query('store') ?: session('storefront_auth_company');
        $company = Company::withoutGlobalScopes()->where('slug', $storeSlug)->first() ?: Company::withoutGlobalScopes()->first();
        abort_unless($company, 404, 'Store not found');

        if (! empty($company->google_client_id) && ! empty($company->google_client_secret)) {
            Config::set('services.google.client_id', $company->google_client_id);
            Config::set('services.google.client_secret', $company->google_client_secret);
        }
        Config::set('services.google.redirect', url('/store/auth/google/callback?store=' . $company->slug));

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            return redirect()->to(url('/store?store=' . $company->slug . '&auth_error=' . urlencode('Social login failed: ' . $e->getMessage())));
        }

        $email = $socialUser->getEmail();
        $name = $socialUser->getName() ?: 'Customer';

        $customer = Customer::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('email', $email)
            ->first();

        $token = Str::random(64);

        if (! $customer) {
            $customer = Customer::withoutGlobalScope('company')->create([
                'company_id' => $company->id,
                'name' => $name,
                'email' => $email,
                'auth_token' => $token,
                'source' => 'google_oauth',
            ]);
        } else {
            $customer->auth_token = $token;
            $customer->save();
        }

        $redirectUrl = url('/store?store=' . $company->slug . '&auth_success=1&token=' . $token . '&cust_id=' . $customer->id . '&cust_name=' . urlencode($customer->name) . '&cust_email=' . urlencode($customer->email ?: ''));

        return redirect()->to($redirectUrl);
    }
}
