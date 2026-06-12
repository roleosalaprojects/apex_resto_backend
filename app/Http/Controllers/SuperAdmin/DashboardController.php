<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth:superadmin');
    }
    public function index()
    {
        return view('superadmin.dashboard');
    }
}
