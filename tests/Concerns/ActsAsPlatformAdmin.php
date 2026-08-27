<?php

namespace Tests\Concerns;

use App\Models\PlatformAdmin;
use Illuminate\Support\Facades\Hash;

trait ActsAsPlatformAdmin
{
    protected function actingAsSuperAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'platform_web');

        return $admin;
    }

    protected function actingAsSupportAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::create([
            'name' => 'Support Admin',
            'email' => 'support@example.com',
            'password' => Hash::make('password123'),
            'role' => 'suporte_admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'platform_web');

        return $admin;
    }
}
