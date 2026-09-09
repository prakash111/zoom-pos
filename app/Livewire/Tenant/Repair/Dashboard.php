<?php

namespace App\Livewire\Tenant\Repair;

use App\Models\RepairTicket;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Repair Workbench overview — web parity for RepairApiController::stats and
 * SchemaResponse::repairDashboardView. Status columns + open-ticket counts.
 */
#[Layout('layouts.tenant', ['title' => 'Repair Workbench'])]
class Dashboard extends Component
{
    public function render()
    {
        $tickets = RepairTicket::query()->get(['status', 'total_amount', 'balance_due']);

        $counts = [];
        foreach (array_keys(RepairTicket::STATUSES) as $status) {
            $counts[$status] = $tickets->where('status', $status)->count();
        }

        return view('livewire.tenant.repair.dashboard', [
            'counts' => $counts,
            'openCount' => $tickets->whereNotIn('status', [RepairTicket::STATUS_DELIVERED, RepairTicket::STATUS_CANCELLED])->count(),
            'pendingReceivables' => round((float) $tickets
                ->whereNotIn('status', [RepairTicket::STATUS_DELIVERED, RepairTicket::STATUS_CANCELLED])
                ->sum('balance_due'), 2),
            'recent' => RepairTicket::with(['technician:id,name'])->latest()->limit(10)->get(),
        ]);
    }
}
