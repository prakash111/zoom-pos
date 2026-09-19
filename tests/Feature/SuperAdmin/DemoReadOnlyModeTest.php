<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Middleware\PreventDemoModifications;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\Concerns\ActsAsPlatformAdmin;
use Tests\TestCase;

/**
 * DEMO_MODE turns the whole Super Admin panel read-only. The mutation surface
 * is really `POST /livewire/update` (every Super Admin route is a GET rendering
 * a full-page Livewire component), so the guard inspects that payload; plain
 * REST routes under /superadmin and /api/superadmin are covered too.
 */
class DemoReadOnlyModeTest extends TestCase
{
    use ActsAsPlatformAdmin, RefreshDatabase;

    private function pass(): Closure
    {
        return fn () => new Response('OK', 200);
    }

    private function livewireRequest(string $component, array $calls = [], array $updates = []): Request
    {
        return Request::create('/livewire/update', 'POST', [
            'components' => [[
                'snapshot' => json_encode(['memo' => ['name' => $component]]),
                'updates' => $updates,
                'calls' => $calls,
            ]],
        ]);
    }

    public function test_superadmin_livewire_save_action_is_blocked_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $request = $this->livewireRequest('super-admin.settings.index', [
            ['method' => 'saveGeneral', 'params' => []],
        ]);

        $response = (new PreventDemoModifications)->handle($request, $this->pass());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(PreventDemoModifications::SUPERADMIN_FLASH, session('error'));
    }

    public function test_superadmin_livewire_property_write_is_blocked_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $request = $this->livewireRequest('super-admin.settings.index', [], [
            'platformTitle' => 'Hacked Title',
        ]);

        $response = (new PreventDemoModifications)->handle($request, $this->pass());

        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_superadmin_livewire_navigation_and_search_still_work_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $request = $this->livewireRequest('super-admin.tenants.index', [
            ['method' => 'gotoPage', 'params' => [2]],
        ], ['search' => 'acme']);

        $response = (new PreventDemoModifications)->handle($request, $this->pass());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    public function test_superadmin_api_mutation_returns_403_json_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $request = Request::create('/api/superadmin/settings/general', 'POST', ['x' => 1]);

        $response = (new PreventDemoModifications)->handle($request, $this->pass());

        $this->assertSame(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertSame(PreventDemoModifications::SUPERADMIN_API_MESSAGE, $data['message']);
    }

    public function test_superadmin_auth_routes_are_exempt_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $request = Request::create('/superadmin/login', 'POST', ['email' => 'x@y.z']);

        $response = (new PreventDemoModifications)->handle($request, $this->pass());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_superadmin_lock_is_inert_when_demo_mode_is_off(): void
    {
        config(['app.demo_mode' => false]);

        $request = $this->livewireRequest('super-admin.settings.index', [
            ['method' => 'saveGeneral', 'params' => []],
        ]);

        $response = (new PreventDemoModifications)->handle($request, $this->pass());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_superadmin_layout_renders_the_readonly_banner_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);
        $this->actingAsSuperAdmin();

        $this->get(route('superadmin.settings.index'))
            ->assertOk()
            ->assertSee('Super Admin controls are read-only')
            ->assertSee('Saving disabled in demo mode', false);
    }

    public function test_superadmin_layout_has_no_readonly_banner_when_demo_mode_is_off(): void
    {
        config(['app.demo_mode' => false]);
        $this->actingAsSuperAdmin();

        $this->get(route('superadmin.settings.index'))
            ->assertOk()
            ->assertDontSee('Super Admin controls are read-only');
    }
}
