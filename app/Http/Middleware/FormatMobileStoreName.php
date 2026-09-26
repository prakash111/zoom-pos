<?php

namespace App\Http\Middleware;

use App\Http\Resources\Tenant\StoreResource;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Formats store and tenant display name to 3 characters exclusively for mobile devices.
 *
 * Checks:
 * - User-Agent: contains 'dart', 'mobile', 'android', 'iphone', or 'okhttp'
 * - X-Client-Platform: 'android', 'ios', or 'mobile'
 *
 * Guarantees:
 * - Mobile requests receive 3-character store name (e.g. "Zoo", "ZNE") without trailing dots.
 * - Always preserves `full_name` holding the complete unmodified business name in the JSON payload.
 * - Desktop, web browser, invoice generation, and receipt endpoints receive full unmodified store name.
 */
class FormatMobileStoreName
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // If not a mobile client or hitting invoice/receipt/pdf endpoints, leave response untouched.
        if (! StoreResource::isMobileClient($request)) {
            return $response;
        }

        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data)) {
                $mutated = false;
                $this->formatStoreNodes($data, $mutated);
                if ($mutated) {
                    $response->setData($data);
                }
            }
        }

        return $response;
    }

    /**
     * Recursively find and format store representations in the payload.
     *
     * @param  array<string, mixed>  $data
     */
    protected function formatStoreNodes(array &$data, bool &$mutated): void
    {
        // 1. Explicit store keys: current_store, store
        foreach (['current_store', 'store'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $this->sanitizeStoreRecord($data[$key]);
                $mutated = true;
            }
        }

        // 2. stores collection
        if (isset($data['stores']) && is_array($data['stores'])) {
            foreach ($data['stores'] as &$store) {
                if (is_array($store)) {
                    $this->sanitizeStoreRecord($store);
                    $mutated = true;
                }
            }
            unset($store);
        }

        // 3. data key containing a store or list of stores
        if (isset($data['data']) && is_array($data['data'])) {
            if ($this->isStoreRecord($data['data'])) {
                $this->sanitizeStoreRecord($data['data']);
                $mutated = true;
            } else {
                foreach ($data['data'] as &$item) {
                    if (is_array($item) && $this->isStoreRecord($item)) {
                        $this->sanitizeStoreRecord($item);
                        $mutated = true;
                    }
                }
                unset($item);
            }
        }
    }

    /**
     * Check if an array resembles a store / branch payload.
     */
    protected function isStoreRecord(array $item): bool
    {
        if (! isset($item['name']) || ! is_string($item['name'])) {
            return false;
        }

        return isset($item['branch_code'])
            || isset($item['code'])
            || isset($item['is_primary'])
            || isset($item['effective_address'])
            || isset($item['receipt_prefix'])
            || isset($item['current_store_id']);
    }

    /**
     * Sanitize a single store record: truncate `name` to 3 characters and preserve `full_name`.
     */
    protected function sanitizeStoreRecord(array &$store): void
    {
        if (isset($store['name']) && is_string($store['name'])) {
            if (! isset($store['full_name'])) {
                $store['full_name'] = $store['name'];
            }
            $store['name'] = Str::limit($store['full_name'], 3, '');
        }
    }
}
