<?php

namespace App\Services;

use App\Models\CashAdvance;
use App\Models\CashAdvanceDeduction;
use App\Models\CashAdvancePayment;
use App\Models\ConsolidatedUpload;
use App\Models\CropYear;
use App\Models\Hauler;
use App\Models\HaulingLiquidation;
use App\Models\HaulingLiquidationItem;
use App\Models\Loan;
use App\Models\LoanAmortization;
use App\Models\LoanPayment;
use App\Models\Molass;
use App\Models\MolassesPrice;
use App\Models\PlanterDeduction;
use App\Models\PlanterDeductionWeek;
use App\Models\PlanterProfile;
use App\Models\Quedan;
use App\Models\QuedanPrice;
use App\Models\TlsHaulingUpload;
use App\Models\TruckingAllowance;
use App\Models\WeekNo;
use App\Support\NavigationPermissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard tabs: Production, Finance, Proceeds, Hauling, and the Balance Check that compares what one
 * module hands to the next (registry → consolidated → voucher, schedules → payments → balances,
 * TLS tickets → liquidations). Every figure is for one crop year, and one week when picked.
 *
 * $access says which modules the viewer may see; sections and checks outside it are left out.
 */
class DashboardAnalytics
{
    public const STATUS_RANK = ['ok' => 0, 'info' => 1, 'warning' => 2, 'danger' => 3];

    private string $cropYear;
    private ?string $weekNo;
    private array $weekValues;
    private array $access;
    private ?Collection $proceedsRows = null;

    public function __construct(string $cropYear, ?string $weekNo, array $access)
    {
        $this->cropYear = $cropYear;
        $this->weekNo = $weekNo !== null && $weekNo !== '' ? (string) (int) $weekNo : null;
        // Week numbers are text and some uploads are zero-padded ("03"), so both spellings match
        $this->weekValues = $this->weekNo !== null ? self::weekValues((int) $this->weekNo, (int) $this->weekNo) : [];
        $this->access = $access;
    }

    /**
     * Modules the user may see on the dashboard, from the same permissions as the sidebar
     */
    public static function accessFor($user): array
    {
        $access = [
            'loans' => NavigationPermissions::canSeeGroup($user, 'loans'),
            'cash_advances' => NavigationPermissions::canSeeGroup($user, 'cash-advances'),
            'deductions' => NavigationPermissions::canSeeGroup($user, 'planter-deductions'),
            'hauling' => NavigationPermissions::canSeeGroup($user, 'hauler-tls-liq'),
            'proceeds' => $user->hasPermission('print-vouchers') || NavigationPermissions::canSeeGroup($user, 'reports'),
        ];
        $access['finance'] = $access['loans'] || $access['cash_advances'] || $access['deductions'];

        return $access;
    }

    /**
     * Tabs shown to the user; Overview, Production and Balance Check are for everyone
     */
    public static function tabsFor(array $access): array
    {
        return array_values(array_filter([
            ['key' => 'overview', 'label' => 'Overview', 'icon' => 'fas fa-chart-pie'],
            ['key' => 'production', 'label' => 'Production', 'icon' => 'fas fa-seedling'],
            $access['finance'] ? ['key' => 'finance', 'label' => 'Loans & Advances', 'icon' => 'fas fa-hand-holding-usd'] : null,
            $access['proceeds'] ? ['key' => 'proceeds', 'label' => 'Proceeds', 'icon' => 'fas fa-money-check'] : null,
            $access['hauling'] ? ['key' => 'hauling', 'label' => 'Hauling', 'icon' => 'fas fa-truck'] : null,
            ['key' => 'balance', 'label' => 'Balance Check', 'icon' => 'fas fa-scale-balanced'],
        ]));
    }

    public static function weekValues(int $from, int $to): array
    {
        return collect(range(min($from, $to), max($from, $to)))
            ->flatMap(fn ($week) => [(string) $week, str_pad((string) $week, 2, '0', STR_PAD_LEFT)])
            // Strict: a loose unique() treats "3" and "03" as equal and drops the padded spelling
            ->uniqueStrict()
            ->values()
            ->all();
    }

