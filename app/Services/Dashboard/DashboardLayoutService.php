<?php

namespace App\Services\Dashboard;

class DashboardLayoutService
{
    /**
     * Get the active dashboard layout schema for a tenant store.
     *
     * Returns a centralized server-driven UI layout schema dictating
     * the visible widgets, ordering, and presentation for the dashboard.
     *
     * The legacy 'total_balance' / 'balance_card' widget is strictly excluded
     * across all layouts to eliminate visual clutter and ensure tenant-accurate reporting.
     *
     * @return array{name: string, description: string, layout_key: string, widgets: array<string>}
     */
    public function getLayoutSchema(?string $layoutKey = 'cards_dark'): array
    {
        $canonicalKey = $this->canonicalLayoutKey($layoutKey ?: 'cards_dark');

        $schemas = [
            'cards_dark' => [
                'name'        => 'Cards (dark)',
                'description' => 'Statistics, quick actions, activity and transactions',
                'widgets'     => [
                    'quick_actions',        // Quick Sale, New Customer, Open Register
                    'receivables_banner',   // Due Payments / Receivables alert
                    // 'total_balance' has been permanently removed from this list
                    'statistics_card',      // Total Earnings, Number of Sales, Catalogue count
                    'purchase_activity',    // Completed vs Pending activity bar chart
                    'popular_tags',         // Top categories
                    'recent_transactions',  // Latest transactions
                ],
            ],
            'cards_light' => [
                'name'        => 'Cards (light)',
                'description' => 'Statistics, quick actions, activity and transactions',
                'widgets'     => [
                    'quick_actions',
                    'receivables_banner',
                    'statistics_card',
                    'purchase_activity',
                    'popular_tags',
                    'recent_transactions',
                ],
            ],
            'analytics_board' => [
                'name'        => 'Analytics board (dark)',
                'description' => 'Glossy dark board – order statistics, overview lines & tracking',
                'widgets'     => [
                    'quick_actions',
                    'receivables_banner',
                    'order_statistics',     // Total orders, Active order, Average order size
                    'sales_overview_chart', // Order and Sale Overview line chart
                    'marketing_radar',      // Marketing polygon/radar
                ],
            ],
            'metro_retail' => [
                'name'        => 'Metro Retail (dark)',
                'description' => 'Metric cards with sparklines, sales overview area chart & receivables split',
                'widgets'     => [
                    'receivables_banner',
                    'sales_overview_chart',
                    'statistics_card',
                    'quick_actions',
                ],
            ],
        ];

        $schema = $schemas[$canonicalKey] ?? $schemas['cards_dark'];
        $schema['layout_key'] = $canonicalKey;

        // Defensive sanitization: ensure balance card keys can never leak into the response
        $schema['widgets'] = $this->sanitizeWidgets($schema['widgets']);

        return $schema;
    }

    /**
     * Sanitize widget section keys to strictly remove any balance card references.
     *
     * @param array<string> $widgets
     * @return array<string>
     */
    public function sanitizeWidgets(array $widgets): array
    {
        $excluded = [
            'total_balance',
            'balance_card',
            'total_balance_card',
            'account_balance',
            'balance',
        ];

        return array_values(array_filter(
            $widgets,
            fn ($w) => !in_array(strtolower(trim((string) $w)), $excluded, true)
        ));
    }

    /**
     * Map any alias or legacy dashboard layout key to its canonical name.
     */
    public function canonicalLayoutKey(string $key): string
    {
        $k = strtolower(trim($key));

        return match ($k) {
            'posh', 'cards', 'cards_light', 'cards-light' => 'cards_light',
            'cards_dark', 'cards-dark' => 'cards_dark',
            'oroit', 'analytics_board', 'analytics-board', 'analytics_board_dark' => 'analytics_board',
            'redesigned', 'metro', 'metro_retail', 'metro-retail', 'metro_retail_dark' => 'metro_retail',
            default => 'cards_dark',
        };
    }

    /**
     * Return all available layout options for preferences and configuration.
     *
     * @return array<string, array{name: string, description: string, widgets: array<string>}>
     */
    public function getAvailableLayouts(): array
    {
        return [
            'cards_light'     => $this->getLayoutSchema('cards_light'),
            'cards_dark'      => $this->getLayoutSchema('cards_dark'),
            'analytics_board' => $this->getLayoutSchema('analytics_board'),
            'metro_retail'    => $this->getLayoutSchema('metro_retail'),
        ];
    }
}
