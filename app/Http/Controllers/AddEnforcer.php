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
        // 1) Base query
        $query = Enforcer::query();

        // 2) Sorting
        $sortOption = $request->get('sort_option','date_desc');
        switch($sortOption) {
            case 'date_asc':
                $column='updated_at'; $direction='asc';  break;
            case 'name_asc':
                $column='fname';      $direction='asc';  break;
            case 'name_desc':
                $column='fname';      $direction='desc'; break;
            case 'date_desc':
            default:
                $column='updated_at'; $direction='desc'; break;
        }

        // 3) Paginate with sort, keep sort_option in links
        $enforcer = $query
            ->orderBy($column,$direction)
            ->paginate(5)
            ->appends('sort_option',$sortOption);

        // 4) AJAX? return only the partial table
        if ($request->ajax()) {
            return view('admin.enforcer', compact('enforcer','sortOption'));
        }

        // 5) Full‐page view
        //return view('admin.enforcer', compact('enforcer','sortOption'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //
        if ($request->ajax()) {
            return view('admin.addenforcer');
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'badge_num' => 'required|string|max:7',
            'fname' => 'required|string|min:2|max:20',
            'mname' => 'nullable|string|min:3|max:20',
            'lname' => 'required|string|min:3|max:20',
            'phone' => 'required|digits:11',
            'password' => 'required|string|max:16',
        ]);
        Enforcer::create([
            'badge_num' => $request->badge_num,
            'fname' => $request->fname,
            'mname' => $request->mname,
            'lname' => $request->lname,
            'phone' => $request->phone,
            'password' => Hash::make($request->password)
        ]);
        if ($request->ajax()) {
            $enforcer= Enforcer::paginate(5);
            return view('admin.partials.enforcer', compact('enforcer'))
                   ->with('success','Enforcer added successfully');
        }

        // Full-page fallback
        return redirect('/enforcer')
                         ->with('success','Enforcer added successfully');
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
        // 1) Build the query + sort just like index()
        $query = Enforcer::query();
        $sortOption = $request->get('sort_option','date_desc');
        switch($sortOption) {
            case 'date_asc':   $column='updated_at'; $dir='asc';  break;
            case 'name_asc':   $column='fname';      $dir='asc';  break;
            case 'name_desc':  $column='fname';      $dir='desc'; break;
            case 'date_desc':
            default:           $column='updated_at'; $dir='desc'; break;
        }

        // 2) Paginate + keep sort_option in links
        $enforcers = $query
        ->orderBy($column,$dir)
        ->paginate(5)
        ->appends('sort_option',$sortOption);

        // 3) Return the table partial
        return view('admin.partials.enforcerTable', compact('enforcers'));
    }
}
