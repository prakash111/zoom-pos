<?php

namespace App\Services\Notifications;

use App\Models\Company;

class AutomatedReminderSettingsService
{
    public const CHANNELS = ['all_active', 'sms', 'whatsapp', 'email'];

    public const FREQUENCIES = ['daily_morning', 'daily_evening', 'every_3_days', 'weekly_monday'];

    public const DOCUMENT_SCOPES = ['both', 'invoices_only', 'quotations_only'];

    public static function defaults(): array
    {
        return [
            'auto_reminders_enabled' => false,
            'reminder_preferred_channel' => 'all_active',
            'reminder_schedule_frequency' => 'daily_morning',
            'reminder_target_documents' => 'both',
        ];
    }

    public function get(Company|string $company): array
    {
        $companyId = $company instanceof Company ? $company->id : $company;
        $stored = tenant_setting($companyId, 'auto_reminders', []);

        if (is_string($stored)) {
            $stored = json_decode($stored, true) ?: [];
        }

        if (! is_array($stored)) {
            $stored = [];
        }

        $settings = array_merge(self::defaults(), array_intersect_key($stored, self::defaults()));
        $settings['auto_reminders_enabled'] = filter_var(
            $settings['auto_reminders_enabled'],
            FILTER_VALIDATE_BOOLEAN
        );

        if (! in_array($settings['reminder_preferred_channel'], self::CHANNELS, true)) {
            $settings['reminder_preferred_channel'] = 'all_active';
        }
        if (! in_array($settings['reminder_schedule_frequency'], self::FREQUENCIES, true)) {
            $settings['reminder_schedule_frequency'] = 'daily_morning';
        }
        if (! in_array($settings['reminder_target_documents'], self::DOCUMENT_SCOPES, true)) {
            $settings['reminder_target_documents'] = 'both';
        }

        return $settings;
    }

    public function save(Company|string $company, array $settings): array
    {
        $companyId = $company instanceof Company ? $company->id : $company;
        $normalized = [
            'auto_reminders_enabled' => filter_var(
                $settings['auto_reminders_enabled'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            ),
            'reminder_preferred_channel' => (string) ($settings['reminder_preferred_channel'] ?? 'all_active'),
            'reminder_schedule_frequency' => (string) ($settings['reminder_schedule_frequency'] ?? 'daily_morning'),
            'reminder_target_documents' => (string) ($settings['reminder_target_documents'] ?? 'both'),
        ];

        tenant_set_setting($companyId, 'auto_reminders', $normalized);

        return $normalized;
    }
}
