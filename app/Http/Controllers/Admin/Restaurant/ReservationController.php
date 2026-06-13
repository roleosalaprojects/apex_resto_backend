<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\Restaurant\Reservation;
use App\Models\Restaurant\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    private function denied()
    {
        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function index()
    {
        $access = Role::find(auth()->user()->role_id);
        if (! $access->rstrnt) {
            return $this->denied();
        }

        return view('admin.restaurant.reservations.index', compact('access'));
    }

    public function create()
    {
        if (! auth()->user()->role->rstrnt_create) {
            return $this->denied();
        }
        $reservation = new Reservation;
        $tables = RestaurantTable::where('user_id', auth()->user()->user_id)->get();

        return view('admin.restaurant.reservations.create', compact('reservation', 'tables'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'party_size' => 'required|integer|min:1',
            'reserved_at' => 'required|date',
        ]);

        Reservation::create([
            'customer_id' => $request->customer_id,
            'name' => $request->name,
            'phone' => $request->phone,
            'party_size' => $request->party_size,
            'reserved_at' => $request->reserved_at,
            'duration_minutes' => $request->duration_minutes ?? 90,
            'table_id' => $request->table_id,
            'status' => Reservation::STATUS_PENDING,
            'notes' => $request->notes,
            'store_id' => $request->store_id,
            'user_id' => auth()->user()->user_id,
        ]);

        return redirect()->route('reservations.index')->with('success', 'Reservation added!');
    }

    public function edit(Reservation $reservation)
    {
        if (! auth()->user()->role->rstrnt_update) {
            return $this->denied();
        }
        $tables = RestaurantTable::where('user_id', auth()->user()->user_id)->get();

        return view('admin.restaurant.reservations.edit', compact('reservation', 'tables'));
    }

    public function update(Request $request, Reservation $reservation)
    {
        $request->validate([
            'name' => 'required|string',
            'party_size' => 'required|integer|min:1',
            'reserved_at' => 'required|date',
        ]);

        $reservation->update($request->only([
            'name', 'phone', 'party_size', 'reserved_at',
            'duration_minutes', 'table_id', 'status', 'notes', 'store_id',
        ]));

        return redirect()->route('reservations.index')->with('info', 'Reservation updated!');
    }

    public function destroy(Reservation $reservation)
    {
        if (! auth()->user()->role->rstrnt_delete) {
            return $this->denied();
        }
        $reservation->delete();

        return redirect()->route('reservations.index')->with('success', 'Reservation deleted!');
    }

    public function table()
    {
        $q = Reservation::query()
            ->where('user_id', auth()->user()->user_id)
            ->with('table:id,name');

        return DataTables($q)
            ->addColumn('actions', function (Reservation $r) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                if (auth()->user()->role->rstrnt_update) {
                    $action .= '<a href="'.route('reservations.edit', $r->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1"><i class="fas fa-edit"></i></a>&nbsp';
                }
                if (auth()->user()->role->rstrnt_delete) {
                    $action .= '<form method="POST" action="'.route('reservations.destroy', $r->id).'" id="form_delete_'.$r->id.'">'.method_field('DELETE').csrf_field().'<button type="submit" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1"><i class="fas fa-trash"></i></button></form>';
                }
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    /**
     * FullCalendar feed for the reservations calendar view.
     */
    public function calendarEvents(Request $request): JsonResponse
    {
        $query = Reservation::query()
            ->where('user_id', auth()->user()->user_id)
            ->with('table:id,name');

        if ($request->filled('start')) {
            $query->where('reserved_at', '>=', $request->input('start'));
        }
        if ($request->filled('end')) {
            $query->where('reserved_at', '<=', $request->input('end'));
        }

        $colors = [
            Reservation::STATUS_PENDING => config('colors.warning', '#ffc107'),
            Reservation::STATUS_CONFIRMED => config('colors.primary', '#0d6efd'),
            Reservation::STATUS_SEATED => config('colors.info', '#0dcaf0'),
            Reservation::STATUS_COMPLETED => config('colors.success', '#198754'),
            Reservation::STATUS_NO_SHOW => config('colors.danger', '#dc3545'),
            Reservation::STATUS_CANCELLED => config('colors.secondary', '#6c757d'),
        ];

        $events = $query->get()->map(function (Reservation $r) use ($colors) {
            return [
                'id' => $r->id,
                'title' => $r->name.' ('.$r->party_size.' pax)',
                'start' => $r->reserved_at->toIso8601String(),
                'end' => $r->reserved_at->copy()->addMinutes($r->duration_minutes)->toIso8601String(),
                'color' => $colors[$r->status] ?? '#6c757d',
                'extendedProps' => [
                    'status' => $r->status,
                    'phone' => $r->phone,
                    'table' => $r->table?->name,
                    'notes' => $r->notes,
                ],
            ];
        });

        return response()->json($events->values());
    }
}
