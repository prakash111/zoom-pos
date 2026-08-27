<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\System\Index;
use App\Models\PlatformSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    public function test_non_super_admin_role_is_blocked(): void
    {
        $this->actingAsSupportAdmin();

        // See PaymentGatewaysTest for why this asserts on rendered output
        // rather than expectException.
        Livewire::test(Index::class)->assertSee('403');
    }

    public function test_super_admin_can_toggle_maintenance_mode(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(Index::class)
            ->set('maintenanceMode', true)
            ->set('maintenanceMessage', 'Back soon.')
            ->call('save');

        $this->assertSame('1', PlatformSystem::get('maintenance_mode'));
        $this->assertSame('Back soon.', PlatformSystem::get('maintenance_message'));
    }
}
