<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\SocialAuthController;
use App\Models\PlatformSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialLoginConfigTest extends TestCase
{
    use RefreshDatabase;

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

    private function setGoogle(string $id, string $secret, bool $enabled = true): void
    {
        PlatformSystem::set('social_google_enabled', $enabled ? '1' : '0');
        PlatformSystem::set('social_google_client_id', $id);
        PlatformSystem::set('social_google_client_secret', $secret);
    }

    public function test_placeholder_credentials_are_treated_as_not_configured(): void
    {
        // The exact shape that was live on the server: enabled + short junk.
        $this->setGoogle('xxxxxxx', 'yyyyyyyyyy');

        $this->assertFalse(SocialAuthController::providerConfigured('google'));
        $this->assertSame([], SocialAuthController::enabledProviders());
    }

    public function test_realistic_google_credentials_are_accepted(): void
    {
        $this->setGoogle(
            '123456789012-abcdefghijklmnopqrstuvwxyz012345.apps.googleusercontent.com',
            'GOCSPX-abcdefghijklmnopqrstuvwx',
        );

        $this->assertTrue(SocialAuthController::providerConfigured('google'));
        $this->assertSame(['google' => 'Google'], SocialAuthController::enabledProviders());
    }

    public function test_disabled_provider_is_not_configured_even_with_real_looking_keys(): void
    {
        $this->setGoogle(
            '123456789012-abcdefghijklmnopqrstuvwxyz012345.apps.googleusercontent.com',
            'GOCSPX-abcdefghijklmnopqrstuvwx',
            enabled: false,
        );

        $this->assertFalse(SocialAuthController::providerConfigured('google'));
    }

    public function test_browser_redirect_with_placeholder_creds_bounces_to_login_with_an_error(): void
    {
        $this->setGoogle('xxxxxxx', 'yyyyyyyyyy');

        // The mock bypass is test/local only — on a real deployment a broken
        // config must bounce back to the login screen with a clear message,
        // never forward the user to a provider error page or a mock session.
        $this->app['env'] = 'production';

        $this->get('/auth/google/redirect')
            ->assertRedirect(route('tenant.login'))
            ->assertSessionHas('error');
    }

    public function test_mock_bypass_is_refused_on_a_production_deployment(): void
    {
        // No credentials at all + production env: the callback must 404 rather
        // than mint a mock.google@example.com session.
        $this->app['env'] = 'production';

        $this->get('/auth/google/callback?state=mock_state&code=mock_code')
            ->assertNotFound();
    }

    public function test_browser_redirect_with_real_creds_goes_to_the_provider(): void
    {
        $this->setGoogle(
            '123456789012-abcdefghijklmnopqrstuvwxyz012345.apps.googleusercontent.com',
            'GOCSPX-abcdefghijklmnopqrstuvwx',
        );

        $res = $this->get('/auth/google/redirect');
        $res->assertRedirect();
        $this->assertStringStartsWith(
            'https://accounts.google.com/o/oauth2/v2/auth',
            $res->headers->get('Location'),
        );
        // A real provider is configured, so it must NOT fall back to the mock.
        $this->assertStringNotContainsString('mock_code', (string) $res->headers->get('Location'));
    }
}
