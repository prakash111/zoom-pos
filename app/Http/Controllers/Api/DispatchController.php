<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CustomNotificationChannel;
use App\Models\Sale;
use App\Services\Delivery\WebhookDispatchService;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Notifications\TenantNotificationDispatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class DispatchController extends Controller
{
    use ResolvesTenantSyncContext;

    /** Dedicated SDUI endpoint used by the custom SMS channel tile. */
    public function dispatchSms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:invoice,sale,quotation'],
            'id' => ['required'],
            'phone' => ['required', 'string'],
            'message' => ['nullable', 'string'],
        ]);

        $request->merge([
            'channel' => 'sms',
            'recipient' => $validated['phone'],
        ]);

        return $this->dispatchDocument($request, $validated['type'], (string) $validated['id']);
    }

    /** Dedicated SDUI endpoint used by the custom SMTP channel tile. */
    public function dispatchEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:invoice,sale,quotation'],
            'id' => ['required'],
            'email' => ['required', 'email'],
        ]);

        $request->merge([
            'channel' => 'email',
            'recipient' => $validated['email'],
        ]);

        return $this->dispatchDocument($request, $validated['type'], (string) $validated['id']);
    }

    /**
     * Unified Document Dispatch endpoint (SMS Gateway, WhatsApp, Email, Custom Webhook).
     * Handles Invoices, Quotations, Sales, and Due Payment Reminders.
     *
     * POST /api/tenant/dispatch/{type}/{id}
     * POST /api/v1/tenant/dispatch/{type}/{id}
     * POST /api/v1/pos/dispatch/{type}/{id}
     * POST /api/dispatch/{type}/{id}
     */
    public function dispatchDocument(Request $request, string $type, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $companyId = $company->id;

        $validator = Validator::make($request->all(), [
            'channel' => ['nullable', 'string', 'in:whatsapp,email,sms,custom'],
            'recipient' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
            'custom_message' => ['nullable', 'string'],
            'channel_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $channel = strtolower((string) $request->input('channel', 'sms'));
        $isQuote = in_array(strtolower($type), ['quotation', 'quote'], true);
        $isReminder = in_array(strtolower($type), ['reminder', 'invoice_reminder', 'due_reminder', 'receivable'], true);

        $applyIdentifier = function ($query, $rawId) {
            $query->where(function ($q) use ($rawId) {
                $q->where('id', $rawId)
                    ->orWhere('external_id', (string) $rawId)
                    ->orWhere('sale_number', (string) $rawId);

                if (is_numeric($rawId)) {
                    $padded = str_pad((string) $rawId, 4, '0', STR_PAD_LEFT);
                    $q->orWhere('sale_number', 'like', "%{$padded}")
                        ->orWhere('sale_number', 'like', "%-{$rawId}")
                        ->orWhere('sale_number', 'like', "QUO-%{$padded}")
                        ->orWhere('sale_number', 'like', "INV-%{$padded}");
                }

                if (is_string($rawId) && preg_match('/(\d+)$/', $rawId, $matches)) {
                    $digits = (int) $matches[1];
                    $padded = str_pad((string) $digits, 4, '0', STR_PAD_LEFT);
                    $q->orWhere('id', $digits)
                        ->orWhere('sale_number', 'like', "%{$padded}");
                }
            });
        };

        $baseQuery = function () use ($companyId, $applyIdentifier, $id) {
            $query = Sale::withoutGlobalScope('company')
                ->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                    if (Schema::hasColumn('sales', 'tenant_id')) {
                        $q->orWhere('tenant_id', $companyId);
                    }
                })
                ->with(['customer', 'company']);
            $applyIdentifier($query, $id);

            return $query;
        };

        $doc = null;
        if ($isQuote) {
            $doc = (clone $baseQuery())->where('operation_type', 'quotation')->first();
            if (! $doc && class_exists(\App\Models\Quotation::class)) {
                $qQuery = \App\Models\Quotation::withoutGlobalScope('company')->with(['customer', 'company']);
                $qQuery->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                    if (Schema::hasColumn('sales', 'tenant_id')) {
                        $q->orWhere('tenant_id', $companyId);
                    }
                });
                $applyIdentifier($qQuery, $id);
                $doc = $qQuery->first();
            }
        } elseif (! $isReminder) {
            $doc = (clone $baseQuery())
                ->where(function ($operation) {
                    $operation->where('operation_type', 'sale')->orWhereNull('operation_type');
                })
                ->first();
        }

        if (! $doc) {
            $doc = (clone $baseQuery())->first();
        }

        if (! $doc) {
            $fallbackQuery = Sale::withoutGlobalScope('company')->with(['customer', 'company']);
            $applyIdentifier($fallbackQuery, $id);
            if ($isQuote) {
                $fallbackQuery->where('operation_type', 'quotation');
            }
            $doc = $fallbackQuery->first();
        }

        if (! $doc) {
            $fallbackAny = Sale::withoutGlobalScope('company')->with(['customer', 'company']);
            $applyIdentifier($fallbackAny, $id);
            $doc = $fallbackAny->first();
        }

        if (! $doc) {
            return response()->json([
                'success' => false,
                'error' => 'Document not found for this tenant.',
            ], 404);
        }

        if ($doc->operation_type === 'quotation') {
            $isQuote = true;
        }

        $customer = $doc->customer;
        $customerName = $customer?->name ?: ($doc->customer_name ?: 'Valued Customer');
        $docNumber = $doc->sale_number ?? (string) $id;
        $storeName = $company->trade_name ?: ($company->name ?: 'Store');

        // Notification delivery is also the quotation's manual “send” event.
        // Keep this scoped to quotations so invoice/sale payment statuses are
        // never rewritten by a dispatch action.
        $markQuotationSent = function () use ($doc, $isQuote): void {
            if ($isQuote && in_array(strtolower((string) $doc->status), ['', 'draft'], true)) {
                $doc->forceFill(['status' => 'sent'])->save();
            }
        };

        // 1. SMS dispatch via the tenant's active provider.
        if ($channel === 'sms') {
            $recipient = trim((string) ($request->input('phone') ?: ($request->input('recipient') ?: ($customer?->phone ?? $doc?->customer_phone ?? ''))));
            $cleanPhone = preg_replace('/[^0-9+]/', '', $recipient);

            if (empty($cleanPhone)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Recipient phone number is required to send SMS.',
                ], 422);
            }

            $customMsg = $request->input('message') ?: $request->input('custom_message');
            if ($customMsg) {
                $messageText = $customMsg;
            } elseif ($isReminder) {
                $messageText = app(InvoiceDeliveryService::class)->buildDueReminderMessage($doc);
            } elseif ($isQuote) {
                $currency = $company->currency_symbol ?: ($company->currency ?: '₹');
                $totalVal = number_format((float) ($doc->total_amount ?? $doc->total ?? 0), 2);
                $messageText = "Hello {$customerName},\nYour Quotation #{$docNumber} for {$currency}{$totalVal} is ready from {$storeName}.\nThank you for choosing us!";
            } else {
                $currency = $company->currency_symbol ?: ($company->currency ?: '$');
                $totalVal = number_format((float) $doc->total, 2);
                $publicLink = route('sales.public', $docNumber);
                $messageText = "Hello {$customerName}, your {$type} #{$docNumber} for {$currency}{$totalVal} is ready: {$publicLink}";
            }

            $res = app(TenantNotificationDispatcherService::class)->dispatchSms(
                $company,
                $cleanPhone,
                $messageText,
                [
                    'customer_name' => $customerName,
                    'total' => (float) ($doc->total ?? 0),
                ]
            );

            if (! ($res['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => $res['error'] ?? $res['message'] ?? 'Failed to send SMS via configured gateway.',
                    'message' => 'SMS dispatch failed.',
                    'details' => $res,
                ], 422);
            }

            $markQuotationSent();

            AuditLog::record('document.dispatched', $companyId, $this->resolveUser($request, $company)?->id, [
                'type' => 'sms',
                'document_type' => $type,
                'recipient' => $cleanPhone,
                'document_number' => $docNumber,
                'status' => 'sent',
            ]);

            return response()->json([
                'success' => true,
                'status' => 'sent',
                'channel' => 'sms',
                'message' => "SMS sent successfully to {$cleanPhone}.",
                'document_number' => $docNumber,
                'sent_at' => now()->toIso8601String(),
                'action' => null,
                'intent' => null,
                'url' => null,
                'whatsapp_url' => null,
            ]);
        }

        // 2. WhatsApp Dispatch
        if ($channel === 'whatsapp') {
            $recipient = trim((string) ($request->input('phone') ?: ($request->input('recipient') ?: ($customer?->phone ?? $doc?->customer_phone ?? ''))));
            $cleanPhone = preg_replace('/[^0-9]/', '', $recipient);

            if (empty($cleanPhone)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Recipient phone number is required for WhatsApp.',
                ], 422);
            }

            $customMsg = $request->input('message') ?: $request->input('custom_message');
            if ($customMsg) {
                $msg = $customMsg;
            } elseif ($isReminder) {
                $msg = app(InvoiceDeliveryService::class)->buildDueReminderMessage($doc);
            } elseif ($isQuote) {
                $currency = $company->currency_symbol ?: ($company->currency ?: '₹');
                $totalVal = number_format((float) ($doc->total_amount ?? $doc->total ?? 0), 2);
                $msg = "Hello {$customerName},\n\nPlease find your Quotation #{$docNumber} for {$currency}{$totalVal} from {$storeName}.\n\nThank you for choosing us!";
            } else {
                $currency = $company->currency_symbol ?: ($company->currency ?: '$');
                $totalVal = number_format((float) $doc->total, 2);
                $publicLink = route('sales.public', $docNumber);
                $msg = "Thank you for your business! Your receipt for {$docNumber} ({$currency}{$totalVal}): {$publicLink}";
            }

            $whatsappUrl = "https://wa.me/{$cleanPhone}?text=".urlencode($msg);

            $markQuotationSent();

            AuditLog::record('document.dispatched', $companyId, $this->resolveUser($request, $company)?->id, [
                'type' => 'whatsapp',
                'document_type' => $type,
                'recipient' => $cleanPhone,
                'document_number' => $docNumber,
                'status' => 'sent',
            ]);

            return response()->json([
                'success' => true,
                'status' => 'sent',
                'channel' => 'whatsapp',
                'message' => 'WhatsApp link prepared.',
                'whatsapp_url' => $whatsappUrl,
                'url' => $whatsappUrl,
                'document_number' => $docNumber,
            ]);
        }

        // 3. Email Dispatch
        if ($channel === 'email') {
            $recipient = trim((string) ($request->input('email') ?: ($request->input('recipient') ?: ($customer?->email ?? $doc?->customer_email ?? ''))));

            if (empty($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'success' => false,
                    'error' => 'A valid recipient email address is required.',
                ], 422);
            }

            $dispatcher = app(TenantNotificationDispatcherService::class);
            $result = $isQuote
                ? $dispatcher->dispatchQuotation($company, $doc, ['email'], null, $recipient)
                : $dispatcher->dispatchReceipt($company, $doc, ['email'], null, $recipient);

            $emailRes = $result['email'] ?? [];
            if (! ($emailRes['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => $emailRes['error'] ?? $emailRes['message'] ?? 'Email dispatch failed.',
                    'message' => 'Email dispatch failed.',
                    'details' => $emailRes,
                ], 422);
            }
            $emailMessage = $emailRes['message'] ?? "Email dispatched successfully to {$recipient}.";

            $markQuotationSent();

            AuditLog::record('document.dispatched', $companyId, $this->resolveUser($request, $company)?->id, [
                'type' => 'email',
                'document_type' => $type,
                'recipient' => $recipient,
                'document_number' => $docNumber,
                'status' => 'sent',
            ]);

            return response()->json([
                'success' => true,
                'status' => 'sent',
                'channel' => 'email',
                'message' => $emailMessage,
                'document_number' => $docNumber,
                'sent_at' => now()->toIso8601String(),
                'action' => null,
                'intent' => null,
                'url' => null,
                'whatsapp_url' => null,
            ]);
        }

        // 4. Custom Channel Dispatch
        if ($channel === 'custom') {
            $channelId = $request->input('channel_id');
            $customChan = CustomNotificationChannel::where('company_id', $companyId)
                ->where('is_active', true)
                ->find($channelId);

            if (! $customChan) {
                return response()->json([
                    'success' => false,
                    'error' => 'Custom notification channel not found or inactive.',
                ], 422);
            }

            $sent = app(WebhookDispatchService::class)->dispatch($customChan, [
                'customer_name' => $customerName,
                'document_type' => $type,
                'document_number' => $docNumber,
                'total' => (float) $doc->total,
                'due_amount' => (float) ($doc->due_amount ?? 0),
            ]);

            if (! $sent) {
                return response()->json([
                    'success' => false,
                    'error' => "Dispatch to {$customChan->name} failed.",
                ], 422);
            }

            $markQuotationSent();

            return response()->json([
                'success' => true,
                'channel' => 'custom',
                'message' => "Dispatched to {$customChan->name}.",
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => "Unsupported channel '{$channel}'.",
        ], 422);
    }
}
