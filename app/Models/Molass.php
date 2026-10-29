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
}