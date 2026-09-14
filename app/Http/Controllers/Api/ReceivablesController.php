<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Sale;
use App\Services\DispatchChannelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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

        $tenantId = auth()->user()?->tenant_id
            ?? auth()->user()?->company_id
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
            ?? 1;

        // Lookup Invoice by ID, external_id, or sale_number
        $invoice = Invoice::with(['customer', 'company'])
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($invoiceId) {
                $q->where('id', $invoiceId)
                    ->orWhere('external_id', $invoiceId)
                    ->orWhere('sale_number', $invoiceId);
                if (is_numeric($invoiceId)) {
                    $padded = str_pad((string) $invoiceId, 4, '0', STR_PAD_LEFT);
                    $q->orWhere('sale_number', 'like', "%{$padded}")
                      ->orWhere('sale_number', 'like', "%-{$invoiceId}");
                }
            })
            ->first();

        if (! $invoice) {
            $invoice = Invoice::with(['customer', 'company'])
                ->where('tenant_id', $tenantId)
                ->findOrFail($invoiceId);
        }

        $customer = $invoice->customer;
        $customerName = $customer?->name ?: ($invoice->customer_name ?: 'Valued Customer');
        $currency = $invoice->company?->currency_symbol ?: (auth()->user()?->company?->currency_symbol ?: '₹');
        $balanceDue = (float) ($invoice->balance_due ?? $invoice->due_amount ?? $invoice->total ?? 0);
        $phone = preg_replace('/[^0-9+]/', '', (string) ($customer?->phone ?? $invoice->customer_phone ?? ''));
        $email = trim((string) ($customer?->email ?? $invoice->customer_email ?? ''));

        $invoiceNumber = $invoice->invoice_number
            ?: ($invoice->sale_number ?: 'INV-'.str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT));

        $context = [
            'type'            => 'invoice_reminder',
            'id'              => $invoice->id,
            'reference'       => $invoiceNumber,
            'phone'           => $phone,
            'email'           => $email,
            'default_message' => "Dear {$customerName}, gentle reminder from "
                .($invoice->company?->name ?? 'our team')
                .": an outstanding balance of {$currency}".number_format($balanceDue, 2)
                ." for Invoice #{$invoiceNumber} is due. Please settle your payment.",
        ];

        // Keep these utility actions identical to the POS omnichannel sheet.
        // Every row follows the SDUI list_tile/action contract so the Flutter
        // registry can render it without a reminder-specific implementation.
        $baseActions = [
            [
                'type'        => 'list_tile',
                'title'       => 'Preview & Print',
                'subtitle'    => 'View the PDF, print, or share the file',
                'leading'     => ['type' => 'icon', 'icon' => 'picture_as_pdf', 'size' => 22, 'color' => 'theme.textPrimary'],
                'action_type' => 'OPEN_URL',
                'action'      => ['type' => 'OPEN_URL', 'url' => "/tenant/documents/invoice/{$invoice->id}/pdf"],
                'text_color'  => 'theme.textPrimary',
            ],
            [
                'type'        => 'list_tile',
                'title'       => 'Print on receipt printer',
                'subtitle'    => 'Bluetooth thermal printer',
                'leading'     => ['type' => 'icon', 'icon' => 'print', 'size' => 22, 'color' => 'theme.textPrimary'],
                'action_type' => 'THERMAL_PRINT',
                'action'      => ['type' => 'THERMAL_PRINT', 'document_id' => $invoice->id, 'invoice_id' => $invoice->id],
                'text_color'  => 'theme.textPrimary',
            ],
        ];

        $channelComponents = \App\Services\OmnichannelRegistryService::resolveChannels($tenantId, $context);
        $gstin = $invoice->company?->gstin
            ?? $invoice->tenant?->gstin
            ?? (auth()->user()?->tenant?->gstin ?? 'N/A');
        $headerComponent = [
            'type' => 'section_header',
            'title' => $invoiceNumber,
            'subtitle' => 'GSTIN: '.$gstin.' · Balance Due: '.$currency.number_format($balanceDue, 2),
            'text_color' => 'theme.textPrimary',
            'divider_color' => 'theme.divider',
        ];
        $components = array_merge([$headerComponent], $baseActions, $channelComponents);

        $responsePayload = [
            'type'       => 'bottom_sheet',
            'title'      => 'Send Reminder',
            'header'     => [
                'title'    => $invoiceNumber,
                'subtitle' => 'GSTIN: '.$gstin.' · Balance Due: '.$currency.number_format($balanceDue, 2),
            ],
            'theme'      => [
                'surface'      => 'theme.surface',
                'canvas'       => 'theme.canvas',
                'divider'      => 'theme.divider',
                'text_primary' => 'theme.textPrimary',
            ],
            'components' => $components,
            'channels'   => $channelComponents,
            'schema'     => [
                'type'       => 'bottom_sheet',
                'title'      => 'Send Reminder',
                'header'     => [
                    'title'    => $invoiceNumber,
                    'subtitle' => 'GSTIN: '.$gstin.' · Balance Due: '.$currency.number_format($balanceDue, 2),
                ],
                'theme'      => [
                    'surface'      => 'theme.surface',
                    'canvas'       => 'theme.canvas',
                    'divider'      => 'theme.divider',
                    'text_primary' => 'theme.textPrimary',
                ],
                'components' => $components,
            ],
            'sale_id'        => (string) $invoice->id,
            'invoice_number' => $invoiceNumber,
            'customer_name'  => $customerName,
            'phone'          => $phone,
            'email'          => $email,
            'due_amount'     => $balanceDue,
            'formatted_due'  => $currency . number_format($balanceDue, 2),
        ];

        return response()->json($responsePayload, 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }
}
