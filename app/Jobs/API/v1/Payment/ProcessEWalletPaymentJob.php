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

    /**
     * Create a new job instance.
     *
     * For a single-tender sale the deposit amount and bank come off the
     * sale itself (bank_amount / bank_id). A multi-tender sale dispatches
     * this job once per e-wallet/bank tender, passing that tender's
     * applied amount and bank explicitly.
     */
    public function __construct(
        private Sale $sale,
        private ?float $amount = null,
        private ?int $bankId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $bank = Bank::find($this->bankId ?? $this->sale->bank_id);
        $sale = $this->sale;
        $tenderAmount = $this->amount ?? $sale->bank_amount;

        if (! $bank) {
            \Log::error('Bank not found for sale: '.$sale->id);

            return;
        }

        DB::transaction(function () use ($bank, $sale, $tenderAmount) {
            $balanceBefore = $bank->balance;
            $amount = $sale->type ? -$tenderAmount : $tenderAmount;
            $balanceAfter = $balanceBefore + $amount;

            // Create bank transaction record
            BankTransaction::create([
                'reference_number' => 'SALE-'.$sale->son,
                'bank_id' => $bank->id,
                'type' => $sale->type ? BankTransaction::TYPE_WITHDRAWAL : BankTransaction::TYPE_DEPOSIT,
                'amount' => abs($tenderAmount),
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
