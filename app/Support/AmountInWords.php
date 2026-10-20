<?php

namespace App\Support;

class AmountInWords
{
    private const ONES = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN',
        'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
    private const TENS = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];

    /**
     * 20000 -> "TWENTY THOUSAND PESOS AND 00/100 ONLY"
     */
    public static function pesos($amount): string
    {
        $amount = round((float) $amount, 2);
        $negative = $amount < 0;
        $amount = abs($amount);
        $pesos = (int) floor($amount);
        $centavos = (int) round(($amount - $pesos) * 100);

        $words = $pesos === 0 ? 'ZERO' : self::convert($pesos);

        return ($negative ? 'NEGATIVE ' : '') . $words . ' PESOS AND ' . str_pad($centavos, 2, '0', STR_PAD_LEFT) . '/100 ONLY';
    }

    private static function convert(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }
        if ($n < 100) {
            return self::TENS[intdiv($n, 10)] . ($n % 10 ? '-' . self::ONES[$n % 10] : '');
        }
        if ($n < 1000) {
            return self::ONES[intdiv($n, 100)] . ' HUNDRED' . ($n % 100 ? ' ' . self::convert($n % 100) : '');
        }
        foreach ([1000000000 => 'BILLION', 1000000 => 'MILLION', 1000 => 'THOUSAND'] as $unit => $name) {
            if ($n >= $unit) {
                return self::convert(intdiv($n, $unit)) . ' ' . $name . ($n % $unit ? ' ' . self::convert($n % $unit) : '');
            }
        }
        return '';
    }
}
