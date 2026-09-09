<?php

namespace App\Livewire\Tenant\Repair;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\RepairTicket;
use App\Services\Documents\DocumentNumberService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Repair Ticket Register + intake — web parity for
 * RepairApiController::ticketsIndex/ticketsStore and
 * SchemaResponse::repairTicketsView / repairCreateTicketView.
 * Intake here does not take an advance deposit (that path creates a Sale and
 * is kept to the mobile POS / core POS).
 */
#[Layout('layouts.tenant', ['title' => 'Repair Tickets'])]
class Tickets extends Component
{
    #[Url]
    public string $status = 'all';

    #[Url]
    public string $search = '';

    public bool $showForm = false;

    public string $customerName = '';

    public string $customerPhone = '';

    public ?int $categoryId = null;

    public string $brand = '';

    public string $model = '';

    public string $serial = '';

    public string $problemReported = '';

    public string $priority = 'normal';

    public $estimatedCost = 0;

    public $diagnosticFee = 0;

    protected function rules(): array
    {
        return [
            'customerName' => ['nullable', 'string', 'max:150'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
            'categoryId' => ['nullable', 'integer'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial' => ['nullable', 'string', 'max:100'],
            'problemReported' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'estimatedCost' => ['nullable', 'numeric', 'min:0'],
            'diagnosticFee' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function newTicket(): void
    {
        $this->reset(['customerName', 'customerPhone', 'categoryId', 'brand', 'model', 'serial', 'problemReported', 'estimatedCost', 'diagnosticFee']);
        $this->priority = 'normal';
        $this->showForm = true;
    }

    public function create(): void
    {
        $data = $this->validate();
        $company = auth()->user()->company;

        $name = trim($data['customerName']) ?: 'Walk-in Customer';
        $customerId = null;
        if (trim($data['customerName']) !== '') {
            $customer = Customer::where('name', $name)
                ->when($data['customerPhone'], fn ($q) => $q->orWhere('phone', $data['customerPhone']))
                ->first()
                ?? Customer::create(['name' => $name, 'phone' => $data['customerPhone'] ?: null]);
            $customerId = $customer->id;
        }

        $estimated = (float) ($data['estimatedCost'] ?: 0);
        $diagnostic = (float) ($data['diagnosticFee'] ?: 0);

        $checklist = null;
        if ($data['categoryId']) {
            $category = Category::find($data['categoryId']);
            $points = $category?->checklist_points ?? ($category?->metadata['checklist_items'] ?? []);
            if (! empty($points)) {
                $checklist = array_map(fn ($p) => [
                    'item_name' => is_array($p) ? ($p['item_name'] ?? $p['name'] ?? '') : (string) $p,
                    'status' => 'pending',
                    'notes' => null,
                ], $points);
            }
        }

        $ticket = RepairTicket::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'ticket_number' => app(DocumentNumberService::class)->next($company, 'repair'),
            'customer_id' => $customerId,
            'customer_name' => $name,
            'customer_phone' => $data['customerPhone'] ?: null,
            'category_id' => $data['categoryId'] ?: null,
            'brand' => $data['brand'] ?: null,
            'model' => $data['model'] ?: null,
            'serial_number_or_imei' => $data['serial'] ?: null,
            'problem_reported' => trim($data['problemReported']),
            'status' => RepairTicket::STATUS_RECEIVED,
            'priority' => $data['priority'],
            'estimated_cost' => $estimated,
            'diagnostic_fee' => $diagnostic,
            'total_amount' => round($estimated + $diagnostic, 2),
            'inspection_checklist' => $checklist,
            'intake_at' => now(),
            'is_demo' => false,
        ]);

        AuditLog::record('repair.ticket_created', $company->id, auth()->id(), [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
        ]);

        $this->showForm = false;
        session()->flash('status', __('Repair ticket :n created.', ['n' => $ticket->ticket_number]));
        $this->redirectRoute('tenant.repair.ticket', $ticket->id, navigate: true);
    }

    public function render()
    {
        $query = RepairTicket::with('technician:id,name')->latest();

        if (array_key_exists($this->status, RepairTicket::STATUSES)) {
            $query->where('status', $this->status);
        }

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(fn ($q) => $q
                ->where('ticket_number', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('customer_phone', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%"));
        }

        return view('livewire.tenant.repair.tickets', [
            'tickets' => $query->limit(100)->get(),
            'categories' => Category::where('active', true)
                ->where(fn ($q) => $q->where('type', 'device')->orWhereNull('type'))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
