<?php

namespace App\Observers;

use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\Accounting\PosLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors POS cash-out events into the expenses table in real time.
 *
 *   type 12 (Cash-Out)        → create a cashless expense with
 *                                receipt_number = "POS-CASHOUT-<id>".
 *   type 13 (Void Cash-Out)   → void the matching expense by reference.
 *
 * The receipt_number doubles as the idempotency anchor so this observer
 * and the cashouts:sync-to-expenses artisan command can coexist —
 * neither will create duplicates if the other already ran.
 *
 * Failures are logged-and-swallowed: a cash-out at the POS must not
 * fail because the expense mirror tripped on some unrelated condition
 * (a missing column on a half-deployed server, etc.). The artisan
 * command exists as the safety net for any rows that miss the observer.
 */
class PosLogObserver
{
    public const TYPE_CASH_OUT = 12;

    public const TYPE_VOID_CASH_OUT = 13;

    public function created(PosLog $log): void
    {
        try {
            if ((int) $log->type === self::TYPE_CASH_OUT) {
                $this->mirrorCashOutAsExpense($log);

                return;
            }

            if ((int) $log->type === self::TYPE_VOID_CASH_OUT && $log->so_id !== null) {
                $this->voidMirroredExpense($log);
            }
        } catch (\Throwable $e) {
            Log::warning('PosLogObserver: failed to mirror cash-out as expense.', [
                'pos_log_id' => $log->id,
                'type' => $log->type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function mirrorCashOutAsExpense(PosLog $log): void
    {
        $amount = (float) ($log->cash_out ?? 0);
        if ($amount <= 0) {
            return;
        }

        $reference = 'POS-CASHOUT-'.$log->id;
        if (Expense::query()->where('receipt_number', $reference)->exists()) {
            return;
        }

        $tz = config('app.timezone');
        $expenseDate = $log->created_at
            ? Carbon::parse($log->created_at)->setTimezone($tz)->toDateString()
            : Carbon::today($tz)->toDateString();

        $hasReason = $log->reason !== null && trim($log->reason) !== '';

        $description = $hasReason
            ? 'POS cash-out #'.$log->id.' — '.$log->reason
            : 'POS cash-out #'.$log->id;

        $payee = $hasReason
            ? 'POS Cash Out — '.trim($log->reason)
            : 'POS Cash Out';

        Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->resolveCategoryId(),
            'store_id' => $log->store_id,
            'supplier_id' => null,
            'bank_id' => null,
            'bank_transaction_id' => null,
            'payee' => $payee,
            'amount' => $amount,
            'expense_date' => $expenseDate,
            'description' => $description,
            'receipt_number' => $reference,
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $log->user_id,
        ]);
    }

    private function voidMirroredExpense(PosLog $log): void
    {
        $reference = 'POS-CASHOUT-'.$log->so_id;
        $expense = Expense::query()->where('receipt_number', $reference)->first();

        if ($expense === null || $expense->isVoided()) {
            return;
        }

        $expense->forceFill([
            'status' => Expense::STATUS_VOIDED,
            'voided_at' => now(),
            'voided_by' => $log->user_id,
            'void_reason' => 'POS cash-out voided (auto-sync)',
        ])->save();
    }

    /**
     * Best-effort default: pick an active "Cash Out" category by name.
     * Returns null if none exists; the bot or admin can later set it
     * via PATCH /v1/openclaw/expenses/{id}.
     */
    private function resolveCategoryId(): ?int
    {
        return ExpenseCategory::query()
            ->whereRaw('LOWER(name) = ?', ['cash out'])
            ->where('status', 1)
            ->value('id');
    }
}
