<?php

namespace App\Services;

use App\Models\CashAdvanceAmortization;
use App\Models\CashAdvanceDeduction;
use App\Models\ConsolidatedUpload;
use App\Models\LoanAmortization;
use App\Models\LoanSetting;
use App\Models\Molass;
use App\Models\MolassesPrice;
use App\Models\PlanterDeductionWeek;
use App\Models\Quedan;
use App\Models\QuedanPrice;
use App\Models\TruckingAllowance;
use App\Models\WeekNo;
use Illuminate\Support\Collection;

/**
 * Planter proceeds for a crop year and week range (quedan A/B/D, molasses, trucking allowance),
 * shared by the Voucher Ledger and the Proceeds of Quedan, Molasses & Trucking Allowance sheet
 */
class PlanterProceeds
{
    /**
     * One record per planter with the uploaded quantities, prices and the cash advance / loan payments.
     * With $boughtWeek, only quedans and molasses bought in that week count (the "Week Bought" rule of
     * the Registry / Buy pages) and only planters with such a quedan or molasses are listed.
     */
    public static function summary($cropYear, $weekFrom, $weekTo, $planterNames = null, $boughtWeek = null, ?bool $includeMolasses = null, bool $perQuedan = false): Collection
    {
        // $includeMolasses: null = molasses follow the Week Bought rule like quedans (default);
        // true = all bought molasses of the weeks, regardless of Week Bought; false = no molasses
        $from = min((int) $weekFrom, (int) $weekTo);
        $to = max((int) $weekFrom, (int) $weekTo);
        // An IN list keeps MySQL on the (crop_year, week_no) indexes. Casting the
        // stored column forced a full scan and made whole-season/all-planter PDFs
        // exceed production web-server timeouts. Include legacy zero-padded weeks.
        $weekValues = collect(range($from, $to))
            ->flatMap(fn ($week) => [(string) $week, str_pad((string) $week, 2, '0', STR_PAD_LEFT)])
            // Strict: a loose unique() treats "3" and "03" as equal and drops the padded spelling
            ->uniqueStrict()
            ->values()
            ->all();

        $weekEndDates = WeekNo::where('crop_year', $cropYear)
            ->whereIn('week_no', $weekValues)
            ->pluck('week_end_date', 'week_no');

        // Pending quedans and molasses are not yet bought, so they are left off the voucher
        $quedanQuery = Quedan::notPending()
            ->where('quedans.crop_year', $cropYear)
            ->whereIn('quedans.week_no', $weekValues);

        if (!empty($planterNames)) {
            $quedanQuery->whereIn('quedans.planter_code', (array) $planterNames);
        }
        $byBoughtWeek = $boughtWeek !== null && $boughtWeek !== '';
        if ($byBoughtWeek) {
            $quedanQuery->boughtInWeek($cropYear, $boughtWeek);
        }

        $quedans = $quedanQuery->orderBy('quedans.id')->get();
        // $perQuedan: one record (voucher) per quedan. The planter's molasses and trucking allowance of the
        // weeks go on its first quedan's record; molasses without a quedan make their own record.
        $firstKey = [];

        // The bought week's price each quedan was bought at (Price Management), as it stands now
        $boughtPrices = QuedanPrice::whereIn('id', $quedans->pluck('quedan_price_id')->filter()->unique()->values())
            ->get(['id', 'quedan_type', 'quedan_price'])
            ->keyBy('id');

        // Types are matched regardless of case ("b" = "B"); the latest price entered wins
        $quedanPrices = QuedanPrice::where('crop_year', $cropYear)
            ->whereIn('week_no', $weekValues)
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn ($p) => strtoupper(trim((string) $p->quedan_type)));

        $molassesQuery = Molass::notPending()
            ->where('molasses.crop_year', $cropYear)
            ->whereIn('molasses.week_no', $weekValues);

        if (!empty($planterNames)) {
            $molassesQuery->whereIn('molasses.planter_code', (array) $planterNames);
        }
        if ($includeMolasses === false) {
            $molassesQuery->whereRaw('1 = 0');
        } elseif ($byBoughtWeek && $includeMolasses === null) {
            $molassesQuery->boughtInWeek($cropYear, $boughtWeek);
        }

