<?php

namespace App\Jobs\Admin;

use App\Sale;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NormalizeReceiptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private String $startDate;
    private String $endDate;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(String $startDate, String $endDate)
    {
        $this->startDate = Carbon::parse($startDate)->startOfDay()->toDateTimeString();
        $this->endDate = Carbon::parse($endDate)->endOfDay()->toDateTimeString();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $receipts = Sale::with(['lines'])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->get();
        foreach ($receipts as $receipt){
            $vatable = 0;
            $vat = 0;
            foreach ($receipt->lines as $line){
                $subTotal = $line->sub_total;
                $subNonVat = $line->non_vat + $line->exempt;
                $subVatable = ($subTotal - $subNonVat) / 1.12;
                $subVat = $subVatable * 0.12;
                $line->update([
                    'vatable' => $subVatable,
                    'vat' => $subVat,
                ]);
                $vatable += $subVatable;
                $vat += $subVat;
            }
            $receipt->update([
                'vatable' => $vatable,
                'vat' => $vat,
            ]);
        }
    }
}
