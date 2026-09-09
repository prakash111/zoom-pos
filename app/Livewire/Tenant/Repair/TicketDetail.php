<?php

namespace App\Livewire\Tenant\Repair;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\RepairTicket;
use App\Models\RepairTicketItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Technician workbench for a single ticket — web parity for
 * RepairApiController::ticketsShow / ticketsUpdateStatus / ticketsAssign /
 * ticketsAddPart / ticketsRemovePart / ticketsSetLabor / ticketsUpdateChecklist
 * and SchemaResponse::repairDetailView. Stock + total side-effects mirror the API.
 */
#[Layout('layouts.tenant', ['title' => 'Repair Ticket'])]
class TicketDetail extends Component
{
    public RepairTicket $ticket;

    public string $newStatus = '';

    public string $technicianDiagnosis = '';

    public ?string $assignTechnicianId = null;

    // Add part
    public ?int $partProductId = null;

    public string $partName = '';

    public float $partQty = 1;

    public $partUnitPrice = 0;

    // Labor
    public $laborFee = 0;

    public string $laborDescription = '';

    public function mount(RepairTicket $ticket): void
    {
        $this->ticket = $ticket;
        $this->newStatus = $ticket->status;
        $this->technicianDiagnosis = (string) $ticket->technician_diagnosis;
        $this->assignTechnicianId = $ticket->assigned_technician_id;
        $this->laborFee = (float) $ticket->labor_fee;
    }

