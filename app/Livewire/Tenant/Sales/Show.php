<?php

namespace App\Livewire\Tenant\Sales;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Delivery\MessageQueueService;
use App\Services\FinancialAnalyticsService;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Printing\DesktopPrintService;
use App\Services\DispatchChannelService;
use App\Services\Notifications\DeviceMessageService;
use App\Services\Notifications\TenantNotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Sale Detail'])]
class Show extends Component
{
    public Sale $sale;

    public string $recipientEmail = '';

    public string $recipientPhone = '';

    public bool $attachPdf = true;

    public string $customMessage = '';

    public string $activeChannel = 'email'; // email | whatsapp | sms

    public bool $showSendModal = false;

    public function mount(Sale $sale): void
    {
        $this->sale = $sale;
        $this->recipientEmail = (string) ($sale->customer?->email ?? '');
        $this->recipientPhone = (string) ($sale->customer?->phone ?? '');
        $this->customMessage = app(InvoiceDeliveryService::class)->getDefaultInvoiceMessage($sale);
    }

    public function getWhatsAppUrlProperty(): string
    {
        return DeviceMessageService::appUrl('whatsapp', $this->recipientPhone, $this->deviceMessage);
    }

    public function getWhatsAppApiConfiguredProperty(): bool
    {
        $company = $this->sale->company ?? Company::find($this->sale->company_id);

        return $company && DispatchChannelService::isWhatsAppConfigured($company->id);
    }

    public function getEmailApiConfiguredProperty(): bool
    {
        return DispatchChannelService::isEmailConfigured($this->sale->company_id);
    }

    public function getSmsApiConfiguredProperty(): bool
    {
        return DispatchChannelService::isSmsConfigured($this->sale->company_id);
    }

    public function getDeviceMessageProperty(): string
    {
        $delivery = app(InvoiceDeliveryService::class);
        $message = $delivery->buildInvoiceWhatsAppMessage($this->sale, $this->customMessage);
        $link = route('sales.public', $this->sale->sale_number);
        if (! str_contains($message, $link)) {
            $message .= "\nView online: {$link}";
        }

        return $message;
    }

    public function getEmailUrlProperty(): string
    {
        return DeviceMessageService::url('email', $this->recipientEmail, $this->deviceMessage, 'Invoice #'.$this->sale->sale_number);
    }

    public function getSmsUrlProperty(): string
    {
        return DeviceMessageService::url('sms', $this->recipientPhone, $this->deviceMessage);
    }

    public function sendSms(): void
    {
        $this->validate(['recipientPhone' => ['required', 'string', 'min:6'], 'customMessage' => ['nullable', 'string', 'max:2500']]);
        $company = $this->sale->company ?? Company::findOrFail($this->sale->company_id);
        $result = app(TenantNotificationDispatcherService::class)->dispatchSms($company, $this->recipientPhone, $this->deviceMessage);
        if (($result['status'] ?? '') === 'manual_link') {
            $this->dispatch('open-external-url', url: $result['url']);
        }
        session()->flash(($result['success'] ?? false) ? 'status' : 'error', $result['message'] ?? $result['error'] ?? 'SMS dispatch failed.');
        $this->showSendModal = false;
    }

    public function getDesktopPrintReadyProperty(): bool
    {
        $service = app(DesktopPrintService::class);

        return $service->isDesktop() && filled($service->defaultPrinterFor(
            ($this->sale->company ?? Company::find($this->sale->company_id))?->getReceiptFormat() ?: '80mm'
        ));
    }

    public function printNow(): void
    {
        $company = $this->sale->company ?? Company::find($this->sale->company_id);
        $format = $company?->getReceiptFormat() ?: '80mm';

        $printed = app(DesktopPrintService::class)->printReceiptAuto($this->sale, $format);

        if ($printed) {
            session()->flash('status', "Invoice #{$this->sale->sale_number} sent to the printer.");
        } else {
            $this->dispatch('open-print-preview', url: route('tenant.sales.pdf', ['sale' => $this->sale->id, 'download' => 0, 'embed' => 1]), title: __('Invoice Preview'));
        }
    }

    public function openSendModal(string $channel = 'email'): void
    {
        $this->activeChannel = $channel;
        if (empty($this->customMessage)) {
            $this->customMessage = app(InvoiceDeliveryService::class)->getDefaultInvoiceMessage($this->sale);
        }
        $this->showSendModal = true;
    }

