<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Pre-populated SDUI Schema for creating / converting to a Tax Invoice.
     * GET /api/v1/tenant/invoices/create?from_quotation=...
     * GET /tenant/views/invoices/create?from_quotation=...
     * GET /tenant/invoices/create?from_quotation=...
     * GET /v1/tenant/invoices/create?from_quotation=...
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

        $quotationId = $request->input('from_quotation') ?: $request->input('quotation_id');
        $quotation = null;

        if ($quotationId) {
            $query = Quotation::withoutGlobalScope('company');
            if ($companyId) {
                $query->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                    if (Schema::hasColumn('sales', 'tenant_id')) {
                        $q->orWhere('tenant_id', $companyId);
                    }
                });
            }
            $quotation = $query->with('customer')->find($quotationId);
        }

        $customer = $quotation?->customer;
        $customerName = $customer?->name ?: ($quotation?->customer_name ?: 'Walk-in Customer');
        $quotationNumber = $quotation?->quotation_number ?? $quotation?->sale_number ?? ($quotationId ? (string) $quotationId : 'N/A');
        $subtotal = (float) ($quotation?->total_amount ?? $quotation?->total ?? $quotation?->net_amount ?? 0.00);

        $components = [
            [
                'type' => 'card',
                'style' => [
                    'backgroundColor' => 'theme.surface',
                    'borderColor' => 'theme.divider',
                    'borderRadius' => 12,
                    'padding' => 16,
                ],
                'components' => [
                    [
                        'type' => 'text',
                        'text' => 'Customer: ' . $customerName,
                        'style' => ['fontSize' => 16, 'fontWeight' => 'bold', 'color' => '#F8FAFC'],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Source Quotation: #' . $quotationNumber,
                        'style' => ['fontSize' => 13, 'color' => '#94A3B8', 'marginTop' => 4],
                    ],
                    [
                        'type' => 'hidden',
                        'name' => 'quotation_id',
                        'value' => $quotation?->id,
                        'initial_value' => $quotation?->id,
                    ],
                    [
                        'type' => 'hidden',
                        'name' => 'customer_id',
                        'value' => $customer?->id,
                        'initial_value' => $customer?->id,
                    ],
                    [
                        'type' => 'number',
                        'name' => 'subtotal',
                        'label' => 'Subtotal (Excl. Tax)',
                        'value' => $subtotal,
                        'initial_value' => $subtotal,
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'name' => 'tax_rate',
                        'label' => 'GST / Tax Slab',
                        'defaultValue' => '18',
                        'value' => '18',
                        'initial_value' => '18',
                        'options' => [
                            ['label' => 'GST 0%', 'value' => '0'],
                            ['label' => 'GST 5%', 'value' => '5'],
                            ['label' => 'GST 12%', 'value' => '12'],
                            ['label' => 'GST 18%', 'value' => '18'],
                            ['label' => 'GST 28%', 'value' => '28'],
                        ],
                    ],
                    [
                        'type' => 'input',
                        'name' => 'notes',
                        'label' => 'Invoice Notes / Delivery Terms',
                        'value' => $quotation?->notes ?? '',
                        'initial_value' => $quotation?->notes ?? '',
                    ],
                ],
                'children' => [
                    [
                        'type' => 'text',
                        'text' => 'Customer: ' . $customerName,
                        'style' => ['fontSize' => 16, 'fontWeight' => 'bold', 'color' => '#F8FAFC'],
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Source Quotation: #' . $quotationNumber,
                        'style' => ['fontSize' => 13, 'color' => '#94A3B8', 'marginTop' => 4],
                    ],
                    [
                        'type' => 'hidden',
                        'name' => 'quotation_id',
                        'value' => $quotation?->id,
                        'initial_value' => $quotation?->id,
                    ],
                    [
                        'type' => 'hidden',
                        'name' => 'customer_id',
                        'value' => $customer?->id,
                        'initial_value' => $customer?->id,
                    ],
                    [
                        'type' => 'number',
                        'name' => 'subtotal',
                        'label' => 'Subtotal (Excl. Tax)',
                        'value' => $subtotal,
                        'initial_value' => $subtotal,
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'name' => 'tax_rate',
                        'label' => 'GST / Tax Slab',
                        'defaultValue' => '18',
                        'value' => '18',
                        'initial_value' => '18',
                        'options' => [
                            ['label' => 'GST 0%', 'value' => '0'],
                            ['label' => 'GST 5%', 'value' => '5'],
                            ['label' => 'GST 12%', 'value' => '12'],
                            ['label' => 'GST 18%', 'value' => '18'],
                            ['label' => 'GST 28%', 'value' => '28'],
                        ],
                    ],
                    [
                        'type' => 'input',
                        'name' => 'notes',
                        'label' => 'Invoice Notes / Delivery Terms',
                        'value' => $quotation?->notes ?? '',
                        'initial_value' => $quotation?->notes ?? '',
                    ],
                ],
            ],
            [
                'type' => 'button',
                'label' => 'Generate & Issue Tax Invoice',
                'variant' => 'primary',
                'action' => [
                    'type' => 'SUBMIT_FORM',
                    'action_type' => 'SUBMIT_FORM',
                    'endpoint' => '/api/v1/tenant/invoices',
                    'method' => 'POST',
                    'success_toast' => 'Tax invoice issued successfully.',
                    'navigate_back' => true,
                    'reload' => true,
                ],
            ],
        ];

        $schema = [
            'type' => 'screen',
            'screen' => 'InvoiceCreateScreen',
            'title' => 'New Tax Invoice',
            'layout' => 'scroll_view',
            'app_bar' => [
                'title' => 'New Tax Invoice',
                'show_back' => true,
                'show_back_button' => true,
            ],
            'components' => $components,
        ];

        return response()->json(array_merge([
            'success' => true,
            'screen' => 'InvoiceCreateScreen',
            'title' => 'New Tax Invoice',
            'app_bar' => [
                'title' => 'New Tax Invoice',
                'show_back' => true,
                'show_back_button' => true,
            ],
            'components' => $components,
            'schema' => $schema,
        ], $schema), 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Issue Tax Invoice from SDUI form submission.
     * POST /api/v1/tenant/invoices
     * POST /tenant/invoices
     */
    public function store(Request $request): JsonResponse
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
        if (! $company) {
            return response()->json(['success' => false, 'error' => 'Tenant company context required.'], 403);
        }

        $user = $this->resolveUser($request, $company);

        $quotationId = $request->input('quotation_id') ?: $request->input('from_quotation');
        $quote = null;
        if ($quotationId) {
            $quote = Quotation::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->find($quotationId);
        }

        $customerId = $request->input('customer_id') ?: $quote?->customer_id;
        $customer = $customerId ? Customer::withoutGlobalScope('company')->find($customerId) : null;
        $customerName = $customer?->name ?: ($quote?->customer_name ?: 'Walk-in Customer');

        $subtotal = (float) ($request->input('subtotal') ?? ($quote?->total ?? 0.00));
        $taxRate = (float) ($request->input('tax_rate', 18));
        $taxAmount = round(($subtotal * $taxRate) / 100, 2);
        $total = round($subtotal + $taxAmount, 2);

        $items = $quote && is_array($quote->items) && ! empty($quote->items)
            ? $quote->items
            : [
                [
                    'name' => $quote?->notes ? 'Scope: ' . Str::limit($quote->notes, 60) : 'Quotation Services / Goods',
                    'quantity' => 1,
                    'unit_price' => $subtotal,
                    'total' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                ],
            ];

        $notes = $request->input('notes') ?: ($quote?->notes ?: null);

        if (! app(\App\Services\Subscription\SubscriptionEntitlementService::class)->canCreateInvoice($company->id)) {
            return response()->json([
                'message' => 'Invoice limit reached for your subscription plan. Please upgrade your plan.',
            ], 403);
        }

        $sale = DB::transaction(function () use ($company, $user, $quote, $customerId, $customerName, $subtotal, $taxRate, $taxAmount, $total, $items, $notes) {
            $saleNumber = 'INV-' . strtoupper(Str::random(8));

            $sale = Sale::create([
                'company_id' => $company->id,
                'external_id' => Str::uuid()->toString(),
                'sale_number' => $saleNumber,
                'user_id' => $user?->id,
                'customer_id' => $customerId,
                'lead_id' => $quote?->lead_id,
                'customer_name' => $customerName,
                'total' => $total,
                'net_amount' => $subtotal,
                'discount' => 0,
                'tax_amount' => $taxAmount,
                'tax_name' => 'GST ' . $taxRate . '%',
                'tax_rate' => $taxRate,
                'status' => 'completed',
                'payment_status' => 'paid',
                'paid_amount' => $total,
                'due_amount' => 0,
                'operation_type' => 'sale',
                'notes' => $notes,
                'items' => $items,
            ]);

            if ($quote) {
                $quote->update(['status' => 'converted']);

                if ($quote->lead_id) {
                    $lead = Lead::find($quote->lead_id);
                    if ($lead) {
                        $lead->update([
                            'stage' => 'won',
                            'status' => 'won',
                            'converted_at' => now(),
                        ]);

                        if (class_exists(\Modules\leadmanagement\Models\LeadActivity::class)) {
                            \Modules\leadmanagement\Models\LeadActivity::create([
                                'company_id' => $company->id,
                                'lead_id' => $lead->id,
                                'type' => 'note',
                                'title' => 'Sale Finalized',
                                'description' => "Quotation #{$quote->sale_number} converted to Tax Invoice #{$saleNumber}. Lead Won.",
                                'status' => 'completed',
                                'completed_at' => now(),
                            ]);
                        }
                    }
                }
            }

            return $sale;
        });

        return response()->json([
            'success' => true,
            'message' => 'Tax invoice issued successfully.',
            'invoice_id' => $sale->id,
            'invoice_number' => $sale->sale_number,
            'sale' => $sale,
        ], 200);
    }

    /**
     * SDUI Bottom Sheet Schema for Invoice Actions (Preview, Print, WhatsApp, SMS, Email).
     * GET /api/tenant/invoices/{id}/actions-sheet
     * GET /api/v1/tenant/invoices/{id}/actions-sheet
     * GET /tenant/invoices/{id}/actions-sheet
     * GET /v1/tenant/invoices/{id}/actions-sheet
     */
    public function actionsSheet(mixed $arg1 = null, mixed $arg2 = null): JsonResponse
    {
        $id = ($arg1 instanceof Request) ? ($arg2 ?? $arg1->route('id') ?? $arg1->input('id') ?? $arg1->input('invoice_id') ?? $arg1->input('sale_id')) : ($arg1 ?? request()->route('id') ?? request()->input('id') ?? request()->input('invoice_id') ?? request()->input('sale_id'));
        $tenantId = auth()->user()?->tenant_id ?? auth()->user()?->company_id ?? 1;

        $query = Sale::withoutGlobalScope('company')
            ->where(function ($q) use ($tenantId) {
                $q->where('company_id', $tenantId);
                if (Schema::hasColumn('sales', 'tenant_id')) {
                    $q->orWhere('tenant_id', $tenantId);
                }
            })
            ->with(['customer', 'company']);

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
            $sale = $query->first();
        } else {
            $sale = $query->latest('id')->first();
        }

        if (! $sale) {
            $sale = Sale::withoutGlobalScope('company')->with(['customer', 'company'])->latest('id')->first();
        }

        if (! $sale) {
            return response()->json([
                'success' => false,
                'error'   => 'Invoice record not found.',
            ], 404);
        }

        $customer = $sale->customer;
        $customerName = $customer?->name ?: ($sale->customer_name ?: 'Valued Customer');
        $phone = preg_replace('/[^0-9+]/', '', (string) ($customer?->phone ?? $sale->customer_phone ?? ''));
        $email = trim((string) ($customer?->email ?? $sale->customer_email ?? ''));
        $currency = $sale->company?->currency_symbol ?: (auth()->user()?->company?->currency_symbol ?: '₹');
        $totalAmount = (float) ($sale->total ?? 0);

        $docType = $sale->operation_type === 'quotation' ? 'quotation' : 'invoice';

        // Preserve PDF file sharing alongside the universal dispatch controls.
        $baseActions = [
            [
                'type'     => 'list_tile',
                'title'    => 'Share as PDF file',
                'subtitle' => 'Send the invoice PDF via any app',
                'leading'  => ['type' => 'icon', 'icon' => 'share', 'size' => 22],
                'action'   => ['type' => 'SYSTEM_SHARE_FILE', 'url' => "/tenant/documents/{$docType}/{$sale->id}/pdf"],
            ],
        ];

        // Dynamically inject the newly tested Omnichannel channels:
        $dynamicChannels = \App\Services\OmnichannelRegistryService::resolveChannels(
            $sale->tenant_id ?? $sale->company_id ?? $tenantId,
            [
                'id'        => $sale->id,
                'type'      => $docType,
                'reference' => $sale->sale_number ?? "DOC-{$sale->id}",
                'phone'     => $phone,
                'email'     => $email,
            ]
        );

        $allComponents = array_merge(\App\Services\DispatchChannelService::groupedComponents($dynamicChannels, ['type' => $docType, 'id' => $sale->id, 'phone' => $phone, 'email' => $email]), $baseActions);
        $gstin = $sale->company?->tax_id ?? ($sale->company?->document ?? (auth()->user()?->tenant?->gstin ?? 'N/A'));

        return response()->json([
            'type'       => 'bottom_sheet',
            'title'      => ($docType === 'quotation' ? 'Quotation Preview' : 'Invoice Preview'),
            'header'     => [
                'title'    => $sale->sale_number,
                'subtitle' => 'GSTIN: ' . $gstin,
            ],
            'components' => $allComponents,
            'channels' => \App\Services\DispatchChannelService::visibleChannels($allComponents),
            'enabled_channels' => \App\Services\DispatchChannelService::splitChannels(\App\Services\DispatchChannelService::visibleChannels($allComponents))['api'],
            'device_channels' => \App\Services\DispatchChannelService::splitChannels($dynamicChannels)['device'],
            'multi_select' => true,
            'schema'     => [
                'type'       => 'bottom_sheet',
                'title'      => 'Invoice Preview',
                'header'     => [
                    'title'    => $sale->sale_number,
                    'subtitle' => 'GSTIN: ' . $gstin,
                ],
                'components' => $allComponents,
            ],
            'success'        => true,
            'sale_id'        => (string) $sale->id,
            'invoice_number' => $sale->sale_number,
            'customer_name'  => $customerName,
            'total'          => $totalAmount,
        ], 200, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }
}
