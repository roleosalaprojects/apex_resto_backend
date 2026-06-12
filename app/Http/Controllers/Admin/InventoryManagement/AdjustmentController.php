<?php

namespace App\Http\Controllers\Admin\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Adjustment;
use App\Models\InventoryManagement\AdjustmentLine;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AdjustmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->adjstmnts) {
            $adjustments = DB::table('adjustments as a')
                ->leftJoin('users as c', 'c.id', 'a.created_by')
                ->leftJoin('stores as s', 's.id', 'a.store_id')
                ->leftJoin('users as r', 'r.id', 'a.received_by')
                ->where('a.user_id', auth()->user()->user_id)
                ->where('a.status', '<>', 0)
                ->select('a.*', 'c.name as creator', 's.name as store', 'r.name as receiver')
                ->get();

            return view('admin.inventory-management.adjustments.index', compact('access', 'adjustments'));
        } else {
            return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->adjstmnts_create) {
            $adjustment = new Adjustment;
            $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();
            $stores = $stores->pluck('name', 'id');
            $selected_store = count($stores) + 1;
            $selected_reason = '';

            return view('admin.inventory-management.adjustments.create', compact('adjustment', 'access', 'stores', 'selected_store', 'selected_reason'));
        } else {
            return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

    }

    /**
     * Store a newly created resource in storage.
     *
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        //
        // dd($request->all());
        $request->validate([
            'store' => 'required',
            'reason' => 'required',
            'item_id.*' => 'required',
        ]);
        $adjustment = DB::table('adjustments')
            ->where('user_id', auth()->user()->user_id)
            ->where('status', '<>', '0')
            ->latest()
            ->first();
        // dd($adjustment);
        $so = ($adjustment) ? $adjustment->so + 1 : 1000;
        // dd($so);
        $adjustment = Adjustment::create([
            'so' => $so,
            'total' => 0,
            'received' => 0,
            'reason' => $request->reason,
            'store_id' => $request->store,
            'created_by' => auth()->user()->id,
            'updated_by' => 0,
            'received_by' => 0,
            'status' => 2,
            'user_id' => auth()->user()->user_id,
            'received_at' => '',
        ]);
        $total = 0;
        for ($i = 0; $i < count($request->item_id); $i++) {
            $unit_id = ($request->unit[$i] == 'pcs') ? 0 : $request->unit[$i];
            $unit = DB::table('item_units as i')
                ->leftJoin('units as u', 'u.id', 'i.unit_id')
                ->where('i.id', $request->unit[$i])
                ->select('i.*', 'u.name as name')
                ->first();
            $unit_name = ($unit) ? $unit->name : 'PCS';
            $unit_qty = ($unit) ? $unit->qty : 1;
            // dd($unit);
            AdjustmentLine::create([
                'qty' => $request->qty[$i],
                'received' => $request->qty[$i],
                'item_id' => $request->item_id[$i],
                'unit_id' => $unit_id,
                'unit' => $unit_name,
                'unit_qty' => $unit_qty,
                'adjustment_id' => $adjustment->id,
            ]);
            $total += $request->qty[$i];
        }
        Adjustment::find($adjustment->id)->update(['total', $total]);

        return redirect()->route('adjustments.index')->with('success', 'Stock Adjustment added successfully!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Adjustment  $adjustment
     * @return Response
     */
    public function show(Adjustment $adjustment)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->adjstmnts_read) {
            $adjustment = DB::table('adjustments as a')
                ->leftJoin('users as c', 'c.id', 'a.created_by')
                ->leftJoin('stores as s', 's.id', 'a.store_id')
                ->leftJoin('users as r', 'r.id', 'a.received_by')
                ->where('a.user_id', auth()->user()->user_id)
                ->where('a.id', $adjustment->id)
                ->select('a.*', 'c.name as creator', 's.name as store', 'r.name as receiver')
                ->first();
            $adjustment_line = DB::table('adjustment_lines as a')
                ->leftJoin('items as i', 'i.id', 'a.item_id')
                ->where('a.adjustment_id', $adjustment->id)
                ->select('a.*', 'i.name as item')
                ->get();

            // dd($adjustment_line);
            return view('admin.inventory-management.adjustments.show', compact('access', 'adjustment', 'adjustment_line'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Adjustment  $adjustment
     * @return Response
     */
    public function edit(Adjustment $adjustment)
    {
        //
        // dd($adjustment);
        $access = Role::find(auth()->user()->role_id);
        if ($access->adjstmnts_update) {
            $adjustment = Adjustment::find($adjustment->id);
            $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();
            $stores = $stores->pluck('name', 'id');
            $selected_store = $adjustment->store_id;
            $selected_reason = $adjustment->reason;
            $adjustment_line = DB::table('adjustment_lines as a')
                ->leftJoin('items as i', 'i.id', 'a.item_id')
                ->leftJoin('item_stores as is', 'is.item_id', 'a.item_id')
                ->where('a.adjustment_id', $adjustment->id)
                ->select('a.*', 'i.name as item', 'is.stock as stock')
                ->get();
            $output = '';
            foreach ($adjustment_line as $line) {
                $output .= "<tr><td><input type='hidden' name='item_id[]' value='".$line->item_id."'/>".$line->item.'</td>';
                // dd($output);
                $output .= "<td><select name='unit[]' class='form-select    '><option value='pcs' ";
                $output .= ($line->unit == 0) ? 'selected' : '';
                $output .= '>PCS</option>';
                $item_unit = DB::table('item_units as i')
                    ->leftJoin('units as u', 'u.id', 'i.unit_id')
                    ->where('i.item_id', $line->item_id)
                    ->select('i.*', 'u.name as name')
                    ->get();
                foreach ($item_unit as $unit) {
                    $output .= '<option value="'.$unit->id.'" ';
                    $output .= ($line->unit_id == $unit->id) ? 'selected' : '';
                    $output .= '>'.$unit->name.'</option>';
                }
                $output .= '</select></td>';
                $output .= "<td>$line->stock</td>";
                $output .= "<td><input name='qty[]' type='text' class='form-control' onkeypress='return isNumberKey(event)' oninput='limitDecimalPlaces(event, 2)' value = '".$line->qty."' required/></td>";
                $output .= '<td><button id="DeleteButton" type="button" class="btn btn-danger btn-icon"><i class="fas fa-trash"></i>
                </button></td></tr>';
            }

            return view('admin.inventory-management.adjustments.edit',
                compact('adjustment', 'access', 'stores', 'selected_store', 'selected_reason', 'output')
            );
        } else {
            return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Adjustment  $adjustment
     * @return Response
     */
    public function update(Request $request, Adjustment $adjustment)
    {
        //
        $request->validate([
            'store' => 'required',
            'reason' => 'required',
            'item_id' => 'required',
        ]);
        $adjustment = DB::table('adjustments')
            ->where('user_id', auth()->user()->user_id)
            ->where('status', '<>', '0')
            ->latest()
            ->first();
        // dd($request->all());
        $so = ($adjustment) ? $adjustment->so + 1 : 1000;
        // dd($so);
        Adjustment::find($adjustment->id)->update([
            'total' => 0,
            'received' => 0,
            'reason' => $request->reason,
            'store_id' => $request->store,
            'updated_by' => 0,
        ]);
        $total = 0;
        AdjustmentLine::where('adjustment_id', $adjustment->id)->delete();
        for ($i = 0; $i < count($request->item_id); $i++) {
            $unit_id = ($request->unit[$i] == 'pcs') ? 0 : $request->unit[$i];
            $unit = DB::table('item_units as i')
                ->leftJoin('units as u', 'u.id', 'i.unit_id')
                ->where('i.id', $request->unit[$i])
                ->select('i.*', 'u.name as name')
                ->first();
            $unit_name = ($unit) ? $unit->name : 'PCS';
            $unit_qty = ($unit) ? $unit->qty : 1;
            // dd($unit);
            AdjustmentLine::create([
                'qty' => $request->qty[$i],
                'received' => $request->qty[$i],
                'item_id' => $request->item_id[$i],
                'unit_id' => $unit_id,
                'unit' => $unit_name,
                'unit_qty' => $unit_qty,
                'adjustment_id' => $adjustment->id,
            ]);
            $total += $request->qty[$i];
        }
        Adjustment::find($adjustment->id)->update(['total', $total]);

        return redirect()->route('adjustments.index')->with('info', 'Stock Adjustment updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Adjustment  $adjustment
     * @return Response
     */
    public function destroy(Adjustment $adjustment)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->adjstmnts_delete) {
            Adjustment::find($adjustment->id)->update(['status' => 0]);

            return redirect()->route('adjustments.index')->with('info', 'Stock adjustment successfully deleted!');
        } else {
            return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

    }

    public function table()
    {
        $q = Adjustment::query()
            ->with(['store', 'creator', 'receiver'])
            ->where('status', '<>', '0');

        return DataTables($q)
            ->addColumn('actions', function (Adjustment $adjustment) {
                // View Button
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                if (auth()->user()->role->adjstmnts_read) {
                    $action .= '<a href="'.route('adjustments.show', $adjustment->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Details"><i class="fas fa-eye"></i></a>&nbsp';
                }
                // Status 1 or true = Not Approved yet
                if ($adjustment->status == 2) {
                    if (auth()->user()->role->adjstmnts_update) {
                        // Edit Button
                        $action .= '<a href="'.route('adjustments.edit', $adjustment->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" value="'.$adjustment->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-edit"></i></a>&nbsp';
                    }
                    if (auth()->user()->role->adjstmnts_delete) {
                        // Delete Button
                        $action .= '<span data-bs-toggle="modal" data-bs-target="#deleteModal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1" value="'.$adjustment->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-trash"></i></button></span>';
                        $action .= '<input type="hidden" id="name_'.$adjustment->id.'" value="Stock Adjustment# : '.$adjustment->so.'" />';
                        $action .= '<form method="POST" action="'.route('adjustments.destroy', $adjustment->id).'" id="form_delete_'.$adjustment->id.'" value="'.$adjustment->name.'">'.method_field('DELETE').csrf_field().'</form>';
                    }
                    $action .= ($adjustment->remark == 0) ? `<a href="adjustment/pay/$adjustment->id'" class="btn btn-sm btn-icon btn-active-color-primary btn-bg-light me-1"><i class="fas fa-file-invoice-dollar"></i></a>` : '';
                }
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    // Search the item
    public function search(Request $request)
    {
        $output = '';
        $items = DB::table('items')
            ->where('status', true)
            ->where('user_id', auth()->user()->user_id)
            ->where('name', 'LIKE', '%'.$request->search.'%')
            ->orWhere('barcode', 'LIKE', '%'.$request->search.'%')
            ->get();
        foreach ($items as $key => $item) {
            $output .= "<option value='".$item->barcode."'>".$item->name.'</option>';
        }

        return Response($output);
    }

    public function getItemFromSearch(Request $request)
    {
        // dd($request->name);
        $item = Item::where('items.id', $request->name)
            ->leftJoin('item_stores as is', 'is.item_id', 'items.id')
            ->where('is.store_id', $request->store)
            ->where('items.status', true)
            ->where('items.user_id', auth()->user()->user_id)
            ->select('items.*', DB::raw('format(is.stock, 2) as stock'))
            ->first();
        $item_unit = DB::table('item_units as i')
            ->leftJoin('units as u', 'u.id', 'i.unit_id')
            ->where('i.item_id', $item->id)
            ->select('i.*', 'u.name as name')
            ->get();
        $units = '';
        foreach ($item_unit as $unit) {
            $units .= '<option value="'.$unit->id.'">'.$unit->name.'</option>';
        }
        $output = [
            "<td><input type='hidden' name='item_id[]' value='".$item->id."'/>".$item->name,
            "<select name='unit[]' class='form-select'><option value='pcs'>PCS</option>$units</select>",
            $item->stock,
            "<input name='qty[]' type='text' class='form-control' onkeypress='return isNumberKey(event)' oninput='limitDecimalPlaces(event, 2)' value = '' required/>",
            '<button id="DeleteButton" type="button" class="btn btn-danger btn-icon"><i class="fas fa-trash"></i>
            </button>',
        ];

        // dd($output);
        return response()->json($output);
    }

    public function approve(Adjustment $adjustment)
    {
        Adjustment::find($adjustment->id)->update(['status' => 1, 'received_by' => auth()->user()->id]);
        $adjustment_line = AdjustmentLine::where('adjustment_id', $adjustment->id)->get();
        // dd($adjustment_line);
        foreach ($adjustment_line as $line) {
            // dd($line);
            $item_store = ItemStore::where('item_id', $line->item_id)->where('store_id', $adjustment->store_id)->first();
            // dd($item_store);
            $item_store->update([
                'stock' => $item_store->stock + ($line->qty * $line->unit_qty),
            ]);
        }

        return redirect()->route('adjustments.index')->with('msg', 'SO # :'.$adjustment->so.' Successfully approved!');
    }

    public function getItems(Request $request)
    {
        $line = DB::select('
            SELECT
                i.id as item_id,
                i.name as item,
                format(is.stock, 2) as stock
            FROM
                items i
            LEFT join
                item_stores `is`
                ON
                is.item_id = i.id
            WHERE
                i.id
                IN
                ('.implode(',', $request->ids).")
                AND
                is.store_id = $request->store
            ORDER BY
            FIND_IN_SET(i.id, '".implode(',', $request->ids)."')
        ");

        return response()->json($line);
    }
}
