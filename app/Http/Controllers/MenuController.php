<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\CropYear;
use App\Models\WeekNo;
use App\Models\Summary;
use App\Models\ConsolidatedUpload;
use App\Models\Quedan;
use App\Models\Molass;
use App\Models\QuedanPrice;
use App\Models\MolassesPrice;
use App\Models\User;
use App\Models\SystemSetting;
use App\Models\LoanSetting;
use App\Models\CashAdvanceAmortization;
use App\Models\TruckingAllowance;
use App\Models\AuditLog;
use App\Models\PlanterProfile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MenuController extends Controller
{

    public function adminDashboard()
    {
        AuditLog::log('view', 'admin', 'Viewed admin dashboard');
        
        $stats = [
            'totalUsers' => User::count(),
            'totalPlanters' => Summary::distinct('planter_code')->count(),
            'totalRecords' => Summary::count(),
            'latestBackup' => 'Never',
        ];
        
        return view('menu.admin-dashboard', compact('stats'));
    }

    public function adminSettings()
    {
        AuditLog::log('view', 'admin_settings', 'Viewed admin settings');
        return view('menu.admin-settings');
    }

    public function getSettingsData()
    {
        $settings = SystemSetting::getAllSettings();
        return response()->json($settings);
    }

    public function updateSetting(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'required',
        ]);

        $oldValue = SystemSetting::get($request->key);
        SystemSetting::set($request->key, $request->value);
        
        AuditLog::log('update', 'settings', 'Updated setting: ' . $request->key, [
            'old' => $oldValue,
            'new' => $request->value
        ]);
        
        Log::info('Setting updated: ' . $request->key . ' by user: ' . auth()->user()->username);
        
        return response()->json(['message' => 'Setting updated successfully']);
    }

    public function systemInfo()
    {
        AuditLog::log('view', 'system', 'Viewed system information');
        
        $info = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => DB::connection()->getDatabaseName(),
            'storage' => disk_free_space(storage_path()) / 1024 / 1024 . ' MB free',
        ];
        
        return view('menu.system-info', compact('info'));
    }

    public function toggleMaintenance(Request $request)
    {
        $newState = !SystemSetting::get('maintenance_mode', false);
        SystemSetting::set('maintenance_mode', $newState ? '1' : '0');
        
        AuditLog::log($newState ? 'enable' : 'disable', 'maintenance', 
            $newState ? 'Maintenance mode enabled' : 'Maintenance mode disabled', [
            'message' => $request->maintenance_message ?? null
        ]);
        
        return response()->json(['message' => 'Maintenance mode updated']);
    }

    public function clearCache(Request $request)
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        
        AuditLog::log('maintenance', 'system', 'All caches cleared');
        
        return response()->json(['message' => 'All caches cleared successfully']);
    }

    public function createBackup(Request $request)
    {
        Artisan::call('backup:run');
        
        AuditLog::log('maintenance', 'system', 'System backup created');
        
        return response()->json(['message' => 'Backup created successfully']);
    }

    public function dashboard()
    {
        AuditLog::log('view', 'dashboard', 'Viewed dashboard');
        return view('menu.dashboard');
    }

    public function summaryReport(Request $request)
    {
        AuditLog::log('view', 'reports', 'Viewed summary report');
        
        $perPage = $request->get('per_page', 50);
        
        $summaries = Summary::select('id', 'crop_year', 'week_no', 'planter_code', 'planter_name', 'net_cane', 'net_amount')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
        
        $cropYears = CropYear::orderBy('crop_year')->pluck('crop_year');
        
        return view('menu.summaryReport', compact('summaries', 'cropYears'));
    }

    public function summaryReportData(Request $request)
    {
        $cropYear = $request->get('crop_year');
        $weekFrom = $request->get('week_from');
        $weekTo = $request->get('week_to');
        $search = $request->get('search');
        $perPage = $request->get('per_page', 50);
        $sortField = $request->get('sort_field', 'id');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        $allowedSortFields = ['id', 'crop_year', 'week_no', 'planter_code', 'planter_name', 'net_cane', 'net_amount'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'id';
        }
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        
        $query = Summary::select('id', 'crop_year', 'week_no', 'planter_code', 'planter_name', 'net_cane', 'net_amount');
        
        if ($cropYear) {
            $query->where('crop_year', $cropYear);
        }
        if ($weekFrom && $weekTo) {
            $query->whereBetween('week_no', [$weekFrom, $weekTo]);
        } elseif ($weekFrom) {
            $query->where('week_no', '>=', $weekFrom);
        } elseif ($weekTo) {
            $query->where('week_no', '<=', $weekTo);
        }
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('planter_name', 'LIKE', "%{$search}%")
                  ->orWhere('planter_code', 'LIKE', "%{$search}%")
                  ->orWhere('crop_year', 'LIKE', "%{$search}%");
            });
        }
        
        $summaries = $query->orderBy($sortField, $sortDirection)->paginate($perPage);
        
        return response()->json([
            'data' => $summaries->items(),
            'total' => $summaries->total(),
            'current_page' => $summaries->currentPage(),
            'last_page' => $summaries->lastPage(),
            'per_page' => $summaries->perPage(),
        ]);
    }

    public function getWeeksByCropYear(Request $request)
    {
        $cropYear = $request->get('crop_year');
        
        if (!$cropYear) {
            return response()->json(['weeks' => []]);
        }
        
        $weeks = Summary::where('crop_year', $cropYear)
            ->select('week_no')
            ->distinct()
            ->orderBy('week_no')
            ->pluck('week_no');
        
        return response()->json(['weeks' => $weeks]);
    }

    public function previewPDF(Request $request)
    {
        $cropYear = $request->input('crop_year');
        $weekFrom = $request->input('week_from');
        $weekTo = $request->input('week_to');

        AuditLog::log('preview', 'reports', 'Previewed summary PDF', [
            'crop_year' => $cropYear,
            'weeks' => $weekFrom . ' - ' . $weekTo
        ]);

        $query = Summary::where('crop_year', $cropYear);
        if ($weekFrom && $weekTo) {
            $query->whereBetween('week_no', [$weekFrom, $weekTo]);
        }
        $summaries = $query->orderBy('week_no')->take(500)->get();

        $pdf = Pdf::loadView('modal.viewSummaryPdf', compact('summaries', 'cropYear', 'weekFrom', 'weekTo'))
                  ->setPaper('a4', 'portrait');

        return $pdf->stream('summary-report.pdf');
    }

    public function downloadPDF(Request $request)
    {
        $cropYear = $request->input('crop_year');
        $weekFrom = $request->input('week_from');
        $weekTo = $request->input('week_to');

        AuditLog::log('download', 'reports', 'Downloaded summary PDF', [
            'crop_year' => $cropYear,
            'weeks' => $weekFrom . ' - ' . $weekTo
        ]);

        $query = Summary::where('crop_year', $cropYear);
        if ($weekFrom && $weekTo) {
            $query->whereBetween('week_no', [$weekFrom, $weekTo]);
        }
        $summaries = $query->orderBy('week_no')->take(500)->get();

        $pdf = Pdf::loadView('modal.viewSummaryPdf', compact('summaries', 'cropYear', 'weekFrom', 'weekTo'))
                    ->setPaper('a4', 'portrait');

        return $pdf->download('summary-report.pdf');
    }

    public function getPlanterNames(Request $request)
{
    $cropYear = $request->input('crop_year');
    $weekFrom = $request->input('week_from');
    $weekTo = $request->input('week_to');
    
    Log::info('Fetching planter names', [
        'crop_year' => $cropYear,
        'week_from' => $weekFrom,
        'week_to' => $weekTo
    ]);
    
    if (!$cropYear || !$weekFrom || !$weekTo) {
        return response()->json([]);
    }
    
    // Get unique planter codes with names from quedans
     $quedanPlanters = Quedan::where('crop_year', $cropYear)
        ->whereBetween('week_no', [(int)$weekFrom, (int)$weekTo])
        ->select('planter_code', 'planter_name')
        ->distinct()
        ->get();
    
    Log::info('Quedan planters found: ' . $quedanPlanters->count());
    
    // Get unique planter codes with names from molasses
    $molassesPlanters = Molass::where('crop_year', $cropYear)
        ->whereBetween('week_no', [(int)$weekFrom, (int)$weekTo])
        ->select('planter_code', 'planter_name')
        ->distinct()
        ->get();
    
    Log::info('Molasses planters found: ' . $molassesPlanters->count());
    
    // Get unique planter codes with names from consolidated uploads
    $consolidatedPlanters = ConsolidatedUpload::where('crop_year', $cropYear)
        ->whereBetween('week_no', [(int)$weekFrom, (int)$weekTo])
        ->select('planter_code', 'planter_name')
        ->distinct()
        ->get();
    
    Log::info('Consolidated planters found: ' . $consolidatedPlanters->count());
    
    // Merge all planters and get unique by planter_code
    $allPlanters = collect();
    
    if ($quedanPlanters->isNotEmpty()) {
        $allPlanters = $allPlanters->concat($quedanPlanters);
    }
    
    if ($molassesPlanters->isNotEmpty()) {
        $allPlanters = $allPlanters->concat($molassesPlanters);
    }
    
    if ($consolidatedPlanters->isNotEmpty()) {
        $allPlanters = $allPlanters->concat($consolidatedPlanters);
    }
    
    // Remove duplicates by planter_code
    $allPlanters = $allPlanters->unique('planter_code')
        ->sortBy('planter_name')
        ->values();
    
    Log::info('Total unique planters: ' . $allPlanters->count());
    
    return response()->json($allPlanters);
}


    public function printVoucher(Request $request)
{
    AuditLog::log('view', 'voucher', 'Viewed print voucher page');
    
    $cropYear = CropYear::pluck('crop_year');
    
    // Get week numbers sorted numerically
    $weekNos = WeekNo::select('week_no')
        ->distinct()
        ->orderByRaw('CAST(week_no AS UNSIGNED) ASC')  // Sort numerically
        ->pluck('week_no');
    
    $selectedCropYear = $request->input('crop_year');
    $weekFrom = $request->input('week_from');
    $weekTo = $request->input('week_to');
    $selectedPlanters = $request->input('planter_name');

    $planterNames = collect([]);

    return view('menu.printVoucher', compact(
        'cropYear', 'weekNos', 'selectedCropYear', 'weekFrom', 'weekTo',
        'planterNames', 'selectedPlanters'
    ));
}


