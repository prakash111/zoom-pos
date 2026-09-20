<?php

namespace App\Services\Notifications;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Sale;
use App\Models\TenantNotificationGateway;
use Illuminate\Support\Facades\Log;

class StorefrontOrderNotificationService
{
    public function __construct(
        protected TenantNotificationDispatcherService $dispatcher
    ) {
    }

    /**
     * Determine if order notifications are enabled for the tenant.
     */
    public function shouldSendNotifications(Company $company, string $event = 'placed'): bool
    {
        $enabled = (bool) ($company->enable_order_notifications
            ?? tenant_setting($company->id, 'enable_order_notifications', false));

        if (! $enabled) {
            return false;
        }

        $allowedEvents = $company->order_notification_events ?? ['placed', 'completed', 'cancelled'];

        return in_array($event, $allowedEvents, true);
    }

    /**
     * Get active notification channels for order notifications.
     */
    public function getActiveChannels(Company $company): array
    {
        $gatewayEnabled = $this->dispatcher->getEnabledChannels($company);
        $active = [];

        if (! empty($gatewayEnabled[TenantNotificationGateway::CHANNEL_EMAIL])) {
            $active[] = 'email';
        }
        if (! empty($gatewayEnabled[TenantNotificationGateway::CHANNEL_SMS])) {
            $active[] = 'sms';
        }
        if (! empty($gatewayEnabled[TenantNotificationGateway::CHANNEL_WHATSAPP])) {
            $active[] = 'whatsapp';
        }

        $allowed = $company->order_notification_channels ?? ['email', 'sms', 'whatsapp'];
        if (is_array($allowed) && ! empty($allowed)) {
            $active = array_values(array_intersect($active, $allowed));
        }

        return $active;
    }

    /**
     * Resolve customer contact details from Sale or Customer relation.
     *
     * @return array{name: string, email: ?string, phone: ?string}
     */
    protected function resolveCustomerContact(Sale $sale): array
    {
        $customer = $sale->customer;
        $name = $customer?->name ?: ($sale->customer_name ?: 'Valued Customer');
        $email = $customer?->email;
        $phone = $customer?->phone;

        // Try extracting from notes if not present
        if (empty($email) && ! empty($sale->notes) && preg_match('/Email:\s*([^\s|]+)/i', $sale->notes, $m)) {
            $email = trim($m[1]);
        }
        if (empty($phone) && ! empty($sale->notes) && preg_match('/Phone:\s*([^\s|]+)/i', $sale->notes, $m)) {
            $phone = trim($m[1]);
        }

        return [
            'name' => $name,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
        ];
    }

