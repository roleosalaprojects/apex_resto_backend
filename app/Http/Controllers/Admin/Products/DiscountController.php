<?php

namespace App\Http\Controllers\Admin\Products;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\Products\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
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
        if ($access->itms) {
            $discounts = Discount::where('user_id', auth()->user()->user_id)->where('status', true)->get();

            return view('admin.products.discounts.index', compact('access', 'discounts'));
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
        $discount = new Discount;
        $access = Role::find(auth()->user()->role_id);
        if ($access->itms_create) {
            return view('admin.products.discounts.create', compact('access', 'discount'));
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
        $request->validate([
            'name' => 'required',
            'rate' => 'required|numeric',
        ]);
        Discount::create([
            'name' => $request->name,
            'rate' => $request->rate,
            'status' => true,
            'user_id' => auth()->user()->user_id,
        ]);

        return redirect()->route('discounts.index')->with('success', 'Discount successfully added!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Discount  $discount
     * @return \Illuminate\Http\Response
     */
    public function show(Discount $discount)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Discount  $discount
     * @return \Illuminate\Http\Response
     */
    public function edit(Discount $discount)
    {
        // $discount = new Discount();
        $access = Role::find(auth()->user()->role_id);

        return view('admin.products.discounts.edit', compact('access', 'discount'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Discount  $discount
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Discount $discount)
    {
        //
        $request->validate([
            'name' => 'required',
            'rate' => 'required|numeric',
        ]);
        Discount::find($discount->id)->update([
            'name' => $request->name,
            'rate' => $request->rate,
        ]);

        return redirect()->route('discounts.index')->with('info', 'Discount successfully updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Discount  $discount
     * @return \Illuminate\Http\Response
     */
    public function destroy(Discount $discount)
    {
        //
        // dd($discount);

        $access = Role::find(auth()->user()->role_id);
        if ($access->itms_delete) {
            $d = Discount::find($discount->id);
            $d->update(['status' => false]);

            return redirect()->route('discounts.index')->with('success', 'Discount successfully deleted!');
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function table()
    {
        $q = Discount::query()->select('id', 'name', 'rate')->where('status', true);

        return DataTables($q)
            ->addColumn('actions', function (Discount $discount) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                // View Button
                if (auth()->user()->role->itms_read) {
                    $action .= '<a href="'.route('discounts.show', $discount->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Details"><i class="fas fa-eye"></i></a>&nbsp';
                }
                if (auth()->user()->role->itms_update) {
                    // Edit Button
                    $action .= '<a href="'.route('discounts.edit', $discount->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" value="'.$discount->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-edit"></i></a>&nbsp';
                }
                if (auth()->user()->role->itms_delete) {
                    // Delete Button
                    $action .= '<span data-bs-toggle="modal" data-bs-target="#deleteModal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1" value="'.$discount->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-trash"></i></button></span>';
                    $action .= '<input type="hidden" id="name_'.$discount->id.'" value="'.$discount->name.'" />';
                    $action .= '<form method="POST" action="'.route('discounts.destroy', $discount->id).'" id="form_delete_'.$discount->id.'" value="'.$discount->name.'">'.method_field('DELETE').csrf_field().'</form>';
                }
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }
}
