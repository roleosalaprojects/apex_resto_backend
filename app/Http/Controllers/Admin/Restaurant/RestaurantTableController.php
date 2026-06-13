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
