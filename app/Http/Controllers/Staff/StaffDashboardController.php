<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class StaffDashboardController extends Controller
{
    public function index(): View
    {
        return view('staff.dashboard');
    }
}