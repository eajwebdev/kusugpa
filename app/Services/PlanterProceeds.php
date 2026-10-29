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
     * One record per planter with the uploaded quantities, prices and the cash advance / loan payments
     */
    public static function summary($cropYear, $weekFrom, $weekTo, $planterNames = null): Collection
    {
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

        $quedans = $quedanQuery->get();

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

        // One molasses price per week, the latest entered. A join on mol_price would repeat each
        // molasses record once per price row of its week and count its quantity twice.
        $molPrices = MolassesPrice::where('crop_year', $cropYear)
            ->whereIn('week_no', $weekValues)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($p) => [(int) $p->week_no => $p->mol_price]);

        $molasses = $molassesQuery->get()->each(function ($mol) use ($molPrices) {
            $mol->mol_price = $molPrices[(int) $mol->week_no] ?? null;
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
            $key = $quedan->planter_code;

            if (!isset($summaryData[$key])) {
                $summaryData[$key] = [
                    'planter_code' => $quedan->planter_code,
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

            $netLkg = $quedan->sugar_lkg - $quedan->labor_lkg;

            if (isset($quedanPrices['A'])) {
                $priceA = $quedanPrices['A']->first()->quedan_price ?? 0;
                $summaryData[$key]['quedan_a_lkg'] += $netLkg;
                $summaryData[$key]['quedan_a_price'] = $priceA;
            }

            if (isset($quedanPrices['B'])) {
                $priceB = $quedanPrices['B']->first()->quedan_price ?? 0;
                $summaryData[$key]['quedan_b_lkg'] += $netLkg;
                $summaryData[$key]['quedan_b_price'] = $priceB;
                $summaryData[$key]['quedan_b_liens'] += $quedan->total_liens;
                $summaryData[$key]['sugar_lkg'] += $quedan->sugar_lkg;
            }

            if (isset($quedanPrices['D'])) {
                $priceD = $quedanPrices['D']->first()->quedan_price ?? 0;
                $summaryData[$key]['quedan_d_lkg'] += $netLkg;
                $summaryData[$key]['quedan_d_price'] = $priceD;
                $summaryData[$key]['quedan_d_liens'] += $quedan->total_liens;
            }

            if (!isset($quedanPrices['A']) && !isset($quedanPrices['B']) && !isset($quedanPrices['D'])) {
                $summaryData[$key]['quedan_b_lkg'] += $netLkg;
                $summaryData[$key]['quedan_b_price'] = 0;
                $summaryData[$key]['quedan_b_liens'] += $quedan->total_liens;
                $summaryData[$key]['sugar_lkg'] += $quedan->sugar_lkg;
            }
        }

        foreach ($molasses as $mol) {
            $key = $mol->planter_code;

            if (!isset($summaryData[$key])) {
                $summaryData[$key] = [
                    'planter_code' => $mol->planter_code,
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
                ];
            }

            $summaryData[$key]['mol_net'] += $mol->mol_net;
            $summaryData[$key]['mol_price'] = $mol->mol_price;
        }

   foreach ($consolidatedUploads as $consolidated) {
        $key = $consolidated->planter_code;

        if (!isset($summaryData[$key])) {
            $summaryData[$key] = [
                'planter_code' => $consolidated->planter_code,
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
        $key = $trucking->planter_code;

        if (!isset($summaryData[$key])) {
            $summaryData[$key] = [
                'planter_code' => $trucking->planter_code,
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

        $summaryData = collect(array_values($summaryData));

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
        $match = fn ($value) => strtoupper(preg_replace('/\s+/', ' ', trim((string) $value)));
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
                ->groupBy(fn ($a) => $a->loan->planter_code)
                ->map(fn ($rows) => round((float) $rows->sum('amount_due'), 2));
        }

        $summaryData = $summaryData->map(function ($data) use ($loanDue) {
            $data['loan_deduction'] = $loanDue[$data['planter_code']] ?? 0;
            return $data;
        });

        return $summaryData;
    }

    /**
     * Amounts of one planter record, as printed on the voucher
     */
    public static function compute(array $record): array
    {
        $sugar_lkg = $record['sugar_lkg'] ?? 0;

        $quedan_a_lkg = $record['quedan_a_lkg'] ?? 0;
        $quedan_a_price = $record['quedan_a_price'] ?? 0;
        $quedan_a_gross = $quedan_a_lkg * $quedan_a_price;

        $quedan_b_lkg = $record['quedan_b_lkg'] ?? 0;
        $quedan_b_price = $record['quedan_b_price'] ?? 0;
        $quedan_b_gross = $quedan_b_lkg * $quedan_b_price;
        $quedan_b_liens = $record['quedan_b_liens'] ?? 0;
        $trucking_additional_charge = $record['trucking_additional_charge'] ?? 0;

        $quedan_b_service = $sugar_lkg * 8.0 + $trucking_additional_charge;
        $quedan_b_insurance = $sugar_lkg * 3.0;
        $quedan_b_tax = $sugar_lkg * $quedan_b_price * 0.01;
        $quedan_b_total_deductions = $quedan_b_liens + $quedan_b_service + $quedan_b_insurance + $quedan_b_tax;
        $quedan_b_net = $quedan_b_gross - $quedan_b_total_deductions;

        $quedan_d_lkg = $record['quedan_d_lkg'] ?? 0;
        $quedan_d_price = $record['quedan_d_price'] ?? 0;
        $quedan_d_gross = $quedan_d_lkg * $quedan_d_price;
        $quedan_d_liens = $record['quedan_d_liens'] ?? 0;
        $quedan_d_service = $quedan_d_lkg * 8.0;
        $quedan_d_insurance = $quedan_d_lkg * 3.0;
        $quedan_d_tax = $quedan_d_lkg * $quedan_d_price * 0.01;
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
        $molasses_gross = $mol_net * $mol_price;
        $molasses_liens = $record['molasses_liens'] ?? 0;
        $molasses_service = $mol_net * 20.0;
        $molasses_insurance = $mol_net * 120.0 + 30.0;
        $molasses_tax = $mol_net * $mol_price * 0.01;
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
