<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Modules\leadmanagement\Models\LeadActivity;
use Modules\leadmanagement\Models\LeadSource;

class LeadWebController extends Controller
{
    public function __construct(
        protected LeadService $leadService
    ) {
    }

    public function index(Request $request): View
    {
        $user = auth()->user();
        $company = $user?->company;

        abort_if(! $company, 403, 'Company not associated with user');

        $search = trim((string) $request->input('q', $request->input('search', '')));
        $stage = trim((string) $request->input('stage', ''));
        $assignedTo = $request->input('assigned_to');
        $sourceId = $request->input('source_id');
        $tab = $request->input('tab', 'pipeline');

        // Pipeline Metrics
        $totalLeads = Lead::where('company_id', $company->id)->count();
        $newLeads = Lead::where('company_id', $company->id)->where('stage', 'new')->count();
        $contactedLeads = Lead::where('company_id', $company->id)->where('stage', 'contacted')->count();
        $qualifiedLeads = Lead::where('company_id', $company->id)->where('stage', 'qualified')->count();
        $proposalSentLeads = Lead::where('company_id', $company->id)->where('stage', 'proposal_sent')->count();
        $wonLeads = Lead::where('company_id', $company->id)->where('stage', 'won')->count();
        $lostLeads = Lead::where('company_id', $company->id)->where('stage', 'lost')->count();

        $pipelineValue = (float) Lead::where('company_id', $company->id)
            ->whereNotIn('stage', ['lost'])
            ->sum('expected_value');

        $wonValue = (float) Lead::where('company_id', $company->id)
            ->where('stage', 'won')
            ->sum('expected_value');

        $metrics = [
            'total' => $totalLeads,
            'new' => $newLeads,
            'contacted' => $contactedLeads,
            'qualified' => $qualifiedLeads,
            'proposal_sent' => $proposalSentLeads,
            'won' => $wonLeads,
            'lost' => $lostLeads,
            'pipeline_value' => $pipelineValue,
            'won_value' => $wonValue,
        ];

        // Leads Query
        $leadsQuery = Lead::with(['customer', 'assignedUser', 'source'])
            ->where('company_id', $company->id);

        if ($search !== '') {
            $hasCompanyCol = Schema::hasColumn('customers', 'company_name');
            $leadsQuery->where(function ($builder) use ($search, $hasCompanyCol) {
                $builder->where('lead_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($sub) use ($search, $hasCompanyCol) {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                        if ($hasCompanyCol) {
                            $sub->orWhere('company_name', 'like', "%{$search}%");
                        }
                    });
            });
        }

        if ($stage !== '') {
            $leadsQuery->where('stage', $stage);
        }

        if ($assignedTo) {
            $leadsQuery->where('assigned_to', $assignedTo);
        }

        if ($sourceId) {
            $leadsQuery->where('source_id', $sourceId);
        }

        $leads = $leadsQuery->orderByDesc('id')->paginate(15)->withQueryString();

        // Upcoming / Pending Activities
        $pendingActivities = LeadActivity::with('lead')
            ->where('company_id', $company->id)
            ->where('status', 'pending')
            ->orderBy('due_date', 'asc')
            ->take(20)
            ->get();

        // Recent Timeline / Completed Activities
        $recentActivities = LeadActivity::with('lead')
            ->where('company_id', $company->id)
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->take(15)
            ->get();

        // Dropdown Data
        $sources = LeadSource::where('company_id', $company->id)
            ->where('is_active', true)
            ->get();

        $users = User::where('company_id', $company->id)
            ->get(['id', 'name', 'email']);

        $customers = Customer::where('company_id', $company->id)
            ->orderBy('name')
            ->take(100)
            ->get(['id', 'name', 'phone', 'email']);

        return view('tenant.leads.index', compact(
            'leads',
            'metrics',
            'pendingActivities',
            'recentActivities',
            'sources',
            'users',
            'customers',
            'search',
            'stage',
            'assignedTo',
            'sourceId',
            'tab'
        ));
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('tenant.leads.index', ['tab' => 'capture']);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $company = $user?->company;

        abort_if(! $company, 403, 'Company not associated with user');

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'customer_id' => 'nullable|integer',
            'stage' => 'nullable|string|in:new,contacted,qualified,proposal_sent,won,lost',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'expected_value' => 'nullable|numeric|min:0',
            'source_id' => 'nullable|integer',
            'assigned_to' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'reminder_title' => 'nullable|string|max:255',
            'reminder_due_date' => 'nullable|date',
        ]);

        $lead = $this->leadService->createLead($validated, $company, $user);

        return redirect()->route('tenant.leads.index', ['tab' => 'all'])
            ->with('status', __("Lead :code created successfully.", ['code' => $lead->lead_code]));
    }

    public function show(Request $request, $id): View|RedirectResponse
    {
        $user = auth()->user();
        $company = $user?->company;

        abort_if(! $company, 403, 'Company not associated with user');

        $lead = Lead::with(['customer', 'assignedUser', 'source', 'activities' => fn ($q) => $q->orderByDesc('id'), 'quotations'])
            ->where('company_id', $company->id)
            ->findOrFail($id);

        return redirect()->route('tenant.leads.index', ['search' => $lead->lead_code, 'tab' => 'all']);
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $user = auth()->user();
        $company = $user?->company;

        abort_if(! $company, 403, 'Company not associated with user');

        $lead = Lead::where('company_id', $company->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'stage' => 'nullable|string|in:new,contacted,qualified,proposal_sent,won,lost',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'expected_value' => 'nullable|numeric|min:0',
            'assigned_to' => 'nullable|string|max:255',
            'source_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $oldStage = $lead->stage;
        $lead->update(array_filter($validated, fn ($val) => ! is_null($val)));

        if (isset($validated['stage']) && $validated['stage'] !== $oldStage) {
            LeadActivity::create([
                'company_id' => $company->id,
                'lead_id' => $lead->id,
                'type' => 'status_change',
                'title' => 'Stage Changed',
                'description' => "Stage moved from {$oldStage} to {$validated['stage']}.",
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return back()->with('status', __('Lead updated successfully.'));
    }

    public function destroy(Request $request, $id): RedirectResponse
    {
        $user = auth()->user();
        $company = $user?->company;

        abort_if(! $company, 403, 'Company not associated with user');

        $lead = Lead::where('company_id', $company->id)->findOrFail($id);
        $code = $lead->lead_code;
        $lead->delete();

        return back()->with('status', __("Lead :code deleted successfully.", ['code' => $code]));
    }

    public function storeActivity(Request $request, $id): RedirectResponse
    {
        $user = auth()->user();
        $company = $user?->company;

        abort_if(! $company, 403, 'Company not associated with user');

        $lead = Lead::where('company_id', $company->id)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'nullable|string|in:call,meeting,email,note,task',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        LeadActivity::create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'type' => $validated['type'] ?? 'note',
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => ! empty($validated['due_date']) ? 'pending' : 'completed',
            'due_date' => $validated['due_date'] ?? null,
            'completed_at' => empty($validated['due_date']) ? now() : null,
        ]);

        return back()->with('status', __('Activity logged successfully.'));
    }

    public function completeActivity(Request $request, $id): RedirectResponse
    {
        $user = auth()->user();
        $company = $user?->company;

        abort_if(! $company, 403, 'Company not associated with user');

        $activity = LeadActivity::where('company_id', $company->id)->findOrFail($id);
        $activity->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return back()->with('status', __('Follow-up activity marked completed.'));
    }
}
