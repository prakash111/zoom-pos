<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\RepairChecklist;
use App\Models\RepairDeviceCategory;
use App\Models\RepairTicket;
use App\Models\RepairTicketPart;
use App\Models\Sale;
use App\Services\Sdui\PosScreenBuilder;
use App\Services\Sdui\SchemaValidator;
use App\Services\TaxCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RepairApiController extends Controller
{
    use ResolvesTenantSyncContext;

    /**
     * Kanban counts and financial metrics for repair workshop.
     * GET /api/tenant/repair/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $tickets = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->get();

        $stats = [
            'active' => $tickets->where('status', 'active')->count(),
            'diagnosing' => $tickets->where('status', 'diagnosing')->count(),
            'waiting_parts' => $tickets->where('status', 'waiting_parts')->count(),
            'in_progress' => $tickets->where('status', 'in_progress')->count(),
            'repaired' => $tickets->where('status', 'repaired')->count(),
            'delivered' => $tickets->where('status', 'delivered')->count(),
            'cancelled' => $tickets->where('status', 'cancelled')->count(),
            'total' => $tickets->count(),
            'total_revenue' => round($tickets->whereIn('status', ['repaired', 'delivered'])->sum('total_amount'), 2),
            'pending_receivables' => round($tickets->whereNotIn('status', ['delivered', 'cancelled'])->sum(fn ($t) => $t->balance_due), 2),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * List all dynamic device repair categories with custom specifications.
     * GET /api/tenant/repair/categories
     */
    public function categoriesIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $categories = RepairDeviceCategory::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // If company has no categories yet, automatically initialize default presets
        if ($categories->isEmpty()) {
            foreach (RepairDeviceCategory::defaultPresets() as $preset) {
                RepairDeviceCategory::create(array_merge($preset, [
                    'company_id' => $company->id,
                    'tenant_id' => $company->id,
                    'is_active' => true,
                    'is_demo' => false,
                ]));
            }

            $categories = RepairDeviceCategory::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        return response()->json([
            'success' => true,
            'count' => $categories->count(),
            'categories' => $categories,
        ]);
    }

    /**
     * Create a new custom device repair category.
     * POST /api/tenant/repair/categories
     */
    public function categoriesStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'icon' => 'nullable|string|max:100',
            'identifier_type' => 'nullable|string|max:100',
            'brands' => 'nullable',
            'checklist_items' => 'nullable',
            'common_issues' => 'nullable',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $parseList = function ($val) {
            if (is_array($val)) {
                return array_values(array_filter(array_map('trim', $val)));
            }
            if (is_string($val) && trim($val) !== '') {
                return array_values(array_filter(array_map('trim', explode(',', $val))));
            }

            return [];
        };

        $name = trim($request->input('name'));
        $slug = Str::slug($name);
        $brands = $parseList($request->input('brands'));
        $checklistItems = $parseList($request->input('checklist_items'));
        $commonIssues = $parseList($request->input('common_issues'));

        if (empty($brands)) {
            $brands = ['Generic', 'OEM', 'Other'];
        }

        if (empty($checklistItems)) {
            $checklistItems = ['Power On / Boot', 'Physical Housing Condition', 'Component Functionality'];
        }

        $category = RepairDeviceCategory::create([
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'name' => $name,
            'slug' => $slug,
            'icon' => $request->input('icon') ?: 'devices',
            'identifier_type' => $request->input('identifier_type') ?: 'Serial / IMEI',
            'brands' => $brands,
            'checklist_items' => $checklistItems,
            'common_issues' => $commonIssues,
            'description' => $request->input('description'),
            'sort_order' => (int) RepairDeviceCategory::withoutGlobalScope('company')->where('company_id', $company->id)->max('sort_order') + 1,
            'is_active' => true,
            'is_demo' => false,
        ]);

        AuditLog::record('repair.category_created', $company->id, $user?->id, [
            'category_id' => $category->id,
            'name' => $category->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device category created successfully.',
            'category' => $category,
        ], 201);
    }

    /**
     * Update custom device category specifications.
     * PUT/POST /api/tenant/repair/categories/{id}
     */
    public function categoriesUpdate(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $category = RepairDeviceCategory::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->find($id);

        if (! $category) {
            return response()->json(['success' => false, 'error' => 'Device category not found.'], 404);
        }

        $parseList = function ($val, $fallback) {
            if (is_array($val)) {
                return array_values(array_filter(array_map('trim', $val)));
            }
            if (is_string($val)) {
                return array_values(array_filter(array_map('trim', explode(',', $val))));
            }

            return $fallback;
        };

        $data = [];
        if ($request->filled('name')) {
            $data['name'] = trim($request->input('name'));
            $data['slug'] = Str::slug($data['name']);
        }
        if ($request->has('icon')) {
            $data['icon'] = $request->input('icon') ?: 'devices';
        }
        if ($request->has('identifier_type')) {
            $data['identifier_type'] = $request->input('identifier_type') ?: 'Serial / IMEI';
        }
        if ($request->has('brands')) {
            $data['brands'] = $parseList($request->input('brands'), $category->brands);
        }
        if ($request->has('checklist_items')) {
            $data['checklist_items'] = $parseList($request->input('checklist_items'), $category->checklist_items);
        }
        if ($request->has('common_issues')) {
            $data['common_issues'] = $parseList($request->input('common_issues'), $category->common_issues);
        }
        if ($request->has('description')) {
            $data['description'] = $request->input('description');
        }
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $category->update($data);

        AuditLog::record('repair.category_updated', $company->id, $user?->id, [
            'category_id' => $category->id,
            'name' => $category->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device category updated successfully.',
            'category' => $category->fresh(),
        ]);
    }

    /**
     * Delete/Deactivate device repair category.
     * DELETE /api/tenant/repair/categories/{id}
     */
    public function categoriesDestroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $category = RepairDeviceCategory::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->find($id);

        if (! $category) {
            return response()->json(['success' => false, 'error' => 'Device category not found.'], 404);
        }

        $category->delete();

        AuditLog::record('repair.category_deleted', $company->id, $user?->id, [
            'category_id' => $id,
            'name' => $category->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device category removed successfully.',
        ]);
    }

    /**
     * Filterable ticket list for Workbench & Register.
     * GET /api/tenant/repair/tickets
     */
    public function ticketsIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $status = $request->query('status'); // 'all', 'active', 'diagnosing', etc.
        $techId = $request->query('technician_id');
        $search = trim((string) ($request->query('search') ?? ''));

        $query = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['parts', 'checklists', 'technician:id,name']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($techId === 'me' && $user) {
            $query->where('technician_id', $user->id);
        } elseif ($techId && $techId !== 'all') {
            $query->where('technician_id', $techId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('serial_or_imei', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        $tickets = $query->orderByDesc('created_at')->limit(100)->get();

        $formatted = $tickets->map(function (RepairTicket $t) {
            return [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'customer_id' => $t->customer_id,
                'customer_name' => $t->customer_name,
                'customer_phone' => $t->customer_phone,
                'device_type' => $t->device_type,
                'brand' => $t->brand,
                'model' => $t->model,
                'serial_or_imei' => $t->serial_or_imei,
                'passcode_or_pattern' => $t->passcode_or_pattern,
                'issue_description' => $t->issue_description,
                'physical_condition_notes' => $t->physical_condition_notes,
                'status' => $t->status,
                'status_color' => $t->status_color,
                'priority' => $t->priority,
                'priority_color' => $t->priority_color,
                'technician_id' => $t->technician_id,
                'technician_name' => $t->technician?->name ?? 'Unassigned',
                'estimated_cost' => (float) $t->estimated_cost,
                'advance_paid' => (float) $t->advance_paid,
                'labor_fee' => (float) $t->labor_fee,
                'parts_cost' => (float) $t->parts_cost,
                'total_amount' => (float) $t->total_amount,
                'balance_due' => (float) $t->balance_due,
                'parts_count' => $t->parts->count(),
                'intake_at' => $t->intake_at?->format('Y-m-d H:i'),
                'completed_at' => $t->completed_at?->format('Y-m-d H:i'),
                'delivered_at' => $t->delivered_at?->format('Y-m-d H:i'),
                'created_at' => $t->created_at?->format('Y-m-d H:i'),
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $formatted->count(),
            'tickets' => $formatted,
        ]);
    }

    /**
     * Single ticket detail with parts, checklist, and actions.
     * GET /api/tenant/repair/tickets/{id}
     */
    public function ticketsShow(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $ticket = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['parts.product', 'checklists', 'technician:id,name', 'finalSale'])
            ->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'customer_id' => $ticket->customer_id,
                'customer_name' => $ticket->customer_name,
                'customer_phone' => $ticket->customer_phone,
                'device_type' => $ticket->device_type,
                'brand' => $ticket->brand,
                'model' => $ticket->model,
                'serial_or_imei' => $ticket->serial_or_imei,
                'passcode_or_pattern' => $ticket->passcode_or_pattern,
                'issue_description' => $ticket->issue_description,
                'physical_condition_notes' => $ticket->physical_condition_notes,
                'status' => $ticket->status,
                'status_color' => $ticket->status_color,
                'priority' => $ticket->priority,
                'priority_color' => $ticket->priority_color,
                'technician_id' => $ticket->technician_id,
                'technician_name' => $ticket->technician?->name ?? 'Unassigned',
                'estimated_cost' => (float) $ticket->estimated_cost,
                'advance_paid' => (float) $ticket->advance_paid,
                'labor_fee' => (float) $ticket->labor_fee,
                'parts_cost' => (float) $ticket->parts_cost,
                'total_amount' => (float) $ticket->total_amount,
                'balance_due' => (float) $ticket->balance_due,
                'internal_notes' => $ticket->internal_notes,
                'intake_at' => $ticket->intake_at?->format('Y-m-d H:i'),
                'completed_at' => $ticket->completed_at?->format('Y-m-d H:i'),
                'delivered_at' => $ticket->delivered_at?->format('Y-m-d H:i'),
                'parts' => $ticket->parts->map(fn ($p) => [
                    'id' => $p->id,
                    'product_id' => $p->product_id,
                    'part_name' => $p->part_name,
                    'quantity' => $p->quantity,
                    'unit_cost' => (float) $p->unit_cost,
                    'unit_price' => (float) $p->unit_price,
                    'subtotal' => (float) $p->subtotal,
                    'billed_to_customer' => (bool) $p->billed_to_customer,
                ]),
                'checklists' => $ticket->checklists->map(fn ($c) => [
                    'id' => $c->id,
                    'item_name' => $c->item_name,
                    'type' => $c->type,
                    'status' => $c->status,
                    'notes' => $c->notes,
                ]),
                'final_sale' => $ticket->finalSale ? [
                    'id' => $ticket->finalSale->id,
                    'sale_number' => $ticket->finalSale->sale_number,
                    'total' => (float) $ticket->finalSale->total,
                ] : null,
            ],
        ]);
    }

    /**
     * Create new device intake repair ticket.
     * POST /api/tenant/repair/tickets
     */
    public function ticketsStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'required|string|max:50',
            'device_category_id' => 'nullable|integer',
            'device_type' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_or_imei' => 'nullable|string|max:100',
            'passcode_or_pattern' => 'nullable|string|max:100',
            'issue_description' => 'required|string',
            'physical_condition_notes' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'technician_id' => 'nullable|string',
            'estimated_cost' => 'nullable|numeric|min:0',
            'advance_paid' => 'nullable|numeric|min:0',
            'checklists' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $ticket = DB::transaction(function () use ($company, $request) {
            $year = date('Y');
            $count = RepairTicket::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->whereYear('created_at', $year)
                ->count() + 1;

            $ticketNumber = sprintf('REP-%s-%04d', $year, $count);
            $estimatedCost = (float) $request->input('estimated_cost', 0);
            $advancePaid = (float) $request->input('advance_paid', 0);

            // Resolve dynamic category
            $category = null;
            if ($request->filled('device_category_id')) {
                $category = RepairDeviceCategory::withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->find($request->input('device_category_id'));
            }
            if (! $category && $request->filled('device_type')) {
                $category = RepairDeviceCategory::withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where('name', trim($request->input('device_type')))
                    ->first();
            }

            $deviceType = $category?->name ?? trim((string) ($request->input('device_type') ?: 'Device'));
            $brand = trim((string) ($request->input('brand') ?: 'Generic'));
            $model = trim((string) ($request->input('model') ?: 'Standard'));

            $ticket = RepairTicket::create([
                'company_id' => $company->id,
                'tenant_id' => $company->id,
                'ticket_number' => $ticketNumber,
                'customer_id' => $request->input('customer_id'),
                'customer_name' => trim($request->input('customer_name')),
                'customer_phone' => trim($request->input('customer_phone')),
                'device_category_id' => $category?->id,
                'device_type' => $deviceType,
                'brand' => $brand,
                'model' => $model,
                'serial_or_imei' => $request->input('serial_or_imei'),
                'passcode_or_pattern' => $request->input('passcode_or_pattern'),
                'issue_description' => trim($request->input('issue_description')),
                'physical_condition_notes' => $request->input('physical_condition_notes'),
                'status' => 'active',
                'priority' => $request->input('priority', 'normal'),
                'technician_id' => $request->input('technician_id'),
                'estimated_cost' => $estimatedCost,
                'advance_paid' => $advancePaid,
                'labor_fee' => 0,
                'parts_cost' => 0,
                'total_amount' => $estimatedCost > 0 ? $estimatedCost : $advancePaid,
                'intake_at' => now(),
            ]);

            // Save checklists: from input or category checklist specifications
            $checklists = $request->input('checklists');
            if (! empty($checklists) && is_array($checklists)) {
                foreach ($checklists as $item) {
                    RepairChecklist::create([
                        'company_id' => $company->id,
                        'tenant_id' => $company->id,
                        'repair_ticket_id' => $ticket->id,
                        'item_name' => $item['item_name'] ?? $item['item'] ?? $item['name'] ?? 'Inspection Item',
                        'type' => $item['type'] ?? 'intake',
                        'status' => $item['status'] ?? 'pass',
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            } else {
                $checklistNames = ! empty($category?->checklist_items) ? $category->checklist_items : [
                    'Power On / Boot',
                    'Display / Touchscreen',
                    'Front & Rear Cameras',
                    'Speakers & Microphone',
                    'Charging Port & Battery',
                    'Housing / Glass Scratches',
                ];

                foreach ($checklistNames as $itemName) {
                    RepairChecklist::create([
                        'company_id' => $company->id,
                        'tenant_id' => $company->id,
                        'repair_ticket_id' => $ticket->id,
                        'item_name' => is_string($itemName) ? $itemName : ($itemName['item_name'] ?? 'Diagnostic Check'),
                        'type' => 'intake',
                        'status' => 'pass',
                    ]);
                }
            }

            return $ticket;
        });

        AuditLog::record('repair.ticket_created', $company->id, $user?->id, [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'customer_name' => $ticket->customer_name,
            'device' => "{$ticket->brand} {$ticket->model}",
        ]);

        return response()->json([
            'success' => true,
            'message' => "Repair intake ticket #{$ticket->ticket_number} created successfully.",
            'ticket' => $ticket->load(['parts', 'checklists']),
        ]);
    }

    /**
     * Transition ticket status along workbench lifecycle.
     * POST /api/tenant/repair/tickets/{id}/status
     */
    public function ticketsUpdateStatus(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,diagnosing,waiting_parts,in_progress,repaired,delivered,cancelled',
            'internal_notes' => 'nullable|string',
            'technician_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $ticket = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Ticket not found.'], 404);
        }

        $newStatus = $request->input('status');
        $updates = ['status' => $newStatus];

        if ($request->filled('internal_notes')) {
            $updates['internal_notes'] = $request->input('internal_notes');
        }
        if ($request->filled('technician_id')) {
            $updates['technician_id'] = $request->input('technician_id');
        }

        if ($newStatus === 'repaired' && ! $ticket->completed_at) {
            $updates['completed_at'] = now();
        }
        if ($newStatus === 'delivered' && ! $ticket->delivered_at) {
            $updates['delivered_at'] = now();
        }

        $ticket->update($updates);

        AuditLog::record('repair.status_changed', $company->id, $user?->id, [
            'ticket_id' => $ticket->id,
            'new_status' => $newStatus,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ticket status transitioned to {$newStatus}.",
            'ticket' => $ticket->fresh(['parts', 'checklists', 'technician:id,name']),
        ]);
    }

    /**
     * Add spare part to ticket & deduct inventory stock.
     * POST /api/tenant/repair/tickets/{id}/parts
     */
    public function ticketsAddPart(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'part_name' => 'required|string|max:200',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'product_id' => 'nullable|integer',
            'billed_to_customer' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $ticket = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Ticket not found.'], 404);
        }

        $productId = $request->input('product_id');
        $qty = (int) $request->input('quantity', 1);
        $unitPrice = (float) $request->input('unit_price', 0);
        $unitCost = (float) ($request->input('unit_cost', 0));
        $billed = $request->boolean('billed_to_customer', true);

        $part = DB::transaction(function () use ($company, $ticket, $productId, $qty, $unitPrice, $unitCost, $billed, $request) {
            $product = null;
            if ($productId) {
                $product = Product::withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->find($productId);

                if ($product) {
                    $product->decrementStock($qty, "Spare part for Ticket #{$ticket->ticket_number}");
                    if ($unitCost <= 0) {
                        $unitCost = (float) $product->cost_price;
                    }
                }
            }

            $subtotal = round($qty * $unitPrice, 2);

            $part = RepairTicketPart::create([
                'company_id' => $company->id,
                'tenant_id' => $company->id,
                'repair_ticket_id' => $ticket->id,
                'product_id' => $productId,
                'part_name' => trim($request->input('part_name')),
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'billed_to_customer' => $billed,
            ]);

            // Recalculate ticket parts_cost and total_amount
            $newPartsCost = (float) RepairTicketPart::where('repair_ticket_id', $ticket->id)
                ->where('billed_to_customer', true)
                ->sum('subtotal');

            $newTotal = round($newPartsCost + (float) $ticket->labor_fee, 2);

            $ticket->update([
                'parts_cost' => $newPartsCost,
                'total_amount' => $newTotal,
            ]);

            return $part;
        });

        AuditLog::record('repair.part_added', $company->id, $user?->id, [
            'ticket_id' => $ticket->id,
            'part_id' => $part->id,
            'part_name' => $part->part_name,
            'subtotal' => $part->subtotal,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Spare part added to repair ticket.',
            'part' => $part,
            'ticket' => $ticket->fresh(['parts', 'checklists']),
        ]);
    }

    /**
     * Remove spare part from ticket & restore inventory stock.
     * DELETE /api/tenant/repair/tickets/{ticketId}/parts/{partId}
     */
    public function ticketsRemovePart(Request $request, string $ticketId, string $partId): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $ticket = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->find($ticketId);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Ticket not found.'], 404);
        }

        $part = RepairTicketPart::where('repair_ticket_id', $ticket->id)->find($partId);
        if (! $part) {
            return response()->json(['success' => false, 'error' => 'Part not found on this ticket.'], 404);
        }

        DB::transaction(function () use ($company, $ticket, $part) {
            if ($part->product_id) {
                $product = Product::withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->find($part->product_id);

                $product?->incrementStock($part->quantity, "Restored part from Ticket #{$ticket->ticket_number}");
            }

            $part->delete();

            $newPartsCost = (float) RepairTicketPart::where('repair_ticket_id', $ticket->id)
                ->where('billed_to_customer', true)
                ->sum('subtotal');

            $newTotal = round($newPartsCost + (float) $ticket->labor_fee, 2);

            $ticket->update([
                'parts_cost' => $newPartsCost,
                'total_amount' => $newTotal,
            ]);
        });

        AuditLog::record('repair.part_removed', $company->id, $user?->id, [
            'ticket_id' => $ticket->id,
            'part_name' => $part->part_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Spare part removed.',
            'ticket' => $ticket->fresh(['parts', 'checklists']),
        ]);
    }

    /**
     * Set labor fee on ticket.
     * POST /api/tenant/repair/tickets/{id}/labor
     */
    public function ticketsSetLabor(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'labor_fee' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $ticket = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Ticket not found.'], 404);
        }

        $laborFee = round((float) $request->input('labor_fee'), 2);
        $newTotal = round((float) $ticket->parts_cost + $laborFee, 2);

        $ticket->update([
            'labor_fee' => $laborFee,
            'total_amount' => $newTotal,
        ]);

        AuditLog::record('repair.labor_updated', $company->id, $user?->id, [
            'ticket_id' => $ticket->id,
            'labor_fee' => $laborFee,
            'total_amount' => $newTotal,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Labor fee updated successfully.',
            'ticket' => $ticket->fresh(['parts', 'checklists']),
        ]);
    }

    /**
     * Settle repair ticket and convert to POS Sale receipt.
     * POST /api/tenant/repair/tickets/{id}/settle
     */
    public function ticketsSettle(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $ticket = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['parts'])
            ->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Ticket not found.'], 404);
        }

        if ($ticket->status === 'delivered' && $ticket->final_sale_id) {
            return response()->json(['success' => false, 'error' => 'This repair ticket has already been settled and delivered.'], 422);
        }

        $paymentMethod = strtolower((string) $request->input('payment_method', $request->input('selected_payment_method', 'cash')));
        $balanceDue = $ticket->balance_due;
        $tendered = $request->filled('tendered')
            ? (float) $request->input('tendered')
            : (float) $request->input('quick_cash_tendered', $request->input('selected_tendered', $balanceDue));

        $sale = DB::transaction(function () use ($company, $user, $ticket, $paymentMethod, $balanceDue, $tendered) {
            $items = [];

            // Add spare parts line items
            foreach ($ticket->parts as $part) {
                if ($part->billed_to_customer && (float) $part->subtotal > 0) {
                    $items[] = [
                        'product_id' => $part->product_id,
                        'name' => "Part: {$part->part_name}",
                        'quantity' => $part->quantity,
                        'price' => (float) $part->unit_price,
                        'total' => (float) $part->subtotal,
                    ];
                }
            }

            // Add Labor Fee line item
            if ((float) $ticket->labor_fee > 0) {
                $items[] = [
                    'product_id' => null,
                    'name' => "Labor / Service Charge ({$ticket->device_type})",
                    'quantity' => 1,
                    'price' => (float) $ticket->labor_fee,
                    'total' => (float) $ticket->labor_fee,
                ];
            }

            // If no parts or labor were recorded, use the estimated or total amount
            if (empty($items)) {
                $items[] = [
                    'product_id' => null,
                    'name' => "Repair Service: {$ticket->device_type} - {$ticket->brand} {$ticket->model}",
                    'quantity' => 1,
                    'price' => (float) $ticket->total_amount,
                    'total' => (float) $ticket->total_amount,
                ];
            }

            $taxTotals = app(TaxCalculationService::class)->calculateCartTotals($items, $company);
            $items = $taxTotals['items'];
            $invoiceTotal = (float) $taxTotals['total'];
            $balanceDue = max(0, round($invoiceTotal - (float) $ticket->advance_paid, 2));

            $prefix = $company->invoice_prefix ?: 'INV-';
            $saleCount = Sale::withoutGlobalScope('company')->where('company_id', $company->id)->count() + 1;
            $saleNumber = $prefix.sprintf('%04d', $saleCount);
            $cashRegister = CashRegister::openFor($company->id);
            $isCredit = $paymentMethod === 'credit';
            $paidAmount = $isCredit ? min((float) $ticket->advance_paid, $invoiceTotal) : $invoiceTotal;
            $remainingDue = $isCredit ? $balanceDue : 0.0;

            $sale = Sale::create([
                'company_id' => $company->id,
                'sale_number' => $saleNumber,
                'customer_id' => $ticket->customer_id,
                'customer_name' => $ticket->customer_name,
                'user_id' => $user?->id,
                'cash_register_id' => $cashRegister?->id,
                'total' => $invoiceTotal,
                'net_amount' => $invoiceTotal,
                'paid_amount' => $paidAmount,
                'due_amount' => $remainingDue,
                'discount' => 0,
                'payment_method' => $paymentMethod,
                'payment_status' => $remainingDue > 0 ? ($paidAmount > 0 ? 'partial' : 'pending') : 'paid',
                'status' => 'completed',
                'operation_type' => 'sale',
                'items' => $items,
                'tax_amount' => $taxTotals['tax_amount'],
                'tax_name' => $company->tax_id_label ?: 'Tax',
                'tax_breakdown' => $taxTotals['tax_summary_table'],
                'notes' => "Settled Repair Ticket #{$ticket->ticket_number}. Device: {$ticket->brand} {$ticket->model} (SN: ".($ticket->serial_or_imei ?: 'N/A')."). Advance: {$ticket->advance_paid}, Final Payment: {$balanceDue}.",
            ]);

            if ((float) $ticket->advance_paid > 0) {
                OrderPayment::create([
                    'company_id' => $company->id,
                    'sale_id' => $sale->id,
                    'cash_register_id' => $cashRegister?->id,
                    'payment_method' => 'advance_deposit',
                    'amount' => min((float) $ticket->advance_paid, $invoiceTotal),
                    'net_amount' => min((float) $ticket->advance_paid, $invoiceTotal),
                    'notes' => "Advance previously received for Repair Ticket #{$ticket->ticket_number}",
                ]);
            }
            if (! $isCredit && $balanceDue > 0) {
                OrderPayment::create([
                    'company_id' => $company->id,
                    'sale_id' => $sale->id,
                    'cash_register_id' => $cashRegister?->id,
                    'payment_method' => $paymentMethod,
                    'amount' => $balanceDue,
                    'tendered' => $paymentMethod === 'cash' ? max($balanceDue, $tendered) : null,
                    'change_returned' => $paymentMethod === 'cash' ? max(0, $tendered - $balanceDue) : 0,
                    'net_amount' => $balanceDue,
                    'notes' => "Final balance for Repair Ticket #{$ticket->ticket_number}",
                ]);
            }

            $ticket->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'completed_at' => $ticket->completed_at ?? now(),
                'final_sale_id' => $sale->id,
            ]);

            return $sale;
        });

        AuditLog::record('repair.ticket_settled', $company->id, $user?->id, [
            'ticket_id' => $ticket->id,
            'sale_id' => $sale->id,
            'total' => (float) $sale->total,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Repair ticket #{$ticket->ticket_number} settled and converted to POS Sale #{$sale->sale_number}.",
            'sale' => $sale,
            'ticket' => $ticket->fresh(['parts', 'checklists', 'finalSale']),
        ]);
    }

    /**
     * Checkout/settlement sheet (component tree) for the repair parts &
     * labor counter-sale catalog, driven by the client's current local
     * cart preview. Distinct from ticketsSettle(), which settles an
     * *existing* ticket's bill rather than a fresh counter sale.
     * GET /api/tenant/repair/checkout-sheet
     */
    public function checkoutSheet(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $cartPreview = $this->decodeCartPreview($request);
        $tickets = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->latest('intake_at')
            ->limit(50)
            ->get(['id', 'ticket_number', 'customer_name', 'device_type', 'advance_paid', 'total_amount'])
            ->map(fn (RepairTicket $ticket) => [
                'id' => $ticket->id,
                'label' => "#{$ticket->ticket_number} · {$ticket->customer_name} · {$ticket->device_type}",
                'advance_paid' => (float) $ticket->advance_paid,
                'total_amount' => (float) $ticket->total_amount,
            ])
            ->all();
        $selectedTicketId = $request->integer('ticket_id') ?: null;
        $selectedTicketCustomer = $selectedTicketId
            ? RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->whereKey($selectedTicketId)->value('customer_name')
            : null;

        $schema = PosScreenBuilder::checkoutSheet(
            $company,
            '/api/tenant/repair/pos-checkout',
            $cartPreview,
            ticketFieldLabel: 'Repair Ticket # (Optional)',
            module: 'repair',
            repairTicketOptions: $tickets,
            selectedTicketId: $selectedTicketId,
            defaultCustomerName: $selectedTicketCustomer,
        );

        $errors = app(SchemaValidator::class)->validate($schema);
        if ($errors !== []) {
            return response()->json(['success' => false, 'error' => 'Invalid SDUI schema.', 'details' => ['schema' => $errors]], 500);
        }

        return response()->json(['success' => true, 'schema' => $schema]);
    }

    /** Native final-payment drawer for an existing workbench ticket. */
    public function ticketCheckoutSheet(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $ticket = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with('parts')
            ->find($id);
        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        $preview = $ticket->parts
            ->where('billed_to_customer', true)
            ->map(fn (RepairTicketPart $part) => [
                'title' => "Part: {$part->part_name}",
                'quantity' => (int) $part->quantity,
                'price' => (float) $part->unit_price,
            ])
            ->values()
            ->all();
        if ((float) $ticket->labor_fee > 0) {
            $preview[] = [
                'title' => "Labor / Service Charge ({$ticket->device_type})",
                'quantity' => 1,
                'price' => (float) $ticket->labor_fee,
            ];
        }
        if ($preview === [] && (float) $ticket->total_amount > 0) {
            $preview[] = [
                'title' => "Repair Service: {$ticket->brand} {$ticket->model}",
                'quantity' => 1,
                'price' => (float) $ticket->total_amount,
            ];
        }

        $schema = PosScreenBuilder::checkoutSheet(
            $company,
            "/api/tenant/repair/tickets/{$ticket->id}/settle",
            $preview,
            ticketFieldLabel: 'Repair Ticket',
            module: 'repair',
            repairTicketOptions: [[
                'id' => $ticket->id,
                'label' => "#{$ticket->ticket_number} · {$ticket->customer_name} · {$ticket->device_type}",
                'advance_paid' => (float) $ticket->advance_paid,
                'total_amount' => (float) $ticket->total_amount,
            ]],
            selectedTicketId: $ticket->id,
            previewIncludesTicket: true,
            defaultCustomerName: $ticket->customer_name,
        );

        return response()->json(['success' => true, 'schema' => $schema]);
    }

    /**
     * Completes a repair counter sale (spare parts + optional labor items
     * sold independently of any ticket workflow). If an optional
     * `ticket_id` is supplied, each line is also recorded against that
     * ticket via RepairTicketPart, mirroring ticketsAddPart()'s bookkeeping
     * — but this endpoint always creates its own Sale, unlike
     * ticketsAddPart() which only updates the ticket's running total.
     * POST /api/tenant/repair/pos-checkout
     */
    public function posCheckout(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $this->normalizeFixedSplitPayments($request);

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'ticket_id' => 'nullable|integer',
            'payment_method' => 'nullable|string',
            'payments' => 'nullable|array',
            'discount' => 'nullable|numeric',
            'tendered' => 'nullable|numeric|min:0',
            'quick_cash_tendered' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $items = $request->input('items', []);
        $ticketId = $request->input('ticket_id');

        try {
            $sale = DB::transaction(function () use ($company, $user, $request, $items, $ticketId) {
                $ticket = null;
                if (! empty($ticketId)) {
                    $ticket = RepairTicket::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->find($ticketId);
                }

                $saleLineItems = [];
                $totalRevenue = 0;

                foreach ($items as $itemData) {
                    $productId = $itemData['product_id'];
                    $qty = (int) $itemData['quantity'];

                    $product = Product::withoutGlobalScope('company')
                        ->where('company_id', $company->id)
                        ->find($productId);

                    if (! $product) {
                        throw new \InvalidArgumentException("Part with ID {$productId} not found.");
                    }

                    $product->decrementStock($qty, 'Repair Counter POS sale');

                    $unitPrice = isset($itemData['unit_price']) && (float) $itemData['unit_price'] > 0
                        ? (float) $itemData['unit_price']
                        : (float) $product->sale_price;

                    $lineTotal = round($qty * $unitPrice, 2);
                    $totalRevenue += $lineTotal;

                    $saleLineItems[] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'price' => $unitPrice,
                        'total' => $lineTotal,
                    ];

                    if ($ticket) {
                        RepairTicketPart::create([
                            'company_id' => $company->id,
                            'tenant_id' => $company->id,
                            'repair_ticket_id' => $ticket->id,
                            'product_id' => $product->id,
                            'part_name' => $product->name,
                            'quantity' => $qty,
                            'unit_cost' => (float) $product->cost_price,
                            'unit_price' => $unitPrice,
                            'subtotal' => $lineTotal,
                            'billed_to_customer' => true,
                        ]);
                    }
                }

                if ($ticket) {
                    $newPartsCost = (float) RepairTicketPart::where('repair_ticket_id', $ticket->id)
                        ->where('billed_to_customer', true)
                        ->sum('subtotal');

                    $ticket->update([
                        'parts_cost' => $newPartsCost,
                        'total_amount' => round($newPartsCost + (float) $ticket->labor_fee, 2),
                    ]);

                    // Issue one complete delivery receipt containing every
                    // billed part and the labor line, not only this cart's
                    // newly added parts.
                    $ticket->refresh()->load('parts');
                    $saleLineItems = $ticket->parts
                        ->where('billed_to_customer', true)
                        ->map(fn (RepairTicketPart $part) => [
                            'product_id' => $part->product_id,
                            'name' => "Part: {$part->part_name}",
                            'quantity' => (int) $part->quantity,
                            'unit_price' => (float) $part->unit_price,
                            'price' => (float) $part->unit_price,
                            'total' => (float) $part->subtotal,
                        ])
                        ->values()
                        ->all();
                    if ((float) $ticket->labor_fee > 0) {
                        $saleLineItems[] = [
                            'product_id' => null,
                            'name' => "Labor / Service Charge ({$ticket->device_type})",
                            'quantity' => 1,
                            'unit_price' => (float) $ticket->labor_fee,
                            'price' => (float) $ticket->labor_fee,
                            'total' => (float) $ticket->labor_fee,
                        ];
                    }
                    $totalRevenue = (float) $ticket->total_amount;
                }

                $discount = (float) $request->input('discount', 0);
                $taxTotals = app(TaxCalculationService::class)->calculateCartTotals($saleLineItems, $company, null, $discount);
                $saleLineItems = $taxTotals['items'];
                $discount = (float) $taxTotals['discount'];
                $netAmount = (float) $taxTotals['total'];
                $totalRevenue = (float) $taxTotals['total'];
                $paymentMethod = strtolower((string) $request->input('payment_method', $request->input('selected_payment_method', 'cash')));
                $advanceApplied = $ticket ? min((float) $ticket->advance_paid, $netAmount) : 0.0;
                $balanceToCollect = max(0, round($netAmount - $advanceApplied, 2));
                $payments = $request->input('payments');
                $collectedNow = $paymentMethod === 'credit'
                    ? 0.0
                    : (! empty($payments) && is_array($payments)
                        ? min($balanceToCollect, collect($payments)->sum(fn ($payment) => max(0, (float) ($payment['amount'] ?? 0))))
                        : $balanceToCollect);
                $paidAmount = min($netAmount, $advanceApplied + $collectedNow);
                $dueAmount = max(0, round($netAmount - $paidAmount, 2));
                $paymentStatus = $dueAmount <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending');
                $cashRegister = CashRegister::openFor($company->id);

                $prefix = $company->invoice_prefix ?: 'INV-';
                $saleCount = Sale::withoutGlobalScope('company')->where('company_id', $company->id)->count() + 1;
                $saleNumber = $prefix.sprintf('%04d', $saleCount);

                $sale = Sale::create([
                    'company_id' => $company->id,
                    'sale_number' => $saleNumber,
                    'customer_id' => $request->input('customer_id'),
                    'customer_name' => $request->input('customer_name') ?: ($ticket->customer_name ?? 'Walk-in Customer'),
                    'user_id' => $user?->id,
                    'cash_register_id' => $cashRegister?->id,
                    'total' => $totalRevenue,
                    'discount' => $discount,
                    'net_amount' => $netAmount,
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'status' => 'completed',
                    'operation_type' => 'sale',
                    'items' => $saleLineItems,
                    'tax_amount' => $taxTotals['tax_amount'],
                    'tax_name' => $company->tax_id_label ?: 'Tax',
                    'tax_breakdown' => $taxTotals['tax_summary_table'],
                    'notes' => $ticket
                        ? "Repair counter sale linked to Ticket #{$ticket->ticket_number}"
                        : ($request->input('notes') ?? 'Repair Counter POS Sale'),
                ]);

                if ($advanceApplied > 0) {
                    OrderPayment::create([
                        'company_id' => $company->id,
                        'sale_id' => $sale->id,
                        'cash_register_id' => $cashRegister?->id,
                        'payment_method' => 'advance_deposit',
                        'amount' => $advanceApplied,
                        'net_amount' => $advanceApplied,
                        'notes' => "Advance previously received for Repair Ticket #{$ticket->ticket_number}",
                    ]);
                }
                if (! empty($payments) && is_array($payments)) {
                    foreach ($payments as $pay) {
                        $amt = (float) ($pay['amount'] ?? 0);
                        if ($amt > 0) {
                            OrderPayment::create([
                                'company_id' => $company->id,
                                'sale_id' => $sale->id,
                                'cash_register_id' => $cashRegister?->id,
                                'payment_method' => strtolower((string) ($pay['method'] ?? 'cash')),
                                'amount' => $amt,
                                'net_amount' => $amt,
                            ]);
                        }
                    }
                } elseif ($paymentMethod !== 'credit' && $collectedNow > 0) {
                    $tendered = $request->filled('tendered')
                        ? (float) $request->input('tendered')
                        : (float) $request->input('quick_cash_tendered', $request->input('selected_tendered', $collectedNow));
                    OrderPayment::create([
                        'company_id' => $company->id,
                        'sale_id' => $sale->id,
                        'cash_register_id' => $cashRegister?->id,
                        'payment_method' => $paymentMethod,
                        'amount' => $collectedNow,
                        'tendered' => $paymentMethod === 'cash' ? max($collectedNow, $tendered) : null,
                        'change_returned' => $paymentMethod === 'cash' ? max(0, $tendered - $collectedNow) : 0,
                        'net_amount' => $collectedNow,
                    ]);
                }

                if ($ticket) {
                    $ticket->update([
                        'status' => 'delivered',
                        'completed_at' => $ticket->completed_at ?? now(),
                        'delivered_at' => now(),
                        'final_sale_id' => $sale->id,
                    ]);
                }

                return $sale;
            });

            AuditLog::record('repair.pos_sale_completed', $company->id, $user?->id, [
                'sale_id' => $sale->id,
                'total' => (float) $sale->total,
                'ticket_id' => $ticketId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Repair counter sale completed successfully',
                'sale' => $sale,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Checkout failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeCartPreview(Request $request): array
    {
        $raw = $request->query('cart');
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * The checkout sheet ships two fixed optional split-payment rows
     * rather than a dynamic list (see PharmacyApiController for the same
     * pattern) — normalize them into the `payments[]` array posCheckout()
     * understands, when a split is actually being used.
     */
    private function normalizeFixedSplitPayments(Request $request): void
    {
        if ($request->filled('payments')) {
            return;
        }

        $rows = [];
        foreach ([1, 2] as $i) {
            $amount = (float) $request->input("payment_{$i}_amount", 0);
            if ($amount > 0) {
                $rows[] = [
                    'method' => (string) $request->input("payment_{$i}_method", 'cash'),
                    'amount' => $amount,
                ];
            }
        }

        if ($rows !== []) {
            $request->merge(['payments' => $rows]);
        }
    }
}