public function voucherPDF(Request $request)
{
    $cropYear = $request->input('crop_year');
    $weekFrom = $request->input('week_from');
    $weekTo = $request->input('week_to');
    $planterNames = $request->input('planter_name');

    AuditLog::log('print', 'voucher', 'Generated voucher PDF from quedan and molasses data', [
        'crop_year' => $cropYear,
        'weeks' => $weekFrom . ' - ' . $weekTo,
        'planters' => $planterNames ? count((array)$planterNames) : 'all'
    ]);

    $weekEndDates = WeekNo::where('crop_year', $cropYear)
        ->whereBetween('week_no', [$weekFrom, $weekTo])
        ->pluck('week_end_date', 'week_no');

    $quedanQuery = Quedan::where('quedans.crop_year', $cropYear)
        ->whereBetween('quedans.week_no', [(int)$weekFrom, (int)$weekTo]);

    if (!empty($planterNames)) {
        $quedanQuery->whereIn('quedans.planter_code', (array) $planterNames);
    }

    $quedans = $quedanQuery->get();

    $quedanPrices = QuedanPrice::where('crop_year', $cropYear)
        ->whereBetween('week_no', [$weekFrom, $weekTo])
        ->get()
        ->groupBy('quedan_type');

    $molassesQuery = Molass::where('molasses.crop_year', $cropYear)
        ->whereBetween('molasses.week_no', [(int)$weekFrom, (int)$weekTo]);

    if (!empty($planterNames)) {
        $molassesQuery->whereIn('molasses.planter_code', (array) $planterNames);
    }

    $molasses = $molassesQuery
        ->leftJoin('mol_price', function($join) {
            $join->on('molasses.crop_year', '=', 'mol_price.crop_year')
                 ->on('molasses.week_no', '=', 'mol_price.week_no');
        })
        ->select('molasses.*', 'mol_price.mol_price')
        ->get();

    $consolidatedQuery = ConsolidatedUpload::where('crop_year', $cropYear)
        ->whereBetween('week_no', [(int)$weekFrom, (int)$weekTo]);

    if (!empty($planterNames)) {
        $consolidatedQuery->whereIn('planter_code', (array) $planterNames);
    }

    $consolidatedUploads = $consolidatedQuery->get();

    $truckingQuery = TruckingAllowance::where('crop_year', $cropYear)
        ->whereBetween('week_no', [(int)$weekFrom, (int)$weekTo]);

    if (!empty($planterNames)) {
        $truckingQuery->whereIn('planter_code', (array) $planterNames);
    }

    $truckingAllowances = $truckingQuery->get();

    $summaryData = [];

    foreach ($quedans as $quedan) {
        $key = $quedan->planter_code;

        if (!isset($summaryData[$key])) {
            $summaryData[$key] = [
                'planter_code' => $quedan->planter_code,
                'planter_name' => $quedan->planter_name,
                'tin_no' => $quedan->tin_no,
                'week_no' => $weekFrom . ' - ' . $weekTo,
                'week_end_date' => $weekEndDates[$weekTo] ?? '',
                'quedan_a_lkg' => 0,
                'quedan_a_price' => 0,
                'quedan_b_lkg' => 0,
                'quedan_b_price' => 0,
                'quedan_b_liens' => 0,
                'quedan_b_service_charge' => 0,
                'quedan_b_insurance' => 0,
                'quedan_b_tax' => 0,
                'quedan_d_lkg' => 0,
                'quedan_d_price' => 0,
                'quedan_d_liens' => 0,
                'quedan_d_service_charge' => 0,
                'quedan_d_insurance' => 0,
                'quedan_d_tax' => 0,
                'mol_net' => 0,
                'mol_price' => 0,
                'molasses_liens' => 0,
                'molasses_service_charge' => 0,
                'molasses_insurance' => 0,
                'molasses_tax' => 0,
                'consolidated_total' => 0,
                'consolidated_ta_wt' => 0,
                'trucking_net_cane' => 0,
                'trucking_ta_amount' => 0,
                'trucking_additional_charge' => 0,
                'trucking_trans_codes' => [],
                'consolidated_ta_amount' => 0,
                'consolidated_fuel_amount' => 0,
                'sugar_lkg' => 0,
            ];
        }

        $netLkg = $quedan->sugar_lkg - $quedan->labor_lkg;

        if (isset($quedanPrices['A'])) {
            $priceA = $quedanPrices['A']->first()->quedan_price ?? 0;
            $summaryData[$key]['quedan_a_lkg'] += $netLkg;
            $summaryData[$key]['quedan_a_price'] = $priceA;
        }

        if (isset($quedanPrices['B'])) {
            $priceB = $quedanPrices['B']->first()->quedan_price ?? 0;
            $summaryData[$key]['quedan_b_lkg'] += $netLkg;
            $summaryData[$key]['quedan_b_price'] = $priceB;
            $summaryData[$key]['quedan_b_liens'] += $quedan->total_liens;
            $summaryData[$key]['sugar_lkg'] += $quedan->sugar_lkg;
        }

        if (isset($quedanPrices['D'])) {
            $priceD = $quedanPrices['D']->first()->quedan_price ?? 0;
            $summaryData[$key]['quedan_d_lkg'] += $netLkg;
            $summaryData[$key]['quedan_d_price'] = $priceD;
            $summaryData[$key]['quedan_d_liens'] += $quedan->total_liens;
        }

        if (!isset($quedanPrices['A']) && !isset($quedanPrices['B']) && !isset($quedanPrices['D'])) {
            $summaryData[$key]['quedan_b_lkg'] += $netLkg;
            $summaryData[$key]['quedan_b_price'] = 0;
            $summaryData[$key]['quedan_b_liens'] += $quedan->total_liens;
            $summaryData[$key]['sugar_lkg'] += $quedan->sugar_lkg;
        }
    }

    foreach ($molasses as $mol) {
        $key = $mol->planter_code;

        if (!isset($summaryData[$key])) {
            $summaryData[$key] = [
                'planter_code' => $mol->planter_code,
                'planter_name' => $mol->planter_name,
                'tin_no' => $mol->tin_no,
                'week_no' => $weekFrom . ' - ' . $weekTo,
                'week_end_date' => $weekEndDates[$weekTo] ?? '',
                'quedan_a_lkg' => 0, 'quedan_a_price' => 0,
                'quedan_b_lkg' => 0, 'quedan_b_price' => 0, 'quedan_b_liens' => 0,
                'quedan_b_service_charge' => 0, 'quedan_b_insurance' => 0, 'quedan_b_tax' => 0,
                'quedan_d_lkg' => 0, 'quedan_d_price' => 0, 'quedan_d_liens' => 0,
                'quedan_d_service_charge' => 0, 'quedan_d_insurance' => 0, 'quedan_d_tax' => 0,
                'mol_net' => 0, 'mol_price' => 0, 'molasses_liens' => 0,
                'molasses_service_charge' => 0, 'molasses_insurance' => 0, 'molasses_tax' => 0,
                'consolidated_total' => 0, 'consolidated_ta_wt' => 0,
                'trucking_net_cane' => 0, 'trucking_ta_amount' => 0,
                'trucking_additional_charge' => 0, 'trucking_trans_codes' => [],
            ];
        }

        $summaryData[$key]['mol_net'] += $mol->mol_net;
        $summaryData[$key]['mol_price'] = $mol->mol_price;
    }

   foreach ($consolidatedUploads as $consolidated) {
    $key = $consolidated->planter_code;

    if (!isset($summaryData[$key])) {
        $summaryData[$key] = [
            'planter_code' => $consolidated->planter_code,
            'planter_name' => $consolidated->planter_name,
            'tin_no' => '',
            'week_no' => $weekFrom . ' - ' . $weekTo,
            'week_end_date' => $weekEndDates[$weekTo] ?? '',
            'quedan_a_lkg' => 0, 'quedan_a_price' => 0,
            'quedan_b_lkg' => 0, 'quedan_b_price' => 0, 'quedan_b_liens' => 0,
            'quedan_b_service_charge' => 0, 'quedan_b_insurance' => 0, 'quedan_b_tax' => 0,
            'quedan_d_lkg' => 0, 'quedan_d_price' => 0, 'quedan_d_liens' => 0,
            'quedan_d_service_charge' => 0, 'quedan_d_insurance' => 0, 'quedan_d_tax' => 0,
            'mol_net' => 0, 'mol_price' => 0, 'molasses_liens' => 0,
            'molasses_service_charge' => 0, 'molasses_insurance' => 0, 'molasses_tax' => 0,
            'consolidated_total' => 0, 'consolidated_ta_wt' => 0,
            'trucking_net_cane' => 0, 'trucking_ta_amount' => 0,
            'trucking_additional_charge' => 0, 'trucking_trans_codes' => [],
            'consolidated_ta_amount' => 0,
            'consolidated_fuel_amount' => 0,
            'sugar_lkg' => 0,
        ];
    }

    $summaryData[$key]['consolidated_ta_wt'] += $consolidated->ta_wt;
    $summaryData[$key]['consolidated_ta_amount'] += $consolidated->ta_amount;
    $summaryData[$key]['consolidated_fuel_amount'] += $consolidated->fuel_issuance_amt;
    $summaryData[$key]['consolidated_total'] += $consolidated->total_summary;
}

    foreach ($truckingAllowances as $trucking) {
    $key = $trucking->planter_code;

    if (!isset($summaryData[$key])) {
        $summaryData[$key] = [
            'planter_code' => $trucking->planter_code,
            'planter_name' => $trucking->planter_name,
            'tin_no' => '',
            'week_no' => $weekFrom . ' - ' . $weekTo,
            'week_end_date' => $weekEndDates[$weekTo] ?? '',
            'quedan_a_lkg' => 0, 'quedan_a_price' => 0,
            'quedan_b_lkg' => 0, 'quedan_b_price' => 0, 'quedan_b_liens' => 0,
            'quedan_b_service_charge' => 0, 'quedan_b_insurance' => 0, 'quedan_b_tax' => 0,
            'quedan_d_lkg' => 0, 'quedan_d_price' => 0, 'quedan_d_liens' => 0,
            'quedan_d_service_charge' => 0, 'quedan_d_insurance' => 0, 'quedan_d_tax' => 0,
            'mol_net' => 0, 'mol_price' => 0, 'molasses_liens' => 0,
            'molasses_service_charge' => 0, 'molasses_insurance' => 0, 'molasses_tax' => 0,
            'consolidated_total' => 0, 'consolidated_ta_wt' => 0,
            'trucking_net_cane' => 0, 'trucking_ta_amount' => 0,
            'trucking_additional_charge' => 0, 'trucking_trans_codes' => [],
            'consolidated_ta_amount' => 0,
            'consolidated_fuel_amount' => 0,
            'sugar_lkg' => 0,
        ];
    }

    $summaryData[$key]['trucking_net_cane'] += $trucking->net_cane;
    $summaryData[$key]['trucking_ta_amount'] += $trucking->ta_amount;

    $additionalChargeCodes = [6, 8, 25, 36, 38];
    if (in_array((int)$trucking->trans_code, $additionalChargeCodes)) {
        $summaryData[$key]['trucking_additional_charge'] += $trucking->net_cane * 7.00;
        $summaryData[$key]['has_additional_insurance'] = true;
        if (!in_array($trucking->trans_code, $summaryData[$key]['trucking_trans_codes'])) {
            $summaryData[$key]['trucking_trans_codes'][] = $trucking->trans_code;
        }
    }
}

    $summaryData = collect(array_values($summaryData));

    Log::info('Final summary data:', $summaryData->toArray());

    $autoDeduct = LoanSetting::get('auto_deduct', true);
    if ($autoDeduct) {
        $loanController = new LoanController();
        foreach ($summaryData as $data) {
            $deduction = $loanController->getActiveDeductions(
                $data['planter_code'],
                $cropYear,
                $weekTo
            );
            $data['loan_deduction'] = $deduction;
        }
    }

    // Cash Advance Auto-Deduct
    $caAutoDeduct = LoanSetting::get('ca_auto_deduct', false);
    if ($caAutoDeduct) {
        foreach ($summaryData as $data) {
            $caDeduction = CashAdvanceAmortization::whereHas('cashAdvance', function($q) use ($data, $cropYear) {
                $q->where('planter_code', $data['planter_code'])
                ->where('crop_year', $cropYear)
                ->whereIn('status', ['active']);
            })
            ->where('status', 'pending')
            ->sum('amount_due');
            
            $data['loan_deduction'] = ($data['loan_deduction'] ?? 0) + $caDeduction;
            $data['ca_deduction'] = $caDeduction;
        }
    }


    $pdf = Pdf::loadView('pdf.voucher', [
        'summaryData' => $summaryData,
        'autoDeduct' => $autoDeduct
    ]);
    return $request->boolean('download') ? $pdf->download('voucher.pdf') : $pdf->stream('voucher-preview.pdf');
}

 public function dashboardData(Request $request)
{
    $filterCropYear = $request->get('crop_year');
    $filterWeekNo = $request->get('week_no');
    
    $allCropYears = CropYear::orderBy('crop_year')->pluck('crop_year');
    $currentCropYear = $filterCropYear ?? ConsolidatedUpload::orderBy('crop_year', 'desc')->value('crop_year');
    
    if (!$currentCropYear) {
        $currentCropYear = $allCropYears->last() ?? '20232024';
    }
    
    $consolidatedQuery = ConsolidatedUpload::where('crop_year', $currentCropYear);
    if ($filterWeekNo) {
        $consolidatedQuery->where('week_no', $filterWeekNo);
    }
    
    $totalTAWT = (clone $consolidatedQuery)->sum(DB::raw('CAST(ta_wt AS DECIMAL(12,3))'));
    $totalSummary = (clone $consolidatedQuery)->sum(DB::raw('CAST(total_summary AS DECIMAL(12,2))'));
    $activePlantersCount = (clone $consolidatedQuery)->distinct('planter_code')->count('planter_code');
    
    $totalPlanters = PlanterProfile::where('status', 'active')->distinct('planter_code')->count('planter_code');
    
    $previousCropYear = ConsolidatedUpload::where('crop_year', '<', $currentCropYear)
        ->orderBy('crop_year', 'desc')->value('crop_year');
    
    $previousYearQuery = ConsolidatedUpload::where('crop_year', $previousCropYear);
    if ($filterWeekNo) $previousYearQuery->where('week_no', $filterWeekNo);
    $lastYearTAWT = $previousCropYear ? (clone $previousYearQuery)->sum(DB::raw('CAST(ta_wt AS DECIMAL(12,3))')) : 0;
    
    $caneChange = $lastYearTAWT > 0 ? (($totalTAWT - $lastYearTAWT) / $lastYearTAWT) * 100 : 0;
    $maxWeek = ConsolidatedUpload::where('crop_year', $currentCropYear)->max('week_no') ?? 0;
    $bestWeekData = ConsolidatedUpload::where('crop_year', $currentCropYear)
        ->select('week_no', DB::raw('SUM(CAST(ta_wt AS DECIMAL(12,3))) as total_ta_wt'))
        ->groupBy('week_no')
        ->orderByDesc('total_ta_wt')
        ->first();

    $bestWeek = $bestWeekData ? (int) $bestWeekData->week_no : 0;
    $bestWeekCane = $bestWeekData ? (float) $bestWeekData->total_ta_wt : 0;
    
    $quedanPriceQuery = QuedanPrice::orderBy('created_at', 'desc');
    $molassesPriceQuery = MolassesPrice::orderBy('created_at', 'desc');
    if ($filterCropYear) {
        $quedanPriceQuery->where('crop_year', $filterCropYear);
        $molassesPriceQuery->where('crop_year', $filterCropYear);
    }
    if ($filterWeekNo) {
        $quedanPriceQuery->where('week_no', $filterWeekNo);
        $molassesPriceQuery->where('week_no', $filterWeekNo);
    }
    
    $latestQuedan = $quedanPriceQuery->first();
    $latestMolasses = $molassesPriceQuery->first();
    
    $quedanPrice = $latestQuedan ? (float) $latestQuedan->quedan_price : 0;
    $quedanType = $latestQuedan ? $latestQuedan->quedan_type : 'N/A';
    $quedanTime = $latestQuedan ? $latestQuedan->created_at->diffForHumans() : 'No data';
    $molassesPrice = $latestMolasses ? (float) $latestMolasses->mol_price : 0;
    $molassesTime = $latestMolasses ? $latestMolasses->created_at->diffForHumans() : 'No data';
    
    $yearlyLabels = [];
    $yearlyData = [];
    foreach ($allCropYears as $cropYear) {
        $yearlyLabels[] = $cropYear;
        $yQuery = ConsolidatedUpload::where('crop_year', $cropYear);
        if ($filterWeekNo) $yQuery->where('week_no', $filterWeekNo);
        $yearlyData[] = (float) $yQuery->sum(DB::raw('CAST(ta_wt AS DECIMAL(12,3))'));
    }
    
    $weeklyData = $this->getWeeklyData($currentCropYear);
    $topPlanters = $this->getTopPlanters($currentCropYear, $filterWeekNo);
    $recentPrices = $this->getRecentPrices($filterCropYear, $filterWeekNo);
    $alerts = $this->getAlerts($currentCropYear, $totalTAWT, $totalSummary, $activePlantersCount);
    $riskPlanters = $this->getRiskPlanters($currentCropYear);
    $loanStats = $this->getLoanStatsOverview();
    
    $activities = $this->getActivities($currentCropYear, $filterWeekNo, $quedanPrice, $quedanType, 
        $molassesPrice, $activePlantersCount, $totalPlanters, $maxWeek, $quedanTime, $molassesTime);
    
    return response()->json([
        'stats' => [
            'totalNetCane' => (float) $totalTAWT,
            'totalNetAmount' => (float) $totalSummary,
            'activePlanters' => $activePlantersCount,
            'totalPlanters' => $totalPlanters,
            'currentCropYear' => $currentCropYear,
            'currentWeek' => (int) $maxWeek,
            'bestWeek' => $bestWeek,
            'bestWeekCane' => $bestWeekCane,
            'caneChange' => round($caneChange, 1),
            'amountChange' => 12.5,
            'quedanPrice' => $quedanPrice,
            'quedanType' => $quedanType,
            'molassesPrice' => $molassesPrice,
            'activeLoans' => \App\Models\Loan::where('status', 'active')->count(),
            'totalLoanAmount' => \App\Models\Loan::whereIn('status', ['active', 'approved'])->sum('principal_amount'),
            'totalMolasses' => 0,
            'averageYield' => $activePlantersCount > 0 ? round($totalTAWT / $activePlantersCount, 2) : 0,
            // 'bestWeek' => (int) $maxWeek,
            // 'bestWeekCane' => (float) ConsolidatedUpload::where('crop_year', $currentCropYear)->where('week_no', $maxWeek)->sum('ta_wt'),
            'collectionRate' => $this->getLoanCollectionRate(),
            'riskPlanters' => count($riskPlanters),
            'pendingApprovals' => \App\Models\Loan::where('status', 'pending')->count(),
        ],
        'yearlyData' => ['labels' => $yearlyLabels, 'datasets' => [['data' => $yearlyData]]],
        'weeklyData' => $weeklyData,
        'monthlyData' => $this->getMonthlyAverageData($currentCropYear),
        'activities' => $activities,
        'topPlanters' => $topPlanters,
        'recentPrices' => $recentPrices,
        'availableYears' => $allCropYears->values(),
        'alerts' => $alerts,
        'recommendations' => $this->getRecommendations($currentCropYear, $totalTAWT, $maxWeek),
        'riskPlanters' => $riskPlanters,
        'loanStats' => $loanStats,
        'distributionData' => $this->getDistributionData($currentCropYear, $filterWeekNo),
    ]);
}

