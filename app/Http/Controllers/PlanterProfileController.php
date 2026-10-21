<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PlanterProfile;
use App\Models\Summary;
use App\Models\ConsolidatedUpload;
use Illuminate\Support\Facades\DB;
use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;

class PlanterProfileController extends Controller
{
    /**
     * Display planter profiles page
     */
    public function index(Request $request)
{
    // Load ALL planters for client-side filtering (up to 2000)
    $allPlanters = PlanterProfile::orderBy('planter_name')->limit(2000)->get();
    
    // Get summary stats per planter from summary table
    $planterStats = Summary::selectRaw('planter_code, 
        SUM(CAST(net_cane AS DECIMAL(12,3))) as total_cane,
        SUM(CAST(net_amount AS DECIMAL(12,2))) as total_amount,
        COUNT(DISTINCT crop_year) as years_active')
        ->groupBy('planter_code')
        ->get()
        ->keyBy('planter_code');
    
    // Also get stats from trucking_allowance
    $truckingStats = \App\Models\TruckingAllowance::selectRaw('planter_code, 
        SUM(CAST(ta_amount AS DECIMAL(12,2))) as total_ta,
        COUNT(DISTINCT crop_year) as ta_years')
        ->groupBy('planter_code')
        ->get()
        ->keyBy('planter_code');

    // Merge stats
    foreach ($planterStats as $code => $stat) {
        if (isset($truckingStats[$code])) {
            $stat->total_ta = $truckingStats[$code]->total_ta ?? 0;
            $stat->ta_years = $truckingStats[$code]->ta_years ?? 0;
        }
    }
    foreach ($truckingStats as $code => $stat) {
        if (!isset($planterStats[$code])) {
            $planterStats[$code] = (object)[
                'total_cane' => 0,
                'total_amount' => 0,
                'years_active' => 0,
                'total_ta' => $stat->total_ta ?? 0,
                'ta_years' => $stat->ta_years ?? 0,
            ];
        }
    }

    $stats = [
        'total' => PlanterProfile::count(),
        'active' => PlanterProfile::where('status', 'active')->count(),
        'inactive' => PlanterProfile::where('status', 'inactive')->count(),
        'new_this_month' => PlanterProfile::whereMonth('created_at', now()->month)->count(),
    ];

    return view('planter-profiles.index', compact('allPlanters', 'planterStats', 'stats'));
}

    /**
     * API endpoint for loading more planters
     */
    public function loadMore(Request $request)
    {
        $query = PlanterProfile::orderBy('planter_name');

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('planter_name', 'like', "%{$search}%")
                  ->orWhere('planter_code', 'like', "%{$search}%");
            });
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $planters = $query->paginate(50);

        $planterStats = Summary::selectRaw('planter_code, 
            SUM(CAST(net_cane AS DECIMAL(12,3))) as total_cane,
            SUM(CAST(net_amount AS DECIMAL(12,2))) as total_amount')
            ->groupBy('planter_code')
            ->get()
            ->keyBy('planter_code');

        return response()->json([
            'data' => $planters->items(),
            'stats' => $planterStats,
            'current_page' => $planters->currentPage(),
            'last_page' => $planters->lastPage(),
            'total' => $planters->total(),
        ]);
    }

    /**
     * Show planter details
     */
    public function show(PlanterProfile $planter)
    {
        // Get production history
        $productionHistory = Summary::where('planter_code', $planter->planter_code)
            ->selectRaw('crop_year, week_no, SUM(CAST(net_cane AS DECIMAL(12,3))) as total_cane, SUM(CAST(net_amount AS DECIMAL(12,2))) as total_amount')
            ->groupBy('crop_year', 'week_no')
            ->orderBy('crop_year')
            ->orderBy('week_no')
            ->get();

        // Get yearly totals
        $yearlyTotals = Summary::where('planter_code', $planter->planter_code)
            ->selectRaw('crop_year, SUM(CAST(net_cane AS DECIMAL(12,3))) as total_cane, SUM(CAST(net_amount AS DECIMAL(12,2))) as total_amount')
            ->groupBy('crop_year')
            ->orderBy('crop_year')
            ->get();

        $totalCane = $yearlyTotals->sum('total_cane');
        $totalAmount = $yearlyTotals->sum('total_amount');

        // Get loans and cash advances
        $loans = $planter->loans()->with('loanType')->latest('application_date')->get();
        $cashAdvances = $planter->cashAdvances()->latest('application_date')->get();

        $loanBalance = $loans->whereIn('status', ['approved', 'active'])->sum('balance');
        $cashAdvanceBalance = $cashAdvances->whereIn('status', ['approved', 'active'])->sum('balance');

        return view('planter-profiles.show', compact(
            'planter', 'productionHistory', 'yearlyTotals', 'totalCane', 'totalAmount',
            'loans', 'cashAdvances', 'loanBalance', 'cashAdvanceBalance'
        ));
    }

    /**
     * Store new planter
     */
    public function store(Request $request)
    {
        $request->validate([
            'planter_code' => 'required|string|unique:planter_profiles,planter_code',
            'planter_name' => 'required|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'area_location' => 'nullable|string|max:255',
            'total_area' => 'nullable|numeric',
            'status' => 'required|in:active,inactive,suspended',
            'membership_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'crop_year' => 'nullable|string',
        ]);

        $planter = PlanterProfile::create($request->all());

        AuditLog::log('create', 'planters', 'Created planter profile: ' . $planter->planter_name, [
            'planter_code' => $planter->planter_code,
            'status' => $planter->status,
        ]);

        return response()->json(['message' => 'Planter profile created', 'planter' => $planter]);
    }

    /**
     * Update planter
     */
    public function update(Request $request, PlanterProfile $planter)
    {
        $request->validate([
            'planter_code' => 'required|string|unique:planter_profiles,planter_code,' . $planter->id,
            'planter_name' => 'required|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'area_location' => 'nullable|string|max:255',
            'total_area' => 'nullable|numeric',
            'status' => 'required|in:active,inactive,suspended',
            'membership_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'crop_year' => 'nullable|string',
        ]);

        $oldStatus = $planter->status;
        $planter->update($request->all());

        AuditLog::log('update', 'planters', 'Updated planter: ' . $planter->planter_name, [
            'planter_code' => $planter->planter_code,
            'old_status' => $oldStatus,
            'new_status' => $planter->status,
        ]);

        return response()->json(['message' => 'Planter updated', 'planter' => $planter]);
    }

    /**
     * Delete planter
     */
    public function destroy(PlanterProfile $planter)
    {
        $name = $planter->planter_name;
        $code = $planter->planter_code;
        $planter->delete();

        AuditLog::log('delete', 'planters', 'Deleted planter: ' . $name, ['planter_code' => $code]);

        return response()->json(['message' => 'Planter deleted']);
    }

    /**
     * Toggle planter status
     */
    public function toggleStatus(PlanterProfile $planter)
    {
        $newStatus = $planter->status === 'active' ? 'inactive' : 'active';
        $planter->update(['status' => $newStatus]);

        AuditLog::log('update', 'planters', 'Changed planter status: ' . $planter->planter_name . ' to ' . $newStatus);

        return response()->json(['message' => 'Status updated', 'status' => $newStatus]);
    }

    /**
     * Sync planters from the uploaded Consolidated report.
     * Codes are compared without leading zeros ("0123" = "123") so an existing planter is never added twice.
     */
    public function syncPlanters()
{
    $normalize = function ($code) {
        $code = trim((string) $code);
        $stripped = ltrim($code, '0');
        return $stripped === '' ? $code : $stripped;
    };

    $existing = PlanterProfile::pluck('planter_code')
        ->mapWithKeys(fn ($code) => [$normalize($code) => true])
        ->all();

    // Latest upload first, so a planter gets the name from their most recent record
    $rows = ConsolidatedUpload::select('planter_code', 'planter_name', 'crop_year')
        ->orderByDesc('crop_year')
        ->orderByRaw('CAST(week_no AS UNSIGNED) DESC')
        ->orderByDesc('id')
        ->get();

    $imported = 0;
    $skipped = 0;
    $errors = [];
    $seen = [];

    DB::transaction(function () use ($rows, $normalize, &$existing, &$seen, &$imported, &$skipped, &$errors) {
        foreach ($rows as $row) {
            $code = trim((string) $row->planter_code);
            $name = trim((string) $row->planter_name);
            $key = $normalize($code);

            if ($code === '' || $name === '') {
                $errors[] = "Empty data: code={$code}, name={$name}";
                continue;
            }
            // Same planter appears once per week in the upload; count each planter once
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if (isset($existing[$key])) {
                $skipped++;
                continue;
            }

            try {
                PlanterProfile::create([
                    'planter_code' => $code,
                    'planter_name' => $name,
                    'status' => 'active',
                    'crop_year' => $row->crop_year,
                ]);
                $existing[$key] = true;
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Error on {$code}: " . $e->getMessage();
            }
        }
    });

    AuditLog::log('sync', 'planters', 'Synced planter profiles from consolidated report', [
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => count($errors),
    ]);

    $totalProfiles = PlanterProfile::count();
    $uniqueConsolidated = count($seen);

    $message = $uniqueConsolidated === 0
        ? 'No planters found in the uploaded Consolidated report. Upload a Consolidated Summary first.'
        : "Sync complete! {$uniqueConsolidated} planter(s) in Consolidated report. Added: {$imported}, Already exist: {$skipped}. Total profiles: {$totalProfiles}.";

    return response()->json([
        'message' => $message,
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
        'total' => $totalProfiles,
    ]);
}
    /**
     * Export planters PDF
     */
    public function exportPDF(Request $request)
    {
        $query = PlanterProfile::orderBy('planter_name');

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $planters = $query->get();

        $pdf = Pdf::loadView('pdf.ledger', [
            'title' => 'Planter Profiles',
            'rows' => $planters,
            'columns' => [
                ['key' => 'planter_code', 'label' => 'Code'],
                ['key' => 'planter_name', 'label' => 'Planter Name'],
                ['key' => 'contact_number', 'label' => 'Contact'],
                ['key' => 'address', 'label' => 'Address'],
                ['key' => 'area_location', 'label' => 'Area Location'],
                ['key' => 'total_area', 'label' => 'Total Area (ha)', 'decimals' => 2],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'membership_date', 'label' => 'Member Since'],
            ],
            'generatedDate' => now()->format('F d, Y'),
        ])->setPaper('a4', 'landscape');

        return $request->boolean('preview') ? $pdf->stream('planter-profiles.pdf') : $pdf->download('planter-profiles.pdf');
    }
}
