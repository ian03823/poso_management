<?php

namespace App\Http\Controllers;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Carbon\Carbon;
use App\Exports\PosoRegisterWorkbookExport;
use Illuminate\Support\Str;
// Excel export
use App\Exports\FormalStatsExport;
use Maatwebsite\Excel\Facades\Excel;

// PhpWord (build from scratch)
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Shared\Converter;

class AnalyticsController extends Controller
{
    /* ----------------
     * Dashboard views
     * ---------------- */
    public function index()
    {
        $latestTicket = \App\Models\Ticket::latest()->first();
        $defaultLat   = $latestTicket->latitude  ?? 15.9285;
        $defaultLng   = $latestTicket->longitude ?? 120.3487;

        $violationOptions = \App\Models\Violation::select('id','violation_name')
            ->orderBy('violation_name')
            ->limit(12)
            ->get();

        return view('admin.analytics.index', compact('defaultLat','defaultLng','violationOptions'));
    }
    
    public function latest(Request $request)
    {
        [$from, $to] = $this->parseDateRange($request->input('from'), $request->input('to'));
        $violationIds = collect($request->input('violations', []))->filter()->map('intval')->values();
        $status = $request->string('status')->lower()->toString(); // 'paid' | 'unpaid' | ''

        $q = DB::table('tickets')->whereBetween('issued_at', [$from, $to]);

        if ($status === 'pending') $q->where('status_id', 1);
        if ($status === 'paid')   $q->where('status_id', 2);
        if ($status === 'unpaid') $q->where('status_id', 3);
        if ($status === 'cancelled') $q->where('status_id', 4);

        if ($violationIds->isNotEmpty() && Schema::hasTable('ticket_violation')) {
            $q->join('ticket_violation', 'ticket_violation.ticket_id', '=', 'tickets.id')
              ->whereIn('ticket_violation.violation_id', $violationIds);
        }

        $paid   = (clone $q)->where('status_id', 2)->count();
        $unpaid = (clone $q)->where('status_id', 3)->count();

        $monthly = (clone $q)
            ->selectRaw('DATE_FORMAT(issued_at, "%Y-%m") as ym, COUNT(*) as c')
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('c','ym');

        $labels = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $end    = Carbon::parse($to)->startOfMonth();
        while ($cursor <= $end) {
            $labels[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }
        $monthlySeries = collect($labels)->mapWithKeys(fn($ym)=>[$ym => (int)($monthly[$ym] ?? 0)]);

        $hotspots = (clone $q)
            ->whereNotNull('tickets.latitude')
            ->whereNotNull('tickets.longitude')
            ->selectRaw('COALESCE(tickets.location,"Unknown") AS area, tickets.latitude, tickets.longitude, COUNT(*) AS c')
            ->groupBy('area','tickets.latitude','tickets.longitude')
            ->orderByDesc('c')
            ->get();

        $insights = $this->buildInsights([
            'paid'     => (int)$paid,
            'unpaid'   => (int)$unpaid,
            'monthly'  => $monthlySeries,
            'hotspots' => $hotspots,
        ]);

        return response()->json([
            'paid'           => (int)$paid,
            'unpaid'         => (int)$unpaid,
            'monthlyCounts'  => $monthlySeries,
            'hotspots'       => $hotspots,
            'insights'       => $insights,
        ]);
    }
      /**
 * Smart, non-repeating recommendations based on hotspot count.
 */
private function smartSuggestion(string $place, int $count, array &$used): string
{
    // Tiered pools by density
    $low = [
        "Increase visibility with periodic patrols during peak hours.",
        "Coordinate with barangay officials for community reminders.",
        "Place temporary warning signage to nudge driver compliance.",
        "Conduct short awareness talks with nearby establishments.",
    ];
    $mid = [
        "Schedule randomized checkpoints in alternating time blocks.",
        "Deploy a mobile team for moving violations along approach roads.",
        "Use targeted SMS or social posts about active enforcement in the area.",
        "Coordinate with traffic aides to optimize lane management.",
    ];
    $high = [
        "Implement sustained checkpoint operations for a week, then reassess.",
        "Propose a permanent signage/road marking refresh and camera coverage.",
        "Assign a fixed post during rush hours and evaluate monthly impact.",
        "Coordinate multi-agency operation (POSO/PNP/LTO) for deterrence.",
    ];

    $pool = $count >= 10 ? array_merge($high, $mid, $low)
          : ($count >= 4  ? array_merge($mid, $low)
                          : $low);

    foreach ($pool as $s) {
        if (!in_array($s, $used, true)) {
            $used[] = $s;
            return $s;
        }
    }

    // Fallback (still unique per place)
    $fallback = "Increase patrols and checkpoint presence near {$place}, then review after 2 weeks.";
    $used[] = $fallback;
    return $fallback;
}

    // Clicked-hotspot ticket list
    public function hotspotTickets(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        [$from, $to] = $this->parseDateRange($request->input('from'), $request->input('to'));
        $status = strtolower((string)$request->input('status', ''));
        $violationIds = collect($request->input('violations', []))->filter()->map('intval')->values();

        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $radiusKm = 0.15; // 150 m

        $q = DB::table('tickets')
            ->whereBetween('tickets.issued_at', [$from, $to])
            ->whereNotNull('tickets.latitude')
            ->whereNotNull('tickets.longitude')
            ->whereRaw("
                (6371 * 2 * ASIN(
                    SQRT(
                        POWER(SIN(RADIANS(? - tickets.latitude) / 2), 2) +
                        COS(RADIANS(?)) * COS(RADIANS(tickets.latitude)) *
                        POWER(SIN(RADIANS(? - tickets.longitude) / 2), 2)
                    )
                )) <= ?
            ", [$lat, $lat, $lng, $radiusKm])
            ->leftJoin('violators', 'violators.id', '=', 'tickets.violator_id')
            ->leftJoin('vehicles',  'vehicles.vehicle_id', '=', 'tickets.vehicle_id')
            ->select([
                'tickets.id',
                'tickets.issued_at',
                'tickets.status_id',
                'violators.first_name',
                'violators.middle_name',
                'violators.last_name',
                'vehicles.plate_number',
                'vehicles.vehicle_type',
                'vehicles.owner_name',
                'vehicles.is_owner',
            ]);

        if ($status === 'paid')   $q->where('tickets.status_id', 2);
        if ($status === 'unpaid') $q->where('tickets.status_id', 1);

        if ($violationIds->isNotEmpty() && Schema::hasTable('ticket_violation')) {
            $q->join('ticket_violation', 'ticket_violation.ticket_id', '=', 'tickets.id')
              ->whereIn('ticket_violation.violation_id', $violationIds);
        }

        $rows = $q->orderByDesc('tickets.issued_at')
            ->limit(100)
            ->get()
            ->map(function ($r) {
                $name = trim(collect([$r->first_name, $r->middle_name, $r->last_name])
                    ->filter(fn($p) => $p && trim($p) !== '')
                    ->implode(' '));

                $vehBits = [];
                if (!empty($r->vehicle_type)) $vehBits[] = $r->vehicle_type;
                if (!is_null($r->is_owner))   $vehBits[] = 'Owner: '.((int)$r->is_owner === 1 ? 'Yes' : 'No');
                if (!empty($r->owner_name))   $vehBits[] = 'Owner Name: '.$r->owner_name;
                $vehMeta = $vehBits ? ' ('.implode(' • ', $vehBits).')' : '';
                $vehicle = ($r->plate_number ?: 'N/A') . $vehMeta;

                return [
                    'id'        => (int) $r->id,
                    'name'      => $name !== '' ? $name : 'Unknown',
                    'vehicle'   => $vehicle,
                    'issued_at' => \Carbon\Carbon::parse($r->issued_at)->format('Y-m-d H:i'),
                    'status'    => ((int)$r->status_id === 2 ? 'Paid' : 'Unpaid'),
                ];
            });

        return response()->json(['tickets' => $rows]);
    }

    // helper: accept "YYYY-MM" or "YYYY-MM-DD"; default last 3 months
    protected function parseDateRange(?string $from, ?string $to): array
    {
        $defaultTo   = Carbon::now()->endOfDay();
        $defaultFrom = (clone $defaultTo)->copy()->subMonths(2)->startOfMonth();

        $f = $from ? (strlen($from)===7 ? Carbon::parse($from.'-01')->startOfMonth() : Carbon::parse($from)->startOfDay()) : $defaultFrom;
        $t = $to   ? (strlen($to)===7   ? Carbon::parse($to.'-01')->endOfMonth()  : Carbon::parse($to)->endOfDay())   : $defaultTo;

        return [$f, $t];
    }

    /* =========================
     *  EXPORTS
     * ========================= */

    // Shared data (for both Excel & Word)
    private function gatherAnalyticsData(Request $request): array
    {
        // --- robust date parsing like your API ---
    [$from, $to] = (function (?string $f, ?string $t): array {
        $defaultTo   = \Carbon\Carbon::now()->endOfDay();
        $defaultFrom = (clone $defaultTo)->copy()->subMonths(2)->startOfMonth();

        $F = $f
            ? (strlen($f) === 7
                ? \Carbon\Carbon::parse($f . '-01')->startOfMonth()
                : \Carbon\Carbon::parse($f)->startOfDay())
            : $defaultFrom;

        $T = $t
            ? (strlen($t) === 7
                ? \Carbon\Carbon::parse($t . '-01')->endOfMonth()
                : \Carbon\Carbon::parse($t)->endOfDay())
            : $defaultTo;

        return [$F, $T];
    })($request->input('from'), $request->input('to'));

    $viocode = $request->input('violation');
    $area    = $request->input('area');

    // status names map, e.g. [1=>'pending', 2=>'paid', 3=>'unpaid', 4=>'cancelled']
    $statusMap = \App\Models\TicketStatus::pluck('name','id')
        ->map(fn($v)=>strtolower($v))->toArray();

    $paidStatusId   = array_search('paid',   $statusMap, true) ?: null;
    $unpaidStatusId = array_search('unpaid', $statusMap, true) ?: null;

    // ---------- BASE: only date/violation/area (NO status filter) ----------
    $base = \App\Models\Ticket::query()
        ->whereBetween('issued_at', [$from, $to]);

    if ($viocode) {
        $code = trim($viocode);
        $base->where(function ($qq) use ($code) {
            $qq->whereJsonContains('violation_codes', $code)
               ->orWhereHas('violations', function ($v) use ($code) {
                   // your pivot uses violations.id (numeric), but we allow code or name in filter
                   $v->where('violation_code', $code)
                     ->orWhere('violation_name', 'like', "%{$code}%");
               });
        });
    }
    if ($area) {
        $base->where('location', 'like', "%{$area}%");
    }

    // minimal columns for counting + hotspots
    $rows = $base->get(['id','location','latitude','longitude','status_id']);

    // ---------- TOTALS ----------
    $totalAll = $rows->count();
    $paid     = $paidStatusId   ? $rows->where('status_id', $paidStatusId)->count()   : 0;
    $unpaid   = $unpaidStatusId ? $rows->where('status_id', $unpaidStatusId)->count() : 0;

    // ---------- REVENUE (sum fines of violations for PAID tickets only) ----------
    // IMPORTANT: pivot links to violations.ID (numeric), not violation_code
    $revQuery = \App\Models\Ticket::query()
        ->whereBetween('issued_at', [$from, $to]);

    if ($viocode) {
        $code = trim($viocode);
        $revQuery->where(function ($qq) use ($code) {
            $qq->whereJsonContains('violation_codes', $code)
               ->orWhereHas('violations', function ($v) use ($code) {
                   $v->where('violation_code',$code)
                     ->orWhere('violation_name','like',"%{$code}%");
               });
        });
    }
    if ($area) {
        $revQuery->where('location','like',"%{$area}%");
    }
    if ($paidStatusId) {
        $revQuery->where('status_id',$paidStatusId);
    }

    $revenue = $revQuery
        ->join('ticket_violation', 'ticket_violation.ticket_id', '=', 'tickets.id')
        ->join('violations', 'violations.id', '=', 'ticket_violation.violation_id') // <-- FIXED
        ->sum('violations.fine_amount');

    // ---------- HOTSPOTS (status-agnostic; objects to avoid array/object mixups) ----------
    $hotspots = $rows
        ->map(function ($t) {
            $place = trim((string)($t->location ?? ''));
            if ($place === '') {
                if (!is_null($t->latitude) && !is_null($t->longitude)) {
                    $place = sprintf('%.5f, %.5f', $t->latitude, $t->longitude);
                } else {
                    $place = 'Unknown';
                }
            }
            return (object) ['area' => $place, 'c' => 1];
        })
        ->groupBy('area')
        ->map(fn ($g) => (object)['area' => $g->first()->area, 'c' => $g->count()])
        ->sortByDesc('c')
        ->values()
        ->take(3)
        ->all();

    // filters line for header
    $filters = [];
    $filters[] = 'Date: '.$from->format('Y-m-d').' to '.$to->format('Y-m-d');
    if ($viocode) $filters[] = "Violation: {$viocode}";
    if ($area)    $filters[] = "Area: {$area}";
    $filtersLine = implode('    •    ', $filters);

    return [
        'total_all'    => $totalAll,
        'paid'         => $paid,
        'unpaid'       => $unpaid,
        'revenue'      => (float)$revenue,
        'hotspots'     => $hotspots, // objects {area, c}
        'generated_on' => now()->format('F d, Y — h:i A'),
        'filters_line' => $filtersLine,
    ];
    }
    // Excel (XLSX) – unchanged (your exporter)
    public function downloadRegisterExcel(Request $request)
    {
        // Parse date range (month-aware)
        [$from, $to] = $this->parseDateRange($request->input('from'), $request->input('to'));

        // Pull tickets in range + eager load needed relations
        $tickets = \App\Models\Ticket::with(['violator', 'violations', 'paidTickets', 'status'])
            ->whereBetween('issued_at', [$from, $to])
            ->orderBy('issued_at')
            ->get();

        // Normalize each ticket to a flat row for the sheet
        $rows = $tickets->map(function ($t) {
            // driver name (first + middle + last, fallback to username/name)
            $v = $t->violator;
            $driver = trim(collect([
                optional($v)->first_name,
                optional($v)->middle_name,
                optional($v)->last_name,
            ])->filter()->implode(' '));
            if ($driver === '' && $v) {
                $driver = $v->name ?: $v->username ?: 'Unknown';
            }

            // address
            $address = optional($v)->address ?: '';

            // violations as readable names (uses your accessor if available)
            $violations = method_exists($t, 'getViolationNamesAttribute')
                ? ($t->violation_names ?? '')
                : $t->violations->pluck('violation_name')->implode(', ');

            // paid info (take latest payment if many)
            $payment   = $t->paidTickets->sortByDesc('created_at')->first();
            $orNumber  = $payment->or_number ?? null;        // adjust field if different
            $amount    = $payment->amount    ?? null;        // adjust field if different

            return [
                'ym'         => \Carbon\Carbon::parse($t->issued_at)->format('Y-m'),
                'date'       => \Carbon\Carbon::parse($t->issued_at),
                'driver'     => $driver,
                'address'    => $address,
                'tct'        => $t->ticket_number,
                'violations' => $violations,
                'or_number'  => $orNumber,
                'amount'     => $amount,
                'status'     => optional($t->status)->name ?? '',
            ];
        });

        // Group rows by month (YYYY-MM)
        $byMonth = $rows->groupBy('ym');

        // Build and download workbook
        $file = 'POSO_Register_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new PosoRegisterWorkbookExport($byMonth->all()), $file);
    }
    public function downloadPdf(Request $request): HttpResponse
    {
         $data = $this->gatherAnalyticsData($request);

        // build top-3 rows with smart suggestions (status-agnostic)
        $used = [];
        $top3 = [];
        foreach ($data['hotspots'] as $h) {
            $place = $h->area ?? 'Unknown';
            $cnt   = (int)($h->c ?? 0);
            $top3[] = [
                'place' => $place,
                'count' => $cnt,
                'reco'  => $this->smartSuggestion($place, $cnt, $used),
            ];
        }

        $viewData = [
            'title'        => 'Public Order and Safety Office (POSO) San Carlos City, Pangasinan.',
            'generated_on' => $data['generated_on'],
            'filters_line' => $data['filters_line'],
            'total_all'    => $data['total_all'],
            'paid'         => $data['paid'],
            'unpaid'       => $data['unpaid'],
            'revenue'      => number_format($data['revenue'], 2),    // format for display
            'top3'         => $top3,
            // DomPDF needs absolute file path prefixed with file://
            'logo_url'     => 'file://' . public_path('images/POSO-Logo.png'),
            // Blade condition:
            'logo_exists'  => file_exists(public_path('images/POSO-Logo.png')),
        ];

        $pdf  = Pdf::loadView('admin.analytics.report', $viewData)->setPaper('a4','portrait');
        $file = 'POSO_Analytics_' . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($file);
    }

    // Word (DOCX) – robust builder (NO watermark API, NO template processing)
    public function download(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        // Kill any previous buffers
        while (ob_get_level() > 0) { ob_end_clean(); }

        // Safe temp + zip impl
        $tmp = storage_path('app/tmp');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($tmp);
        \PhpOffice\PhpWord\Settings::setTempDir($tmp);
        \PhpOffice\PhpWord\Settings::setZipClass(\PhpOffice\PhpWord\Settings::PCLZIP);

        // Gather data (may emit notices if something is odd) — we’ll buffer-protect the build below
        $data = $this->gatherAnalyticsData($request);

        $withLogo = ! $request->boolean('nologo');

        // ---- Guard against ANY stray output during build ----
        ob_start();
        try {
            $path = $this->buildDocx($data, $withLogo, $tmp);
            $garbage = ob_get_clean(); // swallow anything that was echoed
            if ($garbage !== '') {
                // If anything was printed, rebuild a minimal doc (to be 100% clean)
                $path = $this->buildFallbackDocx($tmp, 'Output noise was suppressed; delivering clean file.');
            }
        } catch (\Throwable $e) {
            ob_end_clean(); // discard buffer
            // Last-resort minimal doc the user can always open
            $path = $this->buildFallbackDocx($tmp, 'An error occurred while composing the report. A minimal summary has been generated.');
        }

        clearstatcache(true, $path);
        return response()->download(
            $path,
            basename($path),
            [
                'Content-Type'   => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Length' => (string)filesize($path),
                'X-Content-Type-Options' => 'nosniff',
            ]
        )->deleteFileAfterSend(true);
    }
    private function buildDocx(array $data, bool $withLogo, string $tmp): string
    {
        $pw = new \PhpOffice\PhpWord\PhpWord();
        $pw->addFontStyle('Title', ['name'=>'Calibri','size'=>14,'bold'=>true]);
        $pw->addFontStyle('Sub',   ['name'=>'Calibri','size'=>11]);
        $pw->addFontStyle('H1',    ['name'=>'Calibri','size'=>13,'bold'=>true]);
        $pw->addFontStyle('H2',    ['name'=>'Calibri','size'=>12,'bold'=>true]);
        $pw->addFontStyle('Body',  ['name'=>'Calibri','size'=>11]);
        $pw->addParagraphStyle('Tight', ['spaceAfter'=>0]);
        $pw->addParagraphStyle('After', ['spaceAfter'=>200]);

        $section = $pw->addSection([
            'pageSizeW' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(21.0),
            'pageSizeH' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(29.7),
            'marginTop'    => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.0),
            'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.0),
            'marginLeft'   => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.0),
            'marginRight'  => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.0),
        ]);

