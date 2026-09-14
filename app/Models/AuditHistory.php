<?php

namespace App\Models;

use Modules\leadmanagement\Models\LeadActivity;

class AuditHistory
{
    /**
     * Log an activity or audit trail entry for a Lead.
     */
    public static function log($leadId, string $description, ?string $type = 'quotation'): void
    {
        try {
            $lead = Lead::find($leadId);
            $companyId = $lead?->company_id ?? auth()->user()?->company_id ?? auth()->user()?->tenant_id;
            if ($lead && $companyId) {
                LeadActivity::create([
                    'company_id' => $companyId,
                    'lead_id' => $leadId,
                    'type' => $type ?: 'quotation',
                    'title' => 'Quotation Dispatched',
                    'description' => $description,
                    'status' => 'completed',
                    'completed_at' => now(),
                    'due_date' => now(),
                ]);
            }

            if ($companyId) {
                AuditLog::record(
                    action: 'quotation_dispatched',
                    companyId: (string) $companyId,
                    userId: (string) (auth()->id() ?? ''),
                    details: [
                        'lead_id' => $leadId,
                        'description' => $description,
                    ]
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('AuditHistory::log failed: ' . $e->getMessage());
        }
    }
}
