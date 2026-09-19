<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Mail\InvoiceMailable;
use App\Mail\QuotationMailable;
use App\Models\PharmacyPrescription;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\SalonAppointment;
use App\Models\Tenant;
use App\Models\TenantNotificationGateway;
use App\Services\Dispatch\DocumentDispatchService;
use App\Services\Repair\RepairNotificationService;
use App\Services\OmnichannelRegistryService;
use App\Services\DispatchChannelService;
use App\Services\Restaurant\KotDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentDispatchController extends Controller
{
    use ResolvesTenantSyncContext;

    public function getDispatchOptions(Request $request, string $type, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($this->resolveCompany($request)->id);
        $doc = $this->resolveDocumentInfo($tenant, $type, $id);
        $context = [
            'id' => $id, 'type' => $type, 'phone' => $doc['customerPhone'],
            'email' => $doc['customerEmail'], 'reference' => $doc['code'], 'message' => $doc['message'],
        ];

        return response()->json(array_merge([
            'success' => true, 'document_code' => $doc['code'],
            'customer' => ['name' => $doc['customerName'], 'phone' => $doc['customerPhone'], 'email' => $doc['customerEmail']],
            'context' => $doc['context'],
        ], $this->channelOptions($tenant, $context)), 200, ['Cache-Control' => 'no-store, private']);
    }

    public function getEnabledChannels(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($this->resolveCompany($request)->id);

        return response()->json(['success' => true] + $this->channelOptions($tenant, ['type' => 'document']),
            200, ['Cache-Control' => 'no-store, private']);
    }

    private function channelOptions(Tenant $tenant, array $context): array
    {
        $items = OmnichannelRegistryService::resolveChannels($tenant->id, $context);
        $entries = [];
        foreach ($items as $item) {
            $channel = $item['channel'];
            $device = ($item['delivery_mode'] ?? 'api') === 'device' || ! ($item['api_enabled'] ?? true);
            $target = $channel === 'email' ? ($context['email'] ?? '') : ($context['phone'] ?? '');
            $entry = array_merge($item, [
                'available' => true, 'selectable' => ! $device,
                'default' => ! $device && in_array($channel, ['whatsapp', 'sms', 'email'], true) && filled($target),
                'provider' => $device ? 'Device app' : $this->resolveProviderLabel($tenant, $channel),
                'target' => $target, 'icon' => $item['leading']['icon'] ?? 'send', 'color' => $item['leading']['color'] ?? '#38BDF8',
            ]);
            $entries[] = $entry;
        }
        $components = DispatchChannelService::groupedComponents($entries, $context);
        $batchButton = collect($components)->firstWhere('id', 'dispatch_selected_channels');

        $visibleChannels = [];
        foreach (DispatchChannelService::visibleChannels($components) as $component) {
            $key = $component['channel'] === 'custom' ? 'custom:'.$component['channel_id'] : $component['channel'];
            $visibleChannels[$key] = $component;
        }

        $groups = DispatchChannelService::splitChannels($visibleChannels);
        $deviceChannels = array_filter($visibleChannels, fn ($channel) => ($channel['delivery_mode'] ?? '') === 'device');

        return [
            'channels' => (object) $visibleChannels, 'enabled_channels' => $groups['api'],
            'device_channels' => (object) $deviceChannels, 'secondary_options' => $groups['device'],
            'multi_select' => true, 'batch_action' => $batchButton['action'] ?? null,
            'components' => $components,
            'schema' => ['type' => 'bottom_sheet', 'title' => 'Unified Dispatch', 'components' => $components],
        ];
    }

    public function dispatchDocument(Request $request, DocumentDispatchService $dispatchService): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string'],
            'document_id' => ['required'],
            'send_whatsapp' => ['sometimes', 'boolean'],
            'send_email' => ['sometimes', 'boolean'],
            'send_sms' => ['sometimes', 'boolean'],
            'api_only' => ['sometimes', 'boolean'],
            'channels' => ['sometimes', 'array'],
            'channels.*' => ['string'],
            'channel' => ['nullable', 'string'],
            'channel_id' => ['nullable', 'integer'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'recipient_phone' => ['nullable', 'string'],
            'recipient_email' => ['nullable', 'string'],
        ]);

        $channels = (array) ($validated['channels'] ?? []);
        if (empty($channels) && ! empty($validated['channel'])) {
            $channels = [$validated['channel']];
        }
        $channels = array_values(array_unique(array_map(function ($channel) use ($validated) {
            $channel = strtolower(trim($channel));
            if ($channel === 'custom' && ! empty($validated['channel_id'])) {
                return 'custom:'.$validated['channel_id'];
            }
            if (preg_match('/^(?:channel_)?custom[:_]?(\d+)$/', $channel, $match)) {
                return 'custom:'.$match[1];
            }

            return $channel;
        }, $channels)));
        foreach ($channels as $channel) {
            if (! in_array($channel, ['whatsapp', 'email', 'sms', 'webhook', 'custom_webhook'], true) && ! preg_match('/^custom:\d+$/', $channel)) {
                return response()->json(['success' => false, 'message' => 'A valid delivery channel is required.'], 422);
            }
        }
        $sendWhatsApp = $request->boolean('send_whatsapp') || in_array('whatsapp', $channels, true);
        $sendEmail = $request->boolean('send_email') || in_array('email', $channels, true);
        $sendSms = $request->boolean('send_sms') || in_array('sms', $channels, true);
        $sendWebhook = in_array('webhook', $channels, true) || in_array('custom_webhook', $channels, true);

        if (! $sendWhatsApp && ! $sendEmail && ! $sendSms && ! $sendWebhook && empty($channels)) {
            if ($request->hasAny(['channels', 'channel', 'send_whatsapp', 'send_email', 'send_sms'])) {
                return response()->json(['success' => false, 'message' => 'Select a delivery channel.'], 422);
            }
            $sendWhatsApp = true;
            $sendEmail = ! empty($validated['email']);
        }

        $tenant = Tenant::findOrFail($this->resolveCompany($request)->id);
        $doc = $this->resolveDocumentInfo($tenant, $validated['document_type'], $validated['document_id']);

        $targetPhone = ($validated['phone'] ?? null) ?: ($validated['recipient_phone'] ?? null);
        $targetPhone = $targetPhone ?: $doc['customerPhone'];
        $targetEmail = ($validated['email'] ?? null) ?: ($validated['recipient_email'] ?? null);
        $targetEmail = $targetEmail ?: $doc['customerEmail'];
        $variables = array_merge($doc['deliveryVariables'] ?? [], [
            'document_type' => $validated['document_type'],
            'document_id' => $validated['document_id'],
            'document_code' => $doc['code'],
            'document_number' => ltrim($doc['code'], '#'),
            'customer_name' => $doc['customerName'],
            'customer_phone' => $targetPhone,
            'customer_email' => $targetEmail,
            'phone' => $targetPhone,
            'email' => $targetEmail,
            'message' => $doc['message'],
            'total' => $doc['sale']?->total ?? $doc['deliveryVariables']['total'] ?? null,
        ]);

        $results = [];
        $attempt = function (string $channel, callable $send) use ($tenant, $request): array {
            if ($request->boolean('api_only')) {
                $configured = match ($channel) {
                    'whatsapp' => DispatchChannelService::isWhatsAppConfigured($tenant->id),
                    'email' => DispatchChannelService::isEmailConfigured($tenant->id),
                    'sms' => DispatchChannelService::isSmsConfigured($tenant->id),
                    default => true,
                };
                if (! $configured) {
                    return ['success' => false, 'status' => 'not_configured', 'channel' => $channel,
                        'message' => ucfirst($channel).' API is disabled or incomplete. Use the device app option.'];
                }
            }
            try {
                return $send();
            } catch (\Throwable $exception) {
                report($exception);
                return ['success' => false, 'status' => 'failed', 'channel' => $channel, 'message' => 'Delivery failed. Please try again.'];
            }
        };

        if ($sendWhatsApp) {
            $phoneToUse = $targetPhone ?: '';
            $results['whatsapp'] = $attempt('whatsapp', fn () => $dispatchService->dispatchWhatsApp(
                $tenant,
                $phoneToUse,
                $doc['message'],
                null,
            ));
        }

        if ($sendEmail) {
            $results['email'] = $attempt('email', function () use ($targetEmail, $doc, $tenant, $dispatchService) {
                $mailable = $doc['sale'] instanceof Sale
                    ? ($doc['sale']->operation_type === 'quotation' ? new QuotationMailable($doc['sale'], $tenant) : new InvoiceMailable($doc['sale'], $tenant))
                    : '<div style="font-family:sans-serif;padding:20px"><h2>'.e($doc['code']).'</h2><p>'.nl2br(e($doc['message'])).'</p></div>';

                return $dispatchService->dispatchEmail($tenant, $targetEmail ?: '', "Document {$doc['code']} from {$tenant->name}", $mailable);
            });
        }

        if ($sendSms) {
            $phoneToUse = $targetPhone ?: '';
            $results['sms'] = $attempt('sms', fn () => $dispatchService->dispatchSms(
                $tenant,
                $phoneToUse,
                $doc['message'],
            ));
        }

        if ($sendWebhook) {
            $results['webhook'] = $attempt('webhook', fn () => $dispatchService->dispatchWebhook(
                $tenant,
                isset($doc['deliveryVariables']['kot_id']) ? 'kot_created' : 'document.dispatched',
                array_merge($variables, [
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
                ])
            ));
        }

        foreach ($channels as $channel) {
            if (preg_match('/^custom:(\d+)$/', $channel, $m)) {
                $customId = (int) $m[1];
                $results['custom_' . $customId] = $attempt($channel, fn () => $dispatchService->dispatchCustom(
                    $tenant,
                    $customId,
                    $variables
                ));
            }
        }

        $success = collect($results)->contains(fn ($result) => ($result['success'] ?? false) === true);
        $failed = collect($results)->filter(fn ($result) => ! ($result['success'] ?? false))
            ->map(fn ($result) => $result['message'] ?? $result['error'] ?? 'Delivery failed.')->all();
        $successfulChannels = array_keys(array_filter($results, fn ($result) => ($result['success'] ?? false) && ($result['status'] ?? '') !== 'manual_link'));
        $skippedChannels = array_keys(array_filter($results, fn ($result) => ($result['status'] ?? '') === 'not_configured'));
        $partial = $success && count($failed) > 0;
        $deviceActions = array_values(array_filter($results, fn ($result) => ($result['status'] ?? '') === 'manual_link'));
        $single = count($results) === 1 ? reset($results) : [];
        $whatsappUrl = $results['whatsapp']['whatsapp_url'] ?? $results['whatsapp']['url'] ?? null;

        return response()->json([
            'success' => $success,
            'message' => $deviceActions ? 'Messages prepared. Complete sending in your device app.' : ($success ? ($partial ? 'Some selected channels could not be sent.' : 'Dispatched successfully via selected channels.') : ($single['message'] ?? $single['error'] ?? 'Document dispatch failed.')),
            'status' => $partial ? 'partial' : ($single['status'] ?? ($deviceActions ? 'manual_link' : ($success ? 'sent' : 'failed'))),
            'url' => $single['url'] ?? null,
            'action' => $single['action'] ?? null,
            'email_url' => $results['email']['url'] ?? null,
            'sms_url' => $results['sms']['url'] ?? null,
            'device_actions' => $deviceActions,
            'document_code' => $doc['code'],
            'document_id' => (string) $validated['document_id'],
            'document_type' => $doc['deliveryVariables']['document_type'] ?? $validated['document_type'],
            'channel' => count($results) === 1 ? array_key_first($results) : null,
            'customer' => [
                'name' => $doc['customerName'],
                'phone' => $targetPhone,
                'email' => $targetEmail,
            ],
            'channels' => $channels,
            'results' => $results,
            'successful_channels' => $successfulChannels, 'failed' => (object) $failed, 'skipped_channels' => $skippedChannels,
            'whatsapp_url' => $whatsappUrl,
        ], $success ? 200 : 422);
    }

    /** Adapt legacy single-channel and notification requests to KOT delivery. */
    public function dispatchKot(Request $request, mixed $id, ?string $channel = null): JsonResponse
    {
        $forwarded = clone $request;
        $channel ??= $request->input('channel');
        $recipient = $request->input('recipient');
        $forwarded->merge([
            'document_type' => 'kot',
            'document_id' => $id,
            'channel' => $channel,
            'phone' => $request->input('phone') ?: ($request->input('recipient_phone') ?: (in_array($channel, ['whatsapp', 'sms'], true) ? $recipient : null)),
            'email' => $request->input('email') ?: ($request->input('recipient_email') ?: ($channel === 'email' ? $recipient : null)),
        ]);

        return $this->dispatchDocument($forwarded, app(DocumentDispatchService::class));
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
        if (in_array($normalizedType, ['repair', 'ticket', 'job_sheet', 'intake', 'diagnostic_report', 'repair_invoice', 'intake_job_sheet', 'repair_ticket', 'work_order'], true)) {
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
                $customerName = $ticket->customer?->name ?: ($ticket->customer_name ?: 'Valued Customer');
                $customerPhone = $ticket->customer?->phone ?: ($ticket->customer_phone ?: null);
                $customerEmail = $ticket->customer?->email ?: ($ticket->customer_email ?: null);
                $deviceDesc = trim(($ticket->brand ?? '') . ' ' . ($ticket->model ?? ''));
                $context = implode(' • ', array_filter([$customerName, $deviceDesc, $ticket->created_at?->format('d M Y, h:i A'), $taxId ? "Tax ID: {$taxId}" : null]));
                $message = app(RepairNotificationService::class)->buildCustomerMessage($ticket);
                return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId');
            }
        }

        // 2. Kitchen / Restaurant Tickets
        if (in_array($normalizedType, ['kot', 'kitchen_order_ticket', 'kitchen-ticket', 'kitchen', 'restaurant', 'split_bill', 'dine_in_invoice'], true)) {
            $delivery = app(KotDeliveryService::class);
            $kot = $delivery->find($tenant->id, $id);
            $raw = $kot->kot_number ?: (string) $kot->id;
            $code = $this->formatDocumentCode($raw, 'KOT');
            $tableDesc = $kot->table_name ? "Table {$kot->table_name}" : 'Dine-In';
            $deliveryVariables = $delivery->variables($kot);
            $customerName = $deliveryVariables['customer_name'] ?: $tableDesc;
            $customerPhone = $deliveryVariables['phone'];
            $customerEmail = $deliveryVariables['email'];
            $context = implode(' • ', array_filter([$tableDesc, $kot->created_at?->format('d M Y, h:i A')]));
            $message = $deliveryVariables['message'];
            return compact('code', 'customerName', 'customerPhone', 'customerEmail', 'context', 'message', 'sale', 'timestamp', 'taxId', 'deliveryVariables');
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
            $message = "Hello {$customerName}! Your document {$code} from {$tenant->name} is ready. Total: {$tenant->currency_symbol}{$sale->total}."
                ." View online: ".route($sale->operation_type === 'quotation' ? 'quotes.public' : 'sales.public', $sale->sale_number);
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
