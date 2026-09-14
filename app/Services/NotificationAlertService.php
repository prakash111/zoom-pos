<?php

namespace App\Services;

use App\Models\Reminder;
use App\Models\Sale;
use App\Models\TenantNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class NotificationAlertService
{
    public function unreadCount(mixed $companyId): int
    {
        if (! $companyId) {
            return 0;
        }

        $dueInvoices = Schema::hasTable('sales')
            ? $this->dueInvoicesQuery($companyId)->count()
            : 0;
        $leadReminders = Schema::hasTable('reminders')
            ? $this->leadRemindersQuery($companyId)->count()
            : 0;
        $storedNotifications = Schema::hasTable('tenant_notifications')
            ? $this->storedNotificationsQuery($companyId)->count()
            : 0;

        return $dueInvoices + $leadReminders + $storedNotifications;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function alerts(mixed $companyId): Collection
    {
        $alerts = collect();

        foreach ($this->dueInvoices($companyId) as $invoice) {
            $reference = $invoice->sale_number ?: (string) $invoice->id;
            $due = (float) ($invoice->due_amount ?? $invoice->total ?? 0);
            $alerts->push([
                'type' => 'notification_item',
                'category' => 'invoice',
                'icon' => 'receipt_long',
                'icon_color' => '#EF4444',
                'title' => "Due Payment: #{$reference}",
                'subtitle' => $invoice->company?->formatMoney($due).' overdue from '.($invoice->customer?->name ?: ($invoice->customer_name ?: 'Client')),
                'timestamp' => optional($invoice->due_date)->toIso8601String(),
                'action_type' => 'OPEN_BOTTOM_SHEET',
                'route' => "/api/v1/tenant/documents/invoice/{$invoice->id}/preview-modal",
                'action' => [
                    'type' => 'OPEN_BOTTOM_SHEET',
                    'title' => "Preview & Dispatch #{$reference}",
                    'endpoint' => "/api/v1/tenant/documents/invoice/{$invoice->id}/preview-modal",
                ],
                'background_color' => 'theme.surface',
                'divider_color' => 'theme.divider',
                'text_color' => 'theme.textPrimary',
            ]);
        }

        foreach ($this->leadReminders($companyId) as $reminder) {
            $dueAt = $reminder->due_at ?? $reminder->due_date;
            $alerts->push([
                'type' => 'notification_item',
                'category' => 'lead',
                'icon' => 'person_pin',
                'icon_color' => '#38BDF8',
                'title' => $reminder->subject ?: ($reminder->title ?: 'Lead Follow-up'),
                'subtitle' => 'Scheduled for '.optional($dueAt)->format('M d, H:i'),
                'timestamp' => optional($dueAt)->toIso8601String(),
                'action_type' => 'NAVIGATE_TO',
                'route' => "/api/tenant/views/leads/{$reminder->remindable_id}",
                'action' => [
                    'type' => 'NAVIGATE_TO',
                    'route' => "/api/tenant/views/leads/{$reminder->remindable_id}",
                ],
                'background_color' => 'theme.surface',
                'divider_color' => 'theme.divider',
                'text_color' => 'theme.textPrimary',
            ]);
        }

        foreach ($this->storedNotifications($companyId) as $notification) {
            $category = strtolower((string) ($notification->category ?: 'system'));
            $icon = match ($category) {
                'quotation', 'quote' => 'request_quote',
                'sale', 'sales' => 'point_of_sale',
                'lead' => 'person_pin',
                'invoice' => 'receipt_long',
                default => 'notifications_active',
            };
            $route = $notification->cta_url;
            $action = filled($route) ? [
                'type' => str_starts_with((string) $route, '/api/') ? 'NAVIGATE_TO' : 'OPEN_URL',
                str_starts_with((string) $route, '/api/') ? 'route' : 'url' => $route,
            ] : null;

            $alerts->push(array_filter([
                'type' => 'notification_item',
                'category' => $category,
                'icon' => $icon,
                'icon_color' => '#F59E0B',
                'title' => $notification->title,
                'subtitle' => $notification->message,
                'timestamp' => optional($notification->created_at)->toIso8601String(),
                'action_type' => $action['type'] ?? null,
                'route' => $route,
                'action' => $action,
                'background_color' => 'theme.surface',
                'divider_color' => 'theme.divider',
                'text_color' => 'theme.textPrimary',
            ], fn ($value) => $value !== null));
        }

        return $alerts->sortByDesc('timestamp')->values();
    }

    private function dueInvoices(mixed $companyId): Collection
    {
        if (! $companyId || ! Schema::hasTable('sales')) {
            return collect();
        }

        return $this->dueInvoicesQuery($companyId)
            ->with(['customer', 'company'])
            ->orderBy('due_date')
            ->limit(5)
            ->get();
    }

    private function leadReminders(mixed $companyId): Collection
    {
        if (! $companyId || ! Schema::hasTable('reminders')) {
            return collect();
        }

        return $this->leadRemindersQuery($companyId)
            ->orderByRaw('COALESCE(due_at, due_date) asc')
            ->limit(5)
            ->get();
    }

    private function storedNotifications(mixed $companyId): Collection
    {
        if (! $companyId || ! Schema::hasTable('tenant_notifications')) {
            return collect();
        }

        return $this->storedNotificationsQuery($companyId)
            ->latest()
            ->limit(10)
            ->get();
    }

    private function dueInvoicesQuery(mixed $companyId): Builder
    {
        return Sale::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->where('operation_type', 'sale')->orWhereNull('operation_type');
            })
            ->where('due_amount', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', now());
    }

    private function leadRemindersQuery(mixed $companyId): Builder
    {
        return Reminder::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('status', Reminder::STATUS_PENDING)
            ->where(function ($query) {
                $query->where('due_at', '<=', now()->addDay())
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('due_at')->where('due_date', '<=', now()->addDay());
                    });
            });
    }

    private function storedNotificationsQuery(mixed $companyId): Builder
    {
        return TenantNotification::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('read_status', false);
    }
}
