<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Reminder;
use App\Models\Sale;
use App\Models\TenantNotification;
use App\Services\NotificationAlertService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        if ($alerts->isEmpty()) {
            $components = [[
                'type'             => 'empty_state',
                'icon'             => 'notifications_none',
                'message'          => 'No urgent alerts or pending follow-ups.',
                'background_color' => 'theme.surface',
                'text_color'       => 'theme.textPrimary',
            ]];
        } else {
            $components = [];

            // 1. Sheet Header Bar with Title and "Clear All" Action Button
            $components[] = [
                'type'                 => 'row',
                'main_axis_alignment'  => 'space_between',
                'cross_axis_alignment' => 'center',
                'padding'              => ['top' => 2, 'bottom' => 8, 'left' => 4, 'right' => 4],
                'children'             => [
                    [
                        'type'   => 'text',
                        'value'  => 'Alerts & Reminders (' . $alerts->count() . ')',
                        'style'  => 'title_medium',
                        'weight' => 'bold',
                    ],
                    [
                        'type'            => 'button_danger',
                        'label'           => 'Clear All',
                        'icon'            => 'delete_sweep',
                        'dense'           => true,
                        'confirm_message' => 'Are you sure you want to dismiss all alerts and reminders?',
                        'action'          => [
                            'type'     => 'SUBMIT_FORM',
                            'endpoint' => '/api/v1/tenant/notifications/clear-all',
                            'method'   => 'POST',
                            'reload'   => true,
                        ],
                    ],
                ],
            ];

            $components[] = [
                'type' => 'divider',
            ];

            // 2. Individual Alert Items with Dismiss Button
            foreach ($alerts as $alert) {
                $alertId = $alert['id'] ?? null;
                $alertCategory = $alert['category'] ?? 'system';

                $components[] = [
                    'type'     => 'column',
                    'margin'   => ['bottom' => 6],
                    'children' => [
                        $alert,
                        [
                            'type'                => 'row',
                            'main_axis_alignment' => 'end',
                            'padding'             => ['top' => 2, 'bottom' => 4, 'right' => 4],
                            'children'            => [
                                [
                                    'type'         => 'button_outlined',
                                    'label'        => 'Dismiss',
                                    'icon'         => 'close',
                                    'dense'        => true,
                                    'color'        => '#EF4444',
                                    'border_color' => '#FCA5A5',
                                    'action'       => [
                                        'type'     => 'SUBMIT_FORM',
                                        'endpoint' => "/api/v1/tenant/notifications/{$alertCategory}/{$alertId}/dismiss",
                                        'method'   => 'POST',
                                        'data'     => [
                                            'type' => $alertCategory,
                                            'id'   => $alertId,
                                        ],
                                        'reload'   => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }
        }

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

    /**
     * Dismiss a single notification, lead reminder, or invoice overdue reminder.
     * POST /api/v1/tenant/notifications/{type}/{id}/dismiss
     * POST /api/v1/tenant/notifications/{id}/dismiss
     * POST /api/v1/tenant/notifications/dismiss
     */
    public function dismiss(Request $request, ?string $param1 = null, ?string $param2 = null): JsonResponse
    {
        $tenantId = auth()->user()?->tenant_id
            ?? auth()->user()?->company_id
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
            ?? $request->attributes->get('company_id');

        if (! $tenantId) {
            $tenantId = $this->resolveCompany($request)->id;
        }

        $userId = auth()->user()?->id ?? $this->resolveUser($request, $this->resolveCompany($request))?->id;

        $type = $request->input('type') ?? ($param2 ? $param1 : null);
        $id = $request->input('id') ?? ($param2 ?: $param1);

        if (! $id) {
            return response()->json(['success' => false, 'message' => 'Notification ID required.'], 422);
        }

        $dismissed = false;

        // 1. If type is invoice or id matches a sale
        if ($type === 'invoice' || ! $type) {
            $sale = Sale::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId);
                    if (Schema::hasColumn('sales', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)
                        ->orWhere('external_id', (string) $id)
                        ->orWhere('sale_number', (string) $id);
                })
                ->first();

            if ($sale) {
                if (Schema::hasColumn('sales', 'due_reminder_dismissed_at')) {
                    $sale->forceFill(['due_reminder_dismissed_at' => now()])->save();
                }
                $dismissed = true;
            }
        }

        // 2. If type is lead or id matches a reminder
        if (($type === 'lead' || ! $type) && ! $dismissed) {
            $reminder = Reminder::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId);
                    if (Schema::hasColumn('reminders', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })
                ->where('id', $id)
                ->first();

            if ($reminder) {
                $reminder->forceFill(['status' => Reminder::STATUS_DISMISSED])->save();
                $dismissed = true;
            }
        }

        // 3. If type is system/notification or id matches tenant_notification
        if (! $dismissed && Schema::hasTable('tenant_notifications')) {
            $notif = TenantNotification::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId);
                    if (Schema::hasColumn('tenant_notifications', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })
                ->where('id', $id)
                ->first();

            if ($notif) {
                $notif->forceFill(['read_status' => true])->save();
                $dismissed = true;
            }
        }

        // Record in dismissed_notifications table if exists
        if (Schema::hasTable('dismissed_notifications')) {
            DB::table('dismissed_notifications')->updateOrInsert(
                [
                    'company_id'        => (string) $tenantId,
                    'notification_type' => $type ?: 'general',
                    'notification_id'   => (string) $id,
                ],
                [
                    'user_id'    => $userId ? (string) $userId : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Notification dismissed.',
            'id'           => (string) $id,
            'unread_count' => $this->alerts->unreadCount($tenantId),
        ]);
    }

    /**
     * Clear / dismiss all notifications for the tenant.
     * POST /api/v1/tenant/notifications/clear-all
     */
    public function clearAll(Request $request): JsonResponse
    {
        $tenantId = auth()->user()?->tenant_id
            ?? auth()->user()?->company_id
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
            ?? $request->attributes->get('company_id');

        if (! $tenantId) {
            $tenantId = $this->resolveCompany($request)->id;
        }

        $userId = auth()->user()?->id ?? $this->resolveUser($request, $this->resolveCompany($request))?->id;

        // 1. Dismiss overdue invoice reminders
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'due_reminder_dismissed_at')) {
            Sale::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId);
                    if (Schema::hasColumn('sales', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })
                ->where('due_amount', '>', 0)
                ->whereNotNull('due_date')
                ->whereNull('due_reminder_dismissed_at')
                ->update(['due_reminder_dismissed_at' => now()]);
        }

        // 2. Dismiss pending reminders
        if (Schema::hasTable('reminders')) {
            Reminder::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId);
                    if (Schema::hasColumn('reminders', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })
                ->where('status', Reminder::STATUS_PENDING)
                ->update(['status' => Reminder::STATUS_DISMISSED]);
        }

        // 3. Mark tenant notifications as read
        if (Schema::hasTable('tenant_notifications')) {
            TenantNotification::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId);
                    if (Schema::hasColumn('tenant_notifications', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })
                ->where('read_status', false)
                ->update(['read_status' => true]);
        }

        return response()->json([
            'success'      => true,
            'message'      => 'All alerts cleared successfully.',
            'unread_count' => 0,
        ]);
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
        if (Schema::hasTable('sales')) {
            $balanceColumn = Schema::hasColumn('sales', 'balance_due') ? 'balance_due' : 'due_amount';
            $dueInvoices = Invoice::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId);
                    if (Schema::hasColumn('sales', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })
                ->where($balanceColumn, '>', 0)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', now())
                ->when(Schema::hasColumn('sales', 'due_reminder_dismissed_at'), function ($q) {
                    $q->whereNull('due_reminder_dismissed_at');
                })
                ->when(Schema::hasTable('dismissed_notifications'), function ($query) use ($tenantId) {
                    $query->whereNotIn('id', function ($sub) use ($tenantId) {
                        $sub->select('notification_id')
                            ->from('dismissed_notifications')
                            ->where('company_id', $tenantId)
                            ->where('notification_type', 'invoice');
                    });
                })
                ->count();
        }

        $dueReminders = 0;
        if (Schema::hasTable('reminders')) {
            $dueReminders = Reminder::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId);
                    if (Schema::hasColumn('reminders', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })
                ->where('status', Reminder::STATUS_PENDING)
                ->where(function ($q) {
                    $q->whereDate('due_at', '<=', now())
                      ->orWhere(function ($fallback) {
                          $fallback->whereNull('due_at')->whereDate('due_date', '<=', now());
                      });
                })
                ->when(Schema::hasTable('dismissed_notifications'), function ($query) use ($tenantId) {
                    $query->whereNotIn('id', function ($sub) use ($tenantId) {
                        $sub->select('notification_id')
                            ->from('dismissed_notifications')
                            ->where('company_id', $tenantId)
                            ->where('notification_type', 'lead');
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

