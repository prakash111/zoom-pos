<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Services\NotificationAlertService;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesTenantSyncContext;

    public function __construct(private readonly NotificationAlertService $alerts) {}

    public function show(Request $request): JsonResponse
    {
        $tenantId = auth()->user()?->tenant_id
            ?? auth()->user()?->company_id
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
            ?? $request->attributes->get('company_id');

        $company = $this->resolveCompany($request);
        if (! $tenantId) {
            $tenantId = $company->id;
        }

        $schema = SchemaResponse::dashboardView($company);
        $schema['app_bar'] = [
            'title' => 'Dashboard',
            'show_back_button' => false,
            'actions' => [
                // Quick Sync / Reload
                [
                    'type'        => 'icon_button',
                    'icon'        => 'sync',
                    'action_type' => 'REFRESH_DASHBOARD',
                    'action'      => ['type' => 'REFRESH_DASHBOARD'],
                ],
                // Theme Selector (Match Device / Light / Dark)
                [
                    'type'    => 'theme_selector_dropdown',
                    'current' => 'match_device',
                ],
                // Notification Bell Icon (Replacing the previous logout button)
                [
                    'type'        => 'notification_bell',
                    'icon'        => 'notifications_none',
                    'badge_count' => $this->getUnreadNotificationsCount($tenantId),
                    'action'      => [
                        'type'     => 'OPEN_BOTTOM_SHEET',
                        'title'    => 'System Alerts & Reminders',
                        'endpoint' => '/api/v1/tenant/notifications/feed',
                    ],
                ],
            ],
        ];
        $schema['theme'] = [
            'surface'      => 'theme.surface',
            'canvas'       => 'theme.canvas',
            'divider'      => 'theme.divider',
            'text_primary' => 'theme.textPrimary',
        ];

        return response()->json([
            'success' => true,
            'view'    => 'dashboard',
            'schema'  => $schema,
        ]);
    }

    private function getUnreadNotificationsCount(mixed $tenantId): int
    {
        return $this->alerts->unreadCount($tenantId);
    }
}