    /**
     * Planter codes differ between uploads and profiles in spacing and case
     */
    public static function codeKey($code): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $code)));
    }

    private function can(string $module): bool
    {
        return !empty($this->access[$module]);
    }

    /**
     * Crop year, and the picked week when there is one
     */
    private function scoped($query, string $table = '')
    {
        $prefix = $table ? $table . '.' : '';
        $query->where($prefix . 'crop_year', $this->cropYear);
        if ($this->weekValues) {
            $query->whereIn($prefix . 'week_no', $this->weekValues);
        }
        return $query;
    }

    /**
     * One failing module (missing table on an older install, bad row) must not blank the whole tab
     */
    private function safely(string $section, callable $build, $fallback = null)
    {
        try {
            return $build();
        } catch (\Throwable $e) {
            Log::warning("Dashboard {$section} failed: " . $e->getMessage());
            return $fallback;
        }
    }

    private function scope(): array
    {
        return ['crop_year' => $this->cropYear, 'week_no' => $this->weekNo];
    }

    /**
     * Sums keyed by integer week, sorted, so "3" and "03" land together
     */
    private static function byWeek(Collection $rows, string $field): array
    {
        $out = [];
        foreach ($rows as $row) {
            $week = (int) $row->week_no;
            $out[$week] = ($out[$week] ?? 0) + (float) $row->{$field};
        }
        ksort($out);
        return $out;
    }

    // ==================== PRODUCTION ====================

    public function production(): array
    {
        $quedan = fn () => $this->scoped(Quedan::query());
        $molasses = fn () => $this->scoped(Molass::query());
        $consolidated = fn () => $this->scoped(ConsolidatedUpload::query());

        $sugarLkg = (float) $quedan()->sum('sugar_lkg');
        $laborLkg = (float) $quedan()->sum('labor_lkg');
        $molNet = (float) $molasses()->sum('mol_net');
        $cane = (float) $consolidated()->sum('ta_wt');

        $planterKeys = $quedan()->distinct()->pluck('planter_code')
            ->concat($consolidated()->distinct()->pluck('planter_code'))
            ->map(fn ($c) => self::codeKey($c))->unique();

        $status = function ($query, string $qtyField) {
            $rows = $query->selectRaw("status, COUNT(*) as records, SUM({$qtyField}) as qty")->groupBy('status')->get()->keyBy('status');
            return collect(['pending', 'bought', 'rejected'])->mapWithKeys(fn ($s) => [$s => [
                'records' => (int) ($rows[$s]->records ?? 0),
                'qty' => round((float) ($rows[$s]->qty ?? 0), 3),
            ]])->all();
        };

        // The trend always covers the whole crop year; the picked week is highlighted on the page
        $yearQuedan = Quedan::where('crop_year', $this->cropYear)->selectRaw('week_no, SUM(sugar_lkg) as lkg')->groupBy('week_no')->get();
        $yearMolasses = Molass::where('crop_year', $this->cropYear)->selectRaw('week_no, SUM(mol_net) as mol')->groupBy('week_no')->get();
        $yearCane = ConsolidatedUpload::where('crop_year', $this->cropYear)->selectRaw('week_no, SUM(ta_wt) as cane')->groupBy('week_no')->get();
        $lkgByWeek = self::byWeek($yearQuedan, 'lkg');
        $molByWeek = self::byWeek($yearMolasses, 'mol');
        $caneByWeek = self::byWeek($yearCane, 'cane');
        $weeks = array_unique(array_merge(array_keys($lkgByWeek), array_keys($molByWeek), array_keys($caneByWeek)));
        sort($weeks);

        $allowanceColumns = [
            'ta_amount' => 'Trucking Allowance', 'emi_amount' => 'EMI', 'pat_amount' => 'PAT',
            'cci_fa_amt' => 'CCI Field A', 'cci_fb_amt' => 'CCI Field B', 'cci_fc_amt' => 'CCI Field C',
            'rental_amt' => 'Rental', 'underload_amt' => 'Underload', 'mudpress_amt' => 'Mudpress',
            'adj_amt' => 'Adjustment', 'fuel_issuance_amt' => 'Fuel Issuance (less)',
        ];
        $sums = $consolidated()->selectRaw(collect(array_keys($allowanceColumns))->map(fn ($c) => "SUM({$c}) as {$c}")->implode(', '))->first();
        $allowances = collect($allowanceColumns)->map(fn ($label, $col) => [
            'label' => $label,
            'amount' => round((float) ($sums->{$col} ?? 0), 2),
        ])->values()->all();

        // Top planters by net LKG, with their molasses and cane
        $topQuedan = $quedan()
            ->selectRaw('planter_code, MAX(planter_name) as planter_name, SUM(sugar_lkg) as sugar, SUM(labor_lkg) as labor')
            ->groupBy('planter_code')
            ->orderByRaw('SUM(sugar_lkg) - SUM(labor_lkg) DESC')
            ->limit(10)
            ->get();
        $codes = $topQuedan->pluck('planter_code')->all();
        $molBy = $molasses()->whereIn('planter_code', $codes)->selectRaw('planter_code, SUM(mol_net) as mol')->groupBy('planter_code')->pluck('mol', 'planter_code');
        $caneBy = $consolidated()->whereIn('planter_code', $codes)->selectRaw('planter_code, SUM(ta_wt) as cane')->groupBy('planter_code')->pluck('cane', 'planter_code');

        return [
            'scope' => $this->scope(),
            'stats' => [
                'sugarLkg' => round($sugarLkg, 3),
                'netLkg' => round($sugarLkg - $laborLkg, 3),
                'laborLkg' => round($laborLkg, 3),
                'molassesTons' => round($molNet, 3),
                'caneTons' => round($cane, 3),
                'lkgPerTon' => $cane > 0 ? round($sugarLkg / $cane, 3) : 0,
                'quedanRecords' => $quedan()->count(),
                'molassesRecords' => $molasses()->count(),
                'planters' => $planterKeys->count(),
                'liens' => round((float) $quedan()->sum('total_liens'), 3),
            ],
            'quedanStatus' => $status($quedan(), 'sugar_lkg'),
            'molassesStatus' => $status($molasses(), 'mol_net'),
            'weekly' => [
                'labels' => array_map(fn ($w) => 'Week ' . $w, $weeks),
                'weeks' => $weeks,
                'lkg' => array_map(fn ($w) => round($lkgByWeek[$w] ?? 0, 3), $weeks),
                'molasses' => array_map(fn ($w) => round($molByWeek[$w] ?? 0, 3), $weeks),
                'cane' => array_map(fn ($w) => round($caneByWeek[$w] ?? 0, 3), $weeks),
            ],
            'allowances' => $allowances,
            'topPlanters' => $topQuedan->map(fn ($p) => [
                'planter_code' => $p->planter_code,
                'planter_name' => $p->planter_name,
                'net_lkg' => round((float) $p->sugar - (float) $p->labor, 3),
                'molasses' => round((float) ($molBy[$p->planter_code] ?? 0), 3),
                'cane' => round((float) ($caneBy[$p->planter_code] ?? 0), 3),
                'lkg_per_ton' => (float) ($caneBy[$p->planter_code] ?? 0) > 0
                    ? round((float) $p->sugar / (float) $caneBy[$p->planter_code], 3) : null,
            ])->values()->all(),
        ];
    }

    // ==================== FINANCE ====================

    public function finance(): array
    {
        $today = now()->toDateString();
        $out = ['scope' => $this->scope(), 'loans' => null, 'cashAdvances' => null, 'deductions' => null, 'upcoming' => []];
        $upcoming = collect();

        if ($this->can('loans')) {
            $out['loans'] = $this->safely('finance.loans', function () use ($today, &$upcoming) {
                $byStatus = Loan::selectRaw('status, COUNT(*) as n, SUM(principal_amount) as principal, SUM(balance) as balance')
                    ->groupBy('status')->get()->keyBy('status');
                $open = Loan::whereIn('status', ['approved', 'active']);

                $overdue = LoanAmortization::whereHas('loan', fn ($q) => $q->where('status', 'active'))
                    ->where('due_date', '<', $today)
                    ->whereColumn('amount_paid', '<', 'amount_due')
                    ->selectRaw('COUNT(*) as n, SUM(amount_due - amount_paid) as amount')->first();

                $activeDue = LoanAmortization::whereHas('loan', fn ($q) => $q->where('status', 'active'));
                $due = (float) (clone $activeDue)->where('due_date', '<=', $today)->sum('amount_due');
                $paid = (float) (clone $activeDue)->where('due_date', '<=', $today)->sum('amount_paid');

                LoanAmortization::with('loan:id,loan_number,planter_name')
                    ->whereHas('loan', fn ($q) => $q->where('status', 'active'))
                    ->whereBetween('due_date', [$today, now()->addDays(30)->toDateString()])
                    ->whereColumn('amount_paid', '<', 'amount_due')
                    ->orderBy('due_date')->limit(15)->get()
                    ->each(function ($a) use (&$upcoming) {
                        $upcoming->push([
                            'date' => $a->due_date->toDateString(),
                            'module' => 'Loan',
                            'reference' => $a->loan->loan_number,
                            'planter_name' => $a->loan->planter_name,
                            'amount' => round((float) $a->amount_due - (float) $a->amount_paid, 2),
                        ]);
                    });

                return [
                    'statuses' => collect(['pending', 'approved', 'active', 'completed', 'rejected', 'cancelled'])->map(fn ($s) => [
                        'status' => $s,
                        'count' => (int) ($byStatus[$s]->n ?? 0),
                        'principal' => round((float) ($byStatus[$s]->principal ?? 0), 2),
                    ])->values()->all(),
                    'openCount' => (clone $open)->count(),
                    'principal' => round((float) (clone $open)->sum('principal_amount'), 2),
                    'balance' => round((float) (clone $open)->sum('balance'), 2),
                    'pendingCount' => (int) ($byStatus['pending']->n ?? 0),
                    'pendingAmount' => round((float) ($byStatus['pending']->principal ?? 0), 2),
                    'overdueCount' => (int) ($overdue->n ?? 0),
                    'overdueAmount' => round((float) ($overdue->amount ?? 0), 2),
                    // Of what has fallen due so far on active loans, how much is paid
                    'collectionRate' => $due > 0 ? round($paid / $due * 100, 1) : null,
                ];
            });
        }

        if ($this->can('cash_advances')) {
            $out['cashAdvances'] = $this->safely('finance.cash_advances', function () use ($today, &$upcoming) {
                $byStatus = CashAdvance::selectRaw('status, COUNT(*) as n, SUM(amount) as amount, SUM(balance) as balance')
                    ->groupBy('status')->get()->keyBy('status');
                $open = CashAdvance::whereIn('status', ['approved', 'active']);

                // Weekly schedule of this crop year against what was collected per week
                $scheduled = CashAdvanceDeduction::where('crop_year', $this->cropYear)
                    ->whereHas('cashAdvance', fn ($q) => $q->whereIn('status', ['approved', 'active', 'completed']))
                    ->selectRaw('week_no, SUM(amount) as amount')->groupBy('week_no')->get();
                $collected = CashAdvancePayment::whereHas('cashAdvance', fn ($q) => $q->where('crop_year', $this->cropYear))
                    ->selectRaw('week_no, SUM(amount) as amount')->groupBy('week_no')->get();
                $schedBy = self::byWeek($scheduled, 'amount');
                $collBy = self::byWeek($collected, 'amount');
                $weeks = array_unique(array_merge(array_keys($schedBy), array_keys($collBy)));
                sort($weeks);

                $overdue = $this->caOverdue($today);

                CashAdvanceDeduction::with('cashAdvance:id,ca_number,planter_name')
                    ->whereHas('cashAdvance', fn ($q) => $q->where('status', 'active'))
                    ->whereBetween('due_date', [$today, now()->addDays(30)->toDateString()])
                    ->orderBy('due_date')->limit(15)->get()
                    ->each(function ($d) use (&$upcoming) {
                        $upcoming->push([
                            'date' => $d->due_date?->toDateString(),
                            'module' => 'Cash Advance',
                            'reference' => $d->cashAdvance->ca_number . ' · Wk ' . $d->week_no,
                            'planter_name' => $d->cashAdvance->planter_name,
                            'amount' => round((float) $d->amount, 2),
                        ]);
                    });

                $schedTotal = array_sum($schedBy);
                $collTotal = array_sum($collBy);

                return [
                    'statuses' => collect(['pending', 'approved', 'active', 'completed', 'rejected'])->map(fn ($s) => [
                        'status' => $s,
                        'count' => (int) ($byStatus[$s]->n ?? 0),
                        'amount' => round((float) ($byStatus[$s]->amount ?? 0), 2),
                    ])->values()->all(),
                    'openCount' => (clone $open)->count(),
                    'amount' => round((float) (clone $open)->sum('amount'), 2),
                    'balance' => round((float) (clone $open)->sum('balance'), 2),
                    'pendingCount' => (int) ($byStatus['pending']->n ?? 0),
                    'pendingAmount' => round((float) ($byStatus['pending']->amount ?? 0), 2),
                    'overdueCount' => $overdue->count(),
                    'overdueAmount' => round($overdue->sum('remaining'), 2),
                    'scheduledTotal' => round($schedTotal, 2),
                    'collectedTotal' => round($collTotal, 2),
                    'weekly' => [
                        'labels' => array_map(fn ($w) => 'Week ' . $w, $weeks),
                        'weeks' => $weeks,
                        'scheduled' => array_map(fn ($w) => round($schedBy[$w] ?? 0, 2), $weeks),
                        'collected' => array_map(fn ($w) => round($collBy[$w] ?? 0, 2), $weeks),
                    ],
                ];
            });
        }

        if ($this->can('deductions')) {
            $out['deductions'] = $this->safely('finance.deductions', function () {
                $byType = PlanterDeduction::where('crop_year', $this->cropYear)
                    ->selectRaw('type, COUNT(*) as n, SUM(amount) as amount')->groupBy('type')->get()->keyBy('type');
                $weeks = PlanterDeductionWeek::where('planter_deduction_weeks.crop_year', $this->cropYear)
                    ->join('planter_deductions', 'planter_deductions.id', '=', 'planter_deduction_weeks.planter_deduction_id')
                    ->selectRaw('planter_deduction_weeks.week_no as week_no, planter_deductions.type as type, SUM(planter_deduction_weeks.amount) as amount')
                    ->groupBy('planter_deduction_weeks.week_no', 'planter_deductions.type')
                    ->get();
                $fuelBy = self::byWeek($weeks->where('type', 'fuel'), 'amount');
                $othersBy = self::byWeek($weeks->where('type', 'others'), 'amount');
                $allWeeks = array_unique(array_merge(array_keys($fuelBy), array_keys($othersBy)));
                sort($allWeeks);
                $pick = $this->weekNo !== null ? (int) $this->weekNo : null;

                return [
                    'fuel' => ['count' => (int) ($byType['fuel']->n ?? 0), 'amount' => round((float) ($byType['fuel']->amount ?? 0), 2)],
                    'others' => ['count' => (int) ($byType['others']->n ?? 0), 'amount' => round((float) ($byType['others']->amount ?? 0), 2)],
                    'weekFuel' => $pick !== null ? round($fuelBy[$pick] ?? 0, 2) : null,
                    'weekOthers' => $pick !== null ? round($othersBy[$pick] ?? 0, 2) : null,
                    'weekly' => [
                        'labels' => array_map(fn ($w) => 'Week ' . $w, $allWeeks),
                        'weeks' => $allWeeks,
                        'fuel' => array_map(fn ($w) => round($fuelBy[$w] ?? 0, 2), $allWeeks),
                        'others' => array_map(fn ($w) => round($othersBy[$w] ?? 0, 2), $allWeeks),
                    ],
                ];
            });
        }

        $out['upcoming'] = $upcoming->sortBy('date')->take(12)->values()->all();

        return $out;
    }

    /**
     * Cash advance weeks already past their due date that are not fully paid
     */
    private function caOverdue(string $today): Collection
    {
        $deductions = CashAdvanceDeduction::with('cashAdvance:id,ca_number,planter_code,planter_name,status')
            ->whereHas('cashAdvance', fn ($q) => $q->where('status', 'active'))
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->get();
        if ($deductions->isEmpty()) {
            return collect();
        }

        $paid = CashAdvancePayment::whereIn('cash_advance_id', $deductions->pluck('cash_advance_id')->unique())
            ->get(['cash_advance_id', 'week_no', 'amount'])
            ->groupBy(fn ($p) => $p->cash_advance_id . '|' . (int) $p->week_no)
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        return $deductions->map(function ($d) use ($paid) {
            $remaining = round((float) $d->amount - ($paid[$d->cash_advance_id . '|' . (int) $d->week_no] ?? 0), 2);
            return [
                'reference' => $d->cashAdvance->ca_number,
                'planter_name' => $d->cashAdvance->planter_name,
                'week_no' => (string) $d->week_no,
                'due_date' => $d->due_date?->toDateString(),
                'remaining' => $remaining,
                'cash_advance_id' => $d->cash_advance_id,
            ];
        })->filter(fn ($r) => $r['remaining'] > 0.004)->values();
    }

    // ==================== COLLECTIONS TRACKER ====================

    /**
     * What planters owe the association right now, per account kind: released, collected, still to collect,
     * overdue and due in the next 30 days. Open accounts of every crop year (a balance is owed whatever
     * season it came from), so this does not follow the crop year / week filter.
     *
     * Loans and cash advances are collected by recorded payments. Fuel and Others have no payments: a
     * scheduled week comes off the Check Voucher when the planter has proceeds that week.
     */
    public function collections(): array
    {
        $today = now()->toDateString();
        $in30 = now()->addDays(30)->toDateString();
        $types = [];
        $planters = [];
        $owe = function ($code, $name, string $type, float $amount) use (&$planters) {
            if ($amount <= 0.004) {
                return;
            }
            $key = self::codeKey($code);
            $planters[$key] ??= ['planter_code' => $code, 'planter_name' => $name, 'loan' => 0, 'ca' => 0, 'fuel' => 0, 'others' => 0];
            $planters[$key][$type] += $amount;
        };

        if ($this->can('loans')) {
            $types[] = $this->safely('collections.loans', function () use ($today, $in30, $owe) {
                $open = Loan::whereIn('status', ['approved', 'active'])->get(['id', 'planter_code', 'planter_name', 'principal_amount', 'total_amount', 'balance']);
                $ids = $open->pluck('id');
                $collected = (float) LoanPayment::whereIn('loan_id', $ids)->sum('amount');
                $unpaid = fn () => LoanAmortization::whereIn('loan_id', $ids)->whereColumn('amount_paid', '<', 'amount_due')
                    ->selectRaw('COUNT(*) as n, SUM(amount_due - amount_paid) as amount');
                $overdue = $unpaid()->where('due_date', '<', $today)->first();
                $due = $unpaid()->whereBetween('due_date', [$today, $in30])->first();
                foreach ($open as $loan) {
                    $owe($loan->planter_code, $loan->planter_name, 'loan', (float) $loan->balance);
                }

                return $this->collectionRow('loan', 'Loans', 'fas fa-hand-holding-usd', [
                    'accounts' => $open->count(),
                    'released' => (float) $open->sum('principal_amount'),
                    'receivable' => (float) $open->sum('total_amount'),
                    'collected' => $collected,
                    'remaining' => (float) $open->sum('balance'),
                    'overdue' => (float) ($overdue->amount ?? 0),
                    'overdueCount' => (int) ($overdue->n ?? 0),
                    'due30' => (float) ($due->amount ?? 0),
                    'link' => 'loans.index',
                ]);
            });
        }

        if ($this->can('cash_advances')) {
            $types[] = $this->safely('collections.cash_advances', function () use ($today, $in30, $owe) {
                $open = CashAdvance::whereIn('status', ['approved', 'active'])->get(['id', 'planter_code', 'planter_name', 'amount', 'total_amount', 'balance']);
                $ids = $open->pluck('id');
                $paid = CashAdvancePayment::whereIn('cash_advance_id', $ids)->get(['cash_advance_id', 'week_no', 'amount']);
                $paidByWeek = $paid->groupBy(fn ($p) => $p->cash_advance_id . '|' . (int) $p->week_no)->map(fn ($rows) => (float) $rows->sum('amount'));
                $due = CashAdvanceDeduction::whereIn('cash_advance_id', $ids)->whereBetween('due_date', [$today, $in30])->get()
                    ->sum(fn ($d) => max(0, (float) $d->amount - ($paidByWeek[$d->cash_advance_id . '|' . (int) $d->week_no] ?? 0)));
                $overdue = $this->caOverdue($today);
                foreach ($open as $ca) {
                    $owe($ca->planter_code, $ca->planter_name, 'ca', (float) $ca->balance);
                }

                return $this->collectionRow('ca', 'Cash Advances', 'fas fa-money-bill-wave', [
                    'accounts' => $open->count(),
                    'released' => (float) $open->sum('amount'),
                    'receivable' => (float) $open->sum('total_amount'),
                    'collected' => (float) $paid->sum('amount'),
                    'remaining' => (float) $open->sum('balance'),
                    'overdue' => (float) $overdue->sum('remaining'),
                    'overdueCount' => $overdue->count(),
                    'due30' => (float) $due,
                    'link' => 'cash-advances.index',
                ]);
            });
        }

        if ($this->can('deductions')) {
            $rows = $this->safely('collections.deductions', function () use ($today, $in30, $owe) {
                $deductions = PlanterDeduction::with('weeks')->get();
                if ($deductions->isEmpty()) {
                    return ['fuel' => [], 'others' => []];
                }

                // Planter-weeks with proceeds, by code and by name (the voucher falls back to the name).
                // Pending quedans and molasses are not on the voucher yet.
                $producing = [];
                $cropYears = $deductions->pluck('crop_year')->unique()->all();
                foreach ([Quedan::notPending(), Molass::notPending(), ConsolidatedUpload::query()] as $query) {
                    foreach ($query->whereIn('crop_year', $cropYears)->select('crop_year', 'week_no', 'planter_code', 'planter_name')->distinct()->cursor() as $r) {
                        $week = $r->crop_year . '|' . (int) $r->week_no;
                        $producing[$week . '|' . self::codeKey($r->planter_code)] = true;
                        $producing[$week . '|N:' . self::codeKey($r->planter_name)] = true;
                    }
                }

                $out = [];
                foreach (['fuel', 'others'] as $type) {
                    $stat = ['accounts' => 0, 'released' => 0, 'collected' => 0, 'overdue' => 0, 'overdueCount' => 0, 'due30' => 0];
                    foreach ($deductions->where('type', $type) as $d) {
                        $stat['accounts']++;
                        $stat['released'] += (float) $d->amount;
                        $left = 0;
                        foreach ($d->weeks as $w) {
                            $week = $w->crop_year . '|' . (int) $w->week_no;
                            $applied = isset($producing[$week . '|' . self::codeKey($d->planter_code)])
                                || isset($producing[$week . '|N:' . self::codeKey($d->planter_name)]);
                            if ($applied) {
                                $stat['collected'] += (float) $w->amount;
                                continue;
                            }
                            $left += (float) $w->amount;
                            $dueDate = $w->due_date?->toDateString();
                            if ($dueDate && $dueDate < $today) {
                                $stat['overdue'] += (float) $w->amount;
                                $stat['overdueCount']++;
                            } elseif ($dueDate && $dueDate <= $in30) {
                                $stat['due30'] += (float) $w->amount;
                            }
                        }
                        $owe($d->planter_code, $d->planter_name, $type, $left);
                    }
                    $stat['receivable'] = $stat['released'];
                    $stat['remaining'] = max(0, $stat['released'] - $stat['collected']);
                    $out[$type] = $stat;
                }

                return $out;
            }, ['fuel' => [], 'others' => []]);

            foreach (['fuel' => ['Fuel', 'fas fa-gas-pump'], 'others' => ['Others', 'fas fa-receipt']] as $type => [$label, $icon]) {
                $types[] = $this->collectionRow($type, $label, $icon, ($rows[$type] ?: [
                    'accounts' => 0, 'released' => 0, 'receivable' => 0, 'collected' => 0, 'remaining' => 0,
                    'overdue' => 0, 'overdueCount' => 0, 'due30' => 0,
                ]) + ['link' => 'planter-deductions.index', 'basis' => 'voucher']);
            }
        }

        $types = array_values(array_filter($types));
        $sum = fn ($field) => round(array_sum(array_column($types, $field)), 2);
        $total = [
            'released' => $sum('released'),
            'receivable' => $sum('receivable'),
            'collected' => $sum('collected'),
            'remaining' => $sum('remaining'),
            'overdue' => $sum('overdue'),
            'overdueCount' => (int) array_sum(array_column($types, 'overdueCount')),
            'due30' => $sum('due30'),
            'accounts' => (int) array_sum(array_column($types, 'accounts')),
        ];
        $total['interest'] = round($total['receivable'] - $total['released'], 2);
        $total['rate'] = $total['collected'] + $total['remaining'] > 0
            ? round($total['collected'] / ($total['collected'] + $total['remaining']) * 100, 1) : null;

        $owing = collect($planters)
            ->map(fn ($p) => $p + ['total' => round($p['loan'] + $p['ca'] + $p['fuel'] + $p['others'], 2)])
            ->sortByDesc('total')->values();

        return [
            'asOf' => $today,
            'types' => $types,
            'total' => $total,
            'planterCount' => $owing->count(),
            'planters' => $owing->take(15)->map(fn ($p) => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $p))->all(),
        ];
    }

    private function collectionRow(string $key, string $label, string $icon, array $s): array
    {
        $collected = round($s['collected'], 2);
        $remaining = round($s['remaining'], 2);
        $receivable = round($s['receivable'], 2);

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'basis' => $s['basis'] ?? 'payments',
            'accounts' => $s['accounts'],
            'released' => round($s['released'], 2),
            'receivable' => $receivable,
            'interest' => round($receivable - $s['released'], 2),
            'collected' => $collected,
            'remaining' => $remaining,
            'overdue' => round($s['overdue'], 2),
            'overdueCount' => $s['overdueCount'],
            'due30' => round($s['due30'], 2),
            'rate' => $collected + $remaining > 0 ? round($collected / ($collected + $remaining) * 100, 1) : null,
            // Collected + still to collect should make up what was receivable; when not, Balance Check explains why
            'mismatch' => abs($receivable - $collected - $remaining) > 1,
            'link' => \Illuminate\Support\Facades\Route::has($s['link']) ? route($s['link']) : null,
        ];
    }

    // ==================== PROCEEDS ====================

    /**
     * Week range for proceeds: the picked week, else every week of the crop year with deliveries
     */
    private function proceedsRange(): ?array
    {
        if ($this->weekNo !== null) {
            return [(int) $this->weekNo, (int) $this->weekNo];
        }
        $weeks = Quedan::where('crop_year', $this->cropYear)->distinct()->pluck('week_no')
            ->concat(Molass::where('crop_year', $this->cropYear)->distinct()->pluck('week_no'))
            ->concat(ConsolidatedUpload::where('crop_year', $this->cropYear)->distinct()->pluck('week_no'))
            ->map(fn ($w) => (int) $w)->filter(fn ($w) => $w > 0);

        return $weeks->isEmpty() ? null : [$weeks->min(), $weeks->max()];
    }

    /**
     * Each planter's amounts exactly as the Check Voucher prints them
     */
    private function proceedsRows(): Collection
    {
        if ($this->proceedsRows !== null) {
            return $this->proceedsRows;
        }
        $range = $this->proceedsRange();
        if (!$range) {
            return $this->proceedsRows = collect();
        }

        return $this->proceedsRows = PlanterProceeds::summary($this->cropYear, $range[0], $range[1])
            ->map(function ($record) {
                $p = PlanterProceeds::compute($record);
                return [
                    'planter_code' => $record['planter_code'],
                    'planter_name' => $record['planter_name'],
                    'quedan_gross' => $p['quedanGrossProceeds'],
                    'molasses_gross' => $p['molasses_gross'],
                    'allowances' => $p['consolidated_display'],
                    'gross' => $p['totalGrossProceeds'],
                    'liens' => $p['quedanTotalLiens'] + $p['molasses_liens'],
                    'service' => $p['quedanTotalServiceCharge'] + $p['molasses_service'],
                    'insurance' => $p['quedanTotalInsurance'] + $p['molasses_insurance'],
                    'tax' => $p['quedanTotalTax'] + $p['molasses_tax'],
                    'fuel_issuance' => $p['consolidated_fuel_amount'],
                    'deductions' => $p['totalDeductions'],
                    'net' => $p['totalNetProceeds'],
                    'loan' => $p['loanPayment'],
                    'ca' => $p['caPayment'],
                    'fuel' => $p['fuelPayment'],
                    'others' => $p['othersPayment'],
                    'applied' => $p['accountsApplied'],
                    'paid' => $p['netProceedsPaid'],
                ];
            })->values();
    }

    public function proceeds(): array
    {
        $range = $this->proceedsRange();
        $rows = $this->proceedsRows();
        $sum = fn ($field) => round((float) $rows->sum($field), 2);
        $pick = fn ($r) => collect($r)->only(['planter_code', 'planter_name', 'gross', 'deductions', 'applied', 'paid'])
            ->map(fn ($v) => is_float($v) ? round($v, 2) : $v)->all();

        return [
            'scope' => $this->scope(),
            'range' => $range ? ['from' => $range[0], 'to' => $range[1]] : null,
            'planters' => $rows->count(),
            'totals' => [
                'quedan_gross' => $sum('quedan_gross'),
                'molasses_gross' => $sum('molasses_gross'),
                'allowances' => $sum('allowances'),
                'gross' => $sum('gross'),
                'deductions' => $sum('deductions'),
                'net' => $sum('net'),
                'applied' => $sum('applied'),
                'paid' => $sum('paid'),
            ],
            'deductionBreakdown' => [
                ['label' => 'Liens', 'amount' => $sum('liens')],
                ['label' => 'Service Charge', 'amount' => $sum('service')],
                ['label' => 'Insurance', 'amount' => $sum('insurance')],
                ['label' => 'Tax (1%)', 'amount' => $sum('tax')],
                ['label' => 'Fuel Issuance', 'amount' => $sum('fuel_issuance')],
            ],
            'accountsBreakdown' => [
                ['label' => 'Loan Payment', 'amount' => $sum('loan')],
                ['label' => 'Cash Advance', 'amount' => $sum('ca')],
                ['label' => 'Fuel Payment', 'amount' => $sum('fuel')],
                ['label' => 'Others Account', 'amount' => $sum('others')],
            ],
            'topPaid' => $rows->sortByDesc('paid')->take(10)->map($pick)->values()->all(),
            'negative' => $rows->filter(fn ($r) => round($r['paid'], 2) < 0)->sortBy('paid')->take(10)->map($pick)->values()->all(),
            'negativeCount' => $rows->filter(fn ($r) => round($r['paid'], 2) < 0)->count(),
        ];
    }

    // ==================== HAULING ====================

    public function hauling(): array
    {
        $tls = fn () => $this->scoped(TlsHaulingUpload::query());
        $liq = fn () => $this->scoped(HaulingLiquidation::query());

        $tickets = $tls()->count();
        $tlsTons = (float) $tls()->sum('net_cane_wt');
        $unliquidated = $tls()->whereNull('hauling_liquidation_id');
        $liqTotals = $liq()->selectRaw('COUNT(*) as n, SUM(tonnage) as tons, SUM(gross_amount) as gross, SUM(total_add) as added, SUM(total_less) as less, SUM(net_amount) as net')->first();

        $byHauler = $liq()->selectRaw('hauler_id, COUNT(*) as n, SUM(tonnage) as tons, SUM(net_amount) as net')
            ->groupBy('hauler_id')->get();
        $names = Hauler::whereIn('id', $byHauler->pluck('hauler_id'))->pluck('name', 'id');

        $yearTls = TlsHaulingUpload::where('crop_year', $this->cropYear)->selectRaw('week_no, SUM(net_cane_wt) as tons')->groupBy('week_no')->get();
        $yearLiq = HaulingLiquidation::where('crop_year', $this->cropYear)->selectRaw('week_no, SUM(tonnage) as tons, SUM(net_amount) as net')->groupBy('week_no')->get();
        $tlsBy = self::byWeek($yearTls, 'tons');
        $liqBy = self::byWeek($yearLiq, 'tons');
        $weeks = array_unique(array_merge(array_keys($tlsBy), array_keys($liqBy)));
        sort($weeks);

        $lessItems = HaulingLiquidationItem::where('type', 'less')
            ->whereIn('hauling_liquidation_id', $liq()->select('id'))
            ->selectRaw('description, SUM(amount) as amount, COUNT(*) as n')
            ->groupBy('description')->orderByDesc('amount')->get()
            // Every slip carries the standard "less" lines; only those with an amount say something
            ->filter(fn ($i) => abs((float) $i->amount) > 0.004)->take(8);

        $topTrucks = $liq()->whereNotNull('truck_code')
            ->selectRaw('truck_code, MAX(payee_name) as payee_name, COUNT(*) as n, SUM(tonnage) as tons, SUM(net_amount) as net')
            ->groupBy('truck_code')->orderByDesc('tons')->limit(8)->get();

        return [
            'scope' => $this->scope(),
            'stats' => [
                'tickets' => $tickets,
                'tlsTons' => round($tlsTons, 3),
                'unliquidatedTickets' => (clone $unliquidated)->count(),
                'unliquidatedTons' => round((float) (clone $unliquidated)->sum('net_cane_wt'), 3),
                'entries' => (int) ($liqTotals->n ?? 0),
                'liqTons' => round((float) ($liqTotals->tons ?? 0), 3),
                'gross' => round((float) ($liqTotals->gross ?? 0), 2),
                'added' => round((float) ($liqTotals->added ?? 0), 2),
                'less' => round((float) ($liqTotals->less ?? 0), 2),
                'net' => round((float) ($liqTotals->net ?? 0), 2),
                'noPayee' => $liq()->where(fn ($q) => $q->whereNull('payee_name')->orWhere('payee_name', ''))->count(),
                'noRate' => $liq()->where('rate_per_ton', '<=', 0)->count(),
                'released' => $liq()->whereNotNull('date_released')->count(),
                'activeHaulers' => Hauler::where('is_active', true)->count(),
            ],
            'byHauler' => $byHauler->map(fn ($h) => [
                'hauler' => $names[$h->hauler_id] ?? ('Hauler #' . $h->hauler_id),
                'entries' => (int) $h->n,
                'tons' => round((float) $h->tons, 3),
                'net' => round((float) $h->net, 2),
            ])->sortByDesc('tons')->values()->all(),
            'weekly' => [
                'labels' => array_map(fn ($w) => 'Week ' . $w, $weeks),
                'weeks' => $weeks,
                'tls' => array_map(fn ($w) => round($tlsBy[$w] ?? 0, 3), $weeks),
                'liquidated' => array_map(fn ($w) => round($liqBy[$w] ?? 0, 3), $weeks),
            ],
            'lessItems' => $lessItems->map(fn ($i) => [
                'description' => $i->description,
                'entries' => (int) $i->n,
                'amount' => round((float) $i->amount, 2),
            ])->values()->all(),
            'topTrucks' => $topTrucks->map(fn ($t) => [
                'truck_code' => $t->truck_code,
                'payee_name' => $t->payee_name,
                'entries' => (int) $t->n,
                'tons' => round((float) $t->tons, 3),
                'net' => round((float) $t->net, 2),
            ])->values()->all(),
        ];
    }

    // ==================== MODULE SNAPSHOT ====================

    /**
     * One figure per module for the Overview strip: cheap counts only
     */
    public function modules(): array
    {
        $cards = [];
        $cards[] = $this->safely('modules.registry', fn () => [
            'key' => 'registry', 'label' => 'Quedan Registry', 'icon' => 'fas fa-receipt',
            'value' => $this->scoped(Quedan::query())->count(), 'unit' => 'quedans',
            'foot' => $this->scoped(Quedan::query())->where('status', 'pending')->count() . ' not yet bought',
        ]);
        $cards[] = $this->safely('modules.molasses', fn () => [
            'key' => 'molasses', 'label' => 'Molasses', 'icon' => 'fas fa-flask',
            'value' => round((float) $this->scoped(Molass::query())->sum('mol_net'), 2), 'unit' => 'tons',
            'foot' => $this->scoped(Molass::query())->count() . ' records',
        ]);
        $cards[] = $this->safely('modules.consolidated', fn () => [
            'key' => 'consolidated', 'label' => 'Consolidated Summary', 'icon' => 'fas fa-layer-group',
            'value' => $this->scoped(ConsolidatedUpload::query())->count(), 'unit' => 'planter rows',
            'foot' => '₱' . number_format((float) $this->scoped(ConsolidatedUpload::query())->sum('total_summary'), 0) . ' allowances',
        ]);
        if ($this->can('hauling')) {
            $cards[] = $this->safely('modules.hauling', fn () => [
                'key' => 'hauling', 'label' => 'Hauler TLS LIQ', 'icon' => 'fas fa-truck',
                'value' => $this->scoped(HaulingLiquidation::query())->count(), 'unit' => 'liquidations',
                'foot' => $this->scoped(TlsHaulingUpload::query())->count() . ' TLS tickets',
            ]);
        }
        if ($this->can('cash_advances')) {
            $cards[] = $this->safely('modules.cash_advances', fn () => [
                'key' => 'cash_advances', 'label' => 'Cash Advances', 'icon' => 'fas fa-money-bill-wave',
                'value' => CashAdvance::whereIn('status', ['approved', 'active'])->count(), 'unit' => 'open',
                'foot' => '₱' . number_format((float) CashAdvance::whereIn('status', ['approved', 'active'])->sum('balance'), 0) . ' balance',
            ]);
        }
        if ($this->can('loans')) {
            $cards[] = $this->safely('modules.loans', fn () => [
                'key' => 'loans', 'label' => 'Loans', 'icon' => 'fas fa-hand-holding-usd',
                'value' => Loan::whereIn('status', ['approved', 'active'])->count(), 'unit' => 'open',
                'foot' => '₱' . number_format((float) Loan::whereIn('status', ['approved', 'active'])->sum('balance'), 0) . ' balance',
            ]);
        }
        if ($this->can('deductions')) {
            $cards[] = $this->safely('modules.deductions', fn () => [
                'key' => 'deductions', 'label' => 'Fuel & Others', 'icon' => 'fas fa-gas-pump',
                'value' => PlanterDeduction::where('crop_year', $this->cropYear)->count(), 'unit' => 'accounts',
                'foot' => '₱' . number_format((float) PlanterDeduction::where('crop_year', $this->cropYear)->sum('amount'), 0) . ' scheduled',
            ]);
        }
        $cards[] = $this->safely('modules.profiles', fn () => [
            'key' => 'profiles', 'label' => 'Planter Profiles', 'icon' => 'fas fa-id-card',
            'value' => PlanterProfile::where('status', 'active')->count(), 'unit' => 'active',
            'foot' => PlanterProfile::where('status', '!=', 'active')->count() . ' inactive / suspended',
        ]);

        return ['scope' => $this->scope(), 'cards' => array_values(array_filter($cards))];
    }

    // ==================== BALANCE CHECK ====================

    /**
     * Every check compares what one module hands to another. A check that fails to run shows as
     * "could not run" rather than hiding the rest.
     */
    public function balance(): array
    {
        $checks = [
            ['access' => null, 'key' => 'week_setup', 'run' => fn () => $this->checkWeekSetup()],
            ['access' => null, 'key' => 'prices_missing', 'run' => fn () => $this->checkPricesMissing()],
            ['access' => null, 'key' => 'quedan_vs_consolidated', 'run' => fn () => $this->checkQuedanVsConsolidated()],
            ['access' => null, 'key' => 'molasses_vs_quedan', 'run' => fn () => $this->checkMolassesVsQuedan()],
            ['access' => null, 'key' => 'trucking_vs_consolidated', 'run' => fn () => $this->checkTruckingVsConsolidated()],
            ['access' => null, 'key' => 'registry_unbought', 'run' => fn () => $this->checkRegistryUnbought()],
            ['access' => null, 'key' => 'unregistered_planters', 'run' => fn () => $this->checkUnregisteredPlanters()],
            ['access' => null, 'key' => 'inactive_with_deliveries', 'run' => fn () => $this->checkInactiveWithDeliveries()],
            ['access' => 'proceeds', 'key' => 'over_deducted', 'run' => fn () => $this->checkOverDeducted()],
            ['access' => 'any_finance', 'key' => 'deductions_without_proceeds', 'run' => fn () => $this->checkDeductionsWithoutProceeds()],
            ['access' => 'cash_advances', 'key' => 'ca_overdue', 'run' => fn () => $this->checkCaOverdue()],
            ['access' => 'cash_advances', 'key' => 'ca_schedule', 'run' => fn () => $this->checkCaSchedule()],
            ['access' => 'loans', 'key' => 'loan_balance', 'run' => fn () => $this->checkLoanBalance()],
            ['access' => 'hauling', 'key' => 'tls_unliquidated', 'run' => fn () => $this->checkTlsUnliquidated()],
            ['access' => 'hauling', 'key' => 'liquidation_incomplete', 'run' => fn () => $this->checkLiquidationIncomplete()],
        ];

        $results = [];
        foreach ($checks as $check) {
            if ($check['access'] === 'any_finance') {
                if (!$this->can('cash_advances') && !$this->can('deductions')) {
                    continue;
                }
            } elseif ($check['access'] && !$this->can($check['access'])) {
                continue;
            }
            try {
                $results[] = $check['run']();
            } catch (\Throwable $e) {
                Log::warning('Dashboard balance check ' . $check['key'] . ' failed: ' . $e->getMessage());
                $results[] = [
                    'key' => $check['key'], 'title' => str_replace('_', ' ', ucfirst($check['key'])), 'modules' => [],
                    'status' => 'info', 'summary' => 'Could not run this check',
                    'detail' => 'The data this check needs is not available on this installation (pending migration or missing table).',
                    'count' => 0, 'amount' => null, 'link' => null, 'columns' => [], 'rows' => [],
                ];
            }
        }

        $counts = collect(self::STATUS_RANK)->map(fn ($r, $s) => collect($results)->where('status', $s)->count());
        // Health: each check scores 1 when balanced, half when it only needs a look, none when it is off
        $weights = ['ok' => 1, 'info' => 1, 'warning' => 0.5, 'danger' => 0];
        $score = count($results) ? round(collect($results)->sum(fn ($c) => $weights[$c['status']]) / count($results) * 100) : 100;

        usort($results, fn ($a, $b) => self::STATUS_RANK[$b['status']] <=> self::STATUS_RANK[$a['status']]);

        return [
            'scope' => $this->scope(),
            'score' => $score,
            'counts' => $counts->all(),
            'issues' => $counts['warning'] + $counts['danger'],
            'flow' => $this->flow($results),
            'checks' => $results,
        ];
    }

    /**
     * Modules as the data travels, each colored by the worst check that touches it
     */
    private function flow(array $results): array
    {
        $nodes = [
            ['key' => 'Uploads', 'icon' => 'fas fa-cloud-upload-alt'],
            ['key' => 'Crop & Week', 'icon' => 'fas fa-calendar-alt'],
            ['key' => 'Prices', 'icon' => 'fas fa-tags'],
            ['key' => 'Quedan Registry', 'icon' => 'fas fa-receipt'],
            ['key' => 'Consolidated', 'icon' => 'fas fa-layer-group'],
            ['key' => 'Planter Profiles', 'icon' => 'fas fa-id-card'],
        ];
        if ($this->can('cash_advances') || $this->can('loans') || $this->can('deductions')) {
            $nodes[] = ['key' => 'Receivables', 'icon' => 'fas fa-hand-holding-usd'];
        }
        if ($this->can('proceeds')) {
            $nodes[] = ['key' => 'Check Voucher', 'icon' => 'fas fa-money-check'];
        }
        if ($this->can('hauling')) {
            $nodes[] = ['key' => 'Hauler TLS LIQ', 'icon' => 'fas fa-truck'];
        }

        return array_map(function ($node) use ($results) {
            $touching = collect($results)->filter(fn ($c) => in_array($node['key'], $c['modules'], true));
            $worst = $touching->max(fn ($c) => self::STATUS_RANK[$c['status']]) ?? 0;
            return $node + [
                'status' => array_search($worst, self::STATUS_RANK, true),
                'issues' => $touching->whereIn('status', ['warning', 'danger'])->count(),
            ];
        }, $nodes);
    }

    private function result(string $key, string $title, array $modules, string $status, string $summary, string $detail, array $extra = []): array
    {
        return array_merge([
            'key' => $key, 'title' => $title, 'modules' => $modules, 'status' => $status,
            'summary' => $summary, 'detail' => $detail,
            'count' => 0, 'amount' => null, 'link' => null, 'link_label' => null, 'columns' => [], 'rows' => [],
        ], $extra);
    }

    private function link(?string $route, string $label, array $params = []): array
    {
        if (!$route || !\Illuminate\Support\Facades\Route::has($route)) {
            return ['link' => null, 'link_label' => null];
        }
        return ['link' => route($route, $params), 'link_label' => $label];
    }

    /**
     * Planter + week pairs of a module in scope: "week|CODE" => name
     */
    private function pairs($query, string $codeField = 'planter_code', string $nameField = 'planter_name'): array
    {
        $out = [];
        foreach ($this->scoped($query)->select('week_no', $codeField, $nameField)->distinct()->get() as $row) {
            $out[(int) $row->week_no . '|' . self::codeKey($row->{$codeField})] = $row->{$nameField};
        }
        return $out;
    }

    private static function pairRows(array $keys, array $names, string $missing, int $limit = 10): array
    {
        return collect($keys)->take($limit)->map(function ($key) use ($names, $missing) {
            [$week, $code] = explode('|', $key, 2);
            return ['Week' => $week, 'Planter' => trim(($names[$key] ?? '') . ' (' . $code . ')'), 'Missing in' => $missing];
        })->values()->all();
    }

    private function checkWeekSetup(): array
    {
        $uploadWeeks = collect([Quedan::class, Molass::class, ConsolidatedUpload::class, TlsHaulingUpload::class])
            ->flatMap(fn ($model) => $this->safely('week_setup.' . class_basename($model),
                fn () => $model::where('crop_year', $this->cropYear)->distinct()->pluck('week_no')->all(), []))
            ->map(fn ($w) => (int) $w)->filter(fn ($w) => $w > 0)->unique()->sort()->values();

        $configured = WeekNo::where('crop_year', $this->cropYear)->get(['week_no', 'week_end_date'])
            ->mapWithKeys(fn ($w) => [(int) $w->week_no => $w->week_end_date]);
        $missing = $uploadWeeks->filter(fn ($w) => !$configured->has($w))->values();
        $noEnd = $uploadWeeks->filter(fn ($w) => $configured->has($w) && empty($configured[$w]))->values();
        $yearMissing = !CropYear::where('crop_year', $this->cropYear)->exists();

        $rows = $missing->map(fn ($w) => ['Week' => $w, 'Problem' => 'Not in Crop & Week Management'])
            ->concat($noEnd->map(fn ($w) => ['Week' => $w, 'Problem' => 'No week end date']))
            ->values()->all();
        if ($yearMissing) {
            array_unshift($rows, ['Week' => '—', 'Problem' => 'Crop year ' . $this->cropYear . ' is not registered']);
        }
        $count = count($rows);

        return $this->result('week_setup', 'Uploaded weeks are set up', ['Uploads', 'Crop & Week'],
            $count ? 'danger' : 'ok',
            $count ? $count . ' week setup problem(s)' : 'All ' . $uploadWeeks->count() . ' uploaded week(s) are configured',
            'Weeks with uploads must exist in Crop & Week Management with a week end date. Vouchers print the date from it and Hauler TLS LIQ cannot generate entries without it.',
            ['count' => $count, 'columns' => ['Week', 'Problem'], 'rows' => $rows] + $this->link('crop-weeks.index', 'Open Crop & Week'));
    }

    private function checkPricesMissing(): array
    {
        $quedanWeeks = $this->scoped(Quedan::query())->distinct()->pluck('week_no')->map(fn ($w) => (int) $w)->unique();
        $molWeeks = $this->scoped(Molass::query())->distinct()->pluck('week_no')->map(fn ($w) => (int) $w)->unique();
        $qPriced = QuedanPrice::where('crop_year', $this->cropYear)->where('quedan_price', '>', 0)->pluck('week_no')->map(fn ($w) => (int) $w)->flip();
        $mPriced = MolassesPrice::where('crop_year', $this->cropYear)->where('mol_price', '>', 0)->pluck('week_no')->map(fn ($w) => (int) $w)->flip();

        $rows = $quedanWeeks->reject(fn ($w) => $qPriced->has($w))->sort()->map(fn ($w) => ['Week' => $w, 'Missing' => 'Quedan price'])
            ->concat($molWeeks->reject(fn ($w) => $mPriced->has($w))->sort()->map(fn ($w) => ['Week' => $w, 'Missing' => 'Molasses price']))
            ->values()->all();
        $count = count($rows);

        return $this->result('prices_missing', 'Every delivered week has a price', ['Prices', 'Quedan Registry', 'Check Voucher'],
            $count ? 'danger' : 'ok',
            $count ? $count . ' week(s) without a price' : 'Quedan and molasses prices are set for every week with deliveries',
            'A week with quedan or molasses records but no price computes ₱0.00 gross proceeds on the Check Voucher.',
            ['count' => $count, 'columns' => ['Week', 'Missing'], 'rows' => $rows] + $this->link('prices.index', 'Open Price Management'));
    }

    private function checkQuedanVsConsolidated(): array
    {
        $q = $this->pairs(Quedan::query());
        $c = $this->pairs(ConsolidatedUpload::query());
        $onlyQ = array_values(array_diff(array_keys($q), array_keys($c)));
        $onlyC = array_values(array_diff(array_keys($c), array_keys($q)));
        $count = count($onlyQ) + count($onlyC);

        $rows = array_merge(self::pairRows($onlyQ, $q, 'Consolidated'), self::pairRows($onlyC, $c, 'Quedan Registry'));
        $status = !$count ? 'ok' : ((empty($q) || empty($c)) ? 'danger' : 'warning');
        $summary = !$count ? 'Every planter-week matches (' . count($q) . ')'
            : (empty($c) ? 'Consolidated summary not uploaded for this period'
            : (empty($q) ? 'Quedan registry not uploaded for this period'
            : count($onlyQ) . ' without consolidated · ' . count($onlyC) . ' without quedan'));

        return $this->result('quedan_vs_consolidated', 'Quedan registry ↔ Consolidated summary', ['Quedan Registry', 'Consolidated', 'Uploads'],
            $status, $summary,
            'Each planter delivering in a week should appear in both uploads. A planter missing on one side gets a voucher without its sugar proceeds or without its allowances.',
            ['count' => $count, 'columns' => ['Week', 'Planter', 'Missing in'], 'rows' => array_slice($rows, 0, 12)] + $this->link('consolidated-report', 'Open Consolidated Report'));
    }

    private function checkMolassesVsQuedan(): array
    {
        $q = $this->pairs(Quedan::query());
        $m = $this->pairs(Molass::query());
        $onlyQ = array_values(array_diff(array_keys($q), array_keys($m)));
        $onlyM = array_values(array_diff(array_keys($m), array_keys($q)));
        $count = count($onlyQ) + count($onlyM);
        $rows = array_merge(self::pairRows($onlyQ, $q, 'Molasses'), self::pairRows($onlyM, $m, 'Quedan Registry'));

        return $this->result('molasses_vs_quedan', 'Quedan ↔ Molasses uploads', ['Quedan Registry', 'Uploads'],
            !$count ? 'ok' : ((empty($q) xor empty($m)) ? 'danger' : 'warning'),
            !$count ? 'Quedan and molasses cover the same planter-weeks' : count($onlyQ) . ' quedan without molasses · ' . count($onlyM) . ' molasses without quedan',
            'Sugar and molasses come from the same milling, so the same planters should appear in both files for a week.',
            ['count' => $count, 'columns' => ['Week', 'Planter', 'Missing in'], 'rows' => array_slice($rows, 0, 12)] + $this->link('prices.registry', 'Open Registry'));
    }

    private function checkTruckingVsConsolidated(): array
    {
        $sum = function ($query, string $field) {
            $out = [];
            foreach ($this->scoped($query)->selectRaw("week_no, planter_code, MAX(planter_name) as planter_name, SUM({$field}) as qty")->groupBy('week_no', 'planter_code')->get() as $r) {
                $key = (int) $r->week_no . '|' . self::codeKey($r->planter_code);
                $out[$key] = ['name' => $r->planter_name, 'qty' => ($out[$key]['qty'] ?? 0) + (float) $r->qty];
            }
            return $out;
        };
        $t = $sum(TruckingAllowance::query(), 'net_cane');
        $c = $sum(ConsolidatedUpload::query(), 'ta_wt');

        $diffs = [];
        foreach ($t as $key => $row) {
            if (!isset($c[$key])) {
                continue;
            }
            $diff = $row['qty'] - $c[$key]['qty'];
            // Half a percent (at least 10 kg) of rounding is allowed
            if (abs($diff) > max(0.01, abs($c[$key]['qty']) * 0.005)) {
                [$week, $code] = explode('|', $key, 2);
                $diffs[] = ['Week' => $week, 'Planter' => $row['name'] . ' (' . $code . ')',
                    'Trucking (t)' => round($row['qty'], 3), 'Consolidated (t)' => round($c[$key]['qty'], 3), 'Difference' => round($diff, 3)];
            }
        }
        usort($diffs, fn ($a, $b) => abs($b['Difference']) <=> abs($a['Difference']));
        $count = count($diffs);
        $tons = array_sum(array_map(fn ($d) => abs($d['Difference']), $diffs));

        return $this->result('trucking_vs_consolidated', 'Trucking allowance ↔ Consolidated cane weight', ['Consolidated', 'Uploads'],
            $count ? 'warning' : 'ok',
            $count ? $count . ' planter-week(s) differ by ' . number_format($tons, 3) . ' t' : (empty($t) ? 'No trucking allowance uploaded for this period' : 'Trucking net cane matches consolidated TA weight'),
            'The net cane in the Trucking Allowance upload should equal the TA weight of the same planter and week in the Consolidated summary.',
            ['count' => $count, 'amount' => round($tons, 3), 'columns' => ['Week', 'Planter', 'Trucking (t)', 'Consolidated (t)', 'Difference'], 'rows' => array_slice($diffs, 0, 10)]
                + $this->link('trucking-allowance-report', 'Open Trucking Allowance'));
    }

    private function checkRegistryUnbought(): array
    {
        $q = $this->scoped(Quedan::query())->where('status', 'pending')->selectRaw('COUNT(*) as n, SUM(sugar_lkg) as qty')->first();
        $m = $this->scoped(Molass::query())->where('status', 'pending')->selectRaw('COUNT(*) as n, SUM(mol_net) as qty')->first();
        $count = (int) $q->n + (int) $m->n;
        $byWeek = $this->scoped(Quedan::query())->where('status', 'pending')
            ->selectRaw('week_no, COUNT(*) as n, SUM(sugar_lkg) as qty')->groupBy('week_no')->get()
            ->map(fn ($r) => ['Week' => (int) $r->week_no, 'Module' => 'Quedan', 'Records' => (int) $r->n, 'Quantity' => number_format((float) $r->qty, 3) . ' LKG'])
            ->concat($this->scoped(Molass::query())->where('status', 'pending')
                ->selectRaw('week_no, COUNT(*) as n, SUM(mol_net) as qty')->groupBy('week_no')->get()
                ->map(fn ($r) => ['Week' => (int) $r->week_no, 'Module' => 'Molasses', 'Records' => (int) $r->n, 'Quantity' => number_format((float) $r->qty, 3) . ' t']))
            ->sortBy('Week')->values()->all();

        return $this->result('registry_unbought', 'Registry records bought', ['Quedan Registry'],
            $count ? 'info' : 'ok',
            $count ? (int) $q->n . ' quedan · ' . (int) $m->n . ' molasses not yet bought' : 'Every registry record is bought or rejected',
            'Records stay "pending" until processed in Buy Quedan / Buy Molasses.',
            ['count' => $count, 'columns' => ['Week', 'Module', 'Records', 'Quantity'], 'rows' => array_slice($byWeek, 0, 12)] + $this->link('quedan-buy.index', 'Open Buy Quedan'));
    }

    private function checkUnregisteredPlanters(): array
    {
        $profiles = PlanterProfile::pluck('planter_code')->map(fn ($c) => self::codeKey($c))->flip();
        $seen = [];
        $collect = function (string $module, $query) use (&$seen, $profiles) {
            foreach ($query->select('planter_code', 'planter_name')->distinct()->get() as $r) {
                $key = self::codeKey($r->planter_code);
                if ($key === '' || $profiles->has($key)) {
                    continue;
                }
                $seen[$key] ??= ['Code' => $r->planter_code, 'Planter' => $r->planter_name, 'Found in' => []];
                $seen[$key]['Found in'][$module] = true;
            }
        };
        $collect('Quedan', $this->scoped(Quedan::query()));
        $collect('Molasses', $this->scoped(Molass::query()));
        $collect('Consolidated', $this->scoped(ConsolidatedUpload::query()));
        if ($this->can('cash_advances')) {
            $collect('Cash Advance', CashAdvance::whereIn('status', ['pending', 'approved', 'active']));
        }
        if ($this->can('loans')) {
            $collect('Loan', Loan::whereIn('status', ['pending', 'approved', 'active']));
        }
        $rows = collect($seen)->map(fn ($r) => array_merge($r, ['Found in' => implode(', ', array_keys($r['Found in']))]))->values();

        return $this->result('unregistered_planters', 'Planters have a profile', ['Planter Profiles', 'Uploads', 'Receivables'],
            $rows->count() ? 'warning' : 'ok',
            $rows->count() ? $rows->count() . ' planter code(s) without a profile' : 'Every planter code is registered',
            'Codes in uploads, loans or cash advances that do not match a Planter Profile. Use "Sync" on Planter Profiles, or correct the code in the upload.',
            ['count' => $rows->count(), 'columns' => ['Code', 'Planter', 'Found in'], 'rows' => $rows->take(12)->all()] + $this->link('planter-profiles.index', 'Open Planter Profiles'));
    }

    private function checkInactiveWithDeliveries(): array
    {
        $inactive = PlanterProfile::where('status', '!=', 'active')->get(['planter_code', 'planter_name', 'status'])
            ->keyBy(fn ($p) => self::codeKey($p->planter_code));
        if ($inactive->isEmpty()) {
            return $this->result('inactive_with_deliveries', 'Inactive planters with deliveries', ['Planter Profiles', 'Uploads'],
                'ok', 'No inactive or suspended planters', 'Inactive or suspended planters who still delivered cane in the period.');
        }
        $delivering = $this->scoped(Quedan::query())->distinct()->pluck('planter_code')
            ->concat($this->scoped(ConsolidatedUpload::query())->distinct()->pluck('planter_code'))
            ->map(fn ($c) => self::codeKey($c))->unique();
        $rows = $delivering->filter(fn ($k) => $inactive->has($k))
            ->map(fn ($k) => ['Code' => $inactive[$k]->planter_code, 'Planter' => $inactive[$k]->planter_name, 'Status' => ucfirst($inactive[$k]->status)])
            ->values();

        return $this->result('inactive_with_deliveries', 'Inactive planters with deliveries', ['Planter Profiles', 'Uploads'],
            $rows->count() ? 'info' : 'ok',
            $rows->count() ? $rows->count() . ' inactive planter(s) delivered' : 'No deliveries from inactive planters',
            'Inactive or suspended planters who still delivered cane in the period. Reactivate them or review the upload.',
            ['count' => $rows->count(), 'columns' => ['Code', 'Planter', 'Status'], 'rows' => $rows->take(12)->all()] + $this->link('planter-profiles.index', 'Open Planter Profiles'));
    }

    private function checkOverDeducted(): array
    {
        $neg = $this->proceedsRows()->filter(fn ($r) => round($r['paid'], 2) < 0)->sortBy('paid')->values();
        $amount = round(abs((float) $neg->sum('paid')), 2);

        return $this->result('over_deducted', 'Accounts applied fit within net proceeds', ['Check Voucher', 'Receivables'],
            $neg->count() ? 'danger' : 'ok',
            $neg->count() ? $neg->count() . ' planter(s) with negative net paid (₱' . number_format($amount, 2) . ')' : 'No voucher goes below zero',
            'Loan, cash advance, fuel and others payments are larger than the planter\'s net proceeds, so the voucher would pay a negative amount. Move part of the schedule to a later week.',
            ['count' => $neg->count(), 'amount' => $amount, 'columns' => ['Planter', 'Net Proceeds', 'Accounts Applied', 'Net Paid'],
                'rows' => $neg->take(10)->map(fn ($r) => [
                    'Planter' => $r['planter_name'] . ' (' . $r['planter_code'] . ')',
                    'Net Proceeds' => '₱' . number_format($r['net'], 2),
                    'Accounts Applied' => '₱' . number_format($r['applied'], 2),
                    'Net Paid' => '₱' . number_format($r['paid'], 2),
                ])->all()] + $this->link('proceeds-deductions-report', 'Open Proceeds & Deductions'));
    }

    private function checkDeductionsWithoutProceeds(): array
    {
        // Planter-weeks with something to deduct from
        $producing = [];
        foreach ([Quedan::query(), Molass::query(), ConsolidatedUpload::query()] as $query) {
            foreach ($this->scoped($query)->select('week_no', 'planter_code', 'planter_name')->distinct()->get() as $r) {
                $producing[(int) $r->week_no . '|' . self::codeKey($r->planter_code)] = true;
                $producing[(int) $r->week_no . '|N:' . self::codeKey($r->planter_name)] = true;
            }
        }

        $scheduled = collect();
        if ($this->can('cash_advances')) {
            $scheduled = $scheduled->concat($this->scoped(CashAdvanceDeduction::query())
                ->whereHas('cashAdvance', fn ($q) => $q->whereIn('status', ['approved', 'active']))
                ->with('cashAdvance:id,ca_number,planter_code,planter_name')->get()
                ->map(fn ($d) => ['week' => (int) $d->week_no, 'code' => $d->cashAdvance->planter_code, 'name' => $d->cashAdvance->planter_name,
                    'module' => 'Cash Advance ' . $d->cashAdvance->ca_number, 'amount' => (float) $d->amount]));
        }
        if ($this->can('deductions')) {
            $scheduled = $scheduled->concat($this->safely('balance.deduction_weeks', fn () => $this->scoped(PlanterDeductionWeek::query())
                ->with('deduction:id,type,reference_no,planter_code,planter_name')->get()
                ->filter(fn ($w) => $w->deduction !== null)
                ->map(fn ($w) => ['week' => (int) $w->week_no, 'code' => $w->deduction->planter_code, 'name' => $w->deduction->planter_name,
                    'module' => ucfirst($w->deduction->type) . ' ' . $w->deduction->reference_no, 'amount' => (float) $w->amount]), collect()));
        }

        // Only weeks that already have deliveries uploaded; future weeks are simply not milled yet
        $deliveredWeeks = collect(array_keys($producing))->map(fn ($k) => (int) explode('|', $k)[0])->unique()->flip();
        $orphans = $scheduled->filter(fn ($s) => $deliveredWeeks->has($s['week'])
            && !isset($producing[$s['week'] . '|' . self::codeKey($s['code'])])
            && !isset($producing[$s['week'] . '|N:' . self::codeKey($s['name'])]))
            ->sortBy('week')->values();
        $amount = round((float) $orphans->sum('amount'), 2);

        return $this->result('deductions_without_proceeds', 'Scheduled deductions have proceeds to come from', ['Receivables', 'Check Voucher', 'Quedan Registry'],
            $orphans->count() ? 'warning' : 'ok',
            $orphans->count() ? $orphans->count() . ' scheduled week(s) worth ₱' . number_format($amount, 2) . ' with no delivery' : 'Every scheduled deduction falls on a week the planter delivered',
            'A cash advance, fuel or others schedule falls on a week the planter has no quedan, molasses or consolidated record, so the voucher has nothing to deduct it from and the balance stays unpaid.',
            ['count' => $orphans->count(), 'amount' => $amount, 'columns' => ['Week', 'Planter', 'Account', 'Amount'],
                'rows' => $orphans->take(12)->map(fn ($s) => [
                    'Week' => $s['week'], 'Planter' => $s['name'] . ' (' . $s['code'] . ')', 'Account' => $s['module'],
                    'Amount' => '₱' . number_format($s['amount'], 2),
                ])->all()] + $this->link('cash-advances.index', 'Open Cash Advances'));
    }

    private function checkCaOverdue(): array
    {
        $rows = $this->caOverdue(now()->toDateString());
        $amount = round((float) $rows->sum('remaining'), 2);

        return $this->result('ca_overdue', 'Cash advance schedule ↔ payments', ['Receivables'],
            $rows->count() ? 'danger' : 'ok',
            $rows->count() ? $rows->count() . ' past-due week(s), ₱' . number_format($amount, 2) . ' unpaid' : 'Every past-due cash advance week is paid',
            'Weeks of active cash advances whose due date has passed but whose payment is not recorded in full.',
            ['count' => $rows->count(), 'amount' => $amount, 'columns' => ['CA No.', 'Planter', 'Week', 'Due', 'Unpaid'],
                'rows' => $rows->take(12)->map(fn ($r) => [
                    'CA No.' => $r['reference'], 'Planter' => $r['planter_name'], 'Week' => $r['week_no'],
                    'Due' => $r['due_date'], 'Unpaid' => '₱' . number_format($r['remaining'], 2),
                ])->all()] + $this->link('cash-advances.index', 'Open Cash Advances'));
    }

    private function checkCaSchedule(): array
    {
        $cas = CashAdvance::whereIn('status', ['approved', 'active', 'completed'])
            ->withSum('deductions as scheduled', 'amount')
            ->withSum('payments as paid', 'amount')
            ->withCount('amortizations')
            ->get();

        $rows = [];
        foreach ($cas as $ca) {
            $scheduled = round((float) $ca->scheduled, 2);
            $paid = round((float) $ca->paid, 2);
            if ($scheduled <= 0 && !$ca->amortizations_count) {
                if ($ca->status !== 'completed') {
                    $rows[] = ['CA No.' => $ca->ca_number, 'Planter' => $ca->planter_name, 'Problem' => 'No repayment schedule', 'Stored' => '₱' . number_format($ca->balance, 2), 'Expected' => '—'];
                }
                continue;
            }
            if ($scheduled <= 0) {
                continue; // older monthly advance, repaid by amortization
            }
            $expected = max(0, round($scheduled - $paid, 2));
            if (abs($expected - (float) $ca->balance) > 0.01) {
                $rows[] = ['CA No.' => $ca->ca_number, 'Planter' => $ca->planter_name, 'Problem' => 'Balance ≠ schedule − payments',
                    'Stored' => '₱' . number_format($ca->balance, 2), 'Expected' => '₱' . number_format($expected, 2)];
            }
            $repayable = $ca->repayable();
            if (abs($scheduled - $repayable) > 0.01) {
                $rows[] = ['CA No.' => $ca->ca_number, 'Planter' => $ca->planter_name, 'Problem' => 'Schedule ≠ amount + interest',
                    'Stored' => '₱' . number_format($scheduled, 2), 'Expected' => '₱' . number_format($repayable, 2)];
            }
        }

        return $this->result('ca_schedule', 'Cash advance balance ↔ schedule', ['Receivables'],
            count($rows) ? 'danger' : 'ok',
            count($rows) ? count($rows) . ' cash advance(s) out of balance' : 'Every cash advance balance equals its schedule less payments',
            'The stored balance must equal the weekly schedule less recorded payments, and the schedule must add up to the advance plus its interest. Opening and re-saving the schedule recalculates it.',
            ['count' => count($rows), 'columns' => ['CA No.', 'Planter', 'Problem', 'Stored', 'Expected'], 'rows' => array_slice($rows, 0, 12)]
                + $this->link('cash-advances.index', 'Open Cash Advances'));
    }

    private function checkLoanBalance(): array
    {
        $loans = Loan::whereIn('status', ['approved', 'active', 'completed'])
            ->with(['amortizations:id,loan_id,due_date,amount_due', 'payments:id,loan_id,month,amount'])
            ->get();

        $rows = [];
        foreach ($loans as $loan) {
            if ($loan->amortizations->isEmpty()) {
                if ($loan->status !== 'completed') {
                    $rows[] = ['Loan No.' => $loan->loan_number, 'Planter' => $loan->planter_name, 'Problem' => 'No amortization schedule', 'Stored' => '₱' . number_format($loan->balance, 2), 'Expected' => '—'];
                }
                continue;
            }
            // Same rule as Loan::syncBalance: a payment counts against the installment of its month
            $months = $loan->amortizations->map(fn ($a) => $a->due_date->format('Y-m'))->flip();
            $paid = $loan->payments->filter(fn ($p) => $months->has($p->month))->sum('amount');
            $stray = $loan->payments->reject(fn ($p) => $months->has($p->month));
            $expected = max(0, round((float) $loan->amortizations->sum('amount_due') - (float) $paid, 2));

            if (abs($expected - (float) $loan->balance) > 0.01) {
                $rows[] = ['Loan No.' => $loan->loan_number, 'Planter' => $loan->planter_name, 'Problem' => 'Balance ≠ schedule − payments',
                    'Stored' => '₱' . number_format($loan->balance, 2), 'Expected' => '₱' . number_format($expected, 2)];
            }
            if ($stray->isNotEmpty()) {
                $rows[] = ['Loan No.' => $loan->loan_number, 'Planter' => $loan->planter_name, 'Problem' => 'Payment for a month outside the schedule (' . $stray->pluck('month')->unique()->implode(', ') . ')',
                    'Stored' => '₱' . number_format($stray->sum('amount'), 2), 'Expected' => '₱0.00'];
            }
        }

        return $this->result('loan_balance', 'Loan balance ↔ amortization & payments', ['Receivables'],
            count($rows) ? 'danger' : 'ok',
            count($rows) ? count($rows) . ' loan(s) out of balance' : ($loans->isEmpty() ? 'No approved or active loans' : 'Every loan balance equals its schedule less payments'),
            'The stored loan balance must equal the amortization schedule less payments recorded for scheduled months.',
            ['count' => count($rows), 'columns' => ['Loan No.', 'Planter', 'Problem', 'Stored', 'Expected'], 'rows' => array_slice($rows, 0, 12)]
                + $this->link('loans.index', 'Open Loans'));
    }

    private function checkTlsUnliquidated(): array
    {
        $rows = $this->scoped(TlsHaulingUpload::query())->whereNull('hauling_liquidation_id')
            ->selectRaw('week_no, COUNT(*) as n, SUM(net_cane_wt) as tons, COUNT(DISTINCT plate_no) as plates')
            ->groupBy('week_no')->get()
            ->map(fn ($r) => ['Week' => (int) $r->week_no, 'Tickets' => (int) $r->n, 'Trucks' => (int) $r->plates, 'Net cane (t)' => round((float) $r->tons, 3)])
            ->sortBy('Week')->values();

        // Tonnage liquidated vs the tickets linked to those entries
        $linked = (float) $this->scoped(TlsHaulingUpload::query())->whereNotNull('hauling_liquidation_id')->sum('net_cane_wt');
        $liquidated = (float) $this->scoped(HaulingLiquidation::query())->whereHas('tlsTickets')->sum('tonnage');
        $drift = round($liquidated - $linked, 3);
        $count = (int) $rows->sum('Tickets');
        $status = $count || abs($drift) > 0.01 ? 'warning' : 'ok';
        $summary = $count ? $count . ' TLS ticket(s), ' . number_format($rows->sum('Net cane (t)'), 3) . ' t not liquidated' : 'Every TLS ticket is liquidated';
        if (abs($drift) > 0.01) {
            $summary .= ' · liquidated tonnage differs from its tickets by ' . number_format($drift, 3) . ' t';
        }

        return $this->result('tls_unliquidated', 'TLS tickets ↔ Hauling liquidations', ['Hauler TLS LIQ', 'Uploads'],
            $status, $summary,
            'Uploaded TLS tickets become hauling liquidation entries. Tickets without an entry are not paid to the hauler, and an entry\'s tonnage should equal its tickets\' net cane.',
            ['count' => $count, 'amount' => $drift, 'columns' => ['Week', 'Tickets', 'Trucks', 'Net cane (t)'], 'rows' => $rows->take(12)->all()]
                + $this->link('hauling-liquidity.index', 'Open Hauler TLS LIQ'));
    }

    private function checkLiquidationIncomplete(): array
    {
        $entries = $this->scoped(HaulingLiquidation::query())->with('hauler:id,name')
            ->where(fn ($q) => $q->whereNull('payee_name')->orWhere('payee_name', '')->orWhere('rate_per_ton', '<=', 0))
            ->orderBy('week_no')->limit(50)->get();
        $count = $this->scoped(HaulingLiquidation::query())
            ->where(fn ($q) => $q->whereNull('payee_name')->orWhere('payee_name', '')->orWhere('rate_per_ton', '<=', 0))->count();

        return $this->result('liquidation_incomplete', 'Liquidation entries are complete', ['Hauler TLS LIQ'],
            $count ? 'warning' : 'ok',
            $count ? $count . ' entr' . ($count === 1 ? 'y' : 'ies') . ' without payee or rate' : 'Every entry has a payee and a rate per ton',
            'New truck plates wait for a payee and rate to be picked. Until then the entry\'s gross and net are ₱0.00.',
            ['count' => $count, 'columns' => ['Week', 'Hauler', 'Truck', 'Problem'],
                'rows' => $entries->take(12)->map(fn ($e) => [
                    'Week' => (int) $e->week_no, 'Hauler' => $e->hauler->name ?? '—', 'Truck' => $e->truck_code ?: '—',
                    'Problem' => trim((trim((string) $e->payee_name) === '' ? 'No payee ' : '') . ((float) $e->rate_per_ton <= 0 ? 'No rate' : '')),
                ])->all()] + $this->link('hauling-liquidity.index', 'Open Hauler TLS LIQ'));
    }
}
