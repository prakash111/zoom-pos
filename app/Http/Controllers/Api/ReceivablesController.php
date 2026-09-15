<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Services\Documents\TenantDocumentResolver;
use App\Services\Sdui\SchemaResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceivablesController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Dynamic Bottom Sheet Schema for Invoice Payment / Receivables Reminders.
     * GET /api/tenant/receivables/{id}/reminder-sheet
     * GET /api/v1/tenant/receivables/{id}/reminder-sheet
     * GET /api/v1/pos/receivables/{id}/reminder-sheet
     * GET /api/v1/pos/receivables/{sale}/remind
     */
    public function reminderSheet(mixed $arg1 = null, mixed $arg2 = null): JsonResponse
    {
        if ($arg1 instanceof Request) {
            $request = $arg1;
            $invoiceId = $arg2 ?? $request->route('id') ?? $request->route('sale') ?? $request->input('id');
        } else {
            $request = request();
            $invoiceId = $arg1 ?? $request->route('id') ?? $request->route('sale') ?? $request->input('id');
        }

        $tenantId = auth()->user()?->company_id
            ?? auth()->user()?->tenant_id
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
            ?? $this->resolveCompany($request)->id;

        $resolved = app(TenantDocumentResolver::class)->resolveSaleOrInvoice(
            $tenantId,
            $invoiceId,
            $request->input('document_type')
        );
        $document = $resolved['document'];
        $documentType = $resolved['type'];
        $customer = $document->customer;
        $customerName = $customer?->name ?: ($document->customer_name ?: 'Valued Customer');
        $currency = $document->company?->currency_symbol ?: (auth()->user()?->company?->currency_symbol ?: '₹');
        $balanceDue = (float) ($document->balance_due ?? $document->due_amount ?? $document->total ?? 0);
        $phone = preg_replace('/[^0-9+]/', '', (string) ($customer?->phone ?? $document->customer_phone ?? ''));
        $email = trim((string) ($customer?->email ?? $document->customer_email ?? ''));
        $documentNumber = $document->sale_number
            ?: (($documentType === 'sale' ? 'POS-' : 'INV-').str_pad((string) $document->id, 4, '0', STR_PAD_LEFT));
        $gstin = $document->company?->gstin ?: 'N/A';
        $previewEndpoint = "/api/v1/tenant/documents/{$documentType}/{$document->id}/preview-modal";
        $nativeData = SchemaResponse::postSaleActionData($document);
        $nativeData['actions_endpoint'] = "/api/v1/tenant/documents/{$documentType}/{$document->id}/actions-sheet";
        $nativeAction = [
            'type' => 'show_post_sale_sheet',
            'action_type' => 'show_post_sale_sheet',
            'data' => $nativeData,
        ];

        // Four-row compatibility schema for older SDUI/web clients. Current
        // Flutter clients use native_action/post_sale_sheet below and render
        // the same canonical POS sale bottom sheet directly.
        $components = [
            [
                'type' => 'list_tile',
                'title' => 'Preview & Print',
                'subtitle' => 'View the PDF, print, or share the file',
                'icon' => 'picture_as_pdf',
                'leading' => ['type' => 'icon', 'icon' => 'picture_as_pdf', 'size' => 24, 'color' => '#CBD5E1'],
                'action_type' => 'OPEN_RECEIPT_PREVIEW',
                'endpoint' => $previewEndpoint,
                'action' => [
                    'type' => 'OPEN_RECEIPT_PREVIEW',
                    'endpoint' => $previewEndpoint,
                    'pdf_endpoint' => $nativeData['pdf_endpoint'],
                    'document_id' => $document->id,
                    'document_type' => $documentType,
                ],
            ],
            [
                'type' => 'list_tile',
                'title' => 'Print on receipt printer',
                'subtitle' => 'Bluetooth thermal printer',
                'icon' => 'print',
                'leading' => ['type' => 'icon', 'icon' => 'print', 'size' => 24, 'color' => '#CBD5E1'],
                'action_type' => 'TRIGGER_THERMAL_PRINT',
                'data' => ['document_id' => $document->id, 'document_type' => $documentType],
                'action' => [
                    'type' => 'TRIGGER_THERMAL_PRINT',
                    'document_id' => $document->id,
                    'sale_id' => $document->id,
                    'invoice_id' => $document->id,
                    'document_type' => $documentType,
                ],
            ],
            [
                'type' => 'list_tile',
                'title' => 'Send via WhatsApp',
                'subtitle' => $phone ?: 'Enter phone number',
                'channel' => 'whatsapp',
                'icon' => 'chat',
                'leading' => ['type' => 'icon', 'icon' => 'chat', 'size' => 24, 'color' => '#22C55E'],
                'trailing' => ['type' => 'icon', 'icon' => 'send', 'size' => 16, 'color' => '#94A3B8'],
                'action_type' => 'SUBMIT_FORM',
                'endpoint' => '/api/v1/tenant/dispatch/send',
                'data' => ['channel' => 'whatsapp', 'type' => $documentType, 'id' => $document->id, 'recipient' => $phone],
                'action' => [
                    'type' => 'SUBMIT_FORM',
                    'endpoint' => '/api/v1/tenant/dispatch/send',
                    'method' => 'POST',
                    'data' => ['channel' => 'whatsapp', 'type' => $documentType, 'id' => $document->id, 'recipient' => $phone],
                ],
            ],
            [
                'type' => 'list_tile',
                'title' => 'Send via Email',
                'subtitle' => $email ?: 'Enter email address',
                'channel' => 'email',
                'icon' => 'email',
                'leading' => ['type' => 'icon', 'icon' => 'email', 'size' => 24, 'color' => '#818CF8'],
                'trailing' => ['type' => 'icon', 'icon' => 'send', 'size' => 16, 'color' => '#94A3B8'],
                'action_type' => 'SUBMIT_FORM',
                'endpoint' => '/api/v1/tenant/dispatch/send',
                'data' => ['channel' => 'email', 'type' => $documentType, 'id' => $document->id, 'recipient' => $email],
                'action' => [
                    'type' => 'SUBMIT_FORM',
                    'endpoint' => '/api/v1/tenant/dispatch/send',
                    'method' => 'POST',
                    'data' => ['channel' => 'email', 'type' => $documentType, 'id' => $document->id, 'recipient' => $email],
                ],
            ],
        ];

        $responsePayload = [
            'type' => 'bottom_sheet',
            'title' => $documentNumber,
            'subtitle' => 'GSTIN: '.$gstin,
            'header' => [
                'title' => $documentNumber,
                'subtitle' => 'GSTIN: '.$gstin,
            ],
            'background_color' => '#131E29',
            'style' => ['backgroundColor' => '#131E29'],
            'theme' => [
                'surface' => '#131E29',
                'canvas' => '#0B1120',
                'divider' => '#334155',
                'text_primary' => '#F8FAFC',
                'text_secondary' => '#94A3B8',
            ],
            'components' => $components,
            'channels' => array_values(array_filter($components, fn (array $component) => isset($component['channel']))),
            'native_action' => $nativeAction,
            'post_sale_sheet' => [
                'action' => 'show_post_sale_sheet',
                'data' => $nativeData,
            ],
            'schema' => [
                'type' => 'bottom_sheet',
                'title' => $documentNumber,
                'subtitle' => 'GSTIN: '.$gstin,
                'header' => [
                    'title' => $documentNumber,
                    'subtitle' => 'GSTIN: '.$gstin,
                ],
                'background_color' => '#131E29',
                'style' => ['backgroundColor' => '#131E29'],
                'theme' => [
                    'surface' => '#131E29',
                    'canvas' => '#0B1120',
                    'divider' => '#334155',
                    'text_primary' => '#F8FAFC',
                    'text_secondary' => '#94A3B8',
                ],
                'components' => $components,
            ],
            'success' => true,
            'document_id' => (string) $document->id,
            'document_type' => $documentType,
            'sale_id' => (string) $document->id,
            'invoice_number' => $documentNumber,
            'document_number' => $documentNumber,
            'customer_name' => $customerName,
            'phone' => $phone,
            'email' => $email,
            'due_amount' => $balanceDue,
            'formatted_due' => $currency.number_format($balanceDue, 2),
        ];

        return response()->json($responsePayload, 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
