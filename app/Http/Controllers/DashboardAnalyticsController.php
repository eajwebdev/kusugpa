<?php

namespace App\Http\Controllers;

use App\Models\ConsolidatedUpload;
use App\Models\CropYear;
use App\Services\DashboardAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Data of the dashboard tabs, loaded when a tab is opened (and again when the crop year / week changes)
 */
class DashboardAnalyticsController extends Controller
{
    public function tab(Request $request, string $tab)
    {
        $data = $request->validate([
            'crop_year' => 'nullable|string|max:20',
            'week_no' => 'nullable|integer|min:1|max:99',
        ]);

        $access = DashboardAnalytics::accessFor($request->user());
        $allowed = [
            'modules' => true,
            'production' => true,
            'balance' => true,
            'finance' => $access['finance'],
            'collections' => $access['finance'],
            // Money trail: released → recovered on vouchers / in cash → still to collect
            'trail' => $access['finance'],
            'tls' => true,
            'proceeds' => $access['proceeds'],
            'hauling' => $access['hauling'],
        ];
        if (empty($allowed[$tab])) {
            return response()->json(['message' => 'You do not have permission to view this tab.'], 403);
        }

        $cropYear = $data['crop_year'] ?? null;
        if (!$cropYear) {
            $cropYear = ConsolidatedUpload::orderBy('crop_year', 'desc')->value('crop_year')
                ?? CropYear::orderBy('crop_year', 'desc')->value('crop_year');
        }
        if (!$cropYear) {
            return response()->json(['message' => 'No crop year is set up yet.', 'empty' => true]);
        }

        $analytics = new DashboardAnalytics($cropYear, $data['week_no'] ?? null, $access);

        try {
            return response()->json($analytics->{$tab}());
        } catch (\Throwable $e) {
            Log::error("Dashboard tab {$tab} failed: " . $e->getMessage(), ['crop_year' => $cropYear, 'week_no' => $data['week_no'] ?? null]);
            return response()->json(['message' => 'Could not load this tab. Please try again.'], 500);
        }
    }

    /**
     * TLS station comparison report from the Hauling tab: PDF to print or CSV for Excel
     */
    public function stationsReport(Request $request)
    {
        $data = $request->validate([
            'crop_year' => 'nullable|string|max:20',
            'period' => 'nullable|in:week,month,year',
            'pick' => 'nullable|string|max:20',
            'format' => 'nullable|in:pdf,csv',
        ]);
        $access = DashboardAnalytics::accessFor($request->user());
        abort_unless($access['hauling'], 403, 'You do not have permission to view hauling.');

        $cropYear = $data['crop_year'] ?? null
            ?: ConsolidatedUpload::orderBy('crop_year', 'desc')->value('crop_year')
            ?? CropYear::orderBy('crop_year', 'desc')->value('crop_year');
        abort_unless($cropYear, 404, 'No crop year is set up yet.');

        $report = (new DashboardAnalytics($cropYear, null, $access))->stationReport($data['period'] ?? 'week', $data['pick'] ?? null);
        $name = 'tls-station-performance-' . $cropYear . '-' . $report['period'] . '-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $report['pick']);

        if (($data['format'] ?? 'pdf') === 'csv') {
            return response()->streamDownload(function () use ($report) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF"); // UTF-8 for Excel
                fputcsv($out, ['TLS Station Performance', 'Crop Year ' . $report['cropYear'], $report['periodWord'] . ': ' . $report['pickLabel']]);
                fputcsv($out, []);
                fputcsv($out, ['Rank', 'TLS Station', 'Trans Code', 'Net Cane Wt (t)', 'Share %', 'Gross Cane Wt (t)', 'Loss (t)', 'Loss %', 'Tickets', 'Avg Net / Ticket (t)',
                    'Net ' . ($report['prevLabel'] ?? 'previous') . ' (t)', 'Change %']);
                foreach ($report['rows'] as $r) {
                    fputcsv($out, [$r['rank'], $r['name'], $r['code'], $r['net'], $r['share'], $r['gross'], $r['loss'], $r['lossPct'], $r['tickets'], $r['avgNet'], $r['prevNet'], $r['change']]);
                }
                fputcsv($out, ['', 'TOTAL', '', $report['totals']['net'], 100, $report['totals']['gross'], round($report['totals']['gross'] - $report['totals']['net'], 3), '', $report['totals']['tickets'], '', $report['totals']['prevNet'], '']);
                fputcsv($out, []);
                fputcsv($out, ['Net Cane Wt (t) per ' . strtolower($report['periodWord'])]);
                fputcsv($out, array_merge(['TLS Station', 'Trans Code'], $report['matrix']['labels'], ['Total']));
                foreach ($report['matrix']['rows'] as $m) {
                    fputcsv($out, array_merge([$m['name'], $m['code']], $m['values'], [round(array_sum($m['values']), 3)]));
                }
                $cols = array_map(null, ...array_column($report['matrix']['rows'], 'values'));
                $sums = array_map(fn ($c) => round(array_sum((array) $c), 3), $cols);
                fputcsv($out, array_merge(['TOTAL', ''], $sums, [round(array_sum($sums), 3)]));
                if ($report['findings']) {
                    fputcsv($out, []);
                    fputcsv($out, ['Findings']);
                    foreach ($report['findings'] as $f) {
                        fputcsv($out, [$f]);
                    }
                }
                fclose($out);
            }, $name . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.tls-station-performance', $report + [
            'org' => config('kusugpa'),
            'printedBy' => trim(($request->user()->fname ?? '') . ' ' . ($request->user()->lname ?? '')),
        ])->setPaper('letter', count($report['matrix']['keys']) > 8 ? 'landscape' : 'portrait');

        return $pdf->stream($name . '.pdf');
    }
}
