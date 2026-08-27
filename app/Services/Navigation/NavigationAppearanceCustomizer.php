<?php

namespace App\Services\Navigation;

class NavigationAppearanceCustomizer
{
    /**
     * Get available dock items strictly scoped to Super Admin platform modules.
     *
     * @return array<int, array{key: string, label: string, icon: string}>
     */
    public function getAvailableAdminDockItems(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard Overview', 'icon' => '📊'],
            ['key' => 'tenants', 'label' => 'Tenant Stores', 'icon' => '🏢'],
            ['key' => 'plans', 'label' => 'SaaS Plans & Pricing', 'icon' => '👑'],
            ['key' => 'taxes', 'label' => 'Global Tax Engine', 'icon' => '⚖️'],
            ['key' => 'menus', 'label' => 'Menu Builder', 'icon' => '🧭'],
            ['key' => 'pages', 'label' => 'CMS Custom Pages', 'icon' => '📄'],
            ['key' => 'settings', 'label' => 'Platform Settings', 'icon' => '⚙️'],
            ['key' => 'smtp', 'label' => 'SMTP & Mail Config', 'icon' => '✉️'],
        ];
    }
}
