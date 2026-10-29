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
        'bought_at', 'bought_by',

    ];

    /**
     * Rows that count on the Check Voucher and its reports: a pending row is not yet bought
     */
    public function scopeNotPending($query)
    {
        return $query->where($query->qualifyColumn('status'), '!=', 'pending');
    }
}