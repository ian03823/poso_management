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

        $query = Enforcer::query();

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
            ]);

        // Full page
        return view('admin.enforcer', compact('enforcer','sortOption','search'));

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //
        if ($request->ajax()) {
            return view('admin.partials.addenforcer');
        }
    
        // Direct GET → full page with layout
        return view('admin.addenforcer');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $data = $request->validate([
            'badge_num' => 'required|string|max:7',
            'fname' => 'required|string|min:2|max:20',
            'mname' => 'nullable|string|min:3|max:20',
            'lname' => 'required|string|min:3|max:20',
            'phone' => 'required|digits:11',
            'password' => 'required|string|max:16',
        ]);
        $badge_num = Enforcer::where('badge_num', $data['badge_num'])->exists();
        if ($badge_num) {
            return response()->json([
                'success' => false,
                'message' => 'Badge number already exists',
            ], 422);
        }

        $data['password'] = Hash::make($data['password']);
        Enforcer::create($data);

        $enforcer = Enforcer::orderBy('updated_at','desc')->paginate(5);

        return view('admin.partials.enforcerTable', compact('enforcer'));
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
            'badge_num' => 'required|string|min:2|max:3',
            'fname' => 'required|min:3',
            'mname' => 'nullable',
            'lname' => 'required|min:3',
            'phone' => 'required|digits:11',
        ]);

        $e->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Enforcer Updated Successfully',
            'enforcer'=> $e
        ], 200);
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

    public function partial(Request $request)
    {
        $sortOption   = $request->get('sort_option','date_desc');
        $search = $request->get('search','');

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
