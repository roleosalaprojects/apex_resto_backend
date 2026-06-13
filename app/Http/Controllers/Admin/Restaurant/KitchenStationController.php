<?php

namespace App\Http\Controllers\Admin\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\Restaurant\KitchenStation;
use App\Models\Settings\Store;
use Illuminate\Http\Request;

class KitchenStationController extends Controller
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

        return view('admin.restaurant.stations.index', compact('access'));
    }

    public function create()
    {
        if (! auth()->user()->role->rstrnt_create) {
            return $this->denied();
        }
        $station = new KitchenStation;
        $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();

        return view('admin.restaurant.stations.create', compact('station', 'stores'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string']);

        KitchenStation::create([
            'name' => $request->name,
            'store_id' => $request->store_id,
            'status' => true,
            'user_id' => auth()->user()->user_id,
        ]);

        return redirect()->route('kitchen-stations.index')->with('success', 'Station added!');
    }

    public function edit(KitchenStation $kitchen_station)
    {
        if (! auth()->user()->role->rstrnt_update) {
            return $this->denied();
        }
        $station = $kitchen_station;
        $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();

        return view('admin.restaurant.stations.edit', compact('station', 'stores'));
    }

    public function update(Request $request, KitchenStation $kitchen_station)
    {
        $request->validate(['name' => 'required|string']);

        $kitchen_station->update([
            'name' => $request->name,
            'store_id' => $request->store_id,
            'status' => $request->boolean('status', true),
        ]);

        return redirect()->route('kitchen-stations.index')->with('info', 'Station updated!');
    }

    public function destroy(KitchenStation $kitchen_station)
    {
        if (! auth()->user()->role->rstrnt_delete) {
            return $this->denied();
        }
        $kitchen_station->delete();

        return redirect()->route('kitchen-stations.index')->with('success', 'Station deleted!');
    }

    public function table()
    {
        $q = KitchenStation::query()
            ->where('user_id', auth()->user()->user_id)
            ->with('store:id,name');

        return DataTables($q)
            ->addColumn('actions', function (KitchenStation $s) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                if (auth()->user()->role->rstrnt_update) {
                    $action .= '<a href="'.route('kitchen-stations.edit', $s->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1"><i class="fas fa-edit"></i></a>&nbsp';
                }
                if (auth()->user()->role->rstrnt_delete) {
                    $action .= '<form method="POST" action="'.route('kitchen-stations.destroy', $s->id).'" id="form_delete_'.$s->id.'">'.method_field('DELETE').csrf_field().'<button type="submit" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1"><i class="fas fa-trash"></i></button></form>';
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
        $stations = KitchenStation::where('user_id', auth()->user()->user_id)
            ->where('status', true)
            ->where('name', 'LIKE', "%$name%")
            ->take(50)->get();

        return $stations->map(fn (KitchenStation $s) => ['id' => $s->id, 'text' => $s->name])->all();
    }
}
