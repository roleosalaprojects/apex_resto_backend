<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use Illuminate\Console\Command;

class OpenclawTokenList extends Command
{
    protected $signature = 'openclaw:token-list {--user_id= : Filter by tenant user_id}';

    protected $description = 'List OpenClaw API tokens (hashed; plain values are not stored).';

    public function handle(): int
    {
        $query = ApiToken::query()->orderBy('id');

        if ($this->option('user_id') !== null) {
            $query->where('user_id', (int) $this->option('user_id'));
        }

        $rows = $query->get()->map(fn (ApiToken $t): array => [
            'id' => $t->id,
            'user_id' => $t->user_id,
            'name' => $t->name,
            'abilities' => $t->abilities === null
                ? implode(',', ApiToken::defaultAbilities()).' (default)'
                : implode(',', $t->abilities),
            'last_used_at' => $t->last_used_at?->toDateTimeString() ?? '—',
            'revoked_at' => $t->revoked_at?->toDateTimeString() ?? '—',
            'created_at' => $t->created_at?->toDateTimeString() ?? '—',
        ])->all();

        $this->table(['id', 'user_id', 'name', 'abilities', 'last_used_at', 'revoked_at', 'created_at'], $rows);

        return self::SUCCESS;
    }
}
