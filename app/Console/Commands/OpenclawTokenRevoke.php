<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use Illuminate\Console\Command;

class OpenclawTokenRevoke extends Command
{
    protected $signature = 'openclaw:token-revoke {id : api_tokens.id to revoke}';

    protected $description = 'Revoke an OpenClaw API token by id.';

    public function handle(): int
    {
        $id = (int) $this->argument('id');

        $token = ApiToken::find($id);

        if ($token === null) {
            $this->error("Token #{$id} not found.");

            return self::FAILURE;
        }

        if ($token->isRevoked()) {
            $this->warn("Token #{$id} was already revoked at {$token->revoked_at}.");

            return self::SUCCESS;
        }

        $token->forceFill(['revoked_at' => now()])->save();
        $this->info("Token #{$id} revoked.");

        return self::SUCCESS;
    }
}
