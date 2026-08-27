<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $recipientName = 'Store Administrator',
        public string $platformName = 'Smart Inventory & Sales',
        public int $validMinutes = 15,
        public ?string $fromEmail = null,
        public ?string $fromSenderName = null,
    ) {}

    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: "Your {$this->platformName} Verification Code: {$this->otp}",
        );

        if ($this->fromEmail) {
            $envelope->from(new Address($this->fromEmail, $this->fromSenderName ?: $this->platformName));
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.otp',
            with: [
                'otp' => $this->otp,
                'recipientName' => $this->recipientName,
                'platformName' => $this->platformName,
                'validMinutes' => $this->validMinutes,
            ],
        );
    }
}
