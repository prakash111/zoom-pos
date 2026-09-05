<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesTenantSyncContext;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\CashRegisterTransaction;
use App\Services\CashRegister\CashRegisterReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Mirrors app/Livewire/Tenant/Financials/CashRegister.php's open/movement/close
 * logic exactly (same models, same CashRegisterReportService, same
 * CashRegister::computeMetrics() shift-metrics computation) so mobile Z-Reports
 * match the web dashboard's numbers.
 */
class CashRegisterApiController extends Controller
{
    use ResolvesTenantSyncContext;

    public function current(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $register = CashRegister::openFor($company->id);

        return response()->json([
            'success' => true,
            'register' => $register ? $this->present($register) : null,
        ]);
    }

    /** Unversioned SDUI alias for the native cash-register status URL. */
    public function status(Request $request): JsonResponse
    {
        return $this->current($request);
    }

    public function open(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_denominations' => ['nullable', 'array'],
            'opening_notes' => ['nullable', 'string', 'max:500'],
            'terminal_id' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error while opening register.',
                'details' => $validator->errors(),
            ], 422);
        }

        if (CashRegister::openFor($company->id)) {
            return response()->json(['success' => false, 'error' => 'A cash register session is already active.'], 422);
        }

        $data = $validator->validated();
        $denoms = array_filter($data['opening_denominations'] ?? [], fn ($q) => (int) $q > 0);

        $register = CashRegister::create([
            'company_id' => $company->id,
            'terminal_id' => $data['terminal_id'] ?? 'Main POS',
            'opened_by' => $user?->id,
            'opening_balance' => $data['opening_balance'],
            'opening_denominations' => $denoms ?: null,
            'opening_notes' => $data['opening_notes'] ?? null,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        AuditLog::record('cash_register.opened', $company->id, $user?->id, [
            'register_id' => $register->id,
            'terminal_id' => $register->terminal_id,
            'opening_balance' => (float) $data['opening_balance'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cash register opened.',
            'register' => $this->present($register),
        ], 201);
    }

    /** Unversioned SDUI alias retained separately from the desktop API. */
    public function openRegister(Request $request): JsonResponse
    {
        return $this->open($request);
    }

    public function recordTransaction(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);
        $register = $this->findRegister($company, $id);

        if (! $register || ! $register->isOpen()) {
            return response()->json(['success' => false, 'error' => 'No active open cash register session found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:cash_in,cash_out'],
            'category' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error while recording movement.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $voucherNum = app(CashRegisterReportService::class)
            ->generateVoucherNumber($company->id, $data['type'] === 'cash_in' ? 'SUP' : 'SAN');

        $metrics = $register->computeMetrics();
        $prevBalance = (float) $metrics['expected_cash'];
        $amount = (float) $data['amount'];
        $newBalance = $data['type'] === 'cash_in' ? ($prevBalance + $amount) : ($prevBalance - $amount);

        $tx = CashRegisterTransaction::create([
            'company_id' => $company->id,
            'cash_register_id' => $register->id,
            'voucher_number' => $voucherNum,
            'type' => $data['type'],
            'category' => $data['category'],
            'amount' => $amount,
            'balance_before' => $prevBalance,
            'balance_after' => $newBalance,
            'reason' => $data['reason'] ?? null,
            'created_by' => $user?->id,
        ]);

        AuditLog::record('cash_register.movement_recorded', $company->id, $user?->id, [
            'register_id' => $register->id,
            'voucher_number' => $voucherNum,
            'type' => $data['type'],
            'category' => $data['category'],
            'amount' => $amount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Movement recorded.',
            'transaction' => [
                'id' => $tx->id,
                'voucher_number' => $tx->voucher_number,
                'type' => $tx->type,
                'category' => $tx->category,
                'category_label' => $tx->getCategoryLabel(),
                'amount' => (float) $tx->amount,
                'balance_before' => (float) $tx->balance_before,
                'balance_after' => (float) $tx->balance_after,
                'reason' => $tx->reason,
                'created_at' => $tx->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function close(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $user = $this->resolveUser($request, $company);

        $validator = Validator::make($request->all(), [
            'counted_closing_balance' => ['required', 'numeric', 'min:0'],
            'closing_denominations' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation error while closing register.',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $register = DB::transaction(function () use ($company, $user, $id, $data) {
            $register = CashRegister::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('status', 'open')
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)->orWhere('external_id', $id);
                })
                ->lockForUpdate()
                ->first();

            if (! $register) {
                return null;
            }

            $metrics = $register->computeMetrics();
            $expected = (float) $metrics['expected_cash'];
            $difference = round((float) $data['counted_closing_balance'] - $expected, 2);
            $denoms = array_filter($data['closing_denominations'] ?? [], fn ($q) => (int) $q > 0);

            $register->update([
                'status' => 'closed',
                'closed_by' => $user?->id,
                'closed_at' => now(),
                'expected_closing_balance' => $expected,
                'counted_closing_balance' => $data['counted_closing_balance'],
                'closing_denominations' => $denoms ?: null,
                'cash_difference' => $difference,
                'notes' => $data['notes'] ?? null,
            ]);

            AuditLog::record('cash_register.closed', $company->id, $user?->id, [
                'register_id' => $register->id,
                'expected_closing_balance' => $expected,
                'counted_closing_balance' => (float) $data['counted_closing_balance'],
                'cash_difference' => $difference,
            ]);

            return $register;
        });

        if (! $register) {
            return response()->json(['success' => false, 'error' => 'No open cash register found.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cash register closed. Z-Report generated.',
            'register' => $this->present($register->fresh()),
        ]);
    }

    /**
     * Close the tenant's active register without requiring the SDUI client
     * to know its database identifier.
     */
    public function closeRegister(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $register = CashRegister::openFor($company->id);

        if (! $register) {
            return response()->json(['success' => false, 'error' => 'No open cash register found.'], 404);
        }

        return $this->close($request, (string) $register->id);
    }

    public function history(Request $request): JsonResponse
    {
        $company = $this->resolveCompany($request);

        $registers = CashRegister::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->orderByDesc('opened_at')
            ->limit(100)
            ->get()
            ->map(fn (CashRegister $r) => $this->present($r));

        return response()->json(['success' => true, 'registers' => $registers]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $company = $this->resolveCompany($request);
        $register = $this->findRegister($company, $id);

        if (! $register) {
            return response()->json(['success' => false, 'error' => 'Cash register not found.'], 404);
        }

        $transactions = $register->transactions->map(fn (CashRegisterTransaction $t) => [
            'id' => $t->id,
            'voucher_number' => $t->voucher_number,
            'type' => $t->type,
            'category' => $t->category,
            'category_label' => $t->getCategoryLabel(),
            'amount' => (float) $t->amount,
            'balance_before' => (float) $t->balance_before,
            'balance_after' => (float) $t->balance_after,
            'reason' => $t->reason,
            'created_at' => $t->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'register' => $this->present($register),
            'metrics' => $register->computeMetrics(),
            'transactions' => $transactions,
        ]);
    }

    private function findRegister($company, string $id): ?CashRegister
    {
        return CashRegister::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('external_id', $id);
            })
            ->first();
    }

    private function present(CashRegister $register): array
    {
        return [
            'id' => $register->id,
            'terminal_id' => $register->terminal_id ?? 'Main POS',
            'status' => $register->status,
            'opened_at' => $register->opened_at?->toIso8601String(),
            'closed_at' => $register->closed_at?->toIso8601String(),
            'opening_balance' => (float) $register->opening_balance,
            'expected_closing_balance' => $register->expected_closing_balance !== null ? (float) $register->expected_closing_balance : null,
            'counted_closing_balance' => $register->counted_closing_balance !== null ? (float) $register->counted_closing_balance : null,
            'cash_difference' => $register->cash_difference !== null ? (float) $register->cash_difference : null,
            'notes' => $register->notes,
            'opening_notes' => $register->opening_notes,
        ];
    }
}
