<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantStoreMenu extends Model
{
    use BelongsToCompany;

    protected $table = 'tenant_store_menus';

    public const LOCATION_HEADER = 'header_nav';
    public const LOCATION_FOOTER_1 = 'footer_col_1';
    public const LOCATION_FOOTER_2 = 'footer_col_2';
    public const LOCATION_FOOTER_3 = 'footer_col_3';

    public const LOCATIONS = [
        self::LOCATION_HEADER => 'Header Navigation',
        self::LOCATION_FOOTER_1 => 'Footer - Department / Categories',
        self::LOCATION_FOOTER_2 => 'Footer - Help & Support',
        self::LOCATION_FOOTER_3 => 'Footer - Company & Legal',
    ];

    public const TYPE_CMS_PAGE = 'cms_page';
    public const TYPE_CATEGORY = 'category';
    public const TYPE_ANCHOR = 'anchor';
    public const TYPE_CUSTOM_URL = 'custom_url';

    public const TYPES = [
        self::TYPE_CMS_PAGE => 'Custom CMS Page',
        self::TYPE_CATEGORY => 'Store Category',
        self::TYPE_ANCHOR => 'Section Anchor Link (#)',
        self::TYPE_CUSTOM_URL => 'Custom Web URL',
    ];

    protected $fillable = [
        'company_id',
        'tenant_id',
        'location',
        'title',
        'type',
        'target_url',
        'page_id',
        'category_id',
        'sort_order',
        'is_visible',
        'target',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
        'page_id' => 'integer',
        'category_id' => 'integer',
    ];

    protected $appends = [
        'resolved_url',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($menu) {
            if (empty($menu->tenant_id) && ! empty($menu->company_id)) {
                $menu->tenant_id = $menu->company_id;
            }
            if (empty($menu->location)) {
                $menu->location = self::LOCATION_HEADER;
            }
            if (empty($menu->target)) {
                $menu->target = '_self';
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(TenantCustomPage::class, 'page_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeLocation(Builder $query, string $location): Builder
    {
        return $query->where('location', $location);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    public function getResolvedUrlAttribute(): string
    {
        if ($this->type === self::TYPE_CMS_PAGE) {
            $slug = $this->page?->slug ?: ltrim(str_replace('/page/', '', $this->target_url ?? ''), '/');
            return $slug ? '/page/' . $slug : '#';
        }

        if ($this->type === self::TYPE_CATEGORY) {
            return '#products-section';
        }

        if ($this->type === self::TYPE_ANCHOR) {
            return $this->target_url && str_starts_with($this->target_url, '#')
                ? $this->target_url
                : '#' . ltrim($this->target_url ?? 'products-section', '#');
        }

        return $this->target_url ?: '#';
    }

    public static function seedDefaultsForCompany(string $companyId): void
    {
        if (static::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
            return;
        }

        // Ensure default CMS pages exist first
        TenantCustomPage::seedDefaultsForCompany($companyId);
        $aboutPage = TenantCustomPage::withoutGlobalScopes()->where('company_id', $companyId)->where('slug', 'about-us')->first();
        $deliveryPage = TenantCustomPage::withoutGlobalScopes()->where('company_id', $companyId)->where('slug', 'delivery-terms')->first();
        $returnsPage = TenantCustomPage::withoutGlobalScopes()->where('company_id', $companyId)->where('slug', 'returns-policy')->first();

        // 1. Header Navigation defaults
        $headerItems = [
            [
                'location' => self::LOCATION_HEADER,
                'title' => 'Deals & Specials',
                'type' => self::TYPE_ANCHOR,
                'target_url' => '#products-section',
                'sort_order' => 1,
            ],
            [
                'location' => self::LOCATION_HEADER,
                'title' => "What's New",
                'type' => self::TYPE_ANCHOR,
                'target_url' => '#products-section',
                'sort_order' => 2,
            ],
            [
                'location' => self::LOCATION_HEADER,
                'title' => 'Services & Delivery',
                'type' => self::TYPE_ANCHOR,
                'target_url' => '#services-section',
                'sort_order' => 3,
            ],
            [
                'location' => self::LOCATION_HEADER,
                'title' => 'About Us',
                'type' => self::TYPE_CMS_PAGE,
                'page_id' => $aboutPage?->id,
                'target_url' => '/page/about-us',
                'sort_order' => 4,
            ],
        ];

        // 2. Footer Col 2 (Help & Support)
        $footer2Items = [
            [
                'location' => self::LOCATION_FOOTER_2,
                'title' => 'Delivery Terms',
                'type' => self::TYPE_CMS_PAGE,
                'page_id' => $deliveryPage?->id,
                'target_url' => '/page/delivery-terms',
                'sort_order' => 1,
            ],
            [
                'location' => self::LOCATION_FOOTER_2,
                'title' => 'Order Tracking',
                'type' => self::TYPE_CUSTOM_URL,
                'target_url' => '/store#track',
                'sort_order' => 2,
            ],
            [
                'location' => self::LOCATION_FOOTER_2,
                'title' => 'Returns Policy',
                'type' => self::TYPE_CMS_PAGE,
                'page_id' => $returnsPage?->id,
                'target_url' => '/page/returns-policy',
                'sort_order' => 3,
            ],
        ];

        // 3. Footer Col 3 (Company & Legal)
        $footer3Items = [
            [
                'location' => self::LOCATION_FOOTER_3,
                'title' => 'About Our Store',
                'type' => self::TYPE_CMS_PAGE,
                'page_id' => $aboutPage?->id,
                'target_url' => '/page/about-us',
                'sort_order' => 1,
            ],
            [
                'location' => self::LOCATION_FOOTER_3,
                'title' => 'Store FAQs',
                'type' => self::TYPE_CUSTOM_URL,
                'target_url' => '/store/faqs',
                'sort_order' => 2,
            ],
        ];

        foreach (array_merge($headerItems, $footer2Items, $footer3Items) as $item) {
            static::withoutGlobalScopes()->create(array_merge($item, [
                'company_id' => $companyId,
                'tenant_id' => $companyId,
                'is_visible' => true,
                'target' => '_self',
            ]));
        }
    }
}