private function getWeeklyData($cropYear)
{
    $weeks = ConsolidatedUpload::where('crop_year', $cropYear)
        ->select('week_no', DB::raw('SUM(CAST(ta_wt AS DECIMAL(12,3))) as total_cane'))
        ->groupBy('week_no')
        ->orderByRaw('CAST(week_no AS UNSIGNED) ASC')
        ->limit(52)
        ->get();

    $labels = [];
    $data = [];
    foreach ($weeks as $item) {
        $labels[] = 'Week ' . $item->week_no;
        $data[] = (float) $item->total_cane;
    }

    return ['labels' => $labels, 'datasets' => [['data' => $data]]];
}

private function getTopPlanters($cropYear, $weekNo = null)
{
    $query = ConsolidatedUpload::where('crop_year', $cropYear)
        ->select(
            'planter_code',
            'planter_name',
            DB::raw('SUM(CAST(ta_wt AS DECIMAL(12,3))) as total_cane'),
            DB::raw('SUM(CAST(total_summary AS DECIMAL(12,2))) as total_amount')
        )
        ->groupBy('planter_code', 'planter_name')
        ->orderByDesc('total_amount');
    
    if ($weekNo) $query->where('week_no', $weekNo);

    return $query->limit(10)->get()->map(function($p) {
        return [
            'planter_code' => $p->planter_code,
            'planter_name' => $p->planter_name,
            'total_cane' => (float) $p->total_cane,
            'total_amount' => (float) $p->total_amount,
        ];
    })->values()->toArray();
}

