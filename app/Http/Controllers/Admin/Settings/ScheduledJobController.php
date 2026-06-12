<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Reports\AuditLog;
use App\Models\ScheduledJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Throwable;

/**
 * Admin surface for the Laravel scheduler. Rows are app-defined (the
 * seeder owns the canonical key list), so create/destroy are absent —
 * the admin can toggle a job on/off, run it on demand, and see the
 * last-run timestamp + status.
 *
 * Gated by the `sttngs` role flag — same as SMS Templates and SMS Logs.
 */
class ScheduledJobController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSettings($request);

        $jobs = ScheduledJob::query()->orderBy('key')->get();

        return view('admin.settings.scheduled-jobs.index', compact('jobs'));
    }

    public function toggle(Request $request, ScheduledJob $scheduledJob): RedirectResponse
    {
        $this->authorizeSettings($request);

        $before = ['enabled' => (bool) $scheduledJob->enabled];

        $scheduledJob->forceFill([
            'enabled' => ! $scheduledJob->enabled,
            'updated_by' => $request->user()?->id,
        ])->save();

        AuditLog::record(
            $scheduledJob,
            'scheduled_job_toggled',
            [
                'key' => $scheduledJob->key,
                'enabled' => (bool) $scheduledJob->enabled,
            ],
            oldValues: $before,
        );

        $verb = $scheduledJob->enabled ? 'enabled' : 'disabled';

        return redirect()
            ->route('scheduled-jobs.index')
            ->with('success', "Schedule `{$scheduledJob->key}` {$verb}.");
    }

    public function runNow(Request $request, ScheduledJob $scheduledJob): RedirectResponse
    {
        $this->authorizeSettings($request);

        // `key` carries the same string Schedule::command() received —
        // including any `--type=daily` style flags. Split on the first
        // space so Artisan::call gets ('signature', ['--type' => 'daily']).
        [$signature, $params] = $this->parseArtisanInvocation($scheduledJob->key);

        $startedAt = microtime(true);
        $status = ScheduledJob::STATUS_SUCCESS;
        $errorMessage = null;

        try {
            Artisan::call($signature, $params);
        } catch (Throwable $e) {
            $status = ScheduledJob::STATUS_FAILED;
            $errorMessage = $e->getMessage();
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        ScheduledJob::recordRun($scheduledJob->key, $status, $durationMs);

        AuditLog::record(
            $scheduledJob->fresh(),
            'scheduled_job_run_now',
            [
                'key' => $scheduledJob->key,
                'triggered_by' => 'admin',
                'status' => $status,
                'duration_ms' => $durationMs,
                'error' => $errorMessage,
            ],
        );

        if ($status === ScheduledJob::STATUS_SUCCESS) {
            return redirect()
                ->route('scheduled-jobs.index')
                ->with('success', "Ran `{$scheduledJob->key}` in {$durationMs} ms.");
        }

        return redirect()
            ->route('scheduled-jobs.index')
            ->with('error', "Run failed: {$errorMessage}");
    }

    private function authorizeSettings(Request $request): void
    {
        abort_unless((bool) $request->user()?->role?->sttngs, 403);
    }

    /**
     * Split a stored key like `report:generate --type=daily` into the
     * Artisan signature + a [option => value] array Artisan::call can
     * consume. Boolean flags become `[flag => true]`.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function parseArtisanInvocation(string $key): array
    {
        $parts = preg_split('/\s+/', trim($key));
        $signature = array_shift($parts);
        $params = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '--') && str_contains($part, '=')) {
                [$flag, $value] = explode('=', substr($part, 2), 2);
                $params['--'.$flag] = $value;
            } elseif (str_starts_with($part, '--')) {
                $params['--'.substr($part, 2)] = true;
            } else {
                $params[] = $part;
            }
        }

        return [$signature, $params];
    }
}
