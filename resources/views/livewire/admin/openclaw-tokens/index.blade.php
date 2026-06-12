<div>
    @if ($newPlainToken)
        <div class="alert alert-warning d-flex align-items-center mb-7" role="alert">
            <div class="flex-grow-1">
                <h4 class="mb-2 fw-bolder">New token created</h4>
                <p class="mb-2">Copy this token now. It will not be shown again. Treat it like a password.</p>
                <code class="d-block bg-light p-3 fs-6" style="word-break: break-all;">{{ $newPlainToken }}</code>
            </div>
            <button wire:click="dismissPlainToken" type="button" class="btn btn-sm btn-light ms-3">Dismiss</button>
        </div>
    @endif

    <div class="card card-bordered mb-7">
        <div class="card-header">
            <h3 class="card-title">Mint a new token</h3>
        </div>
        <div class="card-body">
            <form wire:submit="create" class="row g-3 align-items-end">
                <div class="col-md-9">
                    <label class="form-label fw-semibold">Token name (e.g. "OpenClaw production")</label>
                    <input type="text" wire:model="name" class="form-control form-control-solid" placeholder="OpenClaw bot" required maxlength="255">
                    @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="create">
                        <span wire:loading.remove wire:target="create">Create token</span>
                        <span wire:loading wire:target="create">Creating…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-header">
            <h3 class="card-title">Tokens</h3>
        </div>
        <div class="card-body">
            @if ($tokens->isEmpty())
                <div class="text-center text-muted py-10">
                    <p class="fs-5">No tokens yet.</p>
                    <p>Create one above to let OpenClaw access your data.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-row-bordered align-middle">
                        <thead>
                            <tr class="fw-bold fs-6 text-gray-800">
                                <th>#</th>
                                <th>Name</th>
                                <th>Last used</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tokens as $token)
                                <tr wire:key="token-{{ $token->id }}">
                                    <td>{{ $token->id }}</td>
                                    <td>{{ $token->name }}</td>
                                    <td>{{ $token->last_used_at?->diffForHumans() ?? '—' }}</td>
                                    <td>
                                        @if ($token->revoked_at)
                                            <span class="badge badge-light-danger">Revoked {{ $token->revoked_at->diffForHumans() }}</span>
                                        @else
                                            <span class="badge badge-light-success">Active</span>
                                        @endif
                                    </td>
                                    <td>{{ $token->created_at?->toDateTimeString() }}</td>
                                    <td class="text-end">
                                        @unless ($token->revoked_at)
                                            <button
                                                type="button"
                                                wire:click="revoke({{ $token->id }})"
                                                wire:confirm="Revoke token #{{ $token->id }}? This cannot be undone."
                                                class="btn btn-sm btn-light-danger">
                                                Revoke
                                            </button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
