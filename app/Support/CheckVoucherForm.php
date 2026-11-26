<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The Check Voucher as printed on the pre-printed Metrobank check-voucher form (Letter, landscape): only the data,
 * in the dot-matrix draft font (resources/fonts/kp-draft), on the printer's grid of 15 characters per inch and
 * 6 lines per inch. The form already has the association's name, PAYEE, HACIENDA, Date:, Amount and the check.
 *
 * Every line is a fixed-width string placed at a row of the grid; columns and rows were measured from a printed
 * voucher, so the values land where the old dot-matrix program printed them:
 *
 *  row  0  date                                  row 17  T O T A L >>>>>> and the totals
 *  row  1  payee                                 rows 18-20  loan / cash advance / others account
 *  row  1.75 WEEK NO.                            row 23  fuel payment (and the accounts taken off)
 *  row  3  QUEDAN & MOLASSES LIQUIDATION ...     row 24  N E T  P R O C E E D S
 *  rows 6-7 column headings                      row 26  net proceeds
 *  rows 9-16 A, B, D, M and T (trucking)         rows 32-36 the check: week, date, payee, amount, amount in words
 */
class CheckVoucherForm
{
    /** Top-left of the grid (column 0, cap top of row 0) on the page, in inches */
    public const ORIGIN_X_IN = 1.794;
    public const ORIGIN_Y_IN = 0.591;
    public const CPI = 15;
    public const LPI = 6;

    /** Last column of each amount of the table (amounts are right-aligned on it) */
    private const QTY = 11, PRICE = 21, GROSS = 35, LIENS = 45, SERVICE = 54, INSURANCE = 64, TAX = 74, DEDUCTION = 84, NET = 97;

    /** Columns of each character of the check date: M M - D D - Y Y Y Y boxes */
    private const CHECK_DATE_COLS = [70, 73, 76, 78, 80, 83, 85, 87, 89, 91];

