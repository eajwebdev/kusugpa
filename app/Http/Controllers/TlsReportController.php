<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Models\AuditLog;
use App\Models\CropYear;
use App\Models\HaulingLiquidation;
use App\Models\HaulingLiquidationItem;
use App\Models\TlsHaulingUpload;
use App\Models\WeekNo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Summary of Transloading Delivery — the weekly TLS hauling upload grouped
 * by transloading station, then association, like the mill's printed summary.
 */
class TlsReportController extends Controller
{
    public function index()
    {
        $cropYears = CropYear::orderBy('crop_year', 'desc')->pluck('crop_year');
        $weeks = WeekNo::orderByRaw('CAST(week_no AS UNSIGNED) ASC')->get(['crop_year', 'week_no', 'week_start_date', 'week_end_date'])
            ->map(fn ($w) => [
                'crop_year' => $w->crop_year,
                'week_no' => (string) $w->week_no,
                'label' => 'Week ' . $w->week_no . ($w->week_end_date ? ' · ending ' . Carbon::parse($w->week_end_date)->format('M j') : ''),
            ]);

        $canDelete = $this->canDelete();

        return view('reports.tls-delivery', compact('cropYears', 'weeks', 'canDelete'));
    }

    /**
     * Removing uploaded tickets is for whoever may upload them
     */
    private function canDelete(): bool
    {
        return (bool) auth()->user()?->hasPermission('upload-tls');
    }

    /**
     * Weeks of a crop year with uploaded tickets, for the delete dialog: tickets, tons, and the
     * liquidation entries generated from them (released ones lock the week)
     */
    public function weeksWithTickets(Request $request)
    {
        $cropYear = $request->validate(['crop_year' => 'required|string'])['crop_year'];

        $tickets = TlsHaulingUpload::where('crop_year', $cropYear)
            ->get(['week_no', 'net_cane_wt', 'hauling_liquidation_id']);
        $entryIds = $tickets->pluck('hauling_liquidation_id')->filter()->unique();
        $released = HaulingLiquidation::whereIn('id', $entryIds)->released()->pluck('id')->flip();

        $weeks = $tickets->groupBy(fn ($t) => (int) $t->week_no)
            ->map(function ($rows, $week) use ($released) {
                $entries = $rows->pluck('hauling_liquidation_id')->filter()->unique();
                return [
                    'week_no' => (int) $week,
                    'tickets' => $rows->count(),
                    'tons' => round((float) $rows->sum('net_cane_wt'), 3),
                    'entries' => $entries->count(),
                    'released' => $entries->filter(fn ($id) => $released->has($id))->count(),
                ];
            })
            ->sortKeys()
            ->values();

        return response()->json(['crop_year' => $cropYear, 'weeks' => $weeks]);
    }

    /**
     * Delete the uploaded TLS tickets of the picked weeks, with the hauling liquidation entries generated
     * from them. Weeks whose entries are already released (check issued) are refused as a whole.
     */
    public function destroyWeeks(Request $request)
    {
        if (!$this->canDelete()) {
            return response()->json(['message' => 'You do not have permission to delete TLS hauling records.'], 403);
        }

        $data = $request->validate([
            'crop_year' => 'required|string',
            'weeks' => 'required|array|min:1',
            'weeks.*' => 'required|integer|min:1|max:99',
        ], ['weeks.required' => 'Select at least one week.']);

        $weekValues = HaulingLiquidation::weekValues($data['weeks']);
        $tickets = TlsHaulingUpload::where('crop_year', $data['crop_year'])->whereIn('week_no', $weekValues);
        $entryIds = (clone $tickets)->whereNotNull('hauling_liquidation_id')->distinct()->pluck('hauling_liquidation_id');

        $locked = HaulingLiquidation::whereIn('id', $entryIds)->released()->pluck('week_no')
            ->map(fn ($w) => (int) $w)->unique()->sort()->values();
        if ($locked->isNotEmpty()) {
            return response()->json([
                'message' => 'Week ' . $locked->implode(', ') . ' already has released hauling liquidations (CV / check issued). '
                    . 'Nothing was deleted. Remove those weeks from the selection, or undo the release in Hauler TLS LIQ first.',
                'locked_weeks' => $locked,
            ], 422);
        }

        $ticketCount = (clone $tickets)->count();
        if ($ticketCount === 0) {
            return response()->json(['message' => 'No TLS hauling tickets found for the selected weeks.'], 404);
        }

        DB::transaction(function () use ($tickets, $entryIds) {
            (clone $tickets)->delete();
            HaulingLiquidationItem::whereIn('hauling_liquidation_id', $entryIds)->delete();
            HaulingLiquidation::whereIn('id', $entryIds)->delete();
        });

        $weeks = collect($data['weeks'])->map(fn ($w) => (int) $w)->unique()->sort()->values();
        AuditLog::log('delete', 'tls_hauling', "Deleted TLS hauling tickets for {$data['crop_year']} week(s) " . $weeks->implode(', '), [
            'crop_year' => $data['crop_year'],
            'weeks' => $weeks->all(),
            'tickets' => $ticketCount,
            'liquidation_entries' => $entryIds->count(),
        ]);

        return response()->json([
            'message' => "Deleted {$ticketCount} TLS ticket(s) and {$entryIds->count()} hauling liquidation entr"
                . ($entryIds->count() === 1 ? 'y' : 'ies') . " for {$data['crop_year']} week " . $weeks->implode(', ') . '.',
            'tickets' => $ticketCount,
            'entries' => $entryIds->count(),
        ]);
    }

