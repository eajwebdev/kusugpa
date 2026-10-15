<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TruckingAllowance;
use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class TruckingAllowanceController extends Controller
{
    public function index(Request $request)
    {
        AuditLog::log('view', 'reports', 'Viewed trucking allowance report');
        
        $perPage = $request->get('per_page', 50);
        
        $query = TruckingAllowance::orderBy('planter_name');
        
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('planter_name', 'like', "%{$search}%")
                  ->orWhere('planter_code', 'like', "%{$search}%")
                  ->orWhere('trans_code', 'like', "%{$search}%");
            });
        }
        
        $sortField = $request->get('sort_field', 'planter_name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $allowedFields = ['planter_code', 'planter_name', 'crop_year', 'week_no', 'net_cane', 'ta_amount', 'trans_code'];
        
        if (in_array($sortField, $allowedFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }
        
        $uploads = $query->paginate($perPage);
        
        $totals = [
            'total_records' => TruckingAllowance::count(),
            'total_net_cane' => TruckingAllowance::sum('net_cane'),
            'total_ta_amount' => TruckingAllowance::sum('ta_amount'),
        ];
        
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'data' => $uploads->items(),
                'current_page' => $uploads->currentPage(),
                'last_page' => $uploads->lastPage(),
                'total' => $uploads->total(),
                'per_page' => $uploads->perPage(),
            ]);
        }
        
        return view('reports.trucking-allowance', compact('uploads', 'totals'));
    }

    public function getFilters()
    {
        $cropYears = TruckingAllowance::select('crop_year')->distinct()->orderBy('crop_year')->pluck('crop_year');
        
        $weeksData = TruckingAllowance::select('crop_year', 'week_no', DB::raw('COUNT(*) as count'))
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

    public function exportPDF(Request $request)
    {
        $query = TruckingAllowance::orderBy('planter_name');
        
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('planter_name', 'like', "%{$search}%")
                  ->orWhere('planter_code', 'like', "%{$search}%");
            });
        }
        
        $uploads = $query->take(500)->get();
        
        $preview = $request->boolean('preview');

        AuditLog::log($preview ? 'view' : 'export', 'reports', $preview ? 'Previewed trucking allowance report PDF' : 'Exported trucking allowance report PDF', [
            'records' => $uploads->count()
        ]);
        
        $pdf = Pdf::loadView('pdf.ledger', [
            'title' => 'Trucking Allowance Report',
            'rows' => $uploads,
            'columns' => [
                ['key' => 'crop_year', 'label' => 'Season'],
                ['key' => 'week_no', 'label' => 'Week'],
                ['key' => 'planter_code', 'label' => 'Planter Code'],
                ['key' => 'planter_name', 'label' => 'Planter Name'],
                ['key' => 'trans_code', 'label' => 'Transport Code'],
                ['key' => 'net_cane', 'label' => 'Net Cane (t)', 'decimals' => 3],
                ['key' => 'ta_amount', 'label' => 'TA Amount (PHP)', 'decimals' => 2],
            ],
            'generatedDate' => now()->format('F d, Y H:i:s'),
        ])->setPaper('a4', 'landscape');
        
        $filename = 'trucking-allowance-report-' . now()->format('Y-m-d') . '.pdf';
        return $preview ? $pdf->stream($filename) : $pdf->download($filename);
    }

    public function deleteAll()
    {
        $count = TruckingAllowance::count();
        
        if ($count === 0) {
            return response()->json(['message' => 'No records to delete'], 400);
        }
        
        TruckingAllowance::truncate();
        
        AuditLog::log('delete', 'trucking_allowance', "Deleted ALL trucking allowance records ({$count} records)", [
            'deleted_count' => $count,
            'deleted_by' => auth()->user()->username ?? 'System'
        ]);
        
        return response()->json([
            'message' => "Successfully deleted {$count} trucking allowance records",
            'count' => $count
        ]);
    }

    public function deleteByWeek(Request $request)
    {
        $request->validate([
            'crop_year' => 'required|string',
            'week_no' => 'required|string'
        ]);
        
        $count = TruckingAllowance::where('crop_year', $request->crop_year)
            ->where('week_no', $request->week_no)
            ->count();
        
        if ($count === 0) {
            return response()->json(['message' => 'No records found for the specified crop year and week'], 400);
        }
        
        TruckingAllowance::where('crop_year', $request->crop_year)
            ->where('week_no', $request->week_no)
            ->delete();
        
        AuditLog::log('delete', 'trucking_allowance', "Deleted trucking allowance records for {$request->crop_year} Week {$request->week_no} ({$count} records)", [
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

    public function deleteSelected(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:trucking_allowance,id'
        ]);
        
        $count = count($request->ids);
        $records = TruckingAllowance::whereIn('id', $request->ids)->get();
        
        TruckingAllowance::whereIn('id', $request->ids)->delete();
        
        AuditLog::log('delete', 'trucking_allowance', "Deleted {$count} selected trucking allowance records", [
            'deleted_ids' => $request->ids,
            'deleted_count' => $count,
            'sample_records' => $records->take(3)->map(function($r) {
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
}
