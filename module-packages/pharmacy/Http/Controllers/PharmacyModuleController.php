<?php

namespace Modules\pharmacy\Http\Controllers;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Services\Sdui\SchemaResponse as S;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\pharmacy\Models\DrugBatch;
use Modules\pharmacy\Models\Prescription;
use Modules\pharmacy\Models\PrescriptionItem;

/**
 * Server-Driven UI + CRUD for the packaged "pharmacy" module. Every screen is
 * a plain SDUI schema built with the host's App\Services\Sdui\SchemaResponse
 * helpers, so the existing mobile / web renderer draws it with no client
 * changes.
 */
class PharmacyModuleController extends Controller
{
    use ResolvesTenantSyncContext;

    private const BASE = '/api/tenant/pharmacy-module';

    public function dashboard(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $batches = DrugBatch::where('company_id', $company->id)->count();
        $expiringSoon = DrugBatch::where('company_id', $company->id)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [Carbon::now()->toDateString(), Carbon::now()->addDays(30)->toDateString()])
            ->count();
        $pendingRx = Prescription::where('company_id', $company->id)->where('status', 'pending')->count();

        return $this->schema(S::screen('Pharmacy', [
            S::gridView([
                S::lineItemTile('Drug Batches', (string) $batches, 'inventory_2',
                    S::navigateAction(self::BASE.'/views/batches', 'dynamic_page', 'Drug Batches')),
                S::lineItemTile('Expiring in 30 days', (string) $expiringSoon, 'schedule',
                    S::navigateAction(self::BASE.'/views/batches', 'dynamic_page', 'Drug Batches')),
                S::lineItemTile('Pending Prescriptions', (string) $pendingRx, 'receipt_long',
                    S::navigateAction(self::BASE.'/views/prescriptions', 'dynamic_page', 'Prescriptions')),
            ], 2),
            S::card([
                S::text('Pharmacy module', 'title_medium', ['bold' => true]),
                S::text('Track drug batches and their expiry, take prescriptions in, and mark them dispensed. Installed and managed from Super Admin → Modules.', 'body_small', ['color' => '#64748b']),
            ]),
        ]));
    }

    public function batchesView(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $rows = DrugBatch::where('company_id', $company->id)
            ->orderByRaw('expiry_date is null, expiry_date asc')
            ->limit(100)
            ->get();

        $list = [];
        foreach ($rows as $b) {
            $exp = $b->expiry_date ? $b->expiry_date->format('Y-m-d') : 'n/a';
            $list[] = S::lineItemTile(
                trim($b->product_name.'  ·  '.$b->batch_no, ' ·'),
                "Qty {$b->quantity}  ·  MRP {$b->mrp}  ·  Exp {$exp}",
                'medication',
            );
        }

        return $this->schema(S::screen('Drug Batches & Expiry', [
            S::card([
                S::text('Register a drug batch', 'title_medium', ['bold' => true]),
                S::textInput('product_name', 'Medicine / product name', ''),
                S::textInput('batch_no', 'Batch number', ''),
                S::textInput('expiry_date', 'Expiry date (YYYY-MM-DD)', ''),
                S::textInput('quantity', 'Quantity', '0'),
                S::textInput('mrp', 'MRP', '0'),
                S::textInput('supplier', 'Supplier', ''),
                S::buttonPrimary('Save Batch',
                    S::formSubmitAction(self::BASE.'/batches', 'POST', 'Batch saved.', reload: true), 'save'),
            ]),
            S::card($list !== [] ? $list : [
                S::text('No batches registered yet.', 'body_small', ['color' => '#64748b']),
            ]),
        ]));
    }

    public function batchesStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $data = $request->validate([
            'product_name' => ['required', 'string', 'max:200'],
            'batch_no' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'mrp' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:200'],
        ]);
        $data['company_id'] = $company->id;

        $batch = DrugBatch::create($data);

        return response()->json(['success' => true, 'message' => 'Drug batch saved.', 'id' => $batch->id]);
    }

    public function prescriptionsView(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $rows = Prescription::where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $list = [];
        foreach ($rows as $rx) {
            $list[] = S::lineItemTile(
                "{$rx->rx_number}  ·  {$rx->patient_name}",
                'Status: '.ucfirst($rx->status).($rx->doctor_name ? "  ·  Dr {$rx->doctor_name}" : ''),
                'receipt_long',
                S::navigateAction(self::BASE.'/views/prescription-detail?id='.$rx->id, 'dynamic_page', $rx->rx_number),
            );
        }

        return $this->schema(S::screen('Prescriptions', [
            S::card([
                S::text('New prescription', 'title_medium', ['bold' => true]),
                S::textInput('patient_name', 'Patient name', ''),
                S::textInput('patient_phone', 'Patient phone', ''),
                S::textInput('doctor_name', 'Prescribing doctor', ''),
                S::textInput('drugs', 'Drugs (one per line: name | dosage | qty)', '', ['max_lines' => 4, 'keyboard_type' => 'multiline']),
                S::textInput('notes', 'Notes', '', ['max_lines' => 2, 'keyboard_type' => 'multiline']),
                S::buttonPrimary('Create Prescription',
                    S::formSubmitAction(self::BASE.'/prescriptions', 'POST', 'Prescription created.', reload: true), 'add'),
            ]),
            S::card($list !== [] ? $list : [
                S::text('No prescriptions yet.', 'body_small', ['color' => '#64748b']),
            ]),
        ]));
    }

    public function prescriptionsStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $data = $request->validate([
            'patient_name' => ['required', 'string', 'max:200'],
            'patient_phone' => ['nullable', 'string', 'max:40'],
            'doctor_name' => ['nullable', 'string', 'max:200'],
            'drugs' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $rx = Prescription::create([
            'company_id' => $company->id,
            'rx_number' => 'RX-'.strtoupper(Str::random(8)),
            'patient_name' => $data['patient_name'],
            'patient_phone' => $data['patient_phone'] ?? null,
            'doctor_name' => $data['doctor_name'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        foreach (preg_split('/\R/', (string) ($data['drugs'] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            PrescriptionItem::create([
                'prescription_id' => $rx->id,
                'drug_name' => $parts[0] ?? $line,
                'dosage' => $parts[1] ?? null,
                'quantity' => is_numeric($parts[2] ?? null) ? (float) $parts[2] : 1,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Prescription created.', 'id' => $rx->id, 'rx_number' => $rx->rx_number]);
    }

    public function prescriptionDetail(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $rx = Prescription::with('items')
            ->where('company_id', $company->id)
            ->findOrFail($request->query('id'));

        $itemRows = [];
        foreach ($rx->items as $it) {
            $itemRows[] = S::lineItemTile(
                $it->drug_name,
                trim(($it->dosage ? $it->dosage.'  ·  ' : '')."Qty {$it->quantity}"),
                'medication',
            );
        }

        $components = [
            S::card([
                S::text($rx->rx_number, 'title_large', ['bold' => true]),
                S::text("Patient: {$rx->patient_name}".($rx->patient_phone ? "  ·  {$rx->patient_phone}" : ''), 'body_medium'),
                S::text('Status: '.ucfirst($rx->status), 'body_small', ['color' => '#64748b']),
            ]),
            S::card($itemRows !== [] ? $itemRows : [S::text('No line items.', 'body_small', ['color' => '#64748b'])]),
        ];

        if ($rx->status === 'pending') {
            $components[] = S::buttonPrimary('Mark Dispensed',
                S::apiPostAction(self::BASE.'/prescriptions/'.$rx->id.'/dispense', [], 'Prescription dispensed.', reload: true),
                'check_circle');
        }

        return $this->schema(S::screen('Prescription '.$rx->rx_number, $components));
    }

    public function prescriptionDispense(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $rx = Prescription::where('company_id', $company->id)->findOrFail($id);
        $rx->update(['status' => 'dispensed', 'dispensed_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Prescription dispensed.', 'action' => 'refresh_view']);
    }

    private function schema(array $schema): JsonResponse
    {
        return response()->json([
            'success' => true,
            'view' => $schema['key'] ?? 'pharmacy-module',
            'schema' => $schema,
        ]);
    }
}
