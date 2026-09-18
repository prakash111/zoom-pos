<?php

namespace App\Services\Repair;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\RepairTicket;
use App\Models\User;
use App\Services\Notifications\CustomChannelDispatcherService;
use App\Services\Notifications\DeviceMessageService;
use App\Services\Push\FirebasePushService;
use Illuminate\Support\Facades\Log;

class RepairNotificationService
{
    public function __construct(
        protected FirebasePushService $pushService,
        protected CustomChannelDispatcherService $channelDispatcher,
    ) {}

    /**
     * Open the installed WhatsApp app, only addressing a specific recipient
     * when the phone looks like a real, dialable
     * international number, otherwise open the WhatsApp composer with the
     * message pre-filled so the user can pick the contact. This prevents the
     * client from launching `https://wa.me/?text=` or `wa.me/<too-short>` and
     * getting WhatsApp's "not a valid phone number" error.
     */
    public static function whatsAppUrl(string $message, ?string $phone): string
    {
        return DeviceMessageService::appUrl('whatsapp', (string) $phone, $message);
    }

    public function buildCustomerMessage(RepairTicket $ticket, ?string $status = null): string
    {
        $company = $ticket->company ?? Company::find($ticket->company_id);
        $customer = $ticket->customer?->name ?: ($ticket->customer_name ?: 'Valued Customer');
        $device = trim(($ticket->brand ?? '').' '.($ticket->model ?? '')) ?: 'Device';
        $store = $company?->name ?: 'our service center';
        $currency = $company?->currency_symbol ?: '$';
        $advance = $currency.number_format((float) $ticket->advance_deposit, 2);
        $balance = $currency.number_format((float) $ticket->balance_due, 2);
        $stage = match ($status ?? $ticket->status) {
            RepairTicket::STATUS_RECEIVED => "has been received at {$store}. Advance Paid: {$advance}.",
            RepairTicket::STATUS_DIAGNOSING => "is being diagnosed at {$store}. We are checking the reported issue.",
            RepairTicket::STATUS_WAITING_PARTS => "is waiting for parts at {$store}. We will continue the repair once the required parts arrive.",
            RepairTicket::STATUS_IN_PROGRESS => "is being repaired at {$store}. Work is in progress.",
            RepairTicket::STATUS_READY => "has been repaired and is ready for pickup at {$store}. Balance Due: {$balance}.",
            RepairTicket::STATUS_DELIVERED => "has been delivered and closed at {$store}. Thank you for choosing us!",
            RepairTicket::STATUS_CANCELLED => "has been cancelled at {$store}. Please contact us for any questions.",
            default => 'is now '.(RepairTicket::STATUSES[$status ?? $ticket->status] ?? ucwords(str_replace('_', ' ', $status ?? $ticket->status)))." at {$store}.",
        };

        return "Hello {$customer}, repair ticket #{$ticket->ticket_number} for your {$device} {$stage} Track progress: ".route('repair.portal.track', $ticket->ticket_number);
    }

