<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CropYear;
use App\Models\PlanterDeduction;
use App\Models\PlanterProfile;
use App\Models\User;
use App\Models\WeekNo;
use App\Support\TableFeed;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fuel & Others Deduction: a planter's fuel or other-account amount, set on a weekly schedule like a
 * cash advance but without approval. Each week's amount comes off that week's Check Voucher
 * (FUEL PAYMENT / OTHERS ACCOUNT) through PlanterProceeds.
 */
class PlanterDeductionController extends Controller
{
    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    private function canView(): bool
    {
        return $this->user()->hasAnyPermission(['view-planter-deductions', 'manage-planter-deductions']);
    }

    private function canManage(): bool
    {
        return $this->user()->hasPermission('manage-planter-deductions');
    }

    public function index(Request $request)
    {
        abort_unless($this->canView(), 403, 'Unauthorized');

        // The table loads its rows in batches as it scrolls (TableFeed / kpFeed)
        if ($request->expectsJson()) {
            return $this->feed($request);
        }

        $cropYears = CropYear::orderBy('crop_year')->pluck('crop_year');
        $planters = PlanterProfile::where('status', 'active')
            ->orderBy('planter_name')
            ->get(['planter_code', 'planter_name']);
        // Weeks per crop year (Crop & Week Management) for the weekly schedule
        $weeks = WeekNo::orderByRaw('CAST(week_no AS UNSIGNED) ASC')
            ->get(['crop_year', 'week_no', 'week_start_date', 'week_end_date'])
            ->map(fn ($w) => [
                'crop_year' => (string) $w->crop_year,
                'week_no' => (string) $w->week_no,
                'week_start' => $w->week_start_date ? Carbon::parse($w->week_start_date)->toDateString() : null,
                'week_end' => $w->week_end_date ? Carbon::parse($w->week_end_date)->toDateString() : null,
            ]);
        $canManage = $this->canManage();

        AuditLog::log('view', 'planter_deductions', 'Viewed Fuel & Others Deduction page');

        return view('planter-deductions.index', compact('cropYears', 'planters', 'weeks', 'canManage'));
    }

