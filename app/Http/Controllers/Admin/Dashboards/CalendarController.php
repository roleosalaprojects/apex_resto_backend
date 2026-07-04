<?php

namespace App\Http\Controllers\Admin\Dashboards;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\StoreRequest;
use App\Http\Requests\Calendar\UpdateRequest;
use App\Models\Calendar;
use App\Models\InventoryManagement\Purchase;
use App\Models\Pos\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): View
    {
        $tables = \App\Models\Restaurant\RestaurantTable::query()
            ->where('user_id', auth()->user()->user_id)
            ->where('status', '!=', \App\Models\Restaurant\RestaurantTable::STATUS_INACTIVE)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.dashboards.calendar', compact('tables'));
    }

    /**
     * Reservations as a FullCalendar event source (status-coloured).
     */
    public function reservationsData(Request $request): \Illuminate\Http\JsonResponse
    {
        $colors = [
            \App\Models\Restaurant\Reservation::STATUS_PENDING => config('colors.warning'),
            \App\Models\Restaurant\Reservation::STATUS_CONFIRMED => config('colors.success'),
            \App\Models\Restaurant\Reservation::STATUS_SEATED => config('colors.info'),
        ];

        $reservations = \App\Models\Restaurant\Reservation::query()
            ->where('user_id', auth()->user()->user_id)
            ->whereIn('status', array_keys($colors))
            ->with('table:id,name')
            ->get()
            ->map(fn ($r) => [
                'id' => 'rsv-'.$r->id,
                'title' => $r->name.' · '.$r->party_size.' pax',
                'start' => $r->reserved_at->toIso8601String(),
                'end' => $r->reserved_at->copy()->addMinutes($r->duration_minutes ?? 90)->toIso8601String(),
                'color' => $colors[$r->status],
                'editable' => false,
                'type' => 'reservation',
                'reservation_id' => $r->id,
                'guest' => $r->name,
                'phone' => $r->phone,
                'party_size' => $r->party_size,
                'table' => $r->table?->name,
                'status' => $r->status,
                'notes' => $r->notes,
            ]);

        return response()->json($reservations);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        $validated = $request->validated();
        /*
            Fullcalendar event format
            {
                id: '1',
                title: 'Something',
                start: '',
                end: '',
                color: '',
                allDay: true or false,
            }
        */
        $calendar = Calendar::create($validated);

        return response([
            'success' => true,
            'id' => $calendar->id,
            'message' => "Event {$validated['title']}, created!",
        ]);
    }

    /**
     * Get calendar events for FullCalendar.
     */
    public function events(Request $request): Response
    {
        $startDate = Carbon::parse($request->start)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->end)->endOfDay()->toDateTimeString();

        $data = Calendar::whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return response($data);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Calendar  $calendar
     */
    public function show(Request $request): Response
    {
        $startDate = Carbon::parse($request->start)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->end)->endOfDay()->toDateTimeString();

        $data = Calendar::whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return response($data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Calendar  $calendar
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Calendar $calendar)
    {
        $calendar->update($request->validated());

        return response([
            'success' => true,
            'message' => "Event $calendar->title, successfully updated!",
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Calendar  $calendar
     */
    public function destroy(Calendar $calendar): Response
    {
        $calendar->delete();

        return response([
            'success' => true,
            'message' => "Event $calendar->title, has been deleted!",
        ]);
    }

    public function salesData(Request $request): \Illuminate\Http\JsonResponse
    {
        $startDate = Carbon::parse($request->start)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->end)->endOfDay()->toDateTimeString();
        $data = Sale::whereBetween('sales.created_at', [$startDate, $endDate])
            ->leftJoin('users as u', 'u.id', 'sales_by')
            ->leftJoin('pos as p', 'p.id', 'sales.pos_id')
            ->select(
                //                DB::raw('concat(u.name, " - Net Sales: ",format(sum(if(sales.type = 0, total, -total)), 2)) as title'),
                DB::raw('concat(p.name, ": ", u.name, " ", "Sales: ",format(sum(if(sales.type = 0, total, -total)), 2)) as title'),
                DB::raw('date_format(sales.created_at, "%Y-%m-%dT%TZ") as start'),
                //                DB::raw('date_format(sales.created_at, "%Y-%m-%d %H") as formatted_date'),
                DB::raw('date_format(sales.created_at, "%Y-%m-%d") as formatted_date'),
                DB::raw('u.name as transacted_by'),
                DB::raw('"#0095e8" as color'),
                DB::raw('"true" as allDay'),
                DB::raw('"sale" as type'),
            )
            ->orderBy('p.name', 'asc')
            ->groupBy('formatted_date', 'transacted_by')
            ->get();

        return response()->json($data);
    }

    public function purchasesData(Request $request): \Illuminate\Http\JsonResponse
    {
        $startDate = Carbon::parse($request->start)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->end)->endOfDay()->toDateTimeString();
        $data = Purchase::whereBetween(DB::raw('date_add(purchased, interval if(expected >= 1, expected - 1, 0) day)'), [$startDate, $endDate])
            ->leftJoin('suppliers as s', 's.id', 'purchases.supplier_id')
            ->select(
                'purchases.id as id',
                'po',
                'total',
                'purchased as purchase_date',
                'expected as terms',
                'items',
                'received',
                'total',
                DB::raw('s.name as supplier'),
                DB::raw('concat("PO#: ",purchases.po, " - ", format(total, 2)) as title'),
                // Oranges style.bundle.css
                DB::raw('
                    if(purchases.status = 1, "#fd7e14", if(purchases.status = 0, "#50cd89", "#f1416c")) as color
                '),
                DB::raw('"true" as allDay'),
                DB::raw('"purchase" as type'),
                DB::raw('date_format((date_add(purchases.purchased, interval if(purchases.expected >= 1, purchases.expected - 1, 0) day)), "%Y-%m-%dT%TZ") as start'),
            )
            ->get();

        return response()->json($data);
    }
}
