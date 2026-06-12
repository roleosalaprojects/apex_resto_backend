<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Factory|View|\Illuminate\View\View
     */
    public function index()
    {
        //
        $roles = Role::where('user_id', auth()->user()->user_id)->where('status', true)->get();
        // dd($roles);
        $access = Role::find(auth()->user()->role_id);

        return view('admin.employees.roles.index', compact('roles', 'access'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Factory|\Illuminate\View\View|View
     */
    public function create()
    {
        //
        $role = new Role;
        $access = Role::find(auth()->user()->role_id);

        return view('admin.employees.roles.create', compact('role', 'access'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        //
        // dd($request->all());
        $request->validate([
            'name' => 'required',
        ]);
        $pos = ($request->pos) ? true : false;
        $rfnd = ($request->rfnd) ? true : false;
        $discounts = ($request->discounts) ? true : false;
        $bck_offc = ($request->bck_offc) ? true : false;
        $sls = ($request->sls) ? true : false;
        $itms = ($request->itms) ? true : false;
        $itms_read = ($request->itms_read) ? true : false;
        $itms_create = ($request->itms_create) ? true : false;
        $itms_update = ($request->itms_update) ? true : false;
        $itms_delete = ($request->itms_delete) ? true : false;
        $adjstmnts = ($request->adjstmnts) ? true : false;
        $adjstmnts_read = ($request->adjstmnts_read) ? true : false;
        $adjstmnts_create = ($request->adjstmnts_create) ? true : false;
        $adjstmnts_update = ($request->adjstmnts_update) ? true : false;
        $adjstmnts_delete = ($request->adjstmnts_delete) ? true : false;
        $trnsfrs = ($request->trnsfrs) ? true : false;
        $trnsfrs_read = ($request->trnsfrs_read) ? true : false;
        $trnsfrs_create = ($request->trnsfrs_create) ? true : false;
        $trnsfrs_update = ($request->trnsfrs_update) ? true : false;
        $trnsfrs_delete = ($request->trnsfrs_delete) ? true : false;
        $emplys = ($request->emplys) ? true : false;
        $emplys_read = ($request->emplys_read) ? true : false;
        $emplys_create = ($request->emplys_create) ? true : false;
        $emplys_update = ($request->emplys_update) ? true : false;
        $emplys_delete = ($request->emplys_delete) ? true : false;
        $rl = ($request->rl) ? true : false;
        $rl_read = ($request->rl_read) ? true : false;
        $rl_create = ($request->rl_create) ? true : false;
        $rl_update = ($request->rl_update) ? true : false;
        $rl_delete = ($request->rl_delete) ? true : false;
        $cstmr = ($request->cstmr) ? true : false;
        $cstmr_read = ($request->cstmr_read) ? true : false;
        $cstmr_create = ($request->cstmr_create) ? true : false;
        $cstmr_update = ($request->cstmr_update) ? true : false;
        $cstmr_delete = ($request->cstmr_delete) ? true : false;
        $str = ($request->str) ? true : false;
        $str_read = ($request->str_read) ? true : false;
        $str_create = ($request->str_create) ? true : false;
        $str_update = ($request->str_update) ? true : false;
        $str_delete = ($request->str_delete) ? true : false;
        $tax = ($request->tax) ? true : false;
        $tax_read = ($request->tax_read) ? true : false;
        $tax_create = ($request->tax_create) ? true : false;
        $tax_update = ($request->tax_update) ? true : false;
        $tax_delete = ($request->tax_delete) ? true : false;
        $sttngs = ($request->sttngs) ? true : false;
        $prchs = ($request->prchs) ? true : false;
        $prchs_read = ($request->prchs_read) ? true : false;
        $prchs_create = ($request->prchs_create) ? true : false;
        $prchs_update = ($request->prchs_update) ? true : false;
        $prchs_delete = ($request->prchs_delete) ? true : false;
        $prchs_approve = ($request->prchs_approve) ? true : false;
        $invntry = ($request->invntry) ? true : false;
        $invntry_read = ($request->invntry_read) ? true : false;
        $invntry_create = ($request->invntry_create) ? true : false;
        $invntry_update = ($request->invntry_update) ? true : false;
        $invntry_delete = ($request->invntry_delete) ? true : false;
        $spplrs = ($request->spplrs) ? true : false;
        $spplrs_read = ($request->spplrs_read) ? true : false;
        $spplrs_create = ($request->spplrs_create) ? true : false;
        $spplrs_update = ($request->spplrs_update) ? true : false;
        $spplrs_delete = ($request->spplrs_delete) ? true : false;
        $attndnc = ($request->attndnc) ? true : false;
        $attndnc_read = ($request->attndnc_read) ? true : false;
        $attndnc_create = ($request->attndnc_create) ? true : false;
        $attndnc_update = ($request->attndnc_update) ? true : false;
        $attndnc_delete = ($request->attndnc_delete) ? true : false;
        $attndnc_schedules = ($request->attndnc_schedules) ? true : false;
        $bnkng = ($request->bnkng) ? true : false;
        $bnkng_read = ($request->bnkng_read) ? true : false;
        $bnkng_create = ($request->bnkng_create) ? true : false;
        $bnkng_update = ($request->bnkng_update) ? true : false;
        $bnkng_delete = ($request->bnkng_delete) ? true : false;
        $expnss = ($request->expnss) ? true : false;
        $expnss_read = ($request->expnss_read) ? true : false;
        $expnss_create = ($request->expnss_create) ? true : false;
        $expnss_update = ($request->expnss_update) ? true : false;
        $expnss_delete = ($request->expnss_delete) ? true : false;
        $print = ($request->print) ? true : false;
        $delete_items = ($request->delete_items) ? true : false;
        $pulse = ($request->pulse) ? true : false;
        $csh_out = ($request->csh_out) ? true : false;
        $crdt_sale = ($request->crdt_sale) ? true : false;
        $crdt_pymnt = ($request->crdt_pymnt) ? true : false;
        $unit_lock = ($request->unit_lock) ? true : false;
        $unit_lock_approve = ($request->unit_lock_approve) ? true : false;
        Role::create([
            'name' => strtoupper($request->name),
            'pos' => $pos,
            'delete_items' => $delete_items,
            'rfnd' => $rfnd,
            'discounts' => $discounts,
            'print' => $print,
            'bck_offc' => $bck_offc,
            'sls' => $sls,
            'itms' => $itms,
            'itms_read' => $itms_read,
            'itms_create' => $itms_create,
            'itms_update' => $itms_update,
            'itms_delete' => $itms_delete,
            'adjstmnts' => $adjstmnts,
            'adjstmnts_read' => $adjstmnts_read,
            'adjstmnts_create' => $adjstmnts_create,
            'adjstmnts_update' => $adjstmnts_update,
            'adjstmnts_delete' => $adjstmnts_delete,
            'trnsfrs' => $trnsfrs,
            'trnsfrs_read' => $trnsfrs_read,
            'trnsfrs_create' => $trnsfrs_create,
            'trnsfrs_update' => $trnsfrs_update,
            'trnsfrs_delete' => $trnsfrs_delete,
            'emplys' => $emplys,
            'emplys_read' => $emplys_read,
            'emplys_create' => $emplys_create,
            'emplys_update' => $emplys_update,
            'emplys_delete' => $emplys_delete,
            'rl' => $rl,
            'rl_read' => $rl_read,
            'rl_create' => $rl_create,
            'rl_update' => $rl_update,
            'rl_delete' => $rl_delete,
            'cstmr' => $cstmr,
            'cstmr_read' => $cstmr_read,
            'cstmr_create' => $cstmr_create,
            'cstmr_update' => $cstmr_update,
            'cstmr_delete' => $cstmr_delete,
            'str' => $str,
            'str_read' => $str_read,
            'str_create' => $str_create,
            'str_update' => $str_update,
            'str_delete' => $str_delete,
            'tax' => $tax,
            'tax_read' => $tax_read,
            'tax_create' => $tax_create,
            'tax_update' => $tax_update,
            'tax_delete' => $tax_delete,
            'sttngs' => $sttngs,
            'status' => true,
            'user_id' => auth()->user()->user_id,
            'prchs' => $prchs,
            'prchs_read' => $prchs_read,
            'prchs_create' => $prchs_create,
            'prchs_update' => $prchs_update,
            'prchs_delete' => $prchs_delete,
            'prchs_approve' => $prchs_approve,
            'invntry' => $invntry,
            'invntry_read' => $invntry_read,
            'invntry_create' => $invntry_create,
            'invntry_update' => $invntry_update,
            'invntry_delete' => $invntry_delete,
            'spplrs' => $spplrs,
            'spplrs_read' => $spplrs_read,
            'spplrs_create' => $spplrs_create,
            'spplrs_update' => $spplrs_update,
            'spplrs_delete' => $spplrs_delete,
            'attndnc' => $attndnc,
            'attndnc_read' => $attndnc_read,
            'attndnc_create' => $attndnc_create,
            'attndnc_update' => $attndnc_update,
            'attndnc_delete' => $attndnc_delete,
            'attndnc_schedules' => $attndnc_schedules,
            'bnkng' => $bnkng,
            'bnkng_read' => $bnkng_read,
            'bnkng_create' => $bnkng_create,
            'bnkng_update' => $bnkng_update,
            'bnkng_delete' => $bnkng_delete,
            'expnss' => $expnss,
            'expnss_read' => $expnss_read,
            'expnss_create' => $expnss_create,
            'expnss_update' => $expnss_update,
            'expnss_delete' => $expnss_delete,
            'pulse' => $pulse,
            'csh_out' => $csh_out,
            'crdt_sale' => $crdt_sale,
            'crdt_pymnt' => $crdt_pymnt,
            'unit_lock' => $unit_lock,
            'unit_lock_approve' => $unit_lock_approve,
        ]);

        return redirect()->route('roles.index')->with('msg', 'User Access Right successfully added!');

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Role  $role
     * @return Response
     */
    public function show(Role $role)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Role  $role
     * @return Response
     */
    public function edit(Role $role)
    {
        //
        // dd($role);
        $access = Role::find(auth()->user()->role_id);

        return view('admin.employees.roles.edit', compact('role', 'access'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Role  $role
     * @return Response
     */
    public function update(Request $request, Role $role)
    {
        //
        $request->validate([
            'name' => 'required',
        ]);
        $pos = ($request->pos) ? true : false;
        $rfnd = ($request->rfnd) ? true : false;
        $discounts = ($request->discounts) ? true : false;
        $bck_offc = ($request->bck_offc) ? true : false;
        $sls = ($request->sls) ? true : false;
        $itms = ($request->itms) ? true : false;
        $itms_read = ($request->itms_read) ? true : false;
        $itms_create = ($request->itms_create) ? true : false;
        $itms_update = ($request->itms_update) ? true : false;
        $itms_delete = ($request->itms_delete) ? true : false;
        $adjstmnts = ($request->adjstmnts) ? true : false;
        $adjstmnts_read = ($request->adjstmnts_read) ? true : false;
        $adjstmnts_create = ($request->adjstmnts_create) ? true : false;
        $adjstmnts_update = ($request->adjstmnts_update) ? true : false;
        $adjstmnts_delete = ($request->adjstmnts_delete) ? true : false;
        $trnsfrs = ($request->trnsfrs) ? true : false;
        $trnsfrs_read = ($request->trnsfrs_read) ? true : false;
        $trnsfrs_create = ($request->trnsfrs_create) ? true : false;
        $trnsfrs_update = ($request->trnsfrs_update) ? true : false;
        $trnsfrs_delete = ($request->trnsfrs_delete) ? true : false;
        $emplys = ($request->emplys) ? true : false;
        $emplys_read = ($request->emplys_read) ? true : false;
        $emplys_create = ($request->emplys_create) ? true : false;
        $emplys_update = ($request->emplys_update) ? true : false;
        $emplys_delete = ($request->emplys_delete) ? true : false;
        $rl = ($request->rl) ? true : false;
        $rl_read = ($request->rl_read) ? true : false;
        $rl_create = ($request->rl_create) ? true : false;
        $rl_update = ($request->rl_update) ? true : false;
        $rl_delete = ($request->rl_delete) ? true : false;
        $cstmr = ($request->cstmr) ? true : false;
        $cstmr_read = ($request->cstmr_read) ? true : false;
        $cstmr_create = ($request->cstmr_create) ? true : false;
        $cstmr_update = ($request->cstmr_update) ? true : false;
        $cstmr_delete = ($request->cstmr_delete) ? true : false;
        $str = ($request->str) ? true : false;
        $str_read = ($request->str_read) ? true : false;
        $str_create = ($request->str_create) ? true : false;
        $str_update = ($request->str_update) ? true : false;
        $str_delete = ($request->str_delete) ? true : false;
        $tax = ($request->tax) ? true : false;
        $tax_read = ($request->tax_read) ? true : false;
        $tax_create = ($request->tax_create) ? true : false;
        $tax_update = ($request->tax_update) ? true : false;
        $tax_delete = ($request->tax_delete) ? true : false;
        $sttngs = ($request->sttngs) ? true : false;
        $prchs = ($request->prchs) ? true : false;
        $prchs_read = ($request->prchs_read) ? true : false;
        $prchs_create = ($request->prchs_create) ? true : false;
        $prchs_update = ($request->prchs_update) ? true : false;
        $prchs_delete = ($request->prchs_delete) ? true : false;
        $prchs_approve = ($request->prchs_approve) ? true : false;
        $invntry = ($request->invntry) ? true : false;
        $invntry_read = ($request->invntry_read) ? true : false;
        $invntry_create = ($request->invntry_create) ? true : false;
        $invntry_update = ($request->invntry_update) ? true : false;
        $invntry_delete = ($request->invntry_delete) ? true : false;
        $spplrs = ($request->spplrs) ? true : false;
        $spplrs_read = ($request->spplrs_read) ? true : false;
        $spplrs_create = ($request->spplrs_create) ? true : false;
        $spplrs_update = ($request->spplrs_update) ? true : false;
        $spplrs_delete = ($request->spplrs_delete) ? true : false;
        $attndnc = ($request->attndnc) ? true : false;
        $attndnc_read = ($request->attndnc_read) ? true : false;
        $attndnc_create = ($request->attndnc_create) ? true : false;
        $attndnc_update = ($request->attndnc_update) ? true : false;
        $attndnc_delete = ($request->attndnc_delete) ? true : false;
        $attndnc_schedules = ($request->attndnc_schedules) ? true : false;
        $bnkng = ($request->bnkng) ? true : false;
        $bnkng_read = ($request->bnkng_read) ? true : false;
        $bnkng_create = ($request->bnkng_create) ? true : false;
        $bnkng_update = ($request->bnkng_update) ? true : false;
        $bnkng_delete = ($request->bnkng_delete) ? true : false;
        $expnss = ($request->expnss) ? true : false;
        $expnss_read = ($request->expnss_read) ? true : false;
        $expnss_create = ($request->expnss_create) ? true : false;
        $expnss_update = ($request->expnss_update) ? true : false;
        $expnss_delete = ($request->expnss_delete) ? true : false;
        $print = ($request->print) ? true : false;
        $delete_items = ($request->delete_items) ? true : false;
        $pulse = ($request->pulse) ? true : false;
        $csh_out = ($request->csh_out) ? true : false;
        $crdt_sale = ($request->crdt_sale) ? true : false;
        $crdt_pymnt = ($request->crdt_pymnt) ? true : false;
        $unit_lock = ($request->unit_lock) ? true : false;
        $unit_lock_approve = ($request->unit_lock_approve) ? true : false;
        Role::find($role->id)->update([
            'name' => strtoupper($request->name),
            'pos' => $pos,
            'delete_items' => $delete_items,
            'rfnd' => $rfnd,
            'discounts' => $discounts,
            'print' => $print,
            'bck_offc' => $bck_offc,
            'sls' => $sls,
            'itms' => $itms,
            'itms_read' => $itms_read,
            'itms_create' => $itms_create,
            'itms_update' => $itms_update,
            'itms_delete' => $itms_delete,
            'adjstmnts' => $adjstmnts,
            'adjstmnts_read' => $adjstmnts_read,
            'adjstmnts_create' => $adjstmnts_create,
            'adjstmnts_update' => $adjstmnts_update,
            'adjstmnts_delete' => $adjstmnts_delete,
            'trnsfrs' => $trnsfrs,
            'trnsfrs_read' => $trnsfrs_read,
            'trnsfrs_create' => $trnsfrs_create,
            'trnsfrs_update' => $trnsfrs_update,
            'trnsfrs_delete' => $trnsfrs_delete,
            'emplys' => $emplys,
            'emplys_read' => $emplys_read,
            'emplys_create' => $emplys_create,
            'emplys_update' => $emplys_update,
            'emplys_delete' => $emplys_delete,
            'rl' => $rl,
            'rl_read' => $rl_read,
            'rl_create' => $rl_create,
            'rl_update' => $rl_update,
            'rl_delete' => $rl_delete,
            'cstmr' => $cstmr,
            'cstmr_read' => $cstmr_read,
            'cstmr_create' => $cstmr_create,
            'cstmr_update' => $cstmr_update,
            'cstmr_delete' => $cstmr_delete,
            'str' => $str,
            'str_read' => $str_read,
            'str_create' => $str_create,
            'str_update' => $str_update,
            'str_delete' => $str_delete,
            'tax' => $tax,
            'tax_read' => $tax_read,
            'tax_create' => $tax_create,
            'tax_update' => $tax_update,
            'tax_delete' => $tax_delete,
            'sttngs' => $sttngs,
            'status' => true,
            'user_id' => auth()->user()->user_id,
            'prchs' => $prchs,
            'prchs_read' => $prchs_read,
            'prchs_create' => $prchs_create,
            'prchs_update' => $prchs_update,
            'prchs_delete' => $prchs_delete,
            'prchs_approve' => $prchs_approve,
            'invntry' => $invntry,
            'invntry_read' => $invntry_read,
            'invntry_create' => $invntry_create,
            'invntry_update' => $invntry_update,
            'invntry_delete' => $invntry_delete,
            'spplrs' => $spplrs,
            'spplrs_read' => $spplrs_read,
            'spplrs_create' => $spplrs_create,
            'spplrs_update' => $spplrs_update,
            'spplrs_delete' => $spplrs_delete,
            'attndnc' => $attndnc,
            'attndnc_read' => $attndnc_read,
            'attndnc_create' => $attndnc_create,
            'attndnc_update' => $attndnc_update,
            'attndnc_delete' => $attndnc_delete,
            'attndnc_schedules' => $attndnc_schedules,
            'bnkng' => $bnkng,
            'bnkng_read' => $bnkng_read,
            'bnkng_create' => $bnkng_create,
            'bnkng_update' => $bnkng_update,
            'bnkng_delete' => $bnkng_delete,
            'expnss' => $expnss,
            'expnss_read' => $expnss_read,
            'expnss_create' => $expnss_create,
            'expnss_update' => $expnss_update,
            'expnss_delete' => $expnss_delete,
            'pulse' => $pulse,
            'csh_out' => $csh_out,
            'crdt_sale' => $crdt_sale,
            'crdt_pymnt' => $crdt_pymnt,
            'unit_lock' => $unit_lock,
            'unit_lock_approve' => $unit_lock_approve,
        ]);

        return redirect()->route('roles.index')->with('msg', 'User Access Right updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Role  $role
     * @return Response
     */
    public function destroy(Role $role)
    {
        //
        Role::find($role->id)->update([
            'status' => false,
        ]);

        return redirect()->route('roles.index')->with('msg', 'Role '.$role->name.' successfully deleted!');
    }
}
