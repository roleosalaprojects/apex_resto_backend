<?php

namespace App\Jobs\API\v1\Payment;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Pos\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessEWalletPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Sale $sale;

    /**
     * Create a new job instance.
     */
    public function __construct(Sale $sale)
    {
        $this->sale = $sale;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $bank = Bank::find($this->sale->bank_id);
        $sale = $this->sale;

        if (! $bank) {
            \Log::error('Bank not found for sale: '.$sale->id);

            return;
        }

        DB::transaction(function () use ($bank, $sale) {
            $balanceBefore = $bank->balance;
            $amount = $sale->type ? -$sale->bank_amount : $sale->bank_amount;
            $balanceAfter = $balanceBefore + $amount;

            // Create bank transaction record
            BankTransaction::create([
                'reference_number' => 'SALE-'.$sale->son,
                'bank_id' => $bank->id,
                'type' => $sale->type ? BankTransaction::TYPE_WITHDRAWAL : BankTransaction::TYPE_DEPOSIT,
                'amount' => abs($sale->bank_amount),
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $sale->type ? 'Refund - Invoice #'.$sale->son : 'Sale - Invoice #'.$sale->son,
                'payee' => 'POS Sale',
                'transaction_date' => $sale->created_at->toDateString(),
                'created_by' => $sale->sales_by,
            ]);

            $bank->update(['balance' => $balanceAfter]);

            \Log::debug($bank->id.' : '.$bank->account_name.' - '.$bank->bank_name.' Bank Balance Updated: from '.$balanceBefore.' to '.$balanceAfter);
        });
    }
}
