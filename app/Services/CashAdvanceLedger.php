<?php

namespace App\Services;

use App\Models\CashAdvance;
use App\Models\CashAdvancePayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cash Advance Ledger: every cash advance released, with its principal, interest, total, what was paid
 * (split into principal and interest) and the balance, one line per planter with that planter's advances under it.
 *
 * Payments are applied to the interest (and any late charge) first, then to the principal. Adjustments (late
 * charges +, balance reductions −) are kept in their own column.
 */
class CashAdvanceLedger
{
    /** Released cash advances; pending / rejected only when asked for */
    public const RELEASED = ['approved', 'active', 'completed'];

    /**
     * @param  array  $f  crop_year, date_from, date_to, search, status (released|all|<status>), created_by,
     *                    planters (planter codes: only these planters, e.g. the ones picked to print)
     */
    public function build(array $f): array
    {
        $from = !empty($f['date_from']) ? Carbon::parse($f['date_from'])->toDateString() : null;
        $to = !empty($f['date_to']) ? Carbon::parse($f['date_to'])->toDateString() : null;

        $query = CashAdvance::query()
            ->with(['payments:id,cash_advance_id,week_no,amount,payment_method,receipt_no,paid_date', 'deductions:id,cash_advance_id,week_no',
                'createdByUser:id,fname,lname'])
            ->when(!empty($f['crop_year']), fn ($q) => $q->where('crop_year', $f['crop_year']))
            ->when($from, fn ($q) => $q->whereDate('application_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('application_date', '<=', $to))
            ->when(!empty($f['created_by']), fn ($q) => $q->where('created_by', $f['created_by']))
            ->when(!empty($f['search']), function ($q) use ($f) {
                $term = '%' . trim($f['search']) . '%';
                $q->where(fn ($w) => $w->where('planter_name', 'like', $term)->orWhere('planter_code', 'like', $term)->orWhere('ca_number', 'like', $term));
            });
        $status = $f['status'] ?? 'released';
        if ($status === 'released' || $status === '') {
            $query->whereIn('status', self::RELEASED);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $picked = collect($f['planters'] ?? [])->map(fn ($c) => PlanterProfileSync::key((string) $c))->filter()->flip();
        $rows = $query->get()->map(fn (CashAdvance $ca) => $this->row($ca))
            ->filter(fn ($r) => $picked->isEmpty() || $picked->has($r['planter_key']))
            ->sortBy([['planter_sort', 'asc'], ['date', 'asc'], ['ca_number', 'asc']])->values();

        // One line per planter (the totals of their advances), with the advances under it
        $planters = $rows->groupBy('planter_key')->map(function (Collection $items) {
            $first = $items->first();
            return [
                'planter_key' => $first['planter_key'],
                'planter_code' => $first['planter_code'],
                'planter_name' => $first['planter_name'],
                'rows' => $items->map(fn ($r) => collect($r)->except(['planter_sort'])->all())->values()->all(),
                'subtotal' => $this->sum($items),
                'last_date' => $items->max('date'),
                'weeks' => $items->flatMap(fn ($r) => $r['weeks'])->unique()->sort()->values()->all(),
            ];
        })->values();

        return [
            'filters' => ['crop_year' => $f['crop_year'] ?? '', 'date_from' => $from, 'date_to' => $to, 'status' => $status, 'picked' => $picked->count()],
            'planters' => $planters->all(),
            'totals' => $this->sum($rows) + [
                'planters' => $planters->count(),
                'settled' => $rows->where('balance', '<=', 0.004)->count(),
            ],
            'creators' => $rows->pluck('created_by_name', 'created_by')->filter()->unique()->sort()->all(),
        ];
    }

    /**
     * Every cash advance of one planter (all crop years and statuses), with its payments, newest first
     */
    public function planterHistory(string $planterCode): array
    {
        $key = PlanterProfileSync::key($planterCode);
        $cas = CashAdvance::with(['payments', 'deductions:id,cash_advance_id,week_no,amount,due_date', 'createdByUser:id,fname,lname', 'approvedByUser:id,fname,lname'])
            ->where(fn ($q) => $q->where('planter_code', $planterCode)->orWhere('planter_code', ltrim($planterCode, '0'))
                ->orWhere('planter_code', str_pad(ltrim($planterCode, '0'), 5, '0', STR_PAD_LEFT)))
            ->get()
            ->filter(fn ($ca) => PlanterProfileSync::key($ca->planter_code) === $key)
            ->sortByDesc(fn ($ca) => ($ca->application_date?->toDateString() ?? '') . '|' . $ca->id)
            ->values();

        $rows = $cas->map(function (CashAdvance $ca) {
            $row = $this->row($ca);
            $row['approved_by_name'] = $ca->approvedByUser ? trim($ca->approvedByUser->fname . ' ' . $ca->approvedByUser->lname) : null;
            $row['schedule'] = $ca->deductions->map(fn ($d) => ['week_no' => (string) $d->week_no, 'amount' => round((float) $d->amount, 2),
                'due_date' => $d->due_date ? Carbon::parse($d->due_date)->toDateString() : null])->values()->all();
            $row['purpose'] = $ca->purpose;
            $row['url'] = route('cash-advances.show', $ca);
            return collect($row)->except(['planter_sort'])->all();
        });
        $released = $rows->whereIn('status', self::RELEASED);

        return [
            'planter_code' => $planterCode,
            'planter_name' => $cas->first()?->planter_name,
            'advances' => $rows->all(),
            'totals' => $this->sum($released) + ['count' => $rows->count(), 'released' => $released->count()],
        ];
    }

    private function row(CashAdvance $ca): array
    {
        $interest = round((float) $ca->interest_amount, 2);
        $adjustment = round((float) ($ca->adjustment_amount ?? 0), 2);
        $total = $ca->repayable();
        $paid = round((float) $ca->payments->sum('amount'), 2);
        // Interest first, then principal
        $interestPaid = round(min($paid, max(0, $interest + max(0, $adjustment))), 2);
        $principalPaid = round($paid - $interestPaid, 2);
        $date = $ca->application_date?->toDateString() ?? $ca->created_at?->toDateString();
        $firstWeek = $ca->deductions->min(fn ($d) => (int) $d->week_no);

        return [
            'id' => $ca->id,
            'ca_number' => $ca->ca_number,
            'crop_year' => (string) $ca->crop_year,
            'planter_key' => PlanterProfileSync::key($ca->planter_code),
            'planter_sort' => mb_strtoupper(trim((string) $ca->planter_name)),
            'planter_code' => (string) $ca->planter_code,
            'planter_name' => (string) $ca->planter_name,
            'date' => $date,
            'week_no' => $firstWeek ?: null,
            'weeks' => $ca->deductions->pluck('week_no')->map(fn ($w) => (int) $w)->sort()->values()->all(),
            'principal' => round((float) $ca->amount, 2),
            'rate' => round((float) $ca->interest_rate, 2),
            'interest' => $interest,
            'adjustment' => $adjustment,
            'total' => $total,
            'principal_paid' => $principalPaid,
            'interest_paid' => $interestPaid,
            'paid' => $paid,
            'balance' => round($total - $paid, 2),
            'status' => $ca->status,
            'check_no' => $ca->check_no,
            'created_by' => $ca->created_by,
            'created_by_name' => $ca->createdByUser ? trim($ca->createdByUser->fname . ' ' . $ca->createdByUser->lname) : null,
            'created_at' => $ca->created_at?->format('Y-m-d H:i'),
            'payments' => $ca->payments->map(fn (CashAdvancePayment $p) => [
                'paid_date' => $p->paid_date?->toDateString(),
                'week_no' => $p->week_no,
                'amount' => round((float) $p->amount, 2),
                'method' => $p->payment_method === 'cash' ? 'Cash (OTC)' : 'Voucher',
                'receipt_no' => $p->receipt_no,
            ])->values()->all(),
        ];
    }

    private function sum(Collection $rows): array
    {
        $s = fn ($k) => round((float) $rows->sum($k), 2);
        return ['count' => $rows->count(), 'principal' => $s('principal'), 'interest' => $s('interest'), 'adjustment' => $s('adjustment'), 'total' => $s('total'),
            'principal_paid' => $s('principal_paid'), 'interest_paid' => $s('interest_paid'), 'paid' => $s('paid'), 'balance' => $s('balance')];
    }
}
