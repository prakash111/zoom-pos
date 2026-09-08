<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\Public\ContactForm;
use App\Livewire\SuperAdmin\Branding\Index as BrandingIndex;
use App\Livewire\SuperAdmin\Pages\Create as PagesCreate;
use App\Livewire\SuperAdmin\Pages\Edit as PagesEdit;
use App\Livewire\SuperAdmin\Pages\Index as PagesIndex;
use App\Livewire\SuperAdmin\Settings\Index as SettingsIndex;
use App\Mail\ContactInquiryMailable;
use App\Models\ContactInquiry;
use App\Models\Page;
use App\Models\PlatformBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class PagesAndLandingPageTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_superadmin_can_create_edit_and_delete_a_page(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(PagesCreate::class)
            ->set('title', 'About Us')
            ->set('content', '<p>Hello world</p>')
            ->call('save');

        $page = Page::where('title', 'About Us')->firstOrFail();
        $this->assertSame('about-us', $page->slug);
        $this->assertSame('<p>Hello world</p>', $page->content);
        $this->assertTrue($page->is_active);

        // Duplicate title auto-disambiguates the slug.
        Livewire::test(PagesCreate::class)
            ->set('title', 'About Us')
            ->set('content', '<p>Second one</p>')
            ->call('save');

        $second = Page::where('content', '<p>Second one</p>')->firstOrFail();
        $this->assertSame('about-us-2', $second->slug);

        Livewire::test(PagesEdit::class, ['page' => $page])
            ->assertSet('title', 'About Us')
            ->set('title', 'About Our Company')
            ->set('slug', 'about-our-company')
            ->call('save');

        $page->refresh();
        $this->assertSame('About Our Company', $page->title);
        $this->assertSame('about-our-company', $page->slug);

        Livewire::test(PagesIndex::class)->call('delete', $page->id);
        $this->assertNull(Page::find($page->id));
    }

    public function test_public_page_route_respects_is_active(): void
    {
        $active = Page::create(['title' => 'Terms', 'slug' => 'terms', 'content' => '<p>Terms text</p>', 'is_active' => true]);
        $inactive = Page::create(['title' => 'Draft', 'slug' => 'draft-page', 'content' => '<p>Draft</p>', 'is_active' => false]);

        $this->get(route('pages.show', $active->slug))->assertOk()->assertSee('Terms text');
        $this->get(route('pages.show', $inactive->slug))->assertNotFound();
    }

    public function test_root_route_falls_back_to_login_redirect_when_landing_page_disabled(): void
    {
        $this->get('/')->assertRedirect('/tenant/login');
    }

    public function test_enabling_landing_page_in_branding_renders_it_at_root(): void
    {
        $this->actingAsSuperAdmin();

        $page = Page::create(['title' => 'Home', 'slug' => 'home', 'content' => '<p>Welcome to our platform</p>', 'is_active' => true]);

        Livewire::test(BrandingIndex::class)
            ->set('landingPageEnabled', true)
            ->set('landingPageId', $page->id)
            ->call('save');

        $branding = PlatformBranding::current();
        $this->assertTrue($branding->landing_page_enabled);
        $this->assertSame($page->id, $branding->landing_page_id);

        // A guest (not the superadmin session above) hitting `/` sees the landing page.
        auth('platform_web')->logout();
        $response = $this->get('/');
        $response->assertOk()
            ->assertSee('Welcome to our platform')
            ->assertSee('public-site', false)
            ->assertDontSee('app-global-loader', false)
            ->assertDontSee('fonts.googleapis.com', false)
            ->assertDontSee('resources/js/app.js', false)
            ->assertSee('public-navigation', false);
    }

    public function test_contact_form_stores_inquiry_and_sends_notification_email(): void
    {
        Mail::fake();

        PlatformBranding::current()->update(['support_email' => 'owner@example.com']);

        Livewire::test(ContactForm::class)
            ->set('name', 'Jane Prospect')
            ->set('email', 'jane@prospect.test')
            ->set('phone', '555-0100')
            ->set('subject', 'Pricing question')
            ->set('message', 'How does the restaurant mode pricing work?')
            ->call('submit')
            ->assertSet('submitted', true)
            ->assertSet('name', '');

        $inquiry = ContactInquiry::where('email', 'jane@prospect.test')->firstOrFail();
        $this->assertSame('Jane Prospect', $inquiry->name);
        $this->assertSame('How does the restaurant mode pricing work?', $inquiry->message);

        Mail::assertQueued(ContactInquiryMailable::class, function ($mail) use ($inquiry) {
            return $mail->inquiry->id === $inquiry->id
                && $mail->hasTo('owner@example.com');
        });
    }

    public function test_contact_form_requires_name_email_and_message(): void
    {
        Livewire::test(ContactForm::class)
            ->set('email', 'not-an-email')
            ->call('submit')
            ->assertHasErrors(['name', 'email', 'message']);

        $this->assertSame(0, ContactInquiry::count());
    }

    public function test_public_contact_endpoint_stores_inquiry_and_notifies(): void
    {
        Mail::fake();
        PlatformBranding::current()->update(['support_email' => 'owner@example.com']);

        $this->post('/contact', [
            'name' => 'Sam Lead',
            'email' => 'sam@lead.test',
            'store_type' => 'Retail Store',
            'message' => 'Do you support weighing scales at the counter?',
        ])->assertRedirect();

        $inquiry = ContactInquiry::where('email', 'sam@lead.test')->firstOrFail();
        $this->assertSame('Retail Store', $inquiry->store_type);
        Mail::assertQueued(ContactInquiryMailable::class);
    }

    public function test_public_contact_endpoint_validates_and_honours_the_honeypot(): void
    {
        $this->post('/contact', ['email' => 'nope'])
            ->assertSessionHasErrors(['name', 'email', 'message']);

        // Honeypot filled → silently accepted, nothing stored.
        $this->post('/contact', [
            'name' => 'Bot',
            'email' => 'bot@spam.test',
            'message' => 'buy cheap things now',
            'company_website' => 'http://spam.example',
        ])->assertRedirect();

        $this->assertSame(0, ContactInquiry::count());
    }

    public function test_superadmin_saves_download_links_and_section_meta(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SettingsIndex::class)
            ->set('platformName', 'Zoom POS')
            ->set('landingPlaystoreEnabled', true)
            ->set('landingPlaystoreUrl', 'https://play.google.com/store/apps/details?id=com.zoom.pos')
            ->set('landingWindowsEnabled', true)
            ->set('landingWindowsUrl', 'https://cdn.example.com/ZoomPOS-Setup.exe')
            ->set('sectionMeta.features.title', 'What you get')
            ->set('sectionMeta.downloads.subtitle', 'Grab the native app')
            ->set('sectionFaq', false)
            ->set('landingFaqs', [['q' => 'Is it fast?', 'a' => 'Yes, sub-second checkout.']])
            ->call('saveBranding')
            ->assertHasNoErrors();

        $b = PlatformBranding::current()->fresh();
        $this->assertSame('https://play.google.com/store/apps/details?id=com.zoom.pos', $b->playStoreLink());
        $this->assertSame('https://cdn.example.com/ZoomPOS-Setup.exe', $b->windowsAppLink());
        $this->assertTrue($b->hasAnyDownloadLink());
        $this->assertSame('What you get', $b->getSectionTitle('features', 'default'));
        $this->assertSame('Grab the native app', $b->getSectionSubtitle('downloads', 'default'));
        $this->assertFalse($b->isSectionEnabled('faq'));
        $this->assertSame([['q' => 'Is it fast?', 'a' => 'Yes, sub-second checkout.']], $b->landingFaqs());
    }

    public function test_download_link_is_hidden_when_disabled_or_blank(): void
    {
        $b = PlatformBranding::current();

        $b->update(['landing_playstore_url' => 'https://play.google.com/x', 'landing_playstore_enabled' => false]);
        $this->assertNull($b->fresh()->playStoreLink());

        $b->update(['landing_playstore_enabled' => true, 'landing_playstore_url' => null]);
        $this->assertNull($b->fresh()->playStoreLink());

        $b->update(['landing_playstore_url' => 'https://play.google.com/x', 'landing_playstore_enabled' => true]);
        $this->assertNotNull($b->fresh()->playStoreLink());
    }

    public function test_landing_page_renders_download_buttons_when_enabled(): void
    {
        $page = Page::create(['title' => 'Home', 'slug' => 'home', 'content' => 'Welcome to our platform', 'is_active' => true]);
        PlatformBranding::current()->update([
            'landing_page_enabled' => true,
            'landing_page_id' => $page->id,
            'landing_playstore_url' => 'https://play.google.com/store/apps/details?id=com.zoom.pos',
            'landing_playstore_enabled' => true,
        ]);

        $response = $this->get('/');
        $response->assertOk()
            ->assertSee('https://play.google.com/store/apps/details?id=com.zoom.pos', false)
            ->assertSee('Google Play')
            ->assertSee('id="download"', false);

        // Windows link stays hidden while disabled.
        $response->assertDontSee('DOWNLOAD FOR');
    }
}
