<?php

namespace App\Livewire\Tenant\Pharmacy;

use App\Models\PharmacyBatch;
use App\Models\PharmacyPrescription;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pharmacy landing page — mirrors the mobile SDUI dashboard
 * (SchemaResponse::pharmacyPosView / PharmacyModuleController::dashboard):
 * batch, expiry and prescription counters that deep-link into the workflow
 * screens.
 */
#[Layout('layouts.tenant', ['title' => 'Pharmacy'])]
class Dashboard extends Component
{
    public function render()
    {
        $batchQuery = PharmacyBatch::query();

        $totalBatches = (clone $batchQuery)->count();
        $expiringSoon = (clone $batchQuery)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [Carbon::today()->toDateString(), Carbon::today()->addDays(30)->toDateString()])
            ->count();
        $expired = (clone $batchQuery)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', Carbon::today())
            ->count();
        $stockUnits = (int) (clone $batchQuery)->sum('stock_qty');

        $pendingRx = PharmacyPrescription::where('status', 'pending')->count();
        $dispensedToday = PharmacyPrescription::where('status', 'dispensed')
            ->whereDate('dispensed_at', Carbon::today())
            ->count();

        return view('livewire.tenant.pharmacy.dashboard', [
            'totalBatches' => $totalBatches,
            'expiringSoon' => $expiringSoon,
            'expired' => $expired,
            'stockUnits' => $stockUnits,
            'pendingRx' => $pendingRx,
            'dispensedToday' => $dispensedToday,
        ]);
    }
}
