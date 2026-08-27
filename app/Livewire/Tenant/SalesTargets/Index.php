<?php

namespace App\Livewire\Tenant\SalesTargets;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.tenant', ['title' => 'Sales Targets & Goals'])]
class Index extends Component
{
    public int $year;

    public int $month;

    public float $companyTargetAmount = 0.0;

    public string $companyNotes = '';

    /** @var array<int, array{user_id: int, name: string, email: string, target_amount: float, achieved_amount: float, percentage: float, sales_count: int}> */
    public array $userTargets = [];

    public bool $showEditModal = false;

    public function mount(): void
    {
        $user = auth('web')->user();
        if ($user && ! PermissionChecker::can($user, 'targets', 'view') && ! PermissionChecker::can($user, 'reports', 'view')) {
            abort(403, 'Unauthorized.');
        }

        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
        $this->loadTargets();
    }

    public function updatedYear(): void
    {
        $this->loadTargets();
    }

    public function updatedMonth(): void
    {
        $this->loadTargets();
    }

    public function loadTargets(): void
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        if (! $companyId) {
            return;
        }

        // Store overall target
        $compTarget = SalesTarget::where('company_id', $companyId)
            ->whereNull('user_id')
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        $this->companyTargetAmount = $compTarget ? (float) $compTarget->target_amount : 0.0;
        $this->companyNotes = $compTarget ? (string) $compTarget->notes : '';

        // Staff targets
        $users = User::where('company_id', $companyId)->get();
        $this->userTargets = [];

        foreach ($users as $u) {
            $t = SalesTarget::where('company_id', $companyId)
                ->where('user_id', $u->id)
                ->where('year', $this->year)
                ->where('month', $this->month)
                ->first();

            $targetAmt = $t ? (float) $t->target_amount : 0.0;
            $progress = SalesTarget::getProgress($companyId, $u->id, $this->year, $this->month);

            $this->userTargets[] = [
                'user_id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'target_amount' => $targetAmt,
                'achieved_amount' => $progress['achieved'],
                'percentage' => $progress['percentage'],
                'sales_count' => $progress['sales_count'],
            ];
        }
    }

    public function splitEvenly(): void
    {
        if ($this->companyTargetAmount <= 0 || empty($this->userTargets)) {
            return;
        }

        $count = count($this->userTargets);
        $split = round($this->companyTargetAmount / $count, 2);

        foreach ($this->userTargets as $idx => $ut) {
            $this->userTargets[$idx]['target_amount'] = $split;
        }

        session()->flash('status', "Company target split evenly ({$split} per salesperson). Click Save Targets to apply.");
    }

    public function save(): void
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        if (! $companyId) {
            return;
        }

        // Save company target
        SalesTarget::updateOrCreate(
            [
                'company_id' => $companyId,
                'user_id' => null,
                'year' => $this->year,
                'month' => $this->month,
            ],
            [
                'target_amount' => max(0, $this->companyTargetAmount),
                'notes' => $this->companyNotes ?: null,
            ]
        );

        // Save staff targets
        foreach ($this->userTargets as $ut) {
            SalesTarget::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'user_id' => $ut['user_id'],
                    'year' => $this->year,
                    'month' => $this->month,
                ],
                [
                    'target_amount' => max(0, (float) ($ut['target_amount'] ?? 0)),
                ]
            );
        }

        AuditLog::record('sales_targets.updated', $companyId, auth('web')->id(), [
            'year' => $this->year,
            'month' => $this->month,
            'company_target' => $this->companyTargetAmount,
        ]);

        $this->loadTargets();
        $this->showEditModal = false;
        session()->flash('status', 'Sales targets saved successfully!');
    }

    public function render()
    {
        $companyId = app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;

        $overallProgress = SalesTarget::getProgress($companyId, null, $this->year, $this->month);
        $company = Company::find($companyId);

        // Calculate days remaining in month for run-rate
        $daysInMonth = (int) now()->setYear($this->year)->setMonth($this->month)->daysInMonth;
        $currentDay = $this->year === now()->year && $this->month === now()->month ? (int) now()->day : $daysInMonth;
        $remainingDays = max(1, $daysInMonth - $currentDay);
        $dailyRunRateNeeded = $overallProgress['remaining'] > 0 ? round($overallProgress['remaining'] / $remainingDays, 2) : 0;

        return view('livewire.tenant.sales-targets.index', [
            'overallProgress' => $overallProgress,
            'company' => $company,
            'remainingDays' => $remainingDays,
            'dailyRunRateNeeded' => $dailyRunRateNeeded,
        ]);
    }
}