    /**
     * Notify customer that an order has been successfully placed.
     */
    public function notifyOrderPlaced(Sale $sale): array
    {
        $company = $sale->company ?? Company::find($sale->company_id);
        if (! $company) {
            return ['success' => false, 'message' => 'Company not found'];
        }

        if (! $this->shouldSendNotifications($company, 'placed')) {
            return ['success' => true, 'skipped' => true, 'message' => 'Order placed notifications disabled by tenant.'];
        }

        $channels = $this->getActiveChannels($company);
        if (empty($channels)) {
            return ['success' => true, 'skipped' => true, 'message' => 'No active notification gateway.'];
        }

        $contact = $this->resolveCustomerContact($sale);
        $trackingUrl = url('/store/track/' . ($sale->tracking_code ?: $sale->sale_number) . '?store=' . ($company->slug ?? ''));
        $currency = $company->currency_symbol ?: '$';
        $sentChannels = [];
        $errors = [];

        // 1. Email via SMTP
        if (in_array('email', $channels, true) && ! empty($contact['email'])) {
            $subject = "Order Confirmation #{$sale->sale_number} - {$company->name}";
            $html = $this->buildOrderPlacedEmailHtml($company, $sale, $contact['name'], $trackingUrl, $currency);

            try {
                $res = $this->dispatcher->dispatchEmail($company, $contact['email'], $subject, $html);
                if (! empty($res['success'])) {
                    $sentChannels[] = 'email';
                } else {
                    $errors['email'] = $res['message'] ?? 'Email delivery failed';
                }
            } catch (\Throwable $e) {
                $errors['email'] = $e->getMessage();
                Log::warning("Order placed email error: {$e->getMessage()}");
            }
        }

        // 2. SMS Gateway
        if (in_array('sms', $channels, true) && ! empty($contact['phone'])) {
            $sms = "Thank you for your order #{$sale->sale_number} at {$company->name}! Total: {$currency}{$sale->net_amount}. Track your order here: {$trackingUrl}";

            try {
                $res = $this->dispatcher->dispatchSms($company, $contact['phone'], $sms);
                if (! empty($res['success'])) {
                    $sentChannels[] = 'sms';
                } else {
                    $errors['sms'] = $res['message'] ?? 'SMS delivery failed';
                }
            } catch (\Throwable $e) {
                $errors['sms'] = $e->getMessage();
                Log::warning("Order placed SMS error: {$e->getMessage()}");
            }
        }

        // 3. WhatsApp Gateway
        if (in_array('whatsapp', $channels, true) && ! empty($contact['phone'])) {
            $wa = "🛍️ *Order Confirmation - {$company->name}*\n\n"
                . "Hello *{$contact['name']}*, thank you for your order!\n\n"
                . "📄 *Order #:* {$sale->sale_number}\n"
                . "💰 *Total:* {$currency}{$sale->net_amount}\n"
                . "📦 *Status:* Received\n\n"
                . "Track your order live here:\n{$trackingUrl}";

            try {
                $res = $this->dispatcher->dispatchWhatsApp($company, $contact['phone'], $wa);
                if (! empty($res['success'])) {
                    $sentChannels[] = 'whatsapp';
                } else {
                    $errors['whatsapp'] = $res['message'] ?? 'WhatsApp delivery failed';
                }
            } catch (\Throwable $e) {
                $errors['whatsapp'] = $e->getMessage();
                Log::warning("Order placed WhatsApp error: {$e->getMessage()}");
            }
        }

        AuditLog::record('order.notification_dispatched', $company->id, null, [
            'sale_id' => $sale->id,
            'event' => 'placed',
            'channels' => $sentChannels,
            'errors' => $errors,
        ]);

        return [
            'success' => ! empty($sentChannels),
            'channels' => $sentChannels,
            'errors' => $errors,
        ];
    }

    /**
     * Notify customer when order status changes (completed, confirmed, cancelled, etc.).
     */
    public function notifyOrderStatusChanged(Sale $sale, string $newStatus, ?string $statusNote = null): array
    {
        $company = $sale->company ?? Company::find($sale->company_id);
        if (! $company) {
            return ['success' => false, 'message' => 'Company not found'];
        }

        $eventKey = match ($newStatus) {
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'status_update',
        };

        if (! $this->shouldSendNotifications($company, $eventKey)) {
            return ['success' => true, 'skipped' => true, 'message' => 'Notification skipped per tenant choice.'];
        }

        $channels = $this->getActiveChannels($company);
        if (empty($channels)) {
            return ['success' => true, 'skipped' => true, 'message' => 'No active notification gateway.'];
        }

        $contact = $this->resolveCustomerContact($sale);
        $trackingUrl = url('/store/track/' . ($sale->tracking_code ?: $sale->sale_number) . '?store=' . ($company->slug ?? ''));
        $currency = $company->currency_symbol ?: '$';
        $statusLabel = ucfirst(str_replace('_', ' ', $newStatus));
        $sentChannels = [];
        $errors = [];

        // 1. Email via SMTP
        if (in_array('email', $channels, true) && ! empty($contact['email'])) {
            $subject = "Order Status Update #{$sale->sale_number}: {$statusLabel} - {$company->name}";
            $html = $this->buildStatusChangedEmailHtml($company, $sale, $contact['name'], $statusLabel, $trackingUrl, $currency, $statusNote);

            try {
                $res = $this->dispatcher->dispatchEmail($company, $contact['email'], $subject, $html);
                if (! empty($res['success'])) {
                    $sentChannels[] = 'email';
                }
            } catch (\Throwable $e) {
                $errors['email'] = $e->getMessage();
            }
        }

        // 2. SMS Gateway
        if (in_array('sms', $channels, true) && ! empty($contact['phone'])) {
            $sms = "{$company->name}: Your order #{$sale->sale_number} status is now {$statusLabel}. Track: {$trackingUrl}";

            try {
                $res = $this->dispatcher->dispatchSms($company, $contact['phone'], $sms);
                if (! empty($res['success'])) {
                    $sentChannels[] = 'sms';
                }
            } catch (\Throwable $e) {
                $errors['sms'] = $e->getMessage();
            }
        }

        // 3. WhatsApp Gateway
        if (in_array('whatsapp', $channels, true) && ! empty($contact['phone'])) {
            $wa = "🔔 *Order Update - {$company->name}*\n\n"
                . "Hello *{$contact['name']}*,\n"
                . "Your order *#{$sale->sale_number}* status has been updated to: *{$statusLabel}*.\n\n"
                . ($statusNote ? "Note: {$statusNote}\n\n" : "")
                . "Track your order live here:\n{$trackingUrl}";

            try {
                $res = $this->dispatcher->dispatchWhatsApp($company, $contact['phone'], $wa);
                if (! empty($res['success'])) {
                    $sentChannels[] = 'whatsapp';
                }
            } catch (\Throwable $e) {
                $errors['whatsapp'] = $e->getMessage();
            }
        }

        AuditLog::record('order.status_notification_dispatched', $company->id, null, [
            'sale_id' => $sale->id,
            'status' => $newStatus,
            'channels' => $sentChannels,
            'errors' => $errors,
        ]);

        return [
            'success' => ! empty($sentChannels),
            'channels' => $sentChannels,
            'errors' => $errors,
        ];
    }

