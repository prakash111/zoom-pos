<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\Sale;
use App\Services\Notifications\TenantNotificationDispatcherService;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptModalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sale = $this->resource;
        $company = $sale->company ?? ($sale instanceof Sale && $sale->company_id ? Company::find($sale->company_id) : null);

        $dispatcher = app(TenantNotificationDispatcherService::class);
        $enabledChannels = $company ? $dispatcher->getEnabledChannels($company) : [];

        $checkboxes = [];
        if (! empty($enabledChannels['whatsapp'])) {
            $checkboxes[] = [
                'key' => 'send_via_whatsapp',
                'label' => 'Send Receipt via WhatsApp',
                'default' => true,
                'channel' => 'whatsapp',
                'component' => SchemaResponse::checkbox('send_via_whatsapp', 'Send Receipt via WhatsApp', true),
            ];
        }
        if (! empty($enabledChannels['sms'])) {
            $checkboxes[] = [
                'key' => 'send_via_sms',
                'label' => 'Send Receipt via SMS',
                'default' => true,
                'channel' => 'sms',
                'component' => SchemaResponse::checkbox('send_via_sms', 'Send Receipt via SMS', true),
            ];
        }
        if (! empty($enabledChannels['email'])) {
            $checkboxes[] = [
                'key' => 'send_via_email',
                'label' => 'Send Receipt via Email',
                'default' => false,
                'channel' => 'email',
                'component' => SchemaResponse::checkbox('send_via_email', 'Send Receipt via Email', false),
            ];
        }
        if (! empty($enabledChannels['custom_webhook'])) {
            $checkboxes[] = [
                'key' => 'send_via_webhook',
                'label' => 'Trigger Webhook Notification',
                'default' => true,
                'channel' => 'custom_webhook',
                'component' => SchemaResponse::checkbox('send_via_webhook', 'Trigger Webhook Notification', true),
            ];
        }

        return [
            'sale_id' => $sale->id ?? null,
            'sale_number' => $sale->sale_number ?? null,
            'total' => (float) ($sale->total ?? 0),
            'currency' => $company?->currency ?? 'USD',
            'currency_symbol' => $company?->currency_symbol ?? '$',
            'enabled_channels' => array_keys(array_filter($enabledChannels)),
            'notification_checkboxes' => $checkboxes,
            'sdui_components' => array_column($checkboxes, 'component'),
        ];
    }
}
