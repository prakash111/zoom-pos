<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Sale;
use App\Services\OmnichannelRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoicePreviewController extends Controller
{
    use ResolvesTenantSyncContext;

    public function previewSheet(Request $request, int|string|null $id = null): JsonResponse
    {
        $tenantId = auth()->user()?->tenant_id
            ?? auth()->user()?->company_id
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
            ?? $request->attributes->get('company_id');

        if (! $tenantId) {
            try {
                $tenantId = $this->resolveCompany($request)->id;
            } catch (\Throwable $e) {
                $tenantId = 1;
            }
        }

        $id = $id ?? $request->input('id') ?? $request->input('invoice_id') ?? $request->input('sale_id');

        $query = Sale::with(['customer', 'tenant'])
            ->where(function ($q) use ($tenantId) {
                $q->where('company_id', $tenantId)->orWhere('tenant_id', $tenantId);
            });

        if ($id) {
            $query->where(function ($q) use ($id) {
                $q->where('id', $id)
                    ->orWhere('external_id', $id)
                    ->orWhere('sale_number', $id);
                if (is_numeric($id)) {
                    $padded = str_pad((string) $id, 4, '0', STR_PAD_LEFT);
                    $q->orWhere('sale_number', 'like', "%{$padded}")
                      ->orWhere('sale_number', 'like', "%-{$id}");
                }
            });
            $document = $query->first();
        } else {
            $document = $query->latest('id')->first();
        }

        if (! $document) {
            $document = Sale::with(['customer', 'tenant'])->latest('id')->first();
        }

        $tenant = $document?->tenant ?? Company::find($tenantId);
        $docType = 'invoice';

        // Keep the top 3 document utilities:
        $baseActions = [
            [
                'type'     => 'list_tile',
                'title'    => 'Preview & Print',
                'subtitle' => 'View the PDF, print, or share the file',
                'leading'  => ['type' => 'icon', 'icon' => 'picture_as_pdf', 'size' => 22],
                'action'   => ['type' => 'OPEN_URL', 'url' => "/tenant/documents/{$docType}/" . ($document?->id ?? 'preview') . "/pdf"],
            ],
            [
                'type'     => 'list_tile',
                'title'    => 'Print on receipt printer',
                'subtitle' => 'Bluetooth thermal printer',
                'leading'  => ['type' => 'icon', 'icon' => 'print', 'size' => 22],
                'action'   => ['type' => 'THERMAL_PRINT', 'document_id' => $document?->id ?? 0],
            ],
            [
                'type'     => 'list_tile',
                'title'    => 'Share as PDF file',
                'subtitle' => 'Send the invoice PDF via any app',
                'leading'  => ['type' => 'icon', 'icon' => 'share', 'size' => 22],
                'action'   => ['type' => 'SYSTEM_SHARE_FILE', 'url' => "/tenant/documents/{$docType}/" . ($document?->id ?? 'preview') . "/pdf"],
            ],
        ];

        // Dynamically inject the newly tested Omnichannel channels:
        $dynamicChannels = OmnichannelRegistryService::resolveChannels(
            $document?->tenant_id ?? $document?->company_id ?? $tenantId,
            [
                'id'        => $document?->id ?? 0,
                'type'      => $docType,
                'reference' => $document?->invoice_number ?? $document?->sale_number ?? 'Invoice',
                'phone'     => $document?->customer?->phone ?? $request->input('phone'),
                'email'     => $document?->customer?->email ?? $request->input('email'),
            ]
        );

        $components = array_merge($baseActions, $dynamicChannels);
        $gstin = $tenant?->gstin ?? ($tenant?->tax_id ?? (auth()->user()?->tenant?->gstin ?? 'N/A'));

        return response()->json([
            'type'       => 'bottom_sheet',
            'title'      => 'Invoice Preview',
            'header'     => [
                'title'    => $document?->invoice_number ?? $document?->sale_number ?? 'Document',
                'subtitle' => 'GSTIN: ' . $gstin,
            ],
            'components' => $components,
            'channels'   => $dynamicChannels,
            'schema'     => [
                'type'       => 'bottom_sheet',
                'title'      => 'Invoice Preview',
                'header'     => [
                    'title'    => $document?->invoice_number ?? $document?->sale_number ?? 'Document',
                    'subtitle' => 'GSTIN: ' . $gstin,
                ],
                'components' => $components,
            ],
        ]);
    }
}
