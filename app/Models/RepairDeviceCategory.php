<?php

namespace App\Models;

/**
 * Compatibility model mapping legacy device categories directly to core Category table.
 */
class RepairDeviceCategory extends Category
{
    protected $table = 'categories';

    protected static function booted(): void
    {
        static::addGlobalScope('device_type', function ($builder) {
            $builder->where(function ($q) {
                $q->where('type', 'device')->orWhereNull('type');
            });
        });

        static::creating(function ($model) {
            $model->type = 'device';
        });
    }

    public static function defaultPresets(): array
    {
        return [
            [
                'name' => 'Smartphones & Mobiles',
                'slug' => 'smartphone',
                'icon' => 'smartphone',
                'identifier_type' => 'IMEI / Serial Number',
                'brands' => ['Apple', 'Samsung', 'Google Pixel', 'Xiaomi', 'OnePlus', 'Motorola', 'Oppo', 'Vivo', 'Huawei', 'Other'],
                'checklist_items' => [
                    'Power On / Booting',
                    'Display & Touch Digitizer',
                    'Front & Rear Cameras',
                    'Charging Port & Battery Drain',
                    'Ear Speaker & Loudspeaker',
                    'Microphones & Call Quality',
                    'Face ID / Fingerprint Sensor',
                    'Wi-Fi & Cellular Signal',
                ],
                'common_issues' => [
                    'Broken / Shattered Screen',
                    'Battery Not Charging / Drains Fast',
                    'Water / Liquid Damage',
                    'Camera Lens Cracked',
                    'No Power / Boot Loop',
                    'Speaker Distortion',
                ],
                'description' => 'Mobile phones, iOS & Android devices',
                'sort_order' => 1,
            ],
            [
                'name' => 'Laptops & MacBooks',
                'slug' => 'laptop-notebook',
                'icon' => 'laptop',
                'identifier_type' => 'Serial Number',
                'brands' => ['Apple MacBook', 'Dell', 'HP', 'Lenovo ThinkPad', 'Asus ROG', 'Acer', 'Microsoft Surface', 'MSI', 'Razer', 'Other'],
                'checklist_items' => [
                    'Power On & POST',
                    'Screen Display & Backlight',
                    'Keyboard & Trackpad',
                    'Battery Health & AC Adapter',
                    'Storage & RAM Diagnostics',
                    'USB & Type-C / HDMI Ports',
                    'Internal Cooling Fan & Thermals',
                    'Wi-Fi & Bluetooth Connectivity',
                ],
                'common_issues' => [
                    'Cracked LCD / Glitched Screen',
                    'Thermal Overheating / Fan Noise',
                    'Liquid Spill on Keyboard',
                    'Broken Hinge or Chassis',
                    'SSD / OS Boot Failure',
                    'Battery Swelling / Not Holding Charge',
                ],
                'description' => 'Laptops, MacBooks, gaming notebooks, and ultra-portables',
                'sort_order' => 2,
            ],
            [
                'name' => 'Tablets & iPads',
                'slug' => 'tablet',
                'icon' => 'tablet',
                'identifier_type' => 'Serial / IMEI',
                'brands' => ['Apple iPad', 'Samsung Galaxy Tab', 'Microsoft Surface Pro', 'Lenovo Tab', 'Amazon Fire', 'Other'],
                'checklist_items' => [
                    'Power On / Boot',
                    'Touch Screen & Apple Pencil / Stylus',
                    'Battery & Charging Current',
                    'Front & Back Cameras',
                    'Buttons (Power, Volume)',
                    'Audio & Speakers',
                ],
                'common_issues' => [
                    'Cracked Front Glass Digitizer',
                    'Bent Frame / Housing',
                    'Loose Charging Port',
                    'Battery Not Charging',
                ],
                'description' => 'Tablets, iPads, and touch slate devices',
                'sort_order' => 3,
            ],
            [
                'name' => 'Home Appliances',
                'slug' => 'home-appliance',
                'icon' => 'kitchen',
                'identifier_type' => 'Model / Serial Number',
                'brands' => ['LG', 'Samsung', 'Whirlpool', 'Bosch', 'Panasonic', 'Philips', 'Haier', 'Godrej', 'Other'],
                'checklist_items' => [
                    'Power Input & Fuse',
                    'Control Panel & Display',
                    'Motor / Compressor Operation',
                    'Heating / Cooling Test',
                    'Water / Gas Leakage Inspection',
                    'Cables, Hoses & Safety Ground',
                ],
                'common_issues' => [
                    'No Power / Fuse Trips',
                    'Motor or Compressor Noise',
                    'Water Leakage',
                    'Not Heating / Cooling',
                    'Control Board Error',
                ],
                'description' => 'Kitchen, laundry, cooling, and small domestic appliances',
                'sort_order' => 4,
            ],
            [
                'name' => 'Gaming Consoles',
                'slug' => 'gaming-console',
                'icon' => 'sports_esports',
                'identifier_type' => 'Console Serial Number',
                'brands' => ['Sony PlayStation 5', 'Sony PlayStation 4', 'Microsoft Xbox Series X/S', 'Microsoft Xbox One', 'Nintendo Switch', 'Steam Deck', 'Other'],
                'checklist_items' => [
                    'Power On & Boot to Dashboard',
                    'HDMI Video & Audio Output',
                    'Disc Drive / Cartridge Reader',
                    'Controller Bluetooth Sync',
                    'Cooling Fan & Overheating Status',
                    'Wi-Fi & Ethernet Network',
                ],
                'common_issues' => [
                    'Damaged / Loose HDMI Port',
                    'Overheating & Instant Shutdown',
                    'Disc Read Error',
                    'No Power / BLOD / WLOD',
                    'Drifting Stick / Controller Port Fault',
                ],
                'description' => 'Video game consoles and handheld gaming devices',
                'sort_order' => 5,
            ],
            [
                'name' => 'Audio & Headphones',
                'slug' => 'audio-headphones',
                'icon' => 'headphones',
                'identifier_type' => 'Serial Number',
                'brands' => ['Sony', 'Bose', 'Apple AirPods', 'JBL', 'Sennheiser', 'Marshall', 'Beats', 'Other'],
                'checklist_items' => [
                    'Power On & Bluetooth Pairing',
                    'Left Channel Audio Output',
                    'Right Channel Audio Output',
                    'Active Noise Cancellation (ANC)',
                    'Built-in Microphone Clarity',
                    'Battery Capacity & Case Charging',
                ],
                'common_issues' => [
                    'One Side Audio Not Working',
                    'Battery Drains in 15 Minutes',
                    'Charging Case Port Broken',
                    'Distorted Sound / Buzzing Noise',
                ],
                'description' => 'Wireless earbuds, over-ear headphones, and portable speakers',
                'sort_order' => 6,
            ],
            [
                'name' => 'Drones & Aerial Equipment',
                'slug' => 'drone-aerial',
                'icon' => 'flight',
                'identifier_type' => 'Aircraft Serial / Registration',
                'brands' => ['DJI', 'Autel Robotics', 'Parrot', 'Skydio', 'Holy Stone', 'Other'],
                'checklist_items' => [
                    'Power On & Flight Controller Self-Test',
                    'Propeller Motors & ESC Response',
                    'Gimbal Stabilization & Camera Feed',
                    'GPS Satellite Lock & Compass',
                    'Obstacle Avoidance Sensors',
                    'Remote Controller Link & Telemetry',
                ],
                'common_issues' => [
                    'Crashed Arm / Broken Propeller Motor',
                    'Gimbal Overload or Ribbon Cable Tear',
                    'ESC Calibration Error',
                    'Camera Vision Sensor Error',
                ],
                'description' => 'Drones, gimbals, and quadcopter accessories',
                'sort_order' => 7,
            ],
        ];
    }
}
