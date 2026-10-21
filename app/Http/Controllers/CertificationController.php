<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\AuditLog;
use App\Models\ConsolidatedUpload;
use App\Models\CropYear;
use App\Models\Molass;
use App\Models\PlanterProfile;
use App\Models\Quedan;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Production Certification — certifies a planter's crop-year production.
 *
 * Figures come from the weekly mill uploads:
 *   Net Tons        = SUM(consolidated_uploads.ta_wt)   (TA Wt on the Consolidated Report)
 *   Gross Sugar LKG = SUM(quedans.sugar_lkg)
 *   Net Sugar LKG   = SUM(quedans.sugar_lkg - quedans.labor_lkg)   (same net LKG the voucher uses)
 *   Net Molasses MT = SUM(molasses.mol_net)
 */
class CertificationController extends Controller
{
    private const SIGNATORY_DEFAULTS = [
        'certification_mill_name'      => 'URC-SONEDCO',
        'certification_prepared_name'  => 'SHELAMAE G. NONATURA',
        'certification_prepared_title' => 'DISBURSING CLERK',
        'certification_approved_name'  => 'JOYA M. MAGBANUA',
        'certification_approved_title' => 'MANAGER',
    ];

    public function index()
    {
        AuditLog::log('view', 'reports', 'Viewed production certification page');

        $cropYears = CropYear::orderBy('crop_year', 'desc')->pluck('crop_year');
        $signatories = $this->signatories();

        return view('reports.certification', compact('cropYears', 'signatories'));
    }

    /**
     * Planters with production in the crop year (or all crop years), with their totals
     */
    public function planters(Request $request)
    {
        $rows = $this->production($request->get('crop_year'));

        return response()->json(['data' => $rows->values()]);
    }

    public function saveSignatories(Request $request)
    {
        $validated = $request->validate([
            'mill_name'      => 'required|string|max:100',
            'prepared_name'  => 'required|string|max:100',
            'prepared_title' => 'required|string|max:100',
            'approved_name'  => 'required|string|max:100',
            'approved_title' => 'required|string|max:100',
        ]);

        foreach ($validated as $field => $value) {
            SystemSetting::set('certification_' . $field, trim($value));
        }

        AuditLog::log('update', 'reports', 'Updated production certification signatories', $validated);

        return response()->json(['message' => 'Signatories saved']);
    }

