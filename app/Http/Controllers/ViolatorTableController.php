<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Violator;
use App\Models\Ticket;
use App\Models\Vehicle;
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
        $query = Ticket::with(['violator','vehicle'])
            ->whereIn('id',$latestTicketIds)
            // filter by vehicle_type if requested
            ->when($vehicleType!=='all', fn($q) => $q
                ->whereHas('vehicle', fn($q2) =>
                    $q2->where('vehicle_type',$vehicleType)
                )
            )
            // search name/license/plate
            ->when($search, fn($q) => $q
                ->whereHas('violator', fn($q2) => $q2
                    ->where('name','like',"%{$search}%")
                    ->orWhere('license_number','like',"%{$search}%")
                )
                ->orWhereHas('vehicle', fn($q2) =>
                    $q2->where('plate_number','like',"%{$search}%")
                )
            );

        // 3) apply sorting
        match($sortOption) {
            'date_asc'   => $query->orderBy('issued_at','asc'),
            'name_asc'   => $query->join('violators','tickets.violator_id','violators.id')
                                  ->orderBy('violators.name','asc'),
            'name_desc'  => $query->join('violators','tickets.violator_id','violators.id')
                                  ->orderBy('violators.name','desc'),
            default      => $query->orderBy('issued_at','desc'),
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
            'tickets.violations',
            'tickets.status'
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

        // 2) build the base Ticket query
        $query = Ticket::with(['violator','vehicle'])
            ->whereIn('id',$latestTicketIds)
            // filter by vehicle_type if requested
            ->when($vehicleType!=='all', fn($q) => $q
                ->whereHas('vehicle', fn($q2) =>
                    $q2->where('vehicle_type',$vehicleType)
                )
            )
            // search name/license/plate
            ->when($search, fn($q) => $q
                ->whereHas('violator', fn($q2) => $q2
                    ->where('name','like',"%{$search}%")
                    ->orWhere('license_number','like',"%{$search}%")
                )
                ->orWhereHas('vehicle', fn($q2) =>
                    $q2->where('plate_number','like',"%{$search}%")
                )
            );

        // 3) apply sorting
        match($sortOption) {
            'date_asc'   => $query->orderBy('issued_at','asc'),
            'name_asc'   => $query->join('violators','tickets.violator_id','violators.id')
                                  ->orderBy('violators.name','asc'),
            'name_desc'  => $query->join('violators','tickets.violator_id','violators.id')
                                  ->orderBy('violators.name','desc'),
            default      => $query->orderBy('issued_at','desc'),
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

        Log::info('updateStatus', [
            'ticket'  => $ticket->id,
            'request' => $request->all(),
        ]);

        $newStatus = $request->input('status_id');
        $paidId    = TicketStatus::where('name','paid')->value('id');
        $oldStatus = $ticket->status_id;

        // 1) If changing *from* Paid → something else, require password
        if ($oldStatus === $paidId && $newStatus !== $paidId) {
            $request->validate([
                'admin_password' => 'required|string',
            ]);

             $admin = Auth::guard('admin')->user(); // ensure this is your admin guard
            if (! Hash::check($request->admin_password, $admin->password)) {
                return response()->json(
                ['message'=>'Incorrect password'],
                403
                );
            }

             $latestPayment = PaidTicket::where('ticket_id', $ticket->id)
                                   ->orderBy('paid_at', 'desc')
                                   ->first();
             if ($latestPayment) {
                $latestPayment->delete();
                Log::info('Deleted single PaidTicket', ['id' => $latestPayment->id]);
            }
        }

        if ($newStatus == $paidId) {
            $request->validate([
                'reference_number' => 'required|string|unique:paid_tickets,reference_number',
            ]);

            $payment = PaidTicket::create([
                'ticket_id'        => $ticket->id,
                'reference_number' => $request->reference_number,
                'paid_at'          => now(),
            ]);

            Log::info('PaidTicket created', ['id' => $payment->id]);
        }

        $ticket->status_id = $newStatus;
        $ticket->save();

        return response()->json([
            'message' => $newStatus == $paidId
               ? 'Ticket marked as Paid.'
               : 'Status updated successfully.'
        ]);
    }
}
