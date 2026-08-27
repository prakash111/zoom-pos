<?php

namespace App\Livewire\SuperAdmin\Tax;

use App\Services\TaxCalculationService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.superadmin', ['title' => 'Global Tax & Compliance Matrix'])]
class Index extends Component
{
    public ?string $selectedCountry = null;

    public function selectCountry(?string $countryCode): void
    {
        $this->selectedCountry = $countryCode;
    }

    public function render()
    {
        $taxService = app(TaxCalculationService::class);
        $jurisdictions = $taxService->getJurisdictionPresets();

        return view('livewire.superadmin.tax.index', [
            'jurisdictions' => $jurisdictions,
            'selectedPreset' => $this->selectedCountry ? ($jurisdictions[$this->selectedCountry] ?? null) : null,
        ]);
    }
}
