<?php

/**
 * Marketing Landing Page Configuration
 * 
 * The landing page is a pure UI template. ALL pricing, packages, bundles,
 * hero copy, feature cards, demo links, discount rates, and FAQs are managed
 * and fetched dynamically from your Central License Manager.
 * 
 * To host this on ANY domain, simply set LICENSE_SERVER_URL below!
 */

// 1. Central License Manager Location
// Enter the URL where your license manager is installed (e.g. 'https://license.yourdomain.com' or 'https://yourdomain.com/lic')
if (!defined('LICENSE_SERVER_URL')) {
    $envUrl = getenv('LICENSE_SERVER_URL');
    if (!$envUrl && isset($_SERVER['LICENSE_SERVER_URL'])) {
        $envUrl = $_SERVER['LICENSE_SERVER_URL'];
    }
    if (!$envUrl && file_exists(__DIR__ . '/.env')) {
        $envLines = @file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($envLines) {
            foreach ($envLines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                if (strpos($line, '=') !== false) {
                    [$k, $v] = explode('=', $line, 2);
                    if (trim($k) === 'LICENSE_SERVER_URL') {
                        $envUrl = trim($v, " \t\n\r\0\x0B\"'");
                        break;
                    }
                }
            }
        }
    }
    if ($envUrl) {
        define('LICENSE_SERVER_URL', rtrim($envUrl, '/'));
    } else {
        define('LICENSE_SERVER_URL', 'https://license.zoomnearby.com');
    }
}

// Public API endpoints on the Central License Manager
define('LANDING_API_URL', LICENSE_SERVER_URL . '/api/landing.php');
define('CHECKOUT_URL', LICENSE_SERVER_URL . '/buy.php');
define('VERIFY_API_URL', LICENSE_SERVER_URL . '/api/verify.php');

if (!function_exists('marketing_asset')) {
    function marketing_asset($relPath) {
        $clean = ltrim($relPath, '/');
        $file = __DIR__ . '/assets/' . $clean;
        $ver = file_exists($file) ? filemtime($file) : '1.2.0';
        return 'assets/' . $clean . '?v=' . $ver;
    }
}

if (!function_exists('default_section_colors')) {
    function default_section_colors(): array
    {
        return [
            'hero' => [
                'name' => 'Hero Banner Section',
                'icon' => '🚀',
                'dark_bg' => '#070a1a',
                'light_bg' => '#ffffff',
            ],
            'category_strip' => [
                'name' => 'Category Overview Strip',
                'icon' => '🏷️',
                'dark_bg' => '#0c1029',
                'light_bg' => '#f8fafc',
            ],
            'features' => [
                'name' => 'Core Platform Features',
                'icon' => '⚡',
                'dark_bg' => '#070a1a',
                'light_bg' => '#ffffff',
            ],
            'business_types' => [
                'name' => 'Business Types & Verticals',
                'icon' => '🛒',
                'dark_bg' => '#0c1029',
                'light_bg' => '#f8fafc',
            ],
            'industries' => [
                'name' => 'Commercial Industries Grid',
                'icon' => '🏭',
                'dark_bg' => '#070a1a',
                'light_bg' => '#ffffff',
            ],
            'modules' => [
                'name' => 'Modular Add-Ons Overview',
                'icon' => '📦',
                'dark_bg' => '#0c1029',
                'light_bg' => '#f8fafc',
            ],
            'demos' => [
                'name' => 'Multi-Platform Live Demos',
                'icon' => '📱',
                'dark_bg' => '#070a1a',
                'light_bg' => '#ffffff',
            ],
            'pricing' => [
                'name' => 'Pricing & Bundles Section',
                'icon' => '💰',
                'dark_bg' => '#0c1029',
                'light_bg' => '#f8fafc',
            ],
            'why_us' => [
                'name' => 'Why Zoom POS? & Metrics',
                'icon' => '🏆',
                'dark_bg' => '#070a1a',
                'light_bg' => '#ffffff',
            ],
            'ecosystem' => [
                'name' => 'Ecosystem Architecture Section',
                'icon' => '🌐',
                'dark_bg' => '#0c1029',
                'light_bg' => '#f8fafc',
            ],
            'cta_banner' => [
                'name' => 'Ready to Build CTA Banner',
                'icon' => '📣',
                'dark_bg' => 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)',
                'light_bg' => 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)',
            ],
            'faq' => [
                'name' => 'Frequently Asked Questions (FAQ)',
                'icon' => '❓',
                'dark_bg' => '#070a1a',
                'light_bg' => '#ffffff',
            ],
            'footer' => [
                'name' => 'Footer',
                'icon' => '👣',
                'dark_bg' => '#050714',
                'light_bg' => '#0f172a',
            ],
        ];
    }
}

