<?php

namespace App\Livewire\Tenant\Storefront;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\TenantInquiry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Online Store Inquiries'])]
class Inquiries extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all'; // all, unread, contacted, closed

    public bool $showDetailModal = false;

    public ?int $viewingInquiryId = null;

    protected function getCompanyId(): ?string
    {
        $id = app()->bound('tenant.company_id') ? app('tenant.company_id') : auth()->user()?->company_id;

        return $id ? (string) $id : null;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $status): void
    {
        $this->statusFilter = in_array($status, ['all', 'unread', 'contacted', 'closed'], true) ? $status : 'all';
        $this->resetPage();
    }

    public function viewInquiry(int $id): void
    {
        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return;
        }

        $inquiry = TenantInquiry::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->find($id);

        if (! $inquiry) {
            $this->dispatch('toast', ['type' => 'error', 'message' => __('Inquiry not found.')]);

            return;
        }

        $this->viewingInquiryId = $inquiry->id;
        $this->showDetailModal = true;
    }

    public function closeModal(): void
    {
        $this->showDetailModal = false;
        $this->viewingInquiryId = null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $companyId = $this->getCompanyId();
        if (! $companyId || ! in_array($status, ['unread', 'contacted', 'closed'], true)) {
            return;
        }

        $inquiry = TenantInquiry::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->find($id);

        if (! $inquiry) {
            $this->dispatch('toast', ['type' => 'error', 'message' => __('Inquiry not found.')]);

            return;
        }

        $oldStatus = $inquiry->status;
        $inquiry->update(['status' => $status]);

        AuditLog::record('storefront.inquiry_status_updated', $companyId, auth()->id(), [
            'inquiry_id' => $inquiry->id,
            'old_status' => $oldStatus,
            'new_status' => $status,
        ]);

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => __('Inquiry status updated to :status', ['status' => ucfirst($status)]),
        ]);
    }

    public function deleteInquiry(int $id): void
    {
        $companyId = $this->getCompanyId();
        if (! $companyId) {
            return;
        }

        $inquiry = TenantInquiry::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->find($id);

        if (! $inquiry) {
            $this->dispatch('toast', ['type' => 'error', 'message' => __('Inquiry not found.')]);

            return;
        }

        $inquiry->delete();

        AuditLog::record('storefront.inquiry_deleted', $companyId, auth()->id(), [
            'inquiry_id' => $id,
        ]);

        if ($this->viewingInquiryId === $id) {
            $this->closeModal();
        }

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => __('Inquiry deleted successfully.'),
        ]);
    }

    public function render()
    {
        $companyId = $this->getCompanyId();

        $baseQuery = TenantInquiry::withoutGlobalScopes()
            ->where('company_id', $companyId);

        $counts = [
            'all' => (clone $baseQuery)->count(),
            'unread' => (clone $baseQuery)->where('status', 'unread')->count(),
            'contacted' => (clone $baseQuery)->where('status', 'contacted')->count(),
            'closed' => (clone $baseQuery)->where('status', 'closed')->count(),
        ];

        $query = clone $baseQuery;

        if ($this->statusFilter !== 'all' && in_array($this->statusFilter, ['unread', 'contacted', 'closed'], true)) {
            $query->where('status', $this->statusFilter);
        }

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('message', 'like', $term);
            });
        }

        $inquiries = $query->orderByDesc('created_at')->paginate(15);

        $viewingInquiry = null;
        if ($this->showDetailModal && $this->viewingInquiryId) {
            $viewingInquiry = TenantInquiry::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->find($this->viewingInquiryId);
        }

        return view('livewire.tenant.storefront.inquiries', [
            'inquiries' => $inquiries,
            'counts' => $counts,
            'viewingInquiry' => $viewingInquiry,
        ]);
    }
}
