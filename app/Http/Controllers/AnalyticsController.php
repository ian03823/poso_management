<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Exports\SimpleStatsExport;
use Maatwebsite\Excel\Facades\Excel;

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
    public function latest()
    {
        // 1) Paid / Unpaid totals
        $paid   = DB::table('tickets')->where('status_id', 2)->count(); // 2 = paid
        $unpaid = DB::table('tickets')->where('status_id', 1)->count(); // 1 = unpaid/pending

        // 2) Monthly counts for the current year
        $monthlyRaw = DB::table('tickets')
            ->selectRaw('MONTH(issued_at) as month, count(*) as c')
            ->whereYear('issued_at', now()->year)
            ->groupBy('month')
            ->pluck('c','month')
            ->toArray();
        // normalize months 1–12
        $monthly = collect(range(1,12))
            ->mapWithKeys(fn($m) => [date('F', mktime(0, 0, 0, $m, 10)) => $monthlyRaw[$m] ?? 0]);

        $hotspots = DB::table('tickets')
            ->selectRaw('latitude, longitude, COUNT(*) AS c')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->groupBy('latitude', 'longitude')
            ->get();

        return response()->json([
          'paid'           => $paid,
          'unpaid'         => $unpaid,
          'monthlyCounts'  => $monthly,
          'hotspots'       => $hotspots,
        ]);
    }
    public function download($format)
    {
        // Gather summary data
        $paid   = DB::table('tickets')->where('status_id', 2)->count();
        $unpaid = DB::table('tickets')->where('status_id', 1)->count();
        $hotspots = DB::table('tickets')
                ->selectRaw('location AS area, COUNT(*) AS c')
                ->groupBy('location')
                ->orderByDesc('c')
                ->get();

        if ($format === 'xlsx') {
            return Excel::download(
              new SimpleStatsExport(compact('paid','unpaid','hotspots')),
              'POSO Monthly Excel Report.xlsx'
            );
        }

        // Word (.docx)
        $template = new TemplateProcessor(resource_path('reports/template.docx'));
        $template->setValue('paid',   $paid);
        $template->setValue('unpaid', $unpaid);

        // build suggestions
        $s = '';
        foreach ($hotspots->take(3) as $h) {
            $s .= "Area {$h->area} has {$h->c} violations; recommend increasing patrols.\n\n";
        }
        $template->setValue('suggestions', trim($s));

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
