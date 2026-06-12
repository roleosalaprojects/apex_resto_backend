<?php

namespace App\Console\Commands;

use App\Models\Pos\HigherAccessRequest;
use Illuminate\Console\Command;

class ExpireHigherAccessRequests extends Command
{
    protected $signature = 'higher-access:expire';

    protected $description = 'Expire pending higher access requests';

    public function handle(): int
    {
        $count = HigherAccessRequest::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} request(s).");

        return Command::SUCCESS;
    }
}
