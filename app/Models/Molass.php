<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Molass extends Model
{
    use HasFactory;

    protected $table = 'molasses';

    protected $fillable = [
        'crop_year', 'week_no', 'planter_code', 'planter_name',
        'tin_no', 'mc_no', 'mol_net', 'user_id','status', 'bought_at', 'bought_by',
    ];

    /**
     * Rows that count on the Check Voucher and its reports: a pending row is not yet bought
     */
    public function scopeNotPending($query)
    {
        return $query->where($query->qualifyColumn('status'), '!=', 'pending');
    }

    /**
     * "Week Bought": molasses whose bought_at falls inside the week (week_start_date .. week_end_date).
     * Used by the Registry / Buy pages and the Proceeds & Deductions report, so both tally.
     */
    public function scopeBoughtInWeek($query, $cropYear, $boughtWeek)
    {
        $range = WeekNo::dateRange($cropYear, $boughtWeek);

        return $range
            ? $query->whereBetween($query->qualifyColumn('bought_at'), $range)
            : $query->whereRaw('1 = 0');
    }
}