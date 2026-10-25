<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\CashAdvanceAmortization;

class CashAdvance extends Model
{
    use HasFactory;

    protected $table = 'cash_advances';

    protected $fillable = [
        'ca_number', 'planter_code', 'planter_name',
        'amount', 'interest_rate', 'interest_amount', 'term_months', 'term_weeks',
        'monthly_amortization', 'total_amount', 'balance',
        'status', 'application_date', 'approved_date', 'approved_by',
        'start_date', 'end_date', 'purpose', 'remarks',
        'crop_year', 'cv_no', 'check_no', 'receipt_signatories', 'created_by'
    ];

    protected $casts = [
        'application_date' => 'date',
        'approved_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'receipt_signatories' => 'array',
    ];

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function amortizations()
    {
        return $this->hasMany(CashAdvanceAmortization::class, 'cash_advance_id');
    }

    public function deductions()
    {
        return $this->hasMany(CashAdvanceDeduction::class)->orderByRaw('CAST(week_no AS UNSIGNED)');
    }

    public function planter()
    {
        return $this->belongsTo(PlanterProfile::class, 'planter_code', 'planter_code');
    }

    public function payments()
    {
        return $this->hasMany(CashAdvancePayment::class)->orderBy('paid_date')->orderBy('id');
    }

    /**
     * Weekly cash advances are repaid by their weekly schedule (deductions);
     * older ones by the monthly amortization schedule
     */
    public function isWeekly(): bool
    {
        if ($this->term_weeks !== null) {
            return true;
        }
        return $this->deductions()->exists() && !$this->amortizations()->exists();
    }

    /**
     * Weekly repayment schedule: each schedule week with what was paid against it
     */
    public function weeklyRows()
    {
        $paid = $this->payments()->get()->groupBy(fn ($p) => (string) $p->week_no)
            ->map(fn ($rows) => (int) round($rows->sum('amount') * 100));
        $today = now()->toDateString();

        return $this->deductions()->get()->map(function ($d) use ($paid, $today) {
            $dueCents = (int) round($d->amount * 100);
            $paidCents = $paid[(string) $d->week_no] ?? 0;
            $remaining = max(0, $dueCents - $paidCents) / 100;
            $due = $d->due_date?->toDateString();

            $status = match (true) {
                $remaining <= 0 => 'paid',
                $paidCents > 0 => 'partial',
                $due && $due < $today && $this->status === 'active' => 'overdue',
                default => 'upcoming',
            };

            return [
                'week_no' => (string) $d->week_no,
                'due_date' => $due,
                'amount' => $dueCents / 100,
                'paid' => $paidCents / 100,
                'remaining' => $remaining,
                'status' => $status,
            ];
        })->values();
    }

    /**
     * Balance = scheduled weeks - payments; fully paid becomes completed (and back to active on undo)
     */
    public function syncWeeklyBalance(): void
    {
        $scheduled = (int) round($this->deductions()->sum('amount') * 100);
        $paid = (int) round($this->payments()->sum('amount') * 100);

        $this->total_amount = $scheduled / 100;
        $this->balance = max(0, $scheduled - $paid) / 100;

        if ($this->status === 'active' && $scheduled > 0 && $paid >= $scheduled) {
            $this->status = 'completed';
        } elseif ($this->status === 'completed' && $paid < $scheduled) {
            $this->status = 'active';
        }

        $this->save();
    }

    public static function generateCANumber()
    {
        $prefix = 'CA';
        $year = date('Y');
        $lastCA = self::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
        $sequence = $lastCA ? intval(substr($lastCA->ca_number, -4)) + 1 : 1;
        return $prefix . $year . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Flat interest: amount x rate%, charged once
     */
    public static function flatInterest($amount, $rate): float
    {
        return round((float) $amount * (float) $rate / 100, 2);
    }

    /**
     * What the weekly schedule must add up to: the advance plus its interest
     */
    public function repayable(): float
    {
        return round((float) $this->amount + (float) $this->interest_amount, 2);
    }

    /**
     * Charges the flat interest; total and balance are amount + interest, split evenly over the months
     */
    public function calculateAmortization()
    {
        $this->interest_amount = self::flatInterest($this->amount, $this->interest_rate);
        $this->total_amount = $this->repayable();
        $this->monthly_amortization = round($this->total_amount / max(1, (int) $this->term_months), 2);
        $this->balance = $this->total_amount;
    }
}