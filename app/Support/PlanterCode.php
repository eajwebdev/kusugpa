<?php

namespace App\Support;

/**
 * Planter codes are 5 digits with leading zeros (9997 → 09997, 733 → 00733). Uploads and Excel
 * often drop the leading zeros, which split one planter into two (9997 and 09997), so every code
 * is put in this form before it is saved.
 */
class PlanterCode
{
    public const LENGTH = 5;

    /**
     * Numeric codes: leading zeros fixed to 5 digits ("9997" → "09997", "0009997" → "09997").
     * Numeric codes longer than 5 digits keep their digits; other codes are only trimmed.
     */
    public static function normalize($code): string
    {
        $code = preg_replace('/\s+/', '', trim((string) $code));
        if ($code === '' || !ctype_digit($code)) {
            return $code;
        }

        $digits = ltrim($code, '0');

        return strlen($digits) <= self::LENGTH
            ? str_pad($digits, self::LENGTH, '0', STR_PAD_LEFT)
            : $digits;
    }
}
