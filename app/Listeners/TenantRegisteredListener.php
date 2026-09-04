<?php

namespace App\Listeners;

use App\Events\TenantRegistered;
use App\Jobs\SeedTenantSampleDataJob;

class TenantRegisteredListener
{
    public function handle(TenantRegistered $event): void
    {
        SeedTenantSampleDataJob::dispatch(
            (string) $event->company->id,
            $event->posMode,
            $event->user->id
        );
    }
}
