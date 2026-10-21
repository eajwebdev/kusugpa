<?php

namespace App\Services;

use App\Models\CashAdvanceDeduction;
use App\Models\FuelAllowance;
use App\Models\Hauler;
use App\Models\HaulingLiquidation;
use App\Models\PlanterProfile;
use App\Models\TlsHaulingUpload;
use App\Models\WeekNo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns the rows of a weekly TLS hauling upload into Hauling Liquidity entries:
 *  - the TLS description ("CAUAYAN TRANSLOADING") picks the hauler whose name contains the station,
 *    e.g. "ISIO-CAUAYAN HAULER TLS LIQ."; a "<STATION> HAULER TLS LIQ." hauler is created when none matches
 *  - one entry per truck (hauler + plate no.), covering every planter's ticket on it:
 *    tonnage = sum of net cane weight, gross = tonnage x rate per ton
 *  - payee and rate per ton carry over from the plate's last liquidation; the payee's
 *    Cash Adv. and Fuel Account deductions for the week go on their largest slip
 */
class TlsHaulingLiquidationGenerator
{
    // Same default rows as the Add Liquidation form (hauling-liquidity/show.blade.php)
    public const ADD_ROWS = ['Adjustment'];
    public const LESS_ROWS = ['EWT', 'MIS.', 'Fuel Account', 'Cash Adv.', 'Cable Sling/Tire/Battery'];

    /** @var Collection<int, Hauler>|null */
    private ?Collection $haulers = null;

    public static function weekEnding(string $cropYear, string $weekNo): ?string
    {
        $week = WeekNo::where('crop_year', $cropYear)->where('week_no', $weekNo)->first();

        return $week && $week->week_end_date ? Carbon::parse($week->week_end_date)->toDateString() : null;
    }

    /**
     * Deductions owed by a planter for one crop year + week (LESS rows of the slip):
     *  - Cash Adv.     deduction weeks of approved/active cash advances
     *  - Fuel Account  weekly Fuel Allowance upload
     */
    public static function deductionsFor(string $planterCode, string $cropYear, string $weekNo): array
    {
        $caDeductions = CashAdvanceDeduction::with('cashAdvance:id,ca_number')
            ->where('crop_year', $cropYear)
            ->where('week_no', $weekNo)
            ->whereHas('cashAdvance', fn ($q) => $q
                ->where('planter_code', $planterCode)
                ->whereIn('status', ['approved', 'active']))
            ->get();

        $fuel = FuelAllowance::where('planter_code', $planterCode)
            ->where('crop_year', $cropYear)
            ->where('week_no', $weekNo);

        return [
            'cash_advance' => [
                'amount' => round((float) $caDeductions->sum('amount'), 2),
                'sources' => $caDeductions->map(fn ($d) => $d->cashAdvance->ca_number)->unique()->values(),
            ],
            'fuel' => [
                'amount' => round((float) (clone $fuel)->sum('fuel_amount'), 2),
                'rows' => (clone $fuel)->count(),
            ],
        ];
    }

