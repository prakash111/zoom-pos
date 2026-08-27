<?php

namespace App\Livewire\Tenant;

use App\Models\Company;
use App\Models\Configuration;
use App\Support\Desktop;
use Livewire\Component;

/**
 * Offline/Online/Syncing/Synced indicator for the desktop app. Reads
 * RunDesktopSyncCycle's last-known status straight out of the local
 * `configurations` table — a purely local read, so polling this component
 * never itself makes a network call. Renders nothing on the regular web app
 * (no NativePHP App facade available there).
 */
class DesktopSyncStatus extends Component
{
    public bool $isDesktop = false;

    public string $status = 'offline';

    public ?string $lastSyncedAt = null;

    public function mount(): void
    {
        $this->isDesktop = $this->detectDesktop();
        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        if (! $this->isDesktop) {
            return;
        }

        $company = Company::query()->first();
        if (! $company) {
            return;
        }

        $this->status = Configuration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('key', 'desktop_sync.status')
            ->value('value') ?? 'offline';

        $this->lastSyncedAt = Configuration::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('key', 'desktop_sync.last_synced_at')
            ->value('value');
    }

    protected function detectDesktop(): bool
    {
        return Desktop::isRunning();
    }

    public function render()
    {
        return view('livewire.tenant.desktop-sync-status');
    }
}
