<?php

namespace App\Livewire\Auth;

use App\Services\Auth\DesktopAuthBootstrapService;
use App\Services\Auth\TenantAuthService;
use App\Support\Desktop;
use Illuminate\Support\Facades\Auth;
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
        if (config('app.demo_mode')) {
            $this->identifier = 'admin@zoommarket.test';
            $this->password = 'password123';
        }
    }

    public function fillDemo(string $role = 'manager'): void
    {
        if (! config('app.demo_mode')) {
            return;
        }

        if ($role === 'manager' || $role === 'admin') {
            $this->identifier = 'admin@zoommarket.test';
            $this->password = 'password123';
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
            // A fresh desktop install has no local copy of this account yet —
            // TenantAuthService can never find it in the empty local database.
            // Verify against the server once and hydrate enough locally
            // (Plan/Company/User + a local password hash) that every login
            // after this one works fully offline. Never runs on the web app.
            if (! Desktop::isRunning() || ! $bootstrap->attemptOnlineBootstrap($this->identifier, $this->password)) {
                $this->error = Desktop::isRunning()
                    ? 'Invalid credentials, or this device has not signed in to this account before and could not reach the server to verify — check your connection and try again.'
                    : $e->getMessage();

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

        return redirect('/tenant');
    }

    public function render()
    {
        return view('livewire.auth.tenant-login');
    }
}
