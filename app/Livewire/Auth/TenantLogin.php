<?php

namespace App\Livewire\Auth;

use App\Exceptions\DesktopLocalProvisioningException;
use App\Models\Company;
use App\Services\Auth\DesktopAuthBootstrapService;
use App\Services\Auth\TenantAuthService;
use App\Services\Sync\DesktopSyncClient;
use App\Services\Sync\DesktopSyncEngine;
use App\Support\Desktop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class TenantLogin extends Component
{
    public string $identifier = '';

    public string $password = '';

    public string $error = '';

    public function mount(): void
    {
        if (config('app.demo_mode') && ! Desktop::isRunning()) {
            $this->identifier = 'demo@zoomnearby.com';
            $this->password = 'demo1234';
        }
    }

    public function fillDemo(string $role = 'manager'): void
    {
        if (! config('app.demo_mode') || Desktop::isRunning()) {
            return;
        }

        if ($role === 'manager' || $role === 'admin') {
            $this->identifier = 'demo@zoomnearby.com';
            $this->password = 'demo1234';
        } elseif ($role === 'cashier') {
            $this->identifier = 'cashier@zoommarket.test';
            $this->password = 'password123';
        }
        $this->error = '';
    }

    public function login(TenantAuthService $auth, DesktopAuthBootstrapService $bootstrap)
    {
        $this->validate([
            'identifier' => ['required'],
            'password' => ['required'],
        ]);

        try {
            $result = $auth->login($this->identifier, $this->password);
        } catch (\RuntimeException $e) {
            if (! Desktop::isRunning()) {
                $this->error = $e->getMessage();

                return;
            }

            // A fresh desktop install has no local copy of this account yet —
            // TenantAuthService can never find it in the empty local database.
            // Verify against the server once and hydrate enough locally
            // (Plan/Company/User + a local password hash) that every login
            // after this one works fully offline. Never runs on the web app.
            try {
                $bootstrapped = $bootstrap->attemptOnlineBootstrap($this->identifier, $this->password);
            } catch (DesktopLocalProvisioningException $provisioningException) {
                // The server already confirmed these credentials are correct —
                // never tell the user their password is wrong here, that sends
                // them retyping a password that was never the problem.
                $this->error = $provisioningException->getMessage().' Please restart the app and try again.';

                return;
            }

            if (! $bootstrapped) {
                $this->error = 'Invalid credentials, or this device has not signed in to this account before and could not reach the server to verify — check your connection and try again.';

                return;
            }

            try {
                $result = $auth->login($this->identifier, $this->password);
            } catch (\RuntimeException $e2) {
                $this->error = $e2->getMessage();

                return;
            }
        }

        Auth::guard('web')->login($result['user']);
        app()->instance('tenant.company_id', $result['user']->company_id);

        // A returning login never touches the network otherwise (that's the
        // whole point of the local password hash) — but that also means a
        // device closed for a while would only pick up cloud changes once
        // the self-rescheduling RunDesktopSyncCycle background job happens to
        // run, up to ~30s later. Refresh right now instead, so whatever
        // changed on the web/another device while this one was shut is
        // already local by the time the dashboard renders. Best-effort: an
        // offline or slow server here must never block a successful login.
        if (Desktop::isRunning()) {
            // RunDesktopSyncCycle runs in a separate queue-worker process
            // with no session of its own — this is how it learns which
            // account is actually signed in on this device, instead of
            // guessing via Company::first() and potentially grinding away at
            // a stale/different tenant from a previous login on the same
            // hardware.
            Desktop::rememberActiveCompany($result['user']->company_id);
            $this->syncNowBestEffort($result['user']->company_id);
        }

        return redirect('/tenant');
    }

    protected function syncNowBestEffort(string|int $companyId): void
    {
        $company = Company::withoutGlobalScopes()->find($companyId);

        if (! $company) {
            return;
        }

        try {
            (new DesktopSyncClient($company, app(DesktopSyncEngine::class)))->runCycle();
        } catch (\Throwable $e) {
            Log::warning('Post-login catalog refresh failed — the background sync cycle will retry.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.auth.tenant-login');
    }
}
