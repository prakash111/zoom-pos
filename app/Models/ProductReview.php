<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'product_id',
        'customer_id',
        'customer_name',
        'customer_email',
        'rating',
        'title',
        'comment',
        'is_approved',
        'is_verified_purchase',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_approved' => 'boolean',
            'is_verified_purchase' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public static function seedSampleReviewsForCompany(string $companyId): void
    {
        if (static::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
            return;
        }

        $products = Product::withoutGlobalScopes()->where('company_id', $companyId)->take(3)->get();
        if ($products->isEmpty()) {
            return;
        }

        $samples = [
            [
                'rating' => 5,
                'customer_name' => 'Sarah Jenkins',
                'customer_email' => 'sarah.j@example.com',
                'title' => 'Exceptional quality & fast delivery!',
                'comment' => 'Received exactly as described. Outstanding packaging and arrived earlier than expected. Will definitely shop here again!',
                'is_approved' => true,
                'is_verified_purchase' => true,
                'created_at' => now()->subDays(2),
            ],
            [
                'rating' => 5,
                'customer_name' => 'Michael Chen',
                'customer_email' => 'mchen@example.com',
                'title' => 'Top notch product and great service',
                'comment' => 'Great value for money. Built exceptionally well and performs wonderfully. Highly recommended!',
                'is_approved' => true,
                'is_verified_purchase' => true,
                'created_at' => now()->subDays(5),
            ],
            [
                'rating' => 4,
                'customer_name' => 'David Miller',
                'customer_email' => 'dmiller@example.com',
                'title' => 'Solid quality and good support',
                'comment' => 'Item is solid and matches all specifications. Checkout and delivery were smooth. Very satisfied with store communication.',
                'is_approved' => false,
                'is_verified_purchase' => false,
                'created_at' => now()->subHours(8),
            ],
        ];

        foreach ($samples as $i => $sample) {
            $prod = $products[$i % $products->count()];
            static::withoutGlobalScopes()->create(array_merge($sample, [
                'company_id' => $companyId,
                'product_id' => $prod->id,
            ]));
        }
    }
}
