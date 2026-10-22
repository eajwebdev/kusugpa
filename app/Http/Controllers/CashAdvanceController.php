<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\CashAdvance;
use App\Models\CashAdvanceAmortization;
use App\Models\CashAdvancePayment;
use App\Models\LoanSetting;
use App\Models\Summary;
use App\Models\CropYear;
use App\Models\WeekNo;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\PlanterProfile;
use App\Services\CashAdvanceSchedule;
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
            'total_collected' => CashAdvanceAmortization::where('status', 'paid')->sum('amount_paid')
                + CashAdvancePayment::sum('amount'),
        ];

        $userPermissions = [
            'canCreate' => $this->canProcessCA(),
            'canApprove' => $this->canApproveCA(),
            'canManageSettings' => $this->canManageSettings(),
            'canEditReceipt' => CaReceiptController::canManageReceipt($this->user()),
        ];
        $signatoryDefaults = CaReceiptController::signatoryDefaults();

        // Weeks per crop year (Crop & Week Management) for the new CA weekly schedule
        // and the receipt's deduction-week picker
        $weeks = WeekNo::orderByRaw('CAST(week_no AS UNSIGNED) ASC')
            ->get(['crop_year', 'week_no', 'week_start_date', 'week_end_date'])
            ->map(fn ($w) => [
                'crop_year' => $w->crop_year,
                'week_no' => (string) $w->week_no,
                'week_start' => $w->week_start_date ? Carbon::parse($w->week_start_date)->toDateString() : null,
                'week_end' => $w->week_end_date ? Carbon::parse($w->week_end_date)->toDateString() : null,
            ]);

        return view('cash-advances.index', compact('cashAdvances', 'cropYears', 'planters', 'stats', 'userPermissions', 'weeks', 'signatoryDefaults'));
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

        $data = $request->validate([
            'planter_code' => 'required|string',
            'planter_name' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'term_weeks' => 'required_without:term_months|nullable|integer|min:1|max:60',
            'term_months' => 'required_without:term_weeks|nullable|integer|min:1|max:12',
            'crop_year' => 'required|string',
            'purpose' => 'nullable|string|max:500',
            // Weekly payment schedule; its total does not have to equal the advance amount
            'schedule' => 'array',
            'schedule.*.week_no' => 'required|string|distinct',
            'schedule.*.due_date' => 'nullable|date',
            'schedule.*.amount' => 'nullable|numeric|min:0',
        ], [
            'schedule.*.week_no.distinct' => 'Each week can only be listed once.',
        ]);

        $schedule = collect($data['schedule'] ?? []);

        $validWeeks = WeekNo::where('crop_year', $data['crop_year'])->pluck('week_no')->map(fn ($w) => (string) $w);
        $unknown = $schedule->pluck('week_no')->reject(fn ($w) => $validWeeks->contains((string) $w));
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                'schedule' => 'Week ' . $unknown->implode(', ') . ' is not set up for crop year ' . $data['crop_year'] . ' in Crop & Week Management.',
            ]);
        }

        // A weekly term needs a schedule whose total equals the advance amount
        if (!empty($data['term_weeks'])) {
            if ($schedule->isEmpty()) {
                throw ValidationException::withMessages([
                    'schedule' => 'No weeks are set up for crop year ' . $data['crop_year'] . ' in Crop & Week Management.',
                ]);
            }
            $allocatedCents = (int) $schedule->sum(fn ($r) => (int) round((float) ($r['amount'] ?? 0) * 100));
            $amountCents = (int) round((float) $data['amount'] * 100);
            if ($allocatedCents !== $amountCents) {
                throw ValidationException::withMessages([
                    'schedule' => 'Weekly payments total (₱' . number_format($allocatedCents / 100, 2) . ') must equal the advance amount (₱' . number_format($amountCents / 100, 2) . ').',
                ]);
            }
        }

        $ca = new CashAdvance(collect($data)->except(['schedule', 'term_months'])->all());
        // Monthly amortization fields still drive approval; derive the months from the weeks
        $ca->term_months = !empty($data['term_weeks'])
            ? max(1, (int) ceil($data['term_weeks'] / 4))
            : $data['term_months'];
        $ca->ca_number = CashAdvance::generateCANumber();
        $ca->application_date = now();
        $ca->status = 'pending';
        $ca->created_by = auth()->id();
        $ca->calculateAmortization();
        if (!empty($data['term_weeks'])) {
            // Repaid by the weekly schedule: total and balance are the principal
            $ca->total_amount = $ca->amount;
            $ca->balance = $ca->amount;
        }

        DB::transaction(function () use ($ca, $schedule) {
            $ca->save();
            foreach ($schedule as $row) {
                $amount = round((float) ($row['amount'] ?? 0), 2);
                if ($amount <= 0) {
                    continue;
                }
                $ca->deductions()->create([
                    'crop_year' => $ca->crop_year,
                    'week_no' => (string) $row['week_no'],
                    'due_date' => $row['due_date'] ?? null,
                    'amount' => $amount,
                ]);
            }
        });

        AuditLog::log('create', 'cash_advances', 'Created cash advance #' . $ca->ca_number, [
            'amount' => $ca->amount,
            'planter' => $ca->planter_name,
            'interest_rate' => $ca->interest_rate . '%',
            'term' => $ca->term_weeks ? $ca->term_weeks . ' weeks' : $ca->term_months . ' months',
            'monthly' => $ca->monthly_amortization,
            'weekly_schedule' => $schedule->map(fn ($r) => 'Wk ' . $r['week_no'] . ' = ' . ($r['amount'] ?? 0))->implode(', '),
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
            // Weekly repayments can start once approved; the first payment activates the advance
            'canPayWeekly' => $this->canProcessCA() && in_array($cashAdvance->status, ['approved', 'active']),
            'canUndoPayment' => $this->canProcessCA() && in_array($cashAdvance->status, ['active', 'completed']),
            'canEditSchedule' => $this->canProcessCA() && in_array($cashAdvance->status, ['pending', 'approved', 'active']),
            'canDelete' => $this->canProcessCA() && in_array($cashAdvance->status, ['pending', 'rejected']),
        ];

        // Weekly cash advances: repayment follows the weekly schedule
        $isWeekly = $cashAdvance->isWeekly();
        $weeklyRows = $isWeekly ? $cashAdvance->weeklyRows() : collect();
        $payments = $isWeekly ? $cashAdvance->payments()->with('recordedByUser')->get() : collect();

        // An older monthly cash advance can switch to a weekly schedule while no monthly payment is recorded
        $canSwitchToWeekly = !$isWeekly && $userPermissions['canEditSchedule']
            && !$cashAdvance->amortizations->contains(fn ($a) => $a->amount_paid > 0);

        // Weeks of the crop year for the schedule editor
        $cropWeeks = WeekNo::where('crop_year', $cashAdvance->crop_year)
            ->orderByRaw('CAST(week_no AS UNSIGNED) ASC')
            ->get(['week_no', 'week_start_date', 'week_end_date'])
            ->map(fn ($w) => [
                'week_no' => (string) $w->week_no,
                'week_start' => $w->week_start_date ? Carbon::parse($w->week_start_date)->toDateString() : null,
                'week_end' => $w->week_end_date ? Carbon::parse($w->week_end_date)->toDateString() : null,
            ]);

        return view('cash-advances.show', compact('cashAdvance', 'userPermissions', 'isWeekly', 'weeklyRows', 'payments', 'canSwitchToWeekly', 'cropWeeks'));
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

        $isWeekly = $cashAdvance->isWeekly();
        // Weekly cash advances take their dates from the schedule; monthly ones need a start date
        $request->validate(['start_date' => $isWeekly ? 'nullable|date' : 'required|date']);

        $cashAdvance->status = 'approved';
        $cashAdvance->approved_by = auth()->id();
        $cashAdvance->approved_date = now();

        if ($isWeekly) {
            $dueDates = $cashAdvance->deductions()->whereNotNull('due_date')->pluck('due_date');
            $cashAdvance->start_date = $request->start_date ?: now()->toDateString();
            $cashAdvance->end_date = $dueDates->max() ?? $cashAdvance->start_date;
            $cashAdvance->syncWeeklyBalance();
        } else {
            $cashAdvance->start_date = $request->start_date;
            $cashAdvance->end_date = Carbon::parse($request->start_date)->addMonths($cashAdvance->term_months);
            $cashAdvance->save();

            $this->generateAmortizationSchedule($cashAdvance);
        }

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

    /**
     * Record a repayment against one week of a weekly cash advance
     */
    public function storeWeeklyPayment(Request $request, CashAdvance $cashAdvance)
    {
        if (!$this->canProcessCA()) {
            AuditLog::log('error', 'cash_advances', 'Unauthorized payment recording attempt for #' . $cashAdvance->ca_number);
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (!in_array($cashAdvance->status, ['approved', 'active'])) {
            return response()->json(['message' => 'Payments can only be recorded on an approved or active cash advance.'], 422);
        }

        $data = $request->validate([
            'week_no' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'paid_date' => 'required|date',
            'reference' => 'nullable|string|max:50',
            'remarks' => 'nullable|string|max:255',
        ]);

        $week = $cashAdvance->weeklyRows()->firstWhere('week_no', (string) $data['week_no']);
        if (!$week) {
            throw ValidationException::withMessages(['week_no' => 'Week ' . $data['week_no'] . ' is not in this cash advance\'s schedule.']);
        }
        $amount = round((float) $data['amount'], 2);
        if ((int) round($amount * 100) > (int) round($week['remaining'] * 100)) {
            throw ValidationException::withMessages([
                'amount' => 'Week ' . $week['week_no'] . ' only has ₱' . number_format($week['remaining'], 2) . ' left to pay.',
            ]);
        }

        DB::transaction(function () use ($cashAdvance, $data, $amount) {
            // The first payment on an approved advance activates it
            if ($cashAdvance->status === 'approved') {
                $cashAdvance->status = 'active';
            }
            $cashAdvance->payments()->create([
                'week_no' => (string) $data['week_no'],
                'amount' => $amount,
                'paid_date' => $data['paid_date'],
                'reference' => $data['reference'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'recorded_by' => auth()->id(),
            ]);
            $cashAdvance->syncWeeklyBalance();
        });

        AuditLog::log('payment', 'cash_advances', 'Recorded payment for cash advance #' . $cashAdvance->ca_number, [
            'week_no' => $data['week_no'],
            'amount_paid' => $amount,
            'paid_date' => $data['paid_date'],
            'reference' => $data['reference'] ?? null,
            'remaining_balance' => $cashAdvance->balance,
        ]);

        return response()->json([
            'message' => 'Payment of ₱' . number_format($amount, 2) . ' recorded for Week ' . $data['week_no'] . '.'
                . ($cashAdvance->status === 'completed' ? ' The cash advance is now fully paid.' : ''),
            'balance' => $cashAdvance->balance,
            'status' => $cashAdvance->status,
        ]);
    }

    /**
     * Save the weekly schedule from the cash advance page (also switches an older monthly advance to weekly)
     */
    public function updateSchedule(Request $request, CashAdvance $cashAdvance)
    {
        if (!$this->canProcessCA()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (!in_array($cashAdvance->status, ['pending', 'approved', 'active'])) {
            return response()->json(['message' => 'The schedule can only be changed on a pending, approved or active cash advance.'], 422);
        }

        $data = $request->validate([
            'schedule' => 'required|array|min:1',
            'schedule.*.week_no' => 'required|string|distinct',
            'schedule.*.due_date' => 'nullable|date',
            'schedule.*.amount' => 'required|numeric|min:0.01',
        ], [
            'schedule.*.week_no.distinct' => 'Each week can only be listed once.',
            'schedule.*.amount.min' => 'Each week needs an amount.',
        ]);

        $rows = collect($data['schedule']);
        $wasWeekly = $cashAdvance->isWeekly();
        CashAdvanceSchedule::validate($cashAdvance, $rows);
        DB::transaction(fn () => CashAdvanceSchedule::apply($cashAdvance, $rows));

        AuditLog::log('update', 'cash_advances', ($wasWeekly ? 'Updated weekly schedule of' : 'Switched to weekly schedule:') . ' cash advance #' . $cashAdvance->ca_number, [
            'weekly_schedule' => $rows->map(fn ($r) => 'Wk ' . $r['week_no'] . ' = ' . $r['amount'])->implode(', '),
        ]);

        return response()->json(['message' => $wasWeekly ? 'Weekly schedule saved.' : 'Switched to a weekly schedule.']);
    }

    /**
     * Undo a repayment recorded by mistake
     */
    public function destroyWeeklyPayment(CashAdvance $cashAdvance, CashAdvancePayment $payment)
    {
        if (!$this->canProcessCA()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        abort_unless($payment->cash_advance_id === $cashAdvance->id, 404);
        if (!in_array($cashAdvance->status, ['active', 'completed'])) {
            return response()->json(['message' => 'Payments can only be undone on an active or completed cash advance.'], 422);
        }

        $info = ['week_no' => $payment->week_no, 'amount' => $payment->amount, 'paid_date' => $payment->paid_date?->format('Y-m-d'), 'reference' => $payment->reference];

        DB::transaction(function () use ($cashAdvance, $payment) {
            $payment->delete();
            $cashAdvance->syncWeeklyBalance();
        });

        AuditLog::log('delete', 'cash_advances', 'Undid payment for cash advance #' . $cashAdvance->ca_number, $info + ['remaining_balance' => $cashAdvance->balance]);

        return response()->json([
            'message' => 'Payment of ₱' . number_format($info['amount'], 2) . ' for Week ' . $info['week_no'] . ' was undone.',
            'balance' => $cashAdvance->balance,
            'status' => $cashAdvance->status,
        ]);
    }

    public function destroy(Request $request, CashAdvance $cashAdvance)
    {
        if (!$this->canProcessCA()) {
            AuditLog::log('error', 'cash_advances', 'Unauthorized deletion attempt for #' . $cashAdvance->ca_number);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            abort(403, 'Unauthorized');
        }

        if (!in_array($cashAdvance->status, ['pending', 'rejected'])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Only pending or rejected cash advances can be deleted.'], 422);
            }
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

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Cash advance #' . $caInfo['ca_number'] . ' deleted.']);
        }

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
        'ca_auto_deduct' => LoanSetting::get('ca_auto_deduct', false),
        // Default signatories of the CA receipt
        'ca_sig_committee' => LoanSetting::get('ca_sig_committee', ''),
        'ca_sig_prepared' => LoanSetting::get('ca_sig_prepared', ''),
        'ca_sig_approved' => LoanSetting::get('ca_sig_approved', ''),
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
        'ca_sig_committee' => 'nullable|string|max:100',
        'ca_sig_prepared' => 'nullable|string|max:100',
        'ca_sig_approved' => 'nullable|string|max:100',
    ]);

    $oldSettings = [
        'ca_default_interest_rate' => LoanSetting::get('ca_default_interest_rate'),
        'ca_max_term_months' => LoanSetting::get('ca_max_term_months'),
        'ca_min_amount' => LoanSetting::get('ca_min_amount'),
        'ca_max_amount' => LoanSetting::get('ca_max_amount'),
        'ca_auto_deduct' => LoanSetting::get('ca_auto_deduct'),
        'ca_sig_committee' => LoanSetting::get('ca_sig_committee'),
        'ca_sig_prepared' => LoanSetting::get('ca_sig_prepared'),
        'ca_sig_approved' => LoanSetting::get('ca_sig_approved'),
    ];

    foreach ($request->except(['_token', 'ca_sig_committee', 'ca_sig_prepared', 'ca_sig_approved']) as $key => $value) {
        LoanSetting::set($key, $value, is_numeric($value) ? (str_contains($key, 'amount') || str_contains($key, 'rate') ? 'decimal' : 'integer') : 'string');
    }

    // Signatory names are always text (never typed as numbers)
    foreach (['ca_sig_committee', 'ca_sig_prepared', 'ca_sig_approved'] as $key) {
        LoanSetting::set($key, trim((string) $request->input($key, '')), 'string');
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
