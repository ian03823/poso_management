<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Enforcer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Response;

class AddEnforcer extends Controller
{
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

        // Full page
        return view('admin.enforcer', compact('enforcer','sortOption','search', 'show'));

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
        return view('admin.addenforcer', compact('nextStart', 'nextEnd'));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $data = $request->validate([
            'badge_num' => 'required|string|max:4',
            'fname' => 'required|string|min:2|max:20',
            'mname' => 'nullable|string|min:3|max:20',
            'lname' => 'required|string|min:3|max:20',
            'phone' => 'required|digits:11',
            'password' => 'required|string|max:16',
            'ticket_start'  => 'required|digits:3|numeric|min:1|max:999',
            'ticket_end'    => 'required|digits:3|numeric|gte:ticket_start|max:999',
        ]);
        $badge_num = Enforcer::where('badge_num', $data['badge_num'])->exists();
        if ($badge_num) {
            return response()->json([
                'success' => false,
                'message' => 'Badge number already exists',
            ], 422);
        }
        $raw = $data['password'];
        $data['password']         = Hash::make($raw);
        $data['defaultPassword'] = Hash::make($raw);
        Enforcer::create($data);
        

        $enforcer = Enforcer::orderBy('updated_at','desc')->paginate(5);

        return response()->json([
            'success'      => true,
            'raw_password' => $raw,
            'html'         => view('admin.partials.enforcerTable', compact('enforcer'))->render(),
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
        //
        $e = Enforcer::findOrFail($id);

        $data = $request->validate([
            'badge_num' => 'nullable|string|min:2|max:3',
            'fname' => 'nullable|min:3',
            'mname' => 'nullable',
            'lname' => 'required|min:3',
            'phone' => 'nullable|digits:11',
            'password' => 'nullable|string|min:8|max:20',
        ]);
        $resp = ['success'=>true,'message'=>'Enforcer updated'];

        if (!empty($data['password'])) {
            // admin reset via JS-generated value or manual
            $raw = $data['password'];
            $data['password']         = Hash::make($raw);
            $data['defaultPassword'] = Hash::make($raw);
            $resp['raw_password']     = $raw;
            $resp['message']          = 'Password reset & updated';
        } else {
            unset($data['password'],$data['defaultPassword']);
        }
        $e->update($data);

        return response()->json($resp,200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $enforcer = Enforcer::findOrfail($id);
        $enforcer->delete();
        return redirect('/enforcer')->with('success','Client Deleted Succesfully');
    }
    public function restore(string $id)
    {
        // find even trashed records
        $e = Enforcer::withTrashed()->findOrFail($id);

        // “undelete” it
        $e->restore();

        // go back (preserves your ?show=inactive or ?show=active)
        return redirect()->back()
                        ->with('success','Enforcer has been re-activated.');
    }

    public function partial(Request $request)
    {
        $sortOption   = $request->get('sort_option','date_desc');
        $search = $request->get('search','');
        $show       = $request->get('show','active');

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
            ]);

        // Only the table
        return view('admin.partials.enforcerTable', compact('enforcer','sortOption','search'));
    }
}
