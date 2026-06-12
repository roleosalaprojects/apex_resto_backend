<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('guest:superadmin')->except('logout');
    }
    public function index()
    {
        return view('superadmin.auth.login');
    }
    public function store(Request $request)
    {
        // dd($request->all());
        // Validate the user
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);
        //Log the user in
        $credentials = $request->only('email', 'password');
        if (!Auth::guard('superadmin')->attempt($credentials)) {
            return back()->withErrors([
                'message' => 'Wrong credentials please try again'
            ]);
        }
        // Session message
        session()->flash('msg', 'You have been logged in!');
        //Redirect
        return redirect('superadmin/');
    }
    public function logout()
    {
        auth()->guard('superadmin')->logout();
        session()->flash('msg', 'You have been logged out!');
        return redirect('/superadmin/login');
    }
}
