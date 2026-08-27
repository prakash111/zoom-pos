<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;

class CommissionService
{
    public const TYPE_PERCENTAGE_SALE = 'percentage';
    public const TYPE_FIXED = 'fixed';
    public const TYPE_PERCENTAGE_PROFIT = 'profit_percentage';

    public const TYPES = [
        self::TYPE_PERCENTAGE_SALE => 'Percentage (%) of Sale Total',
        self::TYPE_FIXED => 'Fixed Amount ($) per Sale',
        self::TYPE_PERCENTAGE_PROFIT => 'Percentage (%) of Net Profit',
    ];

    /**
     * Calculate commission amount for a sale based on configured rate and scheme type.
     *
     * @param float $rate Commission rate value (e.g. 5.0 for 5% or 10.00 for $10)
     * @param string $type Calculation scheme type (percentage | fixed | profit_percentage)
     * @param float $saleTotal Total revenue of the sale
     * @param array $items Line items of the sale [{product_id, price, quantity, cost_price?}]
     * @param string|null $companyId Optional company ID for batch cost lookup
     * @return float Calculated commission payout amount
     */
    public function calculate(float $rate, string $type = self::TYPE_PERCENTAGE_SALE, float $saleTotal = 0.0, array $items = [], ?string $companyId = null): float
    {
        if ($rate <= 0) {
            return 0.0;
        }

        $type = $this->normalizeType($type);

        if ($type === self::TYPE_FIXED) {
            return round($rate, 2);
        }

        if ($type === self::TYPE_PERCENTAGE_SALE) {
            return round(($saleTotal * $rate) / 100, 2);
        }

        if ($type === self::TYPE_PERCENTAGE_PROFIT) {
            return $this->calculateProfitCommission($rate, $items, $companyId);
        }

        return round(($saleTotal * $rate) / 100, 2);
    }

    /**
     * Alias for calculate method.
     */
    public function calculateCommission(float $rate, string $type = self::TYPE_PERCENTAGE_SALE, float $saleTotal = 0.0, array $items = [], ?string $companyId = null): float
    {
        return $this->calculate($rate, $type, $saleTotal, $items, $companyId);
    }

    /**
     * Calculate profit-based commission across item lines.
     * Formula: Commission = Sum((Item Sale Price - Product Cost Price) * Quantity * (Rate / 100))
     */
    public function calculateProfitCommission(float $rate, array $items = [], ?string $companyId = null): float
    {
        if ($rate <= 0 || empty($items)) {
            return 0.0;
        }

        // Collect product IDs that need cost price lookup
        $productIds = collect($items)
            ->map(fn($it) => $it['product_id'] ?? ($it['id'] ?? null))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $productCosts = [];
        if (! empty($productIds)) {
            $query = Product::whereIn('id', $productIds);
            if ($companyId) {
                $query->where('company_id', $companyId);
            }
            $productCosts = $query->pluck('cost_price', 'id')->all();
        }

        $totalCommission = 0.0;

        foreach ($items as $item) {
            $productId = $item['product_id'] ?? ($item['id'] ?? null);
            $salePrice = (float) ($item['price'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 1);

            // Determine unit cost price (from item snapshot or product record)
            $costPrice = (float) ($item['cost_price'] ?? ($productId && isset($productCosts[$productId]) ? $productCosts[$productId] : 0));

            $unitProfit = $salePrice - $costPrice;
            $lineProfit = max(0, $unitProfit * $quantity);

            $lineCommission = ($lineProfit * $rate) / 100;
            $totalCommission += $lineCommission;
        }

        return round($totalCommission, 2);
    }

    /**
     * Format registered rate label for display in tables and reports.
     * E.g.: "5.0% on Profit", "5.0% on Sale Total", "$10.00 / sale"
     */
    public function formatRate(float $rate, ?string $type = self::TYPE_PERCENTAGE_SALE, string $currencySymbol = '$'): string
    {
        $type = $this->normalizeType($type);

        if ($type === self::TYPE_FIXED) {
            return $currencySymbol . number_format($rate, 2) . ' / sale';
        }

        if ($type === self::TYPE_PERCENTAGE_PROFIT) {
            return number_format($rate, 1) . '% on Profit';
        }

        return number_format($rate, 1) . '% on Sale Total';
    }

    /**
     * Normalize type string to standard constants.
     */
    public function normalizeType(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return match ($type) {
            'profit', 'profit_percentage', 'percentage_of_profit', 'net_profit' => self::TYPE_PERCENTAGE_PROFIT,
            'fixed', 'fixed_amount', 'fixed_per_sale' => self::TYPE_FIXED,
            default => self::TYPE_PERCENTAGE_SALE,
        };
    }
}