private function getRiskPlanters($cropYear)
{
    return ConsolidatedUpload::where('crop_year', $cropYear)
        ->select('planter_code', 'planter_name', DB::raw('SUM(CAST(ta_wt AS DECIMAL(12,3))) as total_cane'))
        ->groupBy('planter_code', 'planter_name')
        ->having('total_cane', '<', 5)
        ->orderBy('total_cane')
        ->limit(10)
        ->get()
        ->map(function($p) {
            return [
                'planter_code' => $p->planter_code,
                'planter_name' => $p->planter_name,
                'total_cane' => (float) $p->total_cane,
            ];
        })
        ->values()
        ->toArray();
}

private function getDistributionData($cropYear, $weekNo = null)
{
    $query = ConsolidatedUpload::where('crop_year', $cropYear);
    if ($weekNo) $query->where('week_no', $weekNo);
    
    $grandTotal = (clone $query)->sum(DB::raw('CAST(ta_wt AS DECIMAL(12,3))'));
    
    if ($grandTotal <= 0) {
        return [
            'labels' => ['No Data'],
            'datasets' => [['data' => [1], 'backgroundColor' => ['#e5e7eb']]]
        ];
    }
    
    $top5 = (clone $query)
        ->select('planter_name', DB::raw('SUM(CAST(ta_wt AS DECIMAL(12,3))) as total_cane'))
        ->groupBy('planter_name')
        ->orderByDesc('total_cane')
        ->limit(5)
        ->get();
    
    $top5Total = $top5->sum('total_cane');
    $othersTotal = $grandTotal - $top5Total;
    
    $labels = [];
    $data = [];
    $colors = ['#22c55e', '#3b82f6', '#a855f7', '#f59e0b', '#ef4444', '#94a3b8'];
    
    foreach ($top5 as $t) {
        $labels[] = $t->planter_name;
        $data[] = (float) $t->total_cane;
    }
    
    if ($othersTotal > 0) {
        $labels[] = 'Other Planters';
        $data[] = (float) $othersTotal;
    }
    
    return [
        'labels' => $labels,
        'datasets' => [['data' => $data, 'backgroundColor' => array_slice($colors, 0, count($labels))]]
    ];
}

