<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\VendorBill;
use App\Models\VendorBillPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Mirrors app/Livewire/Tenant/Financials/Payables.php's create/settle logic
 * exactly (same paid_amount/due_amount/status transitions) so mobile figures
 * match the web dashboard's Accounts Payable tab.
 */
class PayablesApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $query = VendorBill::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with('supplier');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        $bills = $query->orderByDesc('bill_date')->get()->map(fn (VendorBill $b) => $this->present($b));

        return response()->json([
            'success' => true,
            'total_payable' => (float) VendorBill::withoutGlobalScope('company')
                ->where('company_id', $company->id)->where('due_amount', '>', 0)->sum('due_amount'),
            'overdue_payable' => (float) VendorBill::withoutGlobalScope('company')
                ->where('company_id', $company->id)->where('due_amount', '>', 0)
                ->where('due_date', '<', now()->toDateString())->sum('due_amount'),
            'bills' => $bills,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'supplier_id' => ['nullable'],
            'vendor_name' => ['nullable', 'string', 'max:150'],
            'bill_number' => ['nullable', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:64'],
            'title' => ['nullable', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error while creating bill.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $taxAmount = (float) ($data['tax_amount'] ?? 0);
        $totalBill = round((float) $data['amount'] + $taxAmount, 2);

        $bill = VendorBill::create([
            'company_id' => $company->id,
            'supplier_id' => $data['supplier_id'] ?? null,
            'vendor_name' => $data['vendor_name'] ?? null,
            'bill_number' => $data['bill_number'] ?? ('BILL-'.strtoupper(uniqid())),
            'category' => $data['category'],
            'title' => $data['title'] ?? ucfirst($data['category']),
            'amount' => $data['amount'],
            'tax_amount' => $taxAmount,
            'paid_amount' => 0.0,
            'due_amount' => $totalBill,
            'status' => 'pending',
            'bill_date' => $data['bill_date'],
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $user?->id,
        ]);

        AuditLog::record('financials.vendor_bill_created', $company->id, $user?->id, [
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'amount' => (float) $bill->amount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bill created.',
            'bill' => $this->present($bill),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $bill = $this->findBill($company, $id);

        if (! $bill) {
            return response()->json(['success' => false, 'error' => 'Bill not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'supplier_id' => ['nullable'],
            'vendor_name' => ['nullable', 'string', 'max:150'],
            'bill_number' => ['nullable', 'string', 'max:100'],
            'category' => ['required', 'string', 'max:64'],
            'title' => ['nullable', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error while updating bill.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $taxAmount = (float) ($data['tax_amount'] ?? 0);
        $newDue = max(0, round(((float) $data['amount'] + $taxAmount) - (float) $bill->paid_amount, 2));
        $newStatus = $newDue <= 0.001 ? 'paid' : ((float) $bill->paid_amount > 0 ? 'partially_paid' : 'pending');

        $bill->update([
            'supplier_id' => $data['supplier_id'] ?? $bill->supplier_id,
            'vendor_name' => $data['vendor_name'] ?? $bill->vendor_name,
            'bill_number' => $data['bill_number'] ?? $bill->bill_number,
            'category' => $data['category'],
            'title' => $data['title'] ?? $bill->title,
            'amount' => $data['amount'],
            'tax_amount' => $taxAmount,
            'due_amount' => $newDue,
            'status' => $newStatus,
            'bill_date' => $data['bill_date'],
            'due_date' => $data['due_date'] ?? $bill->due_date,
            'notes' => $data['notes'] ?? $bill->notes,
        ]);

        AuditLog::record('financials.vendor_bill_updated', $company->id, $user?->id, [
            'bill_id' => $bill->id,
            'bill_number' => $bill->bill_number,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bill updated.',
            'bill' => $this->present($bill->fresh()),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $bill = $this->findBill($company, $id);

        if (! $bill) {
            return response()->json(['success' => false, 'error' => 'Bill not found.'], 404);
        }

        $bill->delete();
        AuditLog::record('financials.vendor_bill_deleted', $company->id, $user?->id, ['bill_id' => $id]);

        return response()->json(['success' => true, 'message' => 'Bill deleted.']);
    }

    public function pay(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $bill = $this->findBill($company, $id);

        if (! $bill) {
            return response()->json(['success' => false, 'error' => 'Bill not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:64'],
            'payment_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error while recording payment.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $bill = DB::transaction(function () use ($bill, $data, $company, $user) {
            $bill = VendorBill::withoutGlobalScope('company')->lockForUpdate()->find($bill->id);

            $payVal = min((float) $data['amount'], (float) $bill->due_amount > 0 ? (float) $bill->due_amount : (float) $data['amount']);
            $newPaid = round((float) $bill->paid_amount + $payVal, 2);
            $totalBill = round((float) $bill->amount + (float) $bill->tax_amount, 2);
            $newDue = max(0, round($totalBill - $newPaid, 2));
            $newStatus = $newDue <= 0.001 ? 'paid' : 'partially_paid';

            VendorBillPayment::create([
                'vendor_bill_id' => $bill->id,
                'company_id' => $company->id,
                'amount' => $payVal,
                'payment_method' => $data['payment_method'],
                'payment_date' => $data['payment_date'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            $bill->update([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'status' => $newStatus,
                'paid_at' => $newStatus === 'paid' ? now() : null,
                'payment_method' => $data['payment_method'],
            ]);

            AuditLog::record('financials.vendor_bill_paid', $company->id, $user?->id, [
                'bill_id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'amount' => $payVal,
                'remaining_due' => $newDue,
            ]);

            return $bill;
        });

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded.',
            'bill' => $this->present($bill->fresh()),
        ]);
    }

    private function findBill(Company $company, string $id): ?VendorBill
    {
        return VendorBill::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('external_id', $id);
            })
            ->first();
    }

    private function present(VendorBill $bill): array
    {
        return [
            'id' => $bill->id,
            'supplier_id' => $bill->supplier_id,
            'vendor_name' => $bill->getEffectiveVendorNameAttribute(),
            'bill_number' => $bill->bill_number,
            'category' => $bill->category,
            'title' => $bill->title,
            'amount' => (float) $bill->amount,
            'tax_amount' => (float) $bill->tax_amount,
            'paid_amount' => (float) $bill->paid_amount,
            'due_amount' => (float) $bill->due_amount,
            'status' => $bill->status,
            'bill_date' => $bill->bill_date?->toIso8601String(),
            'due_date' => $bill->due_date?->toIso8601String(),
            'is_overdue' => $bill->isOverdue(),
            'notes' => $bill->notes,
        ];
    }
}
