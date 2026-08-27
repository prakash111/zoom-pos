<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\SubscriptionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantSubscriptionInvoiceMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public SubscriptionInvoice $invoice,
        public Company $company
    ) {}

    public function envelope(): Envelope
    {
        $platformName = $this->invoice->seller_details['company_name'] ?? config('app.name');

        return new Envelope(
            subject: "Tax Invoice #{$this->invoice->invoice_number} from {$platformName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-invoice',
            with: [
                'invoice' => $this->invoice,
                'company' => $this->company,
            ],
        );
    }
}
