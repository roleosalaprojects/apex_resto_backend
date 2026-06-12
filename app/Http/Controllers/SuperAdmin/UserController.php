<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRelations\Customer;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeStore;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Supplier;
use App\Models\Products\Category;
use App\Models\Settings\PosSetting;
use App\Models\Settings\Store;
use App\Models\Settings\Tax;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    //
    public function index()
    {
        $users = User::all();

        return view('superadmin.users.index', compact('users'));
    }

    public function create()
    {
        return view('superadmin.users.create');
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $user = User::where('email', $request->email)->get();
        if (! $user) {
            return redirect()->back()->with('msg', 'Email has been taken. Please try again!');
        }
        $request->validate([
            'name' => 'required',
            'email' => 'required | email',
            'password' => 'required|confirmed',
            'address' => 'required',
            'phone' => 'required',
        ]);

        $pos = true;
        $rfnd = true;
        $discounts = true;
        $bck_offc = true;
        $sls = true;
        $itms = true;
        $itms_read = true;
        $itms_create = true;
        $itms_update = true;
        $itms_delete = true;
        $adjstmnts = true;
        $adjstmnts_read = true;
        $adjstmnts_create = true;
        $adjstmnts_update = true;
        $adjstmnts_delete = true;
        $trnsfrs = true;
        $trnsfrs_read = true;
        $trnsfrs_create = true;
        $trnsfrs_update = true;
        $trnsfrs_delete = true;
        $emplys = true;
        $emplys_read = true;
        $emplys_create = true;
        $emplys_update = true;
        $emplys_delete = true;
        $rl = true;
        $rl_read = true;
        $rl_create = true;
        $rl_update = true;
        $rl_delete = true;
        $cstmr = true;
        $cstmr_read = true;
        $cstmr_create = true;
        $cstmr_update = true;
        $cstmr_delete = true;
        $str = true;
        $str_read = true;
        $str_create = true;
        $str_update = true;
        $str_delete = true;
        $tax = true;
        $tax_read = true;
        $tax_create = true;
        $tax_update = true;
        $tax_delete = true;
        $sttngs = true;
        $print = true;
        $delete_items = true;

        // Create a user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role_id' => 0,
            'status' => true,
        ]);

        // Create a default role of OWNER that has access to all features
        $role = Role::create([
            'name' => strtoupper('owner'),
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
            'user_id' => $user->id,
            'prchs' => true,
            'prchs_read' => true,
            'prchs_create' => true,
            'prchs_update' => true,
            'prchs_delete' => true,
            'invntry' => true,
            'invntry_read' => true,
            'invntry_create' => true,
            'invntry_update' => true,
            'invntry_delete' => true,
            'spplrs' => true,
            'spplrs_read' => true,
            'spplrs_create' => true,
            'spplrs_update' => true,
            'spplrs_delete' => true,
        ]);
        $bir_role = Role::create([
            'name' => strtoupper('bir'),
            'pos' => $pos,
            'delete_items' => $delete_items,
            'rfnd' => $rfnd,
            'discounts' => $discounts,
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
            'user_id' => $user->id,
            'prchs' => true,
            'prchs_read' => true,
            'prchs_create' => true,
            'prchs_update' => true,
            'prchs_delete' => true,
            'invntry' => true,
            'invntry_read' => true,
            'invntry_create' => true,
            'invntry_update' => true,
            'invntry_delete' => true,
            'spplrs' => true,
            'spplrs_read' => true,
            'spplrs_create' => true,
            'spplrs_update' => true,
            'spplrs_delete' => true,
        ]);
        // Create a user for BIR auditor
        $bir = User::create([
            'name' => 'BIR Officer',
            'email' => 'officer@bir.gov.ph',
            'password' => bcrypt('U}BeTM5!NX7t(jJ['),
            'role_id' => $bir_role->id,
            'status' => true,
            'user_id' => $user->id,
        ]);
        Role::create([
            'name' => strtoupper('bagger'),
            'pos' => false,
            'delete_items' => false,
            'rfnd' => false,
            'discounts' => false,
            'print' => false,
            'bck_offc' => false,
            'sls' => false,
            'itms' => false,
            'itms_read' => false,
            'itms_create' => false,
            'itms_update' => false,
            'itms_delete' => false,
            'adjstmnts' => false,
            'adjstmnts_read' => false,
            'adjstmnts_create' => false,
            'adjstmnts_update' => false,
            'adjstmnts_delete' => false,
            'trnsfrs' => false,
            'trnsfrs_read' => false,
            'trnsfrs_create' => false,
            'trnsfrs_update' => false,
            'trnsfrs_delete' => false,
            'emplys' => false,
            'emplys_read' => false,
            'emplys_create' => false,
            'emplys_update' => false,
            'emplys_delete' => false,
            'rl' => false,
            'rl_read' => false,
            'rl_create' => false,
            'rl_update' => false,
            'rl_delete' => false,
            'cstmr' => false,
            'cstmr_read' => false,
            'cstmr_create' => false,
            'cstmr_update' => false,
            'cstmr_delete' => false,
            'str' => false,
            'str_read' => false,
            'str_create' => false,
            'str_update' => false,
            'str_delete' => false,
            'tax' => false,
            'tax_read' => false,
            'tax_create' => false,
            'tax_update' => false,
            'tax_delete' => false,
            'sttngs' => false,
            'status' => true,
            'user_id' => $user->id,
        ]);
        // Update the user_id FK
        User::find($user->id)->update([
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);
        // Create employee
        Employee::create([
            'phone' => $request->phone,
            'address' => $request->address,
            'status' => true,
            'user_id' => $user->id,
            'image' => null,
        ]);
        // For BIR Officer
        Employee::create([
            'phone' => '',
            'address' => '',
            'status' => true,
            'user_id' => $bir->id,
            'image' => null,
        ]);
        // Create Tax
        Tax::create([
            'name' => strtoupper('sales tax rate'),
            'rate' => 12,
            'status' => true,
            'user_id' => $user->id,
        ]);
        // Create Category
        Category::create([
            'name' => strtoupper('no category'),
            'status' => true,
            'user_id' => $user->id,
        ]);
        // Create Store
        $store = Store::create([
            'name' => strtoupper('default store'),
            'vat_reg' => false,
            'status' => true,
            'user_id' => $user->id,
        ]);
        // Create a default Customer
        Customer::create([
            'name' => strtoupper('walk-in'),
            'code' => '',
            'phone' => '',
            'address' => '',
            'email' => '',
            'city' => '',
            'zip' => '',
            'province' => '',
            'country' => '',
            'status' => true,
            'user_id' => $user->id,
            'note' => 'This is a default value. This cannot be edited in anyway.',
            'points' => 0,
        ]);
        // Create value for Employee Store
        EmployeeStore::create([
            'status' => true,
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);
        Supplier::create([
            'name' => 'NO SUPPLIER',
            'status' => true,
            'user_id' => $user->id,
        ]);
        PosSetting::create([
            'notif' => true,
            'allow' => true,
            'user_id' => $user->id,
        ]);

        return redirect()->route('admin.index')->with('msg', 'You have successfully added a new user!');
    }

    public function edit() {}

    public function update() {}

    public function destroy(User $user)
    {
        // dd($user);
        User::find($user->id)->update(['status' => false]);

        return redirect()->route('admin.index')->with('msg', 'You have successfully deactivated user : '.$user->name);
    }

    public function activate(User $user)
    {
        User::find($user->id)->update(['status' => true]);

        return redirect()->route('admin.index')->with('msg', 'You have successfully activated user : '.$user->name);
    }
}