    public function pdf(Request $request)
    {
        $request->validate([
            'planters'    => 'required_unless:all,1|nullable|string',
            'issued_date' => 'nullable|date',
        ]);

        $cropYear = $request->get('crop_year');
        // Comma-separated codes keep the preview URL short when many planters are selected
        $selected = collect(explode(',', (string) $request->get('planters')))
            ->filter(fn ($code) => trim($code) !== '')
            ->map(fn ($code) => $this->codeKey($code))
            ->unique();
        $all = $request->boolean('all');

        // One certificate per planter; each lists a row per crop year
        $certificates = $this->production($cropYear)
            ->filter(fn ($row) => $all || $selected->contains($row['code_key']))
            ->groupBy('code_key')
            ->map(fn ($rows) => [
                'planter_name' => $rows->first()['planter_name'],
                'rows' => $rows->sortBy('crop_year')->values(),
            ])
            ->sortBy('planter_name')
            ->values();

        $issuedDate = $request->filled('issued_date') ? Carbon::parse($request->issued_date) : now();
        $signatories = $this->signatories();
        foreach (['mill_name', 'prepared_name', 'prepared_title', 'approved_name', 'approved_title'] as $field) {
            if ($request->filled($field)) {
                $signatories[$field] = trim($request->get($field));
            }
        }

        AuditLog::log('print', 'reports', 'Generated production certification PDF', [
            'crop_year' => $cropYear ?: 'all',
            'planters' => $certificates->count(),
            'planter_codes' => $all ? 'all' : $selected->values()->take(50)->all(),
        ]);

        $pdf = Pdf::loadView('pdf.certification', [
            'certificates' => $certificates,
            'issuedDay' => strtoupper($issuedDate->format('jS')),
            'issuedMonthYear' => strtoupper($issuedDate->format('F, Y')),
            'signatories' => $signatories,
            'logo' => base64_encode(file_get_contents(public_path('img/logo-512.png'))),
        ])->setPaper('a4', 'portrait');

        $filename = 'production-certification-' . ($cropYear ?: 'all') . '.pdf';

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /**
     * Production totals per planter per crop year, merged across summary, quedans and molasses.
     * Planter codes are matched without leading zeros because the summary upload stores them as integers.
     */
    private function production(?string $cropYear)
    {
        $scope = fn ($query) => $cropYear ? $query->where('crop_year', $cropYear) : $query;

        $cane = $scope(ConsolidatedUpload::query())
            ->select('crop_year', 'planter_code', DB::raw('MAX(planter_name) as planter_name'),
                DB::raw('SUM(CAST(ta_wt AS DECIMAL(14,3))) as net_tons'))
            ->groupBy('crop_year', 'planter_code')
            ->get();

        $sugar = $scope(Quedan::query())
            ->select('crop_year', 'planter_code', DB::raw('MAX(planter_name) as planter_name'),
                DB::raw('SUM(sugar_lkg) as gross_lkg'),
                DB::raw('SUM(sugar_lkg - labor_lkg) as net_lkg'))
            ->groupBy('crop_year', 'planter_code')
            ->get();

        $molasses = $scope(Molass::query())
            ->select('crop_year', 'planter_code', DB::raw('MAX(planter_name) as planter_name'),
                DB::raw('SUM(mol_net) as net_mol'))
            ->groupBy('crop_year', 'planter_code')
            ->get();

        $rows = [];
        $add = function ($record, array $values) use (&$rows) {
            $key = $this->codeKey($record->planter_code);
            $rowKey = $record->crop_year . '|' . $key;

            $rows[$rowKey] ??= [
                'crop_year' => (string) $record->crop_year,
                'crop_year_label' => $this->cropYearLabel($record->crop_year),
                'planter_code' => trim((string) $record->planter_code),
                'code_key' => $key,
                'planter_name' => trim((string) $record->planter_name),
                'net_tons' => 0.0,
                'gross_lkg' => 0.0,
                'net_lkg' => 0.0,
                'net_mol' => 0.0,
            ];

            foreach ($values as $field => $value) {
                $rows[$rowKey][$field] += (float) $value;
            }
        };

        foreach ($cane as $r) $add($r, ['net_tons' => $r->net_tons]);
        foreach ($sugar as $r) $add($r, ['gross_lkg' => $r->gross_lkg, 'net_lkg' => $r->net_lkg]);
        foreach ($molasses as $r) $add($r, ['net_mol' => $r->net_mol]);

        // Prefer the registered planter profile name when one exists
        $profileNames = PlanterProfile::pluck('planter_name', 'planter_code')
            ->mapWithKeys(fn ($name, $code) => [$this->codeKey($code) => trim($name)]);

        return collect($rows)
            ->map(function ($row) use ($profileNames) {
                $row['planter_name'] = $profileNames[$row['code_key']] ?? $row['planter_name'];
                return $row;
            })
            ->sortBy([['planter_name', 'asc'], ['crop_year', 'asc']])
            ->values();
    }

    private function codeKey($code): string
    {
        $code = trim((string) $code);
        $stripped = ltrim($code, '0');

        return $stripped === '' ? $code : $stripped;
    }

    /** 20252026 → 2025-2026 */
    private function cropYearLabel($cropYear): string
    {
        $digits = preg_replace('/\D/', '', (string) $cropYear);

        return strlen($digits) === 8 ? substr($digits, 0, 4) . '-' . substr($digits, 4) : (string) $cropYear;
    }

    private function signatories(): array
    {
        $values = [];
        foreach (self::SIGNATORY_DEFAULTS as $key => $default) {
            $values[str_replace('certification_', '', $key)] = SystemSetting::get($key, $default) ?: $default;
        }

        return $values;
    }
}
