<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Models\TicketStatus;
use App\Models\Vehicle;
use App\Models\Violation;
use App\Models\Violator; 
use App\Models\PaidTicket;
use App\Models\Enforcer;
class AdminTicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $sortOption = $request->get('sort_option','date_desc');
        switch($sortOption) {
            case 'date_asc':
                $col = 'issued_at'; $dir = 'asc';  break;
            case 'name_asc':
                // sort by violator’s name (assumes violator has `name` accessor)
                $col = 'violator_name'; $dir = 'asc'; break;
            case 'name_desc':
                $col = 'violator_name'; $dir = 'desc'; break;
            case 'date_desc':
            default:
                $col = 'issued_at'; $dir = 'desc'; break;
        }
        
        $tickets = Ticket::with(['enforcer', 'violator', 'vehicle', 'status'])
    ->whereHas('enforcer') // only get tickets with enforcer
    ->orderBy($col, $dir)
    ->paginate(5)
    ->appends('sort_option', $sortOption);

        
        // Render the main blade that @includes your partial
        return view('admin.issuedTicket.ticketTable', compact('tickets', 'sortOption'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $grouped = Violation::orderBy('category')
        ->orderBy('violation_name')
        ->get()
        ->groupBy('category');

        $violator = null;
        if ($request->filled('violator_id')) {
        $violator = Violator::with('vehicles')
        ->find($request->input('violator_id'));
        }              
        return view('admin.issueTicket.createTicket', [
        'violationGroups' => $grouped,
        'violator'        => $violator,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $d = $request->validate([
            'name'           => 'required|string|max:255',
            'address'        => 'required|string',
            'birthdate'      => 'required|date',
            'license_num'    => 'required|string|max:50',
            'plate_num'      => 'required|string|max:50',
            'vehicle_type'   => 'required|string',
            'is_owner'       => 'sometimes|boolean',
            'is_resident'    => 'sometimes|boolean',
            'owner_name'     => 'nullable|string|max:255',
            'violations'     => 'required|array|min:1',
            'location'       => 'required|string',
            'confiscated'    => 'required|in:none,License ID,Plate Number,ORCR,TCT/TOP',
            'is_impounded'   => 'sometimes|boolean',
        ]);

        $impounded = (bool) ($d['is_impounded'] ?? false);
        $resident  = (bool) ($d['is_resident']  ?? false);

        // 1) Violator
        $violator = Violator::firstOrNew(
            ['license_number' => $d['license_num']]
        );
        if (! $violator->exists) {
            $violator->name      = $d['name'];
            $violator->address   = $d['address'];
            $violator->birthdate = $d['birthdate'];
        }
        $violator->save();

        // 2) Vehicle
        $vehicle = Vehicle::firstOrCreate(
            ['plate_number' => $d['plate_num']],
            [
                'violator_id'  => $violator->id,
                'vehicle_type' => $d['vehicle_type'],
                'is_owner'     => $d['is_owner'] ?? true,
                'owner_name'   => $d['owner_name'],
            ]
        );

        // 3) Ticket
        $ticket = Ticket::create([
            'enforcer_id'     => Auth::guard('admin')->id(),
            'violator_id'     => $violator->id,
            'vehicle_id'      => $vehicle->id,
            'violation_codes' => json_encode($d['violations']),
            'location'        => $d['location'],
            'issued_at'       => now(),
            'status'          => 'pending',
            'offline'         => false,
            'confiscated'     => $d['confiscated'],
            'is_impounded'    => $impounded,
            'is_resident'     => $resident,
        ]);

        // 4) Last apprehended before this
        $prev = Ticket::where('violator_id',$violator->id)
                      ->where('id','!=',$ticket->id)
                      ->orderBy('issued_at','desc')
                      ->first();
        $lastAt = $prev
            ? $prev->issued_at->format('d M Y, H:i')
            : null;

        // 5) Build violations payload
        $violations = Violation::whereIn('violation_code', json_decode($ticket->violation_codes))
                                ->get()
                                ->map(fn($v) => [
                                    'name' => $v->violation_name,
                                    'fine' => number_format($v->fine_amount,2),
                                ]);

        // 6) Return JSON for SweetAlert + print
        return response()->json([
            'ticket' => [
                'id'             => $ticket->id,
                'issued_at'      => $ticket->issued_at->format('d M Y, H:i'),
                'location'       => $ticket->location,
                'confiscated'    => $ticket->confiscated,
                'is_impounded'   => $ticket->is_impounded ? 'Yes' : 'No',
                'is_resident'    => $ticket->is_resident,
            ],
            'last_apprehended_at' => $lastAt,
            'enforcer' => [
                'name'      => Auth::guard('admin')->user()->fname
                               .' '.Auth::guard('admin')->user()->lname,
                'badge_num' => Auth::guard('admin')->user()->badge_num,
            ],
            'violator' => [
                'name'            => $violator->name,
                'address'         => $violator->address,
                'birthdate'       => $violator->birthdate,
                'license_number'  => $violator->license_number,
            ],
            'vehicle' => [
                'plate_number' => $vehicle->plate_number,
                'vehicle_type' => $vehicle->vehicle_type,
                'is_owner'     => $vehicle->is_owner ? 'Yes' : 'No',
                'owner_name'   => $vehicle->owner_name,
            ],
            'violations'  => $violations,
            'credentials' => $creds ?? ['username'=>'Existing','password'=>'Existing'],
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
    public function update(Request $request, Ticket $ticket)
    {
        //
        $data = $request->validate([
            'status' => 'required|in:pending,paid,unpaid,cancelled',
        ]);
        $ticket->status = $data['status'];
        $ticket->save();
        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('ticket.index')->with('success','Status updated');
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
        $sortOption = $request->get('sort_option','date_desc');
        switch($sortOption) {
            case 'date_asc':
                $col = 'issued_at'; $dir = 'asc';  break;
            case 'name_asc':
                $col = 'violator_name'; $dir = 'asc'; break;
            case 'name_desc':
                $col = 'violator_name'; $dir = 'desc'; break;
            default:
                $col = 'issued_at'; $dir = 'desc'; break;
        }

        $tickets = Ticket::with(['enforcer','violator','vehicle'])
                         ->select('tickets.*')
                         ->leftJoin('violators','tickets.violator_id','violators.id')
                         ->orderBy($col,$dir)
                         ->paginate(5)
                         ->appends('sort_option',$sortOption);


        // Render the partial view
        return view('admin.partials.ticketTable', compact('tickets', 'sortOption'));
    }
    public function updateStatus(Request $request, Ticket $ticket)
    {
        Log::info('AdminTicket updateStatus', [
            'ticket_id' => $ticket->id,
            'request'   => $request->all(),
        ]);

        $newStatus = (int)$request->input('status_id');
        $paidId    = TicketStatus::where('name', 'paid')->value('id');
        $oldStatus = $ticket->status_id;

        //
        // 1) Going *away* from Paid → require password & delete latest PaidTicket
        //
        if ($oldStatus === $paidId && $newStatus !== $paidId) {
            $request->validate([
                'admin_password' => 'required|string',
            ]);

            $admin = Auth::guard('admin')->user();
            if (! Hash::check($request->admin_password, $admin->password)) {
                return response()->json(['message'=>'Incorrect password'], 403);
            }

            // delete only the most-recent payment record
            $latest = PaidTicket::where('ticket_id', $ticket->id)
                                ->orderBy('paid_at','desc')
                                ->first();
            if ($latest) {
                $latest->delete();
                Log::info('Deleted PaidTicket', ['id'=>$latest->id]);
            }
        }

        //
        // 2) Going *into* Paid → require ref# & create PaidTicket
        //
        if ($newStatus === $paidId) {
            $request->validate([
                'reference_number' => 'required|string|unique:paid_tickets,reference_number',
            ]);

            $payment = PaidTicket::create([
                'ticket_id'        => $ticket->id,
                'reference_number' => $request->reference_number,
                'paid_at'          => now(),
            ]);
            Log::info('Created PaidTicket', ['id'=>$payment->id]);
        }

        //
        // 3) Finally update the Ticket status
        //
        $ticket->status_id = $newStatus;
        $ticket->save();

        return response()->json([
            'message' => $newStatus === $paidId
               ? 'Ticket marked as Paid.'
               : 'Status updated successfully.'
        ]);
    }
}