    public function updateStatus(): void
    {
        $status = strtolower(trim($this->newStatus));
        if (in_array($status, ['repaired', 'ready_pickup', 'ready_for_pickup'], true)) {
            $status = RepairTicket::STATUS_READY;
        }
        if (! array_key_exists($status, RepairTicket::STATUSES)) {
            $this->addError('newStatus', __('Invalid repair status.'));

            return;
        }

        $old = $this->ticket->status;
        $this->ticket->status = $status;
        if ($this->technicianDiagnosis !== '') {
            $this->ticket->technician_diagnosis = $this->technicianDiagnosis;
        }
        if ($status === RepairTicket::STATUS_READY) {
            $this->ticket->completed_at = now();
        } elseif ($status === RepairTicket::STATUS_DELIVERED) {
            $this->ticket->delivered_at = now();
        } elseif ($status === RepairTicket::STATUS_CANCELLED) {
            foreach ($this->ticket->parts()->get() as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)->first()?->increment('current_stock', (float) $item->quantity);
                }
            }
        }
        $this->ticket->save();

        AuditLog::record('repair.status_updated', $this->ticket->company_id, auth()->id(), [
            'ticket_id' => $this->ticket->id, 'old_status' => $old, 'new_status' => $status,
        ]);
        session()->flash('status', __('Ticket status updated.'));
        $this->ticket->refresh();
    }

    public function assign(): void
    {
        $this->validate(['assignTechnicianId' => ['required', 'exists:users,id']]);
        $technician = User::findOrFail($this->assignTechnicianId);
        $this->ticket->update(['assigned_technician_id' => $technician->id]);

        AuditLog::record('repair.technician_assigned', $this->ticket->company_id, auth()->id(), [
            'ticket_id' => $this->ticket->id, 'technician_id' => $technician->id,
        ]);
        session()->flash('status', __('Assigned to :name.', ['name' => $technician->name]));
        $this->ticket->refresh();
    }

    public function addPart(): void
    {
        $this->validate([
            'partName' => ['required_without:partProductId', 'nullable', 'string', 'max:200'],
            'partProductId' => ['nullable', 'integer'],
            'partQty' => ['required', 'numeric', 'min:0.01'],
            'partUnitPrice' => ['nullable', 'numeric', 'min:0'],
        ]);

        $product = $this->partProductId ? Product::find($this->partProductId) : null;
        $name = trim($this->partName ?: ($product?->name ?? 'Spare Part'));
        $qty = max(0.01, (float) $this->partQty);
        $unit = max(0.0, (float) ($this->partUnitPrice ?: ($product?->sale_price ?? 0)));
        $subtotal = round($qty * $unit, 2);

        if ($product && (float) $product->current_stock < $qty) {
            $this->addError('partQty', __('Insufficient stock for :name.', ['name' => $product->name]));

            return;
        }

        DB::transaction(function () use ($product, $name, $qty, $unit, $subtotal) {
            RepairTicketItem::create([
                'company_id' => $this->ticket->company_id,
                'tenant_id' => $this->ticket->company_id,
                'ticket_id' => $this->ticket->id,
                'product_id' => $product?->id,
                'item_name' => $name,
                'item_type' => RepairTicketItem::TYPE_SPARE_PART,
                'quantity' => $qty,
                'unit_price' => $unit,
                'subtotal' => $subtotal,
                'tax_amount' => 0.0,
                'total' => $subtotal,
                'billed_to_customer' => true,
            ]);
            $product?->decrementStock($qty, "Reserved for Repair Ticket #{$this->ticket->ticket_number}");
        });

        $this->reset(['partProductId', 'partName', 'partQty', 'partUnitPrice']);
        $this->partQty = 1;
        session()->flash('status', __('Spare part added.'));
        $this->ticket->refresh();
    }

    public function removePart(int $itemId): void
    {
        $item = $this->ticket->items()->findOrFail($itemId);
        DB::transaction(function () use ($item) {
            if ($item->product_id && $item->item_type === RepairTicketItem::TYPE_SPARE_PART) {
                Product::where('id', $item->product_id)->first()?->increment('current_stock', (float) $item->quantity);
            }
            $item->delete();
        });
        session()->flash('status', __('Part removed and stock restored.'));
        $this->ticket->refresh();
    }

    public function setLabor(): void
    {
        $this->validate(['laborFee' => ['required', 'numeric', 'min:0']]);
        $fee = (float) $this->laborFee;
        $desc = trim($this->laborDescription ?: 'Technician Diagnostic & Repair Labor');

        $labor = $this->ticket->laborItems()->first();
        if ($labor) {
            $labor->update(['item_name' => $desc, 'quantity' => 1, 'unit_price' => $fee, 'subtotal' => $fee, 'total' => $fee]);
        } else {
            RepairTicketItem::create([
                'company_id' => $this->ticket->company_id,
                'tenant_id' => $this->ticket->company_id,
                'ticket_id' => $this->ticket->id,
                'item_name' => $desc,
                'item_type' => RepairTicketItem::TYPE_SERVICE_LABOR,
                'quantity' => 1,
                'unit_price' => $fee,
                'subtotal' => $fee,
                'tax_amount' => 0.0,
                'total' => $fee,
                'billed_to_customer' => true,
            ]);
        }

        AuditLog::record('repair.labor_updated', $this->ticket->company_id, auth()->id(), [
            'ticket_id' => $this->ticket->id, 'labor_fee' => $fee,
        ]);
        session()->flash('status', __('Labor charge updated.'));
        $this->ticket->refresh();
    }

    public function toggleChecklist(string $key, string $value): void
    {
        $value = match (strtolower(trim($value))) {
            'pass', 'passed', 'ok', 'good' => 'pass',
            'fail', 'failed', 'damaged', 'broken', 'bad' => 'fail',
            'na', 'n/a', 'not_applicable', 'skip' => 'not_applicable',
            default => 'pending',
        };

        $list = array_values((array) ($this->ticket->inspection_checklist ?? []));
        $matched = false;
        foreach ($list as $idx => $row) {
            $rowKey = is_array($row)
                ? (string) ($row['key'] ?? Str::slug((string) ($row['item_name'] ?? $row['name'] ?? ''), '_'))
                : Str::slug((string) $row, '_');
            if ($rowKey === $key) {
                $list[$idx] = [
                    'key' => $key,
                    'item_name' => is_array($row) ? ($row['item_name'] ?? $row['name'] ?? $key) : (string) $row,
                    'status' => $value,
                    'notes' => is_array($row) ? ($row['notes'] ?? null) : null,
                ];
                $matched = true;
                break;
            }
        }
        if (! $matched) {
            $list[] = ['key' => $key, 'item_name' => $key, 'status' => $value, 'notes' => null];
        }

        $this->ticket->update(['inspection_checklist' => $list]);
        $this->ticket->refresh();
    }

    public function render()
    {
        $this->ticket->load(['items', 'technician:id,name', 'category:id,name', 'customer:id,name,phone']);

        return view('livewire.tenant.repair.ticket-detail', [
            'parts' => $this->ticket->items->where('item_type', 'spare_part'),
            'labor' => $this->ticket->items->firstWhere('item_type', 'service_labor'),
            'technicians' => User::where('status', 'approved')->orderBy('name')->get(['id', 'name']),
            'stockProducts' => Product::where('active', true)->orderBy('name')->get(['id', 'name', 'sale_price', 'current_stock']),
        ]);
    }
}
