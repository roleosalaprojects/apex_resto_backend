<?php

namespace App\Services;

use App\Models\Pos\Sale;
use App\Models\Settings\Pos;
use Illuminate\Support\Facades\DB;

/**
 * BIR document series allocation for a POS terminal.
 *
 * Every fiscal event (sale, void, return, reading, cash-out) draws a
 * transaction number from the terminal's txn_counter; sales additionally
 * draw an SI counter. All increments happen under a pessimistic row lock
 * on the pos record so concurrent requests can't collide or leave gaps in
 * the official series. Training-mode transactions draw from a separate
 * training_counter and never touch the official series.
 */
class DocumentNumberService
{
    private const SALE_COUNTER_START = 100000;

    private const REFUND_COUNTER_START = 1000000;

    private const MAX_COUNTER = 999999999999999;

    /**
     * Allocate the numbers a sale needs: SI counter, SON prefix/type,
     * transaction number, and (for refunds) a return number.
     *
     * @return array{counter: int, son_type: int|string, txn_no: int, return_no: int|null}
     */
    public function nextSaleNumbers(Pos $pos, bool $isTraining = false, ?int $refundSaleId = null): array
    {
        return DB::transaction(function () use ($pos, $isTraining, $refundSaleId) {
            $locked = Pos::whereKey($pos->id)->lockForUpdate()->first();

            if ($isTraining) {
                $counter = $locked->training_counter + 1;
                $locked->update(['training_counter' => $counter]);

                return [
                    'counter' => $counter,
                    'son_type' => 'TR',
                    'txn_no' => $counter,
                    'return_no' => null,
                ];
            }

            $txnNo = $locked->txn_counter + 1;
            $updates = ['txn_counter' => $txnNo];
            $returnNo = null;

            if ($refundSaleId !== null) {
                $latestRefund = Sale::where('pos_id', $pos->id)
                    ->where('type', true)
                    ->where('is_training', false)
                    ->latest('id')
                    ->first();
                $counter = $latestRefund ? $latestRefund->counter + 1 : self::REFUND_COUNTER_START;
                $sonType = 'R';
                $returnNo = $locked->return_counter + 1;
                $updates['return_counter'] = $returnNo;
            } else {
                $sonType = $locked->reset_counter;
                $latestSale = Sale::where('pos_id', $pos->id)
                    ->where('type', false)
                    ->where('is_training', false)
                    ->latest('id')
                    ->first();

                if (! $latestSale) {
                    $counter = self::SALE_COUNTER_START;
                } elseif ($latestSale->counter >= self::MAX_COUNTER) {
                    $sonType = $locked->reset_counter + 1;
                    $updates['reset_counter'] = $sonType;
                    $counter = self::SALE_COUNTER_START;
                } else {
                    $counter = $latestSale->counter + 1;
                }
            }

            $locked->update($updates);

            return [
                'counter' => $counter,
                'son_type' => $sonType,
                'txn_no' => $txnNo,
                'return_no' => $returnNo,
            ];
        });
    }

    /**
     * Allocate the next void document number for a terminal.
     */
    public function nextVoidNumber(Pos $pos): int
    {
        return DB::transaction(function () use ($pos) {
            $locked = Pos::whereKey($pos->id)->lockForUpdate()->first();
            $next = $locked->void_counter + 1;
            $locked->update(['void_counter' => $next]);

            return $next;
        });
    }

    /**
     * Allocate the next return document number for a terminal.
     */
    public function nextReturnNumber(Pos $pos): int
    {
        return DB::transaction(function () use ($pos) {
            $locked = Pos::whereKey($pos->id)->lockForUpdate()->first();
            $next = $locked->return_counter + 1;
            $locked->update(['return_counter' => $next]);

            return $next;
        });
    }

    /**
     * Allocate the next transaction number for a non-sale fiscal event
     * (reading, cash-out, etc.).
     */
    public function nextTransactionNumber(Pos $pos): int
    {
        return DB::transaction(function () use ($pos) {
            $locked = Pos::whereKey($pos->id)->lockForUpdate()->first();
            $next = $locked->txn_counter + 1;
            $locked->update(['txn_counter' => $next]);

            return $next;
        });
    }

    /**
     * Record a receipt reprint against a sale for the BIR audit trail.
     */
    public function recordReprint(Sale $sale, ?int $userId = null): Sale
    {
        $sale->update([
            'reprint_count' => $sale->reprint_count + 1,
            'last_reprinted_at' => now(),
            'last_reprinted_by' => $userId,
        ]);

        return $sale->refresh();
    }
}
