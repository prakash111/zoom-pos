<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Models\AuditLog;
use App\Models\SalesTarget;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Mirrors app/Livewire/Tenant/SalesTargets/Index.php: a company-wide target
 * plus one target per staff member for a given year/month, each with
 * SalesTarget::getProgress()'s achieved/percentage/sales_count — same
 * method the web page calls, so the numbers match exactly.
 */
class SalesTargetApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function index(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $companyTarget = SalesTarget::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereNull('user_id')
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $overallProgress = SalesTarget::getProgress($company->id, null, $year, $month);

        $users = User::withoutGlobalScope('company')->where('company_id', $company->id)->orderBy('name')->get();
        $userTargets = $users->map(function (User $u) use ($company, $year, $month) {
            $target = SalesTarget::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('user_id', $u->id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            $progress = SalesTarget::getProgress($company->id, $u->id, $year, $month);

            return [
                'user_id' => (string) $u->id,
                'name' => $u->name,
                'target_amount' => (float) ($target?->target_amount ?? 0),
                'achieved_amount' => (float) $progress['achieved'],
                'percentage' => (float) $progress['percentage'],
                'sales_count' => (int) $progress['sales_count'],
            ];
        });

        $daysInMonth = (int) now()->setYear($year)->setMonth($month)->daysInMonth;
        $currentDay = ($year === (int) now()->year && $month === (int) now()->month) ? (int) now()->day : $daysInMonth;
        $remainingDays = max(1, $daysInMonth - $currentDay);
        $dailyRunRateNeeded = $overallProgress['remaining'] > 0 ? round($overallProgress['remaining'] / $remainingDays, 2) : 0;

        return response()->json([
            'success' => true,
            'year' => $year,
            'month' => $month,
            'company_target_amount' => (float) ($companyTarget?->target_amount ?? 0),
            'company_notes' => $companyTarget?->notes ?? '',
            'overall_progress' => $overallProgress,
            'remaining_days' => $remainingDays,
            'daily_run_rate_needed' => $dailyRunRateNeeded,
            'user_targets' => $userTargets,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'company_target_amount' => ['nullable', 'numeric', 'min:0'],
            'company_notes' => ['nullable', 'string', 'max:500'],
            'user_targets' => ['nullable', 'array'],
            'user_targets.*.user_id' => ['required'],
            'user_targets.*.target_amount' => ['required', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        SalesTarget::withoutGlobalScope('company')->updateOrCreate(
            ['company_id' => $company->id, 'user_id' => null, 'year' => $data['year'], 'month' => $data['month']],
            ['target_amount' => max(0, (float) ($data['company_target_amount'] ?? 0)), 'notes' => $data['company_notes'] ?? null],
        );

        foreach ($data['user_targets'] ?? [] as $ut) {
            SalesTarget::withoutGlobalScope('company')->updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $ut['user_id'], 'year' => $data['year'], 'month' => $data['month']],
                ['target_amount' => max(0, (float) $ut['target_amount'])],
            );
        }

        AuditLog::record('sales_targets.updated', $company->id, $user?->id, ['year' => $data['year'], 'month' => $data['month']]);

        return response()->json(['success' => true, 'message' => 'Sales targets saved.']);
    }
}
