<?php

namespace App\Services;

use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Monthly repayment schedule of a loan (its amortization rows), shared by the New Loan form and the
 * schedule editor on the loan page. The months must add up to the loan's total repayable
 * (principal + interest); repayments are recorded against a month.
 */
class LoanSchedule
{
    /**
     * Evenly split schedule starting the month after $from (the last month takes the centavo remainder)
     */
    public static function even(float $total, int $months, ?Carbon $from = null): Collection
    {
        $from = ($from ?? now())->copy()->startOfMonth();
        $cents = (int) round($total * 100);
        $each = intdiv($cents, max(1, $months));

        return collect(range(1, $months))->map(fn ($i) => [
            'month' => $from->copy()->addMonthsNoOverflow($i)->format('Y-m'),
            'amount' => ($i === $months ? $cents - $each * ($months - 1) : $each) / 100,
        ]);
    }

    /**
     * @param  Collection  $rows  [['month' => '2026-11', 'amount' => 5000, 'due_date' => '2026-11-30' (optional)], ...]
     */
    public static function validate(Loan $loan, Collection $rows): void
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['schedule' => 'Add at least one month.']);
        }

        $months = $rows->pluck('month');
        $dupes = $months->duplicates()->unique();
        if ($dupes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'schedule' => self::label($dupes->first()) . ' is listed more than once. Each month can only be used once.',
            ]);
        }

        foreach ($rows as $r) {
            if (!empty($r['due_date']) && substr($r['due_date'], 0, 7) !== $r['month']) {
                throw ValidationException::withMessages([
                    'schedule' => 'The due date of ' . self::label($r['month']) . ' must be within that month.',
                ]);
            }
        }

        // Months must add up to the total repayable (compared in centavos)
        $scheduledCents = (int) $rows->sum(fn ($r) => (int) round((float) $r['amount'] * 100));
        $totalCents = (int) round((float) $loan->total_amount * 100);
        if ($scheduledCents !== $totalCents) {
            throw ValidationException::withMessages([
                'schedule' => 'Monthly payments total (₱' . number_format($scheduledCents / 100, 2) . ') must equal the total repayable (₱' . number_format($totalCents / 100, 2) . ').',
            ]);
        }

        // Months that already have repayments must stay, with at least what was paid
        $paidByMonth = $loan->payments()->get()->groupBy('month')
            ->map(fn ($payments) => (int) round($payments->sum('amount') * 100));
        foreach ($paidByMonth as $month => $paidCents) {
            $row = $rows->first(fn ($r) => $r['month'] === $month);
            if (!$row || (int) round((float) $row['amount'] * 100) < $paidCents) {
                throw ValidationException::withMessages([
                    'schedule' => self::label($month) . ' already has ₱' . number_format($paidCents / 100, 2) . ' paid, so it must stay with at least that amount.',
                ]);
            }
        }
    }

    /**
     * Replace the schedule (amortization rows). Call inside a DB transaction after validate().
     */
    public static function apply(Loan $loan, Collection $rows): void
    {
        $rows = $rows->sortBy('month')->values();
        $total = (float) $loan->total_amount;
        $interestShare = $total > 0 ? max(0, $total - (float) $loan->principal_amount) / $total : 0;

        $loan->amortizations()->delete();
        $balance = (int) round($total * 100);
        foreach ($rows as $i => $r) {
            $cents = (int) round((float) $r['amount'] * 100);
            $balance -= $cents;
            $interest = round($cents / 100 * $interestShare, 2);
            $loan->amortizations()->create([
                'payment_number' => $i + 1,
                'due_date' => !empty($r['due_date']) ? $r['due_date'] : Carbon::createFromFormat('Y-m-d', $r['month'] . '-01')->endOfMonth()->toDateString(),
                'amount_due' => $cents / 100,
                // planned interest / principal split of the installment
                'interest_paid' => $interest,
                'principal_paid' => $cents / 100 - $interest,
                'balance_after' => $balance / 100,
                'status' => 'pending',
            ]);
        }

        $loan->term_months = $rows->count();
        $loan->monthly_amortization = $rows->count() ? round($total / $rows->count(), 2) : 0;
        $loan->syncBalance();
    }

    /** 2026-11 → Nov 2026 */
    public static function label(string $month): string
    {
        return Carbon::createFromFormat('Y-m-d', $month . '-01')->format('M Y');
    }
}
