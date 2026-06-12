<?php

namespace App\Http\Controllers\Admin\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Count;
use App\Models\InventoryManagement\CountLine;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->invntry) {
            $counts = Count::where('user_id', auth()->user()->user_id)->latest()->get();

            return view('admin.inventory-management.counts.index', compact('access', 'counts'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->invntry_create) {
            $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();
            $selected_store = '';
            $stores = $stores->pluck('name', 'id');

            return view('admin.inventory-management.counts.create', compact('access', 'stores', 'selected_store'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        // dd($request->all());
        $request->validate([
            'item_id' => 'required',
            'store' => 'required',
        ],
            [
                'item_id.required' => 'Please enter at least 1 item to proceed.',
                'store.required' => 'Select a store before proceeding',
            ]);
        $ic = 1000;
        $latest = Count::where('user_id', auth()->user()->user_id)->where('status', true)->latest()->take(1)->first();
        ($latest) ? $ic = $latest->ic + 1 : $ic = 1000;
        $inv = Count::create([
            'created_by' => auth()->user()->id,
            'user_id' => auth()->user()->user_id,
            'status' => true,
            'ic' => $ic,
            'total' => 0,
            'store_id' => $request->store,
        ]);
        // dd($inv);
        for ($i = 0; $i < count($request->item_id); $i++) {
            CountLine::create([
                'item_id' => $request->item_id[$i],
                'unit_id' => $request->unit[$i],
                'count_id' => $inv->id,
            ]);
        }

        return redirect()->route('counts.index')->with('success', 'Inventory Count Successfully Created!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Count  $count
     * @return \Illuminate\Http\Response
     */
    public function show(Count $count)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->invntry_read) {
            // $queries = DB::getQueryLog();
            return view('admin.inventory-management.counts.show', compact('access', 'count'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Count  $count
     * @return \Illuminate\Http\Response
     */
    public function edit(Count $count)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->invntry_update) {
            return view('admin.inventory-management.counts.create', compact('access'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Count  $count
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Count $count)
    {
        return redirect()->route('counts.index')->with('info', 'Inventory Count Successfully Updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Count  $count
     * @return \Illuminate\Http\Response
     */
    public function destroy(Count $count)
    {
        $access = Role::find(auth()->user()->role_id);
        if ($access->invntry_delete) {
            return view('admin.inventory-management.counts.index', compact('access'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function get_item(Request $request)
    {
        $item = Item::where('items.id', $request->name)
            ->leftJoin('item_stores as is', 'is.item_id', 'items.id')
            ->where('is.item_id', $request->name)
            ->where('is.store_id', $request->store)
            ->select('items.*', DB::raw('format(is.stock, 2) as stock'))
            ->first();
        $item_unit = DB::table('item_units as i')
            ->leftJoin('units as u', 'u.id', 'i.unit_id')
            ->where('i.item_id', $item->id)
            ->select('i.*', 'u.name as name', 'u.id as unit_id')
            ->get();
        $units = '';
        foreach ($item_unit as $unit) {
            $units .= '<option value="'.$unit->unit_id.'">'.$unit->name.'</option>';
        }
        $output = [
            "<input type='hidden' name='item_id[]' value='".$item->id."'/>".$item->name,
            "<select name='unit[]' class='form-control'><option value=''>PCS</option>".$units.'</select>',
            $item->stock,
            '<button id="DeleteButton" type="button" class="btn btn-danger btn-icon btn-flat"><i class="fas fa-trash"></i>
            </button>',
        ];

        return response()->json($output);
    }

    public function table(Request $request)
    {
        $query = Count::where('counts.user_id', auth()->user()->user_id)
            ->whereIn('counts.status', [0, 1, 2]) // Draft, In Progress, or Completed
            ->leftJoin('users as u', 'counts.created_by', 'u.id')
            ->leftJoin('stores as s', 'counts.store_id', 's.id')
            ->leftJoin('count_lines as cl', 'cl.count_id', 'counts.id')
            ->groupBy('counts.id', 'ic', 'counts.status', 'counts.created_at', 'u.name', 's.name', 'counts.updated_at')
            ->select(
                'counts.id as id',
                'ic',
                'counts.status as status',
                'counts.created_at as created',
                'u.name as creator',
                's.name as store_name',
                'counts.updated_at as updated_at',
                DB::raw('COUNT(cl.id) as items_count')
            );

        return datatables($query)
            ->addColumn('actions', function ($row) {
                $actions = '';
                $access = Role::find(auth()->user()->role_id);
                if ($access->invntry_read) {
                    $actions .= '<a href="'.route('counts.show', $row->id).'" class="btn btn-icon btn-active-color-primary btn-bg-light"><i class="fas fa-eye"></i></a>';
                }

                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function printIC($id)
    {
        if ($id) {
            $count = Count::find($id);

            return view('admin.inventory-management.counts.print', compact('count'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function getItems(Request $request)
    {
        // dd($request->all());
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

    /**
     * Add a new item to an existing count.
     */
    public function addLine(Request $request, Count $count)
    {
        $access = Role::find(auth()->user()->role_id);
        if (! $access->invntry_update) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($count->status >= 2) {
            return response()->json(['success' => false, 'message' => 'Count already finalized'], 400);
        }

        $request->validate([
            'item_id' => 'required|exists:items,id',
        ]);

        // Check if item already exists in this count
        $existing = CountLine::where('count_id', $count->id)
            ->where('item_id', $request->item_id)
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Item already exists in this count'], 400);
        }

        CountLine::create([
            'count_id' => $count->id,
            'item_id' => $request->item_id,
            'unit_id' => 0,
            'counted_qty' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Item added successfully']);
    }

    /**
     * Delete an item from a count.
     */
    public function deleteLine(Request $request, Count $count)
    {
        $access = Role::find(auth()->user()->role_id);
        if (! $access->invntry_update) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($count->status >= 2) {
            return response()->json(['success' => false, 'message' => 'Count already finalized'], 400);
        }

        $request->validate([
            'line_id' => 'required|exists:count_lines,id',
        ]);

        $line = CountLine::where('id', $request->line_id)
            ->where('count_id', $count->id)
            ->firstOrFail();

        $line->delete();

        return response()->json(['success' => true, 'message' => 'Item removed successfully']);
    }

    /**
     * Update a count line's counted quantity.
     */
    public function updateLine(Request $request, Count $count)
    {
        $access = Role::find(auth()->user()->role_id);
        if (! $access->invntry_update) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($count->status >= 2) {
            return response()->json(['success' => false, 'message' => 'Count already finalized'], 400);
        }

        $request->validate([
            'line_id' => 'required|exists:count_lines,id',
            'counted_qty' => 'nullable|numeric|min:0',
        ]);

        $line = CountLine::where('id', $request->line_id)
            ->where('count_id', $count->id)
            ->firstOrFail();

        $line->counted_qty = $request->counted_qty;
        $line->save();

        return response()->json(['success' => true, 'message' => 'Updated successfully']);
    }

    /**
     * Finalize inventory count and update stock.
     */
    public function finalize(Count $count)
    {
        $access = Role::find(auth()->user()->role_id);
        if (! $access->invntry_update) {
            return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

        if ($count->status >= 2) {
            return redirect()->route('counts.show', $count->id)->with('error', 'This count has already been finalized.');
        }

        // Check if all items have been counted
        $uncountedItems = $count->lines()->whereNull('counted_qty')->count();
        if ($uncountedItems > 0) {
            return redirect()->route('counts.show', $count->id)->with('error', "Cannot finalize: {$uncountedItems} item(s) have not been counted yet.");
        }

        // Update stock for each line item
        $updatedCount = 0;
        $varianceCount = 0;

        foreach ($count->lines as $line) {
            $itemStore = ItemStore::where('item_id', $line->item_id)
                ->where('store_id', $count->store_id)
                ->first();

            if ($itemStore) {
                $oldStock = (float) $itemStore->stock;
                $newStock = (float) $line->counted_qty;

                if ($oldStock !== $newStock) {
                    $varianceCount++;
                }

                $itemStore->stock = $newStock;
                $itemStore->save();
                $updatedCount++;
            } else {
                // Create item_store record if it doesn't exist
                ItemStore::create([
                    'item_id' => $line->item_id,
                    'store_id' => $count->store_id,
                    'stock' => $line->counted_qty,
                ]);
                $updatedCount++;
                $varianceCount++;
            }
        }

        $count->status = 2; // Completed
        $count->save();

        return redirect()->route('counts.show', $count->id)->with('success', "Inventory count finalized successfully! Updated {$updatedCount} item(s) with {$varianceCount} variance(s).");
    }
}