if (!function_exists('landing_color_presets')) {
    function landing_color_presets(): array
    {
        return [
            'midnight_obsidian' => [
                'id' => 'midnight_obsidian',
                'name' => 'Midnight Obsidian & Clean Slate',
                'badge' => 'Default Classic',
                'icon' => '🌌',
                'description' => 'Deep obsidian cosmos with ultra-clean slate and royal indigo accents.',
                'accent' => '#6366f1',
                'dark' => [
                    'primary' => '#070a1a',
                    'secondary' => '#0c1029',
                    'card_bg' => '#0f172a',
                    'card_border' => 'rgba(255, 255, 255, 0.08)',
                    'text_title' => '#ffffff',
                    'text_body' => '#94a3b8',
                    'btn_pri_bg' => '#5850ec',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => 'rgba(255, 255, 255, 0.08)',
                    'btn_sec_border' => 'rgba(255, 255, 255, 0.2)',
                    'btn_sec_color' => '#ffffff',
                    'footer_bg' => '#050714',
                ],
                'light' => [
                    'primary' => '#ffffff',
                    'secondary' => '#f8fafc',
                    'card_bg' => '#ffffff',
                    'card_border' => '#e2e8f0',
                    'text_title' => '#0f172a',
                    'text_body' => '#475569',
                    'btn_pri_bg' => '#5850ec',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => '#f1f5f9',
                    'btn_sec_border' => '#cbd5e1',
                    'btn_sec_color' => '#0f172a',
                    'footer_bg' => '#0f172a',
                ],
                'cta_gradient' => 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)',
                'sections' => [
                    'hero' => ['dark' => '#070a1a', 'light' => '#ffffff'],
                    'category_strip' => ['dark' => '#0c1029', 'light' => '#f8fafc'],
                    'features' => ['dark' => '#070a1a', 'light' => '#ffffff'],
                    'business_types' => ['dark' => '#0c1029', 'light' => '#f8fafc'],
                    'industries' => ['dark' => '#070a1a', 'light' => '#ffffff'],
                    'modules' => ['dark' => '#0c1029', 'light' => '#f8fafc'],
                    'demos' => ['dark' => '#070a1a', 'light' => '#ffffff'],
                    'pricing' => ['dark' => '#0c1029', 'light' => '#f8fafc'],
                    'why_us' => ['dark' => '#070a1a', 'light' => '#ffffff'],
                    'ecosystem' => ['dark' => '#0c1029', 'light' => '#f8fafc'],
                    'cta_banner' => ['dark' => 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)', 'light' => 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)'],
                    'faq' => ['dark' => '#070a1a', 'light' => '#ffffff'],
                    'footer' => ['dark' => '#050714', 'light' => '#0f172a'],
                ],
            ],
            'royal_navy' => [
                'id' => 'royal_navy',
                'name' => 'Royal Navy & Arctic Ice',
                'badge' => 'Fintech & Enterprise',
                'icon' => '⚓',
                'description' => 'Deep corporate navy paired with crisp arctic white and sapphire accents.',
                'accent' => '#2563eb',
                'dark' => [
                    'primary' => '#060f24',
                    'secondary' => '#0b1a3d',
                    'card_bg' => '#0f2352',
                    'card_border' => 'rgba(147, 197, 253, 0.15)',
                    'text_title' => '#ffffff',
                    'text_body' => '#93c5fd',
                    'btn_pri_bg' => '#2563eb',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => 'rgba(59, 130, 246, 0.15)',
                    'btn_sec_border' => 'rgba(147, 197, 253, 0.3)',
                    'btn_sec_color' => '#ffffff',
                    'footer_bg' => '#040917',
                ],
                'light' => [
                    'primary' => '#ffffff',
                    'secondary' => '#f0f7ff',
                    'card_bg' => '#ffffff',
                    'card_border' => '#bfdbfe',
                    'text_title' => '#0b1a3d',
                    'text_body' => '#334155',
                    'btn_pri_bg' => '#2563eb',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => '#e0f2fe',
                    'btn_sec_border' => '#93c5fd',
                    'btn_sec_color' => '#0369a1',
                    'footer_bg' => '#0b1a3d',
                ],
                'cta_gradient' => 'linear-gradient(135deg, #1d4ed8 0%, #0284c7 100%)',
                'sections' => [
                    'hero' => ['dark' => '#060f24', 'light' => '#ffffff'],
                    'category_strip' => ['dark' => '#0b1a3d', 'light' => '#f0f7ff'],
                    'features' => ['dark' => '#060f24', 'light' => '#ffffff'],
                    'business_types' => ['dark' => '#0b1a3d', 'light' => '#f0f7ff'],
                    'industries' => ['dark' => '#060f24', 'light' => '#ffffff'],
                    'modules' => ['dark' => '#0b1a3d', 'light' => '#f0f7ff'],
                    'demos' => ['dark' => '#060f24', 'light' => '#ffffff'],
                    'pricing' => ['dark' => '#0b1a3d', 'light' => '#f0f7ff'],
                    'why_us' => ['dark' => '#060f24', 'light' => '#ffffff'],
                    'ecosystem' => ['dark' => '#0b1a3d', 'light' => '#f0f7ff'],
                    'cta_banner' => ['dark' => 'linear-gradient(135deg, #1d4ed8 0%, #0284c7 100%)', 'light' => 'linear-gradient(135deg, #1d4ed8 0%, #0284c7 100%)'],
                    'faq' => ['dark' => '#060f24', 'light' => '#ffffff'],
                    'footer' => ['dark' => '#040917', 'light' => '#0b1a3d'],
                ],
            ],
            'cyber_emerald' => [
                'id' => 'cyber_emerald',
                'name' => 'Cyber Emerald & Mint Fresh',
                'badge' => 'High Growth',
                'icon' => '🌿',
                'description' => 'Cyberpunk dark slate-green with crisp mint tones and high-conversion emerald.',
                'accent' => '#10b981',
                'dark' => [
                    'primary' => '#051410',
                    'secondary' => '#09221b',
                    'card_bg' => '#0e2e25',
                    'card_border' => 'rgba(110, 231, 183, 0.15)',
                    'text_title' => '#ffffff',
                    'text_body' => '#a7f3d0',
                    'btn_pri_bg' => '#10b981',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => 'rgba(16, 185, 129, 0.15)',
                    'btn_sec_border' => 'rgba(110, 231, 183, 0.3)',
                    'btn_sec_color' => '#ffffff',
                    'footer_bg' => '#020b08',
                ],
                'light' => [
                    'primary' => '#ffffff',
                    'secondary' => '#f0fdf4',
                    'card_bg' => '#ffffff',
                    'card_border' => '#bbf7d0',
                    'text_title' => '#062e24',
                    'text_body' => '#374151',
                    'btn_pri_bg' => '#059669',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => '#dcfce7',
                    'btn_sec_border' => '#86efac',
                    'btn_sec_color' => '#166534',
                    'footer_bg' => '#062e24',
                ],
                'cta_gradient' => 'linear-gradient(135deg, #059669 0%, #0d9488 100%)',
                'sections' => [
                    'hero' => ['dark' => '#051410', 'light' => '#ffffff'],
                    'category_strip' => ['dark' => '#09221b', 'light' => '#f0fdf4'],
                    'features' => ['dark' => '#051410', 'light' => '#ffffff'],
                    'business_types' => ['dark' => '#09221b', 'light' => '#f0fdf4'],
                    'industries' => ['dark' => '#051410', 'light' => '#ffffff'],
                    'modules' => ['dark' => '#09221b', 'light' => '#f0fdf4'],
                    'demos' => ['dark' => '#051410', 'light' => '#ffffff'],
                    'pricing' => ['dark' => '#09221b', 'light' => '#f0fdf4'],
                    'why_us' => ['dark' => '#051410', 'light' => '#ffffff'],
                    'ecosystem' => ['dark' => '#09221b', 'light' => '#f0fdf4'],
                    'cta_banner' => ['dark' => 'linear-gradient(135deg, #059669 0%, #0d9488 100%)', 'light' => 'linear-gradient(135deg, #059669 0%, #0d9488 100%)'],
                    'faq' => ['dark' => '#051410', 'light' => '#ffffff'],
                    'footer' => ['dark' => '#020b08', 'light' => '#062e24'],
                ],
            ],
            'carbon_titanium' => [
                'id' => 'carbon_titanium',
                'name' => 'Carbon Titanium & Monochrome',
                'badge' => 'Minimalist Sleek',
                'icon' => '⚡',
                'description' => 'Industrial carbon graphite with ultra-minimal stark white and gunmetal accents.',
                'accent' => '#71717a',
                'dark' => [
                    'primary' => '#121316',
                    'secondary' => '#1a1c22',
                    'card_bg' => '#22252d',
                    'card_border' => 'rgba(255, 255, 255, 0.1)',
                    'text_title' => '#ffffff',
                    'text_body' => '#d1d5db',
                    'btn_pri_bg' => '#3f3f46',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => 'rgba(255, 255, 255, 0.1)',
                    'btn_sec_border' => 'rgba(255, 255, 255, 0.25)',
                    'btn_sec_color' => '#ffffff',
                    'footer_bg' => '#090a0c',
                ],
                'light' => [
                    'primary' => '#ffffff',
                    'secondary' => '#f4f4f5',
                    'card_bg' => '#ffffff',
                    'card_border' => '#e4e4e7',
                    'text_title' => '#18181b',
                    'text_body' => '#52525b',
                    'btn_pri_bg' => '#18181b',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => '#e4e4e7',
                    'btn_sec_border' => '#d4d4d8',
                    'btn_sec_color' => '#18181b',
                    'footer_bg' => '#18181b',
                ],
                'cta_gradient' => 'linear-gradient(135deg, #18181b 0%, #3f3f46 100%)',
                'sections' => [
                    'hero' => ['dark' => '#121316', 'light' => '#ffffff'],
                    'category_strip' => ['dark' => '#1a1c22', 'light' => '#f4f4f5'],
                    'features' => ['dark' => '#121316', 'light' => '#ffffff'],
                    'business_types' => ['dark' => '#1a1c22', 'light' => '#f4f4f5'],
                    'industries' => ['dark' => '#121316', 'light' => '#ffffff'],
                    'modules' => ['dark' => '#1a1c22', 'light' => '#f4f4f5'],
                    'demos' => ['dark' => '#121316', 'light' => '#ffffff'],
                    'pricing' => ['dark' => '#1a1c22', 'light' => '#f4f4f5'],
                    'why_us' => ['dark' => '#121316', 'light' => '#ffffff'],
                    'ecosystem' => ['dark' => '#1a1c22', 'light' => '#f4f4f5'],
                    'cta_banner' => ['dark' => 'linear-gradient(135deg, #18181b 0%, #3f3f46 100%)', 'light' => 'linear-gradient(135deg, #18181b 0%, #3f3f46 100%)'],
                    'faq' => ['dark' => '#121316', 'light' => '#ffffff'],
                    'footer' => ['dark' => '#090a0c', 'light' => '#18181b'],
                ],
            ],
            'deep_amethyst' => [
                'id' => 'deep_amethyst',
                'name' => 'Royal Amethyst & Lavender',
                'badge' => 'Luxury & Beauty',
                'icon' => '🔮',
                'description' => 'Opulent dark violet obsidian with soft lavender mist and magenta vibrancy.',
                'accent' => '#9333ea',
                'dark' => [
                    'primary' => '#11091e',
                    'secondary' => '#1b102e',
                    'card_bg' => '#261642',
                    'card_border' => 'rgba(216, 180, 254, 0.15)',
                    'text_title' => '#ffffff',
                    'text_body' => '#d8b4fe',
                    'btn_pri_bg' => '#9333ea',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => 'rgba(168, 85, 247, 0.15)',
                    'btn_sec_border' => 'rgba(216, 180, 254, 0.3)',
                    'btn_sec_color' => '#ffffff',
                    'footer_bg' => '#0a0512',
                ],
                'light' => [
                    'primary' => '#ffffff',
                    'secondary' => '#faf5ff',
                    'card_bg' => '#ffffff',
                    'card_border' => '#e9d5ff',
                    'text_title' => '#2e1065',
                    'text_body' => '#475569',
                    'btn_pri_bg' => '#7e22ce',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => '#f3e8ff',
                    'btn_sec_border' => '#d8b4fe',
                    'btn_sec_color' => '#6b21a8',
                    'footer_bg' => '#2e1065',
                ],
                'cta_gradient' => 'linear-gradient(135deg, #7e22ce 0%, #c026d3 100%)',
                'sections' => [
                    'hero' => ['dark' => '#11091e', 'light' => '#ffffff'],
                    'category_strip' => ['dark' => '#1b102e', 'light' => '#faf5ff'],
                    'features' => ['dark' => '#11091e', 'light' => '#ffffff'],
                    'business_types' => ['dark' => '#1b102e', 'light' => '#faf5ff'],
                    'industries' => ['dark' => '#11091e', 'light' => '#ffffff'],
                    'modules' => ['dark' => '#1b102e', 'light' => '#faf5ff'],
                    'demos' => ['dark' => '#11091e', 'light' => '#ffffff'],
                    'pricing' => ['dark' => '#1b102e', 'light' => '#faf5ff'],
                    'why_us' => ['dark' => '#11091e', 'light' => '#ffffff'],
                    'ecosystem' => ['dark' => '#1b102e', 'light' => '#faf5ff'],
                    'cta_banner' => ['dark' => 'linear-gradient(135deg, #7e22ce 0%, #c026d3 100%)', 'light' => 'linear-gradient(135deg, #7e22ce 0%, #c026d3 100%)'],
                    'faq' => ['dark' => '#11091e', 'light' => '#ffffff'],
                    'footer' => ['dark' => '#0a0512', 'light' => '#2e1065'],
                ],
            ],
            'oceanic_teal' => [
                'id' => 'oceanic_teal',
                'name' => 'Oceanic Deep Teal & Seafoam',
                'badge' => 'Cafe & Dining',
                'icon' => '🌊',
                'description' => 'Abyssal teal depths with breezy seafoam white and bright cyan highlights.',
                'accent' => '#0d9488',
                'dark' => [
                    'primary' => '#04151d',
                    'secondary' => '#08222e',
                    'card_bg' => '#0d3243',
                    'card_border' => 'rgba(153, 246, 228, 0.15)',
                    'text_title' => '#ffffff',
                    'text_body' => '#99f6e4',
                    'btn_pri_bg' => '#0d9488',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => 'rgba(20, 184, 166, 0.15)',
                    'btn_sec_border' => 'rgba(153, 246, 228, 0.3)',
                    'btn_sec_color' => '#ffffff',
                    'footer_bg' => '#020c11',
                ],
                'light' => [
                    'primary' => '#ffffff',
                    'secondary' => '#f0fdfa',
                    'card_bg' => '#ffffff',
                    'card_border' => '#99f6e4',
                    'text_title' => '#134e4a',
                    'text_body' => '#334155',
                    'btn_pri_bg' => '#0f766e',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => '#ccfbf1',
                    'btn_sec_border' => '#5eead4',
                    'btn_sec_color' => '#115e59',
                    'footer_bg' => '#134e4a',
                ],
                'cta_gradient' => 'linear-gradient(135deg, #0f766e 0%, #0284c7 100%)',
                'sections' => [
                    'hero' => ['dark' => '#04151d', 'light' => '#ffffff'],
                    'category_strip' => ['dark' => '#08222e', 'light' => '#f0fdfa'],
                    'features' => ['dark' => '#04151d', 'light' => '#ffffff'],
                    'business_types' => ['dark' => '#08222e', 'light' => '#f0fdfa'],
                    'industries' => ['dark' => '#04151d', 'light' => '#ffffff'],
                    'modules' => ['dark' => '#08222e', 'light' => '#f0fdfa'],
                    'demos' => ['dark' => '#04151d', 'light' => '#ffffff'],
                    'pricing' => ['dark' => '#08222e', 'light' => '#f0fdfa'],
                    'why_us' => ['dark' => '#04151d', 'light' => '#ffffff'],
                    'ecosystem' => ['dark' => '#08222e', 'light' => '#f0fdfa'],
                    'cta_banner' => ['dark' => 'linear-gradient(135deg, #0f766e 0%, #0284c7 100%)', 'light' => 'linear-gradient(135deg, #0f766e 0%, #0284c7 100%)'],
                    'faq' => ['dark' => '#04151d', 'light' => '#ffffff'],
                    'footer' => ['dark' => '#020c11', 'light' => '#134e4a'],
                ],
            ],
            'crimson_velvet' => [
                'id' => 'crimson_velvet',
                'name' => 'Crimson Velvet & Rose Pearl',
                'badge' => 'Gourmet & Dining',
                'icon' => '🍷',
                'description' => 'Moody dark ruby slate with soft rose quartz white and rich scarlet accents.',
                'accent' => '#e11d48',
                'dark' => [
                    'primary' => '#1a080d',
                    'secondary' => '#270e15',
                    'card_bg' => '#3b1420',
                    'card_border' => 'rgba(254, 205, 211, 0.15)',
                    'text_title' => '#ffffff',
                    'text_body' => '#fecdd3',
                    'btn_pri_bg' => '#e11d48',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => 'rgba(225, 29, 72, 0.15)',
                    'btn_sec_border' => 'rgba(254, 205, 211, 0.3)',
                    'btn_sec_color' => '#ffffff',
                    'footer_bg' => '#0f0407',
                ],
                'light' => [
                    'primary' => '#ffffff',
                    'secondary' => '#fff1f2',
                    'card_bg' => '#ffffff',
                    'card_border' => '#fecdd3',
                    'text_title' => '#881337',
                    'text_body' => '#475569',
                    'btn_pri_bg' => '#be123c',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => '#ffe4e6',
                    'btn_sec_border' => '#fda4af',
                    'btn_sec_color' => '#9f1239',
                    'footer_bg' => '#4c0519',
                ],
                'cta_gradient' => 'linear-gradient(135deg, #be123c 0%, #e11d48 100%)',
                'sections' => [
                    'hero' => ['dark' => '#1a080d', 'light' => '#ffffff'],
                    'category_strip' => ['dark' => '#270e15', 'light' => '#fff1f2'],
                    'features' => ['dark' => '#1a080d', 'light' => '#ffffff'],
                    'business_types' => ['dark' => '#270e15', 'light' => '#fff1f2'],
                    'industries' => ['dark' => '#1a080d', 'light' => '#ffffff'],
                    'modules' => ['dark' => '#270e15', 'light' => '#fff1f2'],
                    'demos' => ['dark' => '#1a080d', 'light' => '#ffffff'],
                    'pricing' => ['dark' => '#270e15', 'light' => '#fff1f2'],
                    'why_us' => ['dark' => '#1a080d', 'light' => '#ffffff'],
                    'ecosystem' => ['dark' => '#270e15', 'light' => '#fff1f2'],
                    'cta_banner' => ['dark' => 'linear-gradient(135deg, #be123c 0%, #e11d48 100%)', 'light' => 'linear-gradient(135deg, #be123c 0%, #e11d48 100%)'],
                    'faq' => ['dark' => '#1a080d', 'light' => '#ffffff'],
                    'footer' => ['dark' => '#0f0407', 'light' => '#4c0519'],
                ],
            ],
            'amber_charcoal' => [
                'id' => 'amber_charcoal',
                'name' => 'Warm Charcoal & Amber Honey',
                'badge' => 'Artisan & Retail',
                'icon' => '🍯',
                'description' => 'Roasted espresso graphite with warm golden honey amber and soft bakery cream.',
                'accent' => '#f59e0b',
                'dark' => [
                    'primary' => '#15120e',
                    'secondary' => '#221c16',
                    'card_bg' => '#332a21',
                    'card_border' => 'rgba(253, 230, 138, 0.15)',
                    'text_title' => '#ffffff',
                    'text_body' => '#fde68a',
                    'btn_pri_bg' => '#f59e0b',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => 'rgba(245, 158, 11, 0.15)',
                    'btn_sec_border' => 'rgba(253, 230, 138, 0.3)',
                    'btn_sec_color' => '#ffffff',
                    'footer_bg' => '#0c0a08',
                ],
                'light' => [
                    'primary' => '#ffffff',
                    'secondary' => '#fffbeb',
                    'card_bg' => '#ffffff',
                    'card_border' => '#fde68a',
                    'text_title' => '#78350f',
                    'text_body' => '#44403c',
                    'btn_pri_bg' => '#d97706',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => '#fef3c7',
                    'btn_sec_border' => '#fcd34d',
                    'btn_sec_color' => '#92400e',
                    'footer_bg' => '#292524',
                ],
                'cta_gradient' => 'linear-gradient(135deg, #d97706 0%, #b45309 100%)',
                'sections' => [
                    'hero' => ['dark' => '#15120e', 'light' => '#ffffff'],
                    'category_strip' => ['dark' => '#221c16', 'light' => '#fffbeb'],
                    'features' => ['dark' => '#15120e', 'light' => '#ffffff'],
                    'business_types' => ['dark' => '#221c16', 'light' => '#fffbeb'],
                    'industries' => ['dark' => '#15120e', 'light' => '#ffffff'],
                    'modules' => ['dark' => '#221c16', 'light' => '#fffbeb'],
                    'demos' => ['dark' => '#15120e', 'light' => '#ffffff'],
                    'pricing' => ['dark' => '#221c16', 'light' => '#fffbeb'],
                    'why_us' => ['dark' => '#15120e', 'light' => '#ffffff'],
                    'ecosystem' => ['dark' => '#221c16', 'light' => '#fffbeb'],
                    'cta_banner' => ['dark' => 'linear-gradient(135deg, #d97706 0%, #b45309 100%)', 'light' => 'linear-gradient(135deg, #d97706 0%, #b45309 100%)'],
                    'faq' => ['dark' => '#15120e', 'light' => '#ffffff'],
                    'footer' => ['dark' => '#0c0a08', 'light' => '#292524'],
                ],
            ],
            'midnight_indigo' => [
                'id' => 'midnight_indigo',
                'name' => 'Midnight Indigo & Electric Azure',
                'badge' => 'Hyper-Scale SaaS',
                'icon' => '⚡',
                'description' => 'Deep midnight space indigo with high-voltage electric blue accents and azure frost.',
                'accent' => '#3b82f6',
                'dark' => [
                    'primary' => '#080b21',
                    'secondary' => '#0e1438',
                    'card_bg' => '#151e52',
                    'card_border' => 'rgba(191, 219, 254, 0.15)',
                    'text_title' => '#ffffff',
                    'text_body' => '#bfdbfe',
                    'btn_pri_bg' => '#3b82f6',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => 'rgba(59, 130, 246, 0.15)',
                    'btn_sec_border' => 'rgba(191, 219, 254, 0.3)',
                    'btn_sec_color' => '#ffffff',
                    'footer_bg' => '#040614',
                ],
                'light' => [
                    'primary' => '#ffffff',
                    'secondary' => '#eff6ff',
                    'card_bg' => '#ffffff',
                    'card_border' => '#bfdbfe',
                    'text_title' => '#1e3a8a',
                    'text_body' => '#334155',
                    'btn_pri_bg' => '#2563eb',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => '#dbeafe',
                    'btn_sec_border' => '#93c5fd',
                    'btn_sec_color' => '#1d4ed8',
                    'footer_bg' => '#1e293b',
                ],
                'cta_gradient' => 'linear-gradient(135deg, #2563eb 0%, #4f46e5 100%)',
                'sections' => [
                    'hero' => ['dark' => '#080b21', 'light' => '#ffffff'],
                    'category_strip' => ['dark' => '#0e1438', 'light' => '#eff6ff'],
                    'features' => ['dark' => '#080b21', 'light' => '#ffffff'],
                    'business_types' => ['dark' => '#0e1438', 'light' => '#eff6ff'],
                    'industries' => ['dark' => '#080b21', 'light' => '#ffffff'],
                    'modules' => ['dark' => '#0e1438', 'light' => '#eff6ff'],
                    'demos' => ['dark' => '#080b21', 'light' => '#ffffff'],
                    'pricing' => ['dark' => '#0e1438', 'light' => '#eff6ff'],
                    'why_us' => ['dark' => '#080b21', 'light' => '#ffffff'],
                    'ecosystem' => ['dark' => '#0e1438', 'light' => '#eff6ff'],
                    'cta_banner' => ['dark' => 'linear-gradient(135deg, #2563eb 0%, #4f46e5 100%)', 'light' => 'linear-gradient(135deg, #2563eb 0%, #4f46e5 100%)'],
                    'faq' => ['dark' => '#080b21', 'light' => '#ffffff'],
                    'footer' => ['dark' => '#040614', 'light' => '#1e293b'],
                ],
            ],
            'aurora_ice' => [
                'id' => 'aurora_ice',
                'name' => 'Aurora Borealis & Glacier Ice',
                'badge' => 'Nordic Polar',
                'icon' => '❄️',
                'description' => 'Polar night aurora cyan-slate with gleaming glacier ice and silver brilliance.',
                'accent' => '#06b6d4',
                'dark' => [
                    'primary' => '#061219',
                    'secondary' => '#0a1c26',
                    'card_bg' => '#102a39',
                    'card_border' => 'rgba(186, 230, 253, 0.15)',
                    'text_title' => '#ffffff',
                    'text_body' => '#bae6fd',
                    'btn_pri_bg' => '#06b6d4',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => 'rgba(6, 182, 212, 0.15)',
                    'btn_sec_border' => 'rgba(186, 230, 253, 0.3)',
                    'btn_sec_color' => '#ffffff',
                    'footer_bg' => '#03090d',
                ],
                'light' => [
                    'primary' => '#ffffff',
                    'secondary' => '#f0f9ff',
                    'card_bg' => '#ffffff',
                    'card_border' => '#bae6fd',
                    'text_title' => '#0c4a6e',
                    'text_body' => '#334155',
                    'btn_pri_bg' => '#0284c7',
                    'btn_pri_text' => '#ffffff',
                    'btn_sec_bg' => '#e0f2fe',
                    'btn_sec_border' => '#7dd3fc',
                    'btn_sec_color' => '#0369a1',
                    'footer_bg' => '#0f172a',
                ],
                'cta_gradient' => 'linear-gradient(135deg, #0284c7 0%, #0d9488 100%)',
                'sections' => [
                    'hero' => ['dark' => '#061219', 'light' => '#ffffff'],
                    'category_strip' => ['dark' => '#0a1c26', 'light' => '#f0f9ff'],
                    'features' => ['dark' => '#061219', 'light' => '#ffffff'],
                    'business_types' => ['dark' => '#0a1c26', 'light' => '#f0f9ff'],
                    'industries' => ['dark' => '#061219', 'light' => '#ffffff'],
                    'modules' => ['dark' => '#0a1c26', 'light' => '#f0f9ff'],
                    'demos' => ['dark' => '#061219', 'light' => '#ffffff'],
                    'pricing' => ['dark' => '#0a1c26', 'light' => '#f0f9ff'],
                    'why_us' => ['dark' => '#061219', 'light' => '#ffffff'],
                    'ecosystem' => ['dark' => '#0a1c26', 'light' => '#f0f9ff'],
                    'cta_banner' => ['dark' => 'linear-gradient(135deg, #0284c7 0%, #0d9488 100%)', 'light' => 'linear-gradient(135deg, #0284c7 0%, #0d9488 100%)'],
                    'faq' => ['dark' => '#061219', 'light' => '#ffffff'],
                    'footer' => ['dark' => '#03090d', 'light' => '#0f172a'],
                ],
            ],
        ];
    }
}

