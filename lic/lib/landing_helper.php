<?php

require_once __DIR__ . '/helpers.php';

/**
 * Landing Page Configuration & Data Engine for License Manager
 * 
 * Provides centralized settings for marketing copy, hero sections, demo links,
 * features, discount tiers, and FAQs. Combined with products and bundles tables
 * to serve a unified API to the marketing landing page on ANY domain.
 */

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
                    'btn_sec_bg' => 'rgba(99, 102, 241, 0.16)',
                    'btn_sec_border' => 'rgba(165, 180, 252, 0.35)',
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

function default_landing_config(): array
{
    return [
        'brand_name' => 'Zoom POS & Market',
        'brand_tagline' => 'Smarter Business. Greater Control.',
        'support_email' => 'support@zoomnearby.com',
        'currency_code' => 'USD',
        'currency_symbol' => '$',
        'default_theme_mode' => 'dark',
        'active_color_preset' => 'midnight_obsidian',
        'section_colors' => default_section_colors(),

        // Top Navigation Menu Settings
        'show_top_nav' => true,
        'sticky_top_nav' => true,
        'nav_show_brand' => true,
        'nav_show_links' => true,
        'nav_show_language' => true,
        'nav_show_themes' => true,
        'nav_show_dark_toggle' => true,
        'nav_show_demo_btn' => true,
        'nav_show_buy_btn' => true,
        
        // Hero Section
        'hero_badge' => 'Complete Business Management Platform',
        'hero_title' => 'Run Your Entire Business<br>From <span>One Powerful POS</span>',
        'hero_subtitle' => 'Retail, Restaurant & Service — sales, inventory, customers, reports and more in one self-hosted platform.',
        'cta_primary' => 'Buy Now',
        'cta_secondary' => 'Live Demo',
        'cta_verify' => 'Verify License',

        // External / Demo Links & Multi-Platform Clients
        'demo_admin_url' => 'https://saas.zoomnearby.com/login',
        'demo_admin_title' => 'SuperAdmin SaaS Portal',
        'demo_admin_desc' => 'Manage SaaS subscription packages, tenant stores, payment gateways, and system settings.',

        'demo_store_url' => 'https://saas.zoomnearby.com/store/login',
        'demo_store_title' => 'Store & Cashier Backoffice',
        'demo_store_desc' => 'Staff and cashier portal for catalog, orders, table floorplans, and billing settlement.',

        'demo_flutter_web_url' => 'https://saas.zoomnearby.com/pos-web/',
        'demo_flutter_web_title' => 'Flutter Web POS',
        'demo_flutter_web_desc' => 'Instant browser-based POS terminal with touch UI, barcode scanning & receipt printing.',

        'demo_flutter_windows_url' => 'https://saas.zoomnearby.com/zoom-sales-crm-software-1.0.2.exe',
        'demo_flutter_windows_title' => 'Flutter Windows Desktop App',
        'demo_flutter_windows_desc' => 'Native 64-bit Windows desktop installer with ESC/POS thermal receipt printer integration.',

        'demo_flutter_android_url' => 'https://saas.zoomnearby.com/zoom-pos-v1.0.2.apk',
        'demo_flutter_android_title' => 'Flutter Android POS App',
        'demo_flutter_android_desc' => 'Native Android APK build optimized for handheld wireless terminals, smartphones, and tablets.',

        'documentation_url' => 'https://saas.zoomnearby.com/documentation',
        'documentation_title' => 'Documentation & Setup Guide',
        'documentation_desc' => 'Comprehensive developer and administrator installation guide.',

        // Custom / Other Live Demo Links (User-managed repeater)
        'demo_other_links' => [],

        // Metrics Strip
        'metric_1_val' => '$49.00',
        'metric_1_label' => 'One-Time Core Script Price',
        'metric_2_val' => '100%',
        'metric_2_label' => 'Self-Hosted Source Code',
        'metric_3_val' => 'Instant',
        'metric_3_label' => 'License Key Email Delivery',
        'metric_4_val' => 'Unlimited',
        'metric_4_label' => 'Stores, Cashiers & Registers',

        // Interactive Bundle Builder Discounts
        'discount_tier_1' => 10, // 1 add-on module added: 10% off
        'discount_tier_2' => 15, // 2 add-on modules added: 15% off
        'discount_tier_3' => 20, // 3+ add-on modules added: 20% off

        // Core 6 Features
        'features' => [
            [
                'icon' => '🛒',
                'title' => 'Retail POS Engine',
                'desc' => 'High-speed barcode scanner checkout, variant inventory, customer credit accounts, return handling, and price label printing.',
            ],
            [
                'icon' => '🍽️',
                'title' => 'Restaurant & Dine-In (Tables + KOT)',
                'desc' => 'Visual floor & table layout management, Kitchen Order Tickets (KOT) printing/display, waiter ordering, and split bill checkout.',
            ],
            [
                'icon' => '☕',
                'title' => 'Café & Quick-Service Counter',
                'desc' => 'Rapid touch-optimized ordering, modifiers/addons, kitchen queue tokens, and swift card/cash cashier settlement.',
            ],
            [
                'icon' => '🏬',
                'title' => 'Multi-Store & Warehousing',
                'desc' => 'Manage multiple stores and stock warehouses from one screen. Inter-branch stock transfers and low inventory warnings.',
            ],
            [
                'icon' => '🖨️',
                'title' => 'Thermal Receipt & Barcode Printing',
                'desc' => 'Direct ESC/POS 80mm & 58mm thermal printer support, PDF invoices, customized receipts, and automatic barcode sticker generator.',
            ],
            [
                'icon' => '🌐',
                'title' => 'Multi-Tenant SaaS Architecture',
                'desc' => 'Create pricing subscription packages, allow business tenants to register, manage their billing, and connect custom domains.',
            ],
        ],

        // FAQ Items
        'faqs' => default_marketing_faqs(),

        // White-Label Cloud App Builder Showcase Section (Rendered on /marketing)
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
            'bg_mode' => 'theme_matching', // 'theme_matching', 'dark', 'light', 'custom'
            'bg_color_start' => '#0d1428',
            'bg_color_end' => '#070b1a',
            'border_color' => 'rgba(59, 130, 246, 0.28)',
            'accent_color' => '#3b82f6',
        ],
        'business_types_cards' => default_business_types_cards(),
    ];
}

