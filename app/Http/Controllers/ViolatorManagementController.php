<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ViolatorManagementController extends Controller
{
    //
    public function violatorDash(){
        return view('violator.dashboard');
    }
}
