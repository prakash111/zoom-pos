<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Notifications\TenantNotificationDispatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnifiedDispatchController extends Controller
{
    use ResolvesTenantSyncContext;

    public function dispatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:sms,whatsapp,email,webhook,custom_webhook'],
            'type' => ['required', 'string', 'in:invoice,invoice_reminder,sale,receipt,quotation,quote'],
            'id' => ['required'],
            'recipient' => ['nullable', 'string'],
        ]);

        $company = $this->resolveCompany($request);
        $type = match ($validated['type']) {
            'quotation', 'quote' => 'quotation',
            'invoice_reminder' => 'invoice',
            'sale', 'receipt' => 'sale',
            default => 'invoice',
        };
        $channel = $validated['channel'] === 'webhook'
            ? 'custom_webhook'
            : $validated['channel'];
        $recipient = trim((string) ($validated['recipient'] ?? ''));

        $document = Sale::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->when(
                $type === 'quotation',
                fn ($query) => $query->where('operation_type', 'quotation'),
                fn ($query) => $query->where(function ($operation) {
                    $operation->where('operation_type', 'sale')->orWhereNull('operation_type');
                })
            )
            ->where(function ($query) use ($validated) {
                $id = $validated['id'];
                $query->where('id', $id)
                    ->orWhere('external_id', $id)
                    ->orWhere('sale_number', $id);
            })
            ->with('customer')
            ->firstOrFail();

        $phone = in_array($channel, ['sms', 'whatsapp'], true)
            ? $recipient
            : null;
        $email = $channel === 'email' ? $recipient : null;
        $dispatcher = app(TenantNotificationDispatcherService::class);
        $results = $type === 'quotation'
            ? $dispatcher->dispatchQuotation($company, $document, [$channel], $phone, $email)
            : $dispatcher->dispatchReceipt($company, $document, [$channel], $phone, $email);

        $resultKey = $channel === 'custom_webhook' ? 'webhook' : $channel;
        $result = $results[$resultKey] ?? [
            'success' => false,
            'status' => 'not_dispatched',
            'message' => in_array($channel, ['sms', 'whatsapp', 'email'], true) && $recipient === ''
                ? 'A recipient is required for this channel.'
                : 'The configured channel did not dispatch this document.',
        ];
        $success = (bool) ($result['success'] ?? false);

        // A successful manual dispatch advances quotations out of Draft. Do
        // not change invoice/sale lifecycle statuses here: those statuses are
        // governed by payment/completion workflows, not notification delivery.
        if ($success && $type === 'quotation' && in_array(strtolower((string) $document->status), ['', 'draft'], true)) {
            $document->forceFill(['status' => 'sent'])->save();
        }

        return response()->json(array_merge($result, [
            'success' => $success,
            'channel' => $resultKey,
            'document_type' => $type,
            'document_id' => (string) $document->id,
            'message' => $result['message'] ?? $result['error'] ?? ($success
                ? 'Document dispatched successfully.'
                : 'Document dispatch failed.'),
        ]), $success ? 200 : 422);
    }
}
