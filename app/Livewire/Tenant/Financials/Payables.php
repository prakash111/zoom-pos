<?php

namespace App\Livewire\Tenant\Financials;

use App\Models\AuditLog;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\VendorBill;
use App\Models\VendorBillPayment;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Accounts Payable & Expenses'])]
class Payables extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public string $statusFilter = ''; // '' (all), 'pending', 'partially_paid', 'paid', 'overdue'

    public string $categoryFilter = '';

    public ?int $supplierFilter = null;

    // Create / Edit Vendor Bill Modal State
    public bool $showBillModal = false;

    public ?int $editingBillId = null;

    public ?int $supplierId = null;

    public string $vendorName = '';

    public string $billNumber = '';

    public string $category = 'inventory';

    public string $title = '';

    public float $amount = 0.0;

    public float $taxAmount = 0.0;

    public string $billDate = '';

    public ?string $dueDate = null;

    public string $notes = '';

    public $attachment = null;

    // Record Payment Modal State
    public bool $showPaymentModal = false;

    public ?int $selectedBillId = null;

    public ?VendorBill $selectedBill = null;

    public float $settlementAmount = 0.0;

    public string $settlementMethod = 'bank_transfer';

    public string $settlementDate = '';

    public string $referenceNumber = '';

    public string $settlementNotes = '';

    public $settlementProof = null;

    public function mount(): void
    {
        $this->billDate = now()->format('Y-m-d');
        $this->settlementDate = now()->format('Y-m-d');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSupplierFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateBillModal(): void
    {
        $this->editingBillId = null;
        $this->supplierId = null;
        $this->vendorName = '';
        $this->billNumber = 'BILL-'.strtoupper(bin2hex(random_bytes(3)));
        $this->category = 'inventory';
        $this->title = '';
        $this->amount = 0.0;
        $this->taxAmount = 0.0;
        $this->billDate = now()->format('Y-m-d');
        $this->dueDate = now()->addDays(14)->format('Y-m-d');
        $this->notes = '';
        $this->attachment = null;
        $this->showBillModal = true;
    }

    public function openEditBillModal(int $id): void
    {
        $bill = VendorBill::findOrFail($id);
        $this->editingBillId = $bill->id;
        $this->supplierId = $bill->supplier_id;
        $this->vendorName = (string) $bill->vendor_name;
        $this->billNumber = $bill->bill_number;
        $this->category = $bill->category;
        $this->title = $bill->title;
        $this->amount = (float) $bill->amount;
        $this->taxAmount = (float) $bill->tax_amount;
        $this->billDate = $bill->bill_date ? $bill->bill_date->format('Y-m-d') : now()->format('Y-m-d');
        $this->dueDate = $bill->due_date ? $bill->due_date->format('Y-m-d') : null;
        $this->notes = (string) $bill->notes;
        $this->attachment = null;
        $this->showBillModal = true;
    }

    public function closeBillModal(): void
    {
        $this->showBillModal = false;
        $this->editingBillId = null;
    }

    public function saveBill(): void
    {
        $this->validate([
            'billNumber' => ['required', 'string', 'max:50'],
            'category' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'taxAmount' => ['numeric', 'min:0'],
            'billDate' => ['required', 'date'],
            'dueDate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:10240'], // 10MB
        ]);

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $attachmentPath = null;
        if ($this->attachment) {
            $attachmentPath = $this->attachment->store('vendor-bills', 'public');
        }

        $supplier = $this->supplierId ? Supplier::find($this->supplierId) : null;
        $effectiveVendorName = $supplier ? $supplier->name : ($this->vendorName ?: 'General Vendor');

        if ($this->editingBillId) {
            $bill = VendorBill::findOrFail($this->editingBillId);
            $newDue = max(0, round(($this->amount + $this->taxAmount) - (float) $bill->paid_amount, 2));
            $newStatus = $newDue <= 0.001 ? 'paid' : ((float) $bill->paid_amount > 0 ? 'partially_paid' : 'pending');

            $data = [
                'supplier_id' => $this->supplierId,
                'vendor_name' => $effectiveVendorName,
                'bill_number' => $this->billNumber,
                'category' => $this->category,
                'title' => $this->title,
                'amount' => $this->amount,
                'tax_amount' => $this->taxAmount,
                'due_amount' => $newDue,
                'status' => $newStatus,
                'bill_date' => $this->billDate,
                'due_date' => $this->dueDate ?: null,
                'notes' => $this->notes ?: null,
            ];

            if ($attachmentPath) {
                $data['attachment_path'] = $attachmentPath;
            }

            $bill->update($data);

            AuditLog::record('financials.vendor_bill_updated', $companyId, auth('web')->id(), [
                'bill_id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'amount' => $bill->amount,
            ]);

            session()->flash('status', "Vendor Bill #{$bill->bill_number} updated successfully.");
        } else {
            $totalBill = round($this->amount + $this->taxAmount, 2);

            $bill = VendorBill::create([
                'company_id' => $companyId,
                'supplier_id' => $this->supplierId,
                'vendor_name' => $effectiveVendorName,
                'bill_number' => $this->billNumber,
                'category' => $this->category,
                'title' => $this->title,
                'amount' => $this->amount,
                'tax_amount' => $this->taxAmount,
                'paid_amount' => 0.0,
                'due_amount' => $totalBill,
                'status' => 'pending',
                'bill_date' => $this->billDate,
                'due_date' => $this->dueDate ?: null,
                'attachment_path' => $attachmentPath,
                'notes' => $this->notes ?: null,
                'created_by' => auth('web')->id(),
            ]);

            AuditLog::record('financials.vendor_bill_created', $companyId, auth('web')->id(), [
                'bill_id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'amount' => $bill->amount,
                'category' => $bill->category,
            ]);

            session()->flash('status', "Vendor Bill #{$bill->bill_number} created successfully.");
        }

        $this->closeBillModal();
    }

    public function openPaymentModal(int $billId): void
    {
        $bill = VendorBill::with('supplier', 'payments')->findOrFail($billId);
        $this->selectedBillId = $bill->id;
        $this->selectedBill = $bill;
        $this->settlementAmount = (float) $bill->due_amount;
        $this->settlementMethod = 'bank_transfer';
        $this->settlementDate = now()->format('Y-m-d');
        $this->referenceNumber = '';
        $this->settlementNotes = '';
        $this->settlementProof = null;
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->selectedBillId = null;
        $this->selectedBill = null;
    }

    public function recordSettlement(): void
    {
        $this->validate([
            'settlementAmount' => ['required', 'numeric', 'min:0.01'],
            'settlementMethod' => ['required', 'string'],
            'settlementDate' => ['required', 'date'],
            'referenceNumber' => ['nullable', 'string', 'max:100'],
            'settlementNotes' => ['nullable', 'string', 'max:500'],
            'settlementProof' => ['nullable', 'file', 'max:10240'],
        ]);

        if (! $this->selectedBill) {
            return;
        }

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $proofPath = null;
        if ($this->settlementProof) {
            $proofPath = $this->settlementProof->store('bill-payments', 'public');
        }

        DB::transaction(function () use ($companyId, $proofPath) {
            $bill = VendorBill::lockForUpdate()->find($this->selectedBillId);
            if (! $bill) {
                return;
            }

            $payVal = min((float) $this->settlementAmount, (float) $bill->due_amount > 0 ? (float) $bill->due_amount : (float) $this->settlementAmount);
            $newPaid = round((float) $bill->paid_amount + $payVal, 2);
            $totalBill = round((float) $bill->amount + (float) $bill->tax_amount, 2);
            $newDue = max(0, round($totalBill - $newPaid, 2));
            $newStatus = $newDue <= 0.001 ? 'paid' : 'partially_paid';

            VendorBillPayment::create([
                'vendor_bill_id' => $bill->id,
                'amount' => $payVal,
                'payment_method' => $this->settlementMethod,
                'payment_date' => $this->settlementDate,
                'reference_number' => $this->referenceNumber ?: null,
                'attachment_path' => $proofPath,
                'notes' => $this->settlementNotes ?: null,
                'created_by' => auth('web')->id(),
            ]);

            $bill->update([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'status' => $newStatus,
                'paid_at' => $newStatus === 'paid' ? now() : null,
                'payment_method' => $this->settlementMethod,
            ]);

            AuditLog::record('financials.vendor_bill_paid', $companyId, auth('web')->id(), [
                'bill_id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'amount' => $payVal,
                'remaining_due' => $newDue,
            ]);
        });

        session()->flash('status', 'Settlement payment of $'.number_format($this->settlementAmount, 2)." recorded for Bill #{$this->selectedBill->bill_number}.");
        $this->closePaymentModal();
    }

    public function deleteBill(int $id): void
    {
        $bill = VendorBill::findOrFail($id);
        if ($bill->paid_amount > 0) {
            session()->flash('error', 'Cannot delete a bill that already has recorded settlement payments.');

            return;
        }

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $bill->delete();
        AuditLog::record('financials.vendor_bill_deleted', $companyId, auth('web')->id(), [
            'bill_number' => $bill->bill_number,
        ]);

        session()->flash('status', 'Vendor bill deleted.');
    }

    public function render()
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        // KPI Calculations
        $basePayables = VendorBill::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', 'cancelled');

        $totalOutstandingPayables = (float) (clone $basePayables)
            ->where('due_amount', '>', 0)
            ->sum('due_amount');

        $totalDueToday = (float) (clone $basePayables)
            ->where('due_amount', '>', 0)
            ->whereDate('due_date', now()->toDateString())
            ->sum('due_amount');

        $upcomingExpenses = (float) (clone $basePayables)
            ->where('due_amount', '>', 0)
            ->whereBetween('due_date', [now()->addDay()->toDateString(), now()->addDays(30)->toDateString()])
            ->sum('due_amount');

        $settledThisMonth = (float) VendorBillPayment::query()
            ->whereHas('vendorBill', fn ($q) => $q->where('company_id', $companyId))
            ->whereBetween('payment_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount');

        // Query Bills
        $billsQuery = VendorBill::query()
            ->with('supplier', 'payments', 'creator')
            ->where('company_id', $companyId)
            ->when($this->search, function ($q) {
                $t = '%'.trim($this->search).'%';
                $q->where(fn ($sq) => $sq->where('bill_number', 'like', $t)
                    ->orWhere('title', 'like', $t)
                    ->orWhere('vendor_name', 'like', $t)
                );
            })
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->when($this->supplierFilter, fn ($q) => $q->where('supplier_id', $this->supplierFilter))
            ->when($this->statusFilter, function ($q) {
                if ($this->statusFilter === 'overdue') {
                    $q->where('due_amount', '>', 0)->where('due_date', '<', now()->toDateString());
                } elseif ($this->statusFilter === 'pending') {
                    $q->where('due_amount', '>', 0)->where('paid_amount', '<=', 0);
                } elseif ($this->statusFilter === 'partially_paid') {
                    $q->where('due_amount', '>', 0)->where('paid_amount', '>', 0);
                } elseif ($this->statusFilter === 'paid') {
                    $q->where('due_amount', '<=', 0);
                }
            });

        $bills = $billsQuery->orderByDesc('bill_date')->paginate(15);
        $suppliers = Supplier::where('company_id', $companyId)->orderBy('name')->get();
        $paymentMethods = PaymentMethod::getForCompany($companyId);

        return view('livewire.tenant.financials.payables', [
            'bills' => $bills,
            'suppliers' => $suppliers,
            'paymentMethods' => $paymentMethods,
            'totalOutstandingPayables' => $totalOutstandingPayables,
            'totalDueToday' => $totalDueToday,
            'upcomingExpenses' => $upcomingExpenses,
            'settledThisMonth' => $settledThisMonth,
        ]);
    }
}
