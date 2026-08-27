<?php

namespace App\Livewire\Public;

use App\Mail\ContactInquiryMailable;
use App\Models\ContactInquiry;
use App\Models\PlatformBranding;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $storeType = '';

    public string $phone = '';

    public string $subject = '';

    public string $message = '';

    public bool $submitted = false;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'storeType' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    public function submit(): void
    {
        $validated = $this->validate();
        $validated['store_type'] = $validated['storeType'];
        unset($validated['storeType']);

        $inquiry = ContactInquiry::create($validated);

        $recipient = PlatformBranding::current()->support_email ?: config('mail.from.address');

        if ($recipient) {
            try {
                Mail::to($recipient)->send(new ContactInquiryMailable($inquiry));
            } catch (\Throwable $e) {
                // The inquiry is already safely stored — a mail transport
                // hiccup shouldn't block the visitor's submission from succeeding.
                Log::warning('Failed to send contact inquiry notification email.', ['error' => $e->getMessage()]);
            }
        }

        $this->reset(['name', 'email', 'storeType', 'phone', 'subject', 'message']);
        $this->submitted = true;
        $this->dispatch('toast', message: 'Message sent — we\'ll get back to you shortly.');
    }

    public function render()
    {
        return view('livewire.public.contact-form');
    }
}
