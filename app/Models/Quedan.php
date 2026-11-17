<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quedan extends Model
{
    use HasFactory;

    protected $table = 'quedans';

    protected $fillable = [
        'crop_year', 'week_no', 'planter_code', 'planter_name',
        'qdn_no', 'tin_no', 'total_liens', 'sugar_lkg', 'labor_lkg', 'user_id','status', 
        'bought_at', 'bought_by', 'sold_price', 'sold_price_type', 'quedan_price_id',

    ];

    /**
     * Rows that count on the Check Voucher and its reports: a pending row is not yet bought
     */
    public function scopeNotPending($query)
    {
        return $query->where($query->qualifyColumn('status'), '!=', 'pending');
    }

    /**
     * "Week Bought": the week of the sold price picked when the quedan was marked bought
     * (quedan_price.week_no). Quedans bought before sold prices were recorded have no price,
     * so they fall back to bought_at within the week's dates. Used by the Registry / Buy pages
     * and the Proceeds & Deductions report, so both tally.
     */
    public function scopeBoughtInWeek($query, $cropYear, $boughtWeek)
    {
        $boughtWeek = (string) $boughtWeek;
        $range = WeekNo::dateRange($cropYear, $boughtWeek);
        $priceIds = QuedanPrice::select('id')->where('crop_year', $cropYear)->where('week_no', $boughtWeek);

        return $query->where(function ($q) use ($priceIds, $range) {
            $q->whereIn($q->qualifyColumn('quedan_price_id'), $priceIds);
            if ($range) {
                $q->orWhere(fn ($old) => $old->whereNull($old->qualifyColumn('quedan_price_id'))
                    ->whereBetween($old->qualifyColumn('bought_at'), $range));
            }
        });
    }
}