if (!function_exists('get_color_preset')) {
    function get_color_preset(?string $key): array
    {
        $presets = landing_color_presets();
        if ($key && isset($presets[$key])) {
            return $presets[$key];
        }
        return $presets['midnight_obsidian'];
    }
}

if (!function_exists('default_marketing_faqs')) {
    function default_marketing_faqs(): array
    {
        return [
            [
                'q' => 'What is included in the Core main script?',
                'a' => 'The Core main script includes full Retail POS, Restaurant POS (with Table Management, Kitchen Order Tickets / KOT, and Waiter workflow), and Café / Quick-Service modes built-in out of the box. It also includes multi-store warehousing, thermal receipt printing (80mm/58mm), barcode generation, customer ledgers, and the complete multi-tenant SaaS billing engine.',
            ],
            [
                'q' => 'What do I receive after completing payment?',
                'a' => 'Immediately upon purchase, your license details are rendered on screen and sent to your registered email address. This includes your official license key for the Core SaaS platform, plus individual license keys for any add-on modules purchased in your bundle, with simple setup steps.',
            ],
            [
                'q' => 'Can I host this on any domain, cPanel, or VPS?',
                'a' => 'Yes! The system is designed to run on any standard hosting environment with PHP 8.2+ and MySQL. It runs perfectly on cPanel, CloudPanel, DirectAdmin, Ubuntu VPS, AWS, or DigitalOcean with standard Apache or Nginx.',
            ],
            [
                'q' => 'How does bundle pricing work?',
                'a' => 'You can purchase the Core SaaS script for $49.00. If you wish to bundle other modules (such as Lead Manager, Pharmacy POS, or Salon), you can either select our discounted ready-made bundles or use our interactive bundle builder to select exactly the modules you need with automatic bundle discounts applied.',
            ],
            [
                'q' => 'How do I activate vertical modules like Lead Manager?',
                'a' => 'In your self-hosted SaaS SuperAdmin panel, navigate to Modules. Find the purchased module, click Activate / Download, and enter the module\'s license key sent to your email. The system securely downloads the module package from the central license server and installs it automatically.',
            ],
            [
                'q' => 'Are there any recurring monthly subscription fees?',
                'a' => 'No! You pay once for a lifetime perpetual license. You own the code and can use it forever on your registered domain without recurring platform fees.',
            ],
        ];
    }
}

