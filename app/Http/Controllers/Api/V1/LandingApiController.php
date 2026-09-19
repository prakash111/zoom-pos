<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DynamicSetting;
use App\Models\PlatformBranding;
use App\Models\SaaSPlan;
use Illuminate\Http\JsonResponse;

class LandingApiController extends Controller
{
    /**
     * Public Landing Page Data API consumed by Flutter web, desktop, and mobile.
     * GET /api/v1/pos/public/landing
     * GET /api/public/landing
     */
    public function show(): JsonResponse
    {
        $branding = PlatformBranding::current();
        $theme = setting('landing_page_theme', 'theme_fast');

        $plans = SaaSPlan::where('is_active', true)
            ->orderBy('price')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'billing_period' => $p->billing_period ?? 'monthly',
                'description' => $p->description,
                'features' => (array) ($p->features ?? []),
                'is_featured' => (bool) ($p->is_featured ?? false),
            ]);

        $bannerUrl = $branding->landing_hero_banner_image_url
            ?: (DynamicSetting::get('auth_banner_image_url') ?: null);

        $authBannerUrl = DynamicSetting::get('auth_banner_image_url') ?: null;
        $showAuthBanner = (bool) DynamicSetting::get('show_auth_banner', false);

        return response()->json([
            'success' => true,
            'theme' => $theme,
            'appearance' => get_appearance_settings(),
            'branding' => [
                'platform_name' => $branding->platform_name ?: config('app.name', 'Smart Inventory & Sales'),
                'logo_url' => $branding->getLogoPublicUrl(),
                'favicon_url' => $branding->getFaviconPublicUrl(),
                'primary_color' => $branding->landing_primary_color ?: ($branding->primary_color ?: '#4f46e5'),
                'secondary_color' => $branding->secondary_color ?: '#0F172A',
                'accent_color' => $branding->landing_accent_color ?: '#d7f24e',
                'landing_dark_bg' => setting('landing_dark_bg', '#0b0f19'),
                'support_phone' => $branding->support_phone ?: '+918535075196',
                'support_whatsapp' => $branding->support_phone ?: '+918535075196',
                'support_email' => $branding->support_email ?: 'support@zoomnearby.com',
                'auth_banner_image_url' => $authBannerUrl,
                'show_auth_banner' => $showAuthBanner,
            ],
            'hero' => [
                'badge' => $branding->getHeroBadge(),
                'title' => $branding->getHeroTitle(),
                'subtitle' => $branding->getHeroSubtitle(),
                'cta_primary_text' => $branding->getHeroCtaPrimaryText(),
                'cta_primary_url' => $branding->getHeroCtaPrimaryUrl(),
                'cta_secondary_text' => $branding->getHeroCtaSecondaryText(),
                'cta_secondary_url' => $branding->getHeroCtaSecondaryUrl(),
                'banner_image_url' => $bannerUrl,
            ],
            'hardware' => $branding->landingHardware(),
            'solutions' => $branding->landingSolutionsList(),
            'features' => $branding->landingFeatures(),
            'stats' => $branding->landingStatsList(),
            'plans' => $plans,
            'testimonials' => $branding->landingTestimonials(),
            'faqs' => $branding->landingFaqs(),
            'downloads' => [
                'playstore_enabled' => (bool) $branding->landing_playstore_enabled,
                'playstore_url' => $branding->playStoreLink(),
                'windows_enabled' => (bool) $branding->landing_windows_enabled,
                'windows_url' => $branding->windowsAppLink(),
            ],
            'contact' => [
                'fields' => get_contact_form_fields(),
                'settings' => get_contact_form_settings(),
                'support_phone' => $branding->support_phone ?: '+918535075196',
                'support_whatsapp' => $branding->support_phone ?: '+918535075196',
                'support_email' => $branding->support_email ?: 'support@zoomnearby.com',
            ],
        ]);
    }
}
