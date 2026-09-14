<?php

namespace Modules\leadmanagement\Http\Controllers;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use App\Services\Sdui\SchemaResponse as S;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\leadmanagement\Models\Lead;
use Modules\leadmanagement\Models\LeadActivity;
use Modules\leadmanagement\Models\LeadSource;
use Modules\leadmanagement\Services\LeadService;

/**
 * Server-Driven UI + RESTful CRUD for the deeply integrated "leadmanagement" module.
 */
class LeadModuleController extends Controller
{
    use ResolvesTenantSyncContext;

    private const BASE = '/api/tenant/lead-module';

    public function __construct(
        protected LeadService $leadService,
    ) {
    }

    /**
     * Executive Overview Dashboard with Native SDUI Tabbed Layout (Store Profile standard).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $tab = $request->query('tab');

        $schema = $this->leadService->getTabbedLeadManagementSchema($company, $user, $tab);

        return response()->json(array_merge([
            'success' => true,
            'view' => 'lead-management',
            'schema' => $schema,
        ], $schema));
    }

    /**
     * Leads Pipeline SDUI View (defaults to "All Leads" tab in Tabbed layout).
     */
    public function leadsView(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $tab = $request->query('tab') ?: 'all_leads';

        $schema = $this->leadService->getTabbedLeadManagementSchema($company, $user, $tab);

        return response()->json(array_merge([
            'success' => true,
            'view' => 'lead-management',
            'schema' => $schema,
        ], $schema));
    }

    /**
     * Leads List Endpoint (`GET /api/v1/tenant/leads/list` and `/api/tenant/lead-module/leads/list`).
     */
    public function leadsListEndpoint(Request $request): JsonResponse
    {
        return $this->leadsIndex($request);
    }

    /**
     * Follow-ups & Reminders Endpoint (`GET /api/v1/tenant/leads/followups` and `/api/tenant/lead-module/leads/followups`).
     */
    public function followupsEndpoint(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        if ($user && ! PermissionChecker::can($user, 'leads', 'view') && ! PermissionChecker::can($user, 'leads', 'view_any')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized: leads.view permission required.'], 403);
        }

        $activities = LeadActivity::where('company_id', $company->id)
            ->where('status', 'pending')
            ->with('lead')
            ->orderBy('due_date')
            ->limit(50)
            ->get();

        $reminders = \App\Models\Reminder::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('status', 'pending')
            ->with('customer')
            ->orderBy('due_date')
            ->limit(50)
            ->get();

        $reminderCards = [];
        foreach ($reminders as $rem) {
            $notes = $rem->notes ?: ($rem->description ?: $rem->call_script);
            $dueAt = $rem->due_at ?: $rem->due_date;
            $dueStr = $dueAt ? \Carbon\Carbon::parse($dueAt)->format('M d, Y H:i') : 'N/A';
            $subject = $rem->subject ?: ($rem->title ?: 'Follow-up Call');

            $components = [
                [
                    'type' => 'badge',
                    'label' => 'Reminder',
                    'text' => 'Reminder',
                    'variant' => 'info',
                ],
                [
                    'type' => 'text',
                    'text' => $subject,
                    'style' => 'title_medium',
                    'variant' => 'titleMedium',
                ],
                [
                    'type' => 'text',
                    'text' => 'Due: ' . $dueStr,
                    'style' => 'body_small',
                    'variant' => 'bodySmall',
                ],
            ];

            if ($notes) {
                $components[] = [
                    'type' => 'callout',
                    'component_type' => 'callout',
                    'text' => '📝 ' . $notes,
                    'variant' => 'accent',
                    'components' => [
                        [
                            'type' => 'text',
                            'text' => '📝 ' . $notes,
                            'style' => 'body_small',
                            'variant' => 'bodySmall',
                            'bold' => true,
                        ],
                    ],
                ];
            }

            $reminderCards[] = [
                'type' => 'card',
                'components' => $components,
            ];
        }

        return response()->json([
            'success' => true,
            'activities' => $activities,
            'reminders' => $reminderCards,
            'data' => $reminderCards,
            'items' => $reminderCards,
        ]);
    }

    /**
     * Dedicated SDUI Create Lead View.
     */
    public function createLeadView(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        if ($user && ! PermissionChecker::can($user, 'leads', 'create')) {
            abort(403, 'Unauthorized: leads.create permission required.');
        }

        $existingLead = null;
        if ($id = $request->query('id')) {
            try {
                $existingLead = $this->resolveLead($id, $company);
            } catch (\Throwable $e) {
                $existingLead = null;
            }
        }

        $schema = $this->leadService->getCreateLeadSchema($company, $existingLead);

        return $this->schema($schema);
    }

    /**
     * Declarative SDUI Schema endpoint (`GET /api/v1/tenant/leads/schema`).
     */
    public function createSchema(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $schema = $this->leadService->getCreateLeadSchema($company);

        return response()->json([
            'success' => true,
            'schema' => $schema,
        ]);
    }

    /**
     * Store a newly created Lead.
     */
    public function leadsStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        if ($user && ! PermissionChecker::can($user, 'leads', 'create')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized: leads.create permission required.'], 403);
        }

