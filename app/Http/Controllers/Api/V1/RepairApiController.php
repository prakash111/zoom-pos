<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\RepairDeviceCategory;
use App\Models\RepairTicket;
use App\Models\RepairTicketItem;
use App\Models\Sale;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use App\Services\Invoice\InvoiceDeliveryService;
use App\Services\Repair\RepairNotificationService;
use App\Services\Sdui\PosScreenBuilder;
use App\Services\Sdui\SchemaValidator;
use App\Services\Sdui\UniversalPosBuilder;
use App\Services\TaxCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RepairApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function __construct(
        protected PermissionChecker $permissionChecker,
        protected RepairNotificationService $notificationService,
        protected InvoiceDeliveryService $invoiceDeliveryService,
    ) {}

    /**
     * Enforce granular RBAC on repair module actions.
     */
    protected function authorizeAction(Request $request, string $action, ?RepairTicket $ticket = null): User
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        if (! $user) {
            abort(response()->json(['success' => false, 'error' => 'Unauthenticated.'], 401));
        }

        if (! $this->permissionChecker->allows($user, 'repair', $action)) {
            abort(response()->json([
                'success' => false,
                'error' => "Forbidden: Your role ({$user->role}) cannot {$action} repair tickets.",
            ], 403));
        }

        // Technician assignment restriction: If role is technician and ticket is assigned to someone else
        if ($ticket && $user->role === User::ROLE_TECHNICIAN) {
            if ($ticket->assigned_technician_id && (string) $ticket->assigned_technician_id !== (string) $user->id) {
                if (in_array($action, ['diagnose', 'checkout', 'delete'], true)) {
                    abort(response()->json([
                        'success' => false,
                        'error' => 'Forbidden: This repair ticket is assigned to another technician.',
                    ], 403));
                }
            }
        }

        return $user;
    }

    /**
     * Kanban counts and financial metrics for repair workshop.
     * GET /api/tenant/repair/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'view');
        $company = $this->resolveCompany($request);

        $tickets = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['items'])
            ->get();

        $stats = [
            'received' => $tickets->where('status', RepairTicket::STATUS_RECEIVED)->count(),
            'diagnosing' => $tickets->where('status', RepairTicket::STATUS_DIAGNOSING)->count(),
            'waiting_parts' => $tickets->where('status', RepairTicket::STATUS_WAITING_PARTS)->count(),
            'in_progress' => $tickets->where('status', RepairTicket::STATUS_IN_PROGRESS)->count(),
            'ready' => $tickets->whereIn('status', [RepairTicket::STATUS_READY, 'repaired'])->count(),
            'delivered' => $tickets->where('status', RepairTicket::STATUS_DELIVERED)->count(),
            'cancelled' => $tickets->where('status', RepairTicket::STATUS_CANCELLED)->count(),
            'total' => $tickets->count(),
            'total_revenue' => round((float) $tickets->whereIn('status', [RepairTicket::STATUS_READY, 'repaired', RepairTicket::STATUS_DELIVERED])->sum('total_amount'), 2),
            'pending_receivables' => round((float) $tickets->whereNotIn('status', [RepairTicket::STATUS_DELIVERED, RepairTicket::STATUS_CANCELLED])->sum('balance_due'), 2),
        ];

        // Legacy compatibility keys
        $stats['active'] = $stats['received'];
        $stats['repaired'] = $stats['ready'];

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * List all dynamic device categories (mapped to core categories table).
     * GET /api/tenant/repair/categories
     */
    public function categoriesIndex(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'view');
        $company = $this->resolveCompany($request);

        $hasSort = Schema::hasColumn('categories', 'sort_order');
        $categories = Category::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('active', true)
            ->where(function ($q) {
                $q->where('type', 'device')->orWhereNull('type');
            })
            ->when($hasSort, fn ($q) => $q->orderBy('sort_order')->orderBy('name'), fn ($q) => $q->orderBy('name'))
            ->get();

        if ($categories->isEmpty()) {
            foreach (RepairDeviceCategory::defaultPresets() as $preset) {
                Category::firstOrCreate([
                    'company_id' => $company->id,
                    'name' => $preset['name'],
                ], array_filter([
                    'tenant_id' => $company->id,
                    'type' => 'device',
                    'icon' => $preset['icon'] ?? 'devices',
                    'description' => $preset['description'] ?? null,
                    'metadata' => [
                        'identifier_type' => $preset['identifier_type'] ?? 'Serial / IMEI',
                        'brands' => $preset['brands'] ?? [],
                        'checklist_items' => $preset['checklist_items'] ?? [],
                        'common_issues' => $preset['common_issues'] ?? [],
                    ],
                    'sort_order' => $hasSort ? ($preset['sort_order'] ?? 0) : null,
                    'active' => true,
                    'is_demo' => false,
                ], fn ($val) => $val !== null));
            }

            $categories = Category::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('active', true)
                ->where(function ($q) {
                    $q->where('type', 'device')->orWhereNull('type');
                })
                ->when($hasSort, fn ($q) => $q->orderBy('sort_order')->orderBy('name'), fn ($q) => $q->orderBy('name'))
                ->get();
        }

        return response()->json([
            'success' => true,
            'count' => $categories->count(),
            'categories' => $categories,
        ]);
    }

    /**
     * Create a new custom device repair category under core categories table.
     * POST /api/tenant/repair/categories
     */
    public function categoriesStore(Request $request): JsonResponse
    {
        $user = $this->authorizeAction($request, 'create');
        $company = $this->resolveCompany($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50',
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

        $parseList = function ($val, $fallback = []) {
            if (is_array($val)) {
                return array_values(array_filter(array_map('trim', $val)));
            }
            if (is_string($val) && trim($val) !== '') {
                return array_values(array_filter(array_map('trim', explode(',', $val))));
            }

            return $fallback;
        };

        $name = trim($request->input('name'));
        $brands = $parseList($request->input('brands'), ['Generic', 'OEM', 'Other']);
        $checklistItems = $parseList($request->input('checklist_items'), ['Power On / Boot', 'Physical Housing Condition', 'Component Functionality']);
        $commonIssues = $parseList($request->input('common_issues'), []);

        $maxSort = Schema::hasColumn('categories', 'sort_order')
            ? (Category::where('company_id', $company->id)->max('sort_order') + 1)
            : null;

        $categoryData = [
            'company_id' => $company->id,
            'tenant_id' => $company->id,
            'name' => $name,
            'code' => $request->input('code'),
            'slug' => Str::slug($name),
            'type' => 'device',
            'icon' => $request->input('icon') ?: 'devices',
            'description' => $request->input('description'),
            'metadata' => [
                'identifier_type' => $request->input('identifier_type') ?: 'Serial / IMEI',
                'brands' => $brands,
                'checklist_items' => $checklistItems,
                'common_issues' => $commonIssues,
            ],
            'active' => true,
            'is_demo' => false,
        ];

        if ($maxSort !== null) {
            $categoryData['sort_order'] = (int) $maxSort;
        }

        $category = Category::create($categoryData);

        AuditLog::record('repair.category_created', $company->id, $user->id, [
            'category_id' => $category->id,
            'name' => $category->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device category created successfully.',
            'category' => $category,
            'data' => $category,
        ], 201);
    }

    /**
     * Update custom device category specifications.
     * PUT/POST /api/tenant/repair/categories/{id}
     */
    public function categoriesUpdate(Request $request, string $id): JsonResponse
    {
        $user = $this->authorizeAction($request, 'diagnose');
        $company = $this->resolveCompany($request);

        $category = Category::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->find($id);

        if (! $category) {
            return response()->json(['success' => false, 'error' => 'Device category not found.'], 404);
        }

        $parseList = function ($val, $fallback) {
            if (is_array($val)) {
                return array_values(array_filter(array_map('trim', $val)));
            }
            if (is_string($val) && trim($val) !== '') {
                return array_values(array_filter(array_map('trim', explode(',', $val))));
            }

            return $fallback;
        };

        $meta = $category->metadata ?? [];
        if ($request->filled('name')) {
            $category->name = trim($request->input('name'));
            $category->slug = Str::slug($category->name);
        }
        if ($request->has('icon')) {
            $category->icon = $request->input('icon') ?: 'devices';
        }
        if ($request->has('description')) {
            $category->description = $request->input('description');
        }
        if ($request->has('is_active') || $request->has('active')) {
            $category->active = $request->boolean('is_active') || $request->boolean('active');
        }
        if ($request->has('code')) {
            $category->code = $request->input('code');
        }
        if ($request->has('sort_order') && Schema::hasColumn('categories', 'sort_order')) {
            $category->sort_order = (int) $request->input('sort_order');
        }

        if ($request->has('identifier_type')) {
            $meta['identifier_type'] = $request->input('identifier_type') ?: 'Serial / IMEI';
        }
        if ($request->has('brands')) {
            $meta['brands'] = $parseList($request->input('brands'), $meta['brands'] ?? []);
        }
        if ($request->has('checklist_items')) {
            $meta['checklist_items'] = $parseList($request->input('checklist_items'), $meta['checklist_items'] ?? []);
        }
        if ($request->has('common_issues')) {
            $meta['common_issues'] = $parseList($request->input('common_issues'), $meta['common_issues'] ?? []);
        }

        $category->metadata = $meta;
        $category->save();

        AuditLog::record('repair.category_updated', $company->id, $user->id, [
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
        $user = $this->authorizeAction($request, 'delete');
        $company = $this->resolveCompany($request);

        $category = Category::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->find($id);

        if (! $category) {
            return response()->json(['success' => false, 'error' => 'Device category not found.'], 404);
        }

        $category->delete();

        AuditLog::record('repair.category_deleted', $company->id, $user->id, [
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
        $user = $this->authorizeAction($request, 'view');
        $company = $this->resolveCompany($request);

        $query = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['items.product', 'customer', 'category', 'technician', 'advanceSale', 'finalSale'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $status = strtolower(trim((string) $request->input('status')));
            if ($status === 'active') {
                $status = RepairTicket::STATUS_RECEIVED;
            } elseif ($status === 'repaired') {
                $status = RepairTicket::STATUS_READY;
            }
            $query->where('status', $status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', strtolower(trim((string) $request->input('priority'))));
        }

        if ($request->filled('technician_id')) {
            $query->where('assigned_technician_id', $request->input('technician_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('ticket_number', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_phone', 'like', "%{$term}%")
                    ->orWhere('serial_number_or_imei', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhere('model', 'like', "%{$term}%")
                    ->orWhere('problem_reported', 'like', "%{$term}%");
            });
        }

        // If user is a technician and requested only their tickets or restricted
        if ($user->role === User::ROLE_TECHNICIAN && ($request->boolean('my_jobs') || $request->boolean('technician_only'))) {
            $query->where('assigned_technician_id', $user->id);
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 25)));
        $tickets = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'tickets' => $tickets->items(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Intake: Create new repair ticket, accept advance deposit, deduct initial parts,
     * and dispatch multi-channel customer receipts.
     * POST /api/tenant/repair/tickets
     */
    public function ticketsStore(Request $request): JsonResponse
    {
        $user = $this->authorizeAction($request, 'create');
        $company = $this->resolveCompany($request);

        $validator = Validator::make($request->all(), [
            'customer_name' => 'required_without:customer_id|nullable|string|max:150',
            'customer_phone' => 'nullable|string|max:50',
            'customer_id' => 'nullable',
            'category_id' => 'nullable|integer',
            'device_category_id' => 'nullable|integer',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number_or_imei' => 'nullable|string|max:100',
            'serial_or_imei' => 'nullable|string|max:100',
            'passcode_pattern' => 'nullable|string|max:100',
            'passcode_or_pattern' => 'nullable|string|max:100',
            'problem_reported' => 'nullable|string',
            'issue_description' => 'nullable|string',
            'physical_condition_notes' => 'nullable|string',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'technician_id' => 'nullable|string',
            'assigned_technician_id' => 'nullable|string',
            'estimated_cost' => 'nullable|numeric|min:0',
            'advance_deposit' => 'nullable|numeric|min:0',
            'advance_paid' => 'nullable|numeric|min:0',
            'advance_payment_method' => 'nullable|string',
            'expected_delivery_at' => 'nullable|date',
            'inspection_checklist' => 'nullable|array',
            'items' => 'nullable|array',
            'parts' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $problem = trim((string) ($request->input('problem_reported') ?: $request->input('issue_description') ?: 'Hardware / Software diagnostic required'));
        $serial = trim((string) ($request->input('serial_number_or_imei') ?: $request->input('serial_or_imei') ?: ''));
        $passcode = trim((string) ($request->input('passcode_pattern') ?: $request->input('passcode_or_pattern') ?: ''));
        $categoryId = $request->input('category_id') ?: $request->input('device_category_id');
        $technicianId = $request->input('assigned_technician_id') ?: $request->input('technician_id');
        $advanceDeposit = (float) ($request->input('advance_deposit') ?? $request->input('advance_paid') ?? 0.00);
        $advanceMethod = $request->input('advance_payment_method') ?: ($advanceDeposit > 0 ? 'cash' : null);

        // Resolve or create Customer in core CRM table
        $customerId = $request->input('customer_id');
        $customerName = trim((string) $request->input('customer_name'));
        $customerPhone = trim((string) $request->input('customer_phone'));

        if ($customerId) {
            $existingCustomer = Customer::where('company_id', $company->id)
                ->where(fn ($q) => $q->where('id', $customerId)->orWhere('external_id', $customerId))
                ->first();
            if ($existingCustomer) {
                $customerId = $existingCustomer->id;
                if ($customerName === '') {
                    $customerName = $existingCustomer->name;
                }
                if ($customerPhone === '') {
                    $customerPhone = (string) ($existingCustomer->phone ?? '');
                }
            }
        } elseif ($customerName !== '') {
            $existingCustomer = Customer::where('company_id', $company->id)
                ->where(function ($q) use ($customerName, $customerPhone) {
                    if ($customerPhone !== '') {
                        $q->where('phone', $customerPhone);
                    } else {
                        $q->where('name', $customerName);
                    }
                })->first();

            if ($existingCustomer) {
                $customerId = $existingCustomer->id;
            } else {
                $createdCust = Customer::create([
                    'company_id' => $company->id,
                    'tenant_id' => $company->id,
                    'name' => $customerName,
                    'phone' => $customerPhone ?: null,
                    'is_demo' => false,
                ]);
                $customerId = $createdCust->id;
            }
        }

        // Generate unique sequential ticket number: REP-YYYY-XXXX
        $year = date('Y');
        $lastTicket = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('ticket_number', 'like', "REP-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $nextNum = 1;
        if ($lastTicket && preg_match('/REP-\d{4}-(\d+)/', $lastTicket->ticket_number, $matches)) {
            $nextNum = (int) $matches[1] + 1;
        }
        $ticketNumber = sprintf('REP-%s-%04d', $year, $nextNum);

        return DB::transaction(function () use (
            $company, $user, $request, $ticketNumber, $customerId, $customerName, $customerPhone,
            $categoryId, $technicianId, $serial, $passcode, $problem, $advanceDeposit, $advanceMethod
        ) {
            $checklist = $request->input('inspection_checklist');
            if (empty($checklist) && $categoryId) {
                $category = Category::withoutGlobalScopes()->find($categoryId);
                if ($category) {
                    $points = $category->checklist_points;
                    if (!empty($points)) {
                        $checklist = array_map(function ($point) {
                            return [
                                'item_name' => is_array($point) ? ($point['item_name'] ?? $point['name'] ?? '') : (string) $point,
                                'status' => 'pending',
                                'notes' => null,
                            ];
                        }, $points);
                    }
                }
            }

            $ticket = RepairTicket::create([
                'company_id' => $company->id,
                'tenant_id' => $company->id,
                'ticket_number' => $ticketNumber,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone ?: null,
                'category_id' => $categoryId,
                'brand' => $request->input('brand'),
                'model' => $request->input('model'),
                'serial_number_or_imei' => $serial ?: null,
                'passcode_pattern' => $passcode ?: null,
                'problem_reported' => $problem,
                'technician_diagnosis' => $request->input('technician_diagnosis'),
                'physical_condition_notes' => $request->input('physical_condition_notes'),
                'assigned_technician_id' => $technicianId ?: null,
                'status' => RepairTicket::STATUS_RECEIVED,
                'priority' => $request->input('priority') ?: RepairTicket::PRIORITY_NORMAL,
                'estimated_cost' => (float) ($request->input('estimated_cost') ?? 0.00),
                'advance_deposit' => $advanceDeposit,
                'advance_payment_method' => $advanceMethod,
                'inspection_checklist' => $checklist,
                'expected_delivery_at' => $request->input('expected_delivery_at'),
                'intake_at' => now(),
                'is_demo' => false,
            ]);

            // If advance deposit was paid, create an initial sale transaction / drawer record
            if ($advanceDeposit > 0) {
                $saleNumber = 'ADV-'.$ticket->ticket_number;
                $advanceSale = Sale::create([
                    'company_id' => $company->id,
                    'tenant_id' => $company->id,
                    'sale_number' => $saleNumber,
                    'invoice_number' => $saleNumber,
                    'customer_id' => $customerId,
                    'user_id' => $user->id,
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'payment_method' => $advanceMethod ?: 'cash',
                    'subtotal' => $advanceDeposit,
                    'total' => $advanceDeposit,
                    'net_amount' => $advanceDeposit,
                    'paid_amount' => $advanceDeposit,
                    'notes' => "Advance Deposit for Repair Ticket #{$ticket->ticket_number}",
                ]);

                $ticket->update(['advance_sale_id' => $advanceSale->id]);

                // Register drawer payment
                $register = CashRegister::where('company_id', $company->id)->where('status', 'open')->first();
                OrderPayment::create([
                    'company_id' => $company->id,
                    'tenant_id' => $company->id,
                    'sale_id' => $advanceSale->id,
                    'cash_register_id' => $register?->id,
                    'user_id' => $user->id,
                    'amount' => $advanceDeposit,
                    'payment_method' => $advanceMethod ?: 'cash',
                    'status' => 'completed',
                    'notes' => "Advance Deposit: Ticket #{$ticket->ticket_number}",
                ]);
            }

            // Attach initial spare parts or labor lines & deduct central stock
            $itemsList = $request->input('items') ?? $request->input('parts') ?? [];
            foreach ($itemsList as $itemRow) {
                $qty = max(0.01, (float) ($itemRow['quantity'] ?? 1));
                $price = max(0.0, (float) ($itemRow['unit_price'] ?? $itemRow['price'] ?? 0));
                $subtotal = round($qty * $price, 2);
                $productId = ! empty($itemRow['product_id']) ? (int) $itemRow['product_id'] : null;
                $type = ($itemRow['item_type'] ?? '') === 'service_labor' ? 'service_labor' : 'spare_part';

                RepairTicketItem::create([
                    'company_id' => $company->id,
                    'tenant_id' => $company->id,
                    'ticket_id' => $ticket->id,
                    'product_id' => $productId,
                    'item_name' => trim((string) ($itemRow['item_name'] ?? $itemRow['part_name'] ?? 'Part / Labor')),
                    'item_type' => $type,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $subtotal,
                    'tax_amount' => 0.00,
                    'total' => $subtotal,
                    'billed_to_customer' => true,
                ]);

                // Deduct stock for spare parts from core products
                if ($productId && $type === 'spare_part') {
                    $product = Product::where('company_id', $company->id)->find($productId);
                    if ($product) {
                        $product->decrement('current_stock', $qty);
                    }
                }
            }

            // Dispatch customer SMS, WhatsApp, and tracking links
            $dispatchResults = $this->notificationService->notifyTicketCreated($ticket);

            AuditLog::record('repair.ticket_created', $company->id, $user->id, [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'customer_name' => $customerName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Repair ticket created successfully.',
                'ticket' => $ticket->fresh(['items.product', 'customer', 'category', 'technician']),
                'tracking_url' => $dispatchResults['tracking_url'],
                'whatsapp_url' => $dispatchResults['whatsapp_url'],
                'sms_text' => $dispatchResults['sms_text'],
                'intake_sheet_url' => $dispatchResults['intake_sheet_url'],
            ], 200);
        });
    }

    /**
     * View detailed ticket info with parts, labor, customer, and payments.
     * GET /api/tenant/repair/tickets/{id}
     */
    public function ticketsShow(Request $request, string $id): JsonResponse
    {
        $this->authorizeAction($request, 'view');
        $company = $this->resolveCompany($request);

        $ticket = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['items.product', 'customer', 'category', 'technician', 'advanceSale', 'finalSale'])
            ->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'ticket' => $ticket,
        ]);
    }

    /**
     * Update ticket lifecycle status.
     * Moving to 'ready' fires instant Push + WhatsApp/SMS with balance due.
     * Moving to 'cancelled' restores stock for spare parts.
     * POST /api/tenant/repair/tickets/{id}/status
     */
    public function ticketsUpdateStatus(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $ticket = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        $user = $this->authorizeAction($request, 'diagnose', $ticket);

        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
            'notes' => 'nullable|string',
            'technician_diagnosis' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $newStatus = strtolower(trim((string) $request->input('status')));
        if ($newStatus === 'active') {
            $newStatus = RepairTicket::STATUS_RECEIVED;
        } elseif ($newStatus === 'repaired') {
            $newStatus = RepairTicket::STATUS_READY;
        }

        $oldStatus = $ticket->status;
        $ticket->status = $newStatus;

        if ($request->filled('technician_diagnosis')) {
            $ticket->technician_diagnosis = $request->input('technician_diagnosis');
        }

        if ($newStatus === RepairTicket::STATUS_READY) {
            $ticket->completed_at = now();
        } elseif ($newStatus === RepairTicket::STATUS_DELIVERED) {
            $ticket->delivered_at = now();
        } elseif ($newStatus === RepairTicket::STATUS_CANCELLED) {
            // Restore inventory stock for assigned parts
            foreach ($ticket->items()->where('item_type', 'spare_part')->get() as $item) {
                if ($item->product_id) {
                    $product = Product::where('company_id', $company->id)->find($item->product_id);
                    if ($product) {
                        $product->increment('current_stock', (float) $item->quantity);
                    }
                }
            }
        }

        $ticket->save();

        // Multi-channel alert when moving to Ready for Pickup
        $notificationResult = null;
        if ($newStatus === RepairTicket::STATUS_READY && $oldStatus !== RepairTicket::STATUS_READY) {
            $notificationResult = $this->notificationService->notifyStatusReadyForPickup($ticket);
        }

        AuditLog::record('repair.status_updated', $company->id, $user->id, [
            'ticket_id' => $ticket->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ticket status updated to '{$newStatus}'.",
            'ticket' => $ticket->fresh(['items.product', 'customer', 'category', 'technician']),
            'notifications' => $notificationResult,
        ]);
    }

    /**
     * Assign ticket to a technician and trigger internal app push notification.
     * POST /api/tenant/repair/tickets/{id}/assign
     */
    public function ticketsAssign(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $ticket = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        $user = $this->authorizeAction($request, 'assign', $ticket);

        $validator = Validator::make($request->all(), [
            'technician_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $technician = User::where('company_id', $company->id)->find($request->input('technician_id'));
        if (! $technician) {
            return response()->json(['success' => false, 'error' => 'Technician user not found.'], 404);
        }

        $ticket->update(['assigned_technician_id' => $technician->id]);

        // Send high-priority internal push notification to technician device
        $pushed = $this->notificationService->notifyTechnicianAssigned($ticket, $technician);

        AuditLog::record('repair.technician_assigned', $company->id, $user->id, [
            'ticket_id' => $ticket->id,
            'technician_id' => $technician->id,
            'technician_name' => $technician->name,
            'push_delivered' => $pushed,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ticket assigned to {$technician->name}.",
            'ticket' => $ticket->fresh(['technician']),
            'push_delivered' => $pushed,
        ]);
    }

    /**
     * Add spare part item to ticket and decrement core product stock.
     * POST /api/tenant/repair/tickets/{id}/parts
     */
    public function ticketsAddPart(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $ticket = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        $user = $this->authorizeAction($request, 'diagnose', $ticket);

        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|integer',
            'part_name' => 'nullable|string|max:200',
            'item_name' => 'nullable|string|max:200',
            'quantity' => 'nullable|numeric|min:0.01',
            'unit_price' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'billed_to_customer' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $productId = $request->input('product_id');
        $product = $productId ? Product::where('company_id', $company->id)->find($productId) : null;

        $name = trim((string) ($request->input('item_name') ?: $request->input('part_name') ?: ($product?->name ?? 'Spare Part')));
        $quantity = max(0.01, (float) ($request->input('quantity') ?? 1));
        $unitPrice = max(0.0, (float) ($request->input('unit_price') ?? $request->input('price') ?? ($product?->sale_price ?? 0)));
        $subtotal = round($quantity * $unitPrice, 2);

        $item = DB::transaction(function () use ($company, $ticket, $product, $name, $quantity, $unitPrice, $subtotal, $request) {
            $created = RepairTicketItem::create([
                'company_id' => $company->id,
                'tenant_id' => $company->id,
                'ticket_id' => $ticket->id,
                'product_id' => $product?->id,
                'item_name' => $name,
                'item_type' => 'spare_part',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'tax_amount' => 0.00,
                'total' => $subtotal,
                'billed_to_customer' => $request->boolean('billed_to_customer', true),
            ]);

            // Deduct stock from central inventory
            if ($product) {
                $product->decrement('current_stock', $quantity);
            }

            return $created;
        });

        AuditLog::record('repair.part_added', $company->id, $user->id, [
            'ticket_id' => $ticket->id,
            'item_id' => $item->id,
            'part_name' => $name,
            'quantity' => $quantity,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Spare part added to ticket.',
            'item' => $item,
            'ticket' => $ticket->fresh(['items.product']),
        ]);
    }

    /**
     * Remove spare part item from ticket and restore inventory stock.
     * DELETE /api/tenant/repair/tickets/{ticketId}/parts/{partId}
     */
    public function ticketsRemovePart(Request $request, string $ticketId, string $partId): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $ticket = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->find($ticketId);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        $user = $this->authorizeAction($request, 'diagnose', $ticket);

        $item = RepairTicketItem::where('company_id', $company->id)
            ->where('ticket_id', $ticket->id)
            ->find($partId);

        if (! $item) {
            return response()->json(['success' => false, 'error' => 'Part item not found on this ticket.'], 404);
        }

        DB::transaction(function () use ($company, $item) {
            // Restore inventory stock
            if ($item->product_id && $item->item_type === 'spare_part') {
                $product = Product::where('company_id', $company->id)->find($item->product_id);
                if ($product) {
                    $product->increment('current_stock', (float) $item->quantity);
                }
            }

            $item->delete();
        });

        AuditLog::record('repair.part_removed', $company->id, $user->id, [
            'ticket_id' => $ticket->id,
            'item_id' => $partId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Part removed and inventory stock restored.',
            'ticket' => $ticket->fresh(['items.product']),
        ]);
    }

    /**
     * Set or update labor fee on ticket.
     * POST /api/tenant/repair/tickets/{id}/labor
     */
    public function ticketsSetLabor(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $ticket = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        $user = $this->authorizeAction($request, 'diagnose', $ticket);

        $validator = Validator::make($request->all(), [
            'labor_fee' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $fee = (float) $request->input('labor_fee');
        $desc = trim((string) ($request->input('description') ?: 'Technician Diagnostic & Repair Labor'));

        // Update existing labor line or create new
        $laborItem = $ticket->items()->where('item_type', 'service_labor')->first();
        if ($laborItem) {
            $laborItem->update([
                'item_name' => $desc,
                'quantity' => 1.00,
                'unit_price' => $fee,
                'subtotal' => $fee,
                'total' => $fee,
            ]);
        } else {
            RepairTicketItem::create([
                'company_id' => $company->id,
                'tenant_id' => $company->id,
                'ticket_id' => $ticket->id,
                'item_name' => $desc,
                'item_type' => 'service_labor',
                'quantity' => 1.00,
                'unit_price' => $fee,
                'subtotal' => $fee,
                'tax_amount' => 0.00,
                'total' => $fee,
                'billed_to_customer' => true,
            ]);
        }

        AuditLog::record('repair.labor_updated', $company->id, $user->id, [
            'ticket_id' => $ticket->id,
            'labor_fee' => $fee,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Labor charges updated successfully.',
            'ticket' => $ticket->fresh(['items']),
        ]);
    }

    /**
     * Update inspection checklist JSON on ticket.
     * POST /api/tenant/repair/tickets/{id}/checklist
     */
    public function ticketsUpdateChecklist(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $ticket = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        $user = $this->authorizeAction($request, 'diagnose', $ticket);

        $checklist = $request->input('inspection_checklist') ?? $request->input('checklist');
        if (! is_array($checklist)) {
            return response()->json(['success' => false, 'error' => 'The inspection checklist must be an array.'], 422);
        }

        $ticket->update(['inspection_checklist' => $checklist]);

        AuditLog::record('repair.checklist_updated', $company->id, $user->id, [
            'ticket_id' => $ticket->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inspection checklist updated.',
            'ticket' => $ticket->fresh(),
        ]);
    }

    /**
     * Void or delete repair ticket.
     * DELETE /api/tenant/repair/tickets/{id}
     */
    public function ticketsDestroy(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $ticket = RepairTicket::withoutGlobalScope('company')->where('company_id', $company->id)->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        $user = $this->authorizeAction($request, 'delete', $ticket);

        DB::transaction(function () use ($company, $ticket) {
            // Restore inventory for all parts
            foreach ($ticket->items()->where('item_type', 'spare_part')->get() as $item) {
                if ($item->product_id) {
                    $product = Product::where('company_id', $company->id)->find($item->product_id);
                    if ($product) {
                        $product->increment('current_stock', (float) $item->quantity);
                    }
                }
            }

            $ticket->delete();
        });

        AuditLog::record('repair.ticket_deleted', $company->id, $user->id, [
            'ticket_id' => $id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Repair ticket deleted successfully.',
        ]);
    }

    /**
     * Settle & Deliver ticket (Universal Retail Checkout flow).
     * Creates core Sale, attaches items, deducts advance deposit, registers cash drawer entry,
     * and returns thermal PDF & WhatsApp dispatch URLs.
     * POST /api/tenant/repair/tickets/{id}/settle
     */
    public function ticketsSettle(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $ticket = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['items.product', 'customer'])
            ->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        $user = $this->authorizeAction($request, 'checkout', $ticket);

        $validator = Validator::make($request->all(), [
            'payment_method' => 'nullable|string',
            'tendered' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $paymentMethod = $request->input('payment_method') ?: 'cash';
        $total = $ticket->total_amount;
        $deposit = (float) $ticket->advance_deposit;
        $balanceDue = max(0, round($total - $deposit, 2));

        return DB::transaction(function () use ($company, $user, $ticket, $total, $deposit, $balanceDue, $paymentMethod, $request) {
            // Generate Sale Number
            $prefix = $company->invoice_prefix ?: 'INV-';
            $saleNumber = $prefix.date('Ymd').'-'.str_pad((string) (Sale::where('company_id', $company->id)->count() + 1), 4, '0', STR_PAD_LEFT);

            $saleLineItems = [];
            foreach ($ticket->items as $item) {
                $saleLineItems[] = [
                    'product_id' => $item->product_id,
                    'name' => $item->item_name,
                    'quantity' => (float) $item->quantity,
                    'price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                    'total' => (float) $item->total,
                ];
            }

            $totalSaleAmount = $total > 0 ? $total : $deposit;
            $balanceDue = max(0, round($totalSaleAmount - $deposit, 2));

            // Create core Sale record
            $sale = Sale::create([
                'company_id' => $company->id,
                'tenant_id' => $company->id,
                'sale_number' => $saleNumber,
                'invoice_number' => $saleNumber,
                'customer_id' => $ticket->customer_id,
                'customer_name' => $ticket->customer_name ?: ($ticket->customer?->name ?? 'Walk-in Customer'),
                'user_id' => $user->id,
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => $paymentMethod,
                'subtotal' => $totalSaleAmount,
                'tax_amount' => 0.00,
                'discount_amount' => 0.00,
                'total' => $totalSaleAmount,
                'net_amount' => $totalSaleAmount,
                'paid_amount' => $totalSaleAmount,
                'items' => $saleLineItems,
                'notes' => "Repair Settlement #{$ticket->ticket_number} (Advance Deposit Deducted: {$company->currency_symbol}".number_format($deposit, 2).")",
            ]);

            // Register payment against cash drawer
            $register = CashRegister::where('company_id', $company->id)->where('status', 'open')->first();
            if ($deposit > 0) {
                OrderPayment::create([
                    'company_id' => $company->id,
                    'tenant_id' => $company->id,
                    'sale_id' => $sale->id,
                    'cash_register_id' => $register?->id,
                    'user_id' => $user->id,
                    'payment_method' => 'advance_deposit',
                    'amount' => $deposit,
                    'notes' => "Advance deposit previously received for Repair Ticket #{$ticket->ticket_number}",
                ]);
            }

            if ($balanceDue > 0) {
                OrderPayment::create([
                    'company_id' => $company->id,
                    'tenant_id' => $company->id,
                    'sale_id' => $sale->id,
                    'cash_register_id' => $register?->id,
                    'user_id' => $user->id,
                    'amount' => $balanceDue,
                    'payment_method' => $paymentMethod,
                    'status' => 'completed',
                    'notes' => "Settlement: Ticket #{$ticket->ticket_number}",
                ]);
            }

            // Mark ticket delivered and closed
            $ticket->update([
                'status' => RepairTicket::STATUS_DELIVERED,
                'final_sale_id' => $sale->id,
                'delivered_at' => now(),
            ]);

            // Build thermal receipt PDF and WhatsApp URLs
            $phone = $ticket->customer?->phone ?: $ticket->customer_phone;
            $cleanPhone = preg_replace('/\D+/', '', (string) $phone);
            $whatsappUrl = 'https://wa.me/'.$cleanPhone.'?text='.rawurlencode(
                "Hello ".($ticket->customer?->name ?: 'Customer').", thank you for your business! Your device ({$ticket->brand} {$ticket->model}) has been delivered. Invoice #{$sale->invoice_number} Total: {$company->currency_symbol}".number_format($totalSaleAmount, 2)
            );
            $invoiceUrl = "/tenant/sales/{$sale->id}/invoice";
            $thermalPrintUrl = "/tenant/sales/{$sale->id}/receipt/print";
            $pdfUrl = url("/api/tenant/sales/{$sale->id}/receipt-pdf");

            AuditLog::record('repair.ticket_settled', $company->id, $user->id, [
                'ticket_id' => $ticket->id,
                'sale_id' => $sale->id,
                'balance_settled' => $balanceDue,
                'payment_method' => $paymentMethod,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket settled and delivered successfully.',
                'ticket' => $ticket->fresh(['finalSale']),
                'sale' => $sale->fresh(),
                'whatsapp_url' => $whatsappUrl,
                'invoice_url' => $invoiceUrl,
                'thermal_print_url' => $thermalPrintUrl,
                'pdf_url' => $pdfUrl,
                'sms_text' => "Invoice #{$sale->invoice_number} paid. Total: {$company->currency_symbol}".number_format($totalSaleAmount, 2),
            ]);
        });
    }

    /**
     * Universal POS Remote Checkout Drawer for Ticket Settlement.
     * GET /api/tenant/repair/tickets/{id}/checkout-sheet
     */
    public function ticketCheckoutSheet(Request $request, string $id): JsonResponse
    {
        $this->authorizeAction($request, 'view');
        $company = $this->resolveCompany($request);

        $ticket = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['items.product', 'customer'])
            ->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        $items = [];
        foreach ($ticket->items as $item) {
            $items[] = [
                'id' => $item->product_id,
                'title' => $item->item_name,
                'price' => (float) $item->unit_price,
                'quantity' => (float) $item->quantity,
                'qty' => (float) $item->quantity,
            ];
        }

        $schema = UniversalPosBuilder::checkoutSheet(
            company: $company,
            formSubmitEndpoint: "/api/tenant/repair/tickets/{$ticket->id}/settle",
            cartPreview: $items,
            ticketFieldLabel: 'Repair Ticket',
            customerFieldLabel: 'Customer Name',
            module: 'repair',
            selectedTicketId: $ticket->id,
            previewIncludesTicket: true,
            defaultCustomerName: $ticket->customer_name ?: $ticket->customer?->name,
        );

        return response()->json(['success' => true, 'schema' => $schema]);
    }

    /**
     * Standalone Checkout Sheet for Repair POS.
     * GET /api/tenant/repair/checkout-sheet
     */
    public function checkoutSheet(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'view');
        $company = $this->resolveCompany($request);

        $rawItems = (array) $request->input('items', []);
        $cartPreview = [];

        foreach ($rawItems as $raw) {
            $cartPreview[] = [
                'id' => $raw['product_id'] ?? null,
                'title' => (string) ($raw['name'] ?? $raw['title'] ?? 'Spare Part / Service'),
                'price' => (float) ($raw['unit_price'] ?? $raw['price'] ?? 0),
                'quantity' => (float) ($raw['quantity'] ?? $raw['qty'] ?? 1),
                'qty' => (float) ($raw['quantity'] ?? $raw['qty'] ?? 1),
            ];
        }

        $schema = UniversalPosBuilder::checkoutSheet(
            company: $company,
            formSubmitEndpoint: '/api/tenant/repair/checkout',
            cartPreview: $cartPreview,
            ticketFieldLabel: 'Repair Ticket',
            customerFieldLabel: 'Customer Name',
            module: 'repair',
        );

        return response()->json(['success' => true, 'schema' => $schema]);
    }

    /**
     * Process POS Checkout for Repair (Universal Contract).
     * POST /api/tenant/repair/checkout or /api/tenant/repair/pos-checkout
     */
    public function posCheckout(Request $request): JsonResponse
    {
        $user = $this->authorizeAction($request, 'checkout');
        $company = $this->resolveCompany($request);

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'payment_method' => 'nullable|string',
            'customer_id' => 'nullable|integer',
            'ticket_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()->first()], 422);
        }

        $paymentMethod = $request->input('payment_method') ?: 'cash';
        $items = $request->input('items');

        return DB::transaction(function () use ($company, $user, $paymentMethod, $items, $request) {
            $prefix = $company->invoice_prefix ?: 'INV-';
            $saleNumber = $prefix.date('Ymd').'-'.str_pad((string) (Sale::where('company_id', $company->id)->count() + 1), 4, '0', STR_PAD_LEFT);

            $ticket = null;
            if ($request->filled('ticket_id')) {
                $ticket = RepairTicket::where('company_id', $company->id)->find($request->input('ticket_id'));
            }

            $subtotal = 0.0;
            $processedItems = [];
            foreach ($items as $it) {
                $qty = (float) ($it['quantity'] ?? 1);
                $productId = ! empty($it['product_id']) ? (int) $it['product_id'] : null;
                $price = (float) ($it['unit_price'] ?? $it['price'] ?? 0);
                $prod = null;
                if ($productId) {
                    $prod = Product::where('company_id', $company->id)->find($productId);
                    if ($price <= 0 && $prod) {
                        $price = (float) $prod->sale_price;
                    }
                }
                $itemSubtotal = round($qty * $price, 2);
                $subtotal += $itemSubtotal;
                $processedItems[] = [
                    'product_id' => $productId,
                    'product_name' => (string) ($it['name'] ?? $it['item_name'] ?? ($prod ? $prod->name : 'Repair Part/Labor')),
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $itemSubtotal,
                    'total' => $itemSubtotal,
                    'prod' => $prod,
                ];
            }

            if ($ticket) {
                foreach ($processedItems as $pItem) {
                    $ticket->items()->create([
                        'company_id' => $company->id,
                        'tenant_id' => $company->id,
                        'product_id' => $pItem['product_id'],
                        'item_name' => $pItem['product_name'],
                        'item_type' => RepairTicketItem::TYPE_SPARE_PART,
                        'quantity' => $pItem['quantity'],
                        'unit_price' => $pItem['unit_price'],
                        'subtotal' => $pItem['subtotal'],
                        'total' => $pItem['total'],
                        'billed_to_customer' => true,
                    ]);
                }
                $ticket->refresh();
                $deposit = (float) $ticket->advance_deposit;
                $totalToPay = max(0, round($ticket->total_amount - $deposit, 2));
            } else {
                $totalToPay = $subtotal;
            }

            $saleLineItems = [];
            foreach ($processedItems as $pItem) {
                $saleLineItems[] = [
                    'product_id' => $pItem['product_id'],
                    'name' => $pItem['product_name'],
                    'quantity' => $pItem['quantity'],
                    'price' => $pItem['unit_price'],
                    'subtotal' => $pItem['subtotal'],
                    'total' => $pItem['total'],
                ];

                if ($pItem['prod']) {
                    $pItem['prod']->decrement('current_stock', $pItem['quantity']);
                }
            }

            $sale = Sale::create([
                'company_id' => $company->id,
                'tenant_id' => $company->id,
                'sale_number' => $saleNumber,
                'invoice_number' => $saleNumber,
                'customer_id' => $request->input('customer_id') ?: ($ticket ? $ticket->customer_id : null),
                'user_id' => $user->id,
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => $paymentMethod,
                'subtotal' => $ticket ? $ticket->total_amount : $subtotal,
                'total' => $ticket ? $ticket->total_amount : $subtotal,
                'net_amount' => $totalToPay,
                'paid_amount' => $totalToPay,
                'items' => $saleLineItems,
                'notes' => $ticket ? "Repair Ticket #{$ticket->ticket_number} Settlement" : 'Repair POS Checkout',
            ]);

            // Register payment
            $register = CashRegister::where('company_id', $company->id)->where('status', 'open')->first();
            if ($totalToPay > 0) {
                OrderPayment::create([
                    'company_id' => $company->id,
                    'tenant_id' => $company->id,
                    'sale_id' => $sale->id,
                    'cash_register_id' => $register?->id,
                    'user_id' => $user->id,
                    'amount' => $totalToPay,
                    'payment_method' => $paymentMethod,
                    'status' => 'completed',
                ]);
            }

            // If a ticket was linked, link it to this sale and mark delivered
            if ($ticket) {
                $ticket->update([
                    'status' => RepairTicket::STATUS_DELIVERED,
                    'final_sale_id' => $sale->id,
                    'delivered_at' => now(),
                ]);
            }

            $pdfUrl = url("/api/tenant/sales/{$sale->id}/receipt-pdf");
            $whatsappUrl = 'https://wa.me/?text='.rawurlencode("Invoice #{$sale->invoice_number} paid. Total: {$company->currency_symbol}".number_format($subtotal, 2));
            $invoiceUrl = "/tenant/sales/{$sale->id}/invoice";
            $thermalPrintUrl = "/tenant/sales/{$sale->id}/receipt/print";

            return response()->json([
                'success' => true,
                'message' => 'Sale processed successfully.',
                'sale' => $sale->fresh(),
                'pdf_url' => $pdfUrl,
                'whatsapp_url' => $whatsappUrl,
                'invoice_url' => $invoiceUrl,
                'thermal_print_url' => $thermalPrintUrl,
                'sms_text' => "Invoice #{$sale->invoice_number} paid. Total: {$company->currency_symbol}".number_format($subtotal, 2),
            ]);
        });
    }

    /**
     * Intake Sheet Printable HTML View.
     * GET /api/tenant/repair/tickets/{id}/intake-sheet
     */
    public function ticketIntakeSheet(Request $request, string $id)
    {
        $this->authorizeAction($request, 'view');
        $company = $this->resolveCompany($request);

        $ticket = RepairTicket::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->with(['items.product', 'customer', 'category', 'technician'])
            ->find($id);

        if (! $ticket) {
            return response()->json(['success' => false, 'error' => 'Repair ticket not found.'], 404);
        }

        return response()->view('pdf.repair_intake_sheet', [
            'company' => $company,
            'ticket' => $ticket,
        ]);
    }
}
