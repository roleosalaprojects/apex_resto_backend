@extends('layout.app')
@section('header')
    - Scheduled Jobs
@endsection
@section('title')
    Scheduled Jobs
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">Dashboard</a></li>
    <li class="breadcrumb-item text-muted">Scheduled Jobs</li>
@endsection
@section('content')
    {{-- Flash banners are rendered by layout/messages.blade.php. --}}

    <div class="card">
        <div class="card-body">
            <p class="text-muted fs-7 mb-5">
                These rows govern the Laravel scheduler. Toggle <code>Enabled</code> off to mute a specific scheduled
                command without redeploying. <strong>Run Now</strong> executes the command synchronously in the request
                — useful for verifying after a config change. Last-run timestamp + status are stamped on every run
                (scheduled or manual).
            </p>

            <table class="table table-row-bordered table-row-gray-200 align-middle gy-4 w-100">
                <thead>
                    <tr class="fw-bold text-muted">
                        <th>Key</th>
                        <th>Description</th>
                        <th>Cadence</th>
                        <th>Status</th>
                        <th>Last Run</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td><code>{{ $job->key }}</code></td>
                            <td class="text-muted fs-7" style="max-width: 380px;">{{ $job->description }}</td>
                            <td class="text-muted fs-7">{{ $job->cadence_label ?? '—' }}</td>
                            <td>
                                @if ($job->enabled)
                                    <span class="badge badge-light-success">Enabled</span>
                                @else
                                    <span class="badge badge-light-secondary">Disabled</span>
                                @endif
                            </td>
                            <td class="fs-7">
                                @if ($job->last_run_at)
                                    <div>{{ $job->last_run_at->diffForHumans() }}</div>
                                    <div class="text-muted">
                                        {{ $job->last_run_at->format('M j, Y g:i A') }}
                                        @if ($job->last_run_status === \App\Models\ScheduledJob::STATUS_SUCCESS)
                                            <span class="badge badge-light-success ms-1">Success</span>
                                        @elseif ($job->last_run_status === \App\Models\ScheduledJob::STATUS_FAILED)
                                            <span class="badge badge-light-danger ms-1">Failed</span>
                                        @endif
                                        @if ($job->last_run_duration_ms !== null)
                                            <span class="text-muted ms-1">({{ $job->last_run_duration_ms }} ms)</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted">Never run</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('scheduled-jobs.run-now', $job) }}" class="d-inline">
                                    @csrf
                                    <button type="submit"
                                            class="btn btn-sm btn-light-primary"
                                            onclick="return confirm('Run `{{ $job->key }}` now?');"
                                            @disabled(! $job->enabled)>
                                        <i class="ki-outline ki-rocket fs-5"></i> Run Now
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('scheduled-jobs.toggle', $job) }}" class="d-inline">
                                    @csrf
                                    @if ($job->enabled)
                                        <button type="submit" class="btn btn-sm btn-light-warning"
                                                onclick="return confirm('Disable `{{ $job->key }}`? It will stop running on schedule.');">
                                            <i class="ki-outline ki-pause fs-5"></i> Disable
                                        </button>
                                    @else
                                        <button type="submit" class="btn btn-sm btn-light-success">
                                            <i class="ki-outline ki-play fs-5"></i> Enable
                                        </button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                No scheduled jobs yet. Run <code>php artisan db:seed --class=ScheduledJobSeeder</code>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
