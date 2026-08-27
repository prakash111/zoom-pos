<?php

namespace Tests\Feature;

use App\Livewire\Installer\AdminAccountStep;
use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BootstrapFirstAdminGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_second_bootstrap_attempt_is_rejected(): void
    {
        PlatformAdmin::create([
            'name' => 'Existing Owner',
            'email' => 'existing@example.com',
            'password' => bcrypt('secret1234'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $component = Livewire::test(AdminAccountStep::class);
        $component->assertSet('alreadyBootstrapped', true);

        try {
            $component->set('name', 'Intruder')
                ->set('email', 'intruder@example.com')
                ->set('password', 'password123')
                ->set('password_confirmation', 'password123')
                ->call('save');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // Whether the abort propagated to the test or was caught by Livewire's
        // own request lifecycle, no second admin must ever be created.
        $this->assertSame(1, PlatformAdmin::count());
        $this->assertSame('existing@example.com', PlatformAdmin::first()->email);
    }
}
