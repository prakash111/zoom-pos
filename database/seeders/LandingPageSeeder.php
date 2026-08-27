<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PlatformBranding;
use Illuminate\Database\Seeder;

/**
 * Publishes the public marketing homepage: creates the canonical "Home"
 * page record the landing route requires, standard legal pages for the
 * footer, and flips PlatformBranding on so visiting "/" serves the
 * marketing landing page instead of redirecting to the tenant login.
 */
class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        $branding = PlatformBranding::current();
        $platformName = $branding->platform_name;

        $home = Page::query()->firstOrCreate(['slug' => 'home'], [
            'title' => 'Home',
            'content' => '',
            'meta_description' => $platformName.' — an all-in-one POS, inventory, and finance platform for retail and restaurant businesses.',
            'is_active' => true,
            'show_in_footer' => false,
        ]);

        Page::query()->firstOrCreate(['slug' => 'terms-of-service'], [
            'title' => 'Terms of Service',
            'content' => $this->termsContent($platformName),
            'meta_description' => 'Terms of Service for '.$platformName.'.',
            'is_active' => true,
            'show_in_footer' => true,
        ]);

        Page::query()->firstOrCreate(['slug' => 'privacy-policy'], [
            'title' => 'Privacy Policy',
            'content' => $this->privacyContent($platformName),
            'meta_description' => 'Privacy Policy for '.$platformName.'.',
            'is_active' => true,
            'show_in_footer' => true,
        ]);

        Page::query()->firstOrCreate(['slug' => 'refund-policy'], [
            'title' => 'Refund & Cancellation Policy',
            'content' => $this->refundContent($platformName),
            'meta_description' => 'Refund and cancellation policy for '.$platformName.'.',
            'is_active' => true,
            'show_in_footer' => true,
        ]);

        $branding->update([
            'landing_page_enabled' => true,
            'landing_page_id' => $home->id,
        ]);
    }

    private function termsContent(string $name): string
    {
        $date = now()->format('F j, Y');

        return <<<HTML
            <p><em>Last updated: {$date}</em></p>
            <p>These Terms of Service ("Terms") govern access to and use of {$name} (the "Service"), operated by us. By creating an account or using the Service, you agree to these Terms.</p>

            <h2>1. Using the Service</h2>
            <p>You must provide accurate registration information and keep your account credentials secure. You are responsible for all activity carried out under your account, including actions taken by staff accounts you create.</p>

            <h2>2. Subscriptions &amp; Billing</h2>
            <p>Paid plans are billed in advance on a recurring basis (monthly or annually, as selected at checkout) until cancelled. Trial periods, where offered, convert to a paid subscription unless cancelled before the trial ends.</p>

            <h2>3. Your Data</h2>
            <p>You retain ownership of the product, sales, customer, and financial data you enter into the Service. We process this data solely to provide and support the Service, as described in our Privacy Policy.</p>

            <h2>4. Acceptable Use</h2>
            <p>You agree not to misuse the Service, attempt to disrupt its infrastructure, or use it to process unlawful transactions.</p>

            <h2>5. Availability</h2>
            <p>We work to keep the Service available at all times and target the uptime commitment published on our pricing page, but the Service is provided "as is" without warranties of uninterrupted availability.</p>

            <h2>6. Termination</h2>
            <p>You may cancel your subscription at any time from your account settings. We may suspend or terminate accounts that violate these Terms.</p>

            <h2>7. Changes</h2>
            <p>We may update these Terms from time to time. Continued use of the Service after an update constitutes acceptance of the revised Terms.</p>

            <h2>8. Contact</h2>
            <p>Questions about these Terms can be sent through our contact form.</p>

            <p><em>This page is a general-purpose starting template and does not constitute legal advice. Please have it reviewed by qualified counsel before relying on it for your business.</em></p>
            HTML;
    }

    private function privacyContent(string $name): string
    {
        $date = now()->format('F j, Y');

        return <<<HTML
            <p><em>Last updated: {$date}</em></p>
            <p>This Privacy Policy explains how {$name} ("we", "us") collects, uses, and protects information when you use the Service.</p>

            <h2>1. Information We Collect</h2>
            <ul>
                <li>Account information you provide: name, business email, phone number, and store details.</li>
                <li>Business data you enter: products, sales, customers, invoices, and related records.</li>
                <li>Usage data: device, browser, and log information collected automatically to keep the Service secure and reliable.</li>
            </ul>

            <h2>2. How We Use Information</h2>
            <p>We use collected information to operate and improve the Service, process transactions, send service notifications, respond to support and contact requests, and maintain security.</p>

            <h2>3. Sharing of Information</h2>
            <p>We do not sell your data. Information may be shared with service providers who help us operate the platform (such as email delivery and hosting providers), strictly to the extent necessary to provide the Service.</p>

            <h2>4. Data Retention</h2>
            <p>Business data is retained for as long as your account is active. You may request export or deletion of your data by contacting us.</p>

            <h2>5. Security</h2>
            <p>We use reasonable technical and organizational measures to protect data, including encrypted storage of sensitive credentials and access controls on staff accounts.</p>

            <h2>6. Your Choices</h2>
            <p>You can review and update your account information at any time, and may request deletion of your account and associated data, subject to legal record-keeping requirements.</p>

            <h2>7. Contact</h2>
            <p>For privacy questions or data requests, please reach out through our contact form.</p>

            <p><em>This page is a general-purpose starting template and does not constitute legal advice. Please have it reviewed by qualified counsel before relying on it for your business.</em></p>
            HTML;
    }

    private function refundContent(string $name): string
    {
        $date = now()->format('F j, Y');

        return <<<HTML
            <p><em>Last updated: {$date}</em></p>
            <p>This policy describes how cancellations and refunds are handled for {$name} subscriptions.</p>

            <h2>1. Free Trial</h2>
            <p>New accounts start on a free trial. You will not be charged during the trial period, and you may cancel at any time before it ends at no cost.</p>

            <h2>2. Cancelling a Subscription</h2>
            <p>You can cancel a paid subscription at any time from your account settings. Cancellation stops future billing; access continues until the end of the current billing period.</p>

            <h2>3. Refunds</h2>
            <p>Subscription fees are generally non-refundable for the current billing period once charged. If you believe you were billed in error, contact us within 14 days of the charge and we will review the request in good faith.</p>

            <h2>4. Downgrades &amp; Upgrades</h2>
            <p>Plan changes take effect according to the billing cycle in progress; any prorated adjustment will be reflected on your next invoice.</p>

            <h2>5. Contact</h2>
            <p>For billing questions, please reach out through our contact form and we'll be glad to help.</p>

            <p><em>This page is a general-purpose starting template and does not constitute legal advice. Please have it reviewed by qualified counsel before relying on it for your business.</em></p>
            HTML;
    }
}
