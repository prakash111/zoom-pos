<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupLandingMatchingColorsCommand extends Command
{
    protected $signature = 'landing:setup-matching-colors {--theme=obsidian : The harmonized theme pattern to apply (obsidian, midnight, emerald, indigo, oled)}';
    protected $description = 'Setup and apply a cohesive, matching color combination pattern across all landing page sections for both Light and Dark themes.';

    public function handle(): int
    {
        $theme = strtolower(trim((string) $this->option('theme') ?: 'obsidian'));
        $patterns = get_landing_matching_patterns();

        if (! array_key_exists($theme, $patterns)) {
            $this->error("Invalid theme pattern '{$theme}'. Available patterns: " . implode(', ', array_keys($patterns)));
            return self::FAILURE;
        }

        $this->info("Applying '{$patterns[$theme]['name']}' matching color combination pattern across all landing page sections...");

        $result = apply_matching_landing_palette($theme);

        $this->info("✓ Pattern '{$result['name']}' applied successfully!");
        $this->line("  Global Dark Section Backdrop: {$result['dark_bg']}");

        $rows = [];
        foreach ($result['palette'] as $section => $tokens) {
            $rows[] = [
                ucfirst($section),
                $tokens['light_bg'],
                $tokens['light_text'],
                $tokens['light_muted'],
                $tokens['dark_bg'],
                $tokens['dark_text'],
                $tokens['dark_muted'],
                $tokens['light_card_bg'] ?? $tokens['dark_card_bg'] ?? 'N/A',
            ];
        }

        $this->table(
            ['Section', 'Light BG', 'Light Text', 'Light Muted', 'Dark BG', 'Dark Text', 'Dark Muted', 'Card BG'],
            $rows
        );

        $this->info('✓ All landing caches flushed and cache version incremented.');

        return self::SUCCESS;
    }
}
