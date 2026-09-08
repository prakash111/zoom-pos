<?php

namespace App\Http\Controllers;

use App\Mail\ContactInquiryMailable;
use App\Models\ContactInquiry;
use App\Models\PlatformBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Handles the marketing-site "Questions before you sign up?" form.
 *
 * The public pages don't load Livewire, so the contact form is a plain
 * <form method="POST"> that works with JavaScript disabled and is enhanced
 * with a fetch() submit for an inline success state. Throttled at the route.
 */
class PublicContactController extends Controller
{
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // Honeypot: real users never fill a hidden field. Silently accept so a
        // bot can't tell it was rejected.
        if (filled($request->input('company_website'))) {
            return $this->done($request, 'Thanks — your message has been received.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'store_type' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $inquiry = ContactInquiry::create($validated);

        $recipient = PlatformBranding::current()->support_email ?: config('mail.from.address');

        if ($recipient) {
            try {
                Mail::to($recipient)->send(new ContactInquiryMailable($inquiry));
            } catch (\Throwable $e) {
                // The inquiry is already stored — a mail transport hiccup must
                // not fail the visitor's submission.
                Log::warning('Failed to send contact inquiry notification email.', ['error' => $e->getMessage()]);
            }
        }

        return $this->done($request, "Message sent — we'll get back to you shortly.");
    }

    private function done(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->boolean('ajax')) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return back()->with('contact_success', $message);
    }
}
