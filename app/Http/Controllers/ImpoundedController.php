<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\Flag;

class ImpoundedController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $tickets = Ticket::with(['violator','vehicle'])
        ->whereHas('flags', fn($q) =>
            $q->where('key','is_impounded')
        )
        ->orderBy('issued_at','desc')
        ->paginate(5);

        $releasedTickets = Ticket::with(['violator','vehicle','releasedVehicle'])
        ->join('released_vehicles', 'tickets.id', '=', 'released_vehicles.ticket_id')
        ->orderBy('released_vehicles.released_at', 'desc')
        ->select('tickets.*')      // important: select only ticket columns
        ->get();

        return view('admin.impound.impoundedVehicle', compact('tickets', 'releasedTickets'));
    }

    public function resolve(Request $request)
    {
        $data = $request->validate([
            'ticket_id'       => 'required|exists:tickets,id',
            'reference_number'=> 'required|digits:8',
        ]);

        $ticket = Ticket::find($data['ticket_id']);
        if ($ticket->releasedVehicle) {
            return response()->json([
              'status'  => 'error',
              'message' => 'This vehicle has already been released.'
            ], 422);
        }

        // create the release record
        $ticket->releasedVehicle()->create([
          'reference_number'=> $data['reference_number'],
          'released_at'     => now(),
        ]);

        // 2) Detach **only** the impounded pivot
        $impoundedFlagId = Flag::where('key','is_impounded')->value('id');
        $ticket->flags()->detach($impoundedFlagId);

        return response()->json([
          'status'  => 'success',
          'message' => 'Vehicle successfully released!'
        ]);
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
}
