<?php

namespace Tests\Feature\Dashboard;

use App\Models\Company;
use App\Models\User;
use App\Services\Dashboard\DashboardLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), '{}');
    }

    public function test_dashboard_layout_service_excludes_total_balance_across_all_schemas(): void
    {
        $service = app(DashboardLayoutService::class);

        foreach (['cards_dark', 'cards_light', 'analytics_board', 'metro_retail'] as $layout) {
            $schema = $service->getLayoutSchema($layout);
            $this->assertNotEmpty($schema['widgets']);
            $this->assertNotContains('total_balance', $schema['widgets']);
            $this->assertNotContains('balance_card', $schema['widgets']);
            $this->assertNotContains('total_balance_card', $schema['widgets']);
        }
    }

    public function test_dashboard_layout_service_sanitizes_custom_widget_lists(): void
    {
        $service = app(DashboardLayoutService::class);
        $dirtyWidgets = [
            'quick_actions',
            'total_balance',
            'receivables_banner',
            'balance_card',
            'statistics_card',
        ];

        $clean = $service->sanitizeWidgets($dirtyWidgets);
        $this->assertSame(['quick_actions', 'receivables_banner', 'statistics_card'], $clean);
    }

    public function test_dashboard_init_endpoint_returns_sanitized_layout_schema(): void
    {
        $company = Company::create([
            'name' => 'Test Dashboard Store',
            'slug' => 'test-dashboard-store',
            'email' => 'dash@teststore.com',
            'status' => 'active',
            'dashboard_layout' => 'cards_dark',
        ]);
        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Manager User',
            'email' => 'manager@teststore.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tenant/dashboard/init?layout=cards_dark');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.layout_key', 'cards_dark')
            ->assertJsonPath('data.enabled_widgets.0', 'quick_actions');

        $widgets = $response->json('data.enabled_widgets');
        $this->assertNotContains('total_balance', $widgets);
        $this->assertNotContains('balance_card', $widgets);
    }
}
