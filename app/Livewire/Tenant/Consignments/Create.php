<?php

namespace App\Livewire\Tenant\Consignments;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\ConsignmentItem;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Auth\PermissionChecker;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'New Consignment'])]
class Create extends Component
{
    public ?int $customerId = null;

    public string $customerName = '';

    public ?string $dueDate = null;

    public string $notes = '';

    /** @var array<int, array{product_id: ?int, product_name: string, quantity: float, unit_price: float, total: float}> */
    public array $items = [];

    public function mount(): void
    {
        $user = auth('web')->user();
        if ($user && ! PermissionChecker::can($user, 'consignments', 'create') && ! PermissionChecker::can($user, 'sales', 'create')) {
            abort(403, 'Unauthorized.');
        }

        $this->dueDate = now()->addDays(15)->format('Y-m-d');
        $this->addItem();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'product_id' => null,
            'product_name' => '',
            'quantity' => 1.0,
            'unit_price' => 0.0,
            'total' => 0.0,
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        if (empty($this->items)) {
            $this->addItem();
        }
    }

    public function updatedItems($value, $key): void
    {
        if (str_ends_with($key, '.product_id')) {
            $index = (int) explode('.', $key)[0];
            $product = Product::find($this->items[$index]['product_id']);
            if ($product) {
                $this->items[$index]['product_name'] = $product->name;
                $this->items[$index]['unit_price'] = (float) $product->sale_price;
                $this->items[$index]['total'] = round((float) $this->items[$index]['quantity'] * (float) $product->sale_price, 2);
            }
        } elseif (str_ends_with($key, '.quantity') || str_ends_with($key, '.unit_price')) {
            $index = (int) explode('.', $key)[0];
            $qty = (float) ($this->items[$index]['quantity'] ?? 0);
            $price = (float) ($this->items[$index]['unit_price'] ?? 0);
            $this->items[$index]['total'] = round($qty * $price, 2);
        }
    }

    public function getTotalDispatchedProperty(): float
    {
        return round(collect($this->items)->sum(fn ($i) => (float) ($i['total'] ?? 0)), 2);
    }

    public function save(string $status = 'draft')
    {
        $this->validate([
            'customerId' => ['required', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $customer = Customer::find($this->customerId);
        $consignmentNumber = 'CSG-'.now()->format('YmdHis');

        $consignment = DB::transaction(function () use ($companyId, $customer, $consignmentNumber, $status) {
            $c = Consignment::create([
                'company_id' => $companyId,
                'consignment_number' => $consignmentNumber,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'user_id' => auth('web')->id(),
                'status' => $status,
                'dispatched_at' => $status === 'dispatched' ? now() : null,
                'due_date' => $this->dueDate ?: null,
                'notes' => $this->notes ?: null,
            ]);

            foreach ($this->items as $item) {
                ConsignmentItem::create([
                    'consignment_id' => $c->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'dispatched_quantity' => (float) $item['quantity'],
                    'returned_quantity' => 0.0,
                    'sold_quantity' => 0.0,
                    'unit_price' => (float) $item['unit_price'],
                    'sold_total' => 0.0,
                ]);
            }

            $c->recalculateTotals();

            return $c;
        });

        AuditLog::record('consignment.created', $companyId, auth('web')->id(), [
            'consignment_id' => $consignment->id,
            'consignment_number' => $consignmentNumber,
            'status' => $status,
        ]);

        session()->flash('status', "Consignment {$consignmentNumber} created successfully.");
        $this->redirectRoute('tenant.consignments.show', $consignment, navigate: true);
    }

    public function render()
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $customers = Customer::where('company_id', $companyId)->orderBy('name')->get();
        $products = Product::where('company_id', $companyId)->where('active', true)->orderBy('name')->get();
        $company = Company::find($companyId);

        return view('livewire.tenant.consignments.create', [
            'customers' => $customers,
            'products' => $products,
            'company' => $company,
        ]);
    }
}
