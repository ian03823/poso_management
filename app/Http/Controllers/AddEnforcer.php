<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Enforcer;
use Illuminate\Support\Facades\Hash;
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
        //
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
            return response()->json(['success'=>false,'message'=>'Badge number already exists'], 422);
        }

        $raw = $data['password'];
        $data['password']        = Hash::make($raw);
        $data['defaultPassword'] = Hash::make($raw);


        $e = Enforcer::create($data);
        // activity log (match Violation style)
        $this->logCreated($e, 'enforcer', [
            'enforcer_id'  => $e->id,
            'badge_num'    => $e->badge_num,
            'name'         => trim("{$e->fname} {$e->mname} {$e->lname}"),
            'phone'        => $e->phone,
            'ticket_range' => "{$e->ticket_start}-{$e->ticket_end}",
        ]);
        // Return minimal success (your front-end already reloads the table)
        return response()->json(['success'=>true, 'raw_password'=>$raw], 201);
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
        //
        $e = Enforcer::withTrashed()->findOrFail($id);

        $data = $request->validate([
            'badge_num'    => ['nullable','string','max:5',
                Rule::unique('enforcers','badge_num')->ignore($e->id)->whereNull('deleted_at')
            ],
            'fname'        => 'nullable|string|min:2|max:50',
            'mname'        => 'nullable|string|min:1|max:50',
            'lname'        => 'nullable|string|min:2|max:50',
            'phone'        => 'nullable|digits:11',
            'ticket_start' => 'nullable|digits:3|numeric|min:1|max:999',
            'ticket_end'   => 'nullable|digits:3|numeric|min:1|max:999|gte:ticket_start',
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

        // Fill & detect dirty
        $e->fill(array_filter($data, fn($v) => !is_null($v)));
        $dirty = $e->getDirty();

        // Replace password diff (if touched) with masked info
        if (array_key_exists('password', $dirty)) {
            $dirty['password']        = '***reset***';
            $dirty['defaultPassword'] = '***reset***';
        }

        $e->save();

        // Build explicit diff map like Violation logs
        $diff = [];
        foreach ($dirty as $field => $newVal) {
            // don’t log hashed values verbatim
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
