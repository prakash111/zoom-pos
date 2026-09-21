<?php

namespace App\Services;

use App\Mail\ContactInquiryMailable;
use App\Models\ContactInquiry;
use App\Models\PlatformBranding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ContactFormService
{
    public const CACHE_KEY_FIELDS = 'contact_form_custom_fields';

    public static function getDefaultFields(): array
    {
        return [
            [
                'id' => 'first_name',
                'name' => 'first_name',
                'label' => 'First Name',
                'type' => 'text',
                'placeholder' => 'Enter first name',
                'required' => true,
                'width' => 'half',
                'options' => '',
                'is_system' => true,
            ],
            [
                'id' => 'last_name',
                'name' => 'last_name',
                'label' => 'Last Name',
                'type' => 'text',
                'placeholder' => 'Enter last name',
                'required' => false,
                'width' => 'half',
                'options' => '',
                'is_system' => false,
            ],
            [
                'id' => 'email',
                'name' => 'email',
                'label' => 'Work Email',
                'type' => 'email',
                'placeholder' => 'Enter work email',
                'required' => true,
                'width' => 'half',
                'options' => '',
                'is_system' => true,
            ],
            [
                'id' => 'phone',
                'name' => 'phone',
                'label' => 'Phone Number',
                'type' => 'tel',
                'placeholder' => 'Enter phone number',
                'required' => false,
                'width' => 'half',
                'options' => '',
                'is_system' => false,
            ],
            [
                'id' => 'job_title',
                'name' => 'job_title',
                'label' => 'Job Title',
                'type' => 'text',
                'placeholder' => 'Enter job title',
                'required' => false,
                'width' => 'half',
                'options' => '',
                'is_system' => false,
            ],
            [
                'id' => 'company_name',
                'name' => 'company_name',
                'label' => 'Company Name',
                'type' => 'text',
                'placeholder' => 'Enter company name',
                'required' => false,
                'width' => 'half',
                'options' => '',
                'is_system' => false,
            ],
            [
                'id' => 'message',
                'name' => 'message',
                'label' => 'Message',
                'type' => 'textarea',
                'placeholder' => 'Enter message',
                'required' => true,
                'width' => 'full',
                'options' => '',
                'is_system' => true,
            ],
        ];
    }

    public static function getFields(): array
    {
        return Cache::rememberForever(self::CACHE_KEY_FIELDS, function () {
            $raw = setting('contact_form_custom_fields');
            if (empty($raw)) {
                return self::getDefaultFields();
            }

            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            if (! is_array($decoded) || empty($decoded)) {
                return self::getDefaultFields();
            }

            return $decoded;
        });
    }

    public static function saveFields(array $fields): void
    {
        $sanitized = [];
        $allowedTypes = ['text', 'email', 'tel', 'number', 'textarea', 'select', 'checkbox'];

        foreach ($fields as $index => $field) {
            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $rawName = trim((string) ($field['name'] ?? ''));
            $name = Str::slug($rawName !== '' ? $rawName : $label, '_');
            if (empty($name)) {
                $name = 'field_' . ($index + 1);
            }

            $type = in_array($field['type'] ?? '', $allowedTypes, true) ? $field['type'] : 'text';
            $width = in_array($field['width'] ?? '', ['half', 'full'], true) ? $field['width'] : 'full';
            $required = (bool) ($field['required'] ?? false);
            $placeholder = trim((string) ($field['placeholder'] ?? ''));
            $options = trim((string) ($field['options'] ?? ''));
            $isSystem = in_array($name, ['name', 'first_name', 'email', 'message'], true);

            $sanitized[] = [
                'id' => $field['id'] ?? (string) Str::uuid(),
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'placeholder' => $placeholder,
                'required' => $isSystem ? true : $required,
                'width' => $width,
                'options' => $options,
                'is_system' => $isSystem,
            ];
        }

        if (empty($sanitized)) {
            $sanitized = self::getDefaultFields();
        }

        set_setting('contact_form_custom_fields', json_encode($sanitized));
        Cache::forget(self::CACHE_KEY_FIELDS);
        Cache::forget('app_landing_page_theme');
        if (Cache::has('landing_page_cache_version')) {
            Cache::increment('landing_page_cache_version');
        } else {
            Cache::forever('landing_page_cache_version', 2);
        }
    }

    public static function getSettings(): array
    {
        $branding = PlatformBranding::current();

        return [
            'page_title' => (string) setting('contact_page_title', 'Get in Touch'),
            'page_subtitle' => (string) setting('contact_page_subtitle', 'Fill out the form below and our team will get back to you within 1-2 business days.'),
            'submit_button_text' => (string) setting('contact_form_submit_button_text', 'Submit'),
            'success_message' => (string) setting('contact_form_success_message', "Thanks for reaching out — we'll get back to you shortly."),
            'recipient_email' => (string) setting('contact_form_recipient_email', $branding->support_email ?: ''),
            'enabled' => (bool) setting('contact_form_enabled', true),
        ];
    }

    public static function saveSettings(array $settings): void
    {
        if (isset($settings['page_title'])) {
            set_setting('contact_page_title', trim((string) $settings['page_title']));
        }
        if (isset($settings['page_subtitle'])) {
            set_setting('contact_page_subtitle', trim((string) $settings['page_subtitle']));
        }
        if (isset($settings['submit_button_text'])) {
            set_setting('contact_form_submit_button_text', trim((string) $settings['submit_button_text']));
        }
        if (isset($settings['success_message'])) {
            set_setting('contact_form_success_message', trim((string) $settings['success_message']));
        }
        if (isset($settings['recipient_email'])) {
            set_setting('contact_form_recipient_email', trim((string) $settings['recipient_email']));
        }
        if (isset($settings['enabled'])) {
            set_setting('contact_form_enabled', (bool) $settings['enabled']);
        }

        Cache::forget('app_landing_page_theme');
        if (Cache::has('landing_page_cache_version')) {
            Cache::increment('landing_page_cache_version');
        } else {
            Cache::forever('landing_page_cache_version', 2);
        }
    }

    public static function buildValidationRules(): array
    {
        $fields = self::getFields();
        $rules = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $req = ! empty($field['required']) ? 'required' : 'nullable';
            $type = $field['type'];

            $fieldRules = [$req];

            switch ($type) {
                case 'email':
                    $fieldRules[] = 'email';
                    $fieldRules[] = 'max:255';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'textarea':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:5000';
                    break;
                case 'checkbox':
                    $fieldRules = ['nullable'];
                    break;
                case 'tel':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:50';
                    break;
                case 'select':
                case 'text':
                default:
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
                    break;
            }

            $rules[$name] = $fieldRules;
        }

        // Always ensure base required fields have fallback minimum validations
        if (! isset($rules['name'])) {
            $rules['name'] = ['required_without:first_name', 'string', 'max:255'];
        }
        $rules['first_name'] = ['nullable', 'string', 'max:120'];
        $rules['last_name'] = ['nullable', 'string', 'max:120'];
        $rules['job_title'] = ['nullable', 'string', 'max:150'];
        $rules['company_name'] = ['nullable', 'string', 'max:150'];
        $rules['phone_country'] = ['nullable', 'string', 'max:10'];

        if (! isset($rules['phone'])) {
            $rules['phone'] = ['nullable', 'string', 'max:50'];
        }
        if (! isset($rules['subject'])) {
            $rules['subject'] = ['nullable', 'string', 'max:255'];
        }
        if (! isset($rules['store_type'])) {
            $rules['store_type'] = ['nullable', 'string', 'max:100'];
        }

        if (! isset($rules['email'])) {
            $rules['email'] = ['required', 'email', 'max:255'];
        }
        if (! isset($rules['message'])) {
            $rules['message'] = ['required', 'string', 'max:5000'];
        }

        return $rules;
    }

    public static function processSubmission(array $validatedData, ?string $ipAddress = null): ContactInquiry
    {
        $standardColumns = ['name', 'email', 'phone', 'store_type', 'subject', 'message'];
        $baseData = [];
        $customFields = [];

        $fields = self::getFields();
        $fieldsMap = collect($fields)->keyBy('name');

        foreach ($validatedData as $key => $value) {
            if (in_array($key, $standardColumns, true)) {
                $baseData[$key] = $value;
            } else {
                $label = $fieldsMap->get($key)['label'] ?? Str::headline($key);
                $type = $fieldsMap->get($key)['type'] ?? 'text';
                
                $displayValue = $value;
                if ($type === 'checkbox') {
                    $displayValue = $value ? 'Yes' : 'No';
                }

                $customFields[$key] = [
                    'label' => $label,
                    'value' => $displayValue,
                    'type'  => $type,
                ];
            }
        }

        // Standardize name if first_name / last_name provided
        if (empty($baseData['name']) && (! empty($validatedData['first_name']) || ! empty($validatedData['last_name']))) {
            $baseData['name'] = trim(($validatedData['first_name'] ?? '') . ' ' . ($validatedData['last_name'] ?? ''));
        }
        if (empty($baseData['name']) && ! empty($validatedData['name'])) {
            $baseData['name'] = $validatedData['name'];
        }
        if (empty($baseData['name'])) {
            $baseData['name'] = 'Visitor';
        }

        // Format and normalize phone with selected prefix or default platform dial code
        if (! empty($baseData['phone'])) {
            $selectedPrefix = ! empty($validatedData['phone_country']) ? trim((string) $validatedData['phone_country']) : null;
            $baseData['phone'] = \App\Http\Requests\Traits\NormalizesPhoneNumber::normalizePhoneNumber($baseData['phone'], $selectedPrefix);
        }

        $baseData['custom_fields'] = ! empty($customFields) ? $customFields : null;
        $baseData['ip_address'] = $ipAddress;
        $baseData['status'] = ContactInquiry::STATUS_NEW;

        $inquiry = ContactInquiry::create($baseData);

        // Send email notification
        $settings = self::getSettings();
        $recipient = $settings['recipient_email'] 
            ?: PlatformBranding::current()->support_email 
            ?: config('mail.from.address');

        if ($recipient) {
            try {
                Mail::to($recipient)->send(new ContactInquiryMailable($inquiry));
            } catch (\Throwable $e) {
                Log::warning('Failed to send contact inquiry notification email.', ['error' => $e->getMessage()]);
            }
        }

        return $inquiry;
    }
}