if (!function_exists('default_business_types_cards')) {
    function default_business_types_cards(): array
    {
        return [
            'retail' => [
                'key' => 'retail',
                'title' => 'Retail & Supermarkets',
                'icon' => '🛒',
                'tag_text' => 'Built-in Core Platform',
                'tag_class' => 'tag-core',
                'display_mode' => 'image',
                'image_url' => 'assets/images/retail-pos-mockup.png',
                'icon_bg' => '#eef2ff',
                'icon_color' => '#4f46e5',
                'badge_label' => 'Retail POS',
                'badge_sub' => 'Barcode & Counter Setup',
                'btn_text' => 'Explore Retail →',
                'btn_type' => 'link',
                'btn_url' => '#pricing',
                'module_slug' => '',
                'features' => [
                    'Barcode Scanning',
                    'Multi-Variant Stock',
                    'GST / VAT Taxes',
                    'Customer Ledgers',
                    'Purchase Orders',
                    'Returns & Refunds',
                    'Thermal Receipts',
                    'Price Label Print',
                ],
            ],
            'restaurant' => [
                'key' => 'restaurant',
                'title' => 'Restaurant, Café & QSR',
                'icon' => '🍽️',
                'tag_text' => 'Built-in Core Platform',
                'tag_class' => 'tag-core',
                'display_mode' => 'image',
                'image_url' => 'assets/images/restaurant-pos-mockup.png',
                'icon_bg' => '#eff6ff',
                'icon_color' => '#2563eb',
                'badge_label' => 'Restaurant & Café',
                'badge_sub' => 'Tables, KOT & Takeaway',
                'btn_text' => 'Explore Restaurant →',
                'btn_type' => 'link',
                'btn_url' => '#pricing',
                'module_slug' => '',
                'features' => [
                    'Visual Floor Tables',
                    'Kitchen Tickets (KOT)',
                    'Waiter Tablet App',
                    'Food Modifiers',
                    'Recipe Stock Costing',
                    'Contactless QR Menu',
                    'Split Bill by Seat',
                    'Queue Order Tokens',
                ],
            ],
            'pharmacy' => [
                'key' => 'pharmacy',
                'title' => 'Pharmacy & Healthcare',
                'icon' => '💊',
                'tag_text' => 'Vertical Module',
                'tag_class' => 'tag-vertical',
                'display_mode' => 'image',
                'image_url' => 'assets/images/pharmacy-pos-mockup.png',
                'icon_bg' => '#f0fdf4',
                'icon_color' => '#16a34a',
                'badge_label' => 'Pharmacy POS',
                'badge_sub' => 'Batch & Expiry Control',
                'btn_text' => 'Buy Pharmacy Module →',
                'btn_type' => 'checkout',
                'btn_url' => '',
                'module_slug' => 'pharmacy',
                'features' => [
                    'Batch & Lot Numbers',
                    'Expiry Date Alerts',
                    'Prescription Intake',
                    'Prescribing Doctors',
                    'Generic Salt Search',
                    'Schedule H Audit Log',
                    'Supplier Batch PO',
                    'Barcode Dispense',
                ],
            ],
            'salon' => [
                'key' => 'salon',
                'title' => 'Salon, Spa & Wellness',
                'icon' => '✂️',
                'tag_text' => 'Vertical Module',
                'tag_class' => 'tag-vertical',
                'display_mode' => 'image',
                'image_url' => 'assets/images/salon-pos-mockup.png',
                'icon_bg' => '#fdf2f8',
                'icon_color' => '#db2777',
                'badge_label' => 'Salon Management',
                'badge_sub' => 'Appointments & Stylists',
                'btn_text' => 'Buy Salon Module →',
                'btn_type' => 'checkout',
                'btn_url' => '',
                'module_slug' => 'salon',
                'features' => [
                    'Booking Calendar',
                    'Stylist Allocation',
                    'Service Durations',
                    'Staff Commissions',
                    'Tip Management',
                    'SMS/WhatsApp Alert',
                    'Client History',
                    'Loyalty Points',
                ],
            ],
            'repair' => [
                'key' => 'repair',
                'title' => 'Repair Service & Workbench',
                'icon' => '🔧',
                'tag_text' => 'Vertical Module',
                'tag_class' => 'tag-vertical',
                'display_mode' => 'image',
                'image_url' => 'assets/images/repair-pos-mockup.png',
                'icon_bg' => '#fffbeb',
                'icon_color' => '#d97706',
                'badge_label' => 'Repair Workbench',
                'badge_sub' => 'Tickets, Parts & Diagnosis',
                'btn_text' => 'Buy Repair Module →',
                'btn_type' => 'checkout',
                'btn_url' => '',
                'module_slug' => 'repairtechnician',
                'features' => [
                    'Intake Job Tickets',
                    'Condition Checklist',
                    'Diagnostic Notes',
                    'Spare Parts Billing',
                    'Labor Fees',
                    'Technician Kanban',
                    'Lifecycle Status',
                    'SMS Pickup Alerts',
                ],
            ],
            'crm' => [
                'key' => 'crm',
                'title' => 'Lead Management CRM',
                'icon' => '📊',
                'tag_text' => '🔥 Top Add-On',
                'tag_class' => 'tag-vertical',
                'display_mode' => 'image',
                'image_url' => 'assets/images/lead-crm-mockup.png',
                'icon_bg' => '#ecfdf5',
                'icon_color' => '#059669',
                'badge_label' => 'Lead CRM Pipeline',
                'badge_sub' => 'Enquiries to Customers',
                'btn_text' => 'Buy Lead Module →',
                'btn_type' => 'checkout',
                'btn_url' => '',
                'module_slug' => 'leadmanagement',
                'features' => [
                    'Visual Kanban Board',
                    'Multi-Channel Leads',
                    'Follow-Up Tasks',
                    'Quotations Linking',
                    '1-Click Deal Won',
                    'Auto Provisioning',
                    'Source Attribution',
                    'Conversion Metrics',
                ],
            ],
        ];
    }
}

