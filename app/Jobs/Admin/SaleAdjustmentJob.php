<?php

namespace App\Jobs\Admin;

use App\Sale;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaleAdjustmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private String $startDate;
    private String $endDate;
    private array $terminals;
    private float $vat_rate;
    private float $non_vat_rate;
    private float $zero_rated_rate;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(String $startDate, String $endDate, array $terminals, float $vr, float $nvr, float $zrr)
    {
        $this->startDate = Carbon::parse($startDate)->startOfDay()->toDateTimeString();
        $this->endDate = Carbon::parse($endDate)->endOfDay()->toDateTimeString();
        $this->terminals = $terminals;
        $this->vat_rate = $vr / 100;
        $this->non_vat_rate = $nvr / 100;
        $this->zero_rated_rate = $zrr / 100;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        ini_set('max_execution_time', -1);
        ini_set('memory_limit', -1);
        $receipts = Sale::with(['lines'])
            ->whereBetween(
                'created_at',
                [
                    $this->startDate,
                    $this->endDate
                ]
            )
            ->whereIn('pos_id', $this->terminals)
            ->get();
        \DB::beginTransaction();
        foreach ($receipts as $receipt){
            $excessVATable = 0;
            $excessVAT = 0;
            $excessNonVAT = 0;
            foreach($receipt->lines as $line){
                $subVATable = $line->vatable * $this->vat_rate;
                $subVAT = ($line->vatable * (12 / 100)) * $this->vat_rate;
                $subNonVAT = ($line->non_vat + $line->exempt) * $this->non_vat_rate;
                $subZeroRated = $line->zero_rated * $this->non_vat_rate;

                $line->excess_vatable = $subVATable;
                $line->excess_vat = $subVAT;
                $line->excess_non_vat = $subNonVAT;
                // Lacking Zero Rated Adjustment

                $line->save();

                $excessVATable += $subVATable;
                $excessVAT += $subVAT;
                $excessNonVAT += $subNonVAT;
            }

            $receipt->excess_vatable = $excessVATable;
            $receipt->excess_vat = $excessVAT;
            $receipt->excess_non_vat = $excessNonVAT;

            $receipt->save();
        }
        \DB::commit();
    }
}
