<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Loan;
use App\Models\LoanType;
use App\Models\LoanAmortization;
use App\Models\LoanPayment;
use App\Models\LoanSetting;
use App\Models\Summary;
use App\Models\CropYear;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\PlanterProfile;
use App\Services\LoanSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanController extends Controller
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

    private function canApproveLoans(): bool
    {
        $role = $this->currentRole();
        if (in_array($role, ['Administrator', 'super_admin'])) {
            return true;
        }
        if ($role === 'manager') {
            return true;
        }
        return $this->user()->hasPermission('approve-loans');
    }

    private function canProcessLoans(): bool
    {
        $role = $this->currentRole();
        if (in_array($role, ['Administrator', 'super_admin', 'manager'])) {
            return true;
        }
        return $this->user()->hasPermission('create-loans') || 
               $this->user()->hasPermission('process-loan-payments');
    }

    private function canManageSettings(): bool
    {
        $role = $this->currentRole();
        if (in_array($role, ['Administrator', 'super_admin', 'manager'])) {
            return true;
        }
        return $this->user()->hasPermission('manage-loan-settings');
    }

     private function getActivePlanters()
    {
        return PlanterProfile::where('status', 'active')
            ->select('planter_code', 'planter_name')
            ->orderBy('planter_name')
            ->get();
    }

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if ($this->currentRole() === 'Viewer' && !$this->user()->hasAnyPermission([
                'access-all-loans', 'create-loans', 'approve-loans', 'process-loan-payments',
                'manage-loan-settings', 'view-loan-reports',
            ])) {
                if (!in_array($request->route()->getName(), ['loans.index', 'loans.show'])) {
                    return redirect()->route('loans.index')
                        ->with('error', 'You only have view access to loans.');
                }
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $query = Loan::with(['loanType', 'approvedByUser'])->withCount('attachments');

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
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

        $loans = $query->orderBy('created_at', 'desc')->paginate(20);

        $loans->getCollection()->transform(function ($loan) {
            $loan->loan_type_name = $loan->loanType->name ?? 'N/A';
            return $loan;
        });

        $loanTypes = LoanType::where('is_active', true)->get();
        $cropYears = CropYear::pluck('crop_year');
        $planters = $this->getActivePlanters();
        $stats = $this->getLoanStats();

        $userPermissions = [
            'canCreate' => $this->canProcessLoans(),
            'canApprove' => $this->canApproveLoans(),
            'canManageSettings' => $this->canManageSettings(),
        ];

        $maxTerm = (int) LoanSetting::get('max_loan_term_months', 24);

        return view('loans.index', compact('loans', 'loanTypes', 'cropYears', 'planters', 'stats', 'userPermissions', 'maxTerm'));
    }

    public function create()
    {
        $loanTypes = LoanType::where('is_active', true)->get();
        $cropYears = CropYear::pluck('crop_year');
        $planters = $this->getActivePlanters();
        $settings = [
            'default_interest' => LoanSetting::get('default_interest_rate', 5),
            'max_term' => LoanSetting::get('max_loan_term_months', 24),
            'min_amount' => LoanSetting::get('min_loan_amount', 1000),
            'max_amount' => LoanSetting::get('max_loan_amount', 100000),
        ];

        return view('loans.create', compact('loanTypes', 'cropYears', 'planters', 'settings'));
    }

    public function store(Request $request)
    {
        if (!$this->canProcessLoans()) {
            AuditLog::log('error', 'loans', 'Unauthorized loan creation attempt');
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            abort(403, 'Unauthorized');
        }

        $data = $request->validate([
            'planter_code' => 'required|string',
            'planter_name' => 'required|string',
            'loan_type_id' => 'required|exists:loan_types,id',
            'principal_amount' => 'required|numeric|min:0.01',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'term_months' => 'required|integer|min:1|max:60',
            'crop_year' => 'required|string',
            'purpose' => 'nullable|string',
            // Monthly payment schedule; must add up to the total repayable
            'schedule' => 'nullable|array',
            'schedule.*.month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/', 'distinct'],
            'schedule.*.due_date' => 'nullable|date',
            'schedule.*.amount' => 'required|numeric|min:0.01',
        ], [
            'schedule.*.month.distinct' => 'Each month can only be listed once.',
            'schedule.*.amount.min' => 'Each month needs an amount.',
        ]);

        $loan = new Loan(collect($data)->except('schedule')->all());
        $loan->loan_number = Loan::generateLoanNumber();
        $loan->application_date = now();
        $loan->status = 'pending';
        $loan->created_by = auth()->id();
        $loan->calculateAmortization();
        // Total repayable in centavos, the amount the months must add up to
        $loan->total_amount = round($loan->total_amount, 2);
        $loan->balance = $loan->total_amount;

        // Without a schedule (older create page) the total is split evenly from next month
        $schedule = !empty($data['schedule'])
            ? collect($data['schedule'])
            : LoanSchedule::even($loan->total_amount, (int) $data['term_months']);
        LoanSchedule::validate($loan, $schedule);

        DB::transaction(function () use ($loan, $schedule) {
            $loan->save();
            LoanSchedule::apply($loan, $schedule);
        });

        AuditLog::log('create', 'loans', 'Created loan application #' . $loan->loan_number, [
            'amount' => $loan->principal_amount,
            'planter' => $loan->planter_name,
            'planter_code' => $loan->planter_code,
            'interest_rate' => $loan->interest_rate . '%',
            'term' => $loan->term_months . ' months',
            'total_repayable' => $loan->total_amount,
            'monthly_schedule' => $schedule->map(fn ($r) => $r['month'] . ' = ' . $r['amount'])->implode(', '),
        ]);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Loan application #' . $loan->loan_number . ' created successfully.',
                'loan' => $loan->load('loanType')
            ], 201);
        }

        return redirect()->route('loans.index')
            ->with('success', 'Loan application #' . $loan->loan_number . ' created successfully.');
    }

    public function show(Loan $loan)
    {
        $loan->load(['loanType', 'amortizations' => function($q) {
            $q->orderBy('payment_number');
        }, 'approvedByUser', 'createdByUser']);

        $userPermissions = [
            'canApprove' => $this->canApproveLoans() && $loan->status === 'pending',
            'canActivate' => $this->canApproveLoans() && $loan->status === 'approved',
            'canReject' => $this->canApproveLoans() && $loan->status === 'pending',
            // Payments can start once approved; the first payment activates the loan
            'canPay' => $this->canProcessLoans() && in_array($loan->status, ['approved', 'active']),
            'canUndoPayment' => $this->canProcessLoans() && in_array($loan->status, ['active', 'completed']),
            'canEditSchedule' => $this->canProcessLoans() && in_array($loan->status, ['pending', 'approved', 'active']),
            'canDelete' => $this->canProcessLoans() && in_array($loan->status, ['pending', 'rejected']),
        ];

        $monthlyRows = $loan->monthlyRows();
        $payments = $loan->payments()->with('recordedByUser')->get();

        return view('loans.show', compact('loan', 'userPermissions', 'monthlyRows', 'payments'));
    }

    public function approve(Request $request, Loan $loan)
    {
        if (!$this->canApproveLoans()) {
            AuditLog::log('error', 'loans', 'Unauthorized loan approval attempt for #' . $loan->loan_number);
            abort(403, 'Unauthorized. Only managers and administrators can approve loans.');
        }

        if ($loan->status !== 'pending') {
            return back()->with('error', 'Only pending loans can be approved.');
        }

        $request->validate(['start_date' => 'nullable|date']);

        // The monthly schedule was set when the loan was created; its last month ends the loan
        if (!$loan->amortizations()->exists()) {
            DB::transaction(fn () => LoanSchedule::apply($loan, LoanSchedule::even((float) $loan->total_amount, (int) $loan->term_months)));
        }

        $loan->status = 'approved';
        $loan->approved_by = auth()->id();
        $loan->approved_date = now();
        $loan->start_date = $request->start_date ?: now()->toDateString();
        $loan->end_date = $loan->amortizations()->max('due_date');
        $loan->save();

        AuditLog::log('approve', 'loans', 'Approved loan #' . $loan->loan_number, [
            'amount' => $loan->principal_amount,
            'planter' => $loan->planter_name,
            'start_date' => $loan->start_date->format('Y-m-d'),
            'monthly_amortization' => $loan->monthly_amortization,
            'total_amount' => $loan->total_amount,
        ]);

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Loan #' . $loan->loan_number . ' approved successfully.');
    }

    public function activate(Loan $loan)
    {
        if (!$this->canApproveLoans()) {
            AuditLog::log('error', 'loans', 'Unauthorized loan activation attempt for #' . $loan->loan_number);
            abort(403, 'Unauthorized');
        }

        if ($loan->status !== 'approved') {
            return back()->with('error', 'Only approved loans can be activated.');
        }

        $loan->status = 'active';
        $loan->save();

        AuditLog::log('activate', 'loans', 'Activated loan #' . $loan->loan_number, [
            'planter' => $loan->planter_name,
            'amount' => $loan->principal_amount,
        ]);

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Loan #' . $loan->loan_number . ' is now active.');
    }

    public function reject(Request $request, Loan $loan)
    {
        if (!$this->canApproveLoans()) {
            AuditLog::log('error', 'loans', 'Unauthorized loan rejection attempt for #' . $loan->loan_number);
            abort(403, 'Unauthorized. Only managers and administrators can reject loans.');
        }

        if ($loan->status !== 'pending') {
            return back()->with('error', 'Only pending loans can be rejected.');
        }

        $loan->status = 'rejected';
        $loan->remarks = $request->remarks;
        $loan->save();

        AuditLog::log('reject', 'loans', 'Rejected loan #' . $loan->loan_number, [
            'planter' => $loan->planter_name,
            'amount' => $loan->principal_amount,
            'reason' => $request->remarks,
        ]);

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Loan #' . $loan->loan_number . ' rejected.');
    }

    /**
     * Record a repayment against one month of the schedule
     */
    public function storePayment(Request $request, Loan $loan)
    {
        if (!$this->canProcessLoans()) {
            AuditLog::log('error', 'loans', 'Unauthorized payment recording attempt for #' . $loan->loan_number);
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (!in_array($loan->status, ['approved', 'active'])) {
            return response()->json(['message' => 'Payments can only be recorded on an approved or active loan.'], 422);
        }

        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amount' => 'required|numeric|min:0.01',
            'paid_date' => 'required|date',
            'reference' => 'nullable|string|max:50',
            'remarks' => 'nullable|string|max:255',
        ]);

        $row = $loan->monthlyRows()->firstWhere('month', $data['month']);
        if (!$row) {
            throw ValidationException::withMessages(['month' => LoanSchedule::label($data['month']) . ' is not in this loan\'s schedule.']);
        }
        $amount = round((float) $data['amount'], 2);
        if ((int) round($amount * 100) > (int) round($row['remaining'] * 100)) {
            throw ValidationException::withMessages([
                'amount' => LoanSchedule::label($row['month']) . ' only has ₱' . number_format($row['remaining'], 2) . ' left to pay.',
            ]);
        }

        DB::transaction(function () use ($loan, $data, $amount) {
            // The first payment on an approved loan activates it
            if ($loan->status === 'approved') {
                $loan->status = 'active';
            }
            $loan->payments()->create([
                'month' => $data['month'],
                'amount' => $amount,
                'paid_date' => $data['paid_date'],
                'reference' => $data['reference'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'recorded_by' => auth()->id(),
            ]);
            $loan->syncBalance();
        });

        AuditLog::log('payment', 'loans', 'Recorded payment for loan #' . $loan->loan_number, [
            'month' => $data['month'],
            'amount_paid' => $amount,
            'paid_date' => $data['paid_date'],
            'reference' => $data['reference'] ?? null,
            'remaining_balance' => $loan->balance,
        ]);

        return response()->json([
            'message' => 'Payment of ₱' . number_format($amount, 2) . ' recorded for ' . LoanSchedule::label($data['month']) . '.'
                . ($loan->status === 'completed' ? ' The loan is now fully paid.' : ''),
            'balance' => $loan->balance,
            'status' => $loan->status,
        ]);
    }

    /**
     * Undo a repayment recorded by mistake
     */
    public function destroyPayment(Loan $loan, LoanPayment $payment)
    {
        if (!$this->canProcessLoans()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        abort_unless($payment->loan_id === $loan->id, 404);
        if (!in_array($loan->status, ['active', 'completed'])) {
            return response()->json(['message' => 'Payments can only be undone on an active or completed loan.'], 422);
        }

        $info = ['month' => $payment->month, 'amount' => $payment->amount, 'paid_date' => $payment->paid_date?->format('Y-m-d'), 'reference' => $payment->reference];

        DB::transaction(function () use ($loan, $payment) {
            $payment->delete();
            $loan->syncBalance();
        });

        AuditLog::log('delete', 'loans', 'Undid payment for loan #' . $loan->loan_number, $info + ['remaining_balance' => $loan->balance]);

        return response()->json([
            'message' => 'Payment of ₱' . number_format($info['amount'], 2) . ' for ' . LoanSchedule::label($info['month']) . ' was undone.',
            'balance' => $loan->balance,
            'status' => $loan->status,
        ]);
    }

    /**
     * Save the monthly schedule from the loan page
     */
    public function updateSchedule(Request $request, Loan $loan)
    {
        if (!$this->canProcessLoans()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (!in_array($loan->status, ['pending', 'approved', 'active'])) {
            return response()->json(['message' => 'The schedule can only be changed on a pending, approved or active loan.'], 422);
        }

        $data = $request->validate([
            'schedule' => 'required|array|min:1',
            'schedule.*.month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/', 'distinct'],
            'schedule.*.due_date' => 'nullable|date',
            'schedule.*.amount' => 'required|numeric|min:0.01',
        ], [
            'schedule.*.month.distinct' => 'Each month can only be listed once.',
            'schedule.*.amount.min' => 'Each month needs an amount.',
        ]);

        $rows = collect($data['schedule']);
        LoanSchedule::validate($loan, $rows);
        DB::transaction(function () use ($loan, $rows) {
            LoanSchedule::apply($loan, $rows);
            if ($loan->start_date) {
                $loan->end_date = $loan->amortizations()->max('due_date');
                $loan->save();
            }
        });

        AuditLog::log('update', 'loans', 'Updated monthly schedule of loan #' . $loan->loan_number, [
            'monthly_schedule' => $rows->map(fn ($r) => $r['month'] . ' = ' . $r['amount'])->implode(', '),
        ]);

        return response()->json(['message' => 'Monthly schedule saved.']);
    }

    public function destroy(Loan $loan)
    {
        if (!$this->canProcessLoans()) {
            AuditLog::log('error', 'loans', 'Unauthorized loan deletion attempt for #' . $loan->loan_number);
            abort(403, 'Unauthorized');
        }

        if (!in_array($loan->status, ['pending', 'rejected'])) {
            return back()->with('error', 'Only pending or rejected loans can be deleted.');
        }

        $loanInfo = [
            'loan_number' => $loan->loan_number,
            'planter' => $loan->planter_name,
            'amount' => $loan->principal_amount,
            'status' => $loan->status,
        ];

        $loan->amortizations()->delete();
        $loan->delete();

        AuditLog::log('delete', 'loans', 'Deleted loan #' . $loanInfo['loan_number'], $loanInfo);

        return redirect()->route('loans.index')
            ->with('success', 'Loan deleted successfully.');
    }

    public function settings()
    {
        if (!$this->canManageSettings()) {
            AuditLog::log('error', 'loans', 'Unauthorized loan settings access attempt');
            abort(403, 'Unauthorized. Only managers and administrators can access loan settings.');
        }

        $settings = [
            'default_interest_rate' => LoanSetting::get('default_interest_rate', 5),
            'max_loan_term_months' => LoanSetting::get('max_loan_term_months', 24),
            'min_loan_amount' => LoanSetting::get('min_loan_amount', 1000),
            'max_loan_amount' => LoanSetting::get('max_loan_amount', 100000),
            'auto_deduct' => LoanSetting::get('auto_deduct', true),
        ];

        $loanTypes = LoanType::all();

        return view('loans.settings', compact('settings', 'loanTypes'));
    }

    public function updateSettings(Request $request)
    {
        if (!$this->canManageSettings()) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'default_interest_rate' => 'required|numeric|min:0|max:100',
            'max_loan_term_months' => 'required|integer|min:1',
            'min_loan_amount' => 'required|numeric|min:0',
            'max_loan_amount' => 'required|numeric|min:0',
            'auto_deduct' => 'nullable|boolean',
        ]);

        $oldSettings = [
            'default_interest_rate' => LoanSetting::get('default_interest_rate'),
            'max_loan_term_months' => LoanSetting::get('max_loan_term_months'),
            'min_loan_amount' => LoanSetting::get('min_loan_amount'),
            'max_loan_amount' => LoanSetting::get('max_loan_amount'),
            'auto_deduct' => LoanSetting::get('auto_deduct'),
        ];

        foreach ($request->except('_token') as $key => $value) {
            LoanSetting::set($key, $value);
        }

        AuditLog::log('update', 'loan_settings', 'Updated loan settings', [
            'old' => $oldSettings,
            'new' => $request->except('_token'),
        ]);

        return back()->with('success', 'Loan settings updated successfully.');
    }

    public function storeLoanType(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'default_interest_rate' => 'required|numeric|min:0|max:100',
            'default_term_months' => 'required|integer|min:1',
            'max_amount' => 'required|numeric|min:0',
        ]);

        $loanType = LoanType::create($request->all());

        AuditLog::log('create', 'loan_types', 'Created loan type: ' . $loanType->name, [
            'interest_rate' => $loanType->default_interest_rate . '%',
            'term' => $loanType->default_term_months . ' months',
            'max_amount' => $loanType->max_amount,
        ]);

        return back()->with('success', 'Loan type created successfully.');
    }

    public function toggleLoanType(LoanType $loanType)
    {
        $loanType->is_active = !$loanType->is_active;
        $loanType->save();

        AuditLog::log($loanType->is_active ? 'activate' : 'deactivate', 'loan_types', 
            ($loanType->is_active ? 'Activated' : 'Deactivated') . ' loan type: ' . $loanType->name);

        return back()->with('success', 'Loan type updated.');
    }

    private function getLoanStats()
    {
        return [
            'total_active' => Loan::where('status', 'active')->count(),
            'total_pending' => Loan::where('status', 'pending')->count(),
            'total_amount' => Loan::whereIn('status', ['active', 'approved'])->sum('principal_amount'),
            'total_collected' => LoanPayment::sum('amount'),
            'overdue_payments' => LoanAmortization::where('status', 'overdue')->count(),
        ];
    }
}
