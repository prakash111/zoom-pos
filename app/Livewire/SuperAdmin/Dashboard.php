<?php

namespace App\Livewire\SuperAdmin;

use App\Models\ActivationCode;
use App\Models\Company;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'Dashboard'])]
class Dashboard extends Component
{
    public function render()
    {
        $companies = Company::query();

        return view('livewire.superadmin.dashboard', [
            'totalTenants' => (clone $companies)->count(),
            'activeTenants' => (clone $companies)->where('status', 'active')->count(),
            'suspendedTenants' => (clone $companies)->where('status', 'suspended')->count(),
            'expiringSoon' => (clone $companies)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays(30)])
                ->count(),
            'totalUsers' => User::query()->count(),
            'activationCodesAvailable' => ActivationCode::query()
                ->where('revoked', false)
                ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('current_uses', '<', 'max_uses'))
                ->count(),
            'recentTenants' => (clone $companies)->orderByDesc('created_at')->limit(5)->get(),
        ]);
    }
}
