<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DueReminderMailable extends Mailable
{
    use Queueable, SerializesModels;

    public Company $company;

    public function __construct(
        public Sale $sale,
        ?Company $company = null,
    ) {
        $this->company = $company ?? $sale->company ?? Company::find($sale->company_id);
    }

    public function envelope(): Envelope
    {
        $companyName = $this->company->trade_name ?? $this->company->name ?? 'Store';

        return new Envelope(
            subject: "Payment Reminder: Invoice #{$this->sale->sale_number} from {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.due-reminder',
            with: [
                'sale' => $this->sale,
                'company' => $this->company,
            ],
        );
    }
}
