<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\NotificationAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Chat\Models\PromotionalBroadcast;

class NotificationFeedController extends Controller
{
    public function __construct(private readonly ?NotificationAlertService $alerts = null) {}

    /**
     * Get unified notification stream for the top-bar notification bell
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id
            ?? $user?->company_id
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : 1);

        // Fetch IDs of announcements dismissed by this specific user
        $dismissedAnnouncementIds = [];
        if ($user && Schema::hasTable('broadcast_dismissals')) {
            $dismissedAnnouncementIds = DB::table('broadcast_dismissals')
                ->where('user_id', (string) $user->id)
                ->pluck('broadcast_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        // 1. Fetch Super Admin Announcements (targeted to all tenants or specific tenants)
        $announcements = collect();
        if (class_exists(PromotionalBroadcast::class) && Schema::hasTable('promotional_broadcasts')) {
            $announcements = PromotionalBroadcast::where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('tenant_id')->orWhere('tenant_id', '')->orWhere('tenant_id', '0');
                })
                ->whereNotIn('id', $dismissedAnnouncementIds)
                ->where(function ($q) use ($tenantId) {
                    $q->where('target_type', 'all_tenants')
                      ->orWhere(function ($sq) use ($tenantId) {
                          $sq->where('target_type', 'selected_tenants')
                             ->where(function ($jsonQ) use ($tenantId) {
                                 $jsonQ->whereJsonContains('target_ids', (int) $tenantId)
                                       ->orWhereJsonContains('target_ids', (string) $tenantId);
                             });
                      });
                })
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest()
                ->get()
                ->map(function ($item) {
                    return [
                        'id'               => 'announcement_' . $item->id,
                        'raw_id'           => $item->id,
                        'type'             => 'announcement',
                        'title'            => $item->title,
                        'message'          => $item->message,
                        'badge'            => 'Announcement',
                        'badge_color'      => '#38BDF8', // Cyan / Sky Blue for Official Announcement
                        'banner_image_url' => $item->banner_image_url,
                        'pdf_url'          => $item->pdf_url,
                        'cta_label'        => $item->cta_label,
                        'cta_url'          => $item->cta_url,
                        'created_at'       => $item->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    ];
                });
        }

        // 2. Fetch Store / Tenant Staff Notifications (Dispatched by Tenant Admin)
        $staffNotifications = collect();
        if (class_exists(PromotionalBroadcast::class) && Schema::hasTable('promotional_broadcasts')) {
            $staffNotifications = PromotionalBroadcast::where('is_active', true)
                ->where('tenant_id', (string) $tenantId)
                ->whereNotIn('id', $dismissedAnnouncementIds)
                ->where(function ($q) use ($user) {
                    $q->where('target_type', 'all_staff');
                    if ($user) {
                        $q->orWhere(function ($inner) use ($user) {
                            $inner->where('target_type', 'selected_staff')
                                  ->where(function ($jsonQ) use ($user) {
                                      $jsonQ->whereJsonContains('target_ids', (int) $user->id)
                                            ->orWhereJsonContains('target_ids', (string) $user->id);
                                  });
                        });
                    }
                })
                ->latest()
                ->get()
                ->map(function ($item) {
                    return [
                        'id'               => 'staff_notice_' . $item->id,
                        'raw_id'           => $item->id,
                        'type'             => 'staff_notice',
                        'title'            => $item->title,
                        'message'          => $item->message,
                        'badge'            => 'Store Notice',
                        'badge_color'      => '#10B981', // Emerald Green
                        'banner_image_url' => $item->banner_image_url,
                        'pdf_url'          => $item->pdf_url,
                        'cta_label'        => $item->cta_label,
                        'cta_url'          => $item->cta_url,
                        'created_at'       => $item->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    ];
                });
        }

        // 3. System Activity Alerts
        $systemAlerts = collect();
        if ($user && method_exists($user, 'unreadNotifications')) {
            try {
                $userNotifs = $user->unreadNotifications()->get()->map(function ($n) {
                    return [
                        'id'          => 'system_' . $n->id,
                        'raw_id'      => (string) $n->id,
                        'type'        => 'system',
                        'title'       => $n->data['title'] ?? 'System Update',
                        'message'     => $n->data['message'] ?? $n->data['body'] ?? 'Notification details',
                        'badge'       => 'System',
                        'badge_color' => '#64748B',
                        'created_at'  => $n->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    ];
                });
                $systemAlerts = $systemAlerts->concat($userNotifs);
            } catch (\Throwable $e) {}
        }

        if ($this->alerts) {
            try {
                $operationalAlerts = $this->alerts->alerts($tenantId)->map(function ($a) {
                    return [
                        'id'          => 'alert_' . ($a['id'] ?? uniqid()),
                        'raw_id'      => $a['id'] ?? null,
                        'type'        => 'system',
                        'category'    => $a['category'] ?? 'system',
                        'title'       => $a['title'] ?? 'Operational Alert',
                        'message'     => $a['subtitle'] ?? '',
                        'badge'       => ucfirst((string) ($a['category'] ?? 'Alert')),
                        'badge_color' => $a['icon_color'] ?? '#10B981',
                        'created_at'  => $a['timestamp'] ?? now()->toIso8601String(),
                    ];
                });
                $systemAlerts = $systemAlerts->concat($operationalAlerts);
            } catch (\Throwable $e) {}
        }

        // Merge all into one feed, newest first
        $allNotifications = $announcements
            ->concat($staffNotifications)
            ->concat($systemAlerts)
            ->sortByDesc('created_at')
            ->values();

        // Build SDUI component schema for bottom sheet compatibility
        $components = [];
        if ($allNotifications->isNotEmpty()) {
            $components[] = [
                'type' => 'row',
                'main_axis_alignment' => 'space_between',
                'cross_axis_alignment' => 'center',
                'padding' => ['top' => 2, 'bottom' => 8, 'left' => 4, 'right' => 4],
                'children' => [
                    [
                        'type'   => 'text',
                        'value'  => 'Notifications & Activity Alerts (' . $allNotifications->count() . ')',
                        'style'  => 'title_medium',
                        'weight' => 'bold',
                    ],
                    [
                        'type'            => 'button_danger',
                        'label'           => 'Clear All',
                        'icon'            => 'delete_sweep',
                        'dense'           => true,
                        'confirm_message' => 'Are you sure you want to dismiss all notifications and announcements?',
                        'action'          => [
                            'type'             => 'SUBMIT_FORM',
                            'endpoint'         => url('api/v1/pos/notifications/clear-all'),
                            'refresh_in_place' => true,
                            'reload'           => true,
                        ],
                    ],
                ],
            ];
            $components[] = ['type' => 'divider'];

            foreach ($allNotifications as $item) {
                $itemId = $item['id'];
                $isNotice = in_array($item['type'] ?? '', ['announcement', 'staff_notice', 'promotional'], true);

                $dismissAction = [
                    'type'             => 'SUBMIT_FORM',
                    'endpoint'         => url('api/v1/pos/notifications/dismiss'),
                    'method'           => 'POST',
                    'data'             => ['id' => (string) $itemId],
                    'refresh_in_place' => true,
                    'reload'           => true,
                ];

                if ($isNotice) {
                    $components[] = [
                        'type'             => 'announcement_card',
                        'id'               => $itemId,
                        'title'            => $item['title'] ?? '',
                        'description'      => $item['message'] ?? '',
                        'badge'            => $item['badge'] ?? 'Notice',
                        'badge_color'      => $item['badge_color'] ?? '#38BDF8',
                        'banner_image_url' => $item['banner_image_url'] ?? null,
                        'pdf_url'          => $item['pdf_url'] ?? null,
                        'cta_label'        => $item['cta_label'] ?? null,
                        'cta_url'          => $item['cta_url'] ?? null,
                        'created_at'       => $item['created_at'] ?? null,
                        'dismiss_action'   => $dismissAction,
                    ];
                } else {
                    $components[] = [
                        'type'     => 'column',
                        'margin'   => ['bottom' => 6],
                        'children' => [
                            [
                                'type'             => 'notification_item',
                                'id'               => (string) $itemId,
                                'category'         => $item['badge'] ?? 'System',
                                'icon'             => 'notifications_active',
                                'icon_color'       => $item['badge_color'] ?? '#64748B',
                                'title'            => $item['title'] ?? '',
                                'subtitle'         => $item['message'] ?? '',
                                'timestamp'        => $item['created_at'] ?? '',
                                'background_color' => 'theme.surface',
                                'divider_color'    => 'theme.divider',
                                'text_color'       => 'theme.textPrimary',
                            ],
                            [
                                'type'                  => 'row',
                                'main_axis_alignment'   => 'end',
                                'padding'               => ['top' => 2, 'bottom' => 4, 'right' => 4],
                                'children'              => [
                                    [
                                        'type'         => 'button_outlined',
                                        'label'        => 'Dismiss',
                                        'icon'         => 'close',
                                        'dense'        => true,
                                        'color'        => '#EF4444',
                                        'border_color' => '#FCA5A5',
                                        'action'       => $dismissAction,
                                    ],
                                ],
                            ],
                        ],
                    ];
                }
            }
        } else {
            $components[] = [
                'type'             => 'empty_state',
                'icon'             => 'notifications_none',
                'message'          => 'No active alerts or announcements.',
                'background_color' => 'theme.surface',
                'text_color'       => 'theme.textPrimary',
            ];
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
            'unread_count' => $allNotifications->count(),
            'items'        => $allNotifications,
            'components'   => $components,
            'schema'       => $schema,
        ], $schema));
    }

    /**
     * Permanently dismiss a single notification or announcement for the user
     */
    public function dismissItem(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|string']);
        $user = $request->user();
        $id = $request->input('id');

        if (str_starts_with($id, 'announcement_') || str_starts_with($id, 'staff_notice_') || str_starts_with($id, 'promo_') || is_numeric($id)) {
            $broadcastId = (int) preg_replace('/[^0-9]/', '', $id);
            if ($broadcastId && Schema::hasTable('broadcast_dismissals')) {
                DB::table('broadcast_dismissals')->updateOrInsert(
                    [
                        'user_id'      => (string) ($user ? $user->id : 1),
                        'broadcast_id' => $broadcastId,
                    ],
                    [
                        'dismissed_at' => now(),
                    ]
                );
            }
        } elseif (str_starts_with($id, 'alert_')) {
            $rawId = str_replace('alert_', '', $id);
            if (class_exists(\App\Http\Controllers\Api\NotificationController::class)) {
                try {
                    app(\App\Http\Controllers\Api\NotificationController::class)->dismiss($request, null, $rawId);
                } catch (\Throwable $e) {}
            }
        } else {
            $sysId = str_starts_with($id, 'system_') ? str_replace('system_', '', $id) : $id;
            if ($user && method_exists($user, 'notifications')) {
                try {
                    $user->notifications()->where('id', $sysId)->update(['read_at' => now()]);
                } catch (\Throwable $e) {}
            }
            if (class_exists(\App\Http\Controllers\Api\NotificationController::class)) {
                try {
                    app(\App\Http\Controllers\Api\NotificationController::class)->dismiss($request, null, $sysId);
                } catch (\Throwable $e) {}
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification dismissed successfully.',
            'id'      => $id,
        ]);
    }