if (!function_exists('default_app_builder_config')) {
    function default_app_builder_config(): array
    {
    return [
        'enabled' => true,
        'badge' => 'White-Label Cloud App Builder',
        'title' => 'Build Your Branded Mobile & Desktop Apps Without Local SDKs',
        'subtitle' => 'Compile production-ready Flutter apps directly in the cloud. Customize your app name, logo, color palette, and package ID, then let our automated GitHub Actions cloud pipeline generate Android APK/AAB, Windows Desktop (.exe), and Web PWA binaries.',
        'doc_button_text' => 'Builder Documentation',
        'doc_button_url' => 'https://saas.zoomnearby.com/documentation/index.html#app-builder-guide-and-eligibility',
        'launch_button_text' => 'Launch Builder',
        'launch_button_url' => 'https://saas.zoomnearby.com/app-builder/',
        'eligibility_title' => 'Eligibility Note:',
        'eligibility_text' => 'App Builder compilation quotas are tied to Core SaaS Script licenses and bundles containing Core. Standalone modules or plugins do not have independent build quotas.',
        'eligibility_badge' => 'Core Script: Included',
        'features' => [
            [
                'icon' => '☁️',
                'title' => 'Zero-SDK Cloud Compilation',
                'body' => 'No need to install Flutter, Dart, Android Studio, Gradle, or Visual Studio C++ compilers on your computer. Builds are compiled in isolated GitHub Actions cloud environments.',
                'badge' => 'Zero local configuration',
                'color' => 'blue',
            ],
            [
                'icon' => '🎨',
                'title' => '100% White-Label Branding',
                'body' => 'Customize your app name, custom package identifier (com.yourbrand.pos), primary & accent brand colors, app icon, and splash screen to match your visual identity.',
                'badge' => 'Custom logos & colors',
                'color' => 'purple',
            ],
            [
                'icon' => '📱',
                'title' => 'Multi-Platform Generation',
                'body' => 'Export Android APK binaries and Play Store AAB bundles, native Windows desktop executable packages (.exe/.zip) with offline sync, and deployable Web PWAs.',
                'badge' => 'Android • Windows • Web',
                'color' => 'emerald',
            ],
            [
                'icon' => '📬',
                'title' => 'Build History & Email Alerts',
                'body' => 'Track all your builds in the persistent history dashboard. Download completed artifacts directly, and receive automatic email alerts with secure download links upon build completion.',
                'badge' => 'Instant download & notifications',
                'color' => 'amber',
            ],
        ],
        'bg_mode' => 'theme_matching',
        'bg_color_start' => '#0d1428',
        'bg_color_end' => '#070b1a',
        'border_color' => 'rgba(59, 130, 246, 0.28)',
        'accent_color' => '#3b82f6',
    ];
    }
}

