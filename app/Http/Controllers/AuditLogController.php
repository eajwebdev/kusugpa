<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class AuditLogController extends Controller
{
    public function index(Request $request)
{
    // Server-side pagination - only load what's needed
    $perPage = 100; // Load 100 records per page for client-side filtering
    
    $query = AuditLog::with('user')->orderBy('created_at', 'desc');
    
    // Apply server-side filters if provided
    if ($request->date_from) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->date_to) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }
    
    $logs = $query->paginate($perPage);
    
    // Get unique modules and actions for filter dropdowns (from all logs, not just current page)
    $modules = AuditLog::select('module')->distinct()->pluck('module');
    $actions = AuditLog::select('action')->distinct()->pluck('action');

    // Stats from all logs
    $stats = [
        'total' => AuditLog::count(),
        'today' => AuditLog::whereDate('created_at', today())->count(),
        'logins' => AuditLog::where('action', 'login')->count(),
        'errors' => AuditLog::where('action', 'error')->count(),
    ];

    return view('audit-logs.index', compact('logs', 'modules', 'actions', 'stats'));
}

// API endpoint for loading more logs
public function loadMore(Request $request)
{
    $perPage = 100;
    $page = $request->get('page', 1);
    
    $query = AuditLog::with('user')->orderBy('created_at', 'desc');
    
    if ($request->date_from) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }
    if ($request->date_to) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }
    
    $logs = $query->paginate($perPage, ['*'], 'page', $page);
    
    return response()->json([
        'data' => $logs->items(),
        'current_page' => $logs->currentPage(),
        'last_page' => $logs->lastPage(),
        'total' => $logs->total(),
    ]);
}

    public function show(AuditLog $log)
    {
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
        $query = AuditLog::with('user')->orderBy('created_at', 'desc');

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->module && $request->module !== 'all') {
            $query->where('module', $request->module);
        }

        $logs = $query->take(500)->get();

        $pdf = Pdf::loadView('pdf.audit-logs', [
            'logs' => $logs,
            'dateFrom' => $request->date_from,
            'dateTo' => $request->date_to,
            'generatedDate' => now()->format('F d, Y H:i:s'),
        ])->setPaper('a4', 'landscape');

        $filename = 'audit-logs-' . now()->format('Y-m-d') . '.pdf';
        return $request->boolean('preview') ? $pdf->stream($filename) : $pdf->download($filename);
    }

    public function clear(Request $request)
    {
        $days = $request->get('days', 30);
        $date = Carbon::now()->subDays($days);
        
        $count = AuditLog::where('created_at', '<', $date)->delete();
        
        return response()->json([
            'message' => "Cleared {$count} audit logs older than {$days} days."
        ]);
    }
}