private function getAlerts($cropYear, $totalCane, $totalAmount, $activePlanters)
{
    $alerts = [];
    
    if ($totalCane == 0) {
        $alerts[] = ['type' => 'warning', 'title' => 'No Production Data', 'message' => 'No production records found for ' . $cropYear . '. Upload weekly summaries.'];
    }
    
    $pendingLoans = \App\Models\Loan::where('status', 'pending')->count();
    if ($pendingLoans > 0) {
        $alerts[] = ['type' => 'info', 'title' => 'Pending Loan Approvals', 'message' => $pendingLoans . ' loan application(s) awaiting approval.'];
    }
    
    $overduePayments = \App\Models\LoanAmortization::where('status', 'overdue')->count();
    if ($overduePayments > 0) {
        $alerts[] = ['type' => 'danger', 'title' => 'Overdue Loan Payments', 'message' => $overduePayments . ' overdue amortization(s) require attention.'];
    }
    
    if (!QuedanPrice::where('crop_year', $cropYear)->exists()) {
        $alerts[] = ['type' => 'warning', 'title' => 'Quedan Price Not Set', 'message' => 'No quedan price configured for ' . $cropYear . '.'];
    }
    
    return $alerts;
}

private function getRecommendations($cropYear, $totalCane, $maxWeek)
{
    $recommendations = [];
    
    if ($totalCane == 0) {
        $recommendations[] = ['icon' => 'fas fa-upload', 'message' => 'Upload weekly summary data to start tracking production metrics.'];
    }
    
    if ($maxWeek > 0 && $maxWeek < 22) {
        $recommendations[] = ['icon' => 'fas fa-calendar-check', 'message' => 'You are in Week ' . $maxWeek . '. Keep uploads current for accurate reporting.'];
    }
    
    $inactivePlanters = \App\Models\PlanterProfile::where('status', '!=', 'active')->count();
    if ($inactivePlanters > 0) {
        $recommendations[] = ['icon' => 'fas fa-user-check', 'message' => $inactivePlanters . ' inactive planters. Review and update their status.'];
    }
    
    $recommendations[] = ['icon' => 'fas fa-chart-line', 'message' => 'Compare production with previous crop year to identify trends.'];
    
    return $recommendations;
}

