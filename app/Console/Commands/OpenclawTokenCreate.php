<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Console\Command;

class OpenclawTokenCreate extends Command
{
    protected $signature = 'openclaw:token-create
        {user_id : Tenant user_id (the owner\'s users.id)}
        {name : Human-readable label for this token}
        {--abilities= : Comma-separated list of abilities, e.g. "openclaw:read,openclaw:expenses:create" or "*". Defaults to read-only.}';

    protected $description = 'Mint a new OpenClaw API bearer token for a tenant. The plain token is shown once.';

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $name = (string) $this->argument('name');

        $owner = User::query()
            ->where('id', $userId)
            ->where('user_id', $userId)
            ->first();

        if ($owner === null) {
            $this->error("No tenant owner found with id={$userId} (id must equal user_id).");

            return self::FAILURE;
        }

        $abilities = $this->parseAbilities($this->option('abilities'));

        $plain = ApiToken::generatePlainToken();

        $token = ApiToken::create([
            'user_id' => $userId,
            'name' => $name,
            'token' => ApiToken::hashToken($plain),
            'abilities' => $abilities,
        ]);

        $this->info("Token #{$token->id} created for tenant user_id={$userId} ({$owner->name}).");
        $this->line('Abilities: '.implode(', ', $abilities ?? ApiToken::defaultAbilities()).($abilities === null ? ' (default)' : ''));
        $this->newLine();
        $this->line('Plain token (store securely, this is the only time it is shown):');
        $this->line($plain);

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>|null null = use ApiToken::defaultAbilities() at runtime
     */
    private function parseAbilities(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($s) => $s !== ''));

        return $parts === [] ? null : $parts;
    }
}
