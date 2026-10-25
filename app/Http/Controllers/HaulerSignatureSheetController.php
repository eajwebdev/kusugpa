<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CropYear;
use App\Models\Hauler;
use App\Models\HaulingLiquidation;
use App\Models\WeekNo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Hauler TLS Signature: the weekly sheet haulers sign when they receive their hauling check.
 * Laid out like the Proceeds Sheet, one sheet per hauler location (e.g. Canlamay, Inapoy);
 * amounts are the Hauler TLS LIQ entries' own.
 */
class HaulerSignatureSheetController extends Controller
{
    private const ROWS_PER_PAGE = 15;

    public function index()
    {
        AuditLog::log('view', 'reports', 'Viewed hauler TLS signature sheet page');

        $cropYear = CropYear::pluck('crop_year');
        $weeks = WeekNo::byCropYear();
        $haulers = Hauler::orderBy('name')->get(['id', 'name', 'is_active']);

        return view('reports.hauler-signature-sheet', compact('cropYear', 'weeks', 'haulers'));
    }

    public function pdf(Request $request)
    {
        $data = $request->validate([
            'crop_year' => 'required|string',
            'week_from' => 'required',
            'week_to' => 'required',
            'hauler_id' => 'nullable|array',
            'hauler_id.*' => 'integer',
            'date_issued' => 'nullable|date',
        ]);

        $from = (int) $data['week_from'];
        $to = (int) $data['week_to'];

        $sheets = HaulingLiquidation::with('hauler:id,name')
            ->where('crop_year', $data['crop_year'])
            ->when(!empty($data['hauler_id']), fn ($q) => $q->whereIn('hauler_id', $data['hauler_id']))
            ->get()
            ->filter(fn ($e) => (int) $e->week_no >= $from && (int) $e->week_no <= $to)
            // Entries with nothing to receive get no check
            ->reject(fn ($e) => round((float) $e->net_amount, 2) == 0)
            ->groupBy('hauler_id')
            ->map(function ($entries) {
                $rows = $entries
                    ->map(fn ($e) => [
                        'name' => $e->payee_name,
                        'plate_no' => $e->truck_code,
                        'check_no' => $e->check_no,
                        'tons' => round((float) $e->tonnage, 3),
                        'deducted' => round((float) $e->total_less, 2),
                        'net' => round((float) $e->net_amount, 2),
                    ])
                    ->sortBy(fn ($r) => mb_strtoupper(trim((string) $r['name'])) . '|' . mb_strtoupper((string) $r['plate_no']))
                    ->values();

                return [
                    'location' => $entries->first()->hauler->name,
                    'rows' => $rows,
                    'pages' => $rows->chunk(self::ROWS_PER_PAGE)->values(),
                ];
            })
            ->sortBy('location')
            ->values();

        $weekLabel = $from === $to ? str_pad($from, 2, '0', STR_PAD_LEFT) : str_pad($from, 2, '0', STR_PAD_LEFT) . '-' . str_pad($to, 2, '0', STR_PAD_LEFT);

        AuditLog::log('print', 'hauling_liquidity', 'Generated hauler TLS signature sheet', [
            'crop_year' => $data['crop_year'],
            'weeks' => $data['week_from'] . ' - ' . $data['week_to'],
            'locations' => $sheets->pluck('location')->all(),
            'entries' => $sheets->sum(fn ($s) => $s['rows']->count()),
        ]);

        $pdf = Pdf::loadView('pdf.hauler-signature-sheet', [
            'sheets' => $sheets,
            'weekLabel' => $weekLabel,
            'dateIssued' => Carbon::parse($data['date_issued'] ?? now())->format('m-d-Y'),
            'org' => config('kusugpa'),
        ])->setPaper('letter', 'portrait');

        $filename = 'hauler-tls-signature-' . $data['crop_year'] . '-wk' . $weekLabel . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
