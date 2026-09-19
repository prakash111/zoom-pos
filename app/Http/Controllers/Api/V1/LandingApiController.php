<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DynamicSetting;
use App\Models\Plan;
use App\Models\PlatformBranding;
use App\Services\ContactFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LandingApiController extends Controller
{
    /**
     * Public Landing Page Data API consumed by Flutter web, desktop, and mobile.
     * GET /api/v1/pos/public/landing
     * GET /api/v1/public/landing-config
     * GET /api/public/landing
     */
    public function show(): JsonResponse
    {
        $branding = PlatformBranding::current();
        $theme = setting('landing_page_theme', 'theme_fast');

        $plans = $this->getNormalizedPlans();

        $bannerUrl = $branding->landing_hero_banner_image_url
            ?: (DynamicSetting::get('auth_banner_image_url') ?: null);

        $authBannerUrl = DynamicSetting::get('auth_banner_image_url') ?: null;
        $showAuthBanner = (bool) DynamicSetting::get('show_auth_banner', false);

        return response()->json([
            'success' => true,
            'landing_page_enabled' => (bool) $branding->landing_page_enabled,
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
                'landing_page_enabled' => (bool) $branding->landing_page_enabled,
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

    /**
     * Submit Contact Inquiry from Flutter landing page or public website.
     * POST /api/v1/public/contact-us
     * POST /api/v1/pos/public/contact
     */
    public function submitContact(Request $request): JsonResponse
    {
        $rules = ContactFormService::buildValidationRules();
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide valid contact information.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $inquiry = ContactFormService::processSubmission($validator->validated(), $request->ip());
            $settings = ContactFormService::getSettings();

            return response()->json([
                'success' => true,
                'message' => $settings['success_message'] ?? 'Thank you for reaching out! We will contact you shortly.',
                'inquiry_id' => $inquiry->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to send your inquiry at this moment. Please try again or reach out on WhatsApp.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Public Subscription Plans list.
     * GET /api/v1/subscription/plans
     */
    public function plans(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'plans' => $this->getNormalizedPlans(),
        ]);
    }

    /**
     * Get normalized plan items with dynamic limits, extensions, and feature badges.
     */
    private function getNormalizedPlans(): array
    {
        return Plan::where('active', true)
            ->orderBy('price')
            ->get()
            ->map(function (Plan $p) {
                $rawFeatures = is_array($p->features) ? $p->features : (is_string($p->features) ? (json_decode($p->features, true) ?: []) : []);
                $featureList = [];
                foreach ($rawFeatures as $k => $v) {
                    if (is_numeric($k) && is_string($v)) {
                        $featureList[] = $v;
                    } elseif ($v === true || $v === 1 || $v === '1') {
                        $featureList[] = is_string($k) ? str_replace('_', ' ', ucfirst($k)) : (string) $v;
                    } elseif (is_string($v) && ! empty($v)) {
                        $featureList[] = "$k: $v";
                    }
                }

                $limits = is_array($p->limits) ? $p->limits : [];
                $extensions = is_array($p->extensions) ? $p->extensions : [];

                return [
                    'id' => $p->name,
                    'name' => $p->display_name ?: ucfirst($p->name),
                    'display_name' => $p->display_name ?: ucfirst($p->name),
                    'price' => (float) ($p->price ?? 0),
                    'currency' => $p->currency ?: 'USD',
                    'billing_period' => $p->billing_cycle ?? 'monthly',
                    'billing_cycle' => $p->billing_cycle ?? 'monthly',
                    'duration_days' => (int) ($p->duration_days ?? 30),
                    'description' => null,
                    'invoice_limit' => (int) ($p->invoice_limit ?? ($limits['invoices'] ?? -1)),
                    'device_limit' => (int) ($p->device_limit ?? ($limits['dispositivos'] ?? -1)),
                    'staff_limit' => (int) ($p->staff_limit ?? ($limits['usuarios'] ?? -1)),
                    'extensions' => array_values($extensions),
                    'features' => array_values(array_unique($featureList)),
                    'limits' => $limits,
                    'is_featured' => false,
                ];
            })
            ->values()
            ->all();
    }
}
