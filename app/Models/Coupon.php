<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit_total',
        'usage_limit_per_customer',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'float',
            'min_order_amount' => 'float',
            'max_discount_amount' => 'float',
            'usage_limit_total' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Check if the coupon is currently valid for the given cart subtotal and customer.
     * Returns ['valid' => bool, 'message' => string, 'discount_amount' => float].
     */
    public function validateForOrder(float $subtotal, ?int $customerId = null, ?string $phone = null, ?string $email = null): array
    {
        if (! $this->is_active) {
            return [
                'valid' => false,
                'message' => __('This coupon code is no longer active.'),
                'discount_amount' => 0.0,
            ];
        }

        $now = Carbon::now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return [
                'valid' => false,
                'message' => __('This coupon is not active yet. Starts on :date', ['date' => $this->starts_at->format('M d, Y')]),
                'discount_amount' => 0.0,
            ];
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return [
                'valid' => false,
                'message' => __('This coupon code has expired.'),
                'discount_amount' => 0.0,
            ];
        }

        if ($subtotal <= 0) {
            return [
                'valid' => false,
                'message' => __('Please add items to your cart before applying a coupon.'),
                'discount_amount' => 0.0,
            ];
        }

        if ($this->min_order_amount > 0 && $subtotal < $this->min_order_amount) {
            return [
                'valid' => false,
                'message' => __('Minimum order amount for this coupon is $:amount.', ['amount' => number_format($this->min_order_amount, 2)]),
                'discount_amount' => 0.0,
            ];
        }

        if ($this->usage_limit_total !== null && $this->used_count >= $this->usage_limit_total) {
            return [
                'valid' => false,
                'message' => __('This coupon has reached its total usage limit.'),
                'discount_amount' => 0.0,
            ];
        }

        // Check per-customer usage
        $customerId = $customerId ? (int) $customerId : null;
        $phone = filled($phone) ? trim((string) $phone) : null;
        $email = filled($email) ? trim((string) $email) : null;

        if ($this->usage_limit_per_customer > 0 && ($customerId || $phone || $email)) {
            $customerUsages = $this->usages()
                ->where(function ($q) use ($customerId, $phone, $email) {
                    $hasClause = false;
                    if ($customerId) {
                        $q->where('customer_id', $customerId);
                        $hasClause = true;
                    }
                    if ($phone) {
                        if ($hasClause) {
                            $q->orWhere('customer_phone', $phone);
                        } else {
                            $q->where('customer_phone', $phone);
                            $hasClause = true;
                        }
                    }
                    if ($email) {
                        if ($hasClause) {
                            $q->orWhere('customer_email', $email);
                        } else {
                            $q->where('customer_email', $email);
                        }
                    }
                })
                ->count();

            if ($customerUsages >= $this->usage_limit_per_customer) {
                return [
                    'valid' => false,
                    'message' => __('You have already used this coupon the maximum allowed times.'),
                    'discount_amount' => 0.0,
                ];
            }
        }

        $discount = $this->calculateDiscount($subtotal);

        return [
            'valid' => true,
            'message' => __('Coupon code :code applied successfully!', ['code' => $this->code]),
            'discount_amount' => $discount,
        ];
    }

    /**
     * Calculate discount amount for a given subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        $amount = 0.0;

        if ($this->discount_type === 'percentage') {
            $amount = ($subtotal * $this->discount_value) / 100.0;
            if ($this->max_discount_amount !== null && $this->max_discount_amount > 0) {
                $amount = min($amount, $this->max_discount_amount);
            }
        } else {
            // Fixed amount
            $amount = min($this->discount_value, $subtotal);
        }

        return round(max(0.0, $amount), 2);
    }
}