private function getLoanStatsOverview()
{
    $activeLoans = \App\Models\Loan::where('status', 'active')->get();
    
    return [
        'active_count' => $activeLoans->count(),
        'total_principal' => $activeLoans->sum('principal_amount'),
        'total_balance' => $activeLoans->sum('balance'),
        'collection_rate' => $this->getLoanCollectionRate(),
    ];
}

private function getLoanCollectionRate()
{
    $totalDue = \App\Models\LoanAmortization::whereHas('loan', function($q) {
        $q->where('status', 'active');
    })->sum('amount_due');
    
    $totalPaid = \App\Models\LoanAmortization::whereHas('loan', function($q) {
        $q->where('status', 'active');
    })->whereIn('status', ['paid', 'partial'])->sum('amount_paid');
    
    return $totalDue > 0 ? round(($totalPaid / $totalDue) * 100, 1) : 0;
}

private function getMonthlyAverageData($cropYear)
{
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $monthlyData = array_fill(0, 12, 0);
    
    $weekMonthMap = [
        1 => 0, 2 => 0, 3 => 0, 4 => 0,
        5 => 1, 6 => 1, 7 => 1, 8 => 1,
        9 => 2, 10 => 2, 11 => 2, 12 => 2, 13 => 2,
        14 => 3, 15 => 3, 16 => 3, 17 => 3,
        18 => 4, 19 => 4, 20 => 4, 21 => 4,
        22 => 5, 23 => 5, 24 => 5, 25 => 5, 26 => 5,
        27 => 6, 28 => 6, 29 => 6, 30 => 6,
        31 => 7, 32 => 7, 33 => 7, 34 => 7, 35 => 7,
        36 => 8, 37 => 8, 38 => 8, 39 => 8,
        40 => 9, 41 => 9, 42 => 9, 43 => 9,
        44 => 10, 45 => 10, 46 => 10, 47 => 10, 48 => 10,
        49 => 11, 50 => 11, 51 => 11, 52 => 11,
    ];
    
    $weeklyData = ConsolidatedUpload::where('crop_year', $cropYear)
        ->select('week_no', DB::raw('SUM(CAST(ta_wt AS DECIMAL(12,3))) as total_ta_wt'))
        ->groupBy('week_no')
        ->get();
    
    foreach ($weeklyData as $week) {
        $weekNo = (int) $week->week_no;
        if (isset($weekMonthMap[$weekNo])) {
            $monthIndex = $weekMonthMap[$weekNo];
            $monthlyData[$monthIndex] += (float) $week->total_ta_wt;
        }
    }
    
    foreach ($monthlyData as $i => $total) {
        $monthlyData[$i] = round($total, 2);
    }
    
    return ['labels' => $months, 'datasets' => [['data' => $monthlyData]]];
}

