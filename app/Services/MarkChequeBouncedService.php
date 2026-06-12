<?php

namespace App\Services;

use App\Http\Requests\Admin\Accounting\BounceChequeRequest;
use App\Models\CustomerRelations\CustomerCreditTransaction;
use App\Models\Pos\Sale;
use App\Models\Reports\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drawee bank refused — the customer still received the goods, so they
 * owe us. We do NOT undo the Sale (the inventory is gone) and we do NOT
 * touch bank balance (the cheque never cleared, so no balance ever
 * changed). What we do:
 *   1. Flip sales.cheque_status pending → bounced
 *   2. Bump customer.credit_balance by the sale total and write a
 *      CustomerCreditTransaction(type=charge) so the receivable is
 *      visible in the customer's ledger
 *
 * Credit-limit is intentionally NOT enforced here — the cheque was
 * accepted in good faith before bouncing. The customer's balance can
 * temporarily exceed their credit_limit; admin handles the conversation.
 */
class MarkChequeBouncedService
{
    public function bounce(Sale $sale, BounceChequeRequest $request, User $admin): Sale
    {
        $this->guardEligibility($sale);

        DB::transaction(function () use ($sale, $request, $admin) {
            $sale->update(['cheque_status' => Sale::CHEQUE_BOUNCED]);

            $customer = $sale->customer;
            $creditCharged = false;
            $newBalance = null;

            if ($customer) {
                $customer->lockForUpdate();
                $customer->refresh();
                $newBalance = (float) $customer->credit_balance + (float) $sale->total;
                $customer->update(['credit_balance' => $newBalance]);

                CustomerCreditTransaction::create([
                    'customer_id' => $customer->id,
                    'type' => 'charge',
                    'amount' => (float) $sale->total,
                    'balance_after' => $newBalance,
                    'due_date' => now(),
                    'reference_type' => 'cheque_bounce',
                    'reference_id' => $sale->id,
                    'pos_id' => $sale->pos_id,
                    'store_id' => $sale->store_id,
                    'user_id' => $admin->id,
                ]);

                $creditCharged = true;
            }

            AuditLog::record($sale, 'cheque_bounced', [
                'son' => $sale->son,
                'bank_id' => $sale->bank_id,
                'amount' => (float) $sale->total,
                'note' => $request->input('bounce_note'),
                'customer_id' => $customer?->id,
                'credit_charged' => $creditCharged,
                'customer_balance_after' => $newBalance,
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
    }
}
