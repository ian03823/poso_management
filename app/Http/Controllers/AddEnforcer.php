<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Enforcer;
use App\Models\TicketRange;
use App\Models\Ticket;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Http\Controllers\Concerns\WithActivityLogs;
use Illuminate\Validation\Rule;

class AddEnforcer extends Controller
{
    use WithActivityLogs;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $sortOption   = $request->get('sort_option','date_desc');
        $search = $request->get('search','');

        $show       = $request->get('show','active');

        $query = $show === 'inactive'
        ? Enforcer::onlyTrashed()
        : Enforcer::query();

        // 1) Search
        if ($search !== '') {
            $query->where(function($q) use ($search) {
                $q->where('fname','like',"%{$search}%")
                  ->orWhere('lname','like',"%{$search}%")
                  ->orWhere('badge_num','like',"%{$search}%")
                  ->orWhere('phone','like',"%{$search}%");
            });
        }

        // 2) Sort
        switch($sortOption) {
            case 'date_asc':
                $query->orderBy('updated_at','asc'); break;
            case 'name_asc':
                $query->orderBy('fname','asc')->orderBy('lname','asc'); break;
            case 'name_desc':
                $query->orderBy('fname','desc')->orderBy('lname','desc'); break;
            case 'date_desc':
            default:
                $query->orderBy('updated_at','desc'); break;
        }

        // 3) Paginate + carry params
        $enforcer = $query
            ->paginate(5)
            ->appends([
              'sort_option' => $sortOption,
              'search'      => $search,
              'show'        => $show,
            ]);

        //  if ($request->ajax()) {
        //     // Return only the table for AJAX pagination
        //     return view('admin.partials.enforcerTable', compact('enforcer','sortOption','search','show'));
        // }

