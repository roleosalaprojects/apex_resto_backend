<?php

namespace App\Jobs\API\v1\Authentication;

use App\Authentication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Authentication $authentication;

    /**
     * Create a new job instance.
     */
    public function __construct(Authentication $authentication)
    {
        $this->authentication = $authentication;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->authentication->status == null){
            // This runs if delayed timer of 3 minutes is up.
            $this->authentication->update([
                // Timed-out means user exceeded allowable time allowed
                'status' => 'timed_out'
            ]);
        }
    }
}
