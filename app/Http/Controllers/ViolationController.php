<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Violation;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class ViolationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $categoryFilter = $request->get('category', 'all');
        $search         = $request->get('search','');

        // 2) Base query
        $query = Violation::query();
    
        if ($categoryFilter !== 'all') {
            $query->where('category', $categoryFilter);
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('violation_name','like',"%{$search}%")
                  ->orWhere('violation_code','like',"%{$search}%");
            });
        }
    
        // 3) Paginate and carry category in links
        $violation = $query
            ->orderBy('updated_at','desc')
            ->paginate(5)
            ->appends([
                'category' => $categoryFilter,
                'search' => $search,
            ]);

        // 5) Full-page: we need the fixed list of categories
        $categories = Violation::distinct('category')
            ->orderBy('category')
            ->pluck('category')
            ->toArray();

        return view('admin.violation.violationList', compact(
            'violation','categoryFilter','categories','search'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //
       $lastViolation = Violation::where('violation_code', 'like', 'V%')
        ->orderByDesc(DB::raw('CAST(SUBSTRING(violation_code, 2) AS UNSIGNED)'))
        ->first();

        if ($lastViolation) {
            $lastNumber = (int)substr($lastViolation->violation_code, 1); // remove 'V'
            $nextViolation = 'V' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $nextViolation = 'V001';
        }
        if ($request->ajax()) {
            return view('admin.partials.addViolation', compact('nextViolation'));
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
        $violation = Violation::find($id);
        return view('admin.')->with('violation', $violation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $v = Violation::findOrFail($id);

        $data = $request->validate([
            'violation_code' => 'nullable|string|min:2|max:3',
            'violation_name' => 'nullable|min:3',
            'fine_amount' => 'nullable|min:0|numeric',
            'category' => 'nullable|string',

        ]);

        $v->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Violation Updated Successfully',
            'violation'=> $v
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $violation = Violation::findOrfail($id);
        $violation->delete();
        
        return redirect('/violation')->with('success','Violation archived succesfully');
    }
    public function partial(Request $request)
    {
        //
        $categoryFilter = $request->get('category','all');
        $search         = $request->get('search','');

        $query = Violation::query();
        if ($categoryFilter!=='all') {
            $query->where('category',$categoryFilter);
        }
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('violation_name','like',"%{$search}%")
                  ->orWhere('violation_code','like',"%{$search}%");
            });
        }
    
        $violation = $query
            ->orderBy('updated_at','desc')
            ->paginate(5)
            ->appends([
                'category' => $categoryFilter,
                'search' => $search,
            ]);

        return view('admin.partials.violationTable', compact('violation'));
    }
}
