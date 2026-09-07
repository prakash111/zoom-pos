<?php

namespace Modules\repairtechnician\Http\Controllers;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Services\Sdui\SchemaResponse as S;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\repairtechnician\Models\DeviceCategory;
use Modules\repairtechnician\Models\RepairTicket;

/**
 * Server-Driven UI + CRUD for the packaged "repairtechnician" module.
 */
class RepairModuleController extends Controller
{
    use ResolvesTenantSyncContext;

    private const BASE = '/api/tenant/repair-module';

    public function dashboard(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $open = RepairTicket::where('company_id', $company->id)
            ->whereNotIn('status', ['delivered', 'cancelled'])->count();
        $ready = RepairTicket::where('company_id', $company->id)->where('status', 'ready')->count();
        $categories = DeviceCategory::where('company_id', $company->id)->count();

        return $this->schema(S::screen('Repair', [
            S::gridView([
                S::lineItemTile('Open Tickets', (string) $open, 'confirmation_number',
                    S::navigateAction(self::BASE.'/views/tickets', 'dynamic_page', 'Repair Tickets')),
                S::lineItemTile('Ready for Pickup', (string) $ready, 'task_alt',
                    S::navigateAction(self::BASE.'/views/tickets', 'dynamic_page', 'Repair Tickets')),
                S::lineItemTile('Device Categories', (string) $categories, 'category',
                    S::navigateAction(self::BASE.'/views/categories', 'dynamic_page', 'Device Categories')),
            ], 2),
            S::card([
                S::text('Repair module', 'title_medium', ['bold' => true]),
                S::text('Take devices in, run a diagnostic checklist, move a ticket through its lifecycle, and hand it back. Installed and managed from Super Admin → Modules.', 'body_small', ['color' => '#64748b']),
            ]),
        ]));
    }

    public function ticketsView(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $categories = DeviceCategory::where('company_id', $company->id)->orderBy('name')->get();
        $categoryOptions = [['label' => '— none —', 'value' => '']];
        foreach ($categories as $cat) {
            $categoryOptions[] = ['label' => $cat->name, 'value' => (string) $cat->id];
        }

        $rows = RepairTicket::where('company_id', $company->id)
            ->orderByDesc('created_at')->limit(100)->get();

        $list = [];
        foreach ($rows as $t) {
            $list[] = S::lineItemTile(
                "{$t->ticket_number}  ·  {$t->customer_name}",
                trim("{$t->device_brand} {$t->device_model}").'  ·  '.ucfirst(str_replace('_', ' ', $t->status)),
                'handyman',
                S::navigateAction(self::BASE.'/views/ticket-detail?id='.$t->id, 'dynamic_page', $t->ticket_number),
            );
        }

        return $this->schema(S::screen('Repair Tickets', [
            S::card([
                S::text('New repair intake', 'title_medium', ['bold' => true]),
                S::textInput('customer_name', 'Customer name', ''),
                S::textInput('customer_phone', 'Customer phone', ''),
                S::dropdownSelect('category_id', 'Device category', $categoryOptions, ''),
                S::textInput('device_brand', 'Device brand', ''),
                S::textInput('device_model', 'Device model', ''),
                S::textInput('imei_serial', 'IMEI / serial', ''),
                S::textInput('reported_issue', 'Reported issue', '', ['max_lines' => 3, 'keyboard_type' => 'multiline']),
                S::textInput('estimated_cost', 'Estimated cost', '0'),
                S::textInput('advance_paid', 'Advance paid', '0'),
                S::buttonPrimary('Create Ticket',
                    S::formSubmitAction(self::BASE.'/tickets', 'POST', 'Ticket created.', reload: true), 'add_task'),
            ]),
            S::card($list !== [] ? $list : [
                S::text('No repair tickets yet.', 'body_small', ['color' => '#64748b']),
            ]),
        ]));
    }

    public function ticketsStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:200'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'category_id' => ['nullable'],
            'device_brand' => ['nullable', 'string', 'max:120'],
            'device_model' => ['nullable', 'string', 'max:120'],
            'imei_serial' => ['nullable', 'string', 'max:120'],
            'reported_issue' => ['nullable', 'string', 'max:2000'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'advance_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        $categoryId = ! empty($data['category_id']) ? (int) $data['category_id'] : null;
        $checklist = [];
        if ($categoryId) {
            $cat = DeviceCategory::where('company_id', $company->id)->find($categoryId);
            foreach ((array) ($cat->checklist_points ?? []) as $point) {
                $label = is_array($point) ? ($point['label'] ?? '') : (string) $point;
                if (trim((string) $label) !== '') {
                    $checklist[] = ['label' => $label, 'status' => 'pending'];
                }
            }
        }

        $ticket = RepairTicket::create([
            'company_id' => $company->id,
            'ticket_number' => 'REP-'.strtoupper(Str::random(8)),
            'category_id' => $categoryId,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'device_brand' => $data['device_brand'] ?? null,
            'device_model' => $data['device_model'] ?? null,
            'imei_serial' => $data['imei_serial'] ?? null,
            'reported_issue' => $data['reported_issue'] ?? null,
            'estimated_cost' => $data['estimated_cost'] ?? 0,
            'advance_paid' => $data['advance_paid'] ?? 0,
            'inspection_checklist' => $checklist ?: null,
            'status' => 'received',
        ]);

        return response()->json(['success' => true, 'message' => 'Repair ticket created.', 'id' => $ticket->id, 'ticket_number' => $ticket->ticket_number]);
    }