    /**
     * Clear all notifications and announcements for the user
     */
    public function clearAll(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Mark all system notifications as read
        if ($user && method_exists($user, 'unreadNotifications')) {
            try {
                $user->unreadNotifications->markAsRead();
            } catch (\Throwable $e) {}
        }

        // 2. Dismiss all currently active announcements and notices for this user
        if (class_exists(PromotionalBroadcast::class) && Schema::hasTable('broadcast_dismissals')) {
            $activeBroadcastIds = PromotionalBroadcast::where('is_active', true)->pluck('id');
            foreach ($activeBroadcastIds as $pId) {
                DB::table('broadcast_dismissals')->updateOrInsert(
                    [
                        'user_id'      => (string) ($user ? $user->id : 1),
                        'broadcast_id' => $pId,
                    ],
                    [
                        'dismissed_at' => now(),
                    ]
                );
            }
        }

        // 3. Dismiss operational alerts (overdue invoices, reminders) via NotificationController
        if (class_exists(\App\Http\Controllers\Api\NotificationController::class)) {
            try {
                app(\App\Http\Controllers\Api\NotificationController::class)->clearAll($request);
            } catch (\Throwable $e) {}
        }

        return response()->json([
            'success'      => true,
            'message'      => 'All notifications permanently cleared.',
            'unread_count' => 0,
        ]);
    }
}
