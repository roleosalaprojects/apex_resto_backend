<?php

namespace App\Http\Controllers;

use App\Models\Accounting\Expense;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Foundation\Application|Application|RedirectResponse|Redirector
     */
    public function index()
    {
        // dd(auth()->user()->role_id);
        $access = Role::find(auth()->user()->role_id);
        // dd($access);

        // Check if user has the right to access back office
        if ($access->bck_offc) {
            if ($access->sls) {
                return view('admin.home');
            } else {
                return view('admin.dashboards.calendar');
            }
        } else {
            auth()->guard()->logout();

            return redirect('/login')->with('danger', 'You do not have the rights to access the back office. Please contact your administrator.');
        }

        // dd($roles);
    }

    //  Dashboard Data
    //  Default
    public function default(Request $request)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();
        $store_select = $request->store_select;

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();

        // Years
        $dateDiffYears = $startDate->diffInYears($endDate);
        // Months
        $dateDiffMonths = $startDate->diffInMonths($endDate);
        // Days
        $dateDiffDays = $startDate->diffInDays($endDate);

        // Chart Data Response
        $query = Sale::whereBetween('created_at', [$startDayString, $endDayString]);
        if ($dateDiffYears < 1) {
            if ($dateDiffMonths < 1) {
                if ($dateDiffDays > 1) {
                    $query->select(
                        DB::raw('sum(if(type = 0, total, 0)) as sales'),
                        DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                        DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                        DB::raw('count(id) as receipts'),
                        DB::raw('DATE_FORMAT(created_at, "%b %d, %y") as `time`')
                    )
                        ->groupBy(DB::raw('day(created_at)'));
                } elseif ($dateDiffDays > 0) {
                    $query->select(
                        DB::raw('sum(if(type = 0, total, 0)) as sales'),
                        DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                        DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                        DB::raw('count(id) as receipts'),
                        DB::raw('DATE_FORMAT(created_at, "%h:00 %p") as `time`')
                    )
                        ->groupBy(DB::raw('hour(created_at)'));
                }
            } else {
                $query->select(
                    DB::raw('sum(if(type = 0, total, 0)) as sales'),
                    DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                    DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                    DB::raw('count(id) as receipts'),
                    DB::raw('DATE_FORMAT(created_at, "%b %Y") as `time`')
                )
                    ->groupBy(DB::raw('month(created_at)'));
            }
        } else {
            $query->select(
                DB::raw('sum(if(type = 0, total, 0)) as sales'),
                DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                DB::raw('count(id) as receipts'),
                DB::raw('DATE_FORMAT(created_at, "%Y") as `time`')
            )
                ->groupBy(DB::raw('year(created_at)'));
        }

        if ($request->store_select) {
            $query->where('sales.store_id', $request->store_select);
        }

        $gross = Sale::select(
            DB::raw('sum(if(type = 0, total, 0)) as sales'),
            DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
            DB::raw('sum(if(type = 1, total, 0)) as refunds'),
        )
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($request->store_select) {
            $gross->where('sales.store_id', $request->store_select);
        }

        //      Summary Response
        $data = Sale::whereBetween('created_at', [$startDayString, $endDayString]);
        $data->select(
            DB::raw('sum(if(type = 0, total, -total)) as net_sales'),
            DB::raw('sum(if(type = 0, total, 0)) as sales'),
            DB::raw('sum(if(type = 1, total, 0)) as refunds'),
            DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
            DB::raw('count(id) as receipts')
        );

        if ($request->store_select) {
            $data->where('sales.store_id', $request->store_select);
        }

        $summary = $data->first();

        // Expense total for the same window. Active rows only; same store
        // filter as the sales summary so the "Total Expenses" card stays
        // consistent with the Sales / Refunds / Net Sales cards.
        $expensesQuery = Expense::query()
            ->where('status', Expense::STATUS_ACTIVE)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($request->store_select) {
            $expensesQuery->where('store_id', $request->store_select);
        }

        $expensesTotal = (float) $expensesQuery->sum('amount');

        $summaryArray = $summary !== null
            ? (is_array($summary) ? $summary : $summary->toArray())
            : [];
        $summaryArray['expenses'] = round($expensesTotal, 2);

        // Build per-bucket expense aggregates keyed by the same time labels
        // the sales chart uses, so the inline expenses chart shares the
        // x-axis. expense_date is date-only, so for sub-day windows we
        // bucket by hour-of-the-only-day = 0; otherwise we group by day,
        // month, or year matching the sales bucketing rules above.
        $chartRows = $query->orderBy('created_at')->get();

        if ($dateDiffYears < 1 && $dateDiffMonths < 1 && $dateDiffDays > 0) {
            // Daily buckets within a sub-month window.
            $expenseByLabel = $this->aggregateExpensesByPeriod(
                $request->store_select,
                $startDate,
                $endDate,
                'DATE_FORMAT(expense_date, "%b %d, %y")',
            );
        } elseif ($dateDiffYears < 1 && $dateDiffMonths >= 1) {
            $expenseByLabel = $this->aggregateExpensesByPeriod(
                $request->store_select,
                $startDate,
                $endDate,
                'DATE_FORMAT(expense_date, "%b %Y")',
            );
        } elseif ($dateDiffYears >= 1) {
            $expenseByLabel = $this->aggregateExpensesByPeriod(
                $request->store_select,
                $startDate,
                $endDate,
                'DATE_FORMAT(expense_date, "%Y")',
            );
        } else {
            // Single-day window: expense_date has no hour granularity, so
            // collapse the whole day's expenses onto every hour bucket as 0
            // (we still expose the total via data.expenses for the card).
            $expenseByLabel = [];
        }

        $chart = $chartRows->map(function ($row) use ($expenseByLabel) {
            $arr = is_array($row) ? $row : $row->toArray();
            $label = $arr['time'] ?? null;
            $arr['expenses'] = isset($label) && isset($expenseByLabel[$label])
                ? round((float) $expenseByLabel[$label], 2)
                : 0.0;

            return $arr;
        });

        return response()->json([
            'data' => $summaryArray,
            'chart' => $chart,
        ]);
    }

    /**
     * @return array<string, float>
     */
    private function aggregateExpensesByPeriod(
        $storeSelect,
        Carbon $startDate,
        Carbon $endDate,
        string $labelExpr,
    ): array {
        $q = Expense::query()
            ->where('status', Expense::STATUS_ACTIVE)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($storeSelect) {
            $q->where('store_id', $storeSelect);
        }

        return $q->selectRaw($labelExpr.' as label, COALESCE(SUM(amount), 0) as total')
            ->groupBy('label')
            ->pluck('total', 'label')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
