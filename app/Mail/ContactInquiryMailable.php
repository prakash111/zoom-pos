<?php

namespace App\Mail;

use App\Models\ContactInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactInquiryMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactInquiry $inquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Contact Inquiry: '.($this->inquiry->subject ?: 'Website Contact Form'),
            replyTo: [$this->inquiry->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-inquiry',
            with: ['inquiry' => $this->inquiry],
        );
    }
}
