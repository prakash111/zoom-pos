<?php

namespace Modules\salon\Http\Controllers;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Services\Sdui\SchemaResponse as S;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\salon\Models\Appointment;
use Modules\salon\Models\SalonService;
use Modules\salon\Models\Stylist;

/**
 * Server-Driven UI + CRUD for the packaged "salon" module.
 */
class SalonModuleController extends Controller
{
    use ResolvesTenantSyncContext;

    private const BASE = '/api/tenant/salon-module';

    public function dashboard(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $todays = Appointment::where('company_id', $company->id)
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()])
            ->count();
        $upcoming = Appointment::where('company_id', $company->id)
            ->whereIn('status', ['booked', 'confirmed'])->count();
        $services = SalonService::where('company_id', $company->id)->count();
        $stylists = Stylist::where('company_id', $company->id)->count();

        return $this->schema(S::screen('Salon & Bookings', [
            S::gridView([
                S::lineItemTile('Today', (string) $todays, 'today',
                    S::navigateAction(self::BASE.'/views/appointments', 'dynamic_page', 'Appointments')),
                S::lineItemTile('Upcoming', (string) $upcoming, 'event_upcoming',
                    S::navigateAction(self::BASE.'/views/appointments', 'dynamic_page', 'Appointments')),
                S::lineItemTile('Services', (string) $services, 'design_services',
                    S::navigateAction(self::BASE.'/views/services', 'dynamic_page', 'Service Catalogue')),
                S::lineItemTile('Stylists', (string) $stylists, 'badge',
                    S::navigateAction(self::BASE.'/views/stylists', 'dynamic_page', 'Stylists')),
            ], 2),
            S::card([
                S::text('Salon module', 'title_medium', ['bold' => true]),
                S::text('Keep a service catalogue and stylist roster, book appointments against them, and move each booking through its lifecycle. Installed and managed from Super Admin → Modules.', 'body_small', ['color' => '#64748b']),
            ]),
        ]));
    }

    public function servicesView(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $rows = SalonService::where('company_id', $company->id)->orderBy('name')->get();

        $list = [];
        foreach ($rows as $s) {
            $list[] = S::lineItemTile($s->name, "{$s->duration_minutes} min  ·  {$s->price}", 'design_services');
        }

        return $this->schema(S::screen('Service Catalogue', [
            S::card([
                S::text('Add a service', 'title_medium', ['bold' => true]),
                S::textInput('name', 'Service name (e.g. Haircut & Style)', ''),
                S::textInput('duration_minutes', 'Duration (minutes)', '30'),
                S::textInput('price', 'Price', '0'),
                S::buttonPrimary('Save Service',
                    S::formSubmitAction(self::BASE.'/services', 'POST', 'Service saved.', reload: true), 'save'),
            ]),
            S::card($list !== [] ? $list : [
                S::text('No services yet.', 'body_small', ['color' => '#64748b']),
            ]),
        ]));
    }

    public function servicesStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['company_id'] = $company->id;
        $data['duration_minutes'] = $data['duration_minutes'] ?? 30;

        $service = SalonService::create($data);

        return response()->json(['success' => true, 'message' => 'Service saved.', 'id' => $service->id]);
    }

    public function stylistsView(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $rows = Stylist::where('company_id', $company->id)->orderBy('name')->get();

        $list = [];
        foreach ($rows as $st) {
            $list[] = S::lineItemTile($st->name, trim(($st->phone ? $st->phone.'  ·  ' : '').(string) $st->specialties, ' ·'), 'badge');
        }

        return $this->schema(S::screen('Stylists & Specialists', [
            S::card([
                S::text('Add a stylist', 'title_medium', ['bold' => true]),
                S::textInput('name', 'Full name', ''),
                S::textInput('phone', 'Phone', ''),
                S::textInput('specialties', 'Specialties (comma separated)', ''),
                S::buttonPrimary('Save Stylist',
                    S::formSubmitAction(self::BASE.'/stylists', 'POST', 'Stylist saved.', reload: true), 'save'),
            ]),
            S::card($list !== [] ? $list : [
                S::text('No stylists yet.', 'body_small', ['color' => '#64748b']),
            ]),
        ]));
    }

    public function stylistsStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'specialties' => ['nullable', 'string', 'max:255'],
        ]);
        $data['company_id'] = $company->id;

        $stylist = Stylist::create($data);

        return response()->json(['success' => true, 'message' => 'Stylist saved.', 'id' => $stylist->id]);
    }

    public function appointmentsView(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $services = SalonService::where('company_id', $company->id)->orderBy('name')->get();
        $stylists = Stylist::where('company_id', $company->id)->orderBy('name')->get();

        $serviceOptions = [['label' => '— none —', 'value' => '']];
        foreach ($services as $s) {
            $serviceOptions[] = ['label' => $s->name.' ('.$s->price.')', 'value' => (string) $s->id];
        }
        $stylistOptions = [['label' => '— any —', 'value' => '']];
        foreach ($stylists as $s) {
            $stylistOptions[] = ['label' => $s->name, 'value' => (string) $s->id];
        }

        $rows = Appointment::where('company_id', $company->id)
            ->orderByDesc('scheduled_at')->orderByDesc('created_at')->limit(100)->get();

        $list = [];
        foreach ($rows as $a) {
            $when = $a->scheduled_at ? $a->scheduled_at->format('Y-m-d H:i') : 'unscheduled';
            $list[] = S::lineItemTile(
                "{$a->reference}  ·  {$a->customer_name}",
                $when.'  ·  '.ucfirst(str_replace('_', ' ', $a->status)),
                'event',
                S::navigateAction(self::BASE.'/views/appointment-detail?id='.$a->id, 'dynamic_page', $a->reference),
            );
        }

        return $this->schema(S::screen('Appointments', [
            S::card([
                S::text('Book an appointment', 'title_medium', ['bold' => true]),
                S::textInput('customer_name', 'Customer name', ''),
                S::textInput('customer_phone', 'Customer phone', ''),
                S::dropdownSelect('service_id', 'Service', $serviceOptions, ''),
                S::dropdownSelect('stylist_id', 'Stylist', $stylistOptions, ''),
                S::textInput('scheduled_at', 'When (YYYY-MM-DD HH:MM)', ''),
                S::textInput('notes', 'Notes', '', ['max_lines' => 2, 'keyboard_type' => 'multiline']),
                S::buttonPrimary('Book Appointment',
                    S::formSubmitAction(self::BASE.'/appointments', 'POST', 'Appointment booked.', reload: true), 'event_available'),
            ]),
            S::card($list !== [] ? $list : [
                S::text('No appointments yet.', 'body_small', ['color' => '#64748b']),
            ]),
        ]));
    }

    public function appointmentsStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:200'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'service_id' => ['nullable'],
            'stylist_id' => ['nullable'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $serviceId = ! empty($data['service_id']) ? (int) $data['service_id'] : null;
        $price = 0;
        if ($serviceId) {
            $price = (float) (SalonService::where('company_id', $company->id)->find($serviceId)?->price ?? 0);
        }

        $appt = Appointment::create([
            'company_id' => $company->id,
            'reference' => 'APT-'.strtoupper(Str::random(8)),
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'service_id' => $serviceId,
            'stylist_id' => ! empty($data['stylist_id']) ? (int) $data['stylist_id'] : null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'price' => $price,
            'status' => 'booked',
        ]);

        return response()->json(['success' => true, 'message' => 'Appointment booked.', 'id' => $appt->id, 'reference' => $appt->reference]);
    }

    public function appointmentDetail(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $appt = Appointment::with(['service', 'stylist'])
            ->where('company_id', $company->id)
            ->findOrFail($request->query('id'));

        $statusOptions = [];
        foreach (Appointment::STATUSES as $s) {
            $statusOptions[] = [
                'label' => ucfirst(str_replace('_', ' ', $s)),
                'icon' => 'arrow_forward',
                'action' => S::apiPostAction(self::BASE.'/appointments/'.$appt->id.'/status', ['status' => $s], 'Moved to '.$s, reload: true),
            ];
        }

        return $this->schema(S::screen('Appointment '.$appt->reference, [
            S::card([
                S::text($appt->reference, 'title_large', ['bold' => true]),
                S::text("Customer: {$appt->customer_name}".($appt->customer_phone ? "  ·  {$appt->customer_phone}" : ''), 'body_medium'),
                S::text('Service: '.($appt->service->name ?? '—').'  ·  Stylist: '.($appt->stylist->name ?? 'any'), 'body_small', ['color' => '#64748b']),
                S::text('When: '.($appt->scheduled_at ? $appt->scheduled_at->format('Y-m-d H:i') : 'unscheduled'), 'body_small', ['color' => '#64748b']),
                S::text('Status: '.ucfirst(str_replace('_', ' ', $appt->status)), 'body_small', ['color' => '#64748b']),
                S::divider(),
                S::actionSheetTrigger('Change Appointment Status', $statusOptions, 'swap_horiz', ['sheet_title' => 'Move appointment to stage']),
            ]),
            S::card([
                S::text('Notes', 'label_large', ['bold' => true]),
                S::text($appt->notes ?: 'None.', 'body_medium'),
            ]),
        ]));
    }

    public function appointmentStatus(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $appt = Appointment::where('company_id', $company->id)->findOrFail($id);

        $status = strtolower(trim((string) $request->input('status')));
        if (! in_array($status, Appointment::STATUSES, true)) {
            return response()->json(['success' => false, 'error' => 'Invalid status.'], 422);
        }

        $appt->update(['status' => $status]);

        return response()->json(['success' => true, 'message' => "Appointment moved to {$status}.", 'action' => 'refresh_view']);
    }

    private function schema(array $schema): JsonResponse
    {
        return response()->json([
            'success' => true,
            'view' => $schema['key'] ?? 'salon-module',
            'schema' => $schema,
        ]);
    }
}
