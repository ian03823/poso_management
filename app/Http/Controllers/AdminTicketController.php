<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\AdminIssueTicketRequest;
use App\Services\LogActivity;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
            case 'date_asc':  $col='issued_at'; $dir='asc';  break;
            default:          $col='issued_at'; $dir='desc'; break;
        }

         $tickets = Ticket::query()
        ->with([
            'violator',
            'vehicle',
            'status',
            'enforcer'  => fn($q) => $q->withTrashed(),   // already added earlier
            'violations'=> fn($q) => $q->withTrashed(),
        ])
        ->orderBy($col,$dir)
        ->paginate(5)
        ->appends('sort_option',$sortOption);

        // Distinct categories for the modal
        $violationCategories = Violation::query()
            ->select('category')->whereNotNull('category')->distinct()
            ->pluck('category')->filter()->values();

        return view('admin.issuedTicket.ticketTable', compact('tickets','sortOption','violationCategories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $grouped = \App\Models\Violation::orderBy('category')
            ->orderBy('violation_name')
            ->get()
            ->groupBy('category')
            ->mapWithKeys(function ($items, $key) {
                // ensure string keys (handles enums/backed enums cleanly)
                $k = is_object($key) ? (string)$key->value ?? (string)$key : (string)$key;
                return [$k => $items->map(function($v){
                    return [
                        'id'             => $v->id,
                        'violation_code' => $v->violation_code,
                        'violation_name' => $v->violation_name,
                        'fine_amount'    => $v->fine_amount,
                    ];
                })->values()];
            });

        $violator = null;
        if ($request->filled('violator_id')) {
            $violator = \App\Models\Violator::with('vehicles')->find($request->input('violator_id'));
        }

        // Let admin choose which Enforcer’s range to consume
        $enforcers = \App\Models\Enforcer::orderBy('lname')->orderBy('fname')->get();

        return view('admin.issueTicket.createTicket', [
            'violationGroups' => $grouped,
            'violator'        => $violator,
            'enforcers'       => $enforcers,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AdminIssueTicketRequest $request)
    {
        //
        $d = $request->validated();

        // Violator (same logic as Enforcer)
        $violator = \App\Models\Violator::firstOrNew(['license_number' => $d['license_num']]);
        if (! $violator->exists) {
            $violator->first_name  = $d['first_name'];
            $violator->middle_name = $d['middle_name'];
            $violator->last_name   = $d['last_name'];
            $violator->address     = $d['address'];
            $violator->birthdate   = $d['birthdate'];
        }
        $violator->save();
        /** @var \App\Http\Requests\AdminIssueTicketRequest|\Illuminate\Http\Request $request */
        // Prevent plate number bound to a different violator
        $existingPlate = \App\Models\Vehicle::where('plate_number', $d['plate_num'])->first();
        if ($existingPlate && $existingPlate->violator_id !== $violator->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The plate number is already registered under another violator.',
                ], 422);
            }
            return back()->withInput()
                ->with('duplicate_error', 'The plate number is already registered under another violator.');
        }

        // Auto-provision violator credentials if newly created (same as Enforcer)
        if ($violator->wasRecentlyCreated) {
            $violator->username = 'user'.rand(1000,9999);
            $rawPwd             = 'violator'.rand(1000,9999); // default
            $violator->defaultPassword = Hash::make($rawPwd);
            $violator->save();
            $creds = ['username'=>$violator->username, 'password'=>$rawPwd];
        } else {
            $creds = ['username'=>'Existing','password'=>'Existing'];
        }

        // Vehicle (match your PK naming; Enforcer code uses vehicle_id)
        $vehicle = \App\Models\Vehicle::firstOrCreate(
            ['plate_number' => $d['plate_num']],
            [
                'violator_id'  => $violator->id,
                'vehicle_type' => $d['vehicle_type'],
                'is_owner'     => $d['is_owner'] ?? true,
                'owner_name'   => $d['owner_name'],
            ]
        );

        // Transaction to consume the chosen Enforcer’s ticket range (exactly like Enforcer flow)
        $ticket = \Illuminate\Support\Facades\DB::transaction(function() use ($d, $violator, $vehicle) {
            $enf = \App\Models\Enforcer::lockForUpdate()->find($d['apprehending_enforcer_id']);

            $last = \App\Models\Ticket::where('enforcer_id', $enf->id)
                        ->lockForUpdate()
                        ->max('ticket_number');

            $next = $last !== null ? $last + 1 : $enf->ticket_start;

            if ($next > $enf->ticket_end) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'ticket_number' => "Selected enforcer's range exhausted ({$enf->ticket_start}–{$enf->ticket_end})."
                ]);
            }

            $t = \App\Models\Ticket::create([
                'enforcer_id'          => $enf->id,
                'ticket_number'        => $next,
                'violator_id'          => $violator->id,
                'vehicle_id'           => $vehicle->vehicle_id ?? $vehicle->id, // keep compatibility
                'violation_codes'      => json_encode($d['violations']),
                'location'             => $d['location'],
                'issued_at'            => now(),
                'offline'              => false,
                'status_id'            => \App\Models\TicketStatus::where('name','unpaid')->value('id'),
                'latitude'             => $d['latitude'] ?? null,
                'longitude'            => $d['longitude'] ?? null,
                'confiscation_type_id' => $d['confiscation_type_id'],
                'admin_id'             => Auth::guard('admin')->id(),
            ]);

            $t->flags()->sync($d['flags'] ?? []);
            return $t;
        });

        // Attach violations via pivot (same as your Enforcer comment)
        $violationIds = \App\Models\Violation::whereIn('violation_code', $d['violations'])
                            ->pluck('id')->all();
        $ticket->violations()->sync($violationIds);

        // Previous apprehension
        $prev = \App\Models\Ticket::where('violator_id',$violator->id)
                    ->where('id','!=',$ticket->id)
                    ->orderBy('issued_at','desc')
                    ->first();
        $lastAt = $prev ? $prev->issued_at->format('d M Y, H:i') : null;
        $violatorName = trim(implode(' ', array_filter([
            $violator->first_name ?? null,
            $violator->middle_name ?? null,
            $violator->last_name ?? null,
        ]))) ?: 'Violator';
        DB::afterCommit(function () use ($ticket, $violator, $violatorName) {
            try {
                $admin = Auth::guard('admin')->user();
                $adminName = $admin?->name
                    ?: trim(implode(' ', array_filter([$admin->fname ?? null, $admin->lname ?? null])))
                    ?: 'Admin';

                if (class_exists(\App\Services\LogActivity::class)) {
                    \App\Services\LogActivity::on($ticket)
                        ->by($admin)
                        ->event('ticket.issued.by_admin')
                        ->withProperties([
                            'ticket_id'   => $ticket->id,
                            'violator_id' => $violator?->id,
                            'violator'    => $violatorName,
                            'actor_role'  => 'Admin',
                        ])
                        ->fromRequest()
                        ->log("Admin {$adminName} recorded a paper ticket (#{$ticket->id}) for {$violatorName}");
                } else {
                    Log::info('[Activity]', [
                        'event'       => 'ticket.issued.by_admin',
                        'ticket_id'   => $ticket->id,
                        'admin_id'    => $admin?->id,
                        'admin_name'  => $adminName,
                        'violator_id' => $violator?->id,
                        'violator'    => $violatorName,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('[Admin Ticket Activity skipped] '.$e->getMessage());
            }
        });
        // Build response (mirror Enforcer response keys)
        $violations = \App\Models\Violation::whereIn('violation_code', json_decode($ticket->violation_codes))
                        ->get()
                        ->map(fn($v) => [
                            'name' => $v->violation_name,
                            'fine' => $v->fine_amount,
                        ]);

        $issuingEnforcer = \App\Models\Enforcer::find($d['apprehending_enforcer_id']);

        return response()->json([
            'ticket' => [
                'id'            => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'issued_at'     => $ticket->issued_at->format('d M Y, H:i'),
                'location'      => $ticket->location,
                'status'        => optional($ticket->status)->name ?? 'Unknown',
                'confiscated'   => optional($ticket->confiscationType)->name ?? 'None',
                'is_impounded'  => (bool) $ticket->is_impounded, // also reflected via flags
                'is_resident'   => (bool) $ticket->is_resident,  // also reflected via flags
                'flags'         => $ticket->flags->pluck('key')->all(),
            ],
            'last_apprehended_at' => $lastAt,
            'enforcer' => [
                'name'      => $issuingEnforcer->fname.' '.$issuingEnforcer->lname,
                'badge_num' => $issuingEnforcer->badge_num,
            ],
            'violator' => [
                'first_name'     => $violator->first_name,
                'middle_name'    => $violator->middle_name,
                'last_name'      => $violator->last_name,
                'address'        => $violator->address,
                'birthdate'      => $violator->birthdate,
                'license_number' => $violator->license_number,
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
        $status     = $request->get('status');        // paid|unpaid|pending|cancelled|null
        $category   = $request->get('category');      // string|null
        $violationId= $request->get('violation_id');  // int|null

        switch($sortOption) {
            case 'date_asc':  $col='issued_at'; $dir='asc';  break;
            default:          $col='issued_at'; $dir='desc'; break;
        }

        $q = Ticket::query()
        ->select('tickets.*')
        ->leftJoin('violators','tickets.violator_id','=','violators.id')
        ->with([
            'violator',
            'vehicle',
            'enforcer'  => fn($q) => $q->withTrashed(),   // already added earlier
            'violations'=> fn($q) => $q->withTrashed(),
        ]);

        // status filter
        if ($status) {
            $statusId = TicketStatus::where('name',$status)->value('id');
            if ($statusId) $q->where('tickets.status_id',$statusId);
            // If paid, expose latest reference & paid_at
            if ($status === 'paid') {
                $q->selectSub(function($sub){
                    $sub->from('paid_tickets')
                        ->whereColumn('paid_tickets.ticket_id','tickets.id')
                        ->orderByDesc('paid_at')->limit(1)->select('reference_number');
                }, 'paid_reference')
                ->selectSub(function($sub){
                    $sub->from('paid_tickets')
                        ->whereColumn('paid_tickets.ticket_id','tickets.id')
                        ->orderByDesc('paid_at')->limit(1)->select('paid_at');
                }, 'paid_at');
            }
        }

        // violation/category filter
        // if violation chosen, join pivot; else if only category chosen, join violation via pivot and filter category
        if ($violationId) {
            $q->join('ticket_violation as tv','tv.ticket_id','=','tickets.id')
            ->where('tv.violation_id',$violationId);
        } elseif ($category) {
            $q->join('ticket_violation as tv','tv.ticket_id','=','tickets.id')
            ->join('violations as v','v.id','=','tv.violation_id')
            ->where('v.category',$category);
        }

        $tickets = $q->orderBy($col,$dir)
            ->paginate(5)
            ->appends([
                'sort_option'=>$sortOption,
                'status'=>$status,
                'category'=>$category,
                'violation_id'=>$violationId
            ]);

        return view('admin.partials.ticketTable', compact('tickets','sortOption'));
    }
    /** AJAX: return violations for a category */
    public function violationsByCategory(Request $request)
    {
        $cat = $request->query('category');
        if (!$cat) return response()->json([]);
        $items = Violation::where('category',$cat)
            ->orderBy('violation_name')
            ->get(['id','violation_code','violation_name']);
        return response()->json($items);
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
    public function ticketVersion(Request $request)
    {
        // If you want the token to respect current filters, read them (optional):
        $status     = $request->query('status');      // paid|unpaid|pending|cancelled|null
        $category   = $request->query('category');    // string|null
        $violationId= $request->query('violation_id');// int|null

        // Base query similar to partial() but only grabbing the latest update time + counts
        $q = Ticket::query()
            ->select('tickets.updated_at')
            ->leftJoin('violators','tickets.violator_id','=','violators.id');

        if ($status) {
            $statusId = TicketStatus::where('name',$status)->value('id');
            if ($statusId) $q->where('tickets.status_id',$statusId);
        }
        if ($violationId) {
            $q->join('ticket_violation as tv','tv.ticket_id','=','tickets.id')
            ->where('tv.violation_id',$violationId);
        } elseif ($category) {
            $q->join('ticket_violation as tv','tv.ticket_id','=','tickets.id')
            ->join('violations as v','v.id','=','tv.violation_id')
            ->where('v.category',$category);
        }

        // Compute a tiny token that changes when anything relevant changes
        $maxTicketUpdated = optional($q->max('tickets.updated_at'))?->timestamp ?? 0;
        $countTickets     = (clone $q)->distinct('tickets.id')->count('tickets.id');

        // If “paid” is in play, include the latest PaidTicket activity too
        $maxPaidUpdated = 0;
        if (!$status || $status === 'paid') {
            $maxPaidUpdated = optional(
                PaidTicket::max('updated_at')
            )?->timestamp ?? 0;
        }

        // You can include max(id) as well if you want a super-cheap change detector:
        $maxId       = Ticket::max('id') ?? 0;

        // Build a stable token
        $tokenParts  = [$maxTicketUpdated, $maxPaidUpdated, $countTickets, $maxId, $status ?: '-', $category ?: '-', $violationId ?: 0];
        $tokenString = implode('|', $tokenParts);
        $hash        = substr(hash('xxh3','tickets:'.$tokenString), 0, 16);

        return response()->json(['v' => $hash]);
    }
}
