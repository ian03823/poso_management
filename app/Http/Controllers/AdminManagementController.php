<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminManagementController extends Controller
{
    //
    public function adminDash()
    {
        return view('admin.index');
    }
}
