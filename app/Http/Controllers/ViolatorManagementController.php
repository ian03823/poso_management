<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ViolatorManagementController extends Controller
{
    //
    public function violatorDash(){

        $violator = Auth::guard('violator')->user();

        // eager-load anything you need in the table
        $tickets = $violator
            ->tickets()
            ->with(['vehicle','status','confiscationType','violations'])
            ->orderBy('issued_at','desc')
            ->get();
        $activeNames = ['pending','unpaid'];

            // partition
        $active    = $tickets->filter(fn($t) => in_array(optional($t->status)->name, $activeNames));
        $completed = $tickets->reject(fn($t) => in_array(optional($t->status)->name, $activeNames));

        return view('violator.dashboard', compact('active','completed'));
    }

}
