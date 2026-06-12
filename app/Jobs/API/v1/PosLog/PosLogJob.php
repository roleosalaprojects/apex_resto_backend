<?php

namespace App\Jobs\API\v1\PosLog;

use App\Models\Accounting\PosLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PosLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private ?float $cash_in;

    private ?float $cash_out;

    private ?float $rendered;

    private int $type;

    private string $reason;

    private ?int $so_id;

    private int $pos_id;

    private int $store_id;

    private int $user_id;

    /**
     * Create a new job instance.
     */
    public function __construct(
        ?float $cash_in,
        ?float $cash_out,
        ?float $rendered,
        float $type,
        string $reason,
        ?int $so_id,
        int $pos_id,
        int $store_id,
        int $user_id
    ) {
        $this->cash_in = $cash_in;
        $this->cash_out = $cash_out;
        $this->rendered = $rendered;
        $this->type = $type;
        $this->reason = $reason;
        $this->so_id = $so_id;
        $this->pos_id = $pos_id;
        $this->store_id = $store_id;
        $this->user_id = $user_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $posLog = PosLog::create([
            'cash_in' => $this->cash_in,
            'cash_out' => $this->cash_out,
            'rendered' => $this->rendered,
            'type' => $this->type,
            'reason' => $this->reason,
            'so_id' => $this->so_id,
            'pos_id' => $this->pos_id,
            'store_id' => $this->store_id,
            'user_id' => $this->user_id,
        ]);
        \Log::channel('pos_logs')->info('POS Log: '.$this->reason.' Payload:'.$posLog->toJson());
    }
}
