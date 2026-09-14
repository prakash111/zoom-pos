<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\TenantNotification;
use App\Services\NotificationAlertService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ResolvesTenantSyncContext;

    public function __construct(private readonly NotificationAlertService $alerts) {}

    public function feed(Request $request): JsonResponse
    {
        $tenantId = auth()->user()?->tenant_id
            ?? auth()->user()?->company_id
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
            ?? $request->attributes->get('company_id');

        if (! $tenantId) {
            $tenantId = $this->resolveCompany($request)->id;
        }

        $alerts = $this->alerts->alerts($tenantId);

        $components = $alerts->isEmpty()
            ? [[
                'type'             => 'empty_state',
                'icon'             => 'notifications_none',
                'message'          => 'No urgent alerts or pending follow-ups.',
                'background_color' => 'theme.surface',
                'text_color'       => 'theme.textPrimary',
            ]]
            : $alerts->values()->toArray();

        $schema = [
            'type'           => 'bottom_sheet',
            'schema_version' => 1,
            'title'          => 'Notifications & Activity Alerts',
            'layout'         => 'scroll_view',
            'theme'          => [
                'surface'      => 'theme.surface',
                'canvas'       => 'theme.canvas',
                'divider'      => 'theme.divider',
                'text_primary' => 'theme.textPrimary',
            ],
            'components'     => $components,
        ];

        return response()->json(array_merge([
            'success'      => true,
            'unread_count' => $this->alerts->unreadCount($tenantId),
            'schema'       => $schema,
        ], $schema));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $tenantId = auth()->user()?->company_id
            ?? auth()->user()?->tenant_id
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
            ?? $request->attributes->get('company_id');

        if (! $tenantId) {
            try {
                $tenantId = $this->resolveCompany($request)->id;
            } catch (\Throwable) {
                $tenantId = 1;
            }
        }

        $dueInvoices = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('sales')) {
            $balanceColumn = \Illuminate\Support\Facades\Schema::hasColumn('sales', 'balance_due') ? 'balance_due' : 'due_amount';
            $dueInvoices = \App\Models\Invoice::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('sales', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })
                ->where($balanceColumn, '>', 0)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', now())
                ->count();
        }

        $dueReminders = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('reminders')) {
            $dueReminders = \App\Models\Reminder::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('reminders', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })
                ->where('status', 'pending')
                ->where(function ($q) {
                    $q->whereDate('due_at', '<=', now())
                      ->orWhere(function ($fallback) {
                          $fallback->whereNull('due_at')->whereDate('due_date', '<=', now());
                      });
                })
                ->count();
        }

        $totalUnread = $dueInvoices + $dueReminders;
        if ($totalUnread === 0 && isset($this->alerts)) {
            $totalUnread = $this->alerts->unreadCount($tenantId);
        }

        return response()->json([
            'success'       => true,
            'unread_count'  => $totalUnread,
            'due_invoices'  => $dueInvoices,
            'due_reminders' => $dueReminders,
        ]);
    }
}
