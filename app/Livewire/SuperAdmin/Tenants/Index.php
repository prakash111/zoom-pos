<?php

namespace App\Livewire\SuperAdmin\Tenants;

use App\Models\Company;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.superadmin', ['title' => 'Tenants'])]
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
        $tenants = Company::query()
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('unique_account_id', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('id', 'like', $term);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('livewire.superadmin.tenants.index', ['tenants' => $tenants]);
    }
}