    /**
     * Create one liquidation entry per truck (station + plate no.) for the uploaded rows of a week
     * that are not liquidated yet. Call inside a DB transaction.
     *
     * The TLS file has no payee: a plate gets the payee (truck owner) and rate per ton it was
     * last liquidated with in an earlier week; new plates wait for the payee to be picked on edit.
     *
     * @return array{entries: int, haulers: array<int, string>, unassigned_plates: array<int, string>, suspect_plates: array<int, string>}
     */
    public function generate(string $cropYear, string $weekNo, $userId): array
    {
        $weekEnding = self::weekEnding($cropYear, $weekNo);
        if (!$weekEnding) {
            throw new \RuntimeException("Week {$weekNo} of crop year {$cropYear} has no week end date in Crop & Week Management.");
        }

        $rows = TlsHaulingUpload::where('crop_year', $cropYear)
            ->where('week_no', $weekNo)
            ->whereNull('hauling_liquidation_id')
            ->orderBy('id')
            ->get();

        $result = ['entries' => 0, 'haulers' => [], 'unassigned_plates' => [], 'suspect_plates' => []];
        if ($rows->isEmpty()) {
            return $result;
        }

        $profiles = PlanterProfile::get(['planter_code', 'planter_name'])
            ->keyBy(fn ($p) => self::codeKey($p->planter_code));

        // Last payee and rate each plate was liquidated with in another week (latest wins)
        $previous = HaulingLiquidation::whereNotNull('truck_code')
            ->where(fn ($q) => $q->where('crop_year', '!=', $cropYear)->orWhere('week_no', '!=', $weekNo))
            ->orderBy('id')
            ->get(['truck_code', 'planter_code', 'payee_name', 'rate_per_ton']);
        $lastPayee = $previous->filter(fn ($e) => trim((string) $e->payee_name) !== '')->keyBy(fn ($e) => self::plateKey($e->truck_code));
        $lastRate = $previous->filter(fn ($e) => (float) $e->rate_per_ton > 0)->keyBy(fn ($e) => self::plateKey($e->truck_code));

        // Payees that already have a slip this week keep their deductions where they are
        $deductedPayees = HaulingLiquidation::where('crop_year', $cropYear)
            ->where('week_no', $weekNo)
            ->pluck('planter_code')
            ->filter()
            ->mapWithKeys(fn ($code) => [self::codeKey($code) => true])
            ->all();

        $groups = $rows
            ->groupBy(fn ($r) => $this->stationOf($r->description) . '|' . self::plateKey($r->plate_no))
            ->map(function (Collection $tickets) use ($lastRate) {
                $first = $tickets->first();
                $hauler = $this->haulerFor($this->stationOf($first->description));
                // Rate per ton the plate was paid last time; the TDIF rate until one is set
                $rate = (float) optional($lastRate[self::plateKey($first->plate_no)] ?? null)->rate_per_ton;
                if ($rate <= 0) {
                    $rate = (float) $first->tdif_rate;
                }
                $tonnage = round((float) $tickets->sum('net_cane_wt'), 3);

                return [
                    'rows' => $tickets,
                    'hauler' => $hauler,
                    'plate_no' => $first->plate_no,
                    'tonnage' => $tonnage,
                    'rate' => $rate,
                    'gross' => round($tonnage * $rate, 2),
                ];
            })
            // Biggest slip first, so a payee's deductions land on their largest slip
            ->sortByDesc('gross')
            ->values();

        foreach ($groups as $group) {
            $plateKey = self::plateKey($group['plate_no']);
            $payee = $lastPayee[$plateKey] ?? null;
            $profile = $payee && $payee->planter_code ? ($profiles[self::codeKey($payee->planter_code)] ?? null) : null;
            $lessAmounts = array_fill_keys(self::LESS_ROWS, 0.0);

            // Only payees linked to a planter have cash advances / fuel to deduct
            if ($payee && $payee->planter_code && !isset($deductedPayees[self::codeKey($payee->planter_code)])) {
                $deductions = self::deductionsFor($profile->planter_code ?? $payee->planter_code, $cropYear, $weekNo);
                $lessAmounts['Cash Adv.'] = $deductions['cash_advance']['amount'];
                $lessAmounts['Fuel Account'] = $deductions['fuel']['amount'];
                $deductedPayees[self::codeKey($payee->planter_code)] = true;
            }

            if (!$payee) {
                $result['unassigned_plates'][$plateKey] = $group['plate_no'];
            }
            // Excel turns plate numbers like "JAN38" into dates ("Jan-38"); flag them for checking
            if (preg_match('/^(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)-\d{2}$/', (string) $group['plate_no'])) {
                $result['suspect_plates'][$plateKey] = $group['plate_no'];
            }

            $totalLess = round(array_sum($lessAmounts), 2);

            $entry = HaulingLiquidation::create([
                'hauler_id' => $group['hauler']->id,
                'crop_year' => $cropYear,
                'week_no' => $weekNo,
                'week_ending' => $weekEnding,
                'planter_code' => $payee && $payee->planter_code ? ($profile->planter_code ?? $payee->planter_code) : null,
                'payee_name' => $payee ? $payee->payee_name : '',
                'truck_code' => $group['plate_no'],
                'tonnage' => $group['tonnage'],
                'rate_per_ton' => $group['rate'],
                'gross_amount' => $group['gross'],
                'total_add' => 0,
                'total_less' => $totalLess,
                'net_amount' => round($group['gross'] - $totalLess, 2),
                'created_by' => is_numeric($userId) ? (int) $userId : null,
            ]);

            $sort = 0;
            foreach (self::ADD_ROWS as $description) {
                $entry->items()->create(['type' => 'add', 'description' => $description, 'amount' => 0, 'sort_order' => $sort++]);
            }
            foreach ($lessAmounts as $description => $amount) {
                $entry->items()->create(['type' => 'less', 'description' => $description, 'amount' => $amount, 'sort_order' => $sort++]);
            }

            TlsHaulingUpload::whereIn('id', $group['rows']->pluck('id'))->update(['hauling_liquidation_id' => $entry->id]);

            $result['entries']++;
            $result['haulers'][$group['hauler']->id] = $group['hauler']->name;
        }

        $result['haulers'] = array_values($result['haulers']);
        $result['unassigned_plates'] = array_values($result['unassigned_plates']);
        $result['suspect_plates'] = array_values($result['suspect_plates']);

        return $result;
    }