private function getActivities($cropYear, $weekNo, $quedanPrice, $quedanType, $molassesPrice, $activePlanters, $totalPlanters, $maxWeek, $quedanTime, $molassesTime)
{
    $weekLabel = $weekNo ? "Week {$weekNo}" : ($maxWeek > 0 ? "Week {$maxWeek}" : 'N/A');
    
    // Check if there's actual production data for this crop year
    $hasProductionData = $totalPlanters > 0;
    
    return [
        [
            'id' => 1,
            'description' => $quedanPrice > 0 
                ? 'Quedan price: ₱' . number_format($quedanPrice, 2) . ' (Type: ' . $quedanType . ')'
                : 'No quedan price set for ' . $cropYear,
            'time' => $quedanTime,
            'icon' => 'fas fa-tag',
            'bgColor' => 'bg-green-100',
            'iconColor' => 'text-green-600'
        ],
        [
            'id' => 2,
            'description' => $hasProductionData 
                ? 'Current: Crop Year ' . $cropYear . ' - ' . $weekLabel
                : 'No production data for Crop Year ' . $cropYear,
            'time' => $hasProductionData ? 'Current season' : 'No data',
            'icon' => 'fas fa-calendar-week',
            'bgColor' => $hasProductionData ? 'bg-blue-100' : 'bg-gray-100',
            'iconColor' => $hasProductionData ? 'text-blue-600' : 'text-gray-400'
        ],
        [
            'id' => 3,
            'description' => $hasProductionData 
                ? $activePlanters . ' active planters in ' . $cropYear . ' (' . $totalPlanters . ' total)'
                : 'No planters in ' . $cropYear,
            'time' => $hasProductionData ? 'Current season' : 'No data',
            'icon' => 'fas fa-users',
            'bgColor' => $hasProductionData ? 'bg-purple-100' : 'bg-gray-100',
            'iconColor' => $hasProductionData ? 'text-purple-600' : 'text-gray-400'
        ],
        [
            'id' => 4,
            'description' => $molassesPrice > 0 
                ? 'Molasses price: ₱' . number_format($molassesPrice, 2)
                : 'No molasses price set for ' . $cropYear,
            'time' => $molassesTime,
            'icon' => 'fas fa-flask',
            'bgColor' => 'bg-orange-100',
            'iconColor' => 'text-orange-600'
        ]
    ];
}

