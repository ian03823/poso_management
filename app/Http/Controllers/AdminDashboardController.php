<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Ticket;
use App\Models\Enforcer;
use App\Models\Violator;
use App\Models\PaidTicket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminDashboardController extends Controller
{
    //
    public function adminDash()
    {
                // Summary counts
            // today in your app’s timezone
        // Use app timezone and a half-open window [today, tomorrow)
        $start = Carbon::today();
        $end   = Carbon::tomorrow();

        // Summary (TODAY ONLY)
        $ticketCount   = Ticket::whereBetween('issued_at', [$start, $end])->count();
        $enforcerCount = Enforcer::whereBetween('created_at', [$start, $end])->count();
        $violatorCount = Violator::whereBetween('created_at', [$start, $end])->count();

        // Lists (TODAY ONLY)
        $recentViolators = Violator::whereBetween('created_at', [$start, $end])
                                   ->orderByDesc('created_at')
                                   ->limit(5)->get();

        $recentTickets   = Ticket::with(['violator','enforcer'])
                                 ->whereBetween('issued_at', [$start, $end])
                                 ->orderByDesc('issued_at')
                                 ->limit(5)->get();

        return view('admin.index', compact(
            'ticketCount',
            'enforcerCount',
            'violatorCount',
            'recentViolators',
            'recentTickets'
        ));
    }
     /**
     * Show the form for editing the authenticated admin.
     */ 
    public function edit()
    {
        $admin = Auth::guard('admin')->user();
        return view('admin.profile.form', compact('admin'));
    }
     /**
     * Update the admin's profile.
     */
    public function update(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'unique:admins,username,'.$admin->id],
            'password' => ['nullable','string','min:8','confirmed'],
        ]);

        $admin->name     = $data['name'];
        $admin->username = $data['username'];

        if (! empty($data['password'])) {
            $admin->password = Hash::make($data['password']);
        }

        $admin->save();

        return redirect()
            ->route('admin.profile.edit')
            ->with('success','Profile updated successfully.');
    }

    public function version()
    {
       $start = Carbon::today();
        $end   = Carbon::tomorrow();

        // “What changed today?” -> any of these changing triggers a refresh
        $maxTicketUpdated   = optional(
            Ticket::whereBetween('updated_at', [$start, $end])->max('updated_at')
        )?->timestamp ?? 0;

        $maxPaidUpdated     = optional(
            PaidTicket::whereBetween('updated_at', [$start, $end])->max('updated_at')
        )?->timestamp ?? 0;

        $maxViolatorUpdated = optional(
            Violator::whereBetween('updated_at', [$start, $end])->max('updated_at')
        )?->timestamp ?? 0;

        $ticketCountToday   = Ticket::whereBetween('issued_at', [$start, $end])->count();
        $violatorCountToday = Violator::whereBetween('created_at', [$start, $end])->count();

        $maxTicketIdToday   = Ticket::whereBetween('issued_at', [$start, $end])->max('id') ?? 0;
        $maxViolatorIdToday = Violator::whereBetween('created_at', [$start, $end])->max('id') ?? 0;

        $token = implode('|', [
            $start->toDateString(),                    // makes token reset each day
            $maxTicketUpdated, $maxPaidUpdated, $maxViolatorUpdated,
            $ticketCountToday, $violatorCountToday,
            $maxTicketIdToday, $maxViolatorIdToday,
        ]);

        $hash = substr(hash('xxh3', 'admin-dashboard-today:'.$token), 0, 16);
        return response()->json(['v' => $hash]);
    }

public function summaryPartial()
{
    $start = \Carbon\Carbon::today();
        $end   = \Carbon\Carbon::tomorrow();

        $ticketCount   = Ticket::whereBetween('issued_at', [$start, $end])->count();
        $violatorCount = Violator::whereBetween('created_at', [$start, $end])->count();

        return view('admin.partials.dashboardSummary', compact('ticketCount','violatorCount'));
}

public function recentViolatorsPartial()
    {
        $start = \Carbon\Carbon::today();
        $end   = \Carbon\Carbon::tomorrow();

        $recentViolators = Violator::whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')->limit(5)->get();

        return view('admin.partials.recentViolators', compact('recentViolators'));
    }

    public function recentTicketsPartial()
    {
        $start = \Carbon\Carbon::today();
        $end   = \Carbon\Carbon::tomorrow();

        $recentTickets = Ticket::with(['violator','enforcer'])
            ->whereBetween('issued_at', [$start, $end])
            ->orderByDesc('issued_at')->limit(5)->get();

        return view('admin.partials.recentTickets', compact('recentTickets'));
    }
}