    public function resetMessage(): void
    {
        $this->customMessage = app(InvoiceDeliveryService::class)->getDefaultInvoiceMessage($this->sale);
    }

    public function appendPlaceholder(string $placeholder): void
    {
        $this->customMessage .= ' '.$placeholder;
    }

    public function sendEmail(): void
    {
        $this->validate([
            'recipientEmail' => ['required', 'email'],
            'customMessage' => ['nullable', 'string', 'max:2500'],
        ]);

        try {
            $result = app(MessageQueueService::class)->sendOrQueueEmail(
                $this->sale,
                $this->recipientEmail,
                $this->customMessage,
                $this->attachPdf
            );

            $this->showSendModal = false;
            $attachmentText = $this->attachPdf ? 'with PDF attached' : '(text-only)';

            if ($result['status'] === 'manual_link') {
                $this->dispatch('open-external-url', url: $result['url']);
                session()->flash('status', 'Message prepared. Complete sending in your device app.');
            } elseif ($result['status'] === 'sent') {
                session()->flash('status', "Invoice #{$this->sale->sale_number} sent successfully to {$this->recipientEmail} {$attachmentText}!");
            } else {
                session()->flash('status', "No connection right now — invoice #{$this->sale->sale_number} is queued and will send to {$this->recipientEmail} automatically once you're back online.");
            }
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to send invoice: '.$e->getMessage());
        }
    }

    public function sendWhatsApp(): void
    {
        $this->validate([
            'recipientPhone' => ['required', 'string', 'min:6'],
            'customMessage' => ['nullable', 'string', 'max:2500'],
        ]);

        try {
            $result = app(MessageQueueService::class)->sendOrQueueWhatsApp(
                $this->sale,
                $this->recipientPhone,
                $this->customMessage
            );

            $this->showSendModal = false;

            if ($result['status'] === 'manual_link') {
                $this->dispatch('open-external-url', url: $result['url']);
                session()->flash('status', 'Message prepared. Complete sending in your device app.');
            } elseif ($result['status'] === 'sent') {
                session()->flash('status', "Invoice #{$this->sale->sale_number} sent via WhatsApp to {$this->recipientPhone}!");
            } else {
                session()->flash('status', "No connection right now — the WhatsApp message for invoice #{$this->sale->sale_number} is queued and will send automatically once you're back online.");
            }
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to send WhatsApp message: '.$e->getMessage());
        }
    }

    public function markCompleted(): void
    {
        if ($this->sale->status === 'completed') {
            return;
        }

        DB::transaction(function () {
            foreach ($this->sale->items ?? [] as $item) {
                if (! empty($item['product_id'])) {
                    Product::find($item['product_id'])?->decrement('current_stock', (float) ($item['quantity'] ?? 1));
                }
            }
            $this->sale->update([
                'status' => 'completed',
                'paid_amount' => $this->sale->total,
                'due_amount' => 0,
            ]);
        });

        AuditLog::record('sale.completed', $this->sale->company_id, auth('web')->id(), [
            'sale_id' => $this->sale->id,
            'source' => $this->sale->service_type ?? 'pos',
        ]);

        app(FinancialAnalyticsService::class)->clearCache($this->sale->company_id);

        session()->flash('status', 'Order accepted, marked as completed, and stock deducted.');
    }

    public function cancel(): void
    {
        if ($this->sale->status === 'cancelled') {
            return;
        }

        DB::transaction(function () {
            foreach ($this->sale->items ?? [] as $item) {
                if (! empty($item['product_id'])) {
                    Product::find($item['product_id'])?->increment('current_stock', (float) $item['quantity']);
                }
            }
            $this->sale->update([
                'status' => 'cancelled',
                'commission_amount' => 0,
            ]);
        });

        AuditLog::record('sale.cancelled', $this->sale->company_id, auth('web')->id(), [
            'sale_id' => $this->sale->id,
            'commission_reversed' => true,
        ]);

        app(FinancialAnalyticsService::class)->clearCache($this->sale->company_id);

        session()->flash('status', 'Sale cancelled and stock restored.');
    }

    public function render()
    {
        return view('livewire.tenant.sales.show');
    }
}
