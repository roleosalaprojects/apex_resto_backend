<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Employees\EmployeeStore;
use App\Models\Employees\Role;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Http\Request;

class StoreController extends Controller
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
        if ($access->str) {
            $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();

            return view('admin.settings.stores.index', compact('stores', 'access'));
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
        if ($access->str_create) {
            $store = new Store;

            return view('admin.settings.stores.create', compact('store', 'access'));
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
        ]);
        $counter = 1;
        $countStore = Store::where('user_id', auth()->user()->user_id)->get();
        if ($countStore) {
            $counter = count($countStore) + 1;
        }
        $store = Store::create([
            'name' => strtoupper($request->name),
            'header' => strtoupper($request->header),
            'footer' => strtoupper($request->footer),
            'tin' => $request->tin,
            'vat_reg' => $request->vat_reg,
            'phone' => $request->phone,
            'email' => $request->email,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'status' => true,
            'user_id' => auth()->user()->user_id,
            'counter' => $counter,
        ]);
        $items = Item::where('user_id', auth()->user()->user_id)->where('status', true)->get();
        foreach ($items as $item) {
            ItemStore::create([
                'stock' => 0,
                'status' => true,
                'store_id' => $store->id,
                'item_id' => $item->id,
            ]);
        }
        $employees = User::where('user_id', auth()->user()->user_id)->get();
        foreach ($employees as $employee) {
            if ($employee->id == auth()->user()->user_id) {
                EmployeeStore::create([
                    'store_id' => $store->id,
                    'status' => true,
                    'user_id' => $employee->id,
                ]);
            } else {
                EmployeeStore::create([
                    'store_id' => $store->id,
                    'status' => false,
                    'user_id' => $employee->id,
                ]);
            }

        }

        return redirect()->route('stores.index')->with('success', 'Successfully added a new Store!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Store  $store
     * @return \Illuminate\Http\Response
     */
    public function show(Store $store)
    {
        return view('admin.settings.stores.show', compact('store'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Store  $store
     * @return \Illuminate\Http\Response
     */
    public function edit(Store $store)
    {
        //
        $access = Role::find(auth()->user()->role_id);
        if ($access->str_update) {
            return view('admin.settings.stores.edit', compact('store', 'access'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Store  $store
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Store $store)
    {
        //
        $request->validate([
            'name' => 'required',
        ]);
        Store::find($store->id)->update([
            'name' => strtoupper($request->name),
            'header' => strtoupper($request->header),
            'footer' => strtoupper($request->footer),
            'tin' => $request->tin,
            'vat_reg' => $request->vat_reg,
            'phone' => $request->phone,
            'email' => $request->email,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return redirect()->route('stores.index')->with('info', 'Store successfully updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Store  $store
     * @return \Illuminate\Http\Response
     */
    public function destroy(Store $store)
    {
        //

        $access = Role::find(auth()->user()->role_id);
        if ($access->str_delete) {
            Store::find($store->id)->update([
                'status' => false,
            ]);
            ItemStore::where('store_id', $store->id)->delete();

            return redirect()->route('stores.index')->with('success', 'Store successfully deleted!');
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function getStore(Store $store)
    {
        return $store;
    }

    public function table()
    {
        $q = Store::query()->where('status', true);

        return DataTables($q)
            ->addColumn('actions', function (Store $store) {
                $action = "<div class='d-flex justify-content-end flex-shrink-0'>";
                // View Button
                if (auth()->user()->role->str_read) {
                    $action .= '<a href="'.route('stores.show', $store->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm me-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Details"><i class="fas fa-eye"></i></a>&nbsp';
                }
                if (auth()->user()->role->str_update) {
                    // Edit Button
                    $action .= '<a href="'.route('stores.edit', $store->id).'" class="btn btn-icon btn-bg-light btn-active-color-info btn-sm me-1" value="'.$store->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-edit"></i></a>&nbsp';
                }
                if (auth()->user()->role->str_delete) {
                    // Delete Button
                    $action .= '<span data-bs-toggle="modal" data-bs-target="#deleteModal"><button type="button" class="btn btn-icon btn-bg-light btn-active-color-danger btn-sm me-1" value="'.$store->id.'" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-trash"></i></button></span>';
                    $action .= '<input type="hidden" id="name_'.$store->id.'" value="'.$store->name.'" />';
                    $action .= '<form method="POST" action="'.route('stores.destroy', $store->id).'" id="form_delete_'.$store->id.'" value="'.$store->name.'">'.method_field('DELETE').csrf_field().'</form>';
                }
                $action .= '</div>';

                return $action;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function select(Request $request)
    {
        $name = $request->term;
        $stores = Store::where('name', 'LIKE', "%$name%")->where('status', true)->take(50)->get();
        $data = [];
        foreach ($stores as $store) {
            $data[] = ['id' => $store->id, 'text' => $store->name];
        }

        return $data;
    }
}
