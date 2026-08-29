<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAppIsInstalled;
use App\Support\Desktop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DesktopStartupAndErrorRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_desktop_is_running_detected_via_config(): void
    {
        Config::set('nativephp-internal.running', true);
        $this->assertTrue(Desktop::isRunning());

        Config::set('nativephp-internal.running', false);
    }

    public function test_ensure_app_is_installed_middleware_bypasses_for_desktop(): void
    {
        Config::set('nativephp-internal.running', true);

        $middleware = new EnsureAppIsInstalled();
        $request = Request::create('/', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertSame('OK', $response->getContent());
        Config::set('nativephp-internal.running', false);
    }

    public function test_landing_page_redirects_to_tenant_login_in_desktop_mode(): void
    {
        Config::set('nativephp-internal.running', true);

        $response = $this->get('/');
        $response->assertRedirect('/tenant/login');

        Config::set('nativephp-internal.running', false);
    }

    public function test_nativephp_config_does_not_strip_app_key(): void
    {
        $cleanupKeys = config('nativephp.cleanup_env_keys', []);
        $this->assertNotContains('APP_KEY', $cleanupKeys, 'APP_KEY must not be stripped in desktop build cleanup_env_keys.');
    }

    public function test_app_key_configuration_has_safe_fallback(): void
    {
        $this->assertNotEmpty(config('app.key'), 'config app.key must never be blank.');
    }
}
