<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Vehicle;
use App\Models\Violator;
use Illuminate\Http\Request;
use App\Models\Violation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        $violationList = Violation::all();  
        return view('enforcer.issueTicket')->with('violationList', $violationList);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        // Validate the incoming request.
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'address'       => 'required|string',
            'birthdate'     => 'required|date',
            'license_num'   => 'required|string|max:50',
            'plate_num'     => 'required|string|max:50',
            'vehicle_type'  => 'required|string',
            'is_owner'      => 'sometimes|boolean',
            'owner_name'    => 'required|string',
            'violations'    => 'required|array|min:1',
            'location'      => 'required|string',
            'confiscated'   => 'required|in:none,License ID,Plate Number,ORCR,TCT/TOP',
        ]);

        // Create or update the violator record using license number.

        $violator = Violator::firstOrCreate(
            ['license_number' => $data['license_num']],
            [
                'name'      => $data['name'],
                'address'   => $data['address'],
                'birthdate' => $data['birthdate'],
                'license_number' => $data['license_num'],
            ]
        );

        // Optionally, auto-generate login credentials for the violator
        // if they haven't been generated already.
        if (!$violator->username || !$violator->password) {
            $violator->username = 'user' . rand(1000, 9999);
            $rawPassword = Str::random(8);
            $violator->password = bcrypt($rawPassword);
            $violator->save();
        // Now define the credentials variable using the freshly generated values.
            $credentials = [
                'username' => $violator->username,
                'password' => $rawPassword  // This is the plain-text password for printing.
            ];
        } else {
            // Optionally, if the credentials already exist, you may want to indicate that
            $credentials = [
                'username' => $violator->username,
                'password' => 'Existing Password' // Or handle as needed.
            ];
        }

        // Create or update the vehicle record based on the plate number.
        $vehicle = Vehicle::firstOrCreate(
            ['plate_number' => $data['plate_num']],
            [
                'violator_id' => $violator->id,
                'owner_name'  => $data['name'],
                'vehicle_type'  => $data['vehicle_type'],
                'is_owner'     => isset($data['is_owner']) ? $data['is_owner'] : true,
                // Optionally, set vehicle_type if that field is used.
            ]
        );

        // Create the ticket record.
        $ticket = Ticket::create([
            'enforcer_id'     => auth()->guard('enforcer')->user()->id,
            'violator_id'     => $violator->id,
            'vehicle_id'      => $vehicle->id,
            // Store violations as a JSON array.
            'violation_codes' => json_encode($data['violations']),
            'location'        => $data['location'],
            'issued_at'       => now(),
            'status'          => 'pending',
            'offline'         => false,
            'confiscated'     => $data['confiscated'],
        ]);

        // (Optional) Return a view or redirect after successful ticket creation.
        // For example, redirect to a page showing the ticket receipt.
        return view('enforcer.ticketReceipt', [
            'ticket' => $ticket,
            'selectedViolations' => Violation::whereIn('violation_code', json_decode($ticket->violation_codes, true))->get(),
            'credentials' => $credentials 
        ]);
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
