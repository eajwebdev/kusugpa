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

        return view('reports.proceeds-deductions', compact('cropYear', 'weeks'));
    }

    public function pdf(Request $request)
    {
        $data = $request->validate([
            'crop_year' => 'required|string',
            'week_no' => 'required|integer|min:1',
            'date_issued' => 'nullable|date',
        ]);
        $cropYear = $data['crop_year'];
        $week = (int) $data['week_no'];

        // Weeks of the crop year up to the selected one (week 1.. when none are set up)
        $weeks = WeekNo::where('crop_year', $cropYear)->pluck('week_no')
            ->map(fn ($w) => (int) $w)->filter(fn ($w) => $w <= $week)->unique()->sort()->values();
        if ($weeks->isEmpty() || !$weeks->contains($week)) {
            $weeks = collect(range(1, $week));
        }

        $thisWeek = collect();
        $toDate = collect();
        foreach ($weeks as $w) {
            $lines = $this->weekLines($cropYear, $w);
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
            'toDateLabel' => 'WK ' . $weeks->first() . '-' . $weeks->last(),
            'weekEnding' => $weekEnd ? Carbon::parse($weekEnd)->format('m-d-Y') : '',
            'dateIssued' => Carbon::parse($data['date_issued'] ?? now())->format('m-d-Y'),
            'org' => config('kusugpa'),
        ])->setPaper('legal', 'landscape');

        $filename = $parts->filename('proceeds-deductions-' . $cropYear . '-wk' . str_pad($week, 2, '0', STR_PAD_LEFT));

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
            'week_no' => 'required|integer|min:1',
            'date_issued' => 'nullable|date',
        ]);
        $cropYear = $data['crop_year'];
        $week = (int) $data['week_no'];
        $weekValues = array_values(array_unique([(string) $week, str_pad((string) $week, 2, '0', STR_PAD_LEFT)]));

        // Volumes straight from the bought quedans and molasses of the week
        $sugar = Quedan::notPending()->where('crop_year', $cropYear)->whereIn('week_no', $weekValues)
            ->selectRaw('COALESCE(SUM(sugar_lkg), 0) AS gross, COALESCE(SUM(labor_lkg), 0) AS labor, COUNT(*) AS quedans')
            ->first();
        $molassesVolume = (float) Molass::notPending()->where('crop_year', $cropYear)->whereIn('week_no', $weekValues)->sum('mol_net');

        // Amounts as on each planter's Check Voucher, added up
        $sum = array_fill_keys(['sugar', 'molasses', 'liens', 'insurance', 'tax', 'service', 'trucking', 'fuel', 'net', 'loan', 'ca', 'others', 'fuel_ac', 'net_paid'], 0.0);
        $records = PlanterProceeds::summary($cropYear, $week, $week);
        foreach ($records as $record) {
            $p = PlanterProceeds::compute($record);
            $sum['sugar'] += $p['quedanGrossProceeds'];
            $sum['molasses'] += $p['molasses_gross'];
            $sum['liens'] += $p['quedanTotalLiens'] + $p['molasses_liens'];
            $sum['insurance'] += $p['quedanTotalInsurance'] + $p['molasses_insurance'];
            $sum['tax'] += $p['quedanTotalTax'] + $p['molasses_tax'];
            $sum['service'] += $p['quedanTotalServiceCharge'] + $p['molasses_service'];
            $sum['trucking'] += $p['consolidated_display'];
            $sum['fuel'] += $p['consolidated_fuel_amount'];
            $sum['net'] += $p['totalNetProceeds'];
            $sum['loan'] += $p['loanPayment'];
            $sum['ca'] += $p['caPayment'];
            $sum['others'] += $p['othersPayment'];
            $sum['fuel_ac'] += $p['fuelPayment'];
            $sum['net_paid'] += $p['netProceedsPaid'];
        }
        $t = array_map(fn ($v) => round($v, 2), $sum);
        $t['gross'] = round($t['sugar'] + $t['molasses'], 2);
        $t['quedan_liens'] = round($t['liens'] + $t['insurance'], 2);
        $t['other_charges'] = round($t['tax'] + $t['service'], 2);
        $t['deductions'] = round($t['quedan_liens'] + $t['other_charges'], 2);
        $t['net_before'] = round($t['gross'] - $t['deductions'], 2);
        $t['accounts'] = round($t['loan'] + $t['ca'] + $t['others'] + $t['fuel_ac'], 2);

        $volumes = [
            'sugar_gross' => round((float) $sugar->gross, 3),
            'labor' => round((float) $sugar->labor, 3),
            'sugar_net' => round((float) $sugar->gross - (float) $sugar->labor, 3),
            'molasses' => round($molassesVolume, 3),
            'quedans' => (int) $sugar->quedans,
        ];

        $weekEnd = WeekNo::where('crop_year', $cropYear)->whereIn('week_no', $weekValues)->value('week_end_date');

        AuditLog::log('print', 'reports', 'Generated consolidated proceeds and deductions report', [
            'crop_year' => $cropYear,
            'week' => $week,
            'planters' => $records->count(),
        ]);

        $pdf = Pdf::loadView('pdf.proceeds-deductions-consolidated', [
            't' => $t,
            'volumes' => $volumes,
            'planterCount' => $records->count(),
            'cropYear' => $cropYear,
            'week' => $week,
            'weekEnding' => $weekEnd ? Carbon::parse($weekEnd)->format('m-d-Y') : '',
            'dateIssued' => Carbon::parse($data['date_issued'] ?? now())->format('m-d-Y'),
            'org' => config('kusugpa'),
        ])->setPaper('letter', 'portrait');

        $filename = 'proceeds-deductions-consolidated-' . $cropYear . '-wk' . str_pad($week, 2, '0', STR_PAD_LEFT) . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /**
     * One week's voucher figures per planter, keyed by planter code (spacing / case ignored)
     */
    private function weekLines(string $cropYear, int $week): Collection
    {
        return PlanterProceeds::summary($cropYear, $week, $week)
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
