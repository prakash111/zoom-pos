<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Sale;
use App\Services\Invoice\InvoiceDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationMailable extends Mailable
{
    use Queueable, SerializesModels;

    public Company $company;

    public function __construct(
        public Sale $quote,
        ?Company $company = null,
        public ?string $customMessage = null,
        public bool $attachPdf = true,
        public ?string $pdfBinary = null
    ) {
        $this->company = $company ?? $quote->company ?? Company::find($quote->company_id);
    }

    public function envelope(): Envelope
    {
        $companyName = $this->company->trade_name ?? $this->company->name ?? 'Store';

        return new Envelope(
            subject: "Quotation / Price Estimate #{$this->quote->sale_number} from {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quotation-proposal',
            with: [
                'quote' => $this->quote,
                'company' => $this->company,
                'customMessage' => $this->customMessage,
                'hasAttachment' => $this->attachPdf,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->attachPdf) {
            return [];
        }

        if (empty($this->pdfBinary)) {
            $this->pdfBinary = app(InvoiceDeliveryService::class)->generateQuotationPdf($this->quote);
        }
        $pdfData = $this->pdfBinary;

        $fileName = "Quotation-{$this->quote->sale_number}.pdf";

        return [
            Attachment::fromData(fn () => $pdfData, $fileName)
                ->withMime('application/pdf'),
        ];
    }
}
