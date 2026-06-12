<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Reports\ReportRecipient;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ScheduledReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function index()
    {
        $access = auth()->user()->role;

        if (! $access->sls) {
            return redirect('/admin/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

        $userId = auth()->user()->user_id;
        $recipients = ReportRecipient::where('user_id', $userId)->get();

        return view('admin.reports.reports.scheduled.index', compact('recipients', 'access'));
    }

    public function storeRecipient(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'report_type' => 'required|in:daily,weekly,both',
        ]);

        $userId = auth()->user()->user_id;

        ReportRecipient::create([
            'user_id' => $userId,
            'email' => $request->input('email'),
            'report_type' => $request->input('report_type'),
            'is_active' => true,
        ]);

        return redirect()->route('reports.scheduled.index')->with('msg', 'Recipient added successfully.');
    }

    public function destroyRecipient(ReportRecipient $reportRecipient)
    {
        $reportRecipient->delete();

        return redirect()->route('reports.scheduled.index')->with('msg', 'Recipient removed successfully.');
    }

    public function preview(Request $request)
    {
        $userId = auth()->user()->user_id;
        $type = $request->input('type', 'daily');

        $data = $this->reportService->generateReportData($userId, $type);

        return response()->json($data);
    }
}
