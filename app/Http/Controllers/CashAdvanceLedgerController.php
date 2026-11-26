<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CashAdvance;
use App\Services\CashAdvanceLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Cash Advance Ledger: the cash advances released, per planter, with payments and balances,
 * one line per planter with their advances under it; a planter's earlier advances on click; printable (all or the ones ticked).
 */
class CashAdvanceLedgerController extends Controller
{
    public function index()
    {
        $cropYears = CashAdvance::whereNotNull('crop_year')->distinct()->orderByDesc('crop_year')->pluck('crop_year')->map(fn ($c) => (string) $c)->values();

        return view('cash-advances.ledger', ['cropYears' => $cropYears]);
    }

    public function data(Request $request, CashAdvanceLedger $ledger)
    {
        return response()->json($ledger->build($this->filters($request)));
    }

    public function planter(Request $request, CashAdvanceLedger $ledger)
    {
        $data = $request->validate(['planter_code' => 'required|string|max:50']);

        return response()->json($ledger->planterHistory($data['planter_code']));
    }

    public function pdf(Request $request, CashAdvanceLedger $ledger)
    {
        $filters = $this->filters($request);
        $result = $ledger->build($filters);

        AuditLog::log('print', 'cash_advances', 'Printed the cash advance ledger', [
            'crop_year' => $filters['crop_year'] ?: 'all',
            'dates' => ($filters['date_from'] ?: '…') . ' to ' . ($filters['date_to'] ?: '…'),
            'advances' => $result['totals']['count'],
            'balance' => $result['totals']['balance'],
            'planters' => $filters['planters'] ? count($filters['planters']) . ' selected' : 'all',
        ]);

        $pdf = Pdf::loadView('pdf.ca-ledger', $result + [
            'org' => config('kusugpa'),
            'printedBy' => trim(($request->user()->fname ?? '') . ' ' . ($request->user()->lname ?? '')),
        ])->setPaper('legal', 'landscape');

        $filename = 'cash-advance-ledger-' . ($filters['crop_year'] ?: 'all') . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'crop_year' => 'nullable|string|max:20',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            // Only these planters (the ones ticked to print)
            'planters' => 'nullable|array',
            'planters.*' => 'string|max:50',
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:released,all,pending,approved,active,completed,rejected',
            'created_by' => 'nullable|integer',
        ]) + ['crop_year' => null, 'date_from' => null, 'date_to' => null, 'search' => null, 'status' => 'released', 'created_by' => null, 'planters' => []];
    }
}
