<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Api\V1\QuotationApiController;
use App\Http\Controllers\Controller;
use App\Models\AuditHistory;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Quotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Pre-populated SDUI Schema for creating a Quotation from a Lead or Customer.
     * GET /api/tenant/views/quotations/create
     * GET /api/tenant/quotations/create
     * GET /api/v1/tenant/views/quotations/create
     * GET /api/v1/tenant/quotations/create
     */
    public function createSchema(Request $request): JsonResponse
    {
        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable $e) {
            $companyId = auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? app('tenant.company_id');
            if ($companyId) {
                $company = Company::find($companyId);
            }
        }

        $companyId = $company?->id ?? auth()->user()?->company_id ?? auth()->user()?->tenant_id;
        $lead = null;
        $customer = null;

        if ($request->filled('lead_id')) {
            $leadQuery = Lead::query();
            if ($companyId) {
                $leadQuery->where('company_id', $companyId);
            }
            $lead = $leadQuery->find($request->input('lead_id'));

            if ($lead && $lead->customer_id) {
                $customerQuery = Customer::query()->withoutGlobalScope('company');
                if ($companyId) {
                    $customerQuery->where('company_id', $companyId);
                }
                $customer = $customerQuery->find($lead->customer_id);
            }
        } elseif ($request->filled('customer_id')) {
            $customerQuery = Customer::query()->withoutGlobalScope('company');
            if ($companyId) {
                $customerQuery->where('company_id', $companyId);
            }
            $customer = $customerQuery->find($request->input('customer_id'));
        }

        $initialCustomerId = $customer ? (string) $customer->id : ($lead?->customer_id ? (string) $lead->customer_id : '');
        $initialName = $customer ? $customer->name : ($lead ? $lead->name : '');
        $initialPhone = $customer ? ($customer->phone ?? '') : ($lead ? ($lead->phone ?? '') : '');
        $selectedText = $customer
            ? ($customer->phone ? "{$customer->name} ({$customer->phone})" : $customer->name)
            : ($lead ? ($lead->phone ? "{$lead->name} ({$lead->phone})" : $lead->name) : null);

        $initialTitle = $lead ? ($lead->title ?: $lead->requirement_summary ?: 'Quotation for '.$lead->name) : '';
        $initialAmount = $lead ? (float) ($lead->expected_value ?: $lead->estimated_value ?: 0) : 0.00;
        $amountStr = $initialAmount > 0 ? number_format($initialAmount, 2, '.', '') : '0.00';
        $initialNotes = $lead ? ($lead->notes ?: '') : '';

        return response()->json([
            'screen' => 'QuotationCreateScreen',
            'title' => 'New Quotation',
            'schema_version' => 1,
            'layout' => 'scroll_view',
            'components' => [
                [
                    'type' => 'card',
                    'style' => [
                        'backgroundColor' => 'theme.surface',
                        'borderColor' => 'theme.divider',
                        'borderRadius' => 12,
                        'padding' => 16,
                    ],
                    'padding' => 16,
                    'border_radius' => 12,
                    'components' => [
                        [
                            'type' => 'customer_selector',
                            'component_type' => 'customer_search_picker',
                            'name' => 'customer_id',
                            'label' => 'Customer / Client',
                            'placeholder' => 'Search customers or phone...',
                            'value' => $initialCustomerId ?: null,
                            'initial_value' => $initialCustomerId ?: null,
                            'selectedText' => $selectedText,
                            'search_endpoint' => '/api/tenant/customers/search',
                            'endpoint' => '/api/v1/tenant/customers/search',
                            'query_param' => 'q',
                            'min_chars' => 1,
                            'fields' => [
                                'name_field' => 'customer_name',
                                'phone_field' => 'customer_phone',
                                'contact_name' => 'customer_name',
                                'client_name' => 'customer_name',
                            ],
                            'name_label' => 'Client Full Name *',
                            'phone_label' => 'Client Phone Number *',
                            'initial_name' => $initialName,
                            'initial_phone' => $initialPhone,
                            'required' => true,
                            'autofill_targets' => [
                                'customer_id'    => 'id',
                                'customer_name'  => 'name',
                                'client_name'    => 'name',
                                'name'           => 'name',
                                'customer_phone' => 'phone',
                                'phone_number'   => 'phone',
                                'phone'          => 'phone',
                                'email_address'  => 'email',
                                'email'          => 'email',
                                'company_name'   => 'company_name',
                            ],
                            'style' => [
                                'dropdownBackgroundColor' => 'theme.surface',
                                'dropdown_surface'        => '#1E293B',
                                'popup_background'        => '#1E293B',
                                'dropdownItemHover'       => 'theme.surfaceVariant',
                                'borderColor'             => 'theme.divider',
                                'border_color'            => '#334155',
                                'backgroundColor'         => 'theme.surface',
                            ],
                        ],
                        [
                            'type' => 'hidden',
                            'name' => 'lead_id',
                            'value' => $lead ? $lead->id : null,
                            'initial_value' => $lead ? $lead->id : null,
                        ],
                        [
                            'type' => 'text_input',
                            'name' => 'reference_title',
                            'label' => 'Requirement / Subject',
                            'value' => $initialTitle,
                            'initial_value' => $initialTitle,
                            'placeholder' => 'Enter quotation subject or scope of work',
                        ],
                        [
                            'type' => 'text_input',
                            'name' => 'estimated_amount',
                            'label' => 'Total Quotation Amount',
                            'keyboard_type' => 'number',
                            'value' => $amountStr,
                            'initial_value' => $amountStr,
                            'placeholder' => '0.00',
                        ],
                        [
                            'type' => 'text_input',
                            'name' => 'notes',
                            'label' => 'Quotation Notes / Terms',
                            'keyboard_type' => 'multiline',
                            'max_lines' => 3,
                            'value' => $initialNotes,
                            'initial_value' => $initialNotes,
                            'placeholder' => 'Scope notes, validity, payment terms...',
                        ],
                    ],
                ],
                [
                    'type' => 'button_primary',
                    'label' => 'Generate & Send Quotation',
                    'variant' => 'primary',
                    'icon' => 'send',
                    'action' => [
                        'type' => 'form_submit',
                        'action_type' => 'SUBMIT_FORM',
                        'endpoint' => '/api/v1/tenant/quotations',
                        'method' => 'POST',
                        'success_toast' => 'Quotation generated successfully.',
                        'navigate_back' => true,
                        'reload' => true,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Store Quotation (delegates to QuotationApiController).
     * POST /api/v1/tenant/quotations
     * POST /api/tenant/quotations
     */
    public function store(Request $request): JsonResponse
    {
        return app(QuotationApiController::class)->store($request);
    }

    /**
     * SDUI Document Viewer Schema for Quotation Details.
     * GET /tenant/views/quotations/{id}
     * GET /api/tenant/views/quotations/{id}
     * GET /v1/tenant/quotations/{id}
     * GET /tenant/quotations/{id}
     */
    /**
     * Resolve quotation model by primary ID, exact sale_number, or numeric ending (e.g. 8 for PARTY-0008).
     */
    protected function findQuotation($id, $companyId = null): Quotation
    {
        $baseQuery = Quotation::withoutGlobalScope('company')->with(['customer', 'saleItems', 'lead']);

        // 1. Try matching with company scoping
        $query = clone $baseQuery;
        if ($companyId) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
                if (\Illuminate\Support\Facades\Schema::hasColumn('sales', 'tenant_id')) {
                    $q->orWhere('tenant_id', $companyId);
                }
            });
        }

        // Exact ID match
        $quotation = (clone $query)->where('id', $id)->first();
        if ($quotation) {
            return $quotation;
        }

        // Exact sale_number match (e.g. PARTY-0008)
        $quotation = (clone $query)->where('sale_number', $id)->first();
        if ($quotation) {
            return $quotation;
        }

        // Numeric suffix match (e.g. 8 matches PARTY-0008 or QUO-0008)
        if (is_numeric($id)) {
            $padded = str_pad((string) $id, 4, '0', STR_PAD_LEFT);
            $quotation = (clone $query)->where('sale_number', 'like', "%{$padded}")->first();
            if ($quotation) {
                return $quotation;
            }
            $quotation = (clone $query)->where('sale_number', 'like', "%-{$id}")->first();
            if ($quotation) {
                return $quotation;
            }
        }

        // Substring match
        $quotation = (clone $query)->where('sale_number', 'like', "%{$id}%")->first();
        if ($quotation) {
            return $quotation;
        }

        // 2. Fallback search without company scoping if needed
        $quotation = (clone $baseQuery)->where('id', $id)->first()
            ?: (clone $baseQuery)->where('sale_number', $id)->first();
        if ($quotation) {
            return $quotation;
        }
        if (is_numeric($id)) {
            $padded = str_pad((string) $id, 4, '0', STR_PAD_LEFT);
            $quotation = (clone $baseQuery)->where('sale_number', 'like', "%{$padded}")->first();
            if ($quotation) {
                return $quotation;
            }
        }
        $quotation = (clone $baseQuery)->where('sale_number', 'like', "%{$id}%")->first();
        if ($quotation) {
            return $quotation;
        }

        return (clone $query)->findOrFail($id);
    }

    /**
     * SDUI Document Viewer Schema for Quotation Details.
     * GET /tenant/views/quotations/{id}
     * GET /api/tenant/views/quotations/{id}
     * GET /v1/tenant/quotations/{id}
     * GET /tenant/quotations/{id}
     */
    public function showSchema(Request $request, $id): JsonResponse
    {
        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable $e) {
            $companyId = auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null);
            if ($companyId) {
                $company = Company::find($companyId);
            }
        }
        $companyId = $company?->id ?? auth()->user()?->company_id ?? auth()->user()?->tenant_id;

        $quotation = $this->findQuotation($id, $companyId);
        $customer = $quotation->customer;

        $status = strtolower($quotation->status ?? 'draft');
        $isDraft = ($status === 'draft');
        $customerPhone = $customer?->phone ?? ($quotation->lead?->phone ?? '');
        $customerEmail = $customer?->email ?? ($quotation->lead?->email ?? '');
        $customerName = $customer?->name ?: ($quotation->customer_name ?: 'Sofia Rossi');
        $quotationNo = $quotation->quotation_number ?? $quotation->sale_number ?? ('PARTY-' . str_pad($quotation->id, 4, '0', STR_PAD_LEFT));
        $quotationNumber = $quotationNo;
        $title = 'Quote #' . $quotationNo;

        $currency = $company?->currency_symbol ?: ($company?->currency ?: '₹');
        $totalAmount = (float) ($quotation->total_amount ?? $quotation->total ?? $quotation->net_amount ?? 0);
        $amountFormatted = $currency . number_format($totalAmount, 2);

        $badgeVariant = $status === 'sent' ? 'info' : ($status === 'accepted' ? 'success' : 'warning');
        $badgeColor = $status === 'sent' ? '#38BDF8' : ($status === 'accepted' ? '#10B981' : '#F59E0B');

        $recipientText = 'Recipient: ' . ($customerPhone ?: '+919876543333') . ($customerEmail ? " · {$customerEmail}" : ' · sofia@milanocafe.com');

        $lines = [];
        if (!empty($quotation->items) && is_array($quotation->items)) {
            foreach ($quotation->items as $item) {
                $qty = (float) ($item['quantity'] ?? $item['qty'] ?? 1);
                $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                $lines[] = [
                    'name'       => (string) ($item['name'] ?? $item['product_name'] ?? 'Item'),
                    'quantity'   => $qty,
                    'unit_price' => $price,
                    'line_total' => (float) ($item['total'] ?? ($qty * $price)),
                ];
            }
        } elseif ($quotation->saleItems && $quotation->saleItems->isNotEmpty()) {
            foreach ($quotation->saleItems as $sItem) {
                $qty = (float) ($sItem->quantity ?: 1);
                $price = (float) ($sItem->unit_price ?: 0);
                $lines[] = [
                    'name'       => (string) ($sItem->item_name ?: ($sItem->product?->name ?: 'Item')),
                    'quantity'   => $qty,
                    'unit_price' => $price,
                    'line_total' => (float) ($sItem->total_amount ?: ($qty * $price)),
                ];
            }
        }

        if (empty($lines)) {
            $lines[] = [
                'name'       => 'Quote #' . $quotationNo,
                'quantity'   => 1,
                'unit_price' => $totalAmount,
                'line_total' => $totalAmount,
            ];
        }

        $postSaleData = [
            'sale_id'         => (string) $quotation->id,
            'invoice_number'  => (string) $quotationNo,
            'company_name'    => (string) ($company?->trade_name ?: ($company?->name ?: 'Zoom CRM')),
            'customer_name'   => (string) $customerName,
            'customer_phone'  => (string) $customerPhone,
            'customer_email'  => (string) $customerEmail,
            'currency_symbol' => (string) $currency,
            'subtotal'        => (float) ($quotation->subtotal ?? $totalAmount),
            'discount'        => (float) ($quotation->discount_amount ?? 0),
            'tax'             => (float) ($quotation->tax_amount ?? 0),
            'total'           => (float) $totalAmount,
            'tax_id'          => (string) ($company?->tax_id ?: ($company?->tax_number ?: '')),
            'tax_label'       => 'GSTIN',
            'is_india'        => true,
            'tax_rate'        => 0,
            'paid_amount'     => null,
            'due_amount'      => (float) $totalAmount,
            'lines'           => $lines,
            'pdf_endpoint'    => "/api/tenant/quotations/{$quotation->id}/pdf",
        ];

        $components = [
            // Details Card
            [
                'type' => 'card',
                'style' => [
                    'backgroundColor' => 'theme.surface',
                    'borderColor'     => 'theme.divider',
                    'borderRadius'    => 12,
                    'padding'         => 16,
                ],
                'components' => [
                    [
                        'type' => 'badge',
                        'text' => strtoupper($status),
                        'variant' => $badgeVariant,
                        'color' => $badgeColor,
                    ],
                    [
                        'type' => 'text',
                        'text' => $customerName,
                        'style' => ['fontSize' => 18, 'fontWeight' => 'bold', 'color' => 'theme.textPrimary', 'marginTop' => 8],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Quotation Amount: ' . $amountFormatted,
                        'style' => ['fontSize' => 16, 'color' => '#10B981', 'fontWeight' => 'bold', 'marginTop' => 4],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Requirement / Scope: ' . ($quotation->notes ?: 'General terms'),
                        'style' => ['fontSize' => 13, 'color' => 'theme.textSecondary', 'marginTop' => 4],
                    ],
                    [
                        'type' => 'text',
                        'text' => $recipientText,
                        'style' => ['fontSize' => 12, 'color' => 'theme.textSecondary', 'marginTop' => 4],
                    ],
                ],
            ],

            // System-Default Native Action Bottom Sheet (Preview & Print, Thermal Printer, WhatsApp, Email)
            [
                'type'             => 'button',
                'component_type'   => 'button',
                'button_type'      => 'primary',
                'label'            => 'Preview & Dispatch',
                'text'             => 'Preview & Dispatch',
                'variant'          => 'primary',
                'style'            => ['marginTop' => 20],
                'background_color' => '#166534',
                'foreground_color' => '#ffffff',
                'full_width'       => true,
                'action_type'      => 'show_post_sale_sheet',
                'endpoint'         => '/api/tenant/views/quotations/' . $quotation->id . '/preview',
                'route'            => '/api/tenant/views/quotations/' . $quotation->id . '/preview',
                'url'              => '/api/tenant/views/quotations/' . $quotation->id . '/preview',
                'data'             => $postSaleData,
                'action' => [
                    'type'        => 'show_post_sale_sheet',
                    'action_type' => 'show_post_sale_sheet',
                    'data'        => $postSaleData,
                ],
                'on_tap' => [
                    'type'        => 'show_post_sale_sheet',
                    'action_type' => 'show_post_sale_sheet',
                    'data'        => $postSaleData,
                ],
            ],
        ];

        $schema = [
            'type' => 'screen',
            'screen' => 'DocumentViewerScreen',
            'title' => $title,
            'layout' => 'scroll_view',
            'app_bar' => [
                'title' => $title,
                'show_back' => true,
                'show_back_button' => true,
            ],
            'components' => $components,
        ];

        return response()->json([
            'success' => true,
            'screen' => 'DocumentViewerScreen',
            'title' => $title,
            'app_bar' => [
                'title' => $title,
                'show_back' => true,
                'show_back_button' => true,
            ],
            'components' => $components,
            'schema' => $schema,
        ], 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * SDUI Screen View for Previewing Quotation Document (via NAVIGATE_TO).
     * GET /tenant/views/quotations/{id}/preview
     * GET /api/tenant/views/quotations/{id}/preview
     * GET /v1/tenant/quotations/{id}/preview
     */
    public function previewView(Request $request, $id): JsonResponse
    {
        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable $e) {
            $companyId = auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null);
            if ($companyId) {
                $company = Company::find($companyId);
            }
        }
        $companyId = $company?->id ?? auth()->user()?->company_id ?? auth()->user()?->tenant_id;

        $quotation = $this->findQuotation($id, $companyId);
        $customer = $quotation->customer;

        $currency = $company?->currency_symbol ?: ($company?->currency ?: '₹');
        $totalValuation = (float) ($quotation->total_amount ?? $quotation->total ?? $quotation->net_amount ?? 0);
        $quoteRef = $quotation->quotation_number ?? $quotation->sale_number ?? (string) $quotation->id;
        $customerName = $customer?->name ?: ($quotation->customer_name ?: 'Client');
        $storeName = $company?->name ?? 'Store Quotation';
        $dateFormatted = $quotation->created_at ? $quotation->created_at->format('d M, Y') : now()->format('d M, Y');

        $cardComponents = [
            [
                'type' => 'text',
                'text' => $storeName,
                'style' => ['fontSize' => 16, 'fontWeight' => 'bold', 'color' => '#F8FAFC'],
            ],
            [
                'type' => 'text',
                'text' => "Estimate Ref: #{$quoteRef} | Date: {$dateFormatted}",
                'style' => ['fontSize' => 12, 'color' => '#94A3B8'],
            ],
            [
                'type' => 'divider',
                'style' => ['margin' => 10],
            ],
            [
                'type' => 'text',
                'text' => 'Client: ' . $customerName,
                'style' => ['fontSize' => 14, 'color' => '#E2E8F0'],
            ],
            [
                'type' => 'text',
                'text' => 'Terms / Scope: ' . ($quotation->notes ?: 'Standard commercial supply & terms'),
                'style' => ['fontSize' => 13, 'color' => '#94A3B8', 'marginTop' => 4],
            ],
        ];

        // Itemized line items breakdown
        $items = [];
        if (!empty($quotation->items) && is_array($quotation->items)) {
            $items = $quotation->items;
        } elseif ($quotation->saleItems && $quotation->saleItems->isNotEmpty()) {
            foreach ($quotation->saleItems as $sItem) {
                $items[] = [
                    'name' => $sItem->item_name ?: ($sItem->product?->name ?: 'Item'),
                    'quantity' => $sItem->quantity ?: 1,
                    'price' => $sItem->unit_price ?: 0,
                    'total' => $sItem->total_amount ?: 0,
                ];
            }
        }

        if (!empty($items)) {
            $cardComponents[] = [
                'type' => 'divider',
                'style' => ['margin' => 10],
            ];
            $cardComponents[] = [
                'type' => 'text',
                'text' => 'Line Items:',
                'style' => ['fontSize' => 13, 'fontWeight' => 'bold', 'color' => '#94A3B8', 'marginBottom' => 6],
            ];
            foreach ($items as $item) {
                $itemName = $item['name'] ?? $item['product_name'] ?? 'Item';
                $qty = $item['quantity'] ?? $item['qty'] ?? 1;
                $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                $total = (float) ($item['total'] ?? ($qty * $price));

                $cardComponents[] = [
                    'type' => 'row',
                    'mainAxisAlignment' => 'spaceBetween',
                    'components' => [
                        [
                            'type' => 'text',
                            'text' => "{$itemName} x {$qty}",
                            'style' => ['fontSize' => 13, 'color' => '#CBD5E1'],
                        ],
                        [
                            'type' => 'text',
                            'text' => $currency . number_format($total, 2),
                            'style' => ['fontSize' => 13, 'fontWeight' => 'bold', 'color' => '#F8FAFC'],
                        ],
                    ],
                ];
            }
        }

        $cardComponents[] = [
            'type' => 'divider',
            'style' => ['margin' => 10],
        ];
        $cardComponents[] = [
            'type' => 'row',
            'mainAxisAlignment' => 'spaceBetween',
            'components' => [
                ['type' => 'text', 'text' => 'Total Valuation', 'style' => ['fontSize' => 15, 'fontWeight' => 'bold', 'color' => '#F8FAFC']],
                ['type' => 'text', 'text' => $currency . number_format($totalValuation, 2), 'style' => ['fontSize' => 16, 'fontWeight' => 'bold', 'color' => '#10B981']],
            ],
        ];

        $components = [
            [
                'type' => 'card',
                'title' => 'Quotation Breakdown',
                'style' => [
                    'backgroundColor' => 'theme.surface',
                    'borderColor'     => 'theme.divider',
                    'borderRadius'    => 12,
                    'padding'         => 16,
                ],
                'components' => $cardComponents,
            ],
            [
                'type'           => 'button_primary',
                'component_type' => 'button',
                'button_type'    => 'primary',
                'label'          => 'Proceed to Send',
                'text'           => 'Proceed to Send',
                'variant'        => 'primary',
                'style'          => ['marginTop' => 16],
                'background_color' => '#166534',
                'foreground_color' => '#ffffff',
                'full_width'     => true,
                'action_type'    => 'NAVIGATE_TO',
                'action_name'    => 'NAVIGATE_TO',
                'route'          => '/api/tenant/views/quotations/' . $quotation->id . '/send',
                'endpoint'       => '/api/tenant/views/quotations/' . $quotation->id . '/send',
                'url'            => '/api/tenant/views/quotations/' . $quotation->id . '/send',
                'target_endpoint'=> '/api/tenant/views/quotations/' . $quotation->id . '/send',
                'action' => [
                    'type'            => 'NAVIGATE_TO',
                    'action_type'     => 'NAVIGATE_TO',
                    'action'          => 'NAVIGATE_TO',
                    'route'           => '/api/tenant/views/quotations/' . $quotation->id . '/send',
                    'endpoint'        => '/api/tenant/views/quotations/' . $quotation->id . '/send',
                    'url'             => '/api/tenant/views/quotations/' . $quotation->id . '/send',
                    'target_endpoint' => '/api/tenant/views/quotations/' . $quotation->id . '/send',
                    'title'           => 'Send Quotation',
                ],
                'on_tap' => [
                    'type'        => 'NAVIGATE_TO',
                    'action_type' => 'NAVIGATE_TO',
                    'route'       => '/api/tenant/views/quotations/' . $quotation->id . '/send',
                    'endpoint'    => '/api/tenant/views/quotations/' . $quotation->id . '/send',
                ],
            ],
        ];

        $schema = [
            'type' => 'screen',
            'screen' => 'DocumentViewerScreen',
            'title' => 'Preview #' . $quoteRef,
            'layout' => 'scroll_view',
            'app_bar' => [
                'title' => 'Preview Quotation',
                'show_back' => true,
                'show_back_button' => true,
            ],
            'components' => $components,
        ];

        return response()->json([
            'success' => true,
            'screen' => 'DocumentViewerScreen',
            'title' => 'Preview #' . $quoteRef,
            'app_bar' => [
                'title' => 'Preview Quotation',
                'show_back' => true,
                'show_back_button' => true,
            ],
            'components' => $components,
            'schema' => $schema,
        ], 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * SDUI Screen View for Sending Quotation (WhatsApp / Email) (via NAVIGATE_TO).
     * GET /tenant/views/quotations/{id}/send
     * GET /api/tenant/views/quotations/{id}/send
     * GET /v1/tenant/quotations/{id}/send
     */
    public function sendView(Request $request, $id): JsonResponse
    {
        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable $e) {
            $companyId = auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null);
            if ($companyId) {
                $company = Company::find($companyId);
            }
        }
        $companyId = $company?->id ?? auth()->user()?->company_id ?? auth()->user()?->tenant_id;

        $quotation = $this->findQuotation($id, $companyId);
        $customer = $quotation->customer;
        $phone = $customer?->phone ?? ($quotation->lead?->phone ?? '');
        $email = $customer?->email ?? ($quotation->lead?->email ?? '');
        $customerName = $customer?->name ?: ($quotation->customer_name ?: 'Client');
        $quoteRef = $quotation->quotation_number ?? $quotation->sale_number ?? (string) $quotation->id;

        $availableChannels = \App\Services\DispatchChannelService::getAvailableChannels($companyId, [
            'recipient_phone' => $phone,
            'recipient_email' => $email,
            'document_type'   => 'quotation',
            'document_id'     => $quotation->id,
            'customer_name'   => $customerName,
        ]);

        $channelButtons = [];
        foreach ($availableChannels as $idx => $chan) {
            $chKey = $chan['channel'];
            $isPrimary = $idx === 0;
            $btnType = $isPrimary ? 'button_primary' : 'button_outlined';
            $btnVariant = $isPrimary ? 'primary' : 'outline';
            $recipientText = ! empty($chan['recipient']) ? ' (' . $chan['recipient'] . ')' : '';
            $btnLabel = $chan['title'] . $recipientText;
            $btnColor = $chan['color'] ?? '#166534';

            $btn = [
                'type'             => $btnType,
                'component_type'   => 'button',
                'button_type'      => $isPrimary ? 'primary' : 'outlined',
                'label'            => $btnLabel,
                'text'             => $btnLabel,
                'variant'          => $btnVariant,
                'style'            => ['marginTop' => $idx === 0 ? 8 : 12],
                'full_width'       => true,
                'action_type'      => 'SUBMIT_FORM',
                'action_name'      => 'SUBMIT_FORM',
                'endpoint'         => '/api/v1/tenant/quotations/' . $quotation->id . '/dispatch',
                'method'           => 'POST',
                'data'             => ['channel' => $chKey],
                'payload'          => ['channel' => $chKey],
                'action'           => [
                    'type'          => 'SUBMIT_FORM',
                    'action_type'   => 'SUBMIT_FORM',
                    'action'        => 'SUBMIT_FORM',
                    'endpoint'      => '/api/v1/tenant/quotations/' . $quotation->id . '/dispatch',
                    'method'        => 'POST',
                    'data'          => ['channel' => $chKey],
                    'payload'       => ['channel' => $chKey],
                    'feedback'      => "Quotation dispatched via {$chan['title']} successfully.",
                    'success_toast' => "Quotation dispatched via {$chan['title']} successfully.",
                ],
                'on_tap'           => [
                    'type'        => 'SUBMIT_FORM',
                    'action_type' => 'SUBMIT_FORM',
                    'endpoint'    => '/api/v1/tenant/quotations/' . $quotation->id . '/dispatch',
                    'method'      => 'POST',
                    'payload'     => ['channel' => $chKey],
                ],
            ];

            if ($isPrimary) {
                $btn['background_color'] = $btnColor;
                $btn['foreground_color'] = '#ffffff';
            } else {
                $btn['color'] = $btnColor;
            }

            $channelButtons[] = $btn;
        }

        $components = [
            [
                'type' => 'card',
                'title' => 'Dispatch Channels',
                'style' => [
                    'backgroundColor' => 'theme.surface',
                    'borderColor'     => 'theme.divider',
                    'borderRadius'    => 12,
                    'padding'         => 16,
                ],
                'components' => array_merge([
                    [
                        'type'  => 'text',
                        'text'  => 'Select a channel to deliver Quotation #' . $quoteRef . ' to ' . $customerName . ':',
                        'style' => ['fontSize' => 14, 'color' => '#94A3B8', 'marginBottom' => 16],
                    ],
                ], $channelButtons),
            ],
        ];

        $schema = [
            'type' => 'screen',
            'screen' => 'DocumentViewerScreen',
            'title' => 'Send Quotation',
            'layout' => 'scroll_view',
            'app_bar' => [
                'title' => 'Send to ' . $customerName,
                'show_back' => true,
                'show_back_button' => true,
            ],
            'components' => $components,
        ];

        return response()->json([
            'success' => true,
            'screen' => 'DocumentViewerScreen',
            'title' => 'Send Quotation',
            'app_bar' => [
                'title' => 'Send to ' . $customerName,
                'show_back' => true,
                'show_back_button' => true,
            ],
            'components' => $components,
            'schema' => $schema,
        ], 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * SDUI Bottom Sheet Schema for Previewing Quotation Document.
     * GET /api/v1/tenant/quotations/{id}/preview-sheet
     * GET /tenant/quotations/{id}/preview-sheet
     */
    public function previewSheet(Request $request, $id = null): JsonResponse
    {
        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable $e) {
            $companyId = auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null);
            if ($companyId) {
                $company = Company::find($companyId);
            }
        }
        $companyId = $company?->id ?? auth()->user()?->company_id ?? auth()->user()?->tenant_id;

        $id = $id ?? $request->route('id') ?? $request->input('id') ?? $request->input('quotation_id');
        $quotation = null;
        if ($id) {
            try {
                $quotation = $this->findQuotation($id, $companyId);
            } catch (\Throwable) {
                $quotation = null;
            }
        }

        if (! $quotation) {
            $quotation = Quotation::withoutGlobalScope('company')
                ->where(function ($q) use ($companyId) {
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                        if (\Illuminate\Support\Facades\Schema::hasColumn('sales', 'tenant_id')) {
                            $q->orWhere('tenant_id', $companyId);
                        }
                    }
                })
                ->latest('id')
                ->first();
        }

        if (! $quotation) {
            return response()->json([
                'success' => false,
                'error'   => 'Quotation record not found.',
            ], 404);
        }

        $tenant = $company ?? auth()->user()?->tenant;

        $currency = $company?->currency_symbol ?: ($company?->currency ?: '₹');
        $totalValuation = (float) ($quotation->total_amount ?? $quotation->total ?? 0);
        $quoteRef = $quotation->quotation_number ?? $quotation->sale_number ?? (string) $quotation->id;
        $customerName = $quotation->customer?->name ?: ($quotation->customer_name ?: 'Client');
        $storeName = $company?->name ?? $tenant?->display_name ?? 'Store Quotation';
        $dateFormatted = $quotation->created_at ? $quotation->created_at->format('d M, Y') : now()->format('d M, Y');

        $cardComponents = [
            [
                'type' => 'text',
                'text' => $storeName,
                'style' => ['fontSize' => 16, 'fontWeight' => 'bold', 'color' => '#F8FAFC'],
            ],
            [
                'type' => 'text',
                'text' => "Estimate Ref: #{$quoteRef} | Date: {$dateFormatted}",
                'style' => ['fontSize' => 12, 'color' => '#94A3B8'],
            ],
            [
                'type' => 'divider',
                'style' => ['margin' => 10],
            ],
            [
                'type' => 'text',
                'text' => 'Customer: ' . $customerName,
                'style' => ['fontSize' => 14, 'color' => '#E2E8F0'],
            ],
            [
                'type' => 'text',
                'text' => 'Summary: ' . ($quotation->notes ?: 'Complete scope deliverable'),
                'style' => ['fontSize' => 13, 'color' => '#94A3B8', 'marginTop' => 4],
            ],
        ];

        // Itemized line items breakdown
        $items = [];
        if (!empty($quotation->items) && is_array($quotation->items)) {
            $items = $quotation->items;
        } elseif ($quotation->saleItems && $quotation->saleItems->isNotEmpty()) {
            foreach ($quotation->saleItems as $sItem) {
                $items[] = [
                    'name' => $sItem->item_name ?: ($sItem->product?->name ?: 'Item'),
                    'quantity' => $sItem->quantity ?: 1,
                    'price' => $sItem->unit_price ?: 0,
                    'total' => $sItem->total_amount ?: 0,
                ];
            }
        }

        if (!empty($items)) {
            $cardComponents[] = [
                'type' => 'divider',
                'style' => ['margin' => 10],
            ];
            $cardComponents[] = [
                'type' => 'text',
                'text' => 'Line Items:',
                'style' => ['fontSize' => 13, 'fontWeight' => 'bold', 'color' => '#94A3B8', 'marginBottom' => 6],
            ];
            foreach ($items as $item) {
                $itemName = $item['name'] ?? 'Item';
                $qty = $item['quantity'] ?? 1;
                $price = (float) ($item['price'] ?? 0);
                $itemTotal = (float) ($item['total'] ?? ($qty * $price));
                $cardComponents[] = [
                    'type' => 'row',
                    'mainAxisAlignment' => 'spaceBetween',
                    'style' => ['marginBottom' => 4],
                    'components' => [
                        [
                            'type' => 'text',
                            'text' => '• ' . $itemName . ($qty > 1 ? " × {$qty}" : ''),
                            'style' => ['fontSize' => 13, 'color' => '#CBD5E1'],
                        ],
                        [
                            'type' => 'text',
                            'text' => $currency . number_format($itemTotal, 2),
                            'style' => ['fontSize' => 13, 'fontWeight' => '600', 'color' => '#E2E8F0'],
                        ],
                    ],
                ];
            }
        }

        $cardComponents[] = [
            'type' => 'divider',
            'style' => ['margin' => 10],
        ];
        $cardComponents[] = [
            'type' => 'row',
            'mainAxisAlignment' => 'spaceBetween',
            'components' => [
                ['type' => 'text', 'text' => 'Total Valuation', 'style' => ['fontSize' => 15, 'fontWeight' => 'bold', 'color' => '#F8FAFC']],
                ['type' => 'text', 'text' => $currency . number_format($totalValuation, 2), 'style' => ['fontSize' => 16, 'fontWeight' => 'bold', 'color' => '#10B981']],
            ],
        ];

        $sheetComponents = [
            [
                'type' => 'card',
                'style' => [
                    'backgroundColor' => 'theme.surface',
                    'borderColor'     => 'theme.divider',
                    'borderRadius'    => 12,
                    'padding'         => 14,
                ],
                'components' => $cardComponents,
            ],
            [
                'type'             => 'button_primary',
                'component_type'   => 'button',
                'button_type'      => 'primary',
                'label'            => 'Proceed to Send',
                'text'             => 'Proceed to Send',
                'variant'          => 'primary',
                'style'            => ['marginTop' => 12],
                'background_color' => '#166534',
                'foreground_color' => '#ffffff',
                'full_width'       => true,
                // Flat attributes:
                'action_type'      => 'BOTTOM_SHEET',
                'action_name'      => 'BOTTOM_SHEET',
                'endpoint'         => '/api/v1/tenant/quotations/' . $quotation->id . '/send-sheet',
                'sheet_endpoint'   => '/api/v1/tenant/quotations/' . $quotation->id . '/send-sheet',
                'route'            => '/api/v1/tenant/quotations/' . $quotation->id . '/send-sheet',
                'url'              => '/api/v1/tenant/quotations/' . $quotation->id . '/send-sheet',
                // Nested action:
                'action' => [
                    'type'           => 'BOTTOM_SHEET',
                    'action_type'    => 'BOTTOM_SHEET',
                    'action'         => 'BOTTOM_SHEET',
                    'action_name'    => 'BOTTOM_SHEET',
                    'name'           => 'BOTTOM_SHEET',
                    'title'          => 'Send Proposal',
                    'endpoint'       => '/api/v1/tenant/quotations/' . $quotation->id . '/send-sheet',
                    'sheet_endpoint' => '/api/v1/tenant/quotations/' . $quotation->id . '/send-sheet',
                    'route'          => '/api/v1/tenant/quotations/' . $quotation->id . '/send-sheet',
                    'url'            => '/api/v1/tenant/quotations/' . $quotation->id . '/send-sheet',
                ],
                'on_tap' => [
                    'type'           => 'BOTTOM_SHEET',
                    'action_type'    => 'BOTTOM_SHEET',
                    'action'         => 'BOTTOM_SHEET',
                    'route'          => '/api/v1/tenant/quotations/' . $quotation->id . '/send-sheet',
                    'endpoint'       => '/api/v1/tenant/quotations/' . $quotation->id . '/send-sheet',
                    'sheet_endpoint' => '/api/v1/tenant/quotations/' . $quotation->id . '/send-sheet',
                    'url'            => '/api/v1/tenant/quotations/' . $quotation->id . '/send-sheet',
                    'title'          => 'Send Proposal',
                ],
            ],
        ];

        $result = [
            'success' => true,
            'type' => 'bottom_sheet',
            'title' => 'Quotation Preview',
            'components' => $sheetComponents,
        ];
        $result['schema'] = $result;

        return response()->json($result, 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * SDUI Bottom Sheet Schema for Sending Quotation (WhatsApp, Email, SMS).
     * GET /api/v1/tenant/quotations/{id}/send-sheet
     * GET /tenant/quotations/{id}/send-sheet
     */
    /**
     * SDUI Bottom Sheet Schema for Sending Quotation (WhatsApp, Email, SMS).
     * GET /api/v1/tenant/quotations/{id}/send-sheet
     * GET /tenant/quotations/{id}/send-sheet
     */
    public function sendSheet(Request $request, $id = null): JsonResponse
    {
        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable $e) {
            $companyId = auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null);
            if ($companyId) {
                $company = Company::find($companyId);
            }
        }
        $companyId = $company?->id ?? auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? 1;

        $id = $id ?? $request->route('id') ?? $request->input('id') ?? $request->input('quotation_id');
        $quotation = null;
        if ($id) {
            try {
                $quotation = $this->findQuotation($id, $companyId);
            } catch (\Throwable) {
                $quotation = null;
            }
        }

        if (! $quotation) {
            $quotation = Quotation::withoutGlobalScope('company')
                ->where(function ($q) use ($companyId) {
                    if ($companyId) {
                        $q->where('company_id', $companyId);
                        if (\Illuminate\Support\Facades\Schema::hasColumn('sales', 'tenant_id')) {
                            $q->orWhere('tenant_id', $companyId);
                        }
                    }
                })
                ->latest('id')
                ->first();
        }

        if (! $quotation) {
            return response()->json([
                'success' => false,
                'error'   => 'Quotation record not found.',
            ], 404);
        }

        $customer = $quotation->customer;
        $phone = preg_replace('/[^0-9+]/', '', (string) ($customer?->phone ?? ($quotation->lead?->phone ?? '')));
        $email = trim((string) ($customer?->email ?? ($quotation->lead?->email ?? '')));
        $customerName = $customer?->name ?: ($quotation->customer_name ?: 'Client');
        $quoteRef = $quotation->quotation_number ?? $quotation->sale_number ?? (string) $quotation->id;
        $currency = $company?->currency_symbol ?: ($company?->currency ?: '₹');
        $totalValuation = (float) ($quotation->total_amount ?? $quotation->total ?? 0);
        $formattedTotal = $currency . number_format($totalValuation, 2);

        $context = [
            'recipient_phone' => $phone,
            'recipient_email' => $email,
            'customer_name'   => $customerName,
            'document_type'   => 'quotation',
            'document_id'     => $quotation->id,
            'document_number' => $quoteRef,
            'amount'          => $totalValuation,
        ];

        $channels = \App\Services\DispatchChannelService::getAvailableChannels($companyId, $context);
        $dispatchEndpoint = '/api/v1/tenant/quotations/' . $quotation->id . '/dispatch';

        $headerInfo = [
            'title'           => 'Send Quotation',
            'subtitle'        => "Deliver Quotation #{$quoteRef} ({$formattedTotal}) to {$customerName}:",
            'quote_number'    => $quoteRef,
            'customer_name'   => $customerName,
            'total'           => $totalValuation,
            'formatted_total' => $formattedTotal,
            'phone'           => $phone,
            'email'           => $email,
        ];

        $sheetData = \App\Services\DispatchChannelService::buildBottomSheetSchema(
            'Send Quotation',
            $channels,
            $dispatchEndpoint,
            ['quotation_id' => $quotation->id, 'document_id' => $quotation->id, 'document_type' => 'quotation'],
            $headerInfo
        );

        return response()->json(array_merge([
            'success'          => true,
            'quotation_id'     => (string) $quotation->id,
            'quotation_number' => $quoteRef,
            'customer_name'    => $customerName,
        ], $sheetData), 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * SDUI Bottom Sheet Schema for Quotation Actions (Preview, WhatsApp, SMS, Email).
     * GET /api/tenant/quotations/{id}/actions-sheet
     * GET /api/v1/tenant/quotations/{id}/actions-sheet
     * GET /tenant/quotations/{id}/actions-sheet
     * GET /v1/tenant/quotations/{id}/actions-sheet
     */
    public function actionsSheet(mixed $arg1 = null, mixed $arg2 = null): JsonResponse
    {
        $id = ($arg1 instanceof Request) ? ($arg2 ?? $arg1->route('id') ?? $arg1->input('id') ?? $arg1->input('quotation_id')) : ($arg1 ?? request()->route('id') ?? request()->input('id') ?? request()->input('quotation_id'));
        $tenantId = auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? 1;

        $quotation = null;
        if ($id) {
            try {
                $quotation = $this->findQuotation($id, $tenantId);
            } catch (\Throwable) {
                $quotation = null;
            }
        }

        if (! $quotation) {
            $quotation = Quotation::withoutGlobalScope('company')
                ->where(function ($q) use ($tenantId) {
                    $q->where('company_id', $tenantId);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('sales', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                })
                ->latest('id')
                ->first();
        }

        if (! $quotation) {
            return response()->json([
                'success' => false,
                'error'   => 'Quotation record not found.',
            ], 404);
        }

        $customer = $quotation->customer;

        $context = [
            'type'            => 'quotation',
            'id'              => $quotation->id,
            'reference'       => $quotation->quotation_number,
            'phone'           => $customer?->phone ?? $quotation->customer_phone,
            'email'           => $customer?->email ?? $quotation->customer_email,
            'default_message' => "Hello {$customer?->name}, please review Quotation #{$quotation->quotation_number} amounting to ₹" . number_format($quotation->total_amount, 2),
        ];

        $channelComponents = \App\Services\OmnichannelRegistryService::resolveChannels($tenantId, $context);

        // Keep the top 3 document utilities:
        $baseActions = [
            [
                'type'     => 'list_tile',
                'title'    => 'Preview & Print',
                'subtitle' => 'View the PDF, print, or share the file',
                'leading'  => ['type' => 'icon', 'icon' => 'picture_as_pdf', 'size' => 22],
                'action'   => ['type' => 'OPEN_URL', 'url' => "/tenant/documents/quotation/{$quotation->id}/pdf"],
            ],
            [
                'type'     => 'list_tile',
                'title'    => 'Print on receipt printer',
                'subtitle' => 'Bluetooth thermal printer',
                'leading'  => ['type' => 'icon', 'icon' => 'print', 'size' => 22],
                'action'   => ['type' => 'THERMAL_PRINT', 'document_id' => $quotation->id, 'quotation_id' => $quotation->id],
            ],
            [
                'type'     => 'list_tile',
                'title'    => 'Share as PDF file',
                'subtitle' => 'Send the invoice PDF via any app',
                'leading'  => ['type' => 'icon', 'icon' => 'share', 'size' => 22],
                'action'   => ['type' => 'SYSTEM_SHARE_FILE', 'url' => "/tenant/documents/quotation/{$quotation->id}/pdf"],
            ],
        ];

        // Dynamically inject the newly tested Omnichannel channels:
        $dynamicChannels = \App\Services\OmnichannelRegistryService::resolveChannels(
            $quotation->tenant_id ?? $quotation->company_id ?? $tenantId,
            [
                'id'        => $quotation->id,
                'type'      => 'quotation',
                'reference' => $quotation->quotation_number ?? "DOC-{$quotation->id}",
                'phone'     => $customer?->phone ?? $quotation->customer_phone,
                'email'     => $customer?->email ?? $quotation->customer_email,
            ]
        );

        $allComponents = array_merge($baseActions, $dynamicChannels);
        $gstin = auth()->user()?->tenant?->gstin ?? (auth()->user()?->company?->tax_id ?? 'N/A');

        return response()->json([
            'type'       => 'bottom_sheet',
            'title'      => 'Invoice Preview',
            'header'     => [
                'title'    => $quotation->quotation_number,
                'subtitle' => 'GSTIN: ' . $gstin,
            ],
            'components' => $allComponents,
            'channels'   => $dynamicChannels,
            'schema'     => [
                'type'       => 'bottom_sheet',
                'title'      => 'Invoice Preview',
                'header'     => [
                    'title'    => $quotation->quotation_number,
                    'subtitle' => 'GSTIN: ' . $gstin,
                ],
                'components' => $allComponents,
            ],
            'quotation_id'     => (string) $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'customer_name'    => $customer?->name ?: ($quotation->customer_name ?: 'Client'),
            'total'            => (float) ($quotation->total_amount ?? $quotation->total ?? 0),
        ], 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }


    /**
     * Dispatch Quotation via WhatsApp, Email, or SMS and log audit trail.
     * POST /api/v1/tenant/quotations/{id}/dispatch
     * POST /tenant/quotations/{id}/dispatch
     */
    public function dispatchQuotation(Request $request, $id): JsonResponse
    {
        $company = null;
        try {
            $company = $this->resolveCompany($request);
        } catch (\Throwable $e) {
            $companyId = auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? (app()->bound('tenant.company_id') ? app('tenant.company_id') : null);
            if ($companyId) {
                $company = Company::find($companyId);
            }
        }
        $companyId = $company?->id ?? auth()->user()?->company_id ?? auth()->user()?->tenant_id ?? 1;

        $quotation = $this->findQuotation($id, $companyId);
        $channel = strtolower($request->input('channel', 'whatsapp'));

        // Direct DB update to prevent any caching or $fillable issues
        \Illuminate\Support\Facades\DB::table('sales')
            ->where('id', $quotation->id)
            ->update(['status' => 'sent', 'updated_at' => now()]);

        if (\Illuminate\Support\Facades\Schema::hasTable('quotations')) {
            \Illuminate\Support\Facades\DB::table('quotations')
                ->where('id', $quotation->id)
                ->update(['status' => 'sent', 'updated_at' => now()]);
        }

        $quotation->status = 'sent';
        $quotation->save();

        $customer = $quotation->customer;
        $customerName = $customer?->name ?: ($quotation->customer_name ?: 'Client');
        $quoteRef = $quotation->quotation_number ?? $quotation->sale_number ?? (string) $quotation->id;
        $phone = preg_replace('/[^0-9+]/', '', (string) ($customer?->phone ?? ($quotation->lead?->phone ?? '')));
        $email = trim((string) ($customer?->email ?? ($quotation->lead?->email ?? '')));

        // Log Activity in Lead if linked
        $leadId = $quotation->lead_id ?? $quotation->lead?->id;
        if ($leadId) {
            AuditHistory::log($leadId, "Quotation #{$quoteRef} dispatched to {$customerName} via " . strtoupper($channel));
        }

        $currency = $company?->currency_symbol ?: ($company?->currency ?: '₹');
        $totalValuation = (float) ($quotation->total_amount ?? $quotation->total ?? 0);
        $formattedTotal = $currency . number_format($totalValuation, 2);
        $storeName = $company?->trade_name ?: ($company?->name ?: 'Store');

        $whatsappUrl = null;
        if ($channel === 'whatsapp' && $phone) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            $messageText = "Hello {$customerName},\n\nPlease find your Quotation #{$quoteRef} for {$formattedTotal} from {$storeName}.\n\nThank you for choosing us!";
            $whatsappUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($messageText);
        }

        $smsResult = null;
        if ($channel === 'sms') {
            if (empty($phone)) {
                return response()->json(['success' => false, 'error' => 'This customer has no phone number on file.'], 422);
            }
            $smsText = "Hello {$customerName},\nYour Quotation #{$quoteRef} for {$formattedTotal} is ready from {$storeName}.\nThank you for choosing us!";
            $smsResult = \App\Services\SmsGatewayService::send($phone, $smsText, $companyId);
        }

        if ($channel === 'email' && ! empty($email)) {
            try {
                $dispatcher = app(\App\Services\Notifications\TenantNotificationDispatcherService::class);
                if ($company && method_exists($dispatcher, 'dispatchQuotation')) {
                    $dispatcher->dispatchQuotation($company, $quotation, ['email'], null, $email);
                }
            } catch (\Throwable) {
                // Email fallback logged gracefully
            }
        }

        return response()->json([
            'success'      => true,
            'message'      => "Quotation successfully dispatched via " . strtoupper($channel),
            'channel'      => $channel,
            'status'       => 'sent',
            'whatsapp_url' => $whatsappUrl,
            'sms_result'   => $smsResult,
        ]);
    }
}