        if (! $request->filled('name')) {
            $fallbackName = $request->input('contact_name') ?: $request->input('client_name') ?: $request->input('customer_name');
            if ($fallbackName) {
                $request->merge(['name' => $fallbackName]);
            }
        }
        if (! $request->filled('phone')) {
            $fallbackPhone = $request->input('phone_number') ?: $request->input('customer_phone');
            if ($fallbackPhone) {
                $request->merge(['phone' => $fallbackPhone]);
            }
        }
        if (! $request->filled('email')) {
            $fallbackEmail = $request->input('email_address') ?: $request->input('customer_email');
            if ($fallbackEmail) {
                $request->merge(['email' => $fallbackEmail]);
            }
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'title' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'customer_id' => ['nullable'],
            'source_id' => ['nullable'],
            'source' => ['nullable', 'string', 'max:100'],
            'source_name' => ['nullable', 'string', 'max:100'],
            'stage' => ['nullable', 'string', 'in:new,contacted,qualified,proposal_sent,won,lost'],
            'status' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'expected_value' => ['nullable', 'numeric', 'min:0'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'requirement_summary' => ['nullable', 'string', 'max:5000'],
            'reminder_due_date' => ['nullable', 'string'],
            'reminder_title' => ['nullable', 'string', 'max:200'],
            'reminder_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $lead = $this->leadService->createLead($data, $company, $user);

        return response()->json([
            'success' => true,
            'message' => 'Lead created and customer linked successfully.',
            'id' => $lead->id,
            'lead_code' => $lead->lead_code,
            'customer_id' => $lead->customer_id,
            'lead' => $lead->fresh(['customer', 'assignedUser', 'source']),
        ], 201);
    }

    /**
     * Safely resolve a Lead by numeric primary key OR string code (e.g., LD-TMFIAY4U / TMFIAY4U),
     * supporting withTrashed(), company scope fallback, and auto-aligning company_id.
     */
    public function resolveLead($id, ?Company $company = null): Lead
    {
        $tenantId = $company?->id ?? auth()->user()?->tenant_id ?? auth()->user()?->company_id;
        $idStr = trim((string) $id);
        $cleanId = preg_replace('/^LD[-_]?/i', '', $idStr);

        // 1. Resolve lead by numeric primary key OR string code with company constraint & withTrashed
        $lead = Lead::withTrashed()
            ->where(function ($query) use ($idStr, $cleanId) {
                if (is_numeric($idStr)) {
                    $query->where('id', (int) $idStr)->orWhere('lead_code', $idStr);
                } else {
                    $query->where('lead_code', $idStr)
                          ->orWhere('lead_code', 'LD-' . $idStr)
                          ->orWhere('lead_code', 'LD-' . $cleanId);
                    if (! empty($cleanId) && is_numeric($cleanId)) {
                        $query->orWhere('id', (int) $cleanId);
                    }
                }
            })
            ->where(function ($query) use ($tenantId) {
                if ($tenantId) {
                    $query->where('company_id', $tenantId)
                          ->orWhereNull('company_id');
                    if (\Illuminate\Support\Facades\Schema::hasColumn('lead_mod_leads', 'tenant_id')) {
                        $query->orWhere('tenant_id', $tenantId);
                    }
                }
            })
            ->first();

        // 2. Fallback search without tenant constraint if scope differed during migrations
        if (! $lead) {
            $lead = Lead::withTrashed()
                ->where(function ($query) use ($idStr, $cleanId) {
                    if (is_numeric($idStr)) {
                        $query->where('id', (int) $idStr)->orWhere('lead_code', $idStr);
                    } else {
                        $query->where('lead_code', $idStr)
                              ->orWhere('lead_code', 'LD-' . $idStr)
                              ->orWhere('lead_code', 'LD-' . $cleanId);
                        if (! empty($cleanId) && is_numeric($cleanId)) {
                            $query->orWhere('id', (int) $cleanId);
                        }
                    }
                })
                ->first();
        }

        // 3. Fallback LIKE search on lead_code
        if (! $lead && ! empty($cleanId)) {
            $lead = Lead::withTrashed()
                ->where('lead_code', 'LIKE', "%{$cleanId}%")
                ->first();
        }

        // 4. Fallback search via reminder table if lead was referenced in a reminder
        if (! $lead) {
            $reminder = \App\Models\Reminder::withoutGlobalScope('company')
                ->where(function ($q) use ($idStr, $cleanId) {
                    $q->where('notes', 'LIKE', "%{$idStr}%")
                      ->orWhere('notes', 'LIKE', "%{$cleanId}%")
                      ->orWhere('remindable_id', $idStr);
                })
                ->first();
            if ($reminder && $reminder->remindable_id) {
                $lead = Lead::withTrashed()->find($reminder->remindable_id);
            }
        }

        if (! $lead) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(Lead::class, [$id]);
        }

        // 5. Un-delete any mistakenly soft-deleted lead
        if (method_exists($lead, 'trashed') && $lead->trashed()) {
            $lead->restore();
        }

        // 6. Ensure company_id aligns with current session so it doesn't vanish
        if ($tenantId && $lead->company_id !== $tenantId) {
            $lead->company_id = $tenantId;
            $lead->saveQuietly();
        }

        return $lead;
    }

