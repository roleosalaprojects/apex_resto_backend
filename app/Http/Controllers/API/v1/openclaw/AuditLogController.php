<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Reports\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only audit trail for OpenClaw. Lets the bot answer "what changed on
 * my data" prompts without scraping individual model endpoints.
 *
 * Tenant scoping: results are limited to actions performed by users that
 * belong to the same tenant as the authenticated bot user (i.e. users where
 * users.user_id == this tenant's owner id). The audit row's actor user_id
 * is matched against that set.
 */
class AuditLogController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/openclaw/audit-logs — filtered + cursor-paginated audit feed.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'source' => 'nullable|string|in:web,openclaw,mobile,pos,console',
            'event' => 'nullable|string|in:created,updated,deleted,restored,voided,refunded,approved,rejected',
            'auditable_type' => 'nullable|string|max:255',
            'user_id' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:500',
            'cursor' => 'nullable|integer|min:0',
        ]);

        $tz = config('app.timezone');
        $to = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'), $tz)->endOfDay()
            : Carbon::now($tz);
        $from = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'), $tz)->startOfDay()
            : (clone $to)->subDays(29)->startOfDay();
        $limit = (int) $request->input('limit', 100);
        $cursor = (int) $request->input('cursor', 0);

        $tenantOwnerId = (int) auth()->user()->user_id;
        $tenantUserIds = User::query()
            ->where('user_id', $tenantOwnerId)
            ->pluck('id');

        $query = AuditLog::query()
            ->with(['user:id,name', 'apiToken:id,name'])
            ->whereIn('user_id', $tenantUserIds)
            ->whereBetween('created_at', [$from, $to])
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->input('event')))
            ->when($request->filled('auditable_type'), fn ($q) => $q->where('auditable_type', $request->input('auditable_type')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->input('user_id')))
            ->when($cursor > 0, fn ($q) => $q->where('id', '<', $cursor))
            ->orderByDesc('id')
            ->limit($limit + 1);

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $items = $rows->take($limit);
        $nextCursor = $hasMore ? (int) $items->last()->id : null;

        return $this->success([
            'date_from' => $from->toIso8601String(),
            'date_to' => $to->toIso8601String(),
            'limit' => $limit,
            'next_cursor' => $nextCursor,
            'entries' => $items->map(fn (AuditLog $log) => $this->present($log))->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AuditLog $log): array
    {
        return [
            'id' => (int) $log->id,
            'auditable_type' => $this->shortClass($log->auditable_type),
            'auditable_id' => (int) $log->auditable_id,
            'event' => $log->event,
            'source' => $log->source,
            'actor' => [
                'user_id' => $log->user_id !== null ? (int) $log->user_id : null,
                'name' => $log->user?->name,
            ],
            'api_token' => $log->api_token_id !== null ? [
                'id' => (int) $log->api_token_id,
                'name' => $log->apiToken?->name,
            ] : null,
            'changed_fields' => $log->changed_fields,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    private function shortClass(?string $fqcn): ?string
    {
        if ($fqcn === null) {
            return null;
        }

        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
