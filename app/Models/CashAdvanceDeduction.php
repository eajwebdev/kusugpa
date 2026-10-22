<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashAdvanceDeduction extends Model
{
    use HasFactory;

    protected $table = 'cash_advance_deductions';

    protected $fillable = [
        'cash_advance_id',
        'crop_year',
        'week_no',
        'due_date',
        'amount',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
    ];

    public function cashAdvance()
    {
        return $this->belongsTo(CashAdvance::class);
    }
}
