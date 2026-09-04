<?php

namespace App\Services\Integrations;

use App\Models\Company;
use App\Models\DiningTable;
use App\Models\KitchenTicket;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Modular\ModuleRegistry;
use App\Services\Push\FirebasePushService;
use Illuminate\Support\Facades\Log;
use Throwable;

class EcommercePayloadNormalizer
{
    public function __construct(
        protected FirebasePushService $pushService,
        protected OutboundWebhookService $outboundWebhooks
    ) {}

    /**
     * Ingest, normalize, and process an inbound e-commerce order payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public function process(Company $company, array $payload, array $headers = []): array
    {
        $platform = $this->detectPlatform($payload, $headers);
        $externalId = $this->extractExternalId($payload, $platform);

        // 1. Idempotency Check & Deduplication
        if (! empty($externalId)) {
            $existing = Sale::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('external_id', $externalId)
                ->first();

            if ($existing !== null) {
                return [
                    'success' => true,
                    'is_duplicate' => true,
                    'message' => 'Order already processed (idempotent)',
                    'platform' => $platform,
                    'external_id' => $externalId,
                    'sale_id' => $existing->id,
                    'sale_number' => $existing->sale_number,
                ];
            }
        }

        // 2. Normalize Attributes
        $normalized = $this->normalize($company, $payload, $platform, $externalId);

        // 3. Create POS Sale Record
        $sale = Sale::create([
            'company_id' => $company->id,
            'external_id' => $externalId ?: null,
            'sale_number' => $normalized['sale_number'],
            'customer_name' => $normalized['customer_name'],
            'total' => $normalized['total'],
            'net_amount' => $normalized['total'] - $normalized['discount'],
            'discount' => $normalized['discount'],
            'tax_amount' => $normalized['tax_amount'],
            'paid_amount' => $normalized['is_paid'] ? $normalized['total'] : 0,
            'due_amount' => $normalized['is_paid'] ? 0 : $normalized['total'],
            'payment_status' => $normalized['is_paid'] ? 'paid' : 'pending',
            'payment_method' => $normalized['payment_method'],
            'status' => 'completed',
            'items' => $normalized['items'],
            'service_type' => 'delivery',
            'delivery_address' => $normalized['delivery_address'],
            'notes' => "Imported via {$platform} webhook ({$normalized['sale_number']})",
        ]);

        // 4. Decrement Stock for Matched Products & Check Low-Stock Alert
        foreach ($normalized['items'] as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            if ($quantity <= 0) {
                continue;
            }

            $product = null;
            if (! empty($item['sku'])) {
                $product = Product::withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where(function ($q) use ($item) {
                        $q->where('sku', $item['sku'])
                            ->orWhere('code', $item['sku'])
                            ->orWhere('barcode', $item['sku']);
                    })
                    ->first();
            }

            if (! $product && ! empty($item['name'])) {
                $product = Product::withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where('name', $item['name'])
                    ->first();
            }

            if ($product) {
                $product->decrementStock($quantity, "E-commerce order {$sale->sale_number}");
                $freshStock = (float) $product->fresh()->current_stock;
                $minStock = (float) $product->minimum_stock;

                if ($freshStock <= $minStock) {
                    $this->outboundWebhooks->dispatch($company, OutboundWebhookService::EVENT_STOCK_LOW_ALERT, [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'current_stock' => $freshStock,
                        'minimum_stock' => $minStock,
                    ]);
                }
            }
        }

        // 5. Restaurant Mode: Dispatch Kitchen Ticket (KDS)
        $isRestaurant = ($company->pos_mode === 'restaurant')
            || (ModuleRegistry::resolveActiveMode($company) === 'restaurant');

        $kot = null;
        if ($isRestaurant) {
            $kotCount = KitchenTicket::withoutGlobalScope('company')->where('company_id', $company->id)->count();
            $sentAt = now();
            $prepMinutes = 25;
            $targetAt = $sentAt->copy()->addMinutes($prepMinutes);

            $kot = KitchenTicket::create([
                'company_id' => $company->id,
                'sale_id' => $sale->id,
                'kot_number' => 'KOT-'.sprintf('%03d', $kotCount + 1),
                'table_name' => 'Online Delivery',
                'service_type' => 'delivery',
                'status' => KitchenTicket::STATUS_PENDING,
                'server_name' => ucfirst($platform).' Webhook',
                'items' => $normalized['items'],
                'sent_to_kitchen_at' => $sentAt,
                'prep_minutes' => $prepMinutes,
                'target_completion_at' => $targetAt,
            ]);
        }

        // 6. Push Notification Alert to POS Devices
        try {
            $formattedTotal = $company->formatMoney($normalized['total']);
            $this->pushService->sendToCompany($company->id, [
                'type' => 'new_order',
                'action' => 'open_order',
                'title' => "New {$platform} Order {$normalized['sale_number']}",
                'body' => "{$normalized['customer_name']} · {$formattedTotal}",
                'sale_id' => (string) $sale->id,
                'sale_number' => $normalized['sale_number'],
                'route' => "/sales/{$sale->id}",
            ]);
        } catch (Throwable $e) {
            Log::warning("Push notification for ecommerce order {$sale->sale_number} failed: ".$e->getMessage());
        }

        // 7. Dispatch Outbound Webhook Subscription
        try {
            $this->outboundWebhooks->dispatch($company, OutboundWebhookService::EVENT_ORDER_CREATED, [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'external_id' => $sale->external_id,
                'customer_name' => $sale->customer_name,
                'total' => (float) $sale->total,
                'items' => $sale->items,
                'payment_status' => $sale->payment_status,
                'platform' => $platform,
            ]);
        } catch (Throwable $e) {
            Log::warning("Outbound webhook dispatch for order.created failed: ".$e->getMessage());
        }

        return [
            'success' => true,
            'is_duplicate' => false,
            'message' => 'Order processed successfully',
            'platform' => $platform,
            'external_id' => $externalId,
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'kitchen_ticket_id' => $kot?->id,
        ];
    }

    public function detectPlatform(array $payload, array $headers = []): string
    {
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = is_array($value) ? ($value[0] ?? '') : (string) $value;
        }

        if (isset($normalizedHeaders['x-shopify-topic']) || isset($normalizedHeaders['x-shopify-hmac-sha256'])) {
            return 'shopify';
        }

        if (isset($normalizedHeaders['x-wc-webhook-topic']) || isset($normalizedHeaders['x-wc-webhook-signature'])) {
            return 'woocommerce';
        }

        if (isset($payload['admin_graphql_api_id']) || isset($payload['checkout_token'])) {
            return 'shopify';
        }

        if (isset($payload['payment_method_title']) && isset($payload['billing'])) {
            return 'woocommerce';
        }

        return 'generic';
    }

    public function extractExternalId(array $payload, string $platform): string
    {
        return match ($platform) {
            'shopify' => (string) ($payload['id'] ?? $payload['order_number'] ?? ''),
            'woocommerce' => (string) ($payload['id'] ?? $payload['number'] ?? ''),
            default => (string) ($payload['external_id'] ?? $payload['order_id'] ?? $payload['id'] ?? ''),
        };
    }

    protected function normalize(Company $company, array $payload, string $platform, string $externalId): array
    {
        $saleNumber = match ($platform) {
            'shopify' => (string) ($payload['name'] ?? (isset($payload['order_number']) ? '#'.$payload['order_number'] : 'SHOPIFY-'.$externalId)),
            'woocommerce' => (string) (isset($payload['number']) ? '#'.$payload['number'] : 'WC-'.$externalId),
            default => (string) ($payload['sale_number'] ?? $payload['order_number'] ?? ($externalId ? 'ORD-'.$externalId : 'ORD-'.time())),
        };

        $customerName = match ($platform) {
            'shopify' => trim(($payload['customer']['first_name'] ?? '').' '.($payload['customer']['last_name'] ?? ''))
                ?: ($payload['billing_address']['name'] ?? $payload['shipping_address']['name'] ?? 'Shopify Customer'),
            'woocommerce' => trim(($payload['billing']['first_name'] ?? '').' '.($payload['billing']['last_name'] ?? ''))
                ?: ($payload['shipping']['first_name'] ?? 'WooCommerce Customer'),
            default => (string) ($payload['customer_name'] ?? ($payload['customer']['name'] ?? 'Online Customer')),
        };

        $total = match ($platform) {
            'shopify' => (float) ($payload['total_price'] ?? $payload['current_total_price'] ?? 0),
            'woocommerce' => (float) ($payload['total'] ?? 0),
            default => (float) ($payload['total'] ?? $payload['amount'] ?? 0),
        };

        $discount = match ($platform) {
            'shopify' => (float) ($payload['total_discounts'] ?? 0),
            'woocommerce' => (float) ($payload['discount_total'] ?? 0),
            default => (float) ($payload['discount'] ?? 0),
        };

        $taxAmount = match ($platform) {
            'shopify' => (float) ($payload['total_tax'] ?? 0),
            'woocommerce' => (float) ($payload['total_tax'] ?? 0),
            default => (float) ($payload['tax_amount'] ?? $payload['tax'] ?? 0),
        };

        $isPaid = match ($platform) {
            'shopify' => in_array(strtolower((string) ($payload['financial_status'] ?? '')), ['paid', 'partially_paid', 'authorized'], true),
            'woocommerce' => in_array(strtolower((string) ($payload['status'] ?? '')), ['processing', 'completed'], true),
            default => strtolower((string) ($payload['payment_status'] ?? '')) === 'paid' || ($payload['paid'] ?? false) === true,
        };

        $paymentMethod = match ($platform) {
            'shopify' => (string) (($payload['payment_gateway_names'][0] ?? null) ?: 'shopify_payments'),
            'woocommerce' => (string) ($payload['payment_method_title'] ?? $payload['payment_method'] ?? 'woocommerce'),
            default => (string) ($payload['payment_method'] ?? 'online'),
        };

        $deliveryAddress = match ($platform) {
            'shopify' => trim(implode(', ', array_filter([
                $payload['shipping_address']['address1'] ?? null,
                $payload['shipping_address']['city'] ?? null,
                $payload['shipping_address']['province'] ?? null,
                $payload['shipping_address']['zip'] ?? null,
            ]))),
            'woocommerce' => trim(implode(', ', array_filter([
                $payload['shipping']['address_1'] ?? $payload['billing']['address_1'] ?? null,
                $payload['shipping']['city'] ?? $payload['billing']['city'] ?? null,
                $payload['shipping']['state'] ?? $payload['billing']['state'] ?? null,
                $payload['shipping']['postcode'] ?? $payload['billing']['postcode'] ?? null,
            ]))),
            default => (string) ($payload['delivery_address'] ?? $payload['address'] ?? ''),
        };

        $rawItems = match ($platform) {
            'shopify', 'woocommerce' => (array) ($payload['line_items'] ?? []),
            default => (array) ($payload['items'] ?? $payload['line_items'] ?? $payload['products'] ?? []),
        };

        $items = [];
        foreach ($rawItems as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $name = (string) ($raw['title'] ?? $raw['name'] ?? 'Item');
            $sku = (string) ($raw['sku'] ?? '');
            $qty = (float) ($raw['quantity'] ?? 1);
            $price = (float) ($raw['price'] ?? 0);
            $itemTotal = (float) ($raw['total'] ?? ($price * $qty));

            $items[] = [
                'name' => $name,
                'sku' => $sku ?: null,
                'quantity' => $qty,
                'price' => $price,
                'total' => $itemTotal,
            ];
        }

        return [
            'sale_number' => $saleNumber,
            'customer_name' => $customerName,
            'total' => $total,
            'discount' => $discount,
            'tax_amount' => $taxAmount,
            'is_paid' => $isPaid,
            'payment_method' => $paymentMethod,
            'delivery_address' => $deliveryAddress,
            'items' => $items,
        ];
    }
}