if (!function_exists('default_marketing_bundles')) {
    function default_marketing_bundles(): array
    {
        return [
            [
                'slug' => 'all-in-one',
                'name' => 'All-in-One Enterprise Bundle',
                'description' => 'Includes Main Core SaaS script plus all business vertical modules: Lead Manager, Pharmacy POS, Salon Management, and Repair Technician.',
                'price' => 119.00,
                'currency' => 'USD',
                'regular_sum' => 158.00,
                'savings' => 39.00,
                'included_modules' => ['core', 'leadmanagement', 'pharmacy', 'salon', 'repairtechnician'],
                'custom_features' => [
                    'Full Platform + All 4 Vertical Modules Included',
                    'Lead Management & CRM Module',
                    'Pharmacy POS (Batches & Expiry Control)',
                    'Salon & Spa (Stylists & Appointment Booking)',
                    'Repair Workbench (Tickets & Diagnosis)',
                    'Multi-Tenant SaaS Billing & Domain Mapping',
                    'Priority VIP Business Deployment Support',
                ],
                'badge' => '⚡ BEST VALUE BUNDLE',
                'is_featured' => false,
            ],
        ];
    }
}

/**
 * Fetch complete landing page data and pricing from License Manager API
 * with ultra-fast sync (2-second burst cache) and resilient fallback.
 */
