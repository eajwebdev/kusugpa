<?php

namespace Tests\Unit;

use App\Services\PlanterProceeds;
use App\Support\CheckVoucherForm;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * The Check Voucher printed on the pre-printed form: values on the columns and rows of the old dot-matrix print
 */
class CheckVoucherFormTest extends TestCase
{
    private function lines(array $record = []): array
    {
        $record += [
            'planter_code' => '00001', 'planter_name' => 'AVILA, ROBERT', 'bought_week' => 2, 'week_end_date' => '2026-10-12',
            'quedan_b_lkg' => 11.96, 'quedan_b_price' => 2582, 'quedan_b_amount' => 30880.72, 'quedan_b_liens' => 197.83,
            'sugar_lkg' => 12.32, 'trucking_additional_charge' => 85.89, 'quedan_b_tax_base' => 31810,
            'trucking_net_cane' => 12.27, 'consolidated_gross' => 3742.35, 'fuel_deduction' => 2246,
        ];
        $lines = CheckVoucherForm::lines(PlanterProceeds::compute($record), $record, 'Avila,  Robert', Carbon::parse('2026-10-16'));

        return collect($lines)->mapWithKeys(fn ($l) => [(string) $l['row'] => $l['text']])->all();
    }

    /** The column a value starts on, in a line */
    private function col(string $line, string $value): int
    {
        $pos = mb_strpos($line, $value);
        $this->assertNotFalse($pos, "'$value' not in '$line'");

        return $pos;
    }

    public function test_heading_values_are_on_their_columns(): void
    {
        $l = $this->lines();

        $this->assertSame(70, $this->col($l['0'], '10-16-2026'));
        $this->assertSame(15, $this->col($l['1'], 'AVILA, ROBERT'));
        $this->assertSame(42, mb_strlen($l['1.75']) - 1, 'week number ends on column 42');
        $this->assertSame(11, $this->col($l['3'], 'QUEDAN & MOLASSES LIQUIDATION WEEK ENDING : 10-12-2026'));
    }

    public function test_amounts_are_right_aligned_on_their_columns(): void
    {
        $b = $this->lines()['11'];
        $ends = ['11.960' => 11, '2,582.00' => 21, '30,880.72' => 35, '197.83' => 45, '184.45' => 54, '36.96' => 64, '318.10' => 74, '737.34' => 84, '30,143.38' => 97];
        foreach ($ends as $value => $end) {
            $this->assertSame($end, $this->col($b, $value) + mb_strlen($value) - 1, $value);
        }
        $this->assertSame(1, $this->col($b, 'B'));
    }

    public function test_accounts_and_net_proceeds(): void
    {
        $l = $this->lines();

        $this->assertSame(74, $this->col($l['18'], '.00') + 2, 'zero loan payment prints .00');
        $this->assertSame(30, $this->col($l['23'], 'FUEL PAYMENT'));
        $this->assertStringEndsWith('2,246.00', $l['23']);
        $this->assertSame(98, mb_strlen($l['24']) - 1);
        $this->assertSame(21, mb_strlen($l['26']) - 1);
    }

    public function test_check_has_week_date_payee_amount_and_words(): void
    {
        $l = $this->lines();
        $net = trim($l['26']);

        $this->assertSame(35, $this->col($l['32'], 'WEEK NO.'));
        $this->assertSame('1  0  - 1 6  - 2 0 2 6', trim(mb_substr($l['32'], 70)));
        $this->assertSame(19, $this->col($l['34'], 'AVILA, ROBERT'));
        $this->assertSame(85, $this->col($l['34'], $net) + mb_strlen($net) - 1);
        $this->assertSame(13, $this->col($l['36'], 'THIRTY'));
    }

    public function test_nothing_to_pay_prints_no_check(): void
    {
        $l = $this->lines(['fuel_deduction' => 999999]);

        $this->assertArrayNotHasKey('34', $l);
        $this->assertArrayNotHasKey('36', $l);
    }

    public function test_amount_in_words_as_on_the_check(): void
    {
        $this->assertSame('THIRTY SEVEN THOUSANDS NINE HUNDRED EIGHTEEN  & 55 CENTAVOS', CheckVoucherForm::words(37918.55));
        $this->assertSame('ONE HUNDRED TWENTY  & 00 CENTAVOS', CheckVoucherForm::words(120));
        $this->assertSame('ZERO  & 05 CENTAVOS', CheckVoucherForm::words(0.05));
    }

    public function test_amounts_drop_the_leading_zero(): void
    {
        $this->assertSame('.378', CheckVoucherForm::qty(0.378));
        $this->assertSame('.00', CheckVoucherForm::amount(0, true));
        $this->assertSame('', CheckVoucherForm::amount(0));
        $this->assertSame('-.50', CheckVoucherForm::amount(-0.5));
        $this->assertSame('2,582.00', CheckVoucherForm::amount(2582));
    }
}
