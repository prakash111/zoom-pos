<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\Sync\DesktopSyncClient;
use App\Services\Sync\DesktopSyncEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Self-rescheduling background sync cycle for the NativePHP desktop app.
 * There is no OS cron and NativePHP runs no scheduler daemon, so rather than
 * depend on either, this job reuses the queue worker NativePHP already
 * auto-starts (config/nativephp.php `queue_workers`) and requeues itself
 * with a delay after each run — the same "poll via queue" pattern, just
 * self-paced: shorter delay when there's known pending work or a cycle just
 * moved data, longer when idle or the app window is hidden, so it never
 * runs a heavy loop for no reason.
 */
class RunDesktopSyncCycle implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function handle(DesktopSyncEngine $engine): void
    {
        $company = Company::query()->first();

        if (! $company) {
            self::dispatch()->delay(now()->addSeconds(30));

            return;
        }

        $client = new DesktopSyncClient($company, $engine);

        if (! $client->isConfigured()) {
            self::dispatch()->delay(now()->addSeconds(30));

            return;
        }

        if ($this->windowIsHidden()) {
            self::dispatch()->delay(now()->addSeconds(45));

            return;
        }

        $result = $client->runCycle();

        $delay = match (true) {
            $result['status'] === 'offline' => 20,
            ! empty($result['pushed']) || ! empty($result['pulled']) => 10,
            $client->hasPendingWork() => 10,
            default => 25,
        };

        self::dispatch()->delay(now()->addSeconds($delay));
    }

    protected function windowIsHidden(): bool
    {
        if (! class_exists(\Native\Desktop\Facades\App::class)) {
            return false;
        }

        try {
            return \Native\Desktop\Facades\App::isHidden();
        } catch (\Throwable) {
            return false;
        }
    }
}
