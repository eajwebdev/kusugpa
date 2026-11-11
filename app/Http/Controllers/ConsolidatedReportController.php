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

        AuditLog::log($preview ? 'preview' : 'export', 'reports', $preview ? 'Previewed consolidated report PDF' : 'Exported consolidated report PDF', [
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
     * One planter's Trucking Allowance slip for the week, laid out like the printed dot-matrix slip:
     * TA weight x rate, Fresh Cane (CCI FA + FB + FC) and Mudpress incentives, less Gasoline, Truck Rental
     * and Under Load charges, and the net in words. The net is the upload's total.
     */
    public function slip(Request $request, ConsolidatedUpload $upload)
    {
        $data = $this->slipData($upload);

        $preview = $request->boolean('preview', true);
        AuditLog::log($preview ? 'preview' : 'export', 'reports', 'Trucking Allowance slip of ' . $upload->planter_name . ' (' . $upload->crop_year . ' Week ' . $upload->week_no . ')', [
            'consolidated_upload_id' => $upload->id,
            'net' => $data['net'],
        ]);

        $filename = 'trucking-allowance-' . $upload->planter_code . '-wk' . $upload->week_no . '.pdf';

        return $this->slipPdf([$data], $filename, $preview);
    }

    /**
     * Slips of the checked rows (?ids=1,2,3) in one PDF, one slip per page, in planter name order.
     */
    public function slips(Request $request)
    {
        $ids = collect(explode(',', (string) $request->get('ids')))
            ->map(fn ($id) => (int) trim($id))->filter(fn ($id) => $id > 0)->unique()->values();
        if ($ids->isEmpty()) {
            abort(422, 'Check the rows to print first.');
        }

        $uploads = ConsolidatedUpload::whereIn('id', $ids)
            ->orderBy('planter_name')->orderByRaw('CAST(week_no AS UNSIGNED)')->orderBy('id')->get();
        if ($uploads->isEmpty()) {
            abort(404);
        }

        $slips = $uploads->map(fn ($upload) => $this->slipData($upload))->all();

        $preview = $request->boolean('preview', true);
        AuditLog::log($preview ? 'preview' : 'export', 'reports', 'Trucking Allowance slips of ' . $uploads->count() . ' planter row(s)', [
            'consolidated_upload_ids' => $uploads->pluck('id')->all(),
            'net' => round(array_sum(array_column($slips, 'net')), 2),
        ]);

        return $this->slipPdf($slips, 'trucking-allowance-slips-' . now()->format('Y-m-d') . '.pdf', $preview);
    }

    private function slipPdf(array $slips, string $filename, bool $preview)
    {
        $pdf = Pdf::loadView('pdf.trucking-allowance-slip', ['slips' => $slips])
            ->setPaper([0, 0, 612, 396]); // 8.5" x 5.5" slip

        return $preview ? $pdf->stream($filename) : $pdf->download($filename);
    }

    /** Figures of one slip */
    private function slipData(ConsolidatedUpload $upload): array
    {
        $amount = fn ($v) => round((float) $v, 2);
        $taWt = (float) $upload->ta_wt;
        $ta = $amount($upload->ta_amount);
        $freshCane = $amount($upload->cci_fa_amt) + $amount($upload->cci_fb_amt) + $amount($upload->cci_fc_amt);
        $mudpress = $amount($upload->mudpress_amt);
        $gasoline = $amount($upload->fuel_issuance_amt);
        $rental = $amount($upload->rental_amt);
        $underload = $amount($upload->underload_amt);

        $gross = round($ta + $freshCane + $mudpress, 2);
        $less = round($gasoline + $rental + $underload, 2);
        $net = round($gross - $less, 2);

        return [
            'upload' => $upload,
            'weekNo' => str_pad((string) $upload->week_no, 2, '0', STR_PAD_LEFT),
            'cropYear' => self::cropYearLabel($upload->crop_year),
            'date' => now()->format('m-d-Y'),
            'taWt' => $taWt,
            'taRate' => $taWt > 0 ? round($ta / $taWt, 2) : 0,
            'ta' => $ta,
            'freshCane' => $freshCane,
            'mudpress' => $mudpress,
            'gross' => $gross,
            'gasoline' => $gasoline,
            'rental' => $rental,
            'underload' => $underload,
            'less' => $less,
            'net' => $net,
            'words' => self::slipWords($net),
        ];
    }

    /** "20262027" -> "2026-2027" */
    private static function cropYearLabel($cropYear): string
    {
        $cropYear = trim((string) $cropYear);

        return preg_match('/^(\d{4})-?(\d{4})$/', $cropYear, $m) ? $m[1] . '-' . $m[2] : $cropYear;
    }

    /** 2903.55 -> "TWO THOUSANDS NINE HUNDRED THREE & 55 CENTAVOS", worded like the printed slip */
    public static function slipWords($amount): string
    {
        $amount = round(abs((float) $amount), 2);
        $pesos = (int) floor($amount);
        $centavos = (int) round(($amount - $pesos) * 100);

        $words = $pesos === 0 ? 'ZERO' : self::slipNumber($pesos);

        return $words . ' & ' . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT) . ' CENTAVOS';
    }

    private static function slipNumber(int $n): string
    {
        $ones = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN',
            'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
        $tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];

        if ($n < 20) {
            return $ones[$n];
        }
        if ($n < 100) {
            return $tens[intdiv($n, 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
        }
        if ($n < 1000) {
            return $ones[intdiv($n, 100)] . ' HUNDRED' . ($n % 100 ? ' ' . self::slipNumber($n % 100) : '');
        }
        foreach ([1000000000 => 'BILLION', 1000000 => 'MILLION', 1000 => 'THOUSAND'] as $unit => $name) {
            if ($n >= $unit) {
                $count = intdiv($n, $unit);
                // The printed slip says "TWO THOUSANDS": plural past one
                return self::slipNumber($count) . ' ' . $name . ($count > 1 ? 'S' : '')
                    . ($n % $unit ? ' ' . self::slipNumber($n % $unit) : '');
            }
        }

        return '';
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
