<?php

namespace App\Livewire\Tenant;

use App\Models\KitchenTicket;
use App\Models\PushNotificationSetting;
use App\Models\Sale;
use App\Services\Push\FirebasePushService;
use Livewire\Component;

class SystemAlarmBanner extends Component
{
    public array $orderAlarms = [];

    public array $invoiceAlarms = [];

    public int $lastOrderChimeAt = 0;

    public array $seenInvoiceIds = [];

    public function mount(): void
    {
        $this->refreshAlarms(false);
    }

    public function refreshAlarms(bool $playSound = true): void
    {
        $orders = KitchenTicket::query()
            ->whereIn('status', [KitchenTicket::STATUS_PENDING, KitchenTicket::STATUS_PREPARING, KitchenTicket::STATUS_READY])
            ->whereNotNull('alarm_at')
            ->where('alarm_at', '<=', now())
            ->whereNull('alarm_dismissed_at')
            ->orderBy('alarm_at')
            ->limit(8)
            ->get();

        $invoices = Sale::query()
            ->where('status', '!=', 'cancelled')
            ->where('due_amount', '>', 0)
            ->whereNotNull('due_reminder_at')
            ->where('due_reminder_at', '<=', now())
            ->whereNull('due_reminder_dismissed_at')
            ->orderBy('due_reminder_at')
            ->limit(8)
            ->get();

        $this->orderAlarms = $orders->map(fn (KitchenTicket $ticket) => [
            'id' => $ticket->id,
            'number' => $ticket->kot_number,
            'location' => $ticket->table_name ?: ucfirst(str_replace('_', ' ', $ticket->service_type)),
        ])->all();
        $this->invoiceAlarms = $invoices->map(fn (Sale $sale) => [
            'id' => $sale->id,
            'number' => $sale->sale_number ?: 'Invoice #'.$sale->id,
            'customer' => $sale->customer_name ?: 'Customer',
            'amount' => number_format((float) $sale->due_amount, 2),
        ])->all();

        if (! $playSound) {
            $this->seenInvoiceIds = $invoices->modelKeys();

            return;
        }

        $repeat = max(15, PushNotificationSetting::current()->alarm_repeat_seconds);
        $newInvoiceIds = array_diff($invoices->modelKeys(), $this->seenInvoiceIds);
        $orderShouldChime = $orders->isNotEmpty() && (time() - $this->lastOrderChimeAt >= $repeat);

        if ($orderShouldChime || $newInvoiceIds !== []) {
            $this->dispatch('system-alarm-chime', order: $orderShouldChime);
            if ($orderShouldChime) {
                $this->lastOrderChimeAt = time();
            }
        }
        $this->seenInvoiceIds = $invoices->modelKeys();
    }

    public function dismissOrder(string $id): void
    {
        $ticket = KitchenTicket::findOrFail($id);
        $ticket->update(['alarm_dismissed_at' => now()]);
        rescue(fn () => app(FirebasePushService::class)->sendToCompany($ticket->company_id, [
            'type' => 'delayed_order_alarm', 'action' => 'clear',
            'notification_id' => 'order_'.$ticket->id, 'kitchen_ticket_id' => $ticket->id,
        ]), report: true);
        $this->refreshAlarms(false);
    }

    public function dismissInvoice(int $id): void
    {
        Sale::where('due_amount', '>', 0)->findOrFail($id)->update(['due_reminder_dismissed_at' => now()]);
        $this->refreshAlarms(false);
    }

    public function render()
    {
        return view('livewire.tenant.system-alarm-banner');
    }
}
