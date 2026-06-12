<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\openclaw\Bank\DepositRequest;
use App\Http\Requests\API\v1\openclaw\Bank\TransferRequest;
use App\Http\Requests\API\v1\openclaw\Bank\WithdrawalRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Services\ReceiptStorage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Read-only banking endpoints for OpenClaw.
 *
 * Note: the underlying banks and bank_transactions tables have no user_id
 * column in this codebase, so the data is global to the installation.
 * This mirrors the existing /api/v1/mobile/banks behavior — neither this
 * controller nor the mobile one applies tenant scoping. If true multi-
 * tenant isolation is required for banking, banks would need a user_id
 * column and a back-fill migration; that is a separate change.
 */
class BankController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/openclaw/banks/balances — lean id + name + balance per account.
     *
     * Designed for "what's my bank balance right now" prompts where the caller
     * doesn't need transactions, account numbers, or type metadata. Includes
     * the per-bank low_balance_threshold and a derived below_alert flag so
     * the bot can warn the user without a second query.
     */
    public function balances(Request $request): JsonResponse
    {
        $banks = Bank::query()
            ->orderBy('bank_name')
            ->get(['id', 'bank_name', 'account_name', 'account_type', 'balance', 'low_balance_threshold']);

        return $this->success([
            'as_of' => now()->toIso8601String(),
            'total_balance' => round((float) $banks->sum('balance'), 2),
            'accounts' => $banks->map(fn (Bank $b) => [
                'id' => $b->id,
                'bank_name' => $b->bank_name,
                'account_name' => $b->account_name,
                'account_type_name' => $b->account_type_name,
                'balance' => round((float) $b->balance, 2),
                'low_balance_threshold' => $b->low_balance_threshold !== null ? round((float) $b->low_balance_threshold, 2) : null,
                'below_alert' => $b->low_balance_threshold !== null
                    && (float) $b->balance <= (float) $b->low_balance_threshold,
            ])->values(),
        ]);
    }

    /**
     * PATCH /v1/openclaw/banks/{bank}/alert — set or clear low_balance_threshold.
     */
    public function setAlert(Request $request, Bank $bank): JsonResponse
    {
        $validated = $request->validate([
            'low_balance_threshold' => 'present|nullable|numeric|min:0',
        ]);

        $bank->forceFill(['low_balance_threshold' => $validated['low_balance_threshold']])->save();

        return $this->success([
            'bank' => [
                'id' => $bank->id,
                'bank_name' => $bank->bank_name,
                'account_name' => $bank->account_name,
                'balance' => round((float) $bank->balance, 2),
                'low_balance_threshold' => $bank->low_balance_threshold !== null
                    ? round((float) $bank->low_balance_threshold, 2)
                    : null,
                'below_alert' => $bank->low_balance_threshold !== null
                    && (float) $bank->balance <= (float) $bank->low_balance_threshold,
            ],
        ], $validated['low_balance_threshold'] === null
            ? "Low-balance alert cleared for {$bank->bank_name}."
            : "Low-balance alert set to {$validated['low_balance_threshold']} for {$bank->bank_name}.");
    }

    /**
     * GET /v1/openclaw/banks/accounts — all bank accounts (full detail).
     */
    public function accounts(Request $request): JsonResponse
    {
        $request->validate([
            'account_type' => 'nullable|integer|in:0,1,2,3,4',
        ]);

        $query = Bank::query();

        if ($request->filled('account_type')) {
            $query->where('account_type', (int) $request->input('account_type'));
        }

        $banks = $query->orderBy('bank_name')->get();

        return $this->success([
            'accounts' => $banks->map(fn (Bank $b) => [
                'id' => $b->id,
                'bank_name' => $b->bank_name,
                'account_name' => $b->account_name,
                'account_number' => $b->account_number,
                'account_type' => $b->account_type,
                'account_type_name' => $b->account_type_name,
                'opening_balance' => round((float) $b->opening_balance, 2),
                'balance' => round((float) $b->balance, 2),
                'low_balance_threshold' => $b->low_balance_threshold !== null
                    ? round((float) $b->low_balance_threshold, 2)
                    : null,
                'below_alert' => $b->low_balance_threshold !== null
                    && (float) $b->balance <= (float) $b->low_balance_threshold,
            ])->values(),
            'account_types' => $this->accountTypeMap(),
        ]);
    }

    /**
     * GET /v1/openclaw/banking/summary — totals across all accounts.
     */
    public function summary(Request $request): JsonResponse
    {
        $totals = Bank::query()
            ->selectRaw('account_type, COUNT(*) as accounts, COALESCE(SUM(balance), 0) as total_balance')
            ->groupBy('account_type')
            ->get();

        $byType = $totals->mapWithKeys(fn ($row) => [
            $this->accountTypeName($row->account_type) => [
                'accounts' => (int) $row->accounts,
                'total_balance' => round((float) $row->total_balance, 2),
            ],
        ]);

        return $this->success([
            'totals' => [
                'account_count' => (int) $totals->sum('accounts'),
                'total_balance' => round((float) $totals->sum('total_balance'), 2),
            ],
            'by_account_type' => $byType,
        ]);
    }

    /**
     * GET /v1/openclaw/banking/transactions — recent transactions across accounts.
     */
    public function transactions(Request $request): JsonResponse
    {
        $request->validate([
            'bank_id' => 'nullable|integer|min:1',
            'type' => 'nullable|integer|in:1,2,3,4',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $tz = config('app.timezone');
        $to = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'), $tz)->toDateString()
            : Carbon::today($tz)->toDateString();
        $from = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'), $tz)->toDateString()
            : Carbon::today($tz)->subDays(29)->toDateString();
        $limit = (int) $request->input('limit', 100);

        $rows = BankTransaction::query()
            ->with(['bank:id,bank_name,account_type', 'transferToBank:id,bank_name'])
            ->whereBetween('transaction_date', [$from, $to])
            ->when($request->filled('bank_id'), fn ($q) => $q->where('bank_id', (int) $request->input('bank_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', (int) $request->input('type')))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (BankTransaction $t) => $this->presentTransaction($t));

        return $this->success([
            'date_from' => $from,
            'date_to' => $to,
            'limit' => $limit,
            'transactions' => $rows,
        ]);
    }

    /**
     * POST /v1/openclaw/banks/{bank}/adjustment — reconcile Apex's tracked balance
     * with reality (bank passbook, statement, etc.).
     *
     * Records a bank_transaction with an ADJ- reference prefix and the supplied
     * reason. Type is DEPOSIT if the balance went up, WITHDRAWAL if it went
     * down. Sending a target equal to the current balance is a no-op (returns
     * 200 without creating a transaction). Atomic via DB transaction.
     *
     * Accepts EITHER:
     *   { "new_balance": 1855633.09, "reason": "Passbook reconciliation" }
     * OR:
     *   { "amount": -1050371.68, "reason": "..." }
     *
     * Use new_balance for reconciliation (the bot has the passbook number);
     * use amount when you have a known correction to apply.
     */
    public function adjust(Request $request, Bank $bank): JsonResponse
    {
        $validated = $request->validate([
            'new_balance' => 'sometimes|numeric',
            'amount' => 'sometimes|numeric',
            'reason' => 'required|string|min:3|max:500',
            'transaction_date' => 'nullable|date',
        ]);

        $hasNewBalance = array_key_exists('new_balance', $validated);
        $hasAmount = array_key_exists('amount', $validated);

        if ($hasNewBalance === $hasAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Provide exactly one of new_balance or amount, not both.',
            ]);
        }

        $balanceBefore = (float) $bank->balance;
        $balanceAfter = $hasNewBalance
            ? (float) $validated['new_balance']
            : $balanceBefore + (float) $validated['amount'];
        $delta = $balanceAfter - $balanceBefore;

        if (abs($delta) < 0.005) {
            return $this->success([
                'bank' => [
                    'id' => $bank->id,
                    'name' => $bank->bank_name,
                    'account_name' => $bank->account_name,
                    'old_balance' => round($balanceBefore, 2),
                    'new_balance' => round($balanceBefore, 2),
                ],
                'adjustment_transaction' => null,
            ], 'Balance already matches; no adjustment recorded.');
        }

        $transactionDate = $validated['transaction_date']
            ?? now(config('app.timezone'))->toDateString();

        $tx = DB::transaction(function () use ($bank, $balanceBefore, $balanceAfter, $delta, $validated, $transactionDate) {
            $tx = BankTransaction::create([
                'reference_number' => $this->generateAdjustmentReference(),
                'bank_id' => $bank->id,
                'type' => $delta > 0 ? BankTransaction::TYPE_DEPOSIT : BankTransaction::TYPE_WITHDRAWAL,
                'amount' => abs($delta),
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => 'Reconciliation adjustment: '.$validated['reason'],
                'payee' => 'Reconciliation',
                'transaction_date' => $transactionDate,
                'created_by' => (int) auth()->id(),
            ]);

            $bank->update(['balance' => $balanceAfter]);

            return $tx;
        });

        $bank->refresh();

        return $this->success([
            'bank' => [
                'id' => $bank->id,
                'name' => $bank->bank_name,
                'account_name' => $bank->account_name,
                'old_balance' => round($balanceBefore, 2),
                'new_balance' => round((float) $bank->balance, 2),
            ],
            'adjustment_transaction' => [
                'id' => $tx->id,
                'reference_number' => $tx->reference_number,
                'type' => $tx->type_name,
                'amount' => round((float) $tx->amount, 2),
                'delta' => round($delta, 2),
            ],
        ], sprintf(
            'Balance adjusted by %s%.2f.',
            $delta > 0 ? '+' : '-',
            abs($delta),
        ));
    }

    private function generateAdjustmentReference(): string
    {
        return sprintf('ADJ-%s-%s', now()->format('Ymd'), strtoupper(substr(uniqid(), -6)));
    }

    /**
     * POST /v1/openclaw/banks/{bank}/deposit — record an incoming deposit.
     *
     * Increases the bank's balance and writes a TYPE_DEPOSIT transaction.
     * The bot can attach a deposit slip afterwards via
     * POST /banks/transactions/{transaction}/proof.
     */
    public function deposit(DepositRequest $request, Bank $bank): JsonResponse
    {
        $validated = $request->validated();
        $transactionDate = $validated['transaction_date']
            ?? now(config('app.timezone'))->toDateString();

        $tx = DB::transaction(function () use ($validated, $bank, $transactionDate) {
            $balanceBefore = (float) $bank->balance;
            $balanceAfter = $balanceBefore + (float) $validated['amount'];

            $tx = BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_DEPOSIT,
                'amount' => $validated['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $validated['description'] ?? null,
                'payee' => $validated['payee'] ?? null,
                'transaction_date' => $transactionDate,
                'created_by' => (int) auth()->id(),
            ]);

            $bank->update(['balance' => $balanceAfter]);

            return $tx;
        });

        return $this->success([
            'bank' => $this->presentBankSummary($bank->fresh()),
            'transaction' => $this->presentTransaction($tx->fresh(['bank', 'transferToBank'])),
        ], sprintf('Deposit of %.2f recorded.', (float) $validated['amount']));
    }

    /**
     * POST /v1/openclaw/banks/{bank}/withdrawal — record an outgoing withdrawal.
     *
     * Decreases the bank's balance and writes a TYPE_WITHDRAWAL transaction.
     * Form-request guards against overdrawing the account.
     */
    public function withdrawal(WithdrawalRequest $request, Bank $bank): JsonResponse
    {
        $validated = $request->validated();
        $transactionDate = $validated['transaction_date']
            ?? now(config('app.timezone'))->toDateString();

        $tx = DB::transaction(function () use ($validated, $bank, $transactionDate) {
            $balanceBefore = (float) $bank->balance;
            $balanceAfter = $balanceBefore - (float) $validated['amount'];

            $tx = BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_WITHDRAWAL,
                'amount' => $validated['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $validated['description'] ?? null,
                'payee' => $validated['payee'] ?? null,
                'transaction_date' => $transactionDate,
                'created_by' => (int) auth()->id(),
            ]);

            $bank->update(['balance' => $balanceAfter]);

            return $tx;
        });

        return $this->success([
            'bank' => $this->presentBankSummary($bank->fresh()),
            'transaction' => $this->presentTransaction($tx->fresh(['bank', 'transferToBank'])),
        ], sprintf('Withdrawal of %.2f recorded.', (float) $validated['amount']));
    }

    /**
     * POST /v1/openclaw/banks/{bank}/transfer — move funds between two accounts.
     *
     * Creates two linked BankTransaction rows in one DB transaction:
     * TYPE_TRANSFER_OUT on the source and TYPE_TRANSFER_IN on the destination.
     * Reference numbers are paired (XXX and XXX-IN) so the bot can join them
     * client-side. Proof attaches to the source-account leg.
     */
    public function transfer(TransferRequest $request, Bank $bank): JsonResponse
    {
        $validated = $request->validated();
        $destinationBank = Bank::findOrFail($validated['transfer_to_bank_id']);
        $transactionDate = $validated['transaction_date']
            ?? now(config('app.timezone'))->toDateString();

        $sourceTx = DB::transaction(function () use ($validated, $bank, $destinationBank, $transactionDate) {
            $reference = BankTransaction::generateReferenceNumber();

            $sourceBefore = (float) $bank->balance;
            $sourceAfter = $sourceBefore - (float) $validated['amount'];

            $source = BankTransaction::create([
                'reference_number' => $reference,
                'bank_id' => $bank->id,
                'transfer_to_bank_id' => $destinationBank->id,
                'type' => BankTransaction::TYPE_TRANSFER_OUT,
                'amount' => $validated['amount'],
                'balance_before' => $sourceBefore,
                'balance_after' => $sourceAfter,
                'description' => $validated['description']
                    ?? 'Transfer to '.$destinationBank->account_name,
                'transaction_date' => $transactionDate,
                'created_by' => (int) auth()->id(),
            ]);

            $bank->update(['balance' => $sourceAfter]);

            $destBefore = (float) $destinationBank->balance;
            $destAfter = $destBefore + (float) $validated['amount'];

            BankTransaction::create([
                'reference_number' => $reference.'-IN',
                'bank_id' => $destinationBank->id,
                'transfer_to_bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_TRANSFER_IN,
                'amount' => $validated['amount'],
                'balance_before' => $destBefore,
                'balance_after' => $destAfter,
                'description' => $validated['description']
                    ?? 'Transfer from '.$bank->account_name,
                'transaction_date' => $transactionDate,
                'created_by' => (int) auth()->id(),
            ]);

            $destinationBank->update(['balance' => $destAfter]);

            return $source;
        });

        return $this->success([
            'from_bank' => $this->presentBankSummary($bank->fresh()),
            'to_bank' => $this->presentBankSummary($destinationBank->fresh()),
            'transaction' => $this->presentTransaction($sourceTx->fresh(['bank', 'transferToBank'])),
        ], sprintf(
            'Transfer of %.2f from %s to %s completed.',
            (float) $validated['amount'],
            $bank->account_name,
            $destinationBank->account_name,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function presentBankSummary(Bank $bank): array
    {
        return [
            'id' => $bank->id,
            'name' => $bank->bank_name,
            'account_name' => $bank->account_name,
            'balance' => round((float) $bank->balance, 2),
        ];
    }

    /**
     * POST /v1/openclaw/banks/transactions/{transaction}/proof — attach (or replace) a deposit slip / transfer screenshot.
     */
    public function uploadTransactionProof(Request $request, BankTransaction $transaction, ReceiptStorage $storage): JsonResponse
    {
        $request->validate([
            'proof' => ReceiptStorage::VALIDATION_RULE,
        ]);

        $oldPath = $transaction->proof_photo;
        $newPath = $storage->store($request->file('proof'), ReceiptStorage::DIR_BANK_PROOFS);

        $transaction->forceFill(['proof_photo' => $newPath])->save();
        $storage->delete($oldPath);

        return $this->success([
            'transaction' => $this->presentTransaction($transaction->fresh(['bank', 'transferToBank'])),
        ], $oldPath ? 'Proof replaced.' : 'Proof attached.');
    }

    /**
     * DELETE /v1/openclaw/banks/transactions/{transaction}/proof — clear the proof photo.
     */
    public function deleteTransactionProof(Request $request, BankTransaction $transaction, ReceiptStorage $storage): JsonResponse
    {
        if ($transaction->proof_photo === null) {
            return $this->success([
                'transaction' => $this->presentTransaction($transaction),
            ], 'No proof to remove.');
        }

        $storage->delete($transaction->proof_photo);
        $transaction->forceFill(['proof_photo' => null])->save();

        return $this->success([
            'transaction' => $this->presentTransaction($transaction->fresh(['bank', 'transferToBank'])),
        ], 'Proof removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTransaction(BankTransaction $t): array
    {
        return [
            'id' => $t->id,
            'reference_number' => $t->reference_number,
            'bank_id' => (int) $t->bank_id,
            'bank_name' => $t->bank?->bank_name,
            'transfer_to_bank_id' => $t->transfer_to_bank_id,
            'transfer_to_bank_name' => $t->transferToBank?->bank_name,
            'type' => (int) $t->type,
            'type_name' => $t->type_name,
            'amount' => round((float) $t->amount, 2),
            'balance_after' => round((float) $t->balance_after, 2),
            'description' => $t->description,
            'payee' => $t->payee,
            'proof_photo_url' => $t->proof_photo_url,
            'transaction_date' => $t->transaction_date?->toDateString(),
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function accountTypeMap(): array
    {
        return [
            Bank::TYPE_SAVINGS => 'Savings',
            Bank::TYPE_CHECKING => 'Checking',
            Bank::TYPE_CREDIT => 'Credit',
            Bank::TYPE_PASSBOOK => 'Passbook',
            Bank::TYPE_EWALLET => 'E-Wallet',
        ];
    }

    private function accountTypeName(int $type): string
    {
        return $this->accountTypeMap()[$type] ?? 'Unknown';
    }
}
