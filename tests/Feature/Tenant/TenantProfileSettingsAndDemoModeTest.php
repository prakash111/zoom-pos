<?php

namespace Tests\Feature\Tenant;

use App\Livewire\Tenant\Settings\Index as SettingsIndex;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

class TenantProfileSettingsAndDemoModeTest extends TestCase
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

    public function test_profile_settings_tab_retains_branding_and_save_all_settings_without_change_password_card(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $response = $this->actingAs($admin, 'web')->get(route('tenant.settings.profile'));
        $response->assertOk();

        // 1. Must contain Store Profile and Branding sections
        $response->assertSee('Store Profile &amp; Location', false);
        $response->assertSee('Store Theme, Branding &amp; POS Layout', false);

        // 2. Primary button must be "Save All Settings"
        $response->assertSee('Save All Settings');

        // 3. Static "Change Password" section and form must NOT be in the profile settings tab
        $response->assertDontSee('Update the password for your own account.');

        // Livewire view rendering check
        Livewire::test(SettingsIndex::class, ['activeSection' => 'profile'])
            ->assertSee('Save All Settings')
            ->assertSee('Store Profile & Location')
            ->assertDontSee('Update the password for your own account.');
    }

    public function test_change_password_modal_is_included_in_tenant_layout_and_wired_to_avatars(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        $response = $this->actingAs($admin, 'web')->get(route('tenant.dashboard'));
        $response->assertOk();

        // Check avatar click triggers
        $response->assertSee('onclick="openChangePasswordModal(event)"', false);

        // Check password modal fields are present in the layout and strictly hidden by default
        $response->assertSee('id="changePasswordModal"', false);
        $response->assertSee('style="display: none;"', false);
        $response->assertSee('id="modal-password-title"', false);
        $response->assertSee('name="current_password"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertSee('openChangePasswordModal');
    }

    public function test_normal_mode_allows_password_changes_via_settings_and_profile_routes(): void
    {
        config(['app.demo_mode' => false]);
        [$company, $admin] = $this->actingAsTenantAdmin();

        // 1. Password change via password.update (PUT) using password / password_confirmation
        $response = $this->actingAs($admin, 'web')->put(route('tenant.password.update'), [
            'current_password' => 'secret1234',
            'password' => 'new-secure-pwd-123',
            'password_confirmation' => 'new-secure-pwd-123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('new-secure-pwd-123', $admin->fresh()->password));

        // 2. Password change via settings.change-password using password / password_confirmation
        $response2 = $this->actingAs($admin, 'web')->post(route('tenant.settings.change-password'), [
            'current_password' => 'new-secure-pwd-123',
            'password' => 'new-secure-pwd-456',
            'password_confirmation' => 'new-secure-pwd-456',
        ]);

        $response2->assertRedirect();
        $this->assertTrue(Hash::check('new-secure-pwd-456', $admin->fresh()->password));

        // 3. Password change via profile.update-password using new_password / new_password_confirmation
        $response3 = $this->actingAs($admin, 'web')->post(route('tenant.profile.update-password'), [
            'current_password' => 'new-secure-pwd-456',
            'new_password' => 'another-fresh-pass-789',
            'new_password_confirmation' => 'another-fresh-pass-789',
        ]);

        $response3->assertRedirect();
        $this->assertTrue(Hash::check('another-fresh-pass-789', $admin->fresh()->password));
    }

    public function test_demo_mode_modal_displays_notice_and_disables_submit_button(): void
    {
        config(['app.demo_mode' => true]);
        [$company, $admin] = $this->actingAsTenantAdmin();

        $response = $this->actingAs($admin, 'web')->get(route('tenant.dashboard'));
        $response->assertOk();

        // Modal indicator and disabled button
        $response->assertSee('Password change is disabled in demo mode');
        $response->assertSee('Demo Mode Active');
        $response->assertSee('title="Password change is disabled in demo mode"', false);
    }

    public function test_demo_mode_strictly_rejects_password_mutations(): void
    {
        config(['app.demo_mode' => true]);
        [$company, $admin] = $this->actingAsTenantAdmin();

        // 1. Web session post redirect with flash error
        $webResponse = $this->actingAs($admin, 'web')->post(route('tenant.settings.change-password'), [
            'current_password' => 'secret1234',
            'password' => 'attempted-demo-bypass-1',
            'password_confirmation' => 'attempted-demo-bypass-1',
        ]);

        $webResponse->assertRedirect();
        $webResponse->assertSessionHas('error', 'Action disabled: Modifications are restricted in demo mode.');
        $this->assertFalse(Hash::check('attempted-demo-bypass-1', $admin->fresh()->password));

        // 2. JSON request returns 403 Forbidden with exact alert message
        $jsonResponse = $this->actingAs($admin, 'web')->postJson(route('tenant.profile.update-password'), [
            'current_password' => 'secret1234',
            'password' => 'attempted-demo-bypass-2',
            'password_confirmation' => 'attempted-demo-bypass-2',
        ]);

        $jsonResponse->assertStatus(403);
        $jsonResponse->assertJsonFragment([
            'message' => 'Action disabled: Modifications are restricted in demo mode.',
        ]);
        $this->assertFalse(Hash::check('attempted-demo-bypass-2', $admin->fresh()->password));
    }

    public function test_demo_mode_strictly_protects_tenant_settings_and_stores(): void
    {
        config(['app.demo_mode' => true]);
        [$company, $admin] = $this->actingAsTenantAdmin();

        $primaryStore = Store::where('company_id', $company->id)->first();
        if (! $primaryStore) {
            $primaryStore = Store::create([
                'company_id' => $company->id,
                'tenant_id' => $company->id,
                'name' => 'Main Flagship',
                'code' => 'MAIN',
                'is_primary' => true,
                'is_active' => true,
            ]);
        }

        // 1. Attempt creating store is rejected with alert
        $resCreateStore = $this->actingAs($admin, 'web')->post(route('tenant.stores.create'), [
            'name' => 'Unauthorized Demo Branch',
            'code' => 'UNAUTH',
        ]);
        $resCreateStore->assertRedirect();
        $resCreateStore->assertSessionHas('error', 'Action disabled: Modifications are restricted in demo mode.');

        // 2. Attempt updating store is rejected with alert
        $resUpdateStore = $this->actingAs($admin, 'web')->put(route('tenant.stores.update', $primaryStore->id), [
            'name' => 'Modified Store Name In Demo',
        ]);
        $resUpdateStore->assertRedirect();
        $resUpdateStore->assertSessionHas('error', 'Action disabled: Modifications are restricted in demo mode.');
        $this->assertNotSame('Modified Store Name In Demo', $primaryStore->fresh()->name);

        // 3. Attempt updating store profile via API is rejected with 403 JSON
        $resApiProfile = $this->actingAs($admin, 'web')->postJson('/api/v1/tenant/store-profile', [
            'name' => 'Bypassed Company Name',
        ]);
        $resApiProfile->assertStatus(403);
        $resApiProfile->assertJsonFragment([
            'message' => 'Action disabled: Modifications are restricted in demo mode.',
        ]);
        $this->assertNotSame('Bypassed Company Name', $company->fresh()->name);

        // 4. Attempt saving settings via Livewire is guarded
        $originalName = $company->name;
        Livewire::test(SettingsIndex::class)
            ->set('name', 'Hacked Demo Name')
            ->call('save')
            ->assertDispatched('notify', function ($name, $params) {
                $msg = is_array($params) ? ($params['message'] ?? $params[0]['message'] ?? null) : null;
                return $msg === 'Action disabled: Modifications are restricted in demo mode.';
            });

        $this->assertSame($originalName, $company->fresh()->name);
    }

    public function test_change_password_modal_is_strictly_hidden_by_default_and_does_not_auto_open_on_generic_errors(): void
    {
        [$company, $admin] = $this->actingAsTenantAdmin();

        // Standard page view without errors
        $response = $this->actingAs($admin, 'web')->get(route('tenant.dashboard'));
        $response->assertOk();
        $response->assertSee('id="changePasswordModal"', false);
        $response->assertSee('style="display: none;"', false);
        $response->assertSee('class="hidden fixed inset-0', false);
        $response->assertDontSee('openChangePasswordModal();', false);

        // Flash an unrelated error to session
        $responseWithGenericError = $this->actingAs($admin, 'web')
            ->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['unrelated_field' => 'Some error']))])
            ->get(route('tenant.dashboard'));

        $responseWithGenericError->assertOk();
        $responseWithGenericError->assertDontSee('openChangePasswordModal();', false);
    }

    public function test_change_password_modal_auto_opens_only_on_update_password_errors(): void
    {
        config(['app.demo_mode' => false]);
        [$company, $admin] = $this->actingAsTenantAdmin();

        $response = $this->followingRedirects()
            ->actingAs($admin, 'web')
            ->from(route('tenant.dashboard'))
            ->put(route('tenant.password.update'), [
                'current_password' => 'secret1234',
                'password' => 'short',
                'password_confirmation' => 'mismatch',
            ]);

        $response->assertOk();
        $response->assertSee('openChangePasswordModal();', false);
    }
}
