<?php

namespace App\Livewire\Tenant\Brands;

use App\Models\Brand;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Brands'])]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    protected function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }

    public function newBrand(): void
    {
        $this->reset(['editingId', 'name']);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $brand = Brand::findOrFail($id);
        $this->editingId = $brand->id;
        $this->name = $brand->name;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        Brand::updateOrCreate(['id' => $this->editingId], $data);
        $this->showForm = false;
        session()->flash('status', 'Brand saved.');
    }

    public function delete(int $id): void
    {
        Brand::findOrFail($id)->delete();
        session()->flash('status', 'Brand deleted.');
    }

    public function render()
    {
        return view('livewire.tenant.brands.index', ['brands' => Brand::orderBy('name')->get()]);
    }
}
