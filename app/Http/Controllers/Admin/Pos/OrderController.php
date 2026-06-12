<?php

namespace App\Http\Controllers\Admin\Pos;

use App\Http\Controllers\Controller;
use App\Models\Pos\Order;
use App\Models\Products\ItemStore;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Mavinoo\Batch\BatchFacade;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.pos.orders.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function show(Order $order)
    {
        $order = Order::where('id', $order->id)->with([
            'creator',
            'pos',
            'lines' => function ($q) {
                //                $q->with([
                //                    'item',
                //                    'unit',
                //                    'discount',
                //                ]);
                //                return $q;
            },
        ])->first();

        return view('admin.pos.orders.show', compact('order'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function destroy(Order $order)
    {
        //
    }

    public function table(Request $request)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->endDate)->endOfDay()->toDateTimeString();
        // dd($request->all());
        $q = Order::whereBetween('created_at', [$startDate, $endDate])->with([
            'creator' => function ($q) {},
            'pos' => function ($q) {},
        ]);

        return DataTables($q)
            ->addColumn('actions', function (Order $order) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                $action .= '<a href="'.route('orders.show', $order->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Details" target="_blank"><i class="fas fa-eye"></i></a>&nbsp';
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function confirmOrCancelOrder(Order $order, Request $request)
    {
        //        return $request->status;
        $request->validate([
            'status' => 'required|boolean|nullable',
        ], [
            //            'status.boolean' => 'Wrong parameters.'
        ]);
        if ($request->status) {
            $order->update([
                'accepted_by' => auth()->user()->id,
                'accepted_at' => Carbon::parse(),
                'status' => 1, // Order Accepted
            ]);
        } else {
            // Return qty to original if order is cancelled
            $store = Store::where('status', true)->first();
            $orderLines = $order->lines;
            $itemIds = [];
            foreach ($orderLines as $orderLine) {
                $itemIds[] = $orderLine->item_id;
            }
            $itemStores = ItemStore::whereIn('item_id', $itemIds)
                ->where('store_id', $store->id)
                ->get();
            $stocks = [];
            foreach ($itemStores as $index => $itemStore) {
                $stocks[] = [
                    'id' => $itemStore->id,
                    'stock' => $itemStore->stock + $orderLines[$index]->qty,
                ];
            }
            BatchFacade::update(new ItemStore, $stocks, 'id');
            $order->update([
                'cancelled_by' => auth()->user()->id,
                'cancelled_at' => Carbon::now(),
                'status' => 5, // Ordered Canceled
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function setAssignedTerminal(Order $order, Pos $pos)
    {
        $order->update([
            'assigned_by' => auth()->user()->id,
            'assigned_at' => Carbon::now(),
            'status' => 2,
            'pos_id' => $pos->id,
        ]);

        return response()->json(['success' => true]);
    }

    public function orderPrepared(Order $order)
    {
        $order->update([
            'prepared_by' => auth()->user()->id,
            'prepared_at' => Carbon::now(),
            'status' => 3,
        ]);

        return response()->json(['success' => true]);
    }

    public function orderComplete(Order $order)
    {
        $order->update([
            'completed_by' => auth()->user()->id,
            'completed_at' => Carbon::now(),
            'status' => 4,
        ]);

        return response()->json(['success' => true]);
    }
}
