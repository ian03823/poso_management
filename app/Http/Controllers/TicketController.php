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
    public function index(Request $request)
    {
        return view('enforcer.dashboard');
    }
    public function suggestions(Request $request)
    {
        $term = $request->get('q', '');

        $matches = Violator::with('vehicles')
            ->where('name', 'like', "%{$term}%")
            ->orWhere('license_number', 'like', "%{$term}%")
            ->orWhereHas('vehicles', fn($q) =>
                $q->where('plate_number', 'like', "%{$term}%")
            )
            ->limit(5)
            ->get()
            ->map(fn($v) => [
                'id'             => $v->id,
                'name'           => $v->name,
                'license_number' => $v->license_number,
                'plate_number'   => $v->vehicles->pluck('plate_number')->first() ?? null,
            ]);

        return response()->json($matches);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        $grouped = Violation::orderBy('category')
                        ->orderBy('violation_name')
                        ->get()
                        ->groupBy('category');

        return view('enforcer.issueTicket', [
            'violationGroups' => $grouped,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
             $d = $request->validate([
                 'name'          => 'required|string|max:255',
                 'address'       => 'required|string',
                 'birthdate'     => 'required|date',
                 'phone_number'  => 'required|digits:11',
                 'license_num'   => 'required|string|max:50',
                 'plate_num'     => 'required|string|max:50',
                 'vehicle_type'  => 'required|string',
                 'is_owner'      => 'sometimes|boolean',
                 'owner_name'    => 'required|string',
                 'violations'    => 'required|array|min:1',
                 'location'      => 'required|string',
                 'confiscated'   => 'required|in:none,License ID,Plate Number,ORCR,TCT/TOP',
                 'is_impounded'  => 'sometimes|boolean',
             ]);
         
             $impounded = (bool) ($d['is_impounded'] ?? false);
         
             // 2) Violator (create or fetch)
             $violator = Violator::firstOrNew(
                 ['license_number' => $d['license_num']]
             );
             // always update phone#
             $violator->phone_number = $d['phone_number'];
         
             // on first create, fill name/address/birthdate
             if (! $violator->exists) {
                 $violator->name      = $d['name'];
                 $violator->address   = $d['address'];
                 $violator->birthdate = $d['birthdate'];
             }
             $violator->save();
         
             // 3) Credentials: skip if impounded
             if (! $impounded && ! $violator->username) {
                 $violator->username = 'user'.rand(1000,9999);
                 $rawPwd             = Str::random(8);
                 $violator->password = bcrypt($rawPwd);
                 $violator->save();
                 $creds = ['username'=> $violator->username, 'password'=> $rawPwd];
             } else {
                 $creds = ['username'=> null, 'password'=> "••••••"];
             }
         
             // 4) Vehicle
             $vehicle = Vehicle::firstOrCreate(
                 ['plate_number' => $d['plate_num']],
                 [
                     'violator_id'  => $violator->id,
                     'vehicle_type' => $d['vehicle_type'],
                     'is_owner'     => $d['is_owner'] ?? true,
                     'owner_name'   => $d['owner_name'],
                 ]
             );
         
             // 5) Ticket
             $ticket = Ticket::create([
                 'enforcer_id'     => auth()->guard('enforcer')->id(),
                 'violator_id'     => $violator->id,
                 'vehicle_id'      => $vehicle->id,
                 'violation_codes' => json_encode($d['violations']),
                 'location'        => $d['location'],
                 'issued_at'       => now(),
                 'status'          => 'pending',
                 'offline'         => false,
                 'confiscated'     => $d['confiscated'],
                 'is_impounded'    => $impounded,
             ]);
         
             // 6) Last apprehended before **this** ticket
             $prev = Ticket::where('violator_id',$violator->id)
                           ->where('id','!=',$ticket->id)
                           ->orderBy('issued_at','desc')
                           ->first();
             $lastAt = $prev
                 ? $prev->issued_at->format('d M Y, H:i')
                 : null;
         
             // 7) Build JSON payload
             $violations = Violation::whereIn('violation_code', json_decode($ticket->violation_codes))
                                     ->get()
                                     ->map(fn($v) => [
                                         'name'   => $v->violation_name,
                                         'fine'   => $v->fine_amount,
                                         'points' => $v->penalty_points,
                                     ]);
         
             return response()->json([
                 'ticket'      => [
                     'id'                => $ticket->id,
                     'issued_at'         => $ticket->issued_at->format('d M Y, H:i'),
                     'location'          => $ticket->location,
                     'confiscated'       => $ticket->confiscated,
                     'is_impounded'      => $ticket->is_impounded ? 'Yes' : 'No',
                 ],
                 'last_apprehended_at' => $lastAt,
                 'enforcer'    => [
                     'name'      => auth()->guard('enforcer')->user()->fname
                                    .' '.auth()->guard('enforcer')->user()->lname,
                     'badge_num' => auth()->guard('enforcer')->user()->badge_num,
                 ],
                 'violator'    => [
                     'name'           => $violator->name,
                     'address'        => $violator->address,
                     'birthdate'      => $violator->birthdate,
                     'license_number'=> $violator->license_number,
                     'phone_number'  => $violator->phone_number,
                 ],
                 'vehicle'     => [
                     'plate_number' => $vehicle->plate_number,
                     'vehicle_type' => $vehicle->vehicle_type,
                     'is_owner'     => $vehicle->is_owner ? 'Yes' : 'No',
                     'owner_name'   => $vehicle->owner_name,
                 ],
                 'violations'  => $violations,
                 'credentials' => $creds,
                ]);
         
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $violators = Violator::with([
            'vehicles',
            'tickets' => fn($q) => $q->orderBy('issued_at','desc')
        ])->findOrFail($id);

        return view('enforcer.violatorDetails', compact('violators'));
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
