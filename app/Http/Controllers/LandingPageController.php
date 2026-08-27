<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PlatformBranding;
use App\Models\SaaSPlan;

class LandingPageController extends Controller
{
    /**
     * Display the dynamic landing page matching the configured theme.
     */
    public function index()
    {
        if (auth('platform_web')->check()) {
            return redirect('/superadmin');
        }
        if (auth('web')->check()) {
            return redirect('/tenant');
        }

        $branding = PlatformBranding::current();
        if (! $branding->landing_page_enabled) {
            return redirect('/tenant/login');
        }

        $theme = cache()->rememberForever('app_landing_page_theme', function () {
            return setting('landing_page_theme', 'theme_modern');
        });

        $view = "landing.themes.{$theme}";
        if (! view()->exists($view)) {
            $view = 'landing.themes.theme_modern';
        }

        return view($view, [
            'branding' => $branding,
            'page' => $branding->landingPage,
            'footerPages' => Page::where('is_active', true)->where('show_in_footer', true)->orderBy('title')->get(),
            'plans' => SaaSPlan::where('is_active', true)->orderBy('price')->get(),
            'appearance' => get_appearance_settings(),
        ]);
    }
}
