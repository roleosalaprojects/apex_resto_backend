<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Pos\Receipt;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Pos\Xreading;
use App\Models\Pos\Zreading;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // Sales Summary
    // View
    public function salesSummary()
    {
        if (auth()->user()->role->sls) {
            return view('admin.reports.reports.sales_summary');
        } else {
            return redirect('/admin/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }
    }

    // Data
    public function getSalesSummaryData(Request $request)
    {
        $startDate = Carbon::parse($request->input('startDate'))->startOfDay();
        $endDate = Carbon::parse($request->input('endDate'))->endOfDay();

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
                        DB::raw('DATE_FORMAT(created_at, "%b %d, %y") as `time`'))
                        ->groupBy(DB::raw('day(created_at)'));
                } elseif ($dateDiffDays > 0) {
                    $query->select(
                        DB::raw('sum(if(type = 0, total, 0)) as sales'),
                        DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                        DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                        DB::raw('count(id) as receipts'),
                        DB::raw('DATE_FORMAT(created_at, "%h:00 %p") as `time`'),
                    )
                        ->groupBy(DB::raw('hour(created_at)'));
                }
            } else {
                $query->select(
                    DB::raw('sum(if(type = 0, total, 0)) as sales'),
                    DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                    DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                    DB::raw('count(id) as receipts'),
                    DB::raw('DATE_FORMAT(created_at, "%b %Y") as `time`'),
                )
                    ->groupBy(DB::raw('month(created_at)'));
            }
        } else {
            $query->select(
                DB::raw('sum(if(type = 0, total, 0)) as sales'),
                DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                DB::raw('count(id) as receipts'),
                DB::raw('DATE_FORMAT(created_at, "%Y") as `time`'),
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

        return response()->json(['sales' => $gross->first(), 'chart' => $query->get(), 'table' => DataTables($query)->make(true)]);
    }

    // Receipts
    // View
    public function receipts()
    {
        return view('admin.reports.reports.receipts');
    }

    public function getReceiptsData(Request $request)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();
        $sales = Sale::with([
            'sold_by',
            'customer',
            'pos',
            'store',
        ])
            ->whereBetween('created_at', [$startDate, $endDate]);
        if ($request->store_select) {
            $sales->where('store_id', $request->store_select);
        }

        $table = DataTables($sales)
            ->addColumn('actions', function (Sale $sale) {
                $q = '<div class="d-flex justify-content-end">';
                $q .= '<a href="'.route('receipts.show', $sale->id).'" class="btn btn-active-color-primary btn-bg-light btn-icon"><i class="fas fa-eye"></i></a>';
                $q .= '</div>';

                return $q;
            })
            ->rawColumns(['actions'])
            ->make(true);

        return response()->json(['table' => $table]);
    }

    // Data
    public function viewReceipt(Sale $sale)
    {
        $supplier = Receipt::first();
        $sale = Sale::with([
            'sold_by',
            'customer',
            'pos',
            'store',
            'lines' => function ($q) {
                $q->with([
                    'item',
                    // 'unit',
                    'discount',
                    'discountBy',
                ]);
            },
        ])->where('id', $sale->id)->first();

        // return $sale;
        return view('admin.reports.reports.receipt', compact('sale', 'supplier'));
    }

    // Print Receipt
    public function printReceipt(Sale $sale)
    {
        $supplier = Receipt::first();
        $sale = Sale::with([
            'sold_by',
            'customer',
            'pos',
            'store',
            'lines' => function ($q) {
                $q->with([
                    'item',
                    // 'unit',
                    'discount',
                    'discountBy',
                ]);
            },
        ])->where('id', $sale->id)->first();

        // return $sale;
        return view('admin.reports.reports.print_receipt', compact('sale', 'supplier'));
    }

    // Sales by Category
    public function categories()
    {
        return view('admin.reports.reports.category');
    }

    public function getCategoriesData(Request $request)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();

        $categories = SaleLine::whereBetween('sale_lines.created_at', [$startDayString, $endDayString])
            ->leftJoin('sales', 'sales.id', 'sale_lines.sales_id')
            ->leftJoin('items', 'sale_lines.item_id', 'items.id')
            ->leftJoin('categories', 'categories.id', 'items.category_id')
            ->select(
                DB::raw('sum(if(sales.type = 0, sub_total, -sub_total)) as net_sales'),
                DB::raw('sum(if(sales.type = 0, unit_qty * qty, -(unit_qty * qty))) as item_sold'),
                DB::raw('categories.name as category')
            )
            // ->with(['category'])
            ->where('categories.status', true)
            ->groupBy('category');
        if ($request->store_select) {
            $categories->where('sales.store_id', $request->store_id);
        }
        $table = DataTables($categories)
            ->make(true);

        return response()->json(['data' => $categories->get(), 'table' => $table]);
    }

    // Sales by Supplier
    // View
    public function suppliers()
    {
        return view('admin.reports.reports.supplier');
    }

    // Data
    public function getSupplierData(Request $request)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();

        $suppliers = SaleLine::whereBetween('sale_lines.created_at', [$startDayString, $endDayString])
            ->leftJoin('sales', 'sales.id', 'sale_lines.sales_id')
            ->leftJoin('items', 'sale_lines.item_id', 'items.id')
            ->leftJoin('suppliers', 'suppliers.id', 'items.supplier_id')
            ->select(
                DB::raw('sum(if(sales.type = 0, sub_total, -sub_total)) as net_sales'),
                DB::raw('sum(if(sales.type = 0, unit_qty * qty, -(unit_qty * qty))) as item_sold'),
                DB::raw('suppliers.name as supplier')
            )
            // ->with(['category'])
            ->where('suppliers.status', true)
            ->groupBy('supplier');

        if ($request->store_select) {
            $suppliers->where('sales.store_id', $request->store_select);
        }

        $table = DataTables($suppliers)
            ->make(true);

        return response()->json(['data' => $suppliers->get(), 'table' => $table]);
    }

    // Readings
    public function readings()
    {
        return view('admin.reports.reports.readings');
    }

    public function getReadingsData(Request $request): mixed
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $query = Zreading::query()
            ->join('stores as s', 's.id', '=', 'zreadings.store_id')
            ->join('pos as p', 'p.id', '=', 'zreadings.pos_id')
            ->leftJoin('users as u', 'u.id', '=', 'zreadings.user_id')
            ->whereBetween('zreadings.created_at', [$startDate, $endDate])
            ->select([
                'zreadings.id',
                's.name as store',
                'zreadings.counter',
                'p.number as terminal',
                'zreadings.transactions',
                DB::raw('format(zreadings.cash, 2) as gross'),
                DB::raw('format(zreadings.refund, 2) as refunds'),
                DB::raw('format(zreadings.cash - zreadings.refund, 2) as net'),
                'u.name as employee',
                'zreadings.created_at as date',
            ]);

        if ($request->store_select) {
            $query->where('zreadings.store_id', $request->store_select);
        }

        return datatables($query)->make(true);
    }

    // Show
    public function reading($type, $id)
    {
        $reading = [];
        if ($type == 'z') {
            $reading = Zreading::where('id', $id)
                ->with([
                    'pos',
                    'store',
                    'gen',
                ])
                ->first();
        } elseif ($type == 'x') {
            $reading = Xreading::where('id', $id)
                ->with([
                    'pos',
                    'store',
                    'gen',
                ])
                ->first();
        } else {
            abort(404);
        }

        // return $reading;
        return view('admin.reports.reports.reading', compact('reading', 'type'));
    }

    // Sales By Item
    // View
    public function items()
    {
        return view('admin.reports.reports.sales_by_items');
    }

    // Data
    public function itemsData(Request $request)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();

        $items = SaleLine::whereBetween('s.created_at', [$startDayString, $endDayString])
            ->leftJoin('sales as s', 'sale_lines.sales_id', 's.id')
            ->leftJoin('items as i', 'i.id', 'sale_lines.item_id')
            ->select(
                DB::raw('sum(if(s.type = 0, (qty * unit_qty), -(qty * unit_qty))) as items_sold'),
                DB::raw('sum(if(s.type = 0, sub_total, -sub_total)) as net_sales'),
                DB::raw('sum(if(s.type = 0, sub_total - (qty * sale_lines.cost * unit_qty), -(sub_total - (qty * sale_lines.cost * unit_qty)))) as revenue'),
                DB::raw('i.name as item'),
                DB::raw('i.id as item_id'),
            )
            ->groupBy('item_id');
        if ($request->store_select) {
            $items->where('s.store_id', $request->store_select);
        }
        $table = DataTables($items)->make(true);

        return response()->json(['table' => $table, 'top' => $items->orderByDesc('net_sales')->take(50)->get()]);
    }

    // Terminals
    // View
    public function terminals()
    {
        return view('admin.reports.reports.terminals');
    }

    // Data
    public function terminalsData(Request $request)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();

        $terminals = Sale::whereBetween('sales.created_at', [$startDayString, $endDayString])
            ->leftJoin('pos as p', 'p.id', 'sales.pos_id')
            ->select(
                DB::raw('sum(if(sales.type = 0, total, -total)) as net_sales'),
                DB::raw('sum(if(sales.type = 0, total, 0)) as gross'),
                DB::raw('sum(if(sales.type = 1, total, 0)) as refunds'),
                DB::raw('p.name as terminals'),
            )
            ->groupBy('pos_id');
        if ($request->store_select) {
            $terminals->where('sales.store_id', $request->store_select);
        }
        $table = DataTables($terminals)
            ->make(true);

        return response()->json(['table' => $table, 'terminals' => $terminals->orderBy('pos_id')->get()]);
    }

    // Year by Year Comparison
    // View
    public function yearByYearComparison()
    {
        if (auth()->user()->role->sls) {
            return view('admin.reports.reports.year_by_year_comparison');
        }

        return redirect('/admin/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    // Data
    public function getYearByYearComparisonData(Request $request)
    {
        $yearsCount = max(2, min(10, (int) $request->input('years_count', 3)));
        $endYear = (int) $request->input('end_year', Carbon::now()->year);
        $startYear = $endYear - $yearsCount + 1;

        $startDate = Carbon::create($startYear, 1, 1, 0, 0, 0)->startOfDay();
        $endDate = Carbon::create($endYear, 12, 31, 23, 59, 59)->endOfDay();

        $yearlyTotals = Sale::whereBetween('sales.created_at', [$startDate, $endDate])
            ->select(
                DB::raw('YEAR(sales.created_at) as year'),
                DB::raw('sum(if(type = 0, total, 0)) as sales'),
                DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                DB::raw('sum(if(type = 0, total, 0)) - sum(if(type = 1, total, 0)) as net_sales'),
                DB::raw('sum(if(type = 0, profit, -profit)) as profit'),
                DB::raw('count(id) as receipts'),
            )
            ->groupBy(DB::raw('YEAR(sales.created_at)'))
            ->orderBy('year');

        $monthlyBreakdown = Sale::whereBetween('sales.created_at', [$startDate, $endDate])
            ->select(
                DB::raw('YEAR(sales.created_at) as year'),
                DB::raw('MONTH(sales.created_at) as month'),
                DB::raw('sum(if(type = 0, total, 0)) as sales'),
                DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                DB::raw('sum(if(type = 0, total, 0)) - sum(if(type = 1, total, 0)) as net_sales'),
                DB::raw('sum(if(type = 0, profit, -profit)) as profit'),
                DB::raw('count(sales.id) as receipts'),
            )
            ->groupBy(DB::raw('YEAR(sales.created_at)'), DB::raw('MONTH(sales.created_at)'))
            ->orderBy('year')
            ->orderBy('month');

        if ($request->store_select) {
            $yearlyTotals->where('sales.store_id', $request->store_select);
            $monthlyBreakdown->where('sales.store_id', $request->store_select);
        }

        $totals = $yearlyTotals->get();
        $monthly = $monthlyBreakdown->get();

        $years = range($startYear, $endYear);
        $totalsByYear = $totals->keyBy('year');

        $rows = [];
        $previousNetSales = null;
        foreach ($years as $year) {
            $row = $totalsByYear->get($year);
            $netSales = $row->net_sales ?? 0;
            $growth = null;
            if ($previousNetSales !== null && $previousNetSales != 0) {
                $growth = (($netSales - $previousNetSales) / $previousNetSales) * 100;
            }
            $rows[] = [
                'year' => $year,
                'sales' => (float) ($row->sales ?? 0),
                'refunds' => (float) ($row->refunds ?? 0),
                'net_sales' => (float) $netSales,
                'profit' => (float) ($row->profit ?? 0),
                'receipts' => (int) ($row->receipts ?? 0),
                'growth' => $growth,
            ];
            $previousNetSales = $netSales;
        }

        $metricKeys = ['sales', 'refunds', 'net_sales', 'profit', 'receipts'];
        $monthlyByYearMonth = [];
        foreach ($years as $year) {
            for ($m = 1; $m <= 12; $m++) {
                $monthlyByYearMonth[$year][$m] = array_fill_keys($metricKeys, 0.0);
            }
        }
        foreach ($monthly as $entry) {
            $y = (int) $entry->year;
            $m = (int) $entry->month;
            $monthlyByYearMonth[$y][$m] = [
                'sales' => (float) $entry->sales,
                'refunds' => (float) $entry->refunds,
                'net_sales' => (float) $entry->net_sales,
                'profit' => (float) $entry->profit,
                'receipts' => (int) $entry->receipts,
            ];
        }

        $series = [];
        foreach ($years as $year) {
            $series[] = [
                'year' => $year,
                'data' => array_map(static fn ($cell) => $cell['net_sales'], array_values($monthlyByYearMonth[$year])),
            ];
        }

        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthlyRows = [];
        for ($month = 1; $month <= 12; $month++) {
            $values = [];
            foreach ($years as $index => $year) {
                $current = $monthlyByYearMonth[$year][$month];
                $prev = $index > 0 ? $monthlyByYearMonth[$years[$index - 1]][$month] : null;
                $cell = ['year' => $year];
                foreach ($metricKeys as $key) {
                    $cell[$key] = $current[$key];
                    $cell[$key.'_growth'] = null;
                    if ($prev !== null && $prev[$key] != 0) {
                        $cell[$key.'_growth'] = (($current[$key] - $prev[$key]) / $prev[$key]) * 100;
                    }
                }
                $values[] = $cell;
            }
            $monthlyRows[] = [
                'month' => $monthLabels[$month - 1],
                'month_num' => $month,
                'values' => $values,
            ];
        }

        $latest = $rows[count($rows) - 1] ?? null;
        $previous = $rows[count($rows) - 2] ?? null;

        $summary = [
            'latest_year' => $latest['year'] ?? $endYear,
            'latest_net_sales' => $latest['net_sales'] ?? 0,
            'latest_profit' => $latest['profit'] ?? 0,
            'previous_net_sales' => $previous['net_sales'] ?? 0,
            'yoy_growth' => $latest['growth'] ?? null,
        ];

        return response()->json([
            'summary' => $summary,
            'rows' => $rows,
            'series' => $series,
            'monthly_rows' => $monthlyRows,
            'years' => $years,
        ]);
    }

    // Employees
    // View
    public function employees()
    {
        return view('admin.reports.reports.employees');
    }

    // Data
    public function employeesData(Request $request)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();

        $employees = Sale::whereBetween('sales.created_at', [$startDayString, $endDayString])
            ->leftJoin('users as u', 'u.id', 'sales_by')
            ->select(
                DB::raw('sum(if(sales.type = 0, total, -total)) as net_sales'),
                DB::raw('sum(if(sales.type = 0, profit, -profit)) as revenue'),
                DB::raw('sum(if(sales.type = 0, total, 0)) as sales'),
                DB::raw('sum(if(sales.type = 1, total, 0)) as refunds'),
                DB::raw('u.name as employees'),
            )
            ->groupBy('sales_by');
        if ($request->store_select) {
            $employees->where('sales.store_id', $request->store_select);
        }
        $table = DataTables($employees)->make(true);

        return response()->json(['employees' => $employees->get(), 'table' => $table]);
    }
}
