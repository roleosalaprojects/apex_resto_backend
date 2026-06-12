<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Pos\Receipt;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $receipt = Receipt::where('user_id', auth()->user()->id)->first();

        // dd($receipt);
        return view('superadmin.receipts', compact('receipt'));
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
     * @param  \App\Receipt  $receipt
     * @return \Illuminate\Http\Response
     */
    public function show(Receipt $receipt)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Receipt  $receipt
     * @return \Illuminate\Http\Response
     */
    public function edit(Receipt $receipt)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Receipt  $receipt
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Receipt $receipt)
    {
        //
        // dd($request->all());
        Receipt::find($receipt->id)->update([
            'header' => strtoupper($request->header),
            'tin' => $request->tin,
            'vat_reg' => $request->vat_reg,
            'footer' => strtoupper($request->footer),
            'points' => $request->points,
            'name' => strtoupper($request->name),
            'email' => $request->email,
            'phone' => $request->phone,
            'ptu' => $request->ptu,
            'accredition' => $request->accredition,
            'display' => $request->display,
        ]);

        return redirect()->route('receipt.index')->with('msg', 'Receipt details successfully updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Receipt  $receipt
     * @return \Illuminate\Http\Response
     */
    public function destroy(Receipt $receipt)
    {
        //
    }

    public function hocuspocus()
    {
        // dd(auth()->user()->all());
        $receipt = Receipt::find(auth()->user()->id);

        return view('superadmin.magic', compact('receipt'));
    }

    public function hpUpdate(Request $request)
    {
        // dd($request->all());
        $receipt = Receipt::find(auth()->user()->id);
        // dd($receipt);
        $receipt->update([
            'hocus_pocus' => ($request->hocuspocus == 'on') ? true : false,
            'apply' => ($request->blackmagic == 'on') ? true : false,
            'rate' => $request->concoction,
            'exempt' => $request->exempt,
        ]);

        return redirect()->route('hocus.pocus')->with('msg', 'Successfully applied Hocus-Pocus');
    }
}
