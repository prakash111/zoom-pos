<?php

namespace Tests\Feature;

use App\Livewire\Installer\AdminAccountStep;
use App\Livewire\Installer\FinishStep;
use App\Livewire\Installer\RequirementsStep;
use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class InstallerFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(storage_path('installed'));
    }

    protected function tearDown(): void
    {
        @touch(storage_path('installed'));
        parent::tearDown();
    }

    public function test_requirements_step_passes_in_this_environment(): void
    {
        Livewire::test(RequirementsStep::class)->assertSet('allPassed', true);
    }

    public function test_admin_account_step_creates_first_super_admin_and_locks_after(): void
    {
        $this->assertSame(0, PlatformAdmin::count());

        Livewire::test(AdminAccountStep::class)
            ->set('name', 'Platform Owner')
            ->set('email', 'owner@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('save')
            ->assertRedirect(route('install.finish'));

        $this->assertSame(1, PlatformAdmin::count());
        $admin = PlatformAdmin::first();
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue(Hash::check('password123', $admin->password));

        // A second bootstrap attempt must be rejected once an admin exists.
        Livewire::test(AdminAccountStep::class)->assertSet('alreadyBootstrapped', true);
    }

    public function test_finish_step_writes_lock_file(): void
    {
        $this->assertFileDoesNotExist(storage_path('installed'));

        Livewire::test(FinishStep::class)->call('finish');

        $this->assertFileExists(storage_path('installed'));
    }
}