if (!function_exists('get_landing_page_data')) {
    function get_landing_page_data(): array
    {
    // 1. If running in a local test environment or local engine is explicitly enabled
    $currentHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $isLocalTest = in_array($currentHost, ['localhost', '127.0.0.1', '::1', 'test.local'], true)
        || (defined('USE_LOCAL_LICENSE_ENGINE') && USE_LOCAL_LICENSE_ENGINE === true)
        || getenv('USE_LOCAL_LICENSE_ENGINE') === 'true'
        || (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true);

    if ($isLocalTest) {
        $localHelper = dirname(__DIR__, 2) . '/lic/lib/landing_helper.php';
        $localBoot = dirname(__DIR__, 2) . '/lic/lib/bootstrap.php';
        if (file_exists($localHelper) && file_exists($localBoot)) {
            try {
                require_once $localBoot;
                require_once $localHelper;
                if (function_exists('build_landing_api_payload')) {
                    $localData = build_landing_api_payload();
                    if (is_array($localData) && !empty($localData['core_product'])) {
                        if (empty($localData['bundles'])) {
                            $localData['bundles'] = default_marketing_bundles();
                        }
                        if (empty($localData['app_builder'])) {
                            $localData['app_builder'] = default_app_builder_config();
                        }
                        if (empty($localData['business_types_cards'])) {
                            $localData['business_types_cards'] = default_business_types_cards();
                        }
                        if (empty($localData['faqs'])) {
                            $localData['faqs'] = default_marketing_faqs();
                        }
                        return $localData;
                    }
                }
            } catch (Throwable $e) {
                // fallback to remote API
            }
        }
    }

    // Force refresh cache if requested
    $forceRefresh = isset($_GET['refresh']) || isset($_GET['clear_cache']) || isset($_GET['nocache']) || isset($_GET['t']);

    $userSuffix = function_exists('posix_getuid') ? posix_getuid() : (get_current_user() ?: 'u');
    $cacheFile = sys_get_temp_dir() . '/lm_landing_cache_' . md5(LANDING_API_URL . '_' . $userSuffix) . '.json';
    if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 30) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['core_product']) && !empty($cached['bundles'])) {
            if (empty($cached['app_builder'])) {
                $cached['app_builder'] = default_app_builder_config();
            }
            if (empty($cached['business_types_cards'])) {
                $cached['business_types_cards'] = default_business_types_cards();
            }
            if (empty($cached['faqs'])) {
                $cached['faqs'] = default_marketing_faqs();
            }
            return $cached;
        }
    }

    // Connect to Central License Manager API (timeout 6s)
    $raw = false;
    $code = 0;
    if (function_exists('curl_init')) {
        $ch = @curl_init(LANDING_API_URL);
        if ($ch) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) ZoomNearbyLanding/1.2',
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Cache-Control: no-cache'],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }
    }

    // Fallback to file_get_contents with stream context if cURL failed or returned empty
    if ((!$raw || $code !== 200) && ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 6,
                'header' => "Accept: application/json\r\nUser-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) ZoomNearbyLanding/1.2\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $streamRaw = @file_get_contents(LANDING_API_URL, false, $ctx);
        if ($streamRaw) {
            $raw = $streamRaw;
            $code = 200;
        }
    }

    if ($code === 200 && $raw) {
        $data = json_decode($raw, true);
        if (is_array($data) && !empty($data['core_product'])) {
            if (empty($data['app_builder'])) {
                $data['app_builder'] = default_app_builder_config();
            }
            if (empty($data['business_types_cards'])) {
                $data['business_types_cards'] = default_business_types_cards();
            }
            if (empty($data['faqs'])) {
                $data['faqs'] = default_marketing_faqs();
            }
            @file_put_contents($cacheFile, json_encode($data));
            return $data;
        }
    }

    // If cache exists (even expired), return it rather than failing
    if (file_exists($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['core_product'])) {
            if (empty($cached['app_builder'])) {
                $cached['app_builder'] = default_app_builder_config();
            }
            if (empty($cached['business_types_cards'])) {
                $cached['business_types_cards'] = default_business_types_cards();
            }
            if (empty($cached['faqs'])) {
                $cached['faqs'] = default_marketing_faqs();
            }
            return $cached;
        }
    }

    // Resilient offline fallback in case license server is temporarily down
    return [
        'status' => true,
        'branding' => [
            'site_name' => 'Zoom POS & Market',
            'site_tagline' => 'Smarter Business. Greater Control.',
            'support_email' => 'support@zoomnearby.com',
            'currency_code' => 'USD',
            'currency_symbol' => '$',
        ],
        'hero' => [
            'badge' => 'Complete Business Management Platform',
            'title' => 'Run Your Entire Business<br>From <span>One Powerful POS</span>',
            'subtitle' => 'Retail, Restaurant & Service — sales, inventory, customers, reports and more in one self-hosted platform.',
            'cta_primary' => 'Buy Now',
            'cta_secondary' => 'Live Demo',
            'cta_verify' => 'Verify License',
        ],
        'urls' => [
            'demo_admin' => 'https://saas.zoomnearby.com/login',
            'demo_store' => 'https://saas.zoomnearby.com/store/login',
            'demo_flutter_web' => 'https://saas.zoomnearby.com/pos-web/',
            'demo_flutter_windows' => 'https://saas.zoomnearby.com/zoom-sales-crm-software-1.0.2.exe',
            'demo_flutter_android' => 'https://saas.zoomnearby.com/zoom-pos-v1.0.2.apk',
            'documentation' => 'https://saas.zoomnearby.com/documentation',
            'checkout' => CHECKOUT_URL,
            'verify' => VERIFY_API_URL,
        ],
        'demo_links' => [
            'flutter_web' => [
                'key' => 'flutter_web',
                'title' => 'Flutter Web POS',
                'desc' => 'Instant browser-based POS terminal with touch UI, barcode scanning & receipt printing.',
                'url' => 'https://saas.zoomnearby.com/pos-web/',
                'icon' => '🌐',
                'badge' => 'Flutter Web',
                'btn_text' => 'Launch Web POS ↗',
                'type' => 'web',
            ],
            'flutter_windows' => [
                'key' => 'flutter_windows',
                'title' => 'Flutter Windows Desktop App',
                'desc' => 'Native 64-bit Windows desktop installer with ESC/POS thermal receipt printer integration.',
                'url' => 'https://saas.zoomnearby.com/zoom-sales-crm-software-1.0.2.exe',
                'icon' => '🪟',
                'badge' => 'Windows .EXE',
                'btn_text' => 'Download Windows App ⬇',
                'type' => 'download',
            ],
            'flutter_android' => [
                'key' => 'flutter_android',
                'title' => 'Flutter Android POS App',
                'desc' => 'Native Android APK build optimized for handheld wireless terminals, smartphones, and tablets.',
                'url' => 'https://saas.zoomnearby.com/zoom-pos-v1.0.2.apk',
                'icon' => '📱',
                'badge' => 'Android .APK',
                'btn_text' => 'Download Android APK ⬇',
                'type' => 'download',
            ],
            'superadmin' => [
                'key' => 'superadmin',
                'title' => 'SuperAdmin SaaS Portal',
                'desc' => 'Manage SaaS subscription packages, tenant stores, payment gateways, and system settings.',
                'url' => 'https://saas.zoomnearby.com/login',
                'icon' => '👑',
                'badge' => 'SaaS Portal',
                'btn_text' => 'Open SuperAdmin Demo ↗',
                'type' => 'web',
            ],
            'store' => [
                'key' => 'store',
                'title' => 'Store & Cashier Backoffice',
                'desc' => 'Staff and cashier portal for catalog, orders, table floorplans, and billing settlement.',
                'url' => 'https://saas.zoomnearby.com/store/login',
                'icon' => '🏪',
                'badge' => 'Store Web',
                'btn_text' => 'Open Store Demo ↗',
                'type' => 'web',
            ],
        ],
        'other_demo_links' => [],
        'metrics' => [
            ['val' => '$49.00', 'label' => 'One-Time Core Script Price'],
            ['val' => '100%', 'label' => 'Self-Hosted Source Code'],
            ['val' => 'Instant', 'label' => 'License Key Email Delivery'],
            ['val' => 'Unlimited', 'label' => 'Stores, Cashiers & Registers'],
        ],
        'discounts' => [
            'tier_1' => 10,
            'tier_2' => 15,
            'tier_3' => 20,
        ],
        'core_product' => [
            'slug' => 'core',
            'name' => 'Core SaaS Platform (Retail, Restaurant & Café)',
            'description' => 'Multi-tenant POS & Business SaaS platform core platform engine with built-in Retail POS, Restaurant (Dine-in, Tables & KOT), and Café / Quick-Service modules.',
            'price' => 49.00,
            'currency' => 'USD',
            'custom_features' => [
                'Retail POS Module (Barcodes, variants & stock)',
                'Restaurant POS Module (Tables, KOT & KDS)',
                'Café & Quick-Service Module (Fast checkout)',
                'Multi-Store Warehousing & Stock Transfers',
                'Thermal Receipts (80/58mm) & Barcode Labels',
                'Unlimited Stores, Registers & Cashiers',
                '100% Unencrypted Full PHP Source Code',
            ],
        ],
        'modules' => [
            ['slug' => 'leadmanagement', 'name' => 'Lead Management System', 'description' => 'Lead pipeline, follow-ups, activity tracking, and customer conversion analytics.', 'price' => 29.00, 'currency' => 'USD', 'badge' => '🔥 Most Popular Add-On'],
            ['slug' => 'pharmacy', 'name' => 'Pharmacy POS for SaaS', 'description' => 'Drug batch tracking, expiry date monitoring, and prescription intake workflow.', 'price' => 25.00, 'currency' => 'USD', 'badge' => 'Specialized Vertical'],
            ['slug' => 'salon', 'name' => 'Salon Management System', 'description' => 'Stylist bookings, appointments calendar, chair allocation, and commissions.', 'price' => 25.00, 'currency' => 'USD', 'badge' => 'Specialized Vertical'],
            ['slug' => 'repairtechnician', 'name' => 'Repair Service Provider', 'description' => 'Device intake tickets, diagnostic checklists, parts and labor billing.', 'price' => 25.00, 'currency' => 'USD', 'badge' => 'Specialized Vertical'],
        ],
        'bundles' => [
            [
                'slug' => 'core-lead',
                'name' => 'Main Script + Lead Manager Bundle',
                'description' => 'Main SaaS Core Script bundled with the Lead Management & CRM module.',
                'price' => 69.00,
                'currency' => 'USD',
                'regular_sum' => 78.00,
                'savings' => 9.00,
                'included_modules' => ['core', 'leadmanagement'],
                'custom_features' => [
                    'Everything in Core (Retail + Restaurant + Café)',
                    'Lead Management CRM Module Included',
                    'Visual Kanban Sales Pipeline & Follow-ups',
                    'Quotation Generator & Auto Customer Sync',
                    'Two Separate License Keys Emailed Instantly',
                    'Priority Updates & Comprehensive Setup Guide',
                    'Zero Monthly or Annual Platform Fees',
                ],
                'badge' => '🔥 MOST POPULAR BUNDLE',
                'is_featured' => true,
            ],
            [
                'slug' => 'all-in-one',
                'name' => 'All-in-One Enterprise Bundle',
                'description' => 'Includes Main Core SaaS script plus all business vertical modules: Lead Manager, Pharmacy POS, Salon Management, and Repair Technician.',
                'price' => 119.00,
                'currency' => 'USD',
                'regular_sum' => 158.00,
                'savings' => 39.00,
                'included_modules' => ['core', 'leadmanagement', 'pharmacy', 'salon', 'repairtechnician'],
                'custom_features' => [
                    'Full Platform + All 4 Vertical Modules Included',
                    'Lead Management & CRM Module',
                    'Pharmacy POS (Batches & Expiry Control)',
                    'Salon & Spa (Stylists & Appointment Booking)',
                    'Repair Workbench (Tickets & Diagnosis)',
                    'Multi-Tenant SaaS Billing & Domain Mapping',
                    'Priority VIP Business Deployment Support',
                ],
                'badge' => '⚡ BEST VALUE BUNDLE',
                'is_featured' => false,
            ],
        ],
        'features' => [],
        'faqs' => default_marketing_faqs(),
        'show_top_nav' => true,
        'header_settings' => [
            'show_top_nav' => true,
            'sticky_top_nav' => true,
            'nav_show_brand' => true,
            'nav_show_links' => true,
            'nav_show_language' => true,
            'nav_show_themes' => true,
            'nav_show_dark_toggle' => true,
            'nav_show_demo_btn' => true,
            'nav_show_buy_btn' => true,
        ],
        'app_builder' => [
            'enabled' => true,
            'badge' => 'White-Label Cloud App Builder',
            'title' => 'Build Your Branded Mobile & Desktop Apps Without Local SDKs',
            'subtitle' => 'Compile production-ready Flutter apps directly in the cloud. Customize your app name, logo, color palette, and package ID, then let our automated GitHub Actions cloud pipeline generate Android APK/AAB, Windows Desktop (.exe), and Web PWA binaries.',
            'doc_button_text' => 'Builder Documentation',
            'doc_button_url' => 'https://saas.zoomnearby.com/documentation/index.html#app-builder-guide-and-eligibility',
            'launch_button_text' => 'Launch Builder',
            'launch_button_url' => 'https://saas.zoomnearby.com/app-builder/',
            'eligibility_title' => 'Eligibility Note:',
            'eligibility_text' => 'App Builder compilation quotas are tied to Core SaaS Script licenses and bundles containing Core. Standalone modules or plugins do not have independent build quotas.',
            'eligibility_badge' => 'Core Script: Included',
            'features' => [
                [
                    'icon' => '☁️',
                    'title' => 'Zero-SDK Cloud Compilation',
                    'body' => 'No need to install Flutter, Dart, Android Studio, Gradle, or Visual Studio C++ compilers on your computer. Builds are compiled in isolated GitHub Actions cloud environments.',
                    'badge' => 'Zero local configuration',
                    'color' => 'blue',
                ],
                [
                    'icon' => '🎨',
                    'title' => '100% White-Label Branding',
                    'body' => 'Customize your app name, custom package identifier (com.yourbrand.pos), primary & accent brand colors, app icon, and splash screen to match your visual identity.',
                    'badge' => 'Custom logos & colors',
                    'color' => 'purple',
                ],
                [
                    'icon' => '📱',
                    'title' => 'Multi-Platform Generation',
                    'body' => 'Export Android APK binaries and Play Store AAB bundles, native Windows desktop executable packages (.exe/.zip) with offline sync, and deployable Web PWAs.',
                    'badge' => 'Android • Windows • Web',
                    'color' => 'emerald',
                ],
                [
                    'icon' => '📬',
                    'title' => 'Build History & Email Alerts',
                    'body' => 'Track all your builds in the persistent history dashboard. Download completed artifacts directly, and receive automatic email alerts with secure download links upon build completion.',
                    'badge' => 'Instant download & notifications',
                    'color' => 'amber',
                ],
            ],
            'bg_mode' => 'theme_matching',
            'bg_color_start' => '#0d1428',
            'bg_color_end' => '#070b1a',
            'border_color' => 'rgba(59, 130, 246, 0.28)',
            'accent_color' => '#3b82f6',
        ],
        'business_types_cards' => default_business_types_cards(),
    ];
    }
}