    /**
     * Rebuild a week's auto-created entries, e.g. after the grouping rules change.
     * Entries already worked on (edited, or given a CV/check no. or release date) are kept as they are.
     * Call inside a DB transaction.
     */
    public function regenerate(string $cropYear, string $weekNo, $userId): array
    {
        $generatedIds = TlsHaulingUpload::where('crop_year', $cropYear)
            ->where('week_no', $weekNo)
            ->whereNotNull('hauling_liquidation_id')
            ->distinct()
            ->pluck('hauling_liquidation_id');

        $untouched = HaulingLiquidation::whereIn('id', $generatedIds)
            ->whereNull('cv_no')->whereNull('check_no')->whereNull('date_released')
            ->whereColumn('updated_at', 'created_at')
            ->pluck('id');

        TlsHaulingUpload::whereIn('hauling_liquidation_id', $untouched)->update(['hauling_liquidation_id' => null]);
        HaulingLiquidation::whereIn('id', $untouched)->delete();

        return ['removed' => $untouched->count(), 'kept' => $generatedIds->count() - $untouched->count()]
            + $this->generate($cropYear, $weekNo, $userId);
    }

    /**
     * "CAUAYAN TRANSLOADING" -> "CAUAYAN"
     */
    public function stationOf(?string $description): string
    {
        $station = strtoupper(preg_replace('/\s+/', ' ', trim((string) $description)));
        $station = trim(preg_replace('/\b(TRANSLOADING( STATION)?|TLS)\b/', '', $station));

        return $station !== '' ? $station : 'UNASSIGNED';
    }

    /** "rjg480 " -> "RJG480" so the same truck always groups together */
    private static function plateKey($plate): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $plate)));
    }

    private static function codeKey($code): string
    {
        $code = trim((string) $code);
        $stripped = ltrim($code, '0');

        return $stripped === '' ? $code : $stripped;
    }

    private function haulerFor(string $station): Hauler
    {
        $this->haulers ??= Hauler::orderBy('id')->get();

        $pattern = '/\b' . preg_quote($station, '/') . '\b/';
        $hauler = $this->haulers->first(fn (Hauler $h) => preg_match($pattern, strtoupper($h->name)));

        if (!$hauler) {
            $hauler = Hauler::create([
                'name' => $station . ' HAULER TLS LIQ.',
                'description' => 'Created from the weekly TLS hauling upload',
                'is_active' => true,
            ]);
            $this->haulers->push($hauler);
        }

        return $hauler;
    }
}
