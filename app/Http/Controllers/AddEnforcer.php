<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Enforcer;
use Illuminate\Support\Facades\Hash;

class AddEnforcer extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        
        $enforcer = Enforcer::paginate(5);
        return view("admin.enforcer")->with("enforcer", $enforcer);

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
            $enforcers = Enforcer::paginate(5);
            return view('admin.partials.enforcer', compact('enforcers'))
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
        $request->validate([
            'badge_num' => 'required|string|max:7',
            'fname' => 'required|string|min:2|max:20',
            'mname' => 'nullable|string|min:3|max:20',
            'lname' => 'required|string|min:3|max:20',
            'phone' => 'required|digits:11',
        ]);
        $enforcer = Enforcer::findOrfail($id);
        $enforcer->update($request->all());

        return response()->json(['message' => 'success']);;
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
}
