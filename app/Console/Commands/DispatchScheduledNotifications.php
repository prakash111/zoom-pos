<?php

namespace App\Console\Commands;

use App\Models\KitchenTicket;
use App\Models\Sale;
use App\Services\Notifications\CustomChannelDispatcherService;
use App\Services\Push\FirebasePushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchScheduledNotifications extends Command
{
    protected $signature = 'notifications:dispatch-scheduled';

    protected $description = 'Dispatch delayed-order alarms and due-invoice reminders which have reached their scheduled time';

    public function handle(FirebasePushService $push, CustomChannelDispatcherService $channelDispatcher): int
    {
        $this->dispatchOrderAlarms($push, $channelDispatcher);
        $this->dispatchInvoiceReminders($push, $channelDispatcher);

        return self::SUCCESS;
    }

    private function dispatchOrderAlarms(FirebasePushService $push, CustomChannelDispatcherService $channelDispatcher): void
    {
        KitchenTicket::withoutGlobalScope('company')
            ->whereIn('status', [KitchenTicket::STATUS_PENDING, KitchenTicket::STATUS_PREPARING, KitchenTicket::STATUS_READY])
            ->whereNotNull('alarm_at')
            ->where('alarm_at', '<=', now())
            ->whereNull('alarm_sent_at')
            ->whereNull('alarm_dismissed_at')
            ->chunkById(100, function ($tickets) use ($push, $channelDispatcher) {
                foreach ($tickets as $ticket) {
                    try {
                        $delivered = $push->sendToCompany($ticket->company_id, [
                            'type' => 'delayed_order_alarm',
                            'action' => 'alarm',
                            'notification_id' => 'order_'.$ticket->id,
                            'title' => 'Kitchen order needs attention',
                            'body' => $ticket->kot_number.' · '.($ticket->table_name ?: ucfirst(str_replace('_', ' ', $ticket->service_type))),
                            'kitchen_ticket_id' => $ticket->id,
                            'kot_number' => $ticket->kot_number,
                            'route' => '/restaurant/kds?kot='.$ticket->id,
                            'persistent' => true,
                        ]);

                        // Automated trigger for delayed order alerts (KDS)
                        $channelDispatcher->dispatchEvent($ticket->company_id, 'delayed_order_alert', [
                            'customer_name' => $ticket->table_name ?: 'Dine-In',
                            'invoice_id' => $ticket->kot_number,
                            'amount' => '0.00',
                            'order_link' => url('/restaurant/kds?kot='.$ticket->id),
                        ]);

                        if ($delivered > 0) {
                            $ticket->update(['alarm_sent_at' => now()]);
                        }
                    } catch (Throwable $exception) {
                        Log::error('Unable to dispatch delayed-order alarm.', [
                            'kitchen_ticket_id' => $ticket->id,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }
            }, 'id');
    }

    private function dispatchInvoiceReminders(FirebasePushService $push, CustomChannelDispatcherService $channelDispatcher): void
    {
        Sale::withoutGlobalScope('company')
            ->where('due_amount', '>', 0)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('due_reminder_at')
            ->where('due_reminder_at', '<=', now())
            ->whereNull('due_reminder_sent_at')
            ->whereNull('due_reminder_dismissed_at')
            ->chunkById(100, function ($sales) use ($push, $channelDispatcher) {
                foreach ($sales as $sale) {
                    try {
                        $delivered = $push->sendToCompany($sale->company_id, [
                            'type' => 'due_invoice_reminder',
                            'action' => 'open_invoice',
                            'notification_id' => 'invoice_'.$sale->id,
                            'title' => 'Invoice payment is due',
                            'body' => ($sale->sale_number ?: 'Invoice').' · '.number_format((float) $sale->due_amount, 2).' outstanding',
                            'sale_id' => $sale->id,
                            'sale_number' => $sale->sale_number,
                            'route' => '/invoices/'.$sale->id,
                        ]);

                        // Automated trigger for due invoice reminders
                        $channelDispatcher->dispatchEvent($sale->company_id, 'due_invoice_reminder', [
                            'phone' => $sale->customer?->phone ?? '',
                            'customer_name' => $sale->customer_name ?: 'Customer',
                            'invoice_id' => $sale->sale_number,
                            'amount' => number_format((float) $sale->due_amount, 2),
                            'order_link' => url('/invoices/'.$sale->id),
                        ]);

                        if ($delivered > 0) {
                            $sale->update(['due_reminder_sent_at' => now()]);
                        }
                    } catch (Throwable $exception) {
                        Log::error('Unable to dispatch due-invoice reminder.', [
                            'sale_id' => $sale->id,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }
}
