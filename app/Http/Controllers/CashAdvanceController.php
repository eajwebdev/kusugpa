<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashAdvance;
use App\Models\CashAdvanceAmortization;
use App\Models\LoanSetting;
use App\Models\Summary;
use App\Models\CropYear;
use App\Models\WeekNo;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\PlanterProfile;
use Carbon\Carbon;

class CashAdvanceController extends Controller
{
    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    private function currentRole(): string
    {
        return $this->user()->role ?? '';
    }

    private function canApproveCA(): bool
    {
        $role = $this->currentRole();
        if (in_array($role, ['Administrator', 'super_admin'])) {
            return true;
        }
        if ($role === 'manager') {
            return true;
        }
        return $this->user()->hasPermission('approve-cash-advances');
    }

    private function canProcessCA(): bool
    {
        $role = $this->currentRole();
        if (in_array($role, ['Administrator', 'super_admin', 'manager'])) {
            return true;
        }
        return $this->user()->hasPermission('create-cash-advances') || 
               $this->user()->hasPermission('process-cash-advance-payments');
    }

    private function canManageSettings(): bool
    {
        $role = $this->currentRole();
        if (in_array($role, ['Administrator', 'super_admin', 'manager'])) {
            return true;
        }
        return $this->user()->hasPermission('manage-cash-advance-settings');
    }

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if ($this->currentRole() === 'Viewer') {
                if (!in_array($request->route()->getName(), ['cash-advances.index', 'cash-advances.show'])) {
                    return redirect()->route('cash-advances.index')
                        ->with('error', 'You only have view access to cash advances.');
                }
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $query = CashAdvance::with(['approvedByUser', 'deductions']);

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('ca_number', 'like', "%{$search}%")
                  ->orWhere('planter_name', 'like', "%{$search}%")
                  ->orWhere('planter_code', 'like', "%{$search}%");
            });
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->crop_year) {
            $query->where('crop_year', $request->crop_year);
        }

        $cashAdvances = $query->orderBy('created_at', 'desc')->paginate(20);
        $cropYears = CropYear::pluck('crop_year');
        $planters = PlanterProfile::where('status', 'active')
    ->select('planter_code', 'planter_name')
    ->orderBy('planter_name')
    ->get();

        $stats = [
            'total_active' => CashAdvance::where('status', 'active')->count(),
            'total_pending' => CashAdvance::where('status', 'pending')->count(),
            'total_amount' => CashAdvance::whereIn('status', ['active', 'approved'])->sum('amount'),
            'total_collected' => CashAdvanceAmortization::where('status', 'paid')->sum('amount_paid'),
        ];

        $userPermissions = [
            'canCreate' => $this->canProcessCA(),
            'canApprove' => $this->canApproveCA(),
            'canManageSettings' => $this->canManageSettings(),
            'canEditReceipt' => CaReceiptController::canManageReceipt($this->user()),
        ];

        // Weeks per crop year for the receipt's deduction-week picker (Crop & Week Management)
        $weeks = WeekNo::orderByRaw('CAST(week_no AS UNSIGNED) ASC')
            ->get(['crop_year', 'week_no'])
            ->map(fn ($w) => ['crop_year' => $w->crop_year, 'week_no' => (string) $w->week_no]);

        return view('cash-advances.index', compact('cashAdvances', 'cropYears', 'planters', 'stats', 'userPermissions', 'weeks'));
    }

    public function create()
    {
        $cropYears = CropYear::pluck('crop_year');
        $planters = PlanterProfile::where('status', 'active')
    ->select('planter_code', 'planter_name')
    ->orderBy('planter_name')
    ->get();
        $settings = [
            'default_interest' => LoanSetting::get('ca_default_interest_rate', 3),
            'max_term' => LoanSetting::get('ca_max_term_months', 6),
            'min_amount' => LoanSetting::get('ca_min_amount', 500),
            'max_amount' => LoanSetting::get('ca_max_amount', 10000),
        ];

        return view('cash-advances.create', compact('cropYears', 'planters', 'settings'));
    }

    public function store(Request $request)
    {
        if (!$this->canProcessCA()) {
            AuditLog::log('error', 'cash_advances', 'Unauthorized cash advance creation attempt');
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'planter_code' => 'required|string',
            'planter_name' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'term_months' => 'required|integer|min:1|max:12',
            'crop_year' => 'required|string',
            'purpose' => 'nullable|string',
        ]);

        $ca = new CashAdvance($request->all());
        $ca->ca_number = CashAdvance::generateCANumber();
        $ca->application_date = now();
        $ca->status = 'pending';
        $ca->created_by = auth()->id();
        $ca->calculateAmortization();
        $ca->save();

        AuditLog::log('create', 'cash_advances', 'Created cash advance #' . $ca->ca_number, [
            'amount' => $ca->amount,
            'planter' => $ca->planter_name,
            'interest_rate' => $ca->interest_rate . '%',
            'term' => $ca->term_months . ' months',
            'monthly' => $ca->monthly_amortization,
        ]);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Cash advance #' . $ca->ca_number . ' created successfully.',
                'cash_advance' => $ca
            ], 201);
        }

        return redirect()->route('cash-advances.index')
            ->with('success', 'Cash advance #' . $ca->ca_number . ' created successfully.');
    }

    public function show(CashAdvance $cashAdvance)
    {
        $cashAdvance->load(['amortizations' => function($q) {
            $q->orderBy('payment_number');
        }, 'approvedByUser', 'createdByUser']);

        $userPermissions = [
            'canApprove' => $this->canApproveCA() && $cashAdvance->status === 'pending',
            'canActivate' => $this->canApproveCA() && $cashAdvance->status === 'approved',
            'canReject' => $this->canApproveCA() && $cashAdvance->status === 'pending',
            'canRecordPayment' => $this->canProcessCA() && $cashAdvance->status === 'active',
            'canDelete' => $this->canProcessCA() && in_array($cashAdvance->status, ['pending', 'rejected']),
        ];

        return view('cash-advances.show', compact('cashAdvance', 'userPermissions'));
    }

    public function approve(Request $request, CashAdvance $cashAdvance)
    {
        if (!$this->canApproveCA()) {
            AuditLog::log('error', 'cash_advances', 'Unauthorized approval attempt for #' . $cashAdvance->ca_number);
            abort(403, 'Unauthorized. Only managers and administrators can approve cash advances.');
        }

        if ($cashAdvance->status !== 'pending') {
            return back()->with('error', 'Only pending cash advances can be approved.');
        }

        $request->validate(['start_date' => 'required|date']);

        $cashAdvance->status = 'approved';
        $cashAdvance->approved_by = auth()->id();
        $cashAdvance->approved_date = now();
        $cashAdvance->start_date = $request->start_date;
        $cashAdvance->end_date = Carbon::parse($request->start_date)->addMonths($cashAdvance->term_months);
        $cashAdvance->save();

        $this->generateAmortizationSchedule($cashAdvance);

        AuditLog::log('approve', 'cash_advances', 'Approved cash advance #' . $cashAdvance->ca_number, [
            'amount' => $cashAdvance->amount,
            'planter' => $cashAdvance->planter_name,
            'start_date' => $cashAdvance->start_date->format('Y-m-d'),
            'monthly_amortization' => $cashAdvance->monthly_amortization,
        ]);

        return redirect()->route('cash-advances.show', $cashAdvance)
            ->with('success', 'Cash advance #' . $cashAdvance->ca_number . ' approved successfully.');
    }

    public function activate(CashAdvance $cashAdvance)
    {
        if (!$this->canApproveCA()) {
            AuditLog::log('error', 'cash_advances', 'Unauthorized activation attempt for #' . $cashAdvance->ca_number);
            abort(403, 'Unauthorized');
        }

        if ($cashAdvance->status !== 'approved') {
            return back()->with('error', 'Only approved cash advances can be activated.');
        }

        $cashAdvance->status = 'active';
        $cashAdvance->save();

        AuditLog::log('activate', 'cash_advances', 'Activated cash advance #' . $cashAdvance->ca_number, [
            'planter' => $cashAdvance->planter_name,
            'amount' => $cashAdvance->amount,
        ]);

        return redirect()->route('cash-advances.show', $cashAdvance)
            ->with('success', 'Cash advance #' . $cashAdvance->ca_number . ' is now active.');
    }

    public function reject(Request $request, CashAdvance $cashAdvance)
    {
        if (!$this->canApproveCA()) {
            AuditLog::log('error', 'cash_advances', 'Unauthorized rejection attempt for #' . $cashAdvance->ca_number);
            abort(403, 'Unauthorized. Only managers and administrators can reject cash advances.');
        }

        if ($cashAdvance->status !== 'pending') {
            return back()->with('error', 'Only pending cash advances can be rejected.');
        }

        $cashAdvance->status = 'rejected';
        $cashAdvance->remarks = $request->remarks;
        $cashAdvance->save();

        AuditLog::log('reject', 'cash_advances', 'Rejected cash advance #' . $cashAdvance->ca_number, [
            'planter' => $cashAdvance->planter_name,
            'amount' => $cashAdvance->amount,
            'reason' => $request->remarks,
        ]);

        return redirect()->route('cash-advances.show', $cashAdvance)
            ->with('success', 'Cash advance #' . $cashAdvance->ca_number . ' rejected.');
    }

    public function recordPayment(Request $request, CashAdvance $cashAdvance)
    {
        if (!$this->canProcessCA()) {
            AuditLog::log('error', 'cash_advances', 'Unauthorized payment recording attempt for #' . $cashAdvance->ca_number);
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'amortization_id' => 'required|exists:cash_advance_amortizations,id',
            'amount_paid' => 'required|numeric|min:0',
            'week_no' => 'nullable|string',
        ]);

        $amortization = CashAdvanceAmortization::findOrFail($request->amortization_id);
        $amountPaid = $request->amount_paid;
        $interestPaid = min($amountPaid, $amortization->interest_paid);
        $principalPaid = $amountPaid - $interestPaid;

        $amortization->amount_paid = $amountPaid;
        $amortization->interest_paid = $interestPaid;
        $amortization->principal_paid = $principalPaid;
        $amortization->balance_after = $cashAdvance->balance - $amountPaid;
        $amortization->status = $amountPaid >= $amortization->amount_due ? 'paid' : 'partial';
        $amortization->paid_date = now();
        $amortization->week_no = $request->week_no;
        $amortization->save();

        $cashAdvance->balance = $cashAdvance->amortizations()->sum('balance_after');
        $pendingPayments = $cashAdvance->amortizations()->whereIn('status', ['pending', 'partial', 'overdue'])->count();
        if ($pendingPayments === 0) {
            $cashAdvance->status = 'completed';
        }
        $cashAdvance->save();

        AuditLog::log('payment', 'cash_advances', 'Recorded payment for cash advance #' . $cashAdvance->ca_number, [
            'amount_paid' => $amountPaid,
            'payment_number' => $amortization->payment_number,
            'week_no' => $request->week_no,
            'remaining_balance' => $cashAdvance->balance,
        ]);

        return redirect()->route('cash-advances.show', $cashAdvance)
            ->with('success', 'Payment recorded successfully.');
    }

    public function destroy(CashAdvance $cashAdvance)
    {
        if (!$this->canProcessCA()) {
            AuditLog::log('error', 'cash_advances', 'Unauthorized deletion attempt for #' . $cashAdvance->ca_number);
            abort(403, 'Unauthorized');
        }

        if (!in_array($cashAdvance->status, ['pending', 'rejected'])) {
            return back()->with('error', 'Only pending or rejected cash advances can be deleted.');
        }

        $caInfo = [
            'ca_number' => $cashAdvance->ca_number,
            'planter' => $cashAdvance->planter_name,
            'amount' => $cashAdvance->amount,
        ];

        $cashAdvance->amortizations()->delete();
        $cashAdvance->delete();

        AuditLog::log('delete', 'cash_advances', 'Deleted cash advance #' . $caInfo['ca_number'], $caInfo);

        return redirect()->route('cash-advances.index')
            ->with('success', 'Cash advance deleted successfully.');
    }

    public function settings()
{
    if (!$this->canManageSettings()) {
        AuditLog::log('error', 'cash_advances', 'Unauthorized settings access attempt');
        abort(403, 'Unauthorized');
    }

    $settings = [
        'ca_default_interest_rate' => LoanSetting::get('ca_default_interest_rate', 3),
        'ca_max_term_months' => LoanSetting::get('ca_max_term_months', 6),
        'ca_min_amount' => LoanSetting::get('ca_min_amount', 500),
        'ca_max_amount' => LoanSetting::get('ca_max_amount', 10000),
        'ca_receipt_weekly_interest' => LoanSetting::get('ca_receipt_weekly_interest', 1),
    ];

    return view('cash-advances.settings', compact('settings'));
}

    public function updateSettings(Request $request)
{
    if (!$this->canManageSettings()) {
        abort(403, 'Unauthorized');
    }

    $request->validate([
        'ca_default_interest_rate' => 'required|numeric|min:0|max:100',
        'ca_max_term_months' => 'required|integer|min:1',
        'ca_min_amount' => 'required|numeric|min:0',
        'ca_max_amount' => 'required|numeric|min:0',
        'ca_receipt_weekly_interest' => 'required|numeric|min:0|max:100',
        'ca_auto_deduct' => 'nullable|boolean',
    ]);

    $oldSettings = [
        'ca_default_interest_rate' => LoanSetting::get('ca_default_interest_rate'),
        'ca_max_term_months' => LoanSetting::get('ca_max_term_months'),
        'ca_min_amount' => LoanSetting::get('ca_min_amount'),
        'ca_max_amount' => LoanSetting::get('ca_max_amount'),
        'ca_auto_deduct' => LoanSetting::get('ca_auto_deduct'),
    ];

    foreach ($request->except('_token') as $key => $value) {
        LoanSetting::set($key, $value, is_numeric($value) ? (str_contains($key, 'amount') || str_contains($key, 'rate') ? 'decimal' : 'integer') : 'string');
    }

    // Handle checkbox
    LoanSetting::set('ca_auto_deduct', $request->has('ca_auto_deduct') ? true : false, 'boolean');

    AuditLog::log('update', 'cash_advance_settings', 'Updated cash advance settings', [
        'old' => $oldSettings,
        'new' => $request->except('_token'),
    ]);

    return back()->with('success', 'Cash advance settings updated successfully.');
}

    private function generateAmortizationSchedule(CashAdvance $ca)
    {
        $startDate = Carbon::parse($ca->start_date);
        $balance = $ca->total_amount;

        for ($i = 1; $i <= $ca->term_months; $i++) {
            $dueDate = $startDate->copy()->addMonths($i);
            $monthlyInterest = ($balance * ($ca->interest_rate / 100)) / 12;
            $monthlyPrincipal = $ca->monthly_amortization - $monthlyInterest;

            CashAdvanceAmortization::create([
                'cash_advance_id' => $ca->id,
                'payment_number' => $i,
                'due_date' => $dueDate,
                'amount_due' => $ca->monthly_amortization,
                'interest_paid' => $monthlyInterest,
                'principal_paid' => $monthlyPrincipal,
                'balance_after' => $balance - $ca->monthly_amortization,
                'status' => 'pending',
            ]);

            $balance -= $ca->monthly_amortization;
        }
    }

    public function getActiveCADeductions($planterCode, $cropYear, $weekNo)
{
    return CashAdvanceAmortization::whereHas('cashAdvance', function($q) use ($planterCode, $cropYear) {
        $q->where('planter_code', $planterCode)
          ->where('crop_year', $cropYear)
          ->whereIn('status', ['active']);
    })
    ->where('status', 'pending')
    ->where('week_no', $weekNo)
    ->sum('amount_due');
}
}
