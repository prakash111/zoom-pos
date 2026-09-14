<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\OmnichannelRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentPreviewController extends Controller
{
    use ResolvesTenantSyncContext;

    private const FORMATS = [
        'a4' => ['label' => 'Standard A4', 'width' => 210, 'height' => 297, 'unit' => 'mm'],
        'thermal_80mm' => ['label' => '80mm POS', 'width' => 80, 'height' => null, 'unit' => 'mm'],
        'thermal_58mm' => ['label' => '58mm Receipt', 'width' => 58, 'height' => null, 'unit' => 'mm'],
        'slip' => ['label' => 'Mobile Slip', 'width' => 360, 'height' => 640, 'unit' => 'px'],
    ];

    public function previewModal(Request $request, string $type = 'invoice', int|string $id = 0): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $format = $request->input('format', 'a4'); // 'a4', 'thermal_80mm', 'thermal_58mm', 'slip'
        $format = match (strtolower((string) $format)) {
            '80mm', 'thermal-80mm' => 'thermal_80mm',
            '58mm', 'thermal-58mm' => 'thermal_58mm',
            'mobile', 'mobile_slip' => 'slip',
            default => strtolower((string) $format),
        };
        if (! array_key_exists($format, self::FORMATS)) {
            $format = 'a4';
        }

        $normalizedType = $this->normalizeType($type);
        $document = $this->findDocument($tenantId, $normalizedType, $id);

        $reference = $document->sale_number ?: "DOC-{$document->id}";
        $customer = $document->customer;
        $customerName = $customer?->name ?? ($document->customer_name ?? 'Walk-in Client');
        $phone = $customer?->phone ?? data_get($document->gst_invoice, 'customer_phone');
        $email = $customer?->email ?? data_get($document->gst_invoice, 'customer_email');
        $lines = $this->documentLines($document);
        $total = (float) ($document->total_amount ?? $document->total ?? 0);
        $taxAmount = (float) ($document->tax_amount ?? 0);

        // Context for omnichannel actions
        $context = [
            'type'            => $normalizedType,
            'id'              => $document->id,
            'reference'       => $reference,
            'phone'           => $phone,
            'email'           => $email,
            'default_message' => "Hello {$customerName}, here is your {$normalizedType} #{$reference} for ₹" . number_format($total, 2),
            'endpoint_prefix' => $request->is('api/*') ? '/api/v1/tenant' : '/tenant',
        ];

        $availableChannels = OmnichannelRegistryService::resolveChannels($tenantId, $context);
        $gstin = $document->tenant?->gstin ?? ($document->tenant?->tax_id ?? 'Unregistered');
        $endpointPrefix = $request->is('api/*') ? '/api/v1/tenant' : '/tenant';
        $renderUrl = url("{$endpointPrefix}/documents/{$normalizedType}/{$document->id}/render-html?format={$format}");
        $reloadEndpoint = "{$endpointPrefix}/documents/{$normalizedType}/{$document->id}/preview-modal";

        $components = [
            // 1. Paper Size Selector Bar
            [
                'type'         => 'segmented_tabs',
                'param_name'   => 'format',
                'active_value' => $format,
                'options'      => [
                    ['label' => 'Standard A4', 'value' => 'a4'],
                    ['label' => '80mm POS', 'value' => 'thermal_80mm'],
                    ['label' => '58mm Receipt', 'value' => 'thermal_58mm'],
                    ['label' => 'Mobile Slip', 'value' => 'slip'],
                ],
                'action' => [
                    'type'             => 'RELOAD_COMPONENT',
                    'endpoint'         => $reloadEndpoint,
                    'refresh_in_place' => true,
                ],
            ],

            // 2. Scaled Document Preview Card
            [
                'type'             => 'document_preview_card',
                'format'           => $format,
                'paper'            => self::FORMATS[$format],
                'background_color' => 'theme.surface',
                'border_color'     => 'theme.divider',
                'text_color'       => 'theme.textPrimary',
                'style' => [
                    'backgroundColor' => 'theme.surface',
                    'borderColor'     => 'theme.divider',
                    'borderRadius'    => 12,
                    'padding'         => 16,
                    'marginVertical'  => 12,
                ],
                'render_url' => $renderUrl,
                'document'   => [
                    'company_name'    => $document->tenant?->display_name ?? ($document->tenant?->name ?? 'Store'),
                    'tax_label'       => $document->tenant?->tax_id_label ?: 'GSTIN',
                    'tax_id'          => $gstin,
                    'document_label'  => $normalizedType === 'quotation' ? 'QUOTATION' : ($normalizedType === 'sale' ? 'SALES RECEIPT' : 'TAX INVOICE'),
                    'reference'       => $reference,
                    'date'            => optional($document->created_at)->format('d M Y'),
                    'customer_name'   => $customerName,
                    'lines'           => $lines,
                    'currency_symbol' => $document->tenant?->currency_symbol ?: ($document->tenant?->currency ?: '₹'),
                    'subtotal'        => (float) $document->subtotal,
                    'discount'        => (float) ($document->discount ?? 0),
                    'tax_amount'      => $taxAmount,
                    'total_amount'    => $total,
                    'notes'           => $document->notes ?? 'Standard terms & conditions apply.',
                ],
                'summary'    => [
                    'client_name'  => $customerName,
                    'client_phone' => $phone ?? 'N/A',
                    'total_amount' => '₹' . number_format($total, 2),
                    'tax_amount'   => '₹' . number_format($taxAmount, 2),
                    'item_count'   => count($lines),
                    'notes'        => $document->notes ?? 'Standard terms & conditions apply.',
                ],
            ],

            // 3. Dispatch Channel Actions (Dynamically Loaded)
            [
                'type'          => 'section_header',
                'title'         => 'Dispatch Document',
                'subtitle'      => 'Send through configured gateway channels',
                'text_color'    => 'theme.textPrimary',
                'divider_color' => 'theme.divider',
            ],
            ...$availableChannels,
        ];

        $schema = [
            'type'           => 'bottom_sheet',
            'schema_version' => 1,
            'title'          => "Preview & Dispatch #{$reference}",
            'header' => [
                'title'    => $reference,
                'subtitle' => 'GSTIN: ' . $gstin,
            ],
            'theme' => [
                'surface'      => 'theme.surface',
                'canvas'       => 'theme.canvas',
                'divider'      => 'theme.divider',
                'text_primary' => 'theme.textPrimary',
            ],
            'components' => $components,
        ];

        return response()->json(array_merge([
            'success' => true,
            'schema'  => $schema,
        ], $schema));
    }

    public function renderHtml(Request $request, string $type, int|string $id): View
    {
        $tenantId = $this->tenantId($request);

        $format = $request->input('format', 'a4');
        $format = match (strtolower((string) $format)) {
            '80mm', 'thermal-80mm' => 'thermal_80mm',
            '58mm', 'thermal-58mm' => 'thermal_58mm',
            'mobile', 'mobile_slip' => 'slip',
            default => strtolower((string) $format),
        };
        if (! array_key_exists($format, self::FORMATS)) {
            $format = 'a4';
        }

        $normalizedType = $this->normalizeType($type);
        $document = $this->findDocument($tenantId, $normalizedType, $id);

        $company = $document->tenant ?? $document->company;
        $customer = $document->customer;
        $lines = $this->documentLines($document);
        $type = $normalizedType;

        // Render blade document template matching selected dimensions
        return view("tenant.documents.templates.{$format}", compact('document', 'type', 'company', 'customer', 'lines'));
    }

    private function tenantId(Request $request): mixed
    {
        return auth()->user()?->company_id
            ?? auth()->user()?->tenant_id
            ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null)
            ?? $request->attributes->get('company_id')
            ?? $this->resolveCompany($request)->id;
    }

    private function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'quotation', 'quote' => 'quotation',
            'invoice' => 'invoice',
            'sale', 'receipt', 'sales_receipt' => 'sale',
            default => abort(404, 'Document type not supported'),
        };
    }

    private function findDocument(mixed $tenantId, string $type, int|string $id): Sale
    {
        return Sale::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $tenantId)
            ->when(
                $type === 'quotation',
                fn ($query) => $query->where('operation_type', 'quotation'),
                fn ($query) => $query->where(function ($operation) {
                    $operation->where('operation_type', 'sale')->orWhereNull('operation_type');
                })
            )
            ->where(function ($query) use ($id) {
                $query->where('id', $id)
                    ->orWhere('external_id', $id)
                    ->orWhere('sale_number', $id);
            })
            ->with(['customer', 'tenant', 'saleItems.product'])
            ->firstOrFail();
    }

    /** @return array<int, array{name: string, quantity: float, unit_price: float, line_total: float}> */
    private function documentLines(Sale $document): array
    {
        if ($document->saleItems && $document->saleItems->isNotEmpty()) {
            return $document->saleItems->map(fn ($item) => [
                'name' => $item->name ?: ($item->product?->name ?: 'Item'),
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->total,
            ])->values()->all();
        }

        return collect($document->items ?? [])->map(function (array $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);

            return [
                'name' => (string) ($item['name'] ?? $item['product_name'] ?? 'Item'),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => (float) ($item['line_total'] ?? $item['total'] ?? ($quantity * $unitPrice)),
            ];
        })->values()->all();
    }
}