    public function ticketDetail(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $ticket = RepairTicket::where('company_id', $company->id)->findOrFail($request->query('id'));

        $checkRows = [];
        foreach ((array) ($ticket->inspection_checklist ?? []) as $c) {
            $checkRows[] = S::lineItemTile(
                is_array($c) ? ($c['label'] ?? 'Checkpoint') : (string) $c,
                is_array($c) ? strtoupper((string) ($c['status'] ?? 'pending')) : 'PENDING',
                'checklist',
            );
        }

        $statusOptions = [];
        foreach (RepairTicket::STATUSES as $s) {
            $statusOptions[] = [
                'label' => ucfirst(str_replace('_', ' ', $s)),
                'icon' => 'arrow_forward',
                'action' => S::apiPostAction(self::BASE.'/tickets/'.$ticket->id.'/status', ['status' => $s], 'Moved to '.$s, reload: true),
            ];
        }

        return $this->schema(S::screen('Ticket '.$ticket->ticket_number, [
            S::card([
                S::text($ticket->ticket_number, 'title_large', ['bold' => true]),
                S::text("Customer: {$ticket->customer_name}".($ticket->customer_phone ? "  ·  {$ticket->customer_phone}" : ''), 'body_medium'),
                S::text(trim("Device: {$ticket->device_brand} {$ticket->device_model}"), 'body_small', ['color' => '#64748b']),
                S::text('Status: '.ucfirst(str_replace('_', ' ', $ticket->status)), 'body_small', ['color' => '#64748b']),
                S::divider(),
                S::actionSheetTrigger('Change Ticket Status', $statusOptions, 'swap_horiz', ['sheet_title' => 'Move ticket to stage']),
            ]),
            S::card([
                S::text('Reported issue', 'label_large', ['bold' => true]),
                S::text($ticket->reported_issue ?: 'None recorded.', 'body_medium'),
            ]),
            S::card(array_merge(
                [S::text('Diagnostic checklist', 'label_large', ['bold' => true])],
                $checkRows !== [] ? $checkRows : [S::text('No checklist points (pick a device category with checkpoints).', 'body_small', ['color' => '#64748b'])],
            )),
        ]));
    }

    public function ticketStatus(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $ticket = RepairTicket::where('company_id', $company->id)->findOrFail($id);

        $status = strtolower(trim((string) $request->input('status')));
        if (! in_array($status, RepairTicket::STATUSES, true)) {
            return response()->json(['success' => false, 'error' => 'Invalid status.'], 422);
        }

        $ticket->status = $status;
        if ($status === 'delivered') {
            $ticket->delivered_at = now();
        }
        $ticket->save();

        return response()->json(['success' => true, 'message' => "Ticket moved to {$status}.", 'action' => 'refresh_view']);
    }

    public function categoriesView(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $rows = DeviceCategory::where('company_id', $company->id)->orderBy('name')->get();

        $list = [];
        foreach ($rows as $cat) {
            $points = collect((array) ($cat->checklist_points ?? []))
                ->map(fn ($p) => is_array($p) ? ($p['label'] ?? '') : (string) $p)
                ->filter()->implode(', ');
            $list[] = S::lineItemTile($cat->name, "Diag fee {$cat->default_diagnostic_fee}".($points ? "  ·  {$points}" : ''), 'category');
        }

        return $this->schema(S::screen('Device Categories', [
            S::card([
                S::text('Add device category', 'title_medium', ['bold' => true]),
                S::textInput('name', 'Category name (e.g. Smartphone)', ''),
                S::textInput('default_diagnostic_fee', 'Default diagnostic fee', '0'),
                S::textInput('checklist_points', 'Checklist points (one per line)', '', ['max_lines' => 4, 'keyboard_type' => 'multiline']),
                S::buttonPrimary('Save Category',
                    S::formSubmitAction(self::BASE.'/categories', 'POST', 'Category saved.', reload: true), 'save'),
            ]),
            S::card($list !== [] ? $list : [
                S::text('No categories yet.', 'body_small', ['color' => '#64748b']),
            ]),
        ]));
    }

    public function categoriesStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'default_diagnostic_fee' => ['nullable', 'numeric', 'min:0'],
            'checklist_points' => ['nullable', 'string'],
        ]);

        $points = [];
        foreach (preg_split('/\R/', (string) ($data['checklist_points'] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $points[] = ['label' => $line];
            }
        }

        $cat = DeviceCategory::create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'default_diagnostic_fee' => $data['default_diagnostic_fee'] ?? 0,
            'checklist_points' => $points ?: null,
        ]);

        return response()->json(['success' => true, 'message' => 'Device category saved.', 'id' => $cat->id]);
    }

    private function schema(array $schema): JsonResponse
    {
        return response()->json([
            'success' => true,
            'view' => $schema['key'] ?? 'repair-module',
            'schema' => $schema,
        ]);
    }
}
