<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Violator;
use App\Models\Ticket;

class ViolatorTableController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    //  protected function buildQuery(Request $request)
    // {
    //     $sortOption = $request->get('sort_option','date_desc');
    //     $search     = $request->get('search','');

    //     // determine sort column & direction
    //     switch($sortOption) {
    //         case 'date_asc':
    //             $col = 'tickets.issued_at'; $dir = 'asc'; break;
    //         case 'name_asc':
    //             $col = 'violators.name';    $dir = 'asc'; break;
    //         case 'name_desc':
    //             $col = 'violators.name';    $dir = 'desc'; break;
    //         case 'date_desc':
    //         default:
    //             $col = 'tickets.issued_at'; $dir = 'desc'; break;
    //     }

    //     // base query: join latest ticket & vehicle for sorting/search
    //     return Violator::with([
    //                 'tickets'   => fn($q)=> $q->latest()->limit(1),
    //                 'tickets.vehicle',
    //                 'tickets.violations'
    //             ])
    //             ->select('violators.*')
    //             ->distinct()
    //             ->leftJoin('tickets','tickets.violator_id','violators.id')
    //             ->leftJoin('vehicles','vehicles.vehicle_id','tickets.vehicle_id')
    //             ->when($search, fn($q) =>
    //                 $q->where(function($q2) use($search){
    //                     $q2->where('violators.name','like',"%{$search}%")
    //                        ->orWhere('violators.license_number','like',"%{$search}%")
    //                        ->orWhere('vehicles.plate_number','like',"%{$search}%");
    //                 })
    //             )
    //             ->groupBy('violators.id')        // avoid duplicate rows after join
    //             ->orderBy($col, $dir);
    // }
    public function index(Request $request)
    {
        //
        $latestTicketIds = Ticket::groupBy('violator_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        // 2) load those tickets (with violator & vehicle)
        $tickets = Ticket::with(['violator','vehicle'])
            ->whereIn('id', $latestTicketIds)
            ->orderBy('issued_at','desc')
            ->paginate(10);

        return view('admin.violator.violatorTable', compact('tickets'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
    public function update(Request $request, string $id)
    {
        //
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
        $categoryFilter = $request->get('category','all');
        $search         = $request->get('search','');

        $query = Violator::query();
        if ($categoryFilter!=='all') {
            $query->where('category',$categoryFilter);
        }
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name','like',"%{$search}%")
                  ->orWhere('license_number','like',"%{$search}%");
            });
        }
    
        $violators = $query
            ->orderBy('updated_at','desc')
            ->paginate(5)
            ->appends([
                'category' => $categoryFilter,
                'search' => $search,
            ]);

        return view('admin.partials.violatorTable', compact('violators'));
    }
}
