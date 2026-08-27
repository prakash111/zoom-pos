<?php

namespace App\Livewire\Tenant\ServiceOrders;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Service Orders & Warranty Repairs'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $priorityFilter = 'all';

    public string $technicianFilter = 'all';

    // Create / Edit Modal State
    public bool $showModal = false;

    public bool $isEditing = false;

    public ?int $editingOrderId = null;

    public ?int $customerId = null;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerEmail = '';

    public string $equipmentName = '';

    public string $brandModel = '';

    public string $serialNumber = '';

    public string $reportedDefect = '';

    public string $technicalDiagnosis = '';

    /** @var array<int, array{product_id: int|null, name: string, quantity: float, unit_price: float, total: float}> */
    public array $partsUsed = [];

    public $partsTotal = 0.0;

    public $laborCost = 0.0;

    public $discount = 0.0;

    public $totalAmount = 0.0;

    public string $status = ServiceOrder::STATUS_RECEIVED;

    public string $priority = 'normal';

    public string $warrantyPeriod = '90 days';

    public string $warrantyTerms = 'Warranty covers technical repair and replaced parts only. Physical damage, liquid contact, and broken seals void warranty.';

    public ?string $technicianId = null;

    public string $notes = '';

    public string $partSearch = '';

    // View Details / Ticket Modal
    public bool $showViewModal = false;

    public ?ServiceOrder $viewingOrder = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $user = auth('web')->user();
        abort_unless($user && $user->hasPermission('service_orders', 'create'), 403);

        $this->reset([
            'isEditing', 'editingOrderId', 'customerId', 'customerName', 'customerPhone',
            'customerEmail', 'equipmentName', 'brandModel', 'serialNumber', 'reportedDefect',
            'technicalDiagnosis', 'partsUsed', 'notes', 'partSearch',
        ]);

        $this->partsTotal = 0.0;
        $this->laborCost = 0.0;
        $this->discount = 0.0;
        $this->totalAmount = 0.0;
        $this->status = ServiceOrder::STATUS_RECEIVED;
        $this->priority = 'normal';
        $this->warrantyPeriod = '90 days';
        $this->technicianId = (string) auth('web')->id();
        $this->warrantyTerms = 'Warranty covers technical repair and replaced parts only. Physical damage, liquid contact, and broken seals void warranty.';
        $this->showModal = true;
    }

    public function selectCustomer(int $id): void
    {
        $c = Customer::find($id);
        if ($c) {
            $this->customerId = $c->id;
            $this->customerName = $c->name;
            $this->customerPhone = (string) ($c->phone ?? '');
            $this->customerEmail = (string) ($c->email ?? '');
        }
    }

    public function addPart(int $productId): void
    {
        $product = Product::find($productId);
        if (! $product) {
            return;
        }

        $existingIndex = null;
        foreach ($this->partsUsed as $idx => $item) {
            if (($item['product_id'] ?? null) == $product->id) {
                $existingIndex = $idx;
                break;
            }
        }

        if ($existingIndex !== null) {
            $this->partsUsed[$existingIndex]['quantity'] += 1;
            $this->partsUsed[$existingIndex]['total'] = round($this->partsUsed[$existingIndex]['quantity'] * $this->partsUsed[$existingIndex]['unit_price'], 2);
        } else {
            $unitPrice = (float) $product->sale_price;
            $this->partsUsed[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'total' => $unitPrice,
            ];
        }

        $this->partSearch = '';
        $this->recalculateTotals();
    }

    public function removePart(int $index): void
    {
        unset($this->partsUsed[$index]);
        $this->partsUsed = array_values($this->partsUsed);
        $this->recalculateTotals();
    }

    public function updatedPartsUsed(): void
    {
        foreach ($this->partsUsed as $idx => $part) {
            $qty = max(0.1, (float) ($part['quantity'] ?? 1));
            $price = max(0, (float) ($part['unit_price'] ?? 0));
            $this->partsUsed[$idx]['quantity'] = $qty;
            $this->partsUsed[$idx]['unit_price'] = $price;
            $this->partsUsed[$idx]['total'] = round($qty * $price, 2);
        }
        $this->recalculateTotals();
    }

    public function updatedLaborCost(): void
    {
        $this->recalculateTotals();
    }

    public function updatedDiscount(): void
    {
        $this->recalculateTotals();
    }

    public function recalculateTotals(): void
    {
        $parts = 0.0;
        foreach ($this->partsUsed as $p) {
            $parts += (float) ($p['total'] ?? 0);
        }
        $this->partsTotal = round($parts, 2);
        $labor = max(0, (float) ($this->laborCost ?: 0));
        $disc = max(0, (float) ($this->discount ?: 0));
        $this->totalAmount = max(0, round($this->partsTotal + $labor - $disc, 2));
    }

    public function saveServiceOrder(): void
    {
        $this->save();
    }

    public function getSelectedCustomerProperty(): ?Customer
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    public function getGrandTotalProperty(): float
    {
        return (float) ($this->totalAmount ?: 0);
    }

    public function save(): void
    {
        $user = auth('web')->user();
        $action = $this->isEditing ? 'edit' : 'create';
        abort_unless($user && $user->hasPermission('service_orders', $action), 403);

        $this->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'equipmentName' => ['required', 'string', 'max:255'],
            'reportedDefect' => ['required', 'string'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'status' => ['required', 'in:'.implode(',', array_keys(ServiceOrder::STATUSES))],
            'laborCost' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : $user->company_id;

        $this->recalculateTotals();

        $laborCostVal = (float) ($this->laborCost ?: 0);
        $discountVal = (float) ($this->discount ?: 0);
        $partsTotalVal = (float) ($this->partsTotal ?: 0);
        $totalAmountVal = (float) ($this->totalAmount ?: 0);

        if ($this->isEditing && $this->editingOrderId) {
            $order = ServiceOrder::where('company_id', $companyId)->findOrFail($this->editingOrderId);
            $order->update([
                'customer_id' => $this->customerId,
                'customer_name' => $this->customerName,
                'customer_phone' => $this->customerPhone ?: null,
                'customer_email' => $this->customerEmail ?: null,
                'equipment_name' => $this->equipmentName,
                'brand_model' => $this->brandModel ?: null,
                'serial_number' => $this->serialNumber ?: null,
                'reported_defect' => $this->reportedDefect,
                'technical_diagnosis' => $this->technicalDiagnosis ?: null,
                'parts_used' => $this->partsUsed,
                'parts_total' => $partsTotalVal,
                'labor_cost' => $laborCostVal,
                'discount' => $discountVal,
                'total_amount' => $totalAmountVal,
                'status' => $this->status,
                'priority' => $this->priority,
                'warranty_period' => $this->warrantyPeriod ?: '90 days',
                'warranty_terms' => $this->warrantyTerms ?: null,
                'technician_id' => $this->technicianId ?: null,
                'completed_at' => in_array($this->status, [ServiceOrder::STATUS_READY_FOR_PICKUP, ServiceOrder::STATUS_DELIVERED_SETTLED]) ? ($order->completed_at ?: now()) : null,
                'delivered_at' => ($this->status === ServiceOrder::STATUS_DELIVERED_SETTLED) ? ($order->delivered_at ?: now()) : null,
                'notes' => $this->notes ?: null,
            ]);

            AuditLog::record('service_order.updated', $companyId, $user->id, [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ]);

            session()->flash('status', "Service Order #{$order->order_number} successfully updated.");
        } else {
            $orderNumber = ServiceOrder::generateOrderNumber($companyId);
            $order = ServiceOrder::create([
                'company_id' => $companyId,
                'order_number' => $orderNumber,
                'customer_id' => $this->customerId,
                'customer_name' => $this->customerName,
                'customer_phone' => $this->customerPhone ?: null,
                'customer_email' => $this->customerEmail ?: null,
                'equipment_name' => $this->equipmentName,
                'brand_model' => $this->brandModel ?: null,
                'serial_number' => $this->serialNumber ?: null,
                'reported_defect' => $this->reportedDefect,
                'technical_diagnosis' => $this->technicalDiagnosis ?: null,
                'parts_used' => $this->partsUsed,
                'parts_total' => $partsTotalVal,
                'labor_cost' => $laborCostVal,
                'discount' => $discountVal,
                'total_amount' => $totalAmountVal,
                'status' => $this->status,
                'priority' => $this->priority,
                'warranty_period' => $this->warrantyPeriod ?: '90 days',
                'warranty_terms' => $this->warrantyTerms ?: null,
                'received_at' => now(),
                'technician_id' => $this->technicianId ?: null,
                'notes' => $this->notes ?: null,
            ]);

            // Deduct parts stock if selected
            foreach ($this->partsUsed as $part) {
                if (! empty($part['product_id'])) {
                    $prod = Product::find($part['product_id']);
                    if ($prod) {
                        $prod->decrementStock((float) ($part['quantity'] ?? 1), "Parts used for Service Order #{$order->order_number}");
                    }
                }
            }

            AuditLog::record('service_order.created', $companyId, $user->id, [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'equipment' => $order->equipment_name,
            ]);

            session()->flash('status', "Service Order #{$order->order_number} created successfully.");
        }

        $this->showModal = false;
    }

    public function openEditModal(int $id): void
    {
        $user = auth('web')->user();
        abort_unless($user && $user->hasPermission('service_orders', 'edit'), 403);

        $order = ServiceOrder::findOrFail($id);
        $this->editingOrderId = $order->id;
        $this->isEditing = true;
        $this->customerId = $order->customer_id;
        $this->customerName = $order->customer_name;
        $this->customerPhone = (string) ($order->customer_phone ?? '');
        $this->customerEmail = (string) ($order->customer_email ?? '');
        $this->equipmentName = $order->equipment_name;
        $this->brandModel = (string) ($order->brand_model ?? '');
        $this->serialNumber = (string) ($order->serial_number ?? '');
        $this->reportedDefect = (string) $order->reported_defect;
        $this->technicalDiagnosis = (string) ($order->technical_diagnosis ?? '');
        $this->partsUsed = is_array($order->parts_used) ? $order->parts_used : [];
        $this->partsTotal = (float) $order->parts_total;
        $this->laborCost = (float) $order->labor_cost;
        $this->discount = (float) $order->discount;
        $this->totalAmount = (float) $order->total_amount;
        $this->status = $order->status;
        $this->priority = $order->priority;
        $this->warrantyPeriod = (string) ($order->warranty_period ?? '90 days');
        $this->warrantyTerms = (string) ($order->warranty_terms ?? '');
        $this->technicianId = (string) ($order->technician_id ?? '');
        $this->notes = (string) ($order->notes ?? '');
        $this->partSearch = '';
        $this->showModal = true;
    }

    public function openViewModal(int $id): void
    {
        $this->viewingOrder = ServiceOrder::with('customer', 'technician')->findOrFail($id);
        $this->showViewModal = true;
    }

    public function updateOrderStatus(int $id, string $newStatus): void
    {
        $user = auth('web')->user();
        abort_unless($user && $user->hasPermission('service_orders', 'edit'), 403);

        $order = ServiceOrder::findOrFail($id);
        $order->update([
            'status' => $newStatus,
            'completed_at' => in_array($newStatus, [ServiceOrder::STATUS_READY_FOR_PICKUP, ServiceOrder::STATUS_DELIVERED_SETTLED]) ? ($order->completed_at ?: now()) : null,
            'delivered_at' => ($newStatus === ServiceOrder::STATUS_DELIVERED_SETTLED) ? ($order->delivered_at ?: now()) : null,
        ]);

        AuditLog::record('service_order.status_changed', $order->company_id, $user->id, [
            'order_id' => $order->id,
            'new_status' => $newStatus,
        ]);

        session()->flash('status', "Service Order #{$order->order_number} status changed to " . ($order->getStatusInfo()['label'] ?? $newStatus));
    }

    public function deleteOrder(int $id): void
    {
        $user = auth('web')->user();
        abort_unless($user && $user->hasPermission('service_orders', 'delete'), 403);

        $order = ServiceOrder::findOrFail($id);
        $orderNum = $order->order_number;
        $order->delete();

        AuditLog::record('service_order.deleted', $order->company_id, $user->id, [
            'order_id' => $id,
            'order_number' => $orderNum,
        ]);

        session()->flash('status', "Service Order #{$orderNum} was deleted.");
    }

    public function render()
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $company = Company::find($companyId);

        $query = ServiceOrder::where('company_id', $companyId)
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->priorityFilter !== 'all', fn ($q) => $q->where('priority', $this->priorityFilter))
            ->when($this->technicianFilter !== 'all', fn ($q) => $q->where('technician_id', $this->technicianFilter))
            ->when($this->search, function ($q) {
                $t = '%' . $this->search . '%';
                $q->where(function ($sub) use ($t) {
                    $sub->where('order_number', 'like', $t)
                        ->orWhere('customer_name', 'like', $t)
                        ->orWhere('equipment_name', 'like', $t)
                        ->orWhere('brand_model', 'like', $t)
                        ->orWhere('serial_number', 'like', $t)
                        ->orWhere('customer_phone', 'like', $t);
                });
            })
            ->latest();

        $serviceOrders = $query->paginate(12);

        // Status counts for top summary tabs
        $counts = [
            'all' => ServiceOrder::where('company_id', $companyId)->count(),
            ServiceOrder::STATUS_RECEIVED => ServiceOrder::where('company_id', $companyId)->where('status', ServiceOrder::STATUS_RECEIVED)->count(),
            ServiceOrder::STATUS_UNDER_DIAGNOSIS => ServiceOrder::where('company_id', $companyId)->where('status', ServiceOrder::STATUS_UNDER_DIAGNOSIS)->count(),
            ServiceOrder::STATUS_WAITING_PARTS_APPROVAL => ServiceOrder::where('company_id', $companyId)->where('status', ServiceOrder::STATUS_WAITING_PARTS_APPROVAL)->count(),
            ServiceOrder::STATUS_READY_FOR_PICKUP => ServiceOrder::where('company_id', $companyId)->where('status', ServiceOrder::STATUS_READY_FOR_PICKUP)->count(),
            ServiceOrder::STATUS_DELIVERED_SETTLED => ServiceOrder::where('company_id', $companyId)->where('status', ServiceOrder::STATUS_DELIVERED_SETTLED)->count(),
        ];

        // Part search results
        $searchedProducts = [];
        if (strlen($this->partSearch) >= 2) {
            $searchedProducts = Product::where('company_id', $companyId)
                ->where(function ($q) {
                    $t = '%' . $this->partSearch . '%';
                    $q->where('name', 'like', $t)
                        ->orWhere('code', 'like', $t)
                        ->orWhere('barcode', 'like', $t);
                })
                ->limit(6)
                ->get();
        }

        $customers = Customer::where('company_id', $companyId)->orderBy('name')->limit(30)->get();
        $technicians = User::where('company_id', $companyId)->orderBy('name')->get();

        return view('livewire.tenant.service-orders.index', [
            'serviceOrders' => $serviceOrders,
            'counts' => $counts,
            'searchedProducts' => $searchedProducts,
            'customers' => $customers,
            'technicians' => $technicians,
            'company' => $company,
        ]);
    }
}