        $header = $section->addHeader();
        $header->addText(
        $this->xmlSafe('Public Order and Safety Office (POSO) San Carlos City, Pangasinan.'),
            'Title', ['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $header->addText($this->xmlSafe($data['generated_on']), 'Sub', ['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

        if ($withLogo) {
            $logo = public_path('images/POSO-Logo.png');
            if (is_file($logo)) {
                $section->addImage($logo, [
                    'width'         => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(12),
                    'positioning'   => 'absolute',
                    'posHorizontal' => \PhpOffice\PhpWord\Style\Image::POSITION_HORIZONTAL_CENTER,
                    'posVertical'   => \PhpOffice\PhpWord\Style\Image::POSITION_VERTICAL_CENTER,
                    'wrappingStyle' => 'behind',
                ]);
            }
        }

        $section->addText($this->xmlSafe('ANALYTICS SUMMARY'), 'H1', 'After');
        $section->addText($this->xmlSafe('Filters applied: '.$data['filters_line']), 'Body', 'After');

        $section->addText($this->xmlSafe('Totals'), 'H2', 'After');
        $section->addText($this->xmlSafe('Total Paid Tickets: '.$this->n($data['paid'])), 'Body', 'Tight');
        $section->addText($this->xmlSafe('Total Unpaid Tickets: '.$this->n($data['unpaid'])), 'Body', 'After');

        $section->addText($this->xmlSafe('Top 3 Hotspots & Smart Recommendations:'), 'H2', 'After');
        $used = [];
        foreach (array_slice($data['hotspots'], 0, 3) as $h) {
            $place = $this->xmlSafe($h->area ?? 'Unknown');
            $cnt   = (int)($h->c ?? 0);
            $reco  = $this->xmlSafe($this->smartSuggestion($place, $cnt, $used));
            $section->addText(
                $this->xmlSafe("Hotspot: {$place} — {$cnt} ticket(s). ").$reco,
                'Body',
                'After'
            );
        }

        $file = 'POSO_Analytics_' . now()->format('Ymd_His') . ($withLogo ? '' : '_NOLOGO') . '.docx';
        $path = $tmp . DIRECTORY_SEPARATOR . $file;
        \PhpOffice\PhpWord\IOFactory::createWriter($pw, 'Word2007')->save($path);
        clearstatcache(true, $path);
        return $path;
    }
    private function buildFallbackDocx(string $tmp, string $message): string
    {
        $pw = new \PhpOffice\PhpWord\PhpWord();
        $section = $pw->addSection();
        $pw->addFontStyle('H1',   ['name'=>'Calibri','size'=>13,'bold'=>true]);
        $pw->addFontStyle('Body', ['name'=>'Calibri','size'=>11]);

        $section->addText('POSO Analytics — Fallback Report', 'H1');
        $section->addText($message, 'Body');
        $section->addText('Generated: '.now()->format('F d, Y — h:i A'), 'Body');

        $file = 'POSO_Analytics_' . now()->format('Ymd_His') . '_FALLBACK.docx';
        $path = $tmp . DIRECTORY_SEPARATOR . $file;
        \PhpOffice\PhpWord\IOFactory::createWriter($pw, 'Word2007')->save($path);
        return $path;
    }
    /**
     * Make any input safe for WordprocessingML (UTF-8 + strip invalid XML chars).
     */
    private function xmlSafe(?string $s): string
    {
        if ($s === null) return '';
        // Coerce to UTF-8 from common legacy encodings (Win-1252, ISO-8859-1)
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

        // Remove characters not allowed in XML 1.0
        // allowed: TAB(0x09) LF(0x0A) CR(0x0D) and 0x20..0xD7FF, 0xE000..0xFFFD
        $s = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s);

        // Also strip remaining control chars \x00..\x1F except 09/0A/0D (defensive)
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);

