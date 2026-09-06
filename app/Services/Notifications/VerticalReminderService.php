<?php

namespace App\Services\Notifications;

use App\Models\NotificationReminder;
use App\Models\PharmacyPrescription;
use App\Models\Sale;
use App\Models\SalonAppointment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class VerticalReminderService
{
    public function __construct(private readonly CustomChannelDispatcherService $dispatcher) {}

    public function schedulePharmacyRefill(
        PharmacyPrescription $prescription,
        Sale $sale,
        ?int $daysSupply = null,
    ): ?NotificationReminder {
        $daysSupply ??= $prescription->dosage_duration_days;
        if (! $daysSupply || $daysSupply < 1 || ! $prescription->patient_phone) {
            return null;
        }

        $leadDays = min(5, max(1, $daysSupply - 1));
        $scheduledAt = ($prescription->dispensed_at ?? now())->copy()->addDays(max(1, $daysSupply - $leadDays));
        $message = "Hello {$prescription->patient_name}, your {$daysSupply}-day prescription supply is due for a refill soon. Please contact your pharmacist before it runs out.";

        $reminder = $this->upsert([
            'company_id' => $prescription->company_id,
            'module_type' => 'pharmacy',
            'event_type' => 'pharmacy.refill_due',
            'reference_type' => PharmacyPrescription::class,
            'reference_id' => $prescription->id,
        ], [
            'customer_id' => $prescription->customer_id,
            'customer_name' => $prescription->patient_name,
            'recipient' => $prescription->patient_phone,
            'channels' => ['sms', 'whatsapp'],
            'message' => $message,
            'payload' => [
                'sale_id' => $sale->id,
                'prescription_number' => $prescription->prescription_number,
                'days_supply' => $daysSupply,
            ],
            'scheduled_at' => $scheduledAt,
        ]);

        $prescription->update([
            'dosage_duration_days' => $daysSupply,
            'refill_reminder_at' => $scheduledAt,
        ]);

        return $reminder;
    }

    /** @param list<array<string, mixed>> $items */
    public function scheduleSalonFollowUp(
        Sale $sale,
        array $items,
        ?SalonAppointment $appointment = null,
        ?string $recipient = null,
        ?int $overrideDays = null,
        ?string $customMessage = null,
    ): ?NotificationReminder {
        $recipient = $recipient ?: $appointment?->customer_phone ?: $sale->customer?->phone;
        if (! $recipient) {
            return null;
        }

        $service = collect($items)->first(fn (array $item) => ! empty($item['duration_minutes']));
        $days = $overrideDays ?: (int) ($service['follow_up_days'] ?? 0);
        if ($days < 1) {
            return null;
        }

        $serviceName = (string) ($service['name'] ?? 'treatment');
        $customerName = $sale->customer_name ?: ($appointment?->customer_name ?: 'Valued Client');
        $message = $customMessage ?: "Hello {$customerName}, it may be time to book your next {$serviceName} appointment. We would love to see you again.";

        return $this->upsert([
            'company_id' => $sale->company_id,
            'module_type' => 'salon',
            'event_type' => 'salon.follow_up_due',
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
        ], [
            'customer_id' => $sale->customer_id,
            'customer_name' => $customerName,
            'recipient' => $recipient,
            'channels' => ['sms', 'whatsapp'],
            'message' => $message,
            'payload' => [
                'sale_id' => $sale->id,
                'appointment_id' => $appointment?->id,
                'service_name' => $serviceName,
                'follow_up_days' => $days,
            ],
            'scheduled_at' => now()->addDays($days),
        ]);
    }

    public function dispatchDue(?CarbonInterface $at = null): int
    {
        $at ??= now();
        $sent = 0;

        NotificationReminder::withoutGlobalScope('company')
            ->whereIn('status', [NotificationReminder::STATUS_SCHEDULED, NotificationReminder::STATUS_FAILED])
            ->where('attempts', '<', 8)
            ->where('scheduled_at', '<=', $at)
            ->orderBy('scheduled_at')
            ->chunkById(100, function ($rows) use (&$sent) {
                foreach ($rows as $reminder) {
                    try {
                        $payload = array_merge((array) $reminder->payload, [
                            'phone' => $reminder->recipient,
                            'customer_name' => $reminder->customer_name,
                            'message' => $reminder->message,
                            'sms_text' => $reminder->message,
                        ]);
                        $this->dispatcher->dispatchEvent($reminder->company_id, $reminder->event_type, $payload);
                        $reminder->update([
                            'status' => NotificationReminder::STATUS_SENT,
                            'sent_at' => now(),
                            'attempts' => $reminder->attempts + 1,
                            'last_error' => null,
                        ]);
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::warning('Vertical reminder dispatch failed: '.$e->getMessage(), ['reminder_id' => $reminder->id]);
                        $reminder->update([
                            'status' => NotificationReminder::STATUS_FAILED,
                            'attempts' => $reminder->attempts + 1,
                            'last_error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $sent;
    }

    /** @param array<string, mixed> $identity @param array<string, mixed> $values */
    private function upsert(array $identity, array $values): NotificationReminder
    {
        return NotificationReminder::withoutGlobalScope('company')->updateOrCreate(
            $identity,
            array_merge($values, ['status' => NotificationReminder::STATUS_SCHEDULED, 'sent_at' => null, 'attempts' => 0, 'last_error' => null])
        );
    }
}
