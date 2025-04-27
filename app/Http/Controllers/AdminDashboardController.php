<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\Enforcer;
use App\Models\Violator;

class AdminDashboardController extends Controller
{
    //
    public function adminDash()
    {
        // Summary counts
        $ticketCount    = Ticket::count();
        $enforcerCount  = Enforcer::count();
        $violatorCount  = Violator::count();

        // Recent records
        $recentViolators = Violator::latest()
                                   ->take(5)
                                   ->get(['name','license_number','created_at']);
        $recentTickets   = Ticket::with(['violator','enforcer'])
                                 ->latest()
                                 ->take(5)
                                 ->get();

        return view('admin.index', compact(
            'ticketCount',
            'enforcerCount',
            'violatorCount',
            'recentViolators',
            'recentTickets'
        ));
    }
}