        return $s;
    }

    /** Convenience for numbers shown as text. */
    private function n($v): string { return (string) (is_numeric($v) ? $v : 0); }


    /* ----------------
     * Insights helper
     * ---------------- */
    protected function buildInsights(array $m): array
    {
        $paid   = (int)($m['paid']   ?? 0);
        $unpaid = (int)($m['unpaid'] ?? 0);
        $total  = $paid + $unpaid;

        $monthly   = collect($m['monthly'] ?? []);
        $hotspots  = collect($m['hotspots'] ?? []);
        $insights  = [];

        if ($total === 0) {
            $insights[] = "No tickets in the selected period. Validate filters or data capture.";
        } else {
            $unpaidPct = round(($unpaid / max($total,1)) * 100, 1);
            if ($unpaidPct >= 50) {
                $insights[] = "High unpaid rate at {$unpaidPct}% ({$unpaid}/{$total}). Recommend SMS/email reminders within 72 hours and a 7-day follow-up.";
            } elseif ($unpaidPct >= 25) {
                $insights[] = "Unpaid rate is {$unpaidPct}% ({$unpaid}/{$total}). Consider reminder cadence and on-site payment options.";
            } else {
                $insights[] = "Healthy compliance: unpaid rate {$unpaidPct}% ({$unpaid}/{$total}). Maintain current enforcement and reminders.";
            }
        }

        if ($monthly->isNotEmpty()) {
            $peak = $monthly->sortDesc()->keys()->first();
            if ($peak) {
                $insights[] = "Peak month is {$peak} with {$monthly[$peak]} tickets. Plan staffing and checkpoints around this period.";
            }

            $keys = $monthly->keys()->values();
            $n = $keys->count();
            if ($n >= 2) {
                $lastKey = $keys[$n-1];
                $prevKey = $keys[$n-2];
                $delta   = (int)$monthly[$lastKey] - (int)$monthly[$prevKey];
                if ($delta > 0)      $insights[] = "Upward trend: +{$delta} tickets in {$lastKey} vs {$prevKey}.";
                elseif ($delta < 0)  $insights[] = "Downward trend: {$lastKey} is " . abs($delta) . " lower than {$prevKey}.";
            }
        } else {
            $insights[] = "No monthly series available. Encourage consistent date logging.";
        }

        if ($hotspots->isEmpty()) {
            $insights[] = "No geo-tagged tickets. Encourage capturing location to enable hotspot analysis.";
        } else {
            $top = $hotspots->sortByDesc('c')->take(3)->values();
            $list = $top->map(fn($h) => ($h->area ?? 'Unknown') . " ({$h->c})")->implode(', ');
            $insights[] = "Top hotspots: {$list}. Prioritize patrols and random checkpoints in these areas.";

            if ($top->count() >= 2 && (int)$top[1]->c > 0) {
                $ratio = round((int)$top[0]->c / max(1,(int)$top[1]->c), 2);
                if ($ratio >= 1.5) {
                    $insights[] = "One area dominates (≈{$ratio}× second place). Consider targeted enforcement and community advisories there.";
                }
            }
        }

        if ($total > 0 && $total < 10) {
            $insights[] = "Low ticket volume ({$total}). Validate patrol coverage and reporting discipline.";
        } elseif ($total >= 50) {
            $insights[] = "High workload ({$total} tickets). Ensure printer supplies, device charging, and backup staff rotations.";
        }

        return array_values(array_unique($insights));
    }
    public function docxSmoke(): BinaryFileResponse
{
    // 100% minimal DOCX: if THIS fails, the issue is extra output/BOM/caching.
    while (ob_get_level() > 0) { ob_end_clean(); }
    $tmp = storage_path('app/tmp'); \Illuminate\Support\Facades\File::ensureDirectoryExists($tmp);
    \PhpOffice\PhpWord\Settings::setTempDir($tmp);
    \PhpOffice\PhpWord\Settings::setZipClass(\PhpOffice\PhpWord\Settings::PCLZIP);

    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $section->addText('Hello from POSO — smoke test');

    $file = 'POSO_SMOKE_'.now()->format('His').'.docx';
    $path = $tmp.DIRECTORY_SEPARATOR.$file;
    \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($path);

    clearstatcache(true, $path);
    return response()->download($path, $file, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'Content-Length' => (string)filesize($path),
        'X-Content-Type-Options' => 'nosniff',
    ])->deleteFileAfterSend(true);
}

