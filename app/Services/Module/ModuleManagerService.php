<?php

namespace App\Services\Module;

use App\Models\PlatformSystem;
use App\Models\SduiModule;
use App\Services\Modular\ModuleRegistry;
use Illuminate\Support\Facades\Schema;

class ModuleManagerService
{
    /**
     * Get all active and enabled business types/operating modes for onboarding.
     *
     * Returns a centralized collection of business verticals, dynamically resolved
     * from installed packages, SDUI database entries, and license registrations.
     *
     * @return array<int, array{id: string, name: string, icon: string, description: string, enabled: bool}>
     */
    public function getAvailableBusinessTypes(): array
    {
        $pharmacyInstalled = ModuleRegistry::isModuleInstalledAndActive('pharmacy')
            || (Schema::hasTable('sdui_modules') && SduiModule::query()->where('slug', 'pharmacy')->where('is_active', true)->exists());

        $repairInstalled = ModuleRegistry::isModuleInstalledAndActive('repairtechnician')
            || (Schema::hasTable('sdui_modules') && SduiModule::query()->whereIn('slug', ['repairtechnician', 'repair_technician'])->where('is_active', true)->exists());

        $salonInstalled = ModuleRegistry::isModuleInstalledAndActive('salon')
            || (Schema::hasTable('sdui_modules') && SduiModule::query()->whereIn('slug', ['salon', 'service_booking'])->where('is_active', true)->exists());

        // Base verticals definitions
        $verticals = [
            'retail' => [
                'id'          => 'retail',
                'name'        => 'Retail',
                'icon'        => 'store',
                'description' => 'Core Retail POS vertical: barcode scanning, cart & billing, quotations, general retail.',
                'enabled'     => true,
            ],
            'pharmacy' => [
                'id'          => 'pharmacy',
                'name'        => 'Pharmacy & Healthcare',
                'icon'        => 'medication',
                'description' => 'Batch numbers, expiry tracking, drug formulation, doctor prescription billing.',
                'enabled'     => $pharmacyInstalled,
            ],
            'restaurant' => [
                'id'          => 'restaurant',
                'name'        => 'Cafe & Restaurant',
                'icon'        => 'restaurant',
                'description' => 'Core Food & Restaurant vertical: dining table management, KOT printing, kitchen KDS display.',
                'enabled'     => true,
            ],
            'repair' => [
                'id'          => 'repair',
                'name'        => 'Repair Technician',
                'icon'        => 'build',
                'description' => 'Tickets, parts billing, diagnostics, workbench status tracking.',
                'enabled'     => $repairInstalled,
            ],
            'salon' => [
                'id'          => 'salon',
                'name'        => 'Salon & Bookings',
                'icon'        => 'content_cut',
                'description' => 'Appointments, stylist bookings, treatment service billing.',
                'enabled'     => $salonInstalled,
            ],
        ];

        // Dynamically discover any newly installed or third-party core verticals
        try {
            $operating = ModuleRegistry::operatingModules();
            foreach ($operating as $slug => $module) {
                $canonical = $this->canonicalKeyForRegistry($slug);
                if (!isset($verticals[$canonical]) && !isset($verticals[$slug])) {
                    $verticals[$canonical] = [
                        'id'          => $canonical,
                        'name'        => $module['title'] ?? ucwords(str_replace(['_', '-'], ' ', $canonical)),
                        'icon'        => $module['icon'] ?? 'category',
                        'description' => $module['description'] ?? 'Business operating mode for ' . ($module['title'] ?? $canonical) . '.',
                        'enabled'     => true,
                    ];
                }
            }
        } catch (\Throwable) {
            // Fallback gracefully to base verticals array
        }

        // Return only enabled and active verticals
        return array_values(array_filter($verticals, fn($item) => (bool) ($item['enabled'] ?? false)));
    }

    /**
     * Map any alias or legacy operating mode ID to its canonical registry key.
     */
    public function canonicalKeyForRegistry(string $key): string
    {
        $key = strtolower(trim($key));

        return match ($key) {
            'general', 'general_retail', 'retail' => 'retail',
            'food_restaurant', 'restaurant' => 'restaurant',
            'pharmacy', 'pharmacy_pos', 'chemist' => 'pharmacy',
            'repair', 'repairs', 'technician', 'repairtechnician', 'repair_technician' => 'repair',
            'salon', 'spa', 'beauty', 'wellness', 'service_booking' => 'salon',
            default => $key,
        };
    }

    /**
     * Map any alias or onboarding key to the internal system pos_mode.
     */
    public function toSystemPosMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return match ($mode) {
            'general', 'general_retail', 'retail' => 'general',
            'food_restaurant', 'restaurant' => 'restaurant',
            'pharmacy', 'pharmacy_pos', 'chemist' => 'pharmacy',
            'repair', 'repairs', 'technician', 'repairtechnician', 'repair_technician' => 'repair_technician',
            'salon', 'spa', 'beauty', 'wellness', 'service_booking' => 'service_booking',
            default => $mode,
        };
    }

    /**
     * Return all valid ID keys and canonical aliases accepted during registration.
     *
     * @return array<int, string>
     */
    public function getAllowedKeys(): array
    {
        $types = $this->getAvailableBusinessTypes();
        $ids = array_column($types, 'id');

        $aliases = [
            'general', 'retail', 'general_retail',
            'restaurant', 'food_restaurant',
            'pharmacy', 'pharmacy_pos', 'chemist',
            'repair', 'repair_technician', 'repairtechnician',
            'salon', 'service_booking',
        ];

        return array_values(array_unique(array_merge($ids, $aliases)));
    }

    /**
     * Find a specific business type by ID or alias.
     */
    public function getBusinessType(string $id): ?array
    {
        $types = $this->getAvailableBusinessTypes();
        $clean = strtolower(trim($id));
        $canonical = $this->canonicalKeyForRegistry($clean);

        foreach ($types as $type) {
            if ($type['id'] === $clean || $this->canonicalKeyForRegistry($type['id']) === $canonical) {
                return $type;
            }
        }

        return null;
    }
}
