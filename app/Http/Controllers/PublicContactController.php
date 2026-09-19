<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\PlatformBranding;
use App\Services\ContactFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles the marketing-site contact page and form submissions.
 *
 * Supports arbitrary custom form fields configured by Superadmin,
 * Honeypot anti-spam protection, responsive dynamic rendering,
 * and AJAX / standard HTTP redirects.
 */
class PublicContactController extends Controller
{
    public function index(Request $request): View
    {
        $branding = PlatformBranding::current();
        $contactSettings = ContactFormService::getSettings();
        $fields = ContactFormService::getFields();
        $appearance = get_appearance_settings();
        $footerPages = Page::where('is_active', true)
            ->where('show_in_footer', true)
            ->orderBy('title')
            ->get();

        return view('public.contact', [
            'branding' => $branding,
            'contactSettings' => $contactSettings,
            'fields' => $fields,
            'appearance' => $appearance,
            'footerPages' => $footerPages,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // Honeypot: real users never fill a hidden field. Silently accept so a
        // bot cannot tell it was rejected.
        if (filled($request->input('company_website'))) {
            return $this->done($request, 'Thanks — your message has been received.');
        }

        $rules = ContactFormService::buildValidationRules();
        $validated = $request->validate($rules);

        $inquiry = ContactFormService::processSubmission($validated, $request->ip());

        $settings = ContactFormService::getSettings();
        $successMsg = $settings['success_message'] ?: "Message sent — we'll get back to you shortly.";

        return $this->done($request, $successMsg);
    }

    private function done(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->boolean('ajax')) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return back()->with('contact_success', $message);
    }
}
