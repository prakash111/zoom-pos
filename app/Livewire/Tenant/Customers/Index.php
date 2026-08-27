<?php

namespace App\Livewire\Tenant\Customers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\OrderPayment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Customers'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $document = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public ?int $pointsAdjustId = null;

    public int $pointsDelta = 0;

    // Customer Credit & Financial Ledger Modal
    public bool $showCreditModal = false;

    public ?Customer $creditCustomer = null;

    public $creditSales = [];

    // Quick Debt Settlement Modal
    public bool $showSettleModal = false;

    public ?int $selectedSaleId = null;

    public ?Sale $selectedSale = null;

    public float $paymentAmount = 0.0;

    public string $paymentMethod = 'cash';

    public string $paymentNotes = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function newCustomer(): void
    {
        $this->reset(['editingId', 'name', 'document', 'email', 'phone', 'address', 'city', 'state']);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->editingId = $customer->id;
        $this->name = $customer->name;
        $this->document = (string) $customer->document;
        $this->email = (string) $customer->email;
        $this->phone = (string) $customer->phone;
        $this->address = (string) $customer->address;
        $this->city = (string) $customer->city;
        $this->state = (string) $customer->state;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        $customer = Customer::updateOrCreate(['id' => $this->editingId], $data);
        AuditLog::record($this->editingId ? 'customer.updated' : 'customer.created', $customer->company_id, auth('web')->id(), ['customer_id' => $customer->id]);
        $this->showForm = false;
        session()->flash('status', 'Customer saved.');
    }

    public function delete(int $id): void
    {
        Customer::findOrFail($id)->delete();
        session()->flash('status', 'Customer deleted.');
    }

    public function startPointsAdjust(int $id): void
    {
        $this->pointsAdjustId = $id;
        $this->pointsDelta = 0;
    }

    public function applyPointsAdjust(): void
    {
        $this->validate(['pointsDelta' => ['required', 'integer', 'not_in:0']]);

        $customer = Customer::findOrFail($this->pointsAdjustId);
        $newTotal = max(0, $customer->loyalty_points + $this->pointsDelta);
        $customer->update(['loyalty_points' => $newTotal]);

        AuditLog::record('customer.loyalty_adjusted', $customer->company_id, auth('web')->id(), [
            'customer_id' => $customer->id, 'delta' => $this->pointsDelta, 'new_total' => $newTotal,
        ]);

        $this->pointsAdjustId = null;
        session()->flash('status', 'Loyalty points updated.');
    }

    public function openCreditLedger(int $customerId): void
    {
        $customer = Customer::findOrFail($customerId);
        $this->creditCustomer = $customer;
        $this->loadCreditSales();
        $this->showCreditModal = true;
    }

    public function closeCreditLedger(): void
    {
        $this->showCreditModal = false;
        $this->creditCustomer = null;
        $this->creditSales = [];
    }

    public function loadCreditSales(): void
    {
        if ($this->creditCustomer) {
            $this->creditSales = Sale::where('customer_id', $this->creditCustomer->id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) {
                    $q->where('due_amount', '>', 0)
                        ->orWhere('payment_method', 'credit')
                        ->orWhere('payment_status', 'pending')
                        ->orWhere('payment_status', 'partially_paid');
                })
                ->orderByDesc('created_at')
                ->get();
        }
    }

    public function openSettleModal(int $saleId): void
    {
        $sale = Sale::findOrFail($saleId);
        $this->selectedSaleId = $sale->id;
        $this->selectedSale = $sale;
        $this->paymentAmount = (float) $sale->due_amount;
        $this->paymentMethod = 'cash';
        $this->paymentNotes = '';
        $this->showSettleModal = true;
    }

    public function closeSettleModal(): void
    {
        $this->showSettleModal = false;
        $this->selectedSaleId = null;
        $this->selectedSale = null;
    }

    public function recordSettlement(): void
    {
        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentMethod' => ['required', 'string'],
            'paymentNotes' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $this->selectedSale) {
            return;
        }

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        DB::transaction(function () use ($companyId) {
            $sale = Sale::lockForUpdate()->find($this->selectedSaleId);
            if (! $sale) {
                return;
            }

            $payVal = min((float) $this->paymentAmount, (float) $sale->due_amount > 0 ? (float) $sale->due_amount : (float) $this->paymentAmount);
            $newPaid = round((float) $sale->paid_amount + $payVal, 2);
            $newDue = max(0, round((float) $sale->total - $newPaid, 2));
            $newStatus = $newDue <= 0.001 ? 'paid' : 'partially_paid';

            OrderPayment::create([
                'company_id' => $companyId,
                'sale_id' => $sale->id,
                'payment_method' => $this->paymentMethod,
                'amount' => $payVal,
                'tendered' => $payVal,
                'change_returned' => 0,
                'notes' => $this->paymentNotes ?: ('Customer ledger debt settlement on '.now()->format('Y-m-d H:i')),
            ]);

            $sale->update([
                'paid_amount' => $newPaid,
                'due_amount' => $newDue,
                'payment_status' => $newStatus,
            ]);

            AuditLog::record('customer.debt_settled', $companyId, auth('web')->id(), [
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'amount' => $payVal,
                'remaining_due' => $newDue,
            ]);
        });

        session()->flash('status', 'Debt settlement of $'.number_format($this->paymentAmount, 2).' logged successfully.');
        $this->closeSettleModal();
        $this->loadCreditSales();
    }

    public function render()
    {
        $customers = Customer::query()
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.tenant.customers.index', [
            'customers' => $customers,
            'company' => auth('web')->user()?->company,
        ]);
    }
}
