<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TaxRule;
use App\Services\FiscalEInvoicing\FiscalEInvoicingManager;
use App\Services\TaxCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TaxApiController extends Controller
{
    public function __construct(
        protected TaxCalculationService $taxService,
        protected FiscalEInvoicingManager $einvoiceManager
    ) {}

    protected function resolveCompany(Request $request): Company
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : $request->attributes->get('company_id');

        return Company::findOrFail($companyId);
    }

    /**
     * Calculate Tax Breakdown for Cart Items.
     * POST /api/v1/tax/calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.name' => ['nullable', 'string'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_inclusive' => ['nullable', 'boolean'],
            'items.*.hsn_sac_code' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'integer'],
            'customer' => ['nullable', 'array'],
            'customer.tax_id' => ['nullable', 'string'],
            'customer.is_tax_exempt' => ['nullable', 'boolean'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', 'in:fixed,percent'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = null;
        if ($request->filled('customer_id')) {
            $customer = Customer::where('company_id', $company->id)->find($request->customer_id);
        } elseif ($request->isJson() && $request->has('customer')) {
            $cData = $request->input('customer');
            $customer = new Customer([
                'tax_id' => $cData['tax_id'] ?? null,
                'is_tax_exempt' => ! empty($cData['is_tax_exempt']),
            ]);
        }

        $discount = (float) ($request->input('discount', 0));
        $discountType = $request->input('discount_type', 'fixed');

        $result = $this->taxService->calculateCartTotals(
            $request->input('items', []),
            $company,
            $customer,
            $discount,
            $discountType
        );

        return response()->json([
            'success' => true,
            'jurisdiction' => [
                'country' => $company->country ?: 'US',
                'currency' => $company->currency ?: 'USD',
                'tax_id' => $company->tax_id,
            ],
            'subtotal' => $result['subtotal'],
            'discount' => $result['discount'],
            'tax_amount' => $result['tax_amount'],
            'total' => $result['total'],
            'tax_summary_table' => $result['tax_summary_table'],
            'items' => $result['items'],
        ]);
    }

    /**
     * Issue Fiscal Tax Invoice from External Apps (Shopify, WooCommerce, ERP).
     * POST /api/v1/tax/invoices
     */
    public function issueInvoice(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.name' => ['nullable', 'string'],
            'items.*.product_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['nullable', 'string'],
            'customer.phone' => ['nullable', 'string'],
            'customer.email' => ['nullable', 'email'],
            'customer.tax_id' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'string'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'auto_einvoice' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = null;
        if ($request->filled('customer_id')) {
            $customer = Customer::where('company_id', $company->id)->find($request->customer_id);
        } elseif ($request->filled('customer.name')) {
            $cData = $request->input('customer');
            $customer = Customer::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'phone' => $cData['phone'] ?? null,
                ],
                [
                    'name' => $cData['name'],
                    'email' => $cData['email'] ?? null,
                    'tax_id' => $cData['tax_id'] ?? null,
                    'is_tax_exempt' => ! empty($cData['is_tax_exempt']),
                ]
            );
        }

        $discount = (float) $request->input('discount', 0);
        $calcResult = $this->taxService->calculateCartTotals(
            $request->input('items', []),
            $company,
            $customer,
            $discount,
            'fixed'
        );

        $sale = DB::transaction(function () use ($company, $customer, $calcResult, $request) {
            $saleCount = Sale::where('company_id', $company->id)->count();
            $saleNumber = 'INV-'.now()->format('Ymd').'-'.sprintf('%04d', $saleCount + 1);

            $sale = Sale::create([
                'company_id' => $company->id,
                'sale_number' => $saleNumber,
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->name ?: ($request->input('customer.name') ?: 'External Client'),
                'total' => $calcResult['total'],
                'discount' => $calcResult['discount'],
                'tax_amount' => $calcResult['tax_amount'],
                'tax_breakdown' => $calcResult['tax_summary_table'],
                'payment_method' => $request->input('payment_method', 'api_gateway'),
                'status' => 'completed',
                'items' => $calcResult['items'],
                'notes' => $request->input('notes'),
                'paid_amount' => $calcResult['total'],
                'due_amount' => 0.0,
                'payment_status' => 'paid',
            ]);

            // Decrement inventory if product_id matched
            foreach ($calcResult['items'] as $it) {
                if (! empty($it['product_id'])) {
                    $prod = Product::where('company_id', $company->id)->find($it['product_id']);
                    if ($prod) {
                        $prod->decrement('current_stock', (float) $it['quantity']);
                    }
                }
            }

            return $sale;
        });

        // Optional or automated e-invoicing clearance
        $driver = $this->einvoiceManager->driverForCompany($company);
        $einvoiceResult = null;
        if ($request->boolean('auto_einvoice', true)) {
            $einvoiceResult = $driver->submitInvoice($sale);
        }

        AuditLog::record('api.tax_invoice_issued', $company->id, null, [
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'total' => $sale->total,
            'tax_amount' => $sale->tax_amount,
            'api_token' => substr($request->bearerToken() ?? '', 0, 10).'...',
        ]);

        return response()->json([
            'success' => true,
            'invoice' => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'subtotal' => $calcResult['subtotal'],
                'discount' => $calcResult['discount'],
                'tax_amount' => $calcResult['tax_amount'],
                'total' => $calcResult['total'],
                'tax_summary_table' => $calcResult['tax_summary_table'],
                'items' => $calcResult['items'],
                'einvoice' => $einvoiceResult ?: [
                    'status' => $sale->einvoice_status ?: 'pending',
                    'irn' => $sale->einvoice_irn,
                    'qr' => $sale->einvoice_qr,
                ],
                'created_at' => $sale->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * List Active Tax Rules & Jurisdictions.
     * GET /api/v1/tax/rates
     */
    public function getRates(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $tenantRules = TaxRule::where('company_id', $company->id)->where('active', true)->get();
        $presets = $this->taxService->getJurisdictionPresets($company->country);

        return response()->json([
            'success' => true,
            'country' => $company->country ?: 'US',
            'tax_id' => $company->tax_id,
            'rules' => $tenantRules,
            'jurisdiction_presets' => $presets,
        ]);
    }

    /**
     * Get E-Invoice Standardized Payload & QR.
     * GET /api/v1/tax/einvoice/{sale_id}/payload
     */
    public function getEInvoicePayload(Request $request, string $saleId): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $sale = Sale::where('company_id', $company->id)->findOrFail($saleId);

        $driver = $this->einvoiceManager->driverForSale($sale);
        $payload = $driver->generatePayload($sale);
        $compliance = $driver->validateCompliance($sale);
        $qr = $driver->generateQrCodeData($sale);

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'einvoice_status' => $sale->einvoice_status ?: ($compliance['compliant'] ? 'ready' : 'invalid'),
            'irn' => $sale->einvoice_irn,
            'qr_code_data' => $qr,
            'compliance' => $compliance,
            'payload' => $payload,
        ]);
    }
}
