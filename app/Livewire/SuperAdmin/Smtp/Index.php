<?php

namespace App\Livewire\SuperAdmin\Smtp;

use App\Models\AuditLog;
use App\Models\PlatformBranding;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'SMTP Settings'])]
class Index extends Component
{
    public string $smtpHost = '';

    public ?int $smtpPort = 587;

    public string $smtpUsername = '';

    public string $smtpPassword = '';

    public string $smtpEncryption = 'tls';

    public string $smtpFromAddress = '';

    public string $smtpFromName = '';

    public bool $hasStoredPassword = false;

    public string $testEmailTo = '';

    public function mount(): void
    {
        $branding = PlatformBranding::current();
        $this->smtpHost = (string) $branding->smtp_host;
        $this->smtpPort = $branding->smtp_port ?? 587;
        $this->smtpUsername = (string) $branding->smtp_username;
        $this->smtpEncryption = $branding->smtp_encryption ?? 'tls';
        $this->smtpFromAddress = (string) ($branding->smtp_from_address ?? $branding->support_email ?? '');
        $this->smtpFromName = (string) ($branding->smtp_from_name ?? $branding->platform_name ?? '');
        $this->hasStoredPassword = filled($branding->smtp_password);
        $this->testEmailTo = (string) (auth('platform_web')->user()?->email ?? '');
    }

    protected function rules(): array
    {
        return [
            'smtpHost' => ['nullable', 'string', 'max:255'],
            'smtpPort' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtpUsername' => ['nullable', 'string', 'max:255'],
            'smtpEncryption' => ['nullable', 'in:tls,ssl,'],
            'smtpFromAddress' => ['nullable', 'email', 'max:255'],
            'smtpFromName' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();
        $branding = PlatformBranding::current();

        $update = [
            'smtp_host' => $data['smtpHost'] ?: null,
            'smtp_port' => $data['smtpPort'] ?: null,
            'smtp_username' => $data['smtpUsername'] ?: null,
            'smtp_encryption' => $data['smtpEncryption'] ?: null,
            'smtp_from_address' => $data['smtpFromAddress'] ?: null,
            'smtp_from_name' => $data['smtpFromName'] ?: null,
        ];
        if (filled($this->smtpPassword)) {
            $update['smtp_password'] = $this->smtpPassword;
        }
        $branding->update($update);

        $this->hasStoredPassword = filled($branding->fresh()->smtp_password);
        $this->smtpPassword = '';

        AuditLog::record('smtp.updated', null, auth('platform_web')->id());
        session()->flash('status', 'SMTP settings saved.');
    }

    public function sendTest(): void
    {
        $this->validate(['testEmailTo' => ['required', 'email']]);

        $branding = PlatformBranding::current();
        if (! $branding->smtp_host) {
            session()->flash('error', 'Save SMTP settings before sending a test email.');

            return;
        }

        config([
            'mail.mailers.smtp.host' => $branding->smtp_host,
            'mail.mailers.smtp.port' => $branding->smtp_port,
            'mail.mailers.smtp.username' => $branding->smtp_username,
            'mail.mailers.smtp.password' => $branding->smtp_password,
            'mail.mailers.smtp.encryption' => $branding->smtp_encryption,
            'mail.from.address' => $branding->smtp_from_address ?: ($branding->support_email ?: config('mail.from.address')),
            'mail.from.name' => $branding->smtp_from_name ?: ($branding->platform_name ?: config('mail.from.name')),
        ]);

        try {
            Mail::mailer('smtp')->raw(
                'This is a test email from '.$branding->platform_name.' — SMTP settings are working.',
                fn ($message) => $message->to($this->testEmailTo)->subject('SMTP Test Email')
            );
            session()->flash('status', "Test email sent to {$this->testEmailTo}.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to send: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.superadmin.smtp.index');
    }
}
