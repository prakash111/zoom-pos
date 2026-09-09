<?php

namespace App\Livewire\Tenant\Pharmacy;

use App\Models\AuditLog;
use App\Models\PharmacyPrescription;
use App\Services\Documents\DocumentNumberService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Prescription intake + patient queue — web parity for
 * PharmacyApiController::prescriptionsIndex/prescriptionsStore/prescriptionsDispense
 * and SchemaResponse::pharmacyPrescriptionsView / pharmacyRxCreateView.
 */
#[Layout('layouts.tenant', ['title' => 'Prescriptions & Queue'])]
class Prescriptions extends Component
{
    #[Url]
    public string $status = 'pending'; // pending | dispensed | cancelled | all

    #[Url]
    public string $search = '';

    public bool $showForm = false;

    public string $patientName = '';

    public string $patientPhone = '';

    public string $doctorName = '';

    public string $doctorRegistrationNo = '';

    public ?string $prescriptionDate = null;

    public string $diagnosis = '';

    public string $medicines = '';

    public int $dosageDurationDays = 30;

    public ?int $expandedId = null;

    protected function rules(): array
    {
        return [
            'patientName' => ['required', 'string', 'max:150'],
            'patientPhone' => ['nullable', 'string', 'max:50'],
            'doctorName' => ['required', 'string', 'max:150'],
            'doctorRegistrationNo' => ['nullable', 'string', 'max:100'],
            'prescriptionDate' => ['nullable', 'date'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'medicines' => ['required', 'string', 'max:4000'],
            'dosageDurationDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function newIntake(): void
    {
        $this->reset(['patientName', 'patientPhone', 'doctorName', 'doctorRegistrationNo', 'diagnosis', 'medicines']);
        $this->prescriptionDate = Carbon::today()->toDateString();
        $this->dosageDurationDays = 30;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        $company = auth()->user()->company;

        $rx = PharmacyPrescription::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'prescription_number' => app(DocumentNumberService::class)->next($company, 'prescription'),
            'patient_name' => trim($data['patientName']),
            'patient_phone' => $data['patientPhone'] ?: null,
            'doctor_name' => trim($data['doctorName']),
            'doctor_registration_no' => $data['doctorRegistrationNo'] ?: null,
            'prescription_date' => $data['prescriptionDate'] ?: Carbon::today(),
            'diagnosis' => $data['diagnosis'] ?: null,
            'medicines' => array_values(array_filter(array_map('trim', preg_split('/\R/', $data['medicines'])))),
            'notes' => $data['medicines'],
            'dosage_duration_days' => $data['dosageDurationDays'] ?: null,
            'status' => 'pending',
        ]);

        AuditLog::record('pharmacy.prescription_created', $company->id, auth()->id(), [
            'prescription_id' => $rx->id,
            'prescription_number' => $rx->prescription_number,
        ]);

        $this->showForm = false;
        session()->flash('status', __('Prescription added to the queue.'));
    }

    public function dispense(int $id): void
    {
        $rx = PharmacyPrescription::findOrFail($id);
        $rx->update([
            'status' => 'dispensed',
            'dispensed_at' => now(),
            'dispensed_by_user_id' => auth()->id(),
        ]);

        AuditLog::record('pharmacy.prescription_dispensed', $rx->company_id, auth()->id(), [
            'prescription_id' => $rx->id,
            'prescription_number' => $rx->prescription_number,
        ]);
        session()->flash('status', __('Prescription marked as dispensed.'));
    }

    public function cancel(int $id): void
    {
        PharmacyPrescription::findOrFail($id)->update(['status' => 'cancelled']);
        session()->flash('status', __('Prescription cancelled.'));
    }

    public function toggle(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function render()
    {
        $query = PharmacyPrescription::query()->orderByDesc('prescription_date')->orderByDesc('id');

        if (in_array($this->status, ['pending', 'dispensed', 'cancelled'], true)) {
            $query->where('status', $this->status);
        }

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(fn ($q) => $q
                ->where('prescription_number', 'like', "%{$term}%")
                ->orWhere('patient_name', 'like', "%{$term}%")
                ->orWhere('doctor_name', 'like', "%{$term}%")
                ->orWhere('patient_phone', 'like', "%{$term}%"));
        }

        return view('livewire.tenant.pharmacy.prescriptions', [
            'prescriptions' => $query->limit(100)->get(),
            'pendingCount' => PharmacyPrescription::where('status', 'pending')->count(),
        ]);
    }
}
