<?php

namespace App\Livewire\Tenant\Sales;

use App\Models\Sale;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Sales'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $sales = Sale::query()
            ->with(['customer'])
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($q) => $q->where('sale_number', 'like', $term)->orWhere('customer_name', 'like', $term));
            })
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.tenant.sales.index', ['sales' => $sales]);
    }
}
