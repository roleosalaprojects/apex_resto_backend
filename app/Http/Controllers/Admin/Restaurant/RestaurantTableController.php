<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\Restaurant\RestaurantTable;
use App\Models\Settings\Store;
use Illuminate\Http\Request;

class RestaurantTableController extends Controller
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

        return view('admin.restaurant.tables.index', compact('access'));
    }

    /**
     * Live floor map: tables grouped by area with their open orders,
     * refreshed by polling floorplanData().
     */
    public function floorplan()
    {
        $access = Role::find(auth()->user()->role_id);
        if (! $access->rstrnt) {
            return $this->denied();
        }

        return view('admin.restaurant.floorplan.index', compact('access'));
    }

    public function floorplanData()
    {
        $userId = auth()->user()->user_id;

        // Joined-table aware: every table of a joined party reports the
        // same open order (pivot first, primary table_id fallback).
        $openOrders = \App\Models\Pos\Order::query()
            ->where('user_id', $userId)
            ->whereNotNull('order_type')
            ->whereNull('sales_id')
            ->whereNotIn('status', [
                \App\Models\Pos\Order::STATUS_CANCELLED,
                \App\Models\Pos\Order::STATUS_COMPLETED,
            ])
            ->whereNotNull('table_id')
            ->with('tables:id')
            ->get(['id', 'table_id', 'reference', 'pax', 'amount', 'status', 'created_at']);

        $orderByTable = [];
        foreach ($openOrders as $order) {
            $tableIds = $order->tables->pluck('id')->all() ?: [$order->table_id];
            foreach ($tableIds as $tableId) {
                $orderByTable[$tableId] = $order;
            }
        }

        $tables = RestaurantTable::query()
            ->where('user_id', $userId)
            ->orderBy('area')
            ->orderBy('name')
            ->get()
            ->map(function (RestaurantTable $table) use ($orderByTable) {
                $open = $orderByTable[$table->id] ?? null;

                return [
                    'id' => $table->id,
                    'name' => $table->name,
                    'area' => $table->area ?: 'Main',
                    'seats' => $table->seats,
                    'status' => (int) $table->status,
                    'edit_url' => route('restaurant-tables.edit', $table),
                    'open_order' => $open ? [
                        'reference' => $open->reference,
                        'pax' => $open->pax,
                        'amount' => (float) $open->amount,
                        'opened_at' => optional($open->created_at)->toIso8601String(),
                    ] : null,
                ];
            });

        return response()->json(['tables' => $tables]);
    }

    public function create()
    {
        if (! auth()->user()->role->rstrnt_create) {
            return $this->denied();
        }
        $table = new RestaurantTable;
        $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();

        return view('admin.restaurant.tables.create', compact('table', 'stores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'seats' => 'required|integer|min:1',
            'store_id' => 'nullable|integer',
        ]);

        RestaurantTable::create([
            'name' => $request->name,
            'number' => $request->number,
            'area' => $request->area,
            'seats' => $request->seats,
            'status' => RestaurantTable::STATUS_AVAILABLE,
            'store_id' => $request->store_id,
            'user_id' => auth()->user()->user_id,
        ]);

        return redirect()->route('restaurant-tables.index')->with('success', 'Table added!');
    }

    public function edit(RestaurantTable $restaurant_table)
    {
        if (! auth()->user()->role->rstrnt_update) {
            return $this->denied();
        }
        $table = $restaurant_table;
        $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();

        return view('admin.restaurant.tables.edit', compact('table', 'stores'));
    }

    public function update(Request $request, RestaurantTable $restaurant_table)
    {
        $request->validate([
            'name' => 'required|string',
            'seats' => 'required|integer|min:1',
        ]);

        $restaurant_table->update([
            'name' => $request->name,
            'number' => $request->number,
            'area' => $request->area,
            'seats' => $request->seats,
            'status' => $request->filled('status') ? (int) $request->status : $restaurant_table->status,
            'store_id' => $request->store_id,
        ]);

        return redirect()->route('restaurant-tables.index')->with('info', 'Table updated!');
    }

    public function destroy(RestaurantTable $restaurant_table)
    {
        if (! auth()->user()->role->rstrnt_delete) {
            return $this->denied();
        }
        $restaurant_table->delete();

        return redirect()->route('restaurant-tables.index')->with('success', 'Table deleted!');
    }

    public function table()
    {
        $q = RestaurantTable::query()
            ->where('user_id', auth()->user()->user_id)
            ->with('store:id,name');

        return DataTables($q)
            ->addColumn('status_label', fn (RestaurantTable $t) => match ($t->status) {
                RestaurantTable::STATUS_AVAILABLE => 'Available',
                RestaurantTable::STATUS_OCCUPIED => 'Occupied',
                RestaurantTable::STATUS_RESERVED => 'Reserved',
                default => 'Inactive',
            })
            ->addColumn('actions', function (RestaurantTable $t) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                if (auth()->user()->role->rstrnt_update) {
                    $action .= '<a href="'.route('restaurant-tables.edit', $t->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1"><i class="fas fa-edit"></i></a>&nbsp';
                }
                if (auth()->user()->role->rstrnt_delete) {
                    $action .= '<form method="POST" action="'.route('restaurant-tables.destroy', $t->id).'" id="form_delete_'.$t->id.'">'.method_field('DELETE').csrf_field().'<button type="submit" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1"><i class="fas fa-trash"></i></button></form>';
                }
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function select(Request $request)
    {
        $name = $request->term;
        $tables = RestaurantTable::where('user_id', auth()->user()->user_id)
            ->where('name', 'LIKE', "%$name%")
            ->take(50)->get();

        return $tables->map(fn (RestaurantTable $t) => ['id' => $t->id, 'text' => $t->name])->all();
    }
}
