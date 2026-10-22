<?php

namespace App\Services;

use App\Models\CashAdvance;
use App\Models\WeekNo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Weekly schedule (deduction weeks) of a cash advance, shared by the CA Receipt Details
 * dialog and the schedule editor on the cash advance page. Hauling Liquidity reads these
 * weeks for its "Cash Adv." deduction; weekly repayments are recorded against them.
 */
class CashAdvanceSchedule
{
    /**
     * @param  Collection  $rows  [['week_no' => '4', 'amount' => 5000, 'due_date' => '2026-10-25' (optional)], ...]
     */
    public static function validate(CashAdvance $ca, Collection $rows): void
    {
        // Weeks must add up to the advance amount (compared in centavos)
        $scheduledCents = (int) $rows->sum(fn ($r) => (int) round((float) $r['amount'] * 100));
        $amountCents = (int) round((float) $ca->amount * 100);
        if ($scheduledCents !== $amountCents) {
            throw ValidationException::withMessages([
                'deductions' => 'Deduction weeks total (₱' . number_format($scheduledCents / 100, 2) . ') must equal the advance amount (₱' . number_format($amountCents / 100, 2) . ').',
            ]);
        }

        // Weeks that already have repayments must stay, with at least what was paid
        $paidByWeek = $ca->payments()->get()->groupBy(fn ($p) => (string) $p->week_no)
            ->map(fn ($payments) => (int) round($payments->sum('amount') * 100));
        foreach ($paidByWeek as $weekNo => $paidCents) {
            $row = $rows->first(fn ($r) => (string) $r['week_no'] === (string) $weekNo);
            if (!$row || (int) round((float) $row['amount'] * 100) < $paidCents) {
                throw ValidationException::withMessages([
                    'deductions' => 'Week ' . $weekNo . ' already has ₱' . number_format($paidCents / 100, 2) . ' paid, so it must stay with at least that amount.',
                ]);
            }
        }

        $validWeeks = WeekNo::where('crop_year', $ca->crop_year)->pluck('week_no')->map(fn ($w) => (string) $w);
        $unknown = $rows->pluck('week_no')->reject(fn ($w) => $validWeeks->contains((string) $w));
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                'deductions' => 'Week ' . $unknown->implode(', ') . ' is not set up for crop year ' . $ca->crop_year . ' in Crop & Week Management.',
            ]);
        }

        // An older monthly cash advance switches to weekly only while its monthly installments are unpaid
        if ($ca->term_weeks === null && $rows->isNotEmpty() && $ca->amortizations()->where('amount_paid', '>', 0)->exists()) {
            throw ValidationException::withMessages([
                'deductions' => 'This cash advance already has monthly payments recorded, so it keeps its monthly schedule.',
            ]);
        }
    }

    /**
     * Replace the weekly schedule. Call inside a DB transaction after validate().
     */
    public static function apply(CashAdvance $ca, Collection $rows): void
    {
        // Due date: the one given, else the week's current one, else the week's end date
        $dueDates = $ca->deductions()->pluck('due_date', 'week_no');
        $weekEnds = WeekNo::where('crop_year', $ca->crop_year)->pluck('week_end_date', 'week_no')
            ->map(fn ($d) => $d ? substr((string) $d, 0, 10) : null);
        $ca->deductions()->delete();
        foreach ($rows as $r) {
            $week = (string) $r['week_no'];
            $ca->deductions()->create([
                'crop_year' => $ca->crop_year,
                'week_no' => $week,
                'due_date' => ($r['due_date'] ?? null) ?: ($dueDates[$week] ?? null) ?: ($weekEnds[$week] ?? null),
                'amount' => round((float) $r['amount'], 2),
            ]);
        }

        // An older monthly cash advance becomes weekly: its unpaid monthly installments go away
        if ($ca->term_weeks === null && $rows->isNotEmpty()) {
            $ca->amortizations()->delete();
        }
        if ($ca->isWeekly()) {
            $ca->term_weeks = $rows->count() ?: $ca->term_weeks;
            $ca->syncWeeklyBalance();
        }
    }
}
