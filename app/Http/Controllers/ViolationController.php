<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Violation;

class ViolationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $violation = Violation::paginate(5);
        return view("admin.violation.violationList")->with("violation", $violation);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return view("admin.violation.addViolation");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'violation_code' => 'required|string|unique:violations',
            'violation_name' => 'required|string|unique:violations',
            'fine_amount' => 'required|numeric|min:0',
            'penalty_points' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'category' => 'required|string',
        ]);
    
        Violation::create([
            'violation_code' => $request->violation_code,
            'violation_name' => $request->violation_name,
            'fine_amount' => $request->fine_amount,
            'penalty_points' => $request->penalty_points,
            'description' => $request->description,
            'category' => $request->category, // Ensure category is saved
        ]);
    
        return redirect('/violation')->with('success', 'Violation added successfully!');
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
        $violation = Violation::findOrFail($id);
        return view('admin.violation.editViolation', compact('violation'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'violation_code' => 'required|string|unique:violations,violation_code,'.$id,
            'violation_name' => 'required|string|unique:violations,violation_name,'.$id,
            'fine_amount' => 'required|numeric|min:0',
            'penalty_points' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'category' => 'required|string',
        ]);

        $violation = Violation::findOrFail($id);
        $violation->update($request->all());

        return redirect()->route('violation.index')->with('success', 'Violation updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $violation = Violation::findOrfail($id);
        $violation->delete();
        return redirect('/violation')->with('success','Violation Deleted Succesfully');
    }
}
