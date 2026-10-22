<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_number', 'planter_code', 'planter_name', 'loan_type_id',
        'principal_amount', 'interest_rate', 'term_months',
        'monthly_amortization', 'total_amount', 'balance',
        'status', 'application_date', 'approved_date', 'approved_by',
        'start_date', 'end_date', 'purpose', 'remarks',
        'crop_year', 'created_by'
    ];

    protected $casts = [
        'application_date' => 'date',
        'approved_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function loanType()
    {
        return $this->belongsTo(LoanType::class);
    }

    public function attachments()
    {
        return $this->hasMany(LoanAttachment::class);
    }

    public function amortizations()
    {
        return $this->hasMany(LoanAmortization::class);
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pendingAmortizations()
    {
        return $this->amortizations()->where('status', 'pending');
    }

    public function payments()
    {
        return $this->hasMany(LoanPayment::class)->orderBy('paid_date')->orderBy('id');
    }

    /**
     * Monthly repayment schedule: each schedule month with what was paid against it
     */
    public function monthlyRows()
    {
        $paid = $this->payments()->get()->groupBy('month')
            ->map(fn ($rows) => (int) round($rows->sum('amount') * 100));
        $today = now()->toDateString();

        return $this->amortizations()->orderBy('due_date')->get()->map(function ($a) use ($paid, $today) {
            $month = $a->due_date->format('Y-m');
            $dueCents = (int) round($a->amount_due * 100);
            $paidCents = $paid[$month] ?? 0;
            $remaining = max(0, $dueCents - $paidCents) / 100;
            $due = $a->due_date->toDateString();

            $status = match (true) {
                $remaining <= 0 => 'paid',
                $paidCents > 0 => 'partial',
                $due < $today && $this->status === 'active' => 'overdue',
                default => 'upcoming',
            };

            return [
                'month' => $month,
                'due_date' => $due,
                'amount' => $dueCents / 100,
                'paid' => $paidCents / 100,
                'remaining' => $remaining,
                'status' => $status,
            ];
        })->values();
    }

    /**
     * Installments and balance follow the payments: each month's paid amount and status, the loan
     * balance, and completed when fully paid (back to active on undo)
     */
    public function syncBalance(): void
    {
        $payments = $this->payments()->get()->groupBy('month');
        $paidTotal = 0;

        foreach ($this->amortizations()->get() as $a) {
            $monthPayments = $payments[$a->due_date->format('Y-m')] ?? collect();
            $paidCents = (int) round($monthPayments->sum('amount') * 100);
            $paidTotal += $paidCents;
            $a->amount_paid = $paidCents / 100;
            $a->paid_date = $monthPayments->max('paid_date');
            $a->status = $paidCents >= (int) round($a->amount_due * 100) ? 'paid'
                : ($paidCents > 0 ? 'partial' : ($a->due_date->isPast() && $this->status === 'active' ? 'overdue' : 'pending'));
            $a->save();
        }

        $scheduled = (int) round($this->amortizations()->sum('amount_due') * 100);
        $this->balance = max(0, $scheduled - $paidTotal) / 100;

        if ($this->status === 'active' && $scheduled > 0 && $paidTotal >= $scheduled) {
            $this->status = 'completed';
        } elseif ($this->status === 'completed' && $paidTotal < $scheduled) {
            $this->status = 'active';
        }

        $this->save();
    }

    public static function generateLoanNumber()
    {
        $prefix = 'LN';
        $year = date('Y');
        $lastLoan = self::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
        $sequence = $lastLoan ? intval(substr($lastLoan->loan_number, -4)) + 1 : 1;
        return $prefix . $year . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function calculateAmortization()
    {
        $principal = $this->principal_amount;
        $monthlyRate = ($this->interest_rate / 100) / 12;
        $term = $this->term_months;

        if ($monthlyRate > 0) {
            $this->monthly_amortization = $principal * ($monthlyRate * pow(1 + $monthlyRate, $term)) / (pow(1 + $monthlyRate, $term) - 1);
        } else {
            $this->monthly_amortization = $principal / $term;
        }

        $this->total_amount = $this->monthly_amortization * $term;
        $this->balance = $this->total_amount;
    }
}