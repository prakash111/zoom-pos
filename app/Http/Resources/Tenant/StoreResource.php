<?php

namespace App\Http\Resources\Tenant;

use App\Models\Company;
use App\Models\Store;
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
     */
    public static function formatStoreName(?string $name, ?Request $request = null): string
    {
        $name = (string) ($name ?? '');
        if (static::isMobileClient($request)) {
            return Str::limit($name, 3, '');
        }

        return $name;
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

        $isMobile = static::isMobileClient($request);
        $fullName = (string) ($store->name ?? '');
        $displayName = $isMobile ? Str::limit($fullName, 3, '') : $fullName;

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

        return array_merge($base, [
            'name' => $displayName,
            'full_name' => $fullName,
            'effective_address' => $store->effective_address,
            'effective_phone' => $store->effective_phone,
            'effective_tax_id' => $store->effective_tax_id,
            'subdomain' => $company?->slug,
            'is_current' => $store->is_active && (int) $store->id === $currentId,
            'receipt_prefix' => $store->invoice_prefix ?: ($store->settings['invoice_prefix'] ?? $company?->invoice_prefix),
        ]);
    }
}
