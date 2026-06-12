<?php

namespace App\Http\Controllers\Admin\InventoryManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PaymentRequest;
use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\PurchaseAdd;
use App\Models\InventoryManagement\PurchaseApproval;
use App\Models\InventoryManagement\PurchaseLine;
use App\Models\InventoryManagement\PurchasePayment;
use App\Models\InventoryManagement\Supplier;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Products\ItemUnit;
use App\Models\Settings\Store;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
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
        if ($access->prchs) {
            return view('admin.inventory-management.purchases.index', compact('access'));
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
        if ($access->prchs_create) {
            $purchase = new Purchase;
            $suppliers = Supplier::where('user_id', auth()->user()->user_id)->where('status', true)->get();
            $suppliers = $suppliers->pluck('name', 'id');
            $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();
            $stores = $stores->pluck('name', 'id');
            $selected_store = '';
            $selected_supplier = '';
            $purchase_line = DB::table('purchase_lines as pl')
                ->leftJoin('items as i', 'i.id', 'pl.item_id')
                ->leftJoin('units as u', 'u.id', 'pl.unit_id')
                ->where('purchase_id', -1)
                ->select('pl.*', 'u.name as unit', 'i.name as item')
                ->get();

            return view('admin.inventory-management.purchases.create', compact('access', 'suppliers', 'selected_supplier', 'purchase', 'stores', 'selected_store', 'purchase_line'));
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
        $request->validate([
            'supplier' => 'required',
            'store' => 'required',
            'purchased' => 'required | date',
            'expect' => 'required | numeric',
            'item_id.*' => 'required',
            // 'invoice_no'=>'required|max:255|unique:purchases'
        ],
            [
                'invoice_no.required' => 'This field cannot be blank!',
            ]);
        $po = Purchase::where('user_id', auth()->user()->user_id)->where('status', '<>', 3)->latest()->first();
        $po = ($po) ? $po->po + 1 : 1000;
        // dd($po);
        $purchase_date = Carbon::parse($request->purchased);
        $purchase = Purchase::create([
            'po' => $po,
            'supplier_id' => $request->supplier,
            'store_id' => $request->store,
            'purchased' => $purchase_date,
            // expect shall be considered as term day
            'expected' => $request->expect,
            'note' => ($request->note) ? $request->note : '',
            'total' => 0,
            'items' => 0,
            'received' => 0,
            'status' => 1,
            'user_id' => auth()->user()->user_id,
            'created_by' => auth()->user()->id,
            'invoice_no' => $request->invoice_no,
            'payment_status' => Purchase::PAYMENT_UNPAID,
        ]);
        $total = 0;
        $items = 0;
        for ($i = 0; $i < count($request->item_id); $i++) {
            $unit = ItemUnit::where('unit_id', $request->unit[$i])->where('item_id', $request->item_id[$i])->first();
            if ($unit) {
                $total += $request->qty[$i] * $unit->qty * $request->price[$i];
            } else {
                $total += $request->qty[$i] * $request->price[$i];
            }
            PurchaseLine::create([
                'item_id' => $request->item_id[$i],
                'qty' => $request->qty[$i],
                'cost' => $request->price[$i],
                'unit_id' => ($request->unit[$i]) ? $request->unit[$i] : 0,
                'received' => 0,
                'purchase_id' => $purchase->id,
            ]);
            $items += $request->qty[$i];
        }
        if ($request->addAmount > 0) {
            for ($i = 0; $i < count($request->addAmount); $i++) {
                $total += $request->addAmount[$i];
                PurchaseAdd::create([
                    'description' => $request->addDescription[$i],
                    'amount' => $request->addAmount[$i],
                    'purchase_id' => $purchase->id,
                ]);
            }
        }
        Purchase::find($purchase->id)->update(['total' => $total, 'items' => $items]);

        return redirect()->route('purchases.index')->with('success', 'Purchase Order successfully created!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Purchase  $purchase
     * @return \Illuminate\Http\Response
     */
    public function show(Purchase $purchase)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->prchs_read) {
            $purchase = Purchase::where('id', $purchase->id)
                ->with([
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
                                ]);
                            },
                            'unit' => function ($q) {
                                $q->select('id', 'name');
                            },
                        ]);
                    },
                    'adds',
                    'supplier' => function ($q) {
                        $q->select('id', 'name');
                    },
                    'store' => function ($q) {
                        $q->select('id', 'name');
                    },
                    'creator' => function ($q) {
                        $q->select('id', 'name');
                    },
                    'receiver',
                    'latestApproval' => function ($q) {
                        $q->with(['approver' => function ($q) {
                            $q->select('id', 'name');
                        }]);
                    },
                    'payments' => function ($q) {
                        $q->with(['bank', 'createdBy']);
                        $q->orderBy('payment_date', 'desc');
                    },
                ])
                ->first();

            // Load banks for payment modal
            $banks = Bank::select('id', 'bank_name', 'account_name', 'account_number', 'balance')
                ->get();

            // Payment methods
            $paymentMethods = PurchasePayment::paymentMethods();

            return view('admin.inventory-management.purchases.show', compact('access', 'purchase', 'banks', 'paymentMethods'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Purchase  $purchase
     * @return \Illuminate\Http\Response
     */
    public function edit(Purchase $purchase)
    {
        //
        $access = Role::find(auth()->user()->role_id);

        // Prevent editing approved POs
        if ($purchase->isApproved()) {
            return redirect()->route('purchases.index')
                ->with('error', 'This purchase order has been approved and cannot be edited.');
        }

        if ($access->prchs_update) {
            // $suppliers = Supplier::where('user_id', auth()->user()->user_id)->where('status', true)->get();
            // $suppliers = $suppliers->pluck('name', 'id');
            // $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();
            // $stores = $stores->pluck('name', 'id');
            // $selected_store = $purchase->store_id;
            // $selected_supplier = $purchase->supplier_id;
            // $purchase_line  = DB::table('purchase_lines as pl')
            //                 ->leftJoin('items as i', 'i.id', 'pl.item_id')
            //                 ->leftJoin('units as u', 'u.id', 'pl.unit_id')
            //                 ->leftJoin('item_stores as is', 'is.item_id', 'pl.item_id')
            //                 ->where("is.store_id", $purchase->store_id)
            //                 ->where('purchase_id', $purchase->id)
            //                 ->select('pl.*', 'u.name as unit', 'i.name as item', 'is.stock')
            //                 ->get();
            $purchase = Purchase::where('id', $purchase->id)
                ->with([
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
                                ]);
                            },
                            'unit' => function ($q) {
                                $q->select('id', 'name');
                            },
                        ]);
                    },
                    'adds',
                    'supplier' => function ($q) {
                        $q->select('id', 'name');
                    },
                    'store' => function ($q) {
                        $q->select('id', 'name');
                    },
                    'creator' => function ($q) {
                        $q->select('id', 'name');
                    },
                    'receiver',
                ])
                ->first();

            // dd($purchase);
            // return $purchase;
            return view('admin.inventory-management.purchases.edit', compact('access', 'purchase'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Purchase  $purchase
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Purchase $purchase)
    {
        // Prevent editing approved POs
        if ($purchase->isApproved()) {
            return redirect()->route('purchases.index')
                ->with('error', 'This purchase order has been approved and cannot be edited.');
        }

        //
        $request->validate([
            'supplier' => 'required',
            'store' => 'required',
            'purchased' => 'required | date',
            'expect' => 'required | numeric',
            'item_id' => 'required',
            // 'invoice_no'=>'required|max:255|unique:purchases'
        ],
            [
                'invoice_no.required' => 'This field cannot be blank!',
            ]);
        // dd($po);
        $o_purchase = $purchase;
        $purchase = Purchase::find($purchase->id)->update([
            'supplier_id' => $request->supplier,
            'store_id' => $request->store,
            'purchased' => date('Y-m-d', strtotime($request->purchased)),
            // expect shall be considered as term day
            'expected' => $request->expect,
            'note' => ($request->note) ? $request->note : '',
            'total' => 0,
            'items' => 0,
            'received' => 0,
            'invoice_no' => $request->invoice_no,
        ]);
        $total = 0;
        $items = 0;
        PurchaseLine::where('purchase_id', $o_purchase->id)->delete();
        for ($i = 0; $i < count($request->item_id); $i++) {
            $unit = ItemUnit::where('unit_id', $request->unit[$i])->where('item_id', $request->item_id[$i])->first();
            if ($unit) {
                $total += $request->qty[$i] * $unit->qty * $request->price[$i];
            } else {
                $total += $request->qty[$i] * $request->price[$i];
            }
            PurchaseLine::create([
                'item_id' => $request->item_id[$i],
                'qty' => $request->qty[$i],
                'cost' => $request->price[$i],
                'unit_id' => ($request->unit[$i]) ? $request->unit[$i] : 0,
                'received' => 0,
                'purchase_id' => $o_purchase->id,
            ]);
            $items += $request->qty[$i];
        }
        PurchaseAdd::where('purchase_id', $o_purchase->id)->delete();
        if ($request->addAmount) {
            for ($i = 0; $i < count($request->addAmount); $i++) {
                $total += $request->addAmount[$i];
                PurchaseAdd::create([
                    'description' => $request->addDescription[$i],
                    'amount' => $request->addAmount[$i],
                    'purchase_id' => $o_purchase->id,
                ]);
            }
        }
        Purchase::find($o_purchase->id)->update(['total' => $total, 'items' => $items]);

        return redirect()->route('purchases.index')->with('info', 'Purchase Order successfully updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Purchase  $purchase
     * @return \Illuminate\Http\Response
     */
    public function destroy(Purchase $purchase)
    {
        // §1.6 of development/specs/purchase_order_audit_and_remediation.md.
        //
        // Pre-fix: status=3 marker only. Children (PurchaseLines,
        // PurchaseAdds, PurchasePayments) stayed orphaned forever
        // with no audit trail. Now: wrap in a transaction, cascade
        // the soft/hard delete to children, write an audit log row.
        $access = Role::find(auth()->user()->role_id);
        if (! $access->prchs_delete) {
            return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

        $linesCount = $purchase->lines()->count();
        $addsCount = $purchase->adds()->count();
        $paymentsCount = $purchase->payments()->count();

        DB::transaction(function () use ($purchase) {
            // PurchaseLine and PurchaseAdd have no SoftDeletes trait —
            // they're snapshots of the PO at order time; once the PO
            // is voided they have no independent meaning. Hard delete.
            PurchaseLine::where('purchase_id', $purchase->id)->delete();
            PurchaseAdd::where('purchase_id', $purchase->id)->delete();
            // PurchasePayment uses SoftDeletes — soft cascade preserves
            // the audit trail of past payments against this PO.
            PurchasePayment::where('purchase_id', $purchase->id)->delete();

            $purchase->update(['status' => '3']);
        });

        \App\Models\Reports\AuditLog::record(
            $purchase,
            'purchase_voided',
            [
                'po' => $purchase->po,
                'supplier_id' => $purchase->supplier_id,
                'total' => $purchase->total,
                'lines_deleted' => $linesCount,
                'adds_deleted' => $addsCount,
                'payments_soft_deleted' => $paymentsCount,
            ]
        );

        return redirect()->route('purchases.index')->with('success', 'Successfully deleted PO #'.$purchase->po);
    }

    public function table(Request $request)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->endDate)->endOfDay()->toDateTimeString();
        $q = Purchase::query()
            ->with(['supplier', 'store', 'creator', 'receiver'])
            ->where('status', '<', '3');

        return DataTables($q)
            ->addColumn('actions', function (Purchase $purchase) {
                // View Button
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                if (auth()->user()->role->prchs_read) {
                    $action .= '<a href="'.route('purchases.show', $purchase->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Details"><i class="fas fa-eye"></i></a>&nbsp';
                }
                // Status 1 or true = Not Approved yet
                if ($purchase->status == 1) {
                    if (auth()->user()->role->prchs_update) {
                        // Edit Button
                        $action .= '<a href="'.route('purchases.edit', $purchase->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" value="'.$purchase->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-edit"></i></a>&nbsp';
                    }
                    if (auth()->user()->role->prchs_delete) {
                        // Delete Button
                        $action .= '<span data-bs-toggle="modal" data-bs-target="#deleteModal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1" value="'.$purchase->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-trash"></i></button></span>';
                        $action .= '<input type="hidden" id="name_'.$purchase->id.'" value="'.$purchase->po.'" />';
                        $action .= '<form method="POST" action="'.route('purchases.destroy', $purchase->id).'" id="form_delete_'.$purchase->id.'" value="'.$purchase->po.'">'.method_field('DELETE').csrf_field().'</form>';
                    }
                    $action .= ($purchase->remark == 0) ? `<a href="purchase/pay/$purchase->id'" class="btn btn-sm btn-icon btn-active-color-primary btn-bg-light me-1"><i class="fas fa-file-invoice-dollar"></i></a>` : '';
                }
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function searchItem(Request $request)
    {
        $name = $request->search;
        $items = DB::table('items as i')
            ->where('i.status', true)
            ->where('i.user_id', auth()->user()->user_id)
            ->where(function ($query) use ($name) {
                $query->where('i.barcode', 'LIKE', '%'.$name.'%')->orWhere('i.name', 'LIKE', '%'.$name.'%');
            })
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

    public function getChosenItem(Request $request)
    {
        $store_id = $request->store;
        $item = Item::where('items.id', $request->name)
            ->leftJoin('item_stores as is', 'is.item_id', 'items.id')
            ->where('is.store_id', $store_id)
            ->select('items.*', DB::raw('format(is.stock, 2) as stock'))
            ->first();
        // $output = "Ritsard Gwapo";
        $item_units = DB::table('item_units as iu')
            ->leftJoin('units as u', 'u.id', 'iu.unit_id')
            ->where('iu.item_id', $item->id)
            ->get();
        $units = ' ';
        foreach ($item_units as $unit) {
            $units .= '<option value="'.$unit->unit_id.'">'.$unit->name.'</option>';
        }
        $output = [
            '<input type="hidden" value="'.$item->id.'" name="item_id[]"/>'.$item->name.'',
            '<select name="unit[]" class="form-control"><option value="">PCS</option>'.$units.'</select>',
            $item->stock,
            '<input name="qty[]" type="text" class="form-control" onkeypress="return isNumberKey(event)" oninput="limitDecimalPlaces(event, 0)" value = "" required/>',
            '<input name="price[]" type="text" class="form-control" onkeypress="return isNumberKey(event)" oninput="limitDecimalPlaces(event, 2)" value = "" required/>',
            '<button id="DeleteButton" type="button" class="btn btn-danger btn-flat btn-delete"><i class="fas fa-trash"></i></button>',
        ];

        return response()->json($output);
    }

    public function receive($id)
    {
        // Check if PO is approved before allowing receiving
        $purchaseCheck = Purchase::find($id);
        if (! $purchaseCheck || ! $purchaseCheck->isApproved()) {
            return redirect()->route('purchases.index')
                ->with('error', 'This purchase order must be approved before items can be received.');
        }

        $purchase = DB::table('purchases as p')
            ->leftJoin('stores as s', 's.id', 'p.store_id')
            ->leftJoin('suppliers as sup', 'sup.id', 'p.supplier_id')
            ->where('p.status', '<>', 3)
            ->where('p.user_id', auth()->user()->user_id)
            ->leftJoin('users as u', 'u.id', 'p.created_by')
            ->where('p.id', $id)
            ->select('p.*', 's.name as store', 'sup.name as supplier', 'u.name as created')
            ->first();
        $purchase_line = DB::table('purchase_lines as pl')
            ->leftJoin('items as i', 'i.id', 'pl.item_id')
            ->leftJoin('units as u', 'u.id', 'pl.unit_id')
            ->where('purchase_id', $purchase->id)
            ->select('pl.*', 'u.name as unit', 'i.name as item', 'i.id as item_id')
            ->get();
        $access = Role::find(auth()->user()->role_id);

        return view('admin.inventory-management.purchases.receive', compact('purchase', 'access', 'purchase_line'));
    }

    public function receiveNow($id, Request $request)
    {
        $purchase = Purchase::find($id);

        // Check if PO is approved before allowing receiving
        if (! $purchase->isApproved()) {
            return redirect()->route('purchases.index')
                ->with('error', 'This purchase order must be approved before items can be received.');
        }

        // §1.3 of purchase_order_audit_and_remediation.md — wrap the
        // multi-row receive in a transaction with `lockForUpdate` on
        // ItemStore, mirroring OpenClaw's receive implementation
        // (app/Http/Controllers/API/v1/openclaw/PurchaseController.php).
        // Without this, two concurrent partial receives on the same
        // item silently lose updates; mid-call failures leave half-
        // written stock + line.received rows.
        DB::transaction(function () use ($purchase, $request) {
            $total_received = 0;
            $updateCostFlags = $request->input('update_cost', []);

            for ($i = 0; $i < count($request->line_id); $i++) {
                $line = PurchaseLine::find($request->line_id[$i]);
                $line->update([
                    'received' => $request->toReceive[$i] + $line->received,
                ]);

                // §3.2 — prefer the unit_qty snapshot on the line
                // (frozen at order time). Both null and 0 mean "no
                // snapshot recorded"; fall back to a live ItemUnit
                // lookup for those legacy / pre-2025-10-13 rows.
                $snapshot = (float) ($line->unit_qty ?? 0);
                if ($snapshot > 0) {
                    $unit_qty = $snapshot;
                } else {
                    $unit = ItemUnit::where('unit_id', $line->unit_id)->where('item_id', $line->item_id)->first();
                    $unit_qty = ($unit) ? $unit->qty : 1;
                }

                $total_received += $request->toReceive[$i];
                // lockForUpdate so a concurrent receive on the same
                // (item, store) row serialises against this one.
                $source = ItemStore::where('item_id', $line->item_id)
                    ->where('store_id', $purchase->store_id)
                    ->lockForUpdate()
                    ->first();
                $source_stock = $source->stock + ($request->toReceive[$i] * $unit_qty);
                $source->update(['stock' => $source_stock]);

                // Only update item cost if the checkbox for this line was checked
                if (isset($updateCostFlags[$i])) {
                    $item = Item::find($line->item_id);
                    $item->update([
                        'prev_cost' => $item->cost,
                        'cost' => $line->cost / $unit_qty,
                    ]);
                }
            }

            $purchase->update([
                'received' => $total_received + $purchase->received,
                'received_by' => auth()->user()->id,
                'status' => false,
            ]);

            \App\Models\Reports\AuditLog::record(
                $purchase,
                'purchase_received',
                [
                    'po' => $purchase->po,
                    'received_this_call' => $total_received,
                    'received_running_total' => $purchase->received,
                    'lines' => collect($request->line_id)->map(fn ($id, $i) => [
                        'line_id' => (int) $id,
                        'qty_received' => (int) $request->toReceive[$i],
                    ])->all(),
                ]
            );
        });

        return redirect()->route('purchases.index')->with('msg', 'Successfully received PO# :'.$purchase->po);
    }

    public function payNow($id)
    {
        $access = Role::find(auth()->user()->role_id);
        $purchase = DB::table('purchases as p')
            ->leftJoin('stores as s', 's.id', 'p.store_id')
            ->leftJoin('suppliers as sup', 'sup.id', 'p.supplier_id')
            ->where('p.user_id', auth()->user()->user_id)
            ->leftJoin('users as u', 'u.id', 'p.created_by')
            ->leftJoin('users as r', 'r.id', 'p.received_by')
            ->where('p.id', $id)
            ->select('p.*', 's.name as store', 'sup.name as supplier', 'u.name as created', 'r.name as receiver')
            ->first();
        $purchase_line = DB::table('purchase_lines as pl')
            ->leftJoin('items as i', 'i.id', 'pl.item_id')
            ->leftJoin('units as u', 'u.id', 'pl.unit_id')
            ->where('purchase_id', $id)
            ->select('pl.*', 'u.name as unit', 'i.name as item')
            ->get();

        return view('admin.inventory-management.purchases.pay', compact('access', 'purchase', 'purchase_line'));
    }

    public function printPO(Purchase $purchase)
    {
        if (auth()->user()->role->prchs_read) {
            $purchase = Purchase::where('id', $purchase->id)
                ->with([
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
                                ]);
                            },
                            'unit' => function ($q) {
                                $q->select('id', 'name');
                            },
                        ]);
                    },
                    'adds',
                    'supplier' => function ($q) {
                        $q->select('id', 'name');
                    },
                    'store' => function ($q) {
                        $q->select('id', 'name');
                    },
                    'creator' => function ($q) {
                        $q->select('id', 'name');
                    },
                    'receiver',
                ])
                ->first();

            // return $purchase;
            return view('admin.inventory-management.purchases.print', compact('purchase'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function getDataFromDate(Request $request)
    {
        $start = Carbon::parse($request->start)->startOfDay()->format('Y-m-d H:m:s');
        $end = Carbon::parse($request->end)->endOfDay()->format('Y-m-d H:m:s');
        $output = [];
        $purchases = DB::table('purchases as p')
            ->leftJoin('stores as s', 's.id', 'p.store_id')
            ->leftJoin('suppliers as sup', 'sup.id', 'p.supplier_id')
            ->leftJoin('users as u', 'u.id', 'p.created_by')
            ->where('p.status', '<>', 3)
            ->where('p.user_id', auth()->user()->user_id)
            ->whereBetween('purchased', [$start, $end])
            ->select('p.*', 's.name as store', 'sup.name as supplier', 'u.name as created')
            ->get();
        foreach ($purchases as $purchase) {
            $bg = ($purchase->items - $purchase->received != 0) ? 'bg-warning ' : 'bg-primary ';
            $percent = ($purchase->received > 0) ? ($purchase->received / $purchase->items) * 100 : 0;
            $output[] = [
                $purchase->po,
                $purchase->supplier,
                $purchase->store,
                "<span class='text-success'>".$purchase->purchased.'</span>',
                '<div class="progress progress-xs"><div class="progress-bar'.$bg.' progress-bar-striped" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: '.$percent.'%"></div>
                </div>
                <small>'.$purchase->received.' of '.$purchase->items.' received</small>',
                $purchase->invoice_no,
                '₱ '.number_format($purchase->total, 2),
                ($purchase->payment_status == 1) ? '<span class="badge badge-success">Paid</span>' : '<span class="badge badge-danger">Unpaid</span>',
                '<div class="btn-group">
                <a href="'.route('purchases.show', $purchase->id).'" class="btn btn-info "><i class="far fa-eye"></i></a>'.
                ($purchase->status == 1) ?? '<form action="'.route('purchases.destroy', $purchase->id).'" method ="DELETE">'.'<button type="submit" class="btn btn-danger" onclick="return confirm("Are you sure you want to delete this Transfer Order?")"><i class="fas fa-trash"></i></button></form>'
                ($purchase->payment_status == 0) ? '<a href="'.route('purchases.pay', $purchase->id).'" class="btn btn-success"><i class="fas fa-file-invoice-dollar"></i></a>'.'</div>' : '</div>',
            ];
        }

        return response()->json(compact('output'));
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

    /**
     * Approve a purchase order
     */
    public function approve(Purchase $purchase)
    {
        $access = Role::find(auth()->user()->role_id);

        // Check if user has permission to approve
        if (! $access->prchs_approve) {
            return redirect()->route('purchases.show', $purchase->id)
                ->with('error', 'You do not have permission to approve purchase orders.');
        }

        // Prevent self-approval
        if ($purchase->created_by === auth()->user()->id) {
            return redirect()->route('purchases.show', $purchase->id)
                ->with('error', 'You cannot approve your own purchase order.');
        }

        // Check if PO is pending approval
        if (! $purchase->isPendingApproval()) {
            return redirect()->route('purchases.show', $purchase->id)
                ->with('error', 'This purchase order is not pending approval.');
        }

        // Update approval status
        $purchase->update(['approval_status' => Purchase::APPROVAL_APPROVED]);

        // Create approval record
        PurchaseApproval::create([
            'purchase_id' => $purchase->id,
            'status' => 'approved',
            'approved_by' => auth()->user()->id,
            'approved_at' => now(),
        ]);

        \App\Models\Reports\AuditLog::record(
            $purchase,
            'purchase_approved',
            [
                'from_status' => Purchase::APPROVAL_PENDING,
                'to_status' => Purchase::APPROVAL_APPROVED,
                'po' => $purchase->po,
                'total' => $purchase->total,
            ]
        );

        return redirect()->route('purchases.show', $purchase->id)
            ->with('success', 'Purchase order approved successfully.');
    }

    /**
     * Reject a purchase order
     */
    public function reject(Request $request, Purchase $purchase)
    {
        $access = Role::find(auth()->user()->role_id);

        // Check if user has permission to reject
        if (! $access->prchs_approve) {
            return redirect()->route('purchases.show', $purchase->id)
                ->with('error', 'You do not have permission to reject purchase orders.');
        }

        // Prevent self-rejection
        if ($purchase->created_by === auth()->user()->id) {
            return redirect()->route('purchases.show', $purchase->id)
                ->with('error', 'You cannot reject your own purchase order.');
        }

        // Check if PO is pending approval
        if (! $purchase->isPendingApproval()) {
            return redirect()->route('purchases.show', $purchase->id)
                ->with('error', 'This purchase order is not pending approval.');
        }

        // Validate rejection comment
        $request->validate([
            'rejection_comment' => 'required|string|min:10',
        ], [
            'rejection_comment.required' => 'Please provide a reason for rejection.',
            'rejection_comment.min' => 'Rejection reason must be at least 10 characters.',
        ]);

        // Update approval status
        $purchase->update(['approval_status' => Purchase::APPROVAL_REJECTED]);

        // Create approval record with rejection comment
        PurchaseApproval::create([
            'purchase_id' => $purchase->id,
            'status' => 'rejected',
            'approved_by' => auth()->user()->id,
            'approved_at' => now(),
            'rejection_comment' => $request->rejection_comment,
        ]);

        \App\Models\Reports\AuditLog::record(
            $purchase,
            'purchase_rejected',
            [
                'from_status' => Purchase::APPROVAL_PENDING,
                'to_status' => Purchase::APPROVAL_REJECTED,
                'po' => $purchase->po,
                'rejection_comment' => $request->rejection_comment,
            ]
        );

        return redirect()->route('purchases.show', $purchase->id)
            ->with('warning', 'Purchase order has been rejected.');
    }

    /**
     * Record a payment for a purchase order
     */
    public function recordPayment(PaymentRequest $request, Purchase $purchase): JsonResponse
    {
        $access = Role::find(auth()->user()->role_id);

        if (! $access->prchs_update) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to record payments.',
            ], 403);
        }

        $validated = $request->validated();
        $bank = Bank::findOrFail($validated['bank_id']);

        $result = DB::transaction(function () use ($validated, $purchase, $bank) {
            $balanceBefore = $bank->balance;
            $balanceAfter = $balanceBefore - $validated['amount'];

            // Create bank transaction (withdrawal)
            $bankTransaction = BankTransaction::create([
                'reference_number' => BankTransaction::generateReferenceNumber(),
                'bank_id' => $bank->id,
                'type' => BankTransaction::TYPE_WITHDRAWAL,
                'amount' => $validated['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => 'Payment for PO #'.$purchase->po,
                'payee' => $purchase->supplier?->name ?? 'Supplier',
                'transaction_date' => $validated['payment_date'],
                'created_by' => auth()->id(),
            ]);

            // Update bank balance
            $bank->update(['balance' => $balanceAfter]);

            // Create purchase payment record
            $payment = PurchasePayment::create([
                'reference_number' => PurchasePayment::generateReferenceNumber(),
                'purchase_id' => $purchase->id,
                'bank_id' => $bank->id,
                'bank_transaction_id' => $bankTransaction->id,
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'check_number' => $validated['check_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Update purchase amount_paid and payment status
            $purchase->amount_paid = ($purchase->amount_paid ?? 0) + $validated['amount'];
            $purchase->save();
            $purchase->updatePaymentStatus();

            \App\Models\Reports\AuditLog::record(
                $purchase,
                'purchase_payment_recorded',
                [
                    'po' => $purchase->po,
                    'amount' => $validated['amount'],
                    'payment_method' => $validated['payment_method'],
                    'bank_id' => $bank->id,
                    'bank_transaction_id' => $bankTransaction->id,
                    'purchase_payment_id' => $payment->id,
                    'new_amount_paid' => $purchase->amount_paid,
                    'new_payment_status' => $purchase->payment_status,
                ]
            );

            return [
                'payment' => $payment,
                'bank_transaction' => $bankTransaction,
            ];
        });

        $purchase->refresh();

        // §C1 of development/specs/purchase_order_audit_and_remediation.md
        // — return 201 Created to match the OpenClaw + mobile shape.
        // recordPayment creates a PurchasePayment ledger row + a
        // BankTransaction row, so 201 is the semantically right code
        // (RFC 9110 §15.3.2). Was returning 200 inconsistently.
        return response()->json([
            'success' => true,
            'message' => 'Payment of '.number_format($validated['amount'], 2).' recorded successfully.',
            'payment' => $result['payment']->load(['bank', 'createdBy']),
            'new_balance' => $bank->fresh()->balance,
            'purchase' => [
                'amount_paid' => $purchase->amount_paid,
                'remaining_balance' => $purchase->remaining_balance,
                'payment_status' => $purchase->payment_status,
                'payment_status_label' => $purchase->payment_status_label,
            ],
        ], 201);
    }

    /**
     * Get payment history for a purchase order (for DataTables)
     */
    public function paymentHistory(Purchase $purchase): JsonResponse
    {
        $payments = $purchase->payments()
            ->with(['bank', 'createdBy'])
            ->get()
            ->map(function ($payment) {
                return [
                    'reference_number' => $payment->reference_number,
                    'payment_date' => $payment->payment_date?->format('M d, Y'),
                    'payment_method' => $payment->payment_method_name,
                    'bank' => $payment->bank ? $payment->bank->bank_name : 'N/A',
                    'amount' => number_format($payment->amount, 2),
                    'check_number' => $payment->check_number ?? '-',
                    'notes' => $payment->notes ?? '-',
                    'created_by' => $payment->createdBy?->name ?? 'N/A',
                ];
            });

        return response()->json([
            'data' => $payments,
        ]);
    }

    /**
     * Get bank data for payment form
     */
    public function getBanks(): JsonResponse
    {
        $banks = Bank::select('id', 'bank_name', 'account_name', 'account_number', 'balance')
            ->get();

        return response()->json([
            'banks' => $banks,
        ]);
    }
}
