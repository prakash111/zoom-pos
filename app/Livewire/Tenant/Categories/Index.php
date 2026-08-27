<?php

namespace App\Livewire\Tenant\Categories;

use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Categories'])]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $color = '#4f46e5';

    public string $description = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function newCategory(): void
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->color = '#4f46e5';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->color = $category->color ?? '#4f46e5';
        $this->description = (string) $category->description;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        Category::updateOrCreate(['id' => $this->editingId], $data);
        $this->showForm = false;
        session()->flash('status', 'Category saved.');
    }

    public function delete(int $id): void
    {
        Category::findOrFail($id)->delete();
        session()->flash('status', 'Category deleted.');
    }

    public function render()
    {
        return view('livewire.tenant.categories.index', [
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
