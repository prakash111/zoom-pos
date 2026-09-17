<?php

namespace App\Console\Commands;

use App\Models\DynamicSetting;
use App\Models\MenuItem;
use App\Models\PlatformBranding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplyLandingReferenceDesignCommand extends Command
{
    protected $signature = 'landing:apply-reference-design';

    protected $description = 'Apply the blue POS landing reference layout and copy, preserving the existing feature catalog and supporting sections.';

    public function handle(): int
    {
        $branding = PlatformBranding::current();
        if ($branding->landingText('design.reference') === '1') {
            $this->info('The reference design is already applied. Existing customizations were preserved.');

            return self::SUCCESS;
        }

        if (! app()->environment('testing')) {
            $backup = 'landing-design-backups/'.now()->format('Y-m-d-His').'.json';
            Storage::disk('local')->put($backup, json_encode([
                'theme' => setting('landing_page_theme', 'theme_fast'),
                'branding' => $branding->only([
                    'landing_page_enabled', 'landing_primary_color', 'landing_accent_color',
                    'landing_hero_badge', 'landing_hero_title', 'landing_hero_subtitle',
                    'landing_hero_cta_primary_text', 'landing_hero_cta_primary_url',
                    'landing_hero_cta_secondary_text', 'landing_hero_cta_secondary_url',
                    'landing_sections_config', 'landing_section_meta', 'landing_content',
                ]),
                'header_menu' => MenuItem::where('location', 'header')->get()->toArray(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $this->line('Previous landing configuration saved to '.$backup);
        }

        DB::transaction(function () use ($branding) {
            $content = $branding->landing_content ?? [];
            data_set($content, 'design.reference', '1');
            data_set($content, 'design.previous_stats', $branding->landingStatsList());
            data_set($content, 'stats', [
                ['value' => '130+', 'label' => 'Happy Businesses'],
                ['value' => '4.9/5', 'label' => 'Customer Rating'],
                ['value' => '99.9%', 'label' => 'Uptime Guarantee'],
                ['value' => '24/7', 'label' => 'Support'],
            ]);
            data_set($content, 'cta.primary_text', 'Start Free Trial');
            data_set($content, 'cta.primary_url', route('tenant.register'));

            $meta = $branding->landing_section_meta ?? [];
            foreach ([
                'hero' => ['badge' => 'Smart POS  •  Inventory  •  Sales  •  Reports', 'title' => 'Everything You Need to Run Your Business', 'subtitle' => 'Manage sales, inventory, customers, invoices and more — all in one powerful and easy-to-use platform. Perfect for retail stores, restaurants and growing businesses.'],
                'features' => ['badge' => 'Powerful Features', 'title' => 'Everything You Need in One Platform', 'subtitle' => 'From point of sale to advanced reporting, our platform gives you the tools to work smarter, serve better and grow faster.'],
                'cta' => ['title' => 'Ready to take your business to the next level?', 'subtitle' => 'Join successful businesses using our POS & Inventory platform.'],
            ] as $section => $copy) {
                $meta[$section] = array_replace($meta[$section] ?? [], $copy);
            }

            $config = $branding->landing_sections_config ?? [];
            $config['order'] = ['hero', 'stats', 'features', 'cta', 'trust_bar', 'solutions', 'downloads', 'about', 'testimonials', 'pricing', 'faq', 'contact'];
            $branding->update([
                'landing_page_enabled' => true,
                'landing_primary_color' => '#0065ff',
                'landing_accent_color' => '#6233ff',
                'landing_hero_badge' => $meta['hero']['badge'],
                'landing_hero_title' => $meta['hero']['title'],
                'landing_hero_subtitle' => $meta['hero']['subtitle'],
                'landing_hero_cta_primary_text' => 'Start Your Free Trial',
                'landing_hero_cta_primary_url' => route('tenant.register'),
                'landing_hero_cta_secondary_text' => 'Watch Demo',
                'landing_hero_cta_secondary_url' => '#demo',
                'landing_section_meta' => $meta,
                'landing_sections_config' => $config,
                'landing_content' => $content,
            ]);
            DynamicSetting::set('landing_page_theme', 'theme_fast');

            // Keep existing menu links accessible in the footer while the
            // header adopts the five navigation entries in the reference.
            foreach (MenuItem::where('location', 'header')->get() as $item) {
                $item->update(['location' => 'footer_col_2']);
            }
            foreach (['Home' => '#showcase', 'Features' => '#features', 'Pricing' => '#pricing', 'About' => '#about', 'Contact' => '#contact'] as $index => $url) {
                MenuItem::create([
                    'title' => $index,
                    'url' => $url,
                    'type' => 'custom',
                    'target' => '_self',
                    'location' => 'header',
                    'order_index' => ['Home' => 0, 'Features' => 1, 'Pricing' => 2, 'About' => 3, 'Contact' => 4][$index],
                    'is_active' => true,
                ]);
            }
        });

        MenuItem::clearMenuCache();
        Cache::forget('app_landing_page_theme');
        Cache::forget('landing_sections_theme_palette');
        Cache::forever('landing_page_cache_version', (int) Cache::get('landing_page_cache_version', 1) + 1);
        $this->info('Reference landing design applied. Existing feature descriptions, plans, supporting sections and legal links were retained.');

        return self::SUCCESS;
    }
}
