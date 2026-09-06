<?php

namespace App\Console\Commands;

use App\Services\Notifications\VerticalReminderService;
use Illuminate\Console\Command;

class DispatchVerticalReminders extends Command
{
    protected $signature = 'notifications:dispatch-vertical';

    protected $description = 'Dispatch due pharmacy refill and salon retention reminders';

    public function handle(VerticalReminderService $reminders): int
    {
        $count = $reminders->dispatchDue();
        $this->info("Dispatched {$count} vertical reminder(s).");

        return self::SUCCESS;
    }
}
