<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\RepairTicket;
use Illuminate\Contracts\View\View;

/**
 * Public, login-free repair-ticket tracking page.
 *
 * Linked from the "Track progress: …" line in the intake SMS / WhatsApp
 * message (see RepairNotificationService::notifyTicketCreated). The ticket
 * number in the URL is the only credential — the page shows status and
 * cost estimates, never the device passcode / IMEI or internal notes.
 */
class RepairPortalController extends Controller
{
    public function track(string $ticketNumber): View
    {
        $ticket = RepairTicket::withoutGlobalScopes()
            ->with(['company', 'customer'])
            ->where('ticket_number', $ticketNumber)
            ->first();

        abort_if($ticket === null, 404, 'This repair tracking link is not available.');

        $company = $ticket->company ?? Company::find($ticket->company_id);

        // Ordered lifecycle for the progress stepper. "cancelled" is handled
        // separately in the view.
        $flow = [
            RepairTicket::STATUS_RECEIVED,
            RepairTicket::STATUS_DIAGNOSING,
            RepairTicket::STATUS_WAITING_PARTS,
            RepairTicket::STATUS_IN_PROGRESS,
            RepairTicket::STATUS_READY,
            RepairTicket::STATUS_DELIVERED,
        ];

        $currentIndex = array_search($ticket->status, $flow, true);
        if ($currentIndex === false) {
            $currentIndex = $ticket->status === RepairTicket::STATUS_CANCELLED ? -1 : 0;
        }

        return view('public.repair-track', [
            'ticket' => $ticket,
            'company' => $company,
            'flow' => $flow,
            'currentIndex' => $currentIndex,
            'statusLabels' => RepairTicket::STATUSES,
        ]);
    }
}
