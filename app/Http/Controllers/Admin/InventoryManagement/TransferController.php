<?php

namespace App\Http\Controllers\Admin\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Transfer;
use App\Models\InventoryManagement\TransferLine;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
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
        if ($access->trnsfrs) {
            $transfers = Transfer::where('transfers.user_id', auth()->user()->user_id)
                ->leftJoin('users as u', 'u.id', 'transfers.created_by')
                ->leftJoin('stores as source', 'source.id', 'transfers.source_store')
                ->leftJoin('stores as destination', 'destination.id', 'transfers.destination_store')
                ->where('transfers.status', '<>', '0')
                ->select('transfers.*', 'u.name as creator', 'destination.name as destination', 'source.name as source')
                ->get();

            return view('admin.inventory-management.transfers.index', compact('access', 'transfers'));
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
        if ($access->trnsfrs_create) {
            $transfer = new Transfer;
            $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();
            $stores = $stores->pluck('name', 'id')->put('', '-');
            $source_store = '';
            $destination_store = '';

            return view('admin.inventory-management.transfers.create', compact('access', 'transfer', 'stores', 'source_store', 'destination_store'));
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
        // dd($request->all());
        $request->validate([
            'source_store' => 'required',
            'destination_store' => 'required',
            'item_id' => 'required',
        ]);
        $transfer = DB::table('transfers')
            ->where('user_id', auth()->user()->user_id)
            ->where('status', '<>', '0')
            ->latest()
            ->first();
        $to = ($transfer) ? $transfer->to + 1 : 1000;
        $note = ($request->note) ? $request->note : '';
        $transfer = Transfer::create([
            'to' => $to,
            'source_store' => $request->source_store,
            'destination_store' => $request->destination_store,
            'total' => 0,
            'received' => 0,
            'delivery' => $request->delivery,
            'note' => $note,
            'created_by' => auth()->user()->id,
            'updated_by' => 0,
            'received_by' => 0,
            'status' => 2,
            'user_id' => auth()->user()->user_id,
        ]);
        $total = 0;
        for ($i = 0; $i < count($request->item_id); $i++) {
            $unit_id = ($request->unit[$i] == 'pcs') ? 0 : $request->unit[$i];
            $unit = DB::table('item_units as i')
                ->leftJoin('units as u', 'u.id', 'i.unit_id')
                ->where('i.item_id', $request->item_id[$i])
                ->where('u.id', $request->unit[$i])
                ->select('i.*', 'u.name as name')
                ->first();
            $unit_name = ($unit) ? $unit->name : 'PCS';
            $unit_qty = ($unit) ? $unit->qty : 1;
            // dd($unit);
            TransferLine::create([
                'qty' => $request->qty[$i],
                'received' => 0,
                'item_id' => $request->item_id[$i],
                'unit_id' => $unit_id,
                'unit' => $unit_name,
                'unit_qty' => $unit_qty,
                'transfer_id' => $transfer->id,
            ]);
            $total += $request->qty[$i];
        }
        Transfer::find($transfer->id)->update(['total' => $total]);

        return redirect()->route('transfers.index')->with('success', 'Transfer Order successfully added!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Transfer  $transfer
     * @return \Illuminate\Http\Response
     */
    public function show(Transfer $transfer)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->trnsfrs_read) {
            $transfer = Transfer::with([
                'lines' => function ($q) {
                    $q->with([
                        'item' => function ($q) {
                            $q->select('id', 'name');
                        },
                        'unit' => function ($q) {
                            $q->select('id', 'name');
                        },
                    ]);
                },
                'destination',
                'receiver',
            ])
                ->where('id', $transfer->id)->first();

            // return $transfer;
            return view('admin.inventory-management.transfers.show', compact('access', 'transfer'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Transfer  $transfer
     * @return \Illuminate\Http\Response
     */
    public function edit(Transfer $transfer)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->trnsfrs_update) {
            $transfer = Transfer::with([
                'lines' => function ($q) {
                    $q->with([
                        'item' => function ($q) {
                            $q->select('id', 'name');
                            $q->with(['itemStores' => function ($q) {
                                $q->select('id', 'stock', 'store_id', 'item_id');
                                $q->with(['store' => function ($q) {
                                    $q->select('id', 'name');
                                }]);
                            },
                                'itemUnits' => function ($q) {
                                    $q->select('id', 'item_id', 'unit_id', 'qty');
                                    $q->with(['unit' => function ($q) {
                                        $q->select('id', 'name');
                                    }]);
                                },
                                'stocks',
                            ]);
                        },
                        'unit' => function ($q) {
                            $q->select('id', 'name');
                        },
                    ]);
                },
                'destination',
                'receiver',
            ])
                ->where('id', $transfer->id)->first();

            // return $transfer;
            return view('admin.inventory-management.transfers.edit', compact('access', 'transfer'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Transfer  $transfer
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Transfer $transfer)
    {
        //
        $request->validate([
            'source_store' => 'required',
            'destination_store' => 'required',
            'item_id' => 'required',
        ]);
        $note = ($request->note) ? $request->note : '';
        Transfer::find($transfer->id)->update([
            'source_store' => $request->source_store,
            'destination_store' => $request->destination_store,
            'total' => 0,
            'received' => 0,
            'delivery' => $request->delivery,
            'note' => $note,
            'updated_by' => auth()->user()->id,
            'status' => 2,
            'user_id' => auth()->user()->user_id,
        ]);
        $total = 0;
        TransferLine::where('transfer_id', $transfer->id)->delete();
        for ($i = 0; $i < count($request->item_id); $i++) {
            $unit_id = ($request->unit[$i] == 'pcs') ? 0 : $request->unit[$i];
            $unit = DB::table('item_units as i')
                ->leftJoin('units as u', 'u.id', 'i.unit_id')
                ->where('i.item_id', $request->item_id[$i])
                ->where('u.id', $request->unit[$i])
                ->select('i.*', 'u.name as name')
                ->first();
            $unit_name = ($unit) ? $unit->name : 'PCS';
            $unit_qty = ($unit) ? $unit->qty : 1;
            // dd($unit);
            TransferLine::create([
                'qty' => $request->qty[$i],
                'received' => 0,
                'item_id' => $request->item_id[$i],
                'unit_id' => $unit_id,
                'unit' => $unit_name,
                'unit_qty' => $unit_qty,
                'transfer_id' => $transfer->id,
            ]);
            $total += $request->qty[$i];
        }
        Transfer::find($transfer->id)->update(['total' => $total]);

        return redirect()->route('transfers.index')->with('info', 'Transfer Order #: '.$transfer->to.' successfully updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Transfer  $transfer
     * @return \Illuminate\Http\Response
     */
    public function destroy(Transfer $transfer)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->trnsfrs_delete) {
            Transfer::find($transfer->id)->update(['status' => 0]);

            return redirect()->route('transfers.index')->with('success', 'Transfer Order #: '.$transfer->to.' successfully deleted');
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function table(Request $request)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->endDate)->endOfDay()->toDateTimeString();
        $q = Transfer::query()
            ->with(['source', 'destination', 'creator', 'receiver'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '<>', '0');

        return DataTables($q)
            ->addColumn('actions', function (Transfer $transfer) {
                // View Button
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                if (auth()->user()->role->trnsfrs_read) {
                    $action .= '<a href="'.route('transfers.show', $transfer->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Details"><i class="fas fa-eye"></i></a>&nbsp';
                }
                // Status 1 or true = Not Approved yet
                if ($transfer->status == 2) {
                    if (auth()->user()->role->trnsfrs_update) {
                        // Edit Button
                        $action .= '<a href="'.route('transfers.edit', $transfer->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" value="'.$transfer->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-edit"></i></a>&nbsp';
                    }
                    if (auth()->user()->role->trnsfrs_delete) {
                        // Delete Button
                        $action .= '<span data-bs-toggle="modal" data-bs-target="#deleteModal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1" value="'.$transfer->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-trash"></i></button></span>';
                        $action .= '<input type="hidden" id="name_'.$transfer->id.'" value="'.$transfer->to.'" />';
                        $action .= '<form method="POST" action="'.route('transfers.destroy', $transfer->id).'" id="form_delete_'.$transfer->id.'" value="'.$transfer->name.'">'.method_field('DELETE').csrf_field().'</form>';
                    }
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
        $name = $request->name;
        $items = DB::table('items as i')
            ->where('i.status', true)
            ->where('i.user_id', auth()->user()->user_id)
            ->where(function ($query) use ($name) {
                $query->where('i.barcode', 'LIKE', '%'.$name.'%')->orWhere('i.name', 'LIKE', '%'.$name.'%');
            })
            ->skip(0)
            ->take(50)
            ->get();
        foreach ($items as $item) {
            $output .= "<option value='".$item->name."'>".$item->barcode.'</option>';
        }

        return Response($output);
    }

    public function getItem(Request $request)
    {
        // dd($request->all());
        $output = [];
        $item = Item::where('items.id', $request->name)
            ->leftJoin('item_stores as ss', 'ss.item_id', 'items.id')
            ->leftJoin('item_stores as ds', 'ds.item_id', 'items.id')
            ->where('ss.store_id', $request->source_store)
            ->where('ds.store_id', $request->destination_store)
            ->select('items.*', DB::raw('format(ss.stock, 2) as source'), DB::raw('format(ds.stock, 2) as destination'))
            ->first();
        $item_unit = DB::table('item_units as i')
            ->leftJoin('units as u', 'u.id', 'i.unit_id')
            ->where('i.item_id', $item->id)
            ->select('i.*', 'u.name as name')
            ->get();
        $units = '';
        foreach ($item_unit as $unit) {
            $units .= '<option value="'.$unit->unit_id.'">'.$unit->name.'</option>';
        }
        $output = [
            "<td><input type='hidden' name='item_id[]' value='".$item->id."'/>".$item->name,
            "<select name='unit[]' class='form-select'><option value='0'>PCS</option>$units</select>",
            $item->source,
            $item->destination,
            "<input name='qty[]' type='text' class='form-control' onkeypress='return isNumberKey(event)' oninput='limitDecimalPlaces(event, 2)' value = '' required/>",
            '<button id="DeleteButton" type="button" class="btn btn-danger btn-icon"><i class="fas fa-trash"></i></button>',
        ];

        return response()->json($output);
    }

    public function receive(Transfer $transfer)
    {
        $access = Role::find(auth()->user()->role_id);
        $transfer = Transfer::where('transfers.id', $transfer->id)
            ->leftJoin('users as u', 'u.id', 'transfers.created_by')
            ->leftJoin('stores as source', 'source.id', 'transfers.source_store')
            ->leftJoin('stores as destination', 'destination.id', 'transfers.destination_store')
            ->select('transfers.*', 'u.name as creator', 'destination.name as destination', 'source.name as source')
            ->first();
        $transfer_line = DB::table('transfer_lines as t')
            ->leftJoin('items as i', 'i.id', 't.item_id')
            ->where('t.transfer_id', $transfer->id)
            ->select('t.*', 'i.name as item')
            ->get();

        return view('admin.inventory-management.transfers.receive', compact('access', 'transfer', 'transfer_line'));
    }

    public function receiveNow(Request $request, Transfer $transfer)
    {
        // dd($transfer);
        // dd($request->all());
        $total = $transfer->received;
        for ($i = 0; $i < count($request->item_id); $i++) {
            $line = TransferLine::where('id', $request->transfer_line_id[$i])->first();
            $source = ItemStore::where('item_id', $line->item_id)->where('store_id', $transfer->source_store)->first();
            $source->update([
                'stock' => $source->stock - ($line->qty * $line->unit_qty),
            ]);
            $destination = ItemStore::where('item_id', $line->item_id)->where('store_id', $transfer->destination_store)->first();
            $destination->update([
                'stock' => $destination->stock + ($line->qty * $line->unit_qty),
            ]);
            TransferLine::where('id', $request->transfer_line_id[$i])->update([
                'received' => $line->received + $request->toReceive[$i],
            ]);
            $total += $request->toReceive[$i];
        }
        Transfer::find($transfer->id)
            ->update([
                'status' => 1,
                'received' => $total,
                'received_by' => auth()->user()->id,
                'received_at' => Carbon::now(),
            ]);

        return redirect()->route('transfers.show', $transfer->id)->with('msg', 'Transfer Order #: '.$transfer->to.' received successfully!');
    }

    public function getItems(Request $request)
    {
        $line = DB::select('
            SELECT
                i.id as item_id,
                i.name as item,
                format(ss.stock, 2) as source,
                format(ds.stock, 2) as destination
            FROM
                items i
            LEFT join
                item_stores ss
                ON
                ss.item_id = i.id
            LEFT JOIN
                item_stores ds
                ON
                ds.item_id = i.id
            WHERE
                i.id
                IN
                ('.implode(',', $request->ids).")
                AND
                ss.store_id = $request->source
                AND
                ds.store_id = $request->destination
            ORDER BY
            FIND_IN_SET(i.id, '".implode(',', $request->ids)."')
        ");

        return response()->json($line);
    }

    public function print(Transfer $transfer)
    {
        $access = Role::find(auth()->user()->role_id);
        if ($access->trnsfrs_read) {
            $transfer = Transfer::where('transfers.id', $transfer->id)
                ->leftJoin('users as u', 'u.id', 'transfers.created_by')
                ->leftJoin('users as r', 'r.id', 'transfers.received_by')
                ->leftJoin('users as upd', 'upd.id', 'transfers.received_by')
                ->leftJoin('stores as source', 'source.id', 'transfers.source_store')
                ->leftJoin('stores as destination', 'destination.id', 'transfers.destination_store')
                ->select('transfers.*', 'u.name as creator', 'destination.name as destination', 'source.name as source', 'r.name as receiver', 'upd.name as updater')
                ->first();
            $transfer_line = DB::table('transfer_lines as t')
                ->leftJoin('items as i', 'i.id', 't.item_id')
                ->where('t.transfer_id', $transfer->id)
                ->select('t.*', 'i.name as item')
                ->get();

            return view('admin.inventory-management.transfers.print', compact('transfer', 'transfer_line'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }
}
