<?php

namespace App\Console\Commands;

use App\Services\Repair\RepairNotificationService;
use Illuminate\Console\Command;

class SendRepairRemindersCommand extends Command
{
    protected $signature = 'repair:send-reminders {--hours=48 : Overdue hours for ready tickets}';

    protected $description = 'Send automated multi-channel pickup reminders to customers whose devices have been ready for > 48 hours';

    public function handle(RepairNotificationService $notificationService): int
    {
        $hours = (int) $this->option('hours');
        $this->info("Scanning for repaired tickets ready for more than {$hours} hours...");

        $dispatched = $notificationService->sendPendingPickupReminders($hours);

        $this->info("Successfully sent pickup reminders for {$dispatched} repair ticket(s).");

        return self::SUCCESS;
    }
}