    /**
     * Render responsive HTML template for Order Confirmation email.
     */
    protected function buildOrderPlacedEmailHtml(Company $company, Sale $sale, string $customerName, string $trackingUrl, string $currency): string
    {
        $storeName = htmlspecialchars($company->name ?? 'Our Store', ENT_QUOTES, 'UTF-8');
        $custName = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');
        $primaryColor = $company->primary_color ?: '#059669';

        $itemsHtml = '';
        foreach ($sale->items ?? [] as $item) {
            $itemName = htmlspecialchars($item['name'] ?? 'Product', ENT_QUOTES, 'UTF-8');
            $qty = $item['quantity'] ?? 1;
            $price = number_format((float) ($item['price'] ?? 0), 2);
            $total = number_format((float) ($item['total'] ?? ($qty * $price)), 2);

            $itemsHtml .= <<<ROW
<tr>
    <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; font-weight: 600; color: #1e293b;">
        {$itemName} <span style="color: #64748b; font-size: 12px;">&times; {$qty}</span>
    </td>
    <td align="right" style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; font-weight: 700; color: #0f172a;">
        {$currency}{$total}
    </td>
</tr>
ROW;
        }

        $subtotal = number_format((float) ($sale->total ?? 0), 2);
        $discount = number_format((float) ($sale->discount ?? 0), 2);
        $net = number_format((float) ($sale->net_amount ?? 0), 2);
        $deliveryAddr = htmlspecialchars($sale->delivery_address ?? 'Standard Delivery', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation #{$sale->sale_number}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                    <!-- Brand Header -->
                    <tr>
                        <td style="background-color: {$primaryColor}; padding: 32px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 900;">{$storeName}</h1>
                            <p style="margin: 6px 0 0; color: rgba(255,255,255,0.9); font-size: 13px; font-weight: 600;">Order Confirmation</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 32px;">
                            <div style="background-color: #ecfdf5; border-radius: 16px; padding: 16px 20px; margin-bottom: 24px; text-align: center; border: 1px solid #a7f3d0;">
                                <span style="font-size: 24px; vertical-align: middle;">🎉</span>
                                <strong style="color: #065f46; font-size: 15px; margin-left: 8px;">Order Received Successfully!</strong>
                                <p style="margin: 4px 0 0; color: #047857; font-size: 12px;">Order #{$sale->sale_number}</p>
                            </div>

                            <p style="margin: 0 0 20px; font-size: 14px; color: #475569; line-height: 1.6;">
                                Hello <strong>{$custName}</strong>, thank you for shopping with us! We have received your order and our store team is preparing it.
                            </p>

                            <!-- Order Summary Table -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
                                <thead>
                                    <tr>
                                        <th align="left" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">Item</th>
                                        <th align="right" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {$itemsHtml}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td style="padding: 10px 0 4px; font-size: 13px; color: #64748b;">Subtotal</td>
                                        <td align="right" style="padding: 10px 0 4px; font-size: 13px; font-weight: 600; color: #1e293b;">{$currency}{$subtotal}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 4px 0; font-size: 13px; color: #64748b;">Discount</td>
                                        <td align="right" style="padding: 4px 0; font-size: 13px; font-weight: 600; color: #10b981;">-{$currency}{$discount}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px 0; font-size: 16px; font-weight: 800; color: #0f172a; border-top: 2px solid #e2e8f0;">Total</td>
                                        <td align="right" style="padding: 10px 0; font-size: 18px; font-weight: 900; color: #059669; border-top: 2px solid #e2e8f0;">{$currency}{$net}</td>
                                    </tr>
                                </tfoot>
                            </table>

                            <!-- Delivery Address -->
                            <div style="background-color: #f8fafc; border-radius: 16px; padding: 16px; margin-bottom: 24px; border: 1px solid #f1f5f9;">
                                <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #94a3b8; margin-bottom: 4px;">Delivery Address</div>
                                <div style="font-size: 13px; font-weight: 600; color: #334155;">{$deliveryAddr}</div>
                            </div>

                            <!-- Track Order Button -->
                            <div style="text-align: center; margin: 28px 0 10px;">
                                <a href="{$trackingUrl}" style="background-color: {$primaryColor}; color: #ffffff; padding: 14px 32px; border-radius: 14px; font-size: 14px; font-weight: 800; text-decoration: none; display: inline-block; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);">
                                    Track Your Order Online &rarr;
                                </a>
                            </div>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 30px; border-top: 1px solid #f1f5f9; text-align: center;">
                            <p style="margin: 0; font-size: 11px; font-weight: 600; color: #94a3b8;">
                                &copy; {$storeName}. Thank you for your business.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Render responsive HTML template for Order Status Update email.
     */
    protected function buildStatusChangedEmailHtml(Company $company, Sale $sale, string $customerName, string $statusLabel, string $trackingUrl, string $currency, ?string $notes = null): string
    {
        $storeName = htmlspecialchars($company->name ?? 'Our Store', ENT_QUOTES, 'UTF-8');
        $custName = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');
        $primaryColor = $company->primary_color ?: '#2563eb';
        $noteHtml = $notes ? '<p style="margin: 8px 0 0; color: #64748b; font-size: 13px; font-style: italic;">' . htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') . '</p>' : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status Update #{$sale->sale_number}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 520px; background-color: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                    <!-- Brand Header -->
                    <tr>
                        <td style="background-color: {$primaryColor}; padding: 28px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 900;">{$storeName}</h1>
                            <p style="margin: 4px 0 0; color: rgba(255,255,255,0.85); font-size: 12px; font-weight: 600;">Order Status Update</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 32px; text-align: center;">
                            <div style="font-size: 36px; margin-bottom: 12px;">📦</div>
                            <h2 style="margin: 0 0 8px; font-size: 18px; font-weight: 800; color: #0f172a;">Your Order Status Changed</h2>
                            <p style="margin: 0 0 20px; font-size: 14px; color: #64748b;">
                                Hello <strong>{$custName}</strong>, your order <strong>#{$sale->sale_number}</strong> status has been updated:
                            </p>

                            <!-- Status Badge -->
                            <div style="background-color: #eff6ff; border: 2px solid #bfdbfe; border-radius: 14px; padding: 14px 24px; margin: 0 auto 20px; display: inline-block;">
                                <span style="font-size: 16px; font-weight: 800; color: #1d4ed8; text-transform: uppercase; letter-spacing: 0.5px;">{$statusLabel}</span>
                            </div>

                            {$noteHtml}

                            <div style="margin: 24px 0 10px;">
                                <a href="{$trackingUrl}" style="background-color: {$primaryColor}; color: #ffffff; padding: 12px 28px; border-radius: 12px; font-size: 13px; font-weight: 800; text-decoration: none; display: inline-block;">
                                    View Live Tracking &rarr;
                                </a>
                            </div>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 18px 30px; border-top: 1px solid #f1f5f9; text-align: center;">
                            <p style="margin: 0; font-size: 11px; font-weight: 600; color: #94a3b8;">
                                &copy; {$storeName}. Thank you for your business.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
