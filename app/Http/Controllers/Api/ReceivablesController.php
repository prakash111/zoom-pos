<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Services\Documents\TenantDocumentResolver;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\OmnichannelRegistryService;
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
        $nativeData = SchemaResponse::postSaleActionData($document);
        $nativeData['actions_endpoint'] = "/api/v1/tenant/documents/{$documentType}/{$document->id}/actions-sheet";
        $nativeAction = [
            'type' => 'show_post_sale_sheet',
            'action_type' => 'show_post_sale_sheet',
            'data' => $nativeData,
        ];

        $message = app(InvoiceDeliveryService::class)->buildDueReminderMessage($document);
        $channels = OmnichannelRegistryService::resolveChannels($tenantId, [
            'type' => $documentType,
            'id' => $document->id,
            'reference' => $documentNumber,
            'phone' => $phone,
            'email' => $email,
            'message' => $message,
            'subject' => 'Payment Reminder #'.$documentNumber,
        ]);
        $components = \App\Services\DispatchChannelService::groupedComponents($channels, [
            'type' => $documentType, 'id' => $document->id, 'phone' => $phone, 'email' => $email,
        ]);

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
