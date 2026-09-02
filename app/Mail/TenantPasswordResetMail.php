<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public Company $company;

    public function __construct(
        public User $user,
        public string $resetUrl,
        ?Company $company = null,
    ) {
        $this->company = $company ?? $user->company ?? Company::find($user->company_id);
    }

    public function envelope(): Envelope
    {
        $companyName = $this->company->trade_name ?? $this->company->name ?? 'Store';

        return new Envelope(
            subject: "Reset your {$companyName} password",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-password-reset',
            with: [
                'user' => $this->user,
                'company' => $this->company,
                'resetUrl' => $this->resetUrl,
            ],
        );
    }
}
