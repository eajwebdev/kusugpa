<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CropYear;
use App\Models\Molass;
use App\Models\Quedan;
use App\Models\WeekNo;
use App\Services\PlanterProceeds;
use App\Support\PdfParts;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Proceeds & Deductions: every planter's proceeds (quedan, molasses, trucking), mill deductions,
 * accounts applied (loan, cash advance, others, fuel) and net paid, for the selected week and
 * to date (week 1 through that week). To date adds up each week's voucher, so it matches the
 * weekly Check Vouchers and their quedan prices.
 */
class ProceedsDeductionsController extends Controller
{
    private const PLANTERS_PER_PAGE = 18;

    /** Consolidated statement: molasses handling fee per MT, and the flat insurance per planter with molasses (as on the voucher) */
    public const MOLASSES_HANDLING_PER_MT = 120.0;
    public const MOLASSES_INSURANCE_PER_PLANTER = 30.0;

    /** Amount columns of the report, in print order */
    public const COLUMNS = [
        'quedan' => 'QUEDAN PROCEEDS',
        'molasses' => 'MOLASSES PROCEEDS',
        'trucking' => 'TRUCKING ALLOW.',
        'gross' => 'GROSS PROCEEDS',
        'mill' => 'LIENS, CHARGES & TAX',
        'fuel_issued' => 'FUEL ISSUANCE',
        'net' => 'NET PROCEEDS',
        'loan' => 'LOAN PAYMENT',
        'ca' => 'CASH ADVANCE',
        'others' => 'OTHERS',
        'fuel' => 'FUEL A/C',
        'net_paid' => 'NET PAID',
    ];

    public function index()
    {
        $cropYear = CropYear::pluck('crop_year');
        $weeks = WeekNo::byCropYear();
        // Same "Week Bought" options as the Quedan & Molasses Registry
        $boughtWeeks = $cropYear->mapWithKeys(fn ($year) => [(string) $year => WeekNo::boughtWeekOptions((string) $year, true)]);

        return view('reports.proceeds-deductions', compact('cropYear', 'weeks', 'boughtWeeks'));
    }

