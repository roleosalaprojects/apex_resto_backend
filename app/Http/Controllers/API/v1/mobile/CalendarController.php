<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Calendar;
use App\Models\CustomerRelations\CustomerCreditTransaction;
use App\Models\InventoryManagement\Purchase;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $startDate = Carbon::parse($request->start)->startOfMonth()->toDateTimeString();
        $endDate = Carbon::parse($request->end)->endOfMonth()->toDateTimeString();
        $events = Calendar::whereBetween('created_at', [$startDate, $endDate])
            ->select('id', 'created_at')
            ->with(['details' => function ($q) {
                $q->select('id', 'title', 'color');
            }])
            ->get();

        return response(['events' => $events, 'dates' => [$startDate, $endDate]]);
    }

    /**
     * Get purchase orders for calendar display
     */
    public function purchases(Request $request): JsonResponse
    {
        $startDate = Carbon::parse($request->start)->startOfMonth()->toDateTimeString();
        $endDate = Carbon::parse($request->end)->endOfMonth()->toDateTimeString();

        $data = Purchase::whereBetween(
            DB::raw('date_add(purchased, interval if(expected >= 1, expected - 1, 0) day)'),
            [$startDate, $endDate]
        )
            ->where('purchases.user_id', auth()->user()->user_id)
            ->where('purchases.status', '<', 3) // Exclude deleted
            ->leftJoin('suppliers as s', 's.id', 'purchases.supplier_id')
            ->leftJoin('stores as st', 'st.id', 'purchases.store_id')
            ->select(
                'purchases.id as id',
                'po',
                'total',
                'purchased as purchase_date',
                'expected as terms',
                'items',
                'received',
                'purchases.status',
                'purchases.approval_status',
                DB::raw('s.name as supplier'),
                DB::raw('st.name as store'),
                DB::raw('concat("PO#: ", purchases.po, " - ", format(total, 2)) as title'),
                // Color based on status: pending=orange, complete=green, issue=red
                DB::raw('
                    if(purchases.status = 1, "#fd7e14", if(purchases.status = 0, "#50cd89", "#f1416c")) as color
                '),
                DB::raw('"true" as allDay'),
                DB::raw('"purchase" as type'),
                DB::raw('date_format((date_add(purchases.purchased, interval if(purchases.expected >= 1, purchases.expected - 1, 0) day)), "%Y-%m-%d") as due_date'),
            )
            ->get();

        return $this->success(['purchases' => $data]);
    }

    /**
     * Get credit dues for calendar display
     */
    public function creditDues(Request $request): JsonResponse
    {
        $startDate = Carbon::parse($request->start)->startOfMonth()->toDateTimeString();
        $endDate = Carbon::parse($request->end)->endOfMonth()->toDateTimeString();
        $now = Carbon::now()->toDateString();
        $soonDate = Carbon::now()->addDays(7)->toDateString();

        $data = CustomerCreditTransaction::where('customer_credit_transactions.type', 'charge')
            ->whereNotNull('customer_credit_transactions.due_date')
            ->whereBetween('customer_credit_transactions.due_date', [$startDate, $endDate])
            ->leftJoin('customers as c', 'c.id', 'customer_credit_transactions.customer_id')
            ->leftJoin('stores as st', 'st.id', 'customer_credit_transactions.store_id')
            ->select(
                'customer_credit_transactions.id as id',
                DB::raw('c.name as customer_name'),
                'customer_credit_transactions.amount',
                'customer_credit_transactions.due_date',
                DB::raw('st.name as store'),
                'customer_credit_transactions.created_at',
                'customer_credit_transactions.reference_id',
                DB::raw('concat("Credit: ", c.name, " - ", format(customer_credit_transactions.amount, 2)) as title'),
                // Color: overdue=red, due<=7days=orange, upcoming=green
                DB::raw("
                    case
                        when customer_credit_transactions.due_date < '{$now}' then '#f1416c'
                        when customer_credit_transactions.due_date <= '{$soonDate}' then '#fd7e14'
                        else '#50cd89'
                    end as color
                "),
                DB::raw("
                    case
                        when customer_credit_transactions.due_date < '{$now}' then 'overdue'
                        when customer_credit_transactions.due_date <= '{$soonDate}' then 'due_soon'
                        else 'upcoming'
                    end as status
                "),
                DB::raw('"true" as allDay'),
                DB::raw('"credit" as type'),
                DB::raw('c.credit_balance as customer_balance'),
            )
            ->get();

        return $this->success(['credit_dues' => $data]);
    }
}
