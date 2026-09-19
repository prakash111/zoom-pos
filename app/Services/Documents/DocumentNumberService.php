<?php

namespace App\Services\Documents;

use App\Models\Company;
use App\Models\PharmacyPrescription;
use App\Models\RepairTicket;
use App\Models\Sale;
use App\Models\SalonAppointment;
use Carbon\CarbonInterface;

class DocumentNumberService
{
    public function next(Company $company, string $documentType, ?CarbonInterface $date = null): string
    {
        $date ??= now();
        $type = strtolower($documentType);

        return match ($type) {
            'repair', 'repair_ticket' => $this->nextRepair($company, $date),
            'pharmacy', 'prescription', 'rx' => $this->nextPrescription($company, $date),
            'salon', 'appointment' => $this->nextSalon($company, $date),
            'quotation', 'quote' => $this->nextSaleNumber($company, $this->compactPrefix($company->quotation_prefix, 'QUO-'), 'quotation'),
            'order', 'sale', 'receipt' => $this->nextSaleNumber($company, $this->compactPrefix($company->invoice_prefix, 'ORD-'), null),
            default => $this->nextSaleNumber($company, $this->compactPrefix($company->invoice_prefix, 'INV-'), null),
        };
    }

    private function nextRepair(Company $company, CarbonInterface $date): string
    {
        $stem = $this->prefix($company->repair_prefix, 'REP-').$date->format('Y').'-';
        $next = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('ticket_number', 'like', $stem.'%')
            ->count() + 1;

        return $stem.sprintf('%04d', $next);
    }

    private function nextPrescription(Company $company, CarbonInterface $date): string
    {
        $stem = $this->prefix($company->prescription_prefix, 'RX-').$date->format('Ymd').'-';
        $next = PharmacyPrescription::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('prescription_number', 'like', $stem.'%')
            ->count() + 1;

        return $stem.sprintf('%04d', $next);
    }

    private function nextSalon(Company $company, CarbonInterface $date): string
    {
        $stem = $this->prefix($company->salon_prefix, 'SAL-').$date->format('Ymd').'-';
        $next = SalonAppointment::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('appointment_number', 'like', $stem.'%')
            ->count() + 1;

        return $stem.sprintf('%04d', $next);
    }

    private function nextSaleNumber(Company $company, string $prefix, ?string $operationType): string
    {
        $query = Sale::withoutGlobalScope('company')->where('company_id', $company->id);
        if ($operationType !== null) {
            $query->where('operation_type', $operationType);
        }

        return $this->prefix($prefix, 'INV-').sprintf('%03d', $query->count() + 1);
    }

    /** Keep legacy demo/module prefixes from leaking into document numbers. */
    private function compactPrefix(?string $configured, string $fallback): string
    {
        $prefix = trim((string) $configured);
        if ($prefix === '' || strlen($prefix) > 8) {
            return $fallback;
        }

        return $prefix;
    }

    private function prefix(?string $configured, string $fallback): string
    {
        $prefix = trim((string) $configured);
        if ($prefix === '') {
            return $fallback;
        }

        return str_ends_with($prefix, '-') ? $prefix : $prefix.'-';
    }
}
