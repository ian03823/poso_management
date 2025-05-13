<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Vehicle;
use App\Models\Violator;
use App\Models\Enforcer;
use Illuminate\Http\Request;
use App\Models\Violation;
use App\Models\TicketStatus;
use App\Models\ConfiscationType;
use App\Models\Flag;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
    public function create(Request $request)
    {
        //
        $grouped = Violation::orderBy('category')
                        ->orderBy('violation_name')
                        ->get()
                        ->groupBy('category');
        
        $violator = null;   
        if ($request->filled('violator_id')) {
            $violator = Violator::with('vehicles')
            ->find($request->input('violator_id'));
        }              
        return view('enforcer.issueTicket', [
            'violationGroups' => $grouped,
            'violator'        => $violator,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         $d = $request->validate([
            'name'          => 'nullable|string|max:255',
            'address'       => 'nullable|string|min:2',
            'birthdate'     => 'nullable|date',
            'license_num'   => 'nullable|string|max:50|min:8',
            'plate_num'     => 'required|string|max:50|min:5',
            'vehicle_type'  => 'required|string',
            'is_owner'      => 'sometimes|boolean',
            'is_resident'   => 'sometimes|boolean',
            'owner_name'    => 'nullable|',
            'violations'    => 'required|array|min:1',
            'location'      => 'nullable|string',
            'confiscation_type_id'   => 'nullable|exists:confiscation_types,id',
            'is_impounded'  => 'sometimes|boolean',
        ]);

        $resident  = ! empty($d['is_resident']);
        $impounded = ! empty($d['is_impounded']);

        $licenseExists = Violator::where('license_number', $d['license_num'])->exists();
        $plateExists   = Vehicle::where('plate_number',    $d['plate_num'])->exists();

        if ($licenseExists) {
            if ($request->expectsJson()) {
                return response()
                    ->json(['message' => 'The license number already exists.'], 422);
            }
            return back()
                ->withInput()
                ->with('duplicate_error', 'The license number already exists.');
        }
        
        if ($plateExists) {
            if ($request->expectsJson()) {
                return response()
                    ->json(['message' => 'The plate number already exists.'], 422);
            }
            return back()
                ->withInput()
                ->with('duplicate_error', 'The plate number already exists.');
        }
        // 2) Violator (firstOrNew then save)

        $violator = Violator::firstOrNew(
            ['license_number' => $d['license_num']]
        );
        if (! $violator->exists) {
            $violator->name      = $d['name'];
            $violator->address   = $d['address'];
            $violator->birthdate = $d['birthdate'];
        }
        $violator->save();

        // possibly create credentials
        if ($violator->wasRecentlyCreated) {
            $violator->username = 'user'.rand(1000,9999);
        
            // generate an 8-char password
            $rawPwd = Str::random(8);
        
            // set the default password
            $violator->defaultPassword = Hash::make($rawPwd);
            
            $violator->save();
        
            // return the plain text so you can e.g. email it
            $creds = [
              'username' => $violator->username,
              'password' => $rawPwd,
            ];
        } else {
            $creds = ['username'=>'Existing','password'=>'Existing'];
        }

        // 3) Vehicle
        $vehicle = Vehicle::firstOrCreate(
            ['plate_number' => $d['plate_num']],
            [
                'violator_id'  => $violator->id,
                'vehicle_type' => $d['vehicle_type'],
                'is_owner'     => $d['is_owner'] ?? true,
                'owner_name'   => $d['owner_name'],
            ]
        );

        $ticket = DB::transaction(function() use($d, $violator, $vehicle) {
            // a) Lock the enforcer row
            $enf = Enforcer::lockForUpdate()->find(auth()->guard('enforcer')->id());
    
            // b) Find the highest ticket_number they’ve already used
            $last = Ticket::where('enforcer_id', $enf->id)
                          ->lockForUpdate()
                          ->max('ticket_number');
    
            // c) Compute the next one
            if ($last !== null) {
                $next = $last + 1;
            } else {
                $next = $enf->ticket_start;
            }
    
            // d) Enforce their range
            if ($next > $enf->ticket_end) {
                throw ValidationException::withMessages([
                    'ticket_number' => "You’ve hit your allocated range ({$enf->ticket_start}–{$enf->ticket_end})."
                ]);
            }
            // 4) Ticket – note: no more violation_codes JSON
            return Ticket::create([
                'enforcer_id'            => $enf->id,
                'ticket_number'          => $next,
                'violator_id'            => $violator->id,
                'vehicle_id'             => $vehicle->vehicle_id,
                'violation_codes'        => json_encode($d['violations']),
                'location'               => $d['location'],
                'issued_at'              => now(),
                'offline'                => false,
                'status_id'              => TicketStatus::where('name','pending')->value('id'),
                'confiscation_type_id'   => $d['confiscation_type_id'],
            ]);
        });

        // 5) Attach violations via pivot
        // assumes your Ticket model has:
        // public function violations() { return $this->belongsToMany(Violation::class,'ticket_violation','ticket_id','violation_code','id','violation_code'); }
        $violationIds = Violation::whereIn('violation_code', $d['violations'])
                         ->pluck('id')
                         ->all();

        $ticket->violations()->sync($violationIds);

        // 6) Sync boolean flags (resident, impounded)
        $flagKeys = [];
        if ($resident)  $flagKeys[] = 'is_resident';
        if ($impounded) $flagKeys[] = 'is_impounded';

        $flagIds = Flag::whereIn('key',$flagKeys)->pluck('id')->all();
        $ticket->flags()->sync($flagIds);

        // 7) Last apprehended before this ticket
        $prev = Ticket::where('violator_id',$violator->id)
                      ->where('id','!=',$ticket->id)
                      ->orderBy('issued_at','desc')
                      ->first();
        $lastAt = $prev
            ? $prev->issued_at->format('d M Y, H:i')
            : null;

        // 8) Build JSON payload (using relationships)
        $violations = Violation::whereIn('violation_code', json_decode($ticket->violation_codes))
                                     ->get()
                                     ->map(fn($v) => [
                                         'name'   => $v->violation_name,
                                         'fine'   => $v->fine_amount,
                                     ]);

        return response()->json([
            'ticket' => [
                'id'                  => $ticket->id,
                'ticket_number'       => $ticket->ticket_number,
                'issued_at'           => $ticket->issued_at->format('d M Y, H:i'),
                'location'            => $ticket->location,
                'status'              =>  optional($ticket->status)->name      ?? 'Unknown',
                'confiscated'         =>  optional($ticket->confiscationType)->name ?? 'None',
                'is_impounded'        => $ticket->is_impounded ? 'Yes' : 'No',
                'is_resident'         => $ticket->is_resident  ? 'Yes' : 'No',
            ],
            'last_apprehended_at' => $lastAt,
            'enforcer' => [
                'name'      => auth()->guard('enforcer')->user()->fname
                                .' '.auth()->guard('enforcer')->user()->lname,
                'badge_num' => auth()->guard('enforcer')->user()->badge_num,
            ],
            'violator' => [
                'name'           => $violator->name,
                'address'        => $violator->address,
                'birthdate'      => $violator->birthdate,
                'license_number'=> $violator->license_number,
            ],
            'vehicle' => [
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
