<?php

namespace App\Livewire\Tenant\Quotes;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Delivery\MessageQueueService;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Printing\DesktopPrintService;
use App\Services\WhatsApp\WhatsAppCloudApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Quotation Detail'])]
class Show extends Component
{
    public Sale $quote;

    public string $recipientEmail = '';

    public string $recipientPhone = '';

    public bool $attachPdf = true;

    public string $customMessage = '';

    public string $activeChannel = 'email'; // email | whatsapp

    public bool $showSendModal = false;

    public function mount(Sale $quote): void
    {
        $this->quote = $quote;
        $this->recipientEmail = (string) ($quote->customer?->email ?? '');
        $this->recipientPhone = (string) ($quote->customer?->phone ?? '');
        $this->customMessage = app(InvoiceDeliveryService::class)->getDefaultQuotationMessage($quote);
    }

    public function getWhatsAppUrlProperty(): string
    {
        return app(InvoiceDeliveryService::class)->generateQuotationWhatsAppUrl(
            $this->quote,
            $this->recipientPhone,
            $this->customMessage
        );
    }

    public function openSendModal(string $channel = 'email'): void
    {
        $this->activeChannel = $channel;
        if (empty($this->customMessage)) {
            $this->customMessage = app(InvoiceDeliveryService::class)->getDefaultQuotationMessage($this->quote);
        }
        $this->showSendModal = true;
    }

    public function resetMessage(): void
    {
        $this->customMessage = app(InvoiceDeliveryService::class)->getDefaultQuotationMessage($this->quote);
    }

    public function appendPlaceholder(string $placeholder): void
    {
        $this->customMessage .= ' '.$placeholder;
    }

    public function getWhatsAppApiConfiguredProperty(): bool
    {
        $company = $this->quote->company ?? Company::find($this->quote->company_id);

        return $company && app(WhatsAppCloudApiClient::class)->isConfigured($company);
    }

    public function getDesktopPrintReadyProperty(): bool
    {
        $service = app(DesktopPrintService::class);

        return $service->isDesktop() && filled($service->defaultPrinterFor('a4'));
    }

