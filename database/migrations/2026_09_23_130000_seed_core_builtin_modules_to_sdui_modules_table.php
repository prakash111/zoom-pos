<?php

use App\Models\SduiModule;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        SduiModule::firstOrCreate(
            ['slug' => 'retail'],
            [
                'name' => 'Retail',
                'type' => SduiModule::TYPE_CORE,
                'description' => 'Core Retail POS vertical: barcode scanning, cart & billing, quotations, general retail.',
                'icon' => 'storefront',
                'layout_type' => 'standard_grid',
                'is_active' => true,
                'source_type' => 'builtin',
                'version' => config('app.version', '1.0.0'),
                'author' => 'ZoomNearby',
                'sort_order' => 1,
                'requires_license' => false,
                'license_status' => 'active',
                'features' => [
                    'has_tables' => false,
                    'has_barcode_scanner' => true,
                ],
            ]
        );

        SduiModule::firstOrCreate(
            ['slug' => 'restaurant'],
            [
                'name' => 'Cafe & Restaurant',
                'type' => SduiModule::TYPE_CORE,
                'description' => 'Core Food & Restaurant vertical: dining table management, KOT printing, kitchen KDS display.',
                'icon' => 'restaurant',
                'layout_type' => 'table_floor_plan',
                'is_active' => true,
                'source_type' => 'builtin',
                'version' => config('app.version', '1.0.0'),
                'author' => 'ZoomNearby',
                'sort_order' => 2,
                'requires_license' => false,
                'license_status' => 'active',
                'features' => [
                    'has_tables' => true,
                    'has_kot' => true,
                ],
            ]
        );
    }

    public function down(): void
    {
        SduiModule::whereIn('slug', ['retail', 'restaurant'])
            ->where('source_type', 'builtin')
            ->delete();
    }
};
