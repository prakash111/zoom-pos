<?php

namespace App\Http\Controllers\Api;

use App\Models\Company;
use App\Models\Lead;
use App\Services\Sdui\SchemaResponse as S;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\leadmanagement\Http\Controllers\LeadModuleController;

/**
 * RESTful and SDUI API Controller for Lead Management.
 * Aliases and wraps Modules\leadmanagement\Http\Controllers\LeadModuleController.
 */
class LeadController extends LeadModuleController
{
    /**
     * SDUI Tabbed Lead Management View or JSON listing (`GET /api/v1/tenant/leads`, `/api/tenant/views/leads`).
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->routeIs('*views*') || $request->has('tab') || ! $request->wantsJson()) {
            return $this->dashboard($request);
        }

        $tenantId = auth()->user()?->tenant_id ?? auth()->user()?->company_id;
        try {
            $company = $this->resolveCompany($request);
            $tenantId = $company->id;
        } catch (\Throwable $e) {
        }

        $query = Lead::query();

        // Match either company_id or tenant_id
        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('company_id', $tenantId)
                  ->orWhereNull('company_id');
                if (\Illuminate\Support\Facades\Schema::hasColumn('lead_mod_leads', 'tenant_id')) {
                    $q->orWhere('tenant_id', $tenantId);
                }
            });
        }

        // Filter out deleted if column exists
        if (\Illuminate\Support\Facades\Schema::hasColumn('lead_mod_leads', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $leads = $query->latest()->get();

        return response()->json([
            'success'        => true,
            'data'           => $leads,
            'leads'          => $leads,
            'pipeline_count' => $leads->count(),
            'pipeline_value' => (float) ($leads->sum('opportunity_value') ?: ($leads->sum('expected_value') ?: ($leads->sum('estimated_value') ?: 0))),
            'components'     => $this->buildLeadListComponents($leads),
        ]);
    }

    /**
     * SDUI Schema for Lead Detail View.
     * GET /api/tenant/views/lead-detail
     * GET /api/tenant/views/leads/{id}
     * GET /api/v1/tenant/leads/{id}
     */
    public function showSchema($request = null, $id = null): JsonResponse
    {
        if ($request instanceof Request) {
            $req = $request;
            $leadId = $id ?: ($req->query('id') ?: ($req->route('id') ?: ($req->input('id') ?: ($req->query('lead_code') ?: $req->input('lead_code')))));
        } else {
            $leadId = $request ?: $id;
            $req = request();
        }

        if ($leadId && ! $req->has('id')) {
            $req->merge(['id' => $leadId]);
        }

        return $this->leadDetail($req);
    }

    /**
     * Resolve lead by numeric primary key OR string code (e.g., LD-TMFIAY4U)
     */
    public function show($id = null): JsonResponse
    {
        if ($id instanceof Request) {
            return $this->showSchema($id);
        }

        $tenantId = auth()->user()?->tenant_id ?? auth()->user()?->company_id;
        $company = null;
        try {
            $company = $this->resolveCompany(request());
        } catch (\Throwable $e) {
            if ($tenantId) {
                $company = Company::find($tenantId);
            }
        }

        $lead = $this->resolveLead($id ?: request()->input('id'), $company);
        $lead->load(['source', 'customer', 'assignedUser', 'activities' => fn ($q) => $q->orderByDesc('created_at')]);

        return $this->buildLeadSchemaResponse($lead, $company);
    }

    /**
     * SDUI Schema for "Capture New Lead" / Edit Lead.
     * GET /api/tenant/views/create-lead
     * GET /api/tenant/leads/create
     * GET /api/v1/tenant/leads/create
     */
    public function createSchema(Request $request): JsonResponse
    {
        return $this->createLeadView($request);
    }

    /**
     * Alias for createSchema / getCaptureLeadSchema.
     */
    public function getCaptureLeadSchema(Request $request): JsonResponse
    {
        return $this->createLeadView($request);
    }

    /**
     * Alias for captureSchema.
     */
    public function captureSchema(Request $request): JsonResponse
    {
        return $this->createLeadView($request);
    }