if (!function_exists('e')) {
    function e(?string $str): string
    {
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('e_attr')) {
    function e_attr(?string $str): string
    {
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_card_feature')) {
    function format_card_feature(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        // Convert markdown bold **word** to <strong>word</strong>
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong class="feature-title">$1</strong>', $text);

        // If string already contains HTML tags, sanitize with permitted inline tags
        if (str_contains($text, '<')) {
            return strip_tags($text, '<strong><b><em><span>');
        }

        // Escape safely
        $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // If there is a category with colon, e.g. "Retail POS: Barcode scanning...",
        // emphasize the category name before the colon
        if (preg_match('/^([^:(]{2,36}:)\s*(.*)$/', $safe, $matches)) {
            return '<strong class="feature-title">' . trim($matches[1]) . '</strong> <span class="feature-description">' . trim($matches[2]) . '</span>';
        }

        // If there are parentheses, e.g. "Pharmacy POS (Batches & Expiry Control)",
        // emphasize the first part before the parenthesis
        if (preg_match('/^([^(]+)(\s*\(.*\))$/', $safe, $matches)) {
            return '<strong class="feature-title">' . trim($matches[1]) . '</strong> <span class="feature-description">' . trim($matches[2]) . '</span>';
        }

        return '<span class="feature-description">' . $safe . '</span>';
    }
}

if (!function_exists('render_pricing_feature_item')) {
    function render_pricing_feature_item(?string $feat, bool $isDark = false): string
    {
    $feat = trim((string) $feat);
    if ($feat === '') {
        return '';
    }

    // Skip stray pricing header echoes that got into features
    if (preg_match('/^(\$?\d+|enterprise|business|core|one-time payment|🚀 get enterprise)/i', $feat)) {
        return '';
    }

    // Support markdown header prefixes like ### or ##
    $cleanHeader = preg_replace('/^#+\s*/', '', $feat);
    $headerCompare = preg_replace('/^[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}✔✓✘✗🔒•\-\*\s]+/u', '', $cleanHeader);
    $lower = strtolower(trim($headerCompare, " :-\t"));

    // Check if item is a section header (e.g. "Complete Core POS Platform", "Included Modules", "Not Included")
    $isHeader = ($cleanHeader !== $feat && $cleanHeader !== '' && in_array($lower, [
            'complete core pos platform', 'core pos platform', 'core platform', 'platform features',
            'included modules', 'modules included', 'included add-on modules', 'add-on modules', 'addon modules',
            'not included', 'modules not included', 'not included modules', 'excluded', 'excluded modules',
            'what\'s included', 'what is included', 'core features', 'features', 'modules'
        ], true))
        || in_array($lower, [
            'complete core pos platform', 'core pos platform', 'core platform', 'platform features',
            'included modules', 'modules included', 'included add-on modules', 'add-on modules', 'addon modules',
            'not included', 'modules not included', 'not included modules', 'excluded', 'excluded modules',
            'what\'s included', 'what is included', 'core features', 'features', 'modules',
            'not included in core'
        ], true)
        || preg_match('/^(included|not included|modules|features|capabilities|specifications)(\s+(modules|features|included|list))?:?$/i', trim($feat));

    if ($isHeader) {
        $displayTitle = trim($cleanHeader, " \t\n\r\0\x0B*");
        $isNegative = str_contains($lower, 'not included') || str_contains($lower, 'excluded');
        if ($isDark) {
            $color = $isNegative ? '#f87171' : '#e2e8f0';
            $border = $isNegative ? 'border-top:1px dashed #ef4444' : 'border-top:1px solid #334155';
        } else {
            $color = $isNegative ? '#dc2626' : '#1e293b';
            $border = $isNegative ? 'border-top:1px dashed #fca5a5' : 'border-top:1px solid #e2e8f0';
        }
        return '<li class="feature-section-header' . ($isNegative ? ' is-negative' : '') . '" style="font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:' . $color . ';margin-top:16px;margin-bottom:8px;list-style:none;padding-left:0;' . $border . ';padding-top:10px;display:block">'
            . htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8')
            . '</li>';
    }

    // Check if item is an excluded / not-included feature (starts with ✘, ✗, or 🔒)
    if (preg_match('/^[✘✗🔒]/u', $feat)) {
        $cleanText = trim(preg_replace('/^[✘✗🔒\s]+/u', '', $feat));
        $icon = preg_match('/^🔒/u', $feat) ? '🔒' : '✘';
        $textColor = $isDark ? '#94a3b8' : '#64748b';
        return '<li class="feature-not-included" style="color:' . $textColor . ';opacity:0.9">'
            . '<span class="feature-icon" style="color:#ef4444;font-size:12px">' . $icon . '</span> '
            . '<div class="feature-content">' . format_card_feature($cleanText) . '</div>'
            . '</li>';
    }

    // Normal included feature (strip any checkmark prefix completely: ✔, ✓, etc.)
    $cleanText = trim(preg_replace('/^[✔✓\x{2713}\x{2714}\s•\-\*]+/u', '', $feat));

    // Extract leading icon/bullet if present (e.g. ✦, ★, ⚡, etc.)
    $icon = '✦';
    if (preg_match('/^([\x{1F300}-\x{1FAFF}\x{2600}-\x{2704}\x{2706}-\x{2712}\x{2715}-\x{27BF}✦✧★☆•\-]\s*)/u', $cleanText, $mIcon)) {
        $icon = trim($mIcon[1]);
        $cleanText = trim(mb_substr($cleanText, mb_strlen($mIcon[0])));
    }

    // Guard: replace any stray checkmark with standard ✦
    if ($icon === '✔' || $icon === '✓' || $icon === '') {
        $icon = '✦';
    }

    $iconHtml = '<span class="feature-icon">' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '</span>';
    return '<li>' . $iconHtml . '<div class="feature-content">' . format_card_feature($cleanText) . '</div></li>';
}
}