    /**
     * Resolve lead by numeric primary key OR string code (e.g. LD-TMFIAY4U) and return SDUI schema.
     */
    public function show($id = null): JsonResponse
    {
        $tenantId = auth()->user()?->tenant_id ?? auth()->user()?->company_id;
        $company = null;
        try {
            $company = $this->resolveCompany(request());
        } catch (\Throwable $e) {
            if ($tenantId) {
                $company = Company::find($tenantId);
            }
        }

        $lookupId = $id;
        if ($lookupId instanceof Request) {
            $request = $lookupId;
            $lookupId = $request->query('id') ?: ($request->route('id') ?: ($request->input('id') ?: ($request->query('lead_code') ?: $request->input('lead_code'))));
        } elseif (! $lookupId) {
            $lookupId = request()->query('id') ?: (request()->route('id') ?: (request()->input('id') ?: (request()->query('lead_code') ?: request()->input('lead_code'))));
        }

        $lead = $this->resolveLead($lookupId, $company);
        $lead->load(['source', 'customer', 'assignedUser', 'activities' => fn ($q) => $q->orderByDesc('created_at')]);

        return $this->buildLeadSchemaResponse($lead, $company);
    }

    /**
     * Lead Detailed SDUI Screen.
     */
    public function leadDetail(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $leadId = $request->query('id') ?: ($request->route('id') ?: ($request->input('id') ?: ($request->query('lead_code') ?: $request->input('lead_code'))));
        if (! $leadId) {
            return response()->json(['success' => false, 'error' => 'Lead ID or lead code is required.'], 400);
        }

        $lead = $this->resolveLead($leadId, $company);
        $lead->load(['source', 'customer', 'assignedUser', 'activities' => fn ($q) => $q->orderByDesc('created_at')]);

        if ($user && ! PermissionChecker::can($user, 'leads', 'view_any') && $lead->assigned_to !== $user->id) {
            abort(403, 'Unauthorized: you can only view leads assigned to you.');
        }

        return $this->buildLeadSchemaResponse($lead, $company, $user);
    }

