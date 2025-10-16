<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Violator;
use App\Models\Ticket;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use App\Models\TicketStatus;
use App\Models\PaidTicket;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class ViolatorTableController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        
        // grab the UI inputs (with sensible defaults)
        $sortOption   = $request->get('sort_option','date_desc');
        $search       = $request->get('search','');
        $vehicleType  = $request->get('vehicle_type','all');

        // 1) find each violator’s latest ticket id
        $latestTicketIds = Ticket::groupBy('violator_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        // 2) build the base Ticket query
        $query = Ticket::with(['violator','vehicle','violations' => fn($q) => $q->withTrashed()])
    ->select('tickets.*') // important when we join for sorting
    ->whereIn('tickets.id', $latestTicketIds)
    // SEARCH (grouped): full name, license, or plate
    ->when($search, function ($q) use ($search) {
        $term = '%'.$search.'%';
        $q->where(function ($qq) use ($term) {
            $qq->whereHas('violator', function ($v) use ($term) {
                    $v->where(function($w) use ($term) {
                        // match "First Middle Last" OR "Last First Middle"
                        $w->where(DB::raw("CONCAT_WS(' ', first_name, middle_name, last_name)"), 'like', $term)
                          ->orWhere(DB::raw("CONCAT_WS(' ', last_name, first_name, middle_name)"), 'like', $term)
                          ->orWhere('first_name',  'like', $term)
                          ->orWhere('middle_name', 'like', $term)
                          ->orWhere('last_name',   'like', $term)
                          ->orWhere('license_number', 'like', $term);
                    });
                })
               ->orWhereHas('vehicle', function ($v) use ($term) {
                    $v->where('plate_number', 'like', $term);
                });
        });
    });

// 3) sorting
match ($sortOption) {
    'date_asc'  => $query->orderBy('issued_at','asc'),
    'name_asc'  => $query->leftJoin('violators','tickets.violator_id','=','violators.id')
                         ->orderBy('violators.last_name','asc')
                         ->orderBy('violators.first_name','asc')
                         ->orderBy('violators.middle_name','asc'),
    'name_desc' => $query->leftJoin('violators','tickets.violator_id','=','violators.id')
                         ->orderBy('violators.last_name','desc')
                         ->orderBy('violators.first_name','desc')
                         ->orderBy('violators.middle_name','desc'),
    default     => $query->orderBy('issued_at','desc'),
};

        // 4) paginate & carry filters in links
        $tickets = $query
            ->distinct('tickets.id')
            ->paginate(5)
            ->appends([
                'sort_option'  => $sortOption,
                'search'       => $search,
                'vehicle_type' => $vehicleType,
            ]);

        // 5) for the vehicle-type dropdown
        $vehicleTypes = Vehicle::distinct()->pluck('vehicle_type');

        return view(
            'admin.violator.violatorTable',
            compact('tickets','sortOption','search','vehicleType','vehicleTypes')
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $violator = Violator::with([
            'vehicles',
            'tickets.status',
            'tickets.violations' => fn($q) => $q->withTrashed(),
            'tickets.vehicle',
        ])->findOrFail($id);

        // sort tickets newest → oldest
        $violator->tickets = $violator->tickets
                                      ->sortByDesc('issued_at')
                                      ->values();

        return view('admin.partials.violatorView', compact('violator'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    public function partial(Request $request)
    {
        $sortOption   = $request->get('sort_option','date_desc');
        $search       = $request->get('search','');
        $vehicleType  = $request->get('vehicle_type','all');

        // 1) find each violator’s latest ticket id
        $latestTicketIds = Ticket::groupBy('violator_id')
    ->selectRaw('MAX(id) as id')
    ->pluck('id');

// 2) base
$query = Ticket::with(['violator','vehicle','violations' => fn($q) => $q->withTrashed()])
    ->select('tickets.*') // important when we join for sorting
    ->whereIn('tickets.id', $latestTicketIds)
    // SEARCH (grouped): full name, license, or plate
    ->when($search, function ($q) use ($search) {
        $term = '%'.$search.'%';
        $q->where(function ($qq) use ($term) {
            $qq->whereHas('violator', function ($v) use ($term) {
                    $v->where(function($w) use ($term) {
                        // match "First Middle Last" OR "Last First Middle"
                        $w->where(DB::raw("CONCAT_WS(' ', first_name, middle_name, last_name)"), 'like', $term)
                          ->orWhere(DB::raw("CONCAT_WS(' ', last_name, first_name, middle_name)"), 'like', $term)
                          ->orWhere('first_name',  'like', $term)
                          ->orWhere('middle_name', 'like', $term)
                          ->orWhere('last_name',   'like', $term)
                          ->orWhere('license_number', 'like', $term);
                    });
                })
               ->orWhereHas('vehicle', function ($v) use ($term) {
                    $v->where('plate_number', 'like', $term);
                });
        });
    });

// 3) sorting
match ($sortOption) {
    'date_asc'  => $query->orderBy('issued_at','asc'),
    'name_asc'  => $query->leftJoin('violators','tickets.violator_id','=','violators.id')
                         ->orderBy('violators.last_name','asc')
                         ->orderBy('violators.first_name','asc')
                         ->orderBy('violators.middle_name','asc'),
    'name_desc' => $query->leftJoin('violators','tickets.violator_id','=','violators.id')
                         ->orderBy('violators.last_name','desc')
                         ->orderBy('violators.first_name','desc')
                         ->orderBy('violators.middle_name','desc'),
    default     => $query->orderBy('issued_at','desc'),
};
        // 4) paginate & carry filters in links
        $tickets = $query
            ->distinct('tickets.id')
            ->paginate(5)
            ->appends([
                'sort_option'  => $sortOption,
                'search'       => $search,
                'vehicle_type' => $vehicleType,
            ]);

        // 5) for the vehicle-type dropdown
        $vehicleTypes = Vehicle::distinct()->pluck('vehicle_type');

        return view('admin.partials.violatorTable', compact('tickets'));
    }
    public function updateStatus(Request $request, Ticket $ticket)
    {
        Log::info('updateStatus', ['ticket' => $ticket->id, 'request' => $request->all()]);

        $paidId      = TicketStatus::where('name', 'paid')->value('id');
        $pendingId   = TicketStatus::where('name', 'pending')->value('id');
        $unpaidId    = TicketStatus::where('name', 'unpaid')->value('id');
        $cancelledId = TicketStatus::where('name', 'cancelled')->value('id');

        $oldStatus = (int) $ticket->status_id;

        // Frontend sends either:
        // - status_id = 'LEAVING_PAID' (marker) + reference_number (exactly 8 chars)
        // - status_id = <target> + admin_password (for pending -> unpaid/cancelled)
        // - status_id = <target> (straight change for other transitions)

        $incoming = $request->input('status_id');

        // Rule 1: FROM PAID -> (Pending/Unpaid/Cancelled) => require reference_number (8 chars)
        if ($oldStatus === $paidId && $incoming === 'LEAVING_PAID') {
            $data = $request->validate([
                'reference_number' => ['required','string','size:8'],
                'new_status'       => ['nullable','integer', 'in:'.$pendingId.','.$unpaidId.','.$cancelledId],
            ]);

            // default to Pending if not provided explicitly
            $newStatus = (int) ($data['new_status'] ?? $pendingId);

            // delete only the MOST RECENT payment record
            $latestPayment = PaidTicket::where('ticket_id', $ticket->id)
                ->orderBy('paid_at','desc')
                ->first();
            if ($latestPayment) {
                $latestPayment->delete();
                Log::info('PaidTicket deleted on leave-paid', [
                    'ticket_id'   => $ticket->id,
                    'ref_entered' => $data['reference_number'],
                    'deleted_id'  => $latestPayment->id,
                ]);
            }

            $ticket->status_id = $newStatus;
            $ticket->save();

            return response()->json(['message' => 'Status updated (left Paid).']);
        }

        // Rule 2: FROM PENDING -> (Unpaid or Cancelled) => require admin_password
        if ($oldStatus === $pendingId && in_array((int)$incoming, [$unpaidId, $cancelledId], true)) {
            $request->validate(['admin_password' => 'required|string']);

            $admin = Auth::guard('admin')->user();
            if (!$admin || !Hash::check($request->admin_password, $admin->password)) {
                return response()->json(['message' => 'Incorrect password'], 403);
            }

            $ticket->status_id = (int) $incoming;
            $ticket->save();

            return response()->json(['message' => 'Status updated.']);
        }

        // All other transitions (including Pending->Paid, Unpaid->Pending, etc.)
        // NOTE: If you later want to re-impose reference # for setting to Paid, do it here.
        $newStatus = (int) $incoming;
        $ticket->status_id = $newStatus;
        $ticket->save();

        return response()->json(['message' => 'Status updated.']);
    }

}
