<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Violation;

class ViolationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $query = Violation::query();

        // Map dropdown choice to column & direction
        $sortOption = $request->get('sort_option', 'date_desc');
    
        switch($sortOption) {
            case 'date_asc':
                $column = 'updated_at';
                $direction = 'asc';
                break;
    
            case 'name_asc':
                $column = 'violation_name';
                $direction = 'asc';
                break;
    
            case 'name_desc':
                $column = 'violation_name';
                $direction = 'desc';
                break;
    
            case 'date_desc':
            default:
                $column = 'updated_at';
                $direction = 'desc';
                break;
        }
    
        $violation = $query
            ->orderBy($column, $direction)
            ->paginate(5)
            ->appends('sort_option', $sortOption);
    
        return view('admin.violation.violationList', compact('violation', 'sortOption'));

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //
        if ($request->ajax()) {
            return view('admin.violation.addViolation');
        }
        return view('admin.violation.addViolation');
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
            'description' => 'nullable|string',
            'category' => 'required|string',
        ]);
    
        Violation::create([
            'violation_code' => $request->violation_code,
            'violation_name' => $request->violation_name,
            'fine_amount' => $request->fine_amount,
            'description' => $request->description,
            'category' => $request->category, // Ensure category is saved
        ]);
    
        if ($request->ajax()) {
            $violation = Violation::paginate(5);
            return view('admin.partials.addViolation', compact('violations'))
                   ->with('success','Violation added successfully');
        }
        return redirect('/violation')
        ->with('success','Violation added successfully');
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
