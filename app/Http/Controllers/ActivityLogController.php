<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    //
    public function index(Request $request)
    {
        // Map short actor types to FQCNs for filtering
        $actorTypeMap = [
            'admin'    => \App\Models\Admin::class,
            'enforcer' => \App\Models\Enforcer::class,
        ];
        $actorTypeFqcn = $actorTypeMap[$request->get('actor_type')] ?? null;

        $logs = ActivityLog::query()
            ->with(['actor','subject'])
            ->action($request->get('action'))
            ->actorType($actorTypeFqcn)
            ->dateFrom($request->get('date_from'))
            ->dateTo($request->get('date_to'))
            ->search($request->get('q'))
            ->latest('created_at')
            ->paginate(5)
            ->withQueryString();

        return view('admin.logs.activity', [
            'logs' => $logs,
            'filters' => [
                'action'     => $request->get('action', ''),
                'actor_type' => $request->get('actor_type', ''),
                'date_from'  => $request->get('date_from', ''),
                'date_to'    => $request->get('date_to', ''),
                'q'          => $request->get('q', ''),
            ],
        ]);
    }
    public function ticketRangeRequests(Request $request)
    {
        // how far back to look (in minutes), default 2 days
        $minutes = (int) $request->get('minutes', 60 * 48);
        $since   = now()->subMinutes($minutes);

        $logs = ActivityLog::with(['actor', 'subject'])
            ->where('event', 'ticket_range.requested')
            ->where('created_at', '>=', $since)
            ->latest('created_at')
            ->limit(10)
            ->get();

        $items = $logs->map(function ($log) {
            $enforcer = $log->subject; // we logged: LogActivity::on($enforcer)…
            $props    = (array) ($log->properties ?? []);

            $badge = optional($enforcer)->badge_num ?? ($props['badge_num'] ?? null);
            $name  = trim(implode(' ', array_filter([
                optional($enforcer)->fname,
                optional($enforcer)->mname,
                optional($enforcer)->lname,
            ])));

            return [
                'id'            => $log->id,
                'created_at'    => $log->created_at->timezone('Asia/Manila')->format('Y-m-d H:i'),
                'enforcer_id'   => optional($enforcer)->id ?? ($props['enforcer_id'] ?? null),
                'badge_num'     => $badge,
                'enforcer_name' => $name ?: 'Unknown enforcer',
            ];
        });

        return response()->json([
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

}
