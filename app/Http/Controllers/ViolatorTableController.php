<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Violator;
use App\Models\Ticket;
use App\Models\Vehicle;

class ViolatorTableController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        
        // grab the UI inputs (with sensible defaults)
        $sortOption   = $request->get('sort_option','date_desc');
        $search       = $request->get('search','');
        $vehicleType  = $request->get('vehicle_type','all');

        // 1) find each violator’s latest ticket id
        $latestTicketIds = Ticket::groupBy('violator_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        // 2) build the base Ticket query
        $query = Ticket::with(['violator','vehicle'])
            ->whereIn('id',$latestTicketIds)
            // filter by vehicle_type if requested
            ->when($vehicleType!=='all', fn($q) => $q
                ->whereHas('vehicle', fn($q2) =>
                    $q2->where('vehicle_type',$vehicleType)
                )
            )
            // search name/license/plate
            ->when($search, fn($q) => $q
                ->whereHas('violator', fn($q2) => $q2
                    ->where('name','like',"%{$search}%")
                    ->orWhere('license_number','like',"%{$search}%")
                )
                ->orWhereHas('vehicle', fn($q2) =>
                    $q2->where('plate_number','like',"%{$search}%")
                )
            );

        // 3) apply sorting
        match($sortOption) {
            'date_asc'   => $query->orderBy('issued_at','asc'),
            'name_asc'   => $query->join('violators','tickets.violator_id','violators.id')
                                  ->orderBy('violators.name','asc'),
            'name_desc'  => $query->join('violators','tickets.violator_id','violators.id')
                                  ->orderBy('violators.name','desc'),
            default      => $query->orderBy('issued_at','desc'),
        };

        // 4) paginate & carry filters in links
        $tickets = $query
            ->distinct('tickets.id')
            ->paginate(5)
            ->appends([
                'sort_option'  => $sortOption,
                'search'       => $search,
                'vehicle_type' => $vehicleType,
            ]);

        // 5) for the vehicle-type dropdown
        $vehicleTypes = Vehicle::distinct()->pluck('vehicle_type');

        return view(
            'admin.violator.violatorTable',
            compact('tickets','sortOption','search','vehicleType','vehicleTypes')
        );
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
        $violator = Violator::with([
            'vehicles',
            'tickets.violations',
            'tickets.status'
        ])->findOrFail($id);

        // sort tickets newest → oldest
        $violator->tickets = $violator->tickets
                                      ->sortByDesc('issued_at')
                                      ->values();

        return view('admin.partials.violatorView', compact('violator'));
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
        $sortOption   = $request->get('sort_option','date_desc');
        $search       = $request->get('search','');
        $vehicleType  = $request->get('vehicle_type','all');

        // 1) find each violator’s latest ticket id
        $latestTicketIds = Ticket::groupBy('violator_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id');

        // 2) build the base Ticket query
        $query = Ticket::with(['violator','vehicle'])
            ->whereIn('id',$latestTicketIds)
            // filter by vehicle_type if requested
            ->when($vehicleType!=='all', fn($q) => $q
                ->whereHas('vehicle', fn($q2) =>
                    $q2->where('vehicle_type',$vehicleType)
                )
            )
            // search name/license/plate
            ->when($search, fn($q) => $q
                ->whereHas('violator', fn($q2) => $q2
                    ->where('name','like',"%{$search}%")
                    ->orWhere('license_number','like',"%{$search}%")
                )
                ->orWhereHas('vehicle', fn($q2) =>
                    $q2->where('plate_number','like',"%{$search}%")
                )
            );

        // 3) apply sorting
        match($sortOption) {
            'date_asc'   => $query->orderBy('issued_at','asc'),
            'name_asc'   => $query->join('violators','tickets.violator_id','violators.id')
                                  ->orderBy('violators.name','asc'),
            'name_desc'  => $query->join('violators','tickets.violator_id','violators.id')
                                  ->orderBy('violators.name','desc'),
            default      => $query->orderBy('issued_at','desc'),
        };

        // 4) paginate & carry filters in links
        $tickets = $query
            ->distinct('tickets.id')
            ->paginate(5)
            ->appends([
                'sort_option'  => $sortOption,
                'search'       => $search,
                'vehicle_type' => $vehicleType,
            ]);

        // 5) for the vehicle-type dropdown
        $vehicleTypes = Vehicle::distinct()->pluck('vehicle_type');

        return view('admin.partials.violatorTable', compact('tickets'));
    }
}
