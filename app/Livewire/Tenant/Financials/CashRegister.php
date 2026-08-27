<?php

namespace App\Livewire\Tenant\Financials;

use App\Models\AuditLog;
use App\Models\CashRegister as CashRegisterModel;
use App\Models\CashRegisterTransaction;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use App\Services\CashRegister\CashRegisterReportService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.tenant', ['title' => 'Cash Register & Audit Ledger'])]
class CashRegister extends Component
{
    use WithPagination;

    // Filters for Audit Ledger
    public string $dateFrom = '';

    public string $dateTo = '';

    public string $cashierId = '';

    public string $statusFilter = 'all'; // all, open, closed, flagged_variance

    // Open Register Form Modal
    public bool $showOpenModal = false;

    public float $openingBalance = 0.0;

    public string $terminalId = 'Main POS Terminal';

    public string $openingNotes = '';

    public bool $showDenominationCounter = false;

    public array $denominations = [
        '500' => 0,
        '200' => 0,
        '100' => 0,
        '50' => 0,
        '20' => 0,
        '10' => 0,
        '5' => 0,
        '2' => 0,
        '1' => 0,
    ];

    // Add Movement (Sangria / Suprimento) Modal
    public bool $showMovementModal = false;

    public string $movementType = 'cash_out'; // cash_in | cash_out

    public string $movementCategory = 'sangria'; // sangria, suprimento, petty_cash, supplier_payment, bank_drop, change_injection, other

    public float $movementAmount = 0.0;

    public string $movementReason = '';

    public ?int $lastCreatedTransactionId = null;

    // Close Register Modal
    public bool $showCloseModal = false;

    public float $countedClosingBalance = 0.0;

    public float $closeExpectedCash = 0.0;

    public string $closeNotes = '';

    public array $closingDenominations = [
        '500' => 0,
        '200' => 0,
        '100' => 0,
        '50' => 0,
        '20' => 0,
        '10' => 0,
        '5' => 0,
        '2' => 0,
        '1' => 0,
    ];

    // Stale Shift Rollover Notice Modal
    public bool $showStaleShiftModal = false;

    // Shift Detail / Z-Report Viewer Modal
    public ?int $viewingRegisterId = null;

    protected function companyId(): ?string
    {
        return app()->bound('tenant.company_id')
            ? app('tenant.company_id')
            : auth('web')->user()?->company_id;
    }

    public function mount(): void
    {
        $user = auth('web')->user();
        if ($user && ! PermissionChecker::can($user, 'cash_register', 'view') && ! PermissionChecker::can($user, 'finance', 'view')) {
            abort(403, 'Unauthorized.');
        }

        $companyId = $this->companyId();
        $openRegister = CashRegisterModel::openFor($companyId);
        if ($openRegister && $openRegister->isStaleMidnight()) {
            $this->showStaleShiftModal = true;
        }
    }

    public function updatedDenominations(): void
    {
        $total = 0;
        foreach ($this->denominations as $val => $qty) {
            $total += ((float) $val) * ((int) $qty);
        }
        $this->openingBalance = round($total, 2);
    }

    public function updatedClosingDenominations(): void
    {
        $total = 0;
        foreach ($this->closingDenominations as $val => $qty) {
            $total += ((float) $val) * ((int) $qty);
        }
        $this->countedClosingBalance = round($total, 2);
    }

    public function openRegisterModal(): void
    {
        $this->openingBalance = 0.0;
        $this->openingNotes = '';
        $this->terminalId = 'Main POS Terminal';
        $this->denominations = array_fill_keys(array_keys($this->denominations), 0);
        $this->showOpenModal = true;
    }