    /**
     * Station / association totals for the on-page summary
     */
    public function data(Request $request)
    {
        $rows = $this->rows($request);

        return response()->json([
            'stations' => $this->group($rows)->map(fn ($station) => [
                'code' => $station['code'],
                'name' => $station['name'],
                'records' => $station['records'],
                'gross' => $station['gross'],
                'net' => $station['net'],
                'amount' => $station['amount'],
                'associations' => $station['associations']->map(fn ($a) => collect($a)->except('rows'))->values(),
            ])->values(),
            'filters' => [
                'stations' => TlsHaulingUpload::query()->distinct()->orderBy('description')->pluck('description'),
                'associations' => TlsHaulingUpload::query()->distinct()->orderBy('assn_name')->pluck('assn_name'),
            ],
        ]);
    }

    public function pdf(Request $request)
    {
        $request->validate(['crop_year' => 'required|string', 'week_from' => 'required', 'week_to' => 'required']);

        $rows = $this->rows($request);
        [$dateFrom, $dateTo] = $this->period($request);

        AuditLog::log('print', 'reports', 'Generated TLS delivery report PDF', [
            'crop_year' => $request->crop_year,
            'weeks' => $request->week_from . ' - ' . $request->week_to,
            'records' => $rows->count(),
        ]);

        $pdf = Pdf::loadView('pdf.tls-delivery', [
            'stations' => $this->group($rows),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totals' => [
                'records' => $rows->count(),
                'gross' => round((float) $rows->sum('gross_cane_weight'), 3),
                'net' => round((float) $rows->sum('net_cane_wt'), 3),
                'amount' => round((float) $rows->sum('tdif_rate'), 2),
            ],
        ])->setPaper('letter', 'portrait');

        $filename = 'tls-delivery-' . $request->crop_year . '-wk' . $request->week_from . '-' . $request->week_to . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    private function rows(Request $request): Collection
    {
        $from = (int) $request->get('week_from');
        $to = (int) ($request->get('week_to') ?: $from);

        return TlsHaulingUpload::with('liquidation.hauler:id,name')
            ->where('crop_year', $request->get('crop_year'))
            ->when($from, fn ($q) => $q->whereRaw('CAST(week_no AS UNSIGNED) BETWEEN ? AND ?', [min($from, $to), max($from, $to)]))
            ->when($request->filled('station'), fn ($q) => $q->where('description', $request->station))
            ->when($request->filled('association'), fn ($q) => $q->where('assn_name', $request->association))
            ->orderBy('id')
            ->get();
    }

    /**
     * Station -> association -> rows, with record counts and weight / TDIF totals at each level
     */
    private function group(Collection $rows): Collection
    {
        $totals = fn (Collection $set) => [
            'records' => $set->count(),
            'gross' => round((float) $set->sum('gross_cane_weight'), 3),
            'net' => round((float) $set->sum('net_cane_wt'), 3),
            'amount' => round((float) $set->sum('tdif_rate'), 2),
            'rate' => $set->pluck('tdif_rate')->map(fn ($r) => (float) $r)->unique()->count() === 1 ? (float) $set->first()->tdif_rate : null,
        ];

        return $rows->groupBy('description')
            ->sortKeys()
            ->map(function (Collection $stationRows, $description) use ($totals) {
                $associations = $stationRows->groupBy(fn ($r) => $r->assn_code . '|' . $r->assn_name)
                    ->sortBy(fn ($set) => (int) $set->first()->assn_code)
                    ->map(fn (Collection $set) => [
                        'code' => $set->first()->assn_code,
                        'name' => $set->first()->assn_name,
                        // Mill printout lists each truck's tickets together
                        'rows' => $set->sortBy([['plate_no', 'asc'], ['id', 'asc']])->values()->map(fn ($r) => [
                            'ticket' => $r->ticket_no,
                            'planter_code' => $r->planter_code,
                            'planter_name' => $r->planter_name,
                            'plate_no' => $r->plate_no,
                            'gross' => (float) $r->gross_cane_weight,
                            'net' => (float) $r->net_cane_wt,
                            'date_milled' => $r->date_milled_label,
                        ]),
                    ] + $totals($set))
                    ->values();

                return [
                    'code' => $this->stationCode($stationRows),
                    'name' => $description,
                    'hauler' => $stationRows->map(fn ($r) => optional(optional($r->liquidation)->hauler)->name)->filter()->first(),
                    'associations' => $associations,
                ] + $totals($stationRows);
            })
            ->values();
    }

    /** The file marks a station's own trips with its code (MASALING = 38); other rows carry 0 */
    private function stationCode(Collection $rows): ?string
    {
        return $rows->pluck('trans_code')
            ->filter(fn ($c) => is_numeric($c) && (int) $c > 0)
            ->countBy(fn ($c) => (string) (int) $c)
            ->sortDesc()
            ->keys()
            ->first();
    }

    /** Week start of the first week to week end of the last week */
    private function period(Request $request): array
    {
        $from = (int) $request->week_from;
        $to = (int) $request->week_to;
        $weeks = WeekNo::where('crop_year', $request->crop_year)
            ->whereRaw('CAST(week_no AS UNSIGNED) BETWEEN ? AND ?', [min($from, $to), max($from, $to)])
            ->get();

        $start = $weeks->min('week_start_date');
        $end = $weeks->max('week_end_date');

        return [
            $start ? Carbon::parse($start)->format('m/d/Y H:i:s') : null,
            $end ? Carbon::parse($end)->format('m/d/Y H:i:s') : null,
        ];
    }
}
