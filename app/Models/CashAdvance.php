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
        'amount', 'interest_rate', 'term_months',
        'monthly_amortization', 'total_amount', 'balance',
        'status', 'application_date', 'approved_date', 'approved_by',
        'start_date', 'end_date', 'purpose', 'remarks',
        'crop_year', 'cv_no', 'check_no', 'created_by'
    ];

    protected $casts = [
        'application_date' => 'date',
        'approved_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
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

    public static function generateCANumber()
    {
        $prefix = 'CA';
        $year = date('Y');
        $lastCA = self::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
        $sequence = $lastCA ? intval(substr($lastCA->ca_number, -4)) + 1 : 1;
        return $prefix . $year . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function calculateAmortization()
    {
        $principal = $this->amount;
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