        return view('admin.enforcer', compact('enforcer','sortOption','search','show'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Get last enforcer's ticket_end
        $lastEnforcer = Enforcer::orderByDesc('id')->first();

        if ($lastEnforcer && is_numeric($lastEnforcer->ticket_end)) {
            $lastEnd = intval($lastEnforcer->ticket_end);
            $nextStart = str_pad($lastEnd + 1, 3, '0', STR_PAD_LEFT);
            $nextEnd   = str_pad($lastEnd + 100, 3, '0', STR_PAD_LEFT);
        } else {
            $nextStart = '001';
            $nextEnd   = '100';
        }

         // Auto generate badge number
        if ($lastEnforcer && is_numeric($lastEnforcer->badge_num)) {
            $nextBadgeNum = str_pad(intval($lastEnforcer->badge_num) + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $nextBadgeNum = '10001'; // Default start
        }

        // For AJAX requests (partial view only)
        if ($request->ajax()) {
            return view('admin.partials.addenforcer', compact('nextStart', 'nextEnd','nextBadgeNum'));
        }

        // Full page view with layout
        return view('admin.addenforcer', compact('nextStart', 'nextEnd', 'nextBadgeNum'));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'badge_num'    => 'required|string|max:4',
            'fname'        => 'required|string|min:2|max:20',
            'mname'        => 'nullable|string|min:1|max:20',
            'lname'        => 'required|string|min:2|max:20',
            'phone'        => 'required|digits:11',
            'password'     => 'required|string|min:8|max:20',
            'ticket_start' => 'required|digits:3|numeric|min:1|max:999',
            'ticket_end'   => 'required|digits:3|numeric|gte:ticket_start|max:999',
        ]);

        if (Enforcer::where('badge_num', $data['badge_num'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Badge number already exists'], 422);
        }

        $rawPassword = $data['password'];

        // Wrap in transaction so Enforcer + TicketRange stay in sync
        DB::beginTransaction();

        try {
            // 1) Save to enforcers (OLD structure kept)
            $e = Enforcer::create([
                'badge_num'       => $data['badge_num'],
                'fname'           => $data['fname'],
                'mname'           => $data['mname'] ?? null,
                'lname'           => $data['lname'],
                'phone'           => $data['phone'],
                'ticket_start'    => $data['ticket_start'],
                'ticket_end'      => $data['ticket_end'],
                'password'        => Hash::make($rawPassword),
                'defaultPassword' => Hash::make($rawPassword), 
            ]);

            // 2) Save to ticket_range (NEW structure)
            TicketRange::create([
                'badge_num'    => $e->badge_num,
                'ticket_start' => $data['ticket_start'],
                'ticket_end'   => $data['ticket_end'],
            ]);

            DB::commit();

            // activity log
            $this->logCreated($e, 'enforcer', [
                'enforcer_id'  => $e->id,
                'badge_num'    => $e->badge_num,
                'name'         => trim("{$e->fname} {$e->mname} {$e->lname}"),
                'phone'        => $e->phone,
                'ticket_range' => "{$e->ticket_start}-{$e->ticket_end}",
            ]);

            return response()->json(['success' => true, 'raw_password' => $rawPassword], 201);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to create enforcer'], 500);
        }
    }
    public function addTicketRange(Request $request, string $id)
    {
        $enforcer = Enforcer::withTrashed()->findOrFail($id);

        // 1) Find the last ticket range for this enforcer (highest ticket_end)
        $lastRange = TicketRange::where('badge_num', $enforcer->badge_num)
            ->orderByDesc('ticket_end')
            ->first();

        if (! $lastRange) {
            return response()->json([
                'success' => false,
                'message' => 'This enforcer has no existing ticket range. Please set an initial range first.',
            ], 422);
        }

        // 2) Check the last ticket number actually used by this enforcer
        $lastTicket = Ticket::where('enforcer_id', $enforcer->id)->max('ticket_number');

        // If no tickets yet, or last ticket is still below the end of the current batch -> don’t allow new batch
        if ($lastTicket === null || $lastTicket < $lastRange->ticket_end) {
            $currentRangeText = str_pad($lastRange->ticket_start, 3, '0', STR_PAD_LEFT)
                            . '–'
                            . str_pad($lastRange->ticket_end, 3, '0', STR_PAD_LEFT);

            $msg = "Current ticket batch ({$currentRangeText}) is not yet fully used.";
            if ($lastTicket !== null) {
                $msg .= ' Last used ticket: ' . str_pad($lastTicket, 3, '0', STR_PAD_LEFT) . '.';
            } else {
                $msg .= ' No tickets have been issued yet.';
            }

            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 422);
        }

        // 3) Ensure we haven’t hit maximum ticket number
        if ($lastRange->ticket_end >= 999) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add another ticket range: maximum ticket number 999 reached for this enforcer.',
            ], 422);
        }

        // 4) Auto-generate next batch
        // Use the same batch size style as your initial logic (001–100, then 101–200, etc.)
        $batchSize = 100;

        $newStart = $lastRange->ticket_end + 1;
        $newEnd   = $newStart + $batchSize - 1;
        if ($newEnd > 999) {
            $newEnd = 999; // cap at 999 just in case
        }

        // Extra safety: make sure this new range does not overlap anything
        $overlap = TicketRange::where('badge_num', $enforcer->badge_num)
            ->where('ticket_start', '<=', $newEnd)
            ->where('ticket_end',   '>=', $newStart)
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Computed ticket range overlaps with an existing range. Please contact the administrator.',
            ], 422);
        }

        // 5) Create the new range
        $range = TicketRange::create([
            'badge_num'    => $enforcer->badge_num,
            'ticket_start' => $newStart,
            'ticket_end'   => $newEnd,
        ]);

        // optional activity log
        $this->logCreated($range, 'ticket_range', [
            'enforcer_id'  => $enforcer->id,
            'badge_num'    => $enforcer->badge_num,
            'ticket_range' => sprintf('%03d-%03d', $range->ticket_start, $range->ticket_end),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket range added: ' . sprintf('%03d–%03d', $newStart, $newEnd),
            'range'   => [
                'ticket_start' => $range->ticket_start,
                'ticket_end'   => $range->ticket_end,
            ],
        ], 201);
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $enforcer = Enforcer::find($id);
        return view('admin.partials.enforcerTable')->with('enforcer', $enforcer);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $enforcer = Enforcer::find($id);
        return view('editEnforcer')->with('enforcer', $enforcer);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $e = Enforcer::withTrashed()->findOrFail($id);

        $data = $request->validate([
            'badge_num'    => ['nullable','string','max:5',
                Rule::unique('enforcers','badge_num')->ignore($e->id)->whereNull('deleted_at')
            ],
            'fname'        => 'nullable|string|min:2|max:50',
            'mname'        => 'nullable|string|min:1|max:50',
            'lname'        => 'nullable|string|min:2|max:50',
            'phone'        => 'nullable|digits:11',
            // 🚫 removed ticket_start & ticket_end from validation (we don't edit ranges here)
            'password'     => 'nullable|string|min:8|max:50',
        ]);

        // compute diff (don’t expose real passwords)
        $original = $e->getOriginal();

        $resp = ['success'=>true,'message'=>'Enforcer updated'];

        if (!empty($data['password'])) {
            $raw = $data['password'];
            $data['password']        = Hash::make($raw);
            $data['defaultPassword'] = Hash::make($raw);
            $resp['raw_password']    = $raw;
            $resp['message']         = 'Password reset & updated';
        } else {
            unset($data['password'], $data['defaultPassword']);
        }

        // Fill & detect dirty (only fields we allow to change)
        $e->fill(array_filter($data, fn($v) => !is_null($v)));
        $dirty = $e->getDirty();

        // Replace password diff (if touched) with masked info
        if (array_key_exists('password', $dirty)) {
            $dirty['password']        = '***reset***';
            $dirty['defaultPassword'] = '***reset***';
        }

        $e->save();

        /**
         * 🔁 If badge_num changed, propagate to ALL ticket_range rows for this enforcer
         */
        if (array_key_exists('badge_num', $dirty)) {
            TicketRange::where('badge_num', $original['badge_num'])
                ->update(['badge_num' => $e->badge_num]);
        }

        // Build explicit diff map like Violation logs
        $diff = [];
        foreach ($dirty as $field => $newVal) {
            if (in_array($field, ['password','defaultPassword'])) {
                $diff[$field] = ['from' => '***', 'to' => '***reset***'];
            } else {
                $diff[$field] = [
                    'from' => $original[$field] ?? null,
                    'to'   => $newVal,
                ];
            }
        }

        $this->logUpdated($e, 'enforcer', [
            'enforcer_id' => $e->id,
            'badge_num'   => $e->badge_num,
            'diff'        => $diff,
        ]);

        return response()->json($resp, 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, Request $request)
    {
        //
        $request->validate(['admin_password' => 'required']);
        $admin = auth('admin')->user(); // your custom admin guard

        if (!$admin || !Hash::check($request->admin_password, $admin->password)) {
            return response()->json(['message' => 'Invalid admin password'], 422);
        }

        $e = Enforcer::findOrFail($id);

        $e->delete();
        $this->logDeleted($e, 'enforcer', [
            'enforcer_id' => $e->id,
            'badge_num'   => $e->badge_num,
            'name'        => trim("{$e->fname} {$e->mname} {$e->lname}"),
        ]);

        return response()->json(['message' => 'Enforcer inactivated'], 200);
    }
    public function restore(string $id, Request $request)
    {
        $request->validate(['admin_password' => 'required']);
        $admin = auth('admin')->user();

        if (!$admin || !Hash::check($request->admin_password, $admin->password)) {
            return response()->json(['message' => 'Invalid admin password'], 422);
        }

        $e = Enforcer::withTrashed()->findOrFail($id);
        if (!$e) {
            return response()->json(['message' => 'Enforcer not found'], 404);
        }

        if (!$e->trashed()) {
            return response()->json(['message' => 'Already active'], 200);
        }
        $e->restore();
        $this->logRestored($e, 'enforcer', [
            'enforcer_id' => $e->id,
            'badge_num'   => $e->badge_num,
        ]);

        return response()->json(['message' => 'Enforcer activated'], 200);
    }

    public function partial(Request $request)
    {
        $sortOption   = $request->get('sort_option','date_desc');
        $search = $request->get('search','');
        $show       = $request->get('show','active');
        $query = $show === 'inactive'
        ? Enforcer::onlyTrashed()
        : Enforcer::query();
        $query = Enforcer::query();
        if ($search !== '') {
            $query->where(function($q) use ($search) {
                $q->where('fname','like',"%{$search}%")
                  ->orWhere('lname','like',"%{$search}%")
                  ->orWhere('badge_num','like',"%{$search}%")
                  ->orWhere('phone','like',"%{$search}%");
            });
        }
        switch($sortOption) {
            case 'date_asc':
                $query->orderBy('updated_at','asc'); break;
            case 'name_asc':
                $query->orderBy('fname','asc')->orderBy('lname','asc'); break;
            case 'name_desc':
                $query->orderBy('fname','desc')->orderBy('lname','desc'); break;
            case 'date_desc':
            default:
                $query->orderBy('updated_at','desc'); break;
        }
        $enforcer = $query
            ->paginate(5)
            ->appends([
              'sort_option' => $sortOption,
              'search'      => $search,
              'show'       => $show,
            ]);

        // Only the table
        return view('admin.partials.enforcerTable', compact('enforcer','sortOption','search', 'show'));
    }

}
