<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ThemeCustomizerController extends Controller
{
    public function saveGlobalDefaults(Request $request)
    {
        // 1. Process custom landing dark bg if present
        if ($request->has('landing_dark_bg')) {
            $settings = null;
            if (Schema::hasTable('system_settings')) {
                $settings = DB::table('system_settings')->where('key', 'superadmin_theme_customization')->value('value');
            }
            $config = $settings ? json_decode($settings, true) : [];

            $rawBg = trim((string) $request->input('landing_dark_bg', '#0b0f19'));
            if (! str_starts_with($rawBg, '#') && ! empty($rawBg)) {
                $rawBg = '#' . $rawBg;
            }
            if (empty($rawBg) || ! preg_match('/^#[0-9a-fA-F]{3,8}$/', $rawBg)) {
                $rawBg = '#0b0f19';
            }

            $config['landing_dark_bg'] = $rawBg;

            if (Schema::hasTable('system_settings')) {
                DB::table('system_settings')->updateOrInsert(
                    ['key' => 'superadmin_theme_customization'],
                    [
                        'value'      => json_encode($config),
                        'updated_at' => now(),
                    ]
                );
            }

            set_setting('landing_dark_bg', $rawBg);
        }

        // 2. Process granular per-section palette if provided
        if ($request->has('palette') || $request->has('landing_sections_theme_palette')) {
            $this->processSectionPalette($request);
        }

        // Invalidate cached theme tokens
        $this->flushThemeCaches();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Landing page theme settings updated successfully.');
    }

    public function saveSectionThemes(Request $request)
    {
        $sanitized = $this->processSectionPalette($request);
        $this->flushThemeCaches();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'palette' => $sanitized]);
        }

        return redirect()->back()->with('success', 'Landing page section themes updated successfully.');
    }

    public function applyMatchingPattern(Request $request)
    {
        $theme = strtolower(trim((string) $request->input('theme', 'obsidian')));
        $result = apply_matching_landing_palette($theme);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Matching color combination pattern '{$result['name']}' applied successfully.",
                'data' => $result,
            ]);
        }

        return redirect()->back()->with('success', "Matching color combination pattern '{$result['name']}' applied successfully.");
    }

    private function processSectionPalette(Request $request): array
    {
        $defaults = default_landing_sections_palette();
        $sections = array_keys($defaults);

        $inputPalette = $request->input('palette', $request->input('theme', $request->input('landing_sections_theme_palette', [])));
        if (is_string($inputPalette)) {
            $inputPalette = json_decode($inputPalette, true) ?: [];
        }
        if (! is_array($inputPalette)) {
            $inputPalette = [];
        }

        $slugMap = [
            'hero_showcase' => 'hero',
            'retail_features' => 'features',
            'pricing_plans' => 'pricing',
            'our_mission' => 'mission',
            'faq' => 'faq',
            'scale_cta' => 'cta',
            'contact_form' => 'contact',
            'about' => 'mission',
        ];

        // Also normalize any full slug keys into short section keys
        foreach ($slugMap as $slug => $short) {
            if (isset($inputPalette[$slug]) && is_array($inputPalette[$slug])) {
                $inputPalette[$short] = array_merge($inputPalette[$short] ?? [], $inputPalette[$slug]);
            }
        }

        $sanitized = [];
        foreach ($sections as $sec) {
            $sanitized[$sec] = [];
            $keys = array_keys($defaults[$sec] ?? []);
            foreach ($keys as $k) {
                // Support both dark_bg / bg_dark, light_bg / bg_light naming
                $val = $inputPalette[$sec][$k] ?? null;
                if ($val === null && $k === 'dark_bg') {
                    $val = $inputPalette[$sec]['bg_dark'] ?? null;
                } elseif ($val === null && $k === 'light_bg') {
                    $val = $inputPalette[$sec]['bg_light'] ?? null;
                }
                $val = trim((string) ($val ?? $defaults[$sec][$k] ?? ''));
                if (! str_starts_with($val, '#') && ! empty($val)) {
                    $val = '#' . $val;
                }
                if (empty($val) || ! preg_match('/^#[0-9a-fA-F]{3,8}$/', $val)) {
                    $val = $defaults[$sec][$k] ?? '#ffffff';
                }
                $sanitized[$sec][$k] = $val;
            }
        }

        // Build token dictionary with both short keys and full slugs
        $themeTokens = [];
        foreach ($sanitized as $sec => $props) {
            $themeTokens[$sec] = array_merge($props, [
                'bg_dark' => $props['dark_bg'] ?? '#0b0f19',
                'bg_light' => $props['light_bg'] ?? '#ffffff',
                'text_dark' => $props['dark_text'] ?? '#f8fafc',
                'text_light' => $props['light_text'] ?? '#0f172a',
                'muted_dark' => $props['dark_muted'] ?? '#94a3b8',
                'muted_light' => $props['light_muted'] ?? '#64748b',
            ]);
        }
        foreach ($slugMap as $slug => $short) {
            if (isset($themeTokens[$short])) {
                $themeTokens[$slug] = $themeTokens[$short];
            }
        }

        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'landing_sections_theme_palette'],
                [
                    'value'      => json_encode($sanitized),
                    'updated_at' => now(),
                ]
            );
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'landing_theme_tokens'],
                [
                    'value'      => json_encode($themeTokens),
                    'updated_at' => now(),
                ]
            );
        }

        set_setting('landing_sections_theme_palette', json_encode($sanitized));
        set_setting('landing_theme_tokens', json_encode($themeTokens));

        return $sanitized;
    }

    private function flushThemeCaches(): void
    {
        Cache::forget('landing_sections_theme_palette');
        Cache::forget('landing_theme_tokens');
        Cache::forget('superadmin_theme_settings');
        Cache::forget('landing_page_theme_config');
        Cache::forget('app_landing_page_theme');
        if (Cache::has('landing_page_cache_version')) {
            Cache::increment('landing_page_cache_version');
        } else {
            Cache::forever('landing_page_cache_version', 2);
        }
    }
}