    /**
     * The lines of one voucher: [['row' => float, 'text' => string], ...]
     *
     * @param  array  $p  PlanterProceeds::compute() of the voucher
     */
    public static function lines(array $p, array $record, string $payee, CarbonInterface $date): array
    {
        $payee = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $payee)));
        $week = (string) ($record['bought_week'] ?? $record['quedan_week'] ?? preg_replace('/^\D+/', '', (string) ($record['week_no'] ?? '')));
        $weekEnd = !empty($record['week_end_date']) ? date('m-d-Y', strtotime($record['week_end_date'])) : '';

        $rows = [];
        $line = function (float $row) use (&$rows) {
            return new VoucherLine($rows, $row);
        };

        // Heading
        $line(0)->at(70, $date->format('m-d-Y'));
        $line(1)->at(15, $payee);
        $line(1.75)->at(31, 'WEEK NO.')->to(42, $week);
        $line(3)->at(11, 'QUEDAN & MOLASSES LIQUIDATION WEEK ENDING : ' . $weekEnd);
        $line(6)->at(0, 'DOC.')->at(6, 'QTY. IN')->at(27, 'GROSS')->at(37, 'QUEDAN')->at(45, 'SERVICE')->at(53, 'INSURANCE')
            ->at(66, 'W/HOLDING')->at(77, 'T O T A L')->at(90, 'N E T');
        $line(7)->at(0, 'TYPE')->at(6, 'LKG/M.T.')->at(16, 'PRICE')->at(26, 'PROCEEDS')->at(37, 'LIENS')->at(46, 'CHARGE')
            ->at(54, 'HANDLING')->at(68, 'T A X')->at(76, 'DEDUCTION')->at(89, 'PROCEEDS');

        // Document types: A, B and D sugar, M molasses, T trucking allowance
        $table = [
            9 => ['A', $p['quedan_a_lkg'], $p['quedan_a_price'], $p['quedan_a_gross'], 0, 0, 0, 0, 0, $p['quedan_a_gross']],
            11 => ['B', $p['quedan_b_lkg'], $p['quedan_b_price'], $p['quedan_b_gross'], $p['quedan_b_liens'], $p['quedan_b_service'],
                $p['quedan_b_insurance'], $p['quedan_b_tax'], $p['quedan_b_total_deductions'], $p['quedan_b_net']],
            12 => ['D', $p['quedan_d_lkg'], $p['quedan_d_price'], $p['quedan_d_gross'], $p['quedan_d_liens'], $p['quedan_d_service'],
                $p['quedan_d_insurance'], $p['quedan_d_tax'], $p['quedan_d_total_deductions'], $p['quedan_d_net']],
            14 => ['M', $p['mol_net'], $p['mol_price'], $p['molasses_gross'], $p['molasses_liens'], $p['molasses_service'],
                $p['molasses_insurance'], $p['molasses_tax'], $p['molasses_total_deductions'], $p['molasses_net']],
            16 => ['T', $p['trucking_net_cane'], 0, $p['consolidated_display'], 0, 0, 0, 0, $p['consolidated_fuel_amount'], $p['consolidated_net']],
        ];
        foreach ($table as $row => [$type, $qty, $price, $gross, $liens, $service, $insurance, $tax, $deduction, $net]) {
            // A row with nothing sold prints its letter only
            $sold = abs((float) $qty) > 1e-9 || abs((float) $gross) > 0.004;
            $l = $line($row)->at(1, $type);
            if ($sold) {
                $l->to(self::QTY, self::qty($qty))->to(self::PRICE, self::amount($price))->to(self::GROSS, self::amount($gross))
                    ->to(self::LIENS, self::amount($liens))->to(self::SERVICE, self::amount($service))
                    ->to(self::INSURANCE, self::amount($insurance))->to(self::TAX, self::amount($tax))
                    ->to(self::DEDUCTION, self::amount($deduction))->to(self::NET, self::amount($net));
            }
        }

        $line(17)->at(1, 'T O T A L   >>>>>>')
            ->to(self::GROSS, self::amount($p['totalGrossProceeds']))
            ->to(self::LIENS, self::amount($p['quedanTotalLiens'] + $p['molasses_liens']))
            ->to(self::SERVICE, self::amount($p['quedanTotalServiceCharge'] + $p['molasses_service']))
            ->to(self::INSURANCE, self::amount($p['quedanTotalInsurance'] + $p['molasses_insurance']))
            ->to(self::TAX, self::amount($p['quedanTotalTax'] + $p['molasses_tax']))
            ->to(self::DEDUCTION, self::amount($p['totalDeductions']))
            ->to(self::NET, self::amount($p['totalNetProceeds']));

        // Accounts applied: each on its line, and all of them taken off the net proceeds on the fuel line
        $line(18)->at(30, 'LOAN PAYMENT')->to(self::TAX, self::amount($p['loanPayment'], true));
        $line(19)->at(30, 'CASH ADVANCE PAYMENT')->to(self::TAX, self::amount($p['caPayment'], true));
        $line(20)->at(30, 'OTHERS ACCOUNT')->to(self::TAX, self::amount($p['othersPayment'], true));
        $applied = $p['loanPayment'] + $p['caPayment'] + $p['othersPayment'] + $p['fuelPayment'];
        $line(23)->at(30, 'FUEL PAYMENT')->to(self::TAX, self::amount($p['fuelPayment'], true))->to(self::NET + 1, self::amount($applied, true));
        $line(24)->at(30, 'N E T  P R O C E E D S')->to(self::NET + 1, self::amount($p['netProceedsPaid'], true));
        $line(26)->to(21, self::amount($p['netProceedsPaid'], true));

        // The check: only for an amount to pay
        if (round((float) $p['netProceedsPaid'], 2) > 0) {
            $line(32)->at(35, 'WEEK NO.')->to(46, $week);
            $checkDate = $line(32);
            foreach (str_split($date->format('m-d-Y')) as $i => $char) {
                $checkDate->at(self::CHECK_DATE_COLS[$i], $char);
            }
            $line(34)->at(19, $payee)->to(85, self::amount($p['netProceedsPaid'], true));
            $line(36)->at(13, self::words($p['netProceedsPaid']));
        }

        ksort($rows, SORT_NUMERIC);

        return array_values(array_map(fn ($text, $row) => ['row' => (float) $row, 'text' => rtrim($text)], $rows, array_keys($rows)));
    }

    /** Amount as the old program printed it: 2,582.00, .50 for under one peso; blank for zero unless $zero */
    public static function amount($value, bool $zero = false): string
    {
        $value = round((float) $value, 2);
        if (abs($value) < 0.005 && !$zero) {
            return '';
        }

        return self::dropLeadingZero(number_format($value, 2));
    }

    /** LKg / metric tons with three decimals (.378) */
    public static function qty($value): string
    {
        $value = round((float) $value, 3);

        return abs($value) < 0.0005 ? '' : self::dropLeadingZero(number_format($value, 3));
    }

    private static function dropLeadingZero(string $text): string
    {
        return preg_replace('/^(-?)0\./', '$1.', $text);
    }

    /** 37918.55 -> "THIRTY SEVEN THOUSANDS NINE HUNDRED EIGHTEEN  & 55 CENTAVOS", as on the check */
    public static function words($amount): string
    {
        $amount = abs(round((float) $amount, 2));
        $pesos = (int) floor($amount);
        $centavos = (int) round(($amount - $pesos) * 100);

        return ($pesos === 0 ? 'ZERO' : self::convert($pesos)) . '  & ' . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT) . ' CENTAVOS';
    }

    private static function convert(int $n): string
    {
        $ones = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE',
            'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
        $tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];

        if ($n < 20) {
            return $ones[$n];
        }
        if ($n < 100) {
            return $tens[intdiv($n, 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
        }
        if ($n < 1000) {
            return $ones[intdiv($n, 100)] . ' HUNDRED' . ($n % 100 ? ' ' . self::convert($n % 100) : '');
        }
        foreach ([1000000000 => 'BILLIONS', 1000000 => 'MILLIONS', 1000 => 'THOUSANDS'] as $unit => $name) {
            if ($n >= $unit) {
                return self::convert(intdiv($n, $unit)) . ' ' . $name . ($n % $unit ? ' ' . self::convert($n % $unit) : '');
            }
        }

        return '';
    }
}
