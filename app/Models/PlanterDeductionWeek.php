<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanterDeductionWeek extends Model
{
    protected $table = 'planter_deduction_weeks';

    protected $fillable = ['planter_deduction_id', 'crop_year', 'week_no', 'due_date', 'amount'];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
    ];

    public function deduction()
    {
        return $this->belongsTo(PlanterDeduction::class, 'planter_deduction_id');
    }
}
