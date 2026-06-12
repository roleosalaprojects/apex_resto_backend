<?php

namespace App\Services;

use App\Http\Requests\Admin\Accounting\ClearChequeRequest;
use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Pos\Sale;
use App\Models\Reports\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drawee bank confirmed the cheque, so we recognise the money now:
 *   1. Flip sales.cheque_status pending → cleared
 *   2. Write a BankTransaction (TYPE_DEPOSIT) using the admin-picked
 *      clearing date so analytics can reason about days-to-clear
 *   3. Bump bank.balance by the cheque amount
 *
 * We do NOT dispatch ProcessEWalletPaymentJob here — that job hardcodes
 * the SALE-{son} reference and the sale's created_at, neither of which
 * is right for a cheque that cleared days/weeks later.
 */
class MarkChequeClearedService
{
    public function clear(Sale $sale, ClearChequeRequest $request, User $admin): Sale
    {
        $this->guardEligibility($sale);

        $bank = Bank::findOrFail($sale->bank_id);

        DB::transaction(function () use ($sale, $bank, $request, $admin) {
            $balanceBefore = (float) $bank->balance;
            $amount = (float) $sale->bank_amount;
            $balanceAfter = $balanceBefore + $amount;

            BankTransaction::create([
                'reference_number' => $request->input('clearing_reference')
                    ?: 'CHEQ-CLEARED-'.$sale->son,
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_DEPOSIT,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => 'Cheque cleared - Invoice #'.$sale->son,
                'payee' => 'Cheque deposit',
                'transaction_date' => Carbon::parse($request->input('cleared_date'))->toDateString(),
                'created_by' => $admin->id,
            ]);

            $bank->update(['balance' => $balanceAfter]);

            $sale->update(['cheque_status' => Sale::CHEQUE_CLEARED]);

            AuditLog::record($sale, 'cheque_cleared', [
                'son' => $sale->son,
                'bank_id' => $bank->id,
                'amount' => $amount,
                'cleared_date' => $request->input('cleared_date'),
                'clearing_reference' => $request->input('clearing_reference'),
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ], userId: $admin->id);
        });

        return $sale->fresh();
    }

    private function guardEligibility(Sale $sale): void
    {
        if ((int) $sale->payment_type !== Sale::PAYMENT_CHEQUE) {
            throw ValidationException::withMessages([
                'sale' => 'This sale was not paid by cheque.',
            ]);
        }

        if ($sale->cheque_status !== Sale::CHEQUE_PENDING) {
            throw ValidationException::withMessages([
                'sale' => "Cheque is already {$sale->cheque_status}.",
            ]);
        }

        if (! $sale->bank_id) {
            throw ValidationException::withMessages([
                'sale' => 'Cheque has no associated bank.',
            ]);
        }
    }
}
