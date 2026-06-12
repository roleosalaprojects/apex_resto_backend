<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Contracts\SmsRelayContract;
use App\Http\Controllers\Controller;
use App\Models\OutboundSmsLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin surface for the outbound SMS log. Lists every dispatch we
 * attempted, with delivery state pulled from VeroSMS on demand.
 *
 * Gated by the `sttngs` role flag — anyone who can see settings can
 * see SMS logs.
 */
class OutboundSmsLogController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.settings.sms-logs.index');
    }

    /**
     * Server-side paginated table feed. Yajra's eloquent() builder
     * pushes pagination + ordering + filtering down to MySQL, so the
     * payload stays roughly constant (~25KB / 25 rows) no matter how
     * many millions of historical rows we hoard.
     */
    public function table(Request $request): JsonResponse
    {
        $query = OutboundSmsLog::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%'.$request->input('phone').'%');
        }

        return datatables()->eloquent($query)
            ->addColumn('status_badge', function (OutboundSmsLog $log) {
                $variant = $log->statusBadgeVariant();
                $label = $log->statusLabel();

                return "<span class=\"badge badge-light-{$variant}\">{$label}</span>";
            })
            ->addColumn('created_human', function (OutboundSmsLog $log) {
                $absolute = $log->created_at?->format('M d, Y h:i:s A');
                $relative = $log->created_at?->diffForHumans();

                return "<div>{$absolute}</div><div class=\"text-muted fs-8\">{$relative}</div>";
            })
            ->addColumn('last_checked_human', function (OutboundSmsLog $log) {
                if (! $log->last_checked_at) {
                    return '<span class="text-muted">—</span>';
                }

                return $log->last_checked_at->diffForHumans();
            })
            ->addColumn('actions', function (OutboundSmsLog $log) {
                $disabled = $log->sms_id ? '' : 'disabled';

                return '<tr-actions data-log-id="'.$log->id.'">
                    <button type="button"
                        class="btn btn-sm btn-light-primary js-refresh-status"
                        data-log-id="'.$log->id.'"
                        '.$disabled.'>
                        <i class="ki-outline ki-arrows-circle fs-5"></i> Refresh
                    </button>
                </tr-actions>';
            })
            // Default sort: newest first. Server pushes ORDER BY +
            // LIMIT/OFFSET so even huge tables stay cheap.
            ->orderColumn('created_at', 'created_at $1')
            ->setRowAttr([
                'data-log-id' => fn (OutboundSmsLog $log) => $log->id,
            ])
            ->rawColumns(['status_badge', 'created_human', 'last_checked_human', 'actions'])
            ->toJson();
    }

    /**
     * Re-poll VeroSMS for the most recent delivery status of one row.
     * Response carries the updated cell HTML so the frontend can
     * splice it in-place instead of triggering a full table reload —
     * critical when the table has thousands of rows.
     */
    public function refreshStatus(OutboundSmsLog $smsLog, SmsRelayContract $sms): JsonResponse
    {
        $updated = $sms->pollStatus($smsLog);

        if (! $updated) {
            return response()->json([
                'success' => false,
                'message' => 'Could not fetch status from VeroSMS.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Status: {$updated->statusLabel()}",
            'data' => [
                'id' => $updated->id,
                'status' => $updated->status,
                'status_label' => $updated->statusLabel(),
                'status_variant' => $updated->statusBadgeVariant(),
                'status_badge_html' => '<span class="badge badge-light-'.$updated->statusBadgeVariant().'">'.e($updated->statusLabel()).'</span>',
                'last_checked_at' => $updated->last_checked_at?->diffForHumans(),
            ],
        ]);
    }

    /**
     * Bulk: queue a background poll for every "sent" row that still
     * has an sms_id and hasn't been polled recently. Returns
     * immediately with the count we kicked off so the admin doesn't
     * sit on a hanging request while the worker churns through them.
     */
    public function bulkPoll(): JsonResponse
    {
        $candidates = OutboundSmsLog::query()
            ->where('status', OutboundSmsLog::STATUS_SENT)
            ->whereNotNull('sms_id')
            ->where(function ($q) {
                $q->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<', now()->subMinutes(5));
            })
            ->orderBy('id')
            ->limit(500)
            ->pluck('id');

        foreach ($candidates as $id) {
            \App\Jobs\PollVeroSmsStatusJob::dispatch($id);
        }

        return response()->json([
            'success' => true,
            'message' => "Polling {$candidates->count()} pending message(s) in the background.",
            'data' => [
                'queued' => $candidates->count(),
            ],
        ]);
    }
}
