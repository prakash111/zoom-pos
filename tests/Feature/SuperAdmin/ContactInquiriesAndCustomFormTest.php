<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\Inquiries\Index as InquiriesIndex;
use App\Livewire\SuperAdmin\Settings\Index as SettingsIndex;
use App\Models\ContactInquiry;
use App\Services\ContactFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class ContactInquiriesAndCustomFormTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        file_put_contents(storage_path('installed'), '{}');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('installed'));
        parent::tearDown();
    }

    public function test_superadmin_can_access_inquiries_page(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->get(route('superadmin.inquiries.index'));
        $response->assertOk();
        $response->assertSee('Web Inquiries & Contact Form');
        $response->assertSee('Inquiries Received from Web');
    }

    public function test_public_contact_page_renders_dynamic_form(): void
    {
        $response = $this->get('/contact');
        $response->assertOk();
        $response->assertSee('Get in Touch');
        $response->assertSee('name="name"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="message"', false);
    }

    public function test_visitor_submission_with_custom_fields_persists_and_shows_in_superadmin(): void
    {
        // 1. Configure custom fields including a custom one
        ContactFormService::saveFields([
            [
                'id' => 'name',
                'name' => 'name',
                'label' => 'Full Name',
                'type' => 'text',
                'required' => true,
                'width' => 'half',
            ],
            [
                'id' => 'email',
                'name' => 'email',
                'label' => 'Business Email',
                'type' => 'email',
                'required' => true,
                'width' => 'half',
            ],
            [
                'id' => 'custom_revenue',
                'name' => 'monthly_revenue',
                'label' => 'Estimated Monthly Revenue',
                'type' => 'select',
                'options' => '$5,000 - $25,000, $25,000 - $100,000, $100,000+',
                'required' => false,
                'width' => 'half',
            ],
            [
                'id' => 'message',
                'name' => 'message',
                'label' => 'Message',
                'type' => 'textarea',
                'required' => true,
                'width' => 'full',
            ],
        ]);

        // 2. Submit form from public endpoint
        $postData = [
            'name' => 'Alice Merchant',
            'email' => 'alice@retailchain.test',
            'monthly_revenue' => '$25,000 - $100,000',
            'message' => 'We need 5 counter terminals and cloud sync.',
        ];

        $response = $this->post('/contact', $postData);
        $response->assertRedirect();

        // 3. Verify inquiry record in DB
        $inquiry = ContactInquiry::where('email', 'alice@retailchain.test')->firstOrFail();
        $this->assertSame('Alice Merchant', $inquiry->name);
        $this->assertSame(ContactInquiry::STATUS_NEW, $inquiry->status);
        $this->assertNotNull($inquiry->custom_fields);
        $this->assertArrayHasKey('monthly_revenue', $inquiry->custom_fields);
        $this->assertSame('$25,000 - $100,000', $inquiry->custom_fields['monthly_revenue']['value']);
        $this->assertSame('Estimated Monthly Revenue', $inquiry->custom_fields['monthly_revenue']['label']);

        // 4. Test Superadmin Inquiries Livewire component
        $this->actingAsSuperAdmin();

        Livewire::test(InquiriesIndex::class)
            ->assertSee('Alice Merchant')
            ->assertSee('alice@retailchain.test')
            ->call('viewInquiry', $inquiry->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Estimated Monthly Revenue')
            ->assertSee('$25,000 - $100,000');

        // Inquiry was marked as read upon viewing
        $inquiry->refresh();
        $this->assertSame(ContactInquiry::STATUS_READ, $inquiry->status);

        // 5. Change status to replied
        Livewire::test(InquiriesIndex::class)
            ->call('updateStatus', $inquiry->id, ContactInquiry::STATUS_REPLIED);

        $inquiry->refresh();
        $this->assertSame(ContactInquiry::STATUS_REPLIED, $inquiry->status);

        // 6. Delete inquiry
        Livewire::test(InquiriesIndex::class)
            ->call('deleteInquiry', $inquiry->id);

        $this->assertDatabaseMissing('contact_inquiries', ['id' => $inquiry->id]);
    }

    public function test_superadmin_can_add_and_edit_custom_fields_in_builder(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(InquiriesIndex::class)
            ->set('tab', 'builder')
            ->set('fieldLabel', 'Outlets Count')
            ->set('fieldName', 'outlets_count')
            ->set('fieldType', 'number')
            ->set('fieldPlaceholder', 'e.g. 3')
            ->set('fieldWidth', 'half')
            ->set('fieldRequired', true)
            ->call('saveField')
            ->assertDispatched('toast');

        $fields = ContactFormService::getFields();
        $outletField = collect($fields)->firstWhere('name', 'outlets_count');

        $this->assertNotNull($outletField);
        $this->assertSame('Outlets Count', $outletField['label']);
        $this->assertSame('number', $outletField['type']);
        $this->assertTrue($outletField['required']);

        // Check that public contact page now contains the new field
        $response = $this->get('/contact');
        $response->assertSee('Outlets Count');
        $response->assertSee('name="outlets_count"', false);
    }

    public function test_theme_switching_saves_and_persists_across_all_five_themes(): void
    {
        $this->actingAsSuperAdmin();

        $themes = ['theme_fast', 'theme_modern', 'theme_enterprise', 'theme_minimal', 'theme_dark_studio'];

        foreach ($themes as $theme) {
            Livewire::test(SettingsIndex::class)
                ->call('setLandingTheme', $theme)
                ->assertDispatched('toast');

            $this->assertSame($theme, setting('landing_page_theme'));
            $this->assertSame($theme, get_appearance_settings()['theme']);
        }

        // Test saving theme via saveBranding button
        Livewire::test(SettingsIndex::class)
            ->set('landingTheme', 'theme_enterprise')
            ->call('saveBranding');

        $this->assertSame('theme_enterprise', setting('landing_page_theme'));

        // Reset back to fast
        Livewire::test(SettingsIndex::class)
            ->call('setLandingTheme', 'theme_fast');
        $this->assertSame('theme_fast', setting('landing_page_theme'));
    }
}
