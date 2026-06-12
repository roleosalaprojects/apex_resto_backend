<?php

namespace App\Http\Controllers\Admin\InventoryManagement;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreRequest;
use App\Http\Requests\Supplier\UpdateRequest;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\Supplier;
use App\Models\Products\Item;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
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
        if ($access->spplrs) {
            $suppliers = Supplier::where('user_id', auth()->user()->user_id)->where('status', true)->get();

            return view('admin.inventory-management.suppliers.index', compact('access', 'suppliers'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->spplrs) {
            $supplier = new Supplier;

            return view('admin.inventory-management.suppliers.create', compact('access', 'supplier'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(StoreRequest $request)
    {
        $validated = $request->validated();
        $validated['status'] = true;
        $validated['user_id'] = auth()->user()->user_id;
        $supplier = Supplier::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Supplier '.$supplier->name.' created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Supplier  $supplier
     * @return Response
     */
    public function show(Supplier $supplier)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->spplrs_read) {
            $items = Item::where('supplier_id', $supplier->id)
                ->where('status', true)
                ->orderBy('name', 'asc')
                ->get();
            $purchases = Purchase::where('supplier_id', $supplier->id)->get();
            $purchases = DB::table('purchases as p')
                ->where('supplier_id', $supplier->id)
                ->leftJoin('users as u', 'created_by', 'u.id')
                ->select('p.*', 'u.name as creator')
                ->get();
            // Stopped Here
            $addItems = Item::where('user_id', auth()->user()->user_id)->where('status', true)->orderBy('name', 'asc')->take(500)->get();

            return view('admin.inventory-management.suppliers.show', compact('access', 'supplier', 'items', 'addItems', 'purchases'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Supplier  $supplier
     * @return Response
     */
    public function edit(Supplier $supplier)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->spplrs_update) {
            return view('admin.inventory-management.suppliers.edit', compact('access', 'supplier'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");

    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(UpdateRequest $request, Supplier $supplier)
    {
        $validated = $request->validated();
        $supplier->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Supplier '.$supplier->name.' updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Supplier  $supplier
     * @return Response
     */
    public function destroy(Supplier $supplier)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->spplrs_delete) {
            Supplier::find($supplier->id)->update(['status' => false]);

            return redirect()->route('suppliers.index')->with('warning', 'Supplier '.$supplier->name.' successfully deleted.');
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");

    }

    public function table()
    {
        $helper = new HelperController;
        $query = Supplier::query()->where('status', true);

        return DataTables($query)
            ->addColumn('actions', function (Supplier $supplier) use ($helper) {
                return $helper->actionButtonsReturnModal($supplier, 'suppliers', 'Supplier');
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function getSupplier(Supplier $supplier)
    {
        return response()->json($supplier);
    }

    public function select(Request $request)
    {
        $name = $request->search;
        $suppliers = Supplier::where('name', 'LIKE', "%$name%")->where('status', true)->take(50)->get();
        $data = [];
        foreach ($suppliers as $supplier) {
            $data[] = ['id' => $supplier->id, 'text' => $supplier->name];
        }

        return $data;
    }

    public function select_get(Request $request)
    {
        // dd($request->all());
        $suppliers = Supplier::whereIn('id', $request->suppliers)->select('id', 'name')->get();

        return $suppliers;
    }

    public function getItems(Request $request)
    {
        $output = '';
        // dd($request->all());
        $name = $request->search;
        // dd($name);
        $items = DB::table('items as i')
            ->where('i.status', true)
            ->where('i.user_id', auth()->user()->user_id)
            ->where(function ($query) use ($name) {
                $query->where('i.barcode', 'LIKE', '%'.$name.'%')->orWhere('i.name', 'LIKE', '%'.$name.'%');
            })
            ->skip(0)
            ->take(50)
            ->get();
        // foreach($items as $item){
        //     $output .= '<option value="'.$item->name.'">'.$item->barcode.'</option>';
        // }
        $data = [];
        foreach ($items as $item) {
            $data[] = ['id' => $item->id, 'text' => $item->name];
        }

        // $output = $items->pluck('name');
        // return Response()->json(compact('data'));
        return response()->json($data);
    }

    public function saveItems(Supplier $supplier, Request $request)
    {
        // dd($request->all());
        for ($i = 0; $i < count($request->items); $i++) {
            Item::find($request->items[$i])->update([
                'supplier_id' => $supplier->id,
            ]);
        }
        $access = Role::find(auth()->user()->role_id);
        if ($access->spplrs_read) {
            $items = Item::where('supplier_id', $supplier->id)
                ->where('status', true)
                ->orderBy('name', 'asc')
                ->paginate(10);
            // Stopped Here
            $addItems = Item::where('user_id', auth()->user()->user_id)->where('status', true)->orderBy('name', 'asc')->take(500)->get();

            return redirect()->route('suppliers.show', $supplier->id)->with('msg', 'Item / Products successfully added!');
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function get(Request $request)
    {
        $name = $request->search;
        $items = DB::table('suppliers as s')
            ->where('s.status', true)
            ->where('s.user_id', auth()->user()->user_id)
            ->where('name', 'LIKE', "%$name%")
            ->skip(0)
            ->take(50)
            ->get();
        $data = [];
        foreach ($items as $item) {
            $data[] = ['id' => $item->id, 'text' => $item->name];
        }
        // $output = $items->pluck('name');
        // return Response()->json(compact('data'));
        echo json_encode($data);
        exit;
    }

    public function summary()
    {
        $access = Role::find(auth()->user()->role_id);

        return view('admin.reports.reports.supplier', compact('access'));
    }

    public function summaryReportData(Request $request)
    {
        $start = Carbon::parse($request->start)->startOfDay()->toDateTimeString();
        $end = Carbon::parse($request->end)->endOfDay()->toDateTimeString();
        $q = DB::select("
            SELECT
            s.name as supplier,
            format(sum(sl.qty * sl.unit_qty), 2) as sold,
            format(sum(sl.cost * sl.unit_qty), 2) as cost,
            format(sum((sl.price - (sl.cost * sl.unit_qty)) * sl.qty), 2) as revenue,
            s.id as supplier_id
            FROM
            sale_lines sl
            LEFT JOIN
            items i
            on
            i.id = sl.item_id
            LEFT JOIN
            suppliers s
            ON
            s.id = i.supplier_id
            WHERE
            sl.created_at BETWEEN '$start' AND '$end'
            group by
            s.id
            ORDER by
            cast(revenue as unsigned)
            DESC
        ");

        return datatables($q)->make(true);
    }

    public function insight(Request $request)
    {
        $start = Carbon::parse($request->start)->startOfDay()->toDateTimeString();
        $end = Carbon::parse($request->end)->endOfDay()->toDateTimeString();
        $q = DB::select("
            SELECT
            i.name as item,
            format(sum(sl.cost), 2) as unit_cost,
            format(sum(sl.price), 2) as unit_price,
            format(sum(sl.unit_qty), 2) as unit_qty,
            format(sum(sl.price - (sl.cost * sl.unit_qty)), 2) as revenue,
            format(avg(((sl.price - (sl.cost * sl.unit_qty)) /sl.price) * 100), 2) as margin,
            format(avg(((sl.price - (sl.cost * sl.unit_qty)) /(sl.cost * sl.unit_qty)) * 100), 2) as markup,
            i.id as item_id
            from
            sale_lines sl
            LEFT JOIN
            items i
            ON
            i.id = sl.item_id
            WHERE
            i.supplier_id = $request->supplier
            AND
            sl.created_at BETWEEN '$start' AND '$end'
            Group by
            i.id, sl.item_id
            order by
            cast(revenue as unsigned)
            DESC
        ");

        return datatables($q)->make(true);
    }
}
