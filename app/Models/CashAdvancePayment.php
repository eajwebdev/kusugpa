<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashAdvancePayment extends Model
{
    protected $table = 'cash_advance_payments';

    protected $fillable = [
        'cash_advance_id',
        'week_no',
        'amount',
        'paid_date',
        'reference',
        'remarks',
        'recorded_by',
    ];

    protected $casts = [
        'paid_date' => 'date',
    ];

    public function cashAdvance()
    {
        return $this->belongsTo(CashAdvance::class);
    }

    public function recordedByUser()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
