<?php

namespace App\Livewire\Tenant\Restaurant;

use App\Models\DiningFloor;
use App\Models\DiningTable;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Tables & Floor Plan'])]
class Tables extends Component
{
    public ?string $selectedFloorId = 'all';

    // Floor Modal State
    public bool $showFloorModal = false;

    public ?string $editingFloorId = null;

    public string $floorName = '';

    public int $floorOrderIndex = 0;

    // Table Modal State
    public bool $showTableModal = false;

    public ?string $editingTableId = null;

    public string $tableNumber = '';

    public ?string $tableFloorId = null;

    public int $tableCapacity = 4;

    public string $tableStatus = DiningTable::STATUS_AVAILABLE;

    public function mount(): void
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        // Ensure at least default floor exists
        if (DiningFloor::where('company_id', $companyId)->count() === 0) {
            $floor = DiningFloor::create([
                'company_id' => $companyId,
                'name' => 'Main Dining Room',
                'order_index' => 1,
            ]);
            DiningTable::create(['company_id' => $companyId, 'dining_floor_id' => $floor->id, 'table_number' => 'Table 01', 'seating_capacity' => 4]);
            DiningTable::create(['company_id' => $companyId, 'dining_floor_id' => $floor->id, 'table_number' => 'Table 02', 'seating_capacity' => 2]);
            DiningTable::create(['company_id' => $companyId, 'dining_floor_id' => $floor->id, 'table_number' => 'Table 03', 'seating_capacity' => 6]);
        }
    }

    public function openAddFloor(): void
    {
        $this->reset(['editingFloorId', 'floorName']);
        $this->floorOrderIndex = DiningFloor::count() + 1;
        $this->showFloorModal = true;
    }

    public function openEditFloor(string $id): void
    {
        $floor = DiningFloor::findOrFail($id);
        $this->editingFloorId = $floor->id;
        $this->floorName = $floor->name;
        $this->floorOrderIndex = $floor->order_index;
        $this->showFloorModal = true;
    }

    public function saveFloor(): void
    {
        $this->validate([
            'floorName' => ['required', 'string', 'max:100'],
            'floorOrderIndex' => ['integer', 'min:0'],
        ]);

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        if ($this->editingFloorId) {
            $floor = DiningFloor::findOrFail($this->editingFloorId);
            $floor->update([
                'name' => $this->floorName,
                'order_index' => $this->floorOrderIndex,
            ]);
            session()->flash('status', "Floor area {$floor->name} updated.");
        } else {
            $floor = DiningFloor::create([
                'company_id' => $companyId,
                'name' => $this->floorName,
                'order_index' => $this->floorOrderIndex,
            ]);
            session()->flash('status', "Floor area {$floor->name} created.");
        }

        $this->showFloorModal = false;
        $this->reset(['editingFloorId', 'floorName']);
    }

    public function deleteFloor(string $id): void
    {
        $floor = DiningFloor::findOrFail($id);
        $name = $floor->name;
        $floor->delete();
        session()->flash('status', "Floor area {$name} removed.");
    }

    public function openAddTable(): void
    {
        $this->reset(['editingTableId', 'tableNumber']);
        $this->tableCapacity = 4;
        $this->tableStatus = DiningTable::STATUS_AVAILABLE;
        $this->tableFloorId = $this->selectedFloorId !== 'all' ? $this->selectedFloorId : DiningFloor::first()?->id;
        $this->showTableModal = true;
    }

    public function openEditTable(string $id): void
    {
        $table = DiningTable::findOrFail($id);
        $this->editingTableId = $table->id;
        $this->tableNumber = $table->table_number;
        $this->tableFloorId = $table->dining_floor_id;
        $this->tableCapacity = $table->seating_capacity;
        $this->tableStatus = $table->status;
        $this->showTableModal = true;
    }

    public function saveTable(): void
    {
        $this->validate([
            'tableNumber' => ['required', 'string', 'max:50'],
            'tableCapacity' => ['required', 'integer', 'min:1', 'max:50'],
            'tableFloorId' => ['nullable', 'string'],
            'tableStatus' => ['required', 'in:available,occupied,reserved,billed'],
        ]);

        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        if ($this->editingTableId) {
            $table = DiningTable::findOrFail($this->editingTableId);
            $table->update([
                'table_number' => $this->tableNumber,
                'dining_floor_id' => $this->tableFloorId,
                'seating_capacity' => $this->tableCapacity,
                'status' => $this->tableStatus,
            ]);
            session()->flash('status', "Table {$table->table_number} updated.");
        } else {
            $table = DiningTable::create([
                'company_id' => $companyId,
                'table_number' => $this->tableNumber,
                'dining_floor_id' => $this->tableFloorId,
                'seating_capacity' => $this->tableCapacity,
                'status' => $this->tableStatus,
            ]);
            session()->flash('status', "Table {$table->table_number} added.");
        }

        $this->showTableModal = false;
        $this->reset(['editingTableId', 'tableNumber']);
    }

    public function setTableStatus(string $id, string $status): void
    {
        $table = DiningTable::findOrFail($id);
        $table->update(['status' => $status]);
        session()->flash('status', "Table {$table->table_number} status updated to ".ucfirst($status).'.');
    }

    public function deleteTable(string $id): void
    {
        $table = DiningTable::findOrFail($id);
        $num = $table->table_number;
        $table->delete();
        session()->flash('status', "Table {$num} deleted.");
    }

    public function render()
    {
        $floors = DiningFloor::orderBy('order_index')->get();

        $tables = DiningTable::query()
            ->when($this->selectedFloorId !== 'all', fn ($q) => $q->where('dining_floor_id', $this->selectedFloorId))
            ->with('floor', 'currentSale')
            ->orderBy('table_number')
            ->get();

        return view('livewire.tenant.restaurant.tables', [
            'floors' => $floors,
            'tables' => $tables,
        ]);
    }
}