        // One molasses price per week, the latest entered. A join on mol_price would repeat each
        // molasses record once per price row of its week and count its quantity twice.
        $molPrices = MolassesPrice::where('crop_year', $cropYear)
            ->whereIn('week_no', $weekValues)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($p) => [(int) $p->week_no => $p->mol_price]);

        $molasses = $molassesQuery->get();
        // Like quedans: the bought week's price picked on Buy Molasses (as it stands now in Price Management),
        // else the sold price recorded, else (bought before sold prices were recorded) its week's price
        $boughtMolPrices = MolassesPrice::whereIn('id', $molasses->pluck('molasses_price_id')->filter()->unique()->values())
            ->pluck('mol_price', 'id');
        $molasses->each(function ($mol) use ($molPrices, $boughtMolPrices) {
            $mol->mol_price = ($mol->molasses_price_id && isset($boughtMolPrices[$mol->molasses_price_id]))
                ? $boughtMolPrices[$mol->molasses_price_id]
                : ($mol->sold_price !== null ? $mol->sold_price : ($molPrices[(int) $mol->week_no] ?? null));
        });

        $consolidatedQuery = ConsolidatedUpload::where('crop_year', $cropYear)
            ->whereIn('week_no', $weekValues);

        if (!empty($planterNames)) {
            $consolidatedQuery->whereIn('planter_code', (array) $planterNames);
        }

        $consolidatedUploads = $consolidatedQuery->get();

        $truckingQuery = TruckingAllowance::where('crop_year', $cropYear)
            ->whereIn('week_no', $weekValues);

        if (!empty($planterNames)) {
            $truckingQuery->whereIn('planter_code', (array) $planterNames);
        }

        $truckingAllowances = $truckingQuery->get();

        $summaryData = [];

        foreach ($quedans as $quedan) {
            // One record per planter: codes that differ only in leading zeros or spaces (9997 = 09997) are the same planter
            $planterKey = PlanterProfileSync::key($quedan->planter_code);
            $key = $perQuedan ? $planterKey . '#q' . $quedan->id : $planterKey;
            $firstKey[$planterKey] ??= $key;

            if (!isset($summaryData[$key])) {
                $summaryData[$key] = [
                    'planter_code' => \App\Support\PlanterCode::normalize($quedan->planter_code),
                    'planter_name' => $quedan->planter_name,
                    'tin_no' => $quedan->tin_no,
                    'week_no' => $weekFrom . ' - ' . $weekTo,
                    'week_end_date' => $weekEndDates[$weekTo] ?? '',
                    'quedan_a_lkg' => 0,
                    'quedan_a_price' => 0,
                    'quedan_b_lkg' => 0,
                    'quedan_b_price' => 0,
                    'quedan_b_liens' => 0,
                    'quedan_b_service_charge' => 0,
                    'quedan_b_insurance' => 0,
                    'quedan_b_tax' => 0,
                    'quedan_d_lkg' => 0,
                    'quedan_d_price' => 0,
                    'quedan_d_liens' => 0,
                    'quedan_d_service_charge' => 0,
                    'quedan_d_insurance' => 0,
                    'quedan_d_tax' => 0,
                    'mol_net' => 0,
                    'mol_price' => 0,
                    'molasses_liens' => 0,
                    'molasses_service_charge' => 0,
                    'molasses_insurance' => 0,
                    'molasses_tax' => 0,
                    'consolidated_total' => 0,
                    'consolidated_ta_wt' => 0,
                    'trucking_net_cane' => 0,
                    'trucking_ta_amount' => 0,
                    'trucking_additional_charge' => 0,
                    'trucking_trans_codes' => [],
                    'consolidated_ta_amount' => 0,
                    'consolidated_fuel_amount' => 0,
                    'sugar_lkg' => 0,
                ];
            }

            $summaryData[$key]['quedan_count'] = ($summaryData[$key]['quedan_count'] ?? 0) + 1;
            $summaryData[$key]['qdn_nos'][] = (string) $quedan->qdn_no;

            $boughtPrice = $quedan->quedan_price_id ? $boughtPrices->get($quedan->quedan_price_id) : null;
            if ($boughtPrice) {
                // Priced at its bought week's price, so a price corrected in Price Management carries over
                self::addQuedan($summaryData[$key], self::quedanType($boughtPrice->quedan_type), $quedan, (float) $boughtPrice->quedan_price);
                continue;
            }

            if ($quedan->sold_price !== null) {
                // Bought at the price picked on Buy Quedan: that price and type only
                self::addQuedan($summaryData[$key], self::quedanType($quedan->sold_price_type), $quedan, (float) $quedan->sold_price);
                continue;
            }

            // Bought before a sold price was recorded: the week's prices from Price Management
            $typed = false;
            foreach (['A', 'B', 'D'] as $type) {
                if (isset($quedanPrices[$type])) {
                    self::addQuedan($summaryData[$key], $type, $quedan, (float) ($quedanPrices[$type]->first()->quedan_price ?? 0));
                    $typed = true;
                }
            }
            if (!$typed) {
                self::addQuedan($summaryData[$key], 'B', $quedan, 0.0);
            }
        }

        foreach ($molasses as $mol) {
            // One record per planter: codes that differ only in leading zeros or spaces (9997 = 09997) are the same planter
            $key = $firstKey[PlanterProfileSync::key($mol->planter_code)] ?? PlanterProfileSync::key($mol->planter_code);

            if (!isset($summaryData[$key])) {
                $summaryData[$key] = [
                    'planter_code' => \App\Support\PlanterCode::normalize($mol->planter_code),
                    'planter_name' => $mol->planter_name,
                    'tin_no' => $mol->tin_no,
                    'week_no' => $weekFrom . ' - ' . $weekTo,
                    'week_end_date' => $weekEndDates[$weekTo] ?? '',
                    'quedan_a_lkg' => 0, 'quedan_a_price' => 0,
                    'quedan_b_lkg' => 0, 'quedan_b_price' => 0, 'quedan_b_liens' => 0,
                    'quedan_b_service_charge' => 0, 'quedan_b_insurance' => 0, 'quedan_b_tax' => 0,
                    'quedan_d_lkg' => 0, 'quedan_d_price' => 0, 'quedan_d_liens' => 0,
                    'quedan_d_service_charge' => 0, 'quedan_d_insurance' => 0, 'quedan_d_tax' => 0,
                    'mol_net' => 0, 'mol_price' => 0, 'molasses_liens' => 0,
                    'molasses_service_charge' => 0, 'molasses_insurance' => 0, 'molasses_tax' => 0,
                    'consolidated_total' => 0, 'consolidated_ta_wt' => 0,
                    'trucking_net_cane' => 0, 'trucking_ta_amount' => 0,
                    'trucking_additional_charge' => 0, 'trucking_trans_codes' => [],
                    'consolidated_ta_amount' => 0,
                    'consolidated_fuel_amount' => 0,
                    'sugar_lkg' => 0,
                ];
            }

            // Each record's amount is kept, so molasses bought at different prices add up exactly;
            // the price shown is the average per MT
            $summaryData[$key]['mol_net'] += $mol->mol_net;
            $summaryData[$key]['molasses_count'] = ($summaryData[$key]['molasses_count'] ?? 0) + 1;
            $summaryData[$key]['molasses_amount'] = ($summaryData[$key]['molasses_amount'] ?? 0) + (float) $mol->mol_net * (float) $mol->mol_price;
            $summaryData[$key]['mol_price'] = abs($summaryData[$key]['mol_net']) > 1e-9
                ? $summaryData[$key]['molasses_amount'] / $summaryData[$key]['mol_net']
                : (float) $mol->mol_price;
        }

   foreach ($consolidatedUploads as $consolidated) {
        // One record per planter: codes that differ only in leading zeros or spaces (9997 = 09997) are the same planter
        $key = $firstKey[PlanterProfileSync::key($consolidated->planter_code)] ?? PlanterProfileSync::key($consolidated->planter_code);

        if (!isset($summaryData[$key])) {
            $summaryData[$key] = [
                'planter_code' => \App\Support\PlanterCode::normalize($consolidated->planter_code),
                'planter_name' => $consolidated->planter_name,
                'tin_no' => '',
                'week_no' => $weekFrom . ' - ' . $weekTo,
                'week_end_date' => $weekEndDates[$weekTo] ?? '',
                'quedan_a_lkg' => 0, 'quedan_a_price' => 0,
                'quedan_b_lkg' => 0, 'quedan_b_price' => 0, 'quedan_b_liens' => 0,
                'quedan_b_service_charge' => 0, 'quedan_b_insurance' => 0, 'quedan_b_tax' => 0,
                'quedan_d_lkg' => 0, 'quedan_d_price' => 0, 'quedan_d_liens' => 0,
                'quedan_d_service_charge' => 0, 'quedan_d_insurance' => 0, 'quedan_d_tax' => 0,
                'mol_net' => 0, 'mol_price' => 0, 'molasses_liens' => 0,
                'molasses_service_charge' => 0, 'molasses_insurance' => 0, 'molasses_tax' => 0,
                'consolidated_total' => 0, 'consolidated_ta_wt' => 0,
                'trucking_net_cane' => 0, 'trucking_ta_amount' => 0,
                'trucking_additional_charge' => 0, 'trucking_trans_codes' => [],
                'consolidated_ta_amount' => 0,
                'consolidated_fuel_amount' => 0,
                'sugar_lkg' => 0,
            ];
        }

        $summaryData[$key]['consolidated_ta_wt'] += $consolidated->ta_wt;
        $summaryData[$key]['consolidated_ta_amount'] += $consolidated->ta_amount;
        $summaryData[$key]['consolidated_fuel_amount'] += $consolidated->fuel_issuance_amt;
        $summaryData[$key]['consolidated_total'] += $consolidated->total_summary;
}

        foreach ($truckingAllowances as $trucking) {
        // One record per planter: codes that differ only in leading zeros or spaces (9997 = 09997) are the same planter
        $key = $firstKey[PlanterProfileSync::key($trucking->planter_code)] ?? PlanterProfileSync::key($trucking->planter_code);

        if (!isset($summaryData[$key])) {
            $summaryData[$key] = [
                'planter_code' => \App\Support\PlanterCode::normalize($trucking->planter_code),
                'planter_name' => $trucking->planter_name,
                'tin_no' => '',
                'week_no' => $weekFrom . ' - ' . $weekTo,
                'week_end_date' => $weekEndDates[$weekTo] ?? '',
                'quedan_a_lkg' => 0, 'quedan_a_price' => 0,
                'quedan_b_lkg' => 0, 'quedan_b_price' => 0, 'quedan_b_liens' => 0,
                'quedan_b_service_charge' => 0, 'quedan_b_insurance' => 0, 'quedan_b_tax' => 0,
                'quedan_d_lkg' => 0, 'quedan_d_price' => 0, 'quedan_d_liens' => 0,
                'quedan_d_service_charge' => 0, 'quedan_d_insurance' => 0, 'quedan_d_tax' => 0,
                'mol_net' => 0, 'mol_price' => 0, 'molasses_liens' => 0,
                'molasses_service_charge' => 0, 'molasses_insurance' => 0, 'molasses_tax' => 0,
                'consolidated_total' => 0, 'consolidated_ta_wt' => 0,
                'trucking_net_cane' => 0, 'trucking_ta_amount' => 0,
                'trucking_additional_charge' => 0, 'trucking_trans_codes' => [],
                'consolidated_ta_amount' => 0,
                'consolidated_fuel_amount' => 0,
                'sugar_lkg' => 0,
            ];
        }

        $summaryData[$key]['trucking_net_cane'] += $trucking->net_cane;
        $summaryData[$key]['trucking_ta_amount'] += $trucking->ta_amount;

        $additionalChargeCodes = [6, 8, 25, 36, 38];
        if (in_array((int)$trucking->trans_code, $additionalChargeCodes)) {
            $summaryData[$key]['trucking_additional_charge'] += $trucking->net_cane * 7.00;
            $summaryData[$key]['has_additional_insurance'] = true;
            if (!in_array($trucking->trans_code, $summaryData[$key]['trucking_trans_codes'])) {
                $summaryData[$key]['trucking_trans_codes'][] = $trucking->trans_code;
            }
        }
}

        // By week bought, a planter is listed only for the quedans / molasses bought that week
        if ($byBoughtWeek) {
            $boughtCodes = $quedans->pluck('planter_code')->merge($molasses->pluck('planter_code'))
                ->map(fn ($code) => PlanterProfileSync::key($code))->flip();
            $summaryData = array_filter($summaryData, fn ($data) => $boughtCodes->has(PlanterProfileSync::key($data['planter_code'])));
        }

        foreach ($summaryData as $recordKey => $data) {
            $summaryData[$recordKey]['voucher_key'] = (string) $recordKey;
        }
        $summaryData = collect(array_values($summaryData));

        return self::attachAccounts($summaryData, $cropYear, $weekFrom, $weekTo);
    }

    /**
     * A Check Voucher of one Week Bought: every quedan and molasses record bought that week, whatever its
     * own week, at its bought price, plus the trucking allowance of those same weeks (a week-2 quedan bought
     * in week 6 brings the planter's week-2 trucking allowance). One voucher per quedan, with the planter's
     * molasses and trucking of that week on its first quedan; molasses without a quedan in the week make
     * their own voucher.
     * The accounts applied (loan, cash advance, fuel, others) follow their schedule: each voucher takes the
     * deductions scheduled for its quedan's week (on the planter's first voucher of that week), and they are
     * deducted when the quedan is bought. A week-2 cash advance installment comes off the week-2 quedan's voucher.
     */
    public static function summaryByBoughtWeek($cropYear, $boughtWeek, $planterNames = null): Collection
    {
        $boughtWeek = (int) $boughtWeek;
        // The weeks of the quedans and molasses bought in the week
        $weeks = Quedan::notPending()->where('crop_year', $cropYear)->boughtInWeek($cropYear, $boughtWeek)
            ->distinct()->pluck('week_no')
            ->merge(Molass::notPending()->where('crop_year', $cropYear)->boughtInWeek($cropYear, $boughtWeek)->distinct()->pluck('week_no'))
            ->map(fn ($w) => (int) $w)->filter(fn ($w) => $w > 0)->unique()->sort()->values();

        $weekEnd = WeekNo::where('crop_year', $cropYear)
            ->whereIn('week_no', [(string) $boughtWeek, str_pad((string) $boughtWeek, 2, '0', STR_PAD_LEFT)])
            ->value('week_end_date');
        $records = collect();
        foreach ($weeks as $week) {
            // Each week on its own (its prices, its trucking, its scheduled deductions), one record per quedan
            // bought in the week
            foreach (self::summary($cropYear, $week, $week, $planterNames, $boughtWeek, null, true) as $record) {
                // Molasses without a quedan are keyed by planter: the week keeps them apart
                if (!str_contains($record['voucher_key'], '#q')) {
                    $record['voucher_key'] .= '#w' . $week;
                }
                $record['week_no'] = 'WK ' . $week . ' / BOUGHT WK ' . $boughtWeek;
                $record['quedan_week'] = $week;
                $record['week_end_date'] = $weekEnd ?? '';
                $record['bought_week'] = $boughtWeek;
                $records->push($record);
            }
        }
        return $records->sortBy(fn ($r) => sprintf('%s|%03d|%s', mb_strtoupper(trim((string) $r['planter_name'])), $r['quedan_week'], $r['voucher_key']))->values();
    }

    /**
     * The accounts applied on the voucher of the weeks: cash advance, fuel, others and loan payments
     */
    private static function attachAccounts(Collection $summaryData, $cropYear, $weekFrom, $weekTo): Collection
    {
        $from = min((int) $weekFrom, (int) $weekTo);
        $to = max((int) $weekFrom, (int) $weekTo);
        $weekValues = collect(range($from, $to))
            ->flatMap(fn ($week) => [(string) $week, str_pad((string) $week, 2, '0', STR_PAD_LEFT)])
            ->uniqueStrict()->values()->all();
        $weekEndDates = WeekNo::where('crop_year', $cropYear)->whereIn('week_no', $weekValues)->pluck('week_end_date', 'week_no');

        // Cash Advance Payment: the planter's cash advance weeks that fall in the voucher's weeks
        // (the same weekly schedule Hauling Liquidity deducts), from approved / active / completed advances
        $caWeekly = CashAdvanceDeduction::where('crop_year', $cropYear)
            ->whereIn('week_no', $weekValues)
            ->whereHas('cashAdvance', fn ($q) => $q->whereIn('status', ['approved', 'active', 'completed']))
            ->with('cashAdvance:id,planter_code,planter_name')
            ->get()
            ->map(fn ($d) => ['cash_advance' => $d->cashAdvance, 'amount' => (float) $d->amount]);

        // Older monthly cash advances: installments due within the voucher's weeks, when auto-deduct is on
        $caMonthly = collect();
        if (LoanSetting::get('ca_auto_deduct', false) && $weekEndDates->isNotEmpty()) {
            $periodStart = WeekNo::where('crop_year', $cropYear)->where('week_no', $weekFrom)->value('week_start_date') ?? $weekEndDates->min();
            $caMonthly = CashAdvanceAmortization::whereIn('status', ['pending', 'partial', 'overdue'])
                ->whereBetween('due_date', [substr((string) $periodStart, 0, 10), substr((string) $weekEndDates->max(), 0, 10)])
                ->whereHas('cashAdvance', fn ($q) => $q->where('crop_year', $cropYear)->where('status', 'active'))
                ->with('cashAdvance:id,planter_code,planter_name')
                ->get()
                ->map(fn ($a) => ['cash_advance' => $a->cashAdvance, 'amount' => (float) ($a->amount_due - $a->amount_paid)]);
        }

        // Each cash advance goes to the voucher planter with its code; codes from the uploads can differ from
        // the planter profile codes in spacing / case, so they are compared loosely and the name is the fallback
        // Numeric codes also match without leading zeros (00733 = 733)
        $match = function ($value) {
            $value = strtoupper(preg_replace('/\s+/', ' ', trim((string) $value)));

            return ctype_digit($value) ? (ltrim($value, '0') ?: '0') : $value;
        };
        $voucherCodes = $summaryData->map(fn ($data) => $match($data['planter_code']))->flip();
        $assign = function (Collection $rows, string $field) use (&$summaryData, $voucherCodes, $match) {
            $byCode = collect();
            $byName = collect();
            foreach ($rows as $row) {
                $code = $match($row['planter_code']);
                if ($voucherCodes->has($code)) {
                    $byCode[$code] = ($byCode[$code] ?? 0) + $row['amount'];
                } else {
                    $name = $match($row['planter_name']);
                    $byName[$name] = ($byName[$name] ?? 0) + $row['amount'];
                }
            }
            $summaryData = $summaryData->map(function ($data) use ($byCode, $byName, $match, $field) {
                $data[$field] = round(
                    ($byCode[$match($data['planter_code'])] ?? 0) + ($byName[$match($data['planter_name'])] ?? 0),
                    2
                );
                return $data;
            });
        };

        $assign($caWeekly->concat($caMonthly)->map(fn ($row) => [
            'planter_code' => $row['cash_advance']->planter_code,
            'planter_name' => $row['cash_advance']->planter_name,
            'amount' => $row['amount'],
        ]), 'ca_deduction');

        // Fuel & Others Deduction: the planter's scheduled weeks that fall in the voucher's weeks
        $accountWeeks = PlanterDeductionWeek::where('crop_year', $cropYear)
            ->whereIn('week_no', $weekValues)
            ->with('deduction:id,type,planter_code,planter_name')
            ->get()
            ->filter(fn ($w) => $w->deduction !== null)
            ->map(fn ($w) => [
                'type' => $w->deduction->type,
                'planter_code' => $w->deduction->planter_code,
                'planter_name' => $w->deduction->planter_name,
                'amount' => (float) $w->amount,
            ]);
        $assign($accountWeeks->where('type', 'fuel')->values(), 'fuel_deduction');
        $assign($accountWeeks->where('type', 'others')->values(), 'others_deduction');

        // Loan Payment: installments of the planter's loans due within the voucher's weeks,
        // from approved / active / completed loans, when loan auto-deduct is on
        $loanDue = collect();
        if (LoanSetting::get('auto_deduct', true) && $weekEndDates->isNotEmpty()) {
            $periodStart = WeekNo::where('crop_year', $cropYear)->where('week_no', $weekFrom)->value('week_start_date') ?? $weekEndDates->min();
            $loanDue = LoanAmortization::whereBetween('due_date', [substr((string) $periodStart, 0, 10), substr((string) $weekEndDates->max(), 0, 10)])
                ->whereHas('loan', fn ($q) => $q->where('crop_year', $cropYear)->whereIn('status', ['approved', 'active', 'completed']))
                ->with('loan:id,planter_code')
                ->get()
                ->groupBy(fn ($a) => PlanterProfileSync::key($a->loan->planter_code))
                ->map(fn ($rows) => round((float) $rows->sum('amount_due'), 2));
        }

        $summaryData = $summaryData->map(function ($data) use ($loanDue) {
            $data['loan_deduction'] = $loanDue[PlanterProfileSync::key($data['planter_code'])] ?? 0;
            return $data;
        });

        // A planter with several vouchers (one per quedan) has its accounts taken on the first one only
        $seen = [];
        return $summaryData->map(function ($data) use (&$seen) {
            $planterKey = PlanterProfileSync::key($data['planter_code']);
            if (isset($seen[$planterKey])) {
                foreach (['ca_deduction', 'fuel_deduction', 'others_deduction', 'loan_deduction'] as $field) {
                    $data[$field] = 0;
                }
            }
            $seen[$planterKey] = true;
            return $data;
        });
    }

    /** Sold price types are A, B or D; anything else is computed as B */
    private static function quedanType($type): string
    {
        $type = strtoupper(trim((string) $type));

        return in_array($type, ['A', 'B', 'D'], true) ? $type : 'B';
    }

    /**
     * Add one quedan to a planter record at a price. Each quedan's amount is kept, so quedans sold
     * at different prices add up exactly; the price shown is the average per LKg.
     * A: LKg only. B: LKg, liens, sugar LKg (service, insurance, tax). D: LKg and liens.
     */
    private static function addQuedan(array &$record, string $type, $quedan, float $price): void
    {
        $t = strtolower($type);
        $netLkg = (float) $quedan->sugar_lkg - (float) $quedan->labor_lkg;

        $record["quedan_{$t}_lkg"] += $netLkg;
        $record["quedan_{$t}_amount"] = ($record["quedan_{$t}_amount"] ?? 0) + $netLkg * $price;
        $record["quedan_{$t}_price"] = abs($record["quedan_{$t}_lkg"]) > 1e-9
            ? $record["quedan_{$t}_amount"] / $record["quedan_{$t}_lkg"]
            : $price;

        if ($type === 'B') {
            $record['quedan_b_liens'] += $quedan->total_liens;
            $record['sugar_lkg'] += $quedan->sugar_lkg;
            $record['quedan_b_tax_base'] = ($record['quedan_b_tax_base'] ?? 0) + (float) $quedan->sugar_lkg * $price;
        } elseif ($type === 'D') {
            $record['quedan_d_liens'] += $quedan->total_liens;
        }
    }

    /**
     * Amounts of one planter record, as printed on the voucher
     */
    public static function compute(array $record): array
    {
        $sugar_lkg = $record['sugar_lkg'] ?? 0;

        // Gross is the sum of each quedan's LKg x its price (quedans can be sold at different prices)
        $quedan_a_lkg = $record['quedan_a_lkg'] ?? 0;
        $quedan_a_price = $record['quedan_a_price'] ?? 0;
        $quedan_a_gross = $record['quedan_a_amount'] ?? $quedan_a_lkg * $quedan_a_price;

        $quedan_b_lkg = $record['quedan_b_lkg'] ?? 0;
        $quedan_b_price = $record['quedan_b_price'] ?? 0;
        $quedan_b_gross = $record['quedan_b_amount'] ?? $quedan_b_lkg * $quedan_b_price;
        $quedan_b_liens = $record['quedan_b_liens'] ?? 0;
        $trucking_additional_charge = $record['trucking_additional_charge'] ?? 0;

        $quedan_b_service = $sugar_lkg * 8.0 + $trucking_additional_charge;
        $quedan_b_insurance = $sugar_lkg * 3.0;
        $quedan_b_tax = ($record['quedan_b_tax_base'] ?? $sugar_lkg * $quedan_b_price) * 0.01;
        $quedan_b_total_deductions = $quedan_b_liens + $quedan_b_service + $quedan_b_insurance + $quedan_b_tax;
        $quedan_b_net = $quedan_b_gross - $quedan_b_total_deductions;

        $quedan_d_lkg = $record['quedan_d_lkg'] ?? 0;
        $quedan_d_price = $record['quedan_d_price'] ?? 0;
        $quedan_d_gross = $record['quedan_d_amount'] ?? $quedan_d_lkg * $quedan_d_price;
        $quedan_d_liens = $record['quedan_d_liens'] ?? 0;
        $quedan_d_service = $quedan_d_lkg * 8.0;
        $quedan_d_insurance = $quedan_d_lkg * 3.0;
        $quedan_d_tax = $quedan_d_gross * 0.01;
        $quedan_d_total_deductions = $quedan_d_liens + $quedan_d_service + $quedan_d_insurance + $quedan_d_tax;
        $quedan_d_net = $quedan_d_gross - $quedan_d_total_deductions;

        $quedanGrossProceeds = $quedan_a_gross + $quedan_b_gross + $quedan_d_gross;
        $quedanTotalLiens = $quedan_b_liens + $quedan_d_liens;
        $quedanTotalServiceCharge = $quedan_b_service + $quedan_d_service;
        $quedanTotalInsurance = $quedan_b_insurance + $quedan_d_insurance;
        $quedanTotalTax = $quedan_b_tax + $quedan_d_tax;
        $quedanTotalDeductions =
            $quedanTotalLiens + $quedanTotalServiceCharge + $quedanTotalInsurance + $quedanTotalTax;
        $quedanNetProceeds = $quedanGrossProceeds - $quedanTotalDeductions;

        $mol_net = $record['mol_net'] ?? 0;
        $mol_price = $record['mol_price'] ?? 0;
        $molasses_gross = $record['molasses_amount'] ?? $mol_net * $mol_price;
        $molasses_liens = $record['molasses_liens'] ?? 0;
        $molasses_service = $mol_net * 20.0;
        // 120.00 per MT + 30.00 handling, only when the planter has molasses
        $molasses_insurance = $mol_net > 0 ? $mol_net * 120.0 + 30.0 : 0.0;
        $molasses_tax = $molasses_gross * 0.01;
        $molasses_total_deductions = $molasses_liens + $molasses_service + $molasses_insurance + $molasses_tax;
        $molasses_net = $molasses_gross - $molasses_total_deductions;

        $trucking_net_cane = $record['trucking_net_cane'] ?? 0;
        $consolidated_ta_wt = $record['consolidated_ta_wt'] ?? 0;
        $consolidated_ta_amount = $record['consolidated_ta_amount'] ?? 0;
        $consolidated_fuel_amount = $record['consolidated_fuel_amount'] ?? 0;
        $consolidated_total = $record['consolidated_total'] ?? 0;

        $consolidated_display = $consolidated_total >= 0 ? $consolidated_total : $consolidated_ta_amount;
        $consolidated_net = $consolidated_display - $consolidated_fuel_amount;

        $totalGrossProceeds = $quedanGrossProceeds + $molasses_gross + $consolidated_display;
        $totalDeductions = $quedanTotalDeductions + $molasses_total_deductions + $consolidated_fuel_amount;
        $totalNetProceeds = $totalGrossProceeds - $totalDeductions;

        // Accounts applied (Loan, Cash Advance, Others, Fuel payments) come off the proceeds paid to the planter
        $loanPayment = (float) ($record['loan_deduction'] ?? 0);
        $caPayment = (float) ($record['ca_deduction'] ?? 0);
        $othersPayment = (float) ($record['others_deduction'] ?? 0);
        $fuelPayment = (float) ($record['fuel_deduction'] ?? 0);
        $accountsApplied = $loanPayment + $caPayment + $othersPayment + $fuelPayment;
        $netProceedsPaid = $totalNetProceeds - $accountsApplied;

        unset($record);

        return get_defined_vars();
    }
}
