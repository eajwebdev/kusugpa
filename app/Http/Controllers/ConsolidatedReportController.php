<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConsolidatedUpload;
use App\Models\AuditLog;
use App\Support\PdfParts;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class ConsolidatedReportController extends Controller
{
    public function index(Request $request)
{
    AuditLog::log('view', 'reports', 'Viewed consolidated report');
    
    $perPage = $request->get('per_page', 50);
    
    $query = ConsolidatedUpload::query();
    $this->applyFilters($query, $request);
    
    // Apply sorting
    $sortField = $request->get('sort_field', 'planter_name');
    $sortDirection = $request->get('sort_direction', 'asc');
    $allowedFields = ['planter_code', 'planter_name', 'crop_year', 'week_no', 'ta_wt', 'ta_amount', 'emi_wt', 'emi_amount', 'pat_wt', 'pat_amount', 'cci_fa_amt', 'cci_fb_amt', 'cci_fc_amt', 'fuel_issuance_amt', 'rental_amt', 'underload_amt', 'mudpress_amt', 'adj_amt', 'total_summary'];
    
    if (!in_array($sortField, $allowedFields)) {
        $sortField = 'planter_name';
    }
    $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
    
    $uploads = $query->paginate($perPage);
    
    $totals = [
        'total_records' => ConsolidatedUpload::count(),
        'total_ta_amount' => ConsolidatedUpload::sum('ta_amount'),
        'total_emi_amount' => ConsolidatedUpload::sum('emi_amount'),
        'total_pat_amount' => ConsolidatedUpload::sum('pat_amount'),
        'total_fuel' => ConsolidatedUpload::sum('fuel_issuance_amt'),
        'total_rental' => ConsolidatedUpload::sum('rental_amt'),
        'total_underload' => ConsolidatedUpload::sum('underload_amt'),
        'total_mudpress' => ConsolidatedUpload::sum('mudpress_amt'),
        'total_adj' => ConsolidatedUpload::sum('adj_amt'),
        'total_summary' => ConsolidatedUpload::sum('total_summary'),
    ];
    
    // Return JSON for AJAX requests
    if ($request->ajax() || $request->expectsJson()) {
        return response()->json([
            'data' => $uploads->items(),
            'current_page' => $uploads->currentPage(),
            'last_page' => $uploads->lastPage(),
            'total' => $uploads->total(),
            'per_page' => $uploads->perPage(),
        ]);
    }
    
    return view('reports.consolidated', compact('uploads', 'totals'));
}
    
    /**
     * Apply crop year, week range and search filters (same logic as the summary report)
     */
    private function applyFilters($query, Request $request)
    {
        $cropYear = $request->get('crop_year');
        $weekFrom = $request->get('week_from');
        $weekTo = $request->get('week_to');
        $search = $request->get('search');

        if ($cropYear) {
            $query->where('crop_year', $cropYear);
        }
        // week_no is stored as a string, so compare numerically
        if ($weekFrom && $weekTo) {
            $query->whereRaw('CAST(week_no AS UNSIGNED) BETWEEN ? AND ?', [(int) $weekFrom, (int) $weekTo]);
        } elseif ($weekFrom) {
            $query->whereRaw('CAST(week_no AS UNSIGNED) >= ?', [(int) $weekFrom]);
        } elseif ($weekTo) {
            $query->whereRaw('CAST(week_no AS UNSIGNED) <= ?', [(int) $weekTo]);
        }
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('planter_name', 'like', "%{$search}%")
                  ->orWhere('planter_code', 'like', "%{$search}%")
                  ->orWhere('assn_name', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function exportPDF(Request $request)
    {
        $query = ConsolidatedUpload::orderBy('planter_name');
        $this->applyFilters($query, $request);
        
        // Every matching row (no 500-row cap); large lists print in parts of 700
        $uploads = $query->get();
        $parts = PdfParts::make($request, $uploads, 700, 'rows');
        if ($parts->wantsInfo($request)) {
            return $parts->info();
        }

        $preview = $request->boolean('preview');

        AuditLog::log($preview ? 'view' : 'export', 'reports', $preview ? 'Previewed consolidated report PDF' : 'Exported consolidated report PDF', [
            'records' => $uploads->count(),
            'crop_year' => $request->crop_year,
            'weeks' => $request->week_from . ' - ' . $request->week_to,
            'part' => $parts->label() ?: 'whole',
        ]);

        $pdf = Pdf::loadView('pdf.consolidated-report', [
            'uploads' => $parts->items(),
            'totalRows' => $uploads->count(),
            'partLabel' => $parts->label(),
            'generatedDate' => now()->format('F d, Y H:i:s'),
        ])->setPaper('a3', 'landscape');

        $filename = $parts->filename('consolidated-report-' . now()->format('Y-m-d'));

        return $preview ? $pdf->stream($filename) : $pdf->download($filename);
    }


    /**
 * Delete all consolidated records
 */
public function deleteAll(Request $request)
{
    $count = ConsolidatedUpload::count();
    
    if ($count === 0) {
        return response()->json(['message' => 'No records to delete'], 400);
    }
    
    ConsolidatedUpload::truncate();
    
    AuditLog::log('delete', 'consolidated', "Deleted ALL consolidated records ({$count} records)", [
        'deleted_count' => $count,
        'deleted_by' => auth()->user()->username ?? 'System'
    ]);
    
    return response()->json([
        'message' => "Successfully deleted {$count} consolidated records",
        'count' => $count
    ]);
}

/**
 * Delete consolidated records by crop year and week
 */
public function deleteByWeek(Request $request)
{
    $request->validate([
        'crop_year' => 'required|string',
        'week_no' => 'required|string'
    ]);
    
    $count = ConsolidatedUpload::where('crop_year', $request->crop_year)
        ->where('week_no', $request->week_no)
        ->count();
    
    if ($count === 0) {
        return response()->json(['message' => 'No records found for the specified crop year and week'], 400);
    }
    
    ConsolidatedUpload::where('crop_year', $request->crop_year)
        ->where('week_no', $request->week_no)
        ->delete();
    
    AuditLog::log('delete', 'consolidated', "Deleted consolidated records for {$request->crop_year} Week {$request->week_no} ({$count} records)", [
        'crop_year' => $request->crop_year,
        'week_no' => $request->week_no,
        'deleted_count' => $count,
        'deleted_by' => auth()->user()->username ?? 'System'
    ]);
    
    return response()->json([
        'message' => "Successfully deleted {$count} records for {$request->crop_year} Week {$request->week_no}",
        'count' => $count
    ]);
}

/**
 * Delete selected consolidated records by IDs
 */
public function deleteSelected(Request $request)
{
    $request->validate([
        'ids' => 'required|array',
        'ids.*' => 'required|integer|exists:consolidated_uploads,id'
    ]);
    
    $count = count($request->ids);
    
    // Get details before deleting for audit log
    $records = ConsolidatedUpload::whereIn('id', $request->ids)->get();
    
    ConsolidatedUpload::whereIn('id', $request->ids)->delete();
    
    AuditLog::log('delete', 'consolidated', "Deleted {$count} selected consolidated records", [
        'deleted_ids' => $request->ids,
        'deleted_count' => $count,
        'sample_records' => $records->take(5)->map(function($r) {
            return [
                'planter_code' => $r->planter_code,
                'planter_name' => $r->planter_name,
                'crop_year' => $r->crop_year,
                'week_no' => $r->week_no
            ];
        })->toArray(),
        'deleted_by' => auth()->user()->username ?? 'System'
    ]);
    
    return response()->json([
        'message' => "Successfully deleted {$count} selected records",
        'count' => $count
    ]);
}

/**
 * Get available crop years and weeks for delete filters
 */
public function getFilters()
{
    // Get distinct crop years
    $cropYears = ConsolidatedUpload::select('crop_year')
        ->distinct()
        ->orderBy('crop_year')
        ->pluck('crop_year');
    
    // Get weeks with counts for each crop year
    $weeksData = ConsolidatedUpload::select('crop_year', 'week_no', DB::raw('COUNT(*) as count'))
        ->groupBy('crop_year', 'week_no')
        ->orderBy('crop_year')
        ->orderBy('week_no')
        ->get()
        ->map(function($item) {
            return [
                'crop_year' => $item->crop_year,
                'week_no' => $item->week_no,
                'count' => $item->count
            ];
        });
    
    return response()->json([
        'crop_years' => $cropYears,
        'weeks_data' => $weeksData
    ]);
}




}