    public function pdf(Request $request)
    {
        $data = $request->validate([
            'crop_year' => 'required|string',
            'week_no' => 'required|integer|min:1',
            'date_issued' => 'nullable|date',
            'bought_week' => 'nullable|integer|min:1',
        ]);
        $cropYear = $data['crop_year'];
        $week = (int) $data['week_no'];
        $boughtWeek = $data['bought_week'] ?? null;

        // Weeks of the crop year up to the selected one (week 1.. when none are set up)
        $weeks = WeekNo::where('crop_year', $cropYear)->pluck('week_no')
            ->map(fn ($w) => (int) $w)->filter(fn ($w) => $w <= $week)->unique()->sort()->values();
        if ($weeks->isEmpty() || !$weeks->contains($week)) {
            $weeks = collect(range(1, $week));
        }

        $thisWeek = collect();
        $toDate = collect();
        foreach ($weeks as $w) {
            $lines = $this->weekLines($cropYear, $w, $boughtWeek);
            if ($w === $week) {
                $thisWeek = $lines;
            }
            foreach ($lines as $key => $line) {
                $sum = $toDate[$key] ?? array_merge($line, array_fill_keys(array_keys(self::COLUMNS), 0.0));
                foreach (array_keys(self::COLUMNS) as $col) {
                    $sum[$col] = round($sum[$col] + $line[$col], 2);
                }
                $toDate[$key] = $sum;
            }
        }

        $empty = array_fill_keys(array_keys(self::COLUMNS), 0.0);
        $planters = $toDate
            ->map(fn ($td, $key) => [
                'code' => $td['code'],
                'name' => $td['name'],
                'week' => isset($thisWeek[$key]) ? array_intersect_key($thisWeek[$key], $empty) : $empty,
                'to_date' => array_intersect_key($td, $empty),
            ])
            ->sortBy(fn ($p) => mb_strtoupper(trim($p['name'])))
            ->values();

        $totals = [
            'week' => collect(array_keys(self::COLUMNS))->mapWithKeys(fn ($c) => [$c => round($planters->sum(fn ($p) => $p['week'][$c]), 2)])->all(),
            'to_date' => collect(array_keys(self::COLUMNS))->mapWithKeys(fn ($c) => [$c => round($planters->sum(fn ($p) => $p['to_date'][$c]), 2)])->all(),
        ];

        // Large crop years print in parts of 8 pages; totals (over every planter) close the last part
        $parts = PdfParts::make($request, $planters, self::PLANTERS_PER_PAGE * 8, 'planters');
        if ($parts->wantsInfo($request)) {
            return $parts->info();
        }

        $weekEnd = WeekNo::where('crop_year', $cropYear)->where('week_no', (string) $week)->value('week_end_date');

        AuditLog::log('print', 'reports', 'Generated proceeds and deductions report', [
            'crop_year' => $cropYear,
            'week' => $week,
            'weeks_to_date' => $weeks->first() . ' - ' . $weeks->last(),
            'bought_week' => $boughtWeek,
            'planters' => $planters->count(),
            'part' => $parts->label() ?: 'whole',
        ]);

        $pdf = Pdf::loadView('pdf.proceeds-deductions', [
            'pages' => $parts->items()->chunk(self::PLANTERS_PER_PAGE)->values(),
            'pageOffset' => intdiv($parts->offset(), self::PLANTERS_PER_PAGE),
            'totalPages' => max(1, (int) ceil($planters->count() / self::PLANTERS_PER_PAGE)),
            'isLastPart' => $parts->isLast(),
            'planters' => $planters,
            'totals' => $totals,
            'columns' => self::COLUMNS,
            'cropYear' => $cropYear,
            'week' => $week,
            'boughtWeek' => $boughtWeek,
            'toDateLabel' => 'WK ' . $weeks->first() . '-' . $weeks->last(),
            'weekEnding' => $weekEnd ? Carbon::parse($weekEnd)->format('m-d-Y') : '',
            'dateIssued' => Carbon::parse($data['date_issued'] ?? now())->format('m-d-Y'),
            'org' => config('kusugpa'),
        ])->setPaper('legal', 'landscape');

        $filename = $parts->filename('proceeds-deductions-' . $cropYear . '-wk' . str_pad($week, 2, '0', STR_PAD_LEFT)
            . ($boughtWeek ? '-bought-wk' . str_pad($boughtWeek, 2, '0', STR_PAD_LEFT) : ''));

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /**
     * Consolidated statement for one week: grand totals of every planter's volumes, gross proceeds,
     * quedan liens and other charges, trucking allowance and fuel, down to the grand total net proceeds
     */
    public function consolidated(Request $request)
    {
        $data = $request->validate([
            'crop_year' => 'required|string',
            'week_no' => 'nullable|integer|min:1',
            'date_issued' => 'nullable|date',
            'bought_week' => 'nullable|integer|min:1',
        ]);
        $cropYear = $data['crop_year'];
        // No week = All Weeks of the crop year (like the Registry's "All Weeks")
        $week = isset($data['week_no']) ? (int) $data['week_no'] : null;
        $boughtWeek = $data['bought_week'] ?? null;
        $weekValues = $week !== null
            ? array_values(array_unique([(string) $week, str_pad((string) $week, 2, '0', STR_PAD_LEFT)]))
            : null;

        // Two selections, two parts of the statement:
        //  - WEEK BOUGHT picks the quedans and molasses (gross proceeds down to NET PROCEEDS B4 TRKNG ALLW.):
        //    quedans and molasses bought that week, of any week, at the price picked when they were bought
        //    (same as the Registry's All Weeks + Week Bought). Molasses bought before sold prices were
        //    recorded count in their own week at that week's price.
        //    With no Week Bought, the quedans and molasses of the Week No. (or of every week).
        //  - WEEK NO. picks the trucking allowance and fuel A/C, the same weeks as Week From / Week To
        //    on the Trucking Allowance Report and Planter Accnt. Applied.
        $molWeek = null;

        // Volumes straight from the bought quedans and molasses
        $sugarQuery = Quedan::notPending()->where('crop_year', $cropYear);
        $molassesQuery = Molass::notPending()->where('crop_year', $cropYear);
        if ($boughtWeek) {
            $sugarQuery->boughtInWeek($cropYear, $boughtWeek);
            $molassesQuery->boughtInWeek($cropYear, $boughtWeek);
        } elseif ($weekValues !== null) {
            $sugarQuery->whereIn('week_no', $weekValues);
            $molassesQuery->whereIn('week_no', $weekValues);
        }
        $sugar = $sugarQuery
            ->selectRaw('COALESCE(SUM(sugar_lkg), 0) AS gross, COALESCE(SUM(labor_lkg), 0) AS labor, COUNT(*) AS quedans')
            ->first();
        $molassesVolume = (float) $molassesQuery->sum('mol_net');

        // Amounts as on each planter's Check Voucher, added up
        $sum = array_fill_keys(['sugar', 'molasses', 'liens', 'insurance', 'tax', 'service', 'trucking', 'fuel', 'net', 'loan', 'ca', 'others', 'fuel_ac', 'net_paid'], 0.0);
        // Each week is computed on its own (its own prices), as on that week's vouchers, then added up
        $allWeeks = $this->allWeeks($cropYear);
        $weeksToRun = ($week !== null && !$boughtWeek) ? [$week] : $allWeeks;
        $records = collect();
        $planterCodes = [];
        foreach ($weeksToRun as $w) {
            // Week Bought applies to the molasses as it does to the quedans
            foreach (PlanterProceeds::summary($cropYear, $w, $w, null, $boughtWeek) as $record) {
                $records->push($record);
                $planterCodes[strtoupper(preg_replace('/\s+/', ' ', trim((string) $record['planter_code'])))] = true;
            }
        }
        // Laid out like the mill's consolidated statement. Each amount is rounded per voucher, as printed,
        // then added up. The voucher's molasses "Insurance / Handling" (120.00/MT + 30.00) is split:
        // the 30.00 per planter with molasses goes to INSURANCE, the 120.00/MT to HANDLING FEE.
        // The 7.00/ton additional service charge (trans codes 06/08/25/36/38) is kept off the mill
        // deductions and taken off with the trucking allowance, so the grand total still equals the vouchers.
        $sum['handling'] = 0.0;
        $sum['additional'] = 0.0;
        $sum['storage'] = 0.0;
        // Quedans and molasses (Week Bought rule): gross proceeds down to net proceeds before trucking
        foreach ($records as $record) {
            $p = PlanterProceeds::compute($record);
            $hasMolasses = $p['mol_net'] > 0;
            $additional = round((float) $p['trucking_additional_charge'], 2);

            $sum['sugar'] += round($p['quedanGrossProceeds'], 2);
            $sum['molasses'] += round($p['molasses_gross'], 2);
            $sum['liens'] += round($p['quedanTotalLiens'] + $p['molasses_liens'], 2);
            $sum['insurance'] += round($p['quedanTotalInsurance'], 2) + ($hasMolasses ? self::MOLASSES_INSURANCE_PER_PLANTER : 0);
            $sum['handling'] += $hasMolasses ? round($p['mol_net'] * self::MOLASSES_HANDLING_PER_MT, 2) : 0;
            $sum['tax'] += round($p['quedanTotalTax'], 2) + round($p['molasses_tax'], 2);
            $sum['service'] += round($p['quedanTotalServiceCharge'] - $additional, 2) + round($p['molasses_service'], 2);
        }

        // Week No.: trucking allowance, fuel issuance, trans-code service charge and accounts applied of
        // that week (every week when All Weeks), as on that week's vouchers and Planter Accnt. Applied
        $truckingWeeks = $week !== null ? [$week] : $allWeeks;
        // Trucking allowance and fuel issuance: the Total and Fuel columns of the Trucking Allowance Report
        // (consolidated uploads) for the Week No., so the two reports tally
        $truckingWeekValues = collect($truckingWeeks)
            ->flatMap(fn ($w) => [(string) $w, str_pad((string) $w, 2, '0', STR_PAD_LEFT)])
            ->uniqueStrict()->values()->all();
        $uploads = \App\Models\ConsolidatedUpload::where('crop_year', $cropYear)
            ->whereIn('week_no', $truckingWeekValues)
            ->get(['total_summary', 'fuel_issuance_amt']);
        $sum['trucking'] = $uploads->sum(fn ($u) => round((float) $u->total_summary, 2));
        $sum['fuel'] = $uploads->sum(fn ($u) => round((float) $u->fuel_issuance_amt, 2));
        foreach ($truckingWeeks as $w) {
            foreach (PlanterProceeds::summary($cropYear, $w, $w) as $record) {
                $p = PlanterProceeds::compute($record);
                $sum['additional'] += round((float) $p['trucking_additional_charge'], 2);
                $sum['loan'] += round($p['loanPayment'], 2);
                $sum['ca'] += round($p['caPayment'], 2);
                $sum['others'] += round($p['othersPayment'], 2);
                $sum['fuel_ac'] += round($p['fuelPayment'], 2);
            }
        }
        $t = array_map(fn ($v) => round($v, 2), $sum);
        $t['gross'] = round($t['sugar'] + $t['molasses'], 2);
        $t['quedan_liens'] = round($t['liens'] + $t['insurance'] + $t['handling'], 2);
        $t['other_charges'] = round($t['tax'] + $t['service'] + $t['storage'], 2);
        $t['deductions'] = round($t['quedan_liens'] + $t['other_charges'], 2);
        $t['net_before'] = round($t['gross'] - $t['deductions'], 2);
        // Grand total net proceeds = quedan & molasses total - deductions/liens & other charges
        // + total net trucking allowance (Trucking Allowance Report) - total Fuel A/C (Planter Accnt. Applied)
        $t['net'] = round($t['gross'] - $t['deductions'] + $t['trucking'] - $t['fuel_ac'], 2);
        $t['accounts'] = round($t['loan'] + $t['ca'] + $t['others'] + $t['fuel_ac'], 2);
        $t['net_paid'] = round($t['net'] - $t['accounts'], 2);

        $volumes = [
            'sugar_gross' => round((float) $sugar->gross, 3),
            'labor' => round((float) $sugar->labor, 3),
            'sugar_net' => round((float) $sugar->gross - (float) $sugar->labor, 3),
            'molasses' => round($molassesVolume, 3),
            'quedans' => (int) $sugar->quedans,
        ];

        $weekEnd = $weekValues !== null
            ? WeekNo::where('crop_year', $cropYear)->whereIn('week_no', $weekValues)->value('week_end_date')
            : null;
        $planterCount = count($planterCodes);

        AuditLog::log('print', 'reports', 'Generated consolidated proceeds and deductions report', [
            'crop_year' => $cropYear,
            'week' => $week ?? 'all',
            'planters' => $planterCount,
            'bought_week' => $boughtWeek,
        ]);

        $pdf = Pdf::loadView('pdf.proceeds-deductions-consolidated', [
            't' => $t,
            'volumes' => $volumes,
            'planterCount' => $planterCount,
            'boughtWeek' => $boughtWeek,
            'molWeek' => $molWeek,
            'handlingRate' => self::MOLASSES_HANDLING_PER_MT,
            'cropYear' => $cropYear,
            'week' => $week,
            'weekEnding' => $weekEnd ? Carbon::parse($weekEnd)->format('m-d-Y') : '',
            'dateIssued' => Carbon::parse($data['date_issued'] ?? now())->format('m-d-Y'),
            'org' => config('kusugpa'),
        ])->setPaper('letter', 'portrait');

        $filename = 'proceeds-deductions-consolidated-' . $cropYear
            . ($week !== null ? '-wk' . str_pad($week, 2, '0', STR_PAD_LEFT) : '-all-weeks')
            . ($boughtWeek ? '-bought-wk' . str_pad($boughtWeek, 2, '0', STR_PAD_LEFT) : '') . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /**
     * Every week number of a crop year that has a week set up or any quedan, molasses,
     * trucking or consolidated upload ("3" and "03" count as the same week)
     *
     * @return int[]
     */
    private function allWeeks(string $cropYear): array
    {
        $sources = [
            WeekNo::where('crop_year', $cropYear),
            Quedan::where('crop_year', $cropYear),
            Molass::where('crop_year', $cropYear),
            \App\Models\TruckingAllowance::where('crop_year', $cropYear),
            \App\Models\ConsolidatedUpload::where('crop_year', $cropYear),
        ];

        return collect($sources)
            ->flatMap(fn ($q) => $q->distinct()->pluck('week_no'))
            ->map(fn ($w) => (int) $w)
            ->filter(fn ($w) => $w >= 1)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * One week's voucher figures per planter, keyed by planter code (spacing / case ignored)
     */
    private function weekLines(string $cropYear, int $week, $boughtWeek = null): Collection
    {
        return PlanterProceeds::summary($cropYear, $week, $week, null, $boughtWeek)
            ->mapWithKeys(function ($record) {
                $p = PlanterProceeds::compute($record);
                $key = strtoupper(preg_replace('/\s+/', ' ', trim((string) $record['planter_code'])));

                return [$key => [
                    'code' => trim((string) $record['planter_code']),
                    'name' => trim((string) $record['planter_name']),
                    'quedan' => round($p['quedanGrossProceeds'], 2),
                    'molasses' => round($p['molasses_gross'], 2),
                    'trucking' => round($p['consolidated_display'], 2),
                    'gross' => round($p['totalGrossProceeds'], 2),
                    'mill' => round($p['quedanTotalDeductions'] + $p['molasses_total_deductions'], 2),
                    'fuel_issued' => round($p['consolidated_fuel_amount'], 2),
                    'net' => round($p['totalNetProceeds'], 2),
                    'loan' => round($p['loanPayment'], 2),
                    'ca' => round($p['caPayment'], 2),
                    'others' => round($p['othersPayment'], 2),
                    'fuel' => round($p['fuelPayment'], 2),
                    'net_paid' => round($p['netProceedsPaid'], 2),
                ]];
            });
    }
}
