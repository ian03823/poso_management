<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Exports\SimpleStatsExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view('admin.analytics.index');
    }
    public function latest(Request $request)
    {
        // ----- Parse filters -----
        [$from, $to] = $this->parseDateRange($request->input('from'), $request->input('to'));
        $violationIds = collect($request->input('violations', []))->filter()->map('intval')->values();
        $status = $request->string('status')->lower()->toString(); // 'paid' | 'unpaid' | ''

        // base query
        $q = DB::table('tickets')->whereBetween('issued_at', [$from, $to]);

        // status filter
        if ($status === 'paid')   $q->where('status_id', 2);
        if ($status === 'unpaid') $q->where('status_id', 1);

        // optional join if you have a pivot for violations
        if ($violationIds->isNotEmpty() && Schema::hasTable('ticket_violation')) {
            $q->join('ticket_violation', 'ticket_violation.ticket_id', '=', 'tickets.id')
              ->whereIn('ticket_violation.violation_id', $violationIds);
        }

        // counts
        $paid   = (clone $q)->where('status_id', 2)->count();
        $unpaid = (clone $q)->where('status_id', 1)->count();

        // monthly counts across the selected range
        $monthly = (clone $q)
            ->selectRaw('DATE_FORMAT(issued_at, "%Y-%m") as ym, COUNT(*) as c')
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('c','ym');

        // build continuous month labels between from..to
        $labels = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $end    = Carbon::parse($to)->startOfMonth();
        while ($cursor <= $end) {
            $labels[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }
        $monthlySeries = collect($labels)->mapWithKeys(fn($ym)=>[$ym => (int)($monthly[$ym] ?? 0)]);

        // hotspots (by lat/lng)
        $hotspots = (clone $q)
            ->whereNotNull('tickets.latitude')
            ->whereNotNull('tickets.longitude')
            ->selectRaw('COALESCE(tickets.location,"Unknown") AS area, tickets.latitude, tickets.longitude, COUNT(*) AS c')
            ->groupBy('area','tickets.latitude','tickets.longitude')
            ->orderByDesc('c')
            ->get();

        return response()->json([
            'paid'           => (int)$paid,
            'unpaid'         => (int)$unpaid,
            'monthlyCounts'  => $monthlySeries, // keys = 'YYYY-MM'
            'hotspots'       => $hotspots,
        ]);
    }

     // NEW: tickets listing for a clicked hotspot (exact lat/lng match)
    public function hotspotTickets(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        [$from, $to] = $this->parseDateRange($request->input('from'), $request->input('to'));
        $violationIds = collect($request->input('violations', []))->filter()->map('intval')->values();

        $q = DB::table('tickets')
            ->whereBetween('issued_at', [$from, $to])
            ->where('latitude', $request->lat)
            ->where('longitude', $request->lng)
            ->select('id','issued_at','location','status_id');

        if ($violationIds->isNotEmpty() && Schema::hasTable('ticket_violation')) {
            $q->join('ticket_violation', 'ticket_violation.ticket_id', '=', 'tickets.id')
              ->whereIn('ticket_violation.violation_id', $violationIds);
        }

        $rows = $q->orderByDesc('issued_at')->limit(50)->get()
            ->map(function($r){
                return [
                    'id'        => $r->id,
                    'issued_at' => Carbon::parse($r->issued_at)->format('Y-m-d H:i'),
                    'location'  => $r->location ?? 'Unknown',
                    'status'    => (int)$r->status_id === 2 ? 'Paid' : 'Unpaid',
                ];
            });

        return response()->json(['tickets' => $rows]);
    }

    // helper: accept "YYYY-MM" or "YYYY-MM-DD" for from/to; defaults last 3 months
    protected function parseDateRange(?string $from, ?string $to): array
    {
        $defaultTo   = Carbon::now()->endOfDay();
        $defaultFrom = (clone $defaultTo)->copy()->subMonths(2)->startOfMonth(); // last 3 months window

        $f = $from ? (strlen($from)===7 ? Carbon::parse($from.'-01')->startOfMonth() : Carbon::parse($from)->startOfDay()) : $defaultFrom;
        $t = $to   ? (strlen($to)===7   ? Carbon::parse($to.'-01')->endOfMonth()  : Carbon::parse($to)->endOfDay())   : $defaultTo;

        return [$f, $t];
    }

    public function download($format)
    {
        // Basic counts
        $paid   = DB::table('tickets')->where('status_id', 2)->count();
        $unpaid = DB::table('tickets')->where('status_id', 1)->count();
        $total  = $paid + $unpaid;

        // Hotspots by named location (used for Excel + suggestions)
        $hotspots = DB::table('tickets')
            ->selectRaw('location AS area, COUNT(*) AS c')
            ->groupBy('location')
            ->orderByDesc('c')
            ->get();

        // --- Time window: current vs previous month
        $startThis = Carbon::now()->startOfMonth();
        $endThis   = Carbon::now()->endOfMonth();
        $startPrev = Carbon::now()->subMonth()->startOfMonth();
        $endPrev   = Carbon::now()->subMonth()->endOfMonth();

        $countThisMonth = DB::table('tickets')
            ->whereBetween('issued_at', [$startThis, $endThis])
            ->count();

        $countPrevMonth = DB::table('tickets')
            ->whereBetween('issued_at', [$startPrev, $endPrev])
            ->count();

        $trendPct = ($countPrevMonth > 0)
            ? round((($countThisMonth - $countPrevMonth) / $countPrevMonth) * 100, 1)
            : null;

        // Peak day of week this month
        $peakDay = DB::table('tickets')
            ->selectRaw('DAYNAME(issued_at) as d, COUNT(*) as c')
            ->whereBetween('issued_at', [$startThis, $endThis])
            ->groupBy('d')->orderByDesc('c')->first();

        // Peak hour this month
        $peakHour = DB::table('tickets')
            ->selectRaw('HOUR(issued_at) as h, COUNT(*) as c')
            ->whereBetween('issued_at', [$startThis, $endThis])
            ->groupBy('h')->orderByDesc('c')->first();

        if ($format === 'xlsx') {
            return Excel::download(
                new \App\Exports\SimpleStatsExport(compact('paid','unpaid','hotspots')),
                'POSO Monthly Excel Report.xlsx'
            );
        }

        // --- Build smarter suggestions
        $lines = [];

        if ($hotspots->count() > 0) {
            $top = $hotspots->take(3)->map(fn($h) => "{$h->area} ({$h->c})")->implode(', ');
            $lines[] = "Hotspots this month: {$top}. Prioritize visible patrols and random checkpoints in these areas.";
        }

        if (!is_null($trendPct)) {
            $direction = $trendPct > 0 ? 'increased' : ($trendPct < 0 ? 'decreased' : 'remained stable');
            $lines[] = "Total tickets {$direction} by ".abs($trendPct)." % compared to last month ({$countPrevMonth} → {$countThisMonth}). Adjust deployment accordingly.";
        }

        if ($peakDay) {
            $lines[] = "Highest volume day: {$peakDay->d}. Schedule more enforcers and spot checks on {$peakDay->d}s.";
        }

        if ($peakHour) {
            $hour = str_pad($peakHour->h, 2, '0', STR_PAD_LEFT) . ":00";
            $lines[] = "Peak time window: around {$hour}. Intensify monitoring during this hour block.";
        }

        if ($total > 0) {
            $unpaidPct = round(($unpaid / $total) * 100, 1);
            $lines[] = "Unpaid rate is {$unpaidPct} % ({$unpaid}/{$total}). Consider SMS/email reminders and a 7-day follow-up workflow.";
        }

        // Fallback line if somehow empty
        if (empty($lines)) {
            $lines[] = "Maintain current patrol levels; no significant trends detected this month.";
        }

        $suggestions = implode("\n\n", $lines);

        // Word (.docx)
        $template = new TemplateProcessor(resource_path('reports/template.docx'));
        $template->setValue('paid',   $paid);
        $template->setValue('unpaid', $unpaid);
        $template->setValue('suggestions', $suggestions);

        $tmpFile = tempnam(sys_get_temp_dir(), 'rpt').'.docx';
        $template->saveAs($tmpFile);

        return response()
            ->download($tmpFile, 'POSO Monthly Word Report.docx')
            ->deleteFileAfterSend();
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
}
