<?php

namespace App\Http\Requests\Traits;

use App\Services\Localization\PlatformRegionalService;

trait NormalizesPhoneNumber
{
    /**
     * Common request field keys representing phone numbers.
     *
     * @var array<int, string>
     */
    protected array $phoneFieldNames = [
        'phone',
        'customer_phone',
        'patient_phone',
        'mobile',
        'contact_phone',
        'recipient_phone',
        'whatsapp_number',
        'support_phone',
        'user_phone',
    ];

    /**
     * Normalizes a phone number to standard E.164 format.
     *
     * Examples:
     * - '9876543210' with +91 -> '+919876543210'
     * - '09876543210' with +91 -> '+919876543210'
     * - '+91 98765 43210' -> '+919876543210'
     * - '00919876543210' -> '+919876543210'
     * - '919876543210' with +91 -> '+919876543210'
     * - '+1 (555) 234-5678' -> '+15552345678'
     */
    public static function normalizePhoneNumber(?string $phone, ?string $defaultDialCode = null): ?string
    {
        if ($phone === null) {
            return null;
        }

        $raw = trim($phone);
        if ($raw === '') {
            return null;
        }

        $defaultDial = $defaultDialCode ?: PlatformRegionalService::defaultDialCode();
        if (! str_starts_with($defaultDial, '+')) {
            $defaultDial = '+' . $defaultDial;
        }

        // Convert leading international access code '00' to '+'
        if (str_starts_with($raw, '00')) {
            $raw = '+' . substr($raw, 2);
        }

        // Check if explicit '+' is present
        if (str_starts_with($raw, '+')) {
            $digits = preg_replace('/\D+/', '', substr($raw, 1));
            return $digits !== '' ? '+' . $digits : null;
        }

        // Strip all non-digit characters
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        // Strip leading trunk zeros (e.g., 09876543210 -> 9876543210)
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return null;
        }

        $dialDigits = ltrim($defaultDial, '+');

        // Check if the number already includes the dial code digits without '+'
        // e.g. 919876543210 (starts with 91, and remaining length is at least 7 digits)
        if (str_starts_with($digits, $dialDigits) && strlen($digits) >= strlen($dialDigits) + 7) {
            return '+' . $digits;
        }

        return $defaultDial . $digits;
    }

    /**
     * Normalizes a collection of fields in a payload dictionary.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>|null  $fields
     * @return array<string, mixed>
     */
    public static function normalizePhoneFields(array $data, ?array $fields = null, ?string $defaultDialCode = null): array
    {
        $targetFields = $fields ?? [
            'phone',
            'customer_phone',
            'patient_phone',
            'mobile',
            'contact_phone',
            'recipient_phone',
            'whatsapp_number',
            'support_phone',
            'user_phone',
        ];

        foreach ($targetFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $normalized = static::normalizePhoneNumber($data[$field], $defaultDialCode);
                if ($normalized !== null) {
                    $data[$field] = $normalized;
                }
            }
        }

        return $data;
    }

    /**
     * Laravel FormRequest hook to sanitize phone numbers prior to validation.
     */
    protected function prepareForValidation(): void
    {
        if (method_exists(parent::class, 'prepareForValidation')) {
            parent::prepareForValidation();
        }

        $updates = [];
        $defaultDial = PlatformRegionalService::defaultDialCode();

        foreach ($this->phoneFieldNames as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $normalized = static::normalizePhoneNumber($this->input($field), $defaultDial);
                if ($normalized !== null) {
                    $updates[$field] = $normalized;
                }
            }
        }

        if (! empty($updates)) {
            $this->merge($updates);
        }
    }
}
