<?php

namespace App\Livewire\Tenant\Consignments;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Consignment;
use App\Services\Auth\PermissionChecker;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Consignments'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public function mount(): void
    {
        $user = auth('web')->user();
        if ($user && ! PermissionChecker::can($user, 'consignments', 'view') && ! PermissionChecker::can($user, 'sales', 'view')) {
            abort(403, 'Unauthorized.');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function deleteConsignment(int $id): void
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $consignment = Consignment::where('company_id', $companyId)->find($id);
        if ($consignment && $consignment->status !== 'finalized') {
            $num = $consignment->consignment_number;
            $consignment->items()->delete();
            $consignment->delete();

            AuditLog::record('consignment.deleted', $companyId, auth('web')->id(), [
                'consignment_id' => $id,
                'consignment_number' => $num,
            ]);

            session()->flash('status', "Consignment {$num} deleted successfully.");
        }
    }

    public function render()
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $company = Company::find($companyId);

        $query = Consignment::where('company_id', $companyId)
            ->with(['customer', 'items.product'])
            ->orderByDesc('created_at');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('consignment_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term);
            });
        }

        $consignments = $query->paginate(15);

        // Quick statistics
        $stats = [
            'total' => Consignment::where('company_id', $companyId)->count(),
            'dispatched' => Consignment::where('company_id', $companyId)->where('status', 'dispatched')->count(),
            'reconciled' => Consignment::where('company_id', $companyId)->where('status', 'reconciled')->count(),
            'finalized' => Consignment::where('company_id', $companyId)->where('status', 'finalized')->count(),
            'dispatched_value' => (float) Consignment::where('company_id', $companyId)->where('status', 'dispatched')->sum('total_dispatched_amount'),
        ];

        return view('livewire.tenant.consignments.index', [
            'consignments' => $consignments,
            'company' => $company,
            'stats' => $stats,
        ]);
    }
}
