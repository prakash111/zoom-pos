<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthGuardRedirectTest extends TestCase
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

    public function test_guest_hitting_superadmin_is_sent_to_the_platform_login(): void
    {
        $this->get('/superadmin')->assertRedirect(route('superadmin.login'));
    }

    public function test_guest_hitting_tenant_is_sent_to_the_tenant_login(): void
    {
        $this->get('/tenant')->assertRedirect(route('tenant.login'));
    }
}
