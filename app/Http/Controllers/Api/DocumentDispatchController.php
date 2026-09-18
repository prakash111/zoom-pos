<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Mail\InvoiceMailable;
use App\Models\KitchenTicket;
use App\Models\PharmacyPrescription;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\SalonAppointment;
use App\Models\Tenant;
use App\Models\TenantNotificationGateway;
use App\Services\Dispatch\DocumentDispatchService;
use App\Services\OmnichannelRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentDispatchController extends Controller
{
    use ResolvesTenantSyncContext;

    public function getDispatchOptions(Request $request, string $type, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($this->resolveCompany($request)->id);
        $doc = $this->resolveDocumentInfo($tenant, $type, $id);
        $settings = (array) ($tenant->api_settings ?? []);

        $dynamicChannels = OmnichannelRegistryService::resolveChannels($tenant->id, [
            'id' => $id,
            'type' => $type,
            'phone' => $doc['customerPhone'],
            'email' => $doc['customerEmail'],
            'reference' => $doc['code'],
        ]);

        $channels = [];
        $enabledChannelsList = [];

        foreach ($dynamicChannels as $item) {
            $ch = $item['channel'] ?? 'custom';
            $chId = $item['id'] ?? ('channel_' . $ch);
            $target = match ($ch) {
                'whatsapp', 'sms' => $doc['customerPhone'] ?: '',
                'email' => $doc['customerEmail'] ?: '',
                default => $item['subtitle'] ?? '',
            };

            $entry = [
                'id' => $chId,
                'channel' => $ch,
                'channel_id' => $item['channel_id'] ?? null,
                'available' => true,
                'default' => match ($ch) {
                    'whatsapp' => true,
                    'email' => ! empty($doc['customerEmail']),
                    'sms' => ! empty($doc['customerPhone']),
                    default => false,
                },
                'title' => $item['title'] ?? ucfirst($ch),
                'subtitle' => $item['subtitle'] ?? '',
                'provider' => $this->resolveProviderLabel($tenant, $ch),
                'target' => $target,
                'icon' => $item['leading']['icon'] ?? 'send',
                'color' => $item['leading']['color'] ?? '#38BDF8',
            ];

            $channels[$ch] = $entry;
            $enabledChannelsList[] = $entry;
        }

        if (! isset($channels['whatsapp'])) {
            $entry = [
                'id' => 'channel_whatsapp',
                'channel' => 'whatsapp',
                'available' => true,
                'default' => true,
                'title' => 'Send via WhatsApp',
                'subtitle' => $doc['customerPhone'] ?: 'Customer Phone / Kitchen Desk',
                'provider' => $this->enabled($settings, 'whatsapp_api_enabled') ? 'Custom Gateway' : 'System Service',
                'target' => $doc['customerPhone'] ?: 'Customer Phone / Kitchen Desk',
                'icon' => 'chat',
                'color' => '#25D366',
            ];
            $channels['whatsapp'] = $entry;
            $enabledChannelsList[] = $entry;
        }

        if (! isset($channels['email'])) {
            $entry = [
                'id' => 'channel_email',
                'channel' => 'email',
                'available' => true,
                'default' => ! empty($doc['customerEmail']),
                'title' => 'Send via Email',
                'subtitle' => $doc['customerEmail'] ?: 'Enter email address',
                'provider' => $this->enabled($settings, 'smtp_enabled') ? 'Custom SMTP' : 'System Mailer',
                'target' => $doc['customerEmail'] ?: 'Enter email address',
                'icon' => 'email',
                'color' => '#818CF8',
            ];
            $channels['email'] = $entry;
            $enabledChannelsList[] = $entry;
        }

        return response()->json([
            'success' => true,
            'document_code' => $doc['code'],
            'customer' => [
                'name' => $doc['customerName'],
                'phone' => $doc['customerPhone'],
                'email' => $doc['customerEmail'],
            ],
            'context' => $doc['context'],
            'channels' => $channels,
            'enabled_channels' => $enabledChannelsList,
            'components' => $dynamicChannels,
        ]);
    }

    public function getEnabledChannels(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($this->resolveCompany($request)->id);
        $settings = (array) ($tenant->api_settings ?? []);

        $dynamicChannels = OmnichannelRegistryService::resolveChannels($tenant->id, [
            'type' => 'document',
            'id' => null,
            'phone' => null,
            'email' => null,
        ]);

        $channels = [];
        $enabledChannelsList = [];

        foreach ($dynamicChannels as $item) {
            $ch = $item['channel'] ?? 'custom';
            $chId = $item['id'] ?? ('channel_' . $ch);
            $entry = [
                'id' => $chId,
                'channel' => $ch,
                'channel_id' => $item['channel_id'] ?? null,
                'available' => true,
                'title' => $item['title'] ?? ucfirst($ch),
                'subtitle' => $item['subtitle'] ?? '',
                'provider' => $this->resolveProviderLabel($tenant, $ch),
                'icon' => $item['leading']['icon'] ?? 'send',
                'color' => $item['leading']['color'] ?? '#38BDF8',
            ];
            $channels[$ch] = $entry;
            $enabledChannelsList[] = $entry;
        }

        if (! isset($channels['whatsapp'])) {
            $entry = [
                'id' => 'channel_whatsapp',
                'channel' => 'whatsapp',
                'available' => true,
                'title' => 'Send via WhatsApp',
                'subtitle' => 'Customer Phone',
                'provider' => $this->enabled($settings, 'whatsapp_api_enabled') ? 'Custom Gateway' : 'System Service',
                'icon' => 'chat',
                'color' => '#25D366',
            ];
            $channels['whatsapp'] = $entry;
            $enabledChannelsList[] = $entry;
        }

        if (! isset($channels['email'])) {
            $entry = [
                'id' => 'channel_email',
                'channel' => 'email',
                'available' => true,
                'title' => 'Send via Email',
                'subtitle' => 'Customer Email',
                'provider' => $this->enabled($settings, 'smtp_enabled') ? 'Custom SMTP' : 'System Mailer',
                'icon' => 'email',
                'color' => '#818CF8',
            ];
            $channels['email'] = $entry;
            $enabledChannelsList[] = $entry;
        }

        return response()->json([
            'success' => true,
            'channels' => $channels,
            'enabled_channels' => $enabledChannelsList,
            'components' => $dynamicChannels,
        ]);
    }

    public function dispatchDocument(Request $request, DocumentDispatchService $dispatchService): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string'],
            'document_id' => ['required'],
            'send_whatsapp' => ['sometimes', 'boolean'],
            'send_email' => ['sometimes', 'boolean'],
            'send_sms' => ['sometimes', 'boolean'],
            'channels' => ['sometimes', 'array'],
            'channels.*' => ['string'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
        ]);

        $channels = (array) ($validated['channels'] ?? []);
        $sendWhatsApp = $request->boolean('send_whatsapp') || in_array('whatsapp', $channels, true);
        $sendEmail = $request->boolean('send_email') || in_array('email', $channels, true);
        $sendSms = $request->boolean('send_sms') || in_array('sms', $channels, true);
        $sendWebhook = in_array('webhook', $channels, true) || in_array('custom_webhook', $channels, true);

        if (! $sendWhatsApp && ! $sendEmail && ! $sendSms && ! $sendWebhook && empty($channels)) {
            $sendWhatsApp = true;
            $sendEmail = ! empty($validated['email']);
        }

        $tenant = Tenant::findOrFail($this->resolveCompany($request)->id);
        $doc = $this->resolveDocumentInfo($tenant, $validated['document_type'], $validated['document_id']);

        $targetPhone = ! empty($validated['phone']) ? $validated['phone'] : $doc['customerPhone'];
        $targetEmail = ! empty($validated['email']) ? $validated['email'] : $doc['customerEmail'];

        $results = [];

        if ($sendWhatsApp) {
            $phoneToUse = $targetPhone ?: '0000000000';
            $results['whatsapp'] = $dispatchService->dispatchWhatsApp(
                $tenant,
                $phoneToUse,
                $doc['message'],
                null,
            );
        }

        if ($sendEmail) {
            $emailToUse = $targetEmail ?: "customer@{$tenant->domain}";
            $mailable = $doc['sale'] instanceof Sale
                ? new InvoiceMailable($doc['sale'], $tenant)
                : "<div style='font-family:sans-serif;padding:20px;background:#f8fafc;color:#0f172a;'><h2 style='color:#10b981;'>{$doc['code']}</h2><p>{$doc['message']}</p></div>";

            $results['email'] = $dispatchService->dispatchEmail(
                $tenant,
                $emailToUse,
                "Document {$doc['code']} from {$tenant->name}",
                $mailable,
            );
        }

        if ($sendSms) {
            $phoneToUse = $targetPhone ?: '0000000000';
            $results['sms'] = $dispatchService->dispatchSms(
                $tenant,
                $phoneToUse,
                $doc['message'],
            );
        }

        if ($sendWebhook) {
            $results['webhook'] = $dispatchService->dispatchWebhook(
                $tenant,
                'document.dispatched',
                [
                    'document_type' => $validated['document_type'],
                    'document_id' => $validated['document_id'],
                    'document_code' => $doc['code'],
                    'customer' => [
                        'name' => $doc['customerName'],
                        'phone' => $targetPhone,
                        'email' => $targetEmail,
                    ],
                    'message' => $doc['message'],
                    'total' => $doc['sale']?->total,
                ]
            );
        }

        foreach ($channels as $channel) {
            if (preg_match('/^(?:channel_)?custom:?(\d+)$/', $channel, $m)) {
                $customId = (int) $m[1];
                $results['custom_' . $customId] = $dispatchService->dispatchCustom(
                    $tenant,
                    $customId,
                    [
                        'document_type' => $validated['document_type'],
                        'document_id' => $validated['document_id'],
                        'document_code' => $doc['code'],
                        'customer_name' => $doc['customerName'],
                        'customer_phone' => $targetPhone,
                        'customer_email' => $targetEmail,
                        'total' => $doc['sale']?->total,
                    ]
                );
            }
        }

        $whatsappUrl = $results['whatsapp']['whatsapp_url'] ?? $results['whatsapp']['url'] ?? null;

        return response()->json([
            'success' => true,
            'message' => 'Dispatched successfully via selected channels.',
            'document_code' => $doc['code'],
            'customer' => [
                'name' => $doc['customerName'],
                'phone' => $targetPhone,
                'email' => $targetEmail,
            ],
            'results' => $results,
            'whatsapp_url' => $whatsappUrl,
        ], 200);
    }

    protected function resolveDocumentInfo(Tenant $tenant, string $type, string|int $id): array
    {
        $normalizedType = strtolower(trim($type));
        $sale = null;
        $code = null;
        $customerName = null;
        $customerPhone = null;
        $customerEmail = null;
        $context = null;
        $timestamp = now()->toIso8601String();
        $taxId = $tenant->tax_id ?? $tenant->tax_number ?? $tenant->gst_number ?? null;

        // 1. Repair / Service Tickets
        if (in_array($normalizedType, ['repair', 'ticket', 'job_sheet', 'intake', 'diagnostic_report', 'repair_invoice', 'intake_job_sheet'], true)) {
            $ticket = RepairTicket::withoutGlobalScope('company')
                ->where('company_id', $tenant->id)
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                        ->orWhere('ticket_number', (string) $id);
                })
                ->first();

            if ($ticket) {
                $raw = $ticket->ticket_number ?: (string) $ticket->id;
                $code = $this->formatDocumentCode($raw, 'REP');
                $customerName = $ticket->customer_name ?: ($ticket->customer?->name ?? 'Valued Customer');
                $customerPhone = $ticket->customer_phone ?: ($ticket->customer?->phone ?? null);
                $customerEmail = $ticket->customer_email ?: ($ticket->customer?->email ?? null);
                $deviceDesc = trim(($ticket->brand ?? '') . ' ' . ($ticket->model ?? ''));
                $context = implode(' • ', array_filter([$customerName, $deviceDesc, $ticket->created_at?->format('d M Y, h:i A'), $taxId ? "Tax ID: {$taxId}" : null]));
                $message = "Hello {$customerName}! Your repair service sheet {$code} for {$deviceDesc} at {$tenant->name} is ready. Status: " . ucfirst($ticket->status ?? 'Intake');
                return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
            }
        }

        // 2. Kitchen / Restaurant Tickets
        if (in_array($normalizedType, ['kot', 'kitchen', 'restaurant', 'split_bill', 'dine_in_invoice'], true)) {
            $kot = KitchenTicket::withoutGlobalScope('company')
                ->where('company_id', $tenant->id)
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                        ->orWhere('kot_number', (string) $id);
                })
                ->first();

            if ($kot) {
                $raw = $kot->kot_number ?: (string) $kot->id;
                $code = $this->formatDocumentCode($raw, 'KOT');
                $tableDesc = $kot->table_name ? "Table {$kot->table_name}" : 'Dine-In';
                $customerName = $tableDesc;
                $context = implode(' • ', array_filter([$tableDesc, $kot->created_at?->format('d M Y, h:i A')]));
                $message = "KOT Order {$code} for {$tableDesc} at {$tenant->name} has been intimating kitchen.";
                return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
            }
        }

        // 3. Pharmacy / Prescriptions
        if (in_array($normalizedType, ['prescription', 'prescription_slip', 'rx', 'bill_of_supply', 'patient_invoice', 'pharmacy'], true)) {
            $rx = PharmacyPrescription::withoutGlobalScope('company')
                ->where('company_id', $tenant->id)
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                        ->orWhere('prescription_number', (string) $id)
                        ->orWhere('prescription_code', (string) $id)
                        ->orWhere('rx_number', (string) $id);
                })
                ->first();

            if ($rx) {
                $rawCode = $rx->prescription_number ?: ($rx->prescription_code ?: ($rx->rx_number ?: (string) $rx->id));
                $code = $this->formatDocumentCode($rawCode, 'RX');
                $customerName = $rx->patient_name ?: 'Patient';
                $customerPhone = $rx->patient_phone ?: ($rx->customer?->phone ?? null);
                $customerEmail = $rx->patient_email ?: ($rx->customer?->email ?? null);
                $docDesc = $rx->doctor_name ? "Dr. {$rx->doctor_name}" : null;
                $context = implode(' • ', array_filter([$customerName, $docDesc, $rx->created_at?->format('d M Y, h:i A'), $taxId ? "Tax ID: {$taxId}" : null]));
                $message = "Hello {$customerName}! Your prescription record {$code} from {$tenant->name} is ready.";
                return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
            }
        }

        // 4. Salon Appointments
        if (in_array($normalizedType, ['salon', 'appointment', 'treatment_estimation', 'package'], true)) {
            $apt = SalonAppointment::withoutGlobalScope('company')
                ->where('company_id', $tenant->id)
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                        ->orWhere('appointment_number', (string) $id);
                })
                ->first();

            if ($apt) {
                $rawCode = $apt->appointment_number ?: (string) $apt->id;
                $code = $this->formatDocumentCode($rawCode, 'APT');
                $customerName = $apt->customer_name ?: 'Client';
                $customerPhone = $apt->customer_phone ?: null;
                $customerEmail = $apt->customer_email ?: null;
                $context = implode(' • ', array_filter([$customerName, $apt->starts_at?->format('d M Y, h:i A'), $taxId ? "Tax ID: {$taxId}" : null]));
                $message = "Hello {$customerName}! Your appointment confirmation {$code} from {$tenant->name} is booked.";
                return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
            }
        }

        // 5. Repair Tickets / Job Sheets / Work Orders
        if (in_array($normalizedType, ['repair', 'ticket', 'job_sheet', 'repair_ticket', 'work_order'], true)) {
            $ticket = \App\Models\RepairTicket::withoutGlobalScope('company')
                ->with(['customer', 'company'])
                ->where('company_id', $tenant->id)
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                        ->orWhere('ticket_number', (string) $id);
                })
                ->first();

            if ($ticket) {
                $rawCode = (string) $ticket->ticket_number ?: (string) $ticket->id;
                $code = $this->formatDocumentCode($rawCode, 'REP');
                $customer = $ticket->customer;
                $customerName = $ticket->customer_name ?: ($customer?->name ?? 'Customer');
                $customerPhone = $ticket->customer_phone ?: ($customer?->phone ?? null);
                $customerEmail = $customer?->email ?? ($ticket->customer_email ?? null);
                $device = trim(($ticket->brand ?? '') . ' ' . ($ticket->model ?? ''));
                $statusLabel = ucfirst(str_replace('_', ' ', $ticket->status ?? 'received'));
                $trackingUrl = route('repair.portal.track', $ticket->ticket_number);
                $context = implode(' • ', array_filter([
                    $customerName,
                    $device ?: null,
                    $statusLabel,
                    $taxId ? "Tax ID: {$taxId}" : null,
                ]));
                $message = "Hello {$customerName}! Your repair ticket #{$ticket->ticket_number}"
                    . ($device !== '' ? " for {$device}" : '')
                    . " from {$tenant->name} is {$statusLabel}."
                    . " Track status: {$trackingUrl}";

                return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
            }
        }

        // 5. General Sale / Quotation / Due Invoice / POS Receipt
        $sale = Sale::withoutGlobalScope('company')
            ->with(['customer', 'company'])
            ->where('company_id', $tenant->id)
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('sale_number', (string) $id)
                    ->orWhere('external_id', (string) $id);
            })
            ->first();

        if ($sale) {
            $rawCode = $sale->sale_number ?: (string) $sale->id;
            $defaultPrefix = ($sale->operation_type === 'quotation' || $normalizedType === 'quotation') ? 'QUO' : 'INV';
            $code = $this->formatDocumentCode($rawCode, $defaultPrefix);
            $customerName = $sale->customer_name ?: ($sale->customer?->name ?? 'Valued Customer');
            $customerPhone = $sale->customer?->phone ?: ($sale->customer_phone ?? null);
            $customerEmail = $sale->customer?->email ?: ($sale->customer_email ?? null);
            $taxId = $sale->tax_id ?: $taxId;
            $context = implode(' • ', array_filter([$customerName, $sale->created_at?->format('d M Y, h:i A'), $taxId ? "Tax ID: {$taxId}" : null]));
            $message = "Hello {$customerName}! Your document {$code} from {$tenant->name} is ready. Total: {$tenant->currency_symbol}{$sale->total}.";
            return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
        }

        // Fallback placeholder if record not found in specific table
        $code = str_starts_with((string) $id, '#') ? (string) $id : "#{$id}";
        $customerName = 'Valued Customer';
        $context = implode(' • ', array_filter([$customerName, now()->format('d M Y, h:i A'), $taxId ? "Tax ID: {$taxId}" : null]));
        $message = "Hello! Your document {$code} from {$tenant->name} is ready.";
        return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
    }

    protected function formatDocumentCode(string $rawCode, string $prefix): string
    {
        $rawCode = trim($rawCode);
        if (str_starts_with($rawCode, '#')) {
            return $rawCode;
        }
        $prefix = strtoupper(trim($prefix, '-'));
        if (str_starts_with(strtoupper($rawCode), "{$prefix}-") || str_starts_with(strtoupper($rawCode), "{$prefix}")) {
            return "#{$rawCode}";
        }
        return "#{$prefix}-{$rawCode}";
    }

    protected function enabled(array $settings, string $key): bool
    {
        return filter_var($settings[$key] ?? false, FILTER_VALIDATE_BOOL);
    }

    protected function resolveProviderLabel(Tenant $tenant, string $channel): string
    {
        $gw = TenantNotificationGateway::withoutGlobalScope('company')
            ->where(function ($q) use ($tenant) {
                $q->where('company_id', $tenant->id)->orWhere('tenant_id', $tenant->id);
            })
            ->where('channel', $channel === 'webhook' ? TenantNotificationGateway::CHANNEL_WEBHOOK : $channel)
            ->first();

        if ($gw && $gw->is_enabled) {
            return match ($gw->provider) {
                'meta_cloud_api' => 'Meta Cloud API',
                'twilio' => 'Twilio',
                'msg91' => 'MSG91',
                'generic_http' => 'HTTP Gateway',
                'smtp' => 'Custom SMTP',
                'webhook' => 'Webhook Dispatcher',
                'unofficial_http' => 'Private Gateway',
                default => ucwords(str_replace('_', ' ', (string) $gw->provider)),
            };
        }

        $settings = (array) ($tenant->api_settings ?? []);
        return match ($channel) {
            'whatsapp' => $this->enabled($settings, 'whatsapp_api_enabled') ? 'Custom Gateway' : 'System Service',
            'email' => $this->enabled($settings, 'smtp_enabled') ? 'Custom SMTP' : 'System Mailer',
            'sms' => $this->enabled($settings, 'sms_api_enabled') ? 'Custom SMS' : 'Android Gateway',
            'webhook' => 'External Webhook',
            default => 'Integration Service',
        };
    }
}
