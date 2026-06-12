<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\mobile\Bank\DepositRequest;
use App\Http\Requests\API\v1\mobile\Bank\TransferRequest;
use App\Http\Requests\API\v1\mobile\Bank\WithdrawalRequest;
use App\Http\Resources\BankResource;
use App\Http\Resources\BankTransactionResource;
use App\Http\Traits\ApiResponse;
use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankController extends Controller
{
    use ApiResponse;

    /**
     * Get all banks/accounts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Bank::query();

        // Filter by account type
        if ($request->has('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        // Filter e-wallets only
        if ($request->boolean('ewallets_only')) {
            $query->where('account_type', Bank::TYPE_EWALLET);
        }

        // Search by name or account number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('bank_name', 'like', "%{$search}%")
                    ->orWhere('account_name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%");
            });
        }

        $banks = $query->orderBy('bank_name')->get();

        return $this->success([
            'banks' => BankResource::collection($banks),
            'account_types' => BankResource::accountTypes(),
        ]);
    }

    /**
     * Get a specific bank with recent transactions.
     */
    public function show(Bank $bank): JsonResponse
    {
        $bank->load(['transactions' => function ($query) {
            $query->with('createdBy', 'transferToBank')
                ->latest('transaction_date')
                ->latest('id')
                ->limit(20);
        }]);

        return $this->success([
            'bank' => new BankResource($bank),
        ]);
    }

    /**
     * Get transactions for a bank with filtering and pagination.
     */
    public function transactions(Request $request, Bank $bank): JsonResponse
    {
        $query = $bank->transactions()
            ->with(['createdBy', 'transferToBank']);

        // Filter by transaction type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }

        // Search by reference number, description, or payee
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('payee', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $transactions = $query->latest('transaction_date')
            ->latest('id')
            ->paginate($perPage);

        return $this->success([
            'transactions' => BankTransactionResource::collection($transactions),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
            'transaction_types' => BankTransactionResource::transactionTypes(),
        ]);
    }

    /**
     * Record a deposit.
     */
    public function deposit(DepositRequest $request, Bank $bank): JsonResponse
    {
        $validated = $request->validated();

        $transaction = DB::transaction(function () use ($validated, $bank) {
            $balanceBefore = $bank->balance;
            $balanceAfter = $balanceBefore + $validated['amount'];

            $transaction = BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_DEPOSIT,
                'amount' => $validated['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $validated['description'] ?? null,
                'payee' => $validated['payee'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'created_by' => auth()->id(),
            ]);

            $bank->update(['balance' => $balanceAfter]);

            return $transaction;
        });

        $transaction->load('createdBy');

        return $this->created([
            'transaction' => new BankTransactionResource($transaction),
            'new_balance' => $bank->fresh()->balance,
        ], 'Deposit of '.number_format($validated['amount'], 2).' recorded successfully.');
    }

    /**
     * Record a withdrawal.
     */
    public function withdrawal(WithdrawalRequest $request, Bank $bank): JsonResponse
    {
        $validated = $request->validated();

        $transaction = DB::transaction(function () use ($validated, $bank) {
            $balanceBefore = $bank->balance;
            $balanceAfter = $balanceBefore - $validated['amount'];

            $transaction = BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_WITHDRAWAL,
                'amount' => $validated['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $validated['description'] ?? null,
                'payee' => $validated['payee'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'created_by' => auth()->id(),
            ]);

            $bank->update(['balance' => $balanceAfter]);

            return $transaction;
        });

        $transaction->load('createdBy');

        return $this->created([
            'transaction' => new BankTransactionResource($transaction),
            'new_balance' => $bank->fresh()->balance,
        ], 'Withdrawal of '.number_format($validated['amount'], 2).' recorded successfully.');
    }

    /**
     * Transfer funds between accounts.
     */
    public function transfer(TransferRequest $request, Bank $bank): JsonResponse
    {
        $validated = $request->validated();
        $destinationBank = Bank::findOrFail($validated['transfer_to_bank_id']);

        $result = DB::transaction(function () use ($validated, $bank, $destinationBank) {
            $referenceNumber = BankTransaction::generateReferenceNumber();

            // Source account - Transfer Out
            $sourceBalanceBefore = $bank->balance;
            $sourceBalanceAfter = $sourceBalanceBefore - $validated['amount'];

            $outTransaction = BankTransaction::create([
                'reference_number' => $referenceNumber,
                'bank_id' => $bank->id,
                'transfer_to_bank_id' => $destinationBank->id,
                'type' => BankTransaction::TYPE_TRANSFER_OUT,
                'amount' => $validated['amount'],
                'balance_before' => $sourceBalanceBefore,
                'balance_after' => $sourceBalanceAfter,
                'description' => $validated['description'] ?? 'Transfer to '.$destinationBank->account_name,
                'transaction_date' => $validated['transaction_date'],
                'created_by' => auth()->id(),
            ]);

            $bank->update(['balance' => $sourceBalanceAfter]);

            // Destination account - Transfer In
            $destBalanceBefore = $destinationBank->balance;
            $destBalanceAfter = $destBalanceBefore + $validated['amount'];

            $inTransaction = BankTransaction::create([
                'reference_number' => $referenceNumber.'-IN',
                'bank_id' => $destinationBank->id,
                'transfer_to_bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_TRANSFER_IN,
                'amount' => $validated['amount'],
                'balance_before' => $destBalanceBefore,
                'balance_after' => $destBalanceAfter,
                'description' => $validated['description'] ?? 'Transfer from '.$bank->account_name,
                'transaction_date' => $validated['transaction_date'],
                'created_by' => auth()->id(),
            ]);

            $destinationBank->update(['balance' => $destBalanceAfter]);

            return [
                'out_transaction' => $outTransaction,
                'in_transaction' => $inTransaction,
            ];
        });

        $result['out_transaction']->load('createdBy', 'transferToBank');
        $result['in_transaction']->load('createdBy', 'transferToBank');

        return $this->created([
            'source_transaction' => new BankTransactionResource($result['out_transaction']),
            'destination_transaction' => new BankTransactionResource($result['in_transaction']),
            'source_new_balance' => $bank->fresh()->balance,
            'destination_new_balance' => $destinationBank->fresh()->balance,
        ], 'Transfer of '.number_format($validated['amount'], 2).' to '.$destinationBank->account_name.' completed successfully.');
    }

    /**
     * Get banking dashboard summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $period = $request->input('period', 'this_month');
        [$startDate, $endDate] = $this->getDateRange($period);

        // Get total balances by account type
        $balancesByType = Bank::query()
            ->selectRaw('account_type, SUM(balance) as total_balance, COUNT(*) as account_count')
            ->groupBy('account_type')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->account_type => [
                    'total_balance' => (float) $item->total_balance,
                    'account_count' => (int) $item->account_count,
                ],
            ]);

        // Get transaction totals for the period
        $transactionTotals = BankTransaction::query()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->selectRaw('
                SUM(CASE WHEN type IN (1, 4) THEN amount ELSE 0 END) as total_deposits,
                SUM(CASE WHEN type IN (2, 3) THEN amount ELSE 0 END) as total_withdrawals,
                COUNT(CASE WHEN type = 1 THEN 1 END) as deposit_count,
                COUNT(CASE WHEN type = 2 THEN 1 END) as withdrawal_count,
                COUNT(CASE WHEN type IN (3, 4) THEN 1 END) / 2 as transfer_count
            ')
            ->first();

        // Get recent transactions across all accounts
        $recentTransactions = BankTransaction::with(['bank', 'createdBy', 'transferToBank'])
            ->latest('transaction_date')
            ->latest('id')
            ->limit(10)
            ->get();

        return $this->success([
            'total_balance' => Bank::sum('balance'),
            'balances_by_type' => $balancesByType,
            'period' => $period,
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'transaction_summary' => [
                'total_deposits' => (float) ($transactionTotals->total_deposits ?? 0),
                'total_withdrawals' => (float) ($transactionTotals->total_withdrawals ?? 0),
                'net_flow' => (float) (($transactionTotals->total_deposits ?? 0) - ($transactionTotals->total_withdrawals ?? 0)),
                'deposit_count' => (int) ($transactionTotals->deposit_count ?? 0),
                'withdrawal_count' => (int) ($transactionTotals->withdrawal_count ?? 0),
                'transfer_count' => (int) ($transactionTotals->transfer_count ?? 0),
            ],
            'recent_transactions' => BankTransactionResource::collection($recentTransactions),
        ]);
    }

    /**
     * Get date range based on period string.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function getDateRange(string $period): array
    {
        return match ($period) {
            'today' => [
                Carbon::today()->startOfDay(),
                Carbon::today()->endOfDay(),
            ],
            'yesterday' => [
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->endOfDay(),
            ],
            'this_week' => [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfDay(),
            ],
            'last_week' => [
                Carbon::now()->subWeek()->startOfWeek(),
                Carbon::now()->subWeek()->endOfWeek(),
            ],
            'this_month' => [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfDay(),
            ],
            'last_month' => [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ],
            'this_year' => [
                Carbon::now()->startOfYear(),
                Carbon::now()->endOfDay(),
            ],
            default => [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfDay(),
            ],
        };
    }
}
