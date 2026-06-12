<?php

namespace App\Jobs\Admin;

use App\Xreading;
use App\Zreading;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AdjustReadingJob implements ShouldQueue
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
    public function handle()
    {
        $vr = $this->vr/100;
        $nvr = $this->nvr/100;

        $xreadings = Xreading::whereBetween('created_at', [$this->startDate, $this->endDate])
            ->whereIn('pos_id', $this->terminals)
            ->get();
        $zreadings = Zreading::whereBetween('created_at', [$this->startDate, $this->endDate])
            ->whereIn('pos_id', $this->terminals)
            ->get();
        foreach($xreadings as $reading){
            // Non VAT
            $reading->excess_non_vat = ($reading->non_vat + $reading->vat_exempt) * $nvr;
            // VAT
            $reading->excess_vatable = $reading->vatable * $vr;
            $reading->excess_vat = ($reading->vatable * 0.12) * $vr;
            $reading->save();
        }
        foreach($zreadings as $reading){
            // Non VAT
            $reading->excess_non_vat = ($reading->non_vat + $reading->vat_exempt) * $nvr;
            // VAT
            $reading->excess_vatable = $reading->vatable * $vr;
            $reading->excess_vat = ($reading->vatable * 0.12) * $vr;
            $reading->save();
        }
    }
}
