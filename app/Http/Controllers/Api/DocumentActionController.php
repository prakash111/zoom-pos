<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\Sale;
use App\Services\OmnichannelRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentActionController extends Controller
{
    use ResolvesTenantSyncContext;

    public function actionsSheet(Request $request, string $docType, int|string|null $id = null): JsonResponse
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

        $id = $id ?? $request->input('id') ?? $request->input('document_id') ?? $request->input('sale_id');

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

        $normalizedType = strtolower($docType);
        $document = null;

        if (in_array($normalizedType, ['quotation', 'quote'], true)) {
            $q = Quotation::with(['customer', 'tenant'])
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId)->orWhere('tenant_id', $tenantId);
                });
            if ($id) {
                $applyIdentifier($q, $id);
            }
            $document = $q->latest('id')->first();

            if (! $document) {
                $saleQ = Sale::with(['customer', 'tenant'])
                    ->where(function ($q) use ($tenantId) {
                        $q->where('company_id', $tenantId)->orWhere('tenant_id', $tenantId);
                    })
                    ->where('operation_type', 'quotation');
                if ($id) {
                    $applyIdentifier($saleQ, $id);
                }
                $document = $saleQ->latest('id')->first();
            }
        } else {
            $saleQ = Sale::with(['customer', 'tenant'])
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId)->orWhere('tenant_id', $tenantId);
                })
                ->where(function ($operation) {
                    $operation->where('operation_type', 'sale')->orWhereNull('operation_type');
                });
            if ($id) {
                $applyIdentifier($saleQ, $id);
            }
            $document = $saleQ->latest('id')->first();
        }

        if (! $document) {
            $anyQ = Sale::with(['customer', 'tenant'])
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId)->orWhere('tenant_id', $tenantId);
                });
            if ($id) {
                $applyIdentifier($anyQ, $id);
            }
            $document = $anyQ->latest('id')->first();
        }

        if (! $document && $id) {
            $globalQ = Sale::withoutGlobalScope('company')->with(['customer', 'tenant']);
            $applyIdentifier($globalQ, $id);
            $document = $globalQ->latest('id')->first();
        }

        if (! $document) {
            $document = Sale::withoutGlobalScope('company')->with(['customer', 'tenant'])->latest('id')->firstOrFail();
        }

        $tenant = $document->tenant ?? Company::find($tenantId);
        $sheetTitle = in_array($normalizedType, ['quotation', 'quote']) ? 'Quotation Preview' : 'Invoice Preview';

        // Keep the top 3 document utilities:
        $baseActions = [
            [
                'type'     => 'list_tile',
                'title'    => 'Preview & Print',
                'subtitle' => 'View the PDF, print, or share the file',
                'leading'  => ['type' => 'icon', 'icon' => 'picture_as_pdf', 'size' => 22],
                'action'   => ['type' => 'OPEN_URL', 'url' => "/tenant/documents/{$docType}/{$document->id}/pdf"],
            ],
            [
                'type'     => 'list_tile',
                'title'    => 'Print on receipt printer',
                'subtitle' => 'Bluetooth thermal printer',
                'leading'  => ['type' => 'icon', 'icon' => 'print', 'size' => 22],
                'action'   => ['type' => 'THERMAL_PRINT', 'document_id' => $document->id],
            ],
            [
                'type'     => 'list_tile',
                'title'    => 'Share as PDF file',
                'subtitle' => 'Send the invoice PDF via any app',
                'leading'  => ['type' => 'icon', 'icon' => 'share', 'size' => 22],
                'action'   => ['type' => 'SYSTEM_SHARE_FILE', 'url' => "/tenant/documents/{$docType}/{$document->id}/pdf"],
            ],
        ];

        // Dynamically resolve all configured channels
        $dynamicChannels = OmnichannelRegistryService::resolveChannels($tenantId, [
            'id'        => $document->id,
            'type'      => $docType,
            'reference' => $document->invoice_number ?? $document->quotation_number ?? $document->id,
            'phone'     => $document->customer?->phone,
            'email'     => $document->customer?->email,
        ]);

        $components = array_merge($baseActions, $dynamicChannels);

        return response()->json([
            'type'       => 'bottom_sheet',
            'title'      => $sheetTitle,
            'header'     => [
                'title'    => $document->invoice_number ?? $document->quotation_number ?? 'Document',
                'subtitle' => 'GSTIN: ' . ($tenant?->gstin ?? ($tenant?->tax_id ?? 'N/A')),
            ],
            'components' => $components,
            'channels'   => $dynamicChannels,
            'schema'     => [
                'type'       => 'bottom_sheet',
                'title'      => $sheetTitle,
                'header'     => [
                    'title'    => $document->invoice_number ?? $document->quotation_number ?? 'Document',
                    'subtitle' => 'GSTIN: ' . ($tenant?->gstin ?? ($tenant?->tax_id ?? 'N/A')),
                ],
                'components' => $components,
            ],
        ]);
    }
}