    /**
     * RESTful Lead Listing with Live Search Filtering (`GET /api/v1/tenant/leads/list`, `/tenant/leads/list`, `/leads/list`).
     */
    public function list(Request $request): JsonResponse
    {
        $tenantId = auth()->user()?->tenant_id ?? auth()->user()?->company_id;
        try {
            $company = $this->resolveCompany($request);
            $tenantId = $company->id;
        } catch (\Throwable $e) {
            // fallback
        }

        $query = trim((string) ($request->input('q') ?: $request->input('search') ?: $request->input('search_leads') ?: ''));
        $stage = (string) ($request->input('stage') ?: 'all');

        $leadsQuery = Lead::with(['customer', 'source', 'assignedUser'])
            ->where(function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('company_id', $tenantId)
                      ->orWhereNull('company_id');
                    if (\Illuminate\Support\Facades\Schema::hasColumn('lead_mod_leads', 'tenant_id')) {
                        $q->orWhere('tenant_id', $tenantId);
                    }
                }
            })
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('lead_mod_leads', 'deleted_at'), function ($q) {
                $q->whereNull('deleted_at');
            })
            ->when($stage !== 'all', function ($q) use ($stage) {
                $q->where(function ($sq) use ($stage) {
                    $sq->where('stage', $stage)->orWhere('status', $stage);
                });
            })
            ->when(!empty($query), function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('lead_code', 'LIKE', "%{$query}%")
                        ->orWhere('title', 'LIKE', "%{$query}%")
                        ->orWhere('name', 'LIKE', "%{$query}%")
                        ->orWhere('phone', 'LIKE', "%{$query}%")
                        ->orWhere('email', 'LIKE', "%{$query}%")
                        ->orWhere('company_name', 'LIKE', "%{$query}%")
                        ->orWhereHas('customer', function ($cq) use ($query) {
                            $cq->where('name', 'LIKE', "%{$query}%")
                               ->orWhere('phone', 'LIKE', "%{$query}%")
                               ->orWhere('company_name', 'LIKE', "%{$query}%");
                        });
                });
            })
            ->orderBy('created_at', 'desc');

        $user = auth()->user();
        if ($user && ! \App\Services\Auth\PermissionChecker::can($user, 'leads', 'view_any')) {
            $leadsQuery->where('assigned_to', $user->id);
        }

        $leads = $leadsQuery->paginate(20);

        $currency = isset($company) && $company->currency_symbol ? $company->currency_symbol : '₹';
        $items = [];
        foreach ($leads->items() as $lead) {
            $stage = $lead->stage ?: $lead->status;
            $stageColor = match ($stage) {
                'won' => '#10B981',
                'lost' => '#EF4444',
                'proposal_sent' => '#F59E0B',
                'qualified' => '#0284C7',
                'contacted' => '#8B5CF6',
                default => '#06B6D4',
            };
            $stageLabel = ucfirst(str_replace('_', ' ', $stage));
            $val = $currency . number_format((float) ($lead->expected_value ?: $lead->estimated_value ?: 0), 2);
            $leadTitle = $lead->customer?->name ?? ($lead->name ?: ($lead->title ?? 'Unnamed Lead'));
            $companyName = $lead->customer?->company_name ?? ($lead->company_name ?: '');
            $orgSuffix = $companyName ? "  ·  {$companyName}" : '';
            $contactInfo = trim(($lead->phone ?: '') . ($lead->email ? "  ·  {$lead->email}" : ''));
            $repName = $lead->assignedUser?->name ?: ($lead->assigned_to ?: 'Unassigned');

            $items[] = array_merge($lead->toArray(), [
                'type' => 'card',
                'component_type' => 'lead_card',
                'lead_code' => $lead->lead_code,
                'title' => $leadTitle,
                'subtitle' => $companyName,
                'company' => $companyName,
                'contact' => $lead->customer?->phone ?? $lead->phone,
                'email' => $lead->customer?->email ?? $lead->email,
                'contact_info' => $contactInfo,
                'rep_name' => $repName,
                'priority' => ucfirst($lead->priority ?? 'medium'),
                'stage' => $stage,
                'stage_label' => $stageLabel,
                'expected_value' => $val,
                'components' => [
                    S::row([
                        S::badge($lead->lead_code, '#0284C7'),
                        S::badge($stageLabel, $stageColor),
                        S::text($val, 'body_medium', ['bold' => true]),
                    ]),
                    S::text("{$leadTitle}{$orgSuffix}", 'title_medium', ['bold' => true]),
                    $contactInfo ? S::text($contactInfo, 'body_small') : S::text('No phone/email provided', 'body_small'),
                    S::text("Rep: {$repName}  ·  Priority: " . ucfirst($lead->priority ?? 'medium'), 'body_small'),
                    S::row([
                        S::buttonOutlined('View Details',
                            S::navigateAction('/api/tenant/lead-module/views/lead-detail?id=' . $lead->id, 'dynamic_page', $lead->lead_code)),
                        [
                            'type'        => 'button',
                            'label'       => 'Create Quote',
                            'icon'        => 'request_quote',
                            'variant'     => 'primary',
                            'action_type' => 'OPEN_BOTTOM_SHEET',
                            'action'      => [
                                'type'           => 'OPEN_BOTTOM_SHEET',
                                'title'          => 'New quotation',
                                'endpoint'       => '/api/v1/tenant/quotations/create-modal?' . http_build_query([
                                    'lead_id'     => $lead->id,
                                    'lead_code'   => $lead->lead_code ?? "LD-{$lead->id}",
                                    'customer_id' => $lead->customer_id ?? '',
                                    'subject'     => $lead->requirement_scope ?? $lead->subject ?? $lead->requirement_summary ?? '',
                                    'notes'       => $lead->notes ?? '',
                                ]),
                                'sheet_endpoint' => '/api/v1/tenant/quotations/create-modal?' . http_build_query([
                                    'lead_id'     => $lead->id,
                                    'lead_code'   => $lead->lead_code ?? "LD-{$lead->id}",
                                    'customer_id' => $lead->customer_id ?? '',
                                    'subject'     => $lead->requirement_scope ?? $lead->subject ?? $lead->requirement_summary ?? '',
                                    'notes'       => $lead->notes ?? '',
                                ]),
                                'lead_id'        => $lead->id,
                                'lead_code'      => $lead->lead_code ?? "LD-{$lead->id}",
                                'customer_id'    => $lead->customer_id,
                            ],
                        ],
                    ]),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $items,
            'leads' => $items,
            'items' => $items,
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
     * Leads List Endpoint (`GET /api/v1/tenant/leads/list`).
     */
    public function leadsList(Request $request): JsonResponse
    {
        return $this->list($request);
    }

    /**
     * Leads List Endpoint alias.
     */
    public function leadsListEndpoint(Request $request): JsonResponse
    {
        return $this->list($request);
    }

    /**
     * RESTful Lead Index alias.
     */
    public function leadsIndex(Request $request): JsonResponse
    {
        return $this->list($request);
    }

    /**
     * Follow-ups & Reminders Endpoint (`GET /api/v1/tenant/leads/followups`).
     */
    public function followups(Request $request): JsonResponse
    {
        return app(LeadReminderController::class)->index($request);
    }

    /**
     * Build SDUI components for leads list.
     */
    public function buildLeadListComponents($leads): array
    {
        $components = [];
        foreach ($leads as $lead) {
            $stage = $lead->stage ?: ($lead->status ?: 'new');
            $stageColor = match ($stage) {
                'won' => '#10B981',
                'lost' => '#EF4444',
                'proposal_sent' => '#F59E0B',
                'qualified' => '#0284C7',
                'contacted' => '#8B5CF6',
                default => '#06B6D4',
            };
            $val = '₹' . number_format((float) ($lead->expected_value ?: ($lead->estimated_value ?: 0)), 2);
            $leadTitle = $lead->customer?->name ?? ($lead->name ?: ($lead->title ?? 'Unnamed Lead'));
            $companyName = $lead->customer?->company_name ?? ($lead->company_name ?: '');
            $contactInfo = trim(($lead->phone ?: '') . ($lead->email ? "  ·  {$lead->email}" : ''));

            $components[] = [
                'type' => 'card',
                'components' => [
                    S::row([
                        S::badge($lead->lead_code, '#0284C7'),
                        S::badge(ucfirst(str_replace('_', ' ', $stage)), $stageColor),
                        S::text($val, 'body_medium', ['bold' => true]),
                    ]),
                    S::text($leadTitle . ($companyName ? "  ·  {$companyName}" : ''), 'title_medium', ['bold' => true]),
                    $contactInfo ? S::text($contactInfo, 'body_small') : S::text('No phone/email provided', 'body_small'),
                    S::row([
                        S::buttonOutlined('View Details',
                            S::navigateAction('/api/tenant/lead-module/views/lead-detail?id=' . $lead->id, 'dynamic_page', $lead->lead_code)),
                    ]),
                ],
            ];
        }
        return $components;
    }
}