    /**
     * Build the Lead Detail SDUI Schema Response.
     */
    public function buildLeadSchemaResponse(Lead $lead, ?Company $company = null, ?User $user = null): JsonResponse
    {
        $company = $company ?? ($lead->company ?? Company::find($lead->company_id));
        if (! $company) {
            $company = $this->resolveCompany(request());
        }
        $user = $user ?? $this->resolveUser(request(), $company);
        $currency = $company ? ($company->currency_symbol ?: ($company->currency ?: '$')) : '₹';

        $stage = $lead->stage ?: $lead->status ?: 'new';
        $stageLabels = [
            ['key' => 'new', 'label' => 'New'],
            ['key' => 'contacted', 'label' => 'Contacted'],
            ['key' => 'qualified', 'label' => 'Qualified'],
            ['key' => 'proposal_sent', 'label' => 'Proposal'],
            ['key' => 'won', 'label' => 'Won'],
        ];

        $stageColor = match ($stage) {
            'won' => '#10B981',
            'lost' => '#EF4444',
            'proposal_sent' => '#F59E0B',
            'qualified' => '#0284C7',
            'contacted' => '#8B5CF6',
            default => '#2DD4BF',
        };

        $tracker = S::pipelineStageTracker($stageLabels, $stage, [
            'active_color' => '#2DD4BF',
            'completed_color' => '#10B981',
        ]);

        $overviewComponents = [
            S::row([
                S::badge($lead->lead_code, '#0284C7'),
                S::badge(ucfirst(str_replace('_', ' ', $stage)), $stageColor),
                S::text($currency . number_format((float) ($lead->expected_value ?: $lead->estimated_value ?: 0), 0), 'title_medium', ['bold' => true]),
            ]),
            S::text($lead->name, 'title_large', ['bold' => true]),
            $tracker,
            S::divider(),
            S::text('Company: ' . ($lead->company_name ?: 'Individual / Direct'), 'body_medium'),
            S::text('Contact: ' . trim(($lead->phone ?: '') . '  ·  ' . ($lead->email ?: '')), 'body_small'),
            S::text('Source: ' . ($lead->source_name ?: ($lead->source ?: 'Direct')), 'body_small'),
            S::text('Priority: ' . ucfirst($lead->priority ?? 'medium') . '  ·  Rep: ' . ($lead->assignedUser?->name ?: ($lead->assigned_to ?: 'Unassigned')), 'body_small'),
        ];

        if ($lead->title) {
            $overviewComponents[] = S::text("Scope / Title: {$lead->title}", 'body_medium', ['bold' => true]);
        }

        if ($lead->requirement_summary ?: $lead->notes) {
            $overviewComponents[] = S::callout('Requirements: ' . ($lead->requirement_summary ?: $lead->notes), 'accent');
        }

        if ($lead->customer_id) {
            $overviewComponents[] = S::text("✓ Linked Customer: {$lead->customer?->name} (#{$lead->customer_id})", 'body_medium', ['bold' => true, 'color' => '#10B981']);
        }

        if ($lead->stage === 'lost' && $lead->lost_reason) {
            $overviewComponents[] = S::callout("Lost Reason: {$lead->lost_reason}", 'danger');
        }

        $components = [
            S::card($overviewComponents),
        ];

        // Downstream Action Buttons (Quotations, Invoices, Conversion)
        $actionButtons = [];

        // 1. Create Quotation modal
        $quotationModalEndpoint = '/api/v1/tenant/quotations/create-modal?' . http_build_query([
            'lead_id'     => $lead->id,
            'lead_code'   => $lead->lead_code ?? "LD-{$lead->id}",
            'customer_id' => $lead->customer_id ?? '',
            'subject'     => $lead->requirement_scope ?? $lead->subject ?? $lead->requirement_summary ?? '',
            'notes'       => $lead->notes ?? '',
        ]);
        $quotationAction = S::openBottomSheetAction($quotationModalEndpoint, 'New quotation', [
            'lead_id'     => $lead->id,
            'lead_code'   => $lead->lead_code ?? "LD-{$lead->id}",
            'customer_id' => $lead->customer_id,
        ]);
        $actionButtons[] = S::buttonPrimary('Create Quotation', $quotationAction, 'description', [
            'variant'          => 'primary',
            'action_type'      => 'OPEN_BOTTOM_SHEET',
            'background_color' => '#166534',
            'foreground_color' => '#FFFFFF',
            'border_radius'    => 10,
            'style'            => [
                'backgroundColor' => '#166534',
                'textColor'       => '#FFFFFF',
                'borderRadius'    => 10,
                'marginVertical'  => 6,
            ],
        ]);

        // 2. 1-Tap Convert to Invoice
        $actionButtons[] = S::buttonOutlined('Convert to Tax Invoice',
            S::apiPostAction(self::BASE.'/leads/'.$lead->id.'/convert-to-invoice', [], 'Lead converted to draft invoice.', reload: true),
            'receipt_long', [
                'variant'       => 'outline',
                'border_radius' => 10,
                'style'         => [
                    'textColor'      => '#15803D',
                    'borderColor'    => '#15803D',
                    'borderRadius'   => 10,
                    'marginVertical' => 6,
                ],
            ]);

        // 3. Convert to Customer
        if (! $lead->customer_id) {
            $actionButtons[] = S::buttonPrimary('Provision Customer Profile',
                S::apiPostAction(self::BASE.'/leads/'.$lead->id.'/convert', [], 'Customer profile created.', reload: true),
                'person_add');
        }

        // 4. Stage Progression Buttons
        if ($lead->stage !== 'won' && $lead->stage !== 'lost') {
            if ($lead->stage === 'new') {
                $actionButtons[] = S::buttonOutlined('Mark Contacted',
                    S::apiPostAction(self::BASE.'/leads/'.$lead->id.'/status', ['stage' => 'contacted'], 'Marked contacted.', reload: true),
                    'phone_forwarded');
            }
            if (in_array($lead->stage, ['new', 'contacted'], true)) {
                $actionButtons[] = S::buttonOutlined('Mark Qualified',
                    S::apiPostAction(self::BASE.'/leads/'.$lead->id.'/status', ['stage' => 'qualified'], 'Opportunity qualified.', reload: true),
                    'verified');
            }
            if (in_array($lead->stage, ['new', 'contacted', 'qualified'], true)) {
                $actionButtons[] = S::buttonOutlined('Mark Proposal Sent',
                    S::apiPostAction(self::BASE.'/leads/'.$lead->id.'/status', ['stage' => 'proposal_sent'], 'Proposal marked sent.', reload: true),
                    'send');
            }
            $actionButtons[] = S::buttonDanger('Mark as Lost',
                S::apiPostAction(self::BASE.'/leads/'.$lead->id.'/status', ['stage' => 'lost'], 'Lead marked lost.', reload: true),
                'cancel');
        }

        $components[] = S::card($actionButtons);

        // Schedule Follow-up Reminder Form
        $components[] = S::card([
            S::text('Schedule Follow-up Reminder', 'title_medium', ['bold' => true]),
            S::textInput('title', 'Follow-up Subject', "Call {$lead->name}"),
            S::textInput('due_date', 'Reminder Due Date & Time (YYYY-MM-DD HH:MM)', now()->addDay()->format('Y-m-d 10:00')),
            S::textInput('notes', 'Reminder Notes / Call Script', '', [
                'placeholder' => 'Add discussion points, follow-up script, or client requirements...',
                'max_lines' => 3,
                'rows' => 3,
                'keyboard_type' => 'multiline',
            ]),
            S::buttonPrimary('Schedule Reminder',
                S::formSubmitAction(self::BASE.'/leads/'.$lead->id.'/reminders', 'POST', 'Reminder scheduled.', reload: true),
                'alarm_add'),
        ]);

        // List Linked Reminders
        $reminders = $lead->reminders()->orderByDesc('due_date')->get();
        if ($reminders->isNotEmpty()) {
            $remTiles = [S::text('Scheduled Reminders', 'title_small', ['bold' => true])];
            foreach ($reminders as $rem) {
                $dueStr = $rem->due_date ? $rem->due_date->format('Y-m-d H:i') : ($rem->due_at ? date('Y-m-d H:i', strtotime($rem->due_at)) : 'No due date');
                $noteText = trim((string) ($rem->notes ?: ($rem->description ?: $rem->call_script)));

                $cardItems = [
                    S::row([
                        S::icon('alarm', ['color' => '#0284C7', 'size' => 18]),
                        S::text(' '.($rem->title ?: ($rem->subject ?: 'Follow-up Reminder')), 'title_small', ['bold' => true]),
                    ]),
                    S::text("Due: {$dueStr}  ·  Status: ".ucfirst($rem->status ?: 'pending'), 'body_small'),
                ];

                if (!empty($noteText)) {
                    $cardItems[] = S::callout('📝 ' . $noteText, 'accent');
                }

                $remTiles[] = S::container($cardItems, [
                    'border_radius' => 8,
                    'padding' => 10,
                    'margin' => [0, 4, 0, 6],
                ]);
            }
            $components[] = S::card($remTiles);
        }

        // List Linked Quotations & Invoices
        $quotes = $lead->quotations()->get();
        $invoices = $lead->invoices()->get();
        if ($quotes->isNotEmpty() || $invoices->isNotEmpty()) {
            $docTiles = [S::text('Linked Financial Documents', 'title_small', ['bold' => true])];
            foreach ($quotes as $q) {
                $lines = [];
                if (!empty($q->items) && is_array($q->items)) {
                    foreach ($q->items as $item) {
                        $qty = (float) ($item['quantity'] ?? $item['qty'] ?? 1);
                        $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                        $lines[] = [
                            'name'       => (string) ($item['name'] ?? $item['product_name'] ?? 'Item'),
                            'quantity'   => $qty,
                            'unit_price' => $price,
                            'line_total' => (float) ($item['total'] ?? ($qty * $price)),
                        ];
                    }
                }
                if (empty($lines)) {
                    $lines[] = [
                        'name'       => 'Quote #' . $q->sale_number,
                        'quantity'   => 1,
                        'unit_price' => (float) $q->total,
                        'line_total' => (float) $q->total,
                    ];
                }

                $sheetAction = [
                    'type'        => 'show_post_sale_sheet',
                    'action_type' => 'show_post_sale_sheet',
                    'data'        => [
                        'sale_id'            => $q->id,
                        'sale_number'        => $q->sale_number,
                        'invoice_number'     => $q->sale_number,
                        'operation_type'     => 'quotation',
                        'customer_name'      => $q->customer?->name ?? 'Walk-in Customer',
                        'customer_phone'     => $q->customer?->phone ?? '',
                        'customer_email'     => $q->customer?->email ?? '',
                        'total'              => (float) $q->total,
                        'subtotal'           => (float) ($q->subtotal ?? $q->total),
                        'tax'                => (float) ($q->tax_amount ?? 0),
                        'discount'           => (float) ($q->discount_amount ?? 0),
                        'formatted_total'    => $currency.number_format((float) $q->total, 2),
                        'formatted_subtotal' => $currency.number_format((float) ($q->subtotal ?? $q->total), 2),
                        'date'               => $q->created_at?->toIso8601String() ?? now()->toIso8601String(),
                        'created_at'         => $q->created_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A'),
                        'pdf_url'            => url("/tenant/quotations/{$q->id}/print"),
                        'print_url'          => url("/tenant/quotations/{$q->id}/print"),
                        'view_url'           => url("/tenant/quotations/{$q->id}"),
                        'share_url'          => url("/tenant/quotations/{$q->id}/print"),
                        'share_text'         => "Quotation #{$q->sale_number} from {$company->name}: {$currency}".number_format((float) $q->total, 2),
                        'whatsapp_text'      => "Hello, here is your quotation #{$q->sale_number} for {$currency}".number_format((float) $q->total, 2).': '.url("/tenant/quotations/{$q->id}/print"),
                        'items'              => $lines,
                    ],
                ];

                $docTiles[] = S::lineItemTile(
                    "Quotation #{$q->sale_number}",
                    'Total: '.$currency.number_format((float) $q->total, 2).'  ·  Status: '.ucfirst($q->status),
                    'request_quote',
                    [
                        'type'            => 'navigate',
                        'target'          => 'dynamic_page',
                        'endpoint'        => "/api/tenant/views/quotations/{$q->id}",
                        'route'           => "/api/tenant/views/quotations/{$q->id}",
                        'target_endpoint' => "/api/tenant/views/quotations/{$q->id}",
                        'title'           => "Quote #{$q->sale_number}",
                        'action_type'     => 'navigate',
                    ]
                );

                $docTiles[] = S::buttonPrimary(
                    "Preview & Share Quote #{$q->sale_number}",
                    $sheetAction,
                    'share',
                    [
                        'dense'            => true,
                        'background_color' => '#0284c7',
                        'margin'           => [0, 2, 0, 8],
                    ]
                );
            }
            foreach ($invoices as $inv) {
                $invSheetAction = [
                    'type'        => 'show_post_sale_sheet',
                    'action_type' => 'show_post_sale_sheet',
                    'data'        => S::postSaleActionData($inv),
                ];
                $docTiles[] = S::lineItemTile(
                    "Tax Invoice #{$inv->sale_number}",
                    'Total: '.$currency.number_format((float) $inv->total, 2).'  ·  Status: '.ucfirst($inv->payment_status ?: $inv->status),
                    'receipt_long',
                    $invSheetAction
                );
                $docTiles[] = S::buttonPrimary(
                    "Preview & Share Invoice #{$inv->sale_number}",
                    $invSheetAction,
                    'share',
                    [
                        'dense'            => true,
                        'background_color' => '#166534',
                        'margin'           => [0, 2, 0, 8],
                    ]
                );
            }
            $components[] = S::card($docTiles);
        }

        // List Activities
        $activityTiles = [S::text('Activity & Audit History', 'title_small', ['bold' => true])];
        foreach ($lead->activities as $act) {
            $icon = match ($act->type) {
                'call' => 'phone',
                'meeting' => 'groups',
                'email' => 'email',
                'task' => 'task_alt',
                default => 'note',
            };
            $dueText = $act->due_date ? '  ·  Due '.$act->due_date->format('Y-m-d') : '';
            $descText = $act->description ? '  ·  '.$act->description : '';

            $action = null;
            if ($act->status === 'pending') {
                $action = S::apiPostAction(self::BASE.'/activities/'.$act->id.'/complete', [], 'Activity marked completed.', reload: true);
            }

            $activityTiles[] = S::lineItemTile(
                '['.strtoupper($act->type).'] '.$act->title,
                'Status: '.ucfirst($act->status).$dueText.$descText,
                $icon,
                $action
            );
        }
        $components[] = S::card($activityTiles);

        return $this->schema(S::screen('Lead '.$lead->lead_code, $components));
    }