    public function printNow(): void
    {
        $printed = app(DesktopPrintService::class)->printA4DocumentAuto($this->quote);

        if ($printed) {
            session()->flash('status', "Quotation #{$this->quote->sale_number} sent to the printer.");
        } else {
            $this->dispatch('open-print-preview', url: route('tenant.quotes.pdf', ['quote' => $this->quote->id, 'download' => 0, 'embed' => 1]), title: __('Quotation Preview'));
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
                $this->quote,
                $this->recipientPhone,
                $this->customMessage
            );

            if ($this->quote->status === 'draft' && $result['status'] === 'sent') {
                $this->quote->update(['status' => 'sent']);
            }

            AuditLog::record('quotation.whatsapp_shared', $this->quote->company_id, auth('web')->id(), [
                'quote_id' => $this->quote->id,
                'recipient_phone' => $this->recipientPhone,
                'status' => $result['status'],
            ]);

            $this->showSendModal = false;

            if ($result['status'] === 'sent') {
                session()->flash('status', "Quotation #{$this->quote->sale_number} sent via WhatsApp to {$this->recipientPhone}!");
            } else {
                session()->flash('status', "No connection right now — the WhatsApp message for quotation #{$this->quote->sale_number} is queued and will send automatically once you're back online.");
            }
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to send WhatsApp message: '.$e->getMessage());
        }
    }

    public function trackWhatsAppSent(): void
    {
        if ($this->quote->status === 'draft') {
            $this->quote->update(['status' => 'sent']);
        }

        AuditLog::record('quotation.whatsapp_shared', $this->quote->company_id, auth('web')->id(), [
            'quote_id' => $this->quote->id,
            'recipient_phone' => $this->recipientPhone,
        ]);

        $this->showSendModal = false;
        session()->flash('status', "Quotation #{$this->quote->sale_number} shared via WhatsApp.");
    }

    public function setStatus(string $newStatus): void
    {
        if (! in_array($newStatus, ['draft', 'sent', 'accepted', 'rejected'])) {
            return;
        }

        $this->quote->update(['status' => $newStatus]);

        if ($this->quote->lead_id && in_array($newStatus, ['accepted', 'won'])) {
            $lead = \App\Models\Lead::find($this->quote->lead_id);
            if ($lead) {
                $lead->update([
                    'stage' => 'won',
                    'status' => 'won',
                    'converted_at' => now(),
                ]);
                \Modules\leadmanagement\Models\LeadActivity::create([
                    'company_id' => $this->quote->company_id,
                    'lead_id' => $lead->id,
                    'type' => 'note',
                    'title' => 'Proposal Accepted',
                    'description' => "Quotation #{$this->quote->sale_number} was accepted. Lead marked Won.",
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }
        }

        AuditLog::record('quotation.status_changed', $this->quote->company_id, auth('web')->id(), [
            'quote_id' => $this->quote->id,
            'new_status' => $newStatus,
        ]);

        session()->flash('status', 'Quotation status marked as '.ucfirst($newStatus).'.');
    }

    public function sendEmail(): void
    {
        $this->validate([
            'recipientEmail' => ['required', 'email'],
            'customMessage' => ['nullable', 'string', 'max:2500'],
        ]);

        try {
            $result = app(MessageQueueService::class)->sendOrQueueEmail(
                $this->quote,
                $this->recipientEmail,
                $this->customMessage,
                $this->attachPdf
            );

            $this->quote->refresh();
            $this->showSendModal = false;
            $attachmentText = $this->attachPdf ? 'with PDF attached' : '(text-only)';

            if ($result['status'] === 'sent') {
                session()->flash('status', "Quotation #{$this->quote->sale_number} proposal sent successfully to {$this->recipientEmail} {$attachmentText}!");
            } else {
                session()->flash('status', "No connection right now — quotation #{$this->quote->sale_number} is queued and will send to {$this->recipientEmail} automatically once you're back online.");
            }
        } catch (\Throwable $e) {
            Log::error("Quotation email dispatch failure for #{$this->quote->sale_number}: ".$e->getMessage(), [
                'quote_id' => $this->quote->id,
                'recipient' => $this->recipientEmail,
                'trace' => $e->getTraceAsString(),
            ]);
            session()->flash('error', 'Failed to send quotation email: '.$e->getMessage());
        }
    }

    public function convertToSale()
    {
        $user = auth('web')->user();
        abort_unless($user && $user->hasPermission('quotes', 'convert_to_sale'), 403, 'Unauthorized: quotes.convert_to_sale permission required.');

        if ($this->quote->status === 'converted') {
            session()->flash('error', 'This quotation has already been converted to a sale.');

            return;
        }

        $companyId = $this->quote->company_id;
        $company = Company::find($companyId);
        $prefix = trim((string) ($company?->invoice_prefix ?: 'INV-'));
        if ($prefix === '' || strlen($prefix) > 8) {
            $prefix = 'INV-';
        }
        $prefix = str_ends_with($prefix, '-') ? $prefix : $prefix.'-';
        $count = Sale::where('operation_type', 'sale')->count() + 1;
        $saleNumber = $prefix.sprintf('%03d', $count);

        $sale = DB::transaction(function () use ($companyId, $saleNumber) {
            foreach ($this->quote->items ?? [] as $item) {
                if (! empty($item['product_id'])) {
                    Product::find($item['product_id'])?->decrement('current_stock', (float) $item['quantity']);
                }
            }

            $paymentMethod = $this->quote->agreed_payment_method ?: ($this->quote->payment_method ?: 'cash');

            $sale = Sale::create([
                'company_id' => $companyId,
                'sale_number' => $saleNumber,
                'customer_id' => $this->quote->customer_id,
                'lead_id' => $this->quote->lead_id,
                'customer_name' => $this->quote->customer_name,
                'user_id' => auth('web')->id(),
                'total' => $this->quote->total,
                'paid_amount' => $this->quote->total,
                'due_amount' => 0.00,
                'discount' => $this->quote->discount,
                'tax_amount' => $this->quote->tax_amount,
                'tax_name' => $this->quote->tax_name,
                'tax_rate' => $this->quote->tax_rate,
                'tax_breakdown' => $this->quote->tax_breakdown,
                'payment_method' => $paymentMethod,
                'agreed_payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'status' => 'completed',
                'operation_type' => 'sale',
                'service_type' => 'dine_in',
                'items' => $this->quote->items,
                'notes' => $this->quote->notes,
            ]);

            OrderPayment::create([
                'company_id' => $companyId,
                'sale_id' => $sale->id,
                'payment_method' => $paymentMethod,
                'amount' => $sale->total,
            ]);

            $this->quote->update(['status' => 'converted']);

            if ($this->quote->lead_id) {
                $lead = \App\Models\Lead::find($this->quote->lead_id);
                if ($lead) {
                    $lead->update([
                        'stage' => 'won',
                        'status' => 'won',
                        'converted_at' => now(),
                    ]);
                    \Modules\leadmanagement\Models\LeadActivity::create([
                        'company_id' => $companyId,
                        'lead_id' => $lead->id,
                        'type' => 'note',
                        'title' => 'Sale Finalized',
                        'description' => "Quotation #{$this->quote->sale_number} converted to Invoice #{$saleNumber}. Lead Won.",
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);
                }
            }

            return $sale;
        });

        AuditLog::record('quotation.converted_to_sale', $companyId, auth('web')->id(), [
            'quote_id' => $this->quote->id,
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
        ]);

        session()->flash('status', "Quotation {$this->quote->sale_number} converted to Sale {$sale->sale_number}.");

        $this->redirectRoute('tenant.sales.show', $sale, navigate: true);
    }

    public function openInPos()
    {
        $user = auth('web')->user();
        abort_unless($user && $user->hasPermission('quotes', 'convert_to_sale'), 403, 'Unauthorized: quotes.convert_to_sale permission required.');

        $this->redirectRoute('tenant.sales.create', ['quote_id' => $this->quote->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.tenant.quotes.show');
    }
}
