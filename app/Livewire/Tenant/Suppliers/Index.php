<?php

namespace App\Livewire\Tenant\Suppliers;

use App\Models\Supplier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Suppliers'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $legalName = '';

    public string $taxId = '';

    public string $email = '';

    public string $phone = '';

    public string $city = '';

    public string $state = '';

    public bool $active = true;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
        ];
    }

    public function newSupplier(): void
    {
        $this->reset(['editingId', 'name', 'legalName', 'taxId', 'email', 'phone', 'city', 'state']);
        $this->active = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $supplier = Supplier::findOrFail($id);
        $this->editingId = $supplier->id;
        $this->name = $supplier->name;
        $this->legalName = (string) $supplier->legal_name;
        $this->taxId = (string) $supplier->tax_id;
        $this->email = (string) $supplier->email;
        $this->phone = (string) $supplier->phone;
        $this->city = (string) $supplier->city;
        $this->state = (string) $supplier->state;
        $this->active = $supplier->active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        Supplier::updateOrCreate(['id' => $this->editingId], [
            'name' => $data['name'],
            'legal_name' => $this->legalName ?: null,
            'tax_id' => $this->taxId ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $this->phone ?: null,
            'city' => $this->city ?: null,
            'state' => $this->state ?: null,
            'active' => $this->active,
        ]);
        $this->showForm = false;
        session()->flash('status', 'Supplier saved.');
    }

    public function delete(int $id): void
    {
        Supplier::findOrFail($id)->delete();
        session()->flash('status', 'Supplier deleted.');
    }

    public function render()
    {
        $suppliers = Supplier::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.tenant.suppliers.index', ['suppliers' => $suppliers]);
    }
}
