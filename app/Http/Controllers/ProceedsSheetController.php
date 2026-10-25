<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CropYear;
use App\Models\WeekNo;
use App\Services\PlanterProceeds;
use App\Support\PdfParts;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * "Proceeds of Quedan, Molasses & Trucking Allowance": the weekly sheet planters sign when they
 * receive their check. Amounts are the same as the Voucher Ledger's (PlanterProceeds).
 */
class ProceedsSheetController extends Controller
{
    private const ROWS_PER_PAGE = 15;

    public function index()
    {
        AuditLog::log('view', 'reports', 'Viewed proceeds sheet page');

        $cropYear = CropYear::pluck('crop_year');
        $weeks = WeekNo::byCropYear();

        return view('reports.proceeds-sheet', compact('cropYear', 'weeks'));
    }

    public function pdf(Request $request)
    {
        $data = $request->validate([
            'crop_year' => 'required|string',
            'week_from' => 'required',
            'week_to' => 'required',
            'planter_name' => 'nullable|array',
            'start_check_no' => ['nullable', 'regex:/^\d{1,15}$/'],
            'date_issued' => 'nullable|date',
        ], [
            'start_check_no.regex' => 'The starting check no. must be digits only.',
        ]);

        $rows = PlanterProceeds::summary($data['crop_year'], $data['week_from'], $data['week_to'], $data['planter_name'] ?? null)
            ->map(function ($record) {
                $p = PlanterProceeds::compute($record);
                // Rounded to centavos as printed, so page totals add up to the lines (and the vouchers)
                return [
                    'code' => $record['planter_code'],
                    'name' => $record['planter_name'],
                    'trucking' => round($p['consolidated_net'], 2),
                    'quedan_a' => round($p['quedan_a_gross'], 2),
                    'quedan_b_mol' => round($p['quedan_b_net'] + $p['quedan_d_net'] + $p['molasses_net'], 2),
                    // Accounts applied on the voucher: loan, cash advance, others and fuel payments
                    'deduction' => round($p['accountsApplied'], 2),
                    'total' => round($p['netProceedsPaid'], 2),
                ];
            })
            // Planters with nothing to receive get no check
            ->reject(fn ($r) => round($r['trucking'], 2) == 0 && round($r['quedan_a'], 2) == 0 && round($r['quedan_b_mol'], 2) == 0)
            ->sortBy(fn ($r) => mb_strtoupper(trim($r['name'])))
            ->values();

        // Check numbers run in order from the starting number (keeping its leading zeros)
        $start = $data['start_check_no'] ?? null;
        $rows = $rows->map(function ($r, $i) use ($start) {
            $r['check_no'] = $start !== null ? str_pad((string) ((int) $start + $i), strlen($start), '0', STR_PAD_LEFT) : '';
            return $r;
        });

        $from = (int) $data['week_from'];
        $to = (int) $data['week_to'];
        $weekLabel = $from === $to ? str_pad($from, 2, '0', STR_PAD_LEFT) : str_pad($from, 2, '0', STR_PAD_LEFT) . '-' . str_pad($to, 2, '0', STR_PAD_LEFT);

        // Large weeks print in parts of 40 pages; check numbers above are already numbered across all rows
        $parts = PdfParts::make($request, $rows, self::ROWS_PER_PAGE * 40, 'planters');
        if ($parts->wantsInfo($request)) {
            return $parts->info();
        }

        AuditLog::log('print', 'voucher', 'Generated proceeds of quedan, molasses & trucking allowance sheet', [
            'crop_year' => $data['crop_year'],
            'weeks' => $data['week_from'] . ' - ' . $data['week_to'],
            'planters' => $rows->count(),
            'start_check_no' => $start,
            'part' => $parts->label() ?: 'whole',
        ]);

        // LESS ACCOUNTS only when someone has a deduction; then an empty -A- column makes room for it
        $showDeduction = $rows->contains(fn ($r) => round($r['deduction'], 2) != 0);
        $showQuedanA = !$showDeduction || $rows->contains(fn ($r) => round($r['quedan_a'], 2) != 0);

        $pdf = Pdf::loadView('pdf.proceeds-sheet', [
            'pages' => $parts->items()->chunk(self::ROWS_PER_PAGE)->values(),
            'rows' => $rows, // grand totals cover every planter
            'pageOffset' => intdiv($parts->offset(), self::ROWS_PER_PAGE),
            'totalPages' => max(1, (int) ceil($rows->count() / self::ROWS_PER_PAGE)),
            'isLastPart' => $parts->isLast(),
            'showDeduction' => $showDeduction,
            'showQuedanA' => $showQuedanA,
            'weekLabel' => $weekLabel,
            'dateIssued' => Carbon::parse($data['date_issued'] ?? now())->format('m-d-Y'),
            'org' => config('kusugpa'),
        ])->setPaper('letter', 'portrait');

        $filename = $parts->filename('proceeds-sheet-' . $data['crop_year'] . '-wk' . $weekLabel);

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
