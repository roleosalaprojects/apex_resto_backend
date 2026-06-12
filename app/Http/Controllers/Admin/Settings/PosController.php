<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\Settings\Pos;
use App\Models\Settings\PosSetting;
use App\Models\Settings\Store;
use Illuminate\Http\Request;

class PosController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $pos = Pos::where('user_id', auth()->user()->user_id)->get();
        $access = Role::find(auth()->user()->role_id);

        return view('admin.settings.pos.index', compact('access', 'pos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        $pos = new Pos;
        $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();
        $stores = $stores->pluck('name', 'id');
        $access = Role::find(auth()->user()->role_id);
        $selected_store = '';

        return view('admin.settings.pos.create', compact('access', 'pos', 'stores', 'selected_store'));
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
            'store' => 'required',
            'type' => 'required',
        ]);
        $terminal = Pos::where('user_id', auth()->user()->user_id)->latest()->first();
        $terminal = ($terminal) ? $terminal->number + 1 : 1;
        Pos::create([
            'name' => $request->name,
            'min' => $request->min,
            'serial' => $request->serial,
            'store_id' => $request->store,
            'status' => false,
            'number' => $terminal,
            'ptu' => $request->ptu,
            'issued' => $request->issued,
            'expiry' => $request->expiry,
            'user_id' => auth()->user()->user_id,
            'type' => $request->type,
        ]);

        return redirect()->route('pos.index')->with('success', 'Successfully addd a new POS device!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Pos  $pos
     * @return \Illuminate\Http\Response
     */
    public function show(Pos $pos)
    {
        //
        $access = Role::find(auth()->user()->role_id);

        return view('admin.settings.pos.index', compact('access'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Pos  $pos
     * @return \Illuminate\Http\Response
     */
    public function edit(Pos $pos, $id)
    {
        //
        $pos = Pos::find($id);
        $stores = Store::where('user_id', auth()->user()->user_id)->where('status', true)->get();
        $stores = $stores->pluck('name', 'id');
        $access = Role::find(auth()->user()->role_id);
        $selected_store = $pos->store_id;

        return view('admin.settings.pos.edit', compact('access', 'pos', 'stores', 'selected_store'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Pos  $pos
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Pos $pos, $id)
    {
        //
        $request->validate([
            'name' => 'required',
            'store' => 'required',
            'type' => 'required',
        ]);
        Pos::find($id)->update([
            'name' => $request->name,
            'min' => $request->min,
            'serial' => $request->serial,
            'store_id' => $request->store,
            'ptu' => $request->ptu,
            'issued' => $request->issued,
            'expiry' => $request->expiry,
            'type' => $request->type,
        ]);

        return redirect()->route('pos.index')->with('info', 'POS Device successfully updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Pos  $pos
     * @return \Illuminate\Http\Response
     */
    public function destroy(Pos $pos)
    {
        //
    }

    public function settings_index()
    {
        $access = Role::find(auth()->user()->role_id);
        if ($access->sttngs) {
            $settings = PosSetting::where('user_id', auth()->user()->user_id)->first();

            return view('admin.settings.pos-settings', compact('access', 'settings'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function settings_update(Request $request)
    {
        $pos_settings = PosSetting::where('user_id', auth()->user()->user_id);
        // dd($request->all());
        // dd($pos_settings);
        if (count($pos_settings->get()) > 0) {
            $pos_settings->update([
                'allow' => ($request->allow) ? true : false,
                'notif' => ($request->notif) ? true : false,
            ]);
        } else {
            PosSetting::create([
                'allow' => ($request->allow) ? true : false,
                'notif' => ($request->notif) ? true : false,
                'user_id' => auth()->user()->user_id,
            ]);
        }

        return redirect()->route('settings.index')->with('success', 'POS Settings have been updated!');
    }

    public function select(Request $request)
    {
        $name = $request->term;
        $pos = Pos::where('status', true)
            ->where(function ($query) use ($name) {
                $query->where('name', 'LIKE', "%$name%");
            })
            ->take(100)
            ->orderBy('name')
            ->get();
        $data = [];
        foreach ($pos as $terminal) {
            $data[] = ['id' => $terminal->id, 'text' => $terminal->name];
        }

        return $data;
    }

    public function table()
    {
        $pos = Pos::where('status', 1)->get();

        return response()->json([
            'data' => datatables($pos)->make(true),
        ]);
    }
}
