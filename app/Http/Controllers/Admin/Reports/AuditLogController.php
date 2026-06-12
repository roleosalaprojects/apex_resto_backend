<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\Reports\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! optional(auth()->user()?->role)->sttngs) {
                throw new HttpException(403, 'You do not have access to the audit trail.');
            }

            return $next($request);
        });
    }

    public function index(): View
    {
        $access = Role::find(auth()->user()->role_id);

        return view('admin.reports.audit_logs.index', compact('access'));
    }

    public function table(Request $request): View
    {
        $query = AuditLog::query()
            ->with(['user:id,name', 'apiToken:id,name'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('api_token_id')) {
            $query->where('api_token_id', $request->api_token_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(20)->withQueryString();

        return view('admin.reports.audit_logs.table', compact('logs'));
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load(['user:id,name', 'apiToken:id,name']);
        $access = Role::find(auth()->user()->role_id);

        return view('admin.reports.audit_logs.show', compact('auditLog', 'access'));
    }
}
