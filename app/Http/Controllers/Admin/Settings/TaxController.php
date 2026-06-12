<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\Products\Item;
use App\Models\Settings\Tax;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;

class TaxController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Factory|View|RedirectResponse|Redirector|\Illuminate\View\View
     */
    public function index()
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->tax) {
            $taxes = Tax::where('user_id', auth()->user()->user_id)->where('status', true)->get();

            return view('admin.settings.taxes.index', compact('taxes', 'access'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Factory|View|RedirectResponse|Redirector|\Illuminate\View\View
     */
    public function create()
    {
        //
        $tax = new Tax;
        $access = Role::find(auth()->user()->role_id);
        if ($access->tax_create) {
            return view('admin.settings.taxes.create', compact('tax', 'access'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'name' => 'required',
            'rate' => 'required|numeric',
        ]);
        Tax::create([
            'name' => $request->name,
            'rate' => $request->rate,
            'user_id' => auth()->user()->user_id,
            'status' => true,
        ]);

        return redirect()->route('taxes.index')->with('success', 'Successfully added a new Tax!');
    }

    /**
     * Display the specified resource.
     *
     * @return Factory|\Illuminate\View\View|Redirector|RedirectResponse|View
     */
    public function show(Tax $tax)
    {
        $access = Role::find(auth()->user()->role_id);
        if ($access->tax_read) {
            return view('admin.settings.taxes.show', compact('tax', 'access'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Tax  $tax
     * @return \Illuminate\Http\Response
     */
    public function edit(Tax $tax)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->tax_update) {
            return view('admin.settings.taxes.edit', compact('tax', 'access'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Tax  $tax
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Tax $tax)
    {
        //
        $request->validate([
            'name' => 'required',
            'rate' => 'required|numeric',
        ]);
        Tax::find($tax->id)->update([
            'name' => $request->name,
            'rate' => $request->rate,
        ]);

        return redirect()->route('taxes.index')->with('info', 'Tax successfully updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Tax  $tax
     * @return \Illuminate\Http\Response
     */
    public function destroy(Tax $tax)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->tax_delete) {
            Tax::find($tax->id)->update([
                'status' => false,
            ]);

            return redirect()->route('taxes.index')->with('success', 'Tax successfully deleted!');
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function select(Request $request)
    {
        $name = $request->term;
        $taxes = Tax::where('name', 'LIKE', "%$name%")->take(50)->get();
        $data = [];
        foreach ($taxes as $tax) {
            $data[] = ['id' => $tax->id, 'text' => $tax->name];
        }

        return $data;
    }

    public function table()
    {
        $q = Tax::query()->select('id', 'name', 'rate')->where('status', true);

        return DataTables($q)
            ->addColumn('actions', function (Tax $tax) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                // View Button
                if (auth()->user()->role->tax_read) {
                    $action .= '<a href="'.route('taxes.show', $tax->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Details"><i class="fas fa-eye"></i></a>&nbsp';
                }
                if (auth()->user()->role->tax_update) {
                    // Edit Button
                    $action .= '<a href="'.route('taxes.edit', $tax->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" value="'.$tax->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-edit"></i></a>&nbsp';
                }
                if (auth()->user()->role->tax_delete) {
                    // Delete Button
                    $action .= '<span data-bs-toggle="modal" data-bs-target="#deleteModal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1" value="'.$tax->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-trash"></i></button></span>';
                    $action .= '<input type="hidden" id="name_'.$tax->id.'" value="'.$tax->name.'" />';
                    $action .= '<form method="POST" action="'.route('taxes.destroy', $tax->id).'" id="form_delete_'.$tax->id.'" value="'.$tax->name.'">'.method_field('DELETE').csrf_field().'</form>';
                }
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function getTax(Tax $tax)
    {
        return $tax;
    }

    public function showTaxTable(Tax $tax)
    {
        $query = Item::where('tax_id', $tax->id);

        return DataTables($query)
            ->addColumn('actions', function (Item $item) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                // View Button
                if (auth()->user()->role->itms_read) {
                    $action .= '<a href="'.route('items.show', $item->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Details" target="_blank"><i class="bi bi-arrow-up-right-square"></i></a>&nbsp';
                }
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }
}