function get_landing_config(): array
{
    $raw = lic_setting('landing_page_config', '');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_replace_recursive(default_landing_config(), $decoded);
        }
    }
    return default_landing_config();
}

function clear_landing_cache(): void
{
    $pattern = sys_get_temp_dir() . '/lm_landing_cache_*';
    $files = glob($pattern);
    if (is_array($files)) {
        foreach ($files as $f) {
            @unlink($f);
        }
    }
}

function save_landing_config(array $config): void
{
    lic_set_setting('landing_page_config', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    clear_landing_cache();
}


/**
 * Builds unified payload for the public /api/landing.php endpoint.
 */
function build_landing_api_payload(): array
{
    $pdo = db();
    $config = get_landing_config();

    // 1. Fetch active products
    $productRows = [];
    try {
        $productRows = $pdo->query('SELECT slug, name, description, price, currency, custom_features, is_active FROM products WHERE is_active = 1 ORDER BY (slug = "core") DESC, name ASC')->fetchAll();
    } catch (Throwable $e) {
        try {
            $productRows = $pdo->query('SELECT slug, name, description, price, currency, is_active FROM products WHERE is_active = 1 ORDER BY (slug = "core") DESC, name ASC')->fetchAll();
        } catch (Throwable $e2) {
            $productRows = [];
        }
    }

    $coreProduct = null;
    $modules = [];
    foreach ($productRows as $p) {
        if (!isset($p['slug'])) {
            continue;
        }
        $price = (float) $p['price'];
        $pCustomFeat = json_decode($p['custom_features'] ?? '[]', true) ?: [];
        if ($p['slug'] === 'core') {
            $coreProduct = [
                'slug' => 'core',
                'name' => $p['name'],
                'description' => $p['description'],
                'price' => $price,
                'currency' => $p['currency'],
                'custom_features' => $pCustomFeat,
            ];
        } else {
            $badge = ($p['slug'] === 'leadmanagement') ? '🔥 Most Popular Add-On' : 'Specialized Vertical';
            $modules[$p['slug']] = [
                'slug' => $p['slug'],
                'name' => $p['name'],
                'description' => $p['description'],
                'price' => $price,
                'currency' => $p['currency'],
                'badge' => $badge,
                'custom_features' => $pCustomFeat,
            ];
        }
    }

    if (!$coreProduct) {
        $coreProduct = [
            'slug' => 'core',
            'name' => 'Core SaaS Platform',
            'description' => 'Complete multi-tenant POS platform with unlimited stores, cash registers, and inventory.',
            'price' => 49.00,
            'currency' => lic_setting('currency', 'USD'),
            'custom_features' => [],
        ];
    }

    if (empty($modules)) {
        $modules = [
            'leadmanagement' => [
                'slug' => 'leadmanagement',
                'name' => 'Lead Management System',
                'description' => 'Lead pipeline, follow-ups, activity tracking, and customer conversion analytics.',
                'price' => 29.00,
                'currency' => lic_setting('currency', 'USD'),
                'badge' => '🔥 Most Popular Add-On',
                'custom_features' => [],
            ],
            'pharmacy' => [
                'slug' => 'pharmacy',
                'name' => 'Pharmacy POS for SaaS',
                'description' => 'Drug batch tracking, expiry date monitoring, and prescription intake workflow.',
                'price' => 25.00,
                'currency' => lic_setting('currency', 'USD'),
                'badge' => 'Specialized Vertical',
                'custom_features' => [],
            ],
            'salon' => [
                'slug' => 'salon',
                'name' => 'Salon Management System',
                'description' => 'Stylist bookings, appointments calendar, chair allocation, and commissions.',
                'price' => 25.00,
                'currency' => lic_setting('currency', 'USD'),
                'badge' => 'Specialized Vertical',
                'custom_features' => [],
            ],
            'repairtechnician' => [
                'slug' => 'repairtechnician',
                'name' => 'Repair Service Provider',
                'description' => 'Device intake tickets, diagnostic checklists, parts and labor billing.',
                'price' => 25.00,
                'currency' => lic_setting('currency', 'USD'),
                'badge' => 'Specialized Vertical',
                'custom_features' => [],
            ],
        ];
    }

    // 2. Fetch active bundles
    $bundleRows = [];
    try {
        $bundleRows = $pdo->query('SELECT slug, name, description, price, currency, included_modules, custom_features FROM bundles WHERE is_active = 1 ORDER BY price ASC')->fetchAll();
    } catch (Throwable $e) {
        try {
            $bundleRows = $pdo->query('SELECT slug, name, description, price, currency, included_modules FROM bundles WHERE is_active = 1 ORDER BY price ASC')->fetchAll();
        } catch (Throwable $e2) {
            $bundleRows = [];
        }
    }
    $bundles = [];
    foreach ($bundleRows as $b) {
        $mods = json_decode($b['included_modules'] ?? '[]', true) ?: [];
        $regSum = 0.0;
        foreach ($mods as $mSlug) {
            if ($mSlug === 'core') {
                $regSum += (float) $coreProduct['price'];
            } elseif (isset($modules[$mSlug])) {
                $regSum += (float) $modules[$mSlug]['price'];
            }
        }
        $bPrice = (float) $b['price'];
        $savings = max(0.0, $regSum - $bPrice);

        // Custom features or auto-generated smart defaults
        $customFeat = json_decode($b['custom_features'] ?? '[]', true) ?: [];
        if (empty($customFeat)) {
            $customFeat[] = 'Everything in Core (Retail + Restaurant + Café)';
            foreach ($mods as $mSlug) {
                if ($mSlug !== 'core' && isset($modules[$mSlug])) {
                    $customFeat[] = $modules[$mSlug]['name'] . ' Included';
                }
            }
            $customFeat[] = 'Multi-Tenant SaaS Billing & Domain Mapping';
            $customFeat[] = 'Separate License Keys Emailed Instantly';
            $customFeat[] = 'Zero Monthly or Annual Platform Fees';
        }

        $bundles[$b['slug']] = [
            'slug' => $b['slug'],
            'name' => $b['name'],
            'description' => $b['description'],
            'price' => $bPrice,
            'currency' => $b['currency'],
            'regular_sum' => $regSum,
            'savings' => $savings,
            'included_modules' => $mods,
            'custom_features' => $customFeat,
            'badge' => ($b['slug'] === 'core-lead') ? '🔥 MOST POPULAR BUNDLE' : (($b['slug'] === 'all-in-one') ? '⚡ BEST VALUE BUNDLE' : 'SPECIAL BUNDLE'),
            'is_featured' => ($b['slug'] === 'core-lead'),
        ];
    }

    // Resolve server base URL
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $cleanPath = preg_replace('#/(api|admin).*$#', '', $scriptDir);
    $baseLicUrl = $scheme . '://' . $host . ($cleanPath !== '' && $cleanPath !== '/' && $cleanPath !== '.' ? '/' . trim($cleanPath, '/') : '');
    $baseLicUrl = rtrim($baseLicUrl, '/');

    // Dynamic metrics (sync metric_1 with actual core price if left default)
    $metrics = [
        ['val' => '$' . number_format($coreProduct['price'], 2), 'label' => $config['metric_1_label'] ?? 'One-Time Core Script Price'],
        ['val' => $config['metric_2_val'] ?? '100%', 'label' => $config['metric_2_label'] ?? 'Self-Hosted Source Code'],
        ['val' => $config['metric_3_val'] ?? 'Instant', 'label' => $config['metric_3_label'] ?? 'License Key Email Delivery'],
        ['val' => $config['metric_4_val'] ?? 'Unlimited', 'label' => $config['metric_4_label'] ?? 'Stores, Cashiers & Registers'],
    ];

    // Multi-Platform Demo Links
    $demoLinks = [
        'flutter_web' => [
            'key' => 'flutter_web',
            'title' => $config['demo_flutter_web_title'] ?? 'Flutter Web POS',
            'desc' => $config['demo_flutter_web_desc'] ?? 'Instant browser-based POS terminal with touch UI, barcode scanning & receipt printing.',
            'url' => $config['demo_flutter_web_url'] ?? '',
            'icon' => '🌐',
            'badge' => 'Flutter Web',
            'btn_text' => 'Launch Web POS ↗',
            'type' => 'web',
        ],
        'flutter_windows' => [
            'key' => 'flutter_windows',
            'title' => $config['demo_flutter_windows_title'] ?? 'Flutter Windows Desktop App',
            'desc' => $config['demo_flutter_windows_desc'] ?? 'Native 64-bit Windows desktop installer with ESC/POS thermal receipt printer integration.',
            'url' => $config['demo_flutter_windows_url'] ?? '',
            'icon' => '🪟',
            'badge' => 'Windows .EXE',
            'btn_text' => 'Download Windows App ⬇',
            'type' => 'download',
        ],
        'flutter_android' => [
            'key' => 'flutter_android',
            'title' => $config['demo_flutter_android_title'] ?? 'Flutter Android POS App',
            'desc' => $config['demo_flutter_android_desc'] ?? 'Native Android APK build optimized for handheld wireless terminals, smartphones, and tablets.',
            'url' => $config['demo_flutter_android_url'] ?? '',
            'icon' => '📱',
            'badge' => 'Android .APK',
            'btn_text' => 'Download Android APK ⬇',
            'type' => 'download',
        ],
        'superadmin' => [
            'key' => 'superadmin',
            'title' => $config['demo_admin_title'] ?? 'SuperAdmin SaaS Portal',
            'desc' => $config['demo_admin_desc'] ?? 'Manage SaaS subscription packages, tenant stores, payment gateways, and system settings.',
            'url' => $config['demo_admin_url'] ?? '',
            'icon' => '👑',
            'badge' => 'SaaS Portal',
            'btn_text' => 'Open SuperAdmin Demo ↗',
            'type' => 'web',
        ],
        'store' => [
            'key' => 'store',
            'title' => $config['demo_store_title'] ?? 'Store & Cashier Backoffice',
            'desc' => $config['demo_store_desc'] ?? 'Staff and cashier portal for catalog, orders, table floorplans, and billing settlement.',
            'url' => $config['demo_store_url'] ?? '',
            'icon' => '🏪',
            'badge' => 'Store Web',
            'btn_text' => 'Open Store Demo ↗',
            'type' => 'web',
        ],
    ];

    $otherLinks = [];
    if (!empty($config['demo_other_links']) && is_array($config['demo_other_links'])) {
        foreach ($config['demo_other_links'] as $idx => $ol) {
            $u = trim($ol['url'] ?? '');
            $t = trim($ol['title'] ?? '');
            if ($u !== '' && $t !== '') {
                $otherLinks[] = [
                    'key' => 'custom_' . ($idx + 1),
                    'title' => $t,
                    'desc' => trim($ol['desc'] ?? ''),
                    'url' => $u,
                    'icon' => trim($ol['icon'] ?? '🔗'),
                    'badge' => trim($ol['badge'] ?? 'Custom Link'),
                    'btn_text' => trim($ol['btn_text'] ?? 'Open Link ↗'),
                    'type' => 'custom',
                ];
            }
        }
    }

    return [
        'status' => true,
        'server_timestamp' => date('c'),
        'branding' => [
            'site_name' => $config['brand_name'],
            'site_tagline' => $config['brand_tagline'],
            'support_email' => $config['support_email'],
            'currency_code' => $config['currency_code'],
            'currency_symbol' => $config['currency_symbol'],
        ],
        'hero' => [
            'badge' => $config['hero_badge'],
            'title' => $config['hero_title'],
            'subtitle' => $config['hero_subtitle'],
            'cta_primary' => $config['cta_primary'],
            'cta_secondary' => $config['cta_secondary'],
            'cta_verify' => $config['cta_verify'],
        ],
        'urls' => [
            'demo_admin' => $config['demo_admin_url'],
            'demo_store' => $config['demo_store_url'],
            'demo_flutter_web' => $config['demo_flutter_web_url'] ?? '',
            'demo_flutter_windows' => $config['demo_flutter_windows_url'] ?? '',
            'demo_flutter_android' => $config['demo_flutter_android_url'] ?? '',
            'documentation' => $config['documentation_url'],
            'checkout' => $baseLicUrl . '/buy.php',
            'verify' => $baseLicUrl . '/api/verify.php',
        ],
        'demo_links' => $demoLinks,
        'other_demo_links' => $otherLinks,
        'metrics' => $metrics,
        'discounts' => [
            'tier_1' => (int) ($config['discount_tier_1'] ?? 10),
            'tier_2' => (int) ($config['discount_tier_2'] ?? 15),
            'tier_3' => (int) ($config['discount_tier_3'] ?? 20),
        ],
        'core_product' => $coreProduct,
        'modules' => array_values($modules),
        'bundles' => array_values($bundles),
        'features' => $config['features'],
        'faqs' => !empty($config['faqs']) ? $config['faqs'] : default_marketing_faqs(),
        'show_top_nav' => array_key_exists('show_top_nav', $config) ? (bool) $config['show_top_nav'] : true,
        'header_settings' => [
            'show_top_nav' => array_key_exists('show_top_nav', $config) ? (bool) $config['show_top_nav'] : true,
            'sticky_top_nav' => array_key_exists('sticky_top_nav', $config) ? (bool) $config['sticky_top_nav'] : true,
            'nav_show_brand' => array_key_exists('nav_show_brand', $config) ? (bool) $config['nav_show_brand'] : true,
            'nav_show_links' => array_key_exists('nav_show_links', $config) ? (bool) $config['nav_show_links'] : true,
            'nav_show_language' => array_key_exists('nav_show_language', $config) ? (bool) $config['nav_show_language'] : true,
            'nav_show_themes' => array_key_exists('nav_show_themes', $config) ? (bool) $config['nav_show_themes'] : true,
            'nav_show_dark_toggle' => array_key_exists('nav_show_dark_toggle', $config) ? (bool) $config['nav_show_dark_toggle'] : true,
            'nav_show_demo_btn' => array_key_exists('nav_show_demo_btn', $config) ? (bool) $config['nav_show_demo_btn'] : true,
            'nav_show_buy_btn' => array_key_exists('nav_show_buy_btn', $config) ? (bool) $config['nav_show_buy_btn'] : true,
        ],
        'app_builder' => (function() use ($config) {
            $def = default_landing_config()['app_builder'];
            $b = $config['app_builder'] ?? [];
            $feats = [];
            if (!empty($b['features']) && is_array($b['features'])) {
                foreach ($b['features'] as $f) {
                    if (is_array($f) && (!empty($f['title']) || !empty($f['body']))) {
                        $feats[] = [
                            'icon' => trim((string) ($f['icon'] ?? '⚡')) ?: '⚡',
                            'title' => trim((string) ($f['title'] ?? '')),
                            'body' => trim((string) ($f['body'] ?? '')),
                            'badge' => trim((string) ($f['badge'] ?? '')),
                            'color' => trim((string) ($f['color'] ?? 'blue')),
                        ];
                    }
                }
            }
            if (empty($feats)) {
                $feats = $def['features'];
            }
            return [
                'enabled' => array_key_exists('enabled', $b) ? (bool) $b['enabled'] : (bool) $def['enabled'],
                'badge' => trim((string) ($b['badge'] ?? $def['badge'])),
                'title' => trim((string) ($b['title'] ?? $def['title'])),
                'subtitle' => trim((string) ($b['subtitle'] ?? $def['subtitle'])),
                'doc_button_text' => trim((string) ($b['doc_button_text'] ?? $def['doc_button_text'])),
                'doc_button_url' => trim((string) ($b['doc_button_url'] ?? $def['doc_button_url'])),
                'launch_button_text' => trim((string) ($b['launch_button_text'] ?? $def['launch_button_text'])),
                'launch_button_url' => trim((string) ($b['launch_button_url'] ?? $def['launch_button_url'])),
                'eligibility_title' => trim((string) ($b['eligibility_title'] ?? $def['eligibility_title'])),
                'eligibility_text' => trim((string) ($b['eligibility_text'] ?? $def['eligibility_text'])),
                'eligibility_badge' => trim((string) ($b['eligibility_badge'] ?? $def['eligibility_badge'])),
                'bg_mode' => trim((string) ($b['bg_mode'] ?? $def['bg_mode'] ?? 'theme_matching')),
                'bg_color_start' => trim((string) ($b['bg_color_start'] ?? $def['bg_color_start'] ?? '#0d1428')),
                'bg_color_end' => trim((string) ($b['bg_color_end'] ?? $def['bg_color_end'] ?? '#070b1a')),
                'border_color' => trim((string) ($b['border_color'] ?? $def['border_color'] ?? 'rgba(59, 130, 246, 0.28)')),
                'accent_color' => trim((string) ($b['accent_color'] ?? $def['accent_color'] ?? '#3b82f6')),
                'features' => $feats,
            ];
        })(),
        'section_colors' => (function() use ($config) {
            $defaults = default_section_colors();
            $saved = $config['section_colors'] ?? [];
            $merged = [];
            foreach ($defaults as $key => $val) {
                $merged[$key] = [
                    'name' => $val['name'],
                    'icon' => $val['icon'] ?? '🎨',
                    'dark_bg' => trim((string) ($saved[$key]['dark_bg'] ?? $val['dark_bg'])),
                    'light_bg' => trim((string) ($saved[$key]['light_bg'] ?? $val['light_bg'])),
                ];
            }
            return $merged;
        })(),
        'business_types_cards' => (function() use ($config) {
            $defaults = default_business_types_cards();
            $saved = $config['business_types_cards'] ?? [];
            $merged = [];
            foreach ($defaults as $key => $def) {
                $s = $saved[$key] ?? [];
                $feats = [];
                if (!empty($s['features']) && is_array($s['features'])) {
                    foreach ($s['features'] as $f) {
                        $tf = trim((string) $f);
                        if ($tf !== '') {
                            $feats[] = $tf;
                        }
                    }
                }
                if (empty($feats)) {
                    $feats = $def['features'];
                }
                $dispMode = trim((string) ($s['display_mode'] ?? $def['display_mode']));
                if (!in_array($dispMode, ['image', 'icon'], true)) {
                    $dispMode = 'image';
                }
                $merged[$key] = [
                    'key' => $key,
                    'title' => trim((string) ($s['title'] ?? $def['title'])),
                    'icon' => trim((string) ($s['icon'] ?? $def['icon'])),
                    'tag_text' => trim((string) ($s['tag_text'] ?? $def['tag_text'])),
                    'tag_class' => trim((string) ($s['tag_class'] ?? $def['tag_class'])),
                    'display_mode' => $dispMode,
                    'image_url' => trim((string) ($s['image_url'] ?? $def['image_url'])),
                    'icon_bg' => trim((string) ($s['icon_bg'] ?? $def['icon_bg'])),
                    'icon_color' => trim((string) ($s['icon_color'] ?? $def['icon_color'])),
                    'badge_label' => trim((string) ($s['badge_label'] ?? $def['badge_label'])),
                    'badge_sub' => trim((string) ($s['badge_sub'] ?? $def['badge_sub'])),
                    'btn_text' => trim((string) ($s['btn_text'] ?? $def['btn_text'])),
                    'btn_type' => in_array(($s['btn_type'] ?? ''), ['link', 'checkout'], true) ? $s['btn_type'] : $def['btn_type'],
                    'btn_url' => trim((string) ($s['btn_url'] ?? $def['btn_url'])),
                    'module_slug' => trim((string) ($s['module_slug'] ?? $def['module_slug'])),
                    'features' => $feats,
                ];
            }
            return $merged;
        })(),
        'default_theme_mode' => trim((string) ($config['default_theme_mode'] ?? 'dark')) ?: 'dark',
        'active_color_preset' => trim((string) ($config['active_color_preset'] ?? 'midnight_obsidian')) ?: 'midnight_obsidian',
        'color_presets' => landing_color_presets(),
    ];
}
