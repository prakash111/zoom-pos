<?php

namespace App\Livewire\Tenant\Units;

use App\Models\Unit;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Units'])]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $abbreviation = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['nullable', 'string', 'max:16'],
        ];
    }

    public function newUnit(): void
    {
        $this->reset(['editingId', 'name', 'abbreviation']);
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $unit = Unit::findOrFail($id);
        $this->editingId = $unit->id;
        $this->name = $unit->name;
        $this->abbreviation = (string) $unit->abbreviation;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        Unit::updateOrCreate(['id' => $this->editingId], $data);
        $this->showForm = false;
        session()->flash('status', 'Unit saved.');
    }

    public function delete(int $id): void
    {
        Unit::findOrFail($id)->delete();
        session()->flash('status', 'Unit deleted.');
    }

    public function render()
    {
        return view('livewire.tenant.units.index', ['units' => Unit::orderBy('name')->get()]);
    }
}
