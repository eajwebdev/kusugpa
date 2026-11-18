<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Molass extends Model
{
    use \App\Models\Concerns\NormalizesPlanterCode;

    use HasFactory;

    protected $table = 'molasses';

    protected $fillable = [
        'crop_year', 'week_no', 'planter_code', 'planter_name',
        'tin_no', 'mc_no', 'mol_net', 'user_id','status', 'bought_at', 'bought_by',
        'sold_price', 'molasses_price_id',
    ];

    /**
     * Rows that count on the Check Voucher and its reports: a pending row is not yet bought
     */
    public function scopeNotPending($query)
    {
        return $query->where($query->qualifyColumn('status'), '!=', 'pending');
    }

    /**
     * "Week Bought", like quedans: the week of the molasses price picked when it was marked bought
     * (mol_price.week_no). Molasses bought before sold prices were recorded have no price; their own
     * week is their bought week. Used by the Registry / Buy pages and the Proceeds & Deductions report.
     */
    public function scopeBoughtInWeek($query, $cropYear, $boughtWeek)
    {
        $boughtWeek = (string) (int) $boughtWeek;
        $weekValues = array_values(array_unique([$boughtWeek, str_pad($boughtWeek, 2, '0', STR_PAD_LEFT)]));
        $priceIds = MolassesPrice::select('id')->where('crop_year', $cropYear)->whereIn('week_no', $weekValues);

        return $query->where(function ($q) use ($priceIds, $weekValues) {
            $q->whereIn($q->qualifyColumn('molasses_price_id'), $priceIds)
                ->orWhere(fn ($old) => $old->whereNull($old->qualifyColumn('molasses_price_id'))
                    ->where($old->qualifyColumn('status'), 'bought')
                    ->whereIn($old->qualifyColumn('week_no'), $weekValues));
        });
    }
}