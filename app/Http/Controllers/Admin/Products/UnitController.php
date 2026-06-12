<?php

namespace App\Http\Controllers\Admin\Products;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\StoreRequest;
use App\Http\Requests\Unit\UpdateRequest;
use App\Models\Employees\Role;
use App\Models\Products\ItemUnit;
use App\Models\Products\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Exceptions\Exception;

use function response;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->itms) {
            $units = Unit::where('user_id', auth()->user()->user_id)->where('status', true)->get();

            return view('admin.products.units.index', compact('units', 'access'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");

    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(StoreRequest $request)
    {
        $validated = $request->validated();
        $unit = Unit::create([
            'name' => $validated['name'],
            'status' => true,
            'user_id' => auth()->user()->user_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Unit '.strtoupper($unit->nmae).' has been created.',
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Unit  $unit
     * @return Response
     */
    public function show(Unit $unit)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(UpdateRequest $request, Unit $unit)
    {
        $validated = $request->validated();
        $unit->update([
            'name' => $validated['name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Unit has been updated.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return JsonResponse
     */
    public function destroy(Unit $unit)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->itms_delete) {
            $unit->update([
                'status' => false,
            ]);
            ItemUnit::where('unit_id', $unit->id)->update([
                'status' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Unit has been deleted.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'You don\'t have rights to access this. Please contact administrator.',
        ]);

    }

    public function table()
    {
        $helper = new HelperController;
        $q = Unit::query()->select('id', 'name')->where('status', true);
        try {
            return DataTables($q)
                ->addColumn('actions', function (Unit $unit) use ($helper) {
                    return $helper->actionButtonsReturnModal($unit, 'units', 'Units');
                })
                ->rawColumns(['actions'])
                ->make(true);
        } catch (Exception $e) {
            response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }

        return [];
    }

    public function getUnit(Unit $unit)
    {
        return response()->json([
            'id' => $unit->id,
            'name' => $unit->name,
        ]);
    }

    public function select(Request $request)
    {
        $name = $request->search;
        $units = Unit::where('name', 'LIKE', "%$name%")->take(50)->get();
        $data = [];
        foreach ($units as $unit) {
            $data[] = ['id' => $unit->id, 'text' => $unit->name];
        }

        return $data;
    }

    public function items(Request $request)
    {
        $output = '';
        $name = $request->search;
        // dd($name);
        $items = DB::table('units as u')
            ->where('u.status', true)
            ->where(function ($query) use ($name) {
                $query->where('u.name', 'LIKE', '%'.$name.'%');
            })
            ->skip(0)
            ->take(50)
            ->get();
        $data = [];
        foreach ($items as $item) {
            $data[] = ['id' => $item->id, 'text' => $item->name];
        }
        echo json_encode($data);
    }
}
