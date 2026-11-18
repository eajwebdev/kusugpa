<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CropYear;
use App\Models\WeekNo;
use App\Services\PlanterProceeds;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Planter Accnt. Applied: the accounts taken off each planter's Check Voucher for the weeks
 * (Loan, Cash Advance, Others, Fuel), as a ledger for checking. Same planters and amounts as
 * the vouchers and the Proceeds Sheet (PlanterProceeds), without the signature column.
 */
class PlanterAccountsAppliedController extends Controller
{
    /** Room on one page, in single-line rows */
    public const PAGE_UNITS = 25.0;

    /** Characters of a planter name that fit on one line of its column */
    private const NAME_CHARS = 22;

    /** Each extra line of a wrapped planter name, as part of a single-line row */
    private const EXTRA_LINE_UNITS = 0.55;

    /**
     * Pages filled by the room the rows take, not a fixed count: a planter name that wraps to two
     * lines makes a taller row, and a fixed 25 rows then spilled a row or two onto a short extra page.
     * The last page keeps room for the totals row.
     */
    public static function paginate($rows)
    {
        $pages = [];
        $page = [];
        $used = 0.0;
        foreach ($rows as $row) {
            $lines = count(explode("\n", wordwrap(trim((string) $row['name']), self::NAME_CHARS, "\n", true)));
            $units = 1 + max(0, $lines - 1) * self::EXTRA_LINE_UNITS;
            if ($page && $used + $units > self::PAGE_UNITS) {
                $pages[] = $page;
                $page = [];
                $used = 0.0;
            }
            $page[] = $row;
            $used += $units;
        }
        // Totals row (taller, with its rule) must fit under the last page's rows
        if ($page && $used + 1.5 > self::PAGE_UNITS && count($page) > 1) {
            $moved = array_pop($page);
            $pages[] = $page;
            $page = [$moved];
        }
        if ($page) {
            $pages[] = $page;
        }

        return collect($pages)->map(fn ($p) => collect($p))->values();
    }

    public function index()
    {
        $cropYear = CropYear::pluck('crop_year');
        $weeks = WeekNo::byCropYear();

        return view('reports.planter-accounts', compact('cropYear', 'weeks'));
    }

    public function pdf(Request $request)
    {
        $data = $request->validate([
            'crop_year' => 'required|string',
            'week_from' => 'required',
            'week_to' => 'required',
            'date_issued' => 'nullable|date',
        ]);

        $from = min((int) $data['week_from'], (int) $data['week_to']);
        $to = max((int) $data['week_from'], (int) $data['week_to']);

        $rows = PlanterProceeds::summary($data['crop_year'], $from, $to)
            ->map(function ($record) {
                $p = PlanterProceeds::compute($record);
                // Rounded to centavos as printed on the vouchers, so the totals add up to the lines
                $row = [
                    'code' => $record['planter_code'],
                    'name' => $record['planter_name'],
                    'loan' => round($p['loanPayment'], 2),
                    'ca' => round($p['caPayment'], 2),
                    'others' => round($p['othersPayment'], 2),
                    'fuel' => round($p['fuelPayment'], 2),
                ];
                $row['total'] = round($row['loan'] + $row['ca'] + $row['others'] + $row['fuel'], 2);
                return $row;
            })
            // Only planters with an account applied
            ->reject(fn ($r) => $r['total'] == 0)
            ->sortBy(fn ($r) => mb_strtoupper(trim($r['name'])))
            ->values();

        $weekLabel = $from === $to ? str_pad($from, 2, '0', STR_PAD_LEFT) : str_pad($from, 2, '0', STR_PAD_LEFT) . '-' . str_pad($to, 2, '0', STR_PAD_LEFT);

        AuditLog::log('print', 'reports', 'Generated planter accounts applied report', [
            'crop_year' => $data['crop_year'],
            'weeks' => $from . ' - ' . $to,
            'planters' => $rows->count(),
            'total' => $rows->sum('total'),
        ]);

        $pdf = Pdf::loadView('pdf.planter-accounts', [
            'pages' => self::paginate($rows),
            'rows' => $rows,
            'cropYear' => $data['crop_year'],
            'weekLabel' => $weekLabel,
            'dateIssued' => Carbon::parse($data['date_issued'] ?? now())->format('m-d-Y'),
            'org' => config('kusugpa'),
        ])->setPaper('letter', 'portrait');

        $filename = 'planter-accounts-applied-' . $data['crop_year'] . '-wk' . $weekLabel . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
