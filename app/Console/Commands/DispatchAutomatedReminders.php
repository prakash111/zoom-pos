<?php

namespace App\Console\Commands;

use App\Jobs\DispatchAutomatedCustomerReminder;
use App\Models\AutomatedReminderDispatch;
use App\Models\Company;
use App\Models\Sale;
use App\Services\Notifications\AutomatedReminderSettingsService;
use App\Services\Notifications\TenantNotificationDispatcherService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchAutomatedReminders extends Command
{
    protected $signature = 'app:dispatch-automated-reminders
        {--tenant= : Process only this tenant/company ID}
        {--scheduled : Enforce the configured tenant-local schedule window}';

    protected $description = 'Queue overdue invoice and pending quotation reminders through each tenant\'s enabled gateways';

    public function handle(
        AutomatedReminderSettingsService $settingsService,
        TenantNotificationDispatcherService $dispatcher,
    ): int {
        $tenantId = trim((string) $this->option('tenant'));
        $scheduledRun = (bool) $this->option('scheduled');
        $stats = ['tenants' => 0, 'documents' => 0, 'queued' => 0, 'duplicates' => 0, 'skipped' => 0];

        $companies = Company::withoutGlobalScopes()
            ->when($tenantId !== '', fn ($query) => $query->whereKey($tenantId))
            ->orderBy('id')
            ->cursor();

        foreach ($companies as $company) {
            $settings = $settingsService->get($company);
            if (! $settings['auto_reminders_enabled']) {
                continue;
            }

            $localNow = now($company->resolveTimezone());
            if ($scheduledRun && ! $this->isScheduleWindowDue($settings, $localNow)) {
                continue;
            }

            $channels = $this->selectedActiveChannels($company, $settings, $dispatcher);
            if ($channels === []) {
                $stats['skipped']++;
                Log::warning('Automated reminders skipped: no selected gateway is active and configured.', [
                    'company_id' => $company->id,
                    'preferred_channel' => $settings['reminder_preferred_channel'],
                ]);

                continue;
            }

            $stats['tenants']++;
            $scope = $settings['reminder_target_documents'];

            if (in_array($scope, ['both', 'invoices_only'], true)) {
                $this->invoiceQuery($company, $localNow->toDateString())
                    ->chunkById(100, function ($documents) use ($company, $settings, $channels, $localNow, &$stats) {
                        foreach ($documents as $document) {
                            $this->queueDocument($company, $document, 'invoice', $settings, $channels, $localNow, $stats);
                        }
                    });
            }

            if (in_array($scope, ['both', 'quotations_only'], true)) {
                $this->quotationQuery($company, $localNow)
                    ->chunkById(100, function ($documents) use ($company, $settings, $channels, $localNow, &$stats) {
                        foreach ($documents as $document) {
                            $this->queueDocument($company, $document, 'quotation', $settings, $channels, $localNow, $stats);
                        }
                    });
            }
        }

        if ($tenantId !== '' && $stats['tenants'] === 0) {
            $exists = Company::withoutGlobalScopes()->whereKey($tenantId)->exists();
            if (! $exists) {
                $this->warn("Tenant/company [{$tenantId}] was not found.");
            }
        }

        $this->info(sprintf(
            'Automated reminder processing completed: %d tenant(s), %d document(s), %d job(s) queued, %d duplicate(s), %d skipped.',
            $stats['tenants'],
            $stats['documents'],
            $stats['queued'],
            $stats['duplicates'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }

    private function invoiceQuery(Company $company, string $localDate)
    {
        return Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($query) {
                $query->where('operation_type', 'sale')->orWhereNull('operation_type');
            })
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->where('due_amount', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $localDate)
            ->with('customer');
    }

    private function quotationQuery(Company $company, $localNow)
    {
        return Sale::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('operation_type', 'quotation')
            ->whereIn('status', ['draft', 'sent', 'pending'])
            ->where(function ($query) use ($localNow) {
                $query->where(function ($expiring) use ($localNow) {
                    $expiring->whereNotNull('due_date')
                        ->whereDate('due_date', '<=', $localNow->copy()->addDays(3)->toDateString());
                })->orWhere(function ($aged) use ($localNow) {
                    $aged->whereNull('due_date')
                        ->where('created_at', '<=', $localNow->copy()->subDays(2)->utc());
                });
            })
            ->with('customer');
    }

    private function queueDocument(
        Company $company,
        Sale $document,
        string $documentType,
        array $settings,
        array $channels,
        $localNow,
        array &$stats,
    ): void {
        $stats['documents']++;
        $frequency = $settings['reminder_schedule_frequency'];

        if ($frequency === 'every_3_days' && ! $this->threeDayCadenceIsDue($company, $document, $documentType)) {
            $stats['skipped']++;

            return;
        }

        [$cycleKey, $scheduledFor] = $this->cycle($frequency, $localNow, (bool) $this->option('scheduled'));
        $customer = $document->customer;

        foreach ($channels as $channel) {
            $recipient = $channel === 'email' ? $customer?->email : $customer?->phone;
            $recipient = trim((string) $recipient);
            if ($recipient === '') {
                $stats['skipped']++;

                continue;
            }

            $dispatchKey = hash('sha256', implode('|', [
                $company->id,
                $document->id,
                $documentType,
                $channel,
                $cycleKey,
            ]));

            $dispatch = AutomatedReminderDispatch::withoutGlobalScope('company')->firstOrCreate(
                ['dispatch_key' => $dispatchKey],
                [
                    'company_id' => $company->id,
                    'sale_id' => $document->id,
                    'document_type' => $documentType,
                    'channel' => $channel,
                    'recipient' => $recipient,
                    'cycle_key' => $cycleKey,
                    'scheduled_for' => $scheduledFor,
                    'status' => AutomatedReminderDispatch::STATUS_QUEUED,
                ]
            );

            if (! $dispatch->wasRecentlyCreated) {
                $stats['duplicates']++;

                continue;
            }

            try {
                DispatchAutomatedCustomerReminder::dispatch($dispatch->id);
                $stats['queued']++;
            } catch (Throwable $exception) {
                $stats['skipped']++;
                Log::error('Unable to enqueue automated customer reminder.', [
                    'dispatch_id' => $dispatch->id,
                    'company_id' => $company->id,
                    'sale_id' => $document->id,
                    'channel' => $channel,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function selectedActiveChannels(
        Company $company,
        array $settings,
        TenantNotificationDispatcherService $dispatcher,
    ): array {
        $enabled = $dispatcher->getEnabledChannels($company);
        $available = array_values(array_filter(
            ['sms', 'whatsapp', 'email'],
            fn (string $channel) => ! empty($enabled[$channel])
        ));

        $preferred = $settings['reminder_preferred_channel'];
        if ($preferred === 'all_active') {
            return $available;
        }

        return in_array($preferred, $available, true) ? [$preferred] : [];
    }

    private function isScheduleWindowDue(array $settings, $localNow): bool
    {
        if ($localNow->minute >= 15) {
            return false;
        }

        return match ($settings['reminder_schedule_frequency']) {
            'daily_evening' => $localNow->hour === 18,
            'weekly_monday' => $localNow->hour === 10 && $localNow->isMonday(),
            'every_3_days', 'daily_morning' => $localNow->hour === 10,
            default => false,
        };
    }

    private function threeDayCadenceIsDue(Company $company, Sale $document, string $documentType): bool
    {
        $lastScheduled = AutomatedReminderDispatch::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('sale_id', $document->id)
            ->where('document_type', $documentType)
            ->whereIn('status', [
                AutomatedReminderDispatch::STATUS_QUEUED,
                AutomatedReminderDispatch::STATUS_PROCESSING,
                AutomatedReminderDispatch::STATUS_SENT,
                AutomatedReminderDispatch::STATUS_FAILED,
            ])
            ->max('scheduled_for');

        return $lastScheduled === null || Carbon::parse($lastScheduled)->lte(now()->subDays(3));
    }

    /** @return array{string, CarbonInterface} */
    private function cycle(string $frequency, $localNow, bool $scheduledRun): array
    {
        $scheduledLocal = $localNow->copy()->startOfMinute();
        if ($scheduledRun) {
            $scheduledLocal->setTime($frequency === 'daily_evening' ? 18 : 10, 0);
        }

        $cycleKey = match ($frequency) {
            'weekly_monday' => $frequency.':'.$localNow->format('o-W'),
            default => $frequency.':'.$localNow->toDateString(),
        };

        return [$cycleKey, $scheduledLocal->utc()];
    }
}
