<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginAuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\UpdatesController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanReportController;
use App\Http\Controllers\LoanAttachmentController; 
use App\Http\Controllers\CropWeekController;  
use App\Http\Controllers\PriceController;  
use App\Http\Controllers\AuditLogController;  
use App\Http\Controllers\PlanterProfileController;
use App\Http\Controllers\ConsolidatedReportController;
use App\Http\Controllers\TruckingAllowanceController;
use App\Http\Controllers\CashAdvanceController;
use Illuminate\Http\Request;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Guest Routes
Route::middleware(['guest'])->group(function () {
    Route::get('/', function () {
        return view('login.login');
    });
    
    Route::get('/login', [LoginAuthController::class, 'getLogin'])->name('getLogin');
    Route::post('/login', [LoginAuthController::class, 'postLogin'])->name('postLogin');
});

// Admin Login Routes
Route::prefix('system/auth')->name('admin.')->group(function () {
    Route::get('/admin-login', [LoginAuthController::class, 'getAdminLogin'])->name('getLogin');
    Route::post('/admin-login', [LoginAuthController::class, 'postAdminLogin'])->name('postLogin');
});

// Authenticated Routes
Route::middleware(['login_auth'])->group(function () {
    // Logout
    Route::get('/logout', [LoginAuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/dashboard', [MenuController::class, 'dashboard'])->name('dashboard');

    // ==================== LOAN ROUTES (ALL STATIC BEFORE WILDCARD) ====================
    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');

    // Loan Attachments
    Route::get('/loans/{loan}/attachments', [LoanAttachmentController::class, 'index'])->name('loans.attachments.index');
    Route::post('/loans/{loan}/attachments', [LoanAttachmentController::class, 'store'])->name('loans.attachments.store');
    Route::get('/loans/attachments/{attachment}/view', [LoanAttachmentController::class, 'view'])->name('loans.attachment.view');
    Route::get('/loans/attachments/{attachment}/download', [LoanAttachmentController::class, 'download'])->name('loans.attachment.download');
    Route::delete('/loans/attachments/{attachment}', [LoanAttachmentController::class, 'destroy'])->name('loans.attachments.destroy');
    
    // Loan Reports (static)
    Route::get('/loans/reports', [LoanReportController::class, 'index'])->name('loans.reports');
    Route::get('/loans/monthly-report-pdf', [LoanReportController::class, 'monthlyReportPDF'])->name('loans.monthly-report-pdf');
    Route::get('/loans/active-loans-pdf', [LoanReportController::class, 'activeLoansPDF'])->name('loans.active-loans-pdf');
    
    // Loan Settings (static)
    Route::get('/loans/settings', [LoanController::class, 'settings'])->name('loans.settings');
    Route::post('/loans/settings', [LoanController::class, 'updateSettings'])->name('loans.settings.update');
    Route::post('/loans/types', [LoanController::class, 'storeLoanType'])->name('loans.types.store');
    Route::post('/loans/types/{loanType}/toggle', [LoanController::class, 'toggleLoanType'])->name('loans.types.toggle');
    
    // Loan Create (static)
    Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
    Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
    
    // Individual Loan PDF Reports (with {loan} parameter)
    Route::get('/loans/{loan}/amortization-pdf', [LoanReportController::class, 'amortizationPDF'])->name('loans.amortization-pdf');
    Route::get('/loans/{loan}/details-pdf', [LoanReportController::class, 'detailsPDF'])->name('loans.details-pdf');
    Route::get('/loans/{loan}/soa-pdf', [LoanReportController::class, 'soaPDF'])->name('loans.soa-pdf');
    
    // Individual Loan Actions
    Route::post('/loans/{loan}/approve', [LoanController::class, 'approve'])->name('loans.approve');
    Route::post('/loans/{loan}/activate', [LoanController::class, 'activate'])->name('loans.activate');
    Route::post('/loans/{loan}/reject', [LoanController::class, 'reject'])->name('loans.reject');
    Route::post('/loans/{loan}/payment', [LoanController::class, 'recordPayment'])->name('loans.payment');
    Route::delete('/loans/{loan}', [LoanController::class, 'destroy'])->name('loans.destroy');
    
    // Loan Show - MUST BE VERY LAST
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
    // ==================== END LOAN ROUTES ====================


    // Cash Advance Routes - STATIC routes MUST come before wildcard routes
    Route::get('/cash-advances', [CashAdvanceController::class, 'index'])->name('cash-advances.index');
    Route::get('/cash-advances/create', [CashAdvanceController::class, 'create'])->name('cash-advances.create');
    Route::get('/cash-advances/settings', [CashAdvanceController::class, 'settings'])->name('cash-advances.settings');
    Route::post('/cash-advances/settings', [CashAdvanceController::class, 'updateSettings'])->name('cash-advances.settings.update');
    Route::post('/cash-advances', [CashAdvanceController::class, 'store'])->name('cash-advances.store');

    // Wildcard routes AFTER static routes
    Route::get('/cash-advances/{cashAdvance}', [CashAdvanceController::class, 'show'])->name('cash-advances.show');
    Route::post('/cash-advances/{cashAdvance}/approve', [CashAdvanceController::class, 'approve'])->name('cash-advances.approve');
    Route::post('/cash-advances/{cashAdvance}/activate', [CashAdvanceController::class, 'activate'])->name('cash-advances.activate');
    Route::post('/cash-advances/{cashAdvance}/reject', [CashAdvanceController::class, 'reject'])->name('cash-advances.reject');
    Route::post('/cash-advances/{cashAdvance}/payment', [CashAdvanceController::class, 'recordPayment'])->name('cash-advances.payment');
    Route::delete('/cash-advances/{cashAdvance}', [CashAdvanceController::class, 'destroy'])->name('cash-advances.destroy');




    // Settings Routes
    Route::post('/updates/add-crop-year', [UpdatesController::class, 'addCropYear'])
        ->name('updates.addCropYear');
    Route::post('/updates/add-week-number', [UpdatesController::class, 'addWeekNumber'])
        ->name('updates.addWeekNumber');
    Route::post('/updates/add-quedan-price', [UpdatesController::class, 'addQuedanPrice'])
        ->name('updates.addQuedanPrice');
    Route::post('/updates/add-molasses-price', [UpdatesController::class, 'addMolassesPrice'])
        ->name('updates.addMolassesPrice');

    // Upload Routes
    // Route::post('/upload/{type}', [UploadController::class, 'uploadCSV'])
    // ->where('type', 'summary|trucking|fuel|rentals|underload|transloading|fci|mudpress')
    // ->name('upload.csv');

    Route::post('/upload/{type}', [UploadController::class, 'uploadCSV'])
    ->where('type', 'summary|trucking|fuel|rentals|underload|transloading|fci|mudpress|consolidated')
    ->name('upload.csv');

   
    Route::get('/get-weeks-by-crop-year', [UpdatesController::class, 'getWeeksByCropYear'])->name('weeks.by-crop-year');
    Route::get('/get-weeks', function(Request $request) {
    $cropYear = $request->get('crop_year');
    $weeks = \App\Models\WeekNo::where('crop_year', $cropYear)
        ->select('week_no')
        ->distinct()
        ->orderByRaw('CAST(week_no AS UNSIGNED) ASC')
        ->pluck('week_no');
    
    return response()->json(['weeks' => $weeks]);
})->name('get.weeks');

    // Dashboard API endpoints
    Route::get('/dashboard/data', [MenuController::class, 'dashboardData'])->name('dashboard.data');
    Route::get('/dashboard/weekly', [MenuController::class, 'dashboardWeekly'])->name('dashboard.weekly');

    // Reports
    Route::get('/summary-report', [MenuController::class, 'summaryReport'])->name('summaryReport');
    Route::get('/summary-report/data', [MenuController::class, 'summaryReportData'])->name('summaryReport.data');
    Route::get('/summary-report/weeks', [MenuController::class, 'getWeeksByCropYear'])->name('summaryReport.weeks');
    Route::get('/summary/pdf-preview', [MenuController::class, 'previewPDF'])->name('summary.previewPDF');
    Route::get('/summary/download-pdf', [MenuController::class, 'downloadPDF'])->name('summary.downloadPDF');

    // Consolidated Report - ADD THESE
    Route::get('/consolidated-report', [ConsolidatedReportController::class, 'index'])->name('consolidated-report');
    Route::get('/consolidated-report/export', [ConsolidatedReportController::class, 'exportPDF'])->name('consolidated-report.export');

    Route::delete('/consolidated-report/delete-all', [ConsolidatedReportController::class, 'deleteAll'])->name('consolidated-report.delete-all');
    Route::delete('/consolidated-report/delete-by-week', [ConsolidatedReportController::class, 'deleteByWeek'])->name('consolidated-report.delete-by-week');
    Route::delete('/consolidated-report/delete-selected', [ConsolidatedReportController::class, 'deleteSelected'])->name('consolidated-report.delete-selected');
    Route::get('/consolidated-report/filters', [ConsolidatedReportController::class, 'getFilters'])->name('consolidated-report.filters');


    // Trucking Allowance Report
    Route::get('/trucking-allowance-report', [TruckingAllowanceController::class, 'index'])->name('trucking-allowance-report');
    Route::get('/trucking-allowance-report/export', [TruckingAllowanceController::class, 'exportPDF'])->name('trucking-allowance-report.export');
    Route::get('/trucking-allowance-report/filters', [TruckingAllowanceController::class, 'getFilters'])->name('trucking-allowance-report.filters');
    Route::delete('/trucking-allowance-report/delete-all', [TruckingAllowanceController::class, 'deleteAll'])->name('trucking-allowance-report.delete-all');
    Route::delete('/trucking-allowance-report/delete-by-week', [TruckingAllowanceController::class, 'deleteByWeek'])->name('trucking-allowance-report.delete-by-week');
    Route::delete('/trucking-allowance-report/delete-selected', [TruckingAllowanceController::class, 'deleteSelected'])->name('trucking-allowance-report.delete-selected');
    

    // Print Voucher
    Route::get('/print-voucher', [MenuController::class, 'printVoucher'])->name('printVoucher');
    Route::get('/voucher/pdf-preview', [MenuController::class, 'voucherPDF'])->name('voucher.pdf');

    Route::get('/get-planter-names', [MenuController::class, 'getPlanterNames'])->name('get.planter.names');

    // User Management
    Route::get('/user-management', [UserManagementController::class, 'userManagement'])
        ->name('user-management');
    Route::post('/user-management', [UserManagementController::class, 'store'])
        ->name('user-management.store');
    Route::put('/user-management/{user}', [UserManagementController::class, 'update'])
        ->name('user-management.update');
    Route::delete('/user-management/{user}', [UserManagementController::class, 'destroy'])
        ->name('user-management.destroy');
    Route::post('/user-management/assign-permissions', [UserManagementController::class, 'assignPermissions'])
        ->name('assign-permissions');
    Route::get('/permissions/list', [UserManagementController::class, 'permissionsList'])->name('permissions.list');

    // Admin Routes (require admin role middleware)
    Route::middleware(['admin.role'])->prefix('system')->name('admin.')->group(function () {
        Route::get('/panel', [MenuController::class, 'adminDashboard'])->name('dashboard');
        Route::get('/settings', [MenuController::class, 'adminSettings'])->name('settings');
        Route::get('/settings/data', [MenuController::class, 'getSettingsData'])->name('settings.data');
        Route::post('/settings/update', [MenuController::class, 'updateSetting'])->name('settings.update');
        
        Route::post('/cache/clear', [MenuController::class, 'clearCache'])->name('cache.clear');
        Route::post('/backup/create', [MenuController::class, 'createBackup'])->name('backup.create');
    });

    // routes/web.php - Update the crop-weeks route
Route::middleware(['login_auth:manage-crop-weeks'])->group(function () {
    Route::get('/crop-weeks', [CropWeekController::class, 'index'])->name('crop-weeks.index');
    Route::put('/crop-year/{cropYear}', [CropWeekController::class, 'updateCropYear'])->name('crop-year.update');
    Route::delete('/crop-year/{cropYear}', [CropWeekController::class, 'destroyCropYear'])->name('crop-year.destroy');
    Route::put('/week/{weekNo}', [CropWeekController::class, 'updateWeek'])->name('week.update');
    Route::delete('/week/{weekNo}', [CropWeekController::class, 'destroyWeek'])->name('week.destroy');
    // POST fallbacks for shared hosts that block PUT/DELETE (JS sends POST + _method + X-HTTP-Method-Override).
    // If header spoofing works this never fires; if not, it dispatches by _method so hosted still gets JSON.
    Route::post('/crop-year/{cropYear}', function (Request $request, $cropYear) {
        $controller = app(CropWeekController::class);
        return strtoupper($request->input('_method', '')) === 'DELETE'
            ? $controller->destroyCropYear($request, $cropYear)
            : $controller->updateCropYear($request, $cropYear);
    })->name('crop-year.spoofed');
    Route::post('/week/{weekNo}', function (Request $request, $weekNo) {
        $controller = app(CropWeekController::class);
        return strtoupper($request->input('_method', '')) === 'DELETE'
            ? $controller->destroyWeek($request, $weekNo)
            : $controller->updateWeek($request, $weekNo);
    })->name('week.spoofed');

    // Price Management
    Route::get('/prices', [PriceController::class, 'index'])->name('prices.index');
    Route::put('/prices/quedan/{quedanPrice}', [PriceController::class, 'updateQuedanPrice'])->name('prices.quedan.update');
    Route::delete('/prices/quedan/{quedanPrice}', [PriceController::class, 'destroyQuedanPrice'])->name('prices.quedan.destroy');
    Route::put('/prices/molasses/{molassesPrice}', [PriceController::class, 'updateMolassesPrice'])->name('prices.molasses.update');
    Route::delete('/prices/molasses/{molassesPrice}', [PriceController::class, 'destroyMolassesPrice'])->name('prices.molasses.destroy');

    Route::get('/quedan-molasses-registry', [PriceController::class, 'registry'])->name('quedan-molasses-registry');
    // Buy Quedan
    Route::get('/buy-quedan', [PriceController::class, 'buyQuedan'])->name('quedan-buy.index');
    Route::post('/quedans/bulk-update', [PriceController::class, 'bulkUpdateQuedan'])->name('quedans.bulk-update');
    // routes/web.php
    Route::get('/quedan-molasses-registry/data', [PriceController::class, 'registryData'])->name('registry.data');

    // Registry delete routes
    Route::delete('/registry/quedan/delete-all', [PriceController::class, 'deleteAllQuedan'])->name('registry.quedan.delete-all');
    Route::delete('/registry/quedan/delete-by-week', [PriceController::class, 'deleteQuedanByWeek'])->name('registry.quedan.delete-by-week');
    Route::delete('/registry/quedan/delete-selected', [PriceController::class, 'deleteQuedanSelected'])->name('registry.quedan.delete-selected');
    Route::delete('/registry/molasses/delete-all', [PriceController::class, 'deleteAllMolasses'])->name('registry.molasses.delete-all');
    Route::delete('/registry/molasses/delete-by-week', [PriceController::class, 'deleteMolassesByWeek'])->name('registry.molasses.delete-by-week');
    Route::delete('/registry/molasses/delete-selected', [PriceController::class, 'deleteMolassesSelected'])->name('registry.molasses.delete-selected');
    Route::get('/registry/filters', [PriceController::class, 'getRegistryFilters'])->name('registry.filters');

    // Buy Molasses
    Route::get('/buy-molasses', [PriceController::class, 'buyMolasses'])->name('molasses-buy.index');
    Route::post('/molasses/bulk-update', [PriceController::class, 'bulkUpdateMolasses'])->name('molasses.bulk-update');

    Route::post('/upload/{type}', [UploadController::class, 'uploadCSV'])
    ->where('type', 'summary|trucking|fuel|rentals|underload|transloading|fci|mudpress|consolidated|quedan|molasses')
    ->name('upload.csv');

    Route::get('/quedan-registry/export', [PriceController::class, 'exportQuedanPDF'])->name('quedan-registry.export');
    Route::get('/molasses-registry/export', [PriceController::class, 'exportMolassesPDF'])->name('molasses-registry.export');

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/{log}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    Route::get('/audit-logs/export/pdf', [AuditLogController::class, 'exportPDF'])->name('audit-logs.export');
    Route::post('/audit-logs/clear', [AuditLogController::class, 'clear'])->name('audit-logs.clear');
    Route::get('/audit-logs/load-more', [AuditLogController::class, 'loadMore'])->name('audit-logs.load-more');


    // Planter Profiles - Static routes BEFORE wildcard
    Route::get('/planter-profiles', [PlanterProfileController::class, 'index'])->name('planter-profiles.index');
    Route::get('/planter-profiles/load-more', [PlanterProfileController::class, 'loadMore'])->name('planter-profiles.load-more');
    Route::post('/planter-profiles/sync', [PlanterProfileController::class, 'syncPlanters'])->name('planter-profiles.sync');
    Route::get('/planter-profiles/export/pdf', [PlanterProfileController::class, 'exportPDF'])->name('planter-profiles.export');

    // Individual planter routes
    Route::get('/planter-profiles/{planter}', [PlanterProfileController::class, 'show'])->name('planter-profiles.show');
    Route::post('/planter-profiles', [PlanterProfileController::class, 'store'])->name('planter-profiles.store');
    Route::put('/planter-profiles/{planter}', [PlanterProfileController::class, 'update'])->name('planter-profiles.update');
    Route::delete('/planter-profiles/{planter}', [PlanterProfileController::class, 'destroy'])->name('planter-profiles.destroy');
    Route::post('/planter-profiles/{planter}/toggle-status', [PlanterProfileController::class, 'toggleStatus'])->name('planter-profiles.toggle');

});


});
