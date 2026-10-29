<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class AuditLogController extends Controller
{
    public const MIN_RETENTION_DAYS = 90;

    public function index(Request $request)
{
    // Server-side pagination - only load what's needed
    $perPage = 25; // the page shows the totals; the table loads its rows in batches from loadMore()
    
    $query = AuditLog::visibleTo($request->user())->with('user')->orderBy('created_at', 'desc');
    
    // Apply server-side filters if provided
    if ($request->date_from) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->date_to) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }
    
    $logs = $query->paginate($perPage);
    
    // Get unique modules and actions for filter dropdowns (from all logs, not just current page)
    $modules = AuditLog::visibleTo($request->user())->distinct()->pluck('module');
    $actions = AuditLog::visibleTo($request->user())->distinct()->pluck('action');

    // Stats from all logs
    $stats = [
        'total' => AuditLog::visibleTo($request->user())->count(),
        'today' => AuditLog::visibleTo($request->user())->whereDate('created_at', today())->count(),
        'logins' => AuditLog::visibleTo($request->user())->where('action', 'login')->count(),
        'errors' => AuditLog::visibleTo($request->user())->where('action', 'error')->count(),
    ];

    // Crop year / week picker: a week fills the From / To dates with its start and end
    $weeks = \App\Models\WeekNo::orderByRaw('CAST(week_no AS UNSIGNED) ASC')
        ->get(['crop_year', 'week_no', 'week_start_date', 'week_end_date'])
        ->filter(fn ($w) => $w->week_start_date && $w->week_end_date)
        ->map(fn ($w) => [
            'crop_year' => (string) $w->crop_year,
            'week_no' => (string) $w->week_no,
            'start' => substr((string) $w->week_start_date, 0, 10),
            'end' => substr((string) $w->week_end_date, 0, 10),
        ])
        ->unique(fn ($w) => $w['crop_year'] . '|' . $w['week_no'])
        ->values();

    return view('audit-logs.index', compact('logs', 'modules', 'actions', 'stats', 'weeks'));
}

// API endpoint for loading more logs
/**
 * Logs for the scrolling table, 100 at a time; every filter runs in the database
 */
public function loadMore(Request $request)
{
    $query = AuditLog::visibleTo($request->user())->with('user');

    if ($request->date_from) {
        $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
    }
    if ($request->date_to) {
        $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
    }
    if ($request->module && $request->module !== 'all') {
        $query->where('module', $request->module);
    }
    if ($request->action && $request->action !== 'all') {
        $query->where('action', $request->action);
    }
    if ($search = trim((string) $request->search)) {
        $query->where(fn ($q) => $q->where('description', 'like', "%{$search}%")
            ->orWhere('user_name', 'like', "%{$search}%")
            ->orWhere('ip_address', 'like', "%{$search}%")
            ->orWhere('module', 'like', "%{$search}%")
            ->orWhere('action', 'like', "%{$search}%"));
    }

    return \App\Support\TableFeed::respond($request, $query, ['created_at' => 'created_at'], [['created_at', 'desc']]);
}

    public function show(Request $request, int $log)
    {
        $log = AuditLog::visibleTo($request->user())->findOrFail($log);

        return response()->json([
            'id' => $log->id,
            'user_name' => $log->user_name,
            'user_role' => $log->user_role,
            'action' => $log->action,
            'module' => $log->module,
            'description' => $log->description,
            'details' => $log->details,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at->format('M d, Y H:i:s'),
        ]);
    }

    public function exportPDF(Request $request)
    {
        $query = AuditLog::visibleTo($request->user())->with('user')->orderBy('created_at', 'desc');

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->module && $request->module !== 'all') {
            $query->where('module', $request->module);
        }

        // Every matching log (no 500-row cap); long periods print in parts of 800, read one part at a time
        $parts = \App\Support\PdfParts::forCount($request, (clone $query)->count(), 800, 'logs');
        if ($parts->wantsInfo($request)) {
            return $parts->info();
        }
        $logs = $query->skip($parts->offset())->take($parts->size())->get();

        $pdf = Pdf::loadView('pdf.audit-logs', [
            'logs' => $logs,
            'partLabel' => $parts->label(),
            'dateFrom' => $request->date_from,
            'dateTo' => $request->date_to,
            'generatedDate' => now()->format('F d, Y H:i:s'),
        ])->setPaper('a4', 'landscape');

        $filename = $parts->filename('audit-logs-' . now()->format('Y-m-d'));
        return $request->boolean('preview') ? $pdf->stream($filename) : $pdf->download($filename);
    }

    public function clear(Request $request)
    {
        abort_unless($request->user()->role === 'Administrator', 403);

        // The last 90 days are always kept, and clearing is itself recorded, so the trail cannot be wiped quietly
        $days = $request->validate(['days' => ['sometimes', 'integer', 'min:' . self::MIN_RETENTION_DAYS, 'max:3650']])['days'] ?? self::MIN_RETENTION_DAYS;
        $date = Carbon::now()->subDays($days);

        $count = AuditLog::where('created_at', '<', $date)->delete();

        AuditLog::log('delete', 'audit_logs', "Cleared {$count} audit logs older than {$days} days", [
            'older_than' => $date->toDateTimeString(),
            'deleted' => $count,
        ]);

        return response()->json([
            'message' => "Cleared {$count} audit logs older than {$days} days."
        ]);
    }
}
