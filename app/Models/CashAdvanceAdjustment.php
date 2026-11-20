<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One change to a cash advance's balance: a late charge (a week not paid on time, moved to a later week
 * with 1% per week late), an interest change or waiver, or another adjustment
 */
class CashAdvanceAdjustment extends Model
{
    protected $fillable = [
        'cash_advance_id', 'type', 'from_week', 'to_week', 'unpaid_amount', 'weeks_late',
        'charge_amount', 'repayable_before', 'repayable_after', 'reason', 'created_by',
    ];

    protected $casts = [
        'unpaid_amount' => 'float',
        'weeks_late' => 'integer',
        'charge_amount' => 'float',
        'repayable_before' => 'float',
        'repayable_after' => 'float',
    ];

    public function cashAdvance()
    {
        return $this->belongsTo(CashAdvance::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
