<?php

namespace App\Livewire\Tenant\Restaurant;

use App\Models\KitchenTicket;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Kitchen Display System (KDS)'])]
class Kds extends Component
{
    public string $filterServiceType = 'all';

    public string $activeStatusTab = 'all';

    public function startPreparing(string $id): void
    {
        $kot = KitchenTicket::findOrFail($id);
        $kot->update([
            'status' => KitchenTicket::STATUS_PREPARING,
            'prepared_at' => now(),
        ]);
        if ($kot->sale) {
            $kot->sale->update(['kot_status' => 'preparing']);
        }
        session()->flash('status', "Ticket {$kot->kot_number} is now Preparing.");
    }

    public function markReady(string $id): void
    {
        $kot = KitchenTicket::findOrFail($id);
        $kot->update([
            'status' => KitchenTicket::STATUS_READY,
            'ready_at' => now(),
        ]);
        if ($kot->sale) {
            $kot->sale->update(['kot_status' => 'ready']);
        }
        session()->flash('status', "Ticket {$kot->kot_number} marked as Ready! 🔔");
    }

    public function markServed(string $id): void
    {
        $kot = KitchenTicket::findOrFail($id);
        $kot->update([
            'status' => KitchenTicket::STATUS_SERVED,
            'served_at' => now(),
        ]);
        if ($kot->sale) {
            $kot->sale->update(['kot_status' => 'served']);
        }
        session()->flash('status', "Ticket {$kot->kot_number} marked as Served. ✅");
    }

    public function cancelKot(string $id): void
    {
        $kot = KitchenTicket::findOrFail($id);
        $kot->update(['status' => KitchenTicket::STATUS_CANCELLED]);
        session()->flash('status', "Ticket {$kot->kot_number} cancelled.");
    }

    public function render()
    {
        $query = KitchenTicket::query()
            ->when($this->filterServiceType !== 'all', fn ($q) => $q->where('service_type', $this->filterServiceType))
            ->when($this->activeStatusTab !== 'all', fn ($q) => $q->where('status', $this->activeStatusTab))
            ->whereIn('status', [KitchenTicket::STATUS_PENDING, KitchenTicket::STATUS_PREPARING, KitchenTicket::STATUS_READY])
            ->orderBy('created_at', 'asc');

        $activeTickets = $query->with('sale', 'table')->get();

        $completedTickets = KitchenTicket::query()
            ->where('status', KitchenTicket::STATUS_SERVED)
            ->latest('served_at')
            ->limit(10)
            ->get();

        return view('livewire.tenant.restaurant.kds', [
            'tickets' => $activeTickets,
            'completedTickets' => $completedTickets,
            'pendingCount' => KitchenTicket::where('status', KitchenTicket::STATUS_PENDING)->count(),
            'preparingCount' => KitchenTicket::where('status', KitchenTicket::STATUS_PREPARING)->count(),
            'readyCount' => KitchenTicket::where('status', KitchenTicket::STATUS_READY)->count(),
        ]);
    }
}