private function getRecentPrices($cropYear = null, $weekNo = null)
{
    $quedanQuery = QuedanPrice::orderBy('created_at', 'desc');
    $molassesQuery = MolassesPrice::orderBy('created_at', 'desc');
    
    if ($cropYear) {
        $quedanQuery->where('crop_year', $cropYear);
        $molassesQuery->where('crop_year', $cropYear);
    }
    if ($weekNo) {
        $quedanQuery->where('week_no', $weekNo);
        $molassesQuery->where('week_no', $weekNo);
    }
    
    $quedanPrices = $quedanQuery->limit(5)->get();
    $molassesPrices = $molassesQuery->limit(5)->get();
    
    $prices = [];
    
    foreach ($quedanPrices as $item) {
        $prices[] = [
            'id' => 'q_' . $item->id,
            'date' => $item->created_at->format('M d, Y'),
            'type' => 'Quedan',
            'crop_year' => $item->crop_year,
            'week_no' => $item->week_no,
            'price' => (float) $item->quedan_price
        ];
    }
    
    foreach ($molassesPrices as $item) {
        $prices[] = [
            'id' => 'm_' . $item->id,
            'date' => $item->created_at->format('M d, Y'),
            'type' => 'Molasses',
            'crop_year' => $item->crop_year,
            'week_no' => $item->week_no,
            'price' => (float) $item->mol_price
        ];
    }
    
    usort($prices, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    return array_slice($prices, 0, 8);
}

    public function dashboardWeekly(Request $request)
    {
        $year = $request->get('year', '20242025');
        $weeklyData = $this->getWeeklyData($year);
        return response()->json($weeklyData);
    }
}
