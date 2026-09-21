<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TenantCustomPage extends Model
{
    use BelongsToCompany;

    protected $table = 'tenant_custom_pages';

    protected $fillable = [
        'company_id',
        'tenant_id',
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($page) {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
            if (empty($page->tenant_id) && ! empty($page->company_id)) {
                $page->tenant_id = $page->company_id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(TenantStoreMenu::class, 'page_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    public static function seedDefaultsForCompany(string $companyId): void
    {
        if (static::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
            return;
        }

        $defaults = [
            [
                'title' => 'About Our Store',
                'slug' => 'about-us',
                'content' => '<h2>Welcome to our store</h2><p>We are dedicated to providing the freshest, highest quality goods directly to our valued local customers. Established with a passion for excellence, our store combines traditional quality with modern, convenient online shopping and swift doorstep delivery.</p><p>Thank you for choosing us as your trusted neighborhood store.</p>',
                'meta_title' => 'About Us - Our Story & Values',
                'meta_description' => 'Learn about our commitment to quality, community service, and verified local delivery.',
                'is_published' => true,
            ],
            [
                'title' => 'Delivery Terms & Conditions',
                'slug' => 'delivery-terms',
                'content' => '<h2>Delivery & Order Fulfilment</h2><p>We prepare and pack all items with utmost care immediately upon order confirmation. Delivery times vary depending on your location, typically arriving within 1-3 business days or on-demand same-day depending on checkout selection.</p><p>You can track the live progress of your delivery using our official order tracking code.</p>',
                'meta_title' => 'Delivery & Shipping Terms',
                'meta_description' => 'Official shipping terms, delivery turnaround, and real-time order tracking details.',
                'is_published' => true,
            ],
            [
                'title' => 'Returns & Refund Policy',
                'slug' => 'returns-policy',
                'content' => '<h2>Returns & Customer Satisfaction</h2><p>Customer satisfaction is our highest priority. If an item arrives damaged, defective, or incorrect, please reach out via our contact form or hotline within 48 hours of receipt for an immediate replacement or full refund.</p>',
                'meta_title' => 'Returns and Refunds Policy',
                'meta_description' => 'Our hassle-free returns policy and customer satisfaction guarantee.',
                'is_published' => true,
            ],
        ];

        foreach ($defaults as $item) {
            static::withoutGlobalScopes()->create(array_merge($item, [
                'company_id' => $companyId,
                'tenant_id' => $companyId,
            ]));
        }
    }
}
