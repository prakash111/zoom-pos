<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PlatformBranding;
use App\Models\SaaSPlan;
use App\Support\Desktop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LandingPageController extends Controller
{
    /** Default theme for a fresh install — the lightweight, fast-loading one. */
    private const DEFAULT_THEME = 'theme_fast';

    /**
     * Display the dynamic landing page matching the configured theme.
     *
     * The rendered HTML for a guest is cached whole (keyed by theme + locale +
     * a version counter bumped whenever branding, plans, pages or menus
     * change) so a visit to "/" is a single cache read instead of a
     * multi-section Blade render with its translation and query load.
     */
    public function index(Request $request)
    {
        if (Desktop::isRunning()) {
            return auth('web')->check()
                ? redirect('/tenant')
                : redirect('/tenant/login');
        }

        if (auth('platform_web')->check()) {
            return redirect('/superadmin');
        }
        if (auth('web')->check()) {
            return redirect('/tenant');
        }

        // If visiting a tenant workspace domain/subdomain or tenant context is bound, serve Tenant E-Commerce Storefront
        $storefrontController = app(\App\Http\Controllers\Tenant\StorefrontController::class);
        $host = strtolower($request->getHost());
        $baseHost = parse_url(config('app.url'), PHP_URL_HOST);
        $isTenantHost = ($baseHost && str_ends_with($host, '.'.$baseHost) && $host !== $baseHost)
            || ($baseHost && $host !== $baseHost && $host !== 'localhost' && $host !== '127.0.0.1');

        if (app()->bound('tenant.company_id') || $isTenantHost) {
            $company = $storefrontController->resolveCompany($request);
            if ($company) {
                return $storefrontController->index($request);
            }
        }

        $branding = PlatformBranding::current();
        if (! $branding->landing_page_enabled) {
            return redirect('/tenant/login');
        }

        // If homepage is configured to display a static CMS page (Permalink Landing Page Setup)
        $homepageMode = data_get($branding->landing_content, 'homepage_mode', 'modular');
        if ($homepageMode === 'static_page' && $branding->landing_page_id && $branding->landingPage && $branding->landingPage->is_active) {
            return response()->view('public.page', [
                'page' => $branding->landingPage,
                'branding' => $branding,
                'footerPages' => Page::where('is_active', true)->where('show_in_footer', true)->orderBy('title')->get(),
            ]);
        }

        $theme = cache()->rememberForever('app_landing_page_theme', function () {
            return setting('landing_page_theme', self::DEFAULT_THEME);
        });

        $view = "landing.themes.{$theme}";
        if (filled(data_get($branding->landing_content ?? [], 'html'))) {
            $view = 'landing.custom';
        }
        if (! view()->exists($view)) {
            $view = 'landing.themes.'.self::DEFAULT_THEME;
        }

        // Don't serve (or populate) the shared cache for a render that carries
        // per-visitor state: a contact-form validation bounce or success flash.
        $bypassCache = app()->environment('testing')
            || $request->session()->hasOldInput()
            || $request->session()->has('contact_success')
            || $request->session()->has('errors');

        $render = fn () => view($view, [
            'branding' => $branding,
            'page' => $branding->landingPage,
            'footerPages' => Page::where('is_active', true)->where('show_in_footer', true)->orderBy('title')->get(),
            'plans' => SaaSPlan::where('is_active', true)->orderBy('price')->get(),
            'appearance' => get_appearance_settings(),
        ])->render();

        if ($bypassCache) {
            return response($render());
        }

        $version = Cache::get('landing_page_cache_version', 1);
        // Cached HTML includes Vite asset URLs. A new build must get fresh
        // HTML because the previous hashed stylesheet may no longer exist.
        $manifestPath = public_path('build/manifest.json');
        $assetVersion = is_file($manifestPath) ? hash_file('xxh128', $manifestPath) : 'dev';
        $key = "landing_page:html:v{$version}:{$theme}:{$assetVersion}:".app()->getLocale();

        $html = Cache::remember($key, now()->addHour(), $render);

        return response($html);
    }
}
