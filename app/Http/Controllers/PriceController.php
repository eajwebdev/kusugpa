<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QuedanPrice;
use App\Models\MolassesPrice;
use App\Models\CropYear;
use App\Models\WeekNo;
use App\Models\Quedan;
use App\Models\Molass;
use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PriceController extends Controller
{
    /**
     * Display the price management page
     */
    public function index()
    {
        $quedanPrices = QuedanPrice::orderBy('created_at', 'desc')->get();
        $molassesPrices = MolassesPrice::orderBy('created_at', 'desc')->get();
        $cropYears = CropYear::orderBy('crop_year', 'desc')->get();
        $weekNos = WeekNo::select('week_no')->distinct()->orderBy('week_no')->get();

        $cropYearsData = $cropYears->map(function($c) {
            return [
                'id' => $c->id,
                'crop_year' => $c->crop_year,
            ];
        });

        $quedanPricesData = $quedanPrices->map(function($q) {
            return [
                'id' => $q->id,
                'crop_year' => $q->crop_year,
                'week_no' => $q->week_no,
                'quedan_type' => $q->quedan_type,
                'quedan_price' => (float) $q->quedan_price,
                'created_at' => $q->created_at->format('Y-m-d H:i:s'),
            ];
        });

        $molassesPricesData = $molassesPrices->map(function($m) {
            return [
                'id' => $m->id,
                'crop_year' => $m->crop_year,
                'week_no' => $m->week_no,
                'mol_price' => (float) $m->mol_price,
                'created_at' => $m->created_at->format('Y-m-d H:i:s'),
            ];
        });

        $weekNosData = $weekNos->pluck('week_no');

        return view('prices.index', compact(
            'cropYearsData', 
            'quedanPricesData', 
            'molassesPricesData', 
            'weekNosData'
        ));
    }

    /**
     * Update quedan price
     */
    public function updateQuedanPrice(Request $request, QuedanPrice $quedanPrice)
    {
        $request->validate([
            'quedan_type' => 'required|string|max:255',
            'quedan_price' => 'required|numeric|min:0',
            'crop_year' => 'required|string',
            'week_no' => 'required|string',
        ]);

        $quedanPrice->update($request->only(['quedan_type', 'quedan_price', 'crop_year', 'week_no']));
        
        Log::info('Quedan Price updated: ' . $quedanPrice->id . ' by user: ' . auth()->user()->username);
        
        return response()->json(['message' => 'Quedan Price updated successfully']);
    }

    /**
     * Delete quedan price
     */
    public function destroyQuedanPrice(QuedanPrice $quedanPrice)
    {
        $quedanPrice->delete();
        Log::info('Quedan Price deleted: ' . $quedanPrice->id . ' by user: ' . auth()->user()->username);
        return response()->json(['message' => 'Quedan Price deleted successfully']);
    }

    /**
     * Update molasses price
     */
    public function updateMolassesPrice(Request $request, MolassesPrice $molassesPrice)
    {
        $request->validate([
            'mol_price' => 'required|numeric|min:0',
            'crop_year' => 'required|string',
            'week_no' => 'required|string',
        ]);

        $molassesPrice->update($request->only(['mol_price', 'crop_year', 'week_no']));
        
        Log::info('Molasses Price updated: ' . $molassesPrice->id . ' by user: ' . auth()->user()->username);
        
        return response()->json(['message' => 'Molasses Price updated successfully']);
    }

    /**
     * Delete molasses price
     */
    public function destroyMolassesPrice(MolassesPrice $molassesPrice)
    {
        $molassesPrice->delete();
        Log::info('Molasses Price deleted: ' . $molassesPrice->id . ' by user: ' . auth()->user()->username);
        return response()->json(['message' => 'Molasses Price deleted successfully']);
    }

    public function registry()
{
    $quedans = Quedan::orderBy('crop_year', 'desc')
    ->orderByRaw('CAST(week_no AS UNSIGNED) DESC')
    ->orderBy('planter_name', 'asc')
    ->get();
    
    $molassesList = Molass::orderBy('crop_year', 'desc')
    ->orderByRaw('CAST(week_no AS UNSIGNED) DESC')
    ->orderBy('planter_name', 'asc')
    ->get();
    
    $cropYears = CropYear::orderBy('crop_year')->pluck('crop_year');
    
    return view('prices.registry', compact('quedans', 'molassesList', 'cropYears'));
}

public function registryData(Request $request)
{
    $type = $request->get('type', 'quedan');
    $cropYear = $request->get('crop_year');
    $weekNo = $request->get('week_no');
    
    if ($type === 'quedan') {
        $query = Quedan::orderBy('crop_year', 'desc')
            ->orderByRaw('CAST(week_no AS UNSIGNED) DESC')
            ->orderBy('planter_name', 'asc');
    } else {
        $query = Molass::orderBy('crop_year', 'desc')
            ->orderByRaw('CAST(week_no AS UNSIGNED) DESC')
            ->orderBy('planter_name', 'asc');
    }
    
    if ($cropYear) {
        $query->where('crop_year', $cropYear);
    }
    if ($weekNo) {
        $query->where('week_no', $weekNo);
    }
    
    $data = $query->get();
    $weeks = [];
    
    if ($cropYear) {
        if ($type === 'quedan') {
            $weeks = Quedan::where('crop_year', $cropYear)
                ->select('week_no')
                ->distinct()
                ->orderByRaw('CAST(week_no AS UNSIGNED) DESC')
                ->pluck('week_no');
        } else {
            $weeks = Molass::where('crop_year', $cropYear)
                ->select('week_no')
                ->distinct()
                ->orderByRaw('CAST(week_no AS UNSIGNED) DESC')
                ->pluck('week_no');
        }
    }
    
    $stats = [
        'total' => $data->count(),
        'bought' => $data->where('status', 'bought')->count(),
        'pending' => $data->where('status', 'pending')->count(),
    ];
    
    return response()->json([
        'data' => $data,
        'weeks' => $weeks,
        'stats' => $stats,
    ]);
}

public function bulkUpdateQuedan(Request $request)
{
    $request->validate([
        'ids' => 'required|array',
        'status' => 'required|in:bought,rejected,pending',
    ]);

    Quedan::whereIn('id', $request->ids)->update([
        'status' => $request->status,
        'bought_at' => $request->status === 'bought' ? now() : null,
        'bought_by' => auth()->id(),
    ]);

    AuditLog::log('update', 'quedan', 'Bulk updated ' . count($request->ids) . ' quedans to ' . $request->status);

    return response()->json(['message' => count($request->ids) . ' quedans updated successfully']);
}

public function bulkUpdateMolasses(Request $request)
{
    $request->validate([
        'ids' => 'required|array',
        'status' => 'required|in:bought,rejected,pending',
    ]);

    Molass::whereIn('id', $request->ids)->update([
        'status' => $request->status,
        'bought_at' => $request->status === 'bought' ? now() : null,
        'bought_by' => auth()->id(),
    ]);

    AuditLog::log('update', 'molasses', 'Bulk updated ' . count($request->ids) . ' molasses to ' . $request->status);

    return response()->json(['message' => count($request->ids) . ' molasses updated successfully']);
}

public function buyQuedan()
{
    $cropYears = CropYear::orderBy('crop_year')->pluck('crop_year');
    return view('prices.buy-quedan', compact('cropYears'));
}

public function buyMolasses()
{
    $cropYears = CropYear::orderBy('crop_year')->pluck('crop_year');
    return view('prices.buy-molasses', compact('cropYears'));
}


public function exportQuedanPDF(Request $request)
{
    $quedans = Quedan::orderBy('planter_name')->get();
    $pdf = Pdf::loadView('pdf.ledger', [
        'title' => 'Quedan Registry',
        'rows' => $quedans,
        'columns' => [
            ['key' => 'crop_year', 'label' => 'Season'],
            ['key' => 'week_no', 'label' => 'Week'],
            ['key' => 'planter_code', 'label' => 'Planter Code'],
            ['key' => 'planter_name', 'label' => 'Planter Name'],
            ['key' => 'qdn_no', 'label' => 'Quedan No.'],
            ['key' => 'tin_no', 'label' => 'TIN'],
            ['key' => 'total_liens', 'label' => 'Total Liens (PHP)', 'decimals' => 2],
            ['key' => 'sugar_lkg', 'label' => 'Sugar Lkg', 'decimals' => 3],
            ['key' => 'labor_lkg', 'label' => 'Labor Lkg', 'decimals' => 3],
            ['key' => 'status', 'label' => 'Status'],
        ],
        'generatedDate' => now()->format('F d, Y H:i:s'),
    ])->setPaper('a4', 'landscape');
    $filename = 'quedan-registry-' . now()->format('Y-m-d') . '.pdf';
    return $request->boolean('preview') ? $pdf->stream($filename) : $pdf->download($filename);
}

public function exportMolassesPDF(Request $request)
{
    $molassesList = Molass::orderBy('planter_name')->get();
    $pdf = Pdf::loadView('pdf.ledger', [
        'title' => 'Molasses Registry',
        'rows' => $molassesList,
        'columns' => [
            ['key' => 'crop_year', 'label' => 'Season'],
            ['key' => 'week_no', 'label' => 'Week'],
            ['key' => 'planter_code', 'label' => 'Planter Code'],
            ['key' => 'planter_name', 'label' => 'Planter Name'],
            ['key' => 'mc_no', 'label' => 'Molasses Certificate'],
            ['key' => 'tin_no', 'label' => 'TIN'],
            ['key' => 'mol_net', 'label' => 'Molasses Net', 'decimals' => 3],
            ['key' => 'status', 'label' => 'Status'],
        ],
        'generatedDate' => now()->format('F d, Y H:i:s'),
    ])->setPaper('a4', 'landscape');
    $filename = 'molasses-registry-' . now()->format('Y-m-d') . '.pdf';
    return $request->boolean('preview') ? $pdf->stream($filename) : $pdf->download($filename);
}



/**
 * Get filter data for delete modals
 */
public function getRegistryFilters(Request $request)
{
    $type = $request->get('type', 'quedan');
    
    if ($type === 'quedan') {
        $model = new Quedan();
    } else {
        $model = new Molass();
    }
    
    $cropYears = $model::select('crop_year')->distinct()->orderBy('crop_year')->pluck('crop_year');
    
    $weeksData = $model::select('crop_year', 'week_no', DB::raw('COUNT(*) as count'))
    ->groupBy('crop_year', 'week_no')
    ->orderBy('crop_year', 'desc')
    ->orderByRaw('CAST(week_no AS UNSIGNED) DESC')
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

/**
 * Delete all quedan records
 */
public function deleteAllQuedan()
{
    $count = Quedan::count();
    
    if ($count === 0) {
        return response()->json(['message' => 'No quedan records to delete'], 400);
    }
    
    Quedan::truncate();
    
    AuditLog::log('delete', 'registry', "Deleted ALL quedan records ({$count} records)", [
        'type' => 'quedan',
        'deleted_count' => $count,
        'deleted_by' => auth()->user()->username ?? 'System'
    ]);
    
    return response()->json([
        'message' => "Successfully deleted {$count} quedan records",
        'count' => $count
    ]);
}

/**
 * Delete quedan records by crop year and week
 */
public function deleteQuedanByWeek(Request $request)
{
    $request->validate([
        'crop_year' => 'required|string',
        'week_no' => 'required|string'
    ]);
    
    $count = Quedan::where('crop_year', $request->crop_year)
        ->where('week_no', $request->week_no)
        ->count();
    
    if ($count === 0) {
        return response()->json(['message' => 'No quedan records found for the specified crop year and week'], 400);
    }
    
    Quedan::where('crop_year', $request->crop_year)
        ->where('week_no', $request->week_no)
        ->delete();
    
    AuditLog::log('delete', 'registry', "Deleted quedan records for {$request->crop_year} Week {$request->week_no} ({$count} records)", [
        'type' => 'quedan',
        'crop_year' => $request->crop_year,
        'week_no' => $request->week_no,
        'deleted_count' => $count,
        'deleted_by' => auth()->user()->username ?? 'System'
    ]);
    
    return response()->json([
        'message' => "Successfully deleted {$count} quedan records for {$request->crop_year} Week {$request->week_no}",
        'count' => $count
    ]);
}

/**
 * Delete selected quedan records
 */
public function deleteQuedanSelected(Request $request)
{
    $request->validate([
        'ids' => 'required|array',
        'ids.*' => 'required|integer|exists:quedans,id'
    ]);
    
    $count = count($request->ids);
    $records = Quedan::whereIn('id', $request->ids)->get();
    
    Quedan::whereIn('id', $request->ids)->delete();
    
    AuditLog::log('delete', 'registry', "Deleted {$count} selected quedan records", [
        'type' => 'quedan',
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
        'message' => "Successfully deleted {$count} selected quedan records",
        'count' => $count
    ]);
}

/**
 * Delete all molasses records
 */
public function deleteAllMolasses()
{
    $count = Molass::count();
    
    if ($count === 0) {
        return response()->json(['message' => 'No molasses records to delete'], 400);
    }
    
    Molass::truncate();
    
    AuditLog::log('delete', 'registry', "Deleted ALL molasses records ({$count} records)", [
        'type' => 'molasses',
        'deleted_count' => $count,
        'deleted_by' => auth()->user()->username ?? 'System'
    ]);
    
    return response()->json([
        'message' => "Successfully deleted {$count} molasses records",
        'count' => $count
    ]);
}

/**
 * Delete molasses records by crop year and week
 */
public function deleteMolassesByWeek(Request $request)
{
    $request->validate([
        'crop_year' => 'required|string',
        'week_no' => 'required|string'
    ]);
    
    $count = Molass::where('crop_year', $request->crop_year)
        ->where('week_no', $request->week_no)
        ->count();
    
    if ($count === 0) {
        return response()->json(['message' => 'No molasses records found for the specified crop year and week'], 400);
    }
    
    Molass::where('crop_year', $request->crop_year)
        ->where('week_no', $request->week_no)
        ->delete();
    
    AuditLog::log('delete', 'registry', "Deleted molasses records for {$request->crop_year} Week {$request->week_no} ({$count} records)", [
        'type' => 'molasses',
        'crop_year' => $request->crop_year,
        'week_no' => $request->week_no,
        'deleted_count' => $count,
        'deleted_by' => auth()->user()->username ?? 'System'
    ]);
    
    return response()->json([
        'message' => "Successfully deleted {$count} molasses records for {$request->crop_year} Week {$request->week_no}",
        'count' => $count
    ]);
}

/**
 * Delete selected molasses records
 */
public function deleteMolassesSelected(Request $request)
{
    $request->validate([
        'ids' => 'required|array',
        'ids.*' => 'required|integer|exists:molasses,id'
    ]);
    
    $count = count($request->ids);
    $records = Molass::whereIn('id', $request->ids)->get();
    
    Molass::whereIn('id', $request->ids)->delete();
    
    AuditLog::log('delete', 'registry', "Deleted {$count} selected molasses records", [
        'type' => 'molasses',
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
        'message' => "Successfully deleted {$count} selected molasses records",
        'count' => $count
    ]);
}

}
