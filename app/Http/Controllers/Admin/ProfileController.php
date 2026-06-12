<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Employees\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    //
    public function index()
    {
        $profile = DB::table('users as u')
            ->leftJoin('employees as e', 'e.user_id', 'u.id')
            ->where('u.id', auth()->user()->id)
            ->select('u.*', 'e.phone', 'e.address')
            ->first();
        $access = Role::find(auth()->user()->role_id);
        $timeline = DB::table('pos_logs')->where('user_id', auth()->user()->id)->whereDate('created_at', Carbon::today())->orderBy('id', 'DESC')->get();

        return view('admin.profile', compact('access', 'profile', 'timeline'));
    }

    public function update(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'phone' => 'required',
            'address' => 'required',
            'image' => 'mimes:jpg,png,jpeg,gif,svg',
        ]);
        $old_image = $request->old_image;

        $brand_image = $request->file('image');
        if ($brand_image) {
            $name_gen = hexdec(uniqid());
            $img_ext = strtolower($brand_image->getClientOriginalExtension());
            $img_name = $name_gen.'.'.$img_ext;
            $up_location = 'img/employees/';
            $last_image = $up_location.$img_name;
            $brand_image->move($up_location, $last_image);

            if ($request->old_image) {
                if (file_exists($old_image)) {
                    unlink($old_image);
                }
            }
            Employee::where('user_id', auth()->user()->id)->update([
                'phone' => strtoupper($request->phone),
                'address' => strtoupper($request->address),
                'image' => $last_image,
            ]);
        } else {
            Employee::where('user_id', auth()->user()->id)->update([
                'phone' => strtoupper($request->phone),
                'address' => strtoupper($request->address),
            ]);
        }
        Employee::where('user_id', auth()->user()->id)->update([
            'phone' => $request->phone,
            'address' => $request->address,
        ]);

        return redirect()->route('profile')->with('msg', 'Profile details successfully updated!');
    }

    public function update_password(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'password' => 'required',
            'password_confirmation' => 'required',
            'password' => 'confirmed',
        ]);
        User::find(auth()->user()->id)->update([
            'password' => bcrypt($request->password),
        ]);

        return redirect()->route('profile')->with('msg', 'Succesfully updated your password!');
    }
}
