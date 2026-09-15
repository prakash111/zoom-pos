<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\leadmanagement\Models\LeadActivity;

class LeadReminderController extends Controller
{
    /**
     * Store a scheduled follow-up reminder for a lead.
     * Harmonizes all SDUI form field aliases (notes, reminder_notes, call_script, description, etc.)
     */
    public function store(Request $request, string $leadId): JsonResponse
    {
        $user = auth()->user();
        $companyId = $user?->company_id ?? $user?->tenant_id ?? $request->header('X-Company-Id') ?? $request->header('X-Tenant-Id');

        $company = $user?->company;
        if (! $company && $companyId) {
            $company = Company::find($companyId);
        }

        // Resolve lead by numeric ID or alphanumeric code (e.g. LD-ARN04OGW)
        $leadQuery = Lead::query();
        if ($company) {
            $leadQuery->where('company_id', $company->id);
        }

        $lead = $leadQuery->where(function ($q) use ($leadId) {
            if (is_numeric($leadId)) {
                $q->where('id', $leadId)->orWhere('lead_code', $leadId);
            } else {
                $q->where('lead_code', $leadId);
            }
        })->first();

        if (! $lead) {
            $lead = Lead::where('id', $leadId)->orWhere('lead_code', $leadId)->firstOrFail();
        }

        $company = $company ?? $lead->company ?? Company::find($lead->company_id);
        $tenantId = $company?->id ?? $user?->tenant_id ?? $user?->company_id;

        $validated = $request->validate([
            'subject'           => 'nullable|string|max:255',
            'follow_up_subject' => 'nullable|string|max:255',
            'title'             => 'nullable|string|max:255',
            'due_at'            => 'nullable|string',
            'due_date'          => 'nullable|string',
            'reminder_due'      => 'nullable|string',
            'notes'             => 'nullable|string|max:2000',
            'reminder_notes'    => 'nullable|string|max:2000',
            'call_script'       => 'nullable|string|max:2000',
            'description'       => 'nullable|string|max:2000',
            'status'            => 'nullable|string|max:50',
        ]);

        // Extract the note/script regardless of which input name was passed
        $notes = $request->input('notes')
              ?? $request->input('reminder_notes')
              ?? $request->input('call_script')
              ?? $request->input('description')
              ?? $lead->requirement_summary
              ?? $lead->notes;

        $subject = $request->input('subject')
                ?? $request->input('follow_up_subject')
                ?? $request->input('title')
                ?? "Follow up with {$lead->name}";

        $dueAt = $request->input('due_at')
              ?? $request->input('due_date')
              ?? $request->input('reminder_due')
              ?? now()->addDay();

        $reminder = Reminder::create([
            'company_id'      => $tenantId,
            'tenant_id'       => $tenantId,
            'user_id'         => auth()->id(),
            'customer_id'     => $lead->customer_id,
            'remindable_type' => Lead::class,
            'remindable_id'   => $lead->id,
            'type'            => 'lead_followup',
            'subject'         => $subject,
            'title'           => $subject,
            'notes'           => $notes,
            'description'     => $notes,
            'call_script'     => $notes,
            'due_at'          => $dueAt,
            'due_date'        => $dueAt,
            'status'          => $request->input('status') ?: Reminder::STATUS_PENDING,
        ]);

        // Also record a pending activity in LeadActivity for unified audit trail
        try {
            LeadActivity::create([
                'company_id'  => $tenantId,
                'lead_id'     => $lead->id,
                'type'        => 'task',
                'title'       => 'Follow-up Reminder Scheduled',
                'description' => "Reminder set for " . ($reminder->due_date?->format('Y-m-d H:i') ?? $dueAt) . ": {$reminder->title}" . ($notes ? " — {$notes}" : ""),
                'due_date'    => $reminder->due_date,
                'status'      => 'pending',
            ]);
        } catch (\Throwable $e) {
            // Ignore activity logging failure
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Follow-up reminder scheduled successfully.',
            'reminder' => $reminder,
            'action'   => 'refresh_view',
        ]);
    }

    /**
     * Follow-ups & Reminders List (`GET /api/v1/tenant/leads/followups`).
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $companyId = $user?->company_id ?? $user?->tenant_id ?? $request->header('X-Company-Id') ?? $request->header('X-Tenant-Id');

        $company = $user?->company;
        if (! $company && $companyId) {
            $company = Company::find($companyId);
        }
        $tenantId = $company?->id ?? $companyId;

        $remindersQuery = Reminder::withoutGlobalScope('company')
            ->where(function ($q) use ($tenantId) {
                if ($tenantId) {
                    $q->where('company_id', $tenantId)->orWhere('tenant_id', $tenantId);
                }
            })
            ->where('status', 'pending')
            ->with(['customer', 'remindable'])
            ->orderBy('due_date');

        $reminders = $remindersQuery->limit(50)->get();

        $items = [];
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

            $items[] = [
                'type' => 'card',
                'components' => $components,
            ];
        }

        return response()->json([
            'success' => true,
            'reminders' => $items,
            'data' => $items,
            'items' => $items,
        ]);
    }
}
