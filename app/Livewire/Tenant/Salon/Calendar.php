<?php

namespace App\Livewire\Tenant\Salon;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalonAppointment;
use App\Models\User;
use App\Services\Documents\DocumentNumberService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Service Booking Calendar — web parity for SalonApiController::appointmentsIndex
 * /appointmentsStore/appointmentsUpdateStatus and SchemaResponse::serviceCalendarView
 * / salonBookingCreateView. Slot conflict check mirrors the API.
 */
#[Layout('layouts.tenant', ['title' => 'Service Booking Calendar'])]
class Calendar extends Component
{
    public const STATUSES = ['scheduled', 'checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'];

    #[Url]
    public ?string $date = null;

    public bool $showForm = false;

    public ?int $serviceId = null;

    public ?string $specialistId = null;

    public string $customerName = '';

    public string $customerPhone = '';

    public ?string $appointmentDate = null;

    public string $appointmentTime = '10:00';

    public string $notes = '';

    public $advancePaid = 0;

    public string $activeCategory = 'Hair';

    public ?string $stylistFilter = null;

    public array $cartServices = [];

    public function selectCategory(string $category): void
    {
        $this->activeCategory = $category;
    }

    public function toggleCartService(int $serviceId): void
    {
        if (in_array($serviceId, $this->cartServices, true)) {
            $this->cartServices = array_values(array_diff($this->cartServices, [$serviceId]));
        } else {
            $this->cartServices[] = $serviceId;
        }
    }

    public function clearCart(): void
    {
        $this->cartServices = [];
    }

    public function mount(): void
    {
        $this->date ??= Carbon::today()->toDateString();
    }

    protected function rules(): array
    {
        return [
            'serviceId' => ['required', 'integer', 'exists:products,id'],
            'specialistId' => ['required', 'exists:users,id'],
            'customerName' => ['required', 'string', 'max:150'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
            'appointmentDate' => ['required', 'date'],
            'appointmentTime' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'advancePaid' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function newBooking(): void
    {
        $this->reset(['serviceId', 'specialistId', 'customerName', 'customerPhone', 'notes', 'advancePaid']);
        $this->appointmentDate = $this->date;
        $this->appointmentTime = '10:00';
        $this->showForm = true;
    }

    public function book(): void
    {
        $data = $this->validate();
        $company = auth()->user()->company;
        $timezone = $company->resolveTimezone();

        $service = Product::where('active', true)->findOrFail($data['serviceId']);
        $specialist = User::where('is_specialist', true)->where('status', 'approved')->findOrFail($data['specialistId']);

        $duration = (int) ($service->duration_minutes ?: 30);
        $startsAt = Carbon::createFromFormat('Y-m-d H:i', $data['appointmentDate'].' '.$data['appointmentTime'], $timezone)->utc();
        $endsAt = $startsAt->copy()->addMinutes($duration);

        $conflict = SalonAppointment::where('specialist_id', $specialist->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($conflict) {
            $this->addError('appointmentTime', __('That specialist already has an appointment in this slot.'));

            return;
        }

        $customer = Customer::where('name', $data['customerName'])
            ->when($data['customerPhone'], fn ($q) => $q->orWhere('phone', $data['customerPhone']))
            ->first()
            ?? Customer::create(['name' => $data['customerName'], 'phone' => $data['customerPhone'] ?: null]);

        $appointment = SalonAppointment::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'appointment_number' => app(DocumentNumberService::class)->next($company, 'salon', $startsAt),
            'customer_id' => $customer->id,
            'customer_name' => $data['customerName'],
            'customer_phone' => $data['customerPhone'] ?: null,
            'product_id' => $service->id,
            'specialist_id' => $specialist->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'scheduled',
            'notes' => $data['notes'] ?: null,
            'advance_paid' => (float) ($data['advancePaid'] ?: 0),
            'deposit_payment_method' => 'cash',
        ]);

        AuditLog::record('salon.appointment_created', $company->id, auth()->id(), [
            'appointment_id' => $appointment->id,
            'specialist_id' => $specialist->id,
            'starts_at' => $startsAt->toIso8601String(),
        ]);

        $this->date = $startsAt->copy()->setTimezone($timezone)->toDateString();
        $this->showForm = false;
        session()->flash('status', __('Appointment booked.'));
    }

    public function setStatus(int $id, string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            return;
        }
        SalonAppointment::findOrFail($id)->update(['status' => $status]);
        session()->flash('status', __('Appointment updated.'));
    }

    public function shiftDay(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->toDateString();
    }

    public function render()
    {
        $company = auth()->user()->company;
        $timezone = $company->resolveTimezone();
        $day = Carbon::parse($this->date, $timezone);

        $appointments = SalonAppointment::with(['service:id,name,duration_minutes,sale_price', 'specialist:id,name'])
            ->where(function ($q) use ($day) {
                $q->whereBetween('starts_at', [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()])
                    ->orWhereDate('starts_at', $day->toDateString());
            })
            ->when($this->stylistFilter, fn ($q) => $q->where('specialist_id', $this->stylistFilter))
            ->orderBy('starts_at')
            ->get();

        $allServices = Product::where('active', true)
            ->where(fn ($q) => $q->where('type', 'service')->orWhere('duration_minutes', '>', 0)->orWhere('category_type', 'salon'))
            ->orderBy('name')
            ->get(['id', 'name', 'duration_minutes', 'sale_price', 'category_type']);

        $cartItems = $allServices->whereIn('id', $this->cartServices)->values();
        $cartTotal = $cartItems->sum('sale_price');

        return view('livewire.tenant.salon.calendar', [
            'appointments' => $appointments,
            'timezone' => $timezone,
            'services' => $allServices,
            'specialists' => User::where('is_specialist', true)->where('status', 'approved')->orderBy('name')->get(['id', 'name']),
            'categories' => ['Hair', 'Spa', 'Facials', 'Add-ons'],
            'cartItems' => $cartItems,
            'cartTotal' => $cartTotal,
        ]);
    }
}
