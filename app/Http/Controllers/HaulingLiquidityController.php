<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Hauler;
use App\Models\HaulingLiquidation;
use App\Models\CropYear;
use App\Models\PlanterProfile;
use App\Models\WeekNo;
use App\Services\TlsHaulingLiquidationGenerator;
use App\Models\User;
use App\Models\AuditLog;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class HaulingLiquidityController extends Controller
{
    private function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    private function canView(): bool
    {
        if (in_array($this->user()->role, ['Administrator', 'super_admin', 'manager'])) {
            return true;
        }
        return $this->user()->hasAnyPermission(['view-hauling-liquidity', 'manage-hauling-liquidity']);
    }

    private function canManage(): bool
    {
        if (in_array($this->user()->role, ['Administrator', 'super_admin', 'manager'])) {
            return true;
        }
        return $this->user()->hasPermission('manage-hauling-liquidity');
    }

    private function authorizeView(): void
    {
        abort_unless($this->canView(), 403, 'Unauthorized');
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403, 'Unauthorized');
    }

    // ==================== HAULERS ====================

    /**
     * Display hauler list
     */
    public function index()
    {
        $this->authorizeView();

        $haulers = Hauler::withCount('liquidations')
            ->withSum('liquidations as total_tonnage', 'tonnage')
            ->withSum('liquidations as total_net', 'net_amount')
            ->orderBy('name')
            ->get();

        $stats = [
            'total' => $haulers->count(),
            'active' => $haulers->where('is_active', true)->count(),
            'entries' => $haulers->sum('liquidations_count'),
            'total_net' => $haulers->sum('total_net'),
        ];

        $canManage = $this->canManage();

        return view('hauling-liquidity.index', compact('haulers', 'stats', 'canManage'));
    }

    /**
     * Store new hauler
     */
    public function storeHauler(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:haulers,name',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $hauler = Hauler::create($data);

        AuditLog::log('create', 'hauling_liquidity', 'Created hauler: ' . $hauler->name);

        return response()->json(['message' => 'Hauler added', 'hauler' => $hauler]);
    }

    /**
     * Update hauler
     */
    public function updateHauler(Request $request, Hauler $hauler)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:haulers,name,' . $hauler->id,
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $hauler->update($data);

        AuditLog::log('update', 'hauling_liquidity', 'Updated hauler: ' . $hauler->name);

        return response()->json(['message' => 'Hauler updated', 'hauler' => $hauler]);
    }

    /**
     * Toggle hauler active status
     */
    public function toggleHauler(Hauler $hauler)
    {
        $this->authorizeManage();

        $hauler->update(['is_active' => !$hauler->is_active]);

        AuditLog::log('update', 'hauling_liquidity', 'Changed hauler status: ' . $hauler->name . ' to ' . ($hauler->is_active ? 'active' : 'inactive'));

        return response()->json(['message' => 'Status updated', 'is_active' => $hauler->is_active]);
    }

    /**
     * Delete hauler (only when it has no liquidation entries)
     */
    public function destroyHauler(Hauler $hauler)
    {
        $this->authorizeManage();

        if ($hauler->liquidations()->exists()) {
            return response()->json([
                'message' => 'This hauler has liquidation entries. Delete the entries first or set the hauler to inactive.',
            ], 422);
        }

        $name = $hauler->name;
        $hauler->delete();

        AuditLog::log('delete', 'hauling_liquidity', 'Deleted hauler: ' . $name);

        return response()->json(['message' => 'Hauler deleted']);
    }

    // ==================== LIQUIDATION ENTRIES ====================

    /**
     * Display liquidation entries of a hauler
     */
    public function show(Hauler $hauler)
    {
        $this->authorizeView();

        $entries = $hauler->liquidations()
            ->with('items')
            ->orderBy('crop_year', 'desc')
            ->orderByRaw('CAST(week_no AS UNSIGNED) DESC')
            ->orderBy('id', 'desc')
            ->get();

        // Crop years and weeks come from Crop & Week Management (/crop-weeks)
        $cropYears = CropYear::orderBy('crop_year', 'desc')->pluck('crop_year');
        $weeks = WeekNo::orderBy('crop_year', 'desc')
            ->orderByRaw('CAST(week_no AS UNSIGNED) ASC')
            ->get()
            ->map(fn ($w) => [
                'crop_year' => $w->crop_year,
                'week_no' => (string) $w->week_no,
                'week_start_date' => $w->week_start_date ? Carbon::parse($w->week_start_date)->toDateString() : null,
                'week_end_date' => $w->week_end_date ? Carbon::parse($w->week_end_date)->toDateString() : null,
            ]);

        // Payees are typed (hauler / driver / truck owner); a name matching a planter links it for Cash Adv. and Fuel deductions
        $planters = PlanterProfile::orderBy('planter_name')->get(['planter_code', 'planter_name', 'status']);
        $payeeNames = HaulingLiquidation::where('payee_name', '!=', '')
            ->distinct()
            ->orderBy('payee_name')
            ->pluck('payee_name');
        $truckCodes = $entries->pluck('truck_code')->filter()->unique()->sort()->values();
        $canManage = $this->canManage();

        return view('hauling-liquidity.show', compact(
            'hauler', 'entries', 'cropYears', 'weeks', 'planters', 'payeeNames', 'truckCodes', 'canManage'
        ));
    }

    /**
     * Store new liquidation entry
     */
    public function storeEntry(Request $request, Hauler $hauler)
    {
        $this->authorizeManage();

        $entry = DB::transaction(function () use ($request, $hauler) {
            $entry = new HaulingLiquidation([
                'hauler_id' => $hauler->id,
                'created_by' => $this->user()->id,
            ]);
            return $this->saveEntry($request, $entry);
        });

        AuditLog::log('create', 'hauling_liquidity', 'Created hauling liquidation for ' . $entry->payee_name, [
            'hauler' => $hauler->name,
            'crop_year' => $entry->crop_year,
            'week_no' => $entry->week_no,
            'net_amount' => $entry->net_amount,
        ]);

        return response()->json(['message' => 'Liquidation saved', 'entry' => $entry->load('items')]);
    }

    /**
     * Update liquidation entry
     */
    public function updateEntry(Request $request, HaulingLiquidation $entry)
    {
        $this->authorizeManage();

        $entry = DB::transaction(fn () => $this->saveEntry($request, $entry));

        AuditLog::log('update', 'hauling_liquidity', 'Updated hauling liquidation for ' . $entry->payee_name, [
            'hauler' => $entry->hauler->name,
            'crop_year' => $entry->crop_year,
            'week_no' => $entry->week_no,
            'net_amount' => $entry->net_amount,
        ]);

        return response()->json(['message' => 'Liquidation updated', 'entry' => $entry->load('items')]);
    }

    /**
     * Delete liquidation entry
     */
    public function destroyEntry(HaulingLiquidation $entry)
    {
        $this->authorizeManage();

        $payee = $entry->payee_name;
        $hauler = $entry->hauler->name;
        $entry->delete();

        AuditLog::log('delete', 'hauling_liquidity', 'Deleted hauling liquidation for ' . $payee, ['hauler' => $hauler]);

        return response()->json(['message' => 'Liquidation deleted']);
    }

    /**
     * Deductions owed by a planter for one crop year + week, used to fill the LESS rows:
     *  - Cash Adv.     from the deduction weeks of approved/active cash advances (Cash Advances > All Cash Advances > receipt details)
     *  - Fuel Account  from the weekly Fuel Allowance upload
     */
    public function autoDeductions(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'planter_code' => 'required|string',
            'crop_year' => 'required|string',
            'week_no' => 'required|string',
        ]);

        // Same lookup the TLS upload uses when it generates entries
        return response()->json(
            TlsHaulingLiquidationGenerator::deductionsFor($data['planter_code'], $data['crop_year'], $data['week_no'])
        );
    }

    // ==================== PDF SLIPS ====================

    /**
     * Liquidation slip of a single entry (short bond, portrait)
     */
    public function slipPDF(Request $request, HaulingLiquidation $entry)
    {
        $this->authorizeView();

        $entry->load('hauler', 'items', 'tlsTickets');

        return $this->renderSlips($request, collect([$entry]), 'hauling-liquidation-' . $entry->id . '.pdf');
    }

    /**
     * All liquidation slips of a hauler for one crop year and week, one slip per page
     */
    public function weekSlipsPDF(Request $request, Hauler $hauler)
    {
        $this->authorizeView();

        $data = $request->validate([
            'crop_year' => 'required|string',
            'week_no' => 'required|string',
        ]);

        $entries = $hauler->liquidations()
            ->with('hauler', 'items', 'tlsTickets')
            ->where('crop_year', $data['crop_year'])
            ->where('week_no', $data['week_no'])
            ->orderBy('payee_name')
            ->get();

        abort_if($entries->isEmpty(), 404, 'No liquidation entries for this week.');

        return $this->renderSlips($request, $entries, 'hauling-liquidation-' . $data['crop_year'] . '-wk' . $data['week_no'] . '.pdf');
    }

    private function renderSlips(Request $request, $entries, string $filename)
    {
        $pdf = Pdf::loadView('pdf.hauling-liquidation', ['entries' => $entries])
            ->setPaper('letter', 'portrait'); // short bond: 8.5" x 11"

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /**
     * Validate the request, compute totals server-side, and save the entry with its ADD/LESS rows.
     */
    private function saveEntry(Request $request, HaulingLiquidation $entry): HaulingLiquidation
    {
        $data = $request->validate([
            'crop_year' => 'required|string|exists:crop_year,crop_year',
            'week_no' => 'required|string',
            'date_released' => 'nullable|date',
            'payee_name' => 'required|string|max:255',
            'cv_no' => 'nullable|string|max:50',
            'check_no' => 'nullable|string|max:50',
            'truck_code' => 'nullable|string|max:50',
            'tonnage' => 'required|numeric|min:0',
            'rate_per_ton' => 'required|numeric|min:0',
            'add_items' => 'array',
            'add_items.*.description' => 'required|string|max:255',
            'add_items.*.amount' => 'required|numeric',
            'less_items' => 'array',
            'less_items.*.description' => 'required|string|max:255',
            'less_items.*.amount' => 'required|numeric',
        ], [
            'payee_name.required' => 'Enter the payee name.',
        ]);

        $week = WeekNo::where('crop_year', $data['crop_year'])
            ->where('week_no', $data['week_no'])
            ->first();

        if (!$week) {
            throw ValidationException::withMessages([
                'week_no' => 'Week ' . $data['week_no'] . ' is not set up for crop year ' . $data['crop_year'] . ' in Crop & Week Management.',
            ]);
        }

        // Link to a planter only when the typed name matches one, so their Cash Adv. / Fuel still apply
        $payeeName = trim(preg_replace('/\s+/', ' ', $data['payee_name']));
        $planter = PlanterProfile::whereRaw('LOWER(TRIM(planter_name)) = ?', [mb_strtolower($payeeName)])->first();

        $addItems = collect($data['add_items'] ?? []);
        $lessItems = collect($data['less_items'] ?? []);

        $gross = round($data['tonnage'] * $data['rate_per_ton'], 2);
        $totalAdd = round($addItems->sum('amount'), 2);
        $totalLess = round($lessItems->sum('amount'), 2);

        $entry->fill([
            'crop_year' => $data['crop_year'],
            'week_no' => $data['week_no'],
            'week_ending' => Carbon::parse($week->week_end_date)->toDateString(),
            'date_released' => $data['date_released'] ?? null,
            'planter_code' => $planter?->planter_code,
            'payee_name' => $payeeName, // name as printed on the slip
            'cv_no' => $data['cv_no'] ?? null,
            'check_no' => $data['check_no'] ?? null,
            'truck_code' => $data['truck_code'] ?? null,
            'tonnage' => $data['tonnage'],
            'rate_per_ton' => $data['rate_per_ton'],
            'gross_amount' => $gross,
            'total_add' => $totalAdd,
            'total_less' => $totalLess,
            'net_amount' => round($gross + $totalAdd - $totalLess, 2),
        ]);
        $entry->save();

        // Replace ADD/LESS rows with the submitted ones
        $entry->items()->delete();
        $sort = 0;
        foreach (['add' => $addItems, 'less' => $lessItems] as $type => $items) {
            foreach ($items as $item) {
                $entry->items()->create([
                    'type' => $type,
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                    'sort_order' => $sort++,
                ]);
            }
        }

        return $entry;
    }
}
