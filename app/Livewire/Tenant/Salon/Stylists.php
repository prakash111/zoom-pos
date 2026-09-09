<?php

namespace App\Livewire\Tenant\Salon;

use App\Models\AuditLog;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Stylists & Staff Assignments — web parity for
 * SalonApiController::specialistsIndex/specialistsToggle and
 * SchemaResponse::serviceStylistsView. Toggles the User.is_specialist flag.
 */
#[Layout('layouts.tenant', ['title' => 'Stylists & Staff'])]
class Stylists extends Component
{
    public function toggle(string $id): void
    {
        $staff = User::where('status', 'approved')->findOrFail($id);
        $staff->update(['is_specialist' => ! $staff->is_specialist]);

        AuditLog::record('salon.specialist_toggled', $staff->company_id, auth()->id(), [
            'user_id' => $staff->id,
            'is_specialist' => $staff->is_specialist,
        ]);

        session()->flash('status', $staff->is_specialist
            ? __('Added to the specialist roster.')
            : __('Removed from the specialist roster.'));
    }

    public function render()
    {
        return view('livewire.tenant.salon.stylists', [
            'staff' => User::where('status', 'approved')->orderBy('name')->get(['id', 'name', 'role', 'is_specialist']),
        ]);
    }
}