    /**
     * RESTful Lead Listing (`GET /api/v1/tenant/leads`).
     */
    /**
     * RESTful Lead Listing (`GET /api/v1/tenant/leads`).
     */
    public function leadsIndex(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $tenantId = $company->id ?? auth()->user()?->tenant_id ?? auth()->user()?->company_id;

        if ($user && ! PermissionChecker::can($user, 'leads', 'view') && ! PermissionChecker::can($user, 'leads', 'view_any')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized: leads.view permission required.'], 403);
        }

        $query = Lead::withoutGlobalScope('company')
            ->where(function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('company_id', $tenantId)
                      ->orWhereNull('company_id');
                    if (\Illuminate\Support\Facades\Schema::hasColumn('lead_mod_leads', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                }
            })
            ->with(['source', 'customer', 'assignedUser', 'reminders', 'quotations', 'invoices'])
            ->latest('created_at');

        if (\Illuminate\Support\Facades\Schema::hasColumn('lead_mod_leads', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($user && ! PermissionChecker::can($user, 'leads', 'view_any')) {
            $query->where('assigned_to', $user->id);
        }

        $search = trim((string) ($request->input('q') ?: $request->input('search') ?: $request->input('search_leads') ?: ''));
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('lead_code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($stage = ($request->query('stage') ?: $request->query('status'))) {
            if ($stage !== 'all') {
                $query->where(function ($q) use ($stage) {
                    $q->where('stage', $stage)->orWhere('status', $stage);
                });
            }
        }

        $leads = $query->paginate(20);

        $pipelineValue = (float) (Lead::withoutGlobalScope('company')
            ->where(function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('company_id', $tenantId)->orWhereNull('company_id');
                    if (\Illuminate\Support\Facades\Schema::hasColumn('lead_mod_leads', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                }
            })
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('lead_mod_leads', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->whereNotIn('stage', ['lost'])
            ->sum('expected_value') ?: 0);

        return response()->json([
            'success' => true,
            'data' => $leads->items(),
            'leads' => $leads->items(),
            'pipeline_count' => $leads->total(),
            'pipeline_value' => $pipelineValue,
            'meta' => [
                'total' => $leads->total(),
                'current_page' => $leads->currentPage(),
                'has_more' => $leads->hasMorePages(),
            ],
            'pagination' => [
                'total' => $leads->total(),
                'current_page' => $leads->currentPage(),
                'per_page' => $leads->perPage(),
                'last_page' => $leads->lastPage(),
            ],
        ]);
    }

    /**
     * RESTful Lead Show (`GET /api/v1/tenant/leads/{id}`).
     */
    public function leadsShow(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $lead = $this->resolveLead($id, $company);
        $lead->load(['source', 'customer', 'assignedUser', 'activities', 'reminders', 'quotations', 'invoices']);

        if ($user && ! PermissionChecker::can($user, 'leads', 'view_any') && $lead->assigned_to !== $user->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized: you can only view leads assigned to you.'], 403);
        }

        return response()->json([
            'success' => true,
            'lead' => $lead,
        ]);
    }

    /**
     * RESTful Lead Update (`PUT /api/v1/tenant/leads/{id}`).
     */
    public function leadsUpdate(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        if ($user && ! PermissionChecker::can($user, 'leads', 'edit')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized: leads.edit permission required.'], 403);
        }

        $lead = $this->resolveLead($id, $company);

        if ($user && ! PermissionChecker::can($user, 'leads', 'view_any') && $lead->assigned_to !== $user->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized: you can only edit leads assigned to you.'], 403);
        }

        if (! $request->filled('name')) {
            $fallbackName = $request->input('contact_name') ?: $request->input('client_name') ?: $request->input('customer_name');
            if ($fallbackName) {
                $request->merge(['name' => $fallbackName]);
            }
        }
        if (! $request->filled('phone')) {
            $fallbackPhone = $request->input('phone_number') ?: $request->input('customer_phone');
            if ($fallbackPhone) {
                $request->merge(['phone' => $fallbackPhone]);
            }
        }
        if (! $request->filled('email')) {
            $fallbackEmail = $request->input('email_address') ?: $request->input('customer_email');
            if ($fallbackEmail) {
                $request->merge(['email' => $fallbackEmail]);
            }
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'title' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'customer_id' => ['nullable'],
            'source_id' => ['nullable'],
            'source' => ['nullable', 'string', 'max:100'],
            'stage' => ['nullable', 'string', 'in:new,contacted,qualified,proposal_sent,won,lost'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'expected_value' => ['nullable', 'numeric', 'min:0'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'assigned_to' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'requirement_summary' => ['nullable', 'string', 'max:5000'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $this->leadService->updateLead($lead, $data, $company, $user);

        return response()->json([
            'success' => true,
            'message' => 'Lead updated successfully.',
            'lead' => $updated,
        ]);
    }

    /**
     * Stage & Status Transition Handler.
     */
    public function leadStatus(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $lead = $this->resolveLead($id, $company);

        $data = $request->validate([
            'stage' => ['sometimes', 'string', 'in:new,contacted,qualified,proposal_sent,won,lost'],
            'status' => ['sometimes', 'string'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $stage = $data['stage'] ?? ($data['status'] === 'proposal' ? 'proposal_sent' : ($data['status'] ?? 'new'));

        $this->leadService->updateLead($lead, [
            'stage' => $stage,
            'status' => in_array($stage, ['won', 'lost'], true) ? $stage : 'active',
            'lost_reason' => $data['lost_reason'] ?? null,
        ], $company, $user);

        return response()->json([
            'success' => true,
            'message' => 'Lead stage updated to '.ucfirst(str_replace('_', ' ', $stage)).'.',
            'action' => 'refresh_view',
        ]);
    }

    /**
     * Convert Lead to Customer.
     */
    public function leadConvert(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $lead = $this->resolveLead($id, $company);

        $customer = $this->leadService->convertToCustomer($lead, $company, $user);

        return response()->json([
            'success' => true,
            'message' => 'Lead successfully converted to Customer.',
            'customer_id' => $customer->id,
            'action' => 'refresh_view',
        ]);
    }

    /**
     * 1-Tap Convert Lead to Draft Invoice.
     */
    public function leadConvertToInvoice(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $lead = $this->resolveLead($id, $company);

        if ($user && ! PermissionChecker::can($user, 'leads', 'convert')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized: leads.convert permission required.'], 403);
        }

        $invoice = $this->leadService->convertToInvoice($lead, $company, $user);

        return response()->json([
            'success' => true,
            'message' => "Lead converted to Invoice #{$invoice->sale_number}.",
            'invoice_id' => $invoice->id,
            'sale_number' => $invoice->sale_number,
            'action' => 'refresh_view',
        ]);
    }

    /**
     * Schedule a Reminder for Lead.
     */
    public function leadAddReminder(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $lead = $this->resolveLead($id, $company);

        $notes = $request->input('notes')
              ?? $request->input('reminder_notes')
              ?? $request->input('call_script')
              ?? $request->input('description')
              ?? '';

        $title = $request->input('title')
              ?? $request->input('subject')
              ?? $request->input('follow_up_subject')
              ?? "Follow up with {$lead->name}";

        $dueDate = $request->input('due_date')
                ?? $request->input('due_at')
                ?? $request->input('reminder_due')
                ?? now()->addDay()->format('Y-m-d 10:00:00');

        $data = [
            'title' => $title,
            'subject' => $title,
            'due_date' => $dueDate,
            'due_at' => $dueDate,
            'notes' => $notes,
            'description' => $notes,
            'call_script' => $notes,
            'type' => $request->input('type') ?: 'lead_followup',
            'status' => $request->input('status') ?: 'pending',
        ];

        $reminder = $this->leadService->scheduleReminder($lead, $data, $company, $user);

        return response()->json([
            'success' => true,
            'message' => 'Follow-up reminder scheduled successfully.',
            'reminder' => $reminder,
            'action' => 'refresh_view',
        ]);
    }

    /**
     * Get Sales Representatives for dropdown select.
     */
    public function salesReps(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $reps = $this->leadService->getSalesReps($company);

        return response()->json([
            'success' => true,
            'sales_reps' => $reps,
            'reps' => $reps,
        ]);
    }

    /**
     * Search Customers for linking.
     */
    public function customerSearch(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $q = (string) ($request->query('q') ?: $request->query('query') ?: $request->query('search') ?: '');

        $customers = $this->leadService->searchCustomers($q, $company)
            ->map(function (Customer $c) {
                $companyName = $c->company_name ?? ($c->custom_fields['company_name'] ?? '');
                $label = $c->name;
                if ($c->phone) {
                    $label .= " ({$c->phone})";
                }
                if ($companyName && $companyName !== $c->name) {
                    $label .= " - {$companyName}";
                }

                return [
                    'id' => (string) ($c->external_id ?: $c->id),
                    'server_id' => $c->id,
                    'value' => $c->id,
                    'label' => $label,
                    'name' => $c->name,
                    'title' => $c->name,
                    'subtitle' => $c->phone ?: ($c->email ?: ''),
                    'phone' => $c->phone ?? '',
                    'email' => $c->email ?? '',
                    'company_name' => $companyName,
                    'due_amount' => $c->due_balance > 0 ? 'Due: ' . number_format((float) $c->due_balance, 2) : null,
                    'avatar_icon' => 'person',
                    'badge_due_bg' => 'rgba(239, 68, 68, 0.15)',
                    'badge_due_tx' => '#F87171',
                    'document' => $c->document ?? $c->tax_id ?? '',
                    'balance_due' => (float) ($c->due_balance ?? 0),
                    'custom_fields' => $c->custom_fields ?? (object) [],
                ];
            });

        return response()->json([
            'success' => true,
            'count' => $customers->count(),
            'theme' => [
                'container_bg'     => S::themeToken('theme.surface', $request),
                'dropdown_surface' => S::themeToken('theme.surface', $request),
                'popup_background' => S::themeToken('theme.surface', $request),
                'surface'          => S::themeToken('theme.surface', $request),
                'card'             => S::themeToken('theme.surface', $request),
                'border_color'     => S::themeToken('theme.divider', $request),
                'title_color'      => S::themeToken('theme.textPrimary', $request),
                'sub_color'        => S::themeToken('theme.textSecondary', $request),
                'text_color'       => S::themeToken('theme.textPrimary', $request),
                'badge_due_bg'     => 'rgba(239, 68, 68, 0.15)',
                'badge_due_tx'     => '#F87171',
            ],
            'data' => $customers,
            'customers' => $customers,
        ]);
    }

    /**
     * Activities list view.
     */
    public function activitiesView(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $leads = Lead::where('company_id', $company->id)
            ->whereNotIn('stage', ['lost'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $leadOptions = [];
        foreach ($leads as $l) {
            $leadOptions[] = ['label' => "{$l->lead_code} - {$l->name}", 'value' => (string) $l->id];
        }

        $activityTypes = [
            ['label' => 'Phone Call', 'value' => 'call'],
            ['label' => 'Meeting', 'value' => 'meeting'],
            ['label' => 'Email', 'value' => 'email'],
            ['label' => 'Task', 'value' => 'task'],
            ['label' => 'Note', 'value' => 'note'],
        ];

        $rows = LeadActivity::where('company_id', $company->id)
            ->with('lead')
            ->orderByRaw("status = 'pending' desc, due_date asc")
            ->limit(100)
            ->get();

        $tiles = [];
        foreach ($rows as $act) {
            $leadLabel = $act->lead ? "  ·  {$act->lead->name} ({$act->lead->lead_code})" : '';
            $dueText = $act->due_date ? '  ·  Due '.$act->due_date->format('Y-m-d') : '';
            $action = $act->status === 'pending'
                ? S::apiPostAction(self::BASE.'/activities/'.$act->id.'/complete', [], 'Activity completed.', reload: true)
                : null;

            $tiles[] = S::lineItemTile(
                '['.strtoupper($act->type).'] '.$act->title,
                'Status: '.ucfirst($act->status).$dueText.$leadLabel,
                $act->status === 'completed' ? 'check_circle' : 'schedule',
                $action
            );
        }

        $formComponents = [
            S::text('Schedule Activity', 'title_medium', ['bold' => true]),
        ];

        if ($leadOptions !== []) {
            $formComponents[] = S::dropdownSelect('lead_id', 'Associated Lead', $leadOptions, $leadOptions[0]['value']);
            $formComponents[] = S::dropdownSelect('type', 'Activity Type', $activityTypes, 'call');
            $formComponents[] = S::textInput('title', 'Activity Subject / Title', '');
            $formComponents[] = S::textInput('due_date', 'Due Date (YYYY-MM-DD)', now()->addDay()->format('Y-m-d'));
            $formComponents[] = S::textInput('description', 'Details / Notes', '', ['max_lines' => 2, 'keyboard_type' => 'multiline']);
            $formComponents[] = S::buttonPrimary('Save Activity',
                S::formSubmitAction(self::BASE.'/activities', 'POST', 'Activity scheduled.', reload: true), 'add_task');
        } else {
            $formComponents[] = S::text('Create a lead first before scheduling activities.', 'body_small', ['color' => '#64748b']);
        }

        return $this->schema(S::screen('Follow-ups & Activities', [
            S::card($formComponents),
            S::card($tiles !== [] ? $tiles : [
                S::text('No activities scheduled yet.', 'body_small', ['color' => '#64748b']),
            ]),
        ]));
    }

    public function activitiesStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $data = $request->validate([
            'lead_id' => ['required'],
            'type' => ['required', 'string', 'in:call,meeting,email,note,task'],
            'title' => ['required', 'string', 'max:200'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = $this->resolveLead($data['lead_id'], $company);

        $activity = LeadActivity::create([
            'company_id' => $company->id,
            'lead_id' => (int) $lead->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'due_date' => $data['due_date'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Activity scheduled successfully.',
            'id' => $activity->id,
        ]);
    }

    public function activityComplete(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $activity = LeadActivity::where('company_id', $company->id)->findOrFail($id);

        $activity->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Activity marked as completed.',
            'action' => 'refresh_view',
        ]);
    }

    public function sourcesView(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $rows = LeadSource::where('company_id', $company->id)
            ->withCount('leads')
            ->orderBy('name')
            ->get();

        $list = [];
        foreach ($rows as $src) {
            $desc = $src->description ? "  ·  {$src->description}" : '';
            $list[] = S::lineItemTile(
                $src->name,
                "{$src->leads_count} leads{$desc}",
                'source'
            );
        }

        return $this->schema(S::screen('Lead Sources', [
            S::card([
                S::text('Add Lead Source', 'title_medium', ['bold' => true]),
                S::textInput('name', 'Source Name (e.g. Website, Referral, Cold Call)', ''),
                S::textInput('description', 'Description (optional)', ''),
                S::buttonPrimary('Save Source',
                    S::formSubmitAction(self::BASE.'/sources', 'POST', 'Lead source saved.', reload: true), 'save'),
            ]),
            S::card($list !== [] ? $list : [
                S::text('No custom lead sources configured yet.', 'body_small', ['color' => '#64748b']),
            ]),
        ]));
    }

    public function sourcesStore(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $source = LeadSource::create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead source saved.',
            'id' => $source->id,
        ]);
    }

    private function schema(array $schema): JsonResponse
    {
        return response()->json([
            'success' => true,
            'view' => $schema['key'] ?? 'lead-module',
            'schema' => $schema,
        ]);
    }
}
