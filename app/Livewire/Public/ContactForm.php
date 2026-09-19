<?php

namespace App\Livewire\Public;

use App\Models\ContactInquiry;
use App\Services\ContactFormService;
use Livewire\Component;

class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $storeType = '';

    public string $phone = '';

    public string $subject = '';

    public string $message = '';

    public array $customData = [];

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
            'customData' => ['array'],
        ];
    }

    public function submit(): void
    {
        $validated = $this->validate();
        $storeType = $validated['storeType'];
        unset($validated['storeType']);

        $submissionData = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'store_type' => $storeType,
            'subject' => $this->subject,
            'message' => $this->message,
        ];

        foreach ($this->customData as $key => $val) {
            $submissionData[$key] = $val;
        }

        ContactFormService::processSubmission($submissionData, request()->ip());

        $this->reset(['name', 'email', 'storeType', 'phone', 'subject', 'message', 'customData']);
        $this->submitted = true;
        
        $settings = ContactFormService::getSettings();
        $msg = $settings['success_message'] ?: 'Message sent — we\'ll get back to you shortly.';
        $this->dispatch('toast', message: $msg);
    }

    public function render()
    {
        return view('livewire.public.contact-form', [
            'fields' => ContactFormService::getFields(),
            'settings' => ContactFormService::getSettings(),
        ]);
    }
}