    /**
     * One batch of fuel or other deductions. The first batch also carries, for the same crop year /
     * week / search: the count per type (tab badges), and this type's total amount and planters.
     */
    private function feed(Request $request)
    {
        $filtered = function () use ($request) {
            $query = PlanterDeduction::query();
            if ($request->crop_year) {
                $query->where('crop_year', $request->crop_year);
            }
            if ($request->week_no) {
                $query->whereHas('weeks', fn ($q) => $q->where('week_no', (string) $request->week_no));
            }
            if ($search = trim((string) $request->search)) {
                $query->where(fn ($q) => $q->where('planter_name', 'like', "%{$search}%")
                    ->orWhere('planter_code', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%"));
            }
            return $query;
        };
        $type = in_array($request->type, array_keys(PlanterDeduction::TYPES), true) ? $request->type : 'fuel';

        $query = $filtered()->where('type', $type)->with('weeks', 'createdByUser:id,fname,lname');

        return TableFeed::respond($request, $query, [
            'reference_no' => 'reference_no',
            'planter_name' => 'planter_name',
            'amount' => 'amount',
            'created_at' => 'created_at',
        ], [['created_at', 'desc']], null, fn () => [
            'counts' => $filtered()->selectRaw('type, COUNT(*) AS n')->groupBy('type')->pluck('n', 'type'),
            'amount' => (float) $filtered()->where('type', $type)->sum('amount'),
            'planters' => $filtered()->where('type', $type)->distinct()->count('planter_code'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->canManage(), 403, 'Unauthorized');

        $data = $this->validated($request);
        $deduction = new PlanterDeduction([
            'type' => $data['type'],
            'reference_no' => PlanterDeduction::generateReferenceNo($data['type']),
            'created_by' => $this->user()->id,
        ]);

        DB::transaction(fn () => $this->save($deduction, $data));

        AuditLog::log('create', 'planter_deductions', 'Created ' . $deduction->type . ' deduction #' . $deduction->reference_no, $this->auditDetails($deduction, $data));

        return response()->json([
            'message' => PlanterDeduction::TYPES[$deduction->type] . ' deduction #' . $deduction->reference_no . ' saved. It comes off ' . $deduction->planter_name . '\'s Check Voucher in the scheduled weeks.',
            'deduction' => $deduction->load('weeks'),
        ], 201);
    }

    public function update(Request $request, PlanterDeduction $deduction)
    {
        abort_unless($this->canManage(), 403, 'Unauthorized');

        $data = $this->validated($request, $deduction);
        DB::transaction(fn () => $this->save($deduction, $data));

        AuditLog::log('update', 'planter_deductions', 'Updated ' . $deduction->type . ' deduction #' . $deduction->reference_no, $this->auditDetails($deduction, $data));

        return response()->json(['message' => 'Deduction #' . $deduction->reference_no . ' updated.', 'deduction' => $deduction->load('weeks')]);
    }

    public function destroy(PlanterDeduction $deduction)
    {
        abort_unless($this->canManage(), 403, 'Unauthorized');

        $reference = $deduction->reference_no;
        $details = ['planter' => $deduction->planter_name, 'amount' => $deduction->amount, 'type' => $deduction->type];
        $deduction->delete();

        AuditLog::log('delete', 'planter_deductions', 'Deleted deduction #' . $reference, $details);

        return response()->json(['message' => 'Deduction #' . $reference . ' deleted.']);
    }

    /**
     * The weekly schedule must use distinct weeks of the crop year and add up to the amount
     */
    private function validated(Request $request, ?PlanterDeduction $existing = null): array
    {
        $data = $request->validate([
            'type' => [$existing ? 'nullable' : 'required', Rule::in(array_keys(PlanterDeduction::TYPES))],
            'planter_code' => 'required|string|max:50',
            'planter_name' => 'required|string|max:255',
            'crop_year' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'schedule' => 'required|array|min:1',
            'schedule.*.week_no' => 'required|string|distinct',
            'schedule.*.due_date' => 'nullable|date',
            'schedule.*.amount' => 'required|numeric|min:0.01',
        ], [
            'schedule.required' => 'Add at least one deduction week.',
            'schedule.*.week_no.distinct' => 'Each week can only be listed once.',
            'schedule.*.amount.min' => 'Each week needs an amount.',
        ]);
        $data['type'] = $existing ? $existing->type : $data['type'];

        $schedule = collect($data['schedule']);
        $validWeeks = WeekNo::where('crop_year', $data['crop_year'])->pluck('week_no')->map(fn ($w) => (string) $w);
        $unknown = $schedule->pluck('week_no')->reject(fn ($w) => $validWeeks->contains((string) $w));
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                'schedule' => 'Week ' . $unknown->implode(', ') . ' is not set up for crop year ' . $data['crop_year'] . ' in Crop & Week Management.',
            ]);
        }

        $scheduledCents = (int) $schedule->sum(fn ($r) => (int) round((float) $r['amount'] * 100));
        $amountCents = (int) round((float) $data['amount'] * 100);
        if ($scheduledCents !== $amountCents) {
            throw ValidationException::withMessages([
                'schedule' => 'Weekly deductions total (₱' . number_format($scheduledCents / 100, 2) . ') must equal the amount (₱' . number_format($amountCents / 100, 2) . ').',
            ]);
        }

        return $data;
    }

    /** Save the deduction and replace its weekly schedule; call inside a transaction */
    private function save(PlanterDeduction $deduction, array $data): void
    {
        $deduction->fill([
            'planter_code' => trim($data['planter_code']),
            'planter_name' => trim($data['planter_name']),
            'crop_year' => $data['crop_year'],
            'amount' => round((float) $data['amount'], 2),
            'description' => $data['description'] ?? null,
        ]);
        $deduction->save();

        $weekEnds = WeekNo::where('crop_year', $data['crop_year'])->pluck('week_end_date', 'week_no')
            ->map(fn ($d) => $d ? substr((string) $d, 0, 10) : null);
        $deduction->weeks()->delete();
        foreach ($data['schedule'] as $row) {
            $week = (string) $row['week_no'];
            $deduction->weeks()->create([
                'crop_year' => $data['crop_year'],
                'week_no' => $week,
                'due_date' => ($row['due_date'] ?? null) ?: ($weekEnds[$week] ?? null),
                'amount' => round((float) $row['amount'], 2),
            ]);
        }
    }

    private function auditDetails(PlanterDeduction $deduction, array $data): array
    {
        return [
            'planter' => $deduction->planter_name . ' (' . $deduction->planter_code . ')',
            'crop_year' => $deduction->crop_year,
            'amount' => $deduction->amount,
            'weekly_schedule' => collect($data['schedule'])->map(fn ($r) => 'Wk ' . $r['week_no'] . ' = ' . $r['amount'])->implode(', '),
        ];
    }
}
