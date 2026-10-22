<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanPayment extends Model
{
    protected $table = 'loan_payments';

    protected $fillable = [
        'loan_id',
        'month',
        'amount',
        'paid_date',
        'reference',
        'remarks',
        'recorded_by',
    ];

    protected $casts = [
        'paid_date' => 'date',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function recordedByUser()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