    public function openRegister(): void
    {
        $this->validate([
            'openingBalance' => ['required', 'numeric', 'min:0'],
            'terminalId' => ['nullable', 'string', 'max:64'],
            'openingNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $companyId = $this->companyId();

        if (CashRegisterModel::openFor($companyId)) {
            session()->flash('error', 'A cash register session is already active.');
            $this->showOpenModal = false;

            return;
        }

        $activeDenoms = array_filter($this->denominations, fn ($q) => (int) $q > 0);

        $register = CashRegisterModel::create([
            'company_id' => $companyId,
            'terminal_id' => $this->terminalId ?: 'Main POS',
            'opened_by' => auth('web')->id(),
            'opening_balance' => $this->openingBalance,
            'opening_denominations' => ! empty($activeDenoms) ? $activeDenoms : null,
            'opening_notes' => $this->openingNotes ?: null,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        AuditLog::record('cash_register.opened', $companyId, auth('web')->id(), [
            'register_id' => $register->id,
            'terminal_id' => $register->terminal_id,
            'opening_balance' => $this->openingBalance,
        ]);

        $this->showOpenModal = false;
        $this->showStaleShiftModal = false;
        session()->flash('status', "Cash register opened successfully with starting float of \${$this->openingBalance}.");
    }

    public function openMovementModal(string $type, string $category = ''): void
    {
        $this->movementType = $type;
        $this->movementCategory = $category ?: ($type === 'cash_in' ? 'suprimento' : 'sangria');
        $this->movementAmount = 0.0;
        $this->movementReason = '';
        $this->showMovementModal = true;
    }

    public function recordMovement(): void
    {
        $this->validate([
            'movementAmount' => ['required', 'numeric', 'min:0.01'],
            'movementCategory' => ['required', 'string', 'max:64'],
            'movementReason' => ['nullable', 'string', 'max:255'],
        ]);

        $companyId = $this->companyId();
        $register = CashRegisterModel::openFor($companyId);
        if (! $register) {
            session()->flash('error', 'No active open cash register session found.');
            $this->showMovementModal = false;

            return;
        }

        $reportService = app(CashRegisterReportService::class);
        $voucherNum = $reportService->generateVoucherNumber($companyId, $this->movementType === 'cash_in' ? 'SUP' : 'SAN');

        $metrics = $register->computeMetrics();
        $prevBalance = (float) $metrics['expected_cash'];
        $amount = (float) $this->movementAmount;
        $newBalance = $this->movementType === 'cash_in' ? ($prevBalance + $amount) : ($prevBalance - $amount);

        $tx = CashRegisterTransaction::create([
            'company_id' => $companyId,
            'cash_register_id' => $register->id,
            'voucher_number' => $voucherNum,
            'type' => $this->movementType,
            'category' => $this->movementCategory,
            'amount' => $amount,
            'balance_before' => $prevBalance,
            'balance_after' => $newBalance,
            'reason' => $this->movementReason ?: null,
            'created_by' => auth('web')->id(),
        ]);

        AuditLog::record('cash_register.movement_recorded', $companyId, auth('web')->id(), [
            'register_id' => $register->id,
            'voucher_number' => $voucherNum,
            'type' => $this->movementType,
            'category' => $this->movementCategory,
            'amount' => $amount,
            'balance_before' => $prevBalance,
            'balance_after' => $newBalance,
        ]);

        $this->lastCreatedTransactionId = $tx->id;
        $this->showMovementModal = false;

        $catLabel = $tx->getCategoryLabel();
        session()->flash('status', "{$catLabel} of \${$amount} recorded successfully. Voucher #{$voucherNum}.");
    }

    public function openCloseModal(): void
    {
        $companyId = $this->companyId();
        $register = CashRegisterModel::openFor($companyId);
        if (! $register) {
            return;
        }

        $metrics = $register->computeMetrics();
        $this->closeExpectedCash = (float) $metrics['expected_cash'];
        $this->countedClosingBalance = (float) $metrics['expected_cash'];
        $this->closingDenominations = array_fill_keys(array_keys($this->closingDenominations), 0);
        $this->closeNotes = '';
        $this->showCloseModal = true;
    }

    public function closeRegister(): void
    {
        $this->validate([
            'countedClosingBalance' => ['required', 'numeric', 'min:0'],
            'closeNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $companyId = $this->companyId();

        $closedRegisterId = null;

        DB::transaction(function () use ($companyId, &$closedRegisterId) {
            $register = CashRegisterModel::where('company_id', $companyId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if (! $register) {
                session()->flash('error', 'No open cash register.');

                return;
            }

            $metrics = $register->computeMetrics();
            $expected = (float) $metrics['expected_cash'];
            $difference = round($this->countedClosingBalance - $expected, 2);
            $activeDenoms = array_filter($this->closingDenominations, fn ($q) => (int) $q > 0);

            $register->update([
                'status' => 'closed',
                'closed_by' => auth('web')->id(),
                'closed_at' => now(),
                'expected_closing_balance' => $expected,
                'counted_closing_balance' => $this->countedClosingBalance,
                'closing_denominations' => ! empty($activeDenoms) ? $activeDenoms : null,
                'cash_difference' => $difference,
                'notes' => $this->closeNotes ?: null,
            ]);

            AuditLog::record('cash_register.closed', $companyId, auth('web')->id(), [
                'register_id' => $register->id,
                'expected_closing_balance' => $expected,
                'counted_closing_balance' => $this->countedClosingBalance,
                'cash_difference' => $difference,
            ]);

            $closedRegisterId = $register->id;
        });

        $this->showCloseModal = false;
        $this->showStaleShiftModal = false;

        if ($closedRegisterId) {
            $this->viewingRegisterId = $closedRegisterId;
            session()->flash('status', 'Cash register closed and settled. Shift Z-Report generated.');
        }
    }

    public function buildReportSummary(CashRegisterModel $register): array
    {
        return $register->computeMetrics();
    }

    public function getCloseVarianceProperty(): float
    {
        return round($this->countedClosingBalance - $this->closeExpectedCash, 2);
    }

    public function viewRegister(int $id): void
    {
        $this->viewingRegisterId = $id;
    }

    public function closeViewRegister(): void
    {
        $this->viewingRegisterId = null;
    }

    public function resetFilters(): void
    {
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->cashierId = '';
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    public function render()
    {
        $companyId = $this->companyId();
        $openRegister = CashRegisterModel::openFor($companyId);
        $liveMetrics = $openRegister ? $openRegister->computeMetrics() : null;

        // Build audit ledger query with filters
        $historyQuery = CashRegisterModel::with('opener', 'closer')
            ->where('company_id', $companyId);

        if ($this->statusFilter === 'open') {
            $historyQuery->where('status', 'open');
        } elseif ($this->statusFilter === 'closed') {
            $historyQuery->where('status', 'closed');
        } elseif ($this->statusFilter === 'flagged_variance') {
            $historyQuery->where('status', 'closed')->where('cash_difference', '!=', 0);
        }

        if (! empty($this->dateFrom)) {
            $historyQuery->whereDate('opened_at', '>=', $this->dateFrom);
        }
        if (! empty($this->dateTo)) {
            $historyQuery->whereDate('opened_at', '<=', $this->dateTo);
        }

        if (! empty($this->cashierId)) {
            $historyQuery->where(function ($q) {
                $q->where('opened_by', $this->cashierId)
                    ->orWhere('closed_by', $this->cashierId);
            });
        }

        $history = $historyQuery->orderByDesc('opened_at')->paginate(12);

        $viewingRegister = $this->viewingRegisterId
            ? CashRegisterModel::with('opener', 'closer', 'transactions.creator')->find($this->viewingRegisterId)
            : null;
        $viewingMetrics = $viewingRegister ? $viewingRegister->computeMetrics() : null;

        $cashiers = User::where('company_id', $companyId)->orderBy('name')->get();
        $reportService = app(CashRegisterReportService::class);

        return view('livewire.tenant.financials.cash-register', [
            'openRegister' => $openRegister,
            'liveMetrics' => $liveMetrics,
            'movements' => $openRegister ? $openRegister->transactions : collect(),
            'history' => $history,
            'viewingRegister' => $viewingRegister,
            'viewingMetrics' => $viewingMetrics,
            'cashiers' => $cashiers,
            'reportService' => $reportService,
        ]);
    }
}
