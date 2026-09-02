<?php

namespace Tests\Feature\Tenant;

use App\Mail\TenantPasswordResetMail;
use App\Models\Configuration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use ActsAsTenantUser, RefreshDatabase;

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

    public function test_forgot_password_email_and_reset_flow(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();
        // actingAs also logs the test client in — forgot/reset password must
        // work as a signed-out guest, so drop that session.
        auth('web')->logout();

        Configuration::create(['company_id' => $company->id, 'key' => 'smtp_host', 'value' => 'smtp.example.test']);
        Mail::fake();

        $this->post(route('tenant.password.email'), ['email' => $admin->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Mail::assertSent(TenantPasswordResetMail::class);

        $row = DB::table('password_reset_tokens')->where('email', $admin->email)->first();
        $this->assertNotNull($row, 'A password reset token row should have been stored.');

        // Recover the plain token from the mailable that was actually queued,
        // since only its hash is persisted.
        $capturedUrl = null;
        Mail::assertSent(TenantPasswordResetMail::class, function (TenantPasswordResetMail $mail) use (&$capturedUrl) {
            $capturedUrl = $mail->resetUrl;

            return true;
        });
        $plainToken = basename((string) parse_url($capturedUrl, PHP_URL_PATH));

        $this->post(route('tenant.password.reset'), [
            'email' => $admin->email,
            'token' => $plainToken,
            'password' => 'new-secret-999',
            'password_confirmation' => 'new-secret-999',
        ])->assertRedirect(route('tenant.login'));

        $this->assertTrue(Hash::check('new-secret-999', $admin->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $admin->email]);
    }

    public function test_authenticated_user_can_change_own_password(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $this->post(route('tenant.settings.change-password'), [
            'current_password' => 'secret1234',
            'new_password' => 'brand-new-pass-1',
            'new_password_confirmation' => 'brand-new-pass-1',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('brand-new-pass-1', $admin->fresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $this->post(route('tenant.settings.change-password'), [
            'current_password' => 'totally-wrong',
            'new_password' => 'brand-new-pass-1',
            'new_password_confirmation' => 'brand-new-pass-1',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('secret1234', $admin->fresh()->password));
    }
}
