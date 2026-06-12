<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class UserDashboardController extends Controller
{
    public function calendar()
    {
        return view('admin.dashboards.calendar');
    }
}
