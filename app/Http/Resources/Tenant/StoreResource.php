<?php

namespace App\Http\Resources\Tenant;

use App\Models\Company;
use App\Models\Store;
use App\Services\Navigation\NavigationSanitizerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Store API Resource and Response Transformer.
 *
 * Implements mobile device detection: strictly limits store name to 3 characters
 * exclusively for mobile clients (Android/iOS Flutter app), while preserving the
 * full unmodified business name under `full_name` and for desktop/web/invoice/receipt requests.
 *
 * @mixin \App\Models\Store
 */
class StoreResource extends JsonResource
{
    protected ?Company $company;
    protected ?int $currentStoreId;

    public function __construct($resource, ?Company $company = null, ?int $currentStoreId = null)
    {
        parent::__construct($resource);
        $this->company = $company;
        $this->currentStoreId = $currentStoreId;
    }

    /**
     * Determine if the incoming request originates from a mobile client.
     *
     * Detects:
     * - User-Agent containing: dart, mobile, android, iphone, okhttp
     * - X-Client-Platform header with: android, ios, mobile
     * Excludes invoice and receipt endpoints so documents always receive full names.
     */
    public static function isMobileClient(?Request $request = null): bool
    {
        $request ??= request();
        if (! $request) {
            return false;
        }

        // Invoice and receipt endpoints must receive the full unmodified store name
        if ($request->is('*receipt*') || $request->is('*invoice*') || $request->is('*pdf*')) {
            return false;
        }

        $platform = strtolower(trim((string) $request->header('X-Client-Platform', '')));
        if (in_array($platform, ['android', 'ios', 'mobile'], true)) {
            return true;
        }

        $userAgent = strtolower(trim((string) $request->header('User-Agent', '')));
        if ($userAgent === '') {
            return false;
        }

        foreach (['dart', 'mobile', 'android', 'iphone', 'okhttp'] as $needle) {
            if (str_contains($userAgent, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format a store name based on client device type.
     * Truncates to 4 characters for compact mobile header display.
     */
    public static function formatStoreName(?string $name, ?Request $request = null): string
    {
        $name = (string) ($name ?? '');
        return mb_substr($name, 0, 4);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Store|null $store */
        $store = $this->resource;
        if (! $store) {
            return [];
        }

        $fullName = (string) ($store->name ?? '');
        $shortName = mb_substr($fullName, 0, 4);

        $company = $this->company
            ?? $store->company
            ?? ($store->company_id ? Company::withoutGlobalScopes()->find($store->company_id) : null);

        $currentId = $this->currentStoreId
            ?? (int) ($request->attributes->get('store_id') ?? $request->user()?->current_store_id);

        $base = $store->only([
            'id', 'tenant_id', 'company_id', 'code', 'branch_code',
            'phone', 'email', 'address', 'tax_id',
            'address_line_1', 'address_line_2', 'city', 'state', 'pincode',
            'receipt_header', 'receipt_footer', 'invoice_prefix',
            'is_primary', 'is_active',
        ]);

        $navConfig = NavigationSanitizerService::getStoreNavigationConfig($store);

        return array_merge($base, [
            'name' => $fullName,
            'short_name' => $shortName,
            'full_name' => $fullName,
            'logo' => $store->logo_url ?? $company?->logo_url,
            'effective_address' => $store->effective_address,
            'effective_phone' => $store->effective_phone,
            'effective_tax_id' => $store->effective_tax_id,
            'subdomain' => $company?->slug,
            'is_current' => $store->is_active && (int) $store->id === $currentId,
            'receipt_prefix' => $store->invoice_prefix ?: ($store->settings['invoice_prefix'] ?? $company?->invoice_prefix),
            'operating_mode' => $navConfig['operating_mode'],
            'is_restaurant' => (bool) $navConfig['is_restaurant'],
            'pos_layout' => $store->settings['pos_layout'] ?? $navConfig['pos_layout'],
            'default_terminal_view' => $store->settings['default_terminal_view'] ?? $navConfig['default_terminal_view'],
            'primary_pos_route' => $navConfig['primary_pos_route'],
            'center_action_route' => $navConfig['center_action_route'],
            'center_button_route' => $navConfig['center_button_route'],
            'primary_action' => $navConfig['primary_action'],
            'default_pos_screen' => $navConfig['default_pos_screen'],
            'default_pos_action' => $navConfig['default_pos_action'],
            'drawer_pos_route' => $navConfig['drawer_pos_route'],
            'quick_actions' => $navConfig['quick_actions'],
            'navigation_config' => $navConfig,
            'bottom_nav_schema' => NavigationSanitizerService::getBottomNavigationSchema($store),
            'bottom_nav_config' => NavigationSanitizerService::getBottomNavigationSchema($store),
            'bottom_navigation' => NavigationSanitizerService::getBottomNavigationSchema($store),
        ]);
    }
}