public function docxLogoSmoke(): BinaryFileResponse
{
    // Same as above, but adds ONLY the logo as behind-text image.
    while (ob_get_level() > 0) { ob_end_clean(); }
    $tmp = storage_path('app/tmp'); \Illuminate\Support\Facades\File::ensureDirectoryExists($tmp);
    \PhpOffice\PhpWord\Settings::setTempDir($tmp);
    \PhpOffice\PhpWord\Settings::setZipClass(\PhpOffice\PhpWord\Settings::PCLZIP);

    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection([
        'pageSizeW' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(21.0),
        'pageSizeH' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(29.7),
    ]);
    $section->addText('Logo smoke test');

    $logo = public_path('images/POSO-Logo.png');
    if (is_file($logo)) {
        $section->addImage($logo, [
            'width'         => \PhpOffice\PhpWord\Shared\Converter::cmToPixel(12),
            'positioning'   => 'absolute',
            'posHorizontal' => \PhpOffice\PhpWord\Style\Image::POSITION_HORIZONTAL_CENTER,
            'posVertical'   => \PhpOffice\PhpWord\Style\Image::POSITION_VERTICAL_CENTER,
            'wrappingStyle' => 'behind',
        ]);
    }

    $file = 'POSO_LOGO_SMOKE_'.now()->format('His').'.docx';
    $path = $tmp.DIRECTORY_SEPARATOR.$file;
    \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($path);

    clearstatcache(true, $path);
    return response()->download($path, $file, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'Content-Length' => (string)filesize($path),
        'X-Content-Type-Options' => 'nosniff',
    ])->deleteFileAfterSend(true);
}
}