    /**
     * One shared customer notification for every operational status change.
     * Ready-for-pickup retains its richer balance/push notification payload.
     *
     * @return array{whatsapp_url: string, sms_text: string, status: string, dispatched_channels?: int, balance_due?: float}
     */
    public function notifyStatusTransition(RepairTicket $ticket, string $oldStatus, string $newStatus): array
    {
        if ($newStatus === RepairTicket::STATUS_READY) {
            return array_merge(
                ['status' => $newStatus],
                $this->notifyStatusReadyForPickup($ticket),
            );
        }

        $customerName = $ticket->customer?->name ?: ($ticket->customer_name ?: 'Valued Customer');
        $phone = $ticket->customer?->phone ?: $ticket->customer_phone;
        $statusLabel = RepairTicket::STATUSES[$newStatus] ?? ucwords(str_replace('_', ' ', $newStatus));
        $device = trim(($ticket->brand ?? '').' '.($ticket->model ?? '')) ?: 'device';
        $smsText = $this->buildCustomerMessage($ticket, $newStatus);
        $whatsappUrl = self::whatsAppUrl($smsText, $phone);

        $dispatched = 0;
        try {
            $dispatched = $this->channelDispatcher->dispatchEvent($ticket->company_id, 'repair.status_changed', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'customer_name' => $customerName,
                'customer_phone' => $phone,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'status_label' => $statusLabel,
                'device' => $device,
                'sms_text' => $smsText,
                'whatsapp_url' => $whatsappUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to dispatch repair.status_changed: {$e->getMessage()}");
        }

        return [
            'status' => $newStatus,
            'whatsapp_url' => $whatsappUrl,
            'sms_text' => $smsText,
            'dispatched_channels' => $dispatched,
        ];
    }

    /**
     * Dispatches notification when a new intake ticket is created.
     * Generates customer tracking link, WhatsApp URL, SMS text, and internal notification.
     *
     * @return array{tracking_url: string, whatsapp_url: string, sms_text: string, intake_sheet_url: string}
     */
    public function notifyTicketCreated(RepairTicket $ticket): array
    {
        $customerName = $ticket->customer?->name ?: ($ticket->customer_name ?: 'Valued Customer');
        $phone = $ticket->customer?->phone ?: $ticket->customer_phone;

        $trackingUrl = route('repair.portal.track', $ticket->ticket_number);
        $intakeSheetUrl = url("/api/tenant/repair/tickets/{$ticket->id}/intake-sheet");

        $device = trim(($ticket->brand ?? '').' '.($ticket->model ?? ''));
        if ($device === '') {
            $device = 'Device';
        }

        $smsText = $this->buildCustomerMessage($ticket, RepairTicket::STATUS_RECEIVED);
        $whatsappUrl = self::whatsAppUrl($smsText, $phone);

        // 1. Dispatch through registered webhooks / custom channels (SMS, WhatsApp API gateways)
        try {
            $this->channelDispatcher->dispatchEvent($ticket->company_id, 'repair.ticket_created', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'customer_name' => $customerName,
                'customer_phone' => $phone,
                'device' => $device,
                'problem_reported' => $ticket->problem_reported,
                'advance_deposit' => $ticket->advance_deposit,
                'estimated_cost' => $ticket->estimated_cost,
                'tracking_url' => $trackingUrl,
                'intake_sheet_url' => $intakeSheetUrl,
                'sms_text' => $smsText,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to dispatch repair.ticket_created channel: {$e->getMessage()}");
        }

        // 2. Notify assigned technician if assigned immediately at intake
        if ($ticket->assigned_technician_id) {
            $tech = $ticket->technician ?? User::find($ticket->assigned_technician_id);
            if ($tech) {
                $this->notifyTechnicianAssigned($ticket, $tech);
            }
        }

        AuditLog::record('repair.ticket_created_notified', $ticket->company_id, auth()->id(), [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'phone' => $phone,
        ]);

        return [
            'tracking_url' => $trackingUrl,
            'whatsapp_url' => $whatsappUrl,
            'sms_text' => $smsText,
            'intake_sheet_url' => $intakeSheetUrl,
        ];
    }

    /**
     * Dispatches notification when a ticket status moves to 'ready' (Ready for Pickup).
     * Alerts customer via Push Notification, WhatsApp URL, and SMS with exact balance due.
     *
     * @return array{whatsapp_url: string, sms_text: string, balance_due: float}
     */
    public function notifyStatusReadyForPickup(RepairTicket $ticket): array
    {
        $company = $ticket->company ?? Company::find($ticket->company_id);
        $customerName = $ticket->customer?->name ?: ($ticket->customer_name ?: 'Valued Customer');
        $phone = $ticket->customer?->phone ?: $ticket->customer_phone;

        $currency = $company?->currency_symbol ?: '$';
        $balanceDue = $ticket->balance_due;
        $balanceFormatted = $currency.number_format($balanceDue, 2);

        $device = trim(($ticket->brand ?? '').' '.($ticket->model ?? ''));
        if ($device === '') {
            $device = 'Device';
        }

        $smsText = $this->buildCustomerMessage($ticket, RepairTicket::STATUS_READY);
        $whatsappUrl = self::whatsAppUrl($smsText, $phone);

        // 1. Dispatch Push Notification to all active store devices & customer apps
        try {
            $this->pushService->sendToCompany($ticket->company_id, [
                'type' => 'repair_ticket_ready',
                'title' => "Device Ready for Pickup: #{$ticket->ticket_number}",
                'body' => "{$device} for {$customerName} is ready. Balance: {$balanceFormatted}",
                'ticket_id' => (string) $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'balance_due' => (string) $balanceDue,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to send push for repair.ticket_ready: {$e->getMessage()}");
        }

        // 2. Dispatch custom channels (SMS / WhatsApp gateway)
        try {
            $this->channelDispatcher->dispatchEvent($ticket->company_id, 'repair.ticket_ready', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'customer_name' => $customerName,
                'customer_phone' => $phone,
                'device' => $device,
                'balance_due' => $balanceDue,
                'sms_text' => $smsText,
                'whatsapp_url' => $whatsappUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to dispatch repair.ticket_ready channel: {$e->getMessage()}");
        }

        AuditLog::record('repair.status_ready_notified', $ticket->company_id, auth()->id(), [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'balance_due' => $balanceDue,
        ]);

        return [
            'whatsapp_url' => $whatsappUrl,
            'sms_text' => $smsText,
            'balance_due' => $balanceDue,
        ];
    }

    /**
     * Sends internal high-priority app push notification to assigned technician.
     */
    public function notifyTechnicianAssigned(RepairTicket $ticket, User $technician): int
    {
        $device = trim(($ticket->brand ?? '').' '.($ticket->model ?? ''));
        $isUrgent = $ticket->priority === RepairTicket::PRIORITY_URGENT || $ticket->priority === 'urgent';

        $title = ($isUrgent ? '🚨 [URGENT] ' : '')."Repair Assigned: #{$ticket->ticket_number}";
        $body = "Device: {$device} | Priority: ".strtoupper($ticket->priority).' | Problem: '.substr($ticket->problem_reported, 0, 80);

        $sent = 0;
        try {
            $sent = $this->pushService->sendToUser($ticket->company_id, (string) $technician->id, [
                'type' => 'repair_technician_assigned',
                'title' => $title,
                'body' => $body,
                'ticket_id' => (string) $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'priority' => $ticket->priority,
                'is_urgent' => $isUrgent ? '1' : '0',
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to send technician push notification: {$e->getMessage()}");
        }

        AuditLog::record('repair.technician_assigned_notified', $ticket->company_id, auth()->id(), [
            'ticket_id' => $ticket->id,
            'technician_id' => $technician->id,
            'sent_devices' => $sent,
        ]);

        return $sent;
    }

    /**
     * Background routine to send pending pickup reminders for devices ready > 48 hours.
     *
     * @return int Number of reminder alerts dispatched
     */
    public function sendPendingPickupReminders(int $hoursOverdue = 48): int
    {
        $cutoff = now()->subHours($hoursOverdue);

        $tickets = RepairTicket::withoutGlobalScope('company')
            ->whereIn('status', [RepairTicket::STATUS_READY, 'ready', 'repaired'])
            ->where('updated_at', '<=', $cutoff)
            ->whereNull('delivered_at')
            ->with(['company', 'customer'])
            ->get();

        $count = 0;
        foreach ($tickets as $ticket) {
            $company = $ticket->company;
            $customerName = $ticket->customer?->name ?: ($ticket->customer_name ?: 'Valued Customer');
            $phone = $ticket->customer?->phone ?: $ticket->customer_phone;
            if (empty($phone)) {
                continue;
            }

            $currency = $company?->currency_symbol ?: '$';
            $balanceFormatted = $currency.number_format($ticket->balance_due, 2);
            $device = trim(($ticket->brand ?? '').' '.($ticket->model ?? 'Device'));

            $smsText = "Reminder: Your {$device} (Ticket #{$ticket->ticket_number}) is ready for pickup at ".($company?->name ?? 'our store').". Outstanding Balance: {$balanceFormatted}. Please pick up at your earliest convenience.";
            $whatsappUrl = self::whatsAppUrl($smsText, $phone);

            try {
                $this->channelDispatcher->dispatchEvent($ticket->company_id, 'repair.pickup_reminder', [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'customer_name' => $customerName,
                    'customer_phone' => $phone,
                    'device' => $device,
                    'balance_due' => $ticket->balance_due,
                    'sms_text' => $smsText,
                    'whatsapp_url' => $whatsappUrl,
                    'hours_waiting' => (int) round(now()->diffInHours($ticket->updated_at)),
                ]);
                $count++;
            } catch (\Throwable $e) {
                Log::warning("Failed to dispatch repair.pickup_reminder: {$e->getMessage()}");
            }
        }

        return $count;
    }
}
