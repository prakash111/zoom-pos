<?php

namespace App\Livewire\Auth;

use App\Models\AdminSession;
use App\Models\PlatformAdmin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class PlatformLogin extends Component
{
    public string $email = '';

    public string $password = '';

    public string $error = '';

    public function mount(): void
    {
        if (config('app.demo_mode')) {
            $this->email = 'superadmin@gmail.com';
            $this->password = 'password123';
        }
    }

    public function fillDemo(string $role = 'superadmin'): void
    {
        if (! config('app.demo_mode')) {
            return;
        }

        if ($role === 'superadmin') {
            $this->email = 'superadmin@gmail.com';
            $this->password = 'password123';
        }
        $this->error = '';
    }

    public function login()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $admin = PlatformAdmin::query()->where('email', $this->email)->first();

        if (! $admin || ! Hash::check($this->password, $admin->password)) {
            $this->error = 'Invalid credentials.';

            return;
        }

        if ($admin->status !== 'active') {
            $this->error = 'This admin account is suspended.';

            return;
        }

        Auth::guard('platform_web')->login($admin);

        // Also mint a bearer-token admin_sessions row, so the same credentials
        // work for platform_api (sync-compat) callers without a second login.
        AdminSession::create([
            'token' => Str::random(64),
            'platform_admin_id' => $admin->id,
            'expires_at' => now()->addHours(8),
        ]);

        return redirect('/superadmin');
    }

    public function render()
    {
        return view('livewire.auth.platform-login');
    }
}
