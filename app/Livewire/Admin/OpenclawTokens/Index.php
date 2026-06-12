<?php

namespace App\Livewire\Admin\OpenclawTokens;

use App\Models\ApiToken;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class Index extends Component
{
    public string $name = '';

    public ?string $newPlainToken = null;

    public function mount(): void
    {
        abort_unless($this->isOwner(), 403, 'Only the tenant owner can manage OpenClaw tokens.');
    }

    public function create(): void
    {
        abort_unless($this->isOwner(), 403);

        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        $plain = ApiToken::generatePlainToken();

        ApiToken::create([
            'user_id' => auth()->user()->user_id,
            'name' => $this->name,
            'token' => ApiToken::hashToken($plain),
        ]);

        $this->newPlainToken = $plain;
        $this->name = '';
    }

    public function revoke(int $id): void
    {
        abort_unless($this->isOwner(), 403);

        $token = ApiToken::query()
            ->where('user_id', auth()->user()->user_id)
            ->where('id', $id)
            ->whereNull('revoked_at')
            ->first();

        if ($token === null) {
            return;
        }

        $token->forceFill(['revoked_at' => now()])->save();
    }

    public function dismissPlainToken(): void
    {
        $this->newPlainToken = null;
    }

    public function render(): View
    {
        return view('livewire.admin.openclaw-tokens.index', [
            'tokens' => $this->tokens(),
        ]);
    }

    private function tokens(): Collection
    {
        return ApiToken::query()
            ->where('user_id', auth()->user()->user_id)
            ->orderByDesc('id')
            ->get();
    }

    private function isOwner(): bool
    {
        $user = auth()->user();

        return $user !== null && (int) $user->id === (int) $user->user_id;
    }
}
