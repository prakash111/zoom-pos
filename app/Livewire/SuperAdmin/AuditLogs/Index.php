<?php

namespace App\Livewire\SuperAdmin\AuditLogs;

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.superadmin', ['title' => 'Audit Logs'])]
class Index extends Component
{
    use WithPagination;

    public string $action = '';

    public string $companyId = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $logs = AuditLog::query()
            ->when($this->action, fn ($q) => $q->where('action', 'like', '%'.$this->action.'%'))
            ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('livewire.superadmin.audit-logs.index', ['logs' => $logs]);
    }
}
