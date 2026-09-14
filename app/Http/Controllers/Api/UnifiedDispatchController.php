<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\Sale;
use App\Services\Notifications\TenantNotificationDispatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
        $requestedType = match ($validated['type']) {
            'quotation', 'quote' => 'quotation',
            'invoice_reminder' => 'invoice',
            'sale', 'receipt' => 'sale',
            default => 'invoice',
        };
        $channel = $validated['channel'] === 'webhook'
            ? 'custom_webhook'
            : $validated['channel'];
        $recipient = trim((string) ($validated['recipient'] ?? ''));

        [$document, $resolvedType] = $this->resolveDocument($company, $requestedType, $validated['id']);

        $phone = in_array($channel, ['sms', 'whatsapp'], true)
            ? ($recipient ?: ($document->customer?->phone ?? $document->customer_phone ?? null))
            : null;
        $email = $channel === 'email'
            ? ($recipient ?: ($document->customer?->email ?? $document->customer_email ?? null))
            : null;

        $dispatcher = app(TenantNotificationDispatcherService::class);
        $results = $resolvedType === 'quotation'
            ? $dispatcher->dispatchQuotation($company, $document, [$channel], $phone, $email)
            : $dispatcher->dispatchReceipt($company, $document, [$channel], $phone, $email);

        $resultKey = $channel === 'custom_webhook' ? 'webhook' : $channel;
        $result = $results[$resultKey] ?? [
            'success' => false,
            'status' => 'not_dispatched',
            'message' => in_array($channel, ['sms', 'whatsapp', 'email'], true) && empty($recipient) && empty($phone) && empty($email)
                ? 'A recipient is required for this channel.'
                : 'The configured channel did not dispatch this document.',
        ];
        $success = (bool) ($result['success'] ?? false);

        // A successful manual dispatch advances quotations out of Draft. Do
        // not change invoice/sale lifecycle statuses here: those statuses are
        // governed by payment/completion workflows, not notification delivery.
        if ($success && $resolvedType === 'quotation' && in_array(strtolower((string) $document->status), ['', 'draft'], true)) {
            $document->forceFill(['status' => 'sent'])->save();
        }

        AuditLog::record('document.dispatched', $company->id, $this->resolveUser($request, $company)?->id, [
            'channel' => $channel,
            'document_type' => $resolvedType,
            'document_id' => (string) $document->id,
            'document_number' => $document->sale_number ?? (string) $document->id,
            'recipient' => $recipient ?: ($phone ?: $email),
            'success' => $success,
        ]);

        $responsePayload = [
            'success' => $success,
            'channel' => $resultKey,
            'document_type' => $resolvedType,
            'document_id' => (string) $document->id,
            'message' => $result['message'] ?? $result['error'] ?? ($success
                ? 'Document dispatched successfully.'
                : 'Document dispatch failed.'),
        ];

        if ($channel === 'whatsapp') {
            $waUrl = $result['whatsapp_url'] ?? $result['url'] ?? null;
            if (! empty($waUrl)) {
                $responsePayload['whatsapp_url'] = $waUrl;
                $responsePayload['url'] = $waUrl;
            }
        } else {
            // For email, sms, webhooks: explicitly ensure no client intent / url is returned
            $responsePayload['action'] = null;
            $responsePayload['intent'] = null;
            $responsePayload['url'] = null;
            $responsePayload['whatsapp_url'] = null;
            $responsePayload['redirect_url'] = null;
        }

        return response()->json($responsePayload, $success ? 200 : 422);
    }

    /**
     * Polymorphic document resolver supporting ID, sale_number, external_id,
     * and numeric suffix lookups across Quotation and Sale models.
     *
     * @return array{0: Sale, 1: string}
     */
    protected function resolveDocument(Company $company, string $type, mixed $id): array
    {
        $companyId = $company->id;
        $isQuotation = in_array(strtolower($type), ['quotation', 'quote'], true);

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
            $query = Sale::query()
                ->withoutGlobalScope('company')
                ->with(['customer', 'company'])
                ->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                    if (Schema::hasColumn('sales', 'tenant_id')) {
                        $q->orWhere('tenant_id', $companyId);
                    }
                });
            $applyIdentifier($query, $id);

            return $query;
        };

        $document = null;

        // 1. If quotation requested, prioritize quotation lookup
        if ($isQuotation) {
            $document = (clone $baseQuery())->where('operation_type', 'quotation')->first();
            if (! $document && class_exists(Quotation::class)) {
                $qQuery = Quotation::withoutGlobalScope('company')->with(['customer', 'company']);
                $qQuery->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                    if (Schema::hasColumn('sales', 'tenant_id')) {
                        $q->orWhere('tenant_id', $companyId);
                    }
                });
                $applyIdentifier($qQuery, $id);
                $document = $qQuery->first();
            }
        }

        // 2. If not found or if quotation not explicitly requested, try sale lookup
        if (! $document && ! $isQuotation) {
            $document = (clone $baseQuery())
                ->where(function ($operation) {
                    $operation->where('operation_type', 'sale')->orWhereNull('operation_type');
                })
                ->first();
        }

        // 3. Fallback: Search without operation_type filter for this company
        if (! $document) {
            $document = (clone $baseQuery())->first();
        }

        // 4. Fallback across all companies if company scope differed (e.g. integer tenant_id vs emp_...)
        if (! $document) {
            $globalQuery = Sale::query()->withoutGlobalScope('company')->with(['customer', 'company']);
            $applyIdentifier($globalQuery, $id);
            if ($isQuotation) {
                $globalQuery->where('operation_type', 'quotation');
            }
            $document = $globalQuery->first();
        }

        if (! $document) {
            $globalAny = Sale::query()->withoutGlobalScope('company')->with(['customer', 'company']);
            $applyIdentifier($globalAny, $id);
            $document = $globalAny->first();
        }

        if (! $document) {
            abort(404, 'Document not found for dispatch.');
        }

        $effectiveType = $type;
        if ($document->operation_type === 'quotation' || $isQuotation) {
            $effectiveType = 'quotation';
        }

        return [$document, $effectiveType];
    }
}
