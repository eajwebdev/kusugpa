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
use App\Support\LedgerPdf;
use App\Support\TableFeed;
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

        $quedanPrice->update(array_merge(
            $request->only(['quedan_price', 'crop_year', 'week_no']),
            ['quedan_type' => strtoupper(trim($request->quedan_type))] // "b" and "B" are the same type
        ));
        
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
    // Rows load one crop year at a time from registryData(); every crop year at once was
    // tens of thousands of rows (a 12 MB page)
    $cropYears = Quedan::select('crop_year')->distinct()->pluck('crop_year')
        ->merge(Molass::select('crop_year')->distinct()->pluck('crop_year'))
        ->map(fn ($cy) => (string) $cy)
        ->unique()->sort()->reverse()->values();
    $defaultCropYear = $cropYears->first() ?? '';
    $totals = ['quedan' => Quedan::count(), 'molasses' => Molass::count()];

    return view('prices.registry', compact('cropYears', 'defaultCropYear', 'totals'));
}

public function registryData(Request $request)
{
    $type = $request->get('type', 'quedan');
    $cropYear = $request->get('crop_year');
    $weekNo = $request->get('week_no');

    // Batches for the scrolling tables (registry, buy pages): filter, search and sort in the database
    if ($request->has('page')) {
        return $this->registryFeed($request, $type, $cropYear, $weekNo);
    }

    // Only the columns the registry and buy pages show, to keep the response small
    if ($type === 'quedan') {
        $query = Quedan::select('id', 'crop_year', 'week_no', 'planter_code', 'planter_name', 'qdn_no', 'tin_no', 'total_liens', 'sugar_lkg', 'labor_lkg', 'status', 'sold_price', 'sold_price_type')
            ->orderBy('crop_year', 'desc')
            ->orderByRaw('CAST(week_no AS UNSIGNED) DESC')
            ->orderBy('planter_name', 'asc');
    } else {
        $query = Molass::select('id', 'crop_year', 'week_no', 'planter_code', 'planter_name', 'tin_no', 'mc_no', 'mol_net', 'status')
            ->orderBy('crop_year', 'desc')
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

/**
 * One batch of quedan or molasses rows. The first batch also carries the status counts of the
 * crop year / week / search (for the Buy pages' Pending / Bought buttons) and the crop year's weeks.
 */
private function registryFeed(Request $request, string $type, ?string $cropYear, ?string $weekNo)
{
    $isQuedan = $type === 'quedan';
    $model = $isQuedan ? Quedan::class : Molass::class;
    $numberColumn = $isQuedan ? 'qdn_no' : 'mc_no';

    $filtered = function () use ($request, $model, $cropYear, $weekNo, $numberColumn) {
        $query = $model::query();
        if ($cropYear) {
            $query->where('crop_year', $cropYear);
        }
        if ($weekNo) {
            $query->where('week_no', $weekNo);
        }
        if ($search = trim((string) $request->get('search'))) {
            $query->where(fn ($q) => $q->where('planter_name', 'like', "%{$search}%")
                ->orWhere('planter_code', 'like', "%{$search}%")
                ->orWhere($numberColumn, 'like', "%{$search}%"));
        }
        return $query;
    };

    $query = $filtered()->select($isQuedan
        ? ['id', 'crop_year', 'week_no', 'planter_code', 'planter_name', 'qdn_no', 'tin_no', 'total_liens', 'sugar_lkg', 'labor_lkg', 'status', 'sold_price', 'sold_price_type']
        : ['id', 'crop_year', 'week_no', 'planter_code', 'planter_name', 'tin_no', 'mc_no', 'mol_net', 'status']);
    if ($status = $request->get('status')) {
        $query->where('status', $status);
    }

    // The registry header checkbox selects the complete filtered result, including
    // batches that infinite scrolling has not loaded yet. Return only IDs so this
    // stays small and does not defeat lazy row loading.
    if ($request->boolean('all_ids')) {
        $ids = $query->reorder()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->values();

        return response()->json([
            'ids' => $ids,
            'total' => $ids->count(),
        ]);
    }

    $sortable = [
        'crop_year' => 'crop_year',
        'week_no' => ['raw' => 'CAST(week_no AS UNSIGNED)'],
        'planter_code' => 'planter_code',
        'planter_name' => 'planter_name',
        'tin_no' => 'tin_no',
        'status' => 'status',
    ] + ($isQuedan
        ? ['qdn_no' => 'qdn_no', 'total_liens' => 'total_liens', 'sugar_lkg' => 'sugar_lkg', 'labor_lkg' => 'labor_lkg']
        : ['mc_no' => 'mc_no', 'mol_net' => 'mol_net']);

    return TableFeed::respond($request, $query, $sortable, [
        ['crop_year', 'desc'], [['raw' => 'CAST(week_no AS UNSIGNED)'], 'desc'], ['planter_name', 'asc'],
    ], null, function ($matching) use ($filtered, $model, $cropYear, $isQuedan) {
        $byStatus = $filtered()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');
        // Over every matching row, not only the batch loaded so far
        $sumColumns = $isQuedan ? ['total_liens', 'sugar_lkg', 'labor_lkg'] : ['mol_net'];
        $sums = $matching->reorder()
            ->select(array_map(fn ($column) => DB::raw("SUM({$column}) AS {$column}"), $sumColumns))
            ->toBase()->first();
        return [
            'sums' => collect($sumColumns)->mapWithKeys(fn ($column) => [$column => round((float) $sums->{$column}, 3)]),
            'stats' => [
                'total' => (int) $byStatus->sum(),
                'bought' => (int) ($byStatus['bought'] ?? 0),
                'pending' => (int) ($byStatus['pending'] ?? 0),
            ],
            'weeks' => $cropYear
                ? $model::where('crop_year', $cropYear)->select('week_no')->distinct()->pluck('week_no')
                    ->sortByDesc(fn ($w) => (int) $w)->values()
                : [],
        ];
    });
}

public function bulkUpdateQuedan(Request $request)
{
    $request->validate([
        'ids' => 'required|array',
        'status' => 'required|in:bought,rejected,pending',
        // A quedan is bought at a price picked from Price Management
        'quedan_price_id' => 'required_if:status,bought|nullable|integer|exists:quedan_price,id',
    ], [
        'quedan_price_id.required_if' => 'Select the price the quedans are sold at.',
    ]);

    $price = null;
    if ($request->status === 'bought') {
        $price = QuedanPrice::findOrFail($request->quedan_price_id);
        if ((float) $price->quedan_price <= 0) {
            return response()->json(['message' => 'The selected price is ₱0.00. Pick a price above zero.'], 422);
        }
        $otherYears = Quedan::whereIn('id', $request->ids)->where('crop_year', '!=', $price->crop_year)->count();
        if ($otherYears > 0) {
            return response()->json(['message' => "The price is for crop year {$price->crop_year}, but {$otherYears} selected quedan(s) belong to another crop year."], 422);
        }
    }

    Quedan::whereIn('id', $request->ids)->update([
        'status' => $request->status,
        'bought_at' => $request->status === 'bought' ? now() : null,
        'bought_by' => auth()->id(),
        // Pending / rejected quedans are not sold, so they keep no price
        'sold_price' => $price ? round((float) $price->quedan_price, 2) : null,
        'sold_price_type' => $price ? strtoupper(trim((string) $price->quedan_type)) : null,
        'quedan_price_id' => $price?->id,
    ]);

    $priceText = $price ? ' at ₱' . number_format((float) $price->quedan_price, 2) . ' (Type ' . strtoupper(trim((string) $price->quedan_type)) . ', Wk ' . $price->week_no . ')' : '';
    AuditLog::log('update', 'quedan', 'Bulk updated ' . count($request->ids) . ' quedans to ' . $request->status . $priceText, $price ? [
        'quedan_price_id' => $price->id,
        'price' => $price->quedan_price,
        'type' => $price->quedan_type,
        'crop_year' => $price->crop_year,
        'week_no' => $price->week_no,
    ] : []);

    return response()->json(['message' => count($request->ids) . ' quedans updated successfully' . $priceText . '.']);
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

/**
 * Sold Price picker of Buy Quedan, loaded as the user picks:
 *  - ?crop_year=          → the crop year's weeks (with how many prices each has)
 *  - ?crop_year=&week_no= → that week's quedan prices, narrowed by ?search= (type or amount)
 */
public function quedanPriceOptions(Request $request)
{
    $data = $request->validate([
        'crop_year' => 'required|string|max:20',
        'week_no' => 'nullable|string|max:10',
        'search' => 'nullable|string|max:50',
    ]);

    if (empty($data['week_no'])) {
        $priced = QuedanPrice::where('crop_year', $data['crop_year'])->where('quedan_price', '>', 0)
            ->selectRaw('week_no, COUNT(*) AS n')->groupBy('week_no')->pluck('n', 'week_no')
            ->mapWithKeys(fn ($n, $w) => [(string) (int) $w => (int) $n]);
        $weeks = WeekNo::where('crop_year', $data['crop_year'])->pluck('week_no')
            ->map(fn ($w) => (string) $w)
            ->merge($priced->keys())
            ->unique(fn ($w) => (int) $w)
            ->sortBy(fn ($w) => (int) $w)
            ->values()
            ->map(fn ($w) => ['week_no' => $w, 'prices' => $priced[(string) (int) $w] ?? 0]);

        return response()->json(['weeks' => $weeks]);
    }

    $query = QuedanPrice::where('crop_year', $data['crop_year'])
        ->where('week_no', $data['week_no'])
        ->where('quedan_price', '>', 0);
    if ($search = trim((string) ($data['search'] ?? ''))) {
        $amount = str_replace([',', '₱', ' '], '', $search);
        $query->where(function ($q) use ($search, $amount) {
            $q->where('quedan_type', 'like', '%' . $search . '%')
                ->orWhere('quedan_type', 'like', '%' . preg_replace('/^type\s*/i', '', $search) . '%');
            if ($amount !== '' && is_numeric($amount)) {
                $q->orWhere('quedan_price', 'like', $amount . '%');
            }
        });
    }

    $prices = $query->orderBy('quedan_type')->orderByDesc('id')->limit(50)
        ->get(['id', 'crop_year', 'week_no', 'quedan_type', 'quedan_price'])
        ->map(fn ($p) => [
            'id' => $p->id,
            'crop_year' => (string) $p->crop_year,
            'week_no' => (string) $p->week_no,
            'type' => strtoupper(trim((string) $p->quedan_type)),
            'price' => (float) $p->quedan_price,
        ]);

    return response()->json(['prices' => $prices]);
}

public function buyMolasses()
{
    $cropYears = CropYear::orderBy('crop_year')->pluck('crop_year');
    return view('prices.buy-molasses', compact('cropYears'));
}


/**
 * Fill in a missing TIN on a registry row. The planter's other rows in the same registry
 * that have no TIN get it too, since the TIN belongs to the planter.
 */
public function updateTin(Request $request, string $type, int $id)
{
    abort_unless(in_array($type, ['quedan', 'molasses'], true), 404);

    $data = $request->validate([
        'tin_no' => ['required', 'string', 'max:20', 'regex:/^\d[\d\- ]*\d$/'],
    ], [
        'tin_no.regex' => 'Enter the TIN using digits and dashes only, e.g. 123-456-789-000.',
    ]);
    $tin = trim($data['tin_no']);

    $model = $type === 'quedan' ? Quedan::class : Molass::class;
    $row = $model::findOrFail($id);

    abort_if(!self::isBlankTin($row->tin_no), 422, 'This row already has a TIN.');

    $updated = $model::where('planter_code', $row->planter_code)
        ->where(fn ($q) => $q->whereNull('tin_no')->orWhereRaw("TRIM(tin_no) = ''")->orWhereRaw("UPPER(TRIM(tin_no)) = 'NULL'"))
        ->update(['tin_no' => $tin]);

    AuditLog::log('update', $type, 'Set TIN for ' . $row->planter_name . ' on ' . $updated . ' ' . $type . ' row(s)', [
        'planter_code' => $row->planter_code,
        'tin_no' => $tin,
    ]);

    return response()->json([
        'message' => 'TIN saved on ' . $updated . ' ' . ($type === 'quedan' ? 'quedan' : 'molasses') . ' row(s) of ' . $row->planter_name . '.',
        'tin_no' => $tin,
        'planter_code' => $row->planter_code,
    ]);
}

/** Uploads leave the TIN empty or as the text "NULL" when the mill file has none */
private static function isBlankTin($tin): bool
{
    $tin = trim((string) $tin);

    return $tin === '' || strtoupper($tin) === 'NULL';
}

/** The registry page's status filter, when it holds a real status */
private function registryStatus(Request $request): ?string
{
    return in_array($request->get('status'), ['pending', 'bought', 'rejected'], true) ? $request->get('status') : null;
}

/** "Quedan Registry - Pending": a filtered PDF says what its rows and totals cover */
private function registryTitle(string $title, Request $request): string
{
    $status = $this->registryStatus($request);

    return $status ? $title . ' - ' . ucfirst($status) : $title;
}

/** Crop year / week / status filter of the registry page, applied to its PDF too */
private function registryPeriod($query, Request $request)
{
    if ($status = $this->registryStatus($request)) {
        $query->where('status', $status);
    }
    if ($request->filled('crop_year')) {
        $query->where('crop_year', $request->crop_year);
    }
    if ($request->filled('week_no')) {
        $query->where('week_no', $request->week_no);
    }

    return $query;
}

public function exportQuedanPDF(Request $request)
{
    $quedans = $this->registryPeriod(Quedan::orderBy('planter_name'), $request)->get();
    return LedgerPdf::response($request, $quedans, $this->registryTitle('Quedan Registry', $request), [
            ['key' => 'crop_year', 'label' => 'Season'],
            ['key' => 'week_no', 'label' => 'Week'],
            ['key' => 'planter_code', 'label' => 'Planter Code'],
            ['key' => 'planter_name', 'label' => 'Planter Name'],
            ['key' => 'qdn_no', 'label' => 'Quedan No.'],
            ['key' => 'tin_no', 'label' => 'TIN'],
            ['key' => 'total_liens', 'label' => 'Total Liens (PHP)', 'decimals' => 2, 'total' => true],
            ['key' => 'sugar_lkg', 'label' => 'Sugar Lkg', 'decimals' => 3, 'total' => true],
            ['key' => 'labor_lkg', 'label' => 'Labor Lkg', 'decimals' => 3, 'total' => true],
            ['key' => 'status', 'label' => 'Status'],
    ], 'quedan-registry');
}

public function exportMolassesPDF(Request $request)
{
    $molassesList = $this->registryPeriod(Molass::orderBy('planter_name'), $request)->get();
    return LedgerPdf::response($request, $molassesList, $this->registryTitle('Molasses Registry', $request), [
            ['key' => 'crop_year', 'label' => 'Season'],
            ['key' => 'week_no', 'label' => 'Week'],
            ['key' => 'planter_code', 'label' => 'Planter Code'],
            ['key' => 'planter_name', 'label' => 'Planter Name'],
            ['key' => 'mc_no', 'label' => 'Molasses Certificate'],
            ['key' => 'tin_no', 'label' => 'TIN'],
            ['key' => 'mol_net', 'label' => 'Molasses Net', 'decimals' => 3, 'total' => true],
            ['key' => 'status', 'label' => 'Status'],
    ], 'molasses-registry');
